<?php
/**
 * Helper-Funktionen für die Nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

class WUE_Helpers {
	/**
	 * Berechnet alle Werte für einen Aufenthalt
	 *
	 * @param object $aufenthalt Aufenthaltsdaten aus der DB
	 * @return array Berechnete und formatierte Werte
	 */
	public static function calculate_aufenthalt( $aufenthalt ) {
		// Basis-Berechnungen
		$brennerstunden = $aufenthalt->brennerstunden_ende - $aufenthalt->brennerstunden_start;
		$oelverbrauch   = $brennerstunden * floatval( $aufenthalt->verbrauch_pro_brennerstunde );
		$oelkosten      = $oelverbrauch * floatval( $aufenthalt->oelpreis_pro_liter );
		$tage           = ( strtotime( $aufenthalt->abreise ) - strtotime( $aufenthalt->ankunft ) ) / ( 60 * 60 * 24 );

		// Übernachtungskosten
		$kosten_mitglieder = $aufenthalt->anzahl_mitglieder * floatval( $aufenthalt->uebernachtung_mitglied ) * $tage;
		$kosten_gaeste     = $aufenthalt->anzahl_gaeste * floatval( $aufenthalt->uebernachtung_gast ) * $tage;

		return array(
			'datum'          => self::format_date_range( $aufenthalt->ankunft, $aufenthalt->abreise ),
			'brennerstunden' => self::format_decimal( $brennerstunden ),
			'oelkosten'      => self::format_currency( $oelkosten ),
			'mitglieder'     => sprintf(
				'%d Übern. (%s)',
				$aufenthalt->anzahl_mitglieder * $tage,
				self::format_currency( $kosten_mitglieder )
			),
			'gaeste'         => sprintf(
				'%d Übern. (%s)',
				$aufenthalt->anzahl_gaeste * $tage,
				self::format_currency( $kosten_gaeste )
			),
			'gesamt'         => self::format_currency( $oelkosten + $kosten_mitglieder + $kosten_gaeste ),
			'edit_url'       => self::get_edit_url( $aufenthalt->id ),
			'raw'            => array(
				'brennerstunden'             => $brennerstunden,
				'oelkosten'                  => $oelkosten,
				'kosten_mitglieder'          => $kosten_mitglieder,
				'kosten_gaeste'              => $kosten_gaeste,
				'uebernachtungen_mitglieder' => $aufenthalt->anzahl_mitglieder * $tage,
				'uebernachtungen_gaeste'     => $aufenthalt->anzahl_gaeste * $tage,
			),
		);
	}

	/**
	 * Berechnet die Summen für eine Liste von Aufenthalten
	 */
	public static function calculate_sums( $aufenthalte ) {
		$sums = array(
			'brennerstunden'             => 0,
			'oelkosten'                  => 0,
			'uebernachtungen_mitglieder' => 0,
			'kosten_mitglieder'          => 0,
			'uebernachtungen_gaeste'     => 0,
			'kosten_gaeste'              => 0,
			'gesamt'                     => 0,
		);

		foreach ( $aufenthalte as $aufenthalt ) {
			$berechnung = self::calculate_aufenthalt( $aufenthalt );
			$raw        = $berechnung['raw'];

			$sums['brennerstunden']             += $raw['brennerstunden'];
			$sums['oelkosten']                  += $raw['oelkosten'];
			$sums['uebernachtungen_mitglieder'] += $raw['uebernachtungen_mitglieder'];
			$sums['kosten_mitglieder']          += $raw['kosten_mitglieder'];
			$sums['uebernachtungen_gaeste']     += $raw['uebernachtungen_gaeste'];
			$sums['kosten_gaeste']              += $raw['kosten_gaeste'];
		}

		$sums['gesamt'] = $sums['oelkosten'] + $sums['kosten_mitglieder'] + $sums['kosten_gaeste'];

		// Formatierte Werte zurückgeben.
		return array(
			'brennerstunden' => self::format_decimal( $sums['brennerstunden'] ),
			'oelkosten'      => self::format_currency( $sums['oelkosten'] ),
			'mitglieder'     => sprintf(
				'%d Übern. (%s)',
				$sums['uebernachtungen_mitglieder'],
				self::format_currency( $sums['kosten_mitglieder'] )
			),
			'gaeste'         => sprintf(
				'%d Übern. (%s)',
				$sums['uebernachtungen_gaeste'],
				self::format_currency( $sums['kosten_gaeste'] )
			),
			'gesamt'         => self::format_currency( $sums['gesamt'] ),
		);
	}

	/**
	 * Formatiert einen Geldbetrag
	 */
	public static function format_currency( $amount ) {
		return number_format( $amount, 2, ',', '.' ) . ' €';
	}

	/**
	 * Formatiert eine Dezimalzahl
	 */
	public static function format_decimal( $number ) {
		return number_format( $number, 1, ',', '.' );
	}

	/**
	 * Formatiert einen Zeitraum
	 */
	public static function format_date_range( $start, $end ) {
		return sprintf(
			'%s - %s',
			date_i18n( 'd.m.Y', strtotime( $start ) ),
			date_i18n( 'd.m.Y', strtotime( $end ) )
		);
	}

	/**
	 * Erstellt die Edit-URL für einen Aufenthalt
	 */
	public static function get_edit_url( $id ) {
		return esc_url(
			add_query_arg(
				array(
					'page'     => 'wue-aufenthalt-erfassen',
					'action'   => 'edit',
					'id'       => $id,
					'_wpnonce' => wp_create_nonce( WUE_Admin::NONCE_AUFENTHALT ),
				),
				admin_url( 'admin.php' )
			)
		);
	}
}
