<?php
/**
 * Classic editor detector.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator\Detectors;

use Brigade\GravityFormLocator\Parser;
use Brigade\GravityFormLocator\Scan_Context;
use Brigade\GravityFormLocator\Scan_Item;

defined( 'ABSPATH' ) || exit;

/**
 * Fallback for post content no builder claims: Classic Editor shortcodes.
 */
class Classic_Detector extends Abstract_Detector {

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'classic';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Classic Editor shortcodes', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Gravity Forms shortcodes in post content that no page builder and no block markup claims, including content of other builders that store plain shortcodes.', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority() {
		return 90;
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
	 * {@inheritDoc}
	 *
	 * @param Scan_Item $item Location.
	 */
	public function claims_content( Scan_Item $item ) {
		return $item->is_post_like();
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

		$source = __( 'Shortcode in post content', 'brigade-gravity-form-locator' );

		return array_merge(
			Parser::shortcode_usages( $item->content, $source, $item, $context, 'Classic Editor' ),
			Parser::markup_usages( $item->content, __( 'Post content', 'brigade-gravity-form-locator' ), $context, 'Classic Editor' )
		);
	}
}
