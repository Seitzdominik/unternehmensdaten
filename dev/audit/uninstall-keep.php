<?php
/**
 * Zweiter Deinstallationsdurchlauf in einem eigenen Prozess: Plugin erneut
 * installieren, keep_data setzen, loeschen, pruefen, dass die Daten bleiben.
 * Haengt das Ergebnis an result-<UNDT_AUDIT_NAME>.txt an.
 */
define( 'WP_ADMIN', true );

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

global $wpdb;

$undt_name = file_exists( '/wordpress/undt-audit-name.txt' )
	? trim( (string) file_get_contents( '/wordpress/undt-audit-name.txt' ) )
	: ( defined( 'UNDT_AUDIT_NAME' ) ? UNDT_AUDIT_NAME : 'default' );
$file      = 'unternehmensdaten/unternehmensdaten.php';
$out       = array( '', '== Deinstallation mit keep_data=1 (eigener Prozess) ==' );

wp_set_current_user( 1 );
WP_Filesystem();

$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$inst     = $upgrader->install( '/wordpress/build/unternehmensdaten.zip' );
$present  = file_exists( WP_PLUGIN_DIR . '/' . $file );
$out[]    = ( $present ? '  OK   ' : '  FAIL ' ) . 'Erneut installiert (install() lieferte ' . ( is_wp_error( $inst ) ? 'WP_Error: ' . $inst->get_error_message() : var_export( $inst, true ) ) . ')';

// Der Plugin-Cache dieses Prozesses kennt die frisch installierten Dateien noch nicht.
wp_clean_plugins_cache( false );
$out[] = '  INFO Upgrader-Meldungen: ' . implode( ' | ', array_map( 'wp_strip_all_tags', $upgrader->skin->get_upgrade_messages() ) );
$out[] = '  INFO Plugin-Ordner: ' . implode( ', ', array_diff( (array) scandir( WP_PLUGIN_DIR ), array( '.', '..' ) ) ) . ' | get_plugins(): ' . implode( ', ', array_keys( get_plugins() ) );
$res   = activate_plugin( $file );
$out[] = ( null === $res ? '  OK   ' : '  FAIL ' ) . 'Aktiviert' . ( is_wp_error( $res ) ? ': ' . $res->get_error_message() : '' );

update_option( 'undt_company', array( 'company_name' => 'Bleibt GmbH' ) );
$settings              = get_option( 'undt_settings', array() );
$settings              = is_array( $settings ) ? $settings : array();
$settings['keep_data'] = 1;
update_option( 'undt_settings', $settings );

deactivate_plugins( $file );
$del   = delete_plugins( array( $file ) );
$out[] = ( true === $del ? '  OK   ' : '  FAIL ' ) . 'Plugin geloescht (keep_data=1)' . ( is_wp_error( $del ) ? ': ' . $del->get_error_message() : '' );

$company = get_option( 'undt_company' );
$out[]   = ( is_array( $company ) && 'Bleibt GmbH' === $company['company_name'] ? '  OK   ' : '  FAIL ' ) . 'keep_data=1: Daten bleiben nach dem Loeschen erhalten';
$left    = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'undt_%'" );
$out[]   = '  INFO verbliebene Optionen: ' . implode( ', ', $left );

$result = '/wordpress/build/dev/audit/result-' . $undt_name . '.txt';
$prev   = file_exists( $result ) ? file_get_contents( $result ) : '';
file_put_contents( $result, rtrim( $prev ) . "\n" . implode( "\n", $out ) . "\n" );
