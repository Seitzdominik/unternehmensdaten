<?php
/**
 * Angaben sichern und auf eine andere Website uebertragen.
 *
 * Eine Agentur richtet dieselben Bausteine auf vielen Websites ein. Der Export
 * nimmt alle Optionen des Plugins als JSON mit, der Import spielt sie auf der
 * naechsten Website ein und spart die halbe Einrichtung. Vor groesseren
 * Aenderungen ist dieselbe Datei die Sicherung.
 *
 * Eingespielte Daten sind fremde Eingaben. Sie laufen deshalb durch dieselben
 * Sanitisierer wie das Formular, bevor sie eine Option erreichen.
 *
 * Was nur auf der Ursprungsseite gilt, wird beim Einspielen verworfen: die IDs
 * verknuepfter Seiten und des Logos zeigen auf der neuen Website auf etwas
 * anderes oder auf nichts. Eigene Adressen bleiben, sie sind vom Inhaltstyp
 * unabhaengig.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Transfer
 */
final class UNDT_Transfer {

	/**
	 * Kennzeichnet die Datei als Sicherung dieses Plugins.
	 */
	const FORMAT = 'unternehmensdaten';

	/**
	 * Fassung des Dateiaufbaus. Waechst nur, wenn sich der Aufbau aendert.
	 */
	const SCHEMA = 1;

	/**
	 * Groesste angenommene Datei. Alle Optionen zusammen liegen weit darunter.
	 */
	const MAX_BYTES = 2097152;

	/**
	 * Wie lange das Ergebnis eines Imports fuer die Anzeige aufgehoben wird.
	 */
	const REPORT_TTL = 120;

	/**
	 * Haengt sich in die Formulare der Einstellungsseite ein.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_post_undt_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_undt_import', array( __CLASS__, 'handle_import' ) );
	}

	/**
	 * Die Optionen, die zur Sicherung gehoeren.
	 *
	 * Auch die Inhaltsbereiche, die gerade abgeschaltet sind: ihre Daten bleiben
	 * erhalten, also gehoeren sie auch in die Sicherung.
	 *
	 * @return array
	 */
	public static function options() {
		$options = array(
			UNDT_Store::OPTION_PROFILE,
			UNDT_Store::OPTION_COMPANY,
			UNDT_Modules::OPTION_SETTINGS,
		);

		foreach ( UNDT_Modules::all() as $module ) {
			$options[] = $module['option'];
		}

		return $options;
	}

	/**
	 * Alle Angaben als Datensatz.
	 *
	 * @return array
	 */
	public static function export() {
		$data = array();

		foreach ( self::options() as $option ) {
			$value = get_option( $option, array() );

			if ( is_array( $value ) ) {
				$data[ $option ] = $value;
			}
		}

		return array(
			'format'         => self::FORMAT,
			'schema'         => self::SCHEMA,
			'plugin_version' => UNDT_VERSION,
			'exported'       => gmdate( 'c' ),
			'site'           => home_url( '/' ),
			'options'        => $data,
		);
	}

	/**
	 * Der Dateiname der Sicherung.
	 *
	 * @return string
	 */
	public static function filename() {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return sanitize_file_name( 'unternehmensdaten-' . ( '' === $host ? 'export' : $host ) . '-' . gmdate( 'Y-m-d' ) . '.json' );
	}

