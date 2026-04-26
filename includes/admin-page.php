<?php
/**
 * Admin page rendering for Duplicate Image Usage Scanner.
 *
 * @package DuplicateImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the admin menu item.
 */
function dius_add_admin_page() {
	add_menu_page(
		esc_html__( 'Image Usage Scanner', 'scan-duplicate-images' ),
		esc_html__( 'Image Usage Scanner', 'scan-duplicate-images' ),
		'manage_options',
		DIUS_MENU_SLUG,
		'dius_admin_page_content',
		'dashicons-search',
		20
	);
}

/**
 * Display the admin page content.
 */
function dius_admin_page_content() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'scan-duplicate-images' ) );
	}

	$scan_args   = dius_get_default_scan_args();
	$scan_report = null;
	$should_scan = false;

	if ( filter_input( INPUT_POST, 'dius_scan', FILTER_VALIDATE_BOOLEAN ) ) {
		check_admin_referer( 'dius_scan_duplicate_images', 'dius_scan_nonce' );
		$scan_args   = dius_sanitize_scan_args( $_POST );
		$scan_report = dius_scan_for_duplicate_images( $scan_args );
		$should_scan = true;
	}
	?>
	<div class="wrap dius-admin-page">
		<h1><?php esc_html_e( 'Image Usage Scanner', 'scan-duplicate-images' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Find images that are used across multiple posts, pages, custom post types, featured images, Gutenberg block attributes, srcset/background references, and ACF fields.', 'scan-duplicate-images' ); ?>
		</p>

		<?php dius_render_scan_form( $scan_args ); ?>

		<div data-dius-results>
			<?php if ( $should_scan && is_array( $scan_report ) ) : ?>
				<?php dius_display_scan_report( $scan_report ); ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Render the scan form.
 *
 * @param array $scan_args Current scan arguments.
 */
function dius_render_scan_form( $scan_args ) {
	$post_types = dius_get_scannable_post_types();
	$selected   = isset( $scan_args['post_types'] ) && is_array( $scan_args['post_types'] ) ? $scan_args['post_types'] : array();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . DIUS_MENU_SLUG ) ); ?>" class="dius-form dius-card" data-dius-scan-form>
		<?php wp_nonce_field( 'dius_scan_duplicate_images', 'dius_scan_nonce' ); ?>
		<input type="hidden" name="dius_scan" value="1" />

		<h2><?php esc_html_e( 'Scan settings', 'scan-duplicate-images' ); ?></h2>

		<div class="dius-field-group">
			<h3><?php esc_html_e( 'Post types', 'scan-duplicate-images' ); ?></h3>
			<div class="dius-checkbox-grid">
				<?php foreach ( $post_types as $post_type => $label ) : ?>
					<label>
						<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( in_array( $post_type, $selected, true ) ); ?> />
						<?php echo esc_html( $label ); ?>
						<code><?php echo esc_html( $post_type ); ?></code>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="dius-field-group dius-inline-options">
			<label>
				<input type="checkbox" name="include_blocks" value="1" <?php checked( ! empty( $scan_args['include_blocks'] ) ); ?> />
				<?php esc_html_e( 'Scan Gutenberg block attributes', 'scan-duplicate-images' ); ?>
			</label>
			<label>
				<input type="checkbox" name="include_acf" value="1" <?php checked( ! empty( $scan_args['include_acf'] ) ); ?> <?php disabled( ! function_exists( 'get_fields' ) ); ?> />
				<?php esc_html_e( 'Scan ACF fields', 'scan-duplicate-images' ); ?>
				<?php if ( ! function_exists( 'get_fields' ) ) : ?>
					<span class="description"><?php esc_html_e( '(ACF not active)', 'scan-duplicate-images' ); ?></span>
				<?php endif; ?>
			</label>
		</div>

		<div class="dius-field-group">
			<label for="dius-limit">
				<?php esc_html_e( 'Optional scan limit', 'scan-duplicate-images' ); ?>
			</label>
			<input id="dius-limit" type="number" min="0" step="1" name="limit" value="<?php echo esc_attr( isset( $scan_args['limit'] ) ? absint( $scan_args['limit'] ) : 0 ); ?>" />
			<p class="description"><?php esc_html_e( 'Use 0 to scan all matching items. A limit is useful for testing on very large sites.', 'scan-duplicate-images' ); ?></p>
		</div>

		<div class="dius-actions">
			<button type="button" class="button button-primary" data-dius-ajax-start hidden><?php esc_html_e( 'Start batch scan', 'scan-duplicate-images' ); ?></button>
			<?php submit_button( esc_html__( 'Fallback: scan in one request', 'scan-duplicate-images' ), 'secondary', 'submit', false ); ?>
		</div>

		<div class="dius-progress" data-dius-progress hidden>
			<div class="dius-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
				<span class="dius-progress-bar" data-dius-progress-bar></span>
			</div>
			<p class="description" data-dius-progress-text><?php esc_html_e( 'Ready to scan.', 'scan-duplicate-images' ); ?></p>
		</div>
	</form>
	<?php
}

/**
 * Display full scan report.
 *
 * @param array $report Scan report.
 */
function dius_display_scan_report( $report ) {
	$stats      = isset( $report['stats'] ) && is_array( $report['stats'] ) ? $report['stats'] : array();
	$duplicates = isset( $report['duplicates'] ) && is_array( $report['duplicates'] ) ? $report['duplicates'] : array();
	$args       = isset( $report['args'] ) && is_array( $report['args'] ) ? $report['args'] : array();
	?>
	<div class="dius-report">
		<h2><?php esc_html_e( 'Scan results', 'scan-duplicate-images' ); ?></h2>

		<div class="dius-summary-grid">
			<?php dius_render_stat_card( __( 'Scanned items', 'scan-duplicate-images' ), $stats['scanned_posts'] ?? 0 ); ?>
			<?php dius_render_stat_card( __( 'Image usages', 'scan-duplicate-images' ), $stats['total_usages'] ?? 0 ); ?>
			<?php dius_render_stat_card( __( 'Unique images', 'scan-duplicate-images' ), $stats['unique_images'] ?? 0 ); ?>
			<?php dius_render_stat_card( __( 'Repeated images', 'scan-duplicate-images' ), $stats['duplicate_images'] ?? 0 ); ?>
		</div>

		<p class="description">
			<?php esc_html_e( 'A repeated image means the same attachment or normalized image URL appears on more than one scanned item. This can be intentional for logos, icons, badges, banners, and shared design assets.', 'scan-duplicate-images' ); ?>
		</p>

		<?php if ( empty( $duplicates ) ) : ?>
			<p class="dius-notice"><?php esc_html_e( 'No images were found on more than one scanned item.', 'scan-duplicate-images' ); ?></p>
			<?php return; ?>
		<?php endif; ?>

		<?php dius_render_export_form( $args ); ?>
		<?php dius_render_results_table( $duplicates ); ?>
	</div>
	<?php
}

/**
 * Render a stat card.
 *
 * @param string $label Label.
 * @param int    $value Value.
 */
function dius_render_stat_card( $label, $value ) {
	?>
	<div class="dius-stat-card">
		<span class="dius-stat-value"><?php echo esc_html( number_format_i18n( absint( $value ) ) ); ?></span>
		<span class="dius-stat-label"><?php echo esc_html( $label ); ?></span>
	</div>
	<?php
}

/**
 * Render CSV export form.
 *
 * @param array $args Scan arguments.
 */
function dius_render_export_form( $args ) {
	$post_types = isset( $args['post_types'] ) && is_array( $args['post_types'] ) ? $args['post_types'] : array();
	$scan_id    = isset( $args['scan_id'] ) ? sanitize_key( $args['scan_id'] ) : '';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dius-export-form">
		<?php wp_nonce_field( 'dius_export_duplicate_images', 'dius_export_nonce' ); ?>
		<input type="hidden" name="action" value="dius_export_csv" />
		<?php if ( '' !== $scan_id ) : ?>
			<input type="hidden" name="scan_id" value="<?php echo esc_attr( $scan_id ); ?>" />
		<?php endif; ?>
		<?php foreach ( $post_types as $post_type ) : ?>
			<input type="hidden" name="post_types[]" value="<?php echo esc_attr( $post_type ); ?>" />
		<?php endforeach; ?>
		<input type="hidden" name="include_blocks" value="<?php echo esc_attr( ! empty( $args['include_blocks'] ) ? '1' : '0' ); ?>" />
		<input type="hidden" name="include_acf" value="<?php echo esc_attr( ! empty( $args['include_acf'] ) ? '1' : '0' ); ?>" />
		<input type="hidden" name="limit" value="<?php echo esc_attr( isset( $args['limit'] ) ? absint( $args['limit'] ) : 0 ); ?>" />
		<?php submit_button( esc_html__( 'Export CSV', 'scan-duplicate-images' ), 'secondary', 'submit', false ); ?>
	</form>
	<?php
}

/**
 * Render results table.
 *
 * @param array $duplicates Duplicate results.
 */
function dius_render_results_table( $duplicates ) {
	?>
	<table class="widefat striped dius-results-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Image', 'scan-duplicate-images' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Used on', 'scan-duplicate-images' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Sources', 'scan-duplicate-images' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $duplicates as $image ) : ?>
				<tr>
					<td class="dius-image-cell">
						<?php if ( ! empty( $image['url'] ) ) : ?>
							<a href="<?php echo esc_url( $image['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $image['filename'] ); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $image['filename'] ); ?>
						<?php endif; ?>

						<div class="dius-image-meta">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: unique post count, 2: total usage count. */
									__( '%1$s items, %2$s total usages', 'scan-duplicate-images' ),
									number_format_i18n( absint( $image['unique_post_count'] ?? 0 ) ),
									number_format_i18n( absint( $image['usage_count'] ?? 0 ) )
								)
							);
							?>
						</div>

						<?php if ( ! empty( $image['attachment_id'] ) ) : ?>
							<div class="dius-image-meta">
								<a href="<?php echo esc_url( get_edit_post_link( absint( $image['attachment_id'] ) ) ); ?>">
									<?php esc_html_e( 'Edit attachment', 'scan-duplicate-images' ); ?> #<?php echo esc_html( absint( $image['attachment_id'] ) ); ?>
								</a>
							</div>
						<?php endif; ?>
					</td>
					<td>
						<?php dius_render_usage_list( $image['usages'] ?? array(), 'items' ); ?>
					</td>
					<td>
						<?php dius_render_usage_list( $image['usages'] ?? array(), 'sources' ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Render usage list.
 *
 * @param array  $usages Usage rows.
 * @param string $mode   Rendering mode.
 */
function dius_render_usage_list( $usages, $mode ) {
	if ( empty( $usages ) || ! is_array( $usages ) ) {
		return;
	}

	$seen_items = array();
	?>
	<ul class="dius-usage-list">
		<?php foreach ( $usages as $usage ) : ?>
			<?php
			if ( 'items' === $mode ) {
				$item_key = isset( $usage['post_id'] ) ? absint( $usage['post_id'] ) : 0;

				if ( isset( $seen_items[ $item_key ] ) ) {
					continue;
				}

				$seen_items[ $item_key ] = true;
			}
			?>
			<li>
				<?php if ( 'items' === $mode ) : ?>
					<?php if ( ! empty( $usage['edit_url'] ) ) : ?>
						<a href="<?php echo esc_url( $usage['edit_url'] ); ?>">
							<?php echo esc_html( $usage['post_title'] ); ?>
						</a>
					<?php else : ?>
						<?php echo esc_html( $usage['post_title'] ); ?>
					<?php endif; ?>
					<span class="dius-pill"><?php echo esc_html( $usage['post_type'] ); ?></span>
				<?php else : ?>
					<span class="dius-pill"><?php echo esc_html( $usage['source'] ); ?></span>
					<?php echo esc_html( $usage['context'] ); ?>
					<span class="dius-muted">&mdash; <?php echo esc_html( $usage['post_title'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Handle CSV export.
 */
function dius_handle_csv_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this report.', 'scan-duplicate-images' ) );
	}

	check_admin_referer( 'dius_export_duplicate_images', 'dius_export_nonce' );

	$scan_id = isset( $_POST['scan_id'] ) ? sanitize_key( wp_unslash( $_POST['scan_id'] ) ) : '';
	$report  = null;

	if ( '' !== $scan_id ) {
		$stored_report = get_transient( dius_get_scan_transient_key( 'report', $scan_id ) );

		if ( is_array( $stored_report ) ) {
			$report = $stored_report;
		}
	}

	if ( ! is_array( $report ) ) {
		$args   = dius_sanitize_scan_args( $_POST );
		$report = dius_scan_for_duplicate_images( $args );
	}

	$duplicates = isset( $report['duplicates'] ) && is_array( $report['duplicates'] ) ? $report['duplicates'] : array();
	$filename   = 'image-usage-report-' . gmdate( 'Y-m-d-His' ) . '.csv';

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
			'unique_post_count',
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
		foreach ( $image['usages'] as $usage ) {
			fputcsv(
				$output,
				array(
					$image['key'] ?? '',
					$image['attachment_id'] ?? '',
					$image['filename'] ?? '',
					$image['url'] ?? '',
					$image['unique_post_count'] ?? '',
					$image['usage_count'] ?? '',
					$usage['post_id'] ?? '',
					$usage['post_title'] ?? '',
					$usage['post_type'] ?? '',
					$usage['edit_url'] ?? '',
					$usage['source'] ?? '',
					$usage['context'] ?? '',
				)
			);
		}
	}

	fclose( $output );
	exit;
}

/**
 * Return a report as HTML for AJAX responses.
 *
 * @param array $report Scan report.
 * @return string
 */
function dius_get_report_html( $report ) {
	ob_start();
	dius_display_scan_report( $report );

	return (string) ob_get_clean();
}

/**
 * Start a progressive AJAX scan.
 */
function dius_ajax_start_scan() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to run scans.', 'scan-duplicate-images' ) ), 403 );
	}

	check_ajax_referer( 'dius_ajax_scan', 'nonce' );

	$args    = dius_sanitize_scan_args( $_POST );
	$state   = dius_create_scan_state( $args );
	$scan_id = wp_generate_uuid4();

	set_transient( dius_get_scan_transient_key( 'state', $scan_id ), $state, DIUS_TRANSIENT_TTL );

	wp_send_json_success(
		array(
			'scan_id' => $scan_id,
			'total'   => isset( $state['total'] ) ? absint( $state['total'] ) : 0,
		)
	);
}

/**
 * Process one progressive AJAX scan batch.
 */
function dius_ajax_process_scan_batch() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to run scans.', 'scan-duplicate-images' ) ), 403 );
	}

	check_ajax_referer( 'dius_ajax_scan', 'nonce' );

	$scan_id = isset( $_POST['scan_id'] ) ? sanitize_key( wp_unslash( $_POST['scan_id'] ) ) : '';

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
			'processed' => isset( $state['offset'] ) ? absint( $state['offset'] ) : 0,
			'total'     => isset( $state['total'] ) ? absint( $state['total'] ) : 0,
			'html'      => $html,
		)
	);
}

/**
 * Backward-compatible result display wrapper.
 *
 * @param array $results Duplicate results.
 */
function dius_display_results( $results ) {
	dius_display_scan_report(
		array(
			'args'       => dius_get_default_scan_args(),
			'stats'      => array(
				'duplicate_images' => is_array( $results ) ? count( $results ) : 0,
			),
			'duplicates' => is_array( $results ) ? $results : array(),
		)
	);
}
