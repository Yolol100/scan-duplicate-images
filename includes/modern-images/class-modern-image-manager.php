<?php
class Modern_Image_Manager {
    /**
     * Initialiseert de Modern Image Manager.
     */
    public static function init(): void {
        add_action( 'admin_menu', [ self::class, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
    }

    /**
     * Voegt het hoofdmenu en de submenus toe in het WordPress admin dashboard.
     */
    public static function add_admin_menu(): void {
        // Voeg het top-level menu toe.
        add_menu_page(
            __( 'Modern Image Manager', 'modern-image-manager' ),    // Paginatitel
            __( 'Modern Image', 'modern-image-manager' ),            // Menutitel
            'manage_options',                                         // Bevoegdheden
            'modern-image-manager',                                   // Menu slug
            'mim_render_admin_table_page',                            // Callback (extern gedefinieerd in de hoofdplugin)
            'dashicons-media-document',                               // Icon
            20                                                        // Positie
        );

        // Verwijder het automatisch gegenereerde duplicaatsubmenu-item.
        remove_submenu_page( 'modern-image-manager', 'modern-image-manager' );

        // Voeg submenus toe.
        add_submenu_page(
            'modern-image-manager',                                  // Hoofdmenu slug
            __( 'Exporteer Media', 'modern-image-manager' ),         // Paginatitel
            __( 'Exporteren', 'modern-image-manager' ),              // Submenu naam
            'manage_options',                                        // Bevoegdheden
            'export-media',                                          // Submenu slug
            [ 'Modern_Image_Manager_Export', 'render_export_page' ]   // Callback
        );

        add_submenu_page(
            'modern-image-manager',                                  // Hoofdmenu slug
            __( 'Importeer Media', 'modern-image-manager' ),         // Paginatitel
            __( 'Importeren', 'modern-image-manager' ),              // Submenu naam
            'manage_options',                                        // Bevoegdheden
            'import-media',                                          // Submenu slug
            [ 'Modern_Image_Manager_Import', 'render_import_page' ]   // Callback
        );

        // Voeg het "Scan Duplicate Images" submenu-item toe.
        add_submenu_page(
            'modern-image-manager',                                  // Hoofdmenu slug
            __( 'Scan Duplicate Images', 'modern-image-manager' ),   // Paginatitel
            __( 'Scan Duplicate Images', 'modern-image-manager' ),   // Menu titel
            'manage_options',                                        // Bevoegdheden
            'scan-duplicate-images',                                 // Menu slug
            [ 'Modern_Image_Manager_Scan', 'render_scan_page' ]        // Callback
        );
    }

    /**
     * Laadt de benodigde CSS- en JavaScript-assets voor de adminpagina.
     *
     * @param string $hook De huidige adminpagina hook.
     */
    public static function enqueue_assets( string $hook ): void {
        // Controleer of we op een relevante adminpagina zijn
        if ( ! in_array( $hook, [
            'toplevel_page_modern-image-manager',
            'modern-image-manager_page_export-media',
            'modern-image-manager_page_import-media',
            'modern-image-manager_page_scan-duplicate-images'
        ], true ) ) {
            return;
        }
        
        // Assets worden globaal ingeladen via een globale asset-functie.
        // Voeg hier indien nodig extra admin-specifieke assets toe.
    }
}