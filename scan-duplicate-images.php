<?php
/**
 * Plugin Name: Image Usage & Duplicate Media Scanner
 * Description: Audits repeated image usage, possible duplicate media files, featured images, Gutenberg blocks, Elementor data, WooCommerce galleries, and ACF fields.
 * Version: 3.2.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Webactueel
 * Text Domain: scan-duplicate-images
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'DIUS_PLUGIN_FILE' ) || function_exists( 'dius_get_scannable_post_types' ) ) {
	if ( is_admin() && ! function_exists( 'dius_bootstrap_conflict_notice' ) ) {
		/**
		 * Show a controlled notice instead of causing a fatal error when another copy is active.
		 */
		function dius_bootstrap_conflict_notice() {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Image Usage & Duplicate Media Scanner was not loaded because another copy or conflicting version is already active.', 'scan-duplicate-images' ) . '</p></div>';
		}
		add_action( 'admin_notices', 'dius_bootstrap_conflict_notice' );
	}

	return;
}

define( 'DIUS_PLUGIN_FILE', __FILE__ );
define( 'DIUS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'DIUS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DIUS_VERSION', '3.2.1' );
define( 'DIUS_MENU_SLUG', 'image-usage-scanner' );
define( 'DIUS_AJAX_BATCH_SIZE', 25 );
define( 'DIUS_TRANSIENT_TTL', HOUR_IN_SECONDS );
define( 'DIUS_MAX_RENDERED_MEDIA_ITEMS', 80 );

require_once DIUS_PLUGIN_PATH . 'includes/scanner.php';
require_once DIUS_PLUGIN_PATH . 'includes/admin-page.php';

/**
 * Load translations.
 */
function dius_load_textdomain() {
	load_plugin_textdomain( 'scan-duplicate-images', false, dirname( plugin_basename( DIUS_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'dius_load_textdomain' );

if ( is_admin() ) {
	add_action( 'admin_menu', 'dius_add_admin_page' );
	add_action( 'admin_enqueue_scripts', 'dius_enqueue_admin_assets' );
	add_action( 'admin_post_dius_export_csv', 'dius_handle_csv_export' );
	add_action( 'wp_ajax_dius_start_scan', 'dius_ajax_start_scan' );
	add_action( 'wp_ajax_dius_process_scan_batch', 'dius_ajax_process_scan_batch' );
}

/**
 * Enqueue admin assets only on the plugin admin page.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 */
function dius_enqueue_admin_assets( $hook_suffix ) {
	if ( 'toplevel_page_' . DIUS_MENU_SLUG !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'dius-admin-styles',
		DIUS_PLUGIN_URL . 'assets/styles.css',
		array( 'wp-components' ),
		DIUS_VERSION
	);

	wp_enqueue_script(
		'dius-admin-scan',
		DIUS_PLUGIN_URL . 'assets/admin-scan.js',
		array( 'wp-a11y' ),
		DIUS_VERSION,
		true
	);

	wp_localize_script(
		'dius-admin-scan',
		'diusImageUsageScannerSettings',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'dius_ajax_scan' ),
			'batchSize' => DIUS_AJAX_BATCH_SIZE,
			'i18n'      => array(
				'starting'       => __( 'Preparing scan...', 'scan-duplicate-images' ),
				'scanning'       => __( 'Scanning image usage...', 'scan-duplicate-images' ),
				'complete'       => __( 'Scan complete.', 'scan-duplicate-images' ),
				'stopped'        => __( 'Scan stopped. Start a new scan when you are ready.', 'scan-duplicate-images' ),
				'failed'         => __( 'The scan failed. Please try the fallback submit button or reduce the scan scope.', 'scan-duplicate-images' ),
				'noItems'        => __( 'No matching items were found for this scan.', 'scan-duplicate-images' ),
				'fallbackNotice' => __( 'JavaScript scan unavailable. Using the fallback form submit is still supported.', 'scan-duplicate-images' ),
			),
		)
	);
}

/**
 * Build a namespaced transient key for the current user and scan.
 *
 * @param string $type    Transient type, such as state or report.
 * @param string $scan_id Scan UUID.
 * @return string
 */
function dius_get_scan_transient_key( $type, $scan_id ) {
	$type    = sanitize_key( $type );
	$scan_id = sanitize_key( $scan_id );
	$user_id = get_current_user_id();

	return 'dius_' . $type . '_' . $user_id . '_' . $scan_id;
}
