<?php
/**
 * Detector contract.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator\Detectors;

use Brigade\GravityFormLocator\Scan_Context;
use Brigade\GravityFormLocator\Scan_Item;
use Brigade\GravityFormLocator\Usage;

defined( 'ABSPATH' ) || exit;

/**
 * Every detector inspects scan items and returns normalized usages.
 *
 * Register additional detectors with the `bgfl_detectors` filter.
 */
interface Detector {

	const SUPPORT_FULL    = 'supported';
	const SUPPORT_PARTIAL = 'partial';

	/**
	 * Unique detector ID (lowercase, used in settings).
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Translated name shown in the settings screen.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Translated summary of what this detector inspects and its limits.
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * SUPPORT_FULL or SUPPORT_PARTIAL.
	 *
	 * @return string
	 */
	public function get_support_level();

	/**
	 * Whether the related plugin or theme is active on this site. Null when not applicable.
	 * Detectors still scan stored data when the integration is inactive.
	 *
	 * @return bool|null
	 */
	public function is_integration_active();

	/**
	 * Whether this detector wants to inspect the item at all.
	 *
	 * @param Scan_Item $item Location.
	 * @return bool
	 */
	public function supports( Scan_Item $item );

	/**
	 * Priority when claiming the item's main content. Lower runs first.
	 *
	 * @return int
	 */
	public function get_priority();

	/**
	 * Whether this detector is responsible for parsing the item's main content.
	 * Only the first claiming detector (by priority) parses $item->content.
	 *
	 * @param Scan_Item $item Location.
	 * @return bool
	 */
	public function claims_content( Scan_Item $item );

	/**
	 * Inspects the item.
	 *
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 * @return Usage[]
	 */
	public function detect( Scan_Item $item, Scan_Context $context );
}
