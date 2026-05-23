<?php
/**
 * Uninstall cleanup for Duplicate Image Usage Scanner.
 *
 * The plugin does not store permanent content data. This removes only temporary
 * scan/report transients created by the admin scan flow.
 *
 * @package DuplicateImageUsageScanner
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_dius_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_dius_' ) . '%',
		$wpdb->esc_like( '_site_transient_dius_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_dius_' ) . '%'
	)
);
