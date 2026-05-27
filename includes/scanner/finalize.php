<?php
/**
 * Scan result finalization.
 *
 * @package MediaInsight
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
function media_insight_count_unique_usage_posts( $usages ) {
	$post_ids = array();

	foreach ( $usages as $usage ) {
		if ( ! is_array( $usage ) ) {
			continue;
		}

		if ( isset( $usage['post_id'] ) ) {
			$post_ids[] = absint( $usage['post_id'] );
		}
	}

	return count( array_unique( $post_ids ) );
}

/**
 * Finalize a scan state.
 *
 * @param array $state   Current scan state.
 * @param array $results Result map.
 * @return array
 */
function media_insight_finalize_scan_state( $state, $results ) {
	$results = is_array( $results ) ? $results : array();

	$stats = isset( $state['stats'] ) && is_array( $state['stats'] ) ? $state['stats'] : array();

	foreach ( $results as $key => $image ) {
		if ( ! is_array( $image ) ) {
			unset( $results[ $key ] );
			continue;
		}

		$usages = isset( $image['usages'] ) && is_array( $image['usages'] ) ? $image['usages'] : array();

		$results[ $key ]['usages']            = array_values( $usages );
		$results[ $key ]['usage_count']       = count( $usages );
		$results[ $key ]['unique_post_count'] = media_insight_count_unique_usage_posts( $usages );
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

	$stats['total_usages']    = array_sum(
		array_map(
			static function ( $image ) {
				return absint( $image['usage_count'] ?? 0 );
			},
			$results
		)
	);
	$stats['unique_images']   = count( $results );
	$stats['repeated_images'] = count( $duplicates );

	return array(
		'args'       => isset( $state['args'] ) && is_array( $state['args'] ) ? $state['args'] : media_insight_get_default_scan_args(),
		'stats'      => $stats,
		'duplicates' => array_values( $duplicates ),
	);
}

/**
 * Check whether an array is associative.
 *
 * @param array $array Array to check.
 * @return bool
 */
function media_insight_is_assoc_array( $array ) {
	if ( array() === $array ) {
		return false;
	}

	return array_keys( $array ) !== range( 0, count( $array ) - 1 );
}
