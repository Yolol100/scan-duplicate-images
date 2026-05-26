<?php
/**
 * Background scan worker.
 *
 * @package MediaInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build a stable key for a scheduled scan event.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 * @return string
 */
function media_insight_get_scheduled_scan_key( $scan_id, $user_id ) {
	return sanitize_key( absint( $user_id ) . '_' . sanitize_key( $scan_id ) );
}

/**
 * Register a scheduled scan event so deactivation can clear exact events
 * without inspecting WordPress' internal cron array.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 */
function media_insight_register_scheduled_scan( $scan_id, $user_id ) {
	$scan_id = sanitize_key( $scan_id );
	$user_id = absint( $user_id );

	if ( ! $scan_id || ! $user_id ) {
		return;
	}

	$events = get_option( 'media_insight_scheduled_scans', array() );
	$events = is_array( $events ) ? $events : array();
	$key    = media_insight_get_scheduled_scan_key( $scan_id, $user_id );

	if ( isset( $events[ $key ] ) ) {
		return;
	}

	$events[ $key ] = array(
		'scan_id' => $scan_id,
		'user_id' => $user_id,
	);

	update_option( 'media_insight_scheduled_scans', $events, false );
}

/**
 * Unregister a scheduled scan event from the exact-event registry.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 */
function media_insight_unregister_scheduled_scan( $scan_id, $user_id ) {
	$events = get_option( 'media_insight_scheduled_scans', array() );
	if ( ! is_array( $events ) ) {
		return;
	}

	$key = media_insight_get_scheduled_scan_key( $scan_id, $user_id );
	if ( ! isset( $events[ $key ] ) ) {
		return;
	}

	unset( $events[ $key ] );

	if ( empty( $events ) ) {
		delete_option( 'media_insight_scheduled_scans' );
		return;
	}

	update_option( 'media_insight_scheduled_scans', $events, false );
}

/**
 * Clear the exact WP Cron event and remove it from the plugin registry.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 */
function media_insight_clear_scheduled_scan( $scan_id, $user_id ) {
	$scan_id = sanitize_key( $scan_id );
	$user_id = absint( $user_id );

	if ( ! $scan_id || ! $user_id ) {
		return;
	}

	wp_clear_scheduled_hook( 'media_insight_process_scan_event', array( $scan_id, $user_id ) );
	media_insight_unregister_scheduled_scan( $scan_id, $user_id );
}

/**
 * Queue a scan in WP Cron.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 */
function media_insight_queue_scan( $scan_id, $user_id ) {
	$scan_id = sanitize_key( $scan_id );
	$user_id = absint( $user_id );

	if ( ! $scan_id || ! $user_id ) {
		return;
	}

	$args      = array( $scan_id, $user_id );
	$scheduled = wp_next_scheduled( 'media_insight_process_scan_event', $args );

	if ( ! $scheduled ) {
		$scheduled = wp_schedule_single_event( time() + 2, 'media_insight_process_scan_event', $args );
	}

	if ( $scheduled ) {
		media_insight_register_scheduled_scan( $scan_id, $user_id );
	}
}

/**
 * WP Cron callback for background scans.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 */
function media_insight_process_scan_event( $scan_id, $user_id ) {
	media_insight_run_scan_batches( $scan_id, $user_id, MEDIA_INSIGHT_BACKGROUND_MAX_BATCHES, MEDIA_INSIGHT_BACKGROUND_TIME_BUDGET );
}

/**
 * Return a scan-state processed count safely.
 *
 * @param array $state Scan state.
 * @return int
 */
function media_insight_get_state_processed_count( $state ) {
	return absint( $state['processed'] ?? 0 );
}

/**
 * Stop processing when a scan has been cancelled by another request.
 *
 * @param string $scan_id Scan ID.
 * @param int    $user_id User ID.
 * @return array|null Normal worker response when cancelled, otherwise null.
 */
function media_insight_get_cancelled_scan_response( $scan_id, $user_id ) {
	$status = media_insight_get_scan_status( $scan_id, $user_id );

	if ( ! is_array( $status ) || 'cancelled' !== ( $status['status'] ?? '' ) ) {
		return null;
	}

	media_insight_delete_scan_runtime_data( $scan_id, $user_id, false );
	media_insight_clear_scheduled_scan( $scan_id, $user_id );

	return array(
		'success' => true,
		'status'  => $status,
	);
}

