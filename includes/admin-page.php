<?php
/**
 * Admin page rendering for Image Usage & Duplicate Media Scanner.
 *
 * @package DuplicateImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the admin menu item.
 */
function dius_add_admin_page() {
	add_menu_page(
		esc_html__( 'Image Usage Scanner', 'scan-duplicate-images' ),
		esc_html__( 'Image Usage Scanner', 'scan-duplicate-images' ),
		'manage_options',
		DIUS_MENU_SLUG,
		'dius_admin_page_content',
		'dashicons-format-image',
		20
	);
}

/**
 * Display the admin page content.
 */
function dius_admin_page_content() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'scan-duplicate-images' ) );
	}

	$scan_args   = dius_get_default_scan_args();
	$scan_report = null;
	$should_scan = false;

	if ( filter_input( INPUT_POST, 'dius_scan', FILTER_VALIDATE_BOOLEAN ) ) {
		check_admin_referer( 'dius_scan_duplicate_images', 'dius_scan_nonce' );
		$scan_args   = dius_sanitize_scan_args( $_POST );
		$scan_report = dius_scan_for_duplicate_images( $scan_args );
		$should_scan = true;
	}
	?>
	<div class="wrap dius-admin-page">
		<div class="dius-shell">
			<header class="dius-hero components-card" aria-labelledby="dius-page-title">
				<div>
					<p class="dius-eyebrow"><?php esc_html_e( 'Media audit', 'scan-duplicate-images' ); ?></p>
					<h1 id="dius-page-title"><?php esc_html_e( 'Image Usage & Duplicate Media Scanner', 'scan-duplicate-images' ); ?></h1>
					<p class="dius-hero-copy">
						<?php esc_html_e( 'Find repeated image usage, possible duplicate files, WooCommerce galleries, Elementor image references, Gutenberg block images, and ACF image fields without changing site content.', 'scan-duplicate-images' ); ?>
					</p>
				</div>
				<div class="dius-hero-badges" aria-label="<?php esc_attr_e( 'Plugin safeguards', 'scan-duplicate-images' ); ?>">
					<span class="dius-badge dius-badge-safe"><?php esc_html_e( 'Read-only', 'scan-duplicate-images' ); ?></span>
					<span class="dius-badge"><?php esc_html_e( 'Admin only', 'scan-duplicate-images' ); ?></span>
					<span class="dius-badge"><?php esc_html_e( 'CSV export', 'scan-duplicate-images' ); ?></span>
				</div>
			</header>

			<?php dius_render_value_cards(); ?>
			<?php dius_render_scan_form( $scan_args ); ?>

			<div data-dius-results>
				<?php if ( $should_scan && is_array( $scan_report ) ) : ?>
					<?php dius_display_scan_report( $scan_report ); ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render product value cards.
 */
