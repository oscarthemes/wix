<?php
/**
 * Usage locations table.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator\Admin;

use Brigade\GravityFormLocator\Admin as Admin_Controller;
use Brigade\GravityFormLocator\Location_Links;
use Brigade\GravityFormLocator\Results_Repository;
use Brigade\GravityFormLocator\Usage;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * One row per form per location.
 */
class Usage_Table extends \WP_List_Table {

	/**
	 * Active filters.
	 *
	 * @var array
	 */
	private $filters;

	/**
	 * Distinct-location counts per form.
	 *
	 * @var array<int,int>
	 */
	private $counts = array();

	/**
	 * Constructor.
	 *
	 * @param array $filters Filters from Admin::request_filters().
	 */
	public function __construct( array $filters ) {
		$this->filters = $filters;
		parent::__construct(
			array(
				'singular' => 'bgfl-usage',
				'plural'   => 'bgfl-usages',
				'ajax'     => false,
				'screen'   => Admin_Controller::screen_id(),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_columns() {
		return array(
			'form_name'      => __( 'Form name', 'brigade-gravity-form-locator' ),
			'form_id'        => __( 'Form ID', 'brigade-gravity-form-locator' ),
			'usage_count'    => __( 'Usage count', 'brigade-gravity-form-locator' ),
			'object_title'   => __( 'Used on', 'brigade-gravity-form-locator' ),
			'page_link'      => __( 'Page link', 'brigade-gravity-form-locator' ),
			'edit_link'      => __( 'Edit link', 'brigade-gravity-form-locator' ),
			'builder'        => __( 'Builder', 'brigade-gravity-form-locator' ),
			'implementation' => __( 'Implementation', 'brigade-gravity-form-locator' ),
			'object_status'  => __( 'Status', 'brigade-gravity-form-locator' ),
			'scanned_at'     => __( 'Last scanned', 'brigade-gravity-form-locator' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_sortable_columns() {
		return array(
			'form_name'      => array( 'form_name', false ),
			'form_id'        => array( 'form_id', false ),
			'object_title'   => array( 'object_title', false ),
			'builder'        => array( 'builder', false ),
			'implementation' => array( 'implementation', false ),
			'object_status'  => array( 'object_status', false ),
			'scanned_at'     => array( 'scanned_at', true ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_primary_column_name() {
		return 'form_name';
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'bgfl_per_page', 20 );
		$result   = Results_Repository::query(
			array_merge(
				$this->filters,
				array(
					'per_page' => $per_page,
					'page'     => $this->get_pagenum(),
				)
			)
		);

		$this->items           = $result['rows'];
		$this->counts          = Results_Repository::usage_counts();
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'form_name' );

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function no_items() {
		if ( Results_Repository::active_scan_id() ) {
			esc_html_e( 'No usage locations match these filters.', 'brigade-gravity-form-locator' );
		} else {
			esc_html_e( 'No results yet. Run a scan to find where your forms are used.', 'brigade-gravity-form-locator' );
		}
	}

	/**
	 * Filter dropdowns above the table.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$methods = Usage::method_labels();
		$builder = Results_Repository::distinct_values( 'builder' );
		$impl    = array();
		foreach ( Results_Repository::distinct_values( 'implementation' ) as $value ) {
			$impl[ $value ] = isset( $methods[ $value ] ) ? $methods[ $value ] : $value;
		}
		$status = array();
		foreach ( Results_Repository::distinct_values( 'object_status' ) as $value ) {
			$status[ $value ] = Location_Links::status_label( $value );
		}
		$types = array_intersect_key( Location_Links::object_type_labels(), array_flip( Results_Repository::distinct_values( 'object_type' ) ) );

		echo '<div class="alignleft actions bgfl-filters">';
		$this->dropdown( 'builder', __( 'All builders', 'brigade-gravity-form-locator' ), array_combine( $builder, $builder ) );
		$this->dropdown( 'implementation', __( 'All methods', 'brigade-gravity-form-locator' ), $impl );
		$this->dropdown( 'status', __( 'All statuses', 'brigade-gravity-form-locator' ), $status );
		$this->dropdown( 'object_type', __( 'All location types', 'brigade-gravity-form-locator' ), $types );
		$this->dropdown( 'confidence', __( 'Any confidence', 'brigade-gravity-form-locator' ), Usage::confidence_labels() );
		submit_button( __( 'Filter', 'brigade-gravity-form-locator' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Prints a filter dropdown.
	 *
	 * @param string $name    Query arg.
	 * @param string $all     Label of the empty option.
	 * @param array  $options Value => label.
	 */
	private function dropdown( $name, $all, array $options ) {
		$current = isset( $this->filters[ $name ] ) ? (string) $this->filters[ $name ] : '';
		$id      = 'bgfl-filter-' . $name;

		printf( '<label class="screen-reader-text" for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $all ) );
		printf( '<select name="%1$s" id="%2$s">', esc_attr( $name ), esc_attr( $id ) );
		printf( '<option value="">%s</option>', esc_html( $all ) );
		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $current, (string) $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Form name column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_form_name( $item ) {
		$label = esc_html( Location_Links::form_label( $item ) );
		if ( ! (int) $item['form_exists'] ) {
			return '<span class="bgfl-missing">' . $label . '</span>';
		}
		$url = add_query_arg(
			array(
				'page'    => Admin_Controller::SLUG,
				'form_id' => (int) $item['form_id'],
			),
			admin_url( 'admin.php' )
		);
		return sprintf( '<strong><a href="%1$s" title="%2$s">%3$s</a></strong>', esc_url( $url ), esc_attr__( 'Show only this form', 'brigade-gravity-form-locator' ), $label );
	}

	/**
	 * Form ID column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_form_id( $item ) {
		return '<code>' . (int) $item['form_id'] . '</code>';
	}

	/**
	 * Usage count column (distinct locations of this form).
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_usage_count( $item ) {
		$count = isset( $this->counts[ (int) $item['form_id'] ] ) ? $this->counts[ (int) $item['form_id'] ] : 0;
		return '<span class="bgfl-count">' . esc_html( number_format_i18n( $count ) ) . '</span>';
	}

	/**
	 * Used on column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_object_title( $item ) {
		$links = Location_Links::for_row( $item );
		$html  = '<span class="bgfl-location">' . esc_html( $item['object_title'] ) . '</span>';
		$html .= '<span class="bgfl-sub">' . esc_html( Location_Links::type_label( $item ) ) . '</span>';
		if ( $links['missing'] ) {
			$html .= '<span class="bgfl-sub bgfl-missing">' . esc_html__( 'Deleted since the last scan', 'brigade-gravity-form-locator' ) . '</span>';
		}
		return $html;
	}

	/**
	 * Page link column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_page_link( $item ) {
		$links = Location_Links::for_row( $item );
		if ( '' === $links['view'] ) {
			return '<span class="bgfl-muted" aria-label="' . esc_attr__( 'Not available', 'brigade-gravity-form-locator' ) . '">&mdash;</span>';
		}
		return sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="screen-reader-text"> %3$s</span></a>', esc_url( $links['view'] ), esc_html( $links['view_label'] ), esc_html__( '(opens in a new tab)', 'brigade-gravity-form-locator' ) );
	}

	/**
	 * Edit link column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_edit_link( $item ) {
		$links = Location_Links::for_row( $item );
		if ( '' === $links['edit'] ) {
			return '<span class="bgfl-muted" aria-label="' . esc_attr__( 'Not available', 'brigade-gravity-form-locator' ) . '">&mdash;</span>';
		}
		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $links['edit'] ), esc_html__( 'Edit', 'brigade-gravity-form-locator' ) );
	}

	/**
	 * Builder column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_builder( $item ) {
		return '<span class="bgfl-tag">' . esc_html( $item['builder'] ) . '</span>';
	}

	/**
	 * Implementation column with detection source and confidence.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_implementation( $item ) {
		$methods    = Usage::method_labels();
		$confidence = Usage::confidence_labels();
		$method     = isset( $methods[ $item['implementation'] ] ) ? $methods[ $item['implementation'] ] : $item['implementation'];
		$level      = isset( $confidence[ $item['confidence'] ] ) ? $confidence[ $item['confidence'] ] : $item['confidence'];

		$html  = esc_html( $method );
		$html .= ' <span class="bgfl-confidence bgfl-confidence--' . esc_attr( $item['confidence'] ) . '">' . esc_html( $level ) . '</span>';
		$html .= '<span class="bgfl-sub">' . esc_html( $item['source'] );
		if ( (int) $item['occurrences'] > 1 ) {
			/* translators: %s: number of identical matches. */
			$html .= ' &middot; ' . esc_html( sprintf( __( '%s identical matches', 'brigade-gravity-form-locator' ), number_format_i18n( (int) $item['occurrences'] ) ) );
		}
		return $html . '</span>';
	}

	/**
	 * Status column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_object_status( $item ) {
		return '<span class="bgfl-status bgfl-status--' . esc_attr( $item['object_status'] ) . '">' . esc_html( Location_Links::status_label( $item['object_status'] ) ) . '</span>';
	}

	/**
	 * Last scanned column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_scanned_at( $item ) {
		return esc_html( Admin_Controller::format_mysql_time( $item['scanned_at'] ) );
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}
}
