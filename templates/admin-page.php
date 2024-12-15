<?php
/**
 * Template für die Admin-Hauptseite
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
	<h1 class="tw-mb-8"><?php esc_html_e( 'Nutzerabrechnung', 'wue-nutzerabrechnung' ); ?></h1>

	<!-- Statistik-Karten -->
	<div class="tw-grid md:tw-grid-cols-3 tw-gap-6 tw-mb-8">
		<!-- Ölverbrauch -->
		<div class="tw-bg-white tw-rounded-lg tw-p-6 tw-shadow-sm">
			<h3 class="tw-text-sm tw-font-medium tw-text-gray-500 tw-mb-2">
				<?php esc_html_e( 'Ölverbrauch aktuelles Jahr', 'wue-nutzerabrechnung' ); ?>
			</h3>
			<p class="tw-text-3xl tw-font-semibold">
				<?php echo esc_html( number_format( $yearly_stats['oil_consumption'], 1 ) ); ?> L
			</p>
		</div>

		<!-- Übernachtungen -->
		<div class="tw-bg-white tw-rounded-lg tw-p-6 tw-shadow-sm">
			<h3 class="tw-text-sm tw-font-medium tw-text-gray-500 tw-mb-2">
				<?php esc_html_e( 'Übernachtungen aktuelles Jahr', 'wue-nutzerabrechnung' ); ?>
			</h3>
			<div class="tw-space-y-1">
				<p class="tw-text-xl tw-font-semibold">
					<?php echo esc_html( $yearly_stats['member_nights'] ); ?>
					<span class="tw-text-sm tw-text-gray-500"><?php esc_html_e( 'Mitglieder', 'wue-nutzerabrechnung' ); ?></span>
				</p>
				<p class="tw-text-xl tw-font-semibold">
					<?php echo esc_html( $yearly_stats['guest_nights'] ); ?>
					<span class="tw-text-sm tw-text-gray-500"><?php esc_html_e( 'Gäste', 'wue-nutzerabrechnung' ); ?></span>
				</p>
			</div>
		</div>

		<!-- Aktuelle Preise -->
		<div class="tw-bg-white tw-rounded-lg tw-p-6 tw-shadow-sm">
			<h3 class="tw-text-sm tw-font-medium tw-text-gray-500 tw-mb-2">
				<?php esc_html_e( 'Aktuelle Preise', 'wue-nutzerabrechnung' ); ?>
			</h3>
			<div class="tw-space-y-1">
				<p class="tw-text-xl tw-font-semibold">
					<?php echo esc_html( number_format( $current_prices['oil_price'], 2 ) ); ?> €
					<span class="tw-text-sm tw-text-gray-500"><?php esc_html_e( 'pro Liter', 'wue-nutzerabrechnung' ); ?></span>
				</p>
				<p class="tw-text-xl tw-font-semibold">
	<?php echo esc_html( number_format( $current_prices['cost_per_hour'], 2 ) ); ?> €
	<span class="tw-text-sm tw-text-gray-500"><?php esc_html_e( 'pro Brennerstunde', 'wue-nutzerabrechnung' ); ?></span>
</p>
				<p class="tw-text-xl tw-font-semibold">
					<?php echo esc_html( number_format( $current_prices['member_price'], 2 ) ); ?> €
					<span class="tw-text-sm tw-text-gray-500"><?php esc_html_e( 'pro Nacht (Mitglieder)', 'wue-nutzerabrechnung' ); ?></span>
				</p>
				<p class="tw-text-xl tw-font-semibold">
					<?php echo esc_html( number_format( $current_prices['guest_price'], 2 ) ); ?> €
					<span class="tw-text-sm tw-text-gray-500"><?php esc_html_e( 'pro Nacht (Gäste)', 'wue-nutzerabrechnung' ); ?></span>
				</p>
			</div>
		</div>
	</div>

	<!-- Aktionen -->
	<div class="tw-bg-white tw-rounded-lg tw-p-6 tw-shadow-sm">
		<h2 class="tw-text-lg tw-font-medium tw-mb-4"><?php esc_html_e( 'Schnellzugriff', 'wue-nutzerabrechnung' ); ?></h2>
		
		<div class="tw-flex tw-gap-3">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wue-aufenthalt-erfassen' ) ); ?>" 
				class="tw-inline-flex tw-items-center tw-px-4 tw-py-2 tw-bg-blue-600 tw-text-white tw-rounded-md hover:tw-bg-blue-700 tw-font-medium">
				<?php esc_html_e( 'Aufenthalt erfassen', 'wue-nutzerabrechnung' ); ?>
			</a>
			
			<?php if ( current_user_can( 'wue_manage_prices' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wue-nutzerabrechnung-preise' ) ); ?>" 
					class="tw-inline-flex tw-items-center tw-px-4 tw-py-2 tw-bg-white tw-text-gray-700 tw-border tw-border-gray-300 tw-rounded-md hover:tw-bg-gray-50 tw-font-medium">
					<?php esc_html_e( 'Preise verwalten', 'wue-nutzerabrechnung' ); ?>
				</a>
			<?php endif; ?>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wue-tankfuellungen' ) ); ?>" 
				class="tw-inline-flex tw-items-center tw-px-4 tw-py-2 tw-bg-white tw-text-gray-700 tw-border tw-border-gray-300 tw-rounded-md hover:tw-bg-gray-50 tw-font-medium">
				<?php esc_html_e( 'Tankfüllung erfassen', 'wue-nutzerabrechnung' ); ?>
			</a>
		</div>
	</div>
</div>