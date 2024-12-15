<?php
/**
 * Template für das Tankfüllungs-Formular
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
	<h1 class="tw-text-2xl tw-font-bold tw-mb-6">
		<?php esc_html_e( 'Tankfüllung erfassen', 'wue-nutzerabrechnung' ); ?>
	</h1>

	<?php settings_errors( 'wue_tankfuellung' ); ?>

	<div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
		<form method="post" action="">
			<?php wp_nonce_field( WUE_Tankfuellungen::NONCE_ACTION ); ?>
			
			<table class="tw-w-full">
				<tr class="tw-border-b">
					<th class="tw-py-4 tw-pr-4 tw-text-left tw-w-1/4">
						<label for="datum" class="tw-font-medium">
							<?php esc_html_e( 'Datum', 'wue-nutzerabrechnung' ); ?>
						</label>
					</th>
					<td class="tw-py-4">
						<input type="date" 
							id="datum" 
							name="wue_tankfuellung[datum]" 
							value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" 
							required 
							class="tw-w-full tw-max-w-xs tw-rounded-md tw-border-gray-300 tw-shadow-sm">
					</td>
				</tr>
				
				<!-- Weitere Formularfelder ähnlich formatieren -->
			</table>

			<div class="tw-mt-6">
				<?php submit_button( __( 'Tankfüllung speichern', 'wue-nutzerabrechnung' ), 'primary tw-bg-blue-600', 'submit' ); ?>
			</div>
		</form>
	</div>
</div>