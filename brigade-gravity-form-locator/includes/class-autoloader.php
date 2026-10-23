<?php
/**
 * Class autoloader.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Maps plugin classes to files following the WordPress file naming convention.
 *
 * Brigade\GravityFormLocator\Results_Repository      -> includes/class-results-repository.php
 * Brigade\GravityFormLocator\Detectors\Detector      -> includes/detectors/interface-detector.php
 * Brigade\GravityFormLocator\Detectors\Divi_Detector -> includes/detectors/class-divi-detector.php
 */
class Autoloader {

	/**
	 * Interfaces live in interface-*.php files.
	 *
	 * @var string[]
	 */
	const INTERFACES = array( 'Detector' );

	/**
	 * Registers the autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Loads a class file.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public static function load( $class_name ) {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$name  = array_pop( $parts );
		$dirs  = array_map( 'strtolower', $parts );
		$type  = in_array( $name, self::INTERFACES, true ) ? 'interface' : 'class';
		$file  = $type . '-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		$path  = BGFL_PATH . 'includes/' . ( $dirs ? implode( '/', $dirs ) . '/' : '' ) . $file;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
