<?php
/**
 * Kernfunktionalität für die Aufenthaltsverwaltung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hauptklasse für die Aufenthaltsverwaltung
 */
class WUE_Aufenthalte {
	/**
	 * Database instance
	 *
	 * @var WUE_DB
	 */
	private $db;

	/**
	 * Nonce action für Aufenthalte
	 */
	const NONCE_ACTION = 'wue_aufenthalt_action';

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
			add_filter( 'wue_dashboard_data', array( $this, 'add_dashboard_data' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
	}

	/**
	 * Fügt Menüpunkte hinzu
	 */
	public function add_menu_items() {
		add_submenu_page(
			'wue-nutzerabrechnung',
			esc_html__( 'Aufenthalt erfassen', 'wue-nutzerabrechnung' ),
			esc_html__( 'Aufenthalt erfassen', 'wue-nutzerabrechnung' ),
			'read',
			'wue-aufenthalt-erfassen',
			array( $this, 'render_form_page' )
		);
	}

	/**
	 * Registriert und lädt Assets
	 */
	public function enqueue_scripts() {
		// Nur auf der Aufenthalts-Formular-Seite laden
		$screen = get_current_screen();
		if ( $screen && 'nutzerabrechnung_page_wue-aufenthalt-erfassen' === $screen->id ) {
			wp_enqueue_script(
				'wue-aufenthalte-form',
				WUE_PLUGIN_URL . 'assets/js/admin/aufenthalte.js',
				array( 'jquery' ),
				WUE_VERSION,
				true
			);

			// Lokalisierung für JavaScript
			wp_localize_script(
				'wue-aufenthalte-form',
				'wueAufenthalte',
				array(
					'i18n' => array(
						'brennerstundenError' => __( 'Die Brennerstunden bei Abreise müssen höher sein als bei Ankunft.', 'wue-nutzerabrechnung' ),
					),
				)
			);
		}
	}

	/**
	 * Fügt Aufenthaltsdaten zum Dashboard hinzu
	 *
	 * @param array $data Bestehende Dashboard-Daten
	 * @return array Erweiterte Dashboard-Daten
	 */
	public function add_dashboard_data( $data ) {
		if ( ! is_user_logged_in() ) {
			return $data;
		}

		$user_id = get_current_user_id();
		$year    = isset( $_GET['wue_year'] ) ? intval( $_GET['wue_year'] ) : gmdate( 'Y' );

		$data['aufenthalte'] = $this->db->get_user_aufenthalte( $user_id, $year );

		return $data;
	}

	/**
	 * Verarbeitet Formular-Submissions
	 */
	public function handle_form_submissions() {
		if ( ! isset( $_POST['wue_aufenthalt_submit'] ) ) {
			return;
		}

		if ( ! check_admin_referer( self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Sicherheitsüberprüfung fehlgeschlagen.', 'wue-nutzerabrechnung' ) );
		}

		$aufenthalt_id = isset( $_POST['aufenthalt_id'] ) ? intval( $_POST['aufenthalt_id'] ) : 0;
		$this->save_aufenthalt( $aufenthalt_id );
	}

	/**
	 * Rendert die Formular-Seite
	 */
	public function render_form_page() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Sie müssen angemeldet sein, um diese Seite aufzurufen.', 'wue-nutzerabrechnung' ) );
		}

		$aufenthalt    = null;
		$aufenthalt_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		$action        = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'edit' === $action && $aufenthalt_id > 0 ) {
			if ( ! wp_verify_nonce(
				isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '',
				self::NONCE_ACTION
			) ) {
				wp_die( esc_html__( 'Sicherheitsüberprüfung fehlgeschlagen.', 'wue-nutzerabrechnung' ) );
			}

			$aufenthalt = $this->get_aufenthalt( $aufenthalt_id );
		}

