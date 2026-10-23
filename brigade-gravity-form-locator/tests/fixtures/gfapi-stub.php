<?php
/**
 * Minimal Gravity Forms API stub for tests. NOT shipped in the plugin ZIP.
 *
 * It mimics GFAPI::get_forms( $active, $trash ) for a fixed set of forms so the
 * scanner can be tested without the commercial Gravity Forms plugin. It is not a
 * substitute for testing against real Gravity Forms.
 *
 * @package Brigade\GravityFormLocator\Tests
 */

if ( class_exists( 'GFAPI' ) ) {
	return;
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Stub of GFForms.
 */
class GFForms {
	/**
	 * Version.
	 *
	 * @var string
	 */
	public static $version = 'stub';
}

/**
 * Stub of GFAPI.
 */
class GFAPI {

	/**
	 * Forms returned by the stub.
	 *
	 * @var array[]
	 */
	public static $forms = array(
		array( 'id' => 1, 'title' => 'Contact Form', 'is_active' => '1', 'is_trash' => '0' ),
		array( 'id' => 2, 'title' => 'Job Application', 'is_active' => '1', 'is_trash' => '0' ),
		array( 'id' => 3, 'title' => 'Newsletter', 'is_active' => '1', 'is_trash' => '0' ),
		array( 'id' => 4, 'title' => 'Support Request', 'is_active' => '0', 'is_trash' => '0' ),
		array( 'id' => 5, 'title' => 'Never Used', 'is_active' => '1', 'is_trash' => '0' ),
		array( 'id' => 6, 'title' => 'Old Survey', 'is_active' => '0', 'is_trash' => '1' ),
	);

	/**
	 * Mirrors GFAPI::get_forms().
	 *
	 * @param bool|null $active Active filter, null for all.
	 * @param bool      $trash  Trash filter.
	 * @return array[]
	 */
	public static function get_forms( $active = true, $trash = false ) {
		return array_values(
			array_filter(
				self::$forms,
				function ( $form ) use ( $active, $trash ) {
					if ( (bool) $form['is_trash'] !== (bool) $trash ) {
						return false;
					}
					return null === $active || (bool) $form['is_active'] === (bool) $active;
				}
			)
		);
	}
}
