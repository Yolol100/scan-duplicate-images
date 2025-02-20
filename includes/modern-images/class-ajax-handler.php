<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

/**
 * Class ModernImageManagerAjaxHandler
 *
 * Verwerkt AJAX-verzoeken voor het bijwerken van media meta.
 * Debug-meldingen worden gelogd naar wp-content/debug.log.
 */
class ModernImageManagerAjaxHandler
{
    /**
     * Initialiseert de AJAX handler.
     */
    public static function init(): void
    {
        add_action('wp_ajax_update_media_meta', [self::class, 'updateMediaMetaCallback']);
        add_action('wp_ajax_delete_media', [self::class, 'deleteMediaCallback']);
    }

    /**
     * Callback voor het bijwerken van de media meta.
     */
    public static function updateMediaMetaCallback(): void
    {
        error_log('DEBUG: updateMediaMetaCallback aangeroepen');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Geen toestemming.', 'modern-image-manager')]);
            return;
        }

        $attachmentId   = self::sanitizeInt($_POST['attachment_id'] ?? null);
        $newTitle       = sanitize_text_field($_POST['title'] ?? '');
        $newCaption     = sanitize_text_field($_POST['caption'] ?? '');
        $newDescription = sanitize_textarea_field($_POST['description'] ?? '');
        $newAlt         = sanitize_text_field($_POST['alt'] ?? '');
        $newFilename    = sanitize_file_name($_POST['new_filename'] ?? '');

        if (empty($attachmentId)) {
            wp_send_json_error(['message' => __('Ongeldig bijlage-ID.', 'modern-image-manager')]);
            return;
        }

        // Controleer of de bijlage bestaat om racecondities te vermijden
        $attachment = get_post($attachmentId);
        if (!$attachment) {
            error_log('DEBUG: Bijlage niet gevonden of nog niet volledig opgeslagen (raceconditie)');
            wp_send_json_error(['message' => __('Bijlage niet gevonden of nog niet volledig opgeslagen.', 'modern-image-manager')]);
            return;
        }

        // Verwijder bestandsextensie uit titel, caption, description en alt-tag
        $newTitle       = self::removeFileExtension($newTitle);
        $newCaption     = self::removeFileExtension($newCaption);
        $newDescription = self::removeFileExtension($newDescription);
        $newAlt         = self::removeFileExtension($newAlt);

        // Update post gegevens (titel, caption, description)
        $attachmentPost = [
            'ID'           => $attachmentId,
            'post_title'   => $newTitle,
            'post_excerpt' => $newCaption,
            'post_content' => $newDescription,
        ];

        $updateResult = wp_update_post($attachmentPost, true);
        if (is_wp_error($updateResult)) {
            error_log('DEBUG: Fout bij wp_update_post: ' . $updateResult->get_error_message());
            wp_send_json_error(['message' => __('Fout bij het bijwerken van de media gegevens: ', 'modern-image-manager') . $updateResult->get_error_message()]);
            return;
        }

