<?php
/**
 * Hauptklasse für das Plugin
 *
 * @package WueNutzerabrechnung
 * @var array
 */

defined( 'ABSPATH' ) || exit;

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
	 *
	 * @var WUE_DB
	 */
	private $db;
	/**
	 * Helper-Instanz
	 *
	 * @var WUE_Helpers
	 */
	private $helpers;

	/**
	 * Admin-Instanz
	 *
	 * @var WUE_Admin
	 */
	private $admin;

	/**
	 * Dashboard-Instanz
	 *
	 * @var WUE_Dashboard
	 */
	private $dashboard;

	/**
	 * Aufenthalte-Instanz
	 *
	 * @var WUE_Aufenthalte
	 */
	private $aufenthalte;

	/**
	 * Tankfüllungen-Instanz
	 *
	 * @var WUE_Tankfuellungen
	 */
	private $tankfuellungen;

	/**
	 * Preise-Instanz
	 *
	 * @var WUE_Preise
	 */
	private $preise;

	/**
	 * Statistics-Instanz
	 *
	 * @var WUE_Statistics
	 */
	private $statistics;

	/**
	 * OpenWeather-Instanz
	 *
	 * @var WUE_OpenWeather
	 */
	private $weather;

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
			'core/class-wue-statistics.php',
			'core/class-wue-openweather.php',
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
		$this->preise         = new WUE_Preise();

		// Admin-Komponenten
		$this->admin     = new WUE_Admin();
		$this->dashboard = new WUE_Dashboard();

		// statisitcs-Komponenten
		$this->statistics = new WUE_Statistics();

		$this->weather = new WUE_OpenWeather();

		// Aktivierungshook registrieren
		register_activation_hook( WUE_PLUGIN_FILE, array( $this->admin, 'activate_plugin' ) );
	}

	/**
	 * Getter für Komponenten
	 */
	public function get_db() {
		return $this->db;
	}

	/**
	 * Getter für die Helper-Instanz
	 *
	 * @return WUE_Helpers
	 */
	public function get_helpers() {
		return $this->helpers;
	}

	/**
	 * Getter für die Aufenthalte-Instanz
	 *
	 * @return WUE_Aufenthalte
	 */
	public function get_aufenthalte() {
		return $this->aufenthalte;
	}

	/**
	 * Getter für die Tankfüllungen-Instanz
	 *
	 * @return WUE_Tankfuellungen
	 */
	public function get_tankfuellungen() {
		return $this->tankfuellungen;
	}

	/**
	 * Getter für die Preise-Instanz
	 *
	 * @return WUE_Preise
	 */
	public function get_preise() {
		return $this->preise;
	}

	/**
	 * Getter für die Statistics-Instanz
	 *
	 * @return WUE_Statistics
	 */
	public function get_statistics() {

		return $this->statistics;
	}

	/**
	 * Getter für die Weather-Instanz
	 *
	 * @return WUE_OpenWeather
	 */
	public function get_weather() {
		return $this->weather;
	}
}