	/**
	 * Spielt einen Datensatz ein.
	 *
	 * @param mixed $payload Inhalt der Datei.
	 * @return array|WP_Error Bericht oder Fehler.
	 */
	public static function import( $payload ) {
		if ( ! is_array( $payload ) || ! isset( $payload['format'] ) || self::FORMAT !== $payload['format'] ) {
			return new WP_Error( 'undt_format', __( 'Die Datei stammt nicht aus diesem Plugin.', 'unternehmensdaten' ) );
		}

		if ( isset( $payload['schema'] ) && (int) $payload['schema'] > self::SCHEMA ) {
			return new WP_Error( 'undt_schema', __( 'Die Datei stammt aus einer neueren Fassung des Plugins. Bitte zuerst aktualisieren.', 'unternehmensdaten' ) );
		}

		$options = isset( $payload['options'] ) && is_array( $payload['options'] ) ? $payload['options'] : array();

		if ( empty( $options ) ) {
			return new WP_Error( 'undt_empty', __( 'Die Datei enthält keine Angaben.', 'unternehmensdaten' ) );
		}

		$known  = self::options();
		$report = array(
			'imported' => array(),
			'ignored'  => array(),
			'dropped'  => array(),
			'source'   => isset( $payload['site'] ) ? (string) $payload['site'] : '',
		);

		foreach ( $options as $option => $value ) {
			$option = (string) $option;

			if ( ! in_array( $option, $known, true ) || ! is_array( $value ) ) {
				$report['ignored'][] = $option;
				continue;
			}

			$clean = self::sanitize_option( $option, $value );
			$clean = self::strip_local( $option, $clean, $report['dropped'] );

			update_option( $option, $clean );

			$report['imported'][] = $option;
		}

		// Nach einem Import ist das Profil beantwortet, der Hinweis hat sich erledigt.
		if ( in_array( UNDT_Store::OPTION_PROFILE, $report['imported'], true ) ) {
			update_option( UNDT_Store::OPTION_SETUP, 1 );
		}

		UNDT_Store::flush();
		UNDT_Modules::flush();
		UNDT_Content::flush();

		return $report;
	}

	/**
	 * Schickt eine Option durch denselben Sanitisierer wie das Formular.
	 *
	 * @param string $option Optionsname.
	 * @param array  $value  Rohe Werte.
	 * @return array
	 */
	private static function sanitize_option( $option, array $value ) {
		if ( UNDT_Store::OPTION_PROFILE === $option ) {
			return UNDT_Sanitizer::profile( $value );
		}

		if ( UNDT_Store::OPTION_COMPANY === $option ) {
			return UNDT_Sanitizer::company( $value );
		}

		if ( UNDT_Modules::OPTION_SETTINGS === $option ) {
			return UNDT_Modules::sanitize_settings( $value );
		}

		foreach ( UNDT_Modules::all() as $slug => $module ) {
			if ( $module['option'] === $option ) {
				return UNDT_Content::sanitize( $slug, $value );
			}
		}

		return array();
	}

	/**
	 * Entfernt, was nur auf der Ursprungsseite gilt.
	 *
	 * @param string $option  Optionsname.
	 * @param array  $value   Bereits sanitisierte Werte.
	 * @param array  $dropped Sammelt die verworfenen Felder.
	 * @return array
	 */
	private static function strip_local( $option, array $value, array &$dropped ) {
		$fields = array();
		$prefix = '';

		if ( UNDT_Store::OPTION_COMPANY === $option ) {
			$fields = UNDT_Schema::fields();
		} else {
			foreach ( UNDT_Modules::all() as $slug => $module ) {
				if ( $module['option'] === $option ) {
					$fields = $module['fields'];
					$prefix = $slug . '.';
					break;
				}
			}
		}

		foreach ( $fields as $key => $field ) {
			if ( ! isset( $value[ $key ] ) || ! in_array( $field['type'], array( 'page', 'media' ), true ) ) {
				continue;
			}

			// Eine Zahl ist eine ID der anderen Website, eine Adresse gilt ueberall.
			if ( is_numeric( $value[ $key ] ) && (int) $value[ $key ] > 0 ) {
				$value[ $key ] = 0;
				$dropped[]     = $prefix . $key;
			}
		}

		return $value;
	}

	/* ------------------------------------------------------- Formulare --- */

