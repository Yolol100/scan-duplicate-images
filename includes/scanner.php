<?php
/**
 * Focused scanner module loader for ACF page images and featured images.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once DIUS_PLUGIN_PATH . 'includes/scanner/args.php';
require_once DIUS_PLUGIN_PATH . 'includes/scanner/state.php';
require_once DIUS_PLUGIN_PATH . 'includes/scanner/featured.php';
require_once DIUS_PLUGIN_PATH . 'includes/scanner/acf.php';
require_once DIUS_PLUGIN_PATH . 'includes/scanner/images.php';
require_once DIUS_PLUGIN_PATH . 'includes/scanner/finalize.php';
