<?php
/**
 * Admin-Funktionalitäten für die Nutzerabrechnung
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin-Klasse für die Nutzerabrechnung
 */
class WUE_Admin {

    /**
     * Konstruktor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'init_admin' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
        add_action( 'wp_ajax_wue_update_dashboard', array( $this, 'ajax_update_dashboard' ) );
    }

    /**
     * Initialisiert Admin-Funktionalitäten
     */
    public function init_admin() {
        wp_register_style( 
            'wue-admin-style', 
            WUE_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            WUE_VERSION 
        );
        wp_enqueue_style( 'wue-admin-style' );
    }

    /**
     * Plugin aktivieren und Datenbanktabellen erstellen
     */
    public function activate_plugin() {
        $this->create_database_tables();
        $this->insert_default_prices();
        flush_rewrite_rules();
    }

    /**
     * Erstellt die erforderlichen Datenbanktabellen
     */
    private function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql_preise = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wue_preise (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            jahr int NOT NULL,
            oelpreis_pro_liter decimal(10,2) NOT NULL,
            uebernachtung_mitglied decimal(10,2) NOT NULL,
            uebernachtung_gast decimal(10,2) NOT NULL,
            verbrauch_pro_brennerstunde decimal(10,2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY jahr (jahr)
        ) $charset_collate;";

        $sql_aufenthalte = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wue_aufenthalte (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            mitglied_id bigint(20) NOT NULL,
            ankunft date NOT NULL,
            abreise date NOT NULL,
            brennerstunden_start decimal(10,2) NOT NULL,
            brennerstunden_ende decimal(10,2) NOT NULL,
            anzahl_mitglieder int NOT NULL,
            anzahl_gaeste int NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mitglied_id (mitglied_id),
            KEY ankunft (ankunft)
        ) $charset_collate;";

        $sql_tankfuellungen = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wue_tankfuellungen (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            datum date NOT NULL,
            liter decimal(10,2) NOT NULL,
            preis_pro_liter decimal(10,2) NOT NULL,
            brennerstunden_stand decimal(10,2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY datum (datum)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_preise );
        dbDelta( $sql_aufenthalte );
        dbDelta( $sql_tankfuellungen );
    }

    /**
     * Fügt Standardpreise für das aktuelle Jahr ein
     */
    private function insert_default_prices() {
        global $wpdb;
        $current_year = date('Y');
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wue_preise WHERE jahr = %d",
            $current_year
        ));

