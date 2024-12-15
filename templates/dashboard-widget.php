<?php
/**
 * Dashboard Widget Template
 *
 * This template displays the dashboard widget for the Wue Nutzerabrechnung plugin.
 *
 * @package WueNutzerabrechnung
 */

?>
<div class="wue-dashboard-widget">

	<!-- Jahresauswahl -->
	<div class="tw-mb-6">
		<form method="get">
			<select name="wue_year" 
					id="wue-year-selector" 
					onchange="this.form.submit()"
					class="tw-rounded-lg tw-border-gray-300 tw-shadow-sm tw-w-32">
				<?php foreach ( $available_years as $available_year ) : ?>
					<option value="<?php echo esc_attr( $available_year ); ?>" 
							<?php selected( $available_year, $current_year ); ?>>
						<?php echo esc_html( $available_year ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>
	</div>

	<?php if ( empty( $aufenthalte ) ) : ?>
		<div class="tw-bg-blue-50 tw-p-4 tw-rounded-lg tw-text-blue-700">
			<?php esc_html_e( 'Keine Aufenthalte im ausgewählten Jahr.', 'wue-nutzerabrechnung' ); ?>
		</div>
	<?php else : ?>
		<div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-overflow-hidden">
			<div class="tw-overflow-x-auto">
				<table class="tw-w-full tw-min-w-[800px]">
					<thead>
						<tr class="tw-bg-gray-50 tw-border-b">
							<th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-900">Datum</th>
							<th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">Brennerstunden</th>
							<th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">Ölkosten</th>
							<th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">Mitglieder</th>
							<th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">Gäste</th>
							<th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">Gesamt</th>
							<th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
								<span class="tw-sr-only">Aktionen</span>
							</th>
						</tr>
					</thead>
					<tbody class="tw-divide-y tw-divide-gray-200">
						<?php
						foreach ( $aufenthalte as $aufenthalt ) :
							$berechnungen = WUE_Helpers::calculate_aufenthalt( $aufenthalt );
							?>
							<tr class="hover:tw-bg-gray-50">
								<td class="tw-px-4 tw-py-3 tw-whitespace-nowrap"><?php echo esc_html( $berechnungen['datum'] ); ?></td>
								<td class="tw-px-4 tw-py-3 tw-text-right tw-text-gray-600"><?php echo esc_html( $berechnungen['brennerstunden'] ); ?></td>
								<td class="tw-px-4 tw-py-3 tw-text-right tw-text-gray-600"><?php echo esc_html( $berechnungen['oelkosten'] ); ?></td>
								<td class="tw-px-4 tw-py-3 tw-text-right tw-text-gray-600"><?php echo esc_html( $berechnungen['mitglieder'] ); ?></td>
								<td class="tw-px-4 tw-py-3 tw-text-right tw-text-gray-600"><?php echo esc_html( $berechnungen['gaeste'] ); ?></td>
								<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900"><?php echo esc_html( $berechnungen['gesamt'] ); ?></td>
								<td class="tw-px-4 tw-py-3 tw-text-right">
									<a href="<?php echo esc_url( $berechnungen['edit_url'] ); ?>" 
										class="tw-inline-flex tw-items-center tw-px-3 tw-py-1 tw-rounded-md tw-text-sm tw-font-medium tw-text-blue-700 tw-bg-blue-50 hover:tw-bg-blue-100">
										<?php esc_html_e( 'Bearbeiten', 'wue-nutzerabrechnung' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr class="tw-bg-gray-50 tw-border-t-2 tw-border-gray-200">
							<th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-900">Gesamt</th>
							<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900"><?php echo esc_html( $sums['brennerstunden'] ); ?></td>
							<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900"><?php echo esc_html( $sums['oelkosten'] ); ?></td>
							<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900"><?php echo esc_html( $sums['mitglieder'] ); ?></td>
							<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900"><?php echo esc_html( $sums['gaeste'] ); ?></td>
							<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900"><?php echo esc_html( $sums['gesamt'] ); ?></td>
							<td></td>
						</tr>
					</tfoot>
				</table>
			</div>
		</div>
	<?php endif; ?>
</div>