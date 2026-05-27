<?php
/**
 * Cache, locking, and chunked scan result helpers.
 *
 * @package MediaInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register a runtime cache or option key.
 *
 * @param string $kind Key kind: transient or option.
 * @param string $key  Runtime key.
 */
function media_insight_register_runtime_key( $kind, $key ) {
	$kind = sanitize_key( $kind );
	$key  = sanitize_key( $key );

	if ( ! in_array( $kind, array( 'transient', 'option' ), true ) || '' === $key ) {
		return;
	}

	media_insight_prune_runtime_registry();

	$registry = get_option( 'media_insight_runtime_registry', array() );
	$registry = is_array( $registry ) ? $registry : array();

	if ( ! isset( $registry[ $kind ] ) || ! is_array( $registry[ $kind ] ) ) {
		$registry[ $kind ] = array();
	}

	if ( ! isset( $registry[ $kind ][ $key ] ) ) {
		$registry[ $kind ][ $key ] = 1;
		update_option( 'media_insight_runtime_registry', $registry, false );
	}
}

/**
 * Remove a runtime key from the uninstall registry.
 *
 * @param string $kind Key kind: transient or option.
 * @param string $key  Runtime key.
 */
function media_insight_unregister_runtime_key( $kind, $key ) {
	$kind = sanitize_key( $kind );
	$key  = sanitize_key( $key );

	$registry = get_option( 'media_insight_runtime_registry', array() );
	if ( ! is_array( $registry ) || empty( $registry[ $kind ][ $key ] ) ) {
		return;
	}

	unset( $registry[ $kind ][ $key ] );

	if ( isset( $registry[ $kind ] ) && empty( $registry[ $kind ] ) ) {
		unset( $registry[ $kind ] );
	}

	if ( empty( $registry ) ) {
		delete_option( 'media_insight_runtime_registry' );
		return;
	}

	update_option( 'media_insight_runtime_registry', $registry, false );
}

/**
 * Prune runtime registry entries whose transient or option no longer exists.
 */
function media_insight_prune_runtime_registry() {
	static $pruned = false;

	if ( $pruned ) {
		return;
	}

	$pruned  = true;
	$changed = false;
	$registry = get_option( 'media_insight_runtime_registry', array() );

	if ( ! is_array( $registry ) ) {
		delete_option( 'media_insight_runtime_registry' );
		return;
	}

	if ( isset( $registry['transient'] ) && is_array( $registry['transient'] ) ) {
		foreach ( array_keys( $registry['transient'] ) as $key ) {
			$key = sanitize_key( $key );
			if ( 0 !== strpos( $key, 'media_insight_' ) || false === get_transient( $key ) ) {
				unset( $registry['transient'][ $key ] );
				$changed = true;
			}
		}
	}

	if ( isset( $registry['option'] ) && is_array( $registry['option'] ) ) {
		foreach ( array_keys( $registry['option'] ) as $key ) {
			$key = sanitize_key( $key );
			if ( 0 !== strpos( $key, 'media_insight_lock_' ) || null === get_option( $key, null ) ) {
				unset( $registry['option'][ $key ] );
				$changed = true;
			}
		}
	}

	foreach ( array( 'transient', 'option' ) as $kind ) {
		if ( isset( $registry[ $kind ] ) && empty( $registry[ $kind ] ) ) {
			unset( $registry[ $kind ] );
			$changed = true;
		}
	}

	if ( empty( $registry ) ) {
		delete_option( 'media_insight_runtime_registry' );
		return;
	}

	if ( $changed ) {
		update_option( 'media_insight_runtime_registry', $registry, false );
	}
}

/**
 * Read a value from object cache with transient fallback.
 *
 * @param string   $type    Cache type.
 * @param string   $scan_id Scan ID.
 * @param int|null $user_id User ID.
 * @return mixed
 */
function media_insight_cache_get( $type, $scan_id, $user_id = null ) {
	$key    = media_insight_get_scan_transient_key( $type, $scan_id, $user_id );
	$cached = wp_cache_get( $key, MEDIA_INSIGHT_CACHE_GROUP );

	if ( false !== $cached ) {
		return $cached;
	}

	$value = get_transient( $key );

	if ( false !== $value ) {
		wp_cache_set( $key, $value, MEDIA_INSIGHT_CACHE_GROUP, MEDIA_INSIGHT_TRANSIENT_TTL );
	}

	return $value;
}

