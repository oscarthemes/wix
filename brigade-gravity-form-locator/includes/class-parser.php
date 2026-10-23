<?php
/**
 * Shared parsing helpers for shortcodes, blocks and nested data.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator;

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers used by the detectors. Nothing here renders content or executes code.
 */
class Parser {

	/**
	 * Shortcode tags registered by Gravity Forms.
	 *
	 * @var string[]
	 */
	const SHORTCODE_TAGS = array( 'gravityform', 'gravityforms' );

	/**
	 * Gutenberg block registered by Gravity Forms.
	 */
	const GF_BLOCK = 'gravityforms/form';

	/**
	 * Maximum depth for walking nested data structures.
	 */
	const MAX_DEPTH = 40;

	/**
	 * Cheap pre-check before running the shortcode regex.
	 *
	 * @param mixed $text Text.
	 * @return bool
	 */
	public static function mentions_shortcode( $text ) {
		return is_string( $text ) && false !== stripos( $text, '[gravityform' );
	}

	/**
	 * Finds Gravity Forms shortcodes using the WordPress shortcode grammar.
	 *
	 * Escaped shortcodes ("[[gravityform id=1]]") are ignored because WordPress prints them as text.
	 *
	 * @param string $text Text to search.
	 * @return array[] Each with tag, atts, ref, action and offset.
	 */
	public static function find_shortcodes( $text ) {
		if ( ! self::mentions_shortcode( $text ) ) {
			return array();
		}

		$found = array();
		$regex = '/' . get_shortcode_regex( self::SHORTCODE_TAGS ) . '/';

		if ( ! preg_match_all( $regex, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		foreach ( $matches as $match ) {
			if ( '[' === $match[1][0] && ']' === $match[6][0] ) {
				continue;
			}

			$atts = shortcode_parse_atts( $match[3][0] );
			$atts = is_array( $atts ) ? array_change_key_case( $atts, CASE_LOWER ) : array();

			// Gravity Forms falls back to the "name" attribute (a form title) when "id" is empty.
			$ref = isset( $atts['id'] ) ? trim( (string) $atts['id'] ) : '';
			if ( ( '' === $ref || '0' === $ref ) && isset( $atts['name'] ) ) {
				$ref = trim( (string) $atts['name'] );
			}

			$found[] = array(
				'tag'    => strtolower( $match[2][0] ),
				'atts'   => $atts,
				'ref'    => $ref,
				'action' => isset( $atts['action'] ) ? sanitize_key( $atts['action'] ) : 'form',
				'offset' => $match[0][1],
			);
		}

		return $found;
	}

	/**
	 * Whether a reference is a template placeholder or code rather than a literal ID or title.
	 *
	 * @param string $ref Reference.
	 * @return bool
	 */
	public static function is_dynamic_ref( $ref ) {
		return (bool) preg_match( '/[{}$<>%]|\bphp\b/i', $ref );
	}

	/**
	 * Converts shortcodes in a string into usages.
	 *
	 * @param string       $text    Text to search.
	 * @param string       $source  Description of where the text came from.
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 * @param string       $builder Optional builder override.
	 * @return Usage[]
	 */
	public static function shortcode_usages( $text, $source, Scan_Item $item, Scan_Context $context, $builder = '' ) {
		$usages = array();

		foreach ( self::find_shortcodes( $text ) as $shortcode ) {
			$usage = self::shortcode_usage( $shortcode, $source, $item, $context, $builder );
			if ( $usage ) {
				$usages[] = $usage;
			}
		}

		return $usages;
	}

	/**
	 * Converts one shortcode returned by find_shortcodes() into a usage.
	 *
	 * @param array        $shortcode Shortcode from find_shortcodes().
	 * @param string       $source    Description of where the text came from.
	 * @param Scan_Item    $item      Location.
	 * @param Scan_Context $context   Scan context.
	 * @param string       $builder   Optional builder override.
	 * @return Usage|null Null when the reference cannot be resolved statically.
	 */
	public static function shortcode_usage( array $shortcode, $source, Scan_Item $item, Scan_Context $context, $builder = '' ) {
		$ref = $shortcode['ref'];

		if ( '' === $ref || '0' === $ref ) {
			/* translators: %s: where the shortcode was found. */
			$context->unresolved( $item, sprintf( __( 'Gravity Forms shortcode without a form ID (%s).', 'brigade-gravity-form-locator' ), $source ) );
			return null;
		}

		if ( self::is_dynamic_ref( $ref ) ) {
			/* translators: 1: the form reference, 2: where the shortcode was found. */
			$context->unresolved( $item, sprintf( __( 'Gravity Forms shortcode with a dynamic form reference "%1$s" (%2$s).', 'brigade-gravity-form-locator' ), $ref, $source ) );
			return null;
		}

		if ( 'form' !== $shortcode['action'] && '' !== $shortcode['action'] ) {
			/* translators: 1: where the shortcode was found, 2: shortcode action attribute. */
			$source = sprintf( __( '%1$s, action "%2$s"', 'brigade-gravity-form-locator' ), $source, $shortcode['action'] );
		}

		return Usage::make( $ref, Usage::METHOD_SHORTCODE, $source, Usage::CONFIRMED, $builder );
	}

	/**
	 * Finds HTML that Gravity Forms prints when it renders a form (pasted or cached output).
	 *
	 * These are only possible references: the markup proves a form was rendered at some
	 * point, not that the location embeds it today.
	 *
	 * @param string       $text    HTML.
	 * @param string       $source  Description of where the text came from.
	 * @param Scan_Context $context Scan context.
	 * @param string       $builder Optional builder override.
	 * @return Usage[]
	 */
	public static function markup_usages( $text, $source, Scan_Context $context, $builder = '' ) {
		if ( ! $context->possible_references() || ! is_string( $text ) || false === stripos( $text, 'gform_' ) ) {
			return array();
		}

		if ( ! preg_match_all( '/\bid\s*=\s*["\']gform_(?:wrapper_)?(\d+)["\']/i', $text, $matches ) ) {
			return array();
		}

		$usages = array();
		foreach ( array_unique( $matches[1] ) as $form_id ) {
			$usages[] = Usage::make( $form_id, Usage::METHOD_MARKUP, $source, Usage::POSSIBLE, $builder );
		}
		return $usages;
	}

	/**
	 * Walks parsed blocks and returns usages.
	 *
	 * @param array[]      $blocks  Output of parse_blocks().
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 * @param string       $builder Builder label for usages.
	 * @param int          $depth   Recursion depth.
	 * @return Usage[]
	 */
	public static function block_usages( array $blocks, Scan_Item $item, Scan_Context $context, $builder = 'Gutenberg', $depth = 0 ) {
		$usages = array();

		if ( $depth > self::MAX_DEPTH ) {
			return $usages;
		}

		foreach ( $blocks as $block ) {
			$name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$html  = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';

			if ( self::GF_BLOCK === $name ) {
				$form_id = isset( $attrs['formId'] ) ? (string) $attrs['formId'] : '';
				if ( '' === $form_id || '0' === $form_id ) {
					$context->unresolved( $item, __( 'Gravity Forms block with no form selected.', 'brigade-gravity-form-locator' ) );
				} else {
					$usages[] = Usage::make( $form_id, Usage::METHOD_BLOCK, __( 'Gravity Forms block', 'brigade-gravity-form-locator' ), Usage::CONFIRMED, $builder );
				}
			} elseif ( 'core/shortcode' === $name ) {
				$usages = array_merge( $usages, self::shortcode_usages( $html, __( 'Shortcode block', 'brigade-gravity-form-locator' ), $item, $context, $builder ) );
			} elseif ( '' === $name ) {
				$usages = array_merge(
					$usages,
					self::shortcode_usages( $html, __( 'Classic content', 'brigade-gravity-form-locator' ), $item, $context, $builder ),
					self::markup_usages( $html, __( 'Classic content', 'brigade-gravity-form-locator' ), $context, $builder )
				);
			} else {
				/* translators: %s: block name, e.g. core/paragraph. */
				$source = sprintf( __( 'Shortcode inside %s block', 'brigade-gravity-form-locator' ), $name );
				$usages = array_merge(
					$usages,
					self::shortcode_usages( $html, $source, $item, $context, $builder ),
					self::markup_usages( $html, $source, $context, $builder ),
					self::attribute_usages( $name, $attrs, $item, $context, $builder )
				);
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$usages = array_merge( $usages, self::block_usages( $block['innerBlocks'], $item, $context, $builder, $depth + 1 ) );
			}
		}

		return $usages;
	}

	/**
	 * Inspects attributes of non-Gravity-Forms blocks: shortcodes stored in attributes,
	 * and form ID settings of third-party blocks whose name mentions Gravity Forms.
	 *
	 * @param string       $name    Block name.
	 * @param array        $attrs   Block attributes.
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 * @param string       $builder Builder label.
	 * @return Usage[]
	 */
	private static function attribute_usages( $name, array $attrs, Scan_Item $item, Scan_Context $context, $builder ) {
		if ( empty( $attrs ) ) {
			return array();
		}

		/* translators: %s: block name. */
		$source = sprintf( __( 'Shortcode in %s block settings', 'brigade-gravity-form-locator' ), $name );
		$usages = self::nested_shortcode_usages( $attrs, $source, $item, $context, $builder );

		if ( self::name_mentions_gravity( $name ) ) {
			$form_id = self::find_form_id_setting( $attrs );
			if ( null !== $form_id ) {
				/* translators: %s: block name. */
				$usages[] = Usage::make( $form_id, Usage::METHOD_BUILDER_ELEMENT, sprintf( __( 'Third-party block %s', 'brigade-gravity-form-locator' ), $name ), Usage::LIKELY, $builder );
			}
		}

		return $usages;
	}

	/**
	 * Calls $callback for every string in a nested array/object structure.
	 *
	 * @param mixed    $value    Value.
	 * @param callable $callback Receives ( string $value, string $path ).
	 * @param string   $path     Current path.
	 * @param int      $depth    Recursion depth.
	 */
	public static function walk_strings( $value, callable $callback, $path = '', $depth = 0 ) {
		if ( $depth > self::MAX_DEPTH ) {
			return;
		}
		if ( is_string( $value ) ) {
			$callback( $value, $path );
			return;
		}
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				self::walk_strings( $child, $callback, '' === $path ? (string) $key : $path . '.' . $key, $depth + 1 );
			}
		}
	}

