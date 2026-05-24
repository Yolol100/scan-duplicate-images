<?php
/**
 * Incremental scan state processing.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create an incremental scan state for AJAX/batch scans.
 *
 * @param array $args Scan arguments.
 * @return array
 */
function dius_create_scan_state( $args = array() ) {
	$args     = wp_parse_args( $args, dius_get_default_scan_args() );
	$post_ids = dius_get_scan_post_ids( $args );

	return array(
		'args'     => $args,
		'post_ids' => $post_ids,
		'offset'   => 0,
		'total'    => count( $post_ids ),
		'done'     => empty( $post_ids ),
		'results'  => array(),
		'stats'    => array(
			'scanned_items'       => 0,
			'scanned_pages'       => 0,
			'scanned_posts'       => 0,
			'total_usages'        => 0,
			'unique_images'       => 0,
			'repeated_images'     => 0,
			'featured_usages'     => 0,
			'acf_page_usages'     => 0,
			'acf_enabled'         => function_exists( 'get_field_objects' ) || function_exists( 'get_fields' ),
		),
	);
}

/**
 * Process a single scan batch.
 *
 * @param array $state      Current scan state.
 * @param int   $batch_size Number of posts to process.
 * @return array
 */

/**
 * Process a single scan batch.
 *
 * @param array $state      Current scan state.
 * @param int   $batch_size Number of posts to process.
 * @return array
 */
function dius_process_scan_batch( $state, $batch_size = 25 ) {
	if ( empty( $state['post_ids'] ) || ! is_array( $state['post_ids'] ) ) {
		$state['done'] = true;
		return $state;
	}

	$args       = isset( $state['args'] ) && is_array( $state['args'] ) ? $state['args'] : dius_get_default_scan_args();
	$offset     = isset( $state['offset'] ) ? absint( $state['offset'] ) : 0;
	$batch_size = max( 1, absint( $batch_size ) );
	$post_ids   = array_slice( $state['post_ids'], $offset, $batch_size );

	foreach ( $post_ids as $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			$offset++;
			continue;
		}

		$state['stats']['scanned_items']++;

		if ( 'page' === $post->post_type ) {
			$state['stats']['scanned_pages']++;
		} elseif ( 'post' === $post->post_type ) {
			$state['stats']['scanned_posts']++;
		}

		if ( ! empty( $args['include_featured'] ) ) {
			dius_scan_featured_image( $post, $state['results'], $state['stats'] );
		}

		if ( 'page' === $post->post_type && ! empty( $args['include_page_acf'] ) ) {
			dius_scan_page_acf_image_fields( $post, $state['results'], $state['stats'] );
		}

		$offset++;
	}

	$state['offset'] = $offset;
	$state['done']   = $offset >= count( $state['post_ids'] );

	return $state;
}

/**
 * Scan featured image.
 *
 * @param WP_Post $post    Post object.
 * @param array   $results Results array.
 * @param array   $stats   Stats array.
 */