        // Alt-tekst bijwerken als er een waarde is
        if (!empty($newAlt)) {
            error_log('DEBUG: Nieuwe alt-tekst: ' . $newAlt);
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $newAlt);
        }

        // Bestandsnaam ALLEEN updaten als er een nieuwe bestandsnaam is opgegeven
        if (!empty($newFilename)) {
            if (!self::updateFileName($attachmentId, $newFilename)) {
                wp_send_json_error(['message' => __('Fout bij het hernoemen van het bestand.', 'modern-image-manager')]);
                return;
            }
        }

        wp_send_json_success(['message' => __('Media info succesvol bijgewerkt.', 'modern-image-manager')]);
        error_log('DEBUG: updateMediaMetaCallback succesvol afgerond');
    }

    /**
     * Verwijdert de bestandsextensie (zoals .png) van de bestandsnaam.
     *
     * @param string $string
     * @return string
     */
    private static function removeFileExtension(string $string): string
    {
        return preg_replace('/\.[^.\s]{3,4}$/', '', $string);
    }

    /**
     * Callback voor het verwijderen van een media-item.
     *
     * Verwijdert de bijlage uit de database en de server.
     *
     * @return void
     */
    public static function deleteMediaCallback(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Geen toestemming.', 'modern-image-manager')]);
            return;
        }

        $attachmentId = self::sanitizeInt($_POST['attachment_id'] ?? null);
        if (empty($attachmentId)) {
            wp_send_json_error(['message' => __('Ongeldig bijlage-ID.', 'modern-image-manager')]);
            return;
        }

        $deleted = wp_delete_attachment($attachmentId, true);
        if ($deleted) {
            wp_send_json_success(['message' => __('Media succesvol verwijderd.', 'modern-image-manager')]);
        } else {
            wp_send_json_error(['message' => __('Fout bij het verwijderen van de media.', 'modern-image-manager')]);
        }
    }

    /**
     * Hernoemt het bestand van de bijlage en werkt de guid en post_name bij.
     *
     * @param int    $attachmentId
     * @param string $newFilename
     *
     * @return bool
     */
    private static function updateFileName(int $attachmentId, string $newFilename): bool
    {
        error_log(sprintf('DEBUG: updateFileName aangeroepen met attachmentId: %d en newFilename: %s', $attachmentId, $newFilename));

        $file = get_attached_file($attachmentId);
        if (!$file || !file_exists($file)) {
            error_log('DEBUG: Bestand niet gevonden voor bijlage ID: ' . $attachmentId);
            wp_send_json_error(['message' => __('Bestand niet gevonden voor bijlage ID.', 'modern-image-manager')]);
            return false;
        }

        $dir = pathinfo($file, PATHINFO_DIRNAME);
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $newFile = $dir . '/' . $newFilename . '.' . $ext;

        // Controleer of de bestandsnaam niet onnodig wordt veranderd
        if ($file === $newFile) {
            error_log('DEBUG: Bestandsnaam is al correct, geen wijziging nodig');
            return true;
        }

        if (file_exists($newFile)) {
            error_log('DEBUG: De nieuwe bestandsnaam bestaat al: ' . $newFile);
            wp_send_json_error(['message' => __('De nieuwe bestandsnaam bestaat al.', 'modern-image-manager')]);
            return false;
        }

        if (!rename($file, $newFile)) {
            $errorMessage = error_get_last()['message'] ?? 'Onbekende fout';
            error_log(sprintf('DEBUG: Fout bij rename: %s (Oude bestand: %s, Nieuw bestand: %s)', $errorMessage, $file, $newFile));
            wp_send_json_error(['message' => __('Fout bij het hernoemen van het bestand.', 'modern-image-manager')]);
            return false;
        }

        update_attached_file($attachmentId, $newFile);
        error_log('DEBUG: Nieuw bestandspad: ' . $newFile);

        // Voeg een korte vertraging toe om racecondities te vermijden
        sleep(1);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachmentId, $newFile);
        error_log('DEBUG: Gegenereerde metadata: ' . print_r($metadata, true));

        if (!$metadata) {
            error_log('DEBUG: Metadata niet correct gegenereerd voor attachment ID ' . $attachmentId);
            wp_send_json_error(['message' => __('Fout bij het genereren van metadata.', 'modern-image-manager')]);
            return false;
        }

        // Forceer de metadata-update, zelfs als de data hetzelfde is
        delete_post_meta($attachmentId, '_wp_attachment_metadata');
        $metadataUpdated = wp_update_attachment_metadata($attachmentId, $metadata);
        if (is_wp_error($metadataUpdated) || $metadataUpdated === false) {
            error_log('DEBUG: Fout bij wp_update_attachment_metadata voor bestand: ' . $newFile);
            wp_send_json_error(['message' => __('Fout bij het bijwerken van de metadata.', 'modern-image-manager')]);
            return false;
        }

        // Update guid en post_name in de database
        $upload_dir = wp_upload_dir();
        $new_url = $upload_dir['baseurl'] . '/' . basename($newFile);
        wp_update_post([
            'ID'        => $attachmentId,
            'guid'      => $new_url,
            'post_name' => sanitize_title($newFilename),
        ]);
        error_log('DEBUG: updateFileName succesvol afgerond voor ' . $newFile);
        return true;
    }

    /**
     * Sanitizeert een waarde als integer.
     *
     * @param mixed $value
     * @return int|null
     */
    private static function sanitizeInt($value): ?int
    {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
    }
}

ModernImageManagerAjaxHandler::init();