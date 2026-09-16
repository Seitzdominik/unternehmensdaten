<?php
/**
 * Oeffentliche Schnittstelle fuer Themes und Page Builder.
 *
 * Die Shortcodes decken den Redaktionsalltag ab. Wer mit Bricks, Breakdance oder
 * Etch baut, braucht die Daten dagegen als Array, um sie in eigene Schleifen und
 * dynamische Felder zu geben. Genau das liefert diese Klasse, zusammen mit ein
 * paar globalen Funktionen, die sich in den Dynamic-Data-Feldern der Builder
 * direkt aufrufen lassen.
 *
 * Bewusst kein REST-Endpunkt: Page Builder laufen serverseitig und brauchen
 * keinen. Ein oeffentlicher Endpunkt waere zusaetzliche Angriffsflaeche ohne
 * Gegenwert.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Api
 */
final class UNDT_Api {

	/**
	 * Die abfragbaren Datenquellen.
	 *
	 * Der Schluessel ist zugleich der Name der Query im Page Builder.
	 *
	 * @return array
	 */
	public static function sources() {
		return array(
			'undt_hours'         => array(
				'label'  => __( 'Öffnungszeiten, ein Eintrag je Wochentag', 'unternehmensdaten' ),
				'module' => 'hours',
				'fields' => 'day, day_label, day_short, closed, times, slots',
			),
			'undt_hours_grouped' => array(
				'label'  => __( 'Öffnungszeiten, gleiche Tage zusammengefasst', 'unternehmensdaten' ),
				'module' => 'hours',
				'fields' => 'days, days_label, closed, times, slots',
			),
			'undt_hours_special' => array(
				'label'  => __( 'Sonderöffnungszeiten, nur künftige', 'unternehmensdaten' ),
				'module' => 'hours',
				'fields' => 'date, date_label, closed, from, to, times, note',
			),
			'undt_prices'        => array(
				'label'  => __( 'Preise und Leistungen', 'unternehmensdaten' ),
				'module' => 'prices',
				'fields' => 'group, label, price, note',
			),
			'undt_faq'           => array(
				'label'  => __( 'Fragen und Antworten', 'unternehmensdaten' ),
				'module' => 'faq',
				'fields' => 'group, question, answer',
			),
			'undt_social'        => array(
				'label'  => __( 'Social-Profile', 'unternehmensdaten' ),
				'module' => 'social',
				'fields' => 'platform, platform_label, label, url',
			),
		);
	}

	/**
	 * Haengt die Builder-Anbindungen ein.
	 *
	 * @return void
	 */
	public static function register() {
		// Bricks: eigene Query-Typen im Schleifen-Dialog anbieten.
		add_filter( 'bricks/setup/control_options', array( __CLASS__, 'bricks_options' ) );
		add_filter( 'bricks/query/run', array( __CLASS__, 'bricks_run' ), 10, 2 );

		// Bricks: die Funktionen fuer das Dynamic-Data-Tag {echo:...} freigeben.
		add_filter( 'bricks/code/echo_function_names', array( __CLASS__, 'bricks_echo_functions' ), 10, 2 );
	}

	/**
	 * Liefert die Zeilen einer Datenquelle.
	 *
	 * @param string $source Name der Quelle, siehe sources().
	 * @param array  $args   group filtert nach Gruppe, limit begrenzt die Anzahl.
	 * @return array Liste assoziativer Arrays.
	 */
	public static function query( $source, array $args = array() ) {
		$sources = self::sources();

		if ( ! isset( $sources[ $source ] ) ) {
			return array();
		}

		// Ein abgeschalteter Bereich liefert nichts, genau wie sein Shortcode.
		if ( ! UNDT_Modules::is_active( $sources[ $source ]['module'] ) ) {
			return array();
		}

		$args = array_merge(
			array(
				'group' => '',
				'limit' => 0,
			),
			$args
		);

		switch ( $source ) {
			case 'undt_hours':
				$rows = self::hours_rows();
				break;

			case 'undt_hours_grouped':
				$rows = self::hours_grouped_rows();
				break;

			case 'undt_hours_special':
				$rows = self::hours_special_rows();
				break;

			case 'undt_prices':
				$rows = self::content_rows( 'prices', 'items', $args['group'] );
				break;

			case 'undt_faq':
				$rows = self::content_rows( 'faq', 'items', $args['group'] );
				break;

			case 'undt_social':
				$rows = self::social_rows();
				break;

			default:
				$rows = array();
		}

		if ( $args['limit'] > 0 ) {
			$rows = array_slice( $rows, 0, (int) $args['limit'] );
		}

		/**
		 * Erlaubt das Anpassen der Zeilen einer Datenquelle.
		 *
		 * @param array  $rows   Zeilen.
		 * @param string $source Name der Quelle.
		 * @param array  $args   Argumente.
		 */
		return (array) apply_filters( 'undt_query', $rows, $source, $args );
	}

	/**
	 * Zeitfenster als lesbarer Text.
	 *
	 * Page Builder koennen mit verschachtelten Arrays wenig anfangen, deshalb
	 * liegt jeder Zeile zusaetzlich die fertige Zeichenkette bei.
	 *
	 * @param array $slots Zeitfenster.
	 * @return string
	 */
	public static function format_slots( array $slots ) {
		$suffix = trim( (string) UNDT_Content::value( 'hours', 'suffix' ) );
		$parts  = array();

		foreach ( $slots as $slot ) {
			if ( empty( $slot['from'] ) || empty( $slot['to'] ) ) {
				continue;
			}

			$parts[] = $slot['from'] . ' – ' . $slot['to'];
		}

		if ( empty( $parts ) ) {
			return '';
		}

		$text = implode( ', ', $parts );

		return '' === $suffix ? $text : $text . ' ' . $suffix;
	}

	/**
	 * Die Bezeichnung fuer geschlossene Tage.
	 *
	 * @return string
	 */
	private static function closed_label() {
		$label = trim( (string) UNDT_Content::value( 'hours', 'closed_label' ) );

		return '' === $label ? __( 'geschlossen', 'unternehmensdaten' ) : $label;
	}

	/**
	 * Die heute geltenden Zeiten als Text.
	 *
	 * Sondertermine gehen den regulaeren Zeiten vor, siehe UNDT_Hours::today().
	 *
	 * @return string Leerstring, wenn der Bereich abgeschaltet ist oder keine Zeiten hinterlegt sind.
	 */
	public static function today_text() {
		if ( ! UNDT_Modules::is_active( 'hours' ) || ! UNDT_Hours::has_data() ) {
			return '';
		}

		$today = UNDT_Hours::today();

		return $today['closed'] ? self::closed_label() : self::format_slots( $today['slots'] );
	}

	/**
	 * Ein Eintrag je Wochentag.
	 *
	 * @return array
	 */
	private static function hours_rows() {
		$rows = array();

		foreach ( UNDT_Hours::display_order() as $day ) {
			$slots = UNDT_Hours::slots( $day );

			$rows[] = array(
				'day'       => $day,
				'day_label' => UNDT_Hours::day_label( $day ),
				'day_short' => UNDT_Hours::day_label( $day, true ),
				'closed'    => empty( $slots ),
				'times'     => empty( $slots ) ? self::closed_label() : self::format_slots( $slots ),
				'slots'     => $slots,
			);
		}

		return $rows;
	}

	/**
	 * Aufeinanderfolgende Tage mit gleichen Zeiten zusammengefasst.
	 *
	 * @return array
	 */
	private static function hours_grouped_rows() {
		$rows = array();

		foreach ( UNDT_Hours::grouped() as $group ) {
			$days  = $group['days'];
			$count = count( $days );

			if ( 1 === $count ) {
				$label = UNDT_Hours::day_label( $days[0], true );
			} elseif ( 2 === $count ) {
				$label = UNDT_Hours::day_label( $days[0], true ) . ', ' . UNDT_Hours::day_label( $days[1], true );
			} else {
				$label = UNDT_Hours::day_label( $days[0], true ) . ' – ' . UNDT_Hours::day_label( $days[ $count - 1 ], true );
			}

			$rows[] = array(
				'days'       => $days,
				'days_label' => $label,
				'closed'     => (bool) $group['closed'],
				'times'      => $group['closed'] ? self::closed_label() : self::format_slots( $group['slots'] ),
				'slots'      => $group['slots'],
			);
		}

		return $rows;
	}

	/**
	 * Kuenftige Sonderoeffnungszeiten.
	 *
	 * @return array
	 */
	private static function hours_special_rows() {
		$rows = array();

		foreach ( UNDT_Hours::upcoming_special() as $entry ) {
			$date = UNDT_Hours::date( isset( $entry['date'] ) ? $entry['date'] : '' );

			if ( '' === $date ) {
				continue;
			}

			$from   = UNDT_Hours::time( isset( $entry['from'] ) ? $entry['from'] : '' );
			$to     = UNDT_Hours::time( isset( $entry['to'] ) ? $entry['to'] : '' );
			$window = UNDT_Hours::window( $from, $to );
			$closed = ! empty( $entry['closed'] ) || null === $window;

			if ( null !== $window ) {
				$from = $window['from'];
				$to   = $window['to'];
			}

			$rows[] = array(
				'date'       => $date,
				'date_label' => UNDT_Hours::date_label( $date ),
				'closed'     => $closed,
				'from'       => $from,
				'to'         => $to,
				'times'      => $closed ? self::closed_label() : self::format_slots( array( $window ) ),
				'note'       => isset( $entry['note'] ) ? (string) $entry['note'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Zeilen eines Wiederholungsfeldes, auf Wunsch nach Gruppe gefiltert.
	 *
	 * @param string $module Modul-Schluessel.
	 * @param string $field  Feldschluessel.
	 * @param string $group  Nur diese Gruppe, leer fuer alle.
	 * @return array
	 */
	private static function content_rows( $module, $field, $group = '' ) {
		$rows  = UNDT_Content::rows( $module, $field );
		$group = trim( (string) $group );

		if ( '' === $group ) {
			return $rows;
		}

		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $group ) {
					return isset( $row['group'] ) && 0 === strcasecmp( trim( (string) $row['group'] ), $group );
				}
			)
		);
	}

	/**
	 * Social-Profile mit aufgeloestem Plattformnamen.
	 *
	 * @return array
	 */
	private static function social_rows() {
		$platforms = UNDT_Modules::platforms();
		$rows      = array();

		foreach ( UNDT_Content::rows( 'social', 'items' ) as $row ) {
			$url = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';

			if ( '' === $url ) {
				continue;
			}

			$platform = isset( $row['platform'] ) ? (string) $row['platform'] : 'other';
			$name     = isset( $platforms[ $platform ] ) ? $platforms[ $platform ] : $platform;
			$label    = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';

			$rows[] = array(
				'platform'       => $platform,
				'platform_label' => $name,
				'label'          => '' === $label ? $name : $label,
				'url'            => $url,
			);
		}

		return $rows;
	}

	/* ----------------------------------------------------------- Bricks --- */

	/**
	 * Traegt die Datenquellen in die Auswahl des Schleifen-Dialogs ein.
	 *
	 * @param array $options Steuerelement-Optionen von Bricks.
	 * @return array
	 */
	public static function bricks_options( $options ) {
		if ( ! is_array( $options ) || ! isset( $options['queryTypes'] ) ) {
			return $options;
		}

		foreach ( self::sources() as $source => $meta ) {
			$options['queryTypes'][ $source ] = 'Unternehmensdaten: ' . $meta['label'];
		}

		return $options;
	}

	/**
	 * Gibt die Funktionen fuer das Bricks-Tag {echo:...} frei.
	 *
	 * Seit Bricks 1.9.7 fuehrt das Tag nur freigegebene Funktionen aus. Je nach
	 * Fassung reicht Bricks eine Liste durch, prueft einen einzelnen Namen oder
	 * beides, deshalb deckt die Methode alle drei Formen ab. undt_query fehlt
	 * bewusst: es liefert ein Array, und das laesst sich nicht ausgeben.
	 *
	 * @param mixed  $allowed       Bisherige Freigabe: Liste, Wahrheitswert oder Funktionsname.
	 * @param string $function_name Gepruefter Funktionsname, falls Bricks ihn mitgibt.
	 * @return mixed
	 */
	public static function bricks_echo_functions( $allowed = array(), $function_name = '' ) {
		$own = array( 'undt_get', 'undt_has', 'undt_field', 'undt_loop', 'undt_is_open', 'undt_today' );

		if ( is_array( $allowed ) ) {
			return array_values( array_unique( array_merge( $allowed, $own ) ) );
		}

		$name = is_string( $allowed ) ? $allowed : (string) $function_name;

		return in_array( $name, $own, true ) ? true : $allowed;
	}

	/**
	 * Liefert Bricks die Zeilen der gewaehlten Datenquelle.
	 *
	 * @param array  $results Bisheriges Ergebnis.
	 * @param object $query   Query-Objekt von Bricks.
	 * @return array
	 */
	public static function bricks_run( $results, $query ) {
		if ( ! is_object( $query ) || ! isset( $query->object_type ) ) {
			return $results;
		}

		$source = (string) $query->object_type;

		if ( ! array_key_exists( $source, self::sources() ) ) {
			return $results;
		}

		/*
		 * Wo Bricks die Argumente eines eigenen Query-Typs ablegt, ist nicht
		 * dokumentiert. Gelesen werden deshalb die Einstellungen des Elements und
		 * query_vars, wobei query_vars Vorrang hat.
		 */
		$args = array();

		if ( isset( $query->settings['query'] ) && is_array( $query->settings['query'] ) ) {
			$args = $query->settings['query'];
		}

		if ( isset( $query->query_vars ) && is_array( $query->query_vars ) ) {
			$args = array_merge( $args, $query->query_vars );
		}

		return self::query(
			$source,
			array(
				'group' => isset( $args['undt_group'] ) && is_scalar( $args['undt_group'] ) ? (string) $args['undt_group'] : '',
				'limit' => isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 0,
			)
		);
	}

	/**
	 * Der Wert eines Feldes der gerade laufenden Schleife.
	 *
	 * @param string $key     Feldschluessel der Zeile.
	 * @param string $default Ersatzwert.
	 * @return string
	 */
	public static function loop_value( $key, $default = '' ) {
		$object = null;

		// Bricks haelt das aktuelle Schleifenobjekt in seiner Query-Klasse.
		if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_loop_object' ) ) {
			$object = \Bricks\Query::get_loop_object();
		}

		if ( ! is_array( $object ) || ! array_key_exists( $key, $object ) ) {
			return $default;
		}

		$value = $object[ $key ];

		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}

		if ( is_array( $value ) ) {
			return '';
		}

		return (string) $value;
	}
}

/* -------------------------------------------------------------------------
 * Globale Funktionen.
 *
 * Bricks, Breakdance und Etch koennen in ihren Feldern fuer dynamische Daten
 * PHP-Funktionen aufrufen. Diese hier sind genau dafuer gedacht und bewusst
 * kurz gehalten.
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'undt_get' ) ) {
	/**
	 * Ein Stammdaten-Feld.
	 *
	 * @param string $key     Feldschluessel, etwa phone oder company_name.
	 * @param string $default Ersatzwert, wenn das Feld leer ist.
	 * @return string
	 */
	function undt_get( $key, $default = '' ) {
		$value = UNDT_Store::get( (string) $key );

		return '' === trim( $value ) ? (string) $default : $value;
	}
}

if ( ! function_exists( 'undt_has' ) ) {
	/**
	 * Ob ein Stammdaten-Feld befuellt ist.
	 *
	 * @param string $key Feldschluessel.
	 * @return bool
	 */
	function undt_has( $key ) {
		return UNDT_Store::has( (string) $key );
	}
}

if ( ! function_exists( 'undt_field' ) ) {
	/**
	 * Ein Feld eines Inhaltsbereichs.
	 *
	 * @param string $module Bereich, etwa hours oder seo.
	 * @param string $key    Feldschluessel.
	 * @return mixed
	 */
	function undt_field( $module, $key ) {
		return UNDT_Content::value( (string) $module, (string) $key );
	}
}

if ( ! function_exists( 'undt_query' ) ) {
	/**
	 * Die Zeilen einer Datenquelle.
	 *
	 * @param string $source Name der Quelle, etwa undt_faq.
	 * @param array  $args   group und limit.
	 * @return array
	 */
	function undt_query( $source, array $args = array() ) {
		return UNDT_Api::query( (string) $source, $args );
	}
}

if ( ! function_exists( 'undt_loop' ) ) {
	/**
	 * Ein Feld der gerade laufenden Schleife.
	 *
	 * @param string $key     Feldschluessel der Zeile.
	 * @param string $default Ersatzwert.
	 * @return string
	 */
	function undt_loop( $key, $default = '' ) {
		return UNDT_Api::loop_value( (string) $key, (string) $default );
	}
}

if ( ! function_exists( 'undt_is_open' ) ) {
	/**
	 * Ob gerade geoeffnet ist.
	 *
	 * @return bool
	 */
	function undt_is_open() {
		return UNDT_Modules::is_active( 'hours' ) && UNDT_Hours::is_open_now();
	}
}

if ( ! function_exists( 'undt_today' ) ) {
	/**
	 * Die heute geltenden Zeiten als Text.
	 *
	 * @return string
	 */
	function undt_today() {
		return UNDT_Api::today_text();
	}
}
