<?php
/**
 * Die Stammdaten in Etch.
 *
 * Etch setzt die Werte ueber ihren Namen ein, `{options.undt.phone}`, und
 * bekommt sie ueber einen einzigen Haken. Ja-Nein-Werte gehen als echte
 * Wahrheitswerte hinaus, damit sich Bedingungen darauf stuetzen koennen.
 * Seit 0.6.1 stehen daneben die Listen fuer Schleifen, `options.undt.prices`.
 *
 * Die Werte selbst liefert UNDT_Dynamic. Diese Klasse kennt nur Etch.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Dynamic_Etch
 */
final class UNDT_Dynamic_Etch {

	/**
	 * Haengt sich in Etch ein.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'etch/dynamic_data/option', array( __CLASS__, 'options' ) );
	}

	/**
	 * Liefert Etch die Werte unter {options.undt.…}.
	 *
	 * @param mixed $data Bisherige Optionsdaten.
	 * @return mixed
	 */
	public static function options( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$values = UNDT_Dynamic::values( UNDT_Dynamic::CONTEXT_BUILDER );

		// Echte Wahrheitswerte, damit Bedingungen wie {options.undt.banner_show} greifen.
		foreach ( UNDT_Dynamic::FLAGS as $flag ) {
			if ( array_key_exists( $flag, $values ) ) {
				$values[ $flag ] = '1' === $values[ $flag ];
			}
		}

		$data['undt'] = $values + self::lists();

		return $data;
	}

	/**
	 * Die Schleifen-Quellen als Listen, ohne den Vorsatz undt_: Eine Etch-Schleife
	 * mit dem Ziel options.undt.prices laeuft ueber die Preise, einzelne Zeilen gibt
	 * es mit {options.undt.prices.0.label}. Dieselben Zeilen wie in Bricks.
	 *
	 * @return array Schluessel => Zeilen.
	 */
	public static function lists() {
		$lists = array();

		foreach ( array_keys( UNDT_Api::sources() ) as $source ) {
			$lists[ substr( $source, strlen( 'undt_' ) ) ] = UNDT_Api::query( $source );
		}

		return $lists;
	}
}
