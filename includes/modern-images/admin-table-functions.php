<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

/**
 * Haalt de titel op van de post waaraan de bijlage is gekoppeld via:
 * 1. WordPress: Als de afbeelding als uitgelichte afbeelding is ingesteld (post_parent > 0).
 * 2. ACF: Als er een koppeling via ACF-meta-velden is gevonden (meta_key beginnend met "field_").
 * 3. Anders: 'Niet gekoppeld'.
 *
 * @param int $attachment_id De ID van de bijlage.
 * @return string De titel van de gekoppelde post, of 'Niet gekoppeld' als er geen koppeling is.
 */
function modern_image_manager_get_attached_post_title(int $attachment_id): string {
    global $wpdb;

    // Haal de bijlage op.
    $attachment = get_post($attachment_id);
    if (!$attachment) {
        return __('Niet gekoppeld', 'modern-image-manager');
    }

    // Controleer of de afbeelding als uitgelichte afbeelding is ingesteld.
    if ($attachment->post_parent > 0) {
        $parent = get_post($attachment->post_parent);
        return $parent ? $parent->post_title : __('Niet gekoppeld', 'modern-image-manager');
    }

    // Zoek naar een koppeling via ACF-meta-velden.
    $acf_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_value = %d AND meta_key LIKE 'field_%%' LIMIT 1",
            $attachment_id
        )
    );
    if ($acf_data && !empty($acf_data->post_id)) {
        $parent = get_post((int) $acf_data->post_id);
        return $parent ? $parent->post_title : __('Niet gekoppeld', 'modern-image-manager');
    }

    return __('Niet gekoppeld', 'modern-image-manager');
}

/**
 * Haalt alle mediabestanden op via WP_Query.
 *
 * @return WP_Query De query met mediabestanden.
 */
function modern_image_manager_get_media_items(): WP_Query {
    $args = [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
    ];
    return new WP_Query($args);
}