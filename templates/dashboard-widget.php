<?php
/**
 * Template für das Dashboard-Widget
 *
 * @package WueNutzerabrechnung
 *
 * @var array $aufenthalte Liste der Aufenthalte
 * @var array $available_years Verfügbare Jahre
 * @var int $current_year Aktuelles Jahr
 * @var array $sums Berechnete Summen
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wue-dashboard-widget">
	<!-- Jahresauswahl -->
	<div class="wue-year-selector">
		<form method="get">
			<select name="wue_year" id="wue-year-selector" onchange="this.form.submit()">
				<?php foreach ( $available_years as $year ) : ?>
					<option value="<?php echo esc_attr( $year ); ?>" 
							<?php selected( $year, $current_year ); ?>>
						<?php echo esc_html( $year ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>
	</div>

	<?php if ( empty( $aufenthalte ) ) : ?>
		<p><?php esc_html_e( 'Keine Aufenthalte im ausgewählten Jahr.', 'wue-nutzerabrechnung' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th class="column-date"><?php esc_html_e( 'Datum', 'wue-nutzerabrechnung' ); ?></th>
					<th class="column-brenner"><?php esc_html_e( 'Brennerstunden', 'wue-nutzerabrechnung' ); ?></th>
					<th class="column-oil"><?php esc_html_e( 'Ölkosten', 'wue-nutzerabrechnung' ); ?></th>
					<th class="column-members"><?php esc_html_e( 'Mitglieder', 'wue-nutzerabrechnung' ); ?></th>
					<th class="column-guests"><?php esc_html_e( 'Gäste', 'wue-nutzerabrechnung' ); ?></th>
					<th class="column-total"><?php esc_html_e( 'Gesamt', 'wue-nutzerabrechnung' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Aktionen', 'wue-nutzerabrechnung' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $aufenthalte as $aufenthalt ) :
					$berechnungen = WUE_Helpers::calculate_aufenthalt( $aufenthalt );
					?>
					<tr>
						<td class="column-date">
							<?php echo esc_html( $berechnungen['datum'] ); ?>
						</td>
						<td class="column-brenner">
							<?php echo esc_html( $berechnungen['brennerstunden'] ); ?>
						</td>
						<td class="column-oil">
							<?php echo esc_html( $berechnungen['oelkosten'] ); ?>
						</td>
						<td class="column-members">
							<?php echo esc_html( $berechnungen['mitglieder'] ); ?>
						</td>
						<td class="column-guests">
							<?php echo esc_html( $berechnungen['gaeste'] ); ?>
						</td>
						<td class="column-total">
							<?php echo esc_html( $berechnungen['gesamt'] ); ?>
						</td>
						<td class="column-actions">
							<a href="<?php echo esc_url( $berechnungen['edit_url'] ); ?>" class="button button-small">
								<?php esc_html_e( 'Bearbeiten', 'wue-nutzerabrechnung' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th><?php esc_html_e( 'Gesamt', 'wue-nutzerabrechnung' ); ?></th>
					<th class="column-brenner"><?php echo esc_html( $sums['brennerstunden'] ); ?></th>
					<th class="column-oil"><?php echo esc_html( $sums['oelkosten'] ); ?></th>
					<th class="column-members"><?php echo esc_html( $sums['mitglieder'] ); ?></th>
					<th class="column-guests"><?php echo esc_html( $sums['gaeste'] ); ?></th>
					<th class="column-total" colspan="2"><?php echo esc_html( $sums['gesamt'] ); ?></th>
				</tr>
			</tfoot>
		</table>
	<?php endif; ?>
</div>