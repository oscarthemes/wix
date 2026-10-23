<?php
/**
 * Generic post meta detector.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator\Detectors;

use Brigade\GravityFormLocator\Parser;
use Brigade\GravityFormLocator\Scan_Context;
use Brigade\GravityFormLocator\Scan_Item;
use Brigade\GravityFormLocator\Usage;

defined( 'ABSPATH' ) || exit;

/**
 * Looks for Gravity Forms references in post meta: layouts of builders that store data in
 * meta (Beaver Builder, Bricks, Oxygen, SiteOrigin, Themify, Cornerstone) and custom fields
 * (ACF and others) holding shortcodes.
 */
class Metadata_Detector extends Abstract_Detector {

	/**
	 * Meta keys of known builders.
	 *
	 * @var array<string,string> Meta key => builder label.
	 */
	const BUILDER_KEYS = array(
		'_fl_builder_data'              => 'Beaver Builder',
		'_bricks_page_content_2'        => 'Bricks',
		'_bricks_page_header_2'         => 'Bricks',
		'_bricks_page_footer_2'         => 'Bricks',
		'ct_builder_shortcodes'         => 'Oxygen',
		'_ct_builder_shortcodes'        => 'Oxygen',
		'ct_builder_json'               => 'Oxygen',
		'_ct_builder_json'              => 'Oxygen',
		'panels_data'                   => 'SiteOrigin',
		'_themify_builder_settings_json' => 'Themify',
		'_cornerstone_data'             => 'Cornerstone',
	);

	/**
	 * Meta keys and prefixes that are handled elsewhere or hold stale copies.
	 *
	 * @var string[]
	 */
	const SKIP_PREFIXES = array(
		'_elementor_',
		'_et_pb_',
		'_et_builder',
		'_wpb_',
		'_wp_',
		'_edit_',
		'_fl_builder_draft',
		'_fl_builder_history',
		'_oembed_',
		'_encloseme',
		'_pingme',
	);

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'metadata';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Post meta (other builders and custom fields)', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Shortcodes and Gravity Forms elements stored in post meta: Beaver Builder, Bricks, Oxygen, SiteOrigin, Themify and Cornerstone layouts, and custom fields such as ACF. Builder-specific elements are recognised only when their name mentions Gravity Forms.', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_support_level() {
		return self::SUPPORT_PARTIAL;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority() {
		return 40;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item $item Location.
	 */
	public function supports( Scan_Item $item ) {
		return $item->is_post_like();
	}

	/**
	 * Builders that keep the layout in meta and write only a rendered copy to post_content.
	 *
	 * @param Scan_Item $item Location.
	 */
	public function claims_content( Scan_Item $item ) {
		if ( ! $item->is_post_like() ) {
			return false;
		}
		return (bool) $item->meta( '_fl_builder_enabled' )
			|| 'bricks' === $item->meta( '_bricks_editor_mode' )
			|| '' !== (string) $item->meta( 'ct_builder_shortcodes' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 */
	public function detect( Scan_Item $item, Scan_Context $context ) {
		$usages = array();
		$meta   = get_post_meta( $item->post->ID );

		if ( ! is_array( $meta ) ) {
			return $usages;
		}

		foreach ( $meta as $key => $values ) {
			$key = (string) $key;
			if ( $this->skip_key( $key ) ) {
				continue;
			}

			foreach ( (array) $values as $raw ) {
				if ( ! is_string( $raw ) || ( false === stripos( $raw, 'gravity' ) && false === stripos( $raw, 'gform' ) ) ) {
					continue;
				}
				$builder = $this->builder_for( $key, $meta );
				$value   = $this->decode( $raw );

				/* translators: %s: meta key. */
				$source = sprintf( __( 'Shortcode in meta field "%s"', 'brigade-gravity-form-locator' ), $key );
				$usages = array_merge(
					$usages,
					Parser::nested_shortcode_usages( $value, $source, $item, $context, $builder ),
					$this->element_usages( $value, $key, $builder, 0 )
				);
			}
		}

		return $usages;
	}

	/**
	 * Whether a meta key should be ignored.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	private function skip_key( $key ) {
		foreach ( self::SKIP_PREFIXES as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Builder label for a meta key.
	 *
	 * @param string $key  Meta key.
	 * @param array  $meta All meta of the post.
	 * @return string
	 */
	private function builder_for( $key, array $meta ) {
		if ( isset( self::BUILDER_KEYS[ $key ] ) ) {
			return self::BUILDER_KEYS[ $key ];
		}
		// ACF stores a "_{field}" companion key holding the field key.
		$companion = '_' . $key;
		if ( isset( $meta[ $companion ][0] ) && is_string( $meta[ $companion ][0] ) && 0 === strpos( $meta[ $companion ][0], 'field_' ) ) {
			return 'ACF';
		}
		return 'Other';
	}

	/**
	 * Decodes a raw meta value without instantiating arbitrary classes.
	 *
	 * @param string $raw Raw meta value.
	 * @return mixed
	 */
	private function decode( $raw ) {
		if ( is_serialized( $raw ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Only plain stdClass objects are allowed, which is safer than maybe_unserialize().
			$value = @unserialize( trim( $raw ), array( 'allowed_classes' => array( 'stdClass' ) ) );
			return false === $value ? $raw : $value;
		}

		$trimmed = ltrim( $raw );
		if ( '' !== $trimmed && ( '{' === $trimmed[0] || '[' === $trimmed[0] ) && ! Parser::mentions_shortcode( substr( $trimmed, 0, 13 ) ) ) {
			$json = json_decode( $raw, true );
			if ( is_array( $json ) ) {
				return $json;
			}
		}

		return $raw;
	}

	/**
	 * Finds builder elements (arrays/objects) whose type mentions Gravity Forms and that carry a form ID.
	 *
	 * @param mixed  $value   Decoded value.
	 * @param string $key     Meta key.
	 * @param string $builder Builder label.
	 * @param int    $depth   Recursion depth.
	 * @return Usage[]
	 */
	private function element_usages( $value, $key, $builder, $depth ) {
		$usages = array();

		if ( $depth > Parser::MAX_DEPTH ) {
			return $usages;
		}
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( ! is_array( $value ) ) {
			return $usages;
		}

		foreach ( array( 'name', 'type', 'widgetType', 'module', 'element' ) as $type_key ) {
			if ( isset( $value[ $type_key ] ) && is_string( $value[ $type_key ] ) && Parser::name_mentions_gravity( $value[ $type_key ] ) ) {
				$settings = $value;
				if ( isset( $value['settings'] ) ) {
					$settings = is_object( $value['settings'] ) ? get_object_vars( $value['settings'] ) : (array) $value['settings'];
				}
				$form_id = Parser::find_form_id_setting( $settings );
				if ( null !== $form_id ) {
					/* translators: 1: element type, 2: meta key. */
					$usages[] = Usage::make( $form_id, Usage::METHOD_BUILDER_ELEMENT, sprintf( __( 'Element %1$s in meta field "%2$s"', 'brigade-gravity-form-locator' ), $value[ $type_key ], $key ), Usage::LIKELY, $builder );
				}
				break;
			}
		}

		foreach ( $value as $child ) {
			if ( is_array( $child ) || is_object( $child ) ) {
				$usages = array_merge( $usages, $this->element_usages( $child, $key, $builder, $depth + 1 ) );
			}
		}

		return $usages;
	}
}
