<?php
/**
 * Die Liste „Zu erledigen“ der Uebersicht.
 *
 * Macht aus den Punkten von UNDT_Audit eine kurze Liste mit je einer Aktion.
 * Fehlende Pflichtangaben eines Abschnitts werden zu einem Eintrag gebuendelt:
 * „4 Angaben zu deinem Beruf fehlen“ statt vier Zeilen. Die Aktion springt
 * direkt ins erste betroffene Feld.
 *
 * Wer einen Punkt nicht beheben darf, sieht ihn nicht: die verknuepften
 * Rechtsseiten etwa bleiben Admins vorbehalten.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Todo
 */
final class UNDT_Todo {

	/**
	 * Wie viele Eintraege ohne „Alle anzeigen“ sichtbar sind.
	 */
	const VISIBLE = 4;

	/**
	 * Abschnitte, deren Felder nur Admins bearbeiten.
	 */
	const ADMIN_SECTIONS = array( 'pages' );

	/**
	 * Abschnitte, die in der Liste mit einem anderen zusammen zaehlen: Beruf und
	 * Kammer stehen auf „Firma“ getrennt, sind aber eine Aufgabe.
	 */
	const BUNDLE = array( 'chamber' => 'profession' );

	/**
	 * Wie gebuendelte Angaben eines Abschnitts heissen: „4 … fehlen“.
	 *
	 * @return array Abschnitt => Bezeichnung in der Mehrzahl.
	 */
	private static function phrases() {
		return array(
			'identity'       => __( 'Angaben zu deiner Firma', 'unternehmensdaten' ),
			'address'        => __( 'Angaben zur Anschrift', 'unternehmensdaten' ),
			'contact'        => __( 'Kontaktangaben', 'unternehmensdaten' ),
			'register'       => __( 'Angaben zum Registereintrag', 'unternehmensdaten' ),
			'register2'      => __( 'Angaben zur Komplementär-GmbH', 'unternehmensdaten' ),
			'representation' => __( 'Angaben zur Vertretung', 'unternehmensdaten' ),
			'capital'        => __( 'Angaben zur Abwicklung', 'unternehmensdaten' ),
			'tax'            => __( 'steuerliche Angaben', 'unternehmensdaten' ),
			'profession'     => __( 'Angaben zu deinem Beruf', 'unternehmensdaten' ),
			'authority'      => __( 'Angaben zur Aufsichtsbehörde', 'unternehmensdaten' ),
			'insurance'      => __( 'Angaben zur Berufshaftpflicht', 'unternehmensdaten' ),
			'editorial'      => __( 'Angaben zur redaktionellen Verantwortung', 'unternehmensdaten' ),
		);
	}

	/**
	 * Eigene Titel fuer einzelne Felder, deren Bezeichnung als Satz holpert.
	 *
	 * @return array Feld => Titel.
	 */
	private static function single_titles() {
		return array(
			'editorial_name' => __( 'Verantwortliche Person für Inhalte fehlt', 'unternehmensdaten' ),
		);
	}

