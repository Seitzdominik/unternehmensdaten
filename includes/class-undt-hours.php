<?php
/**
 * Logik der Oeffnungszeiten.
 *
 * Datenmodell je Tag: ein Schalter fuer geschlossen und zwei Zeitfenster. Zwei
 * genuegen fuer den Regelfall einschliesslich Mittagspause, ohne die Oberflaeche
 * mit beliebig vielen Zeilen zu belasten.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Hours
 */
final class UNDT_Hours {

	const SLOTS = 2;

	/**
	 * Tagesschluessel in fester Speicherreihenfolge.
	 *
	 * @return array
	 */
	public static function day_keys() {
		return array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
	}

	/**
	 * Tagesschluessel in der Reihenfolge, die im Backend eingestellt ist.
	 *
	 * WordPress kennt mit start_of_week bereits eine Einstellung dafuer, welcher
	 * Tag die Woche beginnt. Die wird hier uebernommen, statt eine zweite
	 * Einstellung fuer dieselbe Frage einzufuehren.
	 *
	 * @return array
	 */
	public static function display_order() {
		$keys  = self::day_keys();
		$start = (int) get_option( 'start_of_week', 1 );

		// start_of_week zaehlt ab Sonntag, unsere Liste beginnt am Montag.
		$offset = ( $start + 6 ) % 7;

		return array_merge( array_slice( $keys, $offset ), array_slice( $keys, 0, $offset ) );
	}

	/**
	 * Uebersetzte Bezeichnung eines Wochentags.
	 *
	 * @param string $key   Tagesschluessel.
	 * @param bool   $short Abkuerzung verwenden.
	 * @return string
	 */
	public static function day_label( $key, $short = false ) {
		global $wp_locale;

		$map = array(
			'sun' => 0,
			'mon' => 1,
			'tue' => 2,
			'wed' => 3,
			'thu' => 4,
			'fri' => 5,
			'sat' => 6,
		);

		if ( ! isset( $map[ $key ] ) ) {
			return '';
		}

		if ( ! $wp_locale instanceof WP_Locale ) {
			return $key;
		}

		$full = $wp_locale->get_weekday( $map[ $key ] );

		return $short ? $wp_locale->get_weekday_abbrev( $full ) : $full;
	}

