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

	// Konstanten für Nonce-Actions
	const NONCE_AUFENTHALT = 'wue_aufenthalt_action';

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

		// Untermenü für Aufenthaltserfassung
		add_submenu_page(
			'wue-nutzerabrechnung',
			esc_html__( 'Aufenthalt erfassen', 'wue-nutzerabrechnung' ),
			esc_html__( 'Aufenthalt erfassen', 'wue-nutzerabrechnung' ),
			'read',
			'wue-aufenthalt-erfassen',
			array( $this, 'display_aufenthalt_form' )
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
	}

	/**
	 * Zeigt die Admin-Hauptseite an
	 */
	public function display_admin_page() {
		// Prüfen auf Erfolgsmeldung
		$message = get_transient( 'wue_aufenthalt_message' );
		if ( 'success' === $message ) {
			delete_transient( 'wue_aufenthalt_message' );
			add_settings_error(
				'wue_aufenthalt',
				'save_success',
				esc_html__( 'Aufenthalt wurde erfolgreich gespeichert.', 'wue-nutzerabrechnung' ),
				'success'
			);
		}

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
	 * Zeigt das Formular zur Aufenthaltserfassung oder -bearbeitung an
	 */
	public function display_aufenthalt_form() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Sie müssen angemeldet sein, um diese Seite aufzurufen.', 'wue-nutzerabrechnung' ) );
		}

		// Initialisiere Variablen
		$aufenthalt    = null;
		$aufenthalt_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		$action        = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		// Wenn ein Aufenthalt bearbeitet werden soll
		if ( 'edit' === $action && $aufenthalt_id > 0 ) {
			// Prüfe Nonce für Edit-Aktion
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_AUFENTHALT ) ) {
				wp_die( esc_html__( 'Sicherheitsüberprüfung fehlgeschlagen.', 'wue-nutzerabrechnung' ) );
			}

			$aufenthalt = $this->db->get_aufenthalt( $aufenthalt_id );

			// Prüfe, ob der Aufenthalt existiert und dem User gehört
			if ( ! $aufenthalt || ! current_user_can( 'edit_aufenthalt' ) ) {
				wp_die( esc_html__( 'Sie haben keine Berechtigung, diesen Aufenthalt zu bearbeiten.', 'wue-nutzerabrechnung' ) );
			}
		}

		// Verarbeite Formularübermittlung
		if ( isset( $_POST['submit'] ) && check_admin_referer( self::NONCE_AUFENTHALT ) ) {
			$this->save_aufenthalt( $aufenthalt_id );
		}

		include WUE_PLUGIN_PATH . 'templates/aufenthalt-form.php';
	}

	/**
	 * Speichert oder aktualisiert einen Aufenthalt
	 *
	 * @param int $aufenthalt_id Optional. Die ID des zu aktualisierenden Aufenthalts.
	 * @return bool|void False bei Fehler, void bei Erfolg (Redirect)
	 */
	private function save_aufenthalt( $aufenthalt_id = 0 ) {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
			self::NONCE_AUFENTHALT
		) ) {
			wp_die( esc_html__( 'Sicherheitsüberprüfung fehlgeschlagen.', 'wue-nutzerabrechnung' ) );
		}

		$aufenthalt = isset( $_POST['wue_aufenthalt'] ) ?
			array_map( 'sanitize_text_field', wp_unslash( $_POST['wue_aufenthalt'] ) ) :
			array();

		// Validierung der Datumsangaben
		$ankunft = sanitize_text_field( $aufenthalt['ankunft'] );
		$abreise = sanitize_text_field( $aufenthalt['abreise'] );

		if ( strtotime( $abreise ) <= strtotime( $ankunft ) ) {
			add_settings_error(
				'wue_aufenthalt',
				'invalid_dates',
				esc_html__( 'Das Abreisedatum muss nach dem Ankunftsdatum liegen.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return;
		}

		// Validierung der Brennerstunden
		$brennerstunden_start = floatval( $aufenthalt['brennerstunden_start'] );
		$brennerstunden_ende  = floatval( $aufenthalt['brennerstunden_ende'] );

		if ( $brennerstunden_ende <= $brennerstunden_start ) {
			add_settings_error(
				'wue_aufenthalt',
				'invalid_brennerstunden',
				esc_html__( 'Die Brennerstunden bei Abreise müssen höher sein als bei Ankunft.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return;
		}

		$data = array(
			'mitglied_id'          => get_current_user_id(),
			'ankunft'              => $ankunft,
			'abreise'              => $abreise,
			'brennerstunden_start' => $brennerstunden_start,
			'brennerstunden_ende'  => $brennerstunden_ende,
			'anzahl_mitglieder'    => intval( $aufenthalt['anzahl_mitglieder'] ),
			'anzahl_gaeste'        => intval( $aufenthalt['anzahl_gaeste'] ),
		);

		$result = $this->db->save_aufenthalt( $data, $aufenthalt_id );

		if ( false === $result ) {
			add_settings_error(
				'wue_aufenthalt',
				'save_error',
				esc_html__( 'Fehler beim Speichern des Aufenthalts.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		set_transient( 'wue_aufenthalt_message', 'success', 30 );
		$redirect_to = admin_url( 'index.php' );
		wp_safe_redirect( $redirect_to );
		exit;
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

		echo '<div class="notice notice-success is-dismissible">';
		echo '<p>' . sprintf(
			esc_html__( 'Jahr %d wurde erfolgreich hinzugefügt.', 'wue-nutzerabrechnung' ),
			esc_html( $new_year )
		) . '</p>';
		echo '</div>';

		echo '<script type="text/javascript">';
		echo 'setTimeout(function() {';
		echo '  window.location.href = "' . esc_url(
			add_query_arg(
				array(
					'page' => 'wue-nutzerabrechnung-preise',
					'year' => $new_year,
				),
				admin_url( 'admin.php' )
			)
		) . '";';
		echo '}, 500);';
		echo '</script>';
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
