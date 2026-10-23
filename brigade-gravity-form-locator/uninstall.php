<?php
/**
 * Uninstall: removes only this plugin's own table, options and events.
 *
 * Gravity Forms, entries, posts, templates and builder layouts are never touched.
 *
 * @package Brigade\GravityFormLocator
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$bgfl_options = array(
	'bgfl_settings',
	'bgfl_db_version',
	'bgfl_active_scan',
	'bgfl_scan_state',
	'bgfl_scan_step_lock',
	'bgfl_scan_counter',
	'bgfl_last_scan',
	'bgfl_history',
);

foreach ( $bgfl_options as $bgfl_option ) {
	delete_option( $bgfl_option );
}

wp_clear_scheduled_hook( 'bgfl_scheduled_scan' );
wp_clear_scheduled_hook( 'bgfl_continue_scan' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping the plugin's own table on uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bgfl_usages" );
