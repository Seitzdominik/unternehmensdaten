<?php
/**
 * Die Bausteine des Backends.
 *
 * Jede Seite setzt sich aus denselben Teilen zusammen: Seitenkopf mit
 * Speicherstatus, Karte mit Kopf und Fuss, Statuszeile, Info-Card,
 * Bernstein-Zeile, Icon-Kachel mit Zaehler, Link-Card, Segment-Schalter,
 * Schalter, Schrittliste und aufklappbarer Abschnitt. Gestaltet werden sie in
 * admin.css ueber die Tokens unter .undt-app.
 *
 * Die Bedeutung der Farben ist fest: Blau ist bedienbar, Petrol heisst in
 * Ordnung, Bernstein heisst fehlt und ist Pflicht, Grau heisst fehlt und ist
 * erwartbar. Kein Zustand wird nur ueber die Farbe vermittelt, es steht immer
 * ein Text dabei, notfalls fuer Screenreader.
 *
 * Alle Methoden geben direkt aus und escapen jeden Wert selbst. Wo HTML
 * hineingereicht wird, laeuft es durch wp_kses_post().
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Ui
 */
final class UNDT_Ui {

	/**
	 * Ein Symbol, siehe UNDT_Ui_Icons.
	 *
	 * @param string $name  Name.
	 * @param int    $size  Groesse.
	 * @param string $class Zusaetzliche Klasse.
	 * @return void
	 */
	public static function icon( $name, $size = 16, $class = '' ) {
		echo UNDT_Ui_Icons::svg( $name, $size, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes Markup, Werte in svg() escaped.
	}

	/**
	 * Der Kopf einer Seite: Titel und, wo automatisch gespeichert wird, der
	 * Speicherstatus rechts daneben.
	 *
	 * @param string $title    Seitentitel.
	 * @param bool   $autosave Ob die Seite automatisch speichert.
	 * @param bool   $visible  Ob der Titel sichtbar ist. Die Uebersicht beginnt mit der
	 *                         Statuszeile, ihr Titel steht nur fuer Screenreader da.
	 * @return void
	 */
	public static function page_head( $title, $autosave = false, $visible = true ) {
		echo '<div class="undt-head' . ( $visible ? '' : ' undt-head--hidden' ) . '">';
		echo '<h1 class="undt-head__title' . ( $visible ? '' : ' screen-reader-text' ) . '">' . esc_html( $title ) . '</h1>';

		if ( $autosave ) {
			self::save_status();
		}

		echo '</div>';

		// Hinweise anderer Plugins setzt WordPress hinter diese Linie, nicht in den Kopf.
		echo '<hr class="wp-header-end">';
	}

	/**
	 * Der Speicherstatus.
	 *
	 * Drei Zustaende, umgeschaltet von admin.js ueber data-state: saved,
	 * saving und error. Im Fehlerfall versucht es das Skript nach zehn Sekunden
	 * erneut, der Knopf zieht das vor.
	 *
	 * @return void
	 */
	public static function save_status() {
		echo '<div class="undt-save" data-undt-save data-state="saved" role="status">';

		echo '<span class="undt-save__state undt-save__state--saved">';
		self::icon( 'check', 14 );
		echo esc_html__( 'Alle Änderungen gespeichert', 'unternehmensdaten' ) . '</span>';

		echo '<span class="undt-save__state undt-save__state--saving">' . esc_html__( 'Wird gespeichert …', 'unternehmensdaten' ) . '</span>';

		echo '<span class="undt-save__state undt-save__state--error"><span class="undt-amber"><span class="undt-amber__dot" aria-hidden="true"></span>';
		echo esc_html__( 'Nicht gespeichert. Neuer Versuch in 10 Sekunden.', 'unternehmensdaten' ) . '</span>';
		echo '<button type="button" class="undt-link-button" data-undt-save-retry>' . esc_html__( 'Jetzt versuchen', 'unternehmensdaten' ) . '</button></span>';

		echo '</div>';
	}

	/**
	 * Der Kopf einer Karte.
	 *
	 * @param string $title Titel.
	 * @param string $desc  Beschreibung darunter, leer fuer keine.
	 * @param callable|null $aside Gibt aus, was rechts im Kopf steht, etwa einen Schalter.
	 * @param string        $id    ID der Ueberschrift, damit sich Bedienelemente darauf beziehen koennen.
	 * @return void
	 */
	public static function card_head( $title, $desc = '', $aside = null, $id = '' ) {
		echo '<div class="undt-box__head"><div class="undt-box__heading">';
		printf(
			'<h2 class="undt-box__title"%s>%s</h2>',
			'' !== $id ? ' id="' . esc_attr( $id ) . '"' : '',
			esc_html( $title )
		);

		if ( '' !== $desc ) {
			echo '<p class="undt-box__desc">' . esc_html( $desc ) . '</p>';
		}

		echo '</div>';

		if ( is_callable( $aside ) ) {
			echo '<div class="undt-box__aside">';
			call_user_func( $aside );
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Der Fuss einer Karte: ein Satz in grau, etwa eine Einordnung.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	public static function card_foot( $text ) {
		echo '<div class="undt-box__footer undt-box__footer--note">' . esc_html( $text ) . '</div>';
	}

	/**
	 * Eine Statuszeile: Symbol, fetter Satz, grauer Nachsatz.
	 *
	 * @param string $tone  ok, open (Bernstein) oder missing (grau).
	 * @param string $title Fetter Satz.
	 * @param string $text  Nachsatz, leer fuer keinen.
	 * @param string $icon  Symbol, leer fuer das passende zum Ton.
	 * @param bool   $large Grosse Fassung mit rundem Symbol, fuer den Kopf der Uebersicht.
	 * @return void
	 */
	public static function status( $tone, $title, $text = '', $icon = '', $large = false ) {
		$tone = in_array( $tone, array( 'ok', 'open', 'missing' ), true ) ? $tone : 'missing';

		if ( '' === $icon ) {
			$icon = 'ok' === $tone ? 'check' : 'excl';
		}

		$labels = array(
			'ok'      => __( 'In Ordnung:', 'unternehmensdaten' ),
			'open'    => __( 'Pflicht:', 'unternehmensdaten' ),
			'missing' => __( 'Hinweis:', 'unternehmensdaten' ),
		);

		printf(
			'<div class="undt-status undt-status--%1$s%2$s"><span class="undt-status__icon">',
			esc_attr( $tone ),
			$large ? ' undt-status--large' : ''
		);
		self::icon( $icon, $large ? 24 : 18 );
		echo '</span><div class="undt-status__text">';
		echo '<span class="screen-reader-text">' . esc_html( $labels[ $tone ] ) . ' </span>';

		if ( $large ) {
			echo '<h2 class="undt-status__title">' . esc_html( $title ) . '</h2>';

			if ( '' !== $text ) {
				echo '<p class="undt-status__more">' . esc_html( $text ) . '</p>';
			}
		} else {
			echo '<strong class="undt-status__title">' . esc_html( $title ) . '</strong>';

			if ( '' !== $text ) {
				echo ' <span class="undt-status__more">' . esc_html( $text ) . '</span>';
			}
		}

		echo '</div></div>';
	}

	/**
	 * Eine Info-Card: grauer Kasten mit Symbol, fuer Einordnungen ohne Handlungsbedarf.
	 *
	 * @param string $html    Text, einfaches HTML erlaubt.
	 * @param string $icon    Symbol.
	 * @param bool   $surface Ob die Card direkt auf dem grauen Seitengrund steht.
	 * @return void
	 */
	public static function info( $html, $icon = 'info', $surface = false ) {
		printf( '<div class="undt-info%s">', $surface ? ' undt-info--surface' : '' );
		self::icon( $icon, 16, 'undt-info__icon' );
		echo '<span>' . wp_kses_post( $html ) . '</span></div>';
	}

	/**
	 * Eine Bernstein-Zeile direkt an der Entscheidung, fuer fehlende Pflicht.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	public static function amber( $text ) {
		echo '<p class="undt-amber"><span class="undt-amber__dot" aria-hidden="true"></span>' . esc_html( $text ) . '</p>';
	}

	/**
	 * Der Zaehler auf einer Kachel.
	 *
	 * @param int $count Anzahl offener Punkte.
	 * @return string
	 */
	public static function badge_html( $count ) {
		$count = (int) $count;

		if ( $count < 1 ) {
			return '';
		}

		return sprintf(
			'<span class="undt-badge" data-undt-badge>%1$d<span class="screen-reader-text"> %2$s</span></span>',
			$count,
			esc_html( _n( 'offener Punkt', 'offene Punkte', $count, 'unternehmensdaten' ) )
		);
	}

	/**
	 * Eine Icon-Kachel, optional mit Zaehler.
	 *
	 * @param string $icon   Symbol.
	 * @param int    $count  Zaehler, 0 fuer keinen.
	 * @param string $state  Leer, active (petrol gefuellt) oder brand (petrol Symbol).
	 * @param string $badge  Name fuer data-undt-badge-for, damit admin.js den Zaehler nachfuehren kann.
	 * @return void
	 */
	public static function tile( $icon, $count = 0, $state = '', $badge = '' ) {
		printf(
			'<span class="undt-tile%1$s"%2$s>',
			'' !== $state ? ' undt-tile--' . esc_attr( $state ) : '',
			'' !== $badge ? ' data-undt-badge-for="' . esc_attr( $badge ) . '"' : ''
		);
		self::icon( $icon, 16 );
		echo self::badge_html( $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in badge_html() escaped.
		echo '</span>';
	}

	/**
	 * Eine Link-Card: die ganze Karte fuehrt auf eine andere Seite.
	 *
	 * @param array $args href, icon, title, sub, line, tone (open|missing|''), count, muted.
	 * @return void
	 */
	public static function link_card( array $args ) {
		$args = array_merge(
			array(
				'href'  => '#',
				'icon'  => 'grid',
				'title' => '',
				'sub'   => '',
				'line'  => '',
				'tone'  => '',
				'count' => 0,
				'muted' => false,
			),
			$args
		);

		printf( '<a class="undt-linkcard" href="%s"><span class="undt-linkcard__top">', esc_url( $args['href'] ) );
		// Grau, wenn der Bereich gerade nichts zeigt, etwa ein ausgeblendetes Banner.
		self::tile( $args['icon'], (int) $args['count'], $args['muted'] ? 'muted' : 'brand' );
		echo '<span class="undt-linkcard__title">' . esc_html( $args['title'] ) . '</span>';
		self::icon( 'caret', 14, 'undt-linkcard__caret' );
		echo '</span><span class="undt-linkcard__text">';

		if ( '' !== $args['sub'] ) {
			echo '<span class="undt-linkcard__sub">' . esc_html( $args['sub'] ) . '</span>';
		}

		if ( '' !== $args['line'] ) {
			printf( '<span class="undt-linkcard__line%s">', '' !== $args['tone'] ? ' undt-linkcard__line--' . esc_attr( $args['tone'] ) : '' );

			if ( '' !== $args['tone'] ) {
				echo '<span class="undt-dot-s" aria-hidden="true"></span>';
			}

			echo esc_html( $args['line'] ) . '</span>';
		}

		echo '</span></a>';
	}

	/**
	 * Ein Segment-Schalter: wenige gleichrangige Moeglichkeiten nebeneinander.
	 *
	 * Technisch Radioknoepfe, damit er wie jedes Formularfeld gespeichert wird.
	 *
	 * @param string $name    Formularname.
	 * @param string $id      ID-Praefix.
	 * @param array  $choices Wert => Beschriftung.
	 * @param string $value   Gewaehlter Wert.
	 * @param string $label   Beschriftung der Gruppe fuer Screenreader.
	 * @param array  $marks   Wert => kleiner Zusatz in Petrol, etwa „erkannt“.
	 * @return void
	 */
	public static function segment( $name, $id, array $choices, $value, $label, array $marks = array() ) {
		printf( '<div class="undt-segment" role="radiogroup" aria-label="%s">', esc_attr( $label ) );

		foreach ( $choices as $key => $text ) {
			printf(
				'<label class="undt-segment__option" for="%1$s"><input type="radio" class="undt-segment__input" id="%1$s" name="%2$s" value="%3$s"%4$s /><span class="undt-segment__face">%5$s%6$s</span></label>',
				esc_attr( $id . '-' . $key ),
				esc_attr( $name ),
				esc_attr( (string) $key ),
				checked( (string) $value, (string) $key, false ),
				esc_html( $text ),
				isset( $marks[ $key ] ) ? '<span class="undt-segment__mark">' . esc_html( $marks[ $key ] ) . '</span>' : ''
			);
		}

		echo '</div>';
	}

	/**
	 * Ein Schalter ausserhalb eines Formulars, etwa in den Schnelleinstellungen.
	 *
	 * Im Formular gilt UNDT_Controls::toggle(), das als Checkbox mitgespeichert
	 * wird. Dieser hier ist ein Knopf, den admin.js bedient.
	 *
	 * @param string $id      ID.
	 * @param bool   $on      Zustand.
	 * @param array  $data    data-Attribute ohne Praefix, Name => Wert.
	 * @param string $label   Beschriftung fuer Screenreader, leer wenn ein label-Element darauf zeigt.
	 * @return void
	 */
	public static function switch_button( $id, $on, array $data = array(), $label = '' ) {
		$attrs = '';

		foreach ( $data as $key => $value ) {
			if ( preg_match( '/^[a-z][a-z0-9-]*$/', (string) $key ) ) {
				$attrs .= ' data-' . $key . '="' . esc_attr( (string) $value ) . '"';
			}
		}

		printf(
			'<button type="button" class="undt-switch-button" id="%1$s" role="switch" aria-checked="%2$s"%3$s%4$s><span class="undt-switch-button__knob"></span></button>',
			esc_attr( $id ),
			$on ? 'true' : 'false',
			'' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '',
			$attrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escaped.
		);
	}

	/**
	 * Eine Schrittliste, etwa fuer den Erststart.
	 *
	 * @param array $steps Je Schritt title, desc, state (done|current|open), action (HTML).
	 * @return void
	 */
	public static function steps( array $steps ) {
		echo '<ol class="undt-steps">';

		foreach ( array_values( $steps ) as $index => $step ) {
			$state = isset( $step['state'] ) && in_array( $step['state'], array( 'done', 'current', 'open' ), true ) ? $step['state'] : 'open';

			printf( '<li class="undt-steps__item undt-steps__item--%s"><span class="undt-steps__circle">', esc_attr( $state ) );

			if ( 'done' === $state ) {
				self::icon( 'check', 14 );
				echo '<span class="screen-reader-text">' . esc_html__( 'Erledigt:', 'unternehmensdaten' ) . '</span>';
			} else {
				echo (int) ( $index + 1 );
			}

			echo '</span><div class="undt-steps__body">';
			echo '<div class="undt-steps__title">' . esc_html( $step['title'] ) . '</div>';

			if ( ! empty( $step['desc'] ) ) {
				echo '<div class="undt-steps__desc">' . esc_html( $step['desc'] ) . '</div>';
			}

			if ( 'current' === $state && ! empty( $step['action'] ) ) {
				echo '<div class="undt-steps__action">' . wp_kses_post( $step['action'] ) . '</div>';
			}

			echo '</div></li>';
		}

		echo '</ol>';
	}

	/**
	 * Oeffnet einen aufklappbaren Abschnitt fuer Seltenes.
	 *
	 * @param string $summary  Titel.
	 * @param string $note     Grauer Hinweis rechts, was darin steckt.
	 * @param bool   $optional Ob „· optional“ dahinter steht.
	 * @return void
	 */
	public static function details_open( $summary, $note = '', $optional = true ) {
		echo '<details class="undt-details"><summary class="undt-details__summary">';
		self::icon( 'caret', 12, 'undt-details__chevron' );
		echo '<span class="undt-details__title">' . esc_html( $summary );

		if ( $optional ) {
			echo ' <span class="undt-optional">· ' . esc_html__( 'optional', 'unternehmensdaten' ) . '</span>';
		}

		echo '</span>';

		if ( '' !== $note ) {
			echo '<span class="undt-details__note">' . esc_html( $note ) . '</span>';
		}

		echo '</summary><div class="undt-details__body">';
	}

	/**
	 * Schliesst den aufklappbaren Abschnitt.
	 *
	 * @return void
	 */
	public static function details_close() {
		echo '</div></details>';
	}

	/**
	 * Ein Ersatz-Speicherknopf fuer Browser ohne JavaScript.
	 *
	 * Mit Skript speichert jede Seite von selbst und der Knopf bleibt unsichtbar.
	 *
	 * @return void
	 */
	public static function noscript_submit() {
		echo '<noscript><p class="undt-noscript">';
		submit_button( __( 'Speichern', 'unternehmensdaten' ), 'primary', 'submit', false );
		echo '</p></noscript>';
	}
}
