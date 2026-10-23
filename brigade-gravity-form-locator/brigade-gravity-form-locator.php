<?php
/**
 * Plugin Name:       Brigade Gravity Form Locator
 * Plugin URI:        https://wordpress.org/plugins/brigade-gravity-form-locator/
 * Description:       Scans your site and shows where every Gravity Forms form is used: pages, posts, templates, synced patterns, widgets, theme files and page builder layouts.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Brigade Themes
 * Author URI:        https://brigadethemes.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       brigade-gravity-form-locator
 * Domain Path:       /languages
 *
 * @package Brigade\GravityFormLocator
 */

defined( 'ABSPATH' ) || exit;

define( 'BGFL_VERSION', '1.0.0' );
define( 'BGFL_DB_VERSION', '1' );
define( 'BGFL_FILE', __FILE__ );
define( 'BGFL_PATH', plugin_dir_path( __FILE__ ) );
define( 'BGFL_URL', plugin_dir_url( __FILE__ ) );

require_once BGFL_PATH . 'includes/class-autoloader.php';

Brigade\GravityFormLocator\Autoloader::register();

register_activation_hook( __FILE__, array( 'Brigade\GravityFormLocator\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Brigade\GravityFormLocator\Installer', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Brigade\GravityFormLocator\Plugin', 'instance' ) );
