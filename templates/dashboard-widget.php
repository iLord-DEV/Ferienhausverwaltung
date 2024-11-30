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
    // URL-Parameter aktualisieren
    var currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('wue_year', year);
    
    // Seite mit neuem Jahr neu laden
    window.location.href = currentUrl.toString();
}
</script>

    <?php if ( empty( $aufenthalte ) ) : ?>
        <p><?php esc_html_e( 'Keine Aufenthalte im ausgewählten Jahr.', 'wue-nutzerabrechnung' ); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Datum', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Brennerstunden', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Ölkosten', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Mitglieder', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Gäste', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Gesamt', 'wue-nutzerabrechnung' ); ?></th>
                    <th><?php esc_html_e( 'Aktionen', 'wue-nutzerabrechnung' ); ?></th>
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

                foreach ( $aufenthalte as $aufenthalt ) : 
                    $brennerstunden = $aufenthalt->brennerstunden_ende - $aufenthalt->brennerstunden_start;
                    $oelverbrauch = $brennerstunden * $aufenthalt->verbrauch_pro_brennerstunde;
                    $oelkosten = $oelverbrauch * $aufenthalt->oelpreis_pro_liter;
                    
                    $kosten_mitglieder = $aufenthalt->anzahl_mitglieder * $aufenthalt->uebernachtung_mitglied;
                    $kosten_gaeste = $aufenthalt->anzahl_gaeste * $aufenthalt->uebernachtung_gast;
                    $gesamtkosten = $oelkosten + $kosten_mitglieder + $kosten_gaeste;

                    // Summiere für Gesamtübersicht
                    $gesamt_brennerstunden += $brennerstunden;
                    $gesamt_oelverbrauch += $oelverbrauch;
                    $gesamt_oelkosten += $oelkosten;
                    $gesamt_uebernachtungen_mitglieder += $aufenthalt->anzahl_mitglieder;
                    $gesamt_uebernachtungen_gaeste += $aufenthalt->anzahl_gaeste;
                    $gesamt_kosten += $gesamtkosten;
                ?>
                    <tr>
                        <td><?php echo esc_html(
                            date_i18n( 'd.m.Y', strtotime( $aufenthalt->ankunft ) ) . ' - ' .
                            date_i18n( 'd.m.Y', strtotime( $aufenthalt->abreise ) )
                        ); ?></td>
                        <td><?php echo esc_html( number_format( $brennerstunden, 1 ) ); ?></td>
                        <td><?php echo esc_html( number_format( $oelkosten, 2 ) ) . ' €'; ?></td>
                        <td><?php 
                            echo esc_html( $aufenthalt->anzahl_mitglieder );
                            echo ' (' . esc_html( number_format( $kosten_mitglieder, 2 ) ) . ' €)';
                        ?></td>
                        <td><?php 
                            echo esc_html( $aufenthalt->anzahl_gaeste );
                            echo ' (' . esc_html( number_format( $kosten_gaeste, 2 ) ) . ' €)';
                        ?></td>
                        <td><?php echo esc_html( number_format( $gesamtkosten, 2 ) ) . ' €'; ?></td>
                        <td>
                            <a href="<?php 
                                echo esc_url(add_query_arg(
                                    array(
                                        'page' => 'wue-aufenthalt-erfassen',
                                        'action' => 'edit',
                                        'id' => $aufenthalt->id,
                                        '_wpnonce' => wp_create_nonce('edit_aufenthalt')
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
                    <th><?php echo esc_html( number_format( $gesamt_brennerstunden, 1 ) ); ?></th>
                    <th><?php echo esc_html( number_format( $gesamt_oelkosten, 2 ) ) . ' €'; ?></th>
                    <th><?php 
                        echo esc_html( $gesamt_uebernachtungen_mitglieder );
                        $gesamt_kosten_mitglieder = $gesamt_uebernachtungen_mitglieder * $aufenthalte[0]->uebernachtung_mitglied;
                        echo ' (' . esc_html( number_format( $gesamt_kosten_mitglieder, 2 ) ) . ' €)';
                    ?></th>
                    <th><?php 
                        echo esc_html( $gesamt_uebernachtungen_gaeste );
                        $gesamt_kosten_gaeste = $gesamt_uebernachtungen_gaeste * $aufenthalte[0]->uebernachtung_gast;
                        echo ' (' . esc_html( number_format( $gesamt_kosten_gaeste, 2 ) ) . ' €)';
                    ?></th>
                    <th colspan="2"><?php echo esc_html( number_format( $gesamt_kosten, 2 ) ) . ' €'; ?></th>
                </tr>
            </tfoot>
        </table>

        <div class="wue-summary" style="margin-top: 10px;">
            <p>
                <strong><?php esc_html_e( 'Ölverbrauch insgesamt:', 'wue-nutzerabrechnung' ); ?></strong>
                <?php echo esc_html( number_format( $gesamt_oelverbrauch, 1 ) ) . ' L'; ?>
            </p>
        </div>
    <?php endif; ?>
</div>