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
	 * Die einzige Instanz dieser Klasse
	 *
	 * @var WUE_Nutzerabrechnung
	 */
	private static $instance = null;

	/**
	 * Komponenten-Instanzen
	 */
	private $db;
	/**
	 * Helper instance
	 *
	 * @var WUE_Helpers
	 */
	private $helpers;

	/**
	 * Admin instance
	 *
	 * @var WUE_Admin
	 */
	private $admin;

	/**
	 * Dashboard instance
	 *
	 * @var WUE_Dashboard
	 */
	private $dashboard;

	/**
	 * Aufenthalte instance
	 *
	 * @var WUE_Aufenthalte
	 */
	private $aufenthalte;

	/**
	 * Tankfuellungen instance
	 *
	 * @var WUE_Tankfuellungen
	 */
	private $tankfuellungen;

	/**
	 * Preise instance
	 *
	 * @var WUE_Preise
	 */
	private $preise;

	/**
	 * Konstruktor
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->initialize_components();
	}

	/**
	 * Singleton-Instanz zurückgeben
	 *
	 * @return WUE_Nutzerabrechnung
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Lädt erforderliche Abhängigkeiten
	 */
	private function load_dependencies() {
		// Admin-Klassen
		$admin_files = array(
			'admin/class-wue-admin.php',
			'admin/class-wue-dashboard.php',
		);

		// Core-Klassen
		$core_files = array(
			'core/class-wue-aufenthalte.php',
			'core/class-wue-tankfuellungen.php',
			'core/class-wue-preise.php',
		);

		// Datenbank-Klassen
		$db_files = array(
			'db/class-wue-db.php',
		);

		// Helper-Klassen
		$helper_files = array(
			'helpers/class-wue-helpers.php',
		);

		// Alle Dateien zusammenführen
		$all_files = array_merge( $admin_files, $core_files, $db_files, $helper_files );

		// Dateien laden
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
		// Basis-Komponenten
		$this->db      = new WUE_DB();
		$this->helpers = new WUE_Helpers();

		// Core-Komponenten
		$this->aufenthalte    = new WUE_Aufenthalte();
		$this->tankfuellungen = new WUE_Tankfuellungen();
		// $this->preise         = new WUE_Preise();

		// Admin-Komponenten
		$this->admin     = new WUE_Admin();
		$this->dashboard = new WUE_Dashboard();

		// Aktivierungshook registrieren
		register_activation_hook( WUE_PLUGIN_FILE, array( $this->admin, 'activate_plugin' ) );
	}

	/**
	 * Getter für Komponenten
	 */
	public function get_db() {
		return $this->db;
	}

	public function get_helpers() {
		return $this->helpers;
	}

	public function get_aufenthalte() {
		return $this->aufenthalte;
	}

	public function get_tankfuellungen() {
		return $this->tankfuellungen;
	}

	public function get_preise() {
		return $this->preise;
	}
}

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

/**
 * Berechtigungen für das Bearbeiten von Aufenthalten hinzufügen
 */
function wue_add_edit_capabilities() {
	$roles = array( 'subscriber', 'administrator' );
	foreach ( $roles as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			$role->add_cap( 'edit_aufenthalt' );
		}
	}
}
add_action( 'init', 'wue_add_edit_capabilities' );

/**
 * Textdomain laden
 */
function wue_load_textdomain() {
	load_plugin_textdomain(
		'wue-nutzerabrechnung',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages/'
	);
}
add_action( 'init', 'wue_load_textdomain' );
