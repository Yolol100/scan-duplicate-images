<?php
/**
 * Admin menu and React mount point.
 *
 * @package MediaInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the admin menu item.
 */
function media_insight_add_admin_page() {
	add_menu_page(
		esc_html__( 'Media Insight', 'media-insight' ),
		esc_html__( 'Media Insight', 'media-insight' ),
		'manage_options',
		MEDIA_INSIGHT_MENU_SLUG,
		'media_insight_admin_page_content',
		'dashicons-format-image',
		20
	);
}

/**
 * Display the React admin app mount point.
 */
function media_insight_admin_page_content() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'media-insight' ), esc_html__( 'Permission denied', 'media-insight' ), array( 'response' => 403 ) );
	}
	?>
	<div class="wrap media-insight-admin-page">
		<div id="media-insight-root" class="media-insight-react-root"></div>
		<noscript>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'Media Insight requires JavaScript for the progressive scan interface.', 'media-insight' ); ?></p>
			</div>
		</noscript>
	</div>
	<?php
}
