<?php
/**
 * Scanner logic for Duplicate Image Usage Scanner.
 *
 * @package DuplicateImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get public post types available for scanning.
 *
 * @return array<string,string> Post type names keyed by slug.
 */
function dius_get_scannable_post_types() {
	$post_types = get_post_types(
		array(
			'public' => true,
		),
		'objects'
	);

	$excluded = array( 'attachment' );
	$options  = array();

	foreach ( $post_types as $post_type => $object ) {
		if ( in_array( $post_type, $excluded, true ) ) {
			continue;
		}

		$options[ $post_type ] = $object->labels->name ? $object->labels->name : $post_type;
	}

	return $options;
}

/**
 * Build default scan arguments.
 *
 * @return array
 */
function dius_get_default_scan_args() {
	$post_types = array_keys( dius_get_scannable_post_types() );

	if ( empty( $post_types ) ) {
		$post_types = array( 'post', 'page' );
	}

	return array(
		'post_types'     => $post_types,
		'post_statuses'  => array( 'publish' ),
		'include_acf'    => true,
		'include_blocks' => true,
		'limit'          => 0,
	);
}

/**
 * Sanitize scan arguments submitted from wp-admin.
 *
 * @param array $raw_args Raw arguments.
 * @return array
 */
function dius_sanitize_scan_args( $raw_args ) {
	$defaults   = dius_get_default_scan_args();
	$available  = array_keys( dius_get_scannable_post_types() );
	$post_types = array();

	if ( isset( $raw_args['post_types'] ) && is_array( $raw_args['post_types'] ) ) {
		foreach ( $raw_args['post_types'] as $post_type ) {
			$post_type = sanitize_key( wp_unslash( $post_type ) );

			if ( in_array( $post_type, $available, true ) ) {
				$post_types[] = $post_type;
			}
		}
	}

	if ( empty( $post_types ) ) {
		$post_types = $defaults['post_types'];
	}

	$limit = isset( $raw_args['limit'] ) ? absint( wp_unslash( $raw_args['limit'] ) ) : 0;

	return array(
		'post_types'     => array_values( array_unique( $post_types ) ),
		'post_statuses'  => $defaults['post_statuses'],
		'include_acf'    => ! empty( $raw_args['include_acf'] ),
		'include_blocks' => ! empty( $raw_args['include_blocks'] ),
		'limit'          => $limit,
	);
}

/**
 * Scan posts for duplicate image usage.
 *
 * @param array $args Scan arguments.
 * @return array Scan report.
 */
function dius_scan_for_duplicate_images( $args = array() ) {
	$args    = wp_parse_args( $args, dius_get_default_scan_args() );
	$results = array();
	$stats   = array(
		'scanned_posts'     => 0,
		'total_usages'      => 0,
		'unique_images'     => 0,
		'duplicate_images'  => 0,
		'scanned_post_types' => $args['post_types'],
		'acf_enabled'       => function_exists( 'get_fields' ),
	);

	$query_args = array(
		'post_type'              => $args['post_types'],
		'post_status'            => $args['post_statuses'],
		'posts_per_page'         => $args['limit'] > 0 ? absint( $args['limit'] ) : -1,
		'orderby'                => 'ID',
		'order'                  => 'ASC',
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	$post_ids = get_posts( $query_args );

	foreach ( $post_ids as $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			continue;
		}

		$stats['scanned_posts']++;
		dius_scan_post_content( $post, $results );
		dius_scan_featured_image( $post, $results );

		if ( ! empty( $args['include_blocks'] ) ) {
			dius_scan_post_blocks( $post, $results );
		}

		if ( ! empty( $args['include_acf'] ) && function_exists( 'get_fields' ) ) {
			dius_scan_acf_fields( $post, $results );
		}
	}

	foreach ( $results as $key => $image ) {
		$results[ $key ]['unique_post_count'] = dius_count_unique_usage_posts( $image['usages'] );
		$results[ $key ]['usage_count']       = count( $image['usages'] );
		$stats['total_usages']               += count( $image['usages'] );
	}

	$stats['unique_images'] = count( $results );

	$duplicates = array_filter(
		$results,
		static function ( $image ) {
			return isset( $image['unique_post_count'] ) && $image['unique_post_count'] > 1;
		}
	);

	uasort(
		$duplicates,
		static function ( $a, $b ) {
			if ( $a['unique_post_count'] === $b['unique_post_count'] ) {
				return strnatcasecmp( $a['filename'], $b['filename'] );
			}

			return $b['unique_post_count'] <=> $a['unique_post_count'];
		}
	);

	$stats['duplicate_images'] = count( $duplicates );

	return array(
		'args'       => $args,
		'stats'      => $stats,
		'all_images' => $results,
		'duplicates' => $duplicates,
	);
}

