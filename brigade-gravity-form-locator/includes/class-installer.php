<?php
/**
 * Activation, deactivation and database schema.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin's storage.
 */
class Installer {

	/**
	 * Runs on plugin activation.
	 */
	public static function activate() {
		self::install();
		Cron::reschedule( Settings::get() );
	}

	/**
	 * Runs on plugin deactivation. Data is kept; only scheduled events are cleared.
	 */
	public static function deactivate() {
		Cron::clear();
		delete_option( Scanner::STEP_LOCK_OPTION );
	}

	/**
	 * Creates or upgrades the table when the stored schema version is out of date.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'bgfl_db_version' ) !== BGFL_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Creates the usage table and default options.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = Results_Repository::table();
		$charset = $wpdb->get_charset_collate();

		// dbDelta requires two spaces after PRIMARY KEY and one field per line.
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL DEFAULT 0,
			record_hash char(32) NOT NULL DEFAULT '',
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			form_name varchar(255) NOT NULL DEFAULT '',
			form_exists tinyint(1) NOT NULL DEFAULT 0,
			object_key varchar(191) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(64) NOT NULL DEFAULT '',
			object_subtype varchar(100) NOT NULL DEFAULT '',
			object_title text NOT NULL,
			object_status varchar(64) NOT NULL DEFAULT '',
			object_url text NOT NULL,
			builder varchar(64) NOT NULL DEFAULT '',
			implementation varchar(191) NOT NULL DEFAULT '',
			source varchar(191) NOT NULL DEFAULT '',
			confidence varchar(16) NOT NULL DEFAULT '',
			occurrences int(10) unsigned NOT NULL DEFAULT 1,
			scanned_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY scan_record (scan_id,record_hash),
			KEY scan_form (scan_id,form_id),
			KEY scan_object (scan_id,object_key)
		) {$charset};";

		dbDelta( $sql );

		add_option( Settings::OPTION, Settings::defaults(), '', false );
		update_option( 'bgfl_db_version', BGFL_DB_VERSION, false );
	}
}
