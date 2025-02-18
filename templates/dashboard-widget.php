<?php
/**
 * Dashboard Widget Template
 *
 * This template displays the dashboard widget for the Wue Nutzerabrechnung plugin.
 * Includes support for overlapping stays.
 *
 * @package WueNutzerabrechnung
 */

global $wpdb;
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
							<th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-900">
								<?php esc_html_e( 'Datum', 'wue-nutzerabrechnung' ); ?>
							</th>
							<th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
								<?php esc_html_e( 'Brennerstunden', 'wue-nutzerabrechnung' ); ?>
							</th>
							<th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
								<?php esc_html_e( 'Ölkosten', 'wue-nutzerabrechnung' ); ?>
							</th>
							<th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
								<?php esc_html_e( 'Mitglieder', 'wue-nutzerabrechnung' ); ?>
							</th>
							<th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
								<?php esc_html_e( 'Gäste', 'wue-nutzerabrechnung' ); ?>
							</th>
							<th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
								<?php esc_html_e( 'Gesamt', 'wue-nutzerabrechnung' ); ?>
							</th>
							<th class="tw-px-4 tw-py-3 tw-text-right">
								<span class="tw-sr-only"><?php esc_html_e( 'Aktionen', 'wue-nutzerabrechnung' ); ?></span>
							</th>
						</tr>
					</thead>
					<tbody class="tw-divide-y tw-divide-gray-200">
						<?php
						foreach ( $aufenthalte as $aufenthalt ) :
							$berechnungen = WUE_Helpers::calculate_aufenthalt( $aufenthalt );
							$has_overlaps = ! empty( $aufenthalt->has_overlaps );
							$overlaps     = $has_overlaps ? WUE()->get_db()->get_overlaps_for_stay( $aufenthalt->id ) : array();
							$row_class    = $has_overlaps ? 'tw-bg-blue-50' : '';
							?>
							<tr class="hover:tw-bg-gray-50 <?php echo esc_attr( $row_class ); ?>">
								<td class="tw-px-4 tw-py-3">
									<div class="tw-flex tw-items-center">
										<span class="tw-whitespace-nowrap"><?php echo esc_html( $berechnungen['datum'] ); ?></span>
										<?php if ( $has_overlaps ) : ?>
											<div class="wue-tooltip tw-relative tw-ml-2">
												<svg xmlns="http://www.w3.org/2000/svg" class="tw-h-5 tw-w-5 tw-text-blue-500" viewBox="0 0 20 20" fill="currentColor">
													<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
												</svg>
												<div class="wue-tooltip-content tw-hidden tw-absolute tw-left-0 tw-z-10 tw-mt-2 tw-w-72 tw-p-4 tw-bg-white tw-rounded-lg tw-shadow-lg tw-border tw-border-gray-200">
	<h4 class="tw-text-sm tw-font-medium tw-text-gray-900 tw-mb-2">
											<?php esc_html_e( 'Geteilte Brennerstunden', 'wue-nutzerabrechnung' ); ?>
	</h4>
											<?php foreach ( $overlaps as $overlap ) : ?>
		<div class="tw-mb-2">
			<p class="tw-text-sm tw-text-gray-600">
												<?php
												printf(
													esc_html__( '%1$s Stunden geteilt mit %2$d anderen (%3$s)', 'wue-nutzerabrechnung' ),
													number_format( $overlap->shared_hours, 1, ',', '.' ),
													$overlap->num_other_users,
													esc_html( $overlap->user_names )
												);
												?>
			</p>
			<p class="tw-text-xs tw-text-gray-500">
												<?php
												printf(
													esc_html__( 'vom %1$s bis %2$s', 'wue-nutzerabrechnung' ),
													date_i18n( get_option( 'date_format' ), strtotime( $overlap->overlap_start ) ),
													date_i18n( get_option( 'date_format' ), strtotime( $overlap->overlap_end ) )
												);
												?>
			</p>
		</div>
	<?php endforeach; ?>
