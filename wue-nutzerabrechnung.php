<?php
/**
 * Plugin Name: Nutzerabrechnung
 * Description: System zur Erfassung und Abrechnung von Aufenthalten, Ölverbrauch und Übernachtungen
 * Version: 1.0.0
 * Author: Christoph Heim
 * License: GPL v2 or later
 * Text Domain: wue-nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

// Plugin-Konstanten definieren
define( 'WUE_PLUGIN_FILE', __FILE__ );
define( 'WUE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WUE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WUE_VERSION', '1.0.0' );

// Hauptklasse laden
require_once WUE_PLUGIN_PATH . 'includes/class-wue-nutzerabrechnung.php';

// Core-Funktionen laden
require_once WUE_PLUGIN_PATH . 'includes/wue-core-functions.php';

/**
 * Globale Funktion zum Zugriff auf Plugin-Funktionalitäten
 *
 * @return WUE_Nutzerabrechnung
 */
function WUE() {
	return WUE_Nutzerabrechnung::get_instance();
}

// Plugin initialisieren
WUE();
