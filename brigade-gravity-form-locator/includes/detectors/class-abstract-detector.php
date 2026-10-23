<?php
/**
 * Base detector.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator\Detectors;

use Brigade\GravityFormLocator\Scan_Item;

defined( 'ABSPATH' ) || exit;

/**
 * Sensible defaults for detectors.
 */
abstract class Abstract_Detector implements Detector {

	/**
	 * {@inheritDoc}
	 */
	public function get_support_level() {
		return self::SUPPORT_FULL;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_integration_active() {
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority() {
		return 100;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item $item Location.
	 */
	public function claims_content( Scan_Item $item ) {
		return false;
	}

	/**
	 * Whether this detector owns the item's main content.
	 *
	 * @param Scan_Item $item Location.
	 * @return bool
	 */
	protected function owns( Scan_Item $item ) {
		return $item->content_owner === $this->get_id();
	}
}
