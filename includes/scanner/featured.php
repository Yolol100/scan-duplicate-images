<?php
/**
 * Featured image scanner.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scan featured image.
 *
 * @param WP_Post $post    Post object.
 * @param array   $results Results array.
 * @param array   $stats   Stats array.
 */
function dius_scan_featured_image( WP_Post $post, &$results, &$stats ) {
	$attachment_id = get_post_thumbnail_id( $post );

	if ( $attachment_id && dius_attachment_is_supported_image( $attachment_id ) ) {
		if ( dius_add_attachment_usage( $results, absint( $attachment_id ), $post, 'featured_image', __( 'Featured image', 'scan-duplicate-images' ) ) ) {
			$stats['featured_usages']++;
		}
	}
}

/**
 * Scan only ACF image-like fields on pages.
 *
 * @param WP_Post $post    Page object.
 * @param array   $results Results array.
 * @param array   $stats   Stats array.
 */
