<?php
/**
 * Image normalization and result helpers.
 *
 * @package MediaInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decide if an array looks like an ACF image array.
 *
 * @param array $value Value array.
 * @return bool
 */
function media_insight_array_looks_like_acf_image( $value ) {
	$mime_type = isset( $value['mime_type'] ) && is_string( $value['mime_type'] ) ? strtolower( $value['mime_type'] ) : '';
	$subtype   = isset( $value['subtype'] ) && is_string( $value['subtype'] ) ? strtolower( $value['subtype'] ) : '';

	if ( 'image/svg+xml' === $mime_type || 'svg' === $subtype ) {
		return false;
	}

	if ( '' !== $mime_type && 0 === strpos( $mime_type, 'image/' ) ) {
		return true;
	}

	if ( isset( $value['type'] ) && 'image' === $value['type'] ) {
		return true;
	}

	if ( in_array( $subtype, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' ), true ) ) {
		return true;
	}

	if ( isset( $value['url'] ) && is_string( $value['url'] ) && media_insight_is_image_url( $value['url'] ) ) {
		return true;
	}

	return false;
}

/**
 * Get attachment ID from an ACF image array.
 *
 * @param array $value ACF value.
 * @return int
 */
function media_insight_get_attachment_id_from_acf_array( $value ) {
	foreach ( array( 'ID', 'id', 'attachment_id' ) as $id_key ) {
		if ( isset( $value[ $id_key ] ) ) {
			$attachment_id = absint( $value[ $id_key ] );

			if ( $attachment_id && media_insight_attachment_is_supported_image( $attachment_id ) ) {
				return $attachment_id;
			}
		}
	}

	return 0;
}

/**
 * Check if an attachment is a supported raster image for this focused scan.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function media_insight_attachment_is_supported_image( $attachment_id ) {
	$attachment_id = absint( $attachment_id );

	if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
		return false;
	}

	$mime_type = get_post_mime_type( $attachment_id );

	if ( 'image/svg+xml' === strtolower( (string) $mime_type ) ) {
		return false;
	}

	return true;
}

/**
 * Get a small preview URL for an attachment.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function media_insight_get_attachment_preview_url( $attachment_id ) {
	$attachment_id = absint( $attachment_id );

	if ( ! $attachment_id ) {
		return '';
	}

	$thumbnail = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
	if ( is_array( $thumbnail ) && ! empty( $thumbnail[0] ) ) {
		return esc_url_raw( $thumbnail[0] );
	}

	$url = wp_get_attachment_url( $attachment_id );
	return $url ? esc_url_raw( $url ) : '';
}

/**
 * Get the media library edit URL for an attachment.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function media_insight_get_attachment_edit_url( $attachment_id ) {
	$attachment_id = absint( $attachment_id );

	if ( ! $attachment_id ) {
		return '';
	}

	$edit_url = get_edit_post_link( $attachment_id, 'raw' );
	return $edit_url ? esc_url_raw( $edit_url ) : '';
}

/**
 * Get sanitized attachment alt text.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function media_insight_get_attachment_alt_text( $attachment_id ) {
	$attachment_id = absint( $attachment_id );

	if ( ! $attachment_id ) {
		return '';
	}

	return sanitize_text_field( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
}

/**
 * Add an attachment usage by ID.
 *
 * @param array   $results       Results array.
 * @param int     $attachment_id Attachment ID.
 * @param WP_Post $post          Post object.
 * @param string  $source        Source label.
 * @param string  $context       Context label.
 * @return bool True when a new usage was added.
 */
function media_insight_add_attachment_usage( &$results, $attachment_id, WP_Post $post, $source, $context ) {
	$url = wp_get_attachment_url( $attachment_id );

	if ( ! $url ) {
		return false;
	}

	return media_insight_add_image_usage( $results, $url, $post, $source, $context, absint( $attachment_id ) );
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
 * @return bool True when a new usage was added.
 */
function media_insight_add_image_usage( &$results, $image_url, WP_Post $post, $source, $context, $attachment_id = 0 ) {
	$normalized_url = media_insight_normalize_image_url( $image_url );

	if ( '' === $normalized_url || ! media_insight_is_image_url( $normalized_url ) ) {
		return false;
	}

	if ( ! $attachment_id ) {
		$attachment_id = media_insight_get_attachment_id_from_url( $image_url, $normalized_url );
	}

	$key            = $attachment_id ? 'attachment:' . absint( $attachment_id ) : 'url:' . $normalized_url;
	$display_url    = $attachment_id ? wp_get_attachment_url( $attachment_id ) : $normalized_url;
	$display_url    = $display_url ? $display_url : $normalized_url;
	$filename       = basename( (string) wp_parse_url( $display_url, PHP_URL_PATH ) );
	$thumbnail_url  = $attachment_id ? media_insight_get_attachment_preview_url( $attachment_id ) : esc_url_raw( $display_url );
	$media_edit_url = $attachment_id ? media_insight_get_attachment_edit_url( $attachment_id ) : '';
	$alt_text       = $attachment_id ? media_insight_get_attachment_alt_text( $attachment_id ) : '';

	if ( ! isset( $results[ $key ] ) ) {
		$results[ $key ] = array(
			'key'               => $key,
			'attachment_id'     => $attachment_id ? absint( $attachment_id ) : 0,
			'url'               => esc_url_raw( $display_url ),
			'thumbnail_url'     => $thumbnail_url,
			'media_edit_url'    => $media_edit_url,
			'alt_text'          => $alt_text,
			'filename'          => sanitize_file_name( $filename ),
			'usages'            => array(),
			'usage_count'       => 0,
			'unique_post_count' => 0,
		);
	}

	$usage = array(
		'post_id'    => absint( $post->ID ),
		'post_title' => sanitize_text_field( get_the_title( $post ) ),
		'post_type'  => sanitize_key( $post->post_type ),
		'edit_url'   => esc_url_raw( get_edit_post_link( $post->ID, 'raw' ) ),
		'source'     => sanitize_key( $source ),
		'context'    => sanitize_text_field( $context ),
	);

	$usage_key = wp_hash( wp_json_encode( $usage ) );

	if ( ! isset( $results[ $key ]['usages'][ $usage_key ] ) ) {
		$results[ $key ]['usages'][ $usage_key ] = $usage;
		return true;
	}

	return false;
}

/**
 * Try to resolve an image URL to an attachment ID.
 *
 * @param string $original_url   Original URL.
 * @param string $normalized_url Normalized URL.
 * @return int
 */
function media_insight_get_attachment_id_from_url( $original_url, $normalized_url ) {
	foreach ( array( $original_url, $normalized_url ) as $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			continue;
		}

		$attachment_id = attachment_url_to_postid( $url );

		if ( $attachment_id && media_insight_attachment_is_supported_image( $attachment_id ) ) {
			return absint( $attachment_id );
		}
	}

	return 0;
}

/**
 * Check whether a URL scheme is safe for admin links.
 *
 * @param string $url URL to check.
 * @return bool
 */
function media_insight_has_supported_image_url_scheme( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url || 0 === strpos( $url, '/' ) ) {
		return true;
	}

	$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

	if ( null === $scheme ) {
		return true;
	}

	return in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true );
}

