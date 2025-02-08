<?php
/**
 * Template für die Statistikseite
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
	<h1 class="tw-text-2xl tw-font-bold tw-mb-6">
		<?php esc_html_e( 'Verbrauchsstatistiken', 'wue-nutzerabrechnung' ); ?>
	</h1>

	<!-- Jahresvergleich -->
	<div class="tw-grid md:tw-grid-cols-2 tw-gap-6 tw-mb-6">
		<div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
			<h2 class="tw-text-lg tw-font-medium tw-mb-4">
				<?php esc_html_e( 'Verbrauch im Jahresvergleich', 'wue-nutzerabrechnung' ); ?>
			</h2>
			<div class="tw-aspect-[16/9]">
				<canvas id="yearlyComparisonChart"></canvas>
			</div>
		</div>

		<!-- Verbrauchsverteilung -->
		<div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
			<h2 class="tw-text-lg tw-font-medium tw-mb-4">
				<?php esc_html_e( 'Verbrauchsverteilung', 'wue-nutzerabrechnung' ); ?>
			</h2>
			<div class="tw-aspect-[16/9]">
				<canvas id="usageDistributionChart"></canvas>
			</div>
		</div>
	</div>

	<!-- Gruppengröße/Verbrauch -->
	<div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
		<h2 class="tw-text-lg tw-font-medium tw-mb-4">
			<?php esc_html_e( 'Verbrauch nach Gruppengröße', 'wue-nutzerabrechnung' ); ?>
		</h2>
		<div class="tw-aspect-[21/9]">
			<canvas id="groupSizeCorrelationChart"></canvas>
		</div>
	</div>
</div>