<?php
/**
 * Kernfunktionalität für die Preisverwaltung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hauptklasse für die Preisverwaltung
 */
class WUE_Preise {
	/**
	 * Database instance
	 *
	 * @var WUE_DB
	 */
	private $db;

	/**
	 * Nonce actions für Preise
	 */
	const NONCE_ACTION      = 'wue_preise_action';
	const NONCE_ADD_YEAR    = 'wue_add_year';
	const NONCE_SAVE_PRICES = 'wue_save_prices';

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
			esc_html__( 'Preiskonfiguration', 'wue-nutzerabrechnung' ),
			esc_html__( 'Preiskonfiguration', 'wue-nutzerabrechnung' ),
			'wue_manage_prices',  // Neue Berechtigung statt manage_options
			'wue-nutzerabrechnung-preise',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Verarbeitet Formular-Submissions
	 */
	public function handle_form_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Verarbeite das Hinzufügen eines neuen Jahres
		if ( isset( $_POST['action'] ) && 'add_year' === $_POST['action'] &&
			check_admin_referer( self::NONCE_ADD_YEAR ) ) {
			$this->add_new_year();
		}

		// Verarbeite das Speichern der Preise
		if ( isset( $_POST['wue_save_prices'] ) &&
			check_admin_referer( self::NONCE_SAVE_PRICES ) ) {
			$this->save_price_settings();
		}
	}

	/**
	 * Rendert die Einstellungsseite
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sie haben nicht die erforderlichen Berechtigungen für diese Aktion.', 'wue-nutzerabrechnung' ) );
		}

		// Bestimme das aktuelle Jahr
		$year = isset( $_GET['year'] ) ? intval( $_GET['year'] ) : gmdate( 'Y' );

		// Hole die Preise für das Jahr
		$prices = $this->db->get_prices_for_year( $year );

		// Hole alle verfügbaren Jahre
		$all_possible_years = range( 2024, gmdate( 'Y' ) + 10 );
		$current_years      = $this->db->get_available_price_years();
		$available_years    = array_diff( $all_possible_years, $current_years );

		// Zeige das Template
		require WUE_PLUGIN_PATH . 'templates/price-settings.php';
	}

	/**
	 * Fügt ein neues Jahr hinzu
	 */
	private function add_new_year() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
			self::NONCE_ADD_YEAR
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
					'page'    => 'wue-nutzerabrechnung-preise',
					'year'    => $new_year,
					'message' => 'year_added',
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

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
			self::NONCE_SAVE_PRICES
		) ) {
			wp_die( esc_html__( 'Sicherheitsüberprüfung fehlgeschlagen.', 'wue-nutzerabrechnung' ) );
		}

		$year   = isset( $_POST['wue_year'] ) ? intval( $_POST['wue_year'] ) : gmdate( 'Y' );
		$prices = isset( $_POST['wue_prices'] ) ? array_map( 'floatval', wp_unslash( $_POST['wue_prices'] ) ) : array();

		if ( ! $this->validate_prices( $prices ) ) {
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

	/**
	 * Validiert die Preisdaten
	 *
	 * @param array $prices Die zu validierenden Preise
	 * @return bool
	 */
	private function validate_prices( $prices ) {
		if ( empty( $prices['oelpreis_pro_liter'] ) ||
			empty( $prices['uebernachtung_mitglied'] ) ||
			empty( $prices['uebernachtung_gast'] ) ||
			empty( $prices['verbrauch_pro_brennerstunde'] ) ) {
			add_settings_error(
				'wue_prices',
				'invalid_prices',
				esc_html__( 'Alle Preise müssen größer als 0 sein.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		return true;
	}

	/**
	 * Zeigt Erfolgsmeldungen an
	 */
	private function render_messages() {
		if ( isset( $_GET['message'] ) ) {
			switch ( $_GET['message'] ) {
				case 'year_added':
					add_settings_error(
						'wue_prices',
						'year_added',
						sprintf(
							esc_html__( 'Jahr %d wurde erfolgreich hinzugefügt.', 'wue-nutzerabrechnung' ),
							isset( $_GET['year'] ) ? intval( $_GET['year'] ) : gmdate( 'Y' )
						),
						'success'
					);
					break;
			}
		}
		settings_errors( 'wue_prices' );
	}
}
