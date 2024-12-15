<?php
/**
 * Template für die Preiskonfiguration
 *
 * @package WueNutzerabrechnung
 *
 * @var array $current_years     Verfügbare Jahre mit Preiskonfiguration
 * @var array $available_years   Mögliche neue Jahre
 * @var int   $year             Aktuell gewähltes Jahr
 * @var object $prices          Preise für das aktuelle Jahr
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
	<h1 class="tw-text-2xl tw-font-bold tw-mb-6">
		<?php esc_html_e( 'Preiskonfiguration', 'wue-nutzerabrechnung' ); ?>
	</h1>

	<?php settings_errors( 'wue_prices' ); ?>

	<!-- Formular für neues Jahr -->
	<div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-mb-6">
		<h2 class="tw-text-lg tw-font-medium tw-mb-4">
			<?php esc_html_e( 'Neues Jahr hinzufügen', 'wue-nutzerabrechnung' ); ?>
		</h2>
		<form method="post" class="tw-flex tw-gap-4 tw-items-center">
			<?php wp_nonce_field( WUE_Preise::NONCE_ADD_YEAR ); ?>
			<input type="hidden" name="action" value="add_year">
			
			<label for="new_year" class="tw-font-medium">
				<?php esc_html_e( 'Jahr:', 'wue-nutzerabrechnung' ); ?>
			</label>
			<select name="new_year" 
					id="new_year" 
					class="tw-rounded-md tw-border-gray-300 tw-shadow-sm">
				<?php foreach ( $available_years as $new_year ) : ?>
					<option value="<?php echo esc_attr( $new_year ); ?>">
						<?php echo esc_html( $new_year ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			
			<button type="submit" 
					name="wue_add_year" 
					<?php echo empty( $available_years ) ? 'disabled' : ''; ?>
					class="tw-px-4 tw-py-2 tw-bg-white tw-border tw-border-gray-300 tw-rounded-md hover:tw-bg-gray-50 tw-font-medium disabled:tw-opacity-50">
				<?php esc_html_e( 'Jahr hinzufügen', 'wue-nutzerabrechnung' ); ?>
			</button>
			
			<?php if ( empty( $available_years ) ) : ?>
				<span class="tw-text-sm tw-text-gray-500">
					<?php esc_html_e( 'Alle möglichen Jahre wurden bereits angelegt.', 'wue-nutzerabrechnung' ); ?>
				</span>
			<?php endif; ?>
		</form>
	</div>

	<?php if ( ! empty( $current_years ) ) : ?>
		<!-- Preise bearbeiten -->
		<div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
			<div class="tw-flex tw-items-center tw-gap-4 tw-mb-6">
				<h2 class="tw-text-lg tw-font-medium tw-mb-0">
					<?php esc_html_e( 'Preise bearbeiten', 'wue-nutzerabrechnung' ); ?>
				</h2>
				<form method="get" class="tw-mb-0">
					<input type="hidden" name="page" value="wue-nutzerabrechnung-preise">
					<select name="year" 
							onchange="this.form.submit()"
							class="tw-rounded-md tw-border-gray-300 tw-shadow-sm">
						<?php foreach ( $current_years as $available_year ) : ?>
							<option value="<?php echo esc_attr( $available_year ); ?>" 
									<?php selected( $available_year, $year ); ?>>
								<?php echo esc_html( $available_year ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</form>
			</div>

			<form method="post">
				<?php wp_nonce_field( WUE_Preise::NONCE_SAVE_PRICES ); ?>
				<input type="hidden" name="wue_year" value="<?php echo esc_attr( $year ); ?>">
				
				<div class="tw-space-y-6">
					<!-- Ölpreis -->
					<div class="tw-grid md:tw-grid-cols-4 tw-gap-4 tw-items-center">
						<label for="oelpreis_pro_liter" class="tw-font-medium">
							<?php esc_html_e( 'Ölpreis pro Liter (€)', 'wue-nutzerabrechnung' ); ?>
						</label>
						<div class="md:tw-col-span-3">
							<input type="number" 
								step="0.01" 
								min="0" 
								id="oelpreis_pro_liter" 
								name="wue_prices[oelpreis_pro_liter]" 
								value="<?php echo esc_attr( $prices->oelpreis_pro_liter ?? '1.00' ); ?>" 
								class="tw-w-full tw-rounded-md tw-border-gray-300 tw-shadow-sm">
						</div>
					</div>

					<!-- Übernachtungspreis Mitglied -->
					<div class="tw-grid md:tw-grid-cols-4 tw-gap-4 tw-items-center">
						<label for="uebernachtung_mitglied" class="tw-font-medium">
							<?php esc_html_e( 'Übernachtungspreis Mitglied (€)', 'wue-nutzerabrechnung' ); ?>
						</label>
						<div class="md:tw-col-span-3">
							<input type="number" 
								step="0.01" 
								min="0" 
								id="uebernachtung_mitglied" 
								name="wue_prices[uebernachtung_mitglied]" 
								value="<?php echo esc_attr( $prices->uebernachtung_mitglied ?? '10.00' ); ?>" 
								class="tw-w-full tw-rounded-md tw-border-gray-300 tw-shadow-sm">
						</div>
					</div>

					<!-- Übernachtungspreis Gast -->
					<div class="tw-grid md:tw-grid-cols-4 tw-gap-4 tw-items-center">
						<label for="uebernachtung_gast" class="tw-font-medium">
							<?php esc_html_e( 'Übernachtungspreis Gast (€)', 'wue-nutzerabrechnung' ); ?>
						</label>
						<div class="md:tw-col-span-3">
							<input type="number" 
								step="0.01" 
								min="0" 
								id="uebernachtung_gast" 
								name="wue_prices[uebernachtung_gast]" 
								value="<?php echo esc_attr( $prices->uebernachtung_gast ?? '15.00' ); ?>" 
								class="tw-w-full tw-rounded-md tw-border-gray-300 tw-shadow-sm">
						</div>
					</div>

					<!-- Verbrauch pro Brennerstunde -->
					<div class="tw-grid md:tw-grid-cols-4 tw-gap-4 tw-items-center">
						<label for="verbrauch_pro_brennerstunde" class="tw-font-medium">
							<?php esc_html_e( 'Verbrauch pro Brennerstunde (Liter)', 'wue-nutzerabrechnung' ); ?>
						</label>
						<div class="md:tw-col-span-3">
							<input type="number" 
								step="0.01" 
								min="0" 
								id="verbrauch_pro_brennerstunde" 
								name="wue_prices[verbrauch_pro_brennerstunde]" 
								value="<?php echo esc_attr( $prices->verbrauch_pro_brennerstunde ?? '2.50' ); ?>" 
								class="tw-w-full tw-rounded-md tw-border-gray-300 tw-shadow-sm">
						</div>
					</div>
				</div>

				<div class="tw-mt-6">
					<?php submit_button( __( 'Preise speichern', 'wue-nutzerabrechnung' ), 'primary tw-bg-blue-600', 'wue_save_prices' ); ?>
				</div>
			</form>
		</div>

	<?php else : ?>
		<div class="tw-bg-yellow-50 tw-border-l-4 tw-border-yellow-400 tw-p-4">
			<p class="tw-text-yellow-700">
				<?php esc_html_e( 'Bitte fügen Sie zunächst ein Jahr hinzu.', 'wue-nutzerabrechnung' ); ?>
			</p>
		</div>
	<?php endif; ?>
</div>