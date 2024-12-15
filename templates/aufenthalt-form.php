<?php
/**
 * Template für das Aufenthalts-Formular
 * Unterstützt Erstellen und Bearbeiten von Aufenthalten
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

$is_edit    = isset( $aufenthalt ) && $aufenthalt;
$form_title = $is_edit ? __( 'Aufenthalt bearbeiten', 'wue-nutzerabrechnung' ) : __( 'Aufenthalt erfassen', 'wue-nutzerabrechnung' );
?>

<div class="wrap">
	<h1><?php echo esc_html( $form_title ); ?></h1>

	<?php settings_errors( 'wue_aufenthalt' ); ?>

	<form method="post" action="" class="wue-aufenthalt-form">
	<?php wp_nonce_field( WUE_Aufenthalte::NONCE_ACTION ); ?>
		
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="wue_aufenthalt[id]" value="<?php echo esc_attr( $aufenthalt->id ); ?>">
		<?php endif; ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="ankunft"><?php esc_html_e( 'Ankunftsdatum', 'wue-nutzerabrechnung' ); ?></label>
				</th>
				<td>
				<input type="date" 
					id="ankunft" 
					name="wue_aufenthalt[ankunft]" 
					required 
					class="regular-text"
					value="<?php echo esc_attr( $is_edit ? date( 'Y-m-d', strtotime( $aufenthalt->ankunft ) ) : date( 'Y-m-d' ) ); ?>">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="brennerstunden_start">
						<?php esc_html_e( 'Brennerstunden bei Ankunft', 'wue-nutzerabrechnung' ); ?>
					</label>
				</th>
				<td>
					<input type="number" 
						step="0.1" 
						min="0" 
						id="brennerstunden_start" 
						name="wue_aufenthalt[brennerstunden_start]" 
						required 
						class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $aufenthalt->brennerstunden_start : '' ); ?>">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="abreise"><?php esc_html_e( 'Abreisedatum', 'wue-nutzerabrechnung' ); ?></label>
				</th>
				<td>
				<input type="date" 
					id="abreise" 
					name="wue_aufenthalt[abreise]" 
					required 
					class="regular-text"
					value="<?php echo esc_attr( $is_edit ? date( 'Y-m-d', strtotime( $aufenthalt->abreise ) ) : '' ); ?>">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="brennerstunden_ende">
						<?php esc_html_e( 'Brennerstunden bei Abreise', 'wue-nutzerabrechnung' ); ?>
					</label>
				</th>
				<td>
					<input type="number" 
						step="0.1" 
						min="0" 
						id="brennerstunden_ende" 
						name="wue_aufenthalt[brennerstunden_ende]" 
						required 
						class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $aufenthalt->brennerstunden_ende : '' ); ?>">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="anzahl_mitglieder">
						<?php esc_html_e( 'Anzahl Übernachtungen Mitglieder', 'wue-nutzerabrechnung' ); ?>
					</label>
				</th>
				<td>
					<input type="number" 
						min="0" 
						id="anzahl_mitglieder" 
						name="wue_aufenthalt[anzahl_mitglieder]" 
						required 
						class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $aufenthalt->anzahl_mitglieder : '' ); ?>">
					<p class="description">
						<?php esc_html_e( 'Gesamtzahl der Übernachtungen aller Mitglieder', 'wue-nutzerabrechnung' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="anzahl_gaeste">
						<?php esc_html_e( 'Anzahl Übernachtungen Gäste', 'wue-nutzerabrechnung' ); ?>
					</label>
				</th>
				<td>
					<input type="number" 
						min="0" 
						id="anzahl_gaeste" 
						name="wue_aufenthalt[anzahl_gaeste]" 
						required 
						class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $aufenthalt->anzahl_gaeste : '' ); ?>">
					<p class="description">
						<?php esc_html_e( 'Gesamtzahl der Übernachtungen aller Gäste', 'wue-nutzerabrechnung' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<div class="submit-wrapper" style="margin-top: 20px;">
			<?php
			submit_button(
				$is_edit ? __( 'Änderungen speichern', 'wue-nutzerabrechnung' ) : __( 'Aufenthalt speichern', 'wue-nutzerabrechnung' ),
				'primary',
				'submit'
			);
			?>

			<?php if ( $is_edit ) : ?>
				<a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>" class="button" style="margin-left: 10px;">
					<?php esc_html_e( 'Abbrechen', 'wue-nutzerabrechnung' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</form>
</div>