/**
 * Store a value in object cache and transients.
 *
 * @param string   $type    Cache type.
 * @param string   $scan_id Scan ID.
 * @param mixed    $value   Value.
 * @param int|null $user_id User ID.
 */
function media_insight_cache_set( $type, $scan_id, $value, $user_id = null ) {
	$key = media_insight_get_scan_transient_key( $type, $scan_id, $user_id );

	wp_cache_set( $key, $value, MEDIA_INSIGHT_CACHE_GROUP, MEDIA_INSIGHT_TRANSIENT_TTL );
	set_transient( $key, $value, MEDIA_INSIGHT_TRANSIENT_TTL );
	media_insight_register_runtime_key( 'transient', $key );
}

/**
 * Delete a cached value.
 *
 * @param string   $type    Cache type.
 * @param string   $scan_id Scan ID.
 * @param int|null $user_id User ID.
 */
function media_insight_cache_delete( $type, $scan_id, $user_id = null ) {
	$key = media_insight_get_scan_transient_key( $type, $scan_id, $user_id );

	wp_cache_delete( $key, MEDIA_INSIGHT_CACHE_GROUP );
	delete_transient( $key );
	media_insight_unregister_runtime_key( 'transient', $key );
}

/**
 * Build a compact option name for the atomic scan lock.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 * @return string
 */
function media_insight_get_lock_option_name( $scan_id, $user_id ) {
	return 'media_insight_lock_' . absint( $user_id ) . '_' . substr( wp_hash( sanitize_key( $scan_id ) ), 0, 32 );
}

/**
 * Acquire an atomic scan lock.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 * @param int    $ttl     Lock TTL in seconds.
 * @return bool
 */
function media_insight_acquire_scan_lock( $scan_id, $user_id, $ttl = 30 ) {
	$scan_id     = sanitize_key( $scan_id );
	$user_id     = absint( $user_id );
	$ttl         = max( 5, absint( $ttl ) );
	$now         = time();
	$option_name = media_insight_get_lock_option_name( $scan_id, $user_id );
	$cache_key   = 'lock_' . $user_id . '_' . $scan_id;
	$existing    = get_option( $option_name, false );

	if ( false !== $existing && ( $now - absint( $existing ) ) >= $ttl ) {
		delete_option( $option_name );
		wp_cache_delete( $cache_key, MEDIA_INSIGHT_CACHE_GROUP );
	}

	$added = add_option( $option_name, $now, '', 'no' );

	if ( $added ) {
		media_insight_register_runtime_key( 'option', $option_name );
		wp_cache_add( $cache_key, $now, MEDIA_INSIGHT_CACHE_GROUP, $ttl );
		return true;
	}

	return false;
}

/**
 * Release an atomic scan lock.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 */
function media_insight_release_scan_lock( $scan_id, $user_id ) {
	$scan_id   = sanitize_key( $scan_id );
	$user_id   = absint( $user_id );
	$cache_key = 'lock_' . $user_id . '_' . $scan_id;

	$option_name = media_insight_get_lock_option_name( $scan_id, $user_id );

	wp_cache_delete( $cache_key, MEDIA_INSIGHT_CACHE_GROUP );
	delete_option( $option_name );
	media_insight_unregister_runtime_key( 'option', $option_name );
}

/**
 * Check whether a scan status should not be overwritten by stale workers.
 *
 * @param string $status Scan status.
 * @return bool
 */
function media_insight_is_terminal_scan_status( $status ) {
	return in_array( sanitize_key( $status ), array( 'complete', 'failed', 'cancelled' ), true );
}

/**
 * Store normalized scan status.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 * @param array  $status  Status data.
 */
