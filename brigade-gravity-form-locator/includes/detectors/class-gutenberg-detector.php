<?php
/**
 * Block editor detector.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator\Detectors;

use Brigade\GravityFormLocator\Parser;
use Brigade\GravityFormLocator\Scan_Context;
use Brigade\GravityFormLocator\Scan_Item;

defined( 'ABSPATH' ) || exit;

/**
 * Parses block content with parse_blocks(): the Gravity Forms block, Shortcode blocks,
 * shortcodes inside other blocks, synced patterns and block theme templates.
 */
class Gutenberg_Detector extends Abstract_Detector {

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'gutenberg';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Block editor (Gutenberg)', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'The Gravity Forms block, Shortcode blocks, shortcodes inside any block, synced patterns (reusable blocks), and site editor templates and template parts saved in the database.', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority() {
		return 50;
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
		return $item->is_post_like() && '' !== $item->content && has_blocks( $item->content );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 */
	public function detect( Scan_Item $item, Scan_Context $context ) {
		if ( ! $this->owns( $item ) ) {
			return array();
		}
		return Parser::block_usages( parse_blocks( $item->content ), $item, $context, 'Gutenberg' );
	}
}
