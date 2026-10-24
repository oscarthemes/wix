<?php
/**
 * Settings view.
 *
 * @package Brigade\GravityFormLocator
 *
 * @var array                                            $settings   Current settings.
 * @var \WP_Post_Type[]                                  $post_types Content post types.
 * @var \Brigade\GravityFormLocator\Detectors\Detector[] $detectors  Detectors.
 * @var array[]                                          $history    Scan history.
 */

use Brigade\GravityFormLocator\Admin;
use Brigade\GravityFormLocator\Cron;
use Brigade\GravityFormLocator\Detectors\Detector;
use Brigade\GravityFormLocator\Location_Links;
use Brigade\GravityFormLocator\Settings;

defined( 'ABSPATH' ) || exit;

$bgfl_name = Settings::OPTION;
$bgfl_next = wp_next_scheduled( Cron::SCHEDULED_HOOK );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
$bgfl_notice = isset( $_GET['bgfl_notice'] ) ? sanitize_key( wp_unslash( $_GET['bgfl_notice'] ) ) : '';
$bgfl_saved  = isset( $_GET['settings-updated'] ) && 'true' === sanitize_key( wp_unslash( $_GET['settings-updated'] ) );
// phpcs:enable
$bgfl_errors = get_settings_errors( Settings::OPTION );

/**
 * Prints a Donix toggle switch bound to a checkbox setting.
 *
 * @param string $name        Field name.
 * @param bool   $checked     Whether it is on.
 * @param string $label       Visible label.
 * @param string $description Optional help text.
 * @param string $attrs       Extra attributes (already escaped).
 */
