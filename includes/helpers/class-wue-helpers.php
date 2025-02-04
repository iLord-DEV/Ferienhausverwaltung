<?php
/**
 * Helper-Funktionen für die Nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hilfsfunktionen für die Nutzerabrechnung
 */
class WUE_Helpers {

	/**
	 * Generiert eine Edit-URL für einen Aufenthalt
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
			'wue_aufenthalt_action'
		);
	}

	/**
	 * Berechnet die Summen für alle Aufenthalte
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

			// Bei Überlappungen die bereinigten Stunden verwenden
			$brennerstunden = $aufenthalt->has_overlaps ?
				floatval( $aufenthalt->adjusted_hours ) :
				( floatval( $aufenthalt->brennerstunden_ende ) - floatval( $aufenthalt->brennerstunden_start ) );

			$total_brennerstunden    += $brennerstunden;
			$total_mitglieder        += $calculated['anzahl_mitglieder'];
			$total_mitglieder_kosten += $calculated['mitglieder_kosten'];
			$total_gaeste            += $calculated['anzahl_gaeste'];
			$total_gaeste_kosten     += $calculated['gaeste_kosten'];
			$total_oelkosten         += $calculated['oelkosten_raw'];
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
	 */
	public static function calculate_aufenthalt( $aufenthalt ) {
		// Brennerstunden berechnen (nutze adjusted_hours falls vorhanden)
		$brennerstunden = $aufenthalt->has_overlaps ?
			floatval( $aufenthalt->adjusted_hours ) :
			floatval( $aufenthalt->brennerstunden_ende ) - floatval( $aufenthalt->brennerstunden_start );

		// Preis für das Jahr holen
		$prices = WUE()->get_db()->get_prices_for_year( gmdate( 'Y', strtotime( $aufenthalt->ankunft ) ) );

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
			'brennerstunden'    => number_format( $brennerstunden, 1, ',', '.' ) . ' h' .
				( $aufenthalt->has_overlaps ? '*' : '' ),
			'oelkosten'         => number_format( $oelkosten, 2, ',', '.' ) . ' €',
			'oelkosten_raw'     => $oelkosten, // Für Summenbildung
			'anzahl_mitglieder' => intval( $aufenthalt->anzahl_mitglieder ),
			'mitglieder_kosten' => $mitglieder_kosten,
			'anzahl_gaeste'     => intval( $aufenthalt->anzahl_gaeste ),
			'gaeste_kosten'     => $gaeste_kosten,
			'gesamt'            => number_format( $oelkosten + $mitglieder_kosten + $gaeste_kosten, 2, ',', '.' ) . ' €',
			'edit_url'          => self::get_edit_url( $aufenthalt->id ),
			'has_overlaps'      => ! empty( $aufenthalt->has_overlaps ),

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


	/**
	 * Berechnet die geteilten Brennerstunden für überlappende Aufenthalte
	 */
	/**
	 * Berechnet die geteilten Brennerstunden basierend auf den Brennerständen
	 */
	public static function calculate_shared_hours( $aufenthalt, $overlapping_stays ) {
		if ( empty( $overlapping_stays ) ) {
			return array(
				'adjusted_hours' => floatval( $aufenthalt->brennerstunden_ende ) - floatval( $aufenthalt->brennerstunden_start ),
				'overlaps'       => array(),
			);
		}

		// Sammle alle Brennerstände und wer jeweils dabei ist
		$points = array();

		// Füge eigene Stände hinzu
		$points[ floatval( $aufenthalt->brennerstunden_start ) ] = array(
			'type'  => 'start',
			'stays' => array( $aufenthalt->id ),
		);
		$points[ floatval( $aufenthalt->brennerstunden_ende ) ]  = array(
			'type'  => 'end',
			'stays' => array( $aufenthalt->id ),
		);

		// Füge überlappende Stände hinzu
		foreach ( $overlapping_stays as $stay ) {
			$start = floatval( $stay->brennerstunden_start );
			$end   = floatval( $stay->brennerstunden_ende );

			if ( ! isset( $points[ $start ] ) ) {
				$points[ $start ] = array(
					'type'  => 'start',
					'stays' => array( $stay->id ),
				);
			} else {
				$points[ $start ]['stays'][] = $stay->id;
			}

			if ( ! isset( $points[ $end ] ) ) {
				$points[ $end ] = array(
					'type'  => 'end',
					'stays' => array( $stay->id ),
				);
			} else {
				$points[ $end ]['stays'][] = $stay->id;
			}
		}

		// Sortiere nach Brennerständen
		ksort( $points );

		// Berechne geteilte Stunden
		$adjusted_hours = 0;
		$active_stays   = array();
		$overlaps       = array();
		$last_point     = null;

		foreach ( $points as $current_point => $data ) {
			if ( $last_point !== null ) {
				$hours_in_section = $current_point - $last_point;
				$num_users        = count( $active_stays );

				if ( in_array( $aufenthalt->id, $active_stays ) && $num_users > 0 ) {
					$shared_hours    = $hours_in_section / $num_users;
					$adjusted_hours += $shared_hours;

					if ( $num_users > 1 ) {
						foreach ( $active_stays as $active_id ) {
							$overlaps[] = array(
								'aufenthalt_id' => $active_id,
								'overlap_start' => $aufenthalt->ankunft,
								'overlap_end'   => $aufenthalt->abreise,
								'shared_hours'  => $shared_hours,
							);
						}
					}
				}
			}

			// Aktualisiere aktive Aufenthalte
			if ( $data['type'] === 'start' ) {
				$active_stays = array_merge( $active_stays, $data['stays'] );
			} else {
				$active_stays = array_diff( $active_stays, $data['stays'] );
			}

			$last_point = $current_point;
		}

		return array(
			'adjusted_hours' => $adjusted_hours,
			'overlaps'       => $overlaps,
		);
	}


	/**
	 * Berechnet die Brennerstunden für einen Überlappungszeitraum
	 */
	private static function calculate_overlap_hours( $stay1, $stay2, $start_date, $end_date ) {
		// Gesamtzeiträume der Aufenthalte
		$stay1_days = ( strtotime( $stay1->abreise ) - strtotime( $stay1->ankunft ) ) / ( 60 * 60 * 24 );
		$stay2_days = ( strtotime( $stay2->abreise ) - strtotime( $stay2->ankunft ) ) / ( 60 * 60 * 24 );

		// Überlappungszeitraum
		$overlap_days = ( strtotime( $end_date ) - strtotime( $start_date ) ) / ( 60 * 60 * 24 );

		// Durchschnittliche Brennerstunden pro Tag für jeden Aufenthalt
		$stay1_hours_per_day = ( $stay1->brennerstunden_ende - $stay1->brennerstunden_start ) / $stay1_days;
		$stay2_hours_per_day = ( $stay2->brennerstunden_ende - $stay2->brennerstunden_start ) / $stay2_days;

		// Brennerstunden im Überlappungszeitraum (kleinerer Wert)
		return min(
			$stay1_hours_per_day * $overlap_days,
			$stay2_hours_per_day * $overlap_days
		);
	}
}
