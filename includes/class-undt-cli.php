<?php
/**
 * Befehle fuer WP-CLI.
 *
 * Gedacht fuer den Fall, dass dieselben Angaben auf mehreren Websites landen
 * sollen: `wp undt export > firma.json` auf der einen, `wp undt import
 * firma.json` auf der naechsten.
 *
 * Die Sicherung geht bewusst nach STDOUT statt in eine Datei. So entscheidet
 * die Umleitung, wohin sie gehoert, und das Plugin schreibt nirgends ins
 * Dateisystem.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verwaltet die Unternehmensdaten.
 */
final class UNDT_Cli {

	/**
	 * Meldet die Befehle bei WP-CLI an.
	 *
	 * @return void
	 */
	public static function register() {
		WP_CLI::add_command( 'undt', __CLASS__ );
	}

	/**
	 * Gibt alle Angaben als JSON aus.
	 *
	 * ## EXAMPLES
	 *
	 *     # Sicherung anlegen
	 *     $ wp undt export > firma.json
	 *
	 * @param array $args       Stellungsargumente.
	 * @param array $assoc_args Benannte Argumente.
	 * @return void
	 */
	public function export( $args, $assoc_args ) {
		unset( $args, $assoc_args );

		WP_CLI::line( (string) wp_json_encode( UNDT_Transfer::export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Spielt eine Sicherung ein.
	 *
	 * ## OPTIONS
	 *
	 * <datei>
	 * : Pfad zu einer Datei aus `wp undt export`.
	 *
	 * [--yes]
	 * : Ohne Rueckfrage einspielen.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp undt import firma.json
	 *
	 * @param array $args       Stellungsargumente.
	 * @param array $assoc_args Benannte Argumente.
	 * @return void
	 */
	public function import( $args, $assoc_args ) {
		$datei = isset( $args[0] ) ? (string) $args[0] : '';

		if ( ! is_readable( $datei ) ) {
			WP_CLI::error( sprintf( 'Die Datei %s ist nicht lesbar.', $datei ) );
		}

		$raw = file_get_contents( $datei ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- oertliche Datei, ausdruecklich angegeben.

		if ( false === $raw ) {
			WP_CLI::error( sprintf( 'Die Datei %s liess sich nicht lesen.', $datei ) );
		}

		$payload = json_decode( (string) $raw, true, 32 );

		if ( ! is_array( $payload ) ) {
			WP_CLI::error( 'Die Datei ist kein gültiges JSON.' );
		}

		WP_CLI::confirm( 'Die vorhandenen Angaben dieses Plugins werden überschrieben. Fortfahren?', $assoc_args );

		$report = UNDT_Transfer::import( $payload );

		if ( is_wp_error( $report ) ) {
			WP_CLI::error( $report->get_error_message() );
		}

		foreach ( $report['imported'] as $option ) {
			WP_CLI::log( 'Eingespielt: ' . $option );
		}

		foreach ( $report['ignored'] as $option ) {
			WP_CLI::warning( 'Übergangen: ' . $option );
		}

		WP_CLI::success( UNDT_Transfer::summary( $report ) );
	}
}
