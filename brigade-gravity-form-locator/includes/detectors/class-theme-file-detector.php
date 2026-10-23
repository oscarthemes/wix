<?php
/**
 * Theme template file detector.
 *
 * @package Brigade\GravityFormLocator
 */

namespace Brigade\GravityFormLocator\Detectors;

use Brigade\GravityFormLocator\Parser;
use Brigade\GravityFormLocator\Scan_Context;
use Brigade\GravityFormLocator\Scan_Item;
use Brigade\GravityFormLocator\Usage;

defined( 'ABSPATH' ) || exit;

/**
 * Statically reads the active theme's (and parent theme's) PHP and HTML files.
 *
 * PHP files are tokenized, never executed. Calls such as gravity_form( 5 ) and
 * do_shortcode( '[gravityform id="5"]' ) are recognised when the form reference is a
 * literal; calls with variables are reported as unresolved.
 */
class Theme_File_Detector extends Abstract_Detector {

	const BUILDER = 'Theme';

	/**
	 * Global functions that render a form; the first argument is the form ID or title.
	 *
	 * @var string[]
	 */
	const FUNCTIONS = array( 'gravity_form' );

	/**
	 * Static methods that return form markup; the first argument is the form ID or title.
	 *
	 * @var array<string,string[]>
	 */
	const STATIC_METHODS = array(
		'gfforms'       => array( 'get_form' ),
		'rgforms'       => array( 'get_form' ),
		'gfformdisplay' => array( 'get_form' ),
	);

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'theme_files';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Theme template files', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'PHP and HTML files of the active theme and its parent: gravity_form() and GFForms::get_form() calls with literal IDs, shortcodes in PHP strings, block markup in templates, parts and patterns. Files are read, never executed; calls with variables are listed as unresolved.', 'brigade-gravity-form-locator' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_support_level() {
		return self::SUPPORT_PARTIAL;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item $item Location.
	 */
	public function supports( Scan_Item $item ) {
		return Scan_Item::TYPE_FILE === $item->type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 */
	public function detect( Scan_Item $item, Scan_Context $context ) {
		$content = $item->content;

		if ( '' === $content || ( ! Parser::mentions_shortcode( $content ) && false === stripos( $content, 'gravity' ) && false === stripos( $content, 'gform' ) ) ) {
			return array();
		}

		if ( '.html' === substr( $item->subtype, -5 ) ) {
			return Parser::block_usages( parse_blocks( $content ), $item, $context, self::BUILDER );
		}

		if ( ! function_exists( 'token_get_all' ) ) {
			$context->skipped( $item, __( 'The PHP tokenizer extension is not available, so this PHP file was only searched for shortcodes.', 'brigade-gravity-form-locator' ) );
			return Parser::shortcode_usages( $content, __( 'Shortcode in PHP template', 'brigade-gravity-form-locator' ), $item, $context, self::BUILDER );
		}

		return $this->php_usages( $content, $item, $context );
	}

	/**
	 * Tokenizes a PHP file and collects usages.
	 *
	 * @param string       $code    PHP source.
	 * @param Scan_Item    $item    Location.
	 * @param Scan_Context $context Scan context.
	 * @return Usage[]
	 */
	private function php_usages( $code, Scan_Item $item, Scan_Context $context ) {
		$usages = array();
		$tokens = token_get_all( $code );
		$count  = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) ) {
				continue;
			}

			list( $id, $text, $line ) = $token;

			if ( T_INLINE_HTML === $id ) {
				$usages = array_merge( $usages, $this->markup_block_usages( $text, $line, $item, $context ) );
				continue;
			}

			if ( T_CONSTANT_ENCAPSED_STRING === $id ) {
				$string = $this->string_value( $text );
				if ( Parser::mentions_shortcode( $string ) || false !== strpos( $string, 'wp:' . Parser::GF_BLOCK ) ) {
					$usages = array_merge( $usages, $this->markup_block_usages( $string, $line, $item, $context, true ) );
				}
				continue;
			}

			$name = $this->function_name( $tokens, $i );
			if ( null === $name ) {
				continue;
			}

			$args_start = $this->next_significant( $tokens, $name['end'] + 1 );
			if ( null === $args_start || '(' !== $tokens[ $args_start ] ) {
				continue;
			}

			$argument = $this->first_argument( $tokens, $args_start + 1 );
			/* translators: 1: PHP function name, 2: line number. */
			$source = sprintf( __( '%1$s() call, line %2$d', 'brigade-gravity-form-locator' ), $name['label'], $line );

			if ( null === $argument ) {
				/* translators: 1: PHP function name, 2: line number. */
				$context->unresolved( $item, sprintf( __( '%1$s() called with a dynamic form reference on line %2$d.', 'brigade-gravity-form-locator' ), $name['label'], $line ) );
			} else {
				$usages[] = Usage::make( $argument, Usage::METHOD_PHP_FUNCTION, $source, Usage::CONFIRMED, self::BUILDER );
			}

