<?php
/**
 * Detector tests.
 *
 * @package Brigade\GravityFormLocator\Tests
 */

namespace Brigade\GravityFormLocator\Tests;

use Brigade\GravityFormLocator\Export;
use Brigade\GravityFormLocator\Forms;
use Brigade\GravityFormLocator\Parser;
use Brigade\GravityFormLocator\Scan_Context;
use Brigade\GravityFormLocator\Scan_Item;
use Brigade\GravityFormLocator\Scanner;
use Brigade\GravityFormLocator\Settings;
use Brigade\GravityFormLocator\Usage;

defined( 'ABSPATH' ) || exit;

/**
 * Detector behaviour on fixture content.
 */
final class Detector_Tests {

	/**
	 * Posts created by the tests.
	 *
	 * @var int[]
	 */
	private static $posts = array();

	/**
	 * Fresh context with default settings.
	 *
	 * @param array $overrides Setting overrides.
	 * @return Scan_Context
	 */
	private static function context( array $overrides = array() ) {
		return new Scan_Context( array_merge( Settings::defaults(), $overrides ) );
	}

	/**
	 * Creates a post and returns its scan item.
	 *
	 * @param string $content Content.
	 * @param array  $meta    Meta.
	 * @param string $type    Post type.
	 * @return Scan_Item
	 */
	private static function post_item( $content, array $meta = array(), $type = 'page' ) {
		$id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => 'Detector fixture',
				'post_content' => wp_slash( $content ),
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, wp_slash( $value ) );
		}
		self::$posts[] = $id;
		return Scanner::post_item( get_post( $id ) );
	}

	/**
	 * Runs the pipeline and returns [form_ref, method, builder, confidence, source] tuples.
	 *
	 * @param Scan_Item    $item    Item.
	 * @param Scan_Context $context Context.
	 * @return array[]
	 */
	private static function detect( Scan_Item $item, Scan_Context $context = null ) {
		$context = $context ? $context : self::context();
		$out     = array();
		foreach ( Scanner::detect_item( $item, $context ) as $usage ) {
			$out[] = array(
				'ref'        => $usage->form_ref,
				'method'     => $usage->method,
				'builder'    => '' !== $usage->builder ? $usage->builder : $item->builder,
				'confidence' => $usage->confidence,
				'source'     => $usage->source,
			);
		}
		return $out;
	}

	/**
	 * Form references found.
	 *
	 * @param array[] $found Output of detect().
	 * @return string[]
	 */
	private static function refs( array $found ) {
		$refs = wp_list_pluck( $found, 'ref' );
		sort( $refs );
		return $refs;
	}

	/**
	 * Removes fixture posts.
	 */
	private static function cleanup() {
		foreach ( self::$posts as $id ) {
			wp_delete_post( $id, true );
		}
		self::$posts = array();
	}

	/**
	 * Shortcode attribute formats.
	 */
	public static function test_shortcode_formats() {
		$item    = new Scan_Item();
		$context = self::context();
		$text    = 'A [gravityform id="1" title="false"] B [gravityform id=2 ajax=true] C [gravityform id=\'3\'] '
			. 'D [gravityforms id="1"] E [gravityform name="Contact Form"] F [GRAVITYFORM ID="9"] '
			. 'G [[gravityform id="4"]] H [gravityform id="{embed_form_id}"] I [gravityform title="true"] '
			. 'J [gravityform id="2" action="polls"] K Call 555 0199 [gravityform id=abc]';

		$usages = Parser::shortcode_usages( $text, 'test', $item, $context );
		$refs   = array_map(
			function ( Usage $u ) {
				return $u->form_ref;
			},
			$usages
		);

		T::same( array( '1', '2', '3', '1', 'Contact Form', '2', 'abc' ), $refs, 'literal IDs, quoted and unquoted, names and gravityforms alias are found; escaped, dynamic and ID-less shortcodes are not' );
		T::ok( false !== strpos( $usages[5]->source, 'polls' ), 'shortcode action is reported' );
		T::same( 2, count( $context->notes ), 'dynamic and missing IDs are reported as unresolved' );
		T::same( null, Forms::resolve( 'abc', Forms::all() ), 'a non-numeric reference that is not a form title does not resolve' );
		T::same(
			array(
				'id'     => 1,
				'exists' => true,
			),
			Forms::resolve( 'contact form', Forms::all() ),
			'form titles resolve case-insensitively'
		);
		T::same(
			array(
				'id'     => 99,
				'exists' => false,
			),
			Forms::resolve( '99', Forms::all() ),
			'unknown numeric IDs resolve as missing forms'
		);
		T::same( array(), Parser::shortcode_usages( 'Phone 555-1234, form 7, gform 8', 'test', $item, $context ), 'bare numbers are never treated as form IDs' );
	}

	/**
	 * Classic content with multiple forms and duplicates.
	 */
	public static function test_classic_multiple_and_duplicates() {
		$item  = self::post_item( "Intro\n[gravityform id=\"1\"]\nMore\n[gravityform id=\"2\"]\n[gravityform id=\"1\"]" );
		$found = self::detect( $item );

		T::same( array( '1', '1', '2' ), self::refs( $found ), 'every shortcode is detected' );
		T::same( 'Classic Editor', $found[0]['builder'], 'builder is Classic Editor' );

		$rows = Scanner::rows_for( $item, Scanner::detect_item( $item, self::context() ), Forms::all(), self::context() );
		T::same( 2, count( $rows ), 'duplicate matches in the same location and source collapse into one row' );
		$by_form = array_column( $rows, 'occurrences', 'form_id' );
		T::same( 2, (int) $by_form[1], 'identical matches are counted as occurrences' );
		self::cleanup();
	}

	/**
	 * Block editor content.
	 */
	public static function test_gutenberg_blocks() {
		$content = '<!-- wp:gravityforms/form {"formId":"2","title":false} /-->'
			. '<!-- wp:shortcode -->[gravityform id="3"]<!-- /wp:shortcode -->'
			. '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>[gravityform id="1"]</p><!-- /wp:paragraph --></div><!-- /wp:group -->'
			. '<!-- wp:html --><div class="gform_wrapper" id="gform_wrapper_5"></div><!-- /wp:html -->'
			. '<!-- wp:gravityforms/form /-->';

		$item  = self::post_item( $content );
		$ctx   = self::context();
		$found = self::detect( $item, $ctx );

		T::same( array( '1', '2', '3', '5' ), self::refs( $found ), 'block, shortcode block, nested shortcode and markup are found' );
		$methods = array_column( $found, 'method', 'ref' );
		T::same( Usage::METHOD_BLOCK, $methods['2'], 'Gravity Forms block method' );
		T::same( Usage::METHOD_MARKUP, $methods['5'], 'rendered markup method' );
		$levels = array_column( $found, 'confidence', 'ref' );
		T::same( Usage::POSSIBLE, $levels['5'], 'rendered markup is only a possible reference' );
		T::same( 1, count( $ctx->notes ), 'block without a form is unresolved' );

		$found = self::detect( $item, self::context( array( 'possible_references' => false ) ) );
		T::same( array( '1', '2', '3' ), self::refs( $found ), 'possible references can be turned off' );
		self::cleanup();
	}

	/**
	 * Elementor data.
	 */
	public static function test_elementor() {
		$data = array(
			array(
				'elType'   => 'section',
				'elements' => array(
					array(
						'elType'   => 'column',
						'elements' => array(
							array(
								'elType'     => 'widget',
								'widgetType' => 'shortcode',
								'settings'   => array( 'shortcode' => '[gravityform id="1" title="false"]' ),
							),
							array(
								'elType'     => 'widget',
								'widgetType' => 'text-editor',
								'settings'   => array( 'editor' => '<p>[gravityform id="2"]</p>' ),
							),
							array(
								'elType'     => 'widget',
								'widgetType' => 'eael-gravity-form',
								'settings'   => array( 'contact_form_list' => '4' ),
							),
							array(
								'elType'     => 'widget',
								'widgetType' => 'heading',
								'settings'   => array( 'title' => 'Form 3 below' ),
							),
						),
					),
				),
			),
		);

		// post_content holds Elementor's plain-text copy, which must not be reported again.
		$item  = self::post_item(
			'[gravityform id="1"] copy',
			array(
				'_elementor_edit_mode' => 'builder',
				'_elementor_data'      => wp_json_encode( $data ),
			)
		);
		$found = self::detect( $item );

		T::same( array( '1', '2', '4' ), self::refs( $found ), 'shortcode widget, text editor widget and third-party widget are found once each' );
		T::same( array( 'Elementor' ), array_values( array_unique( wp_list_pluck( $found, 'builder' ) ) ), 'all attributed to Elementor' );
		$levels = array_column( $found, 'confidence', 'ref' );
		T::same( Usage::LIKELY, $levels['4'], 'third-party widget setting is a likely reference' );

		// Disabling the Elementor detector skips the page rather than misreporting it as Classic.
		$found = self::detect( $item, self::context( array( 'disabled_detectors' => array( 'elementor' ) ) ) );
		T::same( array(), $found, 'disabled builder detector does not fall back to Classic content' );
		self::cleanup();
	}

	/**
	 * WPBakery layouts.
	 */
	public static function test_wpbakery() {
		$raw     = base64_encode( rawurlencode( '<div>[gravityform id="3"]</div>' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$content = '[vc_row][vc_column width="1/2"][gravityform id="1" title="false"][vc_single_image image="7"][/vc_column]'
			. '[vc_column width="1/2"][vc_column_text]<p>[gravityform id="2"]</p>[/vc_column_text][vc_raw_html]' . $raw . '[/vc_raw_html][/vc_column][/vc_row]';

		$item  = self::post_item( $content, array( '_wpb_vc_js_status' => 'true' ) );
		$found = self::detect( $item );
		$by    = array_column( $found, null, 'ref' );

		T::same( array( '1', '2', '3' ), self::refs( $found ), 'element, text block and raw HTML forms are found' );
		T::same( Usage::METHOD_BUILDER_ELEMENT, $by['1']['method'], 'shortcode directly in a column is the Gravity Form element' );
		T::ok( false !== strpos( $by['2']['source'], 'Text Block' ), 'text block source' );
		T::ok( false !== strpos( $by['3']['source'], 'Raw HTML' ), 'raw HTML source' );
		T::same( 'WPBakery', $by['1']['builder'], 'attributed to WPBakery' );
		self::cleanup();
	}

	/**
	 * Divi layouts.
	 */
	public static function test_divi() {
		$content = '[et_pb_section fb_built="1"][et_pb_row][et_pb_column type="4_4"]'
			. '[et_pb_code]<div>[gravityform id="3" ajax="true"]</div>[/et_pb_code]'
			. '[dsm_gravity_form gravity_form_id="2" _builder_version="4.0"][/dsm_gravity_form]'
			. '[et_pb_text]Plain text[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]';

		$item  = self::post_item( $content, array( '_et_pb_use_builder' => 'on' ) );
		$found = self::detect( $item );
		$by    = array_column( $found, null, 'ref' );

		T::same( array( '2', '3' ), self::refs( $found ), 'code module shortcode and third-party module are found' );
		T::ok( false !== strpos( $by['3']['source'], 'et_pb_code' ), 'source names the containing module' );
		T::same( Usage::LIKELY, $by['2']['confidence'], 'third-party module is a likely reference' );
		T::same( 'Divi', $by['3']['builder'], 'attributed to Divi' );
		self::cleanup();
	}

	/**
	 * Post meta: ACF and Beaver Builder.
	 */
	public static function test_metadata() {
		$layout = array(
			'abc' => (object) array(
				'type'     => 'module',
				'settings' => (object) array(
					'type' => 'html',
					'html' => '[gravityform id="2"]',
				),
			),
		);

		$item  = self::post_item(
			'<p>Rendered copy [gravityform id="1"]</p>',
			array(
				'_fl_builder_enabled' => '1',
				'_fl_builder_data'    => $layout,
				'sidebar_form'        => 'Contact us: [gravityform id="3"]',
				'_sidebar_form'       => 'field_123abc',
			)
		);
		$found = self::detect( $item );
		$by    = array_column( $found, null, 'ref' );

		T::same( array( '2', '3' ), self::refs( $found ), 'builder meta and custom field are scanned; the rendered post_content copy is not' );
		T::same( 'Beaver Builder', $by['2']['builder'], 'Beaver Builder data attributed to Beaver Builder' );
		T::same( 'ACF', $by['3']['builder'], 'ACF field recognised by its companion key' );
		self::cleanup();
	}

	/**
	 * Synced patterns.
	 */
	public static function test_synced_pattern() {
		$item  = self::post_item( '<!-- wp:gravityforms/form {"formId":3} /-->', array(), 'wp_block' );
		$found = self::detect( $item );

		T::same( Scan_Item::TYPE_PATTERN, $item->type, 'wp_block posts are synced patterns' );
		T::same( array( '3' ), self::refs( $found ), 'form in pattern is found' );
		self::cleanup();
	}

	/**
	 * Widgets.
	 */
	public static function test_widgets() {
		$cases = array(
			array( 'gform_widget', array( 'form_id' => '3' ), array( '3' ) ),
			array( 'text', array( 'text' => '<p>[gravityform id="1"]</p>' ), array( '1' ) ),
			array( 'custom_html', array( 'content' => '[gravityform id="2"]' ), array( '2' ) ),
			array( 'block', array( 'content' => '<!-- wp:gravityforms/form {"formId":"4"} /-->' ), array( '4' ) ),
			array( 'gform_widget', array( 'form_id' => '' ), array() ),
		);

		foreach ( $cases as $case ) {
			$item          = new Scan_Item();
			$item->type    = Scan_Item::TYPE_WIDGET;
			$item->key     = 'widget:' . $case[0] . '-1';
			$item->subtype = $case[0];
			$item->builder = 'Widgets';
			$item->data    = $case[1];
			T::same( $case[2], self::refs( self::detect( $item ) ), $case[0] . ' widget' );
		}
	}

	/**
	 * Theme files are tokenized, not executed.
	 */
	public static function test_theme_files() {
		$path          = __DIR__ . '/fixtures/theme/footer.php';
		$item          = new Scan_Item();
		$item->type    = Scan_Item::TYPE_FILE;
		$item->key     = 'file:fixture/footer.php';
		$item->subtype = 'fixture/footer.php';
		$item->builder = 'Theme';
		$item->content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$ctx   = self::context();
		$found = self::detect( $item, $ctx );

		T::same( array( '1', '2', '2', '3' ), self::refs( $found ), 'gravity_form(), do_shortcode string, inline HTML shortcode and GFForms::get_form() are found; comments, method calls and declarations are ignored' );
		T::same( 1, count( $ctx->notes ), 'gravity_form( $form_id ) is reported as unresolved' );
		T::ok( false !== strpos( $found[0]['source'], 'line' ), 'source includes the line number' );

		$item->subtype = 'fixture/parts/footer.html';
		$item->content = file_get_contents( __DIR__ . '/fixtures/theme/parts/footer.html' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		T::same( array( '3' ), self::refs( self::detect( $item ) ), 'block theme template part' );
	}

	/**
	 * CSV formula injection.
	 */
	public static function test_csv_cells() {
		T::same( "'=HYPERLINK(\"x\")", Export::safe_cell( '=HYPERLINK("x")' ), 'leading = is neutralised' );
		T::same( "'+1", Export::safe_cell( '+1' ), 'leading + is neutralised' );
		T::same( "'-cmd", Export::safe_cell( '-cmd' ), 'leading - is neutralised' );
		T::same( "'@SUM(A1)", Export::safe_cell( '@SUM(A1)' ), 'leading @ is neutralised' );
		T::same( "'\tx", Export::safe_cell( "\tx" ), 'leading tab is neutralised' );
		T::same( 'Contact', Export::safe_cell( 'Contact' ), 'plain text untouched' );
		T::same( '12', Export::safe_cell( 12 ), 'numbers untouched' );
	}
}
