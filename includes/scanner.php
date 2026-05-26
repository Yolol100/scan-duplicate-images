<?php
/**
 * Focused scanner module loader for ACF page images and featured images.
 *
 * @package MediaInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/scanner/args.php';
require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/scanner/state.php';
require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/scanner/featured.php';
require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/scanner/acf.php';
require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/scanner/images.php';
require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/scanner/finalize.php';
