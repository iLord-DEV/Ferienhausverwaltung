<?php
/**
 * Template für die Preiskonfiguration
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

// Hole alle verfügbaren Jahre aus der Datenbank
global $wpdb;
$available_years = $wpdb->get_col(
	"
    SELECT DISTINCT jahr 
    FROM {$wpdb->prefix}wue_preise 
    ORDER BY jahr DESC
"
);

// Bestimme mögliche neue Jahre (2000 bis aktuelles Jahr + 5)
$all_possible_years  = range( 2024, gmdate( 'Y' ) + 10 );
$available_new_years = array_diff( $all_possible_years, $available_years );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Preiskonfiguration', 'wue-nutzerabrechnung' ); ?></h1>

	<!-- Formular für neues Jahr -->
	<div class="wue-add-year-form" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
		<h2><?php esc_html_e( 'Neues Jahr hinzufügen', 'wue-nutzerabrechnung' ); ?></h2>
		<form method="post" class="add-year-form">
			<?php wp_nonce_field( 'wue_add_year' ); ?>
			<input type="hidden" name="action" value="add_year">
			<p>
				<label for="new_year"><?php esc_html_e( 'Jahr:', 'wue-nutzerabrechnung' ); ?></label>
				<select name="new_year" id="new_year" class="regular-text">
					<?php
					foreach ( $available_new_years as $new_year ) {
						printf(
							'<option value="%d">%d</option>',
							esc_attr( $new_year ),
							esc_attr( $new_year )
						);
					}
					?>
				</select>
				<button type="submit" class="button button-secondary" name="wue_add_year" 
					<?php echo empty( $available_new_years ) ? 'disabled' : ''; ?>>
					<?php esc_html_e( 'Jahr hinzufügen', 'wue-nutzerabrechnung' ); ?>
				</button>
				<?php if ( empty( $available_new_years ) ) : ?>
					<span class="description">
						<?php esc_html_e( 'Alle möglichen Jahre wurden bereits angelegt.', 'wue-nutzerabrechnung' ); ?>
					</span>
				<?php endif; ?>
			</p>
		</form>
	</div>

	<?php if ( ! empty( $available_years ) ) : ?>
	<div class="wue-year-selector" style="margin: 20px 0;">
		<form method="get">
			<input type="hidden" name="page" value="wue-nutzerabrechnung-preise">
			<select name="year" onchange="this.form.submit()">
				<?php
				foreach ( $available_years as $available_year ) {
					printf(
						'<option value="%d" %s>%d</option>',
						esc_attr( $available_year ),
						selected( $available_year, $year, false ),
						esc_attr( $available_year )
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
	<?php else : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Bitte fügen Sie zunächst ein Jahr hinzu.', 'wue-nutzerabrechnung' ); ?></p>
		</div>
	<?php endif; ?>
</div>