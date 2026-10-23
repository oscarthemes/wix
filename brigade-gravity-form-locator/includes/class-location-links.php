<?php
/**
 * View and edit links for stored locations.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Builds links at display time so they respect the current user's capabilities and
 * reflect posts that changed or were deleted since the scan.
 */
class Location_Links {

	/**
	 * Links for a usage row.
	 *
	 * @param array $row Usage row.
	 * @return array{view:string,view_label:string,edit:string,missing:bool}
	 */
	public static function for_row( array $row ) {
		$links = array(
			'view'       => '',
			'view_label' => __( 'View', 'brigade-gravity-form-locator' ),
			'edit'       => '',
			'missing'    => false,
		);

		switch ( $row['object_type'] ) {
			case Scan_Item::TYPE_POST:
			case Scan_Item::TYPE_PATTERN:
			case Scan_Item::TYPE_TEMPLATE:
				return self::post_links( $row, $links );

			case Scan_Item::TYPE_WIDGET:
				if ( current_user_can( 'edit_theme_options' ) ) {
					$links['edit'] = current_theme_supports( 'widgets' ) || wp_is_block_theme()
						? admin_url( 'widgets.php' )
						: admin_url( 'customize.php' );
				}
				return $links;

			case Scan_Item::TYPE_FILE:
				$parts = explode( '/', (string) $row['object_subtype'], 2 );
				if ( 2 === count( $parts ) && current_user_can( 'edit_themes' ) ) {
					$links['edit'] = add_query_arg(
						array(
							'file'  => rawurlencode( $parts[1] ),
							'theme' => rawurlencode( $parts[0] ),
						),
						admin_url( 'theme-editor.php' )
					);
				}
				return $links;
		}

		return $links;
	}

	/**
	 * Links for post-backed locations.
	 *
	 * @param array $row   Usage row.
	 * @param array $links Defaults.
	 * @return array
	 */
	private static function post_links( array $row, array $links ) {
		$post = get_post( (int) $row['object_id'] );

		if ( ! $post ) {
			$links['missing'] = true;
			return $links;
		}

		$can_edit = current_user_can( 'edit_post', $post->ID );

		if ( Scan_Item::TYPE_POST === $row['object_type'] && is_post_type_viewable( $post->post_type ) ) {
			if ( 'publish' === $post->post_status || ( 'private' === $post->post_status && current_user_can( 'read_post', $post->ID ) ) ) {
				$links['view'] = (string) get_permalink( $post );
			} elseif ( $can_edit && in_array( $post->post_status, array( 'draft', 'pending', 'future' ), true ) ) {
				$links['view']       = (string) get_preview_post_link( $post );
				$links['view_label'] = __( 'Preview', 'brigade-gravity-form-locator' );
			}
		}

		if ( $can_edit ) {
			if ( 'Elementor' === $row['builder'] && defined( 'ELEMENTOR_VERSION' ) ) {
				$links['edit'] = add_query_arg(
					array(
						'post'   => $post->ID,
						'action' => 'elementor',
					),
					admin_url( 'post.php' )
				);
			} else {
				$links['edit'] = (string) get_edit_post_link( $post->ID, 'raw' );
			}
		}

		return $links;
	}

	/**
	 * Human-readable location type, e.g. "Page", "Synced pattern", "Elementor template (header)".
	 *
	 * @param array $row Usage row.
	 * @return string
	 */
	public static function type_label( array $row ) {
		$parts = explode( '/', (string) $row['object_subtype'], 2 );

		switch ( $row['object_type'] ) {
			case Scan_Item::TYPE_PATTERN:
				return __( 'Synced pattern', 'brigade-gravity-form-locator' );
			case Scan_Item::TYPE_WIDGET:
				return __( 'Widget', 'brigade-gravity-form-locator' );
			case Scan_Item::TYPE_FILE:
				return __( 'Theme file', 'brigade-gravity-form-locator' );
			case Scan_Item::TYPE_OPTION:
				return __( 'Saved builder template', 'brigade-gravity-form-locator' );
		}

		$object = get_post_type_object( $parts[0] );
		$label  = $object ? $object->labels->singular_name : $parts[0];

		if ( Scan_Item::TYPE_TEMPLATE === $row['object_type'] && isset( $parts[1] ) && '' !== $parts[1] ) {
			/* translators: 1: template post type label, 2: template kind such as header. */
			return sprintf( __( '%1$s (%2$s)', 'brigade-gravity-form-locator' ), $label, str_replace( '-', ' ', $parts[1] ) );
		}

		return $label;
	}

	/**
	 * Translated status label.
	 *
	 * @param string $status Stored status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'publish'  => __( 'Published', 'brigade-gravity-form-locator' ),
			'future'   => __( 'Scheduled', 'brigade-gravity-form-locator' ),
			'draft'    => __( 'Draft', 'brigade-gravity-form-locator' ),
			'pending'  => __( 'Pending review', 'brigade-gravity-form-locator' ),
			'private'  => __( 'Private', 'brigade-gravity-form-locator' ),
			'active'   => __( 'Active', 'brigade-gravity-form-locator' ),
			'inactive' => __( 'Inactive', 'brigade-gravity-form-locator' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
	}

	/**
	 * Translated location type filter labels.
	 *
	 * @return array<string,string>
	 */
	public static function object_type_labels() {
		return array(
			Scan_Item::TYPE_POST     => __( 'Posts, pages and custom post types', 'brigade-gravity-form-locator' ),
			Scan_Item::TYPE_TEMPLATE => __( 'Templates', 'brigade-gravity-form-locator' ),
			Scan_Item::TYPE_PATTERN  => __( 'Synced patterns', 'brigade-gravity-form-locator' ),
			Scan_Item::TYPE_WIDGET   => __( 'Widgets', 'brigade-gravity-form-locator' ),
			Scan_Item::TYPE_FILE     => __( 'Theme files', 'brigade-gravity-form-locator' ),
			Scan_Item::TYPE_OPTION   => __( 'Saved builder templates', 'brigade-gravity-form-locator' ),
		);
	}

	/**
	 * Display name of a form in a row.
	 *
	 * @param array $row Usage row.
	 * @return string
	 */
	public static function form_label( array $row ) {
		if ( (int) $row['form_exists'] && '' !== $row['form_name'] ) {
			return $row['form_name'];
		}
		/* translators: %d: form ID. */
		return sprintf( __( 'Form #%d (not found in Gravity Forms)', 'brigade-gravity-form-locator' ), (int) $row['form_id'] );
	}
}
