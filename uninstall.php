<?php
/**
 * Deinstallation.
 *
 * Wird nur beim Loeschen des Plugins ausgefuehrt, nicht beim Deaktivieren.
 * Ohne die Konstante wurde die Datei direkt aufgerufen und darf nichts tun.
 *
 * @package Unternehmensdaten
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Entfernt alle Optionen des Plugins auf der aktuellen Website.
 *
 * Das Modul-Register wird bewusst nicht geladen: die Liste steht hier
 * ausgeschrieben, damit auch dann restlos aufgeraeumt wird, wenn ein Modul
 * zwischenzeitlich per Filter entfernt wurde.
 *
 * @return void
 */
function undt_uninstall_site() {
	$settings = get_option( 'undt_settings', array() );

	// Wer die Daten behalten will, hat das ausdruecklich eingestellt.
	if ( is_array( $settings ) && ! empty( $settings['keep_data'] ) ) {
		return;
	}

	$options = array(
		'undt_profile',
		'undt_company',
		'undt_setup_done',
		'undt_settings',
		'undt_hours',
		'undt_prices',
		'undt_social',
		'undt_faq',
		'undt_banner',
		'undt_seo',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

if ( is_multisite() ) {
	$undt_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $undt_sites as $undt_site_id ) {
		switch_to_blog( (int) $undt_site_id );
		undt_uninstall_site();
		restore_current_blog();
	}
} else {
	undt_uninstall_site();
}

/*
 * Der Zwischenspeicher des Updaters gilt fuer das ganze Netzwerk und enthaelt
 * keine Angaben des Unternehmens. Er wird deshalb unabhaengig von keep_data
 * entfernt, und nur einmal statt je Website.
 *
 * Laeuft die Deinstallation im selben Aufruf wie die Deaktivierung, etwa bei
 * wp plugin uninstall --deactivate, haengt der Updater noch an update_plugins.
 * WordPress schreibt diesen Transient nach dem Loeschen neu, und der Updater
 * wuerde dabei erneut bei GitHub anfragen, seinen Zwischenspeicher wieder
 * anlegen und das geloeschte Plugin in die Liste der Aktualisierungen
 * zuruecktragen. Deshalb wird er zuerst abgehaengt.
 */
remove_filter( 'pre_set_site_transient_update_plugins', array( 'UNDT_Updater', 'inject' ) );
delete_site_transient( 'undt_update_info' );
