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
 * Textdomain für Übersetzungen laden
 */
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'wue-nutzerabrechnung', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
});

add_action( 'admin_enqueue_scripts', function() {
    // Chart.js über CDN einbinden
    wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true );

    // Eigene JS-Datei für die Chart-Logik
    wp_enqueue_script( 'wue-charts', WUE_PLUGIN_URL . 'assets/js/admin-charts.js', ['chart-js'], WUE_VERSION, true );

    // Optional: Daten an das Skript übergeben
    wp_localize_script( 'wue-charts', 'wueChartData', [
        'labels' => ['Winter', 'Frühling', 'Sommer', 'Herbst'], // Dummy-Daten
        'data'   => [120, 80, 60, 100] // Dummy-Verbrauchsdaten
    ] );
});


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
        $files = [
            'class-wue-admin.php',
            'class-wue-aufenthalte.php',
            'class-wue-tankfuellungen.php'
        ];

        foreach ( $files as $file ) {
            $path = WUE_PLUGIN_PATH . 'includes/' . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            } else {
                error_log( "Fehler: Datei {$file} fehlt." );
            }
        }
    }

    /**
     * Initialisiert Plugin-Komponenten
     */
    private function initialize_components() {
        // Admin-Bereich initialisieren
        $admin = new WUE_Admin();

        // Aktivierungshook registrieren
        register_activation_hook( WUE_PLUGIN_FILE, array( $admin, 'activate_plugin' ) );

        // Optional: Weitere Initialisierungen hinzufügen
    }
}

// Plugin initialisieren
new WUE_Nutzerabrechnung();