/**
 * Run one or more batches for a queued scan.
 *
 * @param string $scan_id     Scan ID.
 * @param int    $user_id     User ID.
 * @param int    $max_batches Maximum batches.
 * @param int    $time_budget Time budget in seconds.
 * @return array Status payload.
 */
function media_insight_run_scan_batches( $scan_id, $user_id, $max_batches = 1, $time_budget = 3 ) {
	$scan_id          = sanitize_key( $scan_id );
	$user_id          = absint( $user_id );
	$max_batches      = max( 1, absint( $max_batches ) );
	$time_budget      = max( 1, absint( $time_budget ) );
	$start_time       = time();
	$previous_user_id = get_current_user_id();
	$lock_acquired    = false;

	$initial_status = media_insight_get_scan_status( $scan_id, $user_id );
	if ( ! is_array( $initial_status ) ) {
		media_insight_clear_scheduled_scan( $scan_id, $user_id );
		media_insight_delete_scan_runtime_data( $scan_id, $user_id, false );

		return array(
			'success' => false,
			'message' => __( 'The scan was not found or has expired.', 'media-insight' ),
		);
	}

	$initial_status_key = sanitize_key( $initial_status['status'] ?? '' );
	if ( 'complete' === $initial_status_key ) {
		$report = media_insight_cache_get( 'report', $scan_id, $user_id );
		if ( is_array( $report ) ) {
			$initial_status['report'] = media_insight_prepare_report_payload( $report );
		}
		media_insight_clear_scheduled_scan( $scan_id, $user_id );
		return array( 'success' => true, 'status' => $initial_status );
	}

	if ( 'cancelled' === $initial_status_key ) {
		media_insight_clear_scheduled_scan( $scan_id, $user_id );
		media_insight_delete_scan_runtime_data( $scan_id, $user_id, false );
		return array( 'success' => true, 'status' => $initial_status );
	}

	if ( 'failed' === $initial_status_key ) {
		media_insight_clear_scheduled_scan( $scan_id, $user_id );
		media_insight_delete_scan_runtime_data( $scan_id, $user_id, false );
		return array( 'success' => true, 'status' => $initial_status );
	}

	if ( ! user_can( $user_id, 'manage_options' ) ) {
		media_insight_set_scan_status(
			$scan_id,
			$user_id,
			array(
				'status'  => 'failed',
				'message' => __( 'The scan owner no longer has permission to run scans.', 'media-insight' ),
			)
		);

		$current_status = media_insight_get_scan_status( $scan_id, $user_id );
		if ( is_array( $current_status ) && 'failed' === sanitize_key( $current_status['status'] ?? '' ) ) {
			media_insight_delete_scan_runtime_data( $scan_id, $user_id, false );
		}

		media_insight_clear_scheduled_scan( $scan_id, $user_id );

		return array(
			'success' => false,
			'message' => __( 'The scan owner no longer has permission to run scans.', 'media-insight' ),
		);
	}

	if ( $previous_user_id !== $user_id ) {
		wp_set_current_user( $user_id );
	}

	try {
		$status = media_insight_get_scan_status( $scan_id, $user_id );
		if ( ! is_array( $status ) ) {
			media_insight_clear_scheduled_scan( $scan_id, $user_id );
			media_insight_delete_scan_runtime_data( $scan_id, $user_id, false );

			return array(
				'success' => false,
				'message' => __( 'The scan was not found or has expired.', 'media-insight' ),
			);
		}

		$cancelled_response = media_insight_get_cancelled_scan_response( $scan_id, $user_id );
		if ( is_array( $cancelled_response ) ) {
			return $cancelled_response;
		}

		if ( 'failed' === sanitize_key( $status['status'] ?? '' ) ) {
			media_insight_clear_scheduled_scan( $scan_id, $user_id );
			media_insight_delete_scan_runtime_data( $scan_id, $user_id, false );
			return array( 'success' => true, 'status' => $status );
		}

		if ( ! media_insight_acquire_scan_lock( $scan_id, $user_id, 30 ) ) {
			return array( 'success' => true, 'status' => $status );
		}

		$lock_acquired = true;

		$cancelled_response = media_insight_get_cancelled_scan_response( $scan_id, $user_id );
		if ( is_array( $cancelled_response ) ) {
			return $cancelled_response;
		}

		$state = media_insight_cache_get( 'state', $scan_id, $user_id );

		if ( ! is_array( $state ) ) {
			$report = media_insight_cache_get( 'report', $scan_id, $user_id );
			if ( is_array( $report ) ) {
				$status['report'] = media_insight_prepare_report_payload( $report );
				media_insight_clear_scheduled_scan( $scan_id, $user_id );
				return array( 'success' => true, 'status' => $status );
			}

			media_insight_set_scan_status(
				$scan_id,
				$user_id,
				array(
					'status'  => 'failed',
					'message' => __( 'The scan state expired. Please start a new scan.', 'media-insight' ),
				)
			);
			media_insight_delete_scan_result_chunks( $scan_id, $user_id );
			media_insight_clear_scheduled_scan( $scan_id, $user_id );

			return array(
				'success' => false,
				'message' => __( 'The scan state expired. Please start a new scan.', 'media-insight' ),
			);
		}

		$batch_count = 0;
		while ( empty( $state['done'] ) && $batch_count < $max_batches && ( time() - $start_time ) < $time_budget ) {
			$cancelled_response = media_insight_get_cancelled_scan_response( $scan_id, $user_id );
			if ( is_array( $cancelled_response ) ) {
				return $cancelled_response;
			}

			$state = media_insight_process_scan_batch( $state, MEDIA_INSIGHT_REST_BATCH_SIZE );
			$batch_count++;
		}

		$cancelled_response = media_insight_get_cancelled_scan_response( $scan_id, $user_id );
		if ( is_array( $cancelled_response ) ) {
			return $cancelled_response;
		}

		$done = ! empty( $state['done'] );

		if ( $done ) {
			$cancelled_response = media_insight_get_cancelled_scan_response( $scan_id, $user_id );
			if ( is_array( $cancelled_response ) ) {
				return $cancelled_response;
			}

			$results = media_insight_get_all_scan_results( $scan_id, $user_id );
			$report  = media_insight_finalize_scan_state( $state, $results );
			$report['args']['scan_id'] = $scan_id;

			$cancelled_response = media_insight_get_cancelled_scan_response( $scan_id, $user_id );
			if ( is_array( $cancelled_response ) ) {
				return $cancelled_response;
			}

			media_insight_cache_set( 'report', $scan_id, $report, $user_id );
			media_insight_cache_delete( 'state', $scan_id, $user_id );
			media_insight_delete_scan_result_chunks( $scan_id, $user_id );

			media_insight_set_scan_status(
				$scan_id,
				$user_id,
				array(
					'status'    => 'complete',
					'processed' => media_insight_get_state_processed_count( $state ),
					'total'     => absint( $state['total'] ?? 0 ),
					'message'   => __( 'Scan complete.', 'media-insight' ),
				)
			);

			media_insight_clear_scheduled_scan( $scan_id, $user_id );

			$status = media_insight_get_scan_status( $scan_id, $user_id );
			if ( is_array( $status ) && 'cancelled' === ( $status['status'] ?? '' ) ) {
				media_insight_cache_delete( 'report', $scan_id, $user_id );
				return array( 'success' => true, 'status' => $status );
			}

			$status = is_array( $status ) ? $status : array();
			$status['report'] = media_insight_prepare_report_payload( $report );

			return array( 'success' => true, 'status' => $status );
		}

		$cancelled_response = media_insight_get_cancelled_scan_response( $scan_id, $user_id );
		if ( is_array( $cancelled_response ) ) {
			return $cancelled_response;
		}

		media_insight_cache_set( 'state', $scan_id, $state, $user_id );
		media_insight_set_scan_status(
			$scan_id,
			$user_id,
			array(
				'status'    => 'running',
				'processed' => media_insight_get_state_processed_count( $state ),
				'total'     => absint( $state['total'] ?? 0 ),
				'message'   => __( 'Scanning media usage...', 'media-insight' ),
			)
		);

		$cancelled_response = media_insight_get_cancelled_scan_response( $scan_id, $user_id );
		if ( is_array( $cancelled_response ) ) {
			return $cancelled_response;
		}

		media_insight_queue_scan( $scan_id, $user_id );

		return array(
			'success' => true,
			'status'  => media_insight_get_scan_status( $scan_id, $user_id ),
		);
	} finally {
		if ( $lock_acquired ) {
			media_insight_release_scan_lock( $scan_id, $user_id );
		}

		if ( $previous_user_id !== $user_id ) {
			wp_set_current_user( $previous_user_id );
		}
	}
}
