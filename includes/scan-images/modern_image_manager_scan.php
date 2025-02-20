<?php
class Modern_Image_Manager_Scan {
    /**
     * Render de Scan Duplicate Images pagina.
     */
    public static function render_scan_page(): void {
        // Zorg ervoor dat de gebruiker voldoende rechten heeft
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Je hebt geen rechten om deze pagina te bekijken.', 'modern-image-manager' ) );
        }

        // Laad de scanpagina template.
        // Dit bestand bevat het formulier weer te geven en (indien ingediend) de scan uit te voeren.
        include MODERN_IMAGE_MANAGER_PATH . 'templates/scan-duplicate-page.php';
    }
}
?>