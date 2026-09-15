<?php
/**
 * Datenzugriff.
 *
 * Alle Werte liegen in zwei autoloaded Optionen. Damit kostet das Lesen im
 * Frontend keine einzige zusaetzliche Datenbankabfrage: WordPress holt
 * autoloaded Optionen ohnehin in einem Rutsch und legt sie im Object Cache ab.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Store
 */
final class UNDT_Store {

	const OPTION_PROFILE = 'undt_profile';
	const OPTION_COMPANY = 'undt_company';
	const OPTION_SETUP   = 'undt_setup_done';

	/**
	 * Laufzeit-Cache.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Das Profil, also die Antworten des Einrichtungsassistenten.
	 *
	 * @return array
	 */
	public static function profile() {
		if ( ! isset( self::$cache['profile'] ) ) {
			$stored = get_option( self::OPTION_PROFILE, array() );

			self::$cache['profile'] = array_merge(
				UNDT_Schema::profile_defaults(),
				is_array( $stored ) ? $stored : array()
			);
		}

		return self::$cache['profile'];
	}

	/**
	 * Alle gespeicherten Feldwerte.
	 *
	 * @return array
	 */
	public static function company() {
		if ( ! isset( self::$cache['company'] ) ) {
			$stored = get_option( self::OPTION_COMPANY, array() );

			self::$cache['company'] = is_array( $stored ) ? $stored : array();
		}

		return self::$cache['company'];
	}

	/**
	 * Wert eines einzelnen Feldes.
	 *
	 * Liefert einen Leerstring, wenn das Feld beim aktuellen Profil gar nicht
	 * gilt. So kann kein Wert durchrutschen, der nach einem Rechtsformwechsel
	 * noch in der Datenbank liegt, aber inhaltlich nicht mehr stimmt.
	 *
	 * @param string $key Feldschluessel.
	 * @return string
	 */
	public static function get( $key ) {
		$field = UNDT_Schema::field( $key );

		if ( null === $field ) {
			return '';
		}

		if ( ! UNDT_Schema::applies( $field['when'], self::profile() ) ) {
			return '';
		}

		$company = self::company();

		/*
		 * Die Voreinstellung gilt nur fuer ein Feld, das nie gespeichert wurde.
		 * Ein bewusst geleertes Feld bleibt leer, sonst liesse sich etwa der
		 * Kleinunternehmer-Hinweis nicht entfernen.
		 */
		$value = isset( $company[ $key ] ) ? $company[ $key ] : $field['default'];

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Ob ein Feld einen ausgebbaren Wert hat.
	 *
	 * @param string $key Feldschluessel.
	 * @return bool
	 */
	public static function has( $key ) {
		$value = trim( self::get( $key ) );

		if ( '' === $value ) {
			return false;
		}

		/*
		 * Felder, die eine ID halten, speichern eine 0, wenn nichts ausgewaehlt
		 * ist. Als Zeichenkette ist "0" nicht leer, gilt hier aber trotzdem als
		 * nicht ausgefuellt. Ohne diese Unterscheidung haelt die Pruefung eine
		 * nie ausgewaehlte Seite fuer hinterlegt.
		 */
		$field = UNDT_Schema::field( $key );

		if ( null !== $field && in_array( $field['type'], array( 'page', 'media' ), true ) ) {
			return (int) $value > 0;
		}

		return true;
	}

	/**
	 * Mehrzeiliges Feld als Array von Zeilen.
	 *
	 * @param string $key Feldschluessel.
	 * @return array
	 */
	public static function get_lines( $key ) {
		$value = self::get( $key );

		if ( '' === $value ) {
			return array();
		}

		$lines = preg_split( '/\R/', $value );
		$lines = array_map( 'trim', $lines );

		return array_values( array_filter( $lines, 'strlen' ) );
	}

	/**
	 * Wert einer Profil-Antwort.
	 *
	 * @param string $key Schluessel.
	 * @return mixed
	 */
	public static function profile_value( $key ) {
		$profile = self::profile();

		return isset( $profile[ $key ] ) ? $profile[ $key ] : null;
	}

	/**
	 * Ob der Einrichtungsassistent abgeschlossen wurde.
	 *
	 * @return bool
	 */
	public static function is_set_up() {
		return (bool) get_option( self::OPTION_SETUP, 0 );
	}

	/**
	 * Markiert den Assistenten als abgeschlossen.
	 *
	 * @return void
	 */
	public static function mark_set_up() {
		update_option( self::OPTION_SETUP, 1, true );
	}

	/**
	 * Leert den Laufzeit-Cache. Wird nach dem Speichern benoetigt.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = array();
	}

	/**
	 * Die Berechtigung, die zum Bearbeiten noetig ist.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Erlaubt es, die Verwaltung an eine eigene Rolle zu haengen.
		 *
		 * @param string $capability Standard: manage_options.
		 */
		return (string) apply_filters( 'undt_capability', 'manage_options' );
	}
}
