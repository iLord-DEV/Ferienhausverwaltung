<?php
/**
 * Template für das Tankfüllungs-Formular
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Tankfüllung erfassen', 'wue-nutzerabrechnung' ); ?></h1>

	<?php settings_errors( 'wue_tankfuellung' ); ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'wue_save_tankfuellung' ); ?>
		
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="datum"><?php esc_html_e( 'Datum', 'wue-nutzerabrechnung' ); ?></label>
				</th>
				<td>
					<input type="date" 
						id="datum" 
						name="wue_tankfuellung[datum]" 
						value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" 
						required 
						class="regular-text">
				</td>
			</tr>
			
			<tr>
				<th scope="row">
					<label for="brennerstunden_stand"><?php esc_html_e( 'Brennerstunden-Stand', 'wue-nutzerabrechnung' ); ?></label>
				</th>
				<td>
					<input type="number" 
						step="0.1" 
						min="0" 
						id="brennerstunden_stand" 
						name="wue_tankfuellung[brennerstunden_stand]" 
						required 
						class="regular-text">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="liter"><?php esc_html_e( 'Getankte Liter', 'wue-nutzerabrechnung' ); ?></label>
				</th>
				<td>
					<input type="number" 
						step="0.1" 
						min="0" 
						id="liter" 
						name="wue_tankfuellung[liter]" 
						required 
						class="regular-text">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="preis_pro_liter"><?php esc_html_e( 'Preis pro Liter (€)', 'wue-nutzerabrechnung' ); ?></label>
				</th>
				<td>
					<input type="number" 
						step="0.01" 
						min="0" 
						id="preis_pro_liter" 
						name="wue_tankfuellung[preis_pro_liter]" 
						required 
						class="regular-text">
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Tankfüllung speichern', 'wue-nutzerabrechnung' ), 'primary', 'submit' ); ?>
	</form>
</div>