/**
 * Backward-compatible wrapper.
 *
 * @return array Duplicate image usage keyed by normalized URL or attachment key.
 */
function dius_scan_pages_for_duplicate_images() {
	$report = dius_scan_for_duplicate_images();

	return $report['duplicates'];
}

/**
 * Scan post_content HTML and image-like URLs.
 *
 * @param WP_Post $post    Post object.
 * @param array   $results Results array.
 */
function dius_scan_post_content( WP_Post $post, &$results ) {
	$content = (string) $post->post_content;

	if ( '' === $content ) {
		return;
	}

	$image_urls = array();

	if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
		$processor = new WP_HTML_Tag_Processor( $content );

		while ( $processor->next_tag( 'img' ) ) {
			$src = $processor->get_attribute( 'src' );

			if ( is_string( $src ) ) {
				$image_urls[] = array( $src, 'post_content', 'img src' );
			}

			$srcset = $processor->get_attribute( 'srcset' );

			if ( is_string( $srcset ) ) {
				foreach ( dius_extract_srcset_urls( $srcset ) as $url ) {
					$image_urls[] = array( $url, 'post_content', 'img srcset' );
				}
			}
		}
	} else {
		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches );

		foreach ( $matches[1] ?? array() as $url ) {
			$image_urls[] = array( $url, 'post_content', 'img src' );
		}

		preg_match_all( '/<img[^>]+srcset=["\']([^"\']+)["\']/i', $content, $srcset_matches );

		foreach ( $srcset_matches[1] ?? array() as $srcset ) {
			foreach ( dius_extract_srcset_urls( $srcset ) as $url ) {
				$image_urls[] = array( $url, 'post_content', 'img srcset' );
			}
		}
	}

	preg_match_all( '/url\(\s*["\']?([^"\')]+\.(?:jpg|jpeg|png|gif|webp|avif))(?:\?[^"\')]+)?["\']?\s*\)/i', $content, $background_matches );

	foreach ( $background_matches[1] ?? array() as $url ) {
		$image_urls[] = array( $url, 'post_content', 'background-image' );
	}

	foreach ( $image_urls as $image ) {
		dius_add_image_usage( $results, $image[0], $post, $image[1], $image[2] );
	}
}

/**
 * Scan featured image.
 *
 * @param WP_Post $post    Post object.
 * @param array   $results Results array.
 */
function dius_scan_featured_image( WP_Post $post, &$results ) {
	$attachment_id = get_post_thumbnail_id( $post );

	if ( $attachment_id ) {
		dius_add_attachment_usage( $results, absint( $attachment_id ), $post, 'featured_image', '_thumbnail_id' );
	}
}

/**
 * Scan Gutenberg block attributes recursively.
 *
 * @param WP_Post $post    Post object.
 * @param array   $results Results array.
 */
function dius_scan_post_blocks( WP_Post $post, &$results ) {
	if ( ! function_exists( 'parse_blocks' ) || ! has_blocks( $post ) ) {
		return;
	}

	$blocks = parse_blocks( $post->post_content );

	dius_scan_block_list( $blocks, $post, $results );
}

/**
 * Scan a list of parsed blocks.
 *
 * @param array   $blocks  Parsed blocks.
 * @param WP_Post $post    Post object.
 * @param array   $results Results array.
 */
