<?php
/**
 * Settings view.
 *
 * @package Brigade\GravityFormLocator
 *
 * @var array                                                  $settings   Current settings.
 * @var \WP_Post_Type[]                                        $post_types Content post types.
 * @var \Brigade\GravityFormLocator\Detectors\Detector[]       $detectors  Detectors.
 * @var array[]                                                $history    Scan history.
 */

use Brigade\GravityFormLocator\Admin;
use Brigade\GravityFormLocator\Cron;
use Brigade\GravityFormLocator\Detectors\Detector;
use Brigade\GravityFormLocator\Location_Links;
use Brigade\GravityFormLocator\Settings;

defined( 'ABSPATH' ) || exit;

$bgfl_name = Settings::OPTION;
$bgfl_next = wp_next_scheduled( Cron::SCHEDULED_HOOK );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
$bgfl_notice = isset( $_GET['bgfl_notice'] ) ? sanitize_key( wp_unslash( $_GET['bgfl_notice'] ) ) : '';
?>
<div class="wrap bgfl-wrap">
	<header class="bgfl-header">
		<p class="bgfl-eyebrow"><?php esc_html_e( 'Brigade GF Locator', 'brigade-gravity-form-locator' ); ?></p>
		<h1 class="bgfl-title"><?php esc_html_e( 'Settings', 'brigade-gravity-form-locator' ); ?></h1>
	</header>

	<hr class="wp-header-end">

	<?php settings_errors( Settings::OPTION ); ?>

	<?php if ( 'reset_done' === $bgfl_notice ) : ?>
		<div class="notice notice-success is-dismissible bgfl-notice"><p><?php esc_html_e( 'All scan results and history were deleted.', 'brigade-gravity-form-locator' ); ?></p></div>
	<?php elseif ( 'reset_unconfirmed' === $bgfl_notice ) : ?>
		<div class="notice notice-error is-dismissible bgfl-notice"><p><?php esc_html_e( 'Nothing was deleted. Tick the confirmation box to reset scan data.', 'brigade-gravity-form-locator' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="options.php" class="bgfl-settings">
		<?php settings_fields( Settings::GROUP ); ?>

		<section class="bgfl-card">
			<h2 class="bgfl-card__title"><?php esc_html_e( 'Scheduled scanning', 'brigade-gravity-form-locator' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Schedule', 'brigade-gravity-form-locator' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $bgfl_name ); ?>[schedule_enabled]" value="1" <?php checked( $settings['schedule_enabled'] ); ?>>
							<?php esc_html_e( 'Scan automatically', 'brigade-gravity-form-locator' ); ?>
						</label>
						<br>
						<label class="screen-reader-text" for="bgfl-frequency"><?php esc_html_e( 'Frequency', 'brigade-gravity-form-locator' ); ?></label>
						<select id="bgfl-frequency" name="<?php echo esc_attr( $bgfl_name ); ?>[schedule_frequency]">
							<option value="daily" <?php selected( $settings['schedule_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'brigade-gravity-form-locator' ); ?></option>
							<option value="weekly" <?php selected( $settings['schedule_frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'brigade-gravity-form-locator' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'When unticked, scans run only when you click Scan website. Scheduled scans use WP-Cron, which runs when someone visits the site; on low-traffic sites set up a real server cron job for reliable timing.', 'brigade-gravity-form-locator' ); ?>
						</p>
						<?php if ( $bgfl_next ) : ?>
							<p class="description">
								<?php
								/* translators: %s: date and time. */
								printf( esc_html__( 'Next scheduled scan: %s.', 'brigade-gravity-form-locator' ), esc_html( Admin::format_time( $bgfl_next ) ) );
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</section>

		<section class="bgfl-card">
			<h2 class="bgfl-card__title"><?php esc_html_e( 'What to scan', 'brigade-gravity-form-locator' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Post types', 'brigade-gravity-form-locator' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Post types', 'brigade-gravity-form-locator' ); ?></legend>
							<?php foreach ( $post_types as $bgfl_type => $bgfl_object ) : ?>
								<label class="bgfl-check">
									<input type="checkbox" name="<?php echo esc_attr( $bgfl_name ); ?>[post_types][]" value="<?php echo esc_attr( $bgfl_type ); ?>" <?php checked( ! in_array( $bgfl_type, (array) $settings['excluded_post_types'], true ) ); ?>>
									<?php echo esc_html( $bgfl_object->labels->name ); ?> <code><?php echo esc_html( $bgfl_type ); ?></code>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Post types registered later are included automatically.', 'brigade-gravity-form-locator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Post statuses', 'brigade-gravity-form-locator' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Post statuses', 'brigade-gravity-form-locator' ); ?></legend>
							<?php foreach ( Settings::STATUSES as $bgfl_status ) : ?>
								<label class="bgfl-check">
									<input type="checkbox" name="<?php echo esc_attr( $bgfl_name ); ?>[statuses][]" value="<?php echo esc_attr( $bgfl_status ); ?>" <?php checked( in_array( $bgfl_status, (array) $settings['statuses'], true ) ); ?>>
									<?php echo esc_html( Location_Links::status_label( $bgfl_status ) ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Trashed content and auto-drafts are never scanned.', 'brigade-gravity-form-locator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Reusable content and templates', 'brigade-gravity-form-locator' ); ?></th>
					<td>
						<label class="bgfl-check">
							<input type="checkbox" name="<?php echo esc_attr( $bgfl_name ); ?>[scan_templates]" value="1" <?php checked( $settings['scan_templates'] ); ?>>
							<?php esc_html_e( 'Synced patterns, site editor templates and parts, and builder templates (Elementor, Divi, WPBakery, Beaver Builder, Bricks, Oxygen)', 'brigade-gravity-form-locator' ); ?>
						</label>
						<label class="bgfl-check">
							<input type="checkbox" name="<?php echo esc_attr( $bgfl_name ); ?>[scan_widgets]" value="1" <?php checked( $settings['scan_widgets'] ); ?>>
							<?php esc_html_e( 'Widgets in widget areas', 'brigade-gravity-form-locator' ); ?>
						</label>
						<label class="bgfl-check">
							<input type="checkbox" name="<?php echo esc_attr( $bgfl_name ); ?>[scan_theme_files]" value="1" <?php checked( $settings['scan_theme_files'] ); ?>>
							<?php esc_html_e( 'Active theme and parent theme files (read only, never executed)', 'brigade-gravity-form-locator' ); ?>
						</label>
						<label class="bgfl-check">
							<input type="checkbox" name="<?php echo esc_attr( $bgfl_name ); ?>[possible_references]" value="1" <?php checked( $settings['possible_references'] ); ?>>
							<?php esc_html_e( 'Report possible references (rendered form markup pasted into content)', 'brigade-gravity-form-locator' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bgfl-batch"><?php esc_html_e( 'Batch size', 'brigade-gravity-form-locator' ); ?></label></th>
					<td>
						<input type="number" id="bgfl-batch" class="small-text" min="5" max="200" name="<?php echo esc_attr( $bgfl_name ); ?>[batch_size]" value="<?php echo esc_attr( $settings['batch_size'] ); ?>">
						<p class="description"><?php esc_html_e( 'Posts read per database query. Lower it if scans run out of memory.', 'brigade-gravity-form-locator' ); ?></p>
					</td>
				</tr>
			</table>
		</section>

		<section class="bgfl-card">
			<h2 class="bgfl-card__title"><?php esc_html_e( 'Detectors', 'brigade-gravity-form-locator' ); ?></h2>
			<p class="bgfl-muted"><?php esc_html_e( 'Each detector reads one kind of stored data. Builder data is scanned even when the builder plugin is inactive, because layouts stay in the database. Disabling a builder detector skips that builder\'s pages entirely instead of reporting them under another builder.', 'brigade-gravity-form-locator' ); ?></p>
			<table class="widefat striped bgfl-detectors">
				<thead>
					<tr>
						<th scope="col" class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Enabled', 'brigade-gravity-form-locator' ); ?></span></th>
						<th scope="col"><?php esc_html_e( 'Detector', 'brigade-gravity-form-locator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Support', 'brigade-gravity-form-locator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Plugin or theme', 'brigade-gravity-form-locator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What it reads', 'brigade-gravity-form-locator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $detectors as $bgfl_id => $bgfl_detector ) : ?>
						<?php $bgfl_active = $bgfl_detector->is_integration_active(); ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" id="bgfl-detector-<?php echo esc_attr( $bgfl_id ); ?>" name="<?php echo esc_attr( $bgfl_name ); ?>[detectors][]" value="<?php echo esc_attr( $bgfl_id ); ?>" <?php checked( ! in_array( $bgfl_id, (array) $settings['disabled_detectors'], true ) ); ?>>
							</th>
							<td><label for="bgfl-detector-<?php echo esc_attr( $bgfl_id ); ?>"><strong><?php echo esc_html( $bgfl_detector->get_label() ); ?></strong></label></td>
							<td>
								<?php if ( Detector::SUPPORT_FULL === $bgfl_detector->get_support_level() ) : ?>
									<span class="bgfl-status bgfl-status--publish"><?php esc_html_e( 'Supported', 'brigade-gravity-form-locator' ); ?></span>
								<?php else : ?>
									<span class="bgfl-status bgfl-status--draft"><?php esc_html_e( 'Partial', 'brigade-gravity-form-locator' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								if ( null === $bgfl_active ) {
									esc_html_e( 'Built in', 'brigade-gravity-form-locator' );
								} elseif ( $bgfl_active ) {
									esc_html_e( 'Active', 'brigade-gravity-form-locator' );
								} else {
									esc_html_e( 'Not active (stored data is still scanned)', 'brigade-gravity-form-locator' );
								}
								?>
							</td>
							<td class="bgfl-muted"><?php echo esc_html( $bgfl_detector->get_description() ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>

		<section class="bgfl-card">
			<h2 class="bgfl-card__title"><?php esc_html_e( 'History', 'brigade-gravity-form-locator' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bgfl-history"><?php esc_html_e( 'Keep scan summaries', 'brigade-gravity-form-locator' ); ?></label></th>
					<td>
						<input type="number" id="bgfl-history" class="small-text" min="0" max="50" name="<?php echo esc_attr( $bgfl_name ); ?>[history_limit]" value="<?php echo esc_attr( $settings['history_limit'] ); ?>">
						<p class="description"><?php esc_html_e( 'Number of past scan summaries (counts only) to keep. Detailed results are kept for the latest successful scan only. 0 keeps no history.', 'brigade-gravity-form-locator' ); ?></p>
					</td>
				</tr>
			</table>
		</section>

		<?php submit_button(); ?>
	</form>

	<?php if ( $history ) : ?>
		<section class="bgfl-card">
			<h2 class="bgfl-card__title"><?php esc_html_e( 'Recent scans', 'brigade-gravity-form-locator' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Finished', 'brigade-gravity-form-locator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Trigger', 'brigade-gravity-form-locator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Forms', 'brigade-gravity-form-locator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'In use', 'brigade-gravity-form-locator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Locations', 'brigade-gravity-form-locator' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Duration', 'brigade-gravity-form-locator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $bgfl_entry ) : ?>
						<tr>
							<td><?php echo esc_html( Admin::format_time( $bgfl_entry['finished_at'] ) ); ?></td>
							<td><?php echo 'scheduled' === $bgfl_entry['trigger'] ? esc_html__( 'Scheduled', 'brigade-gravity-form-locator' ) : esc_html__( 'Manual', 'brigade-gravity-form-locator' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $bgfl_entry['stats']['forms_total'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $bgfl_entry['stats']['forms_used'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $bgfl_entry['stats']['locations'] ) ); ?></td>
							<td><?php echo esc_html( human_time_diff( (int) $bgfl_entry['started_at'], (int) $bgfl_entry['finished_at'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<section class="bgfl-card bgfl-danger">
		<h2 class="bgfl-card__title"><?php esc_html_e( 'Reset scan data', 'brigade-gravity-form-locator' ); ?></h2>
		<p><?php esc_html_e( 'Deletes this plugin\'s scan results, scan progress and history. Settings are kept. Gravity Forms, entries, posts, templates and builder layouts are never changed.', 'brigade-gravity-form-locator' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-bgfl-reset>
			<input type="hidden" name="action" value="<?php echo esc_attr( Admin::RESET_ACTION ); ?>">
			<?php wp_nonce_field( Admin::RESET_ACTION ); ?>
			<label class="bgfl-check">
				<input type="checkbox" name="bgfl_confirm_reset" value="1" required>
				<?php esc_html_e( 'I understand that all scan results and history will be deleted.', 'brigade-gravity-form-locator' ); ?>
			</label>
			<?php submit_button( __( 'Reset scan data', 'brigade-gravity-form-locator' ), 'delete', 'submit', false ); ?>
		</form>
	</section>
</div>
