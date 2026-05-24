<?php
/**
 * Small admin rendering components.
 *
 * @package FeaturedAcfImageUsageScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a stat card.
 *
 * @param string $label Label.
 * @param int    $value Value.
 * @param string $hint  Hint.
 */
function dius_render_stat_card( $label, $value, $hint = '' ) {
	?>
	<div class="dius-stat-card components-card">
		<span class="dius-stat-value"><?php echo esc_html( number_format_i18n( absint( $value ) ) ); ?></span>
		<span class="dius-stat-label"><?php echo esc_html( $label ); ?></span>
		<?php if ( '' !== $hint ) : ?>
			<span class="dius-stat-hint"><?php echo esc_html( $hint ); ?></span>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render CSV export form.
 *
 * @param array $args Scan arguments.
 */

/**
 * Render CSV export form.
 *
 * @param array $args Scan arguments.
 */

/**
 * Render preview image.
 *
 * @param array $image Image result.
 */
function dius_render_image_preview( $image ) {
	if ( ! empty( $image['attachment_id'] ) ) {
		$thumb = wp_get_attachment_image(
			absint( $image['attachment_id'] ),
			'thumbnail',
			false,
			array(
				'class'   => 'dius-thumb',
				'loading' => 'lazy',
			)
		);

		if ( $thumb ) {
			echo wp_kses_post( $thumb );
			return;
		}
	}

	echo '<span class="dius-thumb dius-thumb-empty" aria-hidden="true"></span>';
}

/**
 * Render usage list.
 *
 * @param array $usages Usage rows.
 */

/**
 * Render usage list.
 *
 * @param array $usages Usage rows.
 */

/**
 * Render usage list.
 *
 * @param array $usages Usage rows.
 */
function dius_render_usage_list( $usages ) {
	if ( empty( $usages ) || ! is_array( $usages ) ) {
		return;
	}

	?>
	<ul class="dius-usage-list">
		<?php foreach ( $usages as $usage ) : ?>
			<li>
				<?php if ( ! empty( $usage['edit_url'] ) ) : ?>
					<a href="<?php echo esc_url( $usage['edit_url'] ); ?>"><?php echo esc_html( $usage['post_title'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $usage['post_title'] ); ?>
				<?php endif; ?>
				<span class="dius-pill"><?php echo esc_html( 'page' === ( $usage['post_type'] ?? '' ) ? __( 'page', 'scan-duplicate-images' ) : __( 'post', 'scan-duplicate-images' ) ); ?></span>
				<span class="dius-pill"><?php echo esc_html( 'acf_page_image' === ( $usage['source'] ?? '' ) ? __( 'ACF', 'scan-duplicate-images' ) : __( 'Featured', 'scan-duplicate-images' ) ); ?></span>
				<?php if ( ! empty( $usage['context'] ) && 'Featured image' !== $usage['context'] ) : ?>
					<span class="dius-muted"><?php echo esc_html( $usage['context'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Handle CSV export.
 */

/**
 * Return a report as HTML for AJAX responses.
 *
 * @param array $report Scan report.
 * @return string
 */
