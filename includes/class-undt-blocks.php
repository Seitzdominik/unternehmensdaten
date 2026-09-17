<?php
/**
 * Ausgabe der Inhaltsmodule.
 *
 * Gleicher Grundsatz wie beim Impressum: semantisches HTML ohne Typografie. Die
 * einzige Ausnahme ist das Infobanner, das ohne eigene Farben seinen Zweck
 * verfehlen wuerde. Dort kommen die Farben aus CSS-Variablen, die sich vom Theme
 * ueberschreiben lassen.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Blocks
 */
final class UNDT_Blocks {

	/**
	 * Trennzeichen zwischen zwei Uhrzeiten.
	 */
	const DASH = ' – ';

	/**
	 * Begrenzt die Ueberschriftenebene.
	 *
	 * @param mixed $level Gewuenschte Ebene.
	 * @return int
	 */
	private static function level( $level ) {
		return min( 6, max( 2, (int) $level ) );
	}

	/**
	 * Eine Ueberschrift.
	 *
	 * @param int    $level Ebene.
	 * @param string $text  Text.
	 * @return string
	 */
	private static function h( $level, $text ) {
		if ( '' === trim( $text ) ) {
			return '';
		}

		return sprintf( '<h%1$d class="undt-block__title">%2$s</h%1$d>', $level, esc_html( $text ) );
	}

	/**
	 * Mehrzeiligen Klartext in Absaetze umsetzen.
	 *
	 * Leerzeilen trennen Absaetze, einfache Umbrueche bleiben Umbrueche.
	 *
	 * @param string $text Rohtext.
	 * @return string
	 */
	private static function paragraphs( $text ) {
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return '';
		}

		$out = '';

		foreach ( preg_split( '/\n\s*\n/', $text ) as $block ) {
			$block = trim( $block );

			if ( '' !== $block ) {
				$out .= '<p>' . nl2br( esc_html( $block ) ) . '</p>';
			}
		}

		return $out;
	}

	/**
	 * Ein Zeitfenster als Text.
	 *
	 * @param array  $slot   Fenster mit from und to.
	 * @param string $suffix Zusatz hinter der Uhrzeit.
	 * @return string
	 */
	private static function slot_text( array $slot, $suffix = '' ) {
		$text = $slot['from'] . self::DASH . $slot['to'];

		return '' === $suffix ? $text : $text . ' ' . $suffix;
	}

	/**
	 * Die Beschriftung einer Tagesgruppe.
	 *
	 * @param array $days  Tagesschluessel.
	 * @param bool  $short Abkuerzungen verwenden.
	 * @return string
	 */
	private static function day_range( array $days, $short = true ) {
		$count = count( $days );

		if ( 0 === $count ) {
			return '';
		}

		if ( 1 === $count ) {
			return UNDT_Hours::day_label( $days[0], $short );
		}

		if ( 2 === $count ) {
			return UNDT_Hours::day_label( $days[0], $short ) . ', ' . UNDT_Hours::day_label( $days[1], $short );
		}

		return UNDT_Hours::day_label( $days[0], $short ) . self::DASH . UNDT_Hours::day_label( $days[ $count - 1 ], $short );
	}

	/* ------------------------------------------------------ Öffnungszeiten */

	/**
	 * Die Tabelle der Oeffnungszeiten.
	 *
	 * @param array $atts Attribute.
	 * @return string
	 */
	public static function hours( array $atts ) {
		if ( ! UNDT_Hours::has_data() ) {
			return '';
		}

		$level   = self::level( isset( $atts['heading_level'] ) ? $atts['heading_level'] : 3 );
		$short   = '0' !== (string) $atts['short'];
		$group   = '0' !== (string) $atts['group'];
		$suffix  = trim( (string) UNDT_Content::value( 'hours', 'suffix' ) );
		$closed  = trim( (string) UNDT_Content::value( 'hours', 'closed_label' ) );
		$closed  = '' === $closed ? __( 'geschlossen', 'unternehmensdaten' ) : $closed;

		$rows = $group
			? UNDT_Hours::grouped()
			: array_map(
				static function ( $day ) {
					$slots = UNDT_Hours::slots( $day );

					return array(
						'days'   => array( $day ),
						'closed' => empty( $slots ),
						'slots'  => $slots,
					);
				},
				UNDT_Hours::display_order()
			);

		$out = '<div class="undt-block undt-hours">';
		$out .= '<table class="undt-hours__table"><tbody>';

		foreach ( $rows as $row ) {
			// Die Beschriftung traegt die Punktlinie zur Uhrzeit, siehe css().
			$out .= '<tr class="undt-hours__row' . ( $row['closed'] ? ' undt-hours__row--closed' : '' ) . '">';
			$out .= '<th scope="row"><span class="undt-hours__label">' . esc_html( self::day_range( $row['days'], $short ) ) . '</span></th>';
			$out .= '<td>';

			if ( $row['closed'] ) {
				$out .= esc_html( $closed );
			} else {
				$parts = array();

				foreach ( $row['slots'] as $slot ) {
					$parts[] = esc_html( self::slot_text( $slot, $suffix ) );
				}

				$out .= implode( '<br />', $parts );
			}

			$out .= '</td></tr>';
		}

		$out .= '</tbody></table>';

		if ( '0' !== (string) $atts['note'] && UNDT_Content::has( 'hours', 'note' ) ) {
			$out .= '<div class="undt-hours__note">' . self::paragraphs( UNDT_Content::value( 'hours', 'note' ) ) . '</div>';
		}

		if ( '0' !== (string) $atts['special'] ) {
			$out .= self::special_hours( $level, $suffix, $closed );
		}

		return $out . '</div>';
	}

	/**
	 * Kuenftige Sonderoeffnungszeiten.
	 *
	 * @param int    $level  Ueberschriftenebene.
	 * @param string $suffix Zusatz hinter der Uhrzeit.
	 * @param string $closed Bezeichnung fuer geschlossen.
	 * @return string
	 */
	private static function special_hours( $level, $suffix, $closed ) {
		$rows = UNDT_Hours::upcoming_special();

		if ( empty( $rows ) ) {
			return '';
		}

		$out = self::h( $level, __( 'Abweichende Öffnungszeiten', 'unternehmensdaten' ) );
		$out .= '<table class="undt-hours__table undt-hours__table--special"><tbody>';

		foreach ( $rows as $row ) {
			$date = UNDT_Hours::date( isset( $row['date'] ) ? $row['date'] : '' );

			if ( '' === $date ) {
				continue;
			}

			$window = UNDT_Hours::window(
				isset( $row['from'] ) ? $row['from'] : '',
				isset( $row['to'] ) ? $row['to'] : ''
			);
			$shut   = ! empty( $row['closed'] ) || null === $window;

			$out .= '<tr class="undt-hours__row' . ( $shut ? ' undt-hours__row--closed' : '' ) . '">';
			$out .= '<th scope="row">';
			$out .= '<span class="undt-hours__label"><time datetime="' . esc_attr( $date ) . '">' . esc_html( UNDT_Hours::date_label( $date ) ) . '</time></span>';

			if ( ! empty( $row['note'] ) ) {
				$out .= '<span class="undt-hours__occasion">' . esc_html( $row['note'] ) . '</span>';
			}

			$out .= '</th><td>';
			$out .= $shut ? esc_html( $closed ) : esc_html( self::slot_text( $window, $suffix ) );
			$out .= '</td></tr>';
		}

		return $out . '</tbody></table>';
	}

	/**
	 * Die heutigen Zeiten als Satz.
	 *
	 * @param array $atts Attribute.
	 * @return string
	 */
	public static function hours_today( array $atts ) {
		if ( ! UNDT_Hours::has_data() ) {
			return '';
		}

		$today  = UNDT_Hours::today();
		$suffix = trim( (string) UNDT_Content::value( 'hours', 'suffix' ) );
		$prefix = trim( (string) $atts['prefix'] );

		if ( $today['closed'] ) {
			$text = trim( (string) $atts['closed_text'] );

			if ( '' === $text ) {
				$closed = trim( (string) UNDT_Content::value( 'hours', 'closed_label' ) );
				$text   = '' === $closed ? __( 'geschlossen', 'unternehmensdaten' ) : $closed;
			}
		} else {
			$parts = array();

			foreach ( $today['slots'] as $slot ) {
				$parts[] = self::slot_text( $slot, $suffix );
			}

			$text = implode( ', ', $parts );
		}

		$out = '<span class="undt-hours-today' . ( $today['closed'] ? ' undt-hours-today--closed' : '' ) . '">';

		if ( '' !== $prefix ) {
			$out .= esc_html( $prefix ) . ' ';
		}

		$out .= esc_html( $text );

		if ( $today['is_special'] && '' !== trim( (string) $today['note'] ) ) {
			$out .= ' <span class="undt-hours-today__note">(' . esc_html( $today['note'] ) . ')</span>';
		}

		return $out . '</span>';
	}

	/**
	 * Ob gerade geoeffnet ist.
	 *
	 * Der Wert wird serverseitig ermittelt. Auf Seiten, die von einem Cache
	 * ausgeliefert werden, kann er deshalb veralten. Die Ausgabe traegt dafuer
	 * ein data-Attribut mit dem Zeitpunkt der Berechnung.
	 *
	 * @param array $atts Attribute.
	 * @return string
	 */
	public static function open_now( array $atts ) {
		if ( ! UNDT_Hours::has_data() ) {
			return '';
		}

		$open = UNDT_Hours::is_open_now();

		$text = $open ? trim( (string) $atts['open_text'] ) : trim( (string) $atts['closed_text'] );

		if ( '' === $text ) {
			$text = $open
				? __( 'Jetzt geöffnet', 'unternehmensdaten' )
				: __( 'Zurzeit geschlossen', 'unternehmensdaten' );
		}

		return sprintf(
			'<span class="undt-open undt-open--%1$s" data-undt-checked="%2$s">%3$s</span>',
			$open ? 'yes' : 'no',
			esc_attr( current_datetime()->format( 'c' ) ),
			esc_html( $text )
		);
	}

	/* --------------------------------------------------------------- Preise */

	/**
	 * Die Preisliste.
	 *
	 * @param array $atts Attribute.
	 * @return string
	 */
	public static function prices( array $atts ) {
		$rows = UNDT_Content::rows( 'prices', 'items' );

		if ( empty( $rows ) ) {
			return '';
		}

		$level  = self::level( isset( $atts['heading_level'] ) ? $atts['heading_level'] : 3 );
		$groups = UNDT_Content::group_rows( $rows, (string) $atts['group'] );

		if ( empty( $groups ) ) {
			return '';
		}

		$out = '<div class="undt-block undt-prices">';

		if ( '0' !== (string) $atts['intro'] && UNDT_Content::has( 'prices', 'intro' ) ) {
			$out .= '<div class="undt-prices__intro">' . self::paragraphs( UNDT_Content::value( 'prices', 'intro' ) ) . '</div>';
		}

		foreach ( $groups as $name => $items ) {
			$out .= self::h( $level, (string) $name );
			$out .= '<table class="undt-prices__table"><tbody>';

			foreach ( $items as $item ) {
				$label = isset( $item['label'] ) ? (string) $item['label'] : '';
				$price = isset( $item['price'] ) ? (string) $item['price'] : '';
				$note  = isset( $item['note'] ) ? (string) $item['note'] : '';

				if ( '' === trim( $label ) && '' === trim( $price ) ) {
					continue;
				}

				$out .= '<tr class="undt-prices__row">';
				$out .= '<th scope="row">' . esc_html( $label );

				if ( '' !== trim( $note ) ) {
					$out .= '<span class="undt-prices__note">' . esc_html( $note ) . '</span>';
				}

				$out .= '</th>';
				$out .= '<td class="undt-prices__price">' . esc_html( $price ) . '</td>';
				$out .= '</tr>';
			}

			$out .= '</tbody></table>';
		}

		if ( '0' !== (string) $atts['footnote'] && UNDT_Content::has( 'prices', 'footnote' ) ) {
			$out .= '<div class="undt-prices__footnote">' . self::paragraphs( UNDT_Content::value( 'prices', 'footnote' ) ) . '</div>';
		}

		return $out . '</div>';
	}

	/* --------------------------------------------------------------- Social */

	/**
	 * Die Social-Profile.
	 *
	 * Die Symbole kommen aus dem Social-Icons-Block von WordPress und lassen
	 * sich abschalten. Jeder Link traegt zusaetzlich eine eigene Klasse und ein
	 * data-platform-Attribut fuer ein Icon-Set des Themes.
	 *
	 * Dass sich ein neuer Tab oeffnet, sagt der Name des Links, den Screenreader
	 * vorlesen. Sichtbar ist dafuer hoechstens ein kleiner Pfeil. Ein versteckter
	 * Zusatztext stand frueher im Link und wurde sichtbar, wo das CSS fehlte.
	 *
	 * @param array $atts Attribute.
	 * @return string
	 */
	public static function social( array $atts ) {
		$rows = UNDT_Content::rows( 'social', 'items' );

		if ( empty( $rows ) ) {
			return '';
		}

		$platforms = UNDT_Modules::platforms();
		$rel       = array();

		if ( UNDT_Content::value( 'social', 'rel_me' ) ) {
			$rel[] = 'me';
		}

		$new_tab = (bool) UNDT_Content::value( 'social', 'new_tab' );

		if ( $new_tab ) {
			$rel[] = 'noopener';
		}

		$show_icons = (bool) UNDT_Content::value( 'social', 'show_icons' );

		// Ohne Symbole bleiben die Namen immer sichtbar, sonst stuende nichts da.
		$show_labels = ! $show_icons || (bool) UNDT_Content::value( 'social', 'show_labels' );
		$arrow       = $new_tab && $show_labels && (bool) UNDT_Content::value( 'social', 'new_tab_icon' );

		$items = array();

		foreach ( $rows as $row ) {
			$url = isset( $row['url'] ) ? (string) $row['url'] : '';

			if ( '' === trim( $url ) ) {
				continue;
			}

			$platform = isset( $row['platform'] ) ? (string) $row['platform'] : 'other';
			$label    = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';

			if ( '' === $label ) {
				$label = isset( $platforms[ $platform ] ) ? $platforms[ $platform ] : $platform;
			}

			$content = '';

			if ( $show_icons ) {
				$content .= '<span class="undt-social__icon" aria-hidden="true">' . UNDT_Icons::platform( $platform ) . '</span>';
			}

			if ( $show_labels ) {
				$content .= '<span class="undt-social__label">' . esc_html( $label ) . '</span>';
			}

			if ( $arrow ) {
				$content .= UNDT_Icons::svg( 'external', 'undt-social__external' );
			}

			$name = '';

			if ( $new_tab ) {
				/* translators: %s: Name des Profils, etwa Instagram. */
				$name = sprintf( __( '%s (öffnet in neuem Tab)', 'unternehmensdaten' ), $label );
			} elseif ( ! $show_labels ) {
				$name = $label;
			}

			$items[] = sprintf(
				'<li class="undt-social__item"><a class="undt-social__link undt-social__link--%1$s" data-platform="%1$s" href="%2$s"%3$s%4$s%5$s>%6$s</a></li>',
				esc_attr( $platform ),
				esc_url( $url ),
				empty( $rel ) ? '' : ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"',
				$new_tab ? ' target="_blank"' : '',
				'' === $name ? '' : ' aria-label="' . esc_attr( $name ) . '"',
				$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escaped, Symbole ueber wp_kses.
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		$label = trim( (string) $atts['label'] );

		if ( '' === $label ) {
			$label = __( 'Social Media', 'unternehmensdaten' );
		}

		$classes = 'undt-block undt-social';

		if ( $show_icons ) {
			$classes .= $show_labels ? ' undt-social--icons' : ' undt-social--icons-only';
		}

		return sprintf(
			'<nav class="%1$s" aria-label="%2$s"><ul class="undt-inline-list undt-social__list">%3$s</ul></nav>',
			esc_attr( $classes ),
			esc_attr( $label ),
			implode( '', $items )
		);
	}

	/* ------------------------------------------------------------------ FAQ */

	/**
	 * Die Fragen und Antworten.
	 *
	 * @param array $atts Attribute.
	 * @return string
	 */
	public static function faq( array $atts ) {
		$rows = UNDT_Content::rows( 'faq', 'items' );

		if ( empty( $rows ) ) {
			return '';
		}

		$level  = self::level( isset( $atts['heading_level'] ) ? $atts['heading_level'] : 2 );
		$groups = UNDT_Content::group_rows( $rows, (string) $atts['group'] );

		if ( empty( $groups ) ) {
			return '';
		}

		$style = (string) $atts['style'];

		if ( ! in_array( $style, array( 'details', 'dl' ), true ) ) {
			$style = (string) UNDT_Content::value( 'faq', 'style' );
			$style = in_array( $style, array( 'details', 'dl' ), true ) ? $style : 'details';
		}

		$open_first = (bool) UNDT_Content::value( 'faq', 'open_first' );
		$first      = true;

		$out = '<div class="undt-block undt-faq">';

		foreach ( $groups as $name => $items ) {
			$out .= self::h( $level, (string) $name );

			if ( 'dl' === $style ) {
				$out .= '<dl class="undt-faq__list">';
			}

			foreach ( $items as $item ) {
				$question = isset( $item['question'] ) ? trim( (string) $item['question'] ) : '';
				$answer   = isset( $item['answer'] ) ? (string) $item['answer'] : '';

				if ( '' === $question ) {
					continue;
				}

				if ( 'dl' === $style ) {
					$out .= '<div class="undt-faq__row">';
					$out .= '<dt>' . esc_html( $question ) . '</dt>';
					$out .= '<dd>' . self::paragraphs( $answer ) . '</dd>';
					$out .= '</div>';
				} else {
					$out .= '<details class="undt-faq__item"' . ( $open_first && $first ? ' open' : '' ) . '>';
					$out .= '<summary class="undt-faq__question">' . esc_html( $question ) . '</summary>';
					$out .= '<div class="undt-faq__answer">' . self::paragraphs( $answer ) . '</div>';
					$out .= '</details>';
				}

				$first = false;
			}

			if ( 'dl' === $style ) {
				$out .= '</dl>';
			}
		}

		return $out . '</div>';
	}

	/* ----------------------------------------------------------- Infobanner */

	/**
	 * Ob das Banner ausgegeben werden soll.
	 *
	 * @return bool
	 */
	public static function banner_active() {
		return UNDT_Modules::is_active( 'banner' )
			&& UNDT_Content::value( 'banner', 'enabled' )
			&& UNDT_Content::has( 'banner', 'text' );
	}

	/**
	 * Kennung des aktuellen Bannerinhalts.
	 *
	 * Aendert sich der Text, aendert sich die Kennung. Ein zuvor geschlossenes
	 * Banner erscheint dadurch erneut, statt stillschweigend unterdrueckt zu
	 * bleiben.
	 *
	 * @return string
	 */
	private static function banner_key() {
		$parts = array(
			(string) UNDT_Content::value( 'banner', 'text' ),
			(string) UNDT_Content::value( 'banner', 'type' ),
			(string) UNDT_Content::value( 'banner', 'link_url' ),
			(string) UNDT_Content::value( 'banner', 'link_text' ),
		);

		return substr( md5( implode( '|', $parts ) ), 0, 12 );
	}

	/**
	 * Ob das Banner in diesem Request bereits ausgegeben wurde.
	 *
	 * @var bool
	 */
	private static $banner_done = false;

	/**
	 * Setzt den Ausgabezustand zurueck.
	 *
	 * Wird aufgerufen, wenn sich die zugrunde liegenden Daten aendern.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$banner_done = false;
	}

	/**
	 * Das Infobanner.
	 *
	 * Gibt sich hoechstens einmal je Seitenaufruf aus. Sonst erschiene es
	 * doppelt, wenn die automatische Ausgabe aktiv ist und zusaetzlich der
	 * Shortcode im Inhalt steht.
	 *
	 * @return string
	 */
	public static function banner() {
		if ( ! self::banner_active() || self::$banner_done ) {
			return '';
		}

		self::$banner_done = true;

		$type = (string) UNDT_Content::value( 'banner', 'type' );

		if ( ! in_array( $type, array( 'info', 'success', 'warning', 'urgent' ), true ) ) {
			$type = 'info';
		}

		$dismissible = (bool) UNDT_Content::value( 'banner', 'dismissible' );
		$key         = self::banner_key();

		$out = sprintf(
			'<div class="undt-banner undt-banner--%1$s" role="region" aria-label="%2$s" data-undt-banner="%3$s">',
			esc_attr( $type ),
			esc_attr__( 'Hinweis', 'unternehmensdaten' ),
			esc_attr( $key )
		);

		$out .= '<div class="undt-banner__inner">';
		$out .= '<div class="undt-banner__text">' . self::paragraphs( UNDT_Content::value( 'banner', 'text' ) ) . '</div>';

		if ( UNDT_Content::has( 'banner', 'link_url' ) ) {
			$label = trim( (string) UNDT_Content::value( 'banner', 'link_text' ) );

			if ( '' === $label ) {
				$label = __( 'Mehr erfahren', 'unternehmensdaten' );
			}

			$out .= sprintf(
				'<a class="undt-banner__link" href="%s">%s</a>',
				esc_url( UNDT_Content::value( 'banner', 'link_url' ) ),
				esc_html( $label )
			);
		}

		if ( $dismissible ) {
			$out .= sprintf(
				'<button type="button" class="undt-banner__close" aria-label="%s">&times;</button>',
				esc_attr__( 'Hinweis schließen', 'unternehmensdaten' )
			);
		}

		$out .= '</div></div>';

		if ( $dismissible ) {
			$out .= self::banner_script( $key );
		}

		return $out;
	}

	/**
	 * Das Skript zum Schliessen des Banners.
	 *
	 * Wird unmittelbar hinter dem Banner ausgegeben und nicht im Footer: nur so
	 * verschwindet ein bereits geschlossenes Banner, bevor es kurz aufblitzt.
	 *
	 * Das Banner wird ueber seine Kennung gesucht, nicht ueber die Nachbarschaft
	 * im DOM: steht der Shortcode im Beitragsinhalt, kann wpautop Absaetze
	 * einziehen und die Geschwisterbeziehung zerreissen.
	 *
	 * @param string $key Kennung des Bannerinhalts.
	 * @return string
	 */
	private static function banner_script( $key ) {
		// Die Kennung stammt aus md5 und ist damit hexadezimal. Der Filter kostet
		// nichts und stellt sicher, dass nichts anderes in das Skript geraet.
		$key = preg_replace( '/[^a-f0-9]/', '', (string) $key );

		if ( '' === $key ) {
			return '';
		}

		/*
		 * Beim Schliessen wandert der Fokus auf das naechste bedienbare Element
		 * hinter dem Banner. Sonst fiele er an den Anfang der Seite zurueck, und
		 * wer mit Tastatur oder Screenreader unterwegs ist, muesste von vorn
		 * beginnen.
		 */
		$js = "(function(){var k='%s',e=document.querySelector('[data-undt-banner=\"'+k+'\"]');"
			. "if(!e)return;var s='undt-banner-'+k;"
			. "try{if(window.localStorage&&localStorage.getItem(s)){e.hidden=true;return;}}catch(x){}"
			. "var b=e.querySelector('.undt-banner__close');if(!b)return;"
			. "b.addEventListener('click',function(){"
			. "var l=document.querySelectorAll('a[href],button,input,select,textarea,summary,[tabindex]'),i,t;"
			. "e.hidden=true;try{localStorage.setItem(s,'1');}catch(x){}"
			. "for(i=0;i<l.length;i++){t=l[i];"
			. "if(e.contains(t)||!(e.compareDocumentPosition(t)&4)||t.disabled||t.getAttribute('tabindex')==='-1'||!t.getClientRects().length)continue;"
			. "t.focus();if(document.activeElement===t)return;}"
			. "});})();";

		return '<script>' . sprintf( $js, $key ) . '</script>';
	}

	/**
	 * Das CSS der aktiven Module.
	 *
	 * Nur was gebraucht wird: ist ein Modul abgeschaltet, entfaellt sein CSS.
	 *
	 * Nebensaechliche Angaben werden kleiner gesetzt, nicht blasser. opacity
	 * mischt die Textfarbe des Themes mit dem Hintergrund und drueckt den
	 * Kontrast bei verbreiteten Grautoenen unter die Grenze der WCAG.
	 *
	 * @return string
	 */
	public static function css() {
		$css = '';

		if ( UNDT_Modules::is_active( 'hours' ) ) {
			/*
			 * Tag links, Uhrzeit rechts, dazwischen eine Punktlinie, an der das
			 * Auge entlanglaeuft. Die Breite ist begrenzt, damit Tag und Zeit
			 * auch in breiten Bereichen beieinander bleiben. Beides laesst sich
			 * ueber --undt-hours-width und --undt-hours-leader anpassen.
			 */
			$css .= '.undt-hours__table{width:100%;max-width:var(--undt-hours-width,25em);border-collapse:collapse;margin:0}'
				. '.undt-hours__table th,.undt-hours__table td{padding:.2em 0;text-align:left;vertical-align:top;font-weight:inherit}'
				. '.undt-hours__table th{width:100%}'
				. '.undt-hours__table td{padding-left:.6em;text-align:right;white-space:nowrap}'
				. '.undt-hours__label{display:flex;align-items:baseline;gap:.6em;white-space:nowrap}'
				. '.undt-hours__label::after{content:"";flex:1 1 auto;min-width:1em;border-bottom:1px var(--undt-hours-leader,dotted) currentColor}'
				. '.undt-hours__occasion{display:block;font-size:.9em}'
				. '.undt-hours__note{margin:.75em 0 0}'
				. '.undt-hours__note>p{margin:0}'
				. '.undt-hours__note>p+p{margin-top:.5em}';
		}

		if ( UNDT_Modules::is_active( 'prices' ) ) {
			$css .= '.undt-prices__table{width:100%;border-collapse:collapse;margin:0 0 1em}'
				. '.undt-prices__table th,.undt-prices__table td{padding:.35em 0;text-align:left;vertical-align:top;font-weight:inherit;border-bottom:1px solid currentColor}'
				. '.undt-prices__table tr:last-child th,.undt-prices__table tr:last-child td{border-bottom:0}'
				. '.undt-prices__price{text-align:right;white-space:nowrap;padding-left:1.5em}'
				. '.undt-prices__note{display:block;font-size:.9em}';
		}

		if ( UNDT_Modules::is_active( 'faq' ) ) {
			$css .= '.undt-faq__item{margin:0 0 .5em;padding:0 0 .5em;border-bottom:1px solid currentColor}'
				. '.undt-faq__question{cursor:pointer;font-weight:bolder}'
				. '.undt-faq__answer{padding-top:.5em}'
				. '.undt-faq__answer>p:last-child{margin-bottom:0}'
				. '.undt-faq__row{margin:0 0 1em}'
				. '.undt-faq__list dt{font-weight:bolder;margin:0 0 .25em}'
				. '.undt-faq__list dd{margin:0}';
		}

		if ( UNDT_Modules::is_active( 'banner' ) ) {
			/*
			 * Einzige Stelle mit eigenen Farben: ein Warnhinweis ohne visuelle
			 * Abgrenzung erfuellt seinen Zweck nicht. Alle Werte haengen an
			 * CSS-Variablen und lassen sich vom Theme ueberschreiben.
			 *
			 * Die Schrift ist etwas kleiner als der Fliesstext, damit das Banner
			 * nicht mit der Seite selbst konkurriert. em statt rem, weil viele
			 * Builder-Setups die Wurzelgroesse auf 62,5 % setzen.
			 */
			$css .= '.undt-banner{--undt-bg:#eef2f7;--undt-fg:#1d2939;--undt-accent:#2563eb;'
				. 'background:var(--undt-bg);color:var(--undt-fg);border-left:4px solid var(--undt-accent);'
				. 'font-size:var(--undt-banner-font-size,.875em);line-height:1.5}'
				. '.undt-banner--success{--undt-bg:#e7f6ec;--undt-fg:#14532d;--undt-accent:#15803d}'
				. '.undt-banner--warning{--undt-bg:#fdf4e3;--undt-fg:#713f12;--undt-accent:#c2820e}'
				. '.undt-banner--urgent{--undt-bg:#fdeaea;--undt-fg:#7f1d1d;--undt-accent:#c81e1e}'
				. '.undt-banner__inner{display:flex;flex-wrap:wrap;align-items:center;gap:.5em 1em;'
				. 'max-width:72em;margin:0 auto;padding:.75em 1em}'
				. '.undt-banner__text{flex:1 1 16em}'
				. '.undt-banner__text>p{margin:0}'
				. '.undt-banner__text>p+p{margin-top:.5em}'
				. '.undt-banner__link{color:inherit;font-weight:bolder}'
				. '.undt-banner__close{flex:0 0 auto;padding:0 .4em;border:0;border-radius:.15em;background:none;color:inherit;'
				. 'font:inherit;font-size:1.4em;line-height:1;cursor:pointer}'
				. '.undt-banner__close:hover{box-shadow:inset 0 0 0 1px currentColor}';
		}

		if ( UNDT_Modules::is_active( 'social' ) ) {
			// Symbolgroesse ueber --undt-social-icon-size, Standard etwas groesser als der Text.
			$css .= '.undt-social__item{display:inline-flex}'
				. '.undt-social__link{display:inline-flex;align-items:center;gap:.4em}'
				. '.undt-social__icon{display:inline-flex;flex:0 0 auto}'
				. '.undt-social__icon svg{display:block;width:var(--undt-social-icon-size,1.25em);height:var(--undt-social-icon-size,1.25em)}'
				. '.undt-social__external{flex:0 0 auto;width:.7em;height:.7em}';
		}

		return $css;
	}
}
