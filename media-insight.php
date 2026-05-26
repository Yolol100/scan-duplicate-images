<?php
/**
 * Plugin Name: Media Insight
 * Description: Scans featured images and ACF image/gallery fields for repeated media usage in WordPress content.
 * Version: 4.2.14
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Webactueel
 * Text Domain: media-insight
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MEDIA_INSIGHT_PLUGIN_FILE' ) || function_exists( 'media_insight_get_default_scan_args' ) ) {
	if ( is_admin() && ! function_exists( 'media_insight_bootstrap_conflict_notice' ) ) {
		function media_insight_bootstrap_conflict_notice() {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Media Insight was not loaded because another copy or conflicting version is already active.', 'media-insight' ) . '</p></div>';
		}
		add_action( 'admin_notices', 'media_insight_bootstrap_conflict_notice' );
	}

	return;
}

define( 'MEDIA_INSIGHT_PLUGIN_FILE', __FILE__ );
define( 'MEDIA_INSIGHT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEDIA_INSIGHT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MEDIA_INSIGHT_VERSION', '4.2.14' );
define( 'MEDIA_INSIGHT_MENU_SLUG', 'media-insight' );
define( 'MEDIA_INSIGHT_REST_NAMESPACE', 'media-insight/v2' );
define( 'MEDIA_INSIGHT_REST_BATCH_SIZE', 50 );
define( 'MEDIA_INSIGHT_MAX_SCAN_LIMIT', 50000 );
define( 'MEDIA_INSIGHT_RESULT_BUCKETS', 16 );
define( 'MEDIA_INSIGHT_BACKGROUND_MAX_BATCHES', 8 );
define( 'MEDIA_INSIGHT_BACKGROUND_TIME_BUDGET', 8 );
define( 'MEDIA_INSIGHT_TRANSIENT_TTL', 12 * HOUR_IN_SECONDS );
define( 'MEDIA_INSIGHT_CACHE_GROUP', 'media_insight' );

require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/scanner.php';
require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/cache.php';
require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/workers.php';
require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/rest.php';
require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/admin-page.php';


add_action( 'rest_api_init', 'media_insight_register_rest_routes' );
add_action( 'media_insight_process_scan_event', 'media_insight_process_scan_event', 10, 2 );
register_deactivation_hook( MEDIA_INSIGHT_PLUGIN_FILE, 'media_insight_deactivate_plugin' );

if ( is_admin() ) {
	add_action( 'admin_menu', 'media_insight_add_admin_page' );
	add_action( 'admin_enqueue_scripts', 'media_insight_enqueue_admin_assets' );
	add_action( 'admin_post_media_insight_export_csv', 'media_insight_handle_csv_export' );
}

/**
 * Enqueue the React/Gutenberg admin app only on the plugin page.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 */
function media_insight_enqueue_admin_assets( $hook_suffix ) {
	if ( 'toplevel_page_' . MEDIA_INSIGHT_MENU_SLUG !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style( 'wp-components' );
	wp_enqueue_style(
		'media-insight-admin-styles',
		MEDIA_INSIGHT_PLUGIN_URL . 'assets/styles.css',
		array( 'wp-components' ),
		MEDIA_INSIGHT_VERSION
	);

	$asset_file = MEDIA_INSIGHT_PLUGIN_PATH . 'build/admin-app.asset.php';
	$asset      = file_exists( $asset_file ) ? require $asset_file : array(
		'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-a11y' ),
		'version'      => MEDIA_INSIGHT_VERSION,
	);

	wp_enqueue_script(
		'media-insight-admin-app',
		MEDIA_INSIGHT_PLUGIN_URL . 'build/admin-app.js',
		is_array( $asset['dependencies'] ?? null ) ? $asset['dependencies'] : array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-a11y' ),
		isset( $asset['version'] ) ? $asset['version'] : MEDIA_INSIGHT_VERSION,
		true
	);

	$media_insight_admin_settings = array(
		'restNamespace' => MEDIA_INSIGHT_REST_NAMESPACE,
		'restNonce'     => wp_create_nonce( 'wp_rest' ),
		'adminPostUrl'  => esc_url_raw( admin_url( 'admin-post.php' ) ),
		'exportNonce'   => wp_create_nonce( 'media_insight_export_duplicate_images' ),
		'i18n'          => array(
			'queued'    => __( 'Scan queued.', 'media-insight' ),
			'running'   => __( 'Scanning media usage...', 'media-insight' ),
			'complete'  => __( 'Scan complete.', 'media-insight' ),
			'cancelled' => __( 'Scan cancelled.', 'media-insight' ),
			'failed'    => __( 'The scan failed. Please try again or reduce the scan limit.', 'media-insight' ),
			'exportCsv' => __( 'Export CSV', 'media-insight' ),
		),
	);

	wp_add_inline_script(
		'media-insight-admin-app',
		'window.mediaInsightSettings = ' . wp_json_encode( $media_insight_admin_settings ) . ';',
		'before'
	);

	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'media-insight-admin-app', 'media-insight', MEDIA_INSIGHT_PLUGIN_PATH . 'languages' );
	}
}

/**
 * Build a namespaced transient key for a scan/user pair.
 *
 * @param string   $type    Transient type, such as state or report.
 * @param string   $scan_id Scan UUID.
 * @param int|null $user_id Optional user ID. Defaults to the current user.
 * @return string
 */
function media_insight_get_scan_transient_key( $type, $scan_id, $user_id = null ) {
	$type    = sanitize_key( $type );
	$scan_id = sanitize_key( $scan_id );
	$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );

	return 'media_insight_' . $type . '_' . $user_id . '_' . $scan_id;
}

/**
 * Unschedule pending background events on deactivation.
 */
function media_insight_deactivate_plugin() {
	$events = get_option( 'media_insight_scheduled_scans', array() );

	if ( ! is_array( $events ) ) {
		delete_option( 'media_insight_scheduled_scans' );
		return;
	}

	foreach ( $events as $event ) {
		$scan_id = isset( $event['scan_id'] ) ? sanitize_key( $event['scan_id'] ) : '';
		$user_id = isset( $event['user_id'] ) ? absint( $event['user_id'] ) : 0;

		if ( ! $scan_id || ! $user_id ) {
			continue;
		}

		media_insight_clear_scheduled_scan( $scan_id, $user_id );
	}

	delete_option( 'media_insight_scheduled_scans' );
}
