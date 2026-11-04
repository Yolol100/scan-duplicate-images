<?php
/**
 * WordPress integration smoke for Media Insight.
 *
 * Runs only in a disposable WordPress/MySQL CI installation.
 */

$routes = rest_get_server()->get_routes();
foreach ( array( '/media-insight/v2/scans', '/media-insight/v2/scans/(?P<scan_id>[a-zA-Z0-9_-]+)' ) as $route ) {
    if ( ! isset( $routes[ $route ] ) ) {
        throw new RuntimeException( 'Missing Media Insight REST route: ' . $route );
    }
}

wp_set_current_user( 0 );
$anonymous = rest_do_request( new WP_REST_Request( 'POST', '/media-insight/v2/scans' ) );
if ( $anonymous->get_status() < 400 ) {
    throw new RuntimeException( 'Anonymous user may start a scan.' );
}

$editor_id = wp_create_user( 'media-insight-ci-editor', 'isolated-ci-password', 'editor@example.invalid' );
if ( is_wp_error( $editor_id ) ) {
    throw new RuntimeException( 'Failed to create test editor.' );
}
( new WP_User( $editor_id ) )->set_role( 'editor' );
wp_set_current_user( $editor_id );
$editor = rest_do_request( new WP_REST_Request( 'POST', '/media-insight/v2/scans' ) );
if ( $editor->get_status() < 400 ) {
    throw new RuntimeException( 'Editor may start an administrator-only scan.' );
}

wp_set_current_user( 1 );
if ( ! current_user_can( 'manage_options' ) ) {
    throw new RuntimeException( 'Isolated site admin cannot manage scans.' );
}

$uploads = wp_upload_dir();
$file = trailingslashit( $uploads['path'] ) . 'media-insight-ci.png';
$data = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true );
if ( false === $data || file_put_contents( $file, $data ) !== strlen( $data ) ) {
    throw new RuntimeException( 'Unable to write isolated image fixture.' );
}

$attachment_id = wp_insert_attachment(
    array(
        'post_mime_type' => 'image/png',
        'post_status' => 'inherit',
        'post_title' => 'Media Insight CI image',
    ),
    $file,
    0,
    true
);
if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
    throw new RuntimeException( 'Failed to create isolated attachment.' );
}

$post_ids = array();
try {
    for ( $i = 0; $i < 2; $i++ ) {
        $post_id = wp_insert_post(
            array(
                'post_title' => 'Media Insight CI usage ' . $i,
                'post_status' => 'publish',
                'post_type' => 'post',
            ),
            true
        );
        if ( is_wp_error( $post_id ) || ! $post_id ) {
            throw new RuntimeException( 'Failed to create isolated post.' );
        }
        $post_ids[] = $post_id;
        update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
    }

    $start = rest_do_request( new WP_REST_Request( 'POST', '/media-insight/v2/scans' ) );
    if ( $start->get_status() !== 200 ) {
        throw new RuntimeException( 'Admin start scan failed with ' . $start->get_status() );
    }
    $status = $start->get_data();
    $scan_id = is_array( $status ) ? (string) ( $status['scan_id'] ?? '' ) : '';
    if ( ! media_insight_validate_scan_id( $scan_id ) ) {
        throw new RuntimeException( 'Start scan returned invalid ID.' );
    }

    for ( $cycle = 0; $cycle < 8 && 'complete' !== ( $status['status'] ?? '' ); $cycle++ ) {
        $process = rest_do_request( new WP_REST_Request( 'POST', '/media-insight/v2/scans/' . $scan_id . '/process' ) );
        if ( $process->get_status() !== 200 ) {
            throw new RuntimeException( 'Process scan failed with ' . $process->get_status() );
        }
        $status = $process->get_data();
    }
    if ( 'complete' !== ( $status['status'] ?? '' ) ) {
        throw new RuntimeException( 'Scan did not complete after 8 isolated cycles.' );
    }

    $read = rest_do_request( new WP_REST_Request( 'GET', '/media-insight/v2/scans/' . $scan_id ) );
    if ( $read->get_status() !== 200 ) {
        throw new RuntimeException( 'Failed to read completed scan.' );
    }
    $report = $read->get_data()['report'] ?? null;
    if ( ! is_array( $report ) ) {
        throw new RuntimeException( 'Completed scan report is missing.' );
    }
    $found = false;
    foreach ( (array) ( $report['duplicates'] ?? array() ) as $image ) {
        if ( (int) ( $image['attachment_id'] ?? 0 ) === (int) $attachment_id
            && (int) ( $image['unique_post_count'] ?? 0 ) === 2 ) {
            $found = true;
        }
    }
    if ( ! $found ) {
        throw new RuntimeException( 'Repeated featured image missing from scan results.' );
    }
    wp_set_current_user( $editor_id );
    $low_priv_read = rest_do_request( new WP_REST_Request( 'GET', '/media-insight/v2/scans/' . $scan_id ) );
    if ( $low_priv_read->get_status() < 400 ) {
        throw new RuntimeException( 'Editor can read admin scan results.' );
    }
    echo "Media Insight WordPress REST and image usage smoke: OK\n";
} finally {
    wp_set_current_user( 1 );
    foreach ( $post_ids as $id ) {
        wp_delete_post( $id, true );
    }
    wp_delete_attachment( (int) $attachment_id, true );
    if ( is_file( $file ) ) {
        unlink( $file );
    }
    wp_delete_user( $editor_id );
}
