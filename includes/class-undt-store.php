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
		$value = is_scalar( $value ) ? (string) $value : '';

		// Ein leeres Feld kann seinen Wert aus anderen Angaben ableiten, etwa ein Kartenlink aus der Anschrift.
		if ( '' === trim( $value ) && is_callable( $field['derived'] ) ) {
			$value = (string) call_user_func( $field['derived'], $key );
		}

		return $value;
	}

	/**
	 * Ein Kartenlink aus Firma und Anschrift.
	 *
	 * Fuer Google Maps eine Suche nach Firma und Anschrift, die in der Regel
	 * den Eintrag samt Bewertungen findet. Fuer Apple Maps die Anschrift als
	 * Ort, beschriftet mit dem Firmennamen.
	 *
	 * @param string $key maps_google oder maps_apple.
	 * @return string Leerstring, solange Strasse oder Ort fehlen.
	 */
	public static function maps_link( $key ) {
		$street   = trim( self::get( 'street' ) );
		$locality = trim( self::get( 'postal_code' ) . ' ' . self::get( 'city' ) );

		if ( '' === $street || '' === $locality ) {
			return '';
		}

		$address = $street . ', ' . $locality;
		$country = trim( self::get( 'country' ) );
		$name    = trim( self::get( 'company_name' ) );

		if ( '' !== $country ) {
			$address .= ', ' . $country;
		}

		if ( 'maps_apple' === $key ) {
			$args = array( 'address' => $address );

			if ( '' !== $name ) {
				$args['q'] = $name;
			}

			return 'https://maps.apple.com/?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
		}

		$args = array(
			'api'   => 1,
			'query' => '' === $name ? $address : $name . ', ' . $address,
		);

		return 'https://www.google.com/maps/search/?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
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
		 * nie ausgewaehlte Seite fuer hinterlegt. Ein Seitenfeld kann statt der ID
		 * auch eine eigene Adresse halten, und die zaehlt immer.
		 */
		$field = UNDT_Schema::field( $key );

		if ( null !== $field && in_array( $field['type'], array( 'page', 'media' ), true ) && preg_match( '/^\d+$/', $value ) ) {
			return (int) $value > 0;
		}

		return true;
	}

	/**
	 * Die ID in einem Seitenfeld.
	 *
	 * @param string $key Feldschluessel.
	 * @return int 0, wenn nichts gewaehlt ist oder das Feld eine eigene Adresse haelt.
	 */
	public static function page_id( $key ) {
		$value = trim( self::get( $key ) );

		return preg_match( '/^\d+$/', $value ) ? (int) $value : 0;
	}

	/**
	 * Das Ziel eines Seitenfeldes.
	 *
	 * Ein Seitenfeld haelt die ID eines Beitrags beliebigen Inhaltstyps oder eine
	 * eigene Adresse. Beitraege zaehlen nur, solange sie veroeffentlicht sind:
	 * ein Entwurf ergaebe einen toten Link und verriete seinen Arbeitstitel, bei
	 * privaten Seiten stuende "Privat:" im Linktext.
	 *
	 * @param string $key Feldschluessel.
	 * @return array|null array( url, title, post_id ), null ohne gueltiges Ziel.
	 */
	public static function link( $key ) {
		$field = UNDT_Schema::field( $key );

		if ( null === $field || 'page' !== $field['type'] ) {
			return null;
		}

		$value = trim( self::get( $key ) );

		if ( '' === $value || '0' === $value ) {
			return null;
		}

		if ( ! preg_match( '/^\d+$/', $value ) ) {
			return array(
				'url'     => $value,
				'title'   => '',
				'post_id' => 0,
			);
		}

		$id = (int) $value;

		if ( 'publish' !== get_post_status( $id ) ) {
			return null;
		}

		$url = (string) get_permalink( $id );

		if ( '' === $url ) {
			return null;
		}

		return array(
			'url'     => $url,
			'title'   => (string) get_the_title( $id ),
			'post_id' => $id,
		);
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
