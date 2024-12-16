<?php
/**
 * Dashboard-Funktionalitäten für die Nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WUE_Dashboard
 *
 * Handles the dashboard functionalities for user billing.
 *
 * @package WueNutzerabrechnung
 */
class WUE_Dashboard {
	/**
	 * Database instance.
	 *
	 * @var WUE_DB
	 */
	private $db;

	/**
	 * WUE_Dashboard constructor.
	 */
	public function __construct() {
		$this->db = new WUE_DB();
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
	}

	/**
	 * Adds a dashboard widget for user billing.
	 */
	public function add_dashboard_widgets() {
		wp_add_dashboard_widget(
			'wue_nutzerabrechnung_widget',
			__( 'Meine Aufenthalte und Kosten', 'wue-nutzerabrechnung' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Renders the dashboard widget for user billing.
	 */
	public function render_dashboard_widget() {
		if ( ! is_user_logged_in() ) {
				return;
		}

		$user_id = get_current_user_id();

		$current_year = isset( $_GET['wue_year'] ) ? intval( $_GET['wue_year'] ) : gmdate( 'Y' );

		// Daten laden.
		$aufenthalte     = $this->db->get_user_aufenthalte( $user_id, $current_year );
		$available_years = $this->db->get_available_years( $user_id );

		// Summen berechnen.
		$sums = ( ! empty( $aufenthalte ) )
		? WUE_Helpers::calculate_sums( $aufenthalte )
		: array(
			'brennerstunden' => '0,0',
			'oelkosten'      => '0,00 €',
			'mitglieder'     => '0 Übern. (0,00 €)',
			'gaeste'         => '0 Übern. (0,00 €)',
			'gesamt'         => '0,00 €',
		);

		// Template einbinden.
		include WUE_PLUGIN_PATH . 'templates/dashboard-widget.php';
	}
}
