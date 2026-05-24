<?php
/**
 * Admin report rendering.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display full scan report.
 *
 * @param array $report Scan report.
 */
function dius_display_scan_report( $report ) {
	$stats      = isset( $report['stats'] ) && is_array( $report['stats'] ) ? $report['stats'] : array();
	$duplicates = isset( $report['duplicates'] ) && is_array( $report['duplicates'] ) ? $report['duplicates'] : array();
	$args       = isset( $report['args'] ) && is_array( $report['args'] ) ? $report['args'] : array();
	?>
	<section class="dius-report" aria-labelledby="dius-report-title">
		<div class="dius-panel dius-report-panel components-card">
			<div class="dius-panel-header dius-report-header">
				<div>
					<p class="dius-eyebrow"><?php esc_html_e( 'Audit result', 'scan-duplicate-images' ); ?></p>
					<h2 id="dius-report-title"><?php esc_html_e( 'Focused scan results', 'scan-duplicate-images' ); ?></h2>
					<p><?php esc_html_e( 'Only featured images and ACF image/gallery fields are included in these results.', 'scan-duplicate-images' ); ?></p>
				</div>
				<?php dius_render_export_form( $args ); ?>
			</div>

			<div class="dius-summary-grid dius-summary-grid-simple">
				<?php dius_render_stat_card( __( 'Scanned items', 'scan-duplicate-images' ), $stats['scanned_items'] ?? 0, __( 'Pages and posts', 'scan-duplicate-images' ) ); ?>
				<?php dius_render_stat_card( __( 'Images found', 'scan-duplicate-images' ), $stats['unique_images'] ?? 0, __( 'Featured + ACF', 'scan-duplicate-images' ) ); ?>
				<?php dius_render_stat_card( __( 'Repeated images', 'scan-duplicate-images' ), $stats['repeated_images'] ?? 0, __( 'Needs review', 'scan-duplicate-images' ) ); ?>
			</div>

			<div class="dius-summary-meta">
				<span><strong><?php echo esc_html( number_format_i18n( absint( $stats['featured_usages'] ?? 0 ) ) ); ?></strong> <?php esc_html_e( 'featured image references', 'scan-duplicate-images' ); ?></span>
				<span><strong><?php echo esc_html( number_format_i18n( absint( $stats['acf_page_usages'] ?? 0 ) ) ); ?></strong> <?php esc_html_e( 'ACF page image references', 'scan-duplicate-images' ); ?></span>
			</div>

			<?php if ( empty( $stats['acf_enabled'] ) ) : ?>
				<div class="dius-inline-note dius-inline-note-warning"><?php esc_html_e( 'ACF is not active, so only featured images were scanned.', 'scan-duplicate-images' ); ?></div>
			<?php endif; ?>
		</div>

		<?php dius_render_repeated_usage_results( $duplicates ); ?>
	</section>
	<?php
}

/**
 * Render a stat card.
 *
 * @param string $label Label.
 * @param int    $value Value.
 * @param string $hint  Hint.
 */

/**
 * Render a stat card.
 *
 * @param string $label Label.
 * @param int    $value Value.
 * @param string $hint  Hint.
 */

/**
 * Render repeated usage results.
 *
 * @param array $duplicates Duplicate results.
 */
function dius_render_repeated_usage_results( $duplicates ) {
	?>
	<section class="dius-result-section components-card" aria-labelledby="dius-reuse-title">
		<div class="dius-section-heading">
			<div>
				<p class="dius-eyebrow"><?php esc_html_e( 'Repeated images', 'scan-duplicate-images' ); ?></p>
				<h2 id="dius-reuse-title"><?php esc_html_e( 'Same featured or ACF image used more than once', 'scan-duplicate-images' ); ?></h2>
			</div>
			<span class="dius-badge"><?php echo esc_html( number_format_i18n( count( $duplicates ) ) ); ?></span>
		</div>

		<?php if ( empty( $duplicates ) ) : ?>
			<div class="dius-empty-state"><?php esc_html_e( 'No repeated featured or ACF page images were found.', 'scan-duplicate-images' ); ?></div>
			<?php return; ?>
		<?php endif; ?>

		<div class="dius-results-list">
			<?php foreach ( $duplicates as $image ) : ?>
				<article class="dius-result-card components-card">
					<div class="dius-result-media">
						<?php dius_render_image_preview( $image ); ?>
					</div>
					<div class="dius-result-main">
						<div class="dius-result-title-row">
							<h3>
								<?php if ( ! empty( $image['url'] ) ) : ?>
									<a href="<?php echo esc_url( $image['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $image['filename'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $image['filename'] ); ?>
								<?php endif; ?>
							</h3>
							<span class="dius-badge dius-badge-warning"><?php esc_html_e( 'Repeated', 'scan-duplicate-images' ); ?></span>
						</div>

						<div class="dius-meta-row">
							<span><?php echo esc_html( sprintf( __( '%s pages/posts', 'scan-duplicate-images' ), number_format_i18n( absint( $image['unique_post_count'] ?? 0 ) ) ) ); ?></span>
							<span><?php echo esc_html( sprintf( __( '%s references', 'scan-duplicate-images' ), number_format_i18n( absint( $image['usage_count'] ?? 0 ) ) ) ); ?></span>
							<?php if ( ! empty( $image['attachment_id'] ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( absint( $image['attachment_id'] ) ) ); ?>"><?php echo esc_html( sprintf( __( 'Attachment #%s', 'scan-duplicate-images' ), absint( $image['attachment_id'] ) ) ); ?></a>
							<?php endif; ?>
						</div>

						<h4><?php esc_html_e( 'Used on', 'scan-duplicate-images' ); ?></h4>
						<?php dius_render_usage_list( $image['usages'] ?? array() ); ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * Render preview image.
 *
 * @param array $image Image result.
 */

/**
 * Render preview image.
 *
 * @param array $image Image result.
 */

/**
 * Return a report as HTML for AJAX responses.
 *
 * @param array $report Scan report.
 * @return string
 */
function dius_get_report_html( $report ) {
	ob_start();
	dius_display_scan_report( $report );

	return (string) ob_get_clean();
}

/**
 * Start a progressive AJAX scan.
 */
