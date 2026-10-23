<?php
/**
 * CSV export.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Streams the usage records of the committed scan as CSV.
 */
class Export {

	const ACTION = 'bgfl_export';

	/**
	 * Registers the admin-post handler.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Export URL carrying the current filters.
	 *
	 * @param array $filters Filters from the dashboard.
	 * @return string
	 */
	public static function url( array $filters ) {
		$args = array_merge( array_filter( $filters ), array( 'action' => self::ACTION ) );
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::ACTION );
	}

	/**
	 * Sends the CSV.
	 */
	public static function handle() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to export this data.', 'brigade-gravity-form-locator' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		$filters             = Admin::request_filters();
		$filters['per_page'] = 0;
		$result              = Results_Repository::query( $filters );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=gravity-forms-usage-' . gmdate( 'Y-m-d' ) . '.csv' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Writing to the output stream.
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM so spreadsheet apps detect the encoding.

		foreach ( self::lines( $result['rows'] ) as $line ) {
			fputcsv( $out, $line, ',', '"', '\\' );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Converts rows to CSV lines, header first.
	 *
	 * @param array[] $rows Usage rows.
	 * @return array[]
	 */
	public static function lines( array $rows ) {
		$methods    = Usage::method_labels();
		$confidence = Usage::confidence_labels();

		$lines = array(
			array(
				__( 'Form name', 'brigade-gravity-form-locator' ),
				__( 'Form ID', 'brigade-gravity-form-locator' ),
				__( 'Object title', 'brigade-gravity-form-locator' ),
				__( 'Object type', 'brigade-gravity-form-locator' ),
				__( 'Object status', 'brigade-gravity-form-locator' ),
				__( 'Page or template URL', 'brigade-gravity-form-locator' ),
				__( 'Edit URL', 'brigade-gravity-form-locator' ),
				__( 'Builder', 'brigade-gravity-form-locator' ),
				__( 'Implementation method', 'brigade-gravity-form-locator' ),
				__( 'Detection source', 'brigade-gravity-form-locator' ),
				__( 'Confidence level', 'brigade-gravity-form-locator' ),
				__( 'Occurrences', 'brigade-gravity-form-locator' ),
				__( 'Last scanned (UTC)', 'brigade-gravity-form-locator' ),
			),
		);

		foreach ( $rows as $row ) {
			$links   = Location_Links::for_row( $row );
			$lines[] = array_map(
				array( __CLASS__, 'safe_cell' ),
				array(
					Location_Links::form_label( $row ),
					(string) $row['form_id'],
					$row['object_title'],
					Location_Links::type_label( $row ),
					Location_Links::status_label( $row['object_status'] ),
					'' !== $links['view'] ? $links['view'] : $row['object_url'],
					$links['edit'],
					$row['builder'],
					isset( $methods[ $row['implementation'] ] ) ? $methods[ $row['implementation'] ] : $row['implementation'],
					$row['source'],
					isset( $confidence[ $row['confidence'] ] ) ? $confidence[ $row['confidence'] ] : $row['confidence'],
					(string) $row['occurrences'],
					$row['scanned_at'],
				)
			);
		}

		return $lines;
	}

	/**
	 * Neutralises spreadsheet formulas (CSV injection): cells starting with
	 * =, +, -, @, tab or carriage return are prefixed with an apostrophe.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function safe_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}