function dius_render_value_cards() {
	?>
	<div class="dius-value-grid" aria-label="<?php esc_attr_e( 'Audit capabilities', 'scan-duplicate-images' ); ?>">
		<div class="dius-value-card components-card">
			<span class="dashicons dashicons-search"></span>
			<h2><?php esc_html_e( 'Usage map', 'scan-duplicate-images' ); ?></h2>
			<p><?php esc_html_e( 'Shows where the same image is used across scanned content items.', 'scan-duplicate-images' ); ?></p>
		</div>
		<div class="dius-value-card components-card">
			<span class="dashicons dashicons-images-alt2"></span>
			<h2><?php esc_html_e( 'Duplicate media check', 'scan-duplicate-images' ); ?></h2>
			<p><?php esc_html_e( 'Groups possible duplicate media files by dimensions, file size, and hash when files are readable.', 'scan-duplicate-images' ); ?></p>
		</div>
		<div class="dius-value-card components-card">
			<span class="dashicons dashicons-clipboard"></span>
			<h2><?php esc_html_e( 'Actionable report', 'scan-duplicate-images' ); ?></h2>
			<p><?php esc_html_e( 'Separates intentional reuse, cleanup candidates, and items that need manual review.', 'scan-duplicate-images' ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * Render the scan form.
 *
 * @param array $scan_args Current scan arguments.
 */
function dius_render_scan_form( $scan_args ) {
	$post_types = dius_get_scannable_post_types();
	$selected   = isset( $scan_args['post_types'] ) && is_array( $scan_args['post_types'] ) ? $scan_args['post_types'] : array();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . DIUS_MENU_SLUG ) ); ?>" class="dius-form dius-panel components-card" data-dius-scan-form>
		<?php wp_nonce_field( 'dius_scan_duplicate_images', 'dius_scan_nonce' ); ?>
		<input type="hidden" name="dius_scan" value="1" />

		<div class="dius-panel-header">
			<div>
				<h2><?php esc_html_e( 'Scan setup', 'scan-duplicate-images' ); ?></h2>
				<p><?php esc_html_e( 'Choose what the audit should inspect. The scan is read-only and does not delete, replace, or update media.', 'scan-duplicate-images' ); ?></p>
			</div>
		</div>

		<div class="dius-form-grid">
			<section class="dius-field-card components-card" aria-labelledby="dius-post-types-title">
				<h3 id="dius-post-types-title"><?php esc_html_e( 'Content scope', 'scan-duplicate-images' ); ?></h3>
				<p class="dius-muted"><?php esc_html_e( 'Select the public post types to inspect.', 'scan-duplicate-images' ); ?></p>
				<div class="dius-checkbox-grid">
					<?php foreach ( $post_types as $post_type => $label ) : ?>
						<label class="dius-check-tile">
							<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( in_array( $post_type, $selected, true ) ); ?> />
							<span>
								<strong><?php echo esc_html( $label ); ?></strong>
								<code><?php echo esc_html( $post_type ); ?></code>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="dius-field-card components-card" aria-labelledby="dius-sources-title">
				<h3 id="dius-sources-title"><?php esc_html_e( 'Sources', 'scan-duplicate-images' ); ?></h3>
				<div class="dius-source-list">
					<?php dius_render_toggle( 'include_blocks', __( 'Gutenberg block attributes', 'scan-duplicate-images' ), __( 'Images stored inside block attributes.', 'scan-duplicate-images' ), ! empty( $scan_args['include_blocks'] ) ); ?>
					<?php dius_render_toggle( 'include_elementor', __( 'Elementor data', 'scan-duplicate-images' ), __( 'Images stored in Elementor JSON post meta.', 'scan-duplicate-images' ), ! empty( $scan_args['include_elementor'] ) ); ?>
					<?php dius_render_toggle( 'include_woocommerce', __( 'WooCommerce galleries', 'scan-duplicate-images' ), __( 'Product gallery attachment IDs.', 'scan-duplicate-images' ), ! empty( $scan_args['include_woocommerce'] ) ); ?>
					<?php dius_render_toggle( 'include_acf', __( 'ACF fields', 'scan-duplicate-images' ), function_exists( 'get_fields' ) ? __( 'Field-type aware ACF image, gallery, group, repeater, text, and URL scanning.', 'scan-duplicate-images' ) : __( 'ACF is not active on this site.', 'scan-duplicate-images' ), ! empty( $scan_args['include_acf'] ), ! function_exists( 'get_fields' ) ); ?>
					<?php dius_render_toggle( 'include_media_library', __( 'Media library audit', 'scan-duplicate-images' ), __( 'Possible duplicate files and media not found in scanned content.', 'scan-duplicate-images' ), ! empty( $scan_args['include_media_library'] ) ); ?>
				</div>
			</section>

			<section class="dius-field-card components-card" aria-labelledby="dius-limit-title">
				<h3 id="dius-limit-title"><?php esc_html_e( 'Performance guard', 'scan-duplicate-images' ); ?></h3>
				<label for="dius-limit" class="dius-input-label"><?php esc_html_e( 'Optional scan limit', 'scan-duplicate-images' ); ?></label>
				<input id="dius-limit" class="dius-number-input" type="number" min="0" step="1" name="limit" value="<?php echo esc_attr( isset( $scan_args['limit'] ) ? absint( $scan_args['limit'] ) : 0 ); ?>" />
				<p class="dius-muted"><?php esc_html_e( 'Use 0 for all matching items. Use a small number for a quick staging smoke test.', 'scan-duplicate-images' ); ?></p>
			</section>
		</div>

		<div class="dius-actions">
			<button type="button" class="button button-primary components-button is-primary dius-primary-button" data-dius-ajax-start hidden><?php esc_html_e( 'Start audit', 'scan-duplicate-images' ); ?></button>
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
 * Render a toggle row.
 *
 * @param string $name     Input name.
 * @param string $label    Label.
 * @param string $help     Help text.
 * @param bool   $checked  Checked state.
 * @param bool   $disabled Disabled state.
 */
function dius_render_toggle( $name, $label, $help, $checked, $disabled = false ) {
	?>
	<label class="dius-toggle-row">
		<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked && ! $disabled ); ?> <?php disabled( $disabled ); ?> />
		<span>
			<strong><?php echo esc_html( $label ); ?></strong>
			<small><?php echo esc_html( $help ); ?></small>
		</span>
	</label>
	<?php
}

