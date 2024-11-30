<?php
/**
 * Dashboard-Funktionalitäten für die Nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard-Klasse für die Nutzerabrechnung
 */
class WUE_Dashboard {

    /**
     * Konstruktor
     */
    public function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
    }

    /**
     * Registriert das Dashboard Widget
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'wue_nutzerabrechnung_widget',
            __( 'Meine Aufenthalte und Kosten', 'wue-nutzerabrechnung' ),
            array( $this, 'render_dashboard_widget' )
        );
    }

    /**
     * Rendert das Dashboard Widget
     */
    public function render_dashboard_widget() {
        $year = isset( $_GET['wue_year'] ) ? intval( $_GET['wue_year'] ) : date('Y');
        $user_id = get_current_user_id();

        $aufenthalte = $this->get_user_aufenthalte( $user_id, $year );
        $statistiken = $this->calculate_statistics( $aufenthalte );

        $this->render_year_selector( $year );

        if ( empty( $aufenthalte ) ) {
            $this->render_empty_state();
            return;
        }

        $this->render_aufenthalte_table( $aufenthalte, $statistiken );
        $this->render_statistics_summary( $statistiken );
    }

    /**
     * Holt die Aufenthalte eines Benutzers für ein bestimmtes Jahr
     *
     * @param int $user_id Benutzer ID
     * @param int $year Jahr
     * @return array Array mit Aufenthaltsdaten
     */
    private function get_user_aufenthalte( $user_id, $year ) {
        global $wpdb;
        
        return $wpdb->get_results( $wpdb->prepare(
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
        ) );
    }

    /**
     * Rendert die Jahresauswahl
     *
     * @param int $current_year Aktuell ausgewähltes Jahr
     */
    private function render_year_selector( $current_year ) {
        ?>
        <div class="wue-year-selector" style="margin-bottom: 15px;">
            <form method="get">
                <input type="hidden" name="page" value="dashboard">
                <select name="wue_year" onchange="this.form.submit()">
                    <?php
                    $start_year = 2024;
                    $end_year = intval( date( 'Y' ) );
                    for ( $i = $start_year; $i <= $end_year; $i++ ) {
                        printf(
                            '<option value="%d" %s>%d</option>',
                            $i,
                            selected( $i, $current_year, false ),
                            $i
                        );
                    }
                    ?>
                </select>
            </form>
        </div>
        <?php
    }

    /**
     * Rendert eine Nachricht wenn keine Aufenthalte vorhanden sind
     */
    private function render_empty_state() {
        echo '<p>' . esc_html__( 'Keine Aufenthalte im ausgewählten Jahr.', 'wue-nutzerabrechnung' ) . '</p>';
    }

    /**
     * Berechnet die Statistiken für die Aufenthalte
     *
     * @param array $aufenthalte Array mit Aufenthaltsdaten
     * @return array Array mit berechneten Statistiken
     */
    private function calculate_statistics( $aufenthalte ) {
        $stats = array(
            'gesamt_tage' => 0,
            'gesamt_brennerstunden' => 0,
            'gesamt_oelverbrauch' => 0,
            'gesamt_oelkosten' => 0,
            'gesamt_uebernachtungen_mitglieder' => 0,
            'gesamt_uebernachtungen_gaeste' => 0,
            'gesamt_kosten' => 0
        );

        foreach ( $aufenthalte as $aufenthalt ) {
            $stats['gesamt_tage'] += $aufenthalt->tage;
            $stats['gesamt_brennerstunden'] += $aufenthalt->brennerstunden;
            
            $oelverbrauch = $aufenthalt->brennerstunden * $aufenthalt->verbrauch_pro_brennerstunde;
            $stats['gesamt_oelverbrauch'] += $oelverbrauch;
            
            $oelkosten = $oelverbrauch * $aufenthalt->oelpreis_pro_liter;
            $stats['gesamt_oelkosten'] += $oelkosten;
            
            $stats['gesamt_uebernachtungen_mitglieder'] += $aufenthalt->anzahl_mitglieder;
            $stats['gesamt_uebernachtungen_gaeste'] += $aufenthalt->anzahl_gaeste;
            
            $uebernachtungskosten = ($aufenthalt->anzahl_mitglieder * $aufenthalt->uebernachtung_mitglied) +
                                  ($aufenthalt->anzahl_gaeste * $aufenthalt->uebernachtung_gast);
            $stats['gesamt_kosten'] += $oelkosten + $uebernachtungskosten;
        }

        return $stats;
    }

    /**
     * Rendert die Tabelle mit den Aufenthalten
     *
     * @param array $aufenthalte Array mit Aufenthaltsdaten
     * @param array $statistiken Array mit Statistiken
     */
    private function render_aufenthalte_table( $aufenthalte, $statistiken ) {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Datum', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Tage', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Brennerstd.', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Ölkosten', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Mitglieder', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Gäste', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Gesamt', 'wue-nutzerabrechnung' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $aufenthalte as $aufenthalt ) : 
                    $oelverbrauch = $aufenthalt->brennerstunden * $aufenthalt->verbrauch_pro_brennerstunde;
                    $oelkosten = $oelverbrauch * $aufenthalt->oelpreis_pro_liter;
                    $kosten_mitglieder = $aufenthalt->anzahl_mitglieder * $aufenthalt->uebernachtung_mitglied;
                    $kosten_gaeste = $aufenthalt->anzahl_gaeste * $aufenthalt->uebernachtung_gast;
                    $gesamtkosten = $oelkosten + $kosten_mitglieder + $kosten_gaeste;
                    ?>
                    <tr>
                        <td><?php echo esc_html(
                            date_i18n( 'd.m.Y', strtotime( $aufenthalt->ankunft ) ) . ' - ' .
                            date_i18n( 'd.m.Y', strtotime( $aufenthalt->abreise ) )
                        ); ?></td>
                        <td><?php echo esc_html( $aufenthalt->tage ); ?></td>
                        <td><?php echo esc_html( number_format( $aufenthalt->brennerstunden, 1 ) ); ?></td>
                        <td><?php echo esc_html( number_format( $oelkosten, 2 ) ) . ' €'; ?></td>
                        <td><?php 
                            echo esc_html( $aufenthalt->anzahl_mitglieder );
                            if ( $kosten_mitglieder > 0 ) {
                                echo ' (' . esc_html( number_format( $kosten_mitglieder, 2 ) ) . ' €)';
                            }
                        ?></td>
                        <td><?php 
                            echo esc_html( $aufenthalt->anzahl_gaeste );
                            if ( $kosten_gaeste > 0 ) {
                                echo ' (' . esc_html( number_format( $kosten_gaeste, 2 ) ) . ' €)';
                            }
                        ?></td>
                        <td><?php echo esc_html( number_format( $gesamtkosten, 2 ) ) . ' €'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2"><?php esc_html_e( 'Gesamt:', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php echo esc_html( number_format( $statistiken['gesamt_brennerstunden'], 1 ) ); ?></th>
                    <th><?php echo esc_html( number_format( $statistiken['gesamt_oelkosten'], 2 ) ) . ' €'; ?></th>
                    <th><?php 
                        echo esc_html( $statistiken['gesamt_uebernachtungen_mitglieder'] );
                        $kosten_mitglieder = $statistiken['gesamt_uebernachtungen_mitglieder'] * $aufenthalte[0]->uebernachtung_mitglied;
                        if ( $kosten_mitglieder > 0 ) {
                            echo ' (' . esc_html( number_format( $kosten_mitglieder, 2 ) ) . ' €)';
                        }
                    ?></th>
                    <th><?php 
                        echo esc_html( $statistiken['gesamt_uebernachtungen_gaeste'] );
                        $kosten_gaeste = $statistiken['gesamt_uebernachtungen_gaeste'] * $aufenthalte[0]->uebernachtung_gast;
                        if ( $kosten_gaeste > 0 ) {
                            echo ' (' . esc_html( number_format( $kosten_gaeste, 2 ) ) . ' €)';
                        }
                    ?></th>
                    <th><?php echo esc_html( number_format( $statistiken['gesamt_kosten'], 2 ) ) . ' €'; ?></th>
                </tr>
            </tfoot>
        </table>
        <?php
    }

    /**
     * Rendert die Zusammenfassung der Statistiken
     *
     * @param array $statistiken Array mit Statistiken
     */
    private function render_statistics_summary( $statistiken ) {
        ?>
        <div class="wue-details" style="margin-top: 15px;">
            <p>
                <strong><?php esc_html_e( 'Ölverbrauch:', 'wue-nutzerabrechnung' ); ?></strong>
                <?php echo esc_html( number_format( $statistiken['gesamt_oelverbrauch'], 1 ) ) . ' L'; ?>
            </p>
        </div>
        <?php
    }
}