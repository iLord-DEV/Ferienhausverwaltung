<?php
/**
 * Template für die Admin-Hauptseite
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Nutzerabrechnung', 'wue-nutzerabrechnung' ); ?></h1>

    <div class="wue-admin-overview">
        <div class="wue-section">
            <h2><?php esc_html_e( 'Aktuelle Übersicht', 'wue-nutzerabrechnung' ); ?></h2>
            
            <div class="wue-stats-grid">
                <div class="wue-stat-box">
                    <h3><?php esc_html_e( 'Ölverbrauch aktuelles Jahr', 'wue-nutzerabrechnung' ); ?></h3>
                    <p class="wue-stat-value"><?php echo esc_html( number_format( $yearly_stats['oil_consumption'], 1 ) ); ?> L</p>
                </div>

                <div class="wue-stat-box">
                    <h3><?php esc_html_e( 'Übernachtungen aktuelles Jahr', 'wue-nutzerabrechnung' ); ?></h3>
                    <p class="wue-stat-value">
                        <?php 
                        printf(
                            esc_html__( 'Mitglieder: %d, Gäste: %d', 'wue-nutzerabrechnung' ),
                            $yearly_stats['member_nights'],
                            $yearly_stats['guest_nights']
                        ); 
                        ?>
                    </p>
                </div>

                <div class="wue-stat-box">
                    <h3><?php esc_html_e( 'Aktuelle Preise', 'wue-nutzerabrechnung' ); ?></h3>
                    <p class="wue-stat-value">
                        <?php 
                        printf(
                            esc_html__( 'Öl: %.2f €/L, Mitglieder: %.2f €/Nacht', 'wue-nutzerabrechnung' ),
                            $current_prices['oil_price'],
                            $current_prices['member_price']
                        ); 
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="wue-section">
            <h2><?php esc_html_e( 'Schnellzugriff', 'wue-nutzerabrechnung' ); ?></h2>
            
            <div class="wue-quick-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wue-aufenthalt-erfassen' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Aufenthalt erfassen', 'wue-nutzerabrechnung' ); ?>
                </a>
                
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wue-nutzerabrechnung-preise' ) ); ?>" class="button">
                    <?php esc_html_e( 'Preise verwalten', 'wue-nutzerabrechnung' ); ?>
                </a>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wue-tankfuellungen' ) ); ?>" class="button">
                    <?php esc_html_e( 'Tankfüllung erfassen', 'wue-nutzerabrechnung' ); ?>
                </a>
            </div>
        </div>
    </div>
</div>