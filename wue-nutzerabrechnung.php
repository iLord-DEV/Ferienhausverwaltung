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
	private $wue_db;
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
		// Admin-Klassen.
		$admin_files = array(
			'admin/class-wue-admin.php',
			'admin/class-wue-dashboard.php',
		);

		// Core-Klassen.
		$core_files = array(
			'core/class-wue-aufenthalte.php',
			'core/class-wue-tankfuellungen.php',
			'core/class-wue-preise.php',
		);

		// Datenbank-Klassen.
		$db_files = array(
			'db/class-wue-db.php',
		);

		// Helper-Klassen.
		$helper_files = array(
			'helpers/class-wue-helpers.php',
		);

		// Alle Dateien zusammenführen.
		$all_files = array_merge( $admin_files, $core_files, $db_files, $helper_files );

		// Dateien laden.
		foreach ( $all_files as $file ) {
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
		// Admin-Bereich initialisieren.
		$admin = new WUE_Admin();
		// Dashboard initialisieren.
		$dashboard = new WUE_Dashboard();

		// Aktivierungshook registrieren.
		register_activation_hook( WUE_PLUGIN_FILE, array( $admin, 'activate_plugin' ) );

		// Optional: Weitere Initialisierungen hinzufügen.
	}
}

// Plugin initialisieren.
new WUE_Nutzerabrechnung();


/**
 * Berechtigungen für das Bearbeiten von Aufenthalten hinzufügen
 */
function wue_add_edit_capabilities() {
	// Rollen definieren.
	$roles = array( 'subscriber', 'administrator' );
	foreach ( $roles as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			$role->add_cap( 'edit_aufenthalt' ); // Berechtigung hinzufügen.
		}
	}
}
add_action( 'init', 'wue_add_edit_capabilities' );


add_action(
	'init',
	function () {
		load_plugin_textdomain( 'query-monitor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
);
