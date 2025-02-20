<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

/**
 * Class Modern_Image_Manager_Export
 *
 * Exporteert media als CSV.
 */
class Modern_Image_Manager_Export {
    /**
     * Rendert de exportpagina en verwerkt het CSV-exportproces indien nodig.
     *
     * @return void
     */
    public static function render_export_page(): void {
        if (isset($_POST['export_csv'])) {
            $export_option = $_POST['export_option'] ?? 'all';

            // Verkrijg de geselecteerde kolommen uit de POST-gegevens
            $selected_columns = isset($_POST['export_columns']) ? array_map('sanitize_text_field', $_POST['export_columns']) : [];

            // Genereer de CSV
            self::generate_csv($export_option, $selected_columns);
            exit;
        }

        // Laad de HTML-template
        include plugin_dir_path(__FILE__) . '../../templates/export-page.php';
    }

    /**
     * Genereert en verstuurt een CSV-bestand met media-informatie.
     *
     * @param string $export_option Export-optie (all, linked, unlinked)
     * @param array $selected_columns De geselecteerde kolommen voor export
     * @return void
     */
    public static function generate_csv(string $export_option = 'all', array $selected_columns = []): void {
        // Zorg ervoor dat output buffering is gestopt indien nodig
        if (ob_get_length()) {
            ob_end_clean();
        }

        // Stel de headers in voor de CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=media-export.csv');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Expires: 0');

        // Open de output stream
        $output = fopen('php://output', 'w');
        if ($output === false) {
            wp_die( __( 'Kon output stream niet openen.', 'modern-image-manager' ) );
        }

        // Kolomkoppen voor de CSV
        $column_headers = [
            'filename'     => __( 'Bestandsnaam', 'modern-image-manager' ),
            'new_filename' => __( 'Nieuwe bestandsnaam', 'modern-image-manager' ),
            'page'         => __( 'Pagina', 'modern-image-manager' ),
            'title'        => __( 'Titel', 'modern-image-manager' ),
            'caption'      => __( 'Caption', 'modern-image-manager' ),
            'description'  => __( 'Description', 'modern-image-manager' ),
            'alt_text'     => __( 'Alt-tag', 'modern-image-manager' ),
            'file_url'     => __( 'Bestands-URL', 'modern-image-manager' ),
        ];

        // Als geen kolommen zijn geselecteerd, geef dan alle kolommen weer
        if (empty($selected_columns)) {
            $selected_columns = array_keys($column_headers);
        }

        // Schrijf de kolomkoppen naar het CSV-bestand
        $csv_headers = array_map(fn($column) => $column_headers[$column] ?? '', $selected_columns);
        fputcsv($output, $csv_headers);

        // Query voor alle media bijlagen
        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/webp'],
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
        ];
        $media_items = get_posts($args);

        // Controleer of er media-items zijn
        if (empty($media_items)) {
            echo '<p>' . __( 'Geen afbeeldingen gevonden voor de geselecteerde filteroptie.', 'modern-image-manager' ) . '</p>';
            return;
        }

        // Loop door alle media-items en schrijf ze naar de CSV
        foreach ($media_items as $media) {
            $attachment_id = $media->ID;
            $attachment    = get_post($attachment_id);

            if (!$attachment) {
                continue;
            }

            // Verkrijg gegevens van het bijgevoegde bestand
            $file_path   = get_attached_file($attachment_id);
            $filename    = basename($file_path) ?: '';
            $alt_text    = get_post_meta($attachment_id, '_wp_attachment_image_alt', true) ?: '';
            $caption     = $attachment->post_excerpt ?: '';
            $description = $attachment->post_content ?: '';
            $title       = $attachment->post_title ?: '';
            $file_url    = wp_get_attachment_url($attachment_id) ?: '';

            // Verkrijg de gekoppelde pagina (indien van toepassing)
            $pagina = __( 'Geen', 'modern-image-manager' );
            if ($attachment->post_parent) {
                $parent_post = get_post($attachment->post_parent);
                if ($parent_post) {
                    $pagina = get_the_title($parent_post);
                }
            }

            // Filteren op basis van de geselecteerde export-optie
            if ($export_option === 'linked' && $pagina === __( 'Geen', 'modern-image-manager' )) {
                continue;
            } elseif ($export_option === 'unlinked' && $pagina !== __( 'Geen', 'modern-image-manager' )) {
                continue;
            }

            // Gegevens schrijven naar de CSV op basis van de geselecteerde kolommen
            $data = [];
            foreach ($selected_columns as $column) {
                switch ($column) {
                    case 'filename':
                        $data[] = $filename;
                        break;
                    case 'new_filename':
                        $data[] = ''; // Voeg logica toe voor nieuwe bestandsnaam indien nodig
                        break;
                    case 'page':
                        $data[] = $pagina;
                        break;
                    case 'title':
                        $data[] = $title;
                        break;
                    case 'caption':
                        $data[] = $caption;
                        break;
                    case 'description':
                        $data[] = $description;
                        break;
                    case 'alt_text':
                        $data[] = $alt_text;
                        break;
                    case 'file_url':
                        $data[] = $file_url;
                        break;
                }
            }

            fputcsv($output, $data);
        }

        fclose($output);
        exit;
    }
}