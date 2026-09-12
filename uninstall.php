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
