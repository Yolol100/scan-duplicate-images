<?php
/**
 * Admin module loader for Media Insight.
 *
 * @package MediaInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/admin/menu.php';
require_once MEDIA_INSIGHT_PLUGIN_PATH . 'includes/admin/export.php';
