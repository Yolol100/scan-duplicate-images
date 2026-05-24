<?php
/**
 * Scan arguments and post selection.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build default scan arguments.
 *
 * @return array
 */
function dius_get_default_scan_args() {
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

/**
 * Sanitize scan arguments submitted from wp-admin.
 *
 * @param array $raw_args Raw arguments.
 * @return array
 */
function dius_sanitize_scan_args( $raw_args ) {
	$defaults = dius_get_default_scan_args();

	return array(
		'scan_pages'       => isset( $raw_args['scan_pages'] ) ? (bool) absint( wp_unslash( $raw_args['scan_pages'] ) ) : $defaults['scan_pages'],
		'scan_posts'       => isset( $raw_args['scan_posts'] ) ? (bool) absint( wp_unslash( $raw_args['scan_posts'] ) ) : $defaults['scan_posts'],
		'include_featured' => true,
		'include_page_acf' => true,
		'limit'            => isset( $raw_args['limit'] ) ? absint( wp_unslash( $raw_args['limit'] ) ) : 0,
	);
}

/**
 * Scan for repeated image usage.
 *
 * Scope is intentionally narrow:
 * - pages: featured image + ACF image/gallery fields
 * - posts: featured image
 *
 * @param array $args Scan arguments.
 * @return array Scan report.
 */

/**
 * Scan for repeated image usage.
 *
 * Scope is intentionally narrow:
 * - pages: featured image + ACF image/gallery fields
 * - posts: featured image
 *
 * @param array $args Scan arguments.
 * @return array Scan report.
 */
function dius_scan_for_duplicate_images( $args = array() ) {
	$state      = dius_create_scan_state( $args );
	$batch_size = max( 1, count( $state['post_ids'] ) );

	while ( empty( $state['done'] ) ) {
		$state = dius_process_scan_batch( $state, $batch_size );
	}

	return dius_finalize_scan_state( $state );
}

/**
 * Get post IDs for the focused scan.
 *
 * @param array $args Scan arguments.
 * @return array<int>
 */

/**
 * Get post IDs for the focused scan.
 *
 * @param array $args Scan arguments.
 * @return array<int>
 */
function dius_get_scan_post_ids( $args ) {
	$post_types = array();

	if ( ! empty( $args['scan_pages'] ) ) {
		$post_types[] = 'page';
	}

	if ( ! empty( $args['scan_posts'] ) ) {
		$post_types[] = 'post';
	}

	if ( empty( $post_types ) ) {
		return array();
	}

	$query_args = array(
		'post_type'              => $post_types,
		'post_status'            => array( 'publish' ),
		'posts_per_page'         => ! empty( $args['limit'] ) ? absint( $args['limit'] ) : -1,
		'orderby'                => 'ID',
		'order'                  => 'ASC',
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	$post_ids = get_posts( $query_args );

	return array_values( array_map( 'absint', $post_ids ) );
}

/**
 * Create an incremental scan state for AJAX/batch scans.
 *
 * @param array $args Scan arguments.
 * @return array
 */
