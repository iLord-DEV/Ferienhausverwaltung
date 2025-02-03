<?php
/**
 * Core-Funktionen für das Plugin
 *
 * @package WueNutzerabrechnung
 */

defined( 'ABSPATH' ) || exit;

/**
 * Berechtigungen für das Bearbeiten von Aufenthalten hinzufügen
 */
function wue_add_capabilities() {
	// Berechtigungen für Mitglieder (Subscriber)
	$subscriber = get_role( 'subscriber' );
	if ( $subscriber ) {
		$subscriber->add_cap( 'wue_manage_stays' );        // Aufenthalte verwalten
		$subscriber->add_cap( 'wue_manage_fuel' );         // Tankfüllungen verwalten
		$subscriber->add_cap( 'wue_view_own_stats' );      // Eigene Statistiken sehen

		// Alte Berechtigung entfernen
		$subscriber->remove_cap( 'edit_aufenthalt' );
	}

	// Berechtigungen für Administratoren
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'wue_manage_stays' );     // Aufenthalte verwalten
		$admin->add_cap( 'wue_manage_fuel' );       // Tankfüllungen verwalten
		$admin->add_cap( 'wue_manage_prices' );     // Preise verwalten
		$admin->add_cap( 'wue_view_all_stats' );    // Alle Statistiken sehen
		$admin->add_cap( 'wue_export_data' );       // Datenexport

		// Alte Berechtigung entfernen
		$admin->remove_cap( 'edit_aufenthalt' );
	}
}

/**
 * Prüft, ob der aktuelle Benutzer die Berechtigung hat, einen Aufenthalt zu bearbeiten
 *
 * @param WUE_Aufenthalt $aufenthalt Der Aufenthalt
 *
 * @return bool
 */
function wue_check_aufenthalt_permission( $aufenthalt ) {
	return current_user_can( 'wue_manage_stays' ) &&
			( current_user_can( 'wue_view_all_stats' ) ||
			(int) get_current_user_id() === (int) $aufenthalt->mitglied_id );
}

/**
 * Textdomain laden
 */
function wue_load_textdomain() {
	load_plugin_textdomain(
		'wue-nutzerabrechnung',
		false,
		dirname( plugin_basename( WUE_PLUGIN_FILE ) ) . '/languages/'
	);
}
add_action( 'init', 'wue_load_textdomain' );

// Hook für die Capabilities bei der Plugin-Aktivierung
register_activation_hook( WUE_PLUGIN_FILE, 'wue_add_capabilities' );
