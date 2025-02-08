<?php
/**
 * Statistik-Funktionalitäten für die Nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 */

class WUE_Statistics {
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
		$this->init_hooks();
	}

	/**
	 * Initialisiert die WordPress Hooks
	 */
	private function init_hooks() {
		add_action( 'wue_admin_menu', array( $this, 'add_menu_items' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Fügt Menüpunkte hinzu
	 */
	public function add_menu_items() {
		add_submenu_page(
			'wue-nutzerabrechnung',
			__( 'Statistiken', 'wue-nutzerabrechnung' ),
			__( 'Statistiken', 'wue-nutzerabrechnung' ),
			'wue_view_all_stats',
			'wue-statistiken',
			array( $this, 'render_statistics_page' )
		);
	}

	/**
	 * Lädt die erforderlichen Scripts und Styles
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'nutzerabrechnung_page_wue-statistiken' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'wue-statistics',
			WUE_PLUGIN_URL . 'assets/js/admin/statistics.js',
			array( 'jquery', 'chart-js' ),
			WUE_VERSION,
			true
		);

		// Statistikdaten für JavaScript bereitstellen
		wp_localize_script(
			'wue-statistics',
			'wueStatistics',
			array(
				'yearlyData'           => $this->get_yearly_comparison_data(),
				'usageDistribution'    => $this->get_usage_distribution_data(),
				'groupSizeCorrelation' => $this->get_group_size_correlation_data(),
			)
		);
	}

	/**
	 * Holt die Verbrauchsdaten im Jahresvergleich
	 */
	private function get_yearly_comparison_data() {
		$year          = gmdate( 'Y' );
		$previous_year = $year - 1;

		$current_year_data  = $this->db->get_monthly_consumption( $year );
		$previous_year_data = $this->db->get_monthly_consumption( $previous_year );

		return array(
			'currentYear'  => $current_year_data,
			'previousYear' => $previous_year_data,
			'year'         => $year,
			'previousYear' => $previous_year,
		);
	}

	/**
	 * Holt die Verbrauchsverteilung (Eigen/Andere/Leerlauf)
	 */
	private function get_usage_distribution_data() {
		$year  = gmdate( 'Y' );
		$stats = $this->db->get_yearly_statistics( $year );

		return array(
			'ownUsage'    => $stats['own_consumption'],
			'othersUsage' => $stats['others_consumption'],
			'idleUsage'   => $stats['absent_consumption'],
		);
	}

	/**
	 * Holt die Korrelation zwischen Gruppengröße und Verbrauch
	 */
	private function get_group_size_correlation_data() {
		return $this->db->get_consumption_by_group_size();
	}

	/**
	 * Rendert die Statistikseite
	 */
	public function render_statistics_page() {
		include WUE_PLUGIN_PATH . 'templates/statistics.php';
	}
}