	/**
	 * Die Eintraege fuer den aktuellen Benutzer.
	 *
	 * @param array|null $issues Ergebnis von UNDT_Audit::run(), null fuer einen neuen Lauf.
	 * @return array Liste aus array( tone, title, desc, law, action, url ).
	 */
	public static function items( $issues = null ) {
		$issues  = null === $issues ? UNDT_Audit::run() : $issues;
		$missing = array();
		$other   = array();

		foreach ( $issues as $issue ) {
			if ( isset( $issue['kind'] ) && 'missing' === $issue['kind'] && ! empty( $issue['field'] ) ) {
				$field   = UNDT_Schema::field( $issue['field'] );
				$section = is_array( $field ) ? $field['section'] : 'other';
				$section = isset( self::BUNDLE[ $section ] ) ? self::BUNDLE[ $section ] : $section;

				$missing[ $section ][] = $issue;
				continue;
			}

			$other[] = $issue;
		}

		$items = array();

		foreach ( $missing as $section => $group ) {
			$item = self::bundle( $section, $group );

			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		// Erst die Pflicht, dann dringende Punkte, dann Hinweise.
		foreach ( array( 'error', 'warning', 'notice' ) as $level ) {
			foreach ( $other as $issue ) {
				if ( ( isset( $issue['level'] ) ? $issue['level'] : 'notice' ) !== $level ) {
					continue;
				}

				$item = self::single( $issue );

				if ( null !== $item ) {
					$items[] = $item;
				}
			}
		}

		return $items;
	}

	/**
	 * Ein Eintrag fuer die fehlenden Pflichtangaben eines Abschnitts.
	 *
	 * @param string $section Abschnitt.
	 * @param array  $group   Punkte aus UNDT_Audit.
	 * @return array|null
	 */
	private static function bundle( $section, array $group ) {
		if ( ! self::may_fix( $section ) ) {
			return null;
		}

		$first  = $group[0];
		$fields = array();
		$bases  = array();

		foreach ( $group as $issue ) {
			$field    = UNDT_Schema::field( $issue['field'] );
			$fields[] = is_array( $field ) ? $field['label'] : $issue['field'];
			$bases[]  = (string) $issue['basis'];
		}

		$bases = array_values( array_unique( array_filter( $bases ) ) );
		$law   = 1 === count( $bases ) ? $bases[0] : ( isset( $bases[0] ) ? $bases[0] : '' );
		$count = count( $group );

		if ( 1 === $count ) {
			$titles = self::single_titles();

			return array(
				'tone'    => 'open',
				'title'   => isset( $titles[ $first['field'] ] ) ? $titles[ $first['field'] ] : $first['title'],
				'desc'    => self::first_sentence( (string) $first['detail'] ),
				'law'     => $law,
				'action'  => __( 'Eintragen', 'unternehmensdaten' ),
				'url'     => self::field_url( $first['field'] ),
				'suggest' => '',
			);
		}

		$phrases  = self::phrases();
		$sections = UNDT_Schema::sections();
		$phrase   = isset( $phrases[ $section ] )
			? $phrases[ $section ]
			/* translators: %s: Name des Abschnitts. */
			: sprintf( __( 'Angaben im Abschnitt „%s“', 'unternehmensdaten' ), isset( $sections[ $section ] ) ? $sections[ $section ] : $section );

		return array(
			'tone'    => 'open',
			/* translators: 1: Anzahl, 2: Bezeichnung, etwa „Angaben zu deinem Beruf“. */
			'title'   => sprintf( __( '%1$d %2$s fehlen', 'unternehmensdaten' ), $count, $phrase ),
			'desc'    => self::sentence( self::join( $fields ) ),
			'law'     => $law,
			'action'  => __( 'Ergänzen', 'unternehmensdaten' ),
			'url'     => self::field_url( $first['field'] ),
			'suggest' => '',
		);
	}

	/**
	 * Ein Eintrag fuer eine Regel oder einen Fund in den Rechtsseiten.
	 *
	 * @param array $issue Punkt aus UNDT_Audit.
	 * @return array|null
	 */
	private static function single( array $issue ) {
		$level = isset( $issue['level'] ) ? $issue['level'] : 'notice';
		$url   = '';

		if ( ! empty( $issue['edit'] ) ) {
			if ( ! current_user_can( 'edit_pages' ) ) {
				return null;
			}

			$url    = (string) $issue['edit'];
			$action = __( 'Seite bearbeiten', 'unternehmensdaten' );
		} elseif ( ! empty( $issue['field'] ) ) {
			$field   = UNDT_Schema::field( $issue['field'] );
			$section = is_array( $field ) ? $field['section'] : '';

			if ( ! self::may_fix( $section ) ) {
				return null;
			}

			$url    = self::field_url( $issue['field'] );
			// Fehlt die Angabe ganz, wird sie eingetragen, sonst geprueft.
			if ( 'pages' === $section ) {
				$action = __( 'Seite wählen', 'unternehmensdaten' );
			} elseif ( ! UNDT_Store::has( $issue['field'] ) ) {
				$action = __( 'Eintragen', 'unternehmensdaten' );
			} else {
				$action = __( 'Prüfen', 'unternehmensdaten' );
			}
		} elseif ( ! empty( $issue['tab'] ) ) {
			$url    = admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_COMPANY ) . '#' . $issue['tab'];
			$action = __( 'Prüfen', 'unternehmensdaten' );
		} else {
			$action = '';
		}

		$item = array(
			'tone'    => 'notice' === $level ? 'missing' : 'open',
			'title'   => (string) $issue['title'],
			'desc'    => self::first_sentence( (string) $issue['detail'] ),
			'law'     => (string) ( isset( $issue['basis'] ) ? $issue['basis'] : '' ),
			'action'  => $action,
			'url'     => $url,
			'suggest' => '',
		);

		// Gibt es einen Vorschlag, wird er hier direkt uebernommen, etwa die Aufsichtsbehoerde.
		$suggestions = ! empty( $issue['field'] ) ? UNDT_Company_View::suggestions() : array();

		if ( ! empty( $issue['field'] ) && isset( $suggestions[ $issue['field'] ] ) ) {
			$item['action']  = __( 'Übernehmen', 'unternehmensdaten' );
			$item['suggest'] = $issue['field'];

			if ( 'privacy_authority' === $issue['field'] ) {
				$states    = UNDT_Schema::states();
				$state     = UNDT_Store::get( 'state' );
				$authority = UNDT_Authorities::for_state( $state );

				/* translators: 1: Bundesland, 2: Name der Behoerde. */
				$item['desc'] = sprintf( __( 'Für %1$s ist zuständig: %2$s.', 'unternehmensdaten' ), $states[ $state ], $authority['name'] );
			}
		}

		return $item;
	}

