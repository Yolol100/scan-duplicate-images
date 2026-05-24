<?php
/**
 * CSV export handler.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle CSV export.
 */
function dius_handle_csv_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this report.', 'scan-duplicate-images' ) );
	}

	check_admin_referer( 'dius_export_duplicate_images', 'dius_export_nonce' );

	$post_data = dius_get_post_data();
	$scan_id   = isset( $post_data['scan_id'] ) ? sanitize_key( $post_data['scan_id'] ) : '';
	$report    = null;

	if ( '' !== $scan_id ) {
		$stored_report = get_transient( dius_get_scan_transient_key( 'report', $scan_id ) );

		if ( is_array( $stored_report ) ) {
			$report = $stored_report;
		}
	}

	if ( ! is_array( $report ) ) {
		$args   = dius_sanitize_scan_args( $post_data );
		$report = dius_scan_for_duplicate_images( $args );
	}

	$duplicates = isset( $report['duplicates'] ) && is_array( $report['duplicates'] ) ? $report['duplicates'] : array();
	$filename   = 'featured-acf-image-usage-' . gmdate( 'Y-m-d-His' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

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
					dius_escape_csv_cell( $image['key'] ?? '' ),
					dius_escape_csv_cell( $image['attachment_id'] ?? '' ),
					dius_escape_csv_cell( $image['filename'] ?? '' ),
					dius_escape_csv_cell( $image['url'] ?? '' ),
					dius_escape_csv_cell( $image['unique_post_count'] ?? '' ),
					dius_escape_csv_cell( $image['usage_count'] ?? '' ),
					dius_escape_csv_cell( $usage['post_id'] ?? '' ),
					dius_escape_csv_cell( $usage['post_title'] ?? '' ),
					dius_escape_csv_cell( $usage['post_type'] ?? '' ),
					dius_escape_csv_cell( $usage['edit_url'] ?? '' ),
					dius_escape_csv_cell( $usage['source'] ?? '' ),
					dius_escape_csv_cell( $usage['context'] ?? '' ),
				)
			);
		}
	}

	fclose( $output );
	exit;
}

/**
 * Escape CSV cell values to reduce spreadsheet formula-injection risks.
 *
 * @param mixed $value Raw cell value.
 * @return string
 */

/**
 * Escape CSV cell values to reduce spreadsheet formula-injection risks.
 *
 * @param mixed $value Raw cell value.
 * @return string
 */
function dius_escape_csv_cell( $value ) {
	$value = is_scalar( $value ) ? (string) $value : '';
	$value = wp_check_invalid_utf8( $value );

	if ( '' !== $value && in_array( substr( $value, 0, 1 ), array( '=', '+', '-', '@' ), true ) ) {
		$value = "'" . $value;
	}

	return $value;
}

/**
 * Return a report as HTML for AJAX responses.
 *
 * @param array $report Scan report.
 * @return string
 */