	/**
	 * Finds shortcodes in every string of a nested structure.
	 *
	 * @param mixed        $value   Nested data.
	 * @param string       $source  Source label.
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 * @param string       $builder Builder label.
	 * @return Usage[]
	 */
	public static function nested_shortcode_usages( $value, $source, Scan_Item $item, Scan_Context $context, $builder = '' ) {
		$usages = array();
		self::walk_strings(
			$value,
			function ( $text ) use ( &$usages, $source, $item, $context, $builder ) {
				if ( self::mentions_shortcode( $text ) ) {
					$usages = array_merge( $usages, self::shortcode_usages( $text, $source, $item, $context, $builder ) );
				}
			}
		);
		return $usages;
	}

	/**
	 * Whether an element, widget or block name refers to Gravity Forms.
	 *
	 * @param string $name Name.
	 * @return bool
	 */
	public static function name_mentions_gravity( $name ) {
		return (bool) preg_match( '/gravity|(^|[^a-z])gf([^a-z]|$)|gform/i', (string) $name );
	}

	/**
	 * Looks for a form ID in the common setting names used by third-party integrations.
	 *
	 * @param array $settings Element settings.
	 * @return string|null Numeric form ID or null.
	 */
	public static function find_form_id_setting( array $settings ) {
		$keys = array( 'formId', 'form_id', 'formid', 'gf_form_id', 'gravity_form', 'gravity_form_id', 'gravityform', 'contact_form_list', 'select_form', 'form_list', 'form' );

		foreach ( $keys as $key ) {
			if ( ! isset( $settings[ $key ] ) || is_array( $settings[ $key ] ) || is_object( $settings[ $key ] ) ) {
				continue;
			}
			$value = trim( (string) $settings[ $key ] );
			if ( preg_match( '/^[1-9]\d{0,9}$/', $value ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Builds a minimal tree view of builder shortcodes so a Gravity Forms shortcode can be
	 * attributed to the element that contains it.
	 *
	 * Only tags that have a matching closing tag in the text are treated as containers;
	 * self-closing elements never become parents.
	 *
	 * @param string $text   Builder content.
	 * @param string $prefix Tag prefix of the builder ("vc_", "et_pb_").
	 * @return array[] Each: tag, atts, parent (closest ancestor with the prefix or ''), offset.
	 */
	public static function shortcode_outline( $text, $prefix ) {
		$nodes = array();

		if ( ! preg_match_all( '/\[(\/?)([A-Za-z0-9_\-]+)((?:[^\[\]"\']|"[^"]*"|\'[^\']*\')*?)(\/?)\]/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $nodes;
		}

		preg_match_all( '/\[\/([A-Za-z0-9_\-]+)\]/', $text, $closers );
		$closable = array_flip( array_map( 'strtolower', $closers[1] ) );
		$stack    = array();

		foreach ( $matches as $match ) {
			$tag = strtolower( $match[2][0] );

			if ( '/' === $match[1][0] ) {
				$position = array_search( $tag, $stack, true );
				if ( false !== $position ) {
					$stack = array_slice( $stack, 0, $position );
				}
				continue;
			}

			$parent = '';
			for ( $i = count( $stack ) - 1; $i >= 0; $i-- ) {
				if ( 0 === strpos( $stack[ $i ], $prefix ) ) {
					$parent = $stack[ $i ];
					break;
				}
			}

			$atts    = shortcode_parse_atts( $match[3][0] );
			$nodes[] = array(
				'tag'    => $tag,
				'atts'   => is_array( $atts ) ? $atts : array(),
				'parent' => $parent,
				'offset' => $match[0][1],
			);

			if ( '/' !== $match[4][0] && isset( $closable[ $tag ] ) ) {
				$stack[] = $tag;
			}
		}

		return $nodes;
	}
}