function media_insight_set_scan_status( $scan_id, $user_id, $status ) {
	$defaults = array(
		'scan_id'    => sanitize_key( $scan_id ),
		'user_id'    => absint( $user_id ),
		'status'     => 'queued',
		'processed'  => 0,
		'total'      => 0,
		'progress'   => 0,
		'message'    => '',
		'created_at' => time(),
		'updated_at' => time(),
	);

	$existing = media_insight_cache_get( 'status', $scan_id, $user_id );
	if ( is_array( $existing ) ) {
		$existing_status = sanitize_key( $existing['status'] ?? '' );

		if ( media_insight_is_terminal_scan_status( $existing_status ) ) {
			return;
		}

		$defaults = wp_parse_args( $existing, $defaults );
	}

	$status = wp_parse_args( $status, $defaults );
	$status['scan_id']    = sanitize_key( $scan_id );
	$status['user_id']    = absint( $user_id );
	$status['processed']  = absint( $status['processed'] );
	$status['total']      = absint( $status['total'] );
	$status['status']     = sanitize_key( $status['status'] );

	if ( 'complete' === $status['status'] ) {
		$status['processed'] = max( $status['processed'], $status['total'] );
		$status['progress']  = 100;
	} else {
		$status['progress'] = $status['total'] > 0 ? min( 100, round( ( $status['processed'] / $status['total'] ) * 100 ) ) : 0;
	}

	$status['message']    = sanitize_text_field( $status['message'] );
	$status['updated_at'] = time();

	media_insight_cache_set( 'status', $scan_id, $status, $user_id );
}

/**
 * Get normalized scan status.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 * @return array|null
 */
function media_insight_get_scan_status( $scan_id, $user_id ) {
	$status = media_insight_cache_get( 'status', $scan_id, $user_id );

	return is_array( $status ) ? $status : null;
}

/**
 * Get the result bucket identifier for an image key.
 *
 * @param string $image_key Result image key.
 * @return string
 */
function media_insight_get_result_bucket_id( $image_key ) {
	$buckets = defined( 'MEDIA_INSIGHT_RESULT_BUCKETS' ) ? max( 1, absint( MEDIA_INSIGHT_RESULT_BUCKETS ) ) : 16;
	$bucket  = absint( sprintf( '%u', crc32( (string) $image_key ) ) ) % $buckets;

	return 'result_' . $bucket;
}

/**
 * Merge a result image into an existing result map.
 *
 * @param array $target Existing result map.
 * @param array $image  New image result.
 */
function media_insight_merge_result_image( &$target, $image ) {
	if ( ! is_array( $image ) || empty( $image['key'] ) ) {
		return;
	}

	$key = (string) $image['key'];

	if ( ! isset( $target[ $key ] ) ) {
		$target[ $key ] = $image;
		return;
	}

	$existing_usages = isset( $target[ $key ]['usages'] ) && is_array( $target[ $key ]['usages'] ) ? $target[ $key ]['usages'] : array();
	$new_usages      = isset( $image['usages'] ) && is_array( $image['usages'] ) ? $image['usages'] : array();

	foreach ( $new_usages as $usage_key => $usage ) {
		$existing_usages[ $usage_key ] = $usage;
	}

	$target[ $key ]['usages'] = $existing_usages;
}

/**
 * Merge batch-local image results into chunked cache buckets.
 *
 * @param string $scan_id       Scan ID.
 * @param int    $user_id       User ID.
 * @param array  $batch_results Batch-local result map.
 */
function media_insight_merge_scan_result_chunks( $scan_id, $user_id, $batch_results ) {
	if ( empty( $batch_results ) || ! is_array( $batch_results ) ) {
		return;
	}

	$grouped = array();

	foreach ( $batch_results as $image ) {
		if ( ! is_array( $image ) || empty( $image['key'] ) ) {
			continue;
		}

		$bucket = media_insight_get_result_bucket_id( $image['key'] );

		if ( ! isset( $grouped[ $bucket ] ) ) {
			$grouped[ $bucket ] = array();
		}

		media_insight_merge_result_image( $grouped[ $bucket ], $image );
	}

	foreach ( $grouped as $bucket => $images ) {
		$existing = media_insight_cache_get( $bucket, $scan_id, $user_id );
		$existing = is_array( $existing ) ? $existing : array();

		foreach ( $images as $image ) {
			media_insight_merge_result_image( $existing, $image );
		}

		media_insight_cache_set( $bucket, $scan_id, $existing, $user_id );
	}
}

/**
 * Read all chunked scan results and merge them into one map for finalization.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 * @return array
 */
function media_insight_get_all_scan_results( $scan_id, $user_id ) {
	$buckets = defined( 'MEDIA_INSIGHT_RESULT_BUCKETS' ) ? max( 1, absint( MEDIA_INSIGHT_RESULT_BUCKETS ) ) : 16;
	$results = array();

	for ( $index = 0; $index < $buckets; $index++ ) {
		$chunk = media_insight_cache_get( 'result_' . $index, $scan_id, $user_id );

		if ( ! is_array( $chunk ) ) {
			continue;
		}

		foreach ( $chunk as $image ) {
			media_insight_merge_result_image( $results, $image );
		}
	}

	return $results;
}