</div>
											</div>
										<?php endif; ?>
									</div>
									<div class="tw-text-sm tw-text-gray-500">
										<?php echo esc_html( $berechnungen['brennerstunden_start'] ); ?> 
										→ 
										<?php echo esc_html( $berechnungen['brennerstunden_ende'] ); ?>
									</div>
								</td>
								<td class="tw-px-4 tw-py-3 tw-text-right tw-text-gray-600">
									<?php echo esc_html( $berechnungen['brennerstunden'] ); ?>
								</td>
								<td class="tw-px-4 tw-py-3 tw-text-right tw-text-gray-600">
									<?php echo esc_html( $berechnungen['oelkosten'] ); ?>
								</td>
								<td class="tw-px-4 tw-py-3 tw-text-right tw-text-gray-600">
									<?php echo esc_html( $berechnungen['mitglieder'] ); ?>
								</td>
								<td class="tw-px-4 tw-py-3 tw-text-right tw-text-gray-600">
									<?php echo esc_html( $berechnungen['gaeste'] ); ?>
								</td>
								<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
									<?php echo esc_html( $berechnungen['gesamt'] ); ?>
								</td>
								<td class="tw-px-4 tw-py-3 tw-text-right">
									<div class="tw-flex tw-gap-2 tw-justify-end">
										<a href="<?php echo esc_url( $berechnungen['edit_url'] ); ?>" 
											class="tw-inline-flex tw-items-center tw-px-3 tw-py-1 tw-rounded-md tw-text-sm tw-font-medium tw-text-blue-700 tw-bg-blue-50 hover:tw-bg-blue-100">
											<?php esc_html_e( 'Bearbeiten', 'wue-nutzerabrechnung' ); ?>
										</a>
										<form method="post" class="tw-inline" onsubmit="return confirm('<?php esc_attr_e( 'Möchten Sie diesen Aufenthalt wirklich löschen?', 'wue-nutzerabrechnung' ); ?>');">
											<?php wp_nonce_field( 'delete_aufenthalt_' . $aufenthalt->id ); ?>
											<input type="hidden" name="action" value="delete_aufenthalt">
											<input type="hidden" name="aufenthalt_id" value="<?php echo esc_attr( $aufenthalt->id ); ?>">
											<button type="submit" 
												class="tw-inline-flex tw-items-center tw-p-2 tw-rounded-md tw-text-red-700 tw-bg-red-50 hover:tw-bg-red-100">
												<svg xmlns="http://www.w3.org/2000/svg" class="tw-h-5 tw-w-5" viewBox="0 0 20 20" fill="currentColor">
													<path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm4 0a1 1 0 012 0v6a1 1 0 11-2 0V8z" clip-rule="evenodd" />
												</svg>
											</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr class="tw-bg-gray-50 tw-border-t-2 tw-border-gray-200">
							<th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-900">
								<?php esc_html_e( 'Gesamt', 'wue-nutzerabrechnung' ); ?>
							</th>
							<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
								<?php echo esc_html( $sums['brennerstunden'] ); ?>
							</td>
							<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
								<?php echo esc_html( $sums['oelkosten'] ); ?>
							</td>
							<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
								<?php echo esc_html( $sums['mitglieder'] ); ?>
							</td>
							<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
								<?php echo esc_html( $sums['gaeste'] ); ?>
							</td>
							<td class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-900">
								<?php echo esc_html( $sums['gesamt'] ); ?>
							</td>
							<td></td>
						</tr>
					</tfoot>
				</table>
			</div>
		</div>

		<?php if ( current_user_can( 'wue_view_all_stats' ) ) : ?>
			<div class="tw-mt-4 tw-text-sm tw-text-gray-500">
				<p>* <?php esc_html_e( 'Brennerstunden wurden aufgrund von Überlappungen angepasst', 'wue-nutzerabrechnung' ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<style>
.wue-tooltip:hover .wue-tooltip-content {
	display: block!important;
}
</style>