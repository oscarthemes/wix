<?php
/**
 * Gravity Forms access.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Reads registered forms through the official GFAPI.
 */
class Forms {

	/**
	 * Whether Gravity Forms is loaded.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( 'GFAPI' ) && method_exists( 'GFAPI', 'get_forms' );
	}

	/**
	 * Gravity Forms version, if available.
	 *
	 * @return string
	 */
	public static function version() {
		if ( class_exists( 'GFForms' ) && isset( \GFForms::$version ) ) {
			return (string) \GFForms::$version;
		}
		return '';
	}

	/**
	 * Returns every form (active, inactive and trashed) with only the fields this plugin needs.
	 *
	 * @return array<int,array> Keyed by form ID: id, title, is_active, is_trash.
	 */
	public static function all() {
		if ( ! self::is_available() ) {
			return array();
		}

		$forms = array();

		foreach ( array( false, true ) as $trash ) {
			try {
				$list = \GFAPI::get_forms( null, $trash );
			} catch ( \Throwable $e ) {
				$list = array();
			}

			foreach ( (array) $list as $form ) {
				if ( ! is_array( $form ) || empty( $form['id'] ) ) {
					continue;
				}
				$id           = (int) $form['id'];
				$forms[ $id ] = array(
					'id'        => $id,
					'title'     => isset( $form['title'] ) ? (string) $form['title'] : '',
					'is_active' => ! empty( $form['is_active'] ),
					'is_trash'  => $trash || ! empty( $form['is_trash'] ),
				);
			}
		}

		ksort( $forms );
		return $forms;
	}

	/**
	 * Resolves a reference (numeric ID or title) against a forms snapshot.
	 *
	 * @param string $ref   Reference.
	 * @param array  $forms Forms snapshot from all().
	 * @return array{id:int,exists:bool}|null Null when a title does not match any form.
	 */
	public static function resolve( $ref, array $forms ) {
		$ref = trim( (string) $ref );

		if ( preg_match( '/^\d{1,10}$/', $ref ) ) {
			$id = (int) $ref;
			if ( $id <= 0 ) {
				return null;
			}
			return array(
				'id'     => $id,
				'exists' => isset( $forms[ $id ] ),
			);
		}

		foreach ( $forms as $form ) {
			if ( 0 === strcasecmp( $form['title'], $ref ) ) {
				return array(
					'id'     => (int) $form['id'],
					'exists' => true,
				);
			}
		}

		return null;
	}
}