	/**
	 * Ob der aktuelle Benutzer die Felder eines Abschnitts bearbeiten darf.
	 *
	 * @param string $section Abschnitt.
	 * @return bool
	 */
	private static function may_fix( $section ) {
		return in_array( $section, self::ADMIN_SECTIONS, true ) ? UNDT_Access::is_admin() : UNDT_Access::can_edit();
	}

	/**
	 * Die Adresse, die direkt in ein Feld der Seite „Firma“ springt.
	 *
	 * Das Skript dort oeffnet die passende Registerkarte und setzt den Fokus.
	 *
	 * @param string $key Feld.
	 * @return string
	 */
	public static function field_url( $key ) {
		return admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_COMPANY ) . '#undt-' . sanitize_key( $key );
	}

	/**
	 * Der erste Satz eines Hinweises: die Liste soll kurz bleiben.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function first_sentence( $text ) {
		$text = trim( $text );

		if ( preg_match( '/^(.+?[.!?])\s+\p{Lu}/u', $text, $match ) ) {
			return $match[1];
		}

		return $text;
	}

	/**
	 * Schliesst einen Satz mit Punkt, sofern er nicht schon ein Satzzeichen traegt.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function sentence( $text ) {
		return preg_match( '/[.!?]$/u', $text ) ? $text : $text . '.';
	}

	/**
	 * Verbindet Bezeichnungen zu „A, B und C“.
	 *
	 * @param array $parts Teile.
	 * @return string
	 */
	private static function join( array $parts ) {
		if ( count( $parts ) < 2 ) {
			return (string) reset( $parts );
		}

		$last = array_pop( $parts );

		/* translators: 1: Aufzaehlung, 2: letztes Glied. */
		return sprintf( __( '%1$s und %2$s', 'unternehmensdaten' ), implode( ', ', $parts ), $last );
	}
}
