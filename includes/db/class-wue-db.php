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

		$query = $wpdb->prepare(
			"SELECT a.*, 
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
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Holt die verfügbaren Jahre für einen Benutzer
	 *
	 * @param int $user_id Die ID des Benutzers
	 * @return array Array mit verfügbaren Jahren
	 */
	public function get_available_years( $user_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT DISTINCT YEAR(ankunft) as jahr 
            FROM {$wpdb->prefix}wue_aufenthalte 
            WHERE mitglied_id = %d 
            ORDER BY jahr DESC",
			$user_id
		);

		$years = $wpdb->get_col( $query );

		if ( empty( $years ) ) {
			$years[] = date( 'Y' );
		}

		return $years;
	}

	/**
	 * Erstellt die erforderlichen Datenbanktabellen
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
				"SELECT COUNT(*) FROM {$wpdb->prefix}wue_preise WHERE jahr = %d",
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
}
