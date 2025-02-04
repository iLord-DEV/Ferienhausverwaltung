<?php
/**
 * Admin-Funktionalitäten für die Nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 * @param string $hook The current admin page hook.
 * @param string $hook The current admin page hook.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin-Klasse für die Nutzerabrechnung
 */
class WUE_Admin {
	/**
	 * Database instance
	 *
	 * @var WUE_DB
	 */
	private $db;

	/**
	 * Konstruktor
	 */
	public function __construct() {
		$this->db = new WUE_DB();
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'init_admin' ) );
	}

	/**
	 * Initialisiert Admin-Funktionalitäten
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function init_admin( $hook ) {
		// Nur auf Plugin-Seiten oder Dashboard laden
		if ( false === strpos( $hook, 'wue-' ) && 'index.php' !== $hook ) {
			return;
		}

		// Tailwind Styles
		wp_enqueue_style(
			'wue-tailwind-styles',
			WUE_PLUGIN_URL . 'assets/css/dist/main.css',
			array(),
			WUE_VERSION
		);

		// Bestehende Admin Styles
		wp_register_style(
			'wue-admin-style',
			WUE_PLUGIN_URL . 'assets/css/admin.css',
			array( 'wue-tailwind-styles' ), // Tailwind als Abhängigkeit
			WUE_VERSION
		);
		wp_enqueue_style( 'wue-admin-style' );

		// Dashboard-spezifisches CSS
		$screen = get_current_screen();
		if ( $screen && 'dashboard' === $screen->base ) {
			wp_enqueue_style(
				'wue-dashboard-style',
				WUE_PLUGIN_URL . 'assets/css/dashboard.css',
				array( 'wue-admin-style' ),
				WUE_VERSION
			);
		}
	}

	/**
	 * Plugin aktivieren
	 */
	public function activate_plugin() {
		$this->db->create_tables();
		$this->db->update_overlap_table();
		$this->db->insert_default_prices( gmdate( 'Y' ) );
		wue_add_capabilities();
		flush_rewrite_rules();
	}

	/**
	 * Fügt Admin-Menüpunkte hinzu
	 */
	public function add_admin_menu() {
		// Hauptmenü
		add_menu_page(
			esc_html__( 'Nutzerabrechnung', 'wue-nutzerabrechnung' ),
			esc_html__( 'Nutzerabrechnung', 'wue-nutzerabrechnung' ),
			'read',
			'wue-nutzerabrechnung',
			array( $this, 'display_admin_page' ),
			'dashicons-chart-area'
		);

		// Hook für zusätzliche Menüeinträge
		do_action( 'wue_admin_menu' );
	}

	/**
	 * Zeigt die Admin-Hauptseite an
	 */
	public function display_admin_page() {
		$yearly_stats = $this->db->get_yearly_statistics( gmdate( 'Y' ) );
		$prices       = $this->db->get_prices_for_year( gmdate( 'Y' ) );
		// Berechne Kosten pro Brennerstunde
		$cost_per_hour = $prices->verbrauch_pro_brennerstunde * $prices->oelpreis_pro_liter;

		// Konvertierung ins Array-Format für das Template
		$current_prices = array(
			'oil_price'     => $prices->oelpreis_pro_liter,
			'member_price'  => $prices->uebernachtung_mitglied,
			'guest_price'   => $prices->uebernachtung_gast,
			'cost_per_hour' => $cost_per_hour,
		);

		include WUE_PLUGIN_PATH . 'templates/admin-page.php';
	}







	/**
	 * Fügt ein neues Jahr hinzu
	 */
	private function add_new_year() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
			'wue_add_year'
		) ) {
			wp_die( esc_html__( 'Sicherheitsüberprüfung fehlgeschlagen.', 'wue-nutzerabrechnung' ) );
		}

		$new_year = isset( $_POST['new_year'] ) ? intval( $_POST['new_year'] ) : 0;
		$result   = $this->db->insert_default_prices( $new_year );

		if ( false === $result ) {
			wp_die( esc_html__( 'Fehler beim Hinzufügen des neuen Jahres.', 'wue-nutzerabrechnung' ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wue-nutzerabrechnung-preise',
					'year' => $new_year,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
