<?php
/**
 * Logik der Oeffnungszeiten.
 *
 * Datenmodell je Tag: ein Schalter fuer geschlossen und zwei Zeitfenster. Zwei
 * genuegen fuer den Regelfall einschliesslich Mittagspause, ohne die Oberflaeche
 * mit beliebig vielen Zeilen zu belasten.
 *
 * Ein Zeitfenster, das vor seinem Beginn endet, reicht ueber Mitternacht, etwa
 * 22:00 bis 02:00. Es gehoert zu dem Tag, an dem es beginnt.
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
	 * Die Sprache fuer Tage und Monate.
	 *
	 * Deutsch ist voreingestellt. Viele Websites laufen mit englischem Backend,
	 * geben aber deutsche Inhalte aus, und WordPress lieferte dann „Mon“ und
	 * „December“ mitten in einer deutschen Seite.
	 *
	 * @return string de oder site.
	 */
	public static function language() {
		return 'site' === (string) UNDT_Content::value( 'hours', 'day_language' ) ? 'site' : 'de';
	}

	/**
	 * Bezeichnung eines Wochentags in der eingestellten Sprache.
	 *
	 * @param string $key   Tagesschluessel.
	 * @param bool   $short Abkuerzung verwenden.
	 * @return string
	 */
	public static function day_label( $key, $short = false ) {
		global $wp_locale;

		$german = array(
			'mon' => array( 'Montag', 'Mo' ),
			'tue' => array( 'Dienstag', 'Di' ),
			'wed' => array( 'Mittwoch', 'Mi' ),
			'thu' => array( 'Donnerstag', 'Do' ),
			'fri' => array( 'Freitag', 'Fr' ),
			'sat' => array( 'Samstag', 'Sa' ),
			'sun' => array( 'Sonntag', 'So' ),
		);

		if ( ! isset( $german[ $key ] ) ) {
			return '';
		}

		if ( 'site' !== self::language() || ! $wp_locale instanceof WP_Locale ) {
			return $german[ $key ][ $short ? 1 : 0 ];
		}

		// WP_Locale zaehlt ab Sonntag.
		$map = array(
			'sun' => 0,
			'mon' => 1,
			'tue' => 2,
			'wed' => 3,
			'thu' => 4,
			'fri' => 5,
			'sat' => 6,
		);

		$full = $wp_locale->get_weekday( $map[ $key ] );

		return $short ? $wp_locale->get_weekday_abbrev( $full ) : $full;
	}

	/**
	 * Ein Datum als lesbarer Text in der eingestellten Sprache.
	 *
	 * Deutsch immer als „24. Dezember 2026“. Die Sprache der Website folgt dem
	 * Datumsformat aus den WordPress-Einstellungen.
	 *
	 * @param string $date Datum als YYYY-MM-DD.
	 * @return string Leerstring bei ungueltigem Datum.
	 */
	public static function date_label( $date ) {
		$date = self::date( $date );

		if ( '' === $date ) {
			return '';
		}

		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $date ) );

		if ( 'site' === self::language() ) {
			return date_i18n( (string) get_option( 'date_format', 'j. F Y' ), (int) gmmktime( 12, 0, 0, $month, $day, $year ) );
		}

		$months = array( 1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' );

		return $day . '. ' . $months[ $month ] . ' ' . $year;
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

				$window = self::window(
					isset( $row['slots'][ $i ]['from'] ) ? $row['slots'][ $i ]['from'] : '',
					isset( $row['slots'][ $i ]['to'] ) ? $row['slots'][ $i ]['to'] : ''
				);

				if ( null !== $window ) {
					$data['slots'][ $i ] = $window;
				}
			}

			$clean[ $day ] = $data;
		}

		return $clean;
	}

	/**
	 * Ein Zeitfenster aus zwei Uhrzeiten.
	 *
	 * @param mixed $from Beginn.
	 * @param mixed $to   Ende.
	 * @return array|null Null, wenn sich daraus kein gueltiges Fenster ergibt.
	 */
	public static function window( $from, $to ) {
		$from = self::time( $from );
		$to   = self::time( $to );

		// Ein halb ausgefuelltes Fenster ist keine Angabe.
		if ( '' === $from || '' === $to ) {
			return null;
		}

		if ( $from === $to ) {
			/*
			 * 00:00 bis 00:00 ist als rund um die Uhr gemeint, bedeutet in
			 * strukturierten Daten aber ganztaegig geschlossen. Daraus wird deshalb
			 * 00:00 bis 23:59, die Schreibweise, die Google fuer durchgehend
			 * geoeffnet vorsieht. Jede andere gleiche Uhrzeit ist ein Tippfehler,
			 * der sonst als rund um die Uhr geoeffnet gelten wuerde.
			 */
			if ( '00:00' !== $from ) {
				return null;
			}

			$to = '23:59';
		}

		return array(
			'from' => $from,
			'to'   => $to,
		);
	}

	/**
	 * Prueft und normalisiert eine Uhrzeit auf HH:MM.
	 *
	 * @param mixed $value Rohwert.
	 * @return string Leerstring, wenn ungueltig.
	 */
	public static function time( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

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
		if ( ! is_scalar( $value ) ) {
			return '';
		}

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
	 * Bereitet die Sondertermine beim Speichern auf.
	 *
	 * Ein Termin ohne Uhrzeiten gilt als geschlossen, so behandelt ihn die
	 * Ausgabe ohnehin. Der Schalter wird deshalb gesetzt, damit das Backend nach
	 * dem Speichern zeigt, was auf der Website erscheint, statt es stillschweigend
	 * anzunehmen. Gueltige Zeitfenster laufen durch dieselbe Pruefung wie die
	 * regulaeren Zeiten.
	 *
	 * @param mixed $rows Bereits sanitisierte Zeilen.
	 * @return array
	 */
	public static function normalize_special( $rows ) {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$from   = isset( $row['from'] ) ? $row['from'] : '';
			$to     = isset( $row['to'] ) ? $row['to'] : '';
			$window = self::window( $from, $to );

			if ( null !== $window ) {
				$rows[ $index ]['from'] = $window['from'];
				$rows[ $index ]['to']   = $window['to'];

				continue;
			}

			if ( ! empty( $row['date'] ) && '' === $from && '' === $to ) {
				$rows[ $index ]['closed'] = 1;
			}
		}

		return $rows;
	}

	/**
	 * Die Zeitfenster eines Kalendertags, Sonderregelungen eingerechnet.
	 *
	 * @param DateTimeInterface $date Tag in der Zeitzone der Website.
	 * @return array array( closed, slots, note, is_special ).
	 */
	public static function day_for( DateTimeInterface $date ) {
		$special = self::special_for( $date->format( 'Y-m-d' ) );

		if ( null !== $special ) {
			$window = self::window(
				isset( $special['from'] ) ? $special['from'] : '',
				isset( $special['to'] ) ? $special['to'] : ''
			);

			$slots = ( ! empty( $special['closed'] ) || null === $window ) ? array() : array( $window );

			return array(
				'closed'     => empty( $slots ),
				'slots'      => $slots,
				'note'       => isset( $special['note'] ) ? (string) $special['note'] : '',
				'is_special' => true,
			);
		}

		$slots = self::slots( strtolower( $date->format( 'D' ) ) );

		return array(
			'closed'     => empty( $slots ),
			'slots'      => $slots,
			'note'       => '',
			'is_special' => false,
		);
	}

	/**
	 * Die heute geltenden Zeitfenster, Sonderregelungen eingerechnet.
	 *
	 * Bewusst der Kalendertag: ein Fenster, das gestern begonnen hat und nach
	 * Mitternacht noch laeuft, erscheint hier nicht. So bleibt die Ausgabe den
	 * ganzen Tag gleich und vertraegt einen Seiten-Cache. Ob gerade geoeffnet ist,
	 * beantwortet is_open_now().
	 *
	 * @return array array( closed, slots, note, is_special ).
	 */
	public static function today() {
		return self::day_for( current_datetime() );
	}

	/**
	 * Ob gerade geoeffnet ist.
	 *
	 * Rechnet in der Zeitzone der Website, nicht in der des Servers.
	 *
	 * @return bool
	 */
	public static function is_open_now() {
		$now  = current_datetime();
		$time = $now->format( 'H:i' );

		// Ein Fenster, das gestern begonnen hat, kann ueber Mitternacht noch laufen.
		$yesterday = self::day_for( $now->modify( '-1 day' ) );

		foreach ( $yesterday['slots'] as $slot ) {
			if ( $slot['to'] < $slot['from'] && $time < $slot['to'] ) {
				return true;
			}
		}

		$today = self::day_for( $now );

		foreach ( $today['slots'] as $slot ) {
			// Beginnt heute und endet erst nach Mitternacht.
			if ( $slot['to'] < $slot['from'] ) {
				if ( $time >= $slot['from'] ) {
					return true;
				}

				continue;
			}

			// 23:59 steht fuer das Tagesende, sonst waere die letzte Minute geschlossen.
			if ( $time >= $slot['from'] && ( $time < $slot['to'] || '23:59' === $slot['to'] ) ) {
				return true;
			}
		}

		return false;
	}
}
