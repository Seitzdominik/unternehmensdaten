<?php
/**
 * Wo die Bausteine verwendet werden.
 *
 * Beantwortet fuer die Uebersicht und die Pruefung, auf welchen Seiten ein
 * Baustein steht: als Shortcode im Inhalt, in Vorlagen des Block-Editors (dort
 * speichert auch Etch) und in den Seitendaten von Bricks, die als Meta-Feld
 * liegen.
 *
 * Gesucht wird mit genau zwei Abfragen und nur dann, wenn das Backend danach
 * fragt, nie im Frontend. Das Ergebnis liegt einen Tag als Transient und wird
 * verworfen, sobald ein Inhalt gespeichert oder geloescht wird.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Usage
 */
final class UNDT_Usage {

	const TRANSIENT = 'undt_usage';

	/**
	 * Ergebnis dieses Aufrufs, damit mehrere Fragen nur einmal suchen.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Haengt das Verwerfen des Zwischenspeichers ein.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'save_post', array( __CLASS__, 'flush' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush' ) );
		add_action( 'updated_post_meta', array( __CLASS__, 'flush_meta' ), 10, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'flush_meta' ), 10, 3 );
	}

	/**
	 * Woran jeder Baustein im Inhalt zu erkennen ist.
	 *
	 * Muster statt fester Zeichenketten: [undt_hours] darf nicht auf
	 * [undt_hours_today] passen, und Bricks speichert Anfuehrungszeichen je
	 * nach Fassung maskiert.
	 *
	 * @return array Baustein => Muster.
	 */
	public static function patterns() {
		return array(
			'imprint'   => '/\[undt_impressum[\s\]\/]/',
			'legal_nav' => '/\[undt_legal_nav[\s\]\/]/',
			'footer'    => '/\[undt_footer[\s\]\/]/',
			'address'   => '/\[undt_address[\s\]\/]/',
			'privacy'   => '/\[undt_privacy_block\s+name=\\\\?["\']?controller/',
			'authority' => '/\[undt_privacy_block\s+name=\\\\?["\']?authority/',
			'dpo'       => '/\[undt_privacy_block\s+name=\\\\?["\']?dpo/',
			'hours'     => '/\[undt_hours[\s\]\/]/',
			'today'     => '/\[undt_hours_today[\s\]\/]/',
			'open_now'  => '/\[undt_open_now[\s\]\/]/',
			'social'    => '/\[undt_social[\s\]\/]/',
			'banner'    => '/\[undt_banner[\s\]\/]/',
			'prices'    => '/\[undt_prices[\s\]\/]/',
			'faq'       => '/\[undt_faq[\s\]\/]/',
		);
	}

	/**
	 * Die vier Bausteine der Uebersicht.
	 *
	 * Die vollstaendige Liste mit Vorschau und Optionen steht in UNDT_Embed.
	 *
	 * @return array Schluessel => array( title, desc, code ).
	 */
	public static function blocks() {
		$blocks = array(
			'imprint' => array(
				'title'   => __( 'Impressum', 'unternehmensdaten' ),
				'desc'    => __( 'Alle Pflichtangaben als fertiger Abschnitt', 'unternehmensdaten' ),
				'code'    => '[undt_impressum]',
			),
			'footer'  => array(
				'title'   => __( 'Footer', 'unternehmensdaten' ),
				'desc'    => __( 'Anschrift, Rechtslinks und Copyright', 'unternehmensdaten' ),
				'code'    => '[undt_footer]',
			),
			'privacy' => array(
				'title'   => __( 'Datenschutz: Verantwortlicher', 'unternehmensdaten' ),
				'desc'    => __( 'Für deine Datenschutzerklärung', 'unternehmensdaten' ),
				'code'    => '[undt_privacy_block name="controller"]',
			),
			'hours'   => array(
				'title'   => __( 'Öffnungszeiten', 'unternehmensdaten' ),
				'desc'    => __( 'Tabelle mit Sonderöffnungszeiten', 'unternehmensdaten' ),
				'code'    => '[undt_hours]',
			),
		);

		// Ohne Bereich Oeffnungszeiten gibt es auch den Baustein nicht.
		if ( ! UNDT_Modules::is_active( 'hours' ) ) {
			unset( $blocks['hours'] );
		}

		return $blocks;
	}

	/**
	 * Ob ein Text einen Baustein enthaelt.
	 *
	 * @param string $text Inhalt.
	 * @param string $key  Baustein.
	 * @return bool
	 */
	public static function matches( $text, $key ) {
		$patterns = self::patterns();

		return isset( $patterns[ $key ] ) && 1 === preg_match( $patterns[ $key ], (string) $text );
	}

	/**
	 * Wo jeder Baustein steht.
	 *
	 * @return array Baustein => Liste aus array( id, title, type, footer ).
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_transient( self::TRANSIENT );

		// Kennt der Zwischenspeicher andere Bausteine, stammt er aus einer anderen Fassung.
		if ( is_array( $stored ) && array_keys( $stored ) === array_keys( self::patterns() ) ) {
			self::$cache = $stored;

			return $stored;
		}

		self::$cache = self::scan();
		set_transient( self::TRANSIENT, self::$cache, DAY_IN_SECONDS );

		return self::$cache;
	}

	/**
	 * Wo ein Baustein steht.
	 *
	 * @param string $key Baustein.
	 * @return array
	 */
	public static function find( $key ) {
		$all = self::all();

		return isset( $all[ $key ] ) ? $all[ $key ] : array();
	}

