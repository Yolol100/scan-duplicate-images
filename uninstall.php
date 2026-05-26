<?php
/**
 * Uninstall cleanup for Media Insight.
 *
 * @package MediaInsight
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$registry = get_option( 'media_insight_runtime_registry', array() );

if ( is_array( $registry ) ) {
	$transients = isset( $registry['transient'] ) && is_array( $registry['transient'] ) ? array_keys( $registry['transient'] ) : array();
	$options    = isset( $registry['option'] ) && is_array( $registry['option'] ) ? array_keys( $registry['option'] ) : array();

	foreach ( $transients as $transient_key ) {
		$transient_key = sanitize_key( $transient_key );
		if ( 0 === strpos( $transient_key, 'media_insight_' ) ) {
			delete_transient( $transient_key );
		}
	}

	foreach ( $options as $option_key ) {
		$option_key = sanitize_key( $option_key );
		if ( 0 === strpos( $option_key, 'media_insight_lock_' ) ) {
			delete_option( $option_key );
		}
	}
}

$scheduled_scans = get_option( 'media_insight_scheduled_scans', array() );

if ( is_array( $scheduled_scans ) ) {
	foreach ( $scheduled_scans as $event ) {
		$scan_id = isset( $event['scan_id'] ) ? sanitize_key( $event['scan_id'] ) : '';
		$user_id = isset( $event['user_id'] ) ? absint( $event['user_id'] ) : 0;

		if ( $scan_id && $user_id ) {
			wp_clear_scheduled_hook( 'media_insight_process_scan_event', array( $scan_id, $user_id ) );
		}
	}
}

delete_option( 'media_insight_runtime_registry' );
delete_option( 'media_insight_scheduled_scans' );
