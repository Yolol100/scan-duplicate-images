<?php
/**
 * Progressive AJAX scan handlers.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Start a progressive AJAX scan.
 */
function dius_ajax_start_scan() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to run scans.', 'scan-duplicate-images' ) ), 403 );
	}

	check_ajax_referer( 'dius_ajax_scan', 'nonce' );

	$post_data = dius_get_post_data();
	$args      = dius_sanitize_scan_args( $post_data );
	$state     = dius_create_scan_state( $args );
	$scan_id = wp_generate_uuid4();

	if ( empty( $state['done'] ) ) {
		set_transient( dius_get_scan_transient_key( 'state', $scan_id ), $state, DIUS_TRANSIENT_TTL );
	}

	wp_send_json_success(
		array(
			'scan_id' => empty( $state['done'] ) ? $scan_id : '',
			'total'   => absint( $state['total'] ?? 0 ),
		)
	);
}

/**
 * Process one progressive AJAX scan batch.
 */

/**
 * Process one progressive AJAX scan batch.
 */
function dius_ajax_process_scan_batch() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to run scans.', 'scan-duplicate-images' ) ), 403 );
	}

	check_ajax_referer( 'dius_ajax_scan', 'nonce' );

	$post_data = dius_get_post_data();
	$scan_id   = isset( $post_data['scan_id'] ) ? sanitize_key( $post_data['scan_id'] ) : '';

	if ( '' === $scan_id ) {
		wp_send_json_error( array( 'message' => __( 'Missing scan ID.', 'scan-duplicate-images' ) ), 400 );
	}

	$state = get_transient( dius_get_scan_transient_key( 'state', $scan_id ) );

	if ( ! is_array( $state ) ) {
		wp_send_json_error( array( 'message' => __( 'The scan state expired. Please start a new scan.', 'scan-duplicate-images' ) ), 410 );
	}

	$state = dius_process_scan_batch( $state, DIUS_AJAX_BATCH_SIZE );
	$done  = ! empty( $state['done'] );
	$html  = '';

	if ( $done ) {
		$report = dius_finalize_scan_state( $state );
		$report['args']['scan_id'] = $scan_id;
		set_transient( dius_get_scan_transient_key( 'report', $scan_id ), $report, DIUS_TRANSIENT_TTL );
		delete_transient( dius_get_scan_transient_key( 'state', $scan_id ) );
		$html = dius_get_report_html( $report );
	} else {
		set_transient( dius_get_scan_transient_key( 'state', $scan_id ), $state, DIUS_TRANSIENT_TTL );
	}

	wp_send_json_success(
		array(
			'done'      => $done,
			'processed' => absint( $state['offset'] ?? 0 ),
			'total'     => absint( $state['total'] ?? 0 ),
			'html'      => $html,
		)
	);
}