		$this->render_messages();
		$this->render_form( $aufenthalt );
	}

	/**
	 * Holt einen einzelnen Aufenthalt
	 *
	 * @param int $aufenthalt_id Die ID des Aufenthalts
	 * @return object|null Aufenthaltsdaten oder null
	 */
	private function get_aufenthalt( $aufenthalt_id ) {
		$aufenthalt = $this->db->get_aufenthalt( $aufenthalt_id );

		if ( ! $aufenthalt || ! $this->can_edit_aufenthalt( $aufenthalt ) ) {
			wp_die( esc_html__( 'Sie haben keine Berechtigung, diesen Aufenthalt zu bearbeiten.', 'wue-nutzerabrechnung' ) );
		}

		return $aufenthalt;
	}

	/**
	 * Prüft ob der aktuelle Benutzer einen Aufenthalt bearbeiten darf
	 *
	 * @param object $aufenthalt Aufenthaltsdaten
	 * @return bool
	 */
	private function can_edit_aufenthalt( $aufenthalt ) {
		return current_user_can( 'wue_manage_stays' ) &&
				( current_user_can( 'wue_view_all_stats' ) ||
				(int) $aufenthalt->mitglied_id === get_current_user_id() );
	}

	/**
	 * Speichert oder aktualisiert einen Aufenthalt
	 *
	 * @param int $aufenthalt_id Optional. Die ID des zu aktualisierenden Aufenthalts
	 * @return bool|void False bei Fehler, void bei Erfolg (Redirect)
	 */
	private function save_aufenthalt( $aufenthalt_id = 0 ) {
		$aufenthalt = isset( $_POST['wue_aufenthalt'] ) ?
			array_map( 'sanitize_text_field', wp_unslash( $_POST['wue_aufenthalt'] ) ) :
			array();

		if ( ! $this->validate_aufenthalt( $aufenthalt ) ) {
			return false;
		}

		$data   = $this->prepare_aufenthalt_data( $aufenthalt );
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

		$this->redirect_after_save();
	}

	/**
	 * Validiert die Aufenthaltsdaten
	 *
	 * @param array $aufenthalt Die zu validierenden Daten
	 * @return bool
	 */
	private function validate_aufenthalt( $aufenthalt ) {
		// Validierung der Datumsangaben
		if ( strtotime( $aufenthalt['abreise'] ) <= strtotime( $aufenthalt['ankunft'] ) ) {
			add_settings_error(
				'wue_aufenthalt',
				'invalid_dates',
				esc_html__( 'Das Abreisedatum muss nach dem Ankunftsdatum liegen.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		// Validierung der Brennerstunden
		if ( floatval( $aufenthalt['brennerstunden_ende'] ) <= floatval( $aufenthalt['brennerstunden_start'] ) ) {
			add_settings_error(
				'wue_aufenthalt',
				'invalid_brennerstunden',
				esc_html__( 'Die Brennerstunden bei Abreise müssen höher sein als bei Ankunft.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		return true;
	}

	/**
	 * Bereitet die Aufenthaltsdaten für das Speichern vor
	 *
	 * @param array $aufenthalt Die Rohdaten aus dem Formular
	 * @return array
	 */
	private function prepare_aufenthalt_data( $aufenthalt ) {
		return array(
			'mitglied_id'          => get_current_user_id(),
			'ankunft'              => $aufenthalt['ankunft'],
			'abreise'              => $aufenthalt['abreise'],
			'brennerstunden_start' => floatval( $aufenthalt['brennerstunden_start'] ),
			'brennerstunden_ende'  => floatval( $aufenthalt['brennerstunden_ende'] ),
			'anzahl_mitglieder'    => intval( $aufenthalt['anzahl_mitglieder'] ),
			'anzahl_gaeste'        => intval( $aufenthalt['anzahl_gaeste'] ),
		);
	}

	/**
	 * Leitet nach erfolgreichem Speichern weiter
	 */
	private function redirect_after_save() {
		set_transient( 'wue_aufenthalt_message', 'success', 30 );
		wp_safe_redirect( admin_url( 'index.php' ) );
		exit;
	}

	/**
	 * Zeigt Erfolgsmeldungen an
	 */
	private function render_messages() {
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
		settings_errors( 'wue_aufenthalt' );
	}

	/**
	 * Rendert das Aufenthaltsformular
	 *
	 * @param object|null $aufenthalt Optional. Aufenthaltsdaten für die Bearbeitung
	 */
	private function render_form( $aufenthalt = null ) {
		include WUE_PLUGIN_PATH . 'templates/aufenthalt-form.php';
	}
}
