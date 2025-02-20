<?php
/**
 * Admin Page voor de Scan Duplicate Images functionaliteit.
 * Dit bestand voert de scan uit en toont de resultaten.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Voorkom directe toegang.
}

if ( ! current_user_can( 'manage_options' ) ) {
    return; // Zorg ervoor dat de gebruiker voldoende rechten heeft.
}

// Laad de scannerfunctie uit scanner.php
require_once MODERN_IMAGE_MANAGER_PATH . 'includes/scan-images/scanner.php';

?>

<div class="dius-admin-page">
    <div class="wrap mim-scan-page">
        <?php
        // Als de scan is uitgevoerd, toon dan de resultaten
        if ( isset( $_POST['dius_scan'] ) ) {
            // Start de scan en haal de resultaten op
            $results = dius_scan_pages_for_duplicate_images(); // Haalt de duplicaten op via de functie in scanner.php

            // Als er duplicaten zijn, toon dan de resultaten
            if ( ! empty( $results ) ) {
                dius_display_results( $results );
            } else {
                // Als er geen duplicaten zijn, toon het bericht
                echo '<p>' . esc_html__( 'Geen dubbele afbeeldingen gevonden.', 'modern-image-manager' ) . '</p>';
            }
        } 
        ?>
    </div>
</div>

<?php
/**
 * Functie om de scanresultaten weer te geven.
 */
function dius_display_results( $results ) {
    if ( empty( $results ) ) {
        echo '<p>' . esc_html__( 'Geen dubbele afbeeldingen gevonden.', 'modern-image-manager' ) . '</p>';
        return;
    }

    // Sorteer de resultaten alfabetisch op afbeelding naam
    ksort( $results );

    // Toon het totaal aantal resultaten
    $total_results = count( $results );
    echo '<h2>' . esc_html__( 'Scan Resultaten', 'modern-image-manager' ) . '</h2>';
    echo '<p><strong>' . esc_html__( 'Totaal aantal dubbele afbeeldingen gevonden:', 'modern-image-manager' ) . '</strong> ' . esc_html( $total_results ) . '</p>';

    // Toon de resultaten in een gestylede tabel
    echo '<table class="dius-results-table">';
        echo '<thead>';
            echo '<tr>';
                echo '<th>' . esc_html__( 'Pagina\'s', 'modern-image-manager' ) . '</th>';
                echo '<th>' . esc_html__( 'Afbeelding Naam', 'modern-image-manager' ) . '</th>';
            echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ( $results as $image => $pages ) {
            echo '<tr>';
                // Voeg de CSS-klasse toe voor de styling van de pagina's
                echo '<td class="dius-results-page">' . esc_html( implode( '<br>', $pages ) ) . '</td>';
                // Voeg de CSS-klasse toe voor de styling van de afbeelding naam
                echo '<td class="dius-results-image">' . esc_html( $image ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
    echo '</table>';
}
?>