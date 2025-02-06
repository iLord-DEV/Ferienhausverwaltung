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
	 * Deletes a stay and recalculates affected stays
	 *
	 * @param int $id The stay ID to delete
	 * @return bool True if the stay was deleted successfully, false otherwise
	 */
	public function delete_aufenthalt( $id ) {
		global $wpdb;

		$aufenthalt = $this->db->get_aufenthalt( $id );
		if ( ! $aufenthalt || ! wue_check_aufenthalt_permission( $aufenthalt ) ) {
			return false;
		}

		// Finde alle betroffenen Aufenthalte vor dem Löschen
		$affected_stays = $this->db->find_overlapping_stays(
			$aufenthalt->ankunft,
			$aufenthalt->abreise,
			$id
		);

		// Lösche Überlappungsdaten
		$wpdb->delete(
			$wpdb->prefix . 'wue_aufenthalte_overlapping',
			array( 'aufenthalt_id' => $id ),
			array( '%d' )
		);

		// Lösche den Aufenthalt
		$result = $wpdb->delete(
			$wpdb->prefix . 'wue_aufenthalte',
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( $result ) {
			// Setze betroffene Aufenthalte zurück
			foreach ( $affected_stays as $stay ) {
				// Prüfe ob noch andere Überlappungen existieren
				$other_overlapping = $this->db->find_overlapping_stays(
					$stay->ankunft,
					$stay->abreise,
					$stay->id
				);

				if ( empty( $other_overlapping ) ) {
					// Keine Überlappungen mehr - zurücksetzen
					$this->db->update_adjusted_hours(
						$stay->id,
						floatval( $stay->brennerstunden_ende ) - floatval( $stay->brennerstunden_start ),
						false
					);

					// Lösche verbleibende Überlappungseinträge
					$wpdb->delete(
						$wpdb->prefix . 'wue_aufenthalte_overlapping',
						array( 'aufenthalt_id' => $stay->id ),
						array( '%d' )
					);
				}
			}
		}

		return $result;
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

		if ( ! $this->validate_counter_readings(
			floatval( $aufenthalt['brennerstunden_start'] ),
			floatval( $aufenthalt['brennerstunden_ende'] ),
			$aufenthalt['ankunft'],
			$aufenthalt['abreise']
		) ) {
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

		// Zählerstand-Validierung hinzufügen
		if ( ! $this->validate_counter_readings(
			floatval( $aufenthalt['brennerstunden_start'] ),
			floatval( $aufenthalt['brennerstunden_ende'] ),
			$aufenthalt['ankunft'],
			$aufenthalt['abreise']
		) ) {
			return false;
		}

		// Überlappungen mit anderen Benutzern finden und neu berechnen
		$overlapping = $this->db->find_overlapping_stays(
			$aufenthalt['ankunft'],
			$aufenthalt['abreise'],
			$aufenthalt_id
		);
		error_log( 'Found overlapping stays: ' . print_r( $overlapping, true ) );

		// Objekt für die Berechnung vorbereiten
		$calc_data = (object) array(
			'id'                   => $aufenthalt_id,
			'ankunft'              => $aufenthalt['ankunft'],
			'abreise'              => $aufenthalt['abreise'],
			'brennerstunden_start' => floatval( $aufenthalt['brennerstunden_start'] ),
			'brennerstunden_ende'  => floatval( $aufenthalt['brennerstunden_ende'] ),
		);

		$calc_result = WUE_Helpers::calculate_shared_hours( $calc_data, $overlapping );
		error_log( 'Calculation result: ' . print_r( $calc_result, true ) );

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
			// Die korrekte ID für den Aufenthalt ermitteln
			$saved_id      = $aufenthalt_id ?: $result;
			$db_data['id'] = $saved_id;

			// Überlappungen neu berechnen
			$overlapping = $this->db->find_overlapping_stays(
				$db_data['ankunft'],
				$db_data['abreise'],
				$saved_id
			);

			if ( ! empty( $overlapping ) ) {
				$calc_result = WUE_Helpers::calculate_shared_hours( (object) $db_data, $overlapping );

				// Überlappungsdaten speichern
				if ( ! empty( $calc_result['overlaps'] ) ) {
					foreach ( $calc_result['overlaps'] as $overlap ) {
						$this->db->save_overlap( $overlap );
					}
				}

				// Betroffene Aufenthalte aktualisieren
				$this->update_affected_stays( $calc_result['overlaps'] );
			}

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
	private function update_affected_stays( $overlaps ) {
		if ( empty( $overlaps ) ) {
			error_log( 'No overlaps to process' );
			return;
		}

		// Sammle ALLE betroffenen Aufenthalts-IDs (aktuelle und überlappende)
		$affected_ids = array();

		// Für jeden Overlap-Zeitraum
		foreach ( $overlaps as $overlap ) {
			$current_stay = $this->db->get_aufenthalt( $overlap['aufenthalt_id'] );
			if ( ! $current_stay ) {
				continue;
			}

			$affected_ids[] = $current_stay->id;

			// Finde alle Aufenthalte, die mit diesem Zeitraum überlappen
			$overlapping_stays = $this->db->find_overlapping_stays(
				$current_stay->ankunft,
				$current_stay->abreise,
				$current_stay->id
			);

			foreach ( $overlapping_stays as $overlap_stay ) {
				$affected_ids[] = $overlap_stay->id;
			}
		}

		// Entferne Duplikate
		$affected_ids = array_unique( $affected_ids );
		error_log( 'All affected stay IDs: ' . print_r( $affected_ids, true ) );

		// Aktualisiere JEDEN betroffenen Aufenthalt
		foreach ( $affected_ids as $stay_id ) {
			error_log( 'Processing stay ID: ' . $stay_id );
			$stay = $this->db->get_aufenthalt( $stay_id );
			if ( ! $stay ) {
				continue;
			}

			$overlapping_stays = $this->db->find_overlapping_stays(
				$stay->ankunft,
				$stay->abreise,
				$stay->id
			);
			error_log( 'Found overlapping stays for ' . $stay_id . ': ' . print_r( $overlapping_stays, true ) );

			$calc_result = WUE_Helpers::calculate_shared_hours( $stay, $overlapping_stays );
			error_log( 'Calculation result for ' . $stay_id . ': ' . print_r( $calc_result, true ) );

			$update_result = $this->db->update_adjusted_hours(
				$stay_id,
				$calc_result['adjusted_hours'],
				! empty( $overlapping_stays )
			);
			error_log( 'Update result for ' . $stay_id . ': ' . ( $update_result ? 'success' : 'failed' ) );

			if ( ! empty( $calc_result['overlaps'] ) ) {
				foreach ( $calc_result['overlaps'] as $new_overlap ) {
					$new_overlap['aufenthalt_id'] = $stay_id;
					$save_result                  = $this->db->save_overlap( $new_overlap );
					error_log( 'Save overlap result for stay ' . $stay_id . ': ' . ( $save_result ? 'success' : 'failed' ) );
				}
			}
		}
	}

	/**
	 * Validiert die Brennerstunden-Einträge unter Berücksichtigung von Überlappungen
	 *
	 * @param float  $new_start Neuer Startzählerstand
	 * @param float  $new_end Neuer Endzählerstand
	 * @param string $new_date_start Startdatum im MySQL-Format (Y-m-d H:i:s)
	 * @param string $new_date_end Enddatum im MySQL-Format (Y-m-d H:i:s)
	 * @return bool True wenn valid, false wenn nicht
	 */
	private function validate_counter_readings( $new_start, $new_end, $new_date_start, $new_date_end ) {
		// Basis-Validierung: Ende muss größer als Start sein
		if ( $new_end <= $new_start ) {
			add_settings_error(
				'wue_aufenthalt',
				'invalid_counter_sequence',
				__( 'Der Endzählerstand muss größer als der Startzählerstand sein.', 'wue-nutzerabrechnung' )
			);
			return false;
		}

		// 1. Prüfe zunächst, ob die neuen Brennerstunden mit existierenden Aufenthalten überlappen
		$all_stays = $this->db->get_all_stays_for_period( $new_start, $new_end );

		foreach ( $all_stays as $stay ) {
			$stay_start = floatval( $stay->brennerstunden_start );
			$stay_end   = floatval( $stay->brennerstunden_ende );

			// Prüfe ob Brennerstunden überlappen
			$counters_overlap = ! ( $new_start > $stay_end || $new_end < $stay_start );

			if ( $counters_overlap ) {
				// Wenn Brennerstunden überlappen, muss auch die Zeit überlappen
				// oder es muss ein nahtloser Übergang am selben Tag sein
				$same_day_transition = (
				substr( $new_date_end, 0, 10 ) === substr( $stay->ankunft, 0, 10 ) ||
				substr( $new_date_start, 0, 10 ) === substr( $stay->abreise, 0, 10 )
				);

				$dates_overlap = ! ( strtotime( $new_date_end ) < strtotime( $stay->ankunft ) ||
							strtotime( $new_date_start ) > strtotime( $stay->abreise ) );

				if ( ! $dates_overlap && ! $same_day_transition ) {
					add_settings_error(
						'wue_aufenthalt',
						'invalid_overlap_logic',
						sprintf(
							__(
								'Die Brennerstunden (%1$.1f h bis %2$.1f h) überlappen mit einem Aufenthalt (%3$.1f h bis %4$.1f h) vom %5$s bis %6$s, aber die Zeiträume überlappen nicht. Überlappende Brennerstunden können nur bei überlappender Anwesenheit oder Übergängen am selben Tag entstehen.',
								'wue-nutzerabrechnung'
							),
							$new_start,
							$new_end,
							$stay_start,
							$stay_end,
							wp_date( 'd.m.Y', strtotime( $stay->ankunft ) ),
							wp_date( 'd.m.Y', strtotime( $stay->abreise ) )
						)
					);
					return false;
				}

				// Prüfe auf unmögliche Sprünge im Zählerstand
				if ( $counters_overlap && abs( $new_start - $stay_end ) > 0.1 && $same_day_transition ) {
					add_settings_error(
						'wue_aufenthalt',
						'invalid_counter_jump',
						sprintf(
							__(
								'Der Zählerstand ändert sich zu stark (%1$.1f h zu %2$.1f h) für einen Übergang am selben Tag.',
								'wue-nutzerabrechnung'
							),
							$stay_end,
							$new_start
						)
					);
					return false;
				}
			}
		}

		// 2. Prüfe zeitlich überlappende Aufenthalte
		$overlapping = $this->db->find_overlapping_stays( $new_date_start, $new_date_end, 0 );

		if ( ! empty( $overlapping ) ) {
			foreach ( $overlapping as $overlap ) {
				$overlap_start = floatval( $overlap->brennerstunden_start );
				$overlap_end   = floatval( $overlap->brennerstunden_ende );

				// Bei Anreise am Abreisetag des anderen muss der Startzählerstand
				// dem Endzählerstand des anderen entsprechen oder überlappen
				$is_departure_day_arrival = substr( $overlap->abreise, 0, 10 ) === substr( $new_date_start, 0, 10 );
				$is_arrival_day_departure = substr( $overlap->ankunft, 0, 10 ) === substr( $new_date_end, 0, 10 );

				if ( $is_departure_day_arrival ) {
					// Startzählerstand muss "ungefähr" gleich Endzählerstand des anderen sein
					if ( abs( $new_start - $overlap_end ) > 0.1 ) {
						add_settings_error(
							'wue_aufenthalt',
							'invalid_counter_connection',
							sprintf(
								__(
									'Bei Anreise am Abreisetag eines anderen Aufenthalts muss der Startzählerstand (%1$.1f h) dem Endzählerstand (%2$.1f h) des anderen entsprechen.',
									'wue-nutzerabrechnung'
								),
								$new_start,
								$overlap_end
							)
						);
						return false;
					}
					continue;
				}

				if ( $is_arrival_day_departure ) {
					// Endzählerstand muss "ungefähr" gleich Startzählerstand des anderen sein
					if ( abs( $new_end - $overlap_start ) > 0.1 ) {
						add_settings_error(
							'wue_aufenthalt',
							'invalid_counter_connection',
							sprintf(
								__(
									'Bei Abreise am Anreisetag eines anderen Aufenthalts muss der Endzählerstand (%1$.1f h) dem Startzählerstand (%2$.1f h) des anderen entsprechen.',
									'wue-nutzerabrechnung'
								),
								$new_end,
								$overlap_start
							)
						);
						return false;
					}
					continue;
				}

				// Bei "echten" zeitlichen Überlappungen müssen die Zählerstände verbunden sein
				if ( $new_start > $overlap_end || $new_end < $overlap_start ) {
					add_settings_error(
						'wue_aufenthalt',
						'invalid_counter_overlap',
						sprintf(
							__(
								'Bei zeitlich überlappenden Aufenthalten müssen die Brennerstunden verbunden sein. Der Bereich %1$.1f h bis %2$.1f h ist nicht verbunden mit dem Bereich %3$.1f h bis %4$.1f h des Aufenthalts von %5$s bis %6$s.',
								'wue-nutzerabrechnung'
							),
							$new_start,
							$new_end,
							$overlap_start,
							$overlap_end,
							wp_date( 'd.m.Y', strtotime( $overlap->ankunft ) ),
							wp_date( 'd.m.Y', strtotime( $overlap->abreise ) )
						)
					);
					return false;
				}
			}
		}

		return true;
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
