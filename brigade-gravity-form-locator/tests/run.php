<?php
/**
 * Test runner. Run inside a disposable WordPress install with the plugin active,
 * for example: wp eval-file wp-content/plugins/brigade-gravity-form-locator/tests/run.php
 *
 * The integration tests create and delete posts, widgets and options, so never run
 * them on a real site. Exits with a non-zero status if any assertion fails.
 *
 * @package Brigade\GravityFormLocator\Tests
 */

namespace Brigade\GravityFormLocator\Tests;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/fixtures/gfapi-stub.php';

/**
 * Tiny assertion collector.
 */
final class T {

	/**
	 * Passed assertions.
	 *
	 * @var int
	 */
	public static $passed = 0;

	/**
	 * Failure messages.
	 *
	 * @var string[]
	 */
	public static $failures = array();

	/**
	 * Current test name.
	 *
	 * @var string
	 */
	public static $current = '';

	/**
	 * Asserts a condition.
	 *
	 * @param bool   $condition Condition.
	 * @param string $message   Message.
	 */
	public static function ok( $condition, $message ) {
		if ( $condition ) {
			++self::$passed;
		} else {
			self::$failures[] = self::$current . ': ' . $message;
		}
	}

	/**
	 * Asserts equality.
	 *
	 * @param mixed  $expected Expected.
	 * @param mixed  $actual   Actual.
	 * @param string $message  Message.
	 */
	public static function same( $expected, $actual, $message ) {
		self::ok( $expected === $actual, $message . ' (expected ' . wp_json_encode( $expected ) . ', got ' . wp_json_encode( $actual ) . ')' );
	}

	/**
	 * Runs every public static test_* method of a class.
	 *
	 * @param string $class_name Class.
	 */
	public static function run( $class_name ) {
		foreach ( get_class_methods( $class_name ) as $method ) {
			if ( 0 !== strpos( $method, 'test_' ) ) {
				continue;
			}
			self::$current = $class_name . '::' . $method;
			try {
				call_user_func( array( $class_name, $method ) );
			} catch ( \Throwable $e ) {
				self::$failures[] = self::$current . ': uncaught ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
			}
		}
	}
}

// Integration assertions count exact usages, so existing form embeds would skew them.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test guard.
if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%gravityform%' OR post_content LIKE '%gform_wrapper%'" ) > 0 ) {
	\WP_CLI::error( 'The tests need a fresh WordPress site with no existing Gravity Forms embeds.' );
}

require_once __DIR__ . '/test-detectors.php';
require_once __DIR__ . '/test-scanner.php';

T::run( Detector_Tests::class );
T::run( Scanner_Tests::class );

$bgfl_summary = sprintf( '%d assertions passed, %d failed.', T::$passed, count( T::$failures ) );

if ( T::$failures ) {
	foreach ( T::$failures as $bgfl_failure ) {
		\WP_CLI::warning( $bgfl_failure );
	}
	\WP_CLI::error( $bgfl_summary );
}

\WP_CLI::success( $bgfl_summary );