/**
 * Check whether a URL points to a supported image file.
 *
 * @param string $url URL to check.
 * @return bool
 */
function media_insight_is_image_url( $url ) {
	$url = html_entity_decode( (string) $url, ENT_QUOTES, get_bloginfo( 'charset' ) );

	if ( ! media_insight_has_supported_image_url_scheme( $url ) ) {
		return false;
	}

	$path = wp_parse_url( $url, PHP_URL_PATH );

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
function media_insight_normalize_image_url( $image_url ) {
	$image_url = trim( html_entity_decode( (string) $image_url, ENT_QUOTES, get_bloginfo( 'charset' ) ) );

	if ( '' === $image_url || ! media_insight_has_supported_image_url_scheme( $image_url ) ) {
		return '';
	}

	$split     = preg_split( '/[?#]/', $image_url, 2 );
	$image_url = is_array( $split ) && isset( $split[0] ) ? $split[0] : $image_url;

	if ( ! media_insight_has_supported_image_url_scheme( $image_url ) ) {
		return '';
	}

	if ( 0 === strpos( $image_url, '//' ) ) {
		$image_url = ( is_ssl() ? 'https:' : 'http:' ) . $image_url;
	}

	$parts = wp_parse_url( $image_url );

	if ( ! is_array( $parts ) ) {
		return '';
	}

	$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';

	if ( '' === $path ) {
		return '';
	}

	$path = preg_replace( '/-\d+x\d+(?=\.(?:jpg|jpeg|png|gif|webp|avif)$)/i', '', $path );
	$path = preg_replace( '/-scaled(?=\.(?:jpg|jpeg|png|gif|webp|avif)$)/i', '', $path );

	if ( 0 === strpos( $image_url, '/' ) ) {
		$parts = wp_parse_url( home_url( '/' ) );
	}

	$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'https';
	$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
	$port   = isset( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '';

	if ( '' !== $scheme && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return '';
	}

	return $host ? $scheme . '://' . $host . $port . $path : $path;
}
