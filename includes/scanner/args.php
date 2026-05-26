<?php
/**
 * Scan arguments and post selection helpers.
 *
 * @package MediaInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the maximum positive scan limit accepted from wp-admin.
 *
 * A limit of 0 still means scan all matching items.
 *
 * @return int
 */
function media_insight_get_max_scan_limit() {
	return defined( 'MEDIA_INSIGHT_MAX_SCAN_LIMIT' ) ? absint( MEDIA_INSIGHT_MAX_SCAN_LIMIT ) : 50000;
}

/**
 * Normalize a positive scan limit while preserving 0 as unlimited.
 *
 * @param mixed $limit Raw scan limit.
 * @return int
 */
function media_insight_normalize_scan_limit( $limit ) {
	$limit = is_numeric( $limit ) ? (int) $limit : 0;

	if ( $limit <= 0 ) {
		return 0;
	}

	return min( $limit, media_insight_get_max_scan_limit() );
}

/**
 * Build default scan arguments.
 *
 * @return array
 */
function media_insight_get_default_scan_args() {
	return array(
		'scan_pages'       => true,
		'scan_posts'       => true,
		'include_featured' => true,
		'include_page_acf' => true,
		'limit'            => 0,
	);
}

/**
 * Sanitize scan arguments submitted from wp-admin.
 *
 * @param array $raw_args Raw arguments.
 * @return array
 */
function media_insight_sanitize_scan_args( $raw_args ) {
	$raw_args = is_array( $raw_args ) ? $raw_args : array();
	$defaults = media_insight_get_default_scan_args();

	return array(
		'scan_pages'       => isset( $raw_args['scan_pages'] ) ? (bool) absint( $raw_args['scan_pages'] ) : $defaults['scan_pages'],
		'scan_posts'       => isset( $raw_args['scan_posts'] ) ? (bool) absint( $raw_args['scan_posts'] ) : $defaults['scan_posts'],
		'include_featured' => true,
		'include_page_acf' => true,
		'limit'            => isset( $raw_args['limit'] ) ? media_insight_normalize_scan_limit( $raw_args['limit'] ) : 0,
	);
}

/**
 * Return post types included in the fixed scan scope.
 *
 * @param array $args Scan arguments.
 * @return array<int,string>
 */
function media_insight_get_scan_post_types( $args ) {
	$post_types = array();

	if ( ! empty( $args['scan_pages'] ) ) {
		$post_types[] = 'page';
	}

	if ( ! empty( $args['scan_posts'] ) ) {
		$post_types[] = 'post';
	}

	return $post_types;
}

/**
 * Count publishable items for progress without loading all IDs into memory.
 *
 * @param array<int,string> $post_types Post types.
 * @param array             $args       Scan arguments.
 * @return int
 */
function media_insight_count_scan_posts( $post_types, $args ) {
	$total = 0;

	foreach ( $post_types as $post_type ) {
		$count = wp_count_posts( $post_type );

		if ( isset( $count->publish ) ) {
			$total += absint( $count->publish );
		}
	}

	if ( ! empty( $args['limit'] ) ) {
		$total = min( $total, absint( $args['limit'] ) );
	}

	return absint( $total );
}
