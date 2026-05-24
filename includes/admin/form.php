<?php
/**
 * Admin scan and export form rendering.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the scan form.
 *
 * @param array $scan_args Current scan arguments.
 */
function dius_render_scan_form( $scan_args ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . DIUS_MENU_SLUG ) ); ?>" class="dius-form dius-panel components-card" data-dius-scan-form>
		<?php wp_nonce_field( 'dius_scan_duplicate_images', 'dius_scan_nonce' ); ?>
		<input type="hidden" name="dius_scan" value="1" />
		<input type="hidden" name="scan_pages" value="1" />
		<input type="hidden" name="scan_posts" value="1" />

		<div class="dius-panel-header">
			<div>
				<h2><?php esc_html_e( 'Scan setup', 'scan-duplicate-images' ); ?></h2>
				<p><?php esc_html_e( 'Fixed scope: pages use featured image + ACF image/gallery fields. Posts use featured image only.', 'scan-duplicate-images' ); ?></p>
			</div>
		</div>

		<div class="dius-form-grid dius-form-grid-simple">
			<section class="dius-field-card components-card" aria-labelledby="dius-scope-title">
				<h3 id="dius-scope-title"><?php esc_html_e( 'Included', 'scan-duplicate-images' ); ?></h3>
				<ul class="dius-scope-list">
					<li><?php esc_html_e( 'Pages: featured image', 'scan-duplicate-images' ); ?></li>
					<li><?php esc_html_e( 'Pages: ACF image and gallery fields', 'scan-duplicate-images' ); ?></li>
					<li><?php esc_html_e( 'Posts: featured image', 'scan-duplicate-images' ); ?></li>
				</ul>
			</section>

			<section class="dius-field-card components-card" aria-labelledby="dius-limit-title">
				<h3 id="dius-limit-title"><?php esc_html_e( 'Performance guard', 'scan-duplicate-images' ); ?></h3>
				<label for="dius-limit" class="dius-input-label"><?php esc_html_e( 'Optional scan limit', 'scan-duplicate-images' ); ?></label>
				<input id="dius-limit" class="dius-number-input" type="number" min="0" step="1" name="limit" value="<?php echo esc_attr( isset( $scan_args['limit'] ) ? absint( $scan_args['limit'] ) : 0 ); ?>" />
				<p class="dius-muted"><?php esc_html_e( 'Use 0 for all pages and posts. Use a small number for a quick staging smoke test.', 'scan-duplicate-images' ); ?></p>
			</section>
		</div>

		<div class="dius-actions">
			<button type="button" class="button button-primary components-button is-primary dius-primary-button" data-dius-ajax-start hidden><?php esc_html_e( 'Start focused scan', 'scan-duplicate-images' ); ?></button>
			<button type="button" class="button components-button is-secondary dius-stop-button" data-dius-stop-scan hidden disabled><?php esc_html_e( 'Stop scan', 'scan-duplicate-images' ); ?></button>
			<button type="submit" class="button components-button is-secondary" name="submit" value="1"><?php esc_html_e( 'Fallback: scan in one request', 'scan-duplicate-images' ); ?></button>
		</div>

		<div class="dius-progress" data-dius-progress hidden>
			<div class="dius-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-dius-progress-track>
				<span class="dius-progress-bar" data-dius-progress-bar></span>
			</div>
			<p class="dius-progress-text" data-dius-progress-text aria-live="polite"><?php esc_html_e( 'Ready to scan.', 'scan-duplicate-images' ); ?></p>
		</div>
	</form>
	<?php
}


/**
 * Render CSV export form.
 *
 * @param array $args Scan arguments.
 */
function dius_render_export_form( $args ) {
	$scan_id = isset( $args['scan_id'] ) ? sanitize_key( $args['scan_id'] ) : '';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dius-export-form">
		<?php wp_nonce_field( 'dius_export_duplicate_images', 'dius_export_nonce' ); ?>
		<input type="hidden" name="action" value="dius_export_csv" />
		<?php if ( '' !== $scan_id ) : ?>
			<input type="hidden" name="scan_id" value="<?php echo esc_attr( $scan_id ); ?>" />
		<?php endif; ?>
		<input type="hidden" name="scan_pages" value="1" />
		<input type="hidden" name="scan_posts" value="1" />
		<input type="hidden" name="limit" value="<?php echo esc_attr( isset( $args['limit'] ) ? absint( $args['limit'] ) : 0 ); ?>" />
		<button type="submit" class="button components-button is-secondary" name="submit" value="1"><?php esc_html_e( 'Export CSV', 'scan-duplicate-images' ); ?></button>
	</form>
	<?php
}

