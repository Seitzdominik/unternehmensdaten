<?php
/**
 * Schema-getriebene Sanitisierung.
 *
 * Es wird ueber das Feld-Register iteriert, nicht ueber die Eingabe. Damit ist
 * die Whitelist strukturell erzwungen: was nicht im Register steht, kann gar
 * nicht erst gespeichert werden, egal was gepostet wird.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Sanitizer
 */
final class UNDT_Sanitizer {

	/**
	 * Sanitisiert das Profil.
	 *
	 * @param mixed $input Rohe Eingabe.
	 * @return array
	 */
	public static function profile( $input ) {
		$input     = is_array( $input ) ? $input : array();
		$questions = UNDT_Schema::profile_questions();
		$defaults  = UNDT_Schema::profile_defaults();
		$clean     = array();

		foreach ( $defaults as $key => $default ) {
			$question = isset( $questions[ $key ] ) ? $questions[ $key ] : array( 'type' => 'checkbox' );

			// Nur Einzelwerte. Ein Array kaeme aus einem manipulierten Formular.
			$raw = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? $input[ $key ] : null;

			if ( 'select' === $question['type'] ) {
				$choices       = isset( $question['choices'] ) ? array_keys( $question['choices'] ) : array();
				$clean[ $key ] = in_array( (string) $raw, $choices, true ) ? (string) $raw : $default;
			} else {
				$clean[ $key ] = empty( $raw ) ? 0 : 1;
			}
		}

		UNDT_Store::flush();

		return $clean;
	}

	/**
	 * Sanitisiert die Stammdaten.
	 *
	 * Der bestehende Datenbestand wird als Basis genommen und nur von den
	 * tatsaechlich uebermittelten Feldern ueberschrieben. Das schuetzt Werte,
	 * die gerade wegen der Rechtsform ausgeblendet und deshalb nicht Teil des
	 * Formulars sind: ein Rechtsformwechsel hin und zurueck verliert nichts.
	 *
	 * @param mixed $input Rohe Eingabe.
	 * @return array
	 */
	public static function company( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$fields = UNDT_Schema::fields();
		$clean  = UNDT_Store::company();

		foreach ( $fields as $key => $field ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}

			$clean[ $key ] = self::value( $input[ $key ], $field );
		}

		// Schluessel entfernen, die es im Register nicht mehr gibt.
		$clean = array_intersect_key( $clean, $fields );

		UNDT_Store::flush();

		return $clean;
	}

	/**
	 * Sanitisiert einen einzelnen Wert nach seinem Typ.
	 *
	 * @param mixed $value Rohwert.
	 * @param array $field Felddefinition.
	 * @return string|int
	 */
	private static function value( $value, array $field ) {
		if ( is_array( $value ) ) {
			$value = '';
		}

		$value = (string) $value;

		switch ( $field['type'] ) {
			case 'email':
				return sanitize_email( $value );

			case 'url':
				// Nur Web-Protokolle, damit weder javascript: noch data: durchkommt.
				return esc_url_raw( $value, array( 'http', 'https' ) );

			case 'tel':
				$value = preg_replace( '#[^0-9+/()\-. ]#', '', $value );
				return trim( (string) $value );

			case 'page':
				return absint( $value );

			case 'select':
				$choices = array_keys( $field['choices'] );
				return in_array( $value, $choices, true ) ? $value : '';

			case 'checkbox':
				return empty( $value ) ? 0 : 1;

			case 'list':
			case 'textarea':
				$value = sanitize_textarea_field( $value );
				$value = preg_replace( '/\R/', "\n", $value );
				return trim( (string) $value );

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}
}
