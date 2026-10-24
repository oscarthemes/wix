<?php
/**
 * Results region of the dashboard: summary, view tabs, table and notes.
 * Rendered on page load and by the bgfl_results AJAX endpoint.
 *
 * @package Brigade\GravityFormLocator
 *
 * @var array|null     $summary Last successful scan summary.
 * @var string         $view    usages|forms.
 * @var array          $filters Request filters.
 * @var \WP_List_Table $table   Prepared table.
 */

use Brigade\GravityFormLocator\Admin;
use Brigade\GravityFormLocator\Admin\Forms_Table;
use Brigade\GravityFormLocator\Scan_Context;

defined( 'ABSPATH' ) || exit;

$bgfl_stats    = $summary ? $summary['stats'] : array();
$bgfl_base_url = admin_url( 'admin.php?page=' . Admin::SLUG );
$bgfl_usage    = Forms_Table::usage_filter();
$bgfl_stat     = function ( $key ) use ( $bgfl_stats ) {
	return isset( $bgfl_stats[ $key ] ) ? number_format_i18n( (int) $bgfl_stats[ $key ] ) : '—';
};
?>
<section class="bgfl-stats" aria-label="<?php esc_attr_e( 'Summary', 'brigade-gravity-form-locator' ); ?>">
	<div class="bgfl-stat">
		<span class="bgfl-stat__value"><?php echo esc_html( $bgfl_stat( 'forms_total' ) ); ?></span>
		<span class="bgfl-stat__label"><?php esc_html_e( 'Registered forms', 'brigade-gravity-form-locator' ); ?></span>
	</div>
	<div class="bgfl-stat bgfl-stat--accent">
		<span class="bgfl-stat__value"><?php echo esc_html( $bgfl_stat( 'forms_used' ) ); ?></span>
		<span class="bgfl-stat__label"><?php esc_html_e( 'Forms detected in use', 'brigade-gravity-form-locator' ); ?></span>
	</div>
	<a class="bgfl-stat bgfl-stat--link" href="
	<?php
	echo esc_url(
		add_query_arg(
			array(
				'view'  => 'forms',
				'usage' => 'none',
			),
			$bgfl_base_url
		)
	);
	?>
	">
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
	<a class="bgfl-tab <?php echo 'forms' === $view && 'none' === $bgfl_usage ? 'is-active' : ''; ?>" href="
	<?php
	echo esc_url(
		add_query_arg(
			array(
				'view'  => 'forms',
				'usage' => 'none',
			),
			$bgfl_base_url
		)
	);
	?>
	" <?php echo 'forms' === $view && 'none' === $bgfl_usage ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'No detected usage', 'brigade-gravity-form-locator' ); ?></a>
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
