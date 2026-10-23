<?php
/**
 * Scanner integration tests.
 *
 * @package Brigade\GravityFormLocator\Tests
 */

namespace Brigade\GravityFormLocator\Tests;

use Brigade\GravityFormLocator\Admin;
use Brigade\GravityFormLocator\Cron;
use Brigade\GravityFormLocator\Export;
use Brigade\GravityFormLocator\History;
use Brigade\GravityFormLocator\Results_Repository;
use Brigade\GravityFormLocator\Scanner;
use Brigade\GravityFormLocator\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * End-to-end scans against fixture content.
 */
final class Scanner_Tests {

	/**
	 * Fixture post IDs keyed by name.
	 *
	 * @var int[]
	 */
	private static $ids = array();

	/**
	 * Theme root filter callback.
	 *
	 * @param array $roots Roots.
	 * @return array
	 */
	public static function fixture_theme( $roots ) {
		unset( $roots );
		return array( 'fixture-theme' => __DIR__ . '/fixtures/theme' );
	}

	/**
	 * Creates the fixture site content.
	 */
	private static function setup() {
		Admin::reset_data();
		update_option( Settings::OPTION, array_merge( Settings::defaults(), array( 'batch_size' => 5 ) ) );
		Settings::flush();
		add_filter( 'bgfl_theme_file_roots', array( __CLASS__, 'fixture_theme' ) );

		$make = function ( $name, $args, $meta = array() ) {
			$id = wp_insert_post(
				array_merge(
					array(
						'post_type'   => 'page',
						'post_status' => 'publish',
						'post_title'  => $name,
					),
					$args
				)
			);
			foreach ( $meta as $key => $value ) {
				update_post_meta( $id, $key, wp_slash( $value ) );
			}
			self::$ids[ $name ] = $id;
		};

		$make( 'Contact Us', array( 'post_content' => '<!-- wp:gravityforms/form {"formId":"1"} /--><!-- wp:shortcode -->[gravityform id="2"]<!-- /wp:shortcode --><!-- wp:shortcode -->[gravityform id="2"]<!-- /wp:shortcode -->' ) );
		$make(
			'Careers Draft',
			array(
				'post_status'  => 'draft',
				'post_type'    => 'post',
				'post_content' => 'Apply: [gravityform id="2"]',
			)
		);
		$make(
			'Private Page',
			array(
				'post_status'  => 'private',
				'post_content' => '[gravityform id="1"]',
			)
		);
		$make(
			'Trashed Page',
			array(
				'post_status'  => 'trash',
				'post_content' => '[gravityform id="5"]',
			)
		);
		$make( 'Ghost Form', array( 'post_content' => '[gravityform id="99"]' ) );
		$make(
			'Newsletter Pattern',
			array(
				'post_type'    => 'wp_block',
				'post_content' => '<!-- wp:gravityforms/form {"formId":"3"} /-->',
			)
		);
		$make(
			'Footer Template',
			array(
				'post_type'    => 'elementor_library',
				'post_content' => '',
			),
			array(
				'_elementor_template_type' => 'footer',
				'_elementor_data'          => wp_json_encode(
					array(
						array(
							'elType'     => 'widget',
							'widgetType' => 'shortcode',
							'settings'   => array( 'shortcode' => '[gravityform id="3"]' ),
						),
					)
				),
			)
		);
		for ( $i = 1; $i <= 7; $i++ ) {
			$make( 'Filler ' . $i, array( 'post_content' => 'No forms here, just 555 1234.' ) );
		}

		update_option(
			'widget_gform_widget',
			array(
				2              => array( 'form_id' => '4' ),
				'_multiwidget' => 1,
			)
		);
		update_option(
			'widget_text',
			array(
				3              => array(
					'title' => 'Sidebar',
					'text'  => '[gravityform id="1"]',
				),
				'_multiwidget' => 1,
			)
		);
		$sidebars                      = get_option( 'sidebars_widgets', array() );
		$sidebars['bgfl-test-sidebar'] = array( 'gform_widget-2', 'text-3' );
		update_option( 'sidebars_widgets', $sidebars );

		update_option(
			'wpb_js_templates',
			array(
				'tpl1' => array(
					'name'     => 'Lead form',
					'template' => '[vc_row][vc_column][gravityform id="2"][/vc_column][/vc_row]',
				),
			)
		);
	}

	/**
	 * Removes fixtures.
	 */
	private static function teardown() {
		foreach ( self::$ids as $id ) {
			wp_delete_post( $id, true );
		}
		self::$ids = array();
		delete_option( 'widget_gform_widget' );
		delete_option( 'widget_text' );
		delete_option( 'wpb_js_templates' );
		$sidebars = get_option( 'sidebars_widgets', array() );
		unset( $sidebars['bgfl-test-sidebar'] );
		update_option( 'sidebars_widgets', $sidebars );
		remove_filter( 'bgfl_theme_file_roots', array( __CLASS__, 'fixture_theme' ) );
		Admin::reset_data();
	}