$bgfl_switch = function ( $name, $checked, $label, $description = '', $attrs = '' ) {
	$id = 'bgfl-' . sanitize_key( str_replace( array( '[', ']' ), '-', $name ) );
	?>
	<div class="bgfl-field bgfl-field--switch">
		<label class="bgfl-switch" for="<?php echo esc_attr( $id ); ?>">
			<input type="checkbox" role="switch" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attributes from this file. ?>>
			<span class="bgfl-switch__track" aria-hidden="true"><span class="bgfl-switch__knob"></span></span>
			<span class="bgfl-switch__label"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php if ( '' !== $description ) : ?>
			<p class="bgfl-help"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
	</div>
	<?php
};
?>
<div class="wrap bgfl-wrap bgfl-settings-page">
	<header class="bgfl-header">
		<p class="bgfl-eyebrow"><?php esc_html_e( 'Brigade GF Locator', 'brigade-gravity-form-locator' ); ?></p>
		<h1 class="bgfl-title"><?php esc_html_e( 'Settings', 'brigade-gravity-form-locator' ); ?></h1>
		<p class="bgfl-lead"><?php esc_html_e( 'Choose what is scanned, when scans run, and which detectors read your builder data.', 'brigade-gravity-form-locator' ); ?></p>
	</header>

	<hr class="wp-header-end">

	<?php if ( $bgfl_saved && ! $bgfl_errors ) : ?>
		<div class="bgfl-alert bgfl-alert--success" role="status"><p><?php esc_html_e( 'Settings saved. They apply to the next scan.', 'brigade-gravity-form-locator' ); ?></p></div>
	<?php endif; ?>
	<?php foreach ( $bgfl_errors as $bgfl_error ) : ?>
		<div class="bgfl-alert bgfl-alert--<?php echo 'error' === $bgfl_error['type'] ? 'error' : 'warning'; ?>" role="alert"><p><?php echo esc_html( $bgfl_error['message'] ); ?></p></div>
	<?php endforeach; ?>
	<?php if ( 'reset_done' === $bgfl_notice ) : ?>
		<div class="bgfl-alert bgfl-alert--success" role="status"><p><?php esc_html_e( 'All scan results and history were deleted.', 'brigade-gravity-form-locator' ); ?></p></div>
	<?php elseif ( 'reset_unconfirmed' === $bgfl_notice ) : ?>
		<div class="bgfl-alert bgfl-alert--error" role="alert"><p><?php esc_html_e( 'Nothing was deleted. Tick the confirmation box to reset scan data.', 'brigade-gravity-form-locator' ); ?></p></div>
	<?php endif; ?>

	<nav class="bgfl-tabs bgfl-jump" aria-label="<?php esc_attr_e( 'Settings sections', 'brigade-gravity-form-locator' ); ?>">
		<a class="bgfl-tab" href="#bgfl-section-schedule"><?php esc_html_e( 'Schedule', 'brigade-gravity-form-locator' ); ?></a>
		<a class="bgfl-tab" href="#bgfl-section-content"><?php esc_html_e( 'Content', 'brigade-gravity-form-locator' ); ?></a>
		<a class="bgfl-tab" href="#bgfl-section-detectors"><?php esc_html_e( 'Detectors', 'brigade-gravity-form-locator' ); ?></a>
		<a class="bgfl-tab" href="#bgfl-section-history"><?php esc_html_e( 'History', 'brigade-gravity-form-locator' ); ?></a>
		<a class="bgfl-tab" href="#bgfl-section-reset"><?php esc_html_e( 'Reset', 'brigade-gravity-form-locator' ); ?></a>
	</nav>

	<form method="post" action="options.php" class="bgfl-settings" data-bgfl-settings>
		<?php settings_fields( Settings::GROUP ); ?>

		<section class="bgfl-card bgfl-section" id="bgfl-section-schedule" aria-labelledby="bgfl-h-schedule">
			<div class="bgfl-section__intro">
				<h2 id="bgfl-h-schedule" class="bgfl-card__title"><?php esc_html_e( 'Scheduled scanning', 'brigade-gravity-form-locator' ); ?></h2>
				<p class="bgfl-help"><?php esc_html_e( 'Keep results current without visiting the dashboard. Manual scans are always available.', 'brigade-gravity-form-locator' ); ?></p>
			</div>
			<div class="bgfl-section__body">
				<?php $bgfl_switch( $bgfl_name . '[schedule_enabled]', ! empty( $settings['schedule_enabled'] ), __( 'Scan automatically', 'brigade-gravity-form-locator' ), '', 'data-bgfl-schedule-toggle' ); ?>

				<fieldset class="bgfl-field bgfl-dependent" data-bgfl-schedule-frequency <?php echo empty( $settings['schedule_enabled'] ) ? 'data-off' : ''; ?>>
					<legend class="bgfl-label"><?php esc_html_e( 'Frequency', 'brigade-gravity-form-locator' ); ?></legend>
					<div class="bgfl-segmented">
						<?php
						foreach ( array(
							'daily'  => __( 'Daily', 'brigade-gravity-form-locator' ),
							'weekly' => __( 'Weekly', 'brigade-gravity-form-locator' ),
						) as $bgfl_value => $bgfl_label ) :
							?>
							<label class="bgfl-segmented__option">
								<input type="radio" name="<?php echo esc_attr( $bgfl_name ); ?>[schedule_frequency]" value="<?php echo esc_attr( $bgfl_value ); ?>" <?php checked( $settings['schedule_frequency'], $bgfl_value ); ?>>
								<span><?php echo esc_html( $bgfl_label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<p class="bgfl-help">
					<?php
					if ( $bgfl_next ) {
						/* translators: %s: date and time. */
						printf( esc_html__( 'Next scheduled scan: %s.', 'brigade-gravity-form-locator' ), '<strong>' . esc_html( Admin::format_time( $bgfl_next ) ) . '</strong>' );
						echo ' ';
					}
					esc_html_e( 'Scheduled scans use WP-Cron, which runs when someone visits the site. On low-traffic sites, set up a real server cron job for reliable timing.', 'brigade-gravity-form-locator' );
					?>
				</p>
			</div>
		</section>

		<section class="bgfl-card bgfl-section" id="bgfl-section-content" aria-labelledby="bgfl-h-content">
			<div class="bgfl-section__intro">
				<h2 id="bgfl-h-content" class="bgfl-card__title"><?php esc_html_e( 'What to scan', 'brigade-gravity-form-locator' ); ?></h2>
				<p class="bgfl-help"><?php esc_html_e( 'Narrow the scan to the content you care about. Trashed content and auto-drafts are never scanned.', 'brigade-gravity-form-locator' ); ?></p>
			</div>
			<div class="bgfl-section__body">
				<fieldset class="bgfl-field">
					<legend class="bgfl-label"><?php esc_html_e( 'Post types', 'brigade-gravity-form-locator' ); ?></legend>
					<div class="bgfl-chips">
						<?php foreach ( $post_types as $bgfl_type => $bgfl_object ) : ?>
							<label class="bgfl-chip-check">
								<input type="checkbox" name="<?php echo esc_attr( $bgfl_name ); ?>[post_types][]" value="<?php echo esc_attr( $bgfl_type ); ?>" <?php checked( ! in_array( $bgfl_type, (array) $settings['excluded_post_types'], true ) ); ?>>
								<span><?php echo esc_html( $bgfl_object->labels->name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="bgfl-help"><?php esc_html_e( 'Post types registered later are included automatically.', 'brigade-gravity-form-locator' ); ?></p>
				</fieldset>

				<fieldset class="bgfl-field">
					<legend class="bgfl-label"><?php esc_html_e( 'Post statuses', 'brigade-gravity-form-locator' ); ?></legend>
					<div class="bgfl-chips">
						<?php foreach ( Settings::STATUSES as $bgfl_status ) : ?>
							<label class="bgfl-chip-check">
								<input type="checkbox" name="<?php echo esc_attr( $bgfl_name ); ?>[statuses][]" value="<?php echo esc_attr( $bgfl_status ); ?>" <?php checked( in_array( $bgfl_status, (array) $settings['statuses'], true ) ); ?>>
								<span><?php echo esc_html( Location_Links::status_label( $bgfl_status ) ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<div class="bgfl-field">
					<p class="bgfl-label"><?php esc_html_e( 'Reusable content and templates', 'brigade-gravity-form-locator' ); ?></p>
					<div class="bgfl-stack">
						<?php
						$bgfl_switch( $bgfl_name . '[scan_templates]', ! empty( $settings['scan_templates'] ), __( 'Templates and synced patterns', 'brigade-gravity-form-locator' ), __( 'Synced patterns, site editor templates and parts, and Elementor, Divi, WPBakery, Beaver Builder, Bricks and Oxygen templates.', 'brigade-gravity-form-locator' ) );
						$bgfl_switch( $bgfl_name . '[scan_widgets]', ! empty( $settings['scan_widgets'] ), __( 'Widgets', 'brigade-gravity-form-locator' ), __( 'Every widget assigned to a widget area, including inactive widgets.', 'brigade-gravity-form-locator' ) );
						$bgfl_switch( $bgfl_name . '[scan_theme_files]', ! empty( $settings['scan_theme_files'] ), __( 'Theme files', 'brigade-gravity-form-locator' ), __( 'PHP and HTML files of the active theme and its parent. Files are read, never executed.', 'brigade-gravity-form-locator' ) );
						$bgfl_switch( $bgfl_name . '[possible_references]', ! empty( $settings['possible_references'] ), __( 'Report possible references', 'brigade-gravity-form-locator' ), __( 'Rendered Gravity Forms markup pasted into content. It may be stale, so it is labelled Possible.', 'brigade-gravity-form-locator' ) );
						?>
					</div>
				</div>

				<div class="bgfl-field">
					<label class="bgfl-label" for="bgfl-batch"><?php esc_html_e( 'Batch size', 'brigade-gravity-form-locator' ); ?></label>
					<input type="number" id="bgfl-batch" class="bgfl-input bgfl-input--number" min="5" max="200" name="<?php echo esc_attr( $bgfl_name ); ?>[batch_size]" value="<?php echo esc_attr( $settings['batch_size'] ); ?>" aria-describedby="bgfl-batch-help">
					<p class="bgfl-help" id="bgfl-batch-help"><?php esc_html_e( 'Posts read per database query, from 5 to 200. Lower it if scans run out of memory.', 'brigade-gravity-form-locator' ); ?></p>
				</div>
			</div>
		</section>

		<section class="bgfl-card bgfl-section bgfl-section--stacked" id="bgfl-section-detectors" aria-labelledby="bgfl-h-detectors">
			<div class="bgfl-section__intro">
				<h2 id="bgfl-h-detectors" class="bgfl-card__title"><?php esc_html_e( 'Detectors', 'brigade-gravity-form-locator' ); ?></h2>
				<p class="bgfl-help"><?php esc_html_e( 'Each detector reads one kind of stored data. Builder data is scanned even when the builder is inactive, because layouts stay in the database. Turning a builder detector off skips that builder\'s pages instead of reporting them under another builder.', 'brigade-gravity-form-locator' ); ?></p>
			</div>
			<div class="bgfl-detectors">
				<?php foreach ( $detectors as $bgfl_id => $bgfl_detector ) : ?>
					<?php
					$bgfl_active  = $bgfl_detector->is_integration_active();
					$bgfl_enabled = ! in_array( $bgfl_id, (array) $settings['disabled_detectors'], true );
					$bgfl_input   = 'bgfl-detector-' . $bgfl_id;
					?>
					<div class="bgfl-detector">
						<div class="bgfl-detector__head">
							<label class="bgfl-switch" for="<?php echo esc_attr( $bgfl_input ); ?>">
								<input type="checkbox" role="switch" id="<?php echo esc_attr( $bgfl_input ); ?>" name="<?php echo esc_attr( $bgfl_name ); ?>[detectors][]" value="<?php echo esc_attr( $bgfl_id ); ?>" <?php checked( $bgfl_enabled ); ?> aria-describedby="<?php echo esc_attr( $bgfl_input ); ?>-desc">
								<span class="bgfl-switch__track" aria-hidden="true"><span class="bgfl-switch__knob"></span></span>
								<span class="bgfl-detector__name"><?php echo esc_html( $bgfl_detector->get_label() ); ?></span>
							</label>
						</div>
						<div class="bgfl-detector__badges">
							<?php if ( Detector::SUPPORT_FULL === $bgfl_detector->get_support_level() ) : ?>
								<span class="bgfl-badge bgfl-badge--mint"><?php esc_html_e( 'Supported', 'brigade-gravity-form-locator' ); ?></span>
							<?php else : ?>
								<span class="bgfl-badge bgfl-badge--amber"><?php esc_html_e( 'Partial', 'brigade-gravity-form-locator' ); ?></span>
							<?php endif; ?>
							<?php if ( null === $bgfl_active ) : ?>
								<span class="bgfl-badge"><?php esc_html_e( 'Built in', 'brigade-gravity-form-locator' ); ?></span>
							<?php elseif ( $bgfl_active ) : ?>
								<span class="bgfl-badge"><?php esc_html_e( 'Plugin active', 'brigade-gravity-form-locator' ); ?></span>
							<?php else : ?>
								<span class="bgfl-badge"><?php esc_html_e( 'Plugin not active', 'brigade-gravity-form-locator' ); ?></span>
							<?php endif; ?>
						</div>
						<p class="bgfl-help" id="<?php echo esc_attr( $bgfl_input ); ?>-desc"><?php echo esc_html( $bgfl_detector->get_description() ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="bgfl-card bgfl-section" id="bgfl-section-history" aria-labelledby="bgfl-h-history">
			<div class="bgfl-section__intro">
				<h2 id="bgfl-h-history" class="bgfl-card__title"><?php esc_html_e( 'History', 'brigade-gravity-form-locator' ); ?></h2>
				<p class="bgfl-help"><?php esc_html_e( 'Detailed results are kept for the latest successful scan only. Earlier scans keep a summary of their counts.', 'brigade-gravity-form-locator' ); ?></p>
			</div>
			<div class="bgfl-section__body">
				<div class="bgfl-field">
					<label class="bgfl-label" for="bgfl-history"><?php esc_html_e( 'Scan summaries to keep', 'brigade-gravity-form-locator' ); ?></label>
					<input type="number" id="bgfl-history" class="bgfl-input bgfl-input--number" min="0" max="50" name="<?php echo esc_attr( $bgfl_name ); ?>[history_limit]" value="<?php echo esc_attr( $settings['history_limit'] ); ?>" aria-describedby="bgfl-history-help">
					<p class="bgfl-help" id="bgfl-history-help"><?php esc_html_e( 'From 0 to 50. 0 keeps no history.', 'brigade-gravity-form-locator' ); ?></p>
				</div>

				<?php if ( $history ) : ?>
					<div class="bgfl-table-wrap">
						<table class="bgfl-table">
							<caption class="screen-reader-text"><?php esc_html_e( 'Recent scans', 'brigade-gravity-form-locator' ); ?></caption>
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Finished', 'brigade-gravity-form-locator' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Trigger', 'brigade-gravity-form-locator' ); ?></th>
									<th scope="col" class="num"><?php esc_html_e( 'Forms', 'brigade-gravity-form-locator' ); ?></th>
									<th scope="col" class="num"><?php esc_html_e( 'In use', 'brigade-gravity-form-locator' ); ?></th>
									<th scope="col" class="num"><?php esc_html_e( 'Locations', 'brigade-gravity-form-locator' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Duration', 'brigade-gravity-form-locator' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $history as $bgfl_entry ) : ?>
									<tr>
										<td><?php echo esc_html( Admin::format_time( $bgfl_entry['finished_at'] ) ); ?></td>
										<td><span class="bgfl-badge"><?php echo 'scheduled' === $bgfl_entry['trigger'] ? esc_html__( 'Scheduled', 'brigade-gravity-form-locator' ) : esc_html__( 'Manual', 'brigade-gravity-form-locator' ); ?></span></td>
										<td class="num"><?php echo esc_html( number_format_i18n( (int) $bgfl_entry['stats']['forms_total'] ) ); ?></td>
										<td class="num"><?php echo esc_html( number_format_i18n( (int) $bgfl_entry['stats']['forms_used'] ) ); ?></td>
										<td class="num"><?php echo esc_html( number_format_i18n( (int) $bgfl_entry['stats']['locations'] ) ); ?></td>
										<td><?php echo esc_html( human_time_diff( (int) $bgfl_entry['started_at'], (int) $bgfl_entry['finished_at'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<div class="bgfl-savebar" data-bgfl-savebar>
			<p class="bgfl-savebar__status" data-bgfl-savebar-status aria-live="polite"></p>
			<button type="submit" class="bgfl-button bgfl-button--primary"><?php esc_html_e( 'Save settings', 'brigade-gravity-form-locator' ); ?></button>
		</div>
	</form>

	<section class="bgfl-card bgfl-section bgfl-danger" id="bgfl-section-reset" aria-labelledby="bgfl-h-reset">
		<div class="bgfl-section__intro">
			<h2 id="bgfl-h-reset" class="bgfl-card__title"><?php esc_html_e( 'Reset scan data', 'brigade-gravity-form-locator' ); ?></h2>
			<p class="bgfl-help"><?php esc_html_e( 'Deletes this plugin\'s scan results, scan progress and history. Settings are kept. Gravity Forms, entries, posts, templates and builder layouts are never changed.', 'brigade-gravity-form-locator' ); ?></p>
		</div>
		<div class="bgfl-section__body">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-bgfl-reset>
				<input type="hidden" name="action" value="<?php echo esc_attr( Admin::RESET_ACTION ); ?>">
				<?php wp_nonce_field( Admin::RESET_ACTION ); ?>
				<label class="bgfl-confirm">
					<input type="checkbox" name="bgfl_confirm_reset" value="1" required>
					<span><?php esc_html_e( 'I understand that all scan results and history will be deleted.', 'brigade-gravity-form-locator' ); ?></span>
				</label>
				<button type="submit" class="bgfl-button bgfl-button--danger"><?php esc_html_e( 'Reset scan data', 'brigade-gravity-form-locator' ); ?></button>
			</form>
		</div>
	</section>
</div>
