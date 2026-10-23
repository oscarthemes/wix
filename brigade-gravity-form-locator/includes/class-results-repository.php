<?php
/**
 * Storage for usage records.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the usage table.
 *
 * Each scan writes rows under its own scan ID. The dashboard only reads rows of the
 * committed scan (the `bgfl_active_scan` option), so a failed or interrupted scan
 * never replaces the last successful results.
 */
class Results_Repository {

	const ACTIVE_SCAN_OPTION = 'bgfl_active_scan';

	/**
	 * Columns that can be sorted, mapped to SQL.
	 *
	 * @var array<string,string>
	 */
	const SORTABLE = array(
		'form_name'      => 'form_name',
		'form_id'        => 'form_id',
		'object_title'   => 'object_title',
		'builder'        => 'builder',
		'implementation' => 'implementation',
		'object_status'  => 'object_status',
		'scanned_at'     => 'scanned_at',
		'confidence'     => 'confidence',
	);

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bgfl_usages';
	}

	/**
	 * ID of the committed scan, 0 if none.
	 *
	 * @return int
	 */
	public static function active_scan_id() {
		return (int) get_option( self::ACTIVE_SCAN_OPTION, 0 );
	}

	/**
	 * Replaces all rows of the given locations within a scan. Idempotent, so a batch
	 * that is re-run after an interruption does not duplicate rows.
	 *
	 * @param int      $scan_id     Scan ID.
	 * @param string[] $object_keys Locations processed in this batch.
	 * @param array[]  $rows        Rows to insert.
	 */
	public static function replace_locations( $scan_id, array $object_keys, array $rows ) {
		global $wpdb;

		$table = self::table();

		foreach ( array_chunk( $object_keys, 100 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom table; placeholders built above.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE scan_id = %d AND object_key IN ({$placeholders})", array_merge( array( $scan_id ), $chunk ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		foreach ( $rows as $row ) {
			$row['scan_id'] = $scan_id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
			$wpdb->insert( $table, $row, self::formats( $row ) );
		}
	}

	/**
	 * Makes a scan the visible one and deletes rows of every other scan.
	 *
	 * @param int $scan_id Scan ID.
	 */
	public static function commit( $scan_id ) {
		global $wpdb;

		update_option( self::ACTIVE_SCAN_OPTION, (int) $scan_id, false );

		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE scan_id <> %d", $scan_id ) );
	}

	/**
	 * Deletes the rows of an uncommitted scan.
	 *
	 * @param int $scan_id Scan ID.
	 */
	public static function discard( $scan_id ) {
		global $wpdb;

		if ( (int) $scan_id === self::active_scan_id() ) {
			return;
		}

		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE scan_id = %d", $scan_id ) );
	}

	/**
	 * Deletes every row.
	 */
	public static function truncate() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		$wpdb->query( "DELETE FROM {$table}" );
		delete_option( self::ACTIVE_SCAN_OPTION );
	}

	/**
	 * Queries usage rows of the committed scan.
	 *
	 * @param array $args Filters: search, form_id, builder, implementation, status, object_type,
	 *                    confidence, missing (bool), orderby, order, per_page, page (0 = all).
	 * @return array{rows:array[],total:int}
	 */
	public static function query( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'         => '',
				'form_id'        => 0,
				'builder'        => '',
				'implementation' => '',
				'status'         => '',
				'object_type'    => '',
				'confidence'     => '',
				'missing'        => false,
				'orderby'        => 'form_name',
				'order'          => 'asc',
				'per_page'       => 20,
				'page'           => 1,
			)
		);

		list( $where, $params ) = self::where( $args );

		$table   = self::table();
		$orderby = isset( self::SORTABLE[ $args['orderby'] ] ) ? self::SORTABLE[ $args['orderby'] ] : 'form_name';
		$order   = 'desc' === strtolower( (string) $args['order'] ) ? 'DESC' : 'ASC';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; $where uses placeholders, ORDER BY is whitelisted.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) );

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}, form_id ASC, object_title ASC, id ASC";
		if ( $args['per_page'] > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = (int) $args['per_page'];
			$params[] = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		// phpcs:enable

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Builds the WHERE clause for query().
	 *
	 * @param array $args Filters.
	 * @return array{0:string,1:array}
	 */
	private static function where( array $args ) {
		global $wpdb;

		$where  = array( 'scan_id = %d' );
		$params = array( self::active_scan_id() );

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$clause   = '(form_name LIKE %s OR object_title LIKE %s OR object_url LIKE %s';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			if ( preg_match( '/^#?(\d{1,10})$/', trim( $args['search'] ), $match ) ) {
				$clause  .= ' OR form_id = %d';
				$params[] = (int) $match[1];
			}
			$where[] = $clause . ')';
		}

		$map = array(
			'builder'        => 'builder',
			'implementation' => 'implementation',
			'status'         => 'object_status',
			'object_type'    => 'object_type',
			'confidence'     => 'confidence',
		);
		foreach ( $map as $arg => $column ) {
			if ( '' !== (string) $args[ $arg ] ) {
				$where[]  = "{$column} = %s";
				$params[] = (string) $args[ $arg ];
			}
		}

		if ( $args['form_id'] ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $args['form_id'];
		}

		if ( $args['missing'] ) {
			$where[] = 'form_exists = 0';
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Distinct-location usage counts per form for the committed scan.
	 *
	 * @param bool $confirmed_only Count only confirmed and likely references.
	 * @return array<int,int> Form ID => number of distinct locations.
	 */
	public static function usage_counts( $confirmed_only = false ) {
		global $wpdb;

		$table = self::table();
		$extra = $confirmed_only ? " AND confidence <> 'possible'" : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT form_id, COUNT(DISTINCT object_key) AS locations FROM {$table} WHERE scan_id = %d{$extra} GROUP BY form_id", self::active_scan_id() ), ARRAY_A );

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['form_id'] ] = (int) $row['locations'];
		}
		return $counts;
	}

	/**
	 * Number of distinct locations that hold at least one form.
	 *
	 * @return int
	 */
	public static function distinct_locations() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT object_key) FROM {$table} WHERE scan_id = %d", self::active_scan_id() ) );
	}

	/**
	 * Distinct values of a column in the committed scan, for filter dropdowns.
	 *
	 * @param string $column One of builder, implementation, object_status, object_type, confidence.
	 * @return string[]
	 */
	public static function distinct_values( $column ) {
		global $wpdb;

		if ( ! in_array( $column, array( 'builder', 'implementation', 'object_status', 'object_type', 'confidence' ), true ) ) {
			return array();
		}

		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; column whitelisted.
		$values = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT {$column} FROM {$table} WHERE scan_id = %d ORDER BY {$column} ASC", self::active_scan_id() ) );
		return array_values( array_filter( array_map( 'strval', (array) $values ), 'strlen' ) );
	}

	/**
	 * Rows of one form, for the expandable details in the forms view.
	 *
	 * @param int[] $form_ids Form IDs.
	 * @return array<int,array[]> Form ID => rows.
	 */
	public static function rows_for_forms( array $form_ids ) {
		global $wpdb;

		$form_ids = array_filter( array_map( 'absint', $form_ids ) );
		if ( empty( $form_ids ) ) {
			return array();
		}

		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom table; placeholders built above.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE scan_id = %d AND form_id IN ({$placeholders}) ORDER BY object_title ASC, id ASC", array_merge( array( self::active_scan_id() ), $form_ids ) ), ARRAY_A );

		$grouped = array();
		foreach ( (array) $rows as $row ) {
			$grouped[ (int) $row['form_id'] ][] = $row;
		}
		return $grouped;
	}

	/**
	 * Column formats for $wpdb->insert().
	 *
	 * @param array $row Row.
	 * @return string[]
	 */
	private static function formats( array $row ) {
		$ints    = array( 'scan_id', 'form_id', 'form_exists', 'object_id', 'occurrences' );
		$formats = array();
		foreach ( array_keys( $row ) as $column ) {
			$formats[] = in_array( $column, $ints, true ) ? '%d' : '%s';
		}
		return $formats;
	}
}
