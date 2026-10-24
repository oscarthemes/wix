<?php
/**
 * Forms overview table.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator\Admin;

use Brigade\GravityFormLocator\Location_Links;
use Brigade\GravityFormLocator\Results_Repository;
use Brigade\GravityFormLocator\Usage;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * One row per registered form (plus referenced IDs that are not registered), with an
 * expandable list of every detected location.
 */
class Forms_Table extends \WP_List_Table {

	/**
	 * Last scan summary.
	 *
	 * @var array|null
	 */
	private $summary;

	/**
	 * Rows of the forms on the current page, keyed by form ID.
	 *
	 * @var array<int,array[]>
	 */
	private $locations = array();

	/**
	 * Constructor.
	 *
	 * @param array|null $summary Last scan summary.
	 */
	public function __construct( $summary ) {
		$this->summary = $summary;
		parent::__construct(
			array(
				'singular' => 'bgfl-form',
				'plural'   => 'bgfl-forms',
				'ajax'     => false,
				'screen'   => \Brigade\GravityFormLocator\Admin::screen_id(),
			)
		);
	}

	/**
	 * Usage filter for this view.
	 *
	 * @return string all|used|none|possible|missing
	 */
	public static function usage_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$value = isset( $_GET['usage'] ) ? sanitize_key( wp_unslash( $_GET['usage'] ) ) : 'all';
		return in_array( $value, array( 'all', 'used', 'none', 'possible', 'missing' ), true ) ? $value : 'all';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_columns() {
		return array(
			'form_name'   => __( 'Form name', 'brigade-gravity-form-locator' ),
			'form_id'     => __( 'Form ID', 'brigade-gravity-form-locator' ),
			'form_state'  => __( 'Form status', 'brigade-gravity-form-locator' ),
			'usage_count' => __( 'Usage count', 'brigade-gravity-form-locator' ),
			'locations'   => __( 'Detected locations', 'brigade-gravity-form-locator' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_sortable_columns() {
		return array(
			'form_name'   => array( 'form_name', false ),
			'form_id'     => array( 'form_id', false ),
			'usage_count' => array( 'usage_count', true ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_primary_column_name() {
		return 'form_name';
	}

	/**
	 * Builds all form rows (registered and unregistered).
	 *
	 * @param array|null $summary Last scan summary.
	 * @return array[]
	 */
	public static function all_rows( $summary ) {
		$forms     = $summary && isset( $summary['forms'] ) ? (array) $summary['forms'] : array();
		$counts    = Results_Repository::usage_counts();
		$confirmed = Results_Repository::usage_counts( true );
		$rows      = array();

		foreach ( $forms as $form ) {
			$id     = (int) $form['id'];
			$rows[] = array(
				'form_id'     => $id,
				'form_name'   => $form['title'],
				'exists'      => true,
				'is_active'   => ! empty( $form['is_active'] ),
				'is_trash'    => ! empty( $form['is_trash'] ),
				'usage_count' => isset( $counts[ $id ] ) ? $counts[ $id ] : 0,
				'confirmed'   => isset( $confirmed[ $id ] ) ? $confirmed[ $id ] : 0,
			);
		}

		foreach ( array_diff_key( $counts, $forms ) as $id => $count ) {
			$rows[] = array(
				'form_id'     => (int) $id,
				/* translators: %d: form ID. */
				'form_name'   => sprintf( __( 'Form #%d', 'brigade-gravity-form-locator' ), $id ),
				'exists'      => false,
				'is_active'   => false,
				'is_trash'    => false,
				'usage_count' => $count,
				'confirmed'   => isset( $confirmed[ $id ] ) ? $confirmed[ $id ] : 0,
			);
		}

		return $rows;
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		$search  = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : '';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'form_id';
		$order   = isset( $_GET['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? -1 : 1;
		// phpcs:enable

		$filter = self::usage_filter();
		$rows   = array_filter(
			self::all_rows( $this->summary ),
			function ( $row ) use ( $filter, $search ) {
				if ( 'used' === $filter && ! ( $row['exists'] && $row['confirmed'] > 0 ) ) {
					return false;
				}
				if ( 'none' === $filter && ! ( $row['exists'] && 0 === $row['usage_count'] ) ) {
					return false;
				}
				if ( 'possible' === $filter && ! ( $row['exists'] && $row['usage_count'] > 0 && 0 === $row['confirmed'] ) ) {
					return false;
				}
				if ( 'missing' === $filter && $row['exists'] ) {
					return false;
				}
				if ( '' !== $search ) {
					$id_match = preg_match( '/^#?(\d+)$/', $search, $m ) && (int) $m[1] === $row['form_id'];
					return $id_match || false !== stripos( $row['form_name'], $search );
				}
				return true;
			}
		);

		if ( ! in_array( $orderby, array( 'form_name', 'form_id', 'usage_count' ), true ) ) {
			$orderby = 'form_id';
		}
		usort(
			$rows,
			function ( $a, $b ) use ( $orderby, $order ) {
				$cmp = 'form_name' === $orderby ? strcasecmp( $a['form_name'], $b['form_name'] ) : $a[ $orderby ] <=> $b[ $orderby ];
				return 0 === $cmp ? $a['form_id'] <=> $b['form_id'] : $cmp * $order;
			}
		);

		$per_page = $this->get_items_per_page( 'bgfl_per_page', 20 );
		$total    = count( $rows );
		$page     = $this->get_pagenum();

		$this->items           = array_slice( $rows, ( $page - 1 ) * $per_page, $per_page );
		$this->locations       = Results_Repository::rows_for_forms( wp_list_pluck( $this->items, 'form_id' ) );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'form_name' );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function no_items() {
		if ( ! $this->summary ) {
			esc_html_e( 'No results yet. Run a scan to list your forms and where they are used.', 'brigade-gravity-form-locator' );
		} elseif ( empty( $this->summary['forms'] ) && empty( $this->summary['gf_available'] ) ) {
			esc_html_e( 'Gravity Forms was not active during the last scan, so registered forms could not be listed.', 'brigade-gravity-form-locator' );
		} else {
			esc_html_e( 'No forms match this view.', 'brigade-gravity-form-locator' );
		}
	}

	/**
	 * Form name column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_form_name( $item ) {
		if ( ! $item['exists'] ) {
			return '<span class="bgfl-missing">' . esc_html( $item['form_name'] ) . '</span><span class="bgfl-sub">' . esc_html__( 'Referenced but not found in Gravity Forms', 'brigade-gravity-form-locator' ) . '</span>';
		}
		$html = '<strong>' . esc_html( '' !== $item['form_name'] ? $item['form_name'] : __( '(untitled form)', 'brigade-gravity-form-locator' ) ) . '</strong>';
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Capability registered by Gravity Forms.
		if ( current_user_can( 'gravityforms_edit_forms' ) || current_user_can( 'manage_options' ) ) {
			$url   = add_query_arg(
				array(
					'page' => 'gf_edit_forms',
					'id'   => $item['form_id'],
				),
				admin_url( 'admin.php' )
			);
			$html .= '<div class="row-actions"><a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit form', 'brigade-gravity-form-locator' ) . '</a></div>';
		}
		return $html;
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
	 * Form status column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_form_state( $item ) {
		if ( ! $item['exists'] ) {
			return '<span class="bgfl-status bgfl-status--missing">' . esc_html__( 'Not found', 'brigade-gravity-form-locator' ) . '</span>';
		}
		if ( $item['is_trash'] ) {
			return '<span class="bgfl-status bgfl-status--trash">' . esc_html__( 'In trash', 'brigade-gravity-form-locator' ) . '</span>';
		}
		return $item['is_active']
			? '<span class="bgfl-status bgfl-status--publish">' . esc_html__( 'Active', 'brigade-gravity-form-locator' ) . '</span>'
			: '<span class="bgfl-status bgfl-status--inactive">' . esc_html__( 'Inactive', 'brigade-gravity-form-locator' ) . '</span>';
	}

	/**
	 * Usage count column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_usage_count( $item ) {
		if ( 0 === $item['usage_count'] ) {
			return '<span class="bgfl-none">' . esc_html__( 'No detected usage', 'brigade-gravity-form-locator' ) . '</span>';
		}
		$html = '<span class="bgfl-count">' . esc_html( number_format_i18n( $item['usage_count'] ) ) . '</span>';
		if ( 0 === $item['confirmed'] ) {
			$html .= '<span class="bgfl-sub">' . esc_html__( 'Possible references only', 'brigade-gravity-form-locator' ) . '</span>';
		}
		return $html;
	}

	/**
	 * Expandable list of locations.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_locations( $item ) {
		$rows = isset( $this->locations[ $item['form_id'] ] ) ? $this->locations[ $item['form_id'] ] : array();
		if ( empty( $rows ) ) {
			return '<span class="bgfl-muted">&mdash;</span>';
		}

		$methods = Usage::method_labels();
		$levels  = Usage::confidence_labels();

		/* translators: %s: number of usage records. */
		$html = '<details class="bgfl-details"><summary>' . esc_html( sprintf( _n( 'Show %s record', 'Show %s records', count( $rows ), 'brigade-gravity-form-locator' ), number_format_i18n( count( $rows ) ) ) ) . '</summary><ul>';

		foreach ( $rows as $row ) {
			$links   = Location_Links::for_row( $row );
			$html   .= '<li><span class="bgfl-location">' . esc_html( $row['object_title'] ) . '</span> ';
			$html   .= '<span class="bgfl-tag">' . esc_html( $row['builder'] ) . '</span> ';
			$html   .= '<span class="bgfl-confidence bgfl-confidence--' . esc_attr( $row['confidence'] ) . '">' . esc_html( isset( $levels[ $row['confidence'] ] ) ? $levels[ $row['confidence'] ] : $row['confidence'] ) . '</span>';
			$html   .= '<span class="bgfl-sub">' . esc_html( Location_Links::type_label( $row ) . ' · ' . ( isset( $methods[ $row['implementation'] ] ) ? $methods[ $row['implementation'] ] : $row['implementation'] ) . ' · ' . $row['source'] ) . '</span>';
			$actions = array();
			if ( '' !== $links['view'] ) {
				$actions[] = '<a href="' . esc_url( $links['view'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $links['view_label'] ) . '</a>';
			}
			if ( '' !== $links['edit'] ) {
				$actions[] = '<a href="' . esc_url( $links['edit'] ) . '">' . esc_html__( 'Edit', 'brigade-gravity-form-locator' ) . '</a>';
			}
			if ( $actions ) {
				$html .= '<span class="bgfl-links">' . implode( ' | ', $actions ) . '</span>';
			}
			$html .= '</li>';
		}

		return $html . '</ul></details>';
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
