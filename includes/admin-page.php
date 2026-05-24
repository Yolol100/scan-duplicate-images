<?php
/**
 * Admin module loader for Featured & ACF Image Usage Scanner.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once DIUS_PLUGIN_PATH . 'includes/admin/helpers.php';
require_once DIUS_PLUGIN_PATH . 'includes/admin/render.php';
require_once DIUS_PLUGIN_PATH . 'includes/admin/menu.php';
require_once DIUS_PLUGIN_PATH . 'includes/admin/export.php';
require_once DIUS_PLUGIN_PATH . 'includes/admin/ajax.php';
