<?php
/**
 * Batched, resumable site scanner.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Runs scans in small steps so large sites never hit PHP time or memory limits.
 *
 * Scan lifecycle:
 * 1. start()  snapshots settings and registered forms and stores a scan state.
 * 2. step()   processes batches (posts, widgets, builder options, theme files) until a
 *             time budget is used. Each batch replaces only its own rows, so re-running
 *             a batch after an interruption is safe.
 * 3. finalize commits the new scan ID. Until then the dashboard keeps showing the
 *    previous successful scan.
 */
class Scanner {

	const STATE_OPTION     = 'bgfl_scan_state';
	const STEP_LOCK_OPTION = 'bgfl_scan_step_lock';
	const COUNTER_OPTION   = 'bgfl_scan_counter';

	const STATUS_RUNNING  = 'running';
	const STATUS_COMPLETE = 'complete';
	const STATUS_FAILED   = 'failed';

	/**
	 * Seconds without progress after which a running scan counts as interrupted.
	 */
	const STALE_AFTER = 300;

	/**
	 * Seconds after which a step lock is considered abandoned.
	 */
	const LOCK_TTL = 120;

	/**
	 * Theme files: maximum number of files and bytes per file.
	 */
	const MAX_FILES     = 3000;
	const MAX_FILE_SIZE = 1048576;
	const FILES_PER_UNIT = 40;

	/**
	 * Phases in order.
	 *
	 * @var string[]
	 */
	const PHASES = array( 'posts', 'widgets', 'options', 'theme_files', 'finalize' );

	/**
	 * Returns the stored scan state, or null.
	 *
	 * @return array|null
	 */
	public static function state() {
		$state = get_option( self::STATE_OPTION );
		return is_array( $state ) ? $state : null;
	}

	/**
	 * Whether a scan is in progress (possibly interrupted).
	 *
	 * @return bool
	 */
	public static function is_running() {
		$state = self::state();
		return $state && self::STATUS_RUNNING === $state['status'];
	}

	/**
	 * Whether a running scan has stopped making progress.
	 *
	 * @param array|null $state State.
	 * @return bool
	 */
	public static function is_interrupted( $state = null ) {
		$state = null === $state ? self::state() : $state;
		return $state && self::STATUS_RUNNING === $state['status'] && ( time() - (int) $state['updated_at'] ) > self::STALE_AFTER;
	}

	/**
	 * Starts a new scan.
	 *
	 * @param string $trigger "manual" or "scheduled".
	 * @param bool   $restart Discard a scan already in progress instead of refusing.
	 * @return array|\WP_Error New state.
	 */
	public static function start( $trigger = 'manual', $restart = false ) {
		$current = self::state();

		if ( $current && self::STATUS_RUNNING === $current['status'] ) {
			if ( ! $restart ) {
				return new \WP_Error( 'bgfl_scan_running', __( 'A scan is already in progress.', 'brigade-gravity-form-locator' ) );
			}
			Results_Repository::discard( (int) $current['scan_id'] );
		}

		$settings               = Settings::get();
		$settings['post_types'] = Settings::scan_post_types( $settings );

		$scan_id = (int) get_option( self::COUNTER_OPTION, 0 ) + 1;
		update_option( self::COUNTER_OPTION, $scan_id, false );
		Results_Repository::discard( $scan_id );

		$forms = Forms::all();

		$state = array(
			'scan_id'      => $scan_id,
			'status'       => self::STATUS_RUNNING,
			'trigger'      => 'scheduled' === $trigger ? 'scheduled' : 'manual',
			'started_at'   => time(),
			'updated_at'   => time(),
			'phase'        => 'posts',
			'cursor'       => 0,
			'settings'     => $settings,
			'gf_available' => Forms::is_available(),
			'gf_version'   => Forms::version(),
			'forms'        => $forms,
			'files'        => array(),
			'totals'       => array(
				'posts'       => self::count_posts( $settings ),
				'widgets'     => count( self::widget_ids( $settings ) ),
				'options'     => self::count_option_items( $settings ),
				'theme_files' => 0,
			),
			'counts'       => array(
				'posts'       => 0,
				'widgets'     => 0,
				'options'     => 0,
				'theme_files' => 0,
				'skipped'     => 0,
			),
			'notes'        => array(),
			'error'        => '',
		);

		if ( ! empty( $settings['scan_theme_files'] ) && isset( Detector_Registry::enabled( $settings )['theme_files'] ) ) {
			$state['files']                 = self::theme_files( $state );
			$state['totals']['theme_files'] = count( $state['files'] );
		}

		self::save( $state );

		return $state;
	}

