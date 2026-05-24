<?php
/**
 * Plugin Name: Media Insight
 * Plugin URI: https://webactueel.nl
 * Description: Scan featured images and ACF media usage inside WordPress.
 * Version: 1.1.0
 * Author: Webactueel
 * License: GPL2
 * Text Domain: media-insight
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MEDIA_INSIGHT_URL', plugin_dir_url(__FILE__));

add_action('admin_menu', 'media_insight_register_menu');
add_action('admin_enqueue_scripts', 'media_insight_admin_assets');

function media_insight_register_menu() {

    add_menu_page(
        esc_html__('Media Insight', 'media-insight'),
        esc_html__('Media Insight', 'media-insight'),
        'manage_options',
        'media-insight',
        'media_insight_render_page',
        'dashicons-format-image',
        80
    );
}

function media_insight_admin_assets($hook) {

    if ($hook !== 'toplevel_page_media-insight') {
        return;
    }

    wp_enqueue_style(
        'media-insight-admin',
        MEDIA_INSIGHT_URL . 'assets/admin.css',
        array(),
        '1.1.0'
    );
}

function media_insight_render_page() {

    $acf_active = class_exists('ACF');

    $post_count = wp_count_posts('post')->publish;
    $page_count = wp_count_posts('page')->publish;
    ?>

    <div class="wrap media-insight-wrap">

        <h1><?php echo esc_html__('Media Insight', 'media-insight'); ?></h1>

        <p class="media-insight-description">
            <?php echo esc_html__('Scan featured images and ACF media usage directly inside WordPress.', 'media-insight'); ?>
        </p>

        <div class="media-insight-grid">

            <div class="media-insight-card">

                <h2><?php echo esc_html__('Included', 'media-insight'); ?></h2>

                <ul class="mi-included-list">
                    <li><?php echo esc_html__('Pages — Featured Images', 'media-insight'); ?></li>
                    <li><?php echo esc_html__('Pages — ACF Image Fields', 'media-insight'); ?></li>
                    <li><?php echo esc_html__('Posts — Featured Images', 'media-insight'); ?></li>
                </ul>

            </div>

            <div class="media-insight-card">

                <h2><?php echo esc_html__('Environment', 'media-insight'); ?></h2>

                <table class="widefat striped">
                    <tbody>

                        <tr>
                            <td><?php echo esc_html__('Posts', 'media-insight'); ?></td>
                            <td><?php echo esc_html($post_count); ?></td>
                        </tr>

                        <tr>
                            <td><?php echo esc_html__('Pages', 'media-insight'); ?></td>
                            <td><?php echo esc_html($page_count); ?></td>
                        </tr>

                        <tr>
                            <td><?php echo esc_html__('ACF Detected', 'media-insight'); ?></td>
                            <td>
                                <?php
                                echo $acf_active
                                    ? esc_html__('Yes', 'media-insight')
                                    : esc_html__('No', 'media-insight');
                                ?>
                            </td>
                        </tr>

                    </tbody>
                </table>

            </div>

        </div>

    </div>

    <?php
}
