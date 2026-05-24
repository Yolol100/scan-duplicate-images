<?php
/**
 * Scan result finalization.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
 * Try to resolve an image URL to an attachment ID.
 *
 * @param string $original_url   Original URL.
 * @param string $normalized_url Normalized URL.
 * @return int
 */

/**
 * Finalize a scan state.
 *
 * @param array $state Current scan state.
 * @return array
 */
function dius_finalize_scan_state( $state ) {
	$results = isset( $state['results'] ) && is_array( $state['results'] ) ? $state['results'] : array();
	$stats   = isset( $state['stats'] ) && is_array( $state['stats'] ) ? $state['stats'] : array();

	foreach ( $results as $key => $image ) {
		$usages = isset( $image['usages'] ) && is_array( $image['usages'] ) ? $image['usages'] : array();

		$results[ $key ]['usages']            = array_values( $usages );
		$results[ $key ]['usage_count']       = count( $usages );
		$results[ $key ]['unique_post_count'] = dius_count_unique_usage_posts( $usages );
	}

	$duplicates = array_filter(
		$results,
		static function ( $image ) {
			return ( isset( $image['unique_post_count'] ) && $image['unique_post_count'] > 1 ) || ( isset( $image['usage_count'] ) && $image['usage_count'] > 1 );
		}
	);

	uasort(
		$duplicates,
		static function ( $a, $b ) {
			return ( $b['usage_count'] ?? 0 ) <=> ( $a['usage_count'] ?? 0 );
		}
	);

	$stats['total_usages']    = array_sum( array_map( static function ( $image ) { return absint( $image['usage_count'] ?? 0 ); }, $results ) );
	$stats['unique_images']   = count( $results );
	$stats['repeated_images'] = count( $duplicates );

	return array(
		'args'       => isset( $state['args'] ) && is_array( $state['args'] ) ? $state['args'] : dius_get_default_scan_args(),
		'stats'      => $stats,
		'images'     => $results,
		'duplicates' => array_values( $duplicates ),
	);
}

/**
 * Check whether an array is associative.
 *
 * @param array $array Array to check.
 * @return bool
 */
