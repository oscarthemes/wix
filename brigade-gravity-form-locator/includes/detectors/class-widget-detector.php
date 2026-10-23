<?php
/**
 * Widget detector.
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
 * Inspects widgets assigned to widget areas: the Gravity Forms widget, block widgets,
 * Text and Custom HTML widgets, and shortcodes in any other widget's settings.
 */
class Widget_Detector extends Abstract_Detector {

	const BUILDER = 'Widgets';

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'widgets';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Widgets and sidebars', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'The Gravity Forms widget, block widgets, Text and Custom HTML widgets, and shortcodes in any widget assigned to a widget area (including inactive widgets).', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item $item Location.
	 */
	public function supports( Scan_Item $item ) {
		return Scan_Item::TYPE_WIDGET === $item->type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 */
	public function detect( Scan_Item $item, Scan_Context $context ) {
		$instance = is_array( $item->data ) ? $item->data : array();
		$base     = $item->subtype;

		if ( 'gform_widget' === $base ) {
			$form_id = isset( $instance['form_id'] ) ? (string) $instance['form_id'] : '';
			if ( '' === $form_id || '0' === $form_id ) {
				$context->unresolved( $item, __( 'Gravity Forms widget with no form selected.', 'brigade-gravity-form-locator' ) );
				return array();
			}
			return array( Usage::make( $form_id, Usage::METHOD_WIDGET, __( 'Gravity Forms widget', 'brigade-gravity-form-locator' ), Usage::CONFIRMED, self::BUILDER ) );
		}

		if ( 'block' === $base && isset( $instance['content'] ) && is_string( $instance['content'] ) ) {
			return Parser::block_usages( parse_blocks( $instance['content'] ), $item, $context, self::BUILDER );
		}

		/* translators: %s: widget type, e.g. text. */
		$source = sprintf( __( 'Shortcode in %s widget', 'brigade-gravity-form-locator' ), $base );
		$usages = Parser::nested_shortcode_usages( $instance, $source, $item, $context, self::BUILDER );

		foreach ( array( 'text', 'content' ) as $key ) {
			if ( isset( $instance[ $key ] ) && is_string( $instance[ $key ] ) ) {
				$usages = array_merge( $usages, Parser::markup_usages( $instance[ $key ], $source, $context, self::BUILDER ) );
			}
		}

		if ( Parser::name_mentions_gravity( $base ) ) {
			$form_id = Parser::find_form_id_setting( $instance );
			if ( null !== $form_id ) {
				/* translators: %s: widget type. */
				$usages[] = Usage::make( $form_id, Usage::METHOD_WIDGET, sprintf( __( 'Third-party %s widget', 'brigade-gravity-form-locator' ), $base ), Usage::LIKELY, self::BUILDER );
			}
		}

		return $usages;
	}
}
