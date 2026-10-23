<?php
/**
 * AJAX endpoints for running scans from the dashboard.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Every endpoint checks the capability and the nonce before doing anything.
 */
class Ajax {

	const NONCE = 'bgfl_scan';

	/**
	 * Seconds of work per step request.
	 */
	const STEP_BUDGET = 5.0;

	/**
	 * Registers handlers (logged-in users only; there are no nopriv handlers).
	 */
	public static function init() {
		add_action( 'wp_ajax_bgfl_scan_start', array( __CLASS__, 'start' ) );
		add_action( 'wp_ajax_bgfl_scan_step', array( __CLASS__, 'step' ) );
		add_action( 'wp_ajax_bgfl_scan_status', array( __CLASS__, 'status' ) );
		add_action( 'wp_ajax_bgfl_scan_cancel', array( __CLASS__, 'cancel' ) );
	}

	/**
	 * Rejects unauthorized requests.
	 */
	private static function authorize() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to run scans.', 'brigade-gravity-form-locator' ) ), 403 );
		}
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Reload the page and try again.', 'brigade-gravity-form-locator' ) ), 403 );
		}
	}

	/**
	 * Starts, resumes or restarts a scan.
	 */
	public static function start() {
		self::authorize();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorize().
		$mode  = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'scan';
		$state = Scanner::state();

		if ( 'rescan' !== $mode && $state && Scanner::STATUS_RUNNING === $state['status'] ) {
			// "Scan website" and "Resume" attach to the scan in progress.
			wp_send_json_success( Scanner::progress( $state ) );
		}

		$state = Scanner::start( 'manual', 'rescan' === $mode );
		self::respond( $state );
	}

	/**
	 * Runs one step.
	 */
	public static function step() {
		self::authorize();
		$state = Scanner::step( self::STEP_BUDGET );

		if ( is_wp_error( $state ) && 'bgfl_scan_busy' === $state->get_error_code() ) {
			wp_send_json_success( array_merge( Scanner::progress( Scanner::state() ), array( 'busy' => true ) ) );
		}

		self::respond( $state );
	}

	/**
	 * Returns the progress of the current scan.
	 */
	public static function status() {
		self::authorize();
		$state = Scanner::state();
		if ( ! $state ) {
			wp_send_json_success( array( 'status' => 'idle' ) );
		}
		wp_send_json_success( Scanner::progress( $state ) );
	}

	/**
	 * Cancels the scan in progress.
	 */
	public static function cancel() {
		self::authorize();
		Scanner::cancel();
		wp_send_json_success( array( 'status' => 'idle' ) );
	}

	/**
	 * Sends a state or an error.
	 *
	 * @param array|\WP_Error $state State.
	 */
	private static function respond( $state ) {
		if ( is_wp_error( $state ) ) {
			wp_send_json_error( array( 'message' => $state->get_error_message() ), 409 );
		}
		wp_send_json_success( Scanner::progress( $state ) );
	}
}
