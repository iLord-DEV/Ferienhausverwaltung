<?php
/**
 * Datenbank-Funktionalitäten für die Nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * Datenbank-Klasse für die Nutzerabrechnung
 */
class WUE_DB {

	/**
	 * Holt die Aufenthalte eines Benutzers für ein bestimmtes Jahr
	 *
	 * @param int $user_id Benutzer ID.
	 * @param int $year Jahr.
	 * @return array Array mit Aufenthaltsdaten
	 */
	public function get_user_aufenthalte( $user_id, $year ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                a.*, 
                TIMESTAMPDIFF(DAY, a.ankunft, a.abreise) as tage,
                CASE 
                    WHEN a.has_overlaps = 1 THEN a.adjusted_hours
                    ELSE (a.brennerstunden_ende - a.brennerstunden_start)
                END as brennerstunden,
                p.oelpreis_pro_liter,
                p.uebernachtung_mitglied,
                p.uebernachtung_gast,
                p.verbrauch_pro_brennerstunde
                FROM {$wpdb->prefix}wue_aufenthalte a
                LEFT JOIN {$wpdb->prefix}wue_preise p ON YEAR(a.ankunft) = p.jahr
                WHERE a.mitglied_id = %d 
                AND YEAR(a.ankunft) = %d
                ORDER BY a.ankunft DESC",
				$user_id,
				$year
			)
		);
	}

	/**
	 * Holt die verfügbaren Jahre für einen Benutzer
	 *
	 * @param int $user_id Die ID des Benutzers
	 * @return array Array mit verfügbaren Jahren
	 */
	public function get_available_years( $user_id ) {
		global $wpdb;

		$years = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT YEAR(ankunft) as jahr 
            FROM {$wpdb->prefix}wue_aufenthalte 
            WHERE mitglied_id = %d 
            ORDER BY jahr DESC",
				$user_id
			)
		);

		if ( empty( $years ) ) {
			$years[] = gmdate( 'Y' );
		}

		return $years;
	}

	/**
	 * Holt alle verfügbaren Jahre für die Preiskonfiguration
	 *
	 * @return array Array mit Jahren
	 */
	public function get_available_price_years() {
		global $wpdb;
		return $wpdb->get_col(
			"SELECT DISTINCT jahr 
            FROM {$wpdb->prefix}wue_preise 
            ORDER BY jahr DESC"
		);
	}



	/**
	 * Erstellt die erforderlichen Datenbanktabellen
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Preise-Tabelle
		$sql_preise = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wue_preise (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        jahr int NOT NULL,
        oelpreis_pro_liter decimal(10,2) NOT NULL,
        uebernachtung_mitglied decimal(10,2) NOT NULL,
        uebernachtung_gast decimal(10,2) NOT NULL,
        verbrauch_pro_brennerstunde decimal(10,2) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY jahr (jahr)
    ) $charset_collate;";

		// Aufenthalte-Tabelle
		$sql_aufenthalte = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wue_aufenthalte (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        mitglied_id bigint(20) NOT NULL,
        ankunft date NOT NULL,
        abreise date NOT NULL,
        brennerstunden_start decimal(10,2) NOT NULL,
        brennerstunden_ende decimal(10,2) NOT NULL,
        adjusted_hours decimal(10,2) DEFAULT NULL,
        has_overlaps tinyint(1) DEFAULT 0,
        anzahl_mitglieder int NOT NULL,
        anzahl_gaeste int NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY mitglied_id (mitglied_id),
        KEY ankunft (ankunft)
    ) $charset_collate;";

		// Überlappungs-Tabelle
		$sql_overlapping = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wue_aufenthalte_overlapping (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        aufenthalt_id_1 mediumint(9) NOT NULL,
        aufenthalt_id_2 mediumint(9) NOT NULL,
        overlap_start date NOT NULL,
        overlap_end date NOT NULL,
        shared_hours decimal(10,2) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY aufenthalt_id_1 (aufenthalt_id_1),
        KEY aufenthalt_id_2 (aufenthalt_id_2),
        UNIQUE KEY unique_overlap (aufenthalt_id_1, aufenthalt_id_2)
    ) $charset_collate;";

		// Tankfüllungen-Tabelle
		$sql_tankfuellungen = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wue_tankfuellungen (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        datum date NOT NULL,
        liter decimal(10,2) NOT NULL,
        preis_pro_liter decimal(10,2) NOT NULL,
        brennerstunden_stand decimal(10,2) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY datum (datum)
    ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Tabellen erstellen/aktualisieren
		dbDelta( $sql_preise );
		dbDelta( $sql_aufenthalte );
		dbDelta( $sql_tankfuellungen );
		dbDelta( $sql_overlapping );

		// Überprüfen ob die neuen Spalten in der Aufenthalte-Tabelle existieren
		$aufenthalte_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}wue_aufenthalte" );
		$column_names        = array_column( $aufenthalte_columns, 'Field' );

		// Wenn nötig, neue Spalten hinzufügen
		if ( ! in_array( 'adjusted_hours', $column_names ) || ! in_array( 'has_overlaps', $column_names ) ) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}wue_aufenthalte 
            ADD COLUMN IF NOT EXISTS adjusted_hours decimal(10,2) DEFAULT NULL AFTER brennerstunden_ende,
            ADD COLUMN IF NOT EXISTS has_overlaps tinyint(1) DEFAULT 0 AFTER adjusted_hours"
			);
		}
	}

	/**
	 * Fügt Standardpreise für ein Jahr ein
	 *
	 * @param int $year Das Jahr für die Standardpreise.
	 * @return bool True bei Erfolg, false bei Fehler.
	 */
	public function insert_default_prices( $year ) {
		global $wpdb;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
            FROM {$wpdb->prefix}wue_preise 
            WHERE jahr = %d",
				$year
			)
		);

		if ( ! $exists ) {
			return $wpdb->insert(
				$wpdb->prefix . 'wue_preise',
				array(
					'jahr'                        => $year,
					'oelpreis_pro_liter'          => 1.00,
					'uebernachtung_mitglied'      => 10.00,
					'uebernachtung_gast'          => 15.00,
					'verbrauch_pro_brennerstunde' => 2.50,
				),
				array( '%d', '%f', '%f', '%f', '%f' )
			);
		}

		return false;
	}

	/**
	 * Holt einen einzelnen Aufenthalt
	 *
	 * @param int $id Die ID des Aufenthalts
	 * @return object|null Aufenthaltsdaten oder null
	 */
	public function get_aufenthalt( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * 
            FROM {$wpdb->prefix}wue_aufenthalte 
            WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Holt Statistiken für ein Jahr
	 *
	 * @param int $year Das Jahr für die Statistiken
	 * @return array Array mit statistischen Daten
	 */
	public function get_yearly_statistics( $year ) {
		global $wpdb;

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                SUM(brennerstunden_ende - brennerstunden_start) as total_brennerstunden,
                SUM(anzahl_mitglieder) as member_nights,
                SUM(anzahl_gaeste) as guest_nights
            FROM {$wpdb->prefix}wue_aufenthalte 
            WHERE YEAR(ankunft) = %d",
				$year
			),
			ARRAY_A
		);

		if ( ! $stats ) {
			return array(
				'total_brennerstunden' => 0,
				'member_nights'        => 0,
				'guest_nights'         => 0,
				'oil_consumption'      => 0,
			);
		}

		$prices                   = $this->get_prices_for_year( $year );
		$stats['oil_consumption'] = $stats['total_brennerstunden'] *
			( $prices ? $prices->verbrauch_pro_brennerstunde : 0 );

		return $stats;
	}

	/**
	 * Holt die Preise für ein bestimmtes Jahr
	 *
	 * @param int $year Das Jahr für die Preise
	 * @return object Preisobjekt mit Standardwerten wenn nicht gefunden
	 */
	public function get_prices_for_year( $year ) {
		global $wpdb;

		$prices = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * 
            FROM {$wpdb->prefix}wue_preise 
            WHERE jahr = %d",
				$year
			)
		);

		if ( ! $prices ) {
			$prices = (object) array(
				'jahr'                        => $year,
				'oelpreis_pro_liter'          => 1.00,
				'uebernachtung_mitglied'      => 10.00,
				'uebernachtung_gast'          => 15.00,
				'verbrauch_pro_brennerstunde' => 2.50,
			);
		}

		return $prices;
	}

	/**
	 * Speichert oder aktualisiert einen Aufenthalt
	 *
	 * @param array $data Die zu speichernden Daten
	 * @param int   $aufenthalt_id Optional. Die ID des zu aktualisierenden Aufenthalts
	 * @return bool|int False bei Fehler, bei Update: true bei Erfolg, bei Insert: ID
	 */
	public function save_aufenthalt( $data, $aufenthalt_id = 0 ) {
		global $wpdb;

		// Debug-Output
		// error_log( 'Debug WUE_DB::save_aufenthalt - Received data:' );
		// error_log( print_r( $data, true ) );
		// error_log( 'Aufenthalt ID: ' . $aufenthalt_id );

		if ( $aufenthalt_id > 0 ) {
			$result = $wpdb->update(
				$wpdb->prefix . 'wue_aufenthalte',
				$data,
				array( 'id' => $aufenthalt_id ),
				array( '%d', '%s', '%s', '%f', '%f', '%f', '%d', '%d', '%d' ),
				array( '%d' )
			);
			// error_log( 'Update result: ' . var_export( $result, true ) );
			return $result;
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'wue_aufenthalte',
			$data,
			array( '%d', '%s', '%s', '%f', '%f', '%f', '%d', '%d', '%d' )
		);

		// error_log( 'Insert result: ' . var_export( $result, true ) );
		// error_log( 'Last error: ' . $wpdb->last_error );

		return $result ? $wpdb->insert_id : false;
	}
	/**
	 * Speichert eine Tankfüllung
	 *
	 * @param array $data Die zu speichernden Daten
	 * @return bool|int False bei Fehler, Insert ID bei Erfolg
	 */
	public function save_tankfuellung( $data ) {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'wue_tankfuellungen',
			$data,
			array( '%s', '%f', '%f', '%f' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Speichert Preiseinstellungen
	 *
	 * @param int   $year Das Jahr für die Preise
	 * @param array $prices Die zu speichernden Preisdaten
	 * @return bool|int False bei Fehler, bei Update: affected rows, bei Insert: ID
	 */
	public function save_price_settings( $year, $prices ) {
		global $wpdb;

		$existing = $this->get_prices_for_year( $year );

		if ( $existing && isset( $existing->jahr ) ) {
			return $wpdb->update(
				$wpdb->prefix . 'wue_preise',
				$prices,
				array( 'jahr' => $year ),
				array( '%f', '%f', '%f', '%f' ),
				array( '%d' )
			);
		}

		$prices['jahr'] = $year;
		$result         = $wpdb->insert(
			$wpdb->prefix . 'wue_preise',
			$prices,
			array( '%d', '%f', '%f', '%f', '%f' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	public function find_overlapping_stays( $start_date, $end_date, $exclude_id = 0 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wue_aufenthalte 
            WHERE (ankunft <= %s AND abreise >= %s)
            AND id != %d
            ORDER BY ankunft ASC",
				$end_date,
				$start_date,
				$exclude_id
			)
		);
	}

	/***
	 * Speichert die Überlappungsinformation
	 */
	public function save_overlap( $data ) {
		global $wpdb;

		// Lösche alte Überlappungen für diesen Aufenthalt
		$wpdb->delete(
			$wpdb->prefix . 'wue_aufenthalte_overlapping',
			array( 'aufenthalt_id' => $data['aufenthalt_id'] ),
			array( '%d' )
		);

		// Speichere neue Überlappung
		return $wpdb->insert(
			$wpdb->prefix . 'wue_aufenthalte_overlapping',
			array(
				'aufenthalt_id' => $data['aufenthalt_id'],
				'brenner_start' => $data['brenner_start'],
				'brenner_end'   => $data['brenner_end'],
				'num_users'     => $data['num_users'],
				'shared_hours'  => $data['shared_hours'],
			),
			array( '%d', '%f', '%f', '%d', '%f' )
		);
	}

	/**
	 * Holt alle Überlappungen für einen Aufenthalt
	 *
	 * @param int $aufenthalt_id Die ID des Aufenthalts
	 * @return array Array mit Überlappungsdaten
	 */
	public function get_overlaps_for_stay( $aufenthalt_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.*, 
                a1.mitglied_id as mitglied_id_1,
                a2.mitglied_id as mitglied_id_2,
                u1.display_name as mitglied_name_1,
                u2.display_name as mitglied_name_2
        FROM {$wpdb->prefix}wue_aufenthalte_overlapping o
        LEFT JOIN {$wpdb->prefix}wue_aufenthalte a1 ON o.aufenthalt_id_1 = a1.id
        LEFT JOIN {$wpdb->prefix}wue_aufenthalte a2 ON o.aufenthalt_id_2 = a2.id
        LEFT JOIN {$wpdb->users} u1 ON a1.mitglied_id = u1.ID
        LEFT JOIN {$wpdb->users} u2 ON a2.mitglied_id = u2.ID
        WHERE aufenthalt_id_1 = %d OR aufenthalt_id_2 = %d",
				$aufenthalt_id,
				$aufenthalt_id
			)
		);
	}

	public function find_self_overlaps( $start_date, $end_date, $user_id, $exclude_id = 0 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * 
                FROM {$wpdb->prefix}wue_aufenthalte 
                WHERE (ankunft <= %s AND abreise >= %s)
                AND id != %d
                AND mitglied_id = %d
                ORDER BY ankunft ASC",
				$end_date,
				$start_date,
				$exclude_id,
				$user_id
			)
		);
	}

	/**
	 * Aktualisiert die bereinigten Brennerstunden eines Aufenthalts
	 *
	 * @param int   $aufenthalt_id Die ID des Aufenthalts
	 * @param float $adjusted_hours Die bereinigten Stunden
	 * @param bool  $has_overlaps  Hat der Aufenthalt Überlappungen?
	 * @return bool True bei Erfolg, false bei Fehler
	 */
	public function update_adjusted_hours( $aufenthalt_id, $adjusted_hours, $has_overlaps = true ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'wue_aufenthalte',
			array(
				'adjusted_hours' => $adjusted_hours,
				'has_overlaps'   => $has_overlaps ? 1 : 0,
			),
			array( 'id' => $aufenthalt_id ),
			array( '%f', '%d' ),
			array( '%d' )
		);
	}
}
