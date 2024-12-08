<?php
/**
 * Admin-Funktionalitäten für die Nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin-Klasse für die Nutzerabrechnung
 */
class WUE_Admin {
	/**
	 * Database instance
	 *
	 * @var WUE_DB
	 */
	private $db;

	/**
	 * Konstruktor
	 */
	public function __construct() {
		$this->db = new WUE_DB();
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'init_admin' ) );
	}

	/**
	 * Initialisiert Admin-Funktionalitäten
	 */
	public function init_admin() {
		// CSS einbinden
		wp_register_style(
			'wue-admin-style',
			WUE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WUE_VERSION
		);
		wp_enqueue_style( 'wue-admin-style' );

		// Dashboard-spezifisches CSS
		$screen = get_current_screen();
		if ( $screen && 'dashboard' === $screen->base ) {
			wp_enqueue_style(
				'wue-dashboard-style',
				WUE_PLUGIN_URL . 'assets/css/dashboard.css',
				array( 'wue-admin-style' ),
				WUE_VERSION
			);
		}
	}

	/**
	 * Plugin aktivieren
	 */
	public function activate_plugin() {
		$this->db->create_tables();
		$this->db->insert_default_prices( gmdate( 'Y' ) );
		flush_rewrite_rules();
	}

	/**
	 * Fügt Admin-Menüpunkte hinzu
	 */
	public function add_admin_menu() {
		// Hauptmenü
		add_menu_page(
			esc_html__( 'Nutzerabrechnung', 'wue-nutzerabrechnung' ),
			esc_html__( 'Nutzerabrechnung', 'wue-nutzerabrechnung' ),
			'read',
			'wue-nutzerabrechnung',
			array( $this, 'display_admin_page' ),
			'dashicons-chart-area'
		);

		// Untermenü für Tankfüllungen
		add_submenu_page(
			'wue-nutzerabrechnung',
			esc_html__( 'Tankfüllung erfassen', 'wue-nutzerabrechnung' ),
			esc_html__( 'Tankfüllung erfassen', 'wue-nutzerabrechnung' ),
			'read',
			'wue-tankfuellungen',
			array( $this, 'display_tankfuellung_form' )
		);

		// Preiskonfiguration (nur für Administratoren)
		add_submenu_page(
			'wue-nutzerabrechnung',
			esc_html__( 'Preiskonfiguration', 'wue-nutzerabrechnung' ),
			esc_html__( 'Preiskonfiguration', 'wue-nutzerabrechnung' ),
			'manage_options',
			'wue-nutzerabrechnung-preise',
			array( $this, 'display_price_settings' )
		);

		// Hook für zusätzliche Menüeinträge
		do_action( 'wue_admin_menu' );
	}

	/**
	 * Zeigt die Admin-Hauptseite an
	 */
	public function display_admin_page() {
		$yearly_stats = $this->db->get_yearly_statistics( gmdate( 'Y' ) );
		$prices       = $this->db->get_prices_for_year( gmdate( 'Y' ) );

		// Konvertierung ins Array-Format für das Template
		$current_prices = array(
			'oil_price'    => $prices->oelpreis_pro_liter,
			'member_price' => $prices->uebernachtung_mitglied,
			'guest_price'  => $prices->uebernachtung_gast,
		);

		include WUE_PLUGIN_PATH . 'templates/admin-page.php';
	}

	/**
	 * Zeigt das Formular zur Tankfüllungserfassung an
	 */
	public function display_tankfuellung_form() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Sie müssen angemeldet sein, um diese Seite aufzurufen.', 'wue-nutzerabrechnung' ) );
		}

		// Verarbeite Formularübermittlung
		if ( isset( $_POST['submit'] ) && check_admin_referer( 'wue_save_tankfuellung' ) ) {
			$this->save_tankfuellung();
		}

		include WUE_PLUGIN_PATH . 'templates/tankfuellung-form.php';
	}

	/**
	 * Speichert eine neue Tankfüllung
	 */
	private function save_tankfuellung() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
			'wue_save_tankfuellung'
		) ) {
			wp_die( esc_html__( 'Sicherheitsüberprüfung fehlgeschlagen.', 'wue-nutzerabrechnung' ) );
		}

		$tankfuellung = isset( $_POST['wue_tankfuellung'] ) ?
			array_map( 'sanitize_text_field', wp_unslash( $_POST['wue_tankfuellung'] ) ) :
			array();

		// Validiere und bereinige die Eingaben
		$data = array(
			'datum'                => sanitize_text_field( $tankfuellung['datum'] ),
			'liter'                => floatval( $tankfuellung['liter'] ),
			'preis_pro_liter'      => floatval( $tankfuellung['preis_pro_liter'] ),
			'brennerstunden_stand' => floatval( $tankfuellung['brennerstunden_stand'] ),
		);

		// Grundlegende Validierung
		if ( $data['liter'] <= 0 || $data['preis_pro_liter'] <= 0 ) {
			add_settings_error(
				'wue_tankfuellung',
				'invalid_values',
				esc_html__( 'Bitte geben Sie gültige Werte für Liter und Preis ein.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return;
		}

		$result = $this->db->save_tankfuellung( $data );

		if ( false === $result ) {
			add_settings_error(
				'wue_tankfuellung',
				'save_error',
				esc_html__( 'Fehler beim Speichern der Tankfüllung.', 'wue-nutzerabrechnung' ),
				'error'
			);
		} else {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'wue-tankfuellungen',
						'message' => 'success',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * Zeigt die Preiskonfiguration an
	 */
	public function display_price_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sie haben nicht die erforderlichen Berechtigungen für diese Aktion.', 'wue-nutzerabrechnung' ) );
		}

		// Verarbeite das Hinzufügen eines neuen Jahres
		if ( isset( $_POST['action'] ) && 'add_year' === $_POST['action'] && check_admin_referer( 'wue_add_year' ) ) {
			$this->add_new_year();
		}

		// Verarbeite das Speichern der Preise
		if ( isset( $_POST['wue_save_prices'] ) && check_admin_referer( 'wue_save_prices' ) ) {
			$this->save_price_settings();
		}

		// Bestimme das aktuelle Jahr
		$year = isset( $_GET['year'] ) ? intval( $_GET['year'] ) : gmdate( 'Y' );

		// Hole die Preise für das Jahr
		$prices = $this->db->get_prices_for_year( $year );

		// Zeige das Template
		require WUE_PLUGIN_PATH . 'templates/price-settings.php';
	}

	/**
	 * Fügt ein neues Jahr hinzu
	 */
	private function add_new_year() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
			'wue_add_year'
		) ) {
			wp_die( esc_html__( 'Sicherheitsüberprüfung fehlgeschlagen.', 'wue-nutzerabrechnung' ) );
		}

		$new_year = isset( $_POST['new_year'] ) ? intval( $_POST['new_year'] ) : 0;
		$result   = $this->db->insert_default_prices( $new_year );

		if ( false === $result ) {
			wp_die( esc_html__( 'Fehler beim Hinzufügen des neuen Jahres.', 'wue-nutzerabrechnung' ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wue-nutzerabrechnung-preise',
					'year' => $new_year,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Speichert die Preiseinstellungen
	 */
	private function save_price_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sie haben nicht die erforderlichen Berechtigungen für diese Aktion.', 'wue-nutzerabrechnung' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wue_save_prices' ) ) {
			wp_die( esc_html__( 'Sicherheitsüberprüfung fehlgeschlagen.', 'wue-nutzerabrechnung' ) );
		}

		$year   = isset( $_POST['wue_year'] ) ? intval( $_POST['wue_year'] ) : gmdate( 'Y' );
		$prices = isset( $_POST['wue_prices'] ) ? array_map( 'floatval', wp_unslash( $_POST['wue_prices'] ) ) : array();

		// Validierung der Preise
		if ( empty( $prices['oelpreis_pro_liter'] ) || empty( $prices['uebernachtung_mitglied'] ) ||
			empty( $prices['uebernachtung_gast'] ) || empty( $prices['verbrauch_pro_brennerstunde'] ) ) {
			add_settings_error(
				'wue_prices',
				'invalid_prices',
				esc_html__( 'Alle Preise müssen größer als 0 sein.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return;
		}

		$result = $this->db->save_price_settings( $year, $prices );

		if ( false === $result ) {
			add_settings_error(
				'wue_prices',
				'save_error',
				esc_html__( 'Fehler beim Speichern der Preise.', 'wue-nutzerabrechnung' ),
				'error'
			);
		} else {
			add_settings_error(
				'wue_prices',
				'save_success',
				esc_html__( 'Preise wurden erfolgreich gespeichert.', 'wue-nutzerabrechnung' ),
				'success'
			);
		}
	}
}