/**
 * Delete all chunked scan result buckets.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 */
function media_insight_delete_scan_result_chunks( $scan_id, $user_id ) {
	$buckets = defined( 'MEDIA_INSIGHT_RESULT_BUCKETS' ) ? max( 1, absint( MEDIA_INSIGHT_RESULT_BUCKETS ) ) : 16;

	for ( $index = 0; $index < $buckets; $index++ ) {
		media_insight_cache_delete( 'result_' . $index, $scan_id, $user_id );
	}
}

/**
 * Delete all cached runtime data for one scan.
 *
 * @param string $scan_id      Scan ID.
 * @param int    $user_id      User ID.
 * @param bool   $release_lock Whether to release the scan lock.
 */
function media_insight_delete_scan_runtime_data( $scan_id, $user_id, $release_lock = true ) {
	media_insight_cache_delete( 'state', $scan_id, $user_id );
	media_insight_cache_delete( 'report', $scan_id, $user_id );
	media_insight_delete_scan_result_chunks( $scan_id, $user_id );

	if ( $release_lock ) {
		media_insight_release_scan_lock( $scan_id, $user_id );
	}
}

/**
 * Return a public REST-safe report payload.
 *
 * @param array $report Full report.
 * @return array
 */
function media_insight_prepare_report_payload( $report ) {
	$stats      = isset( $report['stats'] ) && is_array( $report['stats'] ) ? $report['stats'] : array();
	$duplicates = isset( $report['duplicates'] ) && is_array( $report['duplicates'] ) ? $report['duplicates'] : array();
	$prepared   = array();

	foreach ( $duplicates as $image ) {
		if ( ! is_array( $image ) ) {
			continue;
		}

		$usages          = isset( $image['usages'] ) && is_array( $image['usages'] ) ? array_values( $image['usages'] ) : array();
		$prepared_usages = array();

		foreach ( $usages as $usage ) {
			if ( ! is_array( $usage ) ) {
				continue;
			}

			$prepared_usages[] = array(
				'post_id'    => absint( $usage['post_id'] ?? 0 ),
				'post_title' => sanitize_text_field( $usage['post_title'] ?? '' ),
				'post_type'  => sanitize_key( $usage['post_type'] ?? '' ),
				'edit_url'   => esc_url_raw( $usage['edit_url'] ?? '' ),
				'source'     => sanitize_key( $usage['source'] ?? '' ),
				'context'    => sanitize_text_field( $usage['context'] ?? '' ),
			);
		}

		$prepared[] = array(
			'key'               => sanitize_text_field( $image['key'] ?? '' ),
			'attachment_id'     => absint( $image['attachment_id'] ?? 0 ),
			'filename'          => sanitize_text_field( $image['filename'] ?? '' ),
			'url'               => esc_url_raw( $image['url'] ?? '' ),
			'thumbnail_url'     => esc_url_raw( $image['thumbnail_url'] ?? '' ),
			'media_edit_url'    => esc_url_raw( $image['media_edit_url'] ?? '' ),
			'alt_text'          => sanitize_text_field( $image['alt_text'] ?? '' ),
			'unique_post_count' => absint( $image['unique_post_count'] ?? 0 ),
			'usage_count'       => absint( $image['usage_count'] ?? 0 ),
			'usages'            => $prepared_usages,
		);
	}

	return array(
		'stats'      => array(
			'scanned_items'   => absint( $stats['scanned_items'] ?? 0 ),
			'scanned_pages'   => absint( $stats['scanned_pages'] ?? 0 ),
			'scanned_posts'   => absint( $stats['scanned_posts'] ?? 0 ),
			'total_usages'    => absint( $stats['total_usages'] ?? 0 ),
			'unique_images'   => absint( $stats['unique_images'] ?? 0 ),
			'repeated_images' => absint( $stats['repeated_images'] ?? 0 ),
			'featured_usages' => absint( $stats['featured_usages'] ?? 0 ),
			'acf_page_usages' => absint( $stats['acf_page_usages'] ?? 0 ),
			'acf_enabled'     => ! empty( $stats['acf_enabled'] ),
		),
		'duplicates' => $prepared,
	);
}
