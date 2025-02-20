<?php
/**
 * Plugin Name: Duplicate Image Usage Scanner
 * Description: Scans all pages for duplicate images, including those stored via ACF fields, and displays results in the admin area.
 * Version: 1.1
 * Author: Your Name
 */

// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

// Define constants for the plugin.
define('DIUS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DIUS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files.
require_once DIUS_PLUGIN_PATH . 'includes/scanner.php';
require_once DIUS_PLUGIN_PATH . 'includes/admin-page.php';

// Hook to initialize the plugin.
add_action('plugins_loaded', function() {
    if (is_admin()) {
        add_action('admin_menu', 'dius_add_admin_page');
        add_action('admin_enqueue_scripts', 'dius_enqueue_admin_styles');
    }
});

// Enqueue admin styles.
function dius_enqueue_admin_styles($hook_suffix) {
    // Only load styles on the plugin's admin page.
    if (strpos($hook_suffix, 'image-duplicator') !== false) {
        wp_enqueue_style('dius-admin-styles', DIUS_PLUGIN_URL . 'assets/style.css', [], '1.0', 'all');
    }
}