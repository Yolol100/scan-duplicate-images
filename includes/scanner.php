<?php
/**
 * Scanner logic for Image Usage & Duplicate Media Scanner.
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
		'post_types'            => $post_types,
		'post_statuses'         => array( 'publish' ),
		'include_acf'           => true,
		'include_blocks'        => true,
		'include_elementor'     => true,
		'include_woocommerce'   => true,
		'include_media_library' => true,
		'limit'                 => 0,
	);
}

/**
 * Sanitize scan arguments submitted from wp-admin.
 *
 * @param array $raw_args Raw arguments.
 * @return array
 */
function dius_sanitize_scan_args( $raw_args ) {
	$defaults          = dius_get_default_scan_args();
	$available         = array_keys( dius_get_scannable_post_types() );
	$post_types        = array();
	$post_types_posted = array_key_exists( 'post_types', $raw_args );

	if ( isset( $raw_args['post_types'] ) && is_array( $raw_args['post_types'] ) ) {
		foreach ( $raw_args['post_types'] as $post_type ) {
			$post_type = sanitize_key( wp_unslash( $post_type ) );

			if ( in_array( $post_type, $available, true ) ) {
				$post_types[] = $post_type;
			}
		}
	}

	$include_media_library = ! empty( $raw_args['include_media_library'] );

	// Allow a deliberate media-library-only audit when all content post types are unchecked.
	if ( empty( $post_types ) && ! $post_types_posted && ! $include_media_library ) {
		$post_types = $defaults['post_types'];
	}

	$limit = isset( $raw_args['limit'] ) ? absint( wp_unslash( $raw_args['limit'] ) ) : 0;

	return array(
		'post_types'            => array_values( array_unique( $post_types ) ),
		'post_statuses'         => $defaults['post_statuses'],
		'include_acf'           => ! empty( $raw_args['include_acf'] ),
		'include_blocks'        => ! empty( $raw_args['include_blocks'] ),
		'include_elementor'     => ! empty( $raw_args['include_elementor'] ),
		'include_woocommerce'   => ! empty( $raw_args['include_woocommerce'] ),
		'include_media_library' => $include_media_library,
		'limit'                 => $limit,
	);
}

/**
 * Scan posts for repeated image usage and optional media-library findings.
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
			dius_scan_mixed_value_for_images( $block['attrs'], $results, $post, 'block_attrs', $block_name, true );
		}

		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			dius_scan_block_list( $block['innerBlocks'], $post, $results );
		}
	}
}

/**
 * Scan Elementor data from post meta.
 *
 * @param WP_Post $post    Post object.
 * @param array   $results Results array.
 */
function dius_scan_elementor_data( WP_Post $post, &$results ) {
	foreach ( array( '_elementor_data', '_elementor_page_settings' ) as $meta_key ) {
		$raw = get_post_meta( $post->ID, $meta_key, true );

		if ( empty( $raw ) ) {
			continue;
		}

		$value = $raw;

		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$value = $decoded;
			}
		}

		dius_scan_mixed_value_for_images( $value, $results, $post, 'elementor', $meta_key, true );
	}
}

/**
 * Scan WooCommerce product media fields when present.
 *
 * @param WP_Post $post    Post object.
 * @param array   $results Results array.
 */
function dius_scan_woocommerce_media( WP_Post $post, &$results ) {
	$gallery = get_post_meta( $post->ID, '_product_image_gallery', true );

	if ( empty( $gallery ) || ! is_string( $gallery ) ) {
		return;
	}

	$attachment_ids = array_filter( array_map( 'absint', explode( ',', $gallery ) ) );

	foreach ( $attachment_ids as $attachment_id ) {
		if ( wp_attachment_is_image( $attachment_id ) ) {
			dius_add_attachment_usage( $results, $attachment_id, $post, 'woocommerce', '_product_image_gallery' );
		}
	}
}

/**
 * Scan ACF fields recursively with field-type awareness where possible.
 *
 * @param WP_Post $post    Post object.
 * @param array   $results Results array.
 */
function dius_scan_acf_fields( WP_Post $post, &$results ) {
	if ( function_exists( 'get_field_objects' ) ) {
		$field_objects = get_field_objects( $post->ID );

		if ( empty( $field_objects ) || ! is_array( $field_objects ) ) {
			return;
		}

		foreach ( $field_objects as $field ) {
			if ( ! is_array( $field ) || ! array_key_exists( 'value', $field ) ) {
				continue;
			}

			$field_name = isset( $field['name'] ) ? (string) $field['name'] : 'acf_field';
			$field_type = isset( $field['type'] ) ? (string) $field['type'] : 'unknown';
			$context    = $field_name . ' (' . $field_type . ')';

			if ( ! dius_should_scan_acf_field_type( $field_type ) ) {
				continue;
			}

			dius_scan_mixed_value_for_images( $field['value'], $results, $post, 'acf', $context, dius_acf_field_allows_numeric_ids( $field_type ) );
		}

		return;
	}

	if ( ! function_exists( 'get_fields' ) ) {
		return;
	}

	$fields = get_fields( $post->ID );

	if ( empty( $fields ) || ! is_array( $fields ) ) {
		return;
	}

	foreach ( $fields as $field_name => $field_value ) {
		dius_scan_mixed_value_for_images( $field_value, $results, $post, 'acf', (string) $field_name, true );
	}
}

/**
 * Decide whether an ACF field can contain image references.
 *
 * @param string $field_type ACF field type.
 * @return bool
 */
function dius_should_scan_acf_field_type( $field_type ) {
	$field_type = sanitize_key( $field_type );

	return in_array(
		$field_type,
		array(
			'image',
			'gallery',
			'file',
			'wysiwyg',
			'textarea',
			'text',
			'url',
			'oembed',
			'group',
			'repeater',
			'flexible_content',
			'clone',
		),
		true
	);
}

/**
 * Decide whether numeric values in an ACF field should be treated as possible attachment IDs.
 *
 * @param string $field_type ACF field type.
 * @return bool
 */
function dius_acf_field_allows_numeric_ids( $field_type ) {
	$field_type = sanitize_key( $field_type );

	return in_array( $field_type, array( 'image', 'gallery', 'file', 'group', 'repeater', 'flexible_content', 'clone' ), true );
}

/**
 * Recursively scan mixed values for image IDs, arrays, and URLs.
 *
 * @param mixed   $value                        Value to scan.
 * @param array   $results                      Results array.
 * @param WP_Post $post                         Post object.
 * @param string  $source                       Source label.
 * @param string  $context                      Context label.
 * @param bool    $allow_numeric_attachment_ids Whether plain numeric values may be attachment IDs.
 */
function dius_scan_mixed_value_for_images( $value, &$results, WP_Post $post, $source, $context, $allow_numeric_attachment_ids = true ) {
	if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
		if ( ! $allow_numeric_attachment_ids ) {
			return;
		}

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

	$handled_keys          = array();
	$matched_attachment_id = 0;

	foreach ( array( 'ID', 'id', 'attachment_id', 'image_id' ) as $id_key ) {
		if ( isset( $value[ $id_key ] ) ) {
			$attachment_id = absint( $value[ $id_key ] );

			if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
				dius_add_attachment_usage( $results, $attachment_id, $post, $source, $context );
				$handled_keys[ $id_key ] = true;
				$matched_attachment_id   = $attachment_id;
				break;
			}
		}
	}

	foreach ( array( 'url', 'src', 'image', 'background_image', 'background' ) as $url_key ) {
		if ( isset( $value[ $url_key ] ) && is_string( $value[ $url_key ] ) && dius_is_image_url( $value[ $url_key ] ) ) {
			$handled_keys[ $url_key ] = true;

			if ( ! $matched_attachment_id ) {
				dius_add_image_usage( $results, $value[ $url_key ], $post, $source, $context );
			}
		}
	}

	if ( $matched_attachment_id ) {
		foreach ( array( 'sizes', 'width', 'height', 'alt', 'title', 'caption', 'description', 'mime_type', 'filesize', 'subtype', 'icon', 'filename', 'name' ) as $metadata_key ) {
			$handled_keys[ $metadata_key ] = true;
		}
	}

	foreach ( $value as $nested_key => $nested_value ) {
		if ( is_string( $nested_key ) && isset( $handled_keys[ $nested_key ] ) ) {
			continue;
		}

		$nested_context = is_string( $nested_key ) ? $context . ' > ' . $nested_key : $context;
		dius_scan_mixed_value_for_images( $nested_value, $results, $post, $source, $nested_context, $allow_numeric_attachment_ids );
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

	$parts = wp_parse_url( $image_url );

	if ( 0 === strpos( $image_url, '/' ) ) {
		$parts = wp_parse_url( home_url( '/' ) );
	}

	$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
	$host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	$port   = isset( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '';

	return $host ? $scheme . '://' . $host . $port . $path : $path;
}

/**
 * Get post IDs for a scan using the same query rules as the synchronous scanner.
 *
 * @param array $args Scan arguments.
 * @return array<int>
 */
function dius_get_scan_post_ids( $args ) {
	$post_types = isset( $args['post_types'] ) && is_array( $args['post_types'] ) ? array_filter( array_map( 'sanitize_key', $args['post_types'] ) ) : array();

	if ( empty( $post_types ) ) {
		return array();
	}

	$query_args = array(
		'post_type'              => $post_types,
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
			'scanned_posts'          => 0,
			'total_usages'           => 0,
			'unique_images'          => 0,
			'duplicate_images'       => 0,
			'media_duplicate_groups' => 0,
			'possible_unused_media'  => 0,
			'total_media_images'     => 0,
			'scanned_post_types'     => $args['post_types'],
			'acf_enabled'            => function_exists( 'get_fields' ),
			'elementor_enabled'      => true,
			'woocommerce_enabled'    => true,
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

		if ( ! empty( $args['include_elementor'] ) ) {
			dius_scan_elementor_data( $post, $state['results'] );
		}

		if ( ! empty( $args['include_woocommerce'] ) ) {
			dius_scan_woocommerce_media( $post, $state['results'] );
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
			'scanned_posts'          => 0,
			'total_usages'           => 0,
			'unique_images'          => 0,
			'duplicate_images'       => 0,
			'media_duplicate_groups' => 0,
			'possible_unused_media'  => 0,
			'total_media_images'     => 0,
			'scanned_post_types'     => $args['post_types'],
			'acf_enabled'            => function_exists( 'get_fields' ),
			'elementor_enabled'      => true,
			'woocommerce_enabled'    => true,
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

	$report = array(
		'args'        => $args,
		'stats'       => $stats,
		'all_images'  => $results,
		'duplicates'  => $duplicates,
		'media_audit' => array(
			'duplicate_groups' => array(),
			'unused_media'      => array(),
		),
	);

	return dius_add_media_library_audit_to_report( $report );
}

/**
 * Add optional media-library audit data to a finalized report.
 *
 * @param array $report Current report.
 * @return array
 */
function dius_add_media_library_audit_to_report( $report ) {
	$args = isset( $report['args'] ) && is_array( $report['args'] ) ? $report['args'] : dius_get_default_scan_args();

	if ( empty( $args['include_media_library'] ) ) {
		return $report;
	}

	$all_images          = isset( $report['all_images'] ) && is_array( $report['all_images'] ) ? $report['all_images'] : array();
	$used_attachment_ids = dius_get_used_attachment_ids_from_results( $all_images );
	$media_audit         = dius_scan_media_library( $args, $used_attachment_ids );

	$report['media_audit'] = $media_audit;

	if ( ! isset( $report['stats'] ) || ! is_array( $report['stats'] ) ) {
		$report['stats'] = array();
	}

	$report['stats']['media_duplicate_groups'] = count( $media_audit['duplicate_groups'] );
	$report['stats']['possible_unused_media']  = count( $media_audit['unused_media'] );
	$report['stats']['total_media_images']     = isset( $media_audit['total_media_images'] ) ? absint( $media_audit['total_media_images'] ) : 0;

	return $report;
}

/**
 * Get attachment IDs resolved during content scanning.
 *
 * @param array $results Image usage results.
 * @return array<int,bool>
 */
function dius_get_used_attachment_ids_from_results( $results ) {
	$ids = array();

	foreach ( $results as $image ) {
		if ( ! empty( $image['attachment_id'] ) ) {
			$ids[ absint( $image['attachment_id'] ) ] = true;
		}
	}

	return $ids;
}

/**
 * Scan media library for possible duplicate files and images not found in scanned content.
 *
 * @param array $args                Scan arguments.
 * @param array $used_attachment_ids Attachment IDs found in scanned content.
 * @return array
 */
function dius_scan_media_library( $args, $used_attachment_ids ) {
	$query_args = array(
		'post_type'              => 'attachment',
		'post_status'            => 'inherit',
		'post_mime_type'         => 'image',
		'posts_per_page'         => ! empty( $args['limit'] ) ? absint( $args['limit'] ) : -1,
		'orderby'                => 'ID',
		'order'                  => 'ASC',
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	$attachment_ids = get_posts( $query_args );
	$buckets        = array();
	$unused_media   = array();
	$total          = 0;

	foreach ( $attachment_ids as $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			continue;
		}

		$total++;
		$item = dius_build_media_audit_item( $attachment_id, false );

		if ( empty( $used_attachment_ids[ $attachment_id ] ) ) {
			$unused_media[] = $item;
		}

		if ( empty( $item['file_size'] ) || empty( $item['width'] ) || empty( $item['height'] ) ) {
			continue;
		}

		$signature = implode( '|', array( $item['file_size'], $item['width'] . 'x' . $item['height'], $item['mime_type'] ) );

		if ( ! isset( $buckets[ $signature ] ) ) {
			$buckets[ $signature ] = array();
		}

		$buckets[ $signature ][] = $item;
	}

	$duplicate_groups = array();

	foreach ( $buckets as $signature => $items ) {
		if ( count( $items ) < 2 ) {
			continue;
		}

		$hash_groups = array();

		foreach ( $items as $item ) {
			$item_with_hash = dius_build_media_audit_item( absint( $item['attachment_id'] ), true );
			$hash_key       = ! empty( $item_with_hash['file_hash'] ) ? 'hash:' . $item_with_hash['file_hash'] : 'signature:' . $signature;

			if ( ! isset( $hash_groups[ $hash_key ] ) ) {
				$hash_groups[ $hash_key ] = array();
			}

			$hash_groups[ $hash_key ][] = $item_with_hash;
		}

		foreach ( $hash_groups as $hash_key => $hash_items ) {
			if ( count( $hash_items ) < 2 ) {
				continue;
			}

			$duplicate_groups[] = array(
				'key'        => $hash_key,
				'confidence' => 0 === strpos( $hash_key, 'hash:' ) ? 'exact_file_match' : 'same_size_dimensions',
				'count'      => count( $hash_items ),
				'items'      => $hash_items,
			);
		}
	}

	usort(
		$duplicate_groups,
		static function ( $a, $b ) {
			if ( $a['count'] === $b['count'] ) {
				return strnatcasecmp( $a['items'][0]['filename'], $b['items'][0]['filename'] );
			}

			return $b['count'] <=> $a['count'];
		}
	);

	usort(
		$unused_media,
		static function ( $a, $b ) {
			return strnatcasecmp( $a['filename'], $b['filename'] );
		}
	);

	return array(
		'total_media_images' => $total,
		'duplicate_groups'   => $duplicate_groups,
		'unused_media'        => $unused_media,
	);
}

/**
 * Build a normalized media audit item.
 *
 * @param int  $attachment_id Attachment ID.
 * @param bool $include_hash  Whether to calculate a file hash.
 * @return array
 */
function dius_build_media_audit_item( $attachment_id, $include_hash = false ) {
	$attachment_id = absint( $attachment_id );
	$url           = wp_get_attachment_url( $attachment_id );
	$file          = get_attached_file( $attachment_id );
	$meta          = wp_get_attachment_metadata( $attachment_id );
	$path          = is_string( $file ) ? $file : '';
	$file_size     = 0;
	$file_hash     = '';

	if ( '' !== $path && is_readable( $path ) ) {
		$file_size = filesize( $path );
		$file_size = false === $file_size ? 0 : absint( $file_size );

		if ( $include_hash ) {
			$hash = md5_file( $path );
			$file_hash = is_string( $hash ) ? $hash : '';
		}
	}

	$width  = isset( $meta['width'] ) ? absint( $meta['width'] ) : 0;
	$height = isset( $meta['height'] ) ? absint( $meta['height'] ) : 0;

	if ( ( ! $width || ! $height ) && '' !== $path && is_readable( $path ) ) {
		$image_size = @getimagesize( $path );

		if ( is_array( $image_size ) ) {
			$width  = ! empty( $image_size[0] ) ? absint( $image_size[0] ) : $width;
			$height = ! empty( $image_size[1] ) ? absint( $image_size[1] ) : $height;
		}
	}

	$filename = basename( (string) wp_parse_url( (string) $url, PHP_URL_PATH ) );

	if ( '' === $filename && '' !== $path ) {
		$filename = basename( $path );
	}

	return array(
		'attachment_id' => $attachment_id,
		'filename'      => $filename,
		'url'           => $url ? $url : '',
		'edit_url'      => get_edit_post_link( $attachment_id, 'raw' ),
		'file_path'     => $path,
		'file_size'     => $file_size,
		'file_hash'     => $file_hash,
		'width'         => $width,
		'height'        => $height,
		'mime_type'     => get_post_mime_type( $attachment_id ),
	);
}

/**
 * Format bytes for admin display.
 *
 * @param int $bytes File size in bytes.
 * @return string
 */
function dius_format_bytes( $bytes ) {
	$bytes = absint( $bytes );

	if ( $bytes < 1024 ) {
		return sprintf(
			/* translators: %s: number of bytes. */
			__( '%s B', 'scan-duplicate-images' ),
			number_format_i18n( $bytes )
		);
	}

	$units = array( 'KB', 'MB', 'GB' );
	$value = $bytes / 1024;
	$unit  = 'KB';

	foreach ( $units as $index => $candidate ) {
		$unit = $candidate;
		if ( $value < 1024 || count( $units ) - 1 === $index ) {
			break;
		}
		$value /= 1024;
	}

	return number_format_i18n( $value, $value >= 10 ? 0 : 1 ) . ' ' . $unit;
}
