<?php
/**
 * Admin menu and page controller.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the admin menu item.
 */
function dius_add_admin_page() {
	add_menu_page(
		esc_html__( 'Featured & ACF Images', 'scan-duplicate-images' ),
		esc_html__( 'Featured & ACF Images', 'scan-duplicate-images' ),
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
	$post_data   = dius_get_post_data();

	if ( ! empty( $post_data['dius_scan'] ) ) {
		check_admin_referer( 'dius_scan_duplicate_images', 'dius_scan_nonce' );
		$scan_args   = dius_sanitize_scan_args( $post_data );
		$scan_report = dius_scan_for_duplicate_images( $scan_args );
		$should_scan = true;
	}
	?>
	<div class="wrap dius-admin-page">
		<div class="dius-shell">
			<header class="dius-hero components-card" aria-labelledby="dius-page-title">
				<div>
					<p class="dius-eyebrow"><?php esc_html_e( 'Focused media audit', 'scan-duplicate-images' ); ?></p>
					<h1 id="dius-page-title"><?php esc_html_e( 'Featured & ACF Image Usage Scanner', 'scan-duplicate-images' ); ?></h1>
					<p class="dius-hero-copy">
						<?php esc_html_e( 'Checks featured images on pages/posts and ACF image/gallery fields on pages.', 'scan-duplicate-images' ); ?>
					</p>
				</div>
				<div class="dius-hero-badges" aria-label="<?php esc_attr_e( 'Plugin safeguards', 'scan-duplicate-images' ); ?>">
					<span class="dius-badge dius-badge-safe"><?php esc_html_e( 'Read-only', 'scan-duplicate-images' ); ?></span>
					<span class="dius-badge"><?php esc_html_e( 'Admin only', 'scan-duplicate-images' ); ?></span>
					<span class="dius-badge"><?php esc_html_e( 'Focused scope', 'scan-duplicate-images' ); ?></span>
				</div>
			</header>

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
 * Render the scan form.
 *
 * @param array $scan_args Current scan arguments.
 */
