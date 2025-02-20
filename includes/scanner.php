<?php

function dius_scan_pages_for_duplicate_images() {
    $results = [];
    $pages = get_pages();

    foreach ($pages as $page) {
        // Scan reguliere content van de pagina.
        $content = $page->post_content;
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $image_url) {
                $image_name = basename(parse_url($image_url, PHP_URL_PATH));

                // Voeg alle afbeeldingen toe aan de resultaten.
                dius_add_image_to_results($results, $image_name, $page->post_title);
            }
        }

        // Scan ACF-velden indien aanwezig.
        if (class_exists('ACF')) {
            $fields = get_fields($page->ID);

            if (!empty($fields)) {
                foreach ($fields as $field) {
                    dius_check_acf_field($field, $results, $page->post_title);
                }
            }
        }
    }

    // Filter om alleen afbeeldingen te tonen die op meerdere pagina's voorkomen.
    return array_filter($results, function($pages) {
        return count($pages) > 1;
    });
}

// Controleer of een ACF-veld een afbeelding bevat.
function dius_check_acf_field($field, &$results, $page_title) {
    if (is_array($field)) {
        if (isset($field['url'])) {
            $image_name = basename(parse_url($field['url'], PHP_URL_PATH));
            dius_add_image_to_results($results, $image_name, $page_title);
        } else {
            foreach ($field as $sub_field) {
                dius_check_acf_field($sub_field, $results, $page_title);
            }
        }
    } elseif (is_string($field) && dius_is_image_url($field)) {
        $image_name = basename(parse_url($field, PHP_URL_PATH));
        dius_add_image_to_results($results, $image_name, $page_title);
    }
}

// Controleer of een URL een afbeelding is.
function dius_is_image_url($url) {
    return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url);
}

// Voeg een afbeelding toe aan de resultaten.
function dius_add_image_to_results(&$results, $image_name, $page_title) {
    if (!isset($results[$image_name])) {
        $results[$image_name] = [];
    }
    if (!in_array($page_title, $results[$image_name])) {
        $results[$image_name][] = $page_title;
    }
}