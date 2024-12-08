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
                (a.brennerstunden_ende - a.brennerstunden_start) as brennerstunden,
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
	 * Erstellt die erforderlichen Datenbanktabellen
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

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

		$sql_aufenthalte = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wue_aufenthalte (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            mitglied_id bigint(20) NOT NULL,
            ankunft date NOT NULL,
            abreise date NOT NULL,
            brennerstunden_start decimal(10,2) NOT NULL,
            brennerstunden_ende decimal(10,2) NOT NULL,
            anzahl_mitglieder int NOT NULL,
            anzahl_gaeste int NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mitglied_id (mitglied_id),
            KEY ankunft (ankunft)
        ) $charset_collate;";

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
		dbDelta( $sql_preise );
		dbDelta( $sql_aufenthalte );
		dbDelta( $sql_tankfuellungen );
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

		if ( $aufenthalt_id > 0 ) {
			return $wpdb->update(
				$wpdb->prefix . 'wue_aufenthalte',
				$data,
				array( 'id' => $aufenthalt_id ),
				array( '%d', '%s', '%s', '%f', '%f', '%d', '%d' ),
				array( '%d' )
			);
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'wue_aufenthalte',
			$data,
			array( '%d', '%s', '%s', '%f', '%f', '%d', '%d' )
		);

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
}