	/**
	 * Gibt die Sicherung als Datei aus.
	 *
	 * @return void
	 */
	public static function handle_export() {
		if ( ! current_user_can( UNDT_Store::capability() ) ) {
			wp_die( esc_html__( 'Dafür fehlt die Berechtigung.', 'unternehmensdaten' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'undt_export' );

		$json = (string) wp_json_encode( self::export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . self::filename() . '"' );
		header( 'Content-Length: ' . strlen( $json ) );

		// Eine JSON-Datei, kein HTML: Escaping wuerde sie unbrauchbar machen.
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}

	/**
	 * Nimmt eine hochgeladene Sicherung entgegen.
	 *
	 * @return void
	 */
	public static function handle_import() {
		if ( ! current_user_can( UNDT_Store::capability() ) ) {
			wp_die( esc_html__( 'Dafür fehlt die Berechtigung.', 'unternehmensdaten' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'undt_import' );

		$result = self::read_upload();

		if ( ! is_wp_error( $result ) ) {
			$result = self::import( $result );
		}

		set_transient(
			self::report_key(),
			is_wp_error( $result )
				? array( 'error' => $result->get_error_message() )
				: $result,
			self::REPORT_TTL
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => UNDT_Admin::SLUG_SETTINGS,
					'undt-import' => is_wp_error( $result ) ? 'fehler' : 'ok',
				),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 * Liest die hochgeladene Datei.
	 *
	 * @return array|WP_Error Inhalt der Datei oder Fehler.
	 */
	private static function read_upload() {
		/*
		 * Die Nonce prueft handle_import(), bevor diese Methode ueberhaupt
		 * aufgerufen wird; ueber die Funktionsgrenze hinweg sieht die statische
		 * Pruefung das nicht. Die einzelnen Werte gehen unten durch ihre eigene
		 * Pruefung, und die Datei selbst ueber is_uploaded_file().
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = isset( $_FILES['undt_file'] ) ? (array) $_FILES['undt_file'] : array();

		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		$tmp   = isset( $file['tmp_name'] ) ? sanitize_text_field( wp_unslash( (string) $file['tmp_name'] ) ) : '';
		$size  = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( UPLOAD_ERR_NO_FILE === $error || '' === $tmp ) {
			return new WP_Error( 'undt_no_file', __( 'Es wurde keine Datei ausgewählt.', 'unternehmensdaten' ) );
		}

		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'undt_upload', __( 'Die Datei konnte nicht hochgeladen werden.', 'unternehmensdaten' ) );
		}

		if ( $size > self::MAX_BYTES || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'undt_upload', __( 'Die Datei ist zu groß oder stammt nicht aus dem Formular.', 'unternehmensdaten' ) );
		}

		$raw = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- oertliche Datei aus dem Upload.

		if ( false === $raw || '' === trim( (string) $raw ) ) {
			return new WP_Error( 'undt_empty', __( 'Die Datei ist leer.', 'unternehmensdaten' ) );
		}

		$payload = json_decode( (string) $raw, true, 32 );

		if ( null === $payload ) {
			return new WP_Error( 'undt_json', __( 'Die Datei ist kein gültiges JSON.', 'unternehmensdaten' ) );
		}

		return is_array( $payload ) ? $payload : new WP_Error( 'undt_json', __( 'Die Datei hat einen unerwarteten Aufbau.', 'unternehmensdaten' ) );
	}

	/**
	 * Der Schluessel, unter dem das Ergebnis bis zur Anzeige liegt.
	 *
	 * Je Benutzer, damit niemand das Ergebnis eines anderen sieht.
	 *
	 * @return string
	 */
	public static function report_key() {
		return 'undt_import_' . get_current_user_id();
	}

	/**
	 * Holt das Ergebnis des letzten Imports und raeumt es weg.
	 *
	 * @return array Leer, wenn keines vorliegt.
	 */
	public static function take_report() {
		$report = get_transient( self::report_key() );

		if ( ! is_array( $report ) ) {
			return array();
		}

		delete_transient( self::report_key() );

		return $report;
	}

	/**
	 * Das Ergebnis eines Imports als Satz.
	 *
	 * @param array $report Bericht aus import().
	 * @return string
	 */
	public static function summary( array $report ) {
		if ( isset( $report['error'] ) ) {
			return (string) $report['error'];
		}

		$text = sprintf(
			/* translators: %d: Anzahl der eingespielten Bereiche. */
			_n( '%d Bereich eingespielt.', '%d Bereiche eingespielt.', count( $report['imported'] ), 'unternehmensdaten' ),
			count( $report['imported'] )
		);

		if ( ! empty( $report['dropped'] ) ) {
			$text .= ' ' . sprintf(
				/* translators: %s: Liste der Feldnamen. */
				__( 'Nicht übernommen wurden die Verknüpfungen zu Seiten und Bildern der anderen Website: %s. Diese bitte neu auswählen.', 'unternehmensdaten' ),
				implode( ', ', $report['dropped'] )
			);
		}

		return $text;
	}
}
