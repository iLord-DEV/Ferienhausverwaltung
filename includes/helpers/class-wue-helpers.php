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
			'brennerstunden'       => number_format( $brennerstunden, 1, ',', '.' ) . ' h' .
				( $aufenthalt->has_overlaps ? '*' : '' ),
			'brennerstunden_start' => number_format( floatval( $aufenthalt->brennerstunden_start ), 1, ',', '.' ) . ' h',
			'brennerstunden_ende'  => number_format( floatval( $aufenthalt->brennerstunden_ende ), 1, ',', '.' ) . ' h',
			'oelkosten'            => number_format( $oelkosten, 2, ',', '.' ) . ' €',
			'oelkosten_raw'        => $oelkosten, // Für Summenbildung
			'anzahl_mitglieder'    => intval( $aufenthalt->anzahl_mitglieder ),
			'mitglieder_kosten'    => $mitglieder_kosten,
			'anzahl_gaeste'        => intval( $aufenthalt->anzahl_gaeste ),
			'gaeste_kosten'        => $gaeste_kosten,
			'gesamt'               => number_format( $oelkosten + $mitglieder_kosten + $gaeste_kosten, 2, ',', '.' ) . ' €',
			'edit_url'             => self::get_edit_url( $aufenthalt->id ),
			'has_overlaps'         => ! empty( $aufenthalt->has_overlaps ),

			// Formatierte Werte für die Tabelle
			'datum'                => $ankunft . ' - ' . $abreise,
			'mitglieder'           => sprintf(
				'%d × %s €',
				intval( $aufenthalt->anzahl_mitglieder ),
				number_format( $prices->uebernachtung_mitglied, 2, ',', '.' )
			),
			'gaeste'               => sprintf(
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

		// Sammle alle eindeutigen Zeitpunkte
		$points                                      = array();
		$points[ $aufenthalt->brennerstunden_start ] = array(
			'type'  => 'start',
			'stays' => array( $aufenthalt->id ),
		);
		$points[ $aufenthalt->brennerstunden_ende ]  = array(
			'type'  => 'end',
			'stays' => array( $aufenthalt->id ),
		);

		foreach ( $overlapping_stays as $stay ) {
			if ( ! isset( $points[ $stay->brennerstunden_start ] ) ) {
				$points[ $stay->brennerstunden_start ] = array(
					'type'  => 'start',
					'stays' => array( $stay->id ),
				);
			} else {
				$points[ $stay->brennerstunden_start ]['stays'][] = $stay->id;
			}

			if ( ! isset( $points[ $stay->brennerstunden_ende ] ) ) {
				$points[ $stay->brennerstunden_ende ] = array(
					'type'  => 'end',
					'stays' => array( $stay->id ),
				);
			} else {
				$points[ $stay->brennerstunden_ende ]['stays'][] = $stay->id;
			}
		}

		ksort( $points );

		$adjusted_hours = 0;
		$active_stays   = array();
		$overlaps       = array();
		$last_point     = null;

		$point_values = array_keys( $points );
		for ( $i = 0; $i < count( $point_values ); $i++ ) {
			$current_point = $point_values[ $i ];
			$data          = $points[ $current_point ];

			if ( $last_point !== null && in_array( $aufenthalt->id, $active_stays ) ) {
				$segment_hours = $current_point - $last_point;
				$num_active    = count( $active_stays );

				if ( $num_active > 0 ) {
					$shared_hours    = $segment_hours / $num_active;
					$adjusted_hours += $shared_hours;

					if ( $num_active > 1 ) {
						$overlaps[] = array(
							'aufenthalt_id' => $aufenthalt->id,
							'overlap_start' => $aufenthalt->ankunft,
							'overlap_end'   => $aufenthalt->abreise,
							'shared_hours'  => $shared_hours,
						);
					}
				}
			}

			// Update active stays
			foreach ( $data['stays'] as $stay_id ) {
				if ( $data['type'] === 'start' ) {
					$active_stays[] = $stay_id;
				} else {
					$key = array_search( $stay_id, $active_stays );
					if ( $key !== false ) {
						unset( $active_stays[ $key ] );
						$active_stays = array_values( $active_stays );
					}
				}
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