	/**
	 * Ob ein Baustein auf einer bestimmten Seite steht.
	 *
	 * @param int    $id  Seite.
	 * @param string $key Baustein.
	 * @return bool
	 */
	public static function on_post( $id, $key ) {
		foreach ( self::find( $key ) as $place ) {
			if ( (int) $place['id'] === (int) $id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Ein kurzer Satz dazu, wo ein Baustein steht.
	 *
	 * @param string $key Baustein.
	 * @return string
	 */
	public static function label( $key ) {
		$places = self::find( $key );
		$footer = array_filter( array_column( $places, 'footer' ) );
		$pages  = count( $places ) - count( $footer );

		if ( ! empty( $footer ) ) {
			if ( 0 === $pages ) {
				return __( 'Im Footer', 'unternehmensdaten' );
			}

			/* translators: %d: Anzahl der Seiten. */
			return sprintf( _n( 'Im Footer und auf %d Seite', 'Im Footer und auf %d Seiten', $pages, 'unternehmensdaten' ), $pages );
		}

		if ( 0 === $pages ) {
			return __( 'Noch auf keiner Seite', 'unternehmensdaten' );
		}

		if ( 1 === $pages ) {
			/* translators: %s: Titel der Seite. */
			return sprintf( __( 'Auf „%s“', 'unternehmensdaten' ), $places[0]['title'] );
		}

		/* translators: %d: Anzahl der Seiten. */
		return sprintf( _n( 'Auf %d Seite', 'Auf %d Seiten', $pages, 'unternehmensdaten' ), $pages );
	}

	/**
	 * Sucht alle Bausteine in Inhalten und Seitendaten von Bricks.
	 *
	 * @return array
	 */
	private static function scan() {
		global $wpdb;

		$found = array_fill_keys( array_keys( self::patterns() ), array() );

		/*
		 * Zwei direkte Abfragen statt WP_Query: gesucht wird im Inhalt aller
		 * Inhaltstypen und in Meta-Feldern nach Schluessel-Praefix, das kann
		 * WP_Query nur ueber eine langsame meta_query. Zwischengespeichert wird
		 * das Ergebnis als Transient, siehe all().
		 */
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Kandidaten: alles, was ueberhaupt einen Shortcode des Plugins enthaelt.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_type, post_title, post_name, post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','private','draft','future','pending') AND post_type NOT IN ('revision','nav_menu_item') AND post_content LIKE %s",
				'%' . $wpdb->esc_like( '[undt_' ) . '%'
			),
			ARRAY_A
		);

		$meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.post_id AS ID, m.meta_key, m.meta_value, p.post_type, p.post_title, p.post_name FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key LIKE %s AND m.meta_value LIKE %s AND p.post_status IN ('publish','private','draft','future','pending')",
				$wpdb->esc_like( '_bricks_page_' ) . '%',
				'%' . $wpdb->esc_like( '[undt_' ) . '%'
			),
			ARRAY_A
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$rows = array();

		foreach ( (array) $posts as $row ) {
			$rows[] = array( $row, (string) $row['post_content'], false );
		}

		foreach ( (array) $meta as $row ) {
			$rows[] = array( $row, (string) $row['meta_value'], false !== strpos( (string) $row['meta_key'], 'footer' ) );
		}

		foreach ( $rows as $entry ) {
			list( $row, $text, $footer_meta ) = $entry;

			foreach ( array_keys( $found ) as $key ) {
				if ( ! self::matches( $text, $key ) ) {
					continue;
				}

				$id = (int) $row['ID'];

				// Eine Seite zaehlt einmal, auch wenn Inhalt und Meta-Feld treffen.
				if ( isset( $found[ $key ][ $id ] ) ) {
					$found[ $key ][ $id ]['footer'] = $found[ $key ][ $id ]['footer'] || $footer_meta;
					continue;
				}

				$found[ $key ][ $id ] = array(
					'id'     => $id,
					'title'  => '' !== (string) $row['post_title'] ? (string) $row['post_title'] : (string) $row['post_name'],
					'type'   => (string) $row['post_type'],
					'footer' => $footer_meta || self::is_footer( $row ),
				);
			}
		}

		return array_map( 'array_values', $found );
	}

	/**
	 * Ob ein Inhalt ein Footer ist: ein Template-Part oder eine Vorlage von
	 * Bricks, die so heisst.
	 *
	 * @param array $row Zeile mit post_type und post_name.
	 * @return bool
	 */
	private static function is_footer( array $row ) {
		$types = array( 'wp_template_part', 'bricks_template' );

		return in_array( $row['post_type'], $types, true ) && false !== strpos( strtolower( (string) $row['post_name'] ), 'footer' );
	}

	/**
	 * Verwirft den Zwischenspeicher.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Verwirft ihn auch, wenn Bricks seine Seitendaten schreibt.
	 *
	 * @param int    $meta_id Meta-ID.
	 * @param int    $post_id Beitrag.
	 * @param string $key     Meta-Schluessel.
	 * @return void
	 */
	public static function flush_meta( $meta_id, $post_id, $key ) {
		if ( 0 === strpos( (string) $key, '_bricks_page_' ) ) {
			self::flush();
		}
	}
}
