<?php
/**
 * Elementor detector.
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
 * Reads Elementor's `_elementor_data` JSON (pages, Theme Builder templates, popups,
 * saved sections and global widgets stored as `elementor_library` posts).
 */
class Elementor_Detector extends Abstract_Detector {

	const BUILDER = 'Elementor';

	/**
	 * Elementor widgets whose main setting holds raw text that may include shortcodes.
	 *
	 * @var array<string,string> Widget type => setting key.
	 */
	const TEXT_WIDGETS = array(
		'shortcode'   => 'shortcode',
		'text-editor' => 'editor',
		'html'        => 'html',
	);

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'elementor';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Elementor', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Shortcode, Text Editor and HTML widgets, shortcodes inside any widget setting, and third-party Gravity Forms widgets (form ID settings) in pages, Theme Builder templates, popups and global widgets. Pages edited with Elementor are attributed to Elementor only.', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_integration_active() {
		return defined( 'ELEMENTOR_VERSION' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority() {
		return 10;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item $item Location.
	 */
	public function supports( Scan_Item $item ) {
		return $item->is_post_like() && $this->is_built_with_elementor( $item );
	}

	/**
	 * Elementor writes a plain-text copy of the layout into post_content, so it must
	 * own the content of pages it builds to avoid reporting that copy as Classic content.
	 *
	 * @param Scan_Item $item Location.
	 */
	public function claims_content( Scan_Item $item ) {
		return $this->supports( $item );
	}

	/**
	 * Whether the post is currently edited with Elementor.
	 *
	 * @param Scan_Item $item Location.
	 * @return bool
	 */
	private function is_built_with_elementor( Scan_Item $item ) {
		if ( 'elementor_library' === $item->post_type() ) {
			return true;
		}
		return 'builder' === $item->meta( '_elementor_edit_mode' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 */
	public function detect( Scan_Item $item, Scan_Context $context ) {
		$raw = $item->meta( '_elementor_data' );

		if ( is_array( $raw ) ) {
			$elements = $raw;
		} else {
			if ( ! is_string( $raw ) || '' === $raw ) {
				return array();
			}
			$elements = json_decode( $raw, true );
			if ( ! is_array( $elements ) ) {
				$context->skipped( $item, __( 'Elementor data could not be decoded.', 'brigade-gravity-form-locator' ) );
				return array();
			}
		}

		return $this->walk( $elements, $item, $context, 0 );
	}

	/**
	 * Walks Elementor elements recursively.
	 *
	 * @param array        $elements Elements.
	 * @param Scan_Item    $item     Location.
	 * @param Scan_Context $context  Scan context.
	 * @param int          $depth    Depth.
	 * @return Usage[]
	 */
	private function walk( array $elements, Scan_Item $item, Scan_Context $context, $depth ) {
		$usages = array();

		if ( $depth > Parser::MAX_DEPTH ) {
			return $usages;
		}

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

			if ( isset( $element['elType'] ) && 'widget' === $element['elType'] && ! empty( $element['widgetType'] ) ) {
				$usages = array_merge( $usages, $this->widget_usages( (string) $element['widgetType'], $settings, $item, $context ) );
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$usages = array_merge( $usages, $this->walk( $element['elements'], $item, $context, $depth + 1 ) );
			}
		}

		return $usages;
	}

	/**
	 * Usages inside one widget.
	 *
	 * @param string       $type     Widget type.
	 * @param array        $settings Widget settings.
	 * @param Scan_Item    $item     Location.
	 * @param Scan_Context $context  Scan context.
	 * @return Usage[]
	 */
	private function widget_usages( $type, array $settings, Scan_Item $item, Scan_Context $context ) {
		$usages = array();

		if ( 'shortcode' === $type ) {
			$source = __( 'Elementor Shortcode widget', 'brigade-gravity-form-locator' );
		} elseif ( isset( self::TEXT_WIDGETS[ $type ] ) ) {
			/* translators: %s: Elementor widget type. */
			$source = sprintf( __( 'Shortcode in Elementor %s widget', 'brigade-gravity-form-locator' ), $type );
		} else {
			/* translators: %s: Elementor widget type. */
			$source = sprintf( __( 'Shortcode in Elementor %s widget settings', 'brigade-gravity-form-locator' ), $type );
		}

		$usages = Parser::nested_shortcode_usages( $settings, $source, $item, $context, self::BUILDER );

		if ( isset( self::TEXT_WIDGETS[ $type ] ) ) {
			$key = self::TEXT_WIDGETS[ $type ];
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
				$usages = array_merge( $usages, Parser::markup_usages( $settings[ $key ], $source, $context, self::BUILDER ) );
			}
		}

		// Third-party Gravity Forms widgets (Essential Addons, PowerPack, Happy Addons…) store the form ID as a setting.
		if ( Parser::name_mentions_gravity( $type ) ) {
			$form_id = Parser::find_form_id_setting( $settings );
			if ( null !== $form_id ) {
				/* translators: %s: Elementor widget type. */
				$usages[] = Usage::make( $form_id, Usage::METHOD_BUILDER_ELEMENT, sprintf( __( 'Elementor %s widget', 'brigade-gravity-form-locator' ), $type ), Usage::LIKELY, self::BUILDER );
			} elseif ( empty( $usages ) ) {
				/* translators: %s: Elementor widget type. */
				$context->unresolved( $item, sprintf( __( 'Elementor widget "%s" looks like a Gravity Forms widget but no form ID setting was recognised.', 'brigade-gravity-form-locator' ), $type ) );
			}
		}

		return $usages;
	}
}
