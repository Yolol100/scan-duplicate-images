<?php
/**
 * Cursor-based incremental scan state processing.
 *
 * @package MediaInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create an incremental scan state without loading all post IDs into memory.
 *
 * @param array $args Scan arguments.
 * @return array
 */
function media_insight_create_scan_state( $args = array() ) {
	$args       = wp_parse_args( $args, media_insight_get_default_scan_args() );
	$post_types = media_insight_get_scan_post_types( $args );
	$total      = media_insight_count_scan_posts( $post_types, $args );

	return array(
		'args'               => $args,
		'post_types'         => $post_types,
		'current_type_index' => 0,
		'last_id'            => 0,
		'processed'          => 0,
		'total'              => $total,
		'done'               => empty( $post_types ) || 0 === $total,
		'stats'              => array(
			'scanned_items'   => 0,
			'scanned_pages'   => 0,
			'scanned_posts'   => 0,
			'total_usages'    => 0,
			'unique_images'   => 0,
			'repeated_images' => 0,
			'featured_usages' => 0,
			'acf_page_usages' => 0,
			'acf_enabled'     => function_exists( 'get_field_objects' ) || function_exists( 'get_fields' ),
		),
	);
}

/**
 * Add an ID cursor condition to the current scan query.
 *
 * @param string   $where Existing WHERE clause.
 * @param WP_Query $query Query object.
 * @return string
 */
function media_insight_filter_cursor_posts_where( $where, $query ) {
	$last_id = absint( $query->get( 'media_insight_last_id' ) );

	if ( ! $last_id ) {
		return $where;
	}

	global $wpdb;

	return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $last_id );
}

/**
 * Fetch the next cursor-based post IDs for a scan state.
 *
 * @param array $state      Current scan state.
 * @param int   $batch_size Number of posts to fetch.
 * @return array<int>
 */
function media_insight_get_next_scan_batch_ids( &$state, $batch_size ) {
	$args       = isset( $state['args'] ) && is_array( $state['args'] ) ? $state['args'] : media_insight_get_default_scan_args();
	$post_types = isset( $state['post_types'] ) && is_array( $state['post_types'] ) ? array_values( $state['post_types'] ) : media_insight_get_scan_post_types( $args );
	$batch_size = max( 1, absint( $batch_size ) );
	$limit      = ! empty( $args['limit'] ) ? absint( $args['limit'] ) : 0;
	$processed  = isset( $state['processed'] ) ? absint( $state['processed'] ) : 0;

	if ( $limit && $processed >= $limit ) {
		$state['done'] = true;
		return array();
	}

	if ( $limit ) {
		$batch_size = min( $batch_size, $limit - $processed );
	}

	$current_index = isset( $state['current_type_index'] ) ? absint( $state['current_type_index'] ) : 0;
	$last_id       = isset( $state['last_id'] ) ? absint( $state['last_id'] ) : 0;

	while ( isset( $post_types[ $current_index ] ) ) {
		$post_type = sanitize_key( $post_types[ $current_index ] );

		add_filter( 'posts_where', 'media_insight_filter_cursor_posts_where', 10, 2 );
		$post_ids = get_posts(
			array(
				'post_type'                  => $post_type,
				'post_status'                => 'publish',
				'fields'                     => 'ids',
				'orderby'                    => 'ID',
				'order'                      => 'ASC',
				'posts_per_page'             => $batch_size,
				'no_found_rows'              => true,
				'update_post_meta_cache'     => false,
				'update_post_term_cache'     => false,
				'suppress_filters'           => false,
				'media_insight_last_id'      => $last_id,
			)
		);
		remove_filter( 'posts_where', 'media_insight_filter_cursor_posts_where', 10 );

		$post_ids = array_map( 'absint', $post_ids );

		if ( ! empty( $post_ids ) ) {
			$state['current_type_index'] = $current_index;
			$state['last_id']            = max( $post_ids );
			return $post_ids;
		}

		$current_index++;
		$last_id = 0;
		$state['current_type_index'] = $current_index;
		$state['last_id']            = 0;
	}

	$state['done'] = true;
	return array();
}

/**
 * Process a single scan batch.
 *
 * @param array $state      Current scan state.
 * @param int   $batch_size Number of posts to process.
 * @return array
 */
function media_insight_process_scan_batch( $state, $batch_size = 25 ) {
	if ( ! empty( $state['done'] ) ) {
		return $state;
	}

	$args       = isset( $state['args'] ) && is_array( $state['args'] ) ? $state['args'] : media_insight_get_default_scan_args();
	$post_ids   = media_insight_get_next_scan_batch_ids( $state, $batch_size );
	$user_id    = isset( $state['user_id'] ) ? absint( $state['user_id'] ) : 0;
	$scan_id    = isset( $state['scan_id'] ) ? sanitize_key( $state['scan_id'] ) : '';
	$results    = array();

	if ( empty( $post_ids ) ) {
		$state['done'] = true;
		return $state;
	}

	$posts = get_posts(
		array(
			'post_type'              => isset( $state['post_types'] ) && is_array( $state['post_types'] ) ? array_map( 'sanitize_key', $state['post_types'] ) : array( 'page', 'post' ),
			'post_status'            => 'publish',
			'post__in'               => $post_ids,
			'orderby'                => 'post__in',
			'posts_per_page'         => count( $post_ids ),
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);

	$posts_by_id = array();
	foreach ( $posts as $post ) {
		if ( $post instanceof WP_Post ) {
			$posts_by_id[ absint( $post->ID ) ] = $post;
		}
	}

	foreach ( $post_ids as $post_id ) {
		$post = isset( $posts_by_id[ $post_id ] ) ? $posts_by_id[ $post_id ] : null;

		if ( ! $post instanceof WP_Post ) {
			$state['processed'] = absint( $state['processed'] ?? 0 ) + 1;
				continue;
		}

		$state['stats']['scanned_items']++;

		if ( 'page' === $post->post_type ) {
			$state['stats']['scanned_pages']++;
		} elseif ( 'post' === $post->post_type ) {
			$state['stats']['scanned_posts']++;
		}

		if ( ! empty( $args['include_featured'] ) ) {
			media_insight_scan_featured_image( $post, $results, $state['stats'] );
		}

		if ( 'page' === $post->post_type && ! empty( $args['include_page_acf'] ) ) {
			media_insight_scan_page_acf_image_fields( $post, $results, $state['stats'] );
		}

		$state['processed'] = absint( $state['processed'] ?? 0 ) + 1;
	}

	if ( $scan_id && $user_id ) {
		media_insight_merge_scan_result_chunks( $scan_id, $user_id, $results );
	}

	$limit = ! empty( $args['limit'] ) ? absint( $args['limit'] ) : 0;
	if ( $limit && absint( $state['processed'] ) >= $limit ) {
		$state['done'] = true;
	}

	if ( absint( $state['processed'] ) >= absint( $state['total'] ) ) {
		$state['done'] = true;
	}

	return $state;
}
