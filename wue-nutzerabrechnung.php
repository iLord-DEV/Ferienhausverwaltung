<?php
/**
 * Plugin Name: WUE Nutzerabrechnung
 * Description: System zur Erfassung und Abrechnung von Aufenthalten, Ölverbrauch und Übernachtungen
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: wue-nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin-Konstanten definieren
 */
define( 'WUE_PLUGIN_FILE', __FILE__ );
define( 'WUE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WUE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WUE_VERSION', '1.0.0' );

/**
 * Hauptklasse für das Plugin
 */
class WUE_Nutzerabrechnung {

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->load_dependencies();
        $this->initialize_components();
    }

    /**
     * Lädt erforderliche Abhängigkeiten
     */
    private function load_dependencies() {
        require_once WUE_PLUGIN_PATH . 'includes/class-wue-admin.php';
        require_once WUE_PLUGIN_PATH . 'includes/class-wue-aufenthalte.php';
        require_once WUE_PLUGIN_PATH . 'includes/class-wue-tankfuellungen.php';
    }

    /**
     * Initialisiert Plugin-Komponenten
     */
    private function initialize_components() {
        // Admin-Bereich initialisieren
        $admin = new WUE_Admin();

        // Aktivierungshook registrieren
        register_activation_hook( WUE_PLUGIN_FILE, array( $admin, 'activate_plugin' ) );
    }
}

// Plugin initialisieren
new WUE_Nutzerabrechnung();