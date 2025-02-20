<?php

// Add the admin menu item.
function dius_add_admin_page() {
    add_menu_page(
        'Image Duplicator',
        'Image Duplicator',
        'manage_options',
        'image-duplicator',
        'dius_admin_page_content',
        'dashicons-search',
        20
    );
}

// Display the admin page content.
function dius_admin_page_content() {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap dius-wrap" style="font-family: Arial, sans-serif; margin: 20px;">';
    echo '<h1 class="dius-title" style="color: #0073aa; font-size: 24px;">Image Duplicator</h1>';

    if (isset($_POST['dius_scan'])) {
        $results = dius_scan_pages_for_duplicate_images();
        dius_display_results($results);
    } else {
        echo '<form method="POST" class="dius-form" style="margin-top: 20px;">';
        echo '<input type="hidden" name="dius_scan" value="1">';
        echo '<button style="background-color: #0073aa; color: #fff; border: none; padding: 10px 20px; font-size: 16px; border-radius: 4px; cursor: pointer;">Start Scan</button>';
        echo '</form>';
    }

    echo '</div>';
}

// Display scan results.
function dius_display_results($results) {
    if (empty($results)) {
        echo '<p style="color: #444; font-size: 16px;">No duplicate images found.</p>';
        return;
    }

    // Sort the results alphabetically by image name.
    ksort($results);

    // Calculate the total number of results.
    $total_results = count($results);

    // Display the total number of results.
    echo '<h2 style="color: #0073aa; font-size: 20px; margin-top: 20px;">Scan Results</h2>';
    echo '<p style="color: #444; font-size: 16px; margin-bottom: 20px;"><strong>Total Duplicate Images Found:</strong> ' . esc_html($total_results) . '</p>';

    // Display results in a styled table.
    echo '<table style="width: 100%; border-collapse: collapse; margin-top: 20px; border: 1px solid #ccc;">';
    echo '<thead style="background-color: #f4f4f4;">';
    echo '<tr>';
    echo '<th style="text-align: left; padding: 10px; border-bottom: 2px solid #ccc;">Pages</th>';
    echo '<th style="text-align: left; padding: 10px; border-bottom: 2px solid #ccc;">Image Name</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($results as $image => $pages) {
        echo '<tr>';
        echo '<td style="padding: 10px; border-bottom: 1px solid #eee;">' . implode('<br>', array_map('esc_html', $pages)) . '</td>';
        echo '<td style="padding: 10px; border-bottom: 1px solid #eee; color: #0073aa;">' . esc_html($image) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
}