function dius_scan_block_list( $blocks, WP_Post $post, &$results ) {
	foreach ( $blocks as $block ) {
		$block_name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : 'block';

		if ( ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
			dius_scan_mixed_value_for_images( $block['attrs'], $results, $post, 'block_attrs', $block_name );
		}

		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			dius_scan_block_list( $block['innerBlocks'], $post, $results );
		}
	}
}

/**
 * Scan ACF fields recursively.
 *
 * @param WP_Post $post    Post object.
 * @param array   $results Results array.
 */
function dius_scan_acf_fields( WP_Post $post, &$results ) {
	$fields = get_fields( $post->ID );

	if ( empty( $fields ) || ! is_array( $fields ) ) {
		return;
	}

	foreach ( $fields as $field_name => $field_value ) {
		dius_scan_mixed_value_for_images( $field_value, $results, $post, 'acf', (string) $field_name );
	}
}

/**
 * Recursively scan mixed values for image IDs, arrays, and URLs.
 *
 * @param mixed   $value   Value to scan.
 * @param array   $results Results array.
 * @param WP_Post $post    Post object.
 * @param string  $source  Source label.
 * @param string  $context Context label.
 */
function dius_scan_mixed_value_for_images( $value, &$results, WP_Post $post, $source, $context ) {
	if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
		$maybe_attachment_id = absint( $value );

		if ( $maybe_attachment_id && wp_attachment_is_image( $maybe_attachment_id ) ) {
			dius_add_attachment_usage( $results, $maybe_attachment_id, $post, $source, $context );
		}

		return;
	}

	if ( is_string( $value ) ) {
		if ( dius_is_image_url( $value ) ) {
			dius_add_image_usage( $results, $value, $post, $source, $context );
		}

		return;
	}

	if ( ! is_array( $value ) ) {
		return;
	}

	$attachment_id = 0;

	foreach ( array( 'ID', 'id', 'attachment_id', 'image_id' ) as $id_key ) {
		if ( isset( $value[ $id_key ] ) ) {
			$attachment_id = absint( $value[ $id_key ] );

			if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
				dius_add_attachment_usage( $results, $attachment_id, $post, $source, $context );
				break;
			}
		}
	}

	foreach ( array( 'url', 'src', 'image', 'background_image' ) as $url_key ) {
		if ( isset( $value[ $url_key ] ) && is_string( $value[ $url_key ] ) && dius_is_image_url( $value[ $url_key ] ) ) {
			dius_add_image_usage( $results, $value[ $url_key ], $post, $source, $context );
		}
	}

	foreach ( $value as $nested_key => $nested_value ) {
		$nested_context = is_string( $nested_key ) ? $context . ' > ' . $nested_key : $context;
		dius_scan_mixed_value_for_images( $nested_value, $results, $post, $source, $nested_context );
	}
}

/**
 * Add an attachment usage by ID.
 *
 * @param array   $results       Results array.
 * @param int     $attachment_id Attachment ID.
 * @param WP_Post $post          Post object.
 * @param string  $source        Source label.
 * @param string  $context       Context label.
 */
function dius_add_attachment_usage( &$results, $attachment_id, WP_Post $post, $source, $context ) {
	$url = wp_get_attachment_url( $attachment_id );

	if ( ! $url ) {
		return;
	}

	dius_add_image_usage( $results, $url, $post, $source, $context, absint( $attachment_id ) );
}

/**
 * Add an image usage entry to the results.
 *
 * @param array   $results       Results array.
 * @param string  $image_url     Image URL.
 * @param WP_Post $post          Post object.
 * @param string  $source        Source label.
 * @param string  $context       Context label.
 * @param int     $attachment_id Optional attachment ID.
 */
function dius_add_image_usage( &$results, $image_url, WP_Post $post, $source, $context, $attachment_id = 0 ) {
	$normalized_url = dius_normalize_image_url( $image_url );

	if ( '' === $normalized_url || ! dius_is_image_url( $normalized_url ) ) {
		return;
	}

	if ( ! $attachment_id ) {
		$attachment_id = dius_get_attachment_id_from_url( $image_url, $normalized_url );
	}

	$key         = $attachment_id ? 'attachment:' . absint( $attachment_id ) : 'url:' . $normalized_url;
	$display_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : $normalized_url;
	$display_url = $display_url ? $display_url : $normalized_url;
	$filename    = basename( (string) wp_parse_url( $display_url, PHP_URL_PATH ) );

	if ( ! isset( $results[ $key ] ) ) {
		$results[ $key ] = array(
			'key'               => $key,
			'attachment_id'     => $attachment_id ? absint( $attachment_id ) : 0,
			'url'               => $display_url,
			'normalized_url'    => $normalized_url,
			'filename'          => $filename,
			'usages'            => array(),
			'usage_count'       => 0,
			'unique_post_count' => 0,
		);
	}

	$usage = array(
		'post_id'    => absint( $post->ID ),
		'post_title' => get_the_title( $post ),
		'post_type'  => $post->post_type,
		'edit_url'   => get_edit_post_link( $post->ID, 'raw' ),
		'view_url'   => get_permalink( $post ),
		'source'     => sanitize_key( $source ),
		'context'    => sanitize_text_field( $context ),
	);

	$usage_key = md5( wp_json_encode( $usage ) );

	if ( ! isset( $results[ $key ]['usages'][ $usage_key ] ) ) {
		$results[ $key ]['usages'][ $usage_key ] = $usage;
	}
}

/**
 * Count unique posts in usages.
 *
 * @param array $usages Usage rows.
 * @return int
 */
function dius_count_unique_usage_posts( $usages ) {
	$post_ids = array();

	foreach ( $usages as $usage ) {
		if ( isset( $usage['post_id'] ) ) {
			$post_ids[] = absint( $usage['post_id'] );
		}
	}

	return count( array_unique( $post_ids ) );
}

/**
 * Convert srcset into URLs.
 *
 * @param string $srcset Srcset value.
 * @return array
 */
function dius_extract_srcset_urls( $srcset ) {
	$urls  = array();
	$parts = explode( ',', (string) $srcset );

	foreach ( $parts as $part ) {
		$part = trim( $part );

		if ( '' === $part ) {
			continue;
		}

		$segments = preg_split( '/\s+/', $part );

		if ( ! empty( $segments[0] ) && dius_is_image_url( $segments[0] ) ) {
			$urls[] = $segments[0];
		}
	}

	return array_values( array_unique( $urls ) );
}

/**
 * Try to resolve an image URL to an attachment ID.
 *
 * @param string $original_url   Original URL.
 * @param string $normalized_url Normalized URL.
 * @return int
 */
function dius_get_attachment_id_from_url( $original_url, $normalized_url ) {
	foreach ( array( $original_url, $normalized_url ) as $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			continue;
		}

		$attachment_id = attachment_url_to_postid( $url );

		if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
			return absint( $attachment_id );
		}
	}

	return 0;
}

/**
 * Check whether a URL points to a supported image file.
 *
 * @param string $url URL to check.
 * @return bool
 */
function dius_is_image_url( $url ) {
	$path = wp_parse_url( html_entity_decode( (string) $url ), PHP_URL_PATH );

	if ( ! is_string( $path ) ) {
		return false;
	}

	return (bool) preg_match( '/\.(jpg|jpeg|png|gif|webp|avif)$/i', $path );
}

/**
 * Normalize image URLs so common size variants group with the original image.
 *
 * @param string $image_url Image URL.
 * @return string
 */
function dius_normalize_image_url( $image_url ) {
	$image_url = trim( html_entity_decode( (string) $image_url, ENT_QUOTES, get_bloginfo( 'charset' ) ) );

	if ( '' === $image_url ) {
		return '';
	}

	$image_url = strtok( $image_url, '?' );
	$image_url = strtok( $image_url, '#' );

	if ( 0 === strpos( $image_url, '//' ) ) {
		$image_url = ( is_ssl() ? 'https:' : 'http:' ) . $image_url;
	}

	$path = wp_parse_url( $image_url, PHP_URL_PATH );

	if ( ! is_string( $path ) || '' === $path ) {
		return '';
	}

	$path = preg_replace( '/-\d+x\d+(?=\.(?:jpg|jpeg|png|gif|webp|avif)$)/i', '', $path );
	$path = preg_replace( '/-scaled(?=\.(?:jpg|jpeg|png|gif|webp|avif)$)/i', '', $path );

	if ( 0 === strpos( $image_url, '/' ) ) {
		return $path;
	}

	$parts  = wp_parse_url( $image_url );
	$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
	$host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';

	return $host ? $scheme . '://' . $host . $path : $path;
}

/**
 * Get post IDs for a scan using the same query rules as the synchronous scanner.
 *
 * @param array $args Scan arguments.
 * @return array<int>
 */
function dius_get_scan_post_ids( $args ) {
	$query_args = array(
		'post_type'              => $args['post_types'],
		'post_status'            => $args['post_statuses'],
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
function dius_create_scan_state( $args = array() ) {
	$args     = wp_parse_args( $args, dius_get_default_scan_args() );
	$post_ids = dius_get_scan_post_ids( $args );

	return array(
		'args'    => $args,
		'post_ids' => $post_ids,
		'offset'  => 0,
		'total'   => count( $post_ids ),
		'done'    => empty( $post_ids ),
		'results' => array(),
		'stats'   => array(
			'scanned_posts'      => 0,
			'total_usages'       => 0,
			'unique_images'      => 0,
			'duplicate_images'   => 0,
			'scanned_post_types' => $args['post_types'],
			'acf_enabled'        => function_exists( 'get_fields' ),
		),
	);
}

/**
 * Process a single scan batch and return the updated state.
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

		$state['stats']['scanned_posts']++;
		dius_scan_post_content( $post, $state['results'] );
		dius_scan_featured_image( $post, $state['results'] );

		if ( ! empty( $args['include_blocks'] ) ) {
			dius_scan_post_blocks( $post, $state['results'] );
		}

		if ( ! empty( $args['include_acf'] ) && function_exists( 'get_fields' ) ) {
			dius_scan_acf_fields( $post, $state['results'] );
		}

		$offset++;
	}

	$state['offset'] = $offset;
	$state['done']   = $offset >= count( $state['post_ids'] );

	return $state;
}

/**
 * Finalize scan state into the normal report structure.
 *
 * @param array $state Scan state.
 * @return array
 */
function dius_finalize_scan_state( $state ) {
	$args    = isset( $state['args'] ) && is_array( $state['args'] ) ? $state['args'] : dius_get_default_scan_args();
	$results = isset( $state['results'] ) && is_array( $state['results'] ) ? $state['results'] : array();
	$stats   = isset( $state['stats'] ) && is_array( $state['stats'] ) ? $state['stats'] : array();

	$stats = wp_parse_args(
		$stats,
		array(
			'scanned_posts'      => 0,
			'total_usages'       => 0,
			'unique_images'      => 0,
			'duplicate_images'   => 0,
			'scanned_post_types' => $args['post_types'],
			'acf_enabled'        => function_exists( 'get_fields' ),
		)
	);

	$stats['total_usages'] = 0;

	foreach ( $results as $key => $image ) {
		$usages                               = isset( $image['usages'] ) && is_array( $image['usages'] ) ? $image['usages'] : array();
		$results[ $key ]['unique_post_count'] = dius_count_unique_usage_posts( $usages );
		$results[ $key ]['usage_count']       = count( $usages );
		$stats['total_usages']               += count( $usages );
	}

	$stats['unique_images'] = count( $results );

	$duplicates = array_filter(
		$results,
		static function ( $image ) {
			return isset( $image['unique_post_count'] ) && $image['unique_post_count'] > 1;
		}
	);

	uasort(
		$duplicates,
		static function ( $a, $b ) {
			if ( $a['unique_post_count'] === $b['unique_post_count'] ) {
				return strnatcasecmp( $a['filename'], $b['filename'] );
			}

			return $b['unique_post_count'] <=> $a['unique_post_count'];
		}
	);

	$stats['duplicate_images'] = count( $duplicates );

	return array(
		'args'       => $args,
		'stats'      => $stats,
		'all_images' => $results,
		'duplicates' => $duplicates,
	);
}