	/**
	 * Tagesname nach schema.org.
	 *
	 * @param string $key Tagesschluessel.
	 * @return string
	 */
	public static function schema_day( $key ) {
		$map = array(
			'mon' => 'Monday',
			'tue' => 'Tuesday',
			'wed' => 'Wednesday',
			'thu' => 'Thursday',
			'fri' => 'Friday',
			'sat' => 'Saturday',
			'sun' => 'Sunday',
		);

		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	/**
	 * Ein leerer, aber vollstaendiger Tagesdatensatz.
	 *
	 * @return array
	 */
	public static function empty_day() {
		$slots = array();

		for ( $i = 0; $i < self::SLOTS; $i++ ) {
			$slots[] = array(
				'from' => '',
				'to'   => '',
			);
		}

		return array(
			'closed' => 0,
			'slots'  => $slots,
		);
	}

	/**
	 * Bringt die gespeicherten Zeiten in eine vollstaendige Struktur.
	 *
	 * @param mixed $raw Gespeicherter Wert.
	 * @return array
	 */
	public static function normalize( $raw ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$clean = array();

		foreach ( self::day_keys() as $day ) {
			$row  = isset( $raw[ $day ] ) && is_array( $raw[ $day ] ) ? $raw[ $day ] : array();
			$data = self::empty_day();

			$data['closed'] = empty( $row['closed'] ) ? 0 : 1;

			for ( $i = 0; $i < self::SLOTS; $i++ ) {
				if ( ! isset( $row['slots'][ $i ] ) || ! is_array( $row['slots'][ $i ] ) ) {
					continue;
				}

				$from = self::time( isset( $row['slots'][ $i ]['from'] ) ? $row['slots'][ $i ]['from'] : '' );
				$to   = self::time( isset( $row['slots'][ $i ]['to'] ) ? $row['slots'][ $i ]['to'] : '' );

				// Ein halb ausgefuelltes Fenster ist keine Angabe.
				if ( '' === $from || '' === $to ) {
					continue;
				}

				$data['slots'][ $i ] = array(
					'from' => $from,
					'to'   => $to,
				);
			}

			$clean[ $day ] = $data;
		}

		return $clean;
	}

	/**
	 * Prueft und normalisiert eine Uhrzeit auf HH:MM.
	 *
	 * @param mixed $value Rohwert.
	 * @return string Leerstring, wenn ungueltig.
	 */
	public static function time( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( ! preg_match( '/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Prueft und normalisiert ein Datum auf YYYY-MM-DD.
	 *
	 * @param mixed $value Rohwert.
	 * @return string Leerstring, wenn ungueltig.
	 */
	public static function date( $value ) {
		$value = trim( (string) $value );

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return '';
		}

		if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Die belegten Zeitfenster eines Tages.
	 *
	 * @param string $day Tagesschluessel.
	 * @return array Leeres Array, wenn geschlossen oder nichts hinterlegt.
	 */
	public static function slots( $day ) {
		$data = self::normalize( UNDT_Content::value( 'hours', 'days' ) );

		if ( ! isset( $data[ $day ] ) || $data[ $day ]['closed'] ) {
			return array();
		}

		return array_values(
			array_filter(
				$data[ $day ]['slots'],
				static function ( $slot ) {
					return '' !== $slot['from'] && '' !== $slot['to'];
				}
			)
		);
	}

	/**
	 * Ob fuer mindestens einen Tag etwas hinterlegt ist.
	 *
	 * @return bool
	 */
	public static function has_data() {
		$data = self::normalize( UNDT_Content::value( 'hours', 'days' ) );

		foreach ( $data as $day ) {
			if ( $day['closed'] ) {
				return true;
			}

			foreach ( $day['slots'] as $slot ) {
				if ( '' !== $slot['from'] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Fasst aufeinanderfolgende Tage mit gleichen Zeiten zusammen.
	 *
	 * Aus sieben Zeilen wird so „Mo – Fr 09:00 – 18:00“ und „Sa 10:00 – 14:00“.
	 *
	 * @return array Liste aus array( days, closed, slots ).
	 */
	public static function grouped() {
		$data   = self::normalize( UNDT_Content::value( 'hours', 'days' ) );
		$groups = array();
		$last   = null;

		foreach ( self::display_order() as $day ) {
			$entry = $data[ $day ];

			$slots = array_values(
				array_filter(
					$entry['slots'],
					static function ( $slot ) {
						return '' !== $slot['from'] && '' !== $slot['to'];
					}
				)
			);

			$closed    = (bool) $entry['closed'] || empty( $slots );
			$signature = $closed ? 'closed' : wp_json_encode( $slots );

			if ( null !== $last && $groups[ $last ]['signature'] === $signature ) {
				$groups[ $last ]['days'][] = $day;
				continue;
			}

			$groups[] = array(
				'signature' => $signature,
				'days'      => array( $day ),
				'closed'    => $closed,
				'slots'     => $slots,
			);

			$last = count( $groups ) - 1;
		}

		return $groups;
	}

	/**
	 * Kuenftige Sonderoeffnungszeiten, aufsteigend sortiert.
	 *
	 * Vergangene Termine werden nicht ausgegeben: ein Hinweis auf die
	 * Weihnachtsschliessung des Vorjahres ist schlimmer als gar keiner.
	 *
	 * @return array
	 */
	public static function upcoming_special() {
		$rows  = UNDT_Content::rows( 'hours', 'special' );
		$today = current_datetime()->format( 'Y-m-d' );
		$out   = array();

		foreach ( $rows as $row ) {
			$date = self::date( isset( $row['date'] ) ? $row['date'] : '' );

			if ( '' === $date || $date < $today ) {
				continue;
			}

			$out[ $date . '-' . count( $out ) ] = $row;
		}

		ksort( $out );

		return array_values( $out );
	}

	/**
	 * Sonderregelung fuer ein bestimmtes Datum.
	 *
	 * @param string $date Datum als YYYY-MM-DD.
	 * @return array|null
	 */
	public static function special_for( $date ) {
		foreach ( UNDT_Content::rows( 'hours', 'special' ) as $row ) {
			if ( self::date( isset( $row['date'] ) ? $row['date'] : '' ) === $date ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Die heute geltenden Zeitfenster, Sonderregelungen eingerechnet.
	 *
	 * @return array array( closed, slots, note, is_special ).
	 */
	public static function today() {
		$now     = current_datetime();
		$date    = $now->format( 'Y-m-d' );
		$day     = strtolower( $now->format( 'D' ) );
		$special = self::special_for( $date );

		if ( null !== $special ) {
			$from = self::time( isset( $special['from'] ) ? $special['from'] : '' );
			$to   = self::time( isset( $special['to'] ) ? $special['to'] : '' );

			$slots = ( ! empty( $special['closed'] ) || '' === $from || '' === $to )
				? array()
				: array( array( 'from' => $from, 'to' => $to ) );

			return array(
				'closed'     => empty( $slots ),
				'slots'      => $slots,
				'note'       => isset( $special['note'] ) ? (string) $special['note'] : '',
				'is_special' => true,
			);
		}

		$slots = self::slots( $day );

		return array(
			'closed'     => empty( $slots ),
			'slots'      => $slots,
			'note'       => '',
			'is_special' => false,
		);
	}

	/**
	 * Ob gerade geoeffnet ist.
	 *
	 * Rechnet in der Zeitzone der Website, nicht in der des Servers.
	 *
	 * @return bool
	 */
	public static function is_open_now() {
		$today = self::today();

		if ( $today['closed'] ) {
			return false;
		}

		$now = current_datetime()->format( 'H:i' );

		foreach ( $today['slots'] as $slot ) {
			// Ein Fenster ueber Mitternacht endet rechnerisch vor seinem Beginn.
			if ( $slot['to'] <= $slot['from'] ) {
				if ( $now >= $slot['from'] || $now < $slot['to'] ) {
					return true;
				}

				continue;
			}

			if ( $now >= $slot['from'] && $now < $slot['to'] ) {
				return true;
			}
		}

		return false;
	}
}
