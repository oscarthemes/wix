<?php
/**
 * WPBakery Page Builder detector.
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
 * Reads WPBakery shortcode layouts in posts, Templatera templates, grid items and
 * saved templates (the `wpb_js_templates` option).
 */
class WPBakery_Detector extends Abstract_Detector {

	const BUILDER = 'WPBakery';

	/**
	 * Option holding WPBakery saved templates.
	 */
	const TEMPLATES_OPTION = 'wpb_js_templates';

	/**
	 * Column shortcodes: a form shortcode placed directly in a column is WPBakery's Gravity Form element.
	 *
	 * @var string[]
	 */
	const COLUMNS = array( 'vc_column', 'vc_column_inner' );

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'wpbakery';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'WPBakery Page Builder', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'The Gravity Form element, shortcodes in Text Block and other elements, base64-encoded Raw HTML elements and third-party elements whose name mentions Gravity Forms, in pages, Templatera templates, grid items and saved templates.', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_integration_active() {
		return defined( 'WPB_VC_VERSION' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority() {
		return 30;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item $item Location.
	 */
	public function supports( Scan_Item $item ) {
		return $this->claims_content( $item );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item $item Location.
	 */
	public function claims_content( Scan_Item $item ) {
		if ( Scan_Item::TYPE_OPTION === $item->type ) {
			return self::TEMPLATES_OPTION === $item->subtype;
		}
		if ( ! $item->is_post_like() ) {
			return false;
		}
		if ( in_array( $item->post_type(), array( 'templatera', 'vc_grid_item' ), true ) || 'true' === $item->meta( '_wpb_vc_js_status' ) ) {
			return true;
		}
		return false !== strpos( $item->content, '[vc_row' ) || false !== strpos( $item->content, '[vc_section' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 */
	public function detect( Scan_Item $item, Scan_Context $context ) {
		if ( ! $this->owns( $item ) || '' === $item->content ) {
			return array();
		}

		$content = $item->content;
		$usages  = array();
		$parents = array();

		foreach ( Parser::shortcode_outline( $content, 'vc_' ) as $node ) {
			$parents[ $node['offset'] ] = $node['parent'];

			if ( 0 !== strpos( $node['tag'], 'gravityform' ) && Parser::name_mentions_gravity( $node['tag'] ) ) {
				$form_id = Parser::find_form_id_setting( $node['atts'] );
				if ( null !== $form_id ) {
					/* translators: %s: WPBakery element shortcode tag. */
					$usages[] = Usage::make( $form_id, Usage::METHOD_BUILDER_ELEMENT, sprintf( __( 'WPBakery element %s', 'brigade-gravity-form-locator' ), $node['tag'] ), Usage::LIKELY, self::BUILDER );
				}
			}
		}

		foreach ( Parser::find_shortcodes( $content ) as $shortcode ) {
			$parent = isset( $parents[ $shortcode['offset'] ] ) ? $parents[ $shortcode['offset'] ] : '';
			$usage  = Parser::shortcode_usage( $shortcode, $this->source_for( $parent ), $item, $context, self::BUILDER );

			if ( $usage ) {
				if ( in_array( $parent, self::COLUMNS, true ) ) {
					$usage->method = Usage::METHOD_BUILDER_ELEMENT;
				}
				$usages[] = $usage;
			}
		}

		$usages = array_merge( $usages, $this->raw_html_usages( $content, $item, $context ) );

		return array_merge( $usages, Parser::markup_usages( $content, __( 'WPBakery layout', 'brigade-gravity-form-locator' ), $context, self::BUILDER ) );
	}

	/**
	 * Describes a shortcode's position.
	 *
	 * @param string $ancestor Closest WPBakery ancestor.
	 * @return string
	 */
	private function source_for( $ancestor ) {
		if ( in_array( $ancestor, self::COLUMNS, true ) ) {
			return __( 'WPBakery Gravity Form element', 'brigade-gravity-form-locator' );
		}
		if ( 'vc_column_text' === $ancestor ) {
			return __( 'Shortcode in WPBakery Text Block', 'brigade-gravity-form-locator' );
		}
		if ( '' !== $ancestor ) {
			/* translators: %s: WPBakery element shortcode tag. */
			return sprintf( __( 'Shortcode in WPBakery %s element', 'brigade-gravity-form-locator' ), $ancestor );
		}
		return __( 'Shortcode in WPBakery layout', 'brigade-gravity-form-locator' );
	}

	/**
	 * WPBakery stores Raw HTML element content base64- and URL-encoded.
	 *
	 * @param string       $content Layout.
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 * @return Usage[]
	 */
	private function raw_html_usages( $content, Scan_Item $item, Scan_Context $context ) {
		if ( false === strpos( $content, '[vc_raw_html' ) ) {
			return array();
		}

		$usages = array();
		$regex  = '/' . get_shortcode_regex( array( 'vc_raw_html' ) ) . '/';
		$source = __( 'WPBakery Raw HTML element', 'brigade-gravity-form-locator' );

		if ( preg_match_all( $regex, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding WPBakery's stored Raw HTML for read-only inspection.
				$decoded = base64_decode( trim( $match[5] ), true );
				if ( false === $decoded ) {
					continue;
				}
				$html   = rawurldecode( $decoded );
				$usages = array_merge(
					$usages,
					Parser::shortcode_usages( $html, $source, $item, $context, self::BUILDER ),
					Parser::markup_usages( $html, $source, $context, self::BUILDER )
				);
			}
		}

		return $usages;
	}
}
