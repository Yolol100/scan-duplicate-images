<?php
/**
 * REST API scan engine.
 *
 * @package MediaInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validate a scan ID from a REST route.
 *
 * @param mixed $value Raw scan ID.
 * @return bool
 */
function media_insight_validate_scan_id( $value ) {
	return is_string( $value ) && 1 === preg_match( '/^[a-z0-9_-]{1,64}$/i', $value );
}

/**
 * Register REST routes.
 */
function media_insight_register_rest_routes() {
	register_rest_route(
		MEDIA_INSIGHT_REST_NAMESPACE,
		'/scans',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'media_insight_rest_start_scan',
			'permission_callback' => 'media_insight_rest_can_manage',
			'args'                => array(
				'limit' => array(
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => static function ( $value ) {
						return is_numeric( $value ) ? (int) $value : 0;
					},
					'validate_callback' => static function ( $value ) {
						$value = is_numeric( $value ) ? (int) $value : null;
						return null !== $value && $value >= 0;
					},
				),
			),
		)
	);

	register_rest_route(
		MEDIA_INSIGHT_REST_NAMESPACE,
		'/scans/(?P<scan_id>[a-zA-Z0-9_-]+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'media_insight_rest_get_scan',
			'permission_callback' => 'media_insight_rest_can_manage',
			'args'                => array(
				'scan_id' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => 'media_insight_validate_scan_id',
				),
			),
		)
	);

	register_rest_route(
		MEDIA_INSIGHT_REST_NAMESPACE,
		'/scans/(?P<scan_id>[a-zA-Z0-9_-]+)/process',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'media_insight_rest_process_scan',
			'permission_callback' => 'media_insight_rest_can_manage',
			'args'                => array(
				'scan_id' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => 'media_insight_validate_scan_id',
				),
			),
		)
	);

	register_rest_route(
		MEDIA_INSIGHT_REST_NAMESPACE,
		'/scans/(?P<scan_id>[a-zA-Z0-9_-]+)/cancel',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'media_insight_rest_cancel_scan',
			'permission_callback' => 'media_insight_rest_can_manage',
			'args'                => array(
				'scan_id' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => 'media_insight_validate_scan_id',
				),
			),
		)
	);
}

/**
 * Capability check for all privileged Media Insight REST routes.
 *
 * @return bool
 */
function media_insight_rest_can_manage() {
	return current_user_can( 'manage_options' );
}

/**
 * Start a scan and queue background processing.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function media_insight_rest_start_scan( WP_REST_Request $request ) {
	$user_id = get_current_user_id();
	$args    = media_insight_sanitize_scan_args(
		array(
			'scan_pages' => 1,
			'scan_posts' => 1,
			'limit'      => $request->get_param( 'limit' ),
		)
	);
	$state   = media_insight_create_scan_state( $args );
	$scan_id = wp_generate_uuid4();

	$state['scan_id']    = $scan_id;
	$state['user_id']    = $user_id;
	$state['created_at'] = time();

	media_insight_cache_set( 'state', $scan_id, $state, $user_id );
	media_insight_set_scan_status(
		$scan_id,
		$user_id,
		array(
			'status'    => 'queued',
			'processed' => absint( $state['processed'] ?? 0 ),
			'total'     => absint( $state['total'] ?? 0 ),
			'message'   => __( 'Scan queued.', 'media-insight' ),
		)
	);

	if ( ! empty( $state['done'] ) ) {
		return rest_ensure_response( media_insight_run_scan_batches( $scan_id, $user_id, 1, 1 )['status'] );
	}

	media_insight_queue_scan( $scan_id, $user_id );

	return rest_ensure_response( media_insight_get_scan_status( $scan_id, $user_id ) );
}

/**
 * Get scan status and report if complete.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function media_insight_rest_get_scan( WP_REST_Request $request ) {
	$scan_id = sanitize_key( $request['scan_id'] );
	$user_id = get_current_user_id();
	$status  = media_insight_get_scan_status( $scan_id, $user_id );

	if ( ! is_array( $status ) ) {
		return new WP_Error( 'media_insight_scan_not_found', __( 'The scan was not found or has expired.', 'media-insight' ), array( 'status' => 404 ) );
	}

	if ( 'complete' === ( $status['status'] ?? '' ) ) {
		$report = media_insight_cache_get( 'report', $scan_id, $user_id );
		if ( is_array( $report ) ) {
			$status['report'] = media_insight_prepare_report_payload( $report );
		}
	}

	return rest_ensure_response( $status );
}

/**
 * Process one browser-driven batch cycle.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function media_insight_rest_process_scan( WP_REST_Request $request ) {
	$scan_id = sanitize_key( $request['scan_id'] );
	$user_id = get_current_user_id();
	$result  = media_insight_run_scan_batches( $scan_id, $user_id, 1, 3 );

	if ( empty( $result['success'] ) ) {
		$status = media_insight_get_scan_status( $scan_id, $user_id );

		if ( is_array( $status ) && in_array( sanitize_key( $status['status'] ?? '' ), array( 'failed', 'cancelled' ), true ) ) {
			return rest_ensure_response( $status );
		}

		$message = isset( $result['message'] ) ? sanitize_text_field( $result['message'] ) : __( 'The scan could not be processed. Please try again.', 'media-insight' );
		return new WP_Error( 'media_insight_scan_process_failed', $message, array( 'status' => 410 ) );
	}

	return rest_ensure_response( $result['status'] );
}

/**
 * Cancel a running scan.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function media_insight_rest_cancel_scan( WP_REST_Request $request ) {
	$scan_id = sanitize_key( $request['scan_id'] );
	$user_id = get_current_user_id();
	$status  = media_insight_get_scan_status( $scan_id, $user_id );

	if ( ! is_array( $status ) ) {
		return new WP_Error( 'media_insight_scan_not_found', __( 'The scan was not found or has expired.', 'media-insight' ), array( 'status' => 404 ) );
	}

	$current_status = sanitize_key( $status['status'] ?? '' );
	if ( 'cancelled' === $current_status ) {
		media_insight_clear_scheduled_scan( $scan_id, $user_id );
		media_insight_delete_scan_runtime_data( $scan_id, $user_id, false );
		return rest_ensure_response( $status );
	}

	if ( in_array( $current_status, array( 'complete', 'failed' ), true ) ) {
		return rest_ensure_response( $status );
	}

	media_insight_set_scan_status(
		$scan_id,
		$user_id,
		array(
			'status'  => 'cancelled',
			'message' => __( 'Scan cancelled.', 'media-insight' ),
		)
	);
	media_insight_clear_scheduled_scan( $scan_id, $user_id );

	if ( media_insight_acquire_scan_lock( $scan_id, $user_id, 30 ) ) {
		media_insight_delete_scan_runtime_data( $scan_id, $user_id, false );
		media_insight_release_scan_lock( $scan_id, $user_id );
	} else {
		media_insight_delete_scan_runtime_data( $scan_id, $user_id, false );
	}

	return rest_ensure_response( media_insight_get_scan_status( $scan_id, $user_id ) );
}
