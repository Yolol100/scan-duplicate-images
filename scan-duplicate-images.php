<?php
/**
 * Plugin Name: Media Insight
 * Description: Advanced featured image and ACF usage scanner for WordPress.
 * Version: 3.4.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Webactueel
 * Text Domain: media-insight
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'DIUS_PLUGIN_FILE' ) || function_exists( 'dius_get_default_scan_args' ) ) {
	return;
}

define( 'DIUS_PLUGIN_FILE', __FILE__ );
define( 'DIUS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'DIUS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DIUS_VERSION', '3.4.1' );
define( 'DIUS_MENU_SLUG', 'media-insight' );
define( 'DIUS_AJAX_BATCH_SIZE', 25 );
define( 'DIUS_TRANSIENT_TTL', HOUR_IN_SECONDS );

require_once DIUS_PLUGIN_PATH . 'includes/scanner.php';
require_once DIUS_PLUGIN_PATH . 'includes/admin-page.php';

function dius_load_textdomain() {
	load_plugin_textdomain( 'media-insight', false, dirname( plugin_basename( DIUS_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'dius_load_textdomain' );

if ( is_admin() ) {
	add_action( 'admin_menu', 'dius_add_admin_page' );
	add_action( 'admin_enqueue_scripts', 'dius_enqueue_admin_assets' );
	add_action( 'admin_post_dius_export_csv', 'dius_handle_csv_export' );
	add_action( 'wp_ajax_dius_start_scan', 'dius_ajax_start_scan' );
	add_action( 'wp_ajax_dius_process_scan_batch', 'dius_ajax_process_scan_batch' );
}

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
}