	/**
	 * Processes the scan for up to $budget seconds.
	 *
	 * @param float $budget Seconds.
	 * @return array|\WP_Error Updated state.
	 */
	public static function step( $budget = 5.0 ) {
		if ( ! self::acquire_lock() ) {
			return new \WP_Error( 'bgfl_scan_busy', __( 'Another scan step is still running.', 'brigade-gravity-form-locator' ) );
		}

		$state = self::state();

		try {
			if ( ! $state || self::STATUS_RUNNING !== $state['status'] ) {
				return $state ? $state : new \WP_Error( 'bgfl_no_scan', __( 'No scan is in progress.', 'brigade-gravity-form-locator' ) );
			}

			$deadline = microtime( true ) + max( 1.0, (float) $budget );

			do {
				$state = self::run_unit( $state );
				$state['updated_at'] = time();
				self::save( $state );
			} while ( self::STATUS_RUNNING === $state['status'] && microtime( true ) < $deadline && ! self::memory_exhausted() );

			return $state;
		} catch ( \Throwable $e ) {
			$state['status'] = self::STATUS_FAILED;
			/* translators: %s: error message. */
			$state['error'] = sprintf( __( 'The scan stopped because of an error: %s. Your previous results were kept.', 'brigade-gravity-form-locator' ), $e->getMessage() );
			Results_Repository::discard( (int) $state['scan_id'] );
			self::save( $state );
			return new \WP_Error( 'bgfl_scan_failed', $state['error'] );
		} finally {
			self::release_lock();
		}
	}

	/**
	 * Cancels a scan in progress and deletes its partial rows.
	 */
	public static function cancel() {
		$state = self::state();
		if ( $state ) {
			Results_Repository::discard( (int) $state['scan_id'] );
		}
		delete_option( self::STATE_OPTION );
		self::release_lock();
	}

	/**
	 * Runs a whole scan in one request, within a time limit. Used by WP-Cron and tests.
	 *
	 * @param float $limit Seconds.
	 * @return array|\WP_Error State.
	 */
	public static function run_for( $limit ) {
		$deadline = microtime( true ) + $limit;
		$state    = self::state();

		do {
			$state = self::step( min( 5.0, max( 1.0, $deadline - microtime( true ) ) ) );
		} while ( is_array( $state ) && self::STATUS_RUNNING === $state['status'] && microtime( true ) < $deadline && ! self::memory_exhausted() );

		return $state;
	}

	/**
	 * Progress information for the UI.
	 *
	 * @param array $state State.
	 * @return array
	 */
	public static function progress( array $state ) {
		$total = array_sum( $state['totals'] ) + 1;
		$done  = $state['counts']['posts'] + $state['counts']['widgets'] + $state['counts']['options'] + $state['counts']['theme_files'];
		if ( self::STATUS_COMPLETE === $state['status'] ) {
			$done = $total;
		}

		$labels = array(
			'posts'       => __( 'Scanning posts, pages and templates…', 'brigade-gravity-form-locator' ),
			'widgets'     => __( 'Scanning widgets…', 'brigade-gravity-form-locator' ),
			'options'     => __( 'Scanning saved builder templates…', 'brigade-gravity-form-locator' ),
			'theme_files' => __( 'Scanning theme files…', 'brigade-gravity-form-locator' ),
			'finalize'    => __( 'Saving results…', 'brigade-gravity-form-locator' ),
		);

		return array(
			'status'      => $state['status'],
			'phase'       => $state['phase'],
			'label'       => isset( $labels[ $state['phase'] ] ) ? $labels[ $state['phase'] ] : '',
			'percent'     => (int) min( 100, floor( $done / $total * 100 ) ),
			'done'        => min( $done, $total - 1 ),
			'total'       => $total - 1,
			'error'       => $state['error'],
			'interrupted' => self::is_interrupted( $state ),
		);
	}

