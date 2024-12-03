<?php
/**
 * Template für das Dashboard-Widget
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

// Jahresauswahl
$current_year = isset( $_GET['wue_year'] ) ? intval( $_GET['wue_year'] ) : date('Y');
$available_years = $this->get_available_years( get_current_user_id() );

?>

<div class="wue-dashboard-widget">
    <div class="wue-year-selector" style="margin-bottom: 15px;">
        <select id="wue-year-selector" onchange="changeYear(this.value)">
            <?php foreach ($available_years as $year) : ?>
                <option value="<?php echo esc_attr($year); ?>" 
                        <?php selected($year, $current_year); ?>>
                    <?php echo esc_html($year); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <script>
    function changeYear(year) {
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('wue_year', year);
        window.location.href = currentUrl.toString();
    }
    </script>

    <?php if ( empty( $aufenthalte ) ) : ?>
        <p><?php esc_html_e( 'Keine Aufenthalte im ausgewählten Jahr.', 'wue-nutzerabrechnung' ); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-date"><?php esc_html_e( 'Datum', 'wue-nutzerabrechnung' ); ?></th>
                    <th class="column-brenner"><?php esc_html_e( 'Brennerstunden', 'wue-nutzerabrechnung' ); ?></th>
                    <th class="column-oil"><?php esc_html_e( 'Ölkosten', 'wue-nutzerabrechnung' ); ?></th>
                    <th class="column-members"><?php esc_html_e( 'Mitglieder', 'wue-nutzerabrechnung' ); ?></th>
                    <th class="column-guests"><?php esc_html_e( 'Gäste', 'wue-nutzerabrechnung' ); ?></th>
                    <th class="column-total"><?php esc_html_e( 'Gesamt', 'wue-nutzerabrechnung' ); ?></th>
                    <th class="column-actions"><?php esc_html_e( 'Aktionen', 'wue-nutzerabrechnung' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $gesamt_brennerstunden = 0;
                $gesamt_oelverbrauch = 0;
                $gesamt_oelkosten = 0;
                $gesamt_uebernachtungen_mitglieder = 0;
                $gesamt_uebernachtungen_gaeste = 0;
                $gesamt_kosten = 0;
                $gesamt_kosten_mitglieder = 0;
                $gesamt_kosten_gaeste = 0;

                foreach ( $aufenthalte as $aufenthalt ) : 
                    // Debug-Ausgabe
                    error_log('Aufenthalt Daten: ' . print_r($aufenthalt, true));
                    
                    // Berechne Brennerstunden und Ölverbrauch
                    $brennerstunden = $aufenthalt->brennerstunden_ende - $aufenthalt->brennerstunden_start;
                    $oelverbrauch = $brennerstunden * floatval($aufenthalt->verbrauch_pro_brennerstunde);
                    $oelkosten = $oelverbrauch * floatval($aufenthalt->oelpreis_pro_liter);
                    
                    // Berechne Übernachtungskosten
                    $kosten_pro_mitglied = floatval($aufenthalt->uebernachtung_mitglied);
                    $kosten_pro_gast = floatval($aufenthalt->uebernachtung_gast);
                    
                    // Berechne die Anzahl der Tage (inkl. An- und Abreisetag)
                    $tage = (strtotime($aufenthalt->abreise) - strtotime($aufenthalt->ankunft)) / (60 * 60 * 24);
                    
                    $kosten_mitglieder = $aufenthalt->anzahl_mitglieder * $kosten_pro_mitglied * $tage;
                    $kosten_gaeste = $aufenthalt->anzahl_gaeste * $kosten_pro_gast * $tage;
                    $gesamtkosten = $oelkosten + $kosten_mitglieder + $kosten_gaeste;

                    // Debug-Ausgabe der Berechnungen
                    error_log(sprintf(
                        'Berechnungen für Aufenthalt %d: Tage=%d, Brennerstunden=%f, Ölverbrauch=%f, Ölkosten=%f, Kosten Mitglieder=%f, Kosten Gäste=%f',
                        $aufenthalt->id,
                        $tage,
                        $brennerstunden,
                        $oelverbrauch,
                        $oelkosten,
                        $kosten_mitglieder,
                        $kosten_gaeste
                    ));

                    // Summiere für Gesamtübersicht
                    $gesamt_brennerstunden += $brennerstunden;
                    $gesamt_oelverbrauch += $oelverbrauch;
                    $gesamt_oelkosten += $oelkosten;
                    $gesamt_uebernachtungen_mitglieder += $aufenthalt->anzahl_mitglieder * $tage;
                    $gesamt_uebernachtungen_gaeste += $aufenthalt->anzahl_gaeste * $tage;
                    $gesamt_kosten_mitglieder += $kosten_mitglieder;
                    $gesamt_kosten_gaeste += $kosten_gaeste;
                    $gesamt_kosten += $gesamtkosten;
                ?>
                    <tr>
                        <td class="column-date">
                            <?php 
                            echo sprintf(
                                '%s - %s',
                                date_i18n('d.m.Y', strtotime($aufenthalt->ankunft)),
                                date_i18n('d.m.Y', strtotime($aufenthalt->abreise))
                            ); 
                            ?>
                        </td>
                        <td class="column-brenner">
                            <?php echo number_format($brennerstunden, 1, ',', '.'); ?>
                        </td>
                        <td class="column-oil">
                            <?php echo number_format($oelkosten, 2, ',', '.') . ' €'; ?>
                        </td>
                        <td class="column-members">
                            <?php 
                            echo sprintf(
                                '%d Übern. (%s €)',
                                $aufenthalt->anzahl_mitglieder * $tage,
                                number_format($kosten_mitglieder, 2, ',', '.')
                            );
                            ?>
                        </td>
                        <td class="column-guests">
                            <?php 
                            echo sprintf(
                                '%d Übern. (%s €)',
                                $aufenthalt->anzahl_gaeste * $tage,
                                number_format($kosten_gaeste, 2, ',', '.')
                            );
                            ?>
                        </td>
                        <td class="column-total">
                            <?php echo number_format($gesamtkosten, 2, ',', '.') . ' €'; ?>
                        </td>
                        <td class="column-actions">
                            <a href="<?php 
                                echo esc_url(add_query_arg(
                                    array(
                                        'page' => 'wue-aufenthalt-erfassen',
                                        'action' => 'edit',
                                        'id' => $aufenthalt->id,
                                        '_wpnonce' => wp_create_nonce(WUE_Admin::NONCE_AUFENTHALT)
                                    ),
                                    admin_url('admin.php')
                                )); 
                            ?>" class="button button-small">
                                <?php esc_html_e( 'Bearbeiten', 'wue-nutzerabrechnung' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th><?php esc_html_e( 'Gesamt', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php echo number_format($gesamt_brennerstunden, 1, ',', '.'); ?></th>
                    <th><?php echo number_format($gesamt_oelkosten, 2, ',', '.') . ' €'; ?></th>
                    <th><?php 
                        echo sprintf(
                            '%d Übern. (%s €)',
                            $gesamt_uebernachtungen_mitglieder,
                            number_format($gesamt_kosten_mitglieder, 2, ',', '.')
                        );
                    ?></th>
                    <th><?php 
                        echo sprintf(
                            '%d Übern. (%s €)',
                            $gesamt_uebernachtungen_gaeste,
                            number_format($gesamt_kosten_gaeste, 2, ',', '.')
                        );
                    ?></th>
                    <th colspan="2"><?php echo number_format($gesamt_kosten, 2, ',', '.') . ' €'; ?></th>
                </tr>
            </tfoot>
        </table>

        <div class="wue-summary" style="margin-top: 10px;">
            <p>
                <strong><?php esc_html_e( 'Ölverbrauch insgesamt:', 'wue-nutzerabrechnung' ); ?></strong>
                <?php echo number_format($gesamt_oelverbrauch, 1, ',', '.') . ' L'; ?>
            </p>
        </div>

        <style>
            .wue-dashboard-widget table td,
            .wue-dashboard-widget table th {
                padding: 8px;
                text-align: left;
            }
            .wue-dashboard-widget .column-brenner,
            .wue-dashboard-widget .column-oil,
            .wue-dashboard-widget .column-total {
                text-align: right;
            }
            .wue-dashboard-widget .column-members,
            .wue-dashboard-widget .column-guests {
                text-align: left;
            }
        </style>
    <?php endif; ?>
</div>