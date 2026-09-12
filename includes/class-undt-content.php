<?php
/**
 * Datenzugriff und Sanitisierung der Inhaltsmodule.
 *
 * Wie bei den Stammdaten wird ueber das Register iteriert, nicht ueber die
 * Eingabe. Das gilt auch innerhalb der Wiederholungsfelder: eine Zeile kann nur
 * die Unterfelder enthalten, die das Register fuer dieses Modul kennt.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Content
 */
final class UNDT_Content {

	/**
	 * Laufzeit-Cache je Modul.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Alle Daten eines Moduls.
	 *
	 * @param string $slug Modul-Schluessel.
	 * @return array
	 */
	public static function all( $slug ) {
		if ( isset( self::$cache[ $slug ] ) ) {
			return self::$cache[ $slug ];
		}

		$module = UNDT_Modules::get( $slug );

		if ( null === $module ) {
			return array();
		}

		$stored = get_option( $module['option'], array() );

		self::$cache[ $slug ] = is_array( $stored ) ? $stored : array();

		return self::$cache[ $slug ];
	}

	/**
	 * Wert eines Feldes, mit Rueckfall auf die Voreinstellung.
	 *
	 * @param string $slug Modul-Schluessel.
	 * @param string $key  Feldschluessel.
	 * @return mixed
	 */
	public static function value( $slug, $key ) {
		$module = UNDT_Modules::get( $slug );

		if ( null === $module || ! isset( $module['fields'][ $key ] ) ) {
			return '';
		}

		$field = $module['fields'][ $key ];
		$data  = self::all( $slug );

		if ( ! array_key_exists( $key, $data ) ) {
			return $field['default'];
		}

		$value = $data[ $key ];

		if ( is_string( $value ) && '' === $value && '' !== $field['default'] ) {
			return $field['default'];
		}

		return $value;
	}

	/**
	 * Ob ein Feld einen ausgebbaren Wert hat.
	 *
	 * @param string $slug Modul-Schluessel.
	 * @param string $key  Feldschluessel.
	 * @return bool
	 */
	public static function has( $slug, $key ) {
		$value = self::value( $slug, $key );

		if ( is_array( $value ) ) {
			return ! empty( $value );
		}

		if ( '' === trim( (string) $value ) ) {
			return false;
		}

		// Ein Medienfeld ohne Auswahl haelt eine 0, und die zaehlt nicht als Wert.
		$module = UNDT_Modules::get( $slug );

		if ( null !== $module && isset( $module['fields'][ $key ] ) && 'media' === $module['fields'][ $key ]['type'] ) {
			return (int) $value > 0;
		}

		return true;
	}

	/**
	 * Die Zeilen eines Wiederholungsfeldes.
	 *
	 * Vollstaendig leere Zeilen werden verworfen, damit eine versehentlich
	 * hinzugefuegte und nicht ausgefuellte Zeile nichts ausgibt.
	 *
	 * @param string $slug Modul-Schluessel.
	 * @param string $key  Feldschluessel.
	 * @return array
	 */
	public static function rows( $slug, $key ) {
		$value = self::value( $slug, $key );

		if ( ! is_array( $value ) ) {
			return array();
		}

		$rows = array();

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$filled = false;

			foreach ( $row as $sub_key => $sub_value ) {
				// Ein gesetzter Schalter allein macht eine Zeile noch nicht sinnvoll.
				if ( 'closed' === $sub_key ) {
					continue;
				}

				if ( '' !== trim( (string) $sub_value ) ) {
					$filled = true;
					break;
				}
			}

			if ( $filled ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Gruppiert Zeilen nach dem Unterfeld group.
	 *
	 * Die Reihenfolge der Gruppen folgt dem ersten Auftreten, damit die
	 * Sortierung im Backend die Ausgabe bestimmt.
	 *
	 * @param array  $rows   Zeilen.
	 * @param string $filter Nur diese Gruppe, leer fuer alle.
	 * @return array
	 */
	public static function group_rows( array $rows, $filter = '' ) {
		$groups = array();
		$filter = trim( (string) $filter );

		foreach ( $rows as $row ) {
			$group = isset( $row['group'] ) ? trim( (string) $row['group'] ) : '';

			if ( '' !== $filter && 0 !== strcasecmp( $group, $filter ) ) {
				continue;
			}

			$groups[ $group ][] = $row;
		}

		return $groups;
	}

	/**
	 * Sanitisiert die Eingabe eines Moduls.
	 *
	 * @param string $slug  Modul-Schluessel.
	 * @param mixed  $input Rohe Eingabe.
	 * @return array
	 */
	public static function sanitize( $slug, $input ) {
		$module = UNDT_Modules::get( $slug );

		if ( null === $module ) {
			return array();
		}

		$input = is_array( $input ) ? $input : array();
		$clean = self::all( $slug );

		foreach ( $module['fields'] as $key => $field ) {
			// Schalter senden dank ihres versteckten Partnerfeldes immer mit.
			if ( ! array_key_exists( $key, $input ) ) {
				if ( 'repeater' === $field['type'] ) {
					// Ein Wiederholungsfeld ohne jede Zeile kommt gar nicht an.
					$clean[ $key ] = array();
				}

				continue;
			}

			$clean[ $key ] = self::sanitize_value( $input[ $key ], $field );
		}

		// Schluessel entfernen, die es im Register nicht mehr gibt.
		$clean = array_intersect_key( $clean, $module['fields'] );

		self::flush();

		return $clean;
	}

	/**
	 * Sanitisiert einen Wert nach seinem Typ.
	 *
	 * @param mixed $value Rohwert.
	 * @param array $field Felddefinition.
	 * @return mixed
	 */
	private static function sanitize_value( $value, array $field ) {
		switch ( $field['type'] ) {

			case 'repeater':
				if ( ! is_array( $value ) ) {
					return array();
				}

				$rows = array();

				foreach ( $value as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}

					$clean_row = array();

					// Nur die im Register deklarierten Unterfelder ueberleben.
					foreach ( $field['fields'] as $sub_key => $sub_field ) {
						$raw = array_key_exists( $sub_key, $row ) ? $row[ $sub_key ] : '';

						$clean_row[ $sub_key ] = self::sanitize_value( $raw, $sub_field );
					}

					$rows[] = $clean_row;
				}

				// Neu durchnummerieren, damit keine Luecken entstehen.
				return array_values( $rows );

			case 'hours':
				return UNDT_Hours::normalize( $value );

			case 'time':
				return UNDT_Hours::time( $value );

			case 'date':
				return UNDT_Hours::date( $value );

			case 'media':
				return absint( $value );

			case 'url':
				return esc_url_raw( (string) $value, array( 'http', 'https' ) );

			case 'email':
				return sanitize_email( (string) $value );

			case 'checkbox':
				return empty( $value ) ? 0 : 1;

			case 'select':
				$choices = array_keys( $field['choices'] );

				return in_array( (string) $value, $choices, true ) ? (string) $value : (string) $field['default'];

			case 'textarea':
				$value = sanitize_textarea_field( (string) $value );

				return trim( (string) preg_replace( '/\R/', "\n", $value ) );

			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Leert den Laufzeit-Cache.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = array();

		UNDT_Modules::flush();
		UNDT_Blocks::reset();
	}
}