/**
 * Display full scan report.
 *
 * @param array $report Scan report.
 */
function dius_display_scan_report( $report ) {
	$stats       = isset( $report['stats'] ) && is_array( $report['stats'] ) ? $report['stats'] : array();
	$duplicates  = isset( $report['duplicates'] ) && is_array( $report['duplicates'] ) ? $report['duplicates'] : array();
	$args        = isset( $report['args'] ) && is_array( $report['args'] ) ? $report['args'] : array();
	$media_audit = isset( $report['media_audit'] ) && is_array( $report['media_audit'] ) ? $report['media_audit'] : array();
	$groups      = isset( $media_audit['duplicate_groups'] ) && is_array( $media_audit['duplicate_groups'] ) ? $media_audit['duplicate_groups'] : array();
	$unused      = isset( $media_audit['unused_media'] ) && is_array( $media_audit['unused_media'] ) ? $media_audit['unused_media'] : array();
	?>
	<section class="dius-report" aria-labelledby="dius-report-title">
		<div class="dius-panel dius-report-panel components-card">
			<div class="dius-panel-header dius-report-header">
				<div>
					<p class="dius-eyebrow"><?php esc_html_e( 'Audit result', 'scan-duplicate-images' ); ?></p>
					<h2 id="dius-report-title"><?php esc_html_e( 'Scan results', 'scan-duplicate-images' ); ?></h2>
					<p><?php esc_html_e( 'Repeated usage is not automatically wrong. Logos, icons, trust badges, and shared layout assets are often intentionally reused.', 'scan-duplicate-images' ); ?></p>
				</div>
				<?php dius_render_export_form( $args ); ?>
			</div>

			<div class="dius-summary-grid">
				<?php dius_render_stat_card( __( 'Scanned items', 'scan-duplicate-images' ), $stats['scanned_posts'] ?? 0, __( 'Content inspected', 'scan-duplicate-images' ) ); ?>
				<?php dius_render_stat_card( __( 'Image usages', 'scan-duplicate-images' ), $stats['total_usages'] ?? 0, __( 'References found', 'scan-duplicate-images' ) ); ?>
				<?php dius_render_stat_card( __( 'Repeated images', 'scan-duplicate-images' ), $stats['duplicate_images'] ?? 0, __( 'Used on multiple items', 'scan-duplicate-images' ) ); ?>
				<?php dius_render_stat_card( __( 'Duplicate file groups', 'scan-duplicate-images' ), $stats['media_duplicate_groups'] ?? 0, __( 'Media cleanup candidates', 'scan-duplicate-images' ) ); ?>
				<?php dius_render_stat_card( __( 'Not found in scan', 'scan-duplicate-images' ), $stats['possible_unused_media'] ?? 0, __( 'Manual review only', 'scan-duplicate-images' ) ); ?>
			</div>

			<div class="dius-insights">
				<?php dius_render_insight( __( 'Safe interpretation', 'scan-duplicate-images' ), __( 'Do not delete a media item just because it is repeated. Repeated usage usually means the image is shared across content.', 'scan-duplicate-images' ), 'info' ); ?>
				<?php dius_render_insight( __( 'Best cleanup candidates', 'scan-duplicate-images' ), __( 'Duplicate media groups with exact file hashes are the strongest candidates for manual cleanup after backup.', 'scan-duplicate-images' ), 'success' ); ?>
				<?php dius_render_insight( __( 'Manual check needed', 'scan-duplicate-images' ), __( 'Media not found in scanned content may still be used by theme options, builders, CSS, menus, widgets, or external code.', 'scan-duplicate-images' ), 'warning' ); ?>
			</div>
		</div>

		<?php dius_render_repeated_usage_results( $duplicates ); ?>
		<?php dius_render_media_duplicate_groups( $groups ); ?>
		<?php dius_render_unused_media( $unused ); ?>
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
function dius_render_stat_card( $label, $value, $hint = '' ) {
	?>
	<div class="dius-stat-card components-card">
		<span class="dius-stat-value"><?php echo esc_html( number_format_i18n( absint( $value ) ) ); ?></span>
		<span class="dius-stat-label"><?php echo esc_html( $label ); ?></span>
		<?php if ( '' !== $hint ) : ?>
			<span class="dius-stat-hint"><?php echo esc_html( $hint ); ?></span>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render insight callout.
 *
 * @param string $title Title.
 * @param string $copy  Copy.
 * @param string $type  Type.
 */
function dius_render_insight( $title, $copy, $type = 'info' ) {
	?>
	<div class="dius-insight dius-insight-<?php echo esc_attr( sanitize_html_class( $type ) ); ?>">
		<strong><?php echo esc_html( $title ); ?></strong>
		<p><?php echo esc_html( $copy ); ?></p>
	</div>
	<?php
}

/**
 * Render CSV export form.
 *
 * @param array $args Scan arguments.
 */
function dius_render_export_form( $args ) {
	$post_types = isset( $args['post_types'] ) && is_array( $args['post_types'] ) ? $args['post_types'] : array();
	$scan_id    = isset( $args['scan_id'] ) ? sanitize_key( $args['scan_id'] ) : '';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dius-export-form">
		<?php wp_nonce_field( 'dius_export_duplicate_images', 'dius_export_nonce' ); ?>
		<input type="hidden" name="action" value="dius_export_csv" />
		<?php if ( '' !== $scan_id ) : ?>
			<input type="hidden" name="scan_id" value="<?php echo esc_attr( $scan_id ); ?>" />
		<?php endif; ?>
		<?php foreach ( $post_types as $post_type ) : ?>
			<input type="hidden" name="post_types[]" value="<?php echo esc_attr( $post_type ); ?>" />
		<?php endforeach; ?>
		<?php foreach ( array( 'include_blocks', 'include_acf', 'include_elementor', 'include_woocommerce', 'include_media_library' ) as $flag ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $flag ); ?>" value="<?php echo esc_attr( ! empty( $args[ $flag ] ) ? '1' : '0' ); ?>" />
		<?php endforeach; ?>
		<input type="hidden" name="limit" value="<?php echo esc_attr( isset( $args['limit'] ) ? absint( $args['limit'] ) : 0 ); ?>" />
		<button type="submit" class="button components-button is-secondary" name="submit" value="1"><?php esc_html_e( 'Export action CSV', 'scan-duplicate-images' ); ?></button>
	</form>
	<?php
}

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
				<p class="dius-eyebrow"><?php esc_html_e( 'Content usage', 'scan-duplicate-images' ); ?></p>
				<h2 id="dius-reuse-title"><?php esc_html_e( 'Images used on multiple scanned items', 'scan-duplicate-images' ); ?></h2>
			</div>
			<span class="dius-badge"><?php echo esc_html( number_format_i18n( count( $duplicates ) ) ); ?></span>
		</div>

		<?php if ( empty( $duplicates ) ) : ?>
			<div class="dius-empty-state"><?php esc_html_e( 'No images were found on more than one scanned content item.', 'scan-duplicate-images' ); ?></div>
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
							<span class="dius-badge dius-badge-warning"><?php esc_html_e( 'Repeated usage', 'scan-duplicate-images' ); ?></span>
						</div>

						<div class="dius-meta-row">
							<span><?php echo esc_html( sprintf( __( '%s items', 'scan-duplicate-images' ), number_format_i18n( absint( $image['unique_post_count'] ?? 0 ) ) ) ); ?></span>
							<span><?php echo esc_html( sprintf( __( '%s usages', 'scan-duplicate-images' ), number_format_i18n( absint( $image['usage_count'] ?? 0 ) ) ) ); ?></span>
							<?php if ( ! empty( $image['attachment_id'] ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( absint( $image['attachment_id'] ) ) ); ?>"><?php echo esc_html( sprintf( __( 'Attachment #%s', 'scan-duplicate-images' ), absint( $image['attachment_id'] ) ) ); ?></a>
							<?php endif; ?>
						</div>

						<div class="dius-result-columns">
							<div>
								<h4><?php esc_html_e( 'Used on', 'scan-duplicate-images' ); ?></h4>
								<?php dius_render_usage_list( $image['usages'] ?? array(), 'items' ); ?>
							</div>
							<div>
								<h4><?php esc_html_e( 'Detected sources', 'scan-duplicate-images' ); ?></h4>
								<?php dius_render_usage_list( $image['usages'] ?? array(), 'sources' ); ?>
							</div>
						</div>
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
function dius_render_image_preview( $image ) {
	if ( ! empty( $image['attachment_id'] ) ) {
		$thumb = wp_get_attachment_image(
			absint( $image['attachment_id'] ),
			'thumbnail',
			false,
			array(
				'class'   => 'dius-thumb',
				'loading' => 'lazy',
			)
		);

		if ( $thumb ) {
			echo wp_kses_post( $thumb );
			return;
		}
	}

	if ( ! empty( $image['url'] ) && dius_is_safe_admin_preview_url( $image['url'] ) ) {
		printf(
			'<img class="dius-thumb" src="%1$s" alt="%2$s" loading="lazy" />',
			esc_url( $image['url'] ),
			esc_attr( $image['filename'] ?? '' )
		);
		return;
	}

	echo '<span class="dius-thumb dius-thumb-empty" aria-hidden="true"></span>';
}

/**
 * Check whether an image URL is safe to render as an admin preview.
 *
 * External URLs remain linked in the report title, but are not loaded as
 * thumbnails in wp-admin to avoid unnecessary third-party requests.
 *
 * @param string $url Image URL.
 * @return bool
 */
function dius_is_safe_admin_preview_url( $url ) {
	$url = is_string( $url ) ? trim( $url ) : '';

	if ( '' === $url ) {
		return false;
	}

	$host = wp_parse_url( $url, PHP_URL_HOST );

	if ( empty( $host ) ) {
		return true;
	}

	$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

	return is_string( $site_host ) && strtolower( $host ) === strtolower( $site_host );
}

/**
 * Render usage list.
 *
 * @param array  $usages Usage rows.
 * @param string $mode   Rendering mode.
 */
function dius_render_usage_list( $usages, $mode ) {
	if ( empty( $usages ) || ! is_array( $usages ) ) {
		return;
	}

	$seen_items = array();
	?>
	<ul class="dius-usage-list">
		<?php foreach ( $usages as $usage ) : ?>
			<?php
			if ( 'items' === $mode ) {
				$item_key = isset( $usage['post_id'] ) ? absint( $usage['post_id'] ) : 0;

				if ( isset( $seen_items[ $item_key ] ) ) {
					continue;
				}

				$seen_items[ $item_key ] = true;
			}
			?>
			<li>
				<?php if ( 'items' === $mode ) : ?>
					<?php if ( ! empty( $usage['edit_url'] ) ) : ?>
						<a href="<?php echo esc_url( $usage['edit_url'] ); ?>"><?php echo esc_html( $usage['post_title'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $usage['post_title'] ); ?>
					<?php endif; ?>
					<span class="dius-pill"><?php echo esc_html( $usage['post_type'] ); ?></span>
				<?php else : ?>
					<span class="dius-pill"><?php echo esc_html( $usage['source'] ); ?></span>
					<?php echo esc_html( $usage['context'] ); ?>
					<span class="dius-muted">&mdash; <?php echo esc_html( $usage['post_title'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Render media duplicate groups.
 *
 * @param array $groups Duplicate groups.
 */
function dius_render_media_duplicate_groups( $groups ) {
	?>
	<section class="dius-result-section components-card" aria-labelledby="dius-media-duplicates-title">
		<div class="dius-section-heading">
			<div>
				<p class="dius-eyebrow"><?php esc_html_e( 'Media library', 'scan-duplicate-images' ); ?></p>
				<h2 id="dius-media-duplicates-title"><?php esc_html_e( 'Possible duplicate media files', 'scan-duplicate-images' ); ?></h2>
			</div>
			<span class="dius-badge"><?php echo esc_html( number_format_i18n( count( $groups ) ) ); ?></span>
		</div>

		<?php if ( empty( $groups ) ) : ?>
			<div class="dius-empty-state"><?php esc_html_e( 'No possible duplicate media file groups were found.', 'scan-duplicate-images' ); ?></div>
			<?php return; ?>
		<?php endif; ?>

		<div class="dius-media-groups">
			<?php foreach ( $groups as $group ) : ?>
				<article class="dius-media-group-card components-card">
					<div class="dius-result-title-row">
						<h3><?php echo esc_html( sprintf( __( '%s similar files', 'scan-duplicate-images' ), number_format_i18n( absint( $group['count'] ?? 0 ) ) ) ); ?></h3>
						<?php if ( 'exact_file_match' === ( $group['confidence'] ?? '' ) ) : ?>
							<span class="dius-badge dius-badge-success"><?php esc_html_e( 'Exact hash match', 'scan-duplicate-images' ); ?></span>
						<?php else : ?>
							<span class="dius-badge dius-badge-warning"><?php esc_html_e( 'Same size + dimensions', 'scan-duplicate-images' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="dius-media-item-grid">
						<?php foreach ( $group['items'] ?? array() as $item ) : ?>
							<?php dius_render_media_item( $item ); ?>
						<?php endforeach; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * Render an individual media item card.
 *
 * @param array $item Media item.
 */
function dius_render_media_item( $item ) {
	$attachment_id = absint( $item['attachment_id'] ?? 0 );
	?>
	<div class="dius-media-item components-card">
		<div class="dius-result-media">
			<?php
			if ( $attachment_id ) {
				$thumb = wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'class' => 'dius-thumb', 'loading' => 'lazy' ) );
				echo $thumb ? wp_kses_post( $thumb ) : '<span class="dius-thumb dius-thumb-empty" aria-hidden="true"></span>';
			}
			?>
		</div>
		<div>
			<strong><?php echo esc_html( $item['filename'] ?? '' ); ?></strong>
			<div class="dius-meta-row dius-meta-row-small">
				<?php if ( $attachment_id ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $attachment_id ) ); ?>"><?php echo esc_html( sprintf( __( '#%s', 'scan-duplicate-images' ), $attachment_id ) ); ?></a>
				<?php endif; ?>
				<span><?php echo esc_html( dius_format_bytes( $item['file_size'] ?? 0 ) ); ?></span>
				<span><?php echo esc_html( absint( $item['width'] ?? 0 ) . 'x' . absint( $item['height'] ?? 0 ) ); ?></span>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render media not found in scanned content.
 *
 * @param array $items Media items.
 */
function dius_render_unused_media( $items ) {
	$visible_items = array_slice( $items, 0, DIUS_MAX_RENDERED_MEDIA_ITEMS );
	$hidden_count   = max( 0, count( $items ) - count( $visible_items ) );
	?>
	<section class="dius-result-section components-card" aria-labelledby="dius-unused-title">
		<div class="dius-section-heading">
			<div>
				<p class="dius-eyebrow"><?php esc_html_e( 'Manual review', 'scan-duplicate-images' ); ?></p>
				<h2 id="dius-unused-title"><?php esc_html_e( 'Media not found in scanned content', 'scan-duplicate-images' ); ?></h2>
			</div>
			<span class="dius-badge"><?php echo esc_html( number_format_i18n( count( $items ) ) ); ?></span>
		</div>

		<?php if ( empty( $items ) ) : ?>
			<div class="dius-empty-state"><?php esc_html_e( 'No image attachments were flagged as not found in scanned content.', 'scan-duplicate-images' ); ?></div>
			<?php return; ?>
		<?php endif; ?>

		<div class="dius-warning-note">
			<?php esc_html_e( 'These files were not resolved as attachment usage in the selected content scan. They may still be used by theme options, CSS, menus, widgets, forms, builders, or external templates. Treat this as a review list, not a delete list.', 'scan-duplicate-images' ); ?>
		</div>

		<div class="dius-media-item-grid dius-media-item-grid-wide">
			<?php foreach ( $visible_items as $item ) : ?>
				<?php dius_render_media_item( $item ); ?>
			<?php endforeach; ?>
		</div>

		<?php if ( $hidden_count > 0 ) : ?>
			<p class="dius-muted"><?php echo esc_html( sprintf( __( '%s additional items are included in the CSV export.', 'scan-duplicate-images' ), number_format_i18n( $hidden_count ) ) ); ?></p>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Handle CSV export.
 */
function dius_handle_csv_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this report.', 'scan-duplicate-images' ) );
	}

	check_admin_referer( 'dius_export_duplicate_images', 'dius_export_nonce' );

	$scan_id = isset( $_POST['scan_id'] ) ? sanitize_key( wp_unslash( $_POST['scan_id'] ) ) : '';
	$report  = null;

	if ( '' !== $scan_id ) {
		$stored_report = get_transient( dius_get_scan_transient_key( 'report', $scan_id ) );

		if ( is_array( $stored_report ) ) {
			$report = $stored_report;
		}
	}

	if ( ! is_array( $report ) ) {
		$args   = dius_sanitize_scan_args( $_POST );
		$report = dius_scan_for_duplicate_images( $args );
	}

	$duplicates  = isset( $report['duplicates'] ) && is_array( $report['duplicates'] ) ? $report['duplicates'] : array();
	$media_audit = isset( $report['media_audit'] ) && is_array( $report['media_audit'] ) ? $report['media_audit'] : array();
	$groups      = isset( $media_audit['duplicate_groups'] ) && is_array( $media_audit['duplicate_groups'] ) ? $media_audit['duplicate_groups'] : array();
	$unused      = isset( $media_audit['unused_media'] ) && is_array( $media_audit['unused_media'] ) ? $media_audit['unused_media'] : array();
	$filename    = 'image-usage-media-audit-' . gmdate( 'Y-m-d-His' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$output = fopen( 'php://output', 'w' );

	if ( false === $output ) {
		exit;
	}

	fputcsv(
		$output,
		array(
			'report_type',
			'image_key',
			'attachment_id',
			'filename',
			'image_url',
			'unique_item_count',
			'usage_count',
			'post_id',
			'post_title',
			'post_type',
			'edit_url',
			'source',
			'context',
			'file_size_bytes',
			'width',
			'height',
			'confidence',
		)
	);

	foreach ( $duplicates as $image ) {
		$usages = isset( $image['usages'] ) && is_array( $image['usages'] ) ? $image['usages'] : array();

		foreach ( $usages as $usage ) {
			fputcsv(
				$output,
				array(
					'repeated_usage',
					dius_escape_csv_cell( $image['key'] ?? '' ),
					dius_escape_csv_cell( $image['attachment_id'] ?? '' ),
					dius_escape_csv_cell( $image['filename'] ?? '' ),
					dius_escape_csv_cell( $image['url'] ?? '' ),
					dius_escape_csv_cell( $image['unique_post_count'] ?? '' ),
					dius_escape_csv_cell( $image['usage_count'] ?? '' ),
					dius_escape_csv_cell( $usage['post_id'] ?? '' ),
					dius_escape_csv_cell( $usage['post_title'] ?? '' ),
					dius_escape_csv_cell( $usage['post_type'] ?? '' ),
					dius_escape_csv_cell( $usage['edit_url'] ?? '' ),
					dius_escape_csv_cell( $usage['source'] ?? '' ),
					dius_escape_csv_cell( $usage['context'] ?? '' ),
					'',
					'',
					'',
					'reused_in_content',
				)
			);
		}
	}

	foreach ( $groups as $group ) {
		foreach ( $group['items'] ?? array() as $item ) {
			fputcsv(
				$output,
				array(
					'duplicate_media',
					dius_escape_csv_cell( $group['key'] ?? '' ),
					dius_escape_csv_cell( $item['attachment_id'] ?? '' ),
					dius_escape_csv_cell( $item['filename'] ?? '' ),
					dius_escape_csv_cell( $item['url'] ?? '' ),
					'',
					dius_escape_csv_cell( $group['count'] ?? '' ),
					'',
					'',
					'',
					dius_escape_csv_cell( $item['edit_url'] ?? '' ),
					'media_library',
					'possible_duplicate_file',
					dius_escape_csv_cell( $item['file_size'] ?? '' ),
					dius_escape_csv_cell( $item['width'] ?? '' ),
					dius_escape_csv_cell( $item['height'] ?? '' ),
					dius_escape_csv_cell( $group['confidence'] ?? '' ),
				)
			);
		}
	}

	foreach ( $unused as $item ) {
		fputcsv(
			$output,
			array(
				'not_found_in_scanned_content',
				'',
				dius_escape_csv_cell( $item['attachment_id'] ?? '' ),
				dius_escape_csv_cell( $item['filename'] ?? '' ),
				dius_escape_csv_cell( $item['url'] ?? '' ),
				'',
				'',
				'',
				'',
				'',
				dius_escape_csv_cell( $item['edit_url'] ?? '' ),
				'media_library',
				'not_resolved_in_selected_scan',
				dius_escape_csv_cell( $item['file_size'] ?? '' ),
				dius_escape_csv_cell( $item['width'] ?? '' ),
				dius_escape_csv_cell( $item['height'] ?? '' ),
				'manual_review',
			)
		);
	}

	fclose( $output );
	exit;
}

/**
 * Escape CSV cell values to reduce spreadsheet formula-injection risks.
 *
 * @param mixed $value Raw cell value.
 * @return string
 */
function dius_escape_csv_cell( $value ) {
	$value = is_scalar( $value ) ? (string) $value : '';
	$value = wp_check_invalid_utf8( $value );

	if ( '' !== $value && in_array( substr( $value, 0, 1 ), array( '=', '+', '-', '@' ), true ) ) {
		$value = "'" . $value;
	}

	return $value;
}

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
function dius_ajax_start_scan() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to run scans.', 'scan-duplicate-images' ) ), 403 );
	}

	check_ajax_referer( 'dius_ajax_scan', 'nonce' );

	$args    = dius_sanitize_scan_args( $_POST );
	$state   = dius_create_scan_state( $args );
	$scan_id = wp_generate_uuid4();

	set_transient( dius_get_scan_transient_key( 'state', $scan_id ), $state, DIUS_TRANSIENT_TTL );

	wp_send_json_success(
		array(
			'scan_id' => $scan_id,
			'total'   => ! empty( $args['include_media_library'] ) ? max( 1, absint( $state['total'] ?? 0 ) ) : absint( $state['total'] ?? 0 ),
		)
	);
}

/**
 * Process one progressive AJAX scan batch.
 */
function dius_ajax_process_scan_batch() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to run scans.', 'scan-duplicate-images' ) ), 403 );
	}

	check_ajax_referer( 'dius_ajax_scan', 'nonce' );

	$scan_id = isset( $_POST['scan_id'] ) ? sanitize_key( wp_unslash( $_POST['scan_id'] ) ) : '';

	if ( '' === $scan_id ) {
		wp_send_json_error( array( 'message' => __( 'Missing scan ID.', 'scan-duplicate-images' ) ), 400 );
	}

	$state = get_transient( dius_get_scan_transient_key( 'state', $scan_id ) );

	if ( ! is_array( $state ) ) {
		wp_send_json_error( array( 'message' => __( 'The scan state expired. Please start a new scan.', 'scan-duplicate-images' ) ), 410 );
	}

	$state = dius_process_scan_batch( $state, DIUS_AJAX_BATCH_SIZE );
	$done  = ! empty( $state['done'] );
	$html  = '';

	if ( $done ) {
		$report = dius_finalize_scan_state( $state );
		$report['args']['scan_id'] = $scan_id;
		set_transient( dius_get_scan_transient_key( 'report', $scan_id ), $report, DIUS_TRANSIENT_TTL );
		delete_transient( dius_get_scan_transient_key( 'state', $scan_id ) );
		$html = dius_get_report_html( $report );
	} else {
		set_transient( dius_get_scan_transient_key( 'state', $scan_id ), $state, DIUS_TRANSIENT_TTL );
	}

	wp_send_json_success(
		array(
			'done'      => $done,
			'processed' => $done && ! empty( $state['args']['include_media_library'] ) ? max( 1, absint( $state['total'] ?? 0 ) ) : absint( $state['offset'] ?? 0 ),
			'total'     => ! empty( $state['args']['include_media_library'] ) ? max( 1, absint( $state['total'] ?? 0 ) ) : absint( $state['total'] ?? 0 ),
			'html'      => $html,
		)
	);
}

/**
 * Backward-compatible result display wrapper.
 *
 * @param array $results Duplicate results.
 */
function dius_display_results( $results ) {
	dius_display_scan_report(
		array(
			'args'       => dius_get_default_scan_args(),
			'stats'      => array(
				'duplicate_images' => is_array( $results ) ? count( $results ) : 0,
			),
			'duplicates' => is_array( $results ) ? $results : array(),
		)
	);
}