	/**
	 * Runs one unit of work for the current phase.
	 *
	 * @param array $state State.
	 * @return array
	 */
	private static function run_unit( array $state ) {
		switch ( $state['phase'] ) {
			case 'posts':
				return self::scan_posts( $state );
			case 'widgets':
				return self::scan_widgets( $state );
			case 'options':
				return self::scan_options( $state );
			case 'theme_files':
				return self::scan_theme_files( $state );
			default:
				return self::finalize( $state );
		}
	}

	/**
	 * Moves to the next phase.
	 *
	 * @param array $state State.
	 * @return array
	 */
	private static function next_phase( array $state ) {
		$index           = array_search( $state['phase'], self::PHASES, true );
		$state['phase']  = self::PHASES[ min( count( self::PHASES ) - 1, (int) $index + 1 ) ];
		$state['cursor'] = 0;
		return $state;
	}

	/**
	 * SQL fragments restricting posts to the configured post types and statuses.
	 *
	 * @param array $settings Settings snapshot.
	 * @return array{0:string,1:array}|null Null when nothing should be scanned.
	 */
	private static function post_filter( array $settings ) {
		$types    = (array) $settings['post_types'];
		$statuses = (array) $settings['statuses'];

		if ( empty( $types ) || empty( $statuses ) ) {
			return null;
		}

		$sql = sprintf(
			'post_type IN (%s) AND post_status IN (%s)',
			implode( ',', array_fill( 0, count( $types ), '%s' ) ),
			implode( ',', array_fill( 0, count( $statuses ), '%s' ) )
		);

		return array( $sql, array_merge( $types, $statuses ) );
	}

	/**
	 * Number of posts the scan will inspect.
	 *
	 * @param array $settings Settings snapshot.
	 * @return int
	 */
	private static function count_posts( array $settings ) {
		global $wpdb;

		$filter = self::post_filter( $settings );
		if ( ! $filter ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders generated in post_filter().
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$filter[0]}", $filter[1] ) );
	}

	/**
	 * Scans one batch of posts.
	 *
	 * @param array $state State.
	 * @return array
	 */
	private static function scan_posts( array $state ) {
		global $wpdb;

		$settings = $state['settings'];
		$filter   = self::post_filter( $settings );

		if ( ! $filter ) {
			return self::next_phase( $state );
		}

		$batch  = max( 5, (int) $settings['batch_size'] );
		$params = array_merge( array( (int) $state['cursor'] ), $filter[1], array( $batch ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders generated in post_filter().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID > %d AND {$filter[0]} ORDER BY ID ASC LIMIT %d", $params ) );

		if ( empty( $rows ) ) {
			return self::next_phase( $state );
		}

		$ids = wp_list_pluck( $rows, 'ID' );
		update_meta_cache( 'post', $ids );

		$items = array();
		foreach ( $rows as $row ) {
			$post = new \WP_Post( $row );
			wp_cache_add( $post->ID, $row, 'posts' );
			$items[] = self::post_item( $post );
		}

		$state = self::process( $state, $items );

		$state['cursor']           = (int) max( $ids );
		$state['counts']['posts'] += count( $items );

		// Release per-batch caches on long scans.
		foreach ( $ids as $id ) {
			wp_cache_delete( $id, 'posts' );
			wp_cache_delete( $id, 'post_meta' );
		}

		return $state;
	}

	/**
	 * Builds a scan item for a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return Scan_Item
	 */
	private static function post_item( \WP_Post $post ) {
		$item          = new Scan_Item();
		$item->post    = $post;
		$item->id      = (int) $post->ID;
		$item->key     = 'post:' . $post->ID;
		$item->subtype = $post->post_type;
		$item->status  = $post->post_status;
		$item->content = (string) $post->post_content;
		$item->title   = '' !== trim( $post->post_title ) ? $post->post_title : sprintf(
			/* translators: %d: post ID. */
			__( '(no title) #%d', 'brigade-gravity-form-locator' ),
			$post->ID
		);

		if ( 'wp_block' === $post->post_type ) {
			$item->type    = Scan_Item::TYPE_PATTERN;
			$item->builder = 'Gutenberg';
		} elseif ( isset( Settings::TEMPLATE_POST_TYPES[ $post->post_type ] ) ) {
			$item->type    = Scan_Item::TYPE_TEMPLATE;
			$item->builder = Settings::TEMPLATE_POST_TYPES[ $post->post_type ];

			$kind = '';
			if ( 'elementor_library' === $post->post_type ) {
				$kind = (string) get_post_meta( $post->ID, '_elementor_template_type', true );
			} elseif ( 'wp_template_part' === $post->post_type ) {
				$area = get_the_terms( $post, 'wp_template_part_area' );
				$kind = is_array( $area ) && $area ? $area[0]->name : '';
			}
			if ( '' !== $kind ) {
				$item->subtype .= '/' . sanitize_key( $kind );
			}
		} else {
			$item->type = Scan_Item::TYPE_POST;
			if ( 'publish' === $post->post_status && is_post_type_viewable( $post->post_type ) ) {
				$item->url = (string) get_permalink( $post );
			}
		}

		return $item;
	}

	/**
	 * Widget IDs assigned to widget areas (including inactive widgets).
	 *
	 * @param array $settings Settings snapshot.
	 * @return array<string,string> Widget ID => sidebar ID.
	 */
	private static function widget_ids( array $settings ) {
		if ( empty( $settings['scan_widgets'] ) || ! isset( Detector_Registry::enabled( $settings )['widgets'] ) ) {
			return array();
		}

		$ids      = array();
		$sidebars = get_option( 'sidebars_widgets', array() );

		foreach ( (array) $sidebars as $sidebar_id => $widgets ) {
			if ( 'array_version' === $sidebar_id || ! is_array( $widgets ) ) {
				continue;
			}
			foreach ( $widgets as $widget_id ) {
				$ids[ (string) $widget_id ] = (string) $sidebar_id;
			}
		}

		return $ids;
	}

	/**
	 * Scans all widgets in one unit (sites rarely have more than a few dozen).
	 *
	 * @param array $state State.
	 * @return array
	 */
	private static function scan_widgets( array $state ) {
		global $wp_registered_sidebars, $wp_registered_widgets;

		$items     = array();
		$instances = array();

		foreach ( self::widget_ids( $state['settings'] ) as $widget_id => $sidebar_id ) {
			if ( ! preg_match( '/^(.+)-(\d+)$/', $widget_id, $match ) ) {
				continue;
			}
			list( , $base, $number ) = $match;

			if ( ! isset( $instances[ $base ] ) ) {
				$option             = get_option( 'widget_' . $base, array() );
				$instances[ $base ] = is_array( $option ) ? $option : array();
			}
			$instance = isset( $instances[ $base ][ $number ] ) ? $instances[ $base ][ $number ] : array();

			$sidebar_name = isset( $wp_registered_sidebars[ $sidebar_id ]['name'] ) ? $wp_registered_sidebars[ $sidebar_id ]['name'] : $sidebar_id;
			if ( 'wp_inactive_widgets' === $sidebar_id ) {
				$sidebar_name = __( 'Inactive widgets', 'brigade-gravity-form-locator' );
			}
			$widget_name = isset( $wp_registered_widgets[ $widget_id ]['name'] ) ? $wp_registered_widgets[ $widget_id ]['name'] : $base;
			$label       = is_array( $instance ) && ! empty( $instance['title'] ) && is_string( $instance['title'] ) ? $widget_name . ' "' . $instance['title'] . '"' : $widget_name;

			$item          = new Scan_Item();
			$item->type    = Scan_Item::TYPE_WIDGET;
			$item->key     = 'widget:' . $widget_id;
			$item->subtype = $base;
			/* translators: 1: widget name, 2: widget area name. */
			$item->title   = sprintf( __( '%1$s in %2$s', 'brigade-gravity-form-locator' ), $label, $sidebar_name );
			$item->status  = 'wp_inactive_widgets' === $sidebar_id ? 'inactive' : 'active';
			$item->builder = 'Widgets';
			$item->data    = $instance;
			$items[]       = $item;
		}

		$state                       = self::process( $state, $items );
		$state['counts']['widgets'] += count( $items );

		return self::next_phase( $state );
	}

	/**
	 * Number of stored builder option items (WPBakery saved templates).
	 *
	 * @param array $settings Settings snapshot.
	 * @return int
	 */
	private static function count_option_items( array $settings ) {
		return count( self::option_items( $settings ) );
	}

	/**
	 * Builds items for builder data kept in options.
	 *
	 * @param array $settings Settings snapshot.
	 * @return Scan_Item[]
	 */
	private static function option_items( array $settings ) {
		$items = array();

		if ( empty( $settings['scan_templates'] ) || ! isset( Detector_Registry::enabled( $settings )['wpbakery'] ) ) {
			return $items;
		}

		$templates = get_option( Detectors\WPBakery_Detector::TEMPLATES_OPTION, array() );

		foreach ( (array) $templates as $template_id => $template ) {
			if ( ! is_array( $template ) || empty( $template['template'] ) || ! is_string( $template['template'] ) ) {
				continue;
			}
			$item          = new Scan_Item();
			$item->type    = Scan_Item::TYPE_OPTION;
			$item->key     = 'option:' . Detectors\WPBakery_Detector::TEMPLATES_OPTION . ':' . sanitize_key( (string) $template_id );
			$item->subtype = Detectors\WPBakery_Detector::TEMPLATES_OPTION;
			/* translators: %s: template name. */
			$item->title   = sprintf( __( 'WPBakery saved template "%s"', 'brigade-gravity-form-locator' ), isset( $template['name'] ) ? (string) $template['name'] : $template_id );
			$item->status  = 'active';
			$item->builder = 'WPBakery';
			$item->content = $template['template'];
			$items[]       = $item;
		}

		return $items;
	}

	/**
	 * Scans builder data kept in options.
	 *
	 * @param array $state State.
	 * @return array
	 */
	private static function scan_options( array $state ) {
		$items                       = self::option_items( $state['settings'] );
		$state                       = self::process( $state, $items );
		$state['counts']['options'] += count( $items );
		return self::next_phase( $state );
	}

	/**
	 * Lists the active theme's PHP and HTML files.
	 *
	 * @param array $state State (notes are added for skipped files).
	 * @return array[] Each: theme (stylesheet slug), rel (relative path), path (absolute).
	 */
	private static function theme_files( array &$state ) {
		$roots = array( get_stylesheet() => get_stylesheet_directory() );
		if ( get_template() !== get_stylesheet() ) {
			$roots[ get_template() ] = get_template_directory();
		}

		/**
		 * Filters the theme directories scanned for template files.
		 *
		 * @param array<string,string> $roots Theme slug => absolute directory.
		 */
		$roots = (array) apply_filters( 'bgfl_theme_file_roots', $roots );

		$skip_dirs = array( 'node_modules', 'vendor', '.git', '.github', 'tests', 'test', 'bower_components', 'languages' );
		$files     = array();

		foreach ( $roots as $theme => $root ) {
			$root = untrailingslashit( wp_normalize_path( (string) $root ) );
			if ( '' === $root || ! is_dir( $root ) ) {
				continue;
			}

			$directory = new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS );
			$filter    = new \RecursiveCallbackFilterIterator(
				$directory,
				function ( $current ) use ( $skip_dirs ) {
					if ( $current->isDir() ) {
						return ! in_array( $current->getFilename(), $skip_dirs, true ) && ! $current->isLink();
					}
					return in_array( strtolower( $current->getExtension() ), array( 'php', 'html' ), true );
				}
			);
			$iterator  = new \RecursiveIteratorIterator( $filter );
			$iterator->setMaxDepth( 8 );

			foreach ( $iterator as $file ) {
				if ( count( $files ) >= self::MAX_FILES ) {
					++$state['counts']['skipped'];
					$state['notes'][] = array(
						'kind'    => Scan_Context::NOTE_SKIPPED,
						'key'     => 'file:' . $theme,
						'type'    => Scan_Item::TYPE_FILE,
						'title'   => $theme,
						/* translators: %d: maximum number of files. */
						'message' => sprintf( __( 'Theme file limit reached; only the first %d files were scanned.', 'brigade-gravity-form-locator' ), self::MAX_FILES ),
					);
					break 2;
				}
				$path    = wp_normalize_path( $file->getPathname() );
				$files[] = array(
					'theme' => sanitize_file_name( (string) $theme ),
					'rel'   => ltrim( substr( $path, strlen( $root ) ), '/' ),
					'path'  => $path,
				);
			}
		}

		return $files;
	}

	/**
	 * Scans a slice of theme files.
	 *
	 * @param array $state State.
	 * @return array
	 */
	private static function scan_theme_files( array $state ) {
		$slice = array_slice( $state['files'], (int) $state['cursor'], self::FILES_PER_UNIT );

		if ( empty( $slice ) ) {
			return self::next_phase( $state );
		}

		$context = new Scan_Context( $state['settings'] );
		$items   = array();

		foreach ( $slice as $file ) {
			$item          = new Scan_Item();
			$item->type    = Scan_Item::TYPE_FILE;
			$item->key     = 'file:' . $file['theme'] . '/' . $file['rel'];
			$item->subtype = $file['theme'] . '/' . $file['rel'];
			$item->title   = $file['theme'] . '/' . $file['rel'];
			$item->status  = 'active';
			$item->builder = 'Theme';

			$size = is_readable( $file['path'] ) ? filesize( $file['path'] ) : false;
			if ( false === $size ) {
				$context->skipped( $item, __( 'The file could not be read.', 'brigade-gravity-form-locator' ) );
				continue;
			}
			if ( $size > self::MAX_FILE_SIZE ) {
				$context->skipped( $item, __( 'The file is larger than 1 MB and was skipped.', 'brigade-gravity-form-locator' ) );
				continue;
			}

			// Local, read-only access to a theme file; the file is never executed.
			$contents      = file_get_contents( $file['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$item->content = false === $contents ? '' : $contents;
			$items[]       = $item;
		}

		$state = self::merge_notes( $state, $context );
		$state = self::process( $state, $items );

		$state['cursor']                += count( $slice );
		$state['counts']['theme_files'] += count( $slice );

		return $state;
	}

	/**
	 * Runs the detectors on items and stores the rows.
	 *
	 * @param array       $state State.
	 * @param Scan_Item[] $items Items.
	 * @return array
	 */
	private static function process( array $state, array $items ) {
		if ( empty( $items ) ) {
			return $state;
		}

		$context = new Scan_Context( $state['settings'] );
		$all     = Detector_Registry::all();
		$enabled = Detector_Registry::enabled( $state['settings'] );
		$rows    = array();
		$keys    = array();

		foreach ( $items as $item ) {
			$keys[] = $item->key;

			// Ownership is decided across all detectors, so disabling a builder detector
			// skips that builder's content instead of misreporting it as Classic content.
			foreach ( $all as $id => $detector ) {
				if ( $detector->claims_content( $item ) ) {
					$item->content_owner = $id;
					break;
				}
			}

			$usages = array();
			foreach ( $enabled as $detector ) {
				if ( ! $detector->supports( $item ) ) {
					continue;
				}
				try {
					$usages = array_merge( $usages, $detector->detect( $item, $context ) );
				} catch ( \Throwable $e ) {
					/* translators: 1: detector name, 2: error message. */
					$context->skipped( $item, sprintf( __( '%1$s detector error: %2$s', 'brigade-gravity-form-locator' ), $detector->get_label(), $e->getMessage() ) );
				}
			}

			$rows = array_merge( $rows, self::rows_for( $item, $usages, $state['forms'], $context ) );
		}

		Results_Repository::replace_locations( (int) $state['scan_id'], $keys, $rows );

		return self::merge_notes( $state, $context );
	}

	/**
	 * Converts usages into deduplicated table rows.
	 *
	 * @param Scan_Item    $item    Location.
	 * @param Usage[]      $usages  Usages.
	 * @param array        $forms   Forms snapshot.
	 * @param Scan_Context $context Scan context.
	 * @return array[]
	 */
	public static function rows_for( Scan_Item $item, array $usages, array $forms, Scan_Context $context ) {
		$rows = array();
		$now  = current_time( 'mysql', true );

		foreach ( $usages as $usage ) {
			if ( ! $usage instanceof Usage ) {
				continue;
			}

			$resolved = Forms::resolve( $usage->form_ref, $forms );
			if ( null === $resolved ) {
				/* translators: 1: form reference, 2: where it was found. */
				$context->unresolved( $item, sprintf( __( 'Form reference "%1$s" does not match any form ID or title (%2$s).', 'brigade-gravity-form-locator' ), $usage->form_ref, $usage->source ) );
				continue;
			}

			$builder = '' !== $usage->builder ? $usage->builder : ( '' !== $item->builder ? $item->builder : 'Other' );
			$source  = substr( $usage->source, 0, 191 );
			$hash    = md5( $resolved['id'] . '|' . $builder . '|' . $usage->method . '|' . $source );

			if ( isset( $rows[ $hash ] ) ) {
				$rows[ $hash ]['occurrences'] += max( 1, (int) $usage->occurrences );
				continue;
			}

			$rows[ $hash ] = array(
				'record_hash'    => $hash,
				'form_id'        => $resolved['id'],
				'form_name'      => $resolved['exists'] ? substr( $forms[ $resolved['id'] ]['title'], 0, 255 ) : '',
				'form_exists'    => $resolved['exists'] ? 1 : 0,
				'object_key'     => substr( $item->key, 0, 191 ),
				'object_id'      => (int) $item->id,
				'object_type'    => $item->type,
				'object_subtype' => substr( $item->subtype, 0, 100 ),
				'object_title'   => $item->title,
				'object_status'  => $item->status,
				'object_url'     => $item->url,
				'builder'        => substr( $builder, 0, 64 ),
				'implementation' => $usage->method,
				'source'         => $source,
				'confidence'     => $usage->confidence,
				'occurrences'    => max( 1, (int) $usage->occurrences ),
				'scanned_at'     => $now,
			);
		}

		return array_values( $rows );
	}

	/**
	 * Commits the scan and records its summary.
	 *
	 * @param array $state State.
	 * @return array
	 */
	private static function finalize( array $state ) {
		Results_Repository::commit( (int) $state['scan_id'] );

		$forms     = $state['forms'];
		$counts    = Results_Repository::usage_counts();
		$confirmed = Results_Repository::usage_counts( true );
		$used      = array_intersect_key( $confirmed, $forms );
		$possible  = array_diff_key( array_intersect_key( $counts, $forms ), $used );

		$unresolved = 0;
		foreach ( $state['notes'] as $note ) {
			if ( Scan_Context::NOTE_UNRESOLVED === $note['kind'] ) {
				++$unresolved;
			}
		}

		$summary = array(
			'scan_id'      => (int) $state['scan_id'],
			'trigger'      => $state['trigger'],
			'started_at'   => (int) $state['started_at'],
			'finished_at'  => time(),
			'gf_available' => (bool) $state['gf_available'],
			'gf_version'   => $state['gf_version'],
			'forms'        => $forms,
			'notes'        => $state['notes'],
			'stats'        => array(
				'forms_total'    => count( $forms ),
				'forms_used'     => count( $used ),
				'forms_possible' => count( $possible ),
				'forms_unused'   => max( 0, count( $forms ) - count( $used ) - count( $possible ) ),
				'forms_missing'  => count( array_diff_key( $counts, $forms ) ),
				'locations'      => Results_Repository::distinct_locations(),
				'placements'     => array_sum( $counts ),
				'posts'          => $state['counts']['posts'],
				'widgets'        => $state['counts']['widgets'],
				'options'        => $state['counts']['options'],
				'theme_files'    => $state['counts']['theme_files'],
				'skipped'        => $state['counts']['skipped'],
				'unresolved'     => $unresolved,
			),
		);

		History::record( $summary, (int) Settings::get()['history_limit'] );

		$state['status'] = self::STATUS_COMPLETE;
		$state['phase']  = 'finalize';
		$state['files']  = array();
		$state['forms']  = array();
		$state['notes']  = array();

		/**
		 * Fires after a scan has been committed.
		 *
		 * @param array $summary Scan summary.
		 */
		do_action( 'bgfl_scan_completed', $summary );

		return $state;
	}

	/**
	 * Copies notes and skip counts from a context into the state.
	 *
	 * @param array        $state   State.
	 * @param Scan_Context $context Context.
	 * @return array
	 */
	private static function merge_notes( array $state, Scan_Context $context ) {
		$state['counts']['skipped'] += $context->skipped;
		$room = Scan_Context::MAX_NOTES - count( $state['notes'] );
		if ( $room > 0 && $context->notes ) {
			$state['notes'] = array_merge( $state['notes'], array_slice( $context->notes, 0, $room ) );
		}
		$context->notes   = array();
		$context->skipped = 0;
		return $state;
	}

	/**
	 * Persists the state.
	 *
	 * @param array $state State.
	 */
	private static function save( array $state ) {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Whether memory use is close to the limit.
	 *
	 * @return bool
	 */
	private static function memory_exhausted() {
		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		return $limit > 0 && memory_get_usage( true ) > $limit * 0.8;
	}

	/**
	 * Acquires the step lock atomically (INSERT IGNORE on the unique option name).
	 *
	 * @return bool
	 */
	private static function acquire_lock() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock; the options API has no atomic insert.
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::STEP_LOCK_OPTION, (string) time() ) );

		if ( $inserted ) {
			wp_cache_delete( self::STEP_LOCK_OPTION, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the lock without the options cache.
		$locked_at = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::STEP_LOCK_OPTION ) );

		if ( $locked_at && ( time() - $locked_at ) > self::LOCK_TTL ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Take over an abandoned lock only if nobody else did.
			return (bool) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) time(), self::STEP_LOCK_OPTION, (string) $locked_at ) );
		}

		return false;
	}

	/**
	 * Releases the step lock.
	 */
	private static function release_lock() {
		delete_option( self::STEP_LOCK_OPTION );
	}
}
