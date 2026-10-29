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
 * verknuepfter Seiten und Bilder zeigen auf der neuen Website auf etwas
 * anderes oder auf nichts. Eigene Adressen bleiben, sie sind vom Inhaltstyp
 * unabhaengig.
 *
 * Seit 0.6.0 laeuft das Einspielen auf der Seite in drei Schritten: Datei
 * waehlen (die Seite zeigt je Bereich, was sich aendert), Bereiche auswaehlen,
 * einspielen. Vorher sichert das Plugin den Stand, „Rueckgaengig machen“ spielt
 * ihn zurueck. Die Datei liegt zwischen den Schritten als Transient je
 * Benutzer, nie im Browser des Besuchers.
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
	 * Wie lange eine gewaehlte Datei auf das Einspielen wartet.
	 */
	const PENDING_TTL = 3600;

	/**
	 * Der Stand vor dem letzten Einspielen.
	 */
	const OPTION_BACKUP = 'undt_backup';

	/**
	 * Nonce der Schritte auf der Seite.
	 */
	const NONCE = 'undt_import';

	/**
	 * Haengt sich in die Formulare und Schritte der Seite ein.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_post_undt_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_undt_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'wp_ajax_undt_import_preview', array( __CLASS__, 'ajax_preview' ) );
		add_action( 'wp_ajax_undt_import_run', array( __CLASS__, 'ajax_run' ) );
		add_action( 'wp_ajax_undt_import_undo', array( __CLASS__, 'ajax_undo' ) );
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
		$options = array();

		foreach ( self::areas() as $area ) {
			$options = array_merge( $options, $area['options'] );
		}

		return $options;
	}

	/**
	 * Die Bereiche, die sich einzeln einspielen lassen.
	 *
	 * Firma und Rechtsform gehoeren zusammen: welche Angaben gelten, haengt an
	 * der Rechtsform.
	 *
	 * @return array Schluessel => label, options.
	 */
	public static function areas() {
		$areas = array(
			'company' => array(
				'label'   => __( 'Firma und Rechtsform', 'unternehmensdaten' ),
				'options' => array( UNDT_Store::OPTION_PROFILE, UNDT_Store::OPTION_COMPANY ),
			),
		);

		foreach ( UNDT_Modules::all() as $slug => $module ) {
			$areas[ $slug ] = array(
				'label'   => $module['label'],
				'options' => array( $module['option'] ),
			);
		}

		$areas['settings'] = array(
			'label'   => __( 'Einstellungen', 'unternehmensdaten' ),
			'options' => array( UNDT_Modules::OPTION_SETTINGS ),
		);

		return $areas;
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
	 * Der Dateiname der Sicherung, etwa firmendaten-praxis-muster-2026-10-06.json.
	 *
	 * @return string
	 */
	public static function filename() {
		$name = sanitize_title( (string) UNDT_Store::get( 'company_name' ) );

		if ( '' === $name ) {
			$name = sanitize_title( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		}

		return sanitize_file_name( 'firmendaten-' . ( '' === $name ? 'export' : $name ) . '-' . wp_date( 'Y-m-d' ) . '.json' );
	}

	/**
	 * Prueft, ob ein Datensatz eine Sicherung dieses Plugins ist.
	 *
	 * @param mixed $payload Inhalt der Datei.
	 * @return array|WP_Error Die Optionen der Datei oder ein Fehler.
	 */
	public static function check( $payload ) {
		if ( ! is_array( $payload ) || ! isset( $payload['format'] ) || self::FORMAT !== $payload['format'] ) {
			return new WP_Error( 'undt_format', __( 'Die Datei stammt nicht aus diesem Plugin.', 'unternehmensdaten' ) );
		}

		if ( isset( $payload['schema'] ) && (int) $payload['schema'] > self::SCHEMA ) {
			return new WP_Error( 'undt_schema', __( 'Die Datei stammt aus einer neueren Fassung des Plugins. Aktualisiere es zuerst.', 'unternehmensdaten' ) );
		}

		$options = isset( $payload['options'] ) && is_array( $payload['options'] ) ? $payload['options'] : array();

		if ( empty( $options ) ) {
			return new WP_Error( 'undt_empty', __( 'Die Datei enthält keine Angaben.', 'unternehmensdaten' ) );
		}

		return $options;
	}

	/**
	 * Spielt einen Datensatz ein.
	 *
	 * @param mixed      $payload Inhalt der Datei.
	 * @param array|null $areas   Nur diese Bereiche, siehe areas(); null fuer alle.
	 * @return array|WP_Error Bericht oder Fehler.
	 */
	public static function import( $payload, $areas = null ) {
		$options = self::check( $payload );

		if ( is_wp_error( $options ) ) {
			return $options;
		}

		$known = self::options();

		if ( is_array( $areas ) ) {
			$all   = self::areas();
			$known = array();

			foreach ( $areas as $area ) {
				if ( isset( $all[ $area ] ) ) {
					$known = array_merge( $known, $all[ $area ]['options'] );
				}
			}
		}

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

			$clean = self::clean( $option, $value, $report['dropped'] );

			update_option( $option, $clean );

			$report['imported'][] = $option;
		}

		// Nach einem Import ist das Profil beantwortet, der Hinweis hat sich erledigt.
		if ( in_array( UNDT_Store::OPTION_PROFILE, $report['imported'], true ) ) {
			update_option( UNDT_Store::OPTION_SETUP, 1 );
		}

		self::flush();

		return $report;
	}

	/**
	 * Bereinigt eine Option aus der Datei, wie sie gespeichert wuerde.
	 *
	 * @param string $option  Optionsname.
	 * @param array  $value   Rohe Werte.
	 * @param array  $dropped Sammelt die verworfenen Felder.
	 * @return array
	 */
	private static function clean( $option, array $value, array &$dropped ) {
		return self::strip_local( $option, self::sanitize_option( $option, $value ), $dropped );
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
		list( $fields, $prefix ) = self::fields_for( $option );

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

	/**
	 * Die Felddefinitionen einer Option.
	 *
	 * @param string $option Optionsname.
	 * @return array Felder und Praefix fuer den Bericht.
	 */
	private static function fields_for( $option ) {
		if ( UNDT_Store::OPTION_COMPANY === $option ) {
			return array( UNDT_Schema::fields(), '' );
		}

		if ( UNDT_Store::OPTION_PROFILE === $option ) {
			return array( UNDT_Schema::profile_questions(), '' );
		}

		foreach ( UNDT_Modules::all() as $slug => $module ) {
			if ( $module['option'] === $option ) {
				return array( $module['fields'], $slug . '.' );
			}
		}

		return array( array(), '' );
	}

	/* ------------------------------------------------- Vorschau der Datei --- */

	/**
	 * Was eine Datei je Bereich aendern wuerde.
	 *
	 * Verglichen wird, was nach dem Bereinigen gespeichert wuerde, mit dem, was
	 * jetzt gespeichert ist. So zeigt die Vorschau keine Aenderung an, die der
	 * Sanitizer ohnehin verwirft.
	 *
	 * @param mixed $payload Inhalt der Datei.
	 * @return array|WP_Error source, exported, areas (key, label, state, summary, diff).
	 */
	public static function preview( $payload ) {
		$options = self::check( $payload );

		if ( is_wp_error( $options ) ) {
			return $options;
		}

		$areas = array();

		foreach ( self::areas() as $key => $area ) {
			$changes = array();
			$present = false;

			foreach ( $area['options'] as $option ) {
				if ( ! isset( $options[ $option ] ) || ! is_array( $options[ $option ] ) ) {
					continue;
				}

				$present = true;
				$dropped = array();
				$new     = self::clean( $option, $options[ $option ], $dropped );
				$old     = get_option( $option, array() );
				$changes = array_merge( $changes, self::diff( $option, is_array( $old ) ? $old : array(), $new ) );
			}

			// Die Vorschau hat bereinigt, nicht gespeichert: die Caches zeigen wieder den Stand der Datenbank.
			self::flush();

			if ( ! $present ) {
				$state   = 'missing';
				$summary = __( 'Nicht in der Datei', 'unternehmensdaten' );
			} elseif ( empty( $changes ) ) {
				$state   = 'same';
				$summary = __( 'Gleich wie hier', 'unternehmensdaten' );
			} else {
				$state   = 'change';
				$summary = sprintf(
					/* translators: %d: Anzahl der Angaben. */
					_n( '%d Angabe ändert sich', '%d Angaben ändern sich', count( $changes ), 'unternehmensdaten' ),
					count( $changes )
				);
			}

			$shown = array_slice( $changes, 0, 3 );

			if ( count( $changes ) > 3 ) {
				$shown   = array_slice( $changes, 0, 2 );
				$shown[] = array(
					'k'   => sprintf(
						/* translators: %d: Anzahl weiterer Angaben. */
						__( '… und %d weitere', 'unternehmensdaten' ),
						count( $changes ) - 2
					),
					'old' => '',
					'new' => '',
				);
			}

			$areas[] = array(
				'key'     => $key,
				'label'   => $area['label'],
				'state'   => $state,
				'summary' => $summary,
				'diff'    => $shown,
			);
		}

		$site     = isset( $payload['site'] ) ? (string) wp_parse_url( (string) $payload['site'], PHP_URL_HOST ) : '';
		$exported = isset( $payload['exported'] ) ? strtotime( (string) $payload['exported'] ) : false;

		return array(
			'source'   => $site,
			'exported' => $exported ? wp_date( 'j.n.Y', $exported ) : '',
			'areas'    => $areas,
		);
	}

	/**
	 * Die geaenderten Angaben einer Option.
	 *
	 * @param string $option Optionsname.
	 * @param array  $old    Gespeicherte Werte.
	 * @param array  $new    Werte aus der Datei, bereinigt.
	 * @return array Liste aus k (Bezeichnung), old, new.
	 */
	private static function diff( $option, array $old, array $new ) {
		list( $fields ) = self::fields_for( $option );

		$out  = array();
		$keys = array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) );

		foreach ( $keys as $key ) {
			$before = array_key_exists( $key, $old ) ? $old[ $key ] : null;
			$after  = array_key_exists( $key, $new ) ? $new[ $key ] : null;

			if ( self::same( $before, $after ) || null === $after ) {
				continue;
			}

			$field = isset( $fields[ $key ] ) ? $fields[ $key ] : array();
			$label = isset( $field['label'] ) && '' !== $field['label'] ? $field['label'] : self::key_label( $key );

			$out[] = array(
				'k'   => wp_strip_all_tags( (string) $label ),
				'old' => self::shown( $before, $field ),
				'new' => self::shown( $after, $field ),
			);
		}

		return $out;
	}

	/**
	 * Ob zwei Werte gleich sind, ohne auf Zahl oder Text zu achten.
	 *
	 * @param mixed $a Erster Wert.
	 * @param mixed $b Zweiter Wert.
	 * @return bool
	 */
	private static function same( $a, $b ) {
		if ( is_array( $a ) || is_array( $b ) ) {
			return wp_json_encode( $a ) === wp_json_encode( $b );
		}

		return (string) $a === (string) $b;
	}

	/**
	 * Bezeichnung fuer Schluessel ohne Felddefinition, etwa in den Einstellungen.
	 *
	 * @param string $key Schluessel.
	 * @return string
	 */
	private static function key_label( $key ) {
		$labels = array(
			'modules'   => __( 'Bereiche', 'unternehmensdaten' ),
			'roles'     => __( 'Wer pflegen darf', 'unternehmensdaten' ),
			'keep_data' => __( 'Beim Löschen', 'unternehmensdaten' ),
			'updates'   => __( 'Aktualisierungen', 'unternehmensdaten' ),
		);

		return isset( $labels[ $key ] ) ? $labels[ $key ] : (string) $key;
	}

	/**
	 * Ein Wert, kurz fuer die Vorschau.
	 *
	 * @param mixed $value Wert.
	 * @param array $field Felddefinition, leer wenn unbekannt.
	 * @return string
	 */
	private static function shown( $value, array $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : '';

		if ( null === $value || '' === $value ) {
			return '—';
		}

		// Eine verknuepfte Seite ist eine ID dieser Website, als Zahl sagt sie nichts.
		if ( 'page' === $type ) {
			if ( is_array( $value ) ) {
				return isset( $value['url'] ) && '' !== $value['url'] ? (string) $value['url'] : __( 'Seite verknüpft', 'unternehmensdaten' );
			}

			return (int) $value > 0 ? __( 'Seite verknüpft', 'unternehmensdaten' ) : '—';
		}

		if ( is_array( $value ) ) {
			if ( 'repeater' === $type ) {
				/* translators: %d: Anzahl der Eintraege. */
				return sprintf( _n( '%d Eintrag', '%d Einträge', count( $value ), 'unternehmensdaten' ), count( $value ) );
			}

			return __( 'geändert', 'unternehmensdaten' );
		}

		if ( 'checkbox' === $type ) {
			return $value ? __( 'ja', 'unternehmensdaten' ) : __( 'nein', 'unternehmensdaten' );
		}

		if ( 'select' === $type && isset( $field['choices'][ $value ] ) ) {
			$value = $field['choices'][ $value ];
		}

		$value = trim( preg_replace( '/\s+/', ' ', (string) $value ) );

		return function_exists( 'mb_substr' ) && mb_strlen( $value ) > 40 ? mb_substr( $value, 0, 39 ) . '…' : $value;
	}

	/* ------------------------------------------- Sichern und zurueckspielen --- */

	/**
	 * Haelt den jetzigen Stand fest, bevor etwas eingespielt wird.
	 *
	 * Optionen, die es noch nicht gibt, stehen als null darin: zurueckgespielt
	 * werden sie wieder geloescht.
	 *
	 * @return void
	 */
	public static function backup() {
		$options = array();

		foreach ( self::options() as $option ) {
			$value              = get_option( $option, null );
			$options[ $option ] = is_array( $value ) ? $value : null;
		}

		$backup = array(
			'time'    => time(),
			'options' => $options,
		);

		// Nur auf dieser Seite gebraucht, deshalb ohne Autoload.
		if ( false === get_option( self::OPTION_BACKUP, false ) ) {
			add_option( self::OPTION_BACKUP, $backup, '', false );
		} else {
			update_option( self::OPTION_BACKUP, $backup, false );
		}
	}

	/**
	 * Der gesicherte Stand, falls es einen gibt.
	 *
	 * @return array Leer ohne Sicherung, sonst time und options.
	 */
	public static function last_backup() {
		$backup = get_option( self::OPTION_BACKUP, array() );

		return is_array( $backup ) && isset( $backup['time'], $backup['options'] ) && is_array( $backup['options'] ) ? $backup : array();
	}

	/**
	 * Spielt den gesicherten Stand zurueck und raeumt die Sicherung weg.
	 *
	 * Die Werte stammen aus dieser Website und waren schon bereinigt; sie
	 * laufen trotzdem erneut durch die Sanitizer.
	 *
	 * @return bool Ob es etwas zurueckzuspielen gab.
	 */
	public static function undo() {
		$backup = self::last_backup();

		if ( empty( $backup ) ) {
			return false;
		}

		$known = self::options();

		foreach ( $backup['options'] as $option => $value ) {
			if ( ! in_array( (string) $option, $known, true ) ) {
				continue;
			}

			if ( null === $value ) {
				delete_option( (string) $option );
			} else {
				update_option( (string) $option, self::sanitize_option( (string) $option, (array) $value ) );
			}
		}

		delete_option( self::OPTION_BACKUP );
		self::flush();

		return true;
	}

	/**
	 * Leert die Laufzeit-Caches.
	 *
	 * @return void
	 */
	private static function flush() {
		UNDT_Store::flush();
		UNDT_Modules::flush();
		UNDT_Content::flush();
	}

	/* ---------------------------------------------------- Schritte der Seite --- */

	/**
	 * Der Schluessel der wartenden Datei, je Benutzer.
	 *
	 * @return string
	 */
	public static function pending_key() {
		return 'undt_import_file_' . get_current_user_id();
	}

	/**
	 * Prueft Recht und Nonce der Schritte.
	 *
	 * @return void Beendet die Anfrage bei einem Fehler.
	 */
	private static function guard() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Die Sitzung ist abgelaufen. Lade die Seite neu.', 'unternehmensdaten' ) ), 403 );
		}

		if ( ! current_user_can( UNDT_Store::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Dafür fehlt die Berechtigung.', 'unternehmensdaten' ) ), 403 );
		}
	}

	/**
	 * Schritt 1: nimmt die Datei an und zeigt, was sie aendern wuerde.
	 *
	 * @return void
	 */
	public static function ajax_preview() {
		self::guard();

		$payload = self::read_upload();
		$preview = is_wp_error( $payload ) ? $payload : self::preview( $payload );

		if ( is_wp_error( $preview ) ) {
			wp_send_json_error( array( 'message' => $preview->get_error_message() ), 400 );
		}

		set_transient( self::pending_key(), $payload, self::PENDING_TTL );

		wp_send_json_success( $preview );
	}

	/**
	 * Schritt 3: sichert den Stand und spielt die gewaehlten Bereiche ein.
	 *
	 * @return void
	 */
	public static function ajax_run() {
		self::guard();

		$payload = get_transient( self::pending_key() );

		if ( ! is_array( $payload ) ) {
			wp_send_json_error( array( 'message' => __( 'Die Datei ist nicht mehr da. Wähle sie noch einmal aus.', 'unternehmensdaten' ) ), 400 );
		}

		/*
		 * Die Nonce prueft guard() oben; ueber die Funktionsgrenze hinweg sieht
		 * die statische Pruefung das nicht. Nur bekannte Schluessel kommen durch.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce in guard(), sanitize_key je Eintrag.
		$areas = isset( $_POST['areas'] ) && is_array( $_POST['areas'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['areas'] ) ) : array();
		$areas = array_values( array_intersect( $areas, array_keys( self::areas() ) ) );

		if ( empty( $areas ) ) {
			wp_send_json_error( array( 'message' => __( 'Wähle mindestens einen Bereich aus.', 'unternehmensdaten' ) ), 400 );
		}

		self::backup();

		$report = self::import( $payload, $areas );

		if ( is_wp_error( $report ) ) {
			wp_send_json_error( array( 'message' => $report->get_error_message() ), 400 );
		}

		delete_transient( self::pending_key() );

		wp_send_json_success(
			array(
				'title'   => sprintf(
					/* translators: %d: Anzahl der Bereiche. */
					_n( '%d Bereich übernommen.', '%d Bereiche übernommen.', count( $areas ), 'unternehmensdaten' ),
					count( $areas )
				),
				'summary' => self::summary( $report ),
				'dropped' => count( $report['dropped'] ),
				'relink'  => self::relink_url( $report ),
			)
		);
	}

	/**
	 * Spielt den Stand vor dem letzten Einspielen zurueck.
	 *
	 * @return void
	 */
	public static function ajax_undo() {
		self::guard();

		if ( ! self::undo() ) {
			wp_send_json_error( array( 'message' => __( 'Es gibt keinen gesicherten Stand.', 'unternehmensdaten' ) ), 400 );
		}

		wp_send_json_success( array( 'message' => __( 'Der Stand vor dem Einspielen ist wieder da.', 'unternehmensdaten' ) ) );
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
	 * Nimmt eine hochgeladene Sicherung ohne JavaScript entgegen.
	 *
	 * Spielt alle Bereiche ein, sichert aber vorher genauso wie die Schritte.
	 *
	 * @return void
	 */
	public static function handle_import() {
		if ( ! current_user_can( UNDT_Store::capability() ) ) {
			wp_die( esc_html__( 'Dafür fehlt die Berechtigung.', 'unternehmensdaten' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::NONCE );

		$result = self::read_upload();

		if ( ! is_wp_error( $result ) ) {
			$checked = self::check( $result );

			if ( is_wp_error( $checked ) ) {
				$result = $checked;
			} else {
				self::backup();
				$result = self::import( $result );
			}
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
					'page'        => UNDT_Admin::SLUG_TRANSFER,
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
		 * Die Nonce pruefen handle_import() und guard(), bevor diese Methode
		 * ueberhaupt aufgerufen wird; ueber die Funktionsgrenze hinweg sieht die
		 * statische Pruefung das nicht. Die einzelnen Werte gehen unten durch ihre
		 * eigene Pruefung, und die Datei selbst ueber is_uploaded_file().
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
				__( 'Nicht übernommen wurden die Verknüpfungen zu Seiten und Bildern: %s. Wähle sie neu aus.', 'unternehmensdaten' ),
				implode( ', ', array_map( array( __CLASS__, 'dropped_label' ), $report['dropped'] ) )
			);
		}

		return $text;
	}

	/**
	 * Wohin es nach dem Import geht, wenn Seiten neu zu waehlen sind: auf
	 * Einbinden zu den Rechtsseiten. Leer, wenn keine Seite verworfen wurde.
	 *
	 * @param array $report Bericht aus import().
	 * @return string
	 */
	public static function relink_url( array $report ) {
		foreach ( isset( $report['dropped'] ) ? (array) $report['dropped'] : array() as $key ) {
			if ( 0 === strpos( (string) $key, 'page_' ) ) {
				return admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_EMBED ) . '#undt-pages-title';
			}
		}

		return '';
	}

	/**
	 * Die Bezeichnung einer verworfenen Verknuepfung, statt ihres Schluessels.
	 *
	 * @param string $key Schluessel aus dem Bericht, bei Bereichen mit Praefix („seo.logo“).
	 * @return string
	 */
	public static function dropped_label( $key ) {
		$key = (string) $key;

		if ( false !== strpos( $key, '.' ) ) {
			list( $slug, $field ) = explode( '.', $key, 2 );
			$module               = UNDT_Modules::get( $slug );

			return null !== $module && isset( $module['fields'][ $field ] ) ? $module['label'] . ': ' . $module['fields'][ $field ]['label'] : $key;
		}

		$field = UNDT_Schema::field( $key );

		return null !== $field && '' !== (string) $field['label'] ? (string) $field['label'] : $key;
	}
}
