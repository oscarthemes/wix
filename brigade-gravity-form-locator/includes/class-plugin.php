<?php
/**
 * Plugin bootstrap.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's components.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the instance, creating it on first call.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers hooks. Admin code only loads in the admin, never on the front end.
	 */
	private function __construct() {
		Cron::init();

		if ( is_admin() ) {
			add_action( 'admin_init', array( Installer::class, 'maybe_upgrade' ) );
			Admin::init();
			Ajax::init();
			Export::init();
		}
	}

	/**
	 * Capability required to view results, run scans and change settings.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability required to use the plugin.
		 *
		 * @param string $capability Default "manage_options".
		 */
		return (string) apply_filters( 'bgfl_capability', 'manage_options' );
	}
}
