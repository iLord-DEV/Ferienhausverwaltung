<?php
/**
 * Template für das Aufenthalts-Formular
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

$is_edit    = isset( $aufenthalt ) && $aufenthalt;
$form_title = $is_edit ? __( 'Aufenthalt bearbeiten', 'wue-nutzerabrechnung' ) : __( 'Aufenthalt erfassen', 'wue-nutzerabrechnung' );
?>

<div class="wrap">
	<h1 class="tw-text-2xl tw-font-bold tw-mb-6">
		<?php echo esc_html( $form_title ); ?>
	</h1>

	<div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
		<form method="post" action="" class="wue-aufenthalt-form">
			<?php wp_nonce_field( WUE_Aufenthalte::NONCE_ACTION ); ?>
			
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="aufenthalt_id" value="<?php echo esc_attr( $aufenthalt->id ); ?>">
			<?php endif; ?>

			<!-- Zwei Spalten für Ankunft/Abreise -->
			<div class="tw-grid md:tw-grid-cols-2 tw-gap-6 tw-mb-6">
				<!-- Ankunft -->
				<div class="tw-space-y-2">
					<label for="ankunft" class="tw-block tw-font-medium">
						<?php esc_html_e( 'Ankunftsdatum', 'wue-nutzerabrechnung' ); ?>
					</label>
					<input type="date" 
						id="ankunft" 
						name="wue_aufenthalt[ankunft]" 
						required 
						class="tw-w-full tw-rounded-md tw-border-gray-300 tw-shadow-sm"
						value="<?php echo esc_attr( $is_edit ? gmdate( 'Y-m-d', strtotime( $aufenthalt->ankunft ) ) : gmdate( 'Y-m-d' ) ); ?>">
				</div>

				<!-- Abreise -->
				<div class="tw-space-y-2">
					<label for="abreise" class="tw-block tw-font-medium">
						<?php esc_html_e( 'Abreisedatum', 'wue-nutzerabrechnung' ); ?>
					</label>
					<input type="date" 
						id="abreise" 
						name="wue_aufenthalt[abreise]" 
						required 
						class="tw-w-full tw-rounded-md tw-border-gray-300 tw-shadow-sm"
						value="<?php echo esc_attr( $is_edit ? gmdate( 'Y-m-d', strtotime( $aufenthalt->abreise ) ) : '' ); ?>">
				</div>
			</div>

			<!-- Zwei Spalten für Brennerstunden -->
			<div class="tw-grid md:tw-grid-cols-2 tw-gap-6 tw-mb-6">
				<!-- Brennerstunden Start -->
				<div class="tw-space-y-2">
					<label for="brennerstunden_start" class="tw-block tw-font-medium">
						<?php esc_html_e( 'Brennerstunden bei Ankunft', 'wue-nutzerabrechnung' ); ?>
					</label>
					<input type="number" 
						step="0.1" 
						min="0" 
						id="brennerstunden_start" 
						name="wue_aufenthalt[brennerstunden_start]" 
						required 
						class="tw-w-full tw-rounded-md tw-border-gray-300 tw-shadow-sm"
						value="<?php echo esc_attr( $is_edit ? $aufenthalt->brennerstunden_start : '' ); ?>">
				</div>

				<!-- Brennerstunden Ende -->
				<div class="tw-space-y-2">
					<label for="brennerstunden_ende" class="tw-block tw-font-medium">
						<?php esc_html_e( 'Brennerstunden bei Abreise', 'wue-nutzerabrechnung' ); ?>
					</label>
					<input type="number" 
						step="0.1" 
						min="0" 
						id="brennerstunden_ende" 
						name="wue_aufenthalt[brennerstunden_ende]" 
						required 
						class="tw-w-full tw-rounded-md tw-border-gray-300 tw-shadow-sm"
						value="<?php echo esc_attr( $is_edit ? $aufenthalt->brennerstunden_ende : '' ); ?>">
				</div>
			</div>

			<!-- Zwei Spalten für Übernachtungen -->
			<div class="tw-grid md:tw-grid-cols-2 tw-gap-6 tw-mb-6">
				<!-- Mitglieder -->
				<div class="tw-space-y-2">
					<label for="anzahl_mitglieder" class="tw-block tw-font-medium">
						<?php esc_html_e( 'Anzahl Übernachtungen Mitglieder', 'wue-nutzerabrechnung' ); ?>
					</label>
					<input type="number" 
						min="0" 
						id="anzahl_mitglieder" 
						name="wue_aufenthalt[anzahl_mitglieder]" 
						required 
						class="tw-w-full tw-rounded-md tw-border-gray-300 tw-shadow-sm"
						value="<?php echo esc_attr( $is_edit ? $aufenthalt->anzahl_mitglieder : '' ); ?>">
					<p class="tw-text-sm tw-text-gray-500">
						<?php esc_html_e( 'Gesamtzahl der Übernachtungen aller Mitglieder', 'wue-nutzerabrechnung' ); ?>
					</p>
				</div>

				<!-- Gäste -->
				<div class="tw-space-y-2">
					<label for="anzahl_gaeste" class="tw-block tw-font-medium">
						<?php esc_html_e( 'Anzahl Übernachtungen Gäste', 'wue-nutzerabrechnung' ); ?>
					</label>
					<input type="number" 
						min="0" 
						id="anzahl_gaeste" 
						name="wue_aufenthalt[anzahl_gaeste]" 
						required 
						class="tw-w-full tw-rounded-md tw-border-gray-300 tw-shadow-sm"
						value="<?php echo esc_attr( $is_edit ? $aufenthalt->anzahl_gaeste : '' ); ?>">
					<p class="tw-text-sm tw-text-gray-500">
						<?php esc_html_e( 'Gesamtzahl der Übernachtungen aller Gäste', 'wue-nutzerabrechnung' ); ?>
					</p>
				</div>
			</div>

			<div class="tw-mt-6 tw-flex tw-gap-2">
				<?php
				submit_button(
					$is_edit ? __( 'Änderungen speichern', 'wue-nutzerabrechnung' ) : __( 'Aufenthalt speichern', 'wue-nutzerabrechnung' ),
					'primary tw-bg-blue-600',
					'wue_aufenthalt_submit'
				);
				?>

				<?php if ( $is_edit ) : ?>
					<a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>" 
						class="tw-inline-flex tw-items-center tw-px-4 tw-py-2 tw-bg-white tw-border tw-border-gray-300 tw-rounded-md hover:tw-bg-gray-50 tw-font-medium">
						<?php esc_html_e( 'Abbrechen', 'wue-nutzerabrechnung' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</form>
	</div>
</div>
