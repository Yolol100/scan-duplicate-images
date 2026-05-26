<?php
/**
 * CSV export handler.
 *
 * @package MediaInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle CSV export from a cached scan report.
 */
function media_insight_handle_csv_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this report.', 'media-insight' ), esc_html__( 'Permission denied', 'media-insight' ), array( 'response' => 403 ) );
	}

	check_admin_referer( 'media_insight_export_duplicate_images', 'media_insight_export_nonce' );

	$raw_scan_id = filter_input( INPUT_GET, 'scan_id', FILTER_UNSAFE_RAW );
	$scan_id     = is_string( $raw_scan_id ) ? sanitize_key( wp_unslash( $raw_scan_id ) ) : '';
	$report      = null;

	if ( '' !== $scan_id ) {
		$status = media_insight_get_scan_status( $scan_id, get_current_user_id() );
		if ( is_array( $status ) && 'complete' === sanitize_key( $status['status'] ?? '' ) ) {
			$stored_report = media_insight_cache_get( 'report', $scan_id, get_current_user_id() );
			if ( is_array( $stored_report ) ) {
				$report = $stored_report;
			}
		}
	}

	if ( ! is_array( $report ) ) {
		wp_die( esc_html__( 'The report was not found or has expired. Please run a new scan.', 'media-insight' ), esc_html__( 'Report not found', 'media-insight' ), array( 'response' => 404 ) );
	}

	$duplicates = isset( $report['duplicates'] ) && is_array( $report['duplicates'] ) ? $report['duplicates'] : array();
	$filename   = sanitize_file_name( 'media-insight-usage-' . gmdate( 'Y-m-d-His' ) . '.csv' );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	$output = fopen( 'php://output', 'w' );
	if ( false === $output ) {
		exit;
	}

	fputcsv(
		$output,
		array(
			'image_key',
			'attachment_id',
			'filename',
			'image_url',
			'unique_item_count',
			'usage_count',
			'post_id',
			'post_title',
			'post_type',
			'edit_url',
			'source',
			'context',
		)
	);

	foreach ( $duplicates as $image ) {
		$usages = isset( $image['usages'] ) && is_array( $image['usages'] ) ? $image['usages'] : array();

		foreach ( $usages as $usage ) {
			fputcsv(
				$output,
				array(
					media_insight_escape_csv_cell( $image['key'] ?? '' ),
					media_insight_escape_csv_cell( $image['attachment_id'] ?? '' ),
					media_insight_escape_csv_cell( $image['filename'] ?? '' ),
					media_insight_escape_csv_cell( $image['url'] ?? '' ),
					media_insight_escape_csv_cell( $image['unique_post_count'] ?? '' ),
					media_insight_escape_csv_cell( $image['usage_count'] ?? '' ),
					media_insight_escape_csv_cell( $usage['post_id'] ?? '' ),
					media_insight_escape_csv_cell( $usage['post_title'] ?? '' ),
					media_insight_escape_csv_cell( $usage['post_type'] ?? '' ),
					media_insight_escape_csv_cell( $usage['edit_url'] ?? '' ),
					media_insight_escape_csv_cell( $usage['source'] ?? '' ),
					media_insight_escape_csv_cell( $usage['context'] ?? '' ),
				)
			);
		}
	}

	fclose( $output );
	exit;
}

/**
 * Escape CSV cell values to reduce spreadsheet formula injection risk.
 *
 * @param mixed $value Raw cell value.
 * @return string
 */
function media_insight_escape_csv_cell( $value ) {
	$value = is_scalar( $value ) ? (string) $value : '';
	$value = wp_check_invalid_utf8( $value );
	$value = wp_strip_all_tags( $value );

	if ( '' !== $value && preg_match( '/^\s*[=+\-@]/', $value ) ) {
		$value = "'" . $value;
	}

	return $value;
}
