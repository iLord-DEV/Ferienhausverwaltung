<?php
/**
 * Kernfunktionalität für die Tankfüllungsverwaltung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hauptklasse für die Tankfüllungsverwaltung
 */
class WUE_Tankfuellungen {
	/**
	 * Database instance
	 *
	 * @var WUE_DB
	 */
	private $db;

	/**
	 * Nonce action für Tankfüllungen
	 */
	const NONCE_ACTION = 'wue_tankfuellung_action';

	/**
	 * Konstruktor
	 */
	public function __construct() {
		$this->db = new WUE_DB();
		$this->init_hooks();
	}

	/**
	 * Initialisiert die WordPress Hooks
	 */
	private function init_hooks() {
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
			add_action( 'wue_admin_menu', array( $this, 'add_menu_items' ) );
		}
	}

	/**
	 * Fügt Menüpunkte hinzu
	 */
	public function add_menu_items() {
		add_submenu_page(
			'wue-nutzerabrechnung',
			esc_html__( 'Tankfüllung erfassen', 'wue-nutzerabrechnung' ),
			esc_html__( 'Tankfüllung erfassen', 'wue-nutzerabrechnung' ),
			'read',
			'wue-tankfuellungen',
			array( $this, 'render_form_page' )
		);
	}

	/**
	 * Verarbeitet Formular-Submissions
	 */
	public function handle_form_submissions() {
		if ( ! isset( $_POST['submit'] ) || ! wp_verify_nonce(
			isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '',
			self::NONCE_ACTION
		) ) {
			return;
		}

		$this->save_tankfuellung();
	}

	/**
	 * Rendert die Formular-Seite
	 */
	public function render_form_page() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Sie müssen angemeldet sein, um diese Seite aufzurufen.', 'wue-nutzerabrechnung' ) );
		}

		$this->render_messages();
		$this->render_form();
	}

	/**
	 * Speichert eine neue Tankfüllung
	 */
	private function save_tankfuellung() {
		$tankfuellung = isset( $_POST['wue_tankfuellung'] ) ?
			array_map( 'sanitize_text_field', wp_unslash( $_POST['wue_tankfuellung'] ) ) :
			array();

		if ( ! $this->validate_tankfuellung( $tankfuellung ) ) {
			return;
		}

		$data   = $this->prepare_tankfuellung_data( $tankfuellung );
		$result = $this->db->save_tankfuellung( $data );

		if ( false === $result ) {
			add_settings_error(
				'wue_tankfuellung',
				'save_error',
				esc_html__( 'Fehler beim Speichern der Tankfüllung.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return;
		}

		$this->redirect_after_save();
	}

	/**
	 * Validiert die Tankfüllungsdaten
	 *
	 * @param array $tankfuellung Die zu validierenden Daten
	 * @return bool
	 */
	private function validate_tankfuellung( $tankfuellung ) {
		if ( empty( $tankfuellung['datum'] ) ) {
			add_settings_error(
				'wue_tankfuellung',
				'invalid_date',
				esc_html__( 'Bitte geben Sie ein Datum an.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		if ( floatval( $tankfuellung['liter'] ) <= 0 || floatval( $tankfuellung['preis_pro_liter'] ) <= 0 ) {
			add_settings_error(
				'wue_tankfuellung',
				'invalid_values',
				esc_html__( 'Bitte geben Sie gültige Werte für Liter und Preis ein.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		return true;
	}

	/**
	 * Bereitet die Tankfüllungsdaten für das Speichern vor
	 *
	 * @param array $tankfuellung Die Rohdaten aus dem Formular
	 * @return array
	 */
	private function prepare_tankfuellung_data( $tankfuellung ) {
		return array(
			'datum'                => sanitize_text_field( $tankfuellung['datum'] ),
			'liter'                => floatval( $tankfuellung['liter'] ),
			'preis_pro_liter'      => floatval( $tankfuellung['preis_pro_liter'] ),
			'brennerstunden_stand' => floatval( $tankfuellung['brennerstunden_stand'] ),
		);
	}

	/**
	 * Leitet nach erfolgreichem Speichern weiter
	 */
	private function redirect_after_save() {
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

	/**
	 * Zeigt Erfolgsmeldungen an
	 */
	private function render_messages() {
		if ( isset( $_GET['message'] ) && 'success' === $_GET['message'] ) {
			add_settings_error(
				'wue_tankfuellung',
				'save_success',
				esc_html__( 'Tankfüllung wurde erfolgreich gespeichert.', 'wue-nutzerabrechnung' ),
				'success'
			);
		}
		settings_errors( 'wue_tankfuellung' );
	}

	/**
	 * Rendert das Tankfüllungsformular
	 */
	private function render_form() {
		include WUE_PLUGIN_PATH . 'templates/tankfuellung-form.php';
	}
}
