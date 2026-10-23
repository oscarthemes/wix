<?php
/**
 * Scheduled scans.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules daily or weekly scans with WP-Cron.
 */
class Cron {

	const SCHEDULED_HOOK = 'bgfl_scheduled_scan';
	const CONTINUE_HOOK  = 'bgfl_continue_scan';

	/**
	 * Seconds a cron request may spend scanning before handing over to the next request.
	 */
	const RUN_LIMIT = 20;

	/**
	 * Registers cron callbacks.
	 */
	public static function init() {
		add_action( self::SCHEDULED_HOOK, array( __CLASS__, 'run_scheduled' ) );
		add_action( self::CONTINUE_HOOK, array( __CLASS__, 'run_continue' ) );
	}

	/**
	 * Clears and recreates the recurring event to match the settings. Only one event
	 * ever exists, so changing settings cannot create duplicates.
	 *
	 * @param array $settings Settings.
	 */
	public static function reschedule( array $settings ) {
		$frequency = 'daily' === $settings['schedule_frequency'] ? 'daily' : 'weekly';
		$next      = wp_get_scheduled_event( self::SCHEDULED_HOOK );

		if ( empty( $settings['schedule_enabled'] ) ) {
			wp_clear_scheduled_hook( self::SCHEDULED_HOOK );
			return;
		}

		if ( $next && $next->schedule === $frequency ) {
			return;
		}

		wp_clear_scheduled_hook( self::SCHEDULED_HOOK );
		wp_schedule_event( time() + HOUR_IN_SECONDS, $frequency, self::SCHEDULED_HOOK );
	}

	/**
	 * Removes all of the plugin's events.
	 */
	public static function clear() {
		wp_clear_scheduled_hook( self::SCHEDULED_HOOK );
		wp_clear_scheduled_hook( self::CONTINUE_HOOK );
	}

	/**
	 * Starts a scheduled scan unless a manual scan is actively running.
	 */
	public static function run_scheduled() {
		if ( Scanner::is_running() && ! Scanner::is_interrupted() ) {
			return;
		}

		$state = Scanner::start( 'scheduled', true );
		if ( is_wp_error( $state ) ) {
			return;
		}

		self::run_continue();
	}

	/**
	 * Continues a scheduled scan; queues another single event if more work remains.
	 */
	public static function run_continue() {
		$state = Scanner::state();
		if ( ! $state || Scanner::STATUS_RUNNING !== $state['status'] ) {
			return;
		}

		Scanner::run_for( self::RUN_LIMIT );

		if ( Scanner::is_running() && ! wp_next_scheduled( self::CONTINUE_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CONTINUE_HOOK );
		}
	}
}
