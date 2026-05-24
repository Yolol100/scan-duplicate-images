<?php
/**
 * Admin request helpers.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return normalized POST data for admin actions.
 *
 * WordPress adds slashes to request data, so this helper keeps scan/export
 * sanitization paths consistent and avoids scattered direct superglobal reads.
 *
 * @return array
 */
function dius_get_post_data() {
	return isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();
}

/**
 * Add the admin menu item.
 */