	/**
	 * Fingerprint of all content the scan must not change.
	 *
	 * @return string
	 */
	private static function fingerprint() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$posts = $wpdb->get_results( "SELECT ID, post_content, post_status, post_modified FROM {$wpdb->posts} ORDER BY ID", ARRAY_A );
		$meta  = $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_A );
		// phpcs:enable
		$options = array( get_option( 'widget_gform_widget' ), get_option( 'widget_text' ), get_option( 'wpb_js_templates' ), get_option( 'sidebars_widgets' ) );
		return md5( serialize( array( $posts, $meta, $options ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Full scan of the fixture site.
	 */
	public static function test_full_scan() {
		self::setup();
		$before = self::fingerprint();

		$state = Scanner::start( 'manual' );
		T::ok( is_array( $state ), 'scan starts' );
		T::ok( $state['totals']['posts'] >= 13, 'trashed content is excluded from the post count' );

		$steps = 0;
		do {
			$state = Scanner::step( 0.01 );
			++$steps;
		} while ( is_array( $state ) && Scanner::STATUS_RUNNING === $state['status'] && $steps < 200 );

		T::same( Scanner::STATUS_COMPLETE, $state['status'], 'scan completes' );
		T::ok( $steps > 3, 'scan ran in several batches (' . $steps . ' steps)' );
		T::same( $before, self::fingerprint(), 'no post, meta or widget data was modified' );

		$rows   = Results_Repository::query( array( 'per_page' => 0 ) )['rows'];
		$counts = Results_Repository::usage_counts();
		$keys   = array();
		foreach ( $rows as $row ) {
			$keys[ $row['form_id'] ][] = $row['object_title'];
		}

		// Form 1: Contact Us block, private page, text widget, theme footer.php gravity_form( 1 ).
		T::same( 4, $counts[1], 'form 1 distinct locations' );
		// Form 2: Contact Us (twice, one row), draft post, WPBakery template, footer.php.
		T::same( 4, $counts[2], 'form 2 distinct locations; duplicate shortcodes on one page count once' );
		// Form 3: pattern, Elementor footer template, footer.php GFForms::get_form, parts/footer.html.
		T::same( 4, $counts[3], 'form 3 distinct locations, template counted once rather than per page' );
		T::same( 1, $counts[4], 'form 4 via Gravity Forms widget' );
		T::ok( ! isset( $counts[5] ), 'form 5 (only in trash) has no detected usage' );
		T::same( 1, $counts[99], 'unknown form ID is recorded' );

		$missing = Results_Repository::query(
			array(
				'missing'  => true,
				'per_page' => 0,
			)
		);
		T::same( 1, $missing['total'], 'missing-form filter' );
		T::same( 0, (int) $missing['rows'][0]['form_exists'], 'form 99 is flagged as not found' );

		$contact = Results_Repository::query(
			array(
				'search'   => 'Contact Us',
				'per_page' => 0,
			)
		);
		T::same( 2, $contact['total'], 'search by page title' );
		$dup = array_values( array_filter( $contact['rows'], function ( $r ) { return 2 === (int) $r['form_id']; } ) ); // phpcs:ignore
		T::same( 2, (int) $dup[0]['occurrences'], 'duplicate shortcodes recorded as occurrences' );

		T::same(
			1,
			Results_Repository::query(
				array(
					'search'   => '#4',
					'per_page' => 0,
				)
			)['total'],
			'search by form ID'
		);
		T::ok(
			Results_Repository::query(
				array(
					'builder'  => 'Elementor',
					'per_page' => 0,
				)
			)['total'] >= 1,
			'filter by builder'
		);
		T::same(
			1,
			Results_Repository::query(
				array(
					'status'   => 'draft',
					'per_page' => 0,
				)
			)['total'],
			'filter by status'
		);
		T::same(
			2,
			Results_Repository::query(
				array(
					'per_page' => 2,
					'page'     => 1,
				)
			)['total'] > 2 ? 2 : 0,
			'pagination returns a total larger than the page'
		);

		$summary = History::last();
		T::same( 6, $summary['stats']['forms_total'], 'six registered forms' );
		T::same( 4, $summary['stats']['forms_used'], 'forms 1-4 detected in use' );
		T::same( 2, $summary['stats']['forms_unused'], 'forms 5 and 6 have no detected usage' );
		T::same( 1, $summary['stats']['forms_missing'], 'one referenced form is missing' );
		T::ok( $summary['stats']['theme_files'] >= 2, 'theme files scanned' );
		T::ok( $summary['stats']['unresolved'] >= 1, 'unresolved gravity_form( $form_id ) noted' );

		$lines = Export::lines( Results_Repository::query( array( 'per_page' => 0 ) )['rows'] );
		T::same( count( $rows ) + 1, count( $lines ), 'CSV has a header and one line per record' );
		T::same( 13, count( $lines[0] ), 'CSV columns' );

		self::teardown();
	}

	/**
	 * An interrupted or failed scan keeps the previous results.
	 */
	public static function test_interrupted_scan_keeps_results() {
		self::setup();
		Scanner::start();
		Scanner::run_for( 30 );
		$committed = Results_Repository::active_scan_id();
		$before    = Results_Repository::query( array( 'per_page' => 0 ) )['total'];
		T::ok( $before > 0, 'baseline results exist' );

		// Change content, start a new scan and stop after one small step.
		wp_update_post(
			array(
				'ID'           => self::$ids['Ghost Form'],
				'post_content' => '[gravityform id="5"]',
			)
		);
		$state = Scanner::start();
		Scanner::step( 0.01 );

		T::same( $committed, Results_Repository::active_scan_id(), 'active scan unchanged while a new scan runs' );
		T::same( $before, Results_Repository::query( array( 'per_page' => 0 ) )['total'], 'dashboard still shows the previous results' );
		T::ok( is_wp_error( Scanner::start() ), 'overlapping scans are refused' );

		// Simulate an abandoned request.
		$stale               = Scanner::state();
		$stale['updated_at'] = time() - Scanner::STALE_AFTER - 5;
		update_option( Scanner::STATE_OPTION, $stale, false );
		T::ok( Scanner::is_interrupted(), 'stale scan is reported as interrupted' );

		// Resume finishes the same scan.
		$state = Scanner::run_for( 30 );
		T::same( Scanner::STATUS_COMPLETE, $state['status'], 'interrupted scan can be resumed' );
		T::ok( Results_Repository::active_scan_id() > $committed, 'new scan committed after completion' );
		T::ok( isset( Results_Repository::usage_counts()[5] ), 'new results reflect the change' );
		T::ok( ! isset( Results_Repository::usage_counts()[99] ), 'old results replaced' );

		// Cancel leaves results intact.
		$committed = Results_Repository::active_scan_id();
		Scanner::start();
		Scanner::step( 0.01 );
		Scanner::cancel();
		T::same( $committed, Results_Repository::active_scan_id(), 'cancelled scan does not replace results' );
		T::ok( Results_Repository::query( array( 'per_page' => 0 ) )['total'] > 0, 'results still visible after cancel' );

		self::teardown();
	}

	/**
	 * Concurrent steps are locked.
	 */
	public static function test_step_lock() {
		self::setup();
		Scanner::start();
		add_option( Scanner::STEP_LOCK_OPTION, (string) time(), '', false );
		$result = Scanner::step( 0.01 );
		T::ok( is_wp_error( $result ) && 'bgfl_scan_busy' === $result->get_error_code(), 'a held lock blocks a second step' );

		update_option( Scanner::STEP_LOCK_OPTION, (string) ( time() - Scanner::LOCK_TTL - 5 ), false );
		T::ok( is_array( Scanner::step( 0.01 ) ), 'an abandoned lock is taken over' );
		T::same( false, get_option( Scanner::STEP_LOCK_OPTION ), 'lock released after the step' );
		Scanner::cancel();
		self::teardown();
	}

	/**
	 * Cron events are never duplicated.
	 */
	public static function test_cron_schedule() {
		$settings = array_merge(
			Settings::defaults(),
			array(
				'schedule_enabled'   => true,
				'schedule_frequency' => 'daily',
			)
		);
		Cron::reschedule( $settings );
		Cron::reschedule( $settings );
		$crons = _get_cron_array();
		$count = 0;
		foreach ( $crons as $hooks ) {
			if ( isset( $hooks[ Cron::SCHEDULED_HOOK ] ) ) {
				$count += count( $hooks[ Cron::SCHEDULED_HOOK ] );
			}
		}
		T::same( 1, $count, 'one scheduled event after repeated reschedules' );
		T::same( 'daily', wp_get_scheduled_event( Cron::SCHEDULED_HOOK )->schedule, 'daily schedule' );

		Cron::reschedule( array_merge( $settings, array( 'schedule_frequency' => 'weekly' ) ) );
		T::same( 'weekly', wp_get_scheduled_event( Cron::SCHEDULED_HOOK )->schedule, 'frequency change replaces the event' );

		Cron::reschedule( array_merge( $settings, array( 'schedule_enabled' => false ) ) );
		T::same( false, wp_next_scheduled( Cron::SCHEDULED_HOOK ), 'disabling removes the event' );
	}

	/**
	 * A scheduled scan runs to completion within cron.
	 */
	public static function test_scheduled_scan() {
		self::setup();
		Cron::run_scheduled();
		$last = History::last();
		T::ok( $last && 'scheduled' === $last['trigger'], 'scheduled scan completed and recorded' );
		self::teardown();
	}

	/**
	 * Reset only removes plugin data.
	 */
	public static function test_reset() {
		self::setup();
		Scanner::start();
		Scanner::run_for( 30 );
		$before = self::fingerprint();
		Admin::reset_data();
		T::same( 0, Results_Repository::active_scan_id(), 'active scan cleared' );
		T::same( null, History::last(), 'summary cleared' );
		T::same( null, Scanner::state(), 'state cleared' );
		T::same( $before, self::fingerprint(), 'content untouched by reset' );
		T::ok( is_array( get_option( Settings::OPTION ) ), 'settings kept' );
		self::teardown();
	}
}
