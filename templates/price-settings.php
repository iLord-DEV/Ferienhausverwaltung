<?php
/**
 * Template für die Preiskonfiguration
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Preiskonfiguration', 'wue-nutzerabrechnung' ); ?></h1>

    <div class="wue-year-selector" style="margin: 20px 0;">
        <form method="get">
            <input type="hidden" name="page" value="wue-nutzerabrechnung-preise">
            <select name="year" onchange="this.form.submit()">
                <?php
                $start_year = 2024;
                $end_year = intval( date( 'Y' ) ) + 1;
                for ( $i = $start_year; $i <= $end_year; $i++ ) {
                    printf(
                        '<option value="%d" %s>%d</option>',
                        $i,
                        selected( $i, $year, false ),
                        $i
                    );
                }
                ?>
            </select>
        </form>
    </div>

    <form method="post">
        <?php wp_nonce_field( 'wue_save_prices' ); ?>
        <input type="hidden" name="wue_year" value="<?php echo esc_attr( $year ); ?>">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="oelpreis_pro_liter">
                        <?php esc_html_e( 'Ölpreis pro Liter (€)', 'wue-nutzerabrechnung' ); ?>
                    </label>
                </th>
                <td>
                    <input type="number" 
                        step="0.01" 
                        min="0" 
                        id="oelpreis_pro_liter" 
                        name="wue_prices[oelpreis_pro_liter]" 
                        value="<?php echo esc_attr( $prices->oelpreis_pro_liter ?? '1.00' ); ?>" 
                        class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="uebernachtung_mitglied">
                        <?php esc_html_e( 'Übernachtungspreis Mitglied (€)', 'wue-nutzerabrechnung' ); ?>
                    </label>
                </th>
                <td>
                    <input type="number" 
                        step="0.01" 
                        min="0" 
                        id="uebernachtung_mitglied" 
                        name="wue_prices[uebernachtung_mitglied]" 
                        value="<?php echo esc_attr( $prices->uebernachtung_mitglied ?? '10.00' ); ?>" 
                        class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="uebernachtung_gast">
                        <?php esc_html_e( 'Übernachtungspreis Gast (€)', 'wue-nutzerabrechnung' ); ?>
                    </label>
                </th>
                <td>
                    <input type="number" 
                        step="0.01" 
                        min="0" 
                        id="uebernachtung_gast" 
                        name="wue_prices[uebernachtung_gast]" 
                        value="<?php echo esc_attr( $prices->uebernachtung_gast ?? '15.00' ); ?>" 
                        class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="verbrauch_pro_brennerstunde">
                        <?php esc_html_e( 'Verbrauch pro Brennerstunde (Liter)', 'wue-nutzerabrechnung' ); ?>
                    </label>
                </th>
                <td>
                    <input type="number" 
                        step="0.01" 
                        min="0" 
                        id="verbrauch_pro_brennerstunde" 
                        name="wue_prices[verbrauch_pro_brennerstunde]" 
                        value="<?php echo esc_attr( $prices->verbrauch_pro_brennerstunde ?? '2.50' ); ?>" 
                        class="regular-text">
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Preise speichern', 'wue-nutzerabrechnung' ), 'primary', 'wue_save_prices' ); ?>
    </form>
</div>