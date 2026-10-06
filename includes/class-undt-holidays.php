<?php
/**
 * Gesetzliche Feiertage je Bundesland.
 *
 * Nur Feiertage, die im ganzen Land gelten. Was nur in einzelnen Gemeinden
 * gilt, fehlt bewusst: Mariae Himmelfahrt in Bayern (Gemeinden mit
 * ueberwiegend katholischer Bevoelkerung), Fronleichnam in Sachsen und
 * Thueringen (einzelne Gemeinden), das Augsburger Friedensfest. Wer dort liegt,
 * traegt den Tag selbst ein.
 *
 * Stand Oktober 2026, abgeglichen mit den Feiertagsgesetzen der Laender, der
 * Uebersicht des DGB und Wikipedia „Gesetzliche Feiertage in Deutschland“.
 * Einmalige Feiertage (Berlin, 17.06.2028) passen in keine Regel und fehlen.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Holidays
 */
final class UNDT_Holidays {

	/**
	 * Die Feiertage mit ihrer Regel und den Laendern, in denen sie landesweit gelten.
	 *
	 * Regel: „MM-DD“ fuer ein festes Datum, eine Zahl fuer Tage ab Ostersonntag,
	 * „bbt“ fuer den Buss- und Bettag (Mittwoch vor dem 23. November).
	 *
	 * @return array Schluessel => rule, states (leer = alle Laender).
	 */
	public static function rules() {
		return array(
			'neujahr'                   => array( '01-01', array() ),
			'heilige_drei_koenige'      => array( '01-06', array( 'BW', 'BY', 'ST' ) ),
			'frauentag'                 => array( '03-08', array( 'BE', 'MV' ) ),
			'karfreitag'                => array( -2, array() ),
			'ostersonntag'              => array( 0, array( 'BB' ) ),
			'ostermontag'               => array( 1, array() ),
			'tag_der_arbeit'            => array( '05-01', array() ),
			'christi_himmelfahrt'       => array( 39, array() ),
			'pfingstsonntag'            => array( 49, array( 'BB' ) ),
			'pfingstmontag'             => array( 50, array() ),
			'fronleichnam'              => array( 60, array( 'BW', 'BY', 'HE', 'NW', 'RP', 'SL' ) ),
			'mariae_himmelfahrt'        => array( '08-15', array( 'SL' ) ),
			'weltkindertag'             => array( '09-20', array( 'TH' ) ),
			'tag_der_deutschen_einheit' => array( '10-03', array() ),
			'reformationstag'           => array( '10-31', array( 'BB', 'HB', 'HH', 'MV', 'NI', 'SN', 'ST', 'SH', 'TH' ) ),
			'allerheiligen'             => array( '11-01', array( 'BW', 'BY', 'NW', 'RP', 'SL' ) ),
			'buss_und_bettag'           => array( 'bbt', array( 'SN' ) ),
			'weihnachten_1'             => array( '12-25', array() ),
			'weihnachten_2'             => array( '12-26', array() ),
		);
	}

	/**
	 * Die Namen der Feiertage.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'neujahr'                   => __( 'Neujahr', 'unternehmensdaten' ),
			'heilige_drei_koenige'      => __( 'Heilige Drei Könige', 'unternehmensdaten' ),
			'frauentag'                 => __( 'Internationaler Frauentag', 'unternehmensdaten' ),
			'karfreitag'                => __( 'Karfreitag', 'unternehmensdaten' ),
			'ostersonntag'              => __( 'Ostersonntag', 'unternehmensdaten' ),
			'ostermontag'               => __( 'Ostermontag', 'unternehmensdaten' ),
			'tag_der_arbeit'            => __( 'Tag der Arbeit', 'unternehmensdaten' ),
			'christi_himmelfahrt'       => __( 'Christi Himmelfahrt', 'unternehmensdaten' ),
			'pfingstsonntag'            => __( 'Pfingstsonntag', 'unternehmensdaten' ),
			'pfingstmontag'             => __( 'Pfingstmontag', 'unternehmensdaten' ),
			'fronleichnam'              => __( 'Fronleichnam', 'unternehmensdaten' ),
			'mariae_himmelfahrt'        => __( 'Mariä Himmelfahrt', 'unternehmensdaten' ),
			'weltkindertag'             => __( 'Weltkindertag', 'unternehmensdaten' ),
			'tag_der_deutschen_einheit' => __( 'Tag der Deutschen Einheit', 'unternehmensdaten' ),
			'reformationstag'           => __( 'Reformationstag', 'unternehmensdaten' ),
			'allerheiligen'             => __( 'Allerheiligen', 'unternehmensdaten' ),
			'buss_und_bettag'           => __( 'Buß- und Bettag', 'unternehmensdaten' ),
			'weihnachten_1'             => __( '1. Weihnachtstag', 'unternehmensdaten' ),
			'weihnachten_2'             => __( '2. Weihnachtstag', 'unternehmensdaten' ),
		);
	}

	/**
	 * Ostersonntag eines Jahres nach der Gaussschen Osterformel (Anonymer
	 * gregorianischer Algorithmus). Ohne easter_date(), das die Erweiterung
	 * calendar voraussetzt.
	 *
	 * @param int $year Jahr.
	 * @return DateTimeImmutable
	 */
	public static function easter( $year ) {
		$year  = (int) $year;
		$a     = $year % 19;
		$b     = intdiv( $year, 100 );
		$c     = $year % 100;
		$d     = intdiv( $b, 4 );
		$e     = $b % 4;
		$f     = intdiv( $b + 8, 25 );
		$g     = intdiv( $b - $f + 1, 3 );
		$h     = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i     = intdiv( $c, 4 );
		$k     = $c % 4;
		$l     = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m     = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;

		return new DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, $day ), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Die Feiertage eines Landes in einem Jahr.
	 *
	 * @param string $state Kuerzel des Landes, etwa BY.
	 * @param int    $year  Jahr.
	 * @return array Datum (YYYY-MM-DD) => Name, nach Datum sortiert. Leer bei unbekanntem Land.
	 */
	public static function for_state( $state, $year ) {
		$state = strtoupper( (string) $state );

		if ( '' === $state || ! array_key_exists( $state, UNDT_Schema::states() ) ) {
			return array();
		}

		$labels = self::labels();
		$easter = self::easter( $year );
		$out    = array();

		foreach ( self::rules() as $key => $rule ) {
			list( $when, $states ) = $rule;

			if ( ! empty( $states ) && ! in_array( $state, $states, true ) ) {
				continue;
			}

			if ( is_int( $when ) ) {
				$date = $easter->modify( ( $when < 0 ? '' : '+' ) . $when . ' days' )->format( 'Y-m-d' );
			} elseif ( 'bbt' === $when ) {
				// Der Mittwoch vor dem 23. November: zwischen dem 16. und dem 22.
				$date = ( new DateTimeImmutable( (int) $year . '-11-23', new DateTimeZone( 'UTC' ) ) )->modify( 'last wednesday' )->format( 'Y-m-d' );
			} else {
				$date = sprintf( '%04d-%s', (int) $year, $when );
			}

			$out[ $date ] = $labels[ $key ];
		}

		ksort( $out );

		return $out;
	}

	/**
	 * Die Feiertage der naechsten zwoelf Monate ab einem Tag.
	 *
	 * @param string $state Kuerzel des Landes.
	 * @param string $from  Erster Tag, YYYY-MM-DD.
	 * @return array Liste aus date und label.
	 */
	public static function upcoming( $state, $from ) {
		$from = UNDT_Hours::date( $from );

		if ( '' === $from ) {
			return array();
		}

		$year  = (int) substr( $from, 0, 4 );
		$until = ( $year + 1 ) . substr( $from, 4 );
		$all   = self::for_state( $state, $year ) + self::for_state( $state, $year + 1 );
		$out   = array();

		foreach ( $all as $date => $label ) {
			if ( $date >= $from && $date < $until ) {
				$out[] = array(
					'date'  => $date,
					'label' => $label,
				);
			}
		}

		return $out;
	}
}
