<?php
/**
 * Helper-Funktionen für die Nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

class WUE_Helpers {

	/**
	 * Generiert eine Edit-URL für einen Aufenthalt
	 *
	 * @param int $aufenthalt_id ID des Aufenthalts
	 * @return string Die generierte URL
	 */
	public static function get_edit_url( $aufenthalt_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'wue-aufenthalt-erfassen',
					'action' => 'edit',
					'id'     => $aufenthalt_id,
				),
				admin_url( 'admin.php' )
			),
			'wue_aufenthalt_action' // Direkter String statt Konstante
		);
	}
	/**
	 * Berechnet die Summen für alle Aufenthalte
	 *
	 * @param array $aufenthalte Array mit Aufenthaltsdaten
	 * @return array Berechnete Summen
	 */
	public static function calculate_sums( $aufenthalte ) {
		if ( empty( $aufenthalte ) ) {
			return array(
				'brennerstunden' => '0,0',
				'oelkosten'      => '0,00 €',
				'mitglieder'     => '0 Übern. (0,00 €)',
				'gaeste'         => '0 Übern. (0,00 €)',
				'gesamt'         => '0,00 €',
			);
		}

		$total_brennerstunden    = 0;
		$total_oelkosten         = 0;
		$total_mitglieder        = 0;
		$total_mitglieder_kosten = 0;
		$total_gaeste            = 0;
		$total_gaeste_kosten     = 0;

		foreach ( $aufenthalte as $aufenthalt ) {
			$calculated = self::calculate_aufenthalt( $aufenthalt );

			// Nutze die rohen Werte für die Berechnung
			$total_brennerstunden    += ( floatval( $aufenthalt->brennerstunden_ende ) - floatval( $aufenthalt->brennerstunden_start ) );
			$total_oelkosten         += $calculated['mitglieder_kosten'];  // Diese Werte werden nicht formatiert
			$total_mitglieder        += $calculated['anzahl_mitglieder']; // Diese Werte werden nicht formatiert
			$total_mitglieder_kosten += $calculated['mitglieder_kosten'];
			$total_gaeste            += $calculated['anzahl_gaeste'];  // Diese Werte werden nicht formatiert
			$total_gaeste_kosten     += $calculated['gaeste_kosten'];
		}

		return array(
			'brennerstunden' => number_format( $total_brennerstunden, 1, ',', '.' ),
			'oelkosten'      => number_format( $total_oelkosten, 2, ',', '.' ) . ' €',
			'mitglieder'     => sprintf(
				'%d Übern. (%s €)',
				$total_mitglieder,
				number_format( $total_mitglieder_kosten, 2, ',', '.' )
			),
			'gaeste'         => sprintf(
				'%d Übern. (%s €)',
				$total_gaeste,
				number_format( $total_gaeste_kosten, 2, ',', '.' )
			),
			'gesamt'         => number_format(
				$total_oelkosten + $total_mitglieder_kosten + $total_gaeste_kosten,
				2,
				',',
				'.'
			) . ' €',
		);
	}

	/**
	 * Berechnet die Details für einen einzelnen Aufenthalt
	 *
	 * @param object $aufenthalt Aufenthaltsdaten
	 * @return array Berechnete Details
	 */
	public static function calculate_aufenthalt( $aufenthalt ) {
		// Brennerstunden berechnen
		$brennerstunden = floatval( $aufenthalt->brennerstunden_ende ) - floatval( $aufenthalt->brennerstunden_start );

		// Preis für das Jahr holen
		$prices = WUE()->get_db()->get_prices_for_year( date( 'Y', strtotime( $aufenthalt->ankunft ) ) );

		// Ölkosten berechnen
		$oelkosten = $brennerstunden * $prices->verbrauch_pro_brennerstunde * $prices->oelpreis_pro_liter;

		// Übernachtungskosten berechnen
		$mitglieder_kosten = intval( $aufenthalt->anzahl_mitglieder ) * $prices->uebernachtung_mitglied;
		$gaeste_kosten     = intval( $aufenthalt->anzahl_gaeste ) * $prices->uebernachtung_gast;

		// Datum formatieren
		$ankunft = wp_date( 'd.m.Y', strtotime( $aufenthalt->ankunft ) );
		$abreise = wp_date( 'd.m.Y', strtotime( $aufenthalt->abreise ) );

		return array(
			// Original-Werte für Berechnungen
			'brennerstunden'    => number_format( $brennerstunden, 1, ',', '.' ) . ' h',
			'oelkosten'         => number_format( $oelkosten, 2, ',', '.' ) . ' €',
			'anzahl_mitglieder' => intval( $aufenthalt->anzahl_mitglieder ),
			'mitglieder_kosten' => $mitglieder_kosten,
			'anzahl_gaeste'     => intval( $aufenthalt->anzahl_gaeste ),
			'gaeste_kosten'     => $gaeste_kosten,
			'gesamt'            => number_format( $oelkosten + $mitglieder_kosten + $gaeste_kosten, 2, ',', '.' ) . ' €',
			'edit_url'          => self::get_edit_url( $aufenthalt->id ),

			// Formatierte Werte für die Tabelle
			'datum'             => $ankunft . ' - ' . $abreise,
			'mitglieder'        => sprintf(
				'%d × %s €',
				intval( $aufenthalt->anzahl_mitglieder ),
				number_format( $prices->uebernachtung_mitglied, 2, ',', '.' )
			),
			'gaeste'            => sprintf(
				'%d × %s €',
				intval( $aufenthalt->anzahl_gaeste ),
				number_format( $prices->uebernachtung_gast, 2, ',', '.' )
			),
		);
	}
}
