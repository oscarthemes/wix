<?php
/**
 * Dashboard view.
 *
 * @package Brigade\GravityFormLocator
 *
 * @var array|null                                  $summary Last successful scan summary.
 * @var array|null                                  $state   Current scan state.
 * @var string                                      $view    usages|forms.
 * @var array                                       $filters Request filters.
 * @var \WP_List_Table                              $table   Prepared table.
 */

use Brigade\GravityFormLocator\Admin;
use Brigade\GravityFormLocator\Admin\Forms_Table;
use Brigade\GravityFormLocator\Export;
use Brigade\GravityFormLocator\Forms;
use Brigade\GravityFormLocator\Scan_Context;
use Brigade\GravityFormLocator\Scanner;

defined( 'ABSPATH' ) || exit;

$bgfl_stats       = $summary ? $summary['stats'] : array();
$bgfl_running     = $state && Scanner::STATUS_RUNNING === $state['status'];
$bgfl_interrupted = $bgfl_running && Scanner::is_interrupted( $state );
$bgfl_failed      = $state && Scanner::STATUS_FAILED === $state['status'];
$bgfl_progress    = $state ? Scanner::progress( $state ) : null;
$bgfl_base_url    = admin_url( 'admin.php?page=' . Admin::SLUG );
$bgfl_usage       = Forms_Table::usage_filter();
$bgfl_stat        = function ( $key ) use ( $bgfl_stats ) {
	return isset( $bgfl_stats[ $key ] ) ? number_format_i18n( (int) $bgfl_stats[ $key ] ) : '—';
};
?>
<div class="wrap bgfl-wrap">
	<header class="bgfl-header">
		<p class="bgfl-eyebrow"><?php esc_html_e( 'Brigade GF Locator', 'brigade-gravity-form-locator' ); ?></p>
		<h1 class="bgfl-title"><?php esc_html_e( 'Where your forms are used', 'brigade-gravity-form-locator' ); ?></h1>
		<p class="bgfl-lead"><?php esc_html_e( 'Every Gravity Forms form found in your pages, posts, templates, synced patterns, widgets, theme files and page builder layouts.', 'brigade-gravity-form-locator' ); ?></p>
	</header>

	<hr class="wp-header-end">

	<?php if ( ! Forms::is_available() ) : ?>
		<div class="notice notice-warning bgfl-notice">
			<p><strong><?php esc_html_e( 'Gravity Forms is not active.', 'brigade-gravity-form-locator' ); ?></strong>
			<?php esc_html_e( 'Scans still find form IDs in your content, but form names cannot be shown and unused forms cannot be listed. Activate Gravity Forms for a complete scan.', 'brigade-gravity-form-locator' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $bgfl_failed && ! empty( $state['error'] ) ) : ?>
		<div class="notice notice-error bgfl-notice"><p><?php echo esc_html( $state['error'] ); ?></p></div>
	<?php endif; ?>

	<?php if ( $bgfl_interrupted ) : ?>
		<div class="notice notice-warning bgfl-notice" data-bgfl-interrupted>
			<p><?php esc_html_e( 'A scan was interrupted before it finished. Resume it, or start over. The results below are from the last scan that completed.', 'brigade-gravity-form-locator' ); ?></p>
		</div>
	<?php endif; ?>

	<section class="bgfl-card bgfl-scan" aria-labelledby="bgfl-scan-title">
		<div class="bgfl-scan__info">
			<h2 id="bgfl-scan-title" class="bgfl-card__title"><?php esc_html_e( 'Scan', 'brigade-gravity-form-locator' ); ?></h2>
			<p class="bgfl-muted">
				<?php
				if ( $summary ) {
					printf(
						/* translators: 1: date and time, 2: manual or scheduled. */
						esc_html__( 'Last successful scan: %1$s (%2$s).', 'brigade-gravity-form-locator' ),
						esc_html( Admin::format_time( $summary['finished_at'] ) ),
						'scheduled' === $summary['trigger'] ? esc_html__( 'scheduled', 'brigade-gravity-form-locator' ) : esc_html__( 'manual', 'brigade-gravity-form-locator' )
					);
				} else {
					esc_html_e( 'This site has not been scanned yet.', 'brigade-gravity-form-locator' );
				}
				?>
			</p>
		</div>
		<div class="bgfl-scan__actions">
			<?php if ( $bgfl_running ) : ?>
				<button type="button" class="bgfl-button bgfl-button--primary" data-bgfl-action="resume"><?php esc_html_e( 'Resume scan', 'brigade-gravity-form-locator' ); ?></button>
			<?php else : ?>
				<button type="button" class="bgfl-button bgfl-button--primary" data-bgfl-action="scan"><?php esc_html_e( 'Scan website', 'brigade-gravity-form-locator' ); ?></button>
			<?php endif; ?>
			<button type="button" class="bgfl-button" data-bgfl-action="rescan"><?php esc_html_e( 'Rescan all', 'brigade-gravity-form-locator' ); ?></button>
			<a class="bgfl-button" href="<?php echo esc_url( remove_query_arg( 'paged' ) ); ?>"><?php esc_html_e( 'Refresh results', 'brigade-gravity-form-locator' ); ?></a>
			<?php if ( $summary ) : ?>
				<a class="bgfl-button" href="<?php echo esc_url( Export::url( 'usages' === $view ? $filters : array() ) ); ?>"><?php esc_html_e( 'Export CSV', 'brigade-gravity-form-locator' ); ?></a>
			<?php endif; ?>
			<button type="button" class="bgfl-button bgfl-button--link" data-bgfl-action="cancel" <?php echo $bgfl_running ? '' : 'hidden'; ?>><?php esc_html_e( 'Stop scan', 'brigade-gravity-form-locator' ); ?></button>
		</div>
		<div class="bgfl-progress" data-bgfl-progress <?php echo $bgfl_running && ! $bgfl_interrupted ? '' : 'hidden'; ?>>
			<div class="bgfl-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $bgfl_progress ? $bgfl_progress['percent'] : 0 ); ?>" aria-label="<?php esc_attr_e( 'Scan progress', 'brigade-gravity-form-locator' ); ?>">
				<span class="bgfl-progress__fill" style="width: <?php echo esc_attr( $bgfl_progress ? $bgfl_progress['percent'] : 0 ); ?>%"></span>
			</div>
		</div>
		<p class="bgfl-scan__message" data-bgfl-message role="status" aria-live="polite"><?php echo $bgfl_running && $bgfl_progress && ! $bgfl_interrupted ? esc_html( $bgfl_progress['label'] ) : ''; ?></p>
	</section>

	<section class="bgfl-stats" aria-label="<?php esc_attr_e( 'Summary', 'brigade-gravity-form-locator' ); ?>">
		<div class="bgfl-stat">
			<span class="bgfl-stat__value"><?php echo esc_html( $bgfl_stat( 'forms_total' ) ); ?></span>
			<span class="bgfl-stat__label"><?php esc_html_e( 'Registered forms', 'brigade-gravity-form-locator' ); ?></span>
		</div>
		<div class="bgfl-stat bgfl-stat--accent">
			<span class="bgfl-stat__value"><?php echo esc_html( $bgfl_stat( 'forms_used' ) ); ?></span>
			<span class="bgfl-stat__label"><?php esc_html_e( 'Forms detected in use', 'brigade-gravity-form-locator' ); ?></span>
		</div>
		<a class="bgfl-stat bgfl-stat--link" href="<?php echo esc_url( add_query_arg( array( 'view' => 'forms', 'usage' => 'none' ), $bgfl_base_url ) ); ?>">
			<span class="bgfl-stat__value"><?php echo esc_html( $bgfl_stat( 'forms_unused' ) ); ?></span>
			<span class="bgfl-stat__label"><?php esc_html_e( 'No detected usage', 'brigade-gravity-form-locator' ); ?></span>
		</a>
		<div class="bgfl-stat">
			<span class="bgfl-stat__value"><?php echo esc_html( $bgfl_stat( 'locations' ) ); ?></span>
			<span class="bgfl-stat__label"><?php esc_html_e( 'Distinct usage locations', 'brigade-gravity-form-locator' ); ?></span>
		</div>
		<div class="bgfl-stat">
			<span class="bgfl-stat__value bgfl-stat__value--small"><?php echo esc_html( $summary ? Admin::format_time( $summary['finished_at'] ) : __( 'Never', 'brigade-gravity-form-locator' ) ); ?></span>
			<span class="bgfl-stat__label"><?php esc_html_e( 'Last successful scan', 'brigade-gravity-form-locator' ); ?></span>
		</div>
	</section>

	<?php if ( $summary ) : ?>
		<p class="bgfl-scanned bgfl-muted">
			<?php
			printf(
				/* translators: 1: posts, 2: widgets, 3: saved templates, 4: theme files, 5: skipped items, 6: unresolved references. */
				esc_html__( 'Scanned %1$s posts, pages and templates, %2$s widgets, %3$s saved builder templates and %4$s theme files. %5$s items skipped, %6$s unresolved references.', 'brigade-gravity-form-locator' ),
				esc_html( $bgfl_stat( 'posts' ) ),
				esc_html( $bgfl_stat( 'widgets' ) ),
				esc_html( $bgfl_stat( 'options' ) ),
				esc_html( $bgfl_stat( 'theme_files' ) ),
				esc_html( $bgfl_stat( 'skipped' ) ),
				esc_html( $bgfl_stat( 'unresolved' ) )
			);
			if ( ! empty( $bgfl_stats['forms_possible'] ) ) {
				echo ' ';
				printf(
					/* translators: %s: number of forms. */
					esc_html( _n( '%s form has only possible references.', '%s forms have only possible references.', (int) $bgfl_stats['forms_possible'], 'brigade-gravity-form-locator' ) ),
					esc_html( number_format_i18n( (int) $bgfl_stats['forms_possible'] ) )
				);
			}
			?>
		</p>
	<?php endif; ?>

	<nav class="bgfl-tabs" aria-label="<?php esc_attr_e( 'Result views', 'brigade-gravity-form-locator' ); ?>">
		<a class="bgfl-tab <?php echo 'usages' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( $bgfl_base_url ); ?>" <?php echo 'usages' === $view ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Usage locations', 'brigade-gravity-form-locator' ); ?></a>
		<a class="bgfl-tab <?php echo 'forms' === $view && 'none' !== $bgfl_usage ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'view', 'forms', $bgfl_base_url ) ); ?>" <?php echo 'forms' === $view && 'none' !== $bgfl_usage ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'By form', 'brigade-gravity-form-locator' ); ?></a>
		<a class="bgfl-tab <?php echo 'forms' === $view && 'none' === $bgfl_usage ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'view' => 'forms', 'usage' => 'none' ), $bgfl_base_url ) ); ?>" <?php echo 'forms' === $view && 'none' === $bgfl_usage ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'No detected usage', 'brigade-gravity-form-locator' ); ?></a>
	</nav>

	<div class="bgfl-card bgfl-card--table">
		<form method="get" class="bgfl-table-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( Admin::SLUG ); ?>">
			<?php if ( 'forms' === $view ) : ?>
				<input type="hidden" name="view" value="forms">
				<label class="screen-reader-text" for="bgfl-usage-filter"><?php esc_html_e( 'Usage', 'brigade-gravity-form-locator' ); ?></label>
				<div class="bgfl-inline-filter">
					<select name="usage" id="bgfl-usage-filter">
						<option value="all" <?php selected( $bgfl_usage, 'all' ); ?>><?php esc_html_e( 'All forms', 'brigade-gravity-form-locator' ); ?></option>
						<option value="used" <?php selected( $bgfl_usage, 'used' ); ?>><?php esc_html_e( 'Detected in use', 'brigade-gravity-form-locator' ); ?></option>
						<option value="possible" <?php selected( $bgfl_usage, 'possible' ); ?>><?php esc_html_e( 'Possible references only', 'brigade-gravity-form-locator' ); ?></option>
						<option value="none" <?php selected( $bgfl_usage, 'none' ); ?>><?php esc_html_e( 'No detected usage', 'brigade-gravity-form-locator' ); ?></option>
						<option value="missing" <?php selected( $bgfl_usage, 'missing' ); ?>><?php esc_html_e( 'Referenced but not found', 'brigade-gravity-form-locator' ); ?></option>
					</select>
					<?php submit_button( __( 'Filter', 'brigade-gravity-form-locator' ), '', 'filter_action', false ); ?>
				</div>
				<?php $table->search_box( __( 'Search forms', 'brigade-gravity-form-locator' ), 'bgfl-form-search' ); ?>
			<?php else : ?>
				<?php if ( $filters['form_id'] ) : ?>
					<input type="hidden" name="form_id" value="<?php echo esc_attr( $filters['form_id'] ); ?>">
					<p class="bgfl-chip">
						<?php
						/* translators: %d: form ID. */
						printf( esc_html__( 'Showing form #%d only.', 'brigade-gravity-form-locator' ), (int) $filters['form_id'] );
						?>
						<a href="<?php echo esc_url( remove_query_arg( 'form_id' ) ); ?>"><?php esc_html_e( 'Show all forms', 'brigade-gravity-form-locator' ); ?></a>
					</p>
				<?php endif; ?>
				<?php $table->search_box( __( 'Search forms, pages or URLs', 'brigade-gravity-form-locator' ), 'bgfl-search' ); ?>
			<?php endif; ?>
			<?php $table->display(); ?>
		</form>
	</div>

	<?php if ( $summary && ! empty( $summary['notes'] ) ) : ?>
		<details class="bgfl-card bgfl-notes">
			<summary>
				<?php
				/* translators: %s: number of notes. */
				printf( esc_html__( 'Unresolved references and skipped items (%s)', 'brigade-gravity-form-locator' ), esc_html( number_format_i18n( count( $summary['notes'] ) ) ) );
				?>
			</summary>
			<p class="bgfl-muted"><?php esc_html_e( 'These references could not be matched to a form statically, for example a shortcode whose ID comes from a variable. They are not counted as usages.', 'brigade-gravity-form-locator' ); ?></p>
			<ul>
				<?php foreach ( $summary['notes'] as $bgfl_note ) : ?>
					<li>
						<span class="bgfl-tag"><?php echo Scan_Context::NOTE_SKIPPED === $bgfl_note['kind'] ? esc_html__( 'Skipped', 'brigade-gravity-form-locator' ) : esc_html__( 'Unresolved', 'brigade-gravity-form-locator' ); ?></span>
						<strong><?php echo esc_html( $bgfl_note['title'] ); ?></strong> &mdash; <?php echo esc_html( $bgfl_note['message'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</details>
	<?php endif; ?>

	<p class="bgfl-footnote bgfl-muted">
		<?php esc_html_e( 'Detection is static: the plugin reads stored content, builder data and theme files without rendering pages or running code. Forms output by other plugins\' PHP, JavaScript or remote services cannot be detected. "Possible" references are rendered form markup found in content, which may be stale.', 'brigade-gravity-form-locator' ); ?>
	</p>
</div>
