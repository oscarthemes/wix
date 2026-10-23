<?php
/**
 * Plugin settings: storage, defaults and sanitization.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sanitizes the settings option.
 */
class Settings {

	const OPTION = 'bgfl_settings';
	const GROUP  = 'bgfl_settings_group';

	/**
	 * Post types that never hold form embeds.
	 *
	 * @var string[]
	 */
	const INTERNAL_POST_TYPES = array(
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_global_styles',
		'wp_font_family',
		'wp_font_face',
		'wp_navigation',
	);

	/**
	 * Template and reusable-content post types, scanned only when "Scan templates" is on.
	 * These are scanned even when the plugin that registers them is inactive, because
	 * their stored layouts stay in the database.
	 *
	 * @var array<string,string> Post type => builder label.
	 */
	const TEMPLATE_POST_TYPES = array(
		'wp_block'             => 'Gutenberg',
		'wp_template'          => 'Gutenberg',
		'wp_template_part'     => 'Gutenberg',
		'elementor_library'    => 'Elementor',
		'et_pb_layout'         => 'Divi',
		'et_template'          => 'Divi',
		'et_header_layout'     => 'Divi',
		'et_body_layout'       => 'Divi',
		'et_footer_layout'     => 'Divi',
		'templatera'           => 'WPBakery',
		'vc_grid_item'         => 'WPBakery',
		'fl-builder-template'  => 'Beaver Builder',
		'bricks_template'      => 'Bricks',
		'ct_template'          => 'Oxygen',
	);

	/**
	 * Post statuses that can be scanned.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'publish', 'future', 'draft', 'pending', 'private' );

	/**
	 * Cached settings for this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'schedule_enabled'    => false,
			'schedule_frequency'  => 'weekly',
			'excluded_post_types' => array(),
			'statuses'            => self::STATUSES,
			'disabled_detectors'  => array(),
			'scan_templates'      => true,
			'scan_widgets'        => true,
			'scan_theme_files'    => true,
			'possible_references' => true,
			'history_limit'       => 10,
			'batch_size'          => 25,
		);
	}

	/**
	 * Returns the current settings merged over the defaults.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Clears the request cache, e.g. after the option is updated.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Registered content post types that can be offered in the settings screen.
	 *
	 * @return \WP_Post_Type[] Keyed by post type name.
	 */
	public static function content_post_types() {
		$types = array();
		foreach ( get_post_types( array(), 'objects' ) as $name => $object ) {
			if ( in_array( $name, self::INTERNAL_POST_TYPES, true ) || isset( self::TEMPLATE_POST_TYPES[ $name ] ) ) {
				continue;
			}
			if ( ! $object->show_ui && ! $object->public ) {
				continue;
			}
			$types[ $name ] = $object;
		}
		return $types;
	}

	/**
	 * Post types the scanner should query, given a settings array.
	 *
	 * @param array $settings Settings.
	 * @return string[]
	 */
	public static function scan_post_types( array $settings ) {
		$types = array_diff( array_keys( self::content_post_types() ), (array) $settings['excluded_post_types'] );

		if ( ! empty( $settings['scan_templates'] ) ) {
			$types = array_merge( $types, array_keys( self::TEMPLATE_POST_TYPES ) );
		}

		/**
		 * Filters the post types included in a scan.
		 *
		 * @param string[] $types    Post type names.
		 * @param array    $settings Plugin settings.
		 */
		$types = apply_filters( 'bgfl_scan_post_types', array_values( array_unique( $types ) ), $settings );

		return array_values( array_filter( array_map( 'sanitize_key', (array) $types ) ) );
	}

	/**
	 * Sanitizes the settings submitted from the settings screen.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$clean    = array();

		$clean['schedule_enabled']   = ! empty( $input['schedule_enabled'] );
		$clean['schedule_frequency'] = isset( $input['schedule_frequency'] ) && in_array( $input['schedule_frequency'], array( 'daily', 'weekly' ), true )
			? $input['schedule_frequency']
			: $defaults['schedule_frequency'];

		// The form posts the post types to include; store the complement so new post types are scanned by default.
		$included = isset( $input['post_types'] ) ? array_map( 'sanitize_key', (array) $input['post_types'] ) : array();
		$clean['excluded_post_types'] = array_values( array_diff( array_keys( self::content_post_types() ), $included ) );

		$statuses          = isset( $input['statuses'] ) ? array_map( 'sanitize_key', (array) $input['statuses'] ) : array();
		$clean['statuses'] = array_values( array_intersect( self::STATUSES, $statuses ) );
		if ( empty( $clean['statuses'] ) ) {
			$clean['statuses'] = array( 'publish' );
			add_settings_error( self::OPTION, 'bgfl_statuses', __( 'At least one post status is required, so Published was selected.', 'brigade-gravity-form-locator' ), 'warning' );
		}

		$enabled                     = isset( $input['detectors'] ) ? array_map( 'sanitize_key', (array) $input['detectors'] ) : array();
		$clean['disabled_detectors'] = array_values( array_diff( array_keys( Detector_Registry::all() ), $enabled ) );

		foreach ( array( 'scan_templates', 'scan_widgets', 'scan_theme_files', 'possible_references' ) as $flag ) {
			$clean[ $flag ] = ! empty( $input[ $flag ] );
		}

		$clean['history_limit'] = isset( $input['history_limit'] ) ? min( 50, max( 0, absint( $input['history_limit'] ) ) ) : $defaults['history_limit'];
		$clean['batch_size']    = isset( $input['batch_size'] ) ? min( 200, max( 5, absint( $input['batch_size'] ) ) ) : $defaults['batch_size'];

		self::flush();
		Cron::reschedule( $clean );
		History::trim( $clean['history_limit'] );

		return $clean;
	}
}
