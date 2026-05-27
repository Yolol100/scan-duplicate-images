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
 * Handle CSV export from the plugin admin page using a GET request.
 */
function media_insight_maybe_handle_csv_export() {
	$raw_page   = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
	$raw_export = filter_input( INPUT_GET, 'media_insight_export_csv', FILTER_UNSAFE_RAW );

	$page      = is_string( $raw_page ) ? sanitize_key( wp_unslash( $raw_page ) ) : '';
	$is_export = is_string( $raw_export ) && '1' === sanitize_text_field( wp_unslash( $raw_export ) );

	if ( MEDIA_INSIGHT_MENU_SLUG !== $page || ! $is_export ) {
		return;
	}

	media_insight_handle_csv_export();
}

/**
 * Handle CSV export from a cached scan report.
 */
function media_insight_handle_csv_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this report.', 'media-insight' ), esc_html__( 'Permission denied', 'media-insight' ), array( 'response' => 403 ) );
	}

	$nonce = filter_input( INPUT_POST, 'media_insight_export_nonce', FILTER_UNSAFE_RAW );

	if ( null === $nonce ) {
		$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW );
	}

	$nonce = is_string( $nonce ) ? sanitize_text_field( wp_unslash( $nonce ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'media_insight_export_duplicate_images' ) ) {
		wp_die( esc_html__( 'The export link has expired. Please reload the Media Insight page and try again.', 'media-insight' ), esc_html__( 'Invalid export link', 'media-insight' ), array( 'response' => 403 ) );
	}

	$raw_scan_id = filter_input( INPUT_GET, 'scan_id', FILTER_UNSAFE_RAW );

	if ( null === $raw_scan_id ) {
		$raw_scan_id = filter_input( INPUT_POST, 'scan_id', FILTER_UNSAFE_RAW );
	}

	$scan_id = is_string( $raw_scan_id ) ? sanitize_key( wp_unslash( $raw_scan_id ) ) : '';
	$report  = null;

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
			'thumbnail_url',
			'media_edit_url',
			'alt_text',
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
		if ( ! is_array( $image ) ) {
			continue;
		}

		$usages = isset( $image['usages'] ) && is_array( $image['usages'] ) ? $image['usages'] : array();

		foreach ( $usages as $usage ) {
			if ( ! is_array( $usage ) ) {
				continue;
			}
			fputcsv(
				$output,
				array(
					media_insight_escape_csv_cell( $image['key'] ?? '' ),
					media_insight_escape_csv_cell( $image['attachment_id'] ?? '' ),
					media_insight_escape_csv_cell( $image['filename'] ?? '' ),
					media_insight_escape_csv_cell( $image['url'] ?? '' ),
					media_insight_escape_csv_cell( $image['thumbnail_url'] ?? '' ),
					media_insight_escape_csv_cell( $image['media_edit_url'] ?? '' ),
					media_insight_escape_csv_cell( $image['alt_text'] ?? '' ),
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
