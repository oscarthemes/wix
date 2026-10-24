<?php
/**
 * Dashboard view.
 *
 * @package Brigade\GravityFormLocator
 *
 * @var array|null     $summary Last successful scan summary.
 * @var array|null     $state   Current scan state.
 * @var string         $view    usages|forms.
 * @var array          $filters Request filters.
 * @var \WP_List_Table $table   Prepared table.
 */

use Brigade\GravityFormLocator\Admin;
use Brigade\GravityFormLocator\Forms;
use Brigade\GravityFormLocator\Scanner;

defined( 'ABSPATH' ) || exit;

$bgfl_running     = $state && Scanner::STATUS_RUNNING === $state['status'];
$bgfl_interrupted = $bgfl_running && Scanner::is_interrupted( $state );
$bgfl_failed      = $state && Scanner::STATUS_FAILED === $state['status'];
$bgfl_progress    = $state ? Scanner::progress( $state ) : null;
$bgfl_export      = Admin::export_url( $summary, $view, $filters );
?>
<div class="wrap bgfl-wrap">
	<header class="bgfl-header">
		<p class="bgfl-eyebrow"><?php esc_html_e( 'Brigade GF Locator', 'brigade-gravity-form-locator' ); ?></p>
		<h1 class="bgfl-title"><?php esc_html_e( 'Where your forms are used', 'brigade-gravity-form-locator' ); ?></h1>
		<p class="bgfl-lead"><?php esc_html_e( 'Every Gravity Forms form found in your pages, posts, templates, synced patterns, widgets, theme files and page builder layouts.', 'brigade-gravity-form-locator' ); ?></p>
	</header>

	<hr class="wp-header-end">

	<?php if ( ! Forms::is_available() ) : ?>
		<div class="bgfl-alert bgfl-alert--warning">
			<p><strong><?php esc_html_e( 'Gravity Forms is not active.', 'brigade-gravity-form-locator' ); ?></strong>
			<?php esc_html_e( 'Scans still find form IDs in your content, but form names cannot be shown and unused forms cannot be listed. Activate Gravity Forms for a complete scan.', 'brigade-gravity-form-locator' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $bgfl_failed && ! empty( $state['error'] ) ) : ?>
		<div class="bgfl-alert bgfl-alert--error" data-bgfl-stale-alert>
			<p><?php echo esc_html( $state['error'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $bgfl_interrupted ) : ?>
		<div class="bgfl-alert bgfl-alert--warning" data-bgfl-stale-alert>
			<p><?php esc_html_e( 'A scan was interrupted before it finished. Resume it, or start over. The results below are from the last scan that completed.', 'brigade-gravity-form-locator' ); ?></p>
		</div>
	<?php endif; ?>

	<section class="bgfl-card bgfl-scan" aria-labelledby="bgfl-scan-title">
		<div class="bgfl-scan__info">
			<h2 id="bgfl-scan-title" class="bgfl-card__title"><?php esc_html_e( 'Scan', 'brigade-gravity-form-locator' ); ?></h2>
			<p class="bgfl-muted" data-bgfl-last-scan><?php echo esc_html( Admin::last_scan_text( $summary ) ); ?></p>
		</div>
		<div class="bgfl-scan__actions">
			<button type="button" class="bgfl-button bgfl-button--primary" data-bgfl-action="<?php echo $bgfl_running ? 'resume' : 'scan'; ?>" data-bgfl-primary>
				<?php echo $bgfl_running ? esc_html__( 'Resume scan', 'brigade-gravity-form-locator' ) : esc_html__( 'Scan website', 'brigade-gravity-form-locator' ); ?>
			</button>
			<button type="button" class="bgfl-button" data-bgfl-action="rescan"><?php esc_html_e( 'Rescan all', 'brigade-gravity-form-locator' ); ?></button>
			<button type="button" class="bgfl-button" data-bgfl-action="refresh"><?php esc_html_e( 'Refresh results', 'brigade-gravity-form-locator' ); ?></button>
			<a class="bgfl-button" data-bgfl-export href="<?php echo esc_url( $bgfl_export ); ?>" <?php echo '' === $bgfl_export ? 'hidden' : ''; ?>><?php esc_html_e( 'Export CSV', 'brigade-gravity-form-locator' ); ?></a>
			<button type="button" class="bgfl-button bgfl-button--link" data-bgfl-action="cancel" <?php echo $bgfl_running ? '' : 'hidden'; ?>><?php esc_html_e( 'Stop scan', 'brigade-gravity-form-locator' ); ?></button>
		</div>
		<div class="bgfl-progress" data-bgfl-progress <?php echo $bgfl_running && ! $bgfl_interrupted ? '' : 'hidden'; ?>>
			<div class="bgfl-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $bgfl_progress ? $bgfl_progress['percent'] : 0 ); ?>" aria-label="<?php esc_attr_e( 'Scan progress', 'brigade-gravity-form-locator' ); ?>">
				<span class="bgfl-progress__fill" style="width: <?php echo esc_attr( $bgfl_progress ? $bgfl_progress['percent'] : 0 ); ?>%"></span>
			</div>
		</div>
		<p class="bgfl-scan__message" data-bgfl-message role="status" aria-live="polite"><?php echo $bgfl_running && $bgfl_progress && ! $bgfl_interrupted ? esc_html( $bgfl_progress['label'] ) : ''; ?></p>
	</section>

	<div class="bgfl-results" data-bgfl-results aria-busy="false">
		<?php require BGFL_PATH . 'admin/views/partials/results.php'; ?>
	</div>

	<p class="bgfl-footnote bgfl-muted">
		<?php esc_html_e( 'Detection is static: the plugin reads stored content, builder data and theme files without rendering pages or running code. Forms output by other plugins\' PHP, JavaScript or remote services cannot be detected. "Possible" references are rendered form markup found in content, which may be stale.', 'brigade-gravity-form-locator' ); ?>
	</p>
</div>
