<?php
/**
 * Featured image scanner.
 *
 * @package MediaInsight
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
function media_insight_scan_featured_image( WP_Post $post, &$results, &$stats ) {
	$attachment_id = get_post_thumbnail_id( $post );

	if ( $attachment_id && media_insight_attachment_is_supported_image( $attachment_id ) ) {
		if ( media_insight_add_attachment_usage( $results, absint( $attachment_id ), $post, 'featured_image', __( 'Featured image', 'media-insight' ) ) ) {
			$stats['featured_usages']++;
		}
	}
}