        if (!$exists) {
            $wpdb->insert(
                $wpdb->prefix . 'wue_preise',
                array(
                    'jahr' => $current_year,
                    'oelpreis_pro_liter' => 1.00,
                    'uebernachtung_mitglied' => 10.00,
                    'uebernachtung_gast' => 15.00,
                    'verbrauch_pro_brennerstunde' => 2.50
                ),
                array('%d', '%f', '%f', '%f', '%f')
            );
        }
    }

    /**
     * Fügt Admin-Menüpunkte hinzu
     */
    public function add_admin_menu() {
        // Hauptmenü
        add_menu_page(
            __( 'Nutzerabrechnung', 'wue-nutzerabrechnung' ),
            __( 'Nutzerabrechnung', 'wue-nutzerabrechnung' ),
            'read',
            'wue-nutzerabrechnung',
            array( $this, 'display_admin_page' ),
            'dashicons-chart-area'
        );

        // Untermenü für Aufenthaltserfassung
        add_submenu_page(
            'wue-nutzerabrechnung',
            __( 'Aufenthalt erfassen', 'wue-nutzerabrechnung' ),
            __( 'Aufenthalt erfassen', 'wue-nutzerabrechnung' ),
            'read',
            'wue-aufenthalt-erfassen',
            array( $this, 'display_aufenthalt_form' )
        );

        // Untermenü für Tankfüllungen
        add_submenu_page(
            'wue-nutzerabrechnung',
            __( 'Tankfüllung erfassen', 'wue-nutzerabrechnung' ),
            __( 'Tankfüllung erfassen', 'wue-nutzerabrechnung' ),
            'read',
            'wue-tankfuellungen',
            array( $this, 'display_tankfuellung_form' )
        );

        // Preiskonfiguration (nur für Administratoren)
        add_submenu_page(
            'wue-nutzerabrechnung',
            __( 'Preiskonfiguration', 'wue-nutzerabrechnung' ),
            __( 'Preiskonfiguration', 'wue-nutzerabrechnung' ),
            'manage_options',
            'wue-nutzerabrechnung-preise',
            array( $this, 'display_price_settings' )
        );
    }

    /**
     * Fügt das Dashboard Widget hinzu
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'wue_nutzerabrechnung_widget',
            __( 'Meine Aufenthalte und Kosten', 'wue-nutzerabrechnung' ),
            array( $this, 'display_dashboard_widget' )
        );
    }

    /**
     * Zeigt das Dashboard Widget an
     */
    public function display_dashboard_widget() {
        // Sicherstellen, dass der aktuelle Benutzer berechtigt ist
    if (!is_user_logged_in()) {
        return;
    }

     // Erfolgsmeldung prüfen und anzeigen
     $message = get_transient('wue_aufenthalt_message');
     if ($message === 'success') {
         delete_transient('wue_aufenthalt_message');
         echo '<div class="notice notice-success is-dismissible"><p>' . 
              esc_html__('Aufenthalt wurde erfolgreich gespeichert.', 'wue-nutzerabrechnung') . 
              '</p></div>';
     }
    
    $user_id = get_current_user_id();
    
    // Jahr aus der URL oder Cookie holen, sonst aktuelles Jahr
    $year = isset($_REQUEST['wue_year']) ? intval($_REQUEST['wue_year']) : date('Y');
    
    // Daten für das Widget vorbereiten
    $available_years = $this->get_available_years($user_id);
    $aufenthalte = $this->get_user_aufenthalte($user_id, $year);
    
    // Variablen für das Template
    $current_year = $year;
    
    include WUE_PLUGIN_PATH . 'templates/dashboard-widget.php';
}

    /**
     * Zeigt die Admin-Hauptseite an
     */
    public function display_admin_page() {
        // Prüfen auf Erfolgsmeldung
        $message = get_transient('wue_aufenthalt_message');
        if ($message === 'success') {
            delete_transient('wue_aufenthalt_message');
            add_settings_error(
                'wue_aufenthalt',
                'save_success',
                __('Aufenthalt wurde erfolgreich gespeichert.', 'wue-nutzerabrechnung'),
                'success'
            );
        }
    
        $yearly_stats = $this->get_yearly_statistics();
        $current_prices = $this->get_current_prices();
        include WUE_PLUGIN_PATH . 'templates/admin-page.php';
    }

    /**
     * Zeigt das Formular zur Aufenthaltserfassung oder -bearbeitung an
     */
    public function display_aufenthalt_form() {
        if (!is_user_logged_in()) {
            wp_die(__('Sie müssen angemeldet sein, um diese Seite aufzurufen.', 'wue-nutzerabrechnung'));
        }
 // Initialisiere Variablen
 $aufenthalt = null;
 $aufenthalt_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
 $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';

 // Wenn ein Aufenthalt bearbeitet werden soll
 if ($action === 'edit' && $aufenthalt_id > 0) {
    $aufenthalt = $this->get_aufenthalt($aufenthalt_id);
    
    // Prüfe, ob der Aufenthalt existiert und Benutzer Zugriffsrechte hat
    if (!$aufenthalt || (!current_user_can('manage_options') && $aufenthalt->mitglied_id !== get_current_user_id())) {
        wp_die(__('Sie haben keine Berechtigung, diesen Aufenthalt zu bearbeiten.', 'wue-nutzerabrechnung'));
    }
}

 // Verarbeite Formularübermittlung
 if (isset($_POST['submit']) && check_admin_referer('wue_save_aufenthalt')) {
     $this->save_aufenthalt($aufenthalt_id);
 }

 // Zeige Erfolgsmeldung nach Redirect
 if (isset($_GET['message']) && $_GET['message'] === 'success') {
     add_settings_error(
         'wue_aufenthalt',
         'save_success',
         __('Aufenthalt wurde erfolgreich gespeichert.', 'wue-nutzerabrechnung'),
         'success'
     );
 }

 // Zeige das Formular
 include WUE_PLUGIN_PATH . 'templates/aufenthalt-form.php';
}
/**
     * Speichert oder aktualisiert einen Aufenthalt
     *
     * @param int $aufenthalt_id Optional. Die ID des zu aktualisierenden Aufenthalts
     */
    private function save_aufenthalt($aufenthalt_id = 0) {
        global $wpdb;
        
        $aufenthalt = isset($_POST['wue_aufenthalt']) ? $_POST['wue_aufenthalt'] : array();

        // Validierung der Datumsangaben
        $ankunft = sanitize_text_field($aufenthalt['ankunft']);
        $abreise = sanitize_text_field($aufenthalt['abreise']);
        
        if (strtotime($abreise) <= strtotime($ankunft)) {
            add_settings_error(
                'wue_aufenthalt',
                'invalid_dates',
                __('Das Abreisedatum muss nach dem Ankunftsdatum liegen.', 'wue-nutzerabrechnung'),
                'error'
            );
            return;
        }

        // Validierung der Brennerstunden
        $brennerstunden_start = floatval($aufenthalt['brennerstunden_start']);
        $brennerstunden_ende = floatval($aufenthalt['brennerstunden_ende']);
        
        if ($brennerstunden_ende <= $brennerstunden_start) {
            add_settings_error(
                'wue_aufenthalt',
                'invalid_brennerstunden',
                __('Die Brennerstunden bei Abreise müssen höher sein als bei Ankunft.', 'wue-nutzerabrechnung'),
                'error'
            );
            return;
        }

        $data = array(
            'mitglied_id' => get_current_user_id(),
            'ankunft' => $ankunft,
            'abreise' => $abreise,
            'brennerstunden_start' => $brennerstunden_start,
            'brennerstunden_ende' => $brennerstunden_ende,
            'anzahl_mitglieder' => intval($aufenthalt['anzahl_mitglieder']),
            'anzahl_gaeste' => intval($aufenthalt['anzahl_gaeste'])
        );

        // Update oder Insert basierend auf aufenthalt_id
        if ($aufenthalt_id > 0) {
            $result = $wpdb->update(
                $wpdb->prefix . 'wue_aufenthalte',
                $data,
                array('id' => $aufenthalt_id),
                array('%d', '%s', '%s', '%f', '%f', '%d', '%d'),
                array('%d')
            );
        } else {
            $result = $wpdb->insert(
                $wpdb->prefix . 'wue_aufenthalte',
                $data,
                array('%d', '%s', '%s', '%f', '%f', '%d', '%d')
            );
        }

        if ($result === false) {
            add_settings_error(
                'wue_aufenthalt',
                'save_error',
                __('Fehler beim Speichern des Aufenthalts.', 'wue-nutzerabrechnung'),
                'error'
            );
            return false;
        }
        
        // Setze die Erfolgsmeldung
    set_transient('wue_aufenthalt_message', 'success', 30);
    
    // Leite zurück zum Dashboard
    $redirect_to = admin_url('index.php');
    
    echo '<script>window.location.href = "' . esc_url($redirect_to) . '";</script>';
    exit;
    }
    

    /**
     * Zeigt das Formular zur Tankfüllungserfassung an
     */
    public function display_tankfuellung_form() {
        if (!is_user_logged_in()) {
            wp_die(__('Sie müssen angemeldet sein, um diese Seite aufzurufen.', 'wue-nutzerabrechnung'));
        }

        // Verarbeite Formularübermittlung
        if (isset($_POST['submit']) && check_admin_referer('wue_save_tankfuellung')) {
            $this->save_tankfuellung();
        }

        // Zeige Erfolgsmeldung nach Redirect
        if (isset($_GET['message']) && $_GET['message'] === 'success') {
            add_settings_error(
                'wue_tankfuellung',
                'save_success',
                __('Tankfüllung wurde erfolgreich gespeichert.', 'wue-nutzerabrechnung'),
                'success'
            );
        }

        include WUE_PLUGIN_PATH . 'templates/tankfuellung-form.php';
    }

    /**
     * Speichert eine neue Tankfüllung
     */
    private function save_tankfuellung() {
        global $wpdb;
        
        $tankfuellung = isset($_POST['wue_tankfuellung']) ? $_POST['wue_tankfuellung'] : array();

        // Validiere und bereinige die Eingaben
        $datum = sanitize_text_field($tankfuellung['datum']);
        $liter = floatval($tankfuellung['liter']);
        $preis_pro_liter = floatval($tankfuellung['preis_pro_liter']);
        $brennerstunden_stand = floatval($tankfuellung['brennerstunden_stand']);

        // Grundlegende Validierung
        if ($liter <= 0 || $preis_pro_liter <= 0) {
            add_settings_error(
                'wue_tankfuellung',
                'invalid_values',
                __('Bitte geben Sie gültige Werte für Liter und Preis ein.', 'wue-nutzerabrechnung'),
                'error'
            );
            return;
        }

        // Speichere die Daten
        $result = $wpdb->insert(
            $wpdb->prefix . 'wue_tankfuellungen',
            array(
                'datum' => $datum,
                'liter' => $liter,
                'preis_pro_liter' => $preis_pro_liter,
                'brennerstunden_stand' => $brennerstunden_stand
            ),
            array('%s', '%f', '%f', '%f')
        );

        if ($result === false) {
            add_settings_error(
                'wue_tankfuellung',
                'save_error',
                __('Fehler beim Speichern der Tankfüllung.', 'wue-nutzerabrechnung'),
                'error'
            );
        } else {
            // Redirect mit Erfolgsmeldung
            $redirect_url = add_query_arg(
                array(
                    'page' => 'wue-tankfuellungen',
                    'message' => 'success'
                ),
                admin_url('admin.php')
            );
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Zeigt die Preiskonfiguration an
     */
    public function display_price_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Sie haben nicht die erforderlichen Berechtigungen für diese Aktion.', 'wue-nutzerabrechnung'));
        }

        if (isset($_POST['wue_save_prices']) && check_admin_referer('wue_save_prices')) {
            $this->save_price_settings();
        }

        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $prices = $this->get_prices_for_year($year);

        include WUE_PLUGIN_PATH . 'templates/price-settings.php';
    }

    /**
     * Speichert die Preiseinstellungen
     */
    private function save_price_settings() {
        global $wpdb;

        if (!current_user_can('manage_options')) {
            wp_die(__('Sie haben nicht die erforderlichen Berechtigungen für diese Aktion.', 'wue-nutzerabrechnung'));
        }

        $year = isset($_POST['wue_year']) ? intval($_POST['wue_year']) : date('Y');
        $prices = isset($_POST['wue_prices']) ? map_deep($_POST['wue_prices'], 'floatval') : array();

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wue_preise WHERE jahr = %d",
            $year
        ));

        if ($existing) {
            $result = $wpdb->update(
                $wpdb->prefix . 'wue_preise',
                array(
                    'oelpreis_pro_liter' => $prices['oelpreis_pro_liter'],
                    'uebernachtung_mitglied' => $prices['uebernachtung_mitglied'],
                    'uebernachtung_gast' => $prices['uebernachtung_gast'],
                    'verbrauch_pro_brennerstunde' => $prices['verbrauch_pro_brennerstunde']
                ),
                array('jahr' => $year),
                array('%f', '%f', '%f', '%f'),
                array('%d')
            );
        } else {
            $result = $wpdb->insert(
                $wpdb->prefix . 'wue_preise',
                array(
                    'jahr' => $year,
                    'oelpreis_pro_liter' => $prices['oelpreis_pro_liter'],
                    'uebernachtung_mitglied' => $prices['uebernachtung_mitglied'],
                    'uebernachtung_gast' => $prices['uebernachtung_gast'],
                    'verbrauch_pro_brennerstunde' => $prices['verbrauch_pro_brennerstunde']
                ),
                array('%d', '%f', '%f', '%f', '%f')
            );
        }

        if ($result === false) {
            add_settings_error(
                'wue_prices',
                'save_error',
                __('Fehler beim Speichern der Preise.', 'wue-nutzerabrechnung'),
                'error'
            );
        } else {
            add_settings_error(
                'wue_prices',
                'save_success',
                __('Preise wurden erfolgreich gespeichert.', 'wue-nutzerabrechnung'),
                'success'
            );
        }
    }

    /**
     * Holt die Statistiken für das aktuelle Jahr
     */
    private function get_yearly_statistics() {
        global $wpdb;
        $current_year = date('Y');

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(brennerstunden_ende - brennerstunden_start) as total_brennerstunden,
                SUM(anzahl_mitglieder) as member_nights,
                SUM(anzahl_gaeste) as guest_nights
            FROM {$wpdb->prefix}wue_aufenthalte 
            WHERE YEAR(ankunft) = %d",
            $current_year
        ), ARRAY_A);

        if (!$stats) {
            return array(
                'total_brennerstunden' => 0,
                'member_nights' => 0,
                'guest_nights' => 0,
                'oil_consumption' => 0
            );
        }

        $prices = $this->get_prices_for_year($current_year);
        $stats['oil_consumption'] = $stats['total_brennerstunden'] * ($prices ? $prices->verbrauch_pro_brennerstunde : 0);

        return $stats;
    }

    /**
     * Holt die aktuellen Preise
     */
    private function get_current_prices() {
        $prices = $this->get_prices_for_year(date('Y'));
        
        return array(
            'oil_price' => $prices ? $prices->oelpreis_pro_liter : 0,
            'member_price' => $prices ? $prices->uebernachtung_mitglied : 0,
            'guest_price' => $prices ? $prices->uebernachtung_gast : 0
        );
    }

    /**
     * Holt die Preise für ein bestimmtes Jahr
     *
     * @param int $year Das gewünschte Jahr
     * @return object|null Die Preisdaten oder null
     */
    private function get_prices_for_year($year) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wue_preise WHERE jahr = %d",
            $year
        ));
    }

    /**
     * Holt die Aufenthalte eines Benutzers
     *
     * @param int $user_id Die ID des Benutzers
     * @param int $year Das Jahr der Aufenthalte
     * @return array Array mit Aufenthaltsdaten
     */
    private function get_user_aufenthalte($user_id, $year) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, 
                DATEDIFF(a.abreise, a.ankunft) as tage,
                (a.brennerstunden_ende - a.brennerstunden_start) as brennerstunden,
                p.oelpreis_pro_liter,
                p.uebernachtung_mitglied,
                p.uebernachtung_gast,
                p.verbrauch_pro_brennerstunde
            FROM {$wpdb->prefix}wue_aufenthalte a
            LEFT JOIN {$wpdb->prefix}wue_preise p ON YEAR(a.ankunft) = p.jahr
            WHERE a.mitglied_id = %d 
            AND YEAR(a.ankunft) = %d
            ORDER BY a.ankunft DESC",
            $user_id,
            $year
        ));
    }

    /**
     * Holt einen einzelnen Aufenthalt
     *
     * @param int $id Die ID des Aufenthalts
     * @return object|null Aufenthaltsdaten oder null
     */
    private function get_aufenthalt($id) {
        global $wpdb;
        
        $aufenthalt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wue_aufenthalte WHERE id = %d",
            $id
        ));
        
        // Zugriff erlauben wenn: Admin oder eigener Aufenthalt
        if ($aufenthalt && (current_user_can('manage_options') || $aufenthalt->mitglied_id === get_current_user_id())) {
            return $aufenthalt;
        }
        
        return null;
    }

    /**
     * Ermittelt die verfügbaren Jahre für einen Benutzer
     *
     * @param int $user_id Die ID des Benutzers
     * @return array Array mit verfügbaren Jahren
     */
    private function get_available_years($user_id) {
        global $wpdb;
        
        $years = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT YEAR(ankunft) as jahr 
            FROM {$wpdb->prefix}wue_aufenthalte 
            WHERE mitglied_id = %d 
            ORDER BY jahr DESC",
            $user_id
        ));
        
        if (empty($years)) {
            $years[] = date('Y');
        }
        
        return $years;
    }

    /**
     * AJAX: Aktualisiert das Dashboard
     */
    public function ajax_update_dashboard() {
        check_ajax_referer('wue_dashboard', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Nicht berechtigt');
            return;
        }
        
        if (isset($_POST['year'])) {
            set_transient('wue_dashboard_year_' . get_current_user_id(), intval($_POST['year']), DAY_IN_SECONDS);
            wp_send_json_success();
        }
        
        wp_send_json_error('Ungültige Anfrage');
    }
}