<?php
/**
 * Scan summaries.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the summary of the last successful scan and a short history of earlier ones.
 */
class History {

	const LAST_OPTION    = 'bgfl_last_scan';
	const HISTORY_OPTION = 'bgfl_history';

	/**
	 * Summary of the last successful scan, or null.
	 *
	 * @return array|null
	 */
	public static function last() {
		$last = get_option( self::LAST_OPTION );
		return is_array( $last ) ? $last : null;
	}

	/**
	 * Earlier scan summaries, newest first (without notes and form lists).
	 *
	 * @return array[]
	 */
	public static function all() {
		$history = get_option( self::HISTORY_OPTION, array() );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Stores a completed scan's summary.
	 *
	 * @param array $summary Summary.
	 * @param int   $limit   Number of history entries to keep.
	 */
	public static function record( array $summary, $limit ) {
		update_option( self::LAST_OPTION, $summary, false );

		if ( $limit <= 0 ) {
			delete_option( self::HISTORY_OPTION );
			return;
		}

		$entry = array_intersect_key( $summary, array_flip( array( 'scan_id', 'trigger', 'started_at', 'finished_at', 'stats', 'gf_available' ) ) );
		$all   = self::all();
		array_unshift( $all, $entry );
		update_option( self::HISTORY_OPTION, array_slice( $all, 0, $limit ), false );
	}

	/**
	 * Trims the history to a new limit.
	 *
	 * @param int $limit Entries to keep.
	 */
	public static function trim( $limit ) {
		$all = self::all();
		if ( $limit <= 0 ) {
			delete_option( self::HISTORY_OPTION );
		} elseif ( count( $all ) > $limit ) {
			update_option( self::HISTORY_OPTION, array_slice( $all, 0, $limit ), false );
		}
	}

	/**
	 * Deletes all summaries.
	 */
	public static function clear() {
		delete_option( self::LAST_OPTION );
		delete_option( self::HISTORY_OPTION );
	}
}
