<?php
/**
 * OpenWeather Integration für die Wetterstatistik
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * OpenWeather Handler Klasse
 */
class WUE_OpenWeather {
	const LAT          = 49.8844;
	const LON          = 11.2338;
	const API_BASE_URL = 'https://api.openweathermap.org/data/3.0';

	private $db;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->db = new WUE_DB();
		$this->init_hooks();
	}

	/**
	 * Initialisiert WordPress Hooks
	 */
	private function init_hooks() {
		// Settings im Admin
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );

		// Test-Aktion hinzufügen
		add_action( 'admin_post_test_weather_api', array( $this, 'handle_api_test' ) );
	}

	/**
	 * Registriert die Einstellungen
	 */
	public function register_settings() {
		register_setting( 'wue_weather_settings', 'wue_openweather_api_key' );

		add_settings_section(
			'wue_weather_settings_section',
			__( 'OpenWeather Einstellungen', 'wue-nutzerabrechnung' ),
			null,
			'wue_weather_settings'
		);

		add_settings_field(
			'wue_openweather_api_key',
			__( 'API Key', 'wue-nutzerabrechnung' ),
			array( $this, 'render_api_key_field' ),
			'wue_weather_settings',
			'wue_weather_settings_section'
		);
	}

	/**
	 * Fügt die Einstellungsseite hinzu
	 */
	public function add_settings_page() {
		add_submenu_page(
			'wue-nutzerabrechnung',
			__( 'Wetter-Einstellungen', 'wue-nutzerabrechnung' ),
			__( 'Wetter-Einstellungen', 'wue-nutzerabrechnung' ),
			'manage_options',
			'wue-weather-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Rendert die Einstellungsseite
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'wue_weather_settings',
				'settings_updated',
				__( 'Einstellungen gespeichert.', 'wue-nutzerabrechnung' ),
				'success'
			);
		}

		if ( isset( $_GET['api-tested'] ) ) {
			$temp = get_transient( 'wue_api_test_message' );
			if ( $temp !== false ) {
				add_settings_error(
					'wue_weather_settings',
					'api_test_success',
					sprintf(
						__( 'API Test erfolgreich! Aktuelle Temperatur in Aufseß: %s°C', 'wue-nutzerabrechnung' ),
						round( $temp, 1 )
					),
					'success'
				);
				delete_transient( 'wue_api_test_message' );
			}
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'OpenWeather Einstellungen', 'wue-nutzerabrechnung' ); ?></h1>
			<?php settings_errors(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'wue_weather_settings' );
				do_settings_sections( 'wue_weather_settings' );
				submit_button();
				?>
			</form>
			<?php if ( $this->get_api_key() ) : ?>
				<div class="card">
					<h2><?php echo esc_html__( 'API Test', 'wue-nutzerabrechnung' ); ?></h2>
					<p>
						<?php echo esc_html__( 'Hier können Sie die API-Verbindung testen.', 'wue-nutzerabrechnung' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'wue_test_weather_api' ); ?>
						<input type="hidden" name="action" value="test_weather_api">
						<?php submit_button( __( 'API testen', 'wue-nutzerabrechnung' ), 'secondary' ); ?>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Rendert das API Key Eingabefeld
	 */
	public function render_api_key_field() {
		$api_key = $this->get_api_key();
		?>
		<input type="text" 
				name="wue_openweather_api_key" 
				value="<?php echo esc_attr( $api_key ); ?>" 
				class="regular-text"
				placeholder="OpenWeather API Key eingeben">
		<p class="description">
			<?php _e( 'Den API Key von OpenWeather hier eingeben.', 'wue-nutzerabrechnung' ); ?>
		</p>
		<?php
	}

	/**
	 * Holt den API Key aus den WordPress Optionen
	 */
	private function get_api_key() {
		return get_option( 'wue_openweather_api_key' );
	}

	/**
	 * Verarbeitet den API Test
	 */
	public function handle_api_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Nicht erlaubt.', 'wue-nutzerabrechnung' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'wue_test_weather_api' ) ) {
			wp_die( __( 'Sicherheitscheck fehlgeschlagen.', 'wue-nutzerabrechnung' ) );
		}

		// API Test durchführen
		$url = self::API_BASE_URL . '/onecall?' . http_build_query(
			array(
				'lat'   => self::LAT,
				'lon'   => self::LON,
				'appid' => $this->get_api_key(),
				'units' => 'metric',
				'lang'  => 'de',
			)
		);

		error_log( 'OpenWeather API Test URL: ' . $url );

		$response = wp_remote_get( $url );

		error_log( 'OpenWeather API Response: ' . wp_remote_retrieve_body( $response ) );

		if ( is_wp_error( $response ) ) {
			error_log( 'OpenWeather API Error: ' . $response->get_error_message() );
			add_settings_error(
				'wue_weather_settings',
				'api_test_failed',
				sprintf( __( 'API Test fehlgeschlagen: %s', 'wue-nutzerabrechnung' ), $response->get_error_message() ),
				'error'
			);
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ) );

			error_log( 'OpenWeather API Status Code: ' . $code );

			if ( $code === 200 && isset( $body->current ) ) {
				set_transient( 'wue_api_test_message', $body->current->temp, 30 );
				wp_redirect(
					add_query_arg(
						array(
							'page'       => 'wue-weather-settings',
							'api-tested' => 'true',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			} else {
				add_settings_error(
					'wue_weather_settings',
					'api_test_failed',
					sprintf( __( 'API Test fehlgeschlagen. Status: %s', 'wue-nutzerabrechnung' ), $code ),
					'error'
				);
				wp_redirect(
					add_query_arg(
						array( 'page' => 'wue-weather-settings' ),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
		}
	}
}