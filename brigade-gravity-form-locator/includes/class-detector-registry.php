<?php
/**
 * Detector registry.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

use Brigade\GravityFormLocator\Detectors\Detector;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the available detectors.
 */
class Detector_Registry {

	/**
	 * Detector instances keyed by ID.
	 *
	 * @var Detector[]|null
	 */
	private static $detectors = null;

	/**
	 * All registered detectors, sorted by priority.
	 *
	 * @return Detector[] Keyed by detector ID.
	 */
	public static function all() {
		if ( null !== self::$detectors ) {
			return self::$detectors;
		}

		$detectors = array(
			new Detectors\Elementor_Detector(),
			new Detectors\Divi_Detector(),
			new Detectors\WPBakery_Detector(),
			new Detectors\Metadata_Detector(),
			new Detectors\Gutenberg_Detector(),
			new Detectors\Classic_Detector(),
			new Detectors\Widget_Detector(),
			new Detectors\Theme_File_Detector(),
		);

		/**
		 * Filters the detectors. Add instances implementing
		 * Brigade\GravityFormLocator\Detectors\Detector to support more builders.
		 *
		 * @param Detector[] $detectors Detector instances.
		 */
		$detectors = apply_filters( 'bgfl_detectors', $detectors );

		$sorted = array();
		foreach ( (array) $detectors as $detector ) {
			if ( $detector instanceof Detector ) {
				$sorted[ sanitize_key( $detector->get_id() ) ] = $detector;
			}
		}

		uasort(
			$sorted,
			function ( Detector $a, Detector $b ) {
				return $a->get_priority() <=> $b->get_priority();
			}
		);

		self::$detectors = $sorted;
		return self::$detectors;
	}

	/**
	 * Detectors not disabled in the given settings.
	 *
	 * @param array $settings Settings.
	 * @return Detector[]
	 */
	public static function enabled( array $settings ) {
		$disabled = (array) $settings['disabled_detectors'];
		return array_diff_key( self::all(), array_flip( $disabled ) );
	}

	/**
	 * Resets the cache (used by tests).
	 */
	public static function reset() {
		self::$detectors = null;
	}
}
