<?php
/**
 * Admin screens.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the menu, settings, assets and screen handlers.
 */
class Admin {

	const SLUG          = 'brigade-gf-locator';
	const SETTINGS_SLUG = 'brigade-gf-locator-settings';
	const RESET_ACTION  = 'bgfl_reset';

	/**
	 * Page hook suffixes, used to load assets only on this plugin's screens.
	 *
	 * @var string[]
	 */
	private static $hooks = array();

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_' . self::RESET_ACTION, array( __CLASS__, 'handle_reset' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BGFL_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Adds the admin menu.
	 */
	public static function menu() {
		$cap = Plugin::capability();

		self::$hooks[] = add_menu_page(
			__( 'Brigade Gravity Form Locator', 'brigade-gravity-form-locator' ),
			__( 'Brigade GF Locator', 'brigade-gravity-form-locator' ),
			$cap,
			self::SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-location-alt',
			81
		);

		self::$hooks[] = add_submenu_page( self::SLUG, __( 'Form usage', 'brigade-gravity-form-locator' ), __( 'Form usage', 'brigade-gravity-form-locator' ), $cap, self::SLUG, array( __CLASS__, 'render_dashboard' ) );
		self::$hooks[] = add_submenu_page( self::SLUG, __( 'Locator settings', 'brigade-gravity-form-locator' ), __( 'Settings', 'brigade-gravity-form-locator' ), $cap, self::SETTINGS_SLUG, array( __CLASS__, 'render_settings' ) );
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ), esc_html__( 'Form usage', 'brigade-gravity-form-locator' ) )
		);
		return $links;
	}

	/**
	 * Registers the settings option.
	 */
	public static function register_settings() {
		register_setting(
			Settings::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);

		// Settings are saved through options.php, which checks manage_options by default.
		$cap = Plugin::capability();
		add_filter(
			'option_page_capability_' . Settings::GROUP,
			function () use ( $cap ) {
				return $cap;
			}
		);
	}

	/**
	 * Enqueues assets on this plugin's screens only.
	 *
	 * @param string $hook Current screen hook suffix.
	 */
	public static function assets( $hook ) {
		if ( ! in_array( $hook, self::$hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'bgfl-admin', BGFL_URL . 'admin/css/admin.css', array(), BGFL_VERSION );
		wp_enqueue_script( 'bgfl-admin', BGFL_URL . 'admin/js/admin.js', array(), BGFL_VERSION, true );
		wp_localize_script(
			'bgfl-admin',
			'bgflAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Ajax::NONCE ),
				'i18n'    => array(
					'starting'      => __( 'Starting scan…', 'brigade-gravity-form-locator' ),
					'complete'      => __( 'Scan complete. Loading the new results…', 'brigade-gravity-form-locator' ),
					'unsaved'       => __( 'You have unsaved changes.', 'brigade-gravity-form-locator' ),
					'failed'        => __( 'The scan failed. Your previous results were kept.', 'brigade-gravity-form-locator' ),
					'network'       => __( 'The server did not respond. The scan can be resumed; your previous results were kept.', 'brigade-gravity-form-locator' ),
					'confirmRescan' => __( 'Discard any scan in progress and scan the whole site again?', 'brigade-gravity-form-locator' ),
					'confirmCancel' => __( 'Stop the scan? Partial results are discarded and your previous results stay visible.', 'brigade-gravity-form-locator' ),
					'confirmReset'  => __( 'Delete all scan results and history? Settings, Gravity Forms and your content are not affected.', 'brigade-gravity-form-locator' ),
					/* translators: 1: items done, 2: total items. */
					'progress'      => __( '%1$s of %2$s items', 'brigade-gravity-form-locator' ),
					'cancelled'     => __( 'Scan stopped. Your previous results are still shown.', 'brigade-gravity-form-locator' ),
					'refreshing'    => __( 'Loading results…', 'brigade-gravity-form-locator' ),
					'refreshed'     => __( 'Results updated.', 'brigade-gravity-form-locator' ),
					'loadFailed'    => __( 'The results could not be loaded. Try again.', 'brigade-gravity-form-locator' ),
					'scanLabel'     => __( 'Scan website', 'brigade-gravity-form-locator' ),
					'scanComplete'  => __( 'Scan complete. Results updated.', 'brigade-gravity-form-locator' ),
				),
			)
		);
	}

	/**
	 * Reads and sanitizes the dashboard filters from the request.
	 *
	 * @return array
	 */
	public static function request_filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters for display; exports verify their own nonce.
		$get = function ( $key ) {
			return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		};

		$filters = array(
			'search'         => $get( 's' ),
			'builder'        => $get( 'builder' ),
			'implementation' => sanitize_key( $get( 'implementation' ) ),
			'status'         => sanitize_key( $get( 'status' ) ),
			'object_type'    => sanitize_key( $get( 'object_type' ) ),
			'confidence'     => sanitize_key( $get( 'confidence' ) ),
			'form_id'        => absint( $get( 'form_id' ) ),
			'missing'        => '1' === $get( 'missing' ),
			'orderby'        => sanitize_key( $get( 'orderby' ) ),
			'order'          => 'desc' === strtolower( $get( 'order' ) ) ? 'desc' : 'asc',
		);
		// phpcs:enable

		if ( ! isset( Results_Repository::SORTABLE[ $filters['orderby'] ] ) ) {
			$filters['orderby'] = 'form_name';
		}

		return $filters;
	}

	/**
	 * Current dashboard view.
	 *
	 * @return string usages|forms
	 */
	public static function current_view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'usages';
		return in_array( $view, array( 'usages', 'forms' ), true ) ? $view : 'usages';
	}

	/**
	 * Renders the dashboard.
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'brigade-gravity-form-locator' ) );
		}

		$state                                    = Scanner::state();
		list( $summary, $view, $filters, $table ) = self::results_context();

		include BGFL_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Builds everything the results region needs from the current request.
	 *
	 * @return array{0:array|null,1:string,2:array,3:\WP_List_Table}
	 */
	private static function results_context() {
		$summary = History::last();
		$view    = self::current_view();
		$filters = self::request_filters();

		if ( 'forms' === $view ) {
			$table = new Admin\Forms_Table( $summary );
		} else {
			$table = new Admin\Usage_Table( $filters );
		}
		$table->prepare_items();

		return array( $summary, $view, $filters, $table );
	}

	/**
	 * Renders the results region for a dashboard URL query, for the AJAX endpoint.
	 *
	 * WP_List_Table reads its state (page number, sorting) from the request and builds
	 * its links from REQUEST_URI, so the dashboard's query is applied to the request
	 * before rendering. Only the dashboard's own query arguments are accepted.
	 *
	 * @param string $query Query string of the dashboard URL.
	 * @return array{html:string,lastScan:string,exportUrl:string,url:string}
	 */
	public static function render_results( $query ) {
		$allowed = array( 'view', 'usage', 's', 'builder', 'implementation', 'status', 'object_type', 'confidence', 'form_id', 'missing', 'orderby', 'order', 'paged' );
		parse_str( (string) $query, $parsed );

		$args = array( 'page' => self::SLUG );
		foreach ( $allowed as $key ) {
			if ( isset( $parsed[ $key ] ) && is_scalar( $parsed[ $key ] ) && '' !== (string) $parsed[ $key ] ) {
				$args[ $key ] = sanitize_text_field( (string) $parsed[ $key ] );
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Display-only state; the AJAX caller verified the nonce.
		$_GET     = wp_slash( $args ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$_REQUEST = $_GET; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable

		$url                    = add_query_arg( $args, admin_url( 'admin.php' ) );
		$_SERVER['REQUEST_URI'] = wp_parse_url( $url, PHP_URL_PATH ) . '?' . wp_parse_url( $url, PHP_URL_QUERY );

		list( $summary, $view, $filters, $table ) = self::results_context();

		ob_start();
		require BGFL_PATH . 'admin/views/partials/results.php';
		$html = (string) ob_get_clean();

		return array(
			'html'      => $html,
			'lastScan'  => self::last_scan_text( $summary ),
			'exportUrl' => self::export_url( $summary, $view, $filters ),
			'url'       => $url,
		);
	}

	/**
	 * CSV export URL for the current view, or '' before the first scan.
	 *
	 * @param array|null $summary Last scan summary.
	 * @param string     $view    usages|forms.
	 * @param array      $filters Filters.
	 * @return string
	 */
	public static function export_url( $summary, $view, array $filters ) {
		if ( ! $summary ) {
			return '';
		}
		return Export::url( 'usages' === $view ? $filters : array() );
	}

	/**
	 * Sentence describing the last successful scan.
	 *
	 * @param array|null $summary Last scan summary.
	 * @return string
	 */
	public static function last_scan_text( $summary ) {
		if ( ! $summary ) {
			return __( 'This site has not been scanned yet.', 'brigade-gravity-form-locator' );
		}
		return sprintf(
			/* translators: 1: date and time, 2: manual or scheduled. */
			__( 'Last successful scan: %1$s (%2$s).', 'brigade-gravity-form-locator' ),
			self::format_time( $summary['finished_at'] ),
			'scheduled' === $summary['trigger'] ? __( 'scheduled', 'brigade-gravity-form-locator' ) : __( 'manual', 'brigade-gravity-form-locator' )
		);
	}

	/**
	 * Screen ID of the dashboard, used by the list tables when rendered over AJAX.
	 *
	 * @return string
	 */
	public static function screen_id() {
		return 'toplevel_page_' . self::SLUG;
	}

	/**
	 * Renders the settings screen.
	 */
	public static function render_settings() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'brigade-gravity-form-locator' ) );
		}

		$settings   = Settings::get();
		$post_types = Settings::content_post_types();
		$detectors  = Detector_Registry::all();
		$history    = History::all();

		include BGFL_PATH . 'admin/views/settings.php';
	}

	/**
	 * Deletes this plugin's scan data after explicit confirmation.
	 */
	public static function handle_reset() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to reset this data.', 'brigade-gravity-form-locator' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::RESET_ACTION );

		$redirect = admin_url( 'admin.php?page=' . self::SETTINGS_SLUG );

		if ( empty( $_POST['bgfl_confirm_reset'] ) ) {
			wp_safe_redirect( add_query_arg( 'bgfl_notice', 'reset_unconfirmed', $redirect ) );
			exit;
		}

		self::reset_data();

		wp_safe_redirect( add_query_arg( 'bgfl_notice', 'reset_done', $redirect ) );
		exit;
	}

	/**
	 * Removes scan results, state and history. Settings, forms and content are untouched.
	 */
	public static function reset_data() {
		Scanner::cancel();
		Results_Repository::truncate();
		History::clear();
		wp_clear_scheduled_hook( Cron::CONTINUE_HOOK );
	}

	/**
	 * Formats a Unix timestamp in the site's timezone.
	 *
	 * @param int $timestamp Timestamp.
	 * @return string
	 */
	public static function format_time( $timestamp ) {
		if ( ! $timestamp ) {
			return __( 'Never', 'brigade-gravity-form-locator' );
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp );
	}

	/**
	 * Formats a stored UTC MySQL datetime in the site's timezone.
	 *
	 * @param string $datetime Datetime (UTC).
	 * @return string
	 */
	public static function format_mysql_time( $datetime ) {
		$timestamp = strtotime( $datetime . ' UTC' );
		return $timestamp ? self::format_time( $timestamp ) : '';
	}
}
