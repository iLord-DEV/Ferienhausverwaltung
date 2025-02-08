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
            aufenthalt_id mediumint(9) NOT NULL,
            overlap_start date NOT NULL,
            overlap_end date NOT NULL,
            shared_hours decimal(10,2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY aufenthalt_id (aufenthalt_id)
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

		$sql_weather = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wue_weather_data (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    date date NOT NULL,
    temp_avg decimal(5,2) NOT NULL,
    temp_min decimal(5,2) NOT NULL,
    temp_max decimal(5,2) NOT NULL,
    humidity_avg decimal(5,2),
    precipitation decimal(5,2),
    updated_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY date (date)
) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Tabellen erstellen/aktualisieren
		dbDelta( $sql_preise );
		dbDelta( $sql_aufenthalte );
		dbDelta( $sql_tankfuellungen );
		dbDelta( $sql_overlapping );
		dbDelta( $sql_weather );

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


	public function update_overlap_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Alte Tabelle löschen
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wue_aufenthalte_overlapping" );

		// Neue Tabelle erstellen
		$sql = "CREATE TABLE {$wpdb->prefix}wue_aufenthalte_overlapping (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            aufenthalt_id mediumint(9) NOT NULL,
            overlap_start date NOT NULL,
            overlap_end date NOT NULL,
            shared_hours decimal(10,2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY aufenthalt_id (aufenthalt_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
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

		// Speichere neue Überlappung mit korrekten Spaltennamen
		$result = $wpdb->insert(
			$wpdb->prefix . 'wue_aufenthalte_overlapping',
			array(
				'aufenthalt_id' => $data['aufenthalt_id'],
				'overlap_start' => $data['overlap_start'],
				'overlap_end'   => $data['overlap_end'],
				'shared_hours'  => $data['shared_hours'],
			),
			array( '%d', '%s', '%s', '%f' )
		);

		if ( false === $result ) {
			error_log( 'Failed to save overlap: ' . $wpdb->last_error );
		}

		return $result;
	}



	/**
	 * Holt alle Überlappungen für einen Aufenthalt
	 *
	 * @param int $aufenthalt_id Die ID des Aufenthalts
	 * @return array Array mit Überlappungsdaten
	 */
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
                (SELECT COUNT(DISTINCT a2.mitglied_id)
                FROM {$wpdb->prefix}wue_aufenthalte a2
                WHERE a2.ankunft <= o.overlap_end 
                AND a2.abreise >= o.overlap_start
                AND a2.id != %d) as num_other_users,
                GROUP_CONCAT(DISTINCT u.display_name) as user_names
            FROM {$wpdb->prefix}wue_aufenthalte_overlapping o
            JOIN {$wpdb->prefix}wue_aufenthalte a ON o.aufenthalt_id = a.id
            LEFT JOIN {$wpdb->prefix}wue_aufenthalte a2 ON 
                a2.ankunft <= o.overlap_end AND 
                a2.abreise >= o.overlap_start AND
                a2.id != a.id
            LEFT JOIN {$wpdb->users} u ON a2.mitglied_id = u.ID
            WHERE o.aufenthalt_id = %d
            GROUP BY o.id",
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

	/**
	 * Letzter aufenthalt vor einem bestimmten datum
	 *
	 * @param int $aufenthalt_id Die ID des Aufenthalts
	 * @return bool True bei Erfolg, false bei Fehler
	 */
	public function get_last_stay_before_date( $date ) {
		global $wpdb;

		error_log( 'Looking for last stay before: ' . $date );

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wue_aufenthalte 
            WHERE DATE(abreise) < DATE(%s)
            ORDER BY abreise DESC 
            LIMIT 1",
				$date
			)
		);

		error_log( 'SQL Result: ' . print_r( $result, true ) );
		return $result;
	}
	/**
	 * Holt den frühesten Brennerstand
	 *
	 * @return object|null Brennerstand oder null
	 */
	public function get_earliest_counter_reading() {
		global $wpdb;
		return $wpdb->get_row(
			"
            SELECT brennerstunden_ende 
            FROM {$wpdb->prefix}wue_aufenthalte 
            WHERE abreise < CURRENT_DATE 
            ORDER BY abreise ASC 
            LIMIT 1
        "
		);
	}

	public function get_first_stay_after_date( $date ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wue_aufenthalte 
                WHERE DATE(ankunft) > DATE(%s)
                ORDER BY ankunft ASC 
                LIMIT 1",
				$date
			)
		);
	}

	public function get_all_stays_for_period( $counter_start, $counter_end ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * 
                FROM {$wpdb->prefix}wue_aufenthalte 
                WHERE (
                    brennerstunden_start <= %f AND brennerstunden_ende >= %f
                    OR
                    (brennerstunden_start BETWEEN %f AND %f)
                    OR
                    (brennerstunden_ende BETWEEN %f AND %f)
                )",
				$counter_end,
				$counter_start,
				$counter_start,
				$counter_end,
				$counter_start,
				$counter_end
			)
		);
	}

	/**
	 * Holt erweiterte Statistiken für ein Jahr
	 *
	 * @param int      $year Das Jahr für die Statistiken
	 * @param int|null $user_id Optional. Benutzer ID für eigene Statistiken
	 * @return array Array mit statistischen Daten
	 */
	public function get_yearly_statistics( $year, $user_id = null ) {
		global $wpdb;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Hole alle Aufenthalte des Jahres, sortiert nach Ankunft
		$aufenthalte = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *, 
                    CASE 
                        WHEN has_overlaps = 1 THEN adjusted_hours
                        ELSE (brennerstunden_ende - brennerstunden_start)
                    END as effective_hours
                FROM {$wpdb->prefix}wue_aufenthalte 
                WHERE YEAR(ankunft) = %d 
                ORDER BY ankunft ASC",
				$year
			)
		);

		$stats = array(
			'member_nights' => 0,
			'guest_nights'  => 0,
			'own_hours'     => 0,
			'others_hours'  => 0,
			'absent_hours'  => 0,
		);

		$prev_end     = null;
		$prev_brenner = null;

		foreach ( $aufenthalte as $aufenthalt ) {
			// Normale Statistiken
			$hours                   = floatval( $aufenthalt->effective_hours );
			$stats['member_nights'] += $aufenthalt->anzahl_mitglieder;
			$stats['guest_nights']  += $aufenthalt->anzahl_gaeste;

			// Nutzer-spezifische Stunden
			if ( $aufenthalt->mitglied_id == $user_id ) {
				$stats['own_hours'] += $hours;
			} else {
				$stats['others_hours'] += $hours;
			}

			// Prüfe auf Abwesenheit
			if ( $prev_end && $prev_brenner !== null ) {
				$gap_start = strtotime( $prev_end );
				$gap_end   = strtotime( $aufenthalt->ankunft );

				if ( $gap_end > $gap_start &&
					floatval( $aufenthalt->brennerstunden_start ) > floatval( $prev_brenner ) ) {
					$stats['absent_hours'] +=
						floatval( $aufenthalt->brennerstunden_start ) - floatval( $prev_brenner );
				}
			}

			$prev_end     = $aufenthalt->abreise;
			$prev_brenner = $aufenthalt->brennerstunden_ende;
		}

		// Hole die Preise für das Jahr
		$prices               = $this->get_prices_for_year( $year );
		$verbrauch_pro_stunde = $prices ? $prices->verbrauch_pro_brennerstunde : 0;

		// Berechne die Verbrauchswerte
		$own_consumption    = $stats['own_hours'] * $verbrauch_pro_stunde;
		$others_consumption = $stats['others_hours'] * $verbrauch_pro_stunde;
		$absent_consumption = $stats['absent_hours'] * $verbrauch_pro_stunde;

		return array(
			'member_nights'      => $stats['member_nights'],
			'guest_nights'       => $stats['guest_nights'],
			'oil_consumption'    => $own_consumption + $others_consumption + $absent_consumption,
			'own_consumption'    => $own_consumption,
			'others_consumption' => $others_consumption,
			'absent_consumption' => $absent_consumption,
		);
	}

	/**
	 * Holt den monatlichen Verbrauch für ein Jahr
	 *
	 * @param int $year Das Jahr
	 * @return array Monatliche Verbrauchswerte
	 */
	public function get_monthly_consumption( $year ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                MONTH(ankunft) as month,
                SUM(CASE 
                    WHEN has_overlaps = 1 THEN adjusted_hours
                    ELSE (brennerstunden_ende - brennerstunden_start)
                END) as total_hours
            FROM {$wpdb->prefix}wue_aufenthalte
            WHERE YEAR(ankunft) = %d
            GROUP BY MONTH(ankunft)
            ORDER BY month ASC",
				$year
			)
		);

		// Array mit 12 Monaten initialisieren
		$monthly_data = array_fill( 0, 12, 0 );

		// Daten einfüllen
		foreach ( $results as $row ) {
			$month_index                  = intval( $row->month ) - 1;
			$monthly_data[ $month_index ] = floatval( $row->total_hours );
		}

		return $monthly_data;
	}

	/**
	 * Holt die Verbrauchsdaten gruppiert nach Gruppengröße
	 *
	 * @return array Verbrauchsdaten nach Gruppengröße
	 */
	public function get_consumption_by_group_size() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT 
                (anzahl_mitglieder + anzahl_gaeste) as group_size,
                AVG(CASE 
                    WHEN has_overlaps = 1 THEN adjusted_hours
                    ELSE (brennerstunden_ende - brennerstunden_start)
                END) / (anzahl_mitglieder + anzahl_gaeste) as consumption_per_person
            FROM {$wpdb->prefix}wue_aufenthalte
            WHERE (anzahl_mitglieder + anzahl_gaeste) > 0
            GROUP BY (anzahl_mitglieder + anzahl_gaeste)
            ORDER BY group_size ASC"
		);
	}

	/**
	 * Speichert oder aktualisiert Wetterdaten
	 *
	 * @param array $data Die Wetterdaten
	 * @return bool|int False bei Fehler, Insert ID bei Erfolg
	 */
	public function save_weather_data( $data ) {
		global $wpdb;

		// Prüfe ob bereits Daten für das Datum existieren
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}wue_weather_data WHERE date = %s",
				$data['date']
			)
		);

		if ( $existing ) {
			// Update
			return $wpdb->update(
				$wpdb->prefix . 'wue_weather_data',
				$data,
				array( 'date' => $data['date'] ),
				array( '%s', '%f', '%f', '%f', '%f', '%f' ),
				array( '%s' )
			);
		}

		// Insert
		return $wpdb->insert(
			$wpdb->prefix . 'wue_weather_data',
			$data,
			array( '%s', '%f', '%f', '%f', '%f', '%f' )
		);
	}

	/**
	 * Holt die Verbrauchs-Temperatur-Korrelation
	 *
	 * @param int $year Optional. Jahr für die Analyse
	 * @return array Korrelationsdaten
	 */
	public function get_consumption_temperature_correlation( $year = null ) {
		global $wpdb;

		$year_condition = '';
		if ( $year ) {
			$year_condition = $wpdb->prepare( 'AND YEAR(a.ankunft) = %d', $year );
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                w.temp_avg,
                AVG(CASE 
                    WHEN a.has_overlaps = 1 THEN a.adjusted_hours
                    ELSE (a.brennerstunden_ende - a.brennerstunden_start)
                END) * p.verbrauch_pro_brennerstunde as avg_consumption,
                COUNT(*) as num_stays,
                AVG(a.anzahl_mitglieder + a.anzahl_gaeste) as avg_group_size
            FROM {$wpdb->prefix}wue_aufenthalte a
            JOIN {$wpdb->prefix}wue_weather_data w ON DATE(a.ankunft) = w.date
            JOIN {$wpdb->prefix}wue_preise p ON YEAR(a.ankunft) = p.jahr
            WHERE 1=1 {$year_condition}
            GROUP BY w.temp_avg
            HAVING num_stays >= 3
            ORDER BY w.temp_avg ASC"
			)
		);
	}

	/**
	 * Holt die durchschnittliche Temperatur für einen Zeitraum
	 */
	public function get_average_temperature_for_period( $start_date, $end_date ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(temp_avg)
            FROM {$wpdb->prefix}wue_weather_data
            WHERE date BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);
	}

	/**
	 * Holt die Wetterdaten für einen Zeitraum
	 */
	public function get_weather_data_for_period( $start_date, $end_date ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
            FROM {$wpdb->prefix}wue_weather_data
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC",
				$start_date,
				$end_date
			)
		);
	}
}
