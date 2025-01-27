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
		$screen = get_current_screen();
		if ( $screen && 'nutzerabrechnung_page_wue-aufenthalt-erfassen' === $screen->id ) {
			wp_enqueue_script(
				'wue-aufenthalte-form',
				WUE_PLUGIN_URL . 'assets/js/admin/aufenthalte.js',
				array( 'jquery' ),
				WUE_VERSION,
				true
			);

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
	 * Verarbeitet Formularübermittlungen
	 */
	public function handle_form_submissions() {
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'save_aufenthalt' ) {
			check_admin_referer( self::NONCE_ACTION );

			$start_date     = sanitize_text_field( $_POST['start_date'] );
			$end_date       = sanitize_text_field( $_POST['end_date'] );
			$brennerstunden = intval( $_POST['brennerstunden'] );

			$this->recalculate_overlapping_hours( $start_date, $end_date, $brennerstunden );

			$this->db->save_entry(
				array(
					'start_date'     => $start_date,
					'end_date'       => $end_date,
					'brennerstunden' => $brennerstunden,
				)
			);
		}
	}

	/**
	 * Berechnet die überlappenden Stunden neu
	 */
	private function recalculate_overlapping_hours( $start_date, $end_date, &$brennerstunden ) {
		$overlaps = $this->db->get_overlapping_entries( $start_date, $end_date );

		if ( ! empty( $overlaps ) ) {
			$shared_hours = $brennerstunden / ( count( $overlaps ) + 1 );

			foreach ( $overlaps as $overlap ) {
				$this->update_entry_shared_hours( $overlap, $shared_hours );
			}

			$brennerstunden -= $shared_hours * count( $overlaps );
		}
	}

	/**
	 * Aktualisiert die gemeinsamen Stunden für einen Aufenthalt
	 */
	private function update_entry_shared_hours( $entry, $shared_hours ) {
		$entry_brennerstunden = $entry['brennerstunden'] - $entry['shared_hours'] + $shared_hours;
		$this->db->update_entry(
			$entry['id'],
			array(
				'shared_hours'   => $shared_hours,
				'brennerstunden' => $entry_brennerstunden,
			)
		);
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
	 */
	private function can_edit_aufenthalt( $aufenthalt ) {
		return current_user_can( 'wue_manage_stays' ) &&
				( current_user_can( 'wue_view_all_stats' ) ||
				(int) $aufenthalt->mitglied_id === get_current_user_id() );
	}

	/**
	 * Validiert die Aufenthaltsdaten und prüft auf Überlappungen
	 */
	private function validate_aufenthalt( $aufenthalt, $aufenthalt_id = 0 ) {
		// Basis-Validierung
		if ( strtotime( $aufenthalt['abreise'] ) <= strtotime( $aufenthalt['ankunft'] ) ) {
			add_settings_error(
				'wue_aufenthalt',
				'invalid_dates',
				esc_html__( 'Das Abreisedatum muss nach dem Ankunftsdatum liegen.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		if ( floatval( $aufenthalt['brennerstunden_ende'] ) <= floatval( $aufenthalt['brennerstunden_start'] ) ) {
			add_settings_error(
				'wue_aufenthalt',
				'invalid_brennerstunden',
				esc_html__( 'Die Brennerstunden bei Abreise müssen höher sein als bei Ankunft.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		// Selbst-Überlappungen prüfen
		$self_overlaps = $this->db->find_self_overlaps(
			$aufenthalt['ankunft'],
			$aufenthalt['abreise'],
			get_current_user_id(),
			$aufenthalt_id
		);

		if ( ! empty( $self_overlaps ) ) {
			add_settings_error(
				'wue_aufenthalt',
				'self_overlap',
				esc_html__( 'Sie haben bereits einen Aufenthalt in diesem Zeitraum eingetragen.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		// Bei Bearbeitung: Prüfen ob es ein bereits bekannter überlappender Aufenthalt ist
		// Bei Bearbeitung: Prüfen ob es ein bereits bekannter überlappender Aufenthalt ist
		if ( $aufenthalt_id > 0 ) {
			$existing = $this->db->get_aufenthalt( $aufenthalt_id );
			if ( $existing && $existing->has_overlaps ) {
				// Alle originalen Aufenthaltsdaten mit den Überlappungsinformationen zurückgeben
				return array_merge(
					$aufenthalt,
					array(
						'adjusted_hours' => $existing->adjusted_hours,
						'has_overlaps'   => true,
						'overlaps'       => array(), // Bestehende Überlappungen bleiben erhalten
					)
				);
			}
		}

		// Überlappungen mit anderen Benutzern prüfen
		$overlapping = $this->db->find_overlapping_stays(
			$aufenthalt['ankunft'],
			$aufenthalt['abreise'],
			$aufenthalt_id
		);

		if ( ! empty( $overlapping ) ) {
			$data = array(
				'id'                   => $aufenthalt_id,
				'ankunft'              => $aufenthalt['ankunft'],
				'abreise'              => $aufenthalt['abreise'],
				'brennerstunden_start' => floatval( $aufenthalt['brennerstunden_start'] ),
				'brennerstunden_ende'  => floatval( $aufenthalt['brennerstunden_ende'] ),
			);

			$calc_result = WUE_Helpers::calculate_shared_hours( (object) $data, $overlapping );

			return array_merge(
				$aufenthalt,
				array(
					'adjusted_hours' => $calc_result['adjusted_hours'],
					'has_overlaps'   => true,
					'overlaps'       => $calc_result['overlaps'],
				)
			);
		}

		return array_merge(
			$aufenthalt,
			array(
				'adjusted_hours' => floatval( $aufenthalt['brennerstunden_ende'] ) - floatval( $aufenthalt['brennerstunden_start'] ),
				'has_overlaps'   => false,
				'overlaps'       => array(),
			)
		);
	}

	/**
	 * Speichert oder aktualisiert einen Aufenthalt
	 */
	private function save_aufenthalt( $aufenthalt_id = 0 ) {
		error_log( '=== SAVE AUFENTHALT STARTED ===' );

		$aufenthalt = isset( $_POST['wue_aufenthalt'] ) ?
		array_map( 'sanitize_text_field', wp_unslash( $_POST['wue_aufenthalt'] ) ) :
		array();

		error_log( 'POST data: ' . print_r( $_POST, true ) );

		// Basis-Validierung durchführen
		if ( strtotime( $aufenthalt['abreise'] ) <= strtotime( $aufenthalt['ankunft'] ) ) {
			add_settings_error(
				'wue_aufenthalt',
				'invalid_dates',
				esc_html__( 'Das Abreisedatum muss nach dem Ankunftsdatum liegen.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		if ( floatval( $aufenthalt['brennerstunden_ende'] ) <= floatval( $aufenthalt['brennerstunden_start'] ) ) {
			add_settings_error(
				'wue_aufenthalt',
				'invalid_brennerstunden',
				esc_html__( 'Die Brennerstunden bei Abreise müssen höher sein als bei Ankunft.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		// Selbst-Überlappungen prüfen
		$self_overlaps = $this->db->find_self_overlaps(
			$aufenthalt['ankunft'],
			$aufenthalt['abreise'],
			get_current_user_id(),
			$aufenthalt_id
		);

		if ( ! empty( $self_overlaps ) ) {
			add_settings_error(
				'wue_aufenthalt',
				'self_overlap',
				esc_html__( 'Sie haben bereits einen Aufenthalt in diesem Zeitraum eingetragen.', 'wue-nutzerabrechnung' ),
				'error'
			);
			return false;
		}

		// Überlappungen mit anderen Benutzern finden und neu berechnen
		$overlapping = $this->db->find_overlapping_stays(
			$aufenthalt['ankunft'],
			$aufenthalt['abreise'],
			$aufenthalt_id
		);

		// Objekt für die Berechnung vorbereiten
		$calc_data = (object) array(
			'id'                   => $aufenthalt_id,
			'ankunft'              => $aufenthalt['ankunft'],
			'abreise'              => $aufenthalt['abreise'],
			'brennerstunden_start' => floatval( $aufenthalt['brennerstunden_start'] ),
			'brennerstunden_ende'  => floatval( $aufenthalt['brennerstunden_ende'] ),
		);

		// Brennerstunden neu berechnen
		$calc_result = WUE_Helpers::calculate_shared_hours( $calc_data, $overlapping );

		// Daten für DB vorbereiten
		$db_data = array(
			'mitglied_id'          => get_current_user_id(),
			'ankunft'              => $aufenthalt['ankunft'],
			'abreise'              => $aufenthalt['abreise'],
			'brennerstunden_start' => floatval( $aufenthalt['brennerstunden_start'] ),
			'brennerstunden_ende'  => floatval( $aufenthalt['brennerstunden_ende'] ),
			'adjusted_hours'       => $calc_result['adjusted_hours'],
			'has_overlaps'         => ! empty( $overlapping ) ? 1 : 0,
			'anzahl_mitglieder'    => intval( $aufenthalt['anzahl_mitglieder'] ),
			'anzahl_gaeste'        => intval( $aufenthalt['anzahl_gaeste'] ),
		);

		error_log( 'Debug WUE_Aufenthalte::save_aufenthalt - Data to save:' );
		error_log( print_r( $db_data, true ) );
		error_log( 'Aufenthalt ID: ' . $aufenthalt_id );

		$result = $this->db->save_aufenthalt( $db_data, $aufenthalt_id );

		error_log( 'Save result: ' . var_export( $result, true ) );

		if ( $result ) {
			// Überlappungsdaten speichern
			if ( ! empty( $calc_result['overlaps'] ) ) {
				foreach ( $calc_result['overlaps'] as $overlap ) {
					$this->db->save_overlap( $overlap );
				}
			}

			// Betroffene Aufenthalte aktualisieren
			$this->update_affected_stays( $calc_result['overlaps'] );
			$this->redirect_after_save();
			return true;
		}

		add_settings_error(
			'wue_aufenthalt',
			'save_error',
			esc_html__( 'Fehler beim Speichern des Aufenthalts.', 'wue-nutzerabrechnung' ),
			'error'
		);
		return false;
	}

	/**
	 * Aktualisiert andere betroffene Aufenthalte
	 */
	/**
	 * Aktualisiert andere betroffene Aufenthalte
	 *
	 * @param array $overlaps Array mit Überlappungsdaten
	 */
	private function update_affected_stays( $overlaps ) {
		if ( empty( $overlaps ) ) {
			return;
		}

		error_log( '=== Start Aktualisierung betroffener Aufenthalte ===' );
		$processed_ids = array();

		// Sammle alle betroffenen Aufenthalts-IDs
		$affected_ids = array();
		foreach ( $overlaps as $overlap ) {
			$affected_ids[] = $overlap['aufenthalt_id_1'];
			$affected_ids[] = $overlap['aufenthalt_id_2'];
		}
		$affected_ids = array_unique( $affected_ids );

		error_log( 'Betroffene Aufenthalte: ' . print_r( $affected_ids, true ) );

		// Aktualisiere jeden betroffenen Aufenthalt
		foreach ( $affected_ids as $stay_id ) {
			if ( in_array( $stay_id, $processed_ids ) ) {
				continue;
			}

			$stay = $this->db->get_aufenthalt( $stay_id );
			if ( ! $stay ) {
				continue;
			}

			error_log( 'Bearbeite Aufenthalt ' . $stay_id );

			// Finde ALLE Überlappungen für diesen Aufenthalt
			$all_overlapping = $this->db->find_overlapping_stays(
				$stay->ankunft,
				$stay->abreise,
				$stay->id
			);

			error_log( 'Gefundene Überlappungen: ' . count( $all_overlapping ) );

			// Neue Berechnung durchführen
			$calc_result = WUE_Helpers::calculate_shared_hours( $stay, $all_overlapping );

			error_log( 'Neue bereinigte Stunden: ' . $calc_result['adjusted_hours'] );

			// Aktualisiere den Aufenthalt
			$this->db->update_adjusted_hours(
				$stay->id,
				$calc_result['adjusted_hours'],
				! empty( $calc_result['overlaps'] )
			);

			// Aktualisiere die Überlappungsdaten
			foreach ( $calc_result['overlaps'] as $new_overlap ) {
				$this->db->save_overlap( $new_overlap );
			}

			$processed_ids[] = $stay->id;
		}

		error_log( '=== Ende Aktualisierung betroffener Aufenthalte ===' );
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
	 */
	private function render_form( $aufenthalt = null ) {
		include WUE_PLUGIN_PATH . 'templates/aufenthalt-form.php';
	}
}
