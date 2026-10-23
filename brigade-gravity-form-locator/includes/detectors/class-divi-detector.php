<?php
/**
 * Divi detector.
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
 * Reads Divi Builder layouts: Divi 4 `et_pb_*` shortcode layouts and Divi 5
 * `wp:divi/*` block layouts, including Theme Builder templates and the Divi Library.
 */
class Divi_Detector extends Abstract_Detector {

	const BUILDER = 'Divi';

	/**
	 * Divi Library and Theme Builder post types.
	 *
	 * @var string[]
	 */
	const POST_TYPES = array( 'et_pb_layout', 'et_template', 'et_header_layout', 'et_body_layout', 'et_footer_layout' );

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'divi';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Divi Builder', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Gravity Forms shortcodes inside Divi modules (Code, Text and others) and third-party Divi modules whose name mentions Gravity Forms, in pages, Divi Library layouts and Theme Builder headers, bodies and footers. Divi 5 layouts are read through their block markup.', 'brigade-gravity-form-locator' );
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
	public function is_integration_active() {
		return defined( 'ET_BUILDER_VERSION' ) || defined( 'ET_CORE_VERSION' ) || 'Divi' === get_template();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority() {
		return 20;
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
		if ( ! $item->is_post_like() ) {
			return false;
		}
		if ( in_array( $item->post_type(), self::POST_TYPES, true ) || 'on' === $item->meta( '_et_pb_use_builder' ) ) {
			return true;
		}
		return false !== strpos( $item->content, '[et_pb_' ) || false !== strpos( $item->content, '<!-- wp:divi/' );
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

		// Divi 5 stores layouts as block markup.
		if ( false !== strpos( $item->content, '<!-- wp:divi/' ) && function_exists( 'parse_blocks' ) ) {
			return Parser::block_usages( parse_blocks( $item->content ), $item, $context, self::BUILDER );
		}

		return $this->shortcode_layout_usages( $item, $context );
	}

	/**
	 * Divi 4 shortcode layouts.
	 *
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 * @return Usage[]
	 */
	private function shortcode_layout_usages( Scan_Item $item, Scan_Context $context ) {
		$usages  = array();
		$content = $item->content;
		$outline = Parser::shortcode_outline( $content, 'et_pb_' );
		$parents = array();

		foreach ( $outline as $node ) {
			$parents[ $node['offset'] ] = $node['parent'];

			// Third-party modules, e.g. [dsm_gravity_form gravity_form_id="3"].
			if ( 0 !== strpos( $node['tag'], 'gravityform' ) && Parser::name_mentions_gravity( $node['tag'] ) ) {
				$form_id = Parser::find_form_id_setting( $node['atts'] );
				if ( null !== $form_id ) {
					/* translators: %s: Divi module shortcode tag. */
					$usages[] = Usage::make( $form_id, Usage::METHOD_BUILDER_ELEMENT, sprintf( __( 'Divi module %s', 'brigade-gravity-form-locator' ), $node['tag'] ), Usage::LIKELY, self::BUILDER );
				} else {
					/* translators: %s: Divi module shortcode tag. */
					$context->unresolved( $item, sprintf( __( 'Divi module "%s" looks like a Gravity Forms module but no form ID attribute was recognised.', 'brigade-gravity-form-locator' ), $node['tag'] ) );
				}
			}
		}

		foreach ( Parser::find_shortcodes( $content ) as $shortcode ) {
			$parent = isset( $parents[ $shortcode['offset'] ] ) ? $parents[ $shortcode['offset'] ] : '';
			$source = '' !== $parent
				/* translators: %s: Divi module shortcode tag, e.g. et_pb_code. */
				? sprintf( __( 'Shortcode in Divi %s module', 'brigade-gravity-form-locator' ), $parent )
				: __( 'Shortcode in Divi layout', 'brigade-gravity-form-locator' );

			$usage = Parser::shortcode_usage( $shortcode, $source, $item, $context, self::BUILDER );
			if ( $usage ) {
				$usages[] = $usage;
			}
		}

		return array_merge( $usages, Parser::markup_usages( $content, __( 'Divi layout', 'brigade-gravity-form-locator' ), $context, self::BUILDER ) );
	}
}