			$i = $args_start;
		}

		return $usages;
	}

	/**
	 * Usages in literal HTML or strings: block markup and shortcodes.
	 *
	 * @param string       $text      Text.
	 * @param int          $line      Line number.
	 * @param Scan_Item    $item      Location.
	 * @param Scan_Context $context   Scan context.
	 * @param bool         $in_string Whether the text is a PHP string literal.
	 * @return Usage[]
	 */
	private function markup_block_usages( $text, $line, Scan_Item $item, Scan_Context $context, $in_string = false ) {
		if ( false !== strpos( $text, '<!-- wp:' ) ) {
			$usages = Parser::block_usages( parse_blocks( $text ), $item, $context, self::BUILDER );
			foreach ( $usages as $usage ) {
				/* translators: 1: original source description, 2: line number. */
				$usage->source = sprintf( __( '%1$s, line %2$d', 'brigade-gravity-form-locator' ), $usage->source, $line );
			}
			return $usages;
		}

		$source = $in_string
			/* translators: %d: line number. */
			? sprintf( __( 'Shortcode in PHP string (e.g. do_shortcode), line %d', 'brigade-gravity-form-locator' ), $line )
			/* translators: %d: line number. */
			: sprintf( __( 'Shortcode in template HTML, line %d', 'brigade-gravity-form-locator' ), $line );

		return Parser::shortcode_usages( $text, $source, $item, $context, self::BUILDER );
	}

	/**
	 * Returns the called function's name if token $i starts a call to a known form function.
	 *
	 * @param array $tokens Tokens.
	 * @param int   $i      Index.
	 * @return array|null With label and end index.
	 */
	private function function_name( array $tokens, $i ) {
		$token = $tokens[ $i ];
		$name  = strtolower( ltrim( $token[1], '\\' ) );
		$kinds = array( T_STRING );
		if ( defined( 'T_NAME_FULLY_QUALIFIED' ) ) {
			$kinds[] = T_NAME_FULLY_QUALIFIED;
		}
		if ( ! in_array( $token[0], $kinds, true ) ) {
			return null;
		}

		$previous = $this->previous_significant( $tokens, $i - 1 );
		$prev_id  = null !== $previous && is_array( $tokens[ $previous ] ) ? $tokens[ $previous ][0] : null;

		// Ignore declarations and method calls on objects: function gravity_form(), $x->gravity_form().
		if ( in_array( $prev_id, array( T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW ), true ) ) {
			return null;
		}
		if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) && T_NULLSAFE_OBJECT_OPERATOR === $prev_id ) {
			return null;
		}

		if ( in_array( $name, self::FUNCTIONS, true ) ) {
			return array(
				'label' => $name,
				'end'   => $i,
			);
		}

		if ( isset( self::STATIC_METHODS[ $name ] ) ) {
			$colon  = $this->next_significant( $tokens, $i + 1 );
			$method = null !== $colon ? $this->next_significant( $tokens, $colon + 1 ) : null;
			if ( null !== $method && is_array( $tokens[ $colon ] ) && T_DOUBLE_COLON === $tokens[ $colon ][0]
				&& is_array( $tokens[ $method ] ) && in_array( strtolower( $tokens[ $method ][1] ), self::STATIC_METHODS[ $name ], true ) ) {
				return array(
					'label' => $token[1] . '::' . $tokens[ $method ][1],
					'end'   => $method,
				);
			}
		}

		return null;
	}

	/**
	 * Returns the literal first argument of a call, or null when it is not a single literal.
	 *
	 * @param array $tokens Tokens.
	 * @param int   $start  Index after the opening parenthesis.
	 * @return string|null
	 */
	private function first_argument( array $tokens, $start ) {
		$parts = array();
		$count = count( $tokens );

		for ( $i = $start; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ',' === $token || ')' === $token ) {
				break;
			}
			if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$parts[] = $token;
			if ( count( $parts ) > 1 ) {
				return null;
			}
		}

		if ( 1 !== count( $parts ) || ! is_array( $parts[0] ) ) {
			return null;
		}

		if ( T_LNUMBER === $parts[0][0] ) {
			return (string) (int) $parts[0][1];
		}
		if ( T_CONSTANT_ENCAPSED_STRING === $parts[0][0] ) {
			$value = $this->string_value( $parts[0][1] );
			return '' !== $value && ! Parser::is_dynamic_ref( $value ) ? $value : null;
		}

		return null;
	}

	/**
	 * Unquotes a PHP string literal.
	 *
	 * @param string $literal Literal including quotes.
	 * @return string
	 */
	private function string_value( $literal ) {
		$quote = $literal[0];
		$inner = substr( $literal, 1, -1 );
		return '"' === $quote ? stripcslashes( $inner ) : str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $inner );
	}

	/**
	 * Index of the next token that is not whitespace or a comment.
	 *
	 * @param array $tokens Tokens.
	 * @param int   $i      Start index.
	 * @return int|null
	 */
	private function next_significant( array $tokens, $i ) {
		$count = count( $tokens );
		for ( ; $i < $count; $i++ ) {
			if ( ! $this->is_insignificant( $tokens[ $i ] ) ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Index of the previous token that is not whitespace or a comment.
	 *
	 * @param array $tokens Tokens.
	 * @param int   $i      Start index.
	 * @return int|null
	 */
	private function previous_significant( array $tokens, $i ) {
		for ( ; $i >= 0; $i-- ) {
			if ( ! $this->is_insignificant( $tokens[ $i ] ) ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Whether a token is whitespace or a comment.
	 *
	 * @param mixed $token Token.
	 * @return bool
	 */
	private function is_insignificant( $token ) {
		return is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
	}
}
