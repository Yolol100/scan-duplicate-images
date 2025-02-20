<?php
/**
 * Scanner voor dubbele afbeeldingen.
 *
 * Dit bestand scant de mediabibliotheek op dubbele afbeeldingen door de MD5-hash van elk afbeeldingsbestand te berekenen.
 * Zorg ervoor dat dit bestand alleen wordt uitgevoerd binnen de WordPress-omgeving.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Voorkom directe toegang.
}

/**
 * Scan de mediabibliotheek op dubbele afbeeldingen.
 *
 * @return array Een array met duplicaatgroepen, waarbij elke groep een array is met gegevens van de gedupliceerde afbeeldingen.
 */
function mim_scan_duplicate_images() {
    // Haal alle bijlagen op met het mime-type image
    $attachments = get_posts( array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'numberposts'    => -1,
    ) );
    
    // Arrays voor het opslaan van hashes en duplicaten
    $hashes = array();
    $duplicates = array();
    
    // Loop door elke bijlage
    foreach ( $attachments as $attachment ) {
        $file = get_attached_file( $attachment->ID );
        
        // Controleer of het bestand bestaat
        if ( ! file_exists( $file ) ) {
            continue;
        }
        
        // Bereken de MD5-hash van het bestand
        $hash = md5_file( $file );
        
        // Controleer of deze hash al eerder is aangetroffen
        if ( isset( $hashes[ $hash ] ) ) {
            // Voeg de huidige bijlage toe aan de bestaande duplicaatgroep
            $duplicates[ $hash ][] = array(
                'ID'    => $attachment->ID,
                'url'   => wp_get_attachment_url( $attachment->ID ),
                'title' => get_the_title( $attachment->ID ),
            );
        } else {
            // Sla de hash op en start een nieuwe groep
            $hashes[ $hash ] = $attachment->ID;
            $duplicates[ $hash ] = array(
                array(
                    'ID'    => $attachment->ID,
                    'url'   => wp_get_attachment_url( $attachment->ID ),
                    'title' => get_the_title( $attachment->ID ),
                )
            );
        }
    }
    
    // Filter de groepen: we willen alleen groepen met meer dan één afbeelding (echte duplicaten)
    $duplicate_groups = array_filter( $duplicates, function( $group ) {
        return count( $group ) > 1;
    } );
    
    return $duplicate_groups;
}

// Voer de scan uit en sla de resultaten op
$duplicate_groups = mim_scan_duplicate_images();

// Toon de resultaten
if ( ! empty( $duplicate_groups ) ) {
    echo '<h2>' . esc_html__( 'Duplicates Found:', 'modern-image-manager' ) . '</h2>';
    echo '<ul>';
    foreach ( $duplicate_groups as $hash => $group ) {
        echo '<li>';
        echo '<strong>' . esc_html__( 'Duplicate Group (Hash:', 'modern-image-manager' ) . ' ' . esc_html( $hash ) . ')</strong><br />';
        
        // Loop door de duplicaten in de groep en toon de details
        foreach ( $group as $file ) {
            echo 'ID: ' . esc_html( $file['ID'] ) . ' - ';
            echo '<a href="' . esc_url( $file['url'] ) . '" target="_blank">' . esc_html( $file['title'] ) . '</a><br />';
        }
        
        echo '</li>';
    }
    echo '</ul>';
} else {
    echo '<p>' . esc_html__( 'No duplicate images found.', 'modern-image-manager' ) . '</p>';
}