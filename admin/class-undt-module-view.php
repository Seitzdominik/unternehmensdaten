<?php
/**
 * Die Seiten der Inhaltsbereiche.
 *
 * Seit 0.6.0 hat jeder Bereich seine eigene Seite nach dem Entwurf:
 * Oeffnungszeiten, Social Media, Infobanner, Preise, FAQ und Suchmaschinen.
 * Die Formularnamen bleiben die des Modul-Registers (undt_hours[days][mon]…),
 * gespeichert wird wie ueberall ueber UNDT_Autosave und den Sanitizer des
 * Bereichs. Was ein Filter als eigenes Modul ergaenzt, bekommt weiter das
 * allgemeine Formular aus UNDT_Fields.
 *
 * Teile, die vom gespeicherten Stand abhaengen (Vorschau, Statuszeile,
 * JSON-LD), stehen in Elementen mit data-undt-part. Die Antwort auf das
 * Speichern bringt sie neu mit, siehe parts().
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Module_View
 */
final class UNDT_Module_View {

	/**
	 * Gibt die Seite eines Bereichs aus.
	 *
	 * @param string $slug Bereich.
	 * @return void
	 */
	public static function render( $slug ) {
		switch ( $slug ) {
			case 'hours':
				self::hours();
				break;

			case 'social':
				self::social();
				break;

			case 'banner':
				self::banner();
				break;

			case 'prices':
				self::prices();
				break;

			case 'faq':
				self::faq();
				break;

			case 'seo':
				self::seo();
				break;

			default:
				self::generic( $slug );
		}
	}

	/**
	 * Die neu berechneten Teile einer Seite nach dem Speichern.
	 *
	 * @param string $slug Bereich.
	 * @return array Name => HTML.
	 */
	public static function parts( $slug ) {
		switch ( $slug ) {
			case 'hours':
				return array(
					'today'   => self::capture( array( __CLASS__, 'today_status' ) ),
					'preview' => self::preview( '[undt_hours]' ),
				);

			case 'social':
				return array( 'preview' => self::preview( '[undt_social]', __( 'Erscheint, sobald du ein Profil hinzufügst.', 'unternehmensdaten' ) ) );

			case 'prices':
				return array( 'preview' => self::preview( '[undt_prices]', __( 'Erscheint, sobald eine Leistung einen Namen hat.', 'unternehmensdaten' ) ) );

			case 'banner':
				return array(
					'preview'    => wp_kses_post( UNDT_Blocks::banner_markup( true ) ),
					'bannerhead' => self::capture( array( __CLASS__, 'banner_head' ) ),
				);

			case 'seo':
				return array(
					'seostatus' => self::capture( array( __CLASS__, 'seo_status' ) ),
					'jsonld'    => esc_html( self::jsonld() ),
				);
		}

		return array();
	}

	/* ------------------------------------------------------------ Helfer --- */

	/**
	 * Faengt die Ausgabe einer Funktion auf.
	 *
	 * @param callable $callback Funktion.
	 * @return string
	 */
	private static function capture( $callback ) {
		ob_start();
		call_user_func( $callback );

		return (string) ob_get_clean();
	}

	/**
	 * Ein Formularname im Modul, etwa undt_hours[days][mon][open].
	 *
	 * @param string $slug Bereich.
	 * @param string ...$keys Schluessel.
	 * @return string
	 */
	private static function name( $slug, ...$keys ) {
		$module = UNDT_Modules::get( $slug );
		$name   = null === $module ? 'undt_' . $slug : $module['option'];

		foreach ( $keys as $key ) {
			$name .= '[' . $key . ']';
		}

		return $name;
	}

	/**
	 * Ein gespeicherter Wert des Bereichs.
	 *
	 * @param string $slug Bereich.
	 * @param string $key  Feld.
	 * @return mixed
	 */
	private static function value( $slug, $key ) {
		return UNDT_Content::value( $slug, $key );
	}

	/**
	 * Ein Shortcode, den man kopieren kann. Nur fuer Admins.
	 *
	 * @param string $code  Shortcode.
	 * @param string $title Was er ausgibt.
	 * @return void
	 */
	public static function chip( $code, $title ) {
		if ( ! UNDT_Access::is_admin() ) {
			return;
		}

		printf(
			'<button type="button" class="undt-copy undt-chip" data-undt-copy="%1$s" aria-label="%2$s"><code class="undt-chip__code">%3$s</code><span class="undt-chip__label undt-chip__label--idle">%4$s%5$s</span><span class="undt-chip__label undt-chip__label--done">%6$s%7$s</span></button>',
			esc_attr( $code ),
			esc_attr( sprintf( /* translators: 1: Baustein, 2: Shortcode. */ __( '%1$s kopieren: %2$s', 'unternehmensdaten' ), $title, $code ) ),
			esc_html( $code ),
			UNDT_Ui_Icons::svg( 'copy', 14 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
			esc_html__( 'Kopieren', 'unternehmensdaten' ),
			UNDT_Ui_Icons::svg( 'check', 14 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
			esc_html__( 'Kopiert', 'unternehmensdaten' )
		);
	}

	/**
	 * Gibt einen Baustein fuer die Vorschau aus.
	 *
	 * @param string $code  Shortcode.
	 * @param string $empty Text, solange nichts auszugeben ist.
	 * @return string
	 */
	private static function preview( $code, $empty = '' ) {
		$html = trim( (string) do_shortcode( $code ) );

		if ( '' === wp_strip_all_tags( $html ) ) {
			return '<p class="undt-preview__empty">' . esc_html( '' !== $empty ? $empty : __( 'Noch nichts auszugeben.', 'unternehmensdaten' ) ) . '</p>';
		}

		return UNDT_Icons::kses( $html );
	}

	/**
	 * Eine Zeile: Beschriftung links, Feld rechts.
	 *
	 * @param string   $title    Beschriftung.
	 * @param string   $for      ID des Feldes, leer fuer eine Gruppe ohne label.
	 * @param callable $control  Gibt das Feld aus.
	 * @param array    $args     optional (bool), help (Text), class, id.
	 * @return void
	 */
	private static function row( $title, $for, $control, array $args = array() ) {
		$args = array_merge(
			array(
				'optional' => false,
				'help'     => '',
				'class'    => '',
				'id'       => '',
				'title_id' => '',
			),
			$args
		);

		printf(
			'<div class="undt-frow%1$s"%2$s><div class="undt-frow__label">',
			'' !== $args['class'] ? ' ' . esc_attr( $args['class'] ) : '',
			'' !== $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : ''
		);

		if ( '' !== $for ) {
			echo '<label class="undt-frow__title" for="' . esc_attr( $for ) . '">' . esc_html( $title ) . '</label>';
		} else {
			printf(
				'<span class="undt-frow__title"%1$s>%2$s</span>',
				'' !== $args['title_id'] ? ' id="' . esc_attr( $args['title_id'] ) . '"' : '',
				esc_html( $title )
			);
		}

		if ( $args['optional'] ) {
			echo '<div class="undt-frow__optional">' . esc_html__( 'optional', 'unternehmensdaten' ) . '</div>';
		}

		echo '</div><div class="undt-frow__main"><div class="undt-frow__control">';
		call_user_func( $control );
		echo '</div>';

		if ( '' !== $args['help'] ) {
			echo '<p class="undt-frow__help">' . esc_html( $args['help'] ) . '</p>';
		}

		echo '</div></div>';
	}

	/**
	 * Ein Textfeld.
	 *
	 * @param string $name        Formularname.
	 * @param string $id          ID.
	 * @param string $value       Wert.
	 * @param string $placeholder Platzhalter.
	 * @param string $type        text, url oder date.
	 * @param array  $attrs       Weitere Attribute.
	 * @return void
	 */
	private static function input( $name, $id, $value, $placeholder = '', $type = 'text', array $attrs = array() ) {
		$extra = '';

		foreach ( $attrs as $attr => $attr_value ) {
			if ( preg_match( '/^[a-z][a-z0-9-]*$/', (string) $attr ) ) {
				$extra .= ' ' . $attr . '="' . esc_attr( (string) $attr_value ) . '"';
			}
		}

		printf(
			'<input type="%1$s" id="%2$s"%3$s value="%4$s"%5$s%6$s />',
			esc_attr( $type ),
			esc_attr( $id ),
			'' !== $name ? ' name="' . esc_attr( $name ) . '"' : '',
			esc_attr( (string) $value ),
			'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '',
			$extra // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escaped.
		);
	}

	/**
	 * Ein Symbolknopf: hoch, runter, entfernen.
	 *
	 * @param string $icon  up, down, trash oder x.
	 * @param string $label Beschriftung.
	 * @param string $class Klassen fuer das Skript.
	 * @param string $dir   Richtung, leer ohne.
	 * @return void
	 */
	private static function icon_button( $icon, $label, $class, $dir = '' ) {
		printf(
			'<button type="button" class="undt-ibtn %1$s"%2$s aria-label="%3$s" title="%3$s">%4$s</button>',
			esc_attr( $class ),
			'' !== $dir ? ' data-dir="' . esc_attr( $dir ) . '"' : '',
			esc_attr( $label ),
			UNDT_Ui_Icons::svg( $icon, 'trash' === $icon ? 15 : 14 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
		);
	}

	/**
	 * Oeffnet ein Formular des Bereichs.
	 *
	 * @param string $slug  Bereich.
	 * @param string $class Zusaetzliche Klasse.
	 * @param array  $data  data-Attribute ohne Praefix.
	 * @return void
	 */
	private static function form_open( $slug, $class = '', array $data = array() ) {
		$attrs = '';

		foreach ( $data as $key => $value ) {
			$attrs .= ' data-' . $key . '="' . esc_attr( (string) $value ) . '"';
		}

		printf(
			'<form method="post" action="options.php" class="undt-form undt-mpage%1$s" data-undt-autosave="module:%2$s"%3$s>',
			'' !== $class ? ' ' . esc_attr( $class ) : '',
			esc_attr( $slug ),
			$attrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escaped.
		);

		settings_fields( UNDT_Admin::module_group( $slug ) );
	}

	/**
	 * Schliesst ein Formular des Bereichs.
	 *
	 * @return void
	 */
	private static function form_close() {
		UNDT_Ui::noscript_submit();
		echo '</form>';
	}

	/* ---------------------------------------------------- Oeffnungszeiten --- */

	/**
	 * Die Statuszeile: was heute auf der Website steht.
	 *
	 * @return void
	 */
	public static function today_status() {
		$now   = current_datetime();
		$today = UNDT_Hours::today();
		$day   = UNDT_Hours::day_label( strtolower( $now->format( 'D' ) ) );

		if ( ! UNDT_Hours::has_data() && ! $today['is_special'] ) {
			UNDT_Ui::status( 'missing', __( 'Noch keine Zeiten eingetragen.', 'unternehmensdaten' ), __( 'Schalte die Tage ein, an denen du geöffnet hast.', 'unternehmensdaten' ), 'clock' );
			return;
		}

		if ( $today['closed'] ) {
			$line = __( 'Heute geschlossen.', 'unternehmensdaten' );
		} else {
			$parts = array();

			foreach ( $today['slots'] as $slot ) {
				$parts[] = $slot['from'] . '–' . $slot['to'];
			}

			$suffix = trim( (string) self::value( 'hours', 'suffix' ) );
			/* translators: %s: Zeitfenster, etwa „08:00–12:00 und 14:00–18:00 Uhr“. */
			$line = sprintf( __( 'Heute %s.', 'unternehmensdaten' ), implode( ' ' . __( 'und', 'unternehmensdaten' ) . ' ', $parts ) . ( '' !== $suffix ? ' ' . $suffix : '' ) );
		}

		if ( $today['is_special'] && '' !== trim( $today['note'] ) ) {
			$line = rtrim( $line, '.' ) . ' (' . trim( $today['note'] ) . ').';
		}

		/* translators: %s: Wochentag. */
		UNDT_Ui::status( 'ok', $line, sprintf( __( 'So steht es heute, %s, auf deiner Website.', 'unternehmensdaten' ), $day ), 'clock' );
	}

	/**
	 * Seite: Oeffnungszeiten.
	 *
	 * @return void
	 */
	private static function hours() {
		$days    = UNDT_Hours::normalize( self::value( 'hours', 'days' ) );
		$special = self::value( 'hours', 'special' );
		$special = is_array( $special ) ? array_values( $special ) : array();
		$state   = (string) UNDT_Store::get( 'state' );
		$states  = UNDT_Schema::states();

		echo '<div data-undt-part="today">';
		self::today_status();
		echo '</div>';

		self::form_open( 'hours', 'undt-hours-page' );

		echo '<div class="undt-mgrid">';

		// Regulaere Zeiten.
		echo '<section class="undt-box undt-mgrid__main">';
		UNDT_Ui::card_head( __( 'Reguläre Zeiten', 'unternehmensdaten' ) );
		echo '<div class="undt-days">';

		foreach ( UNDT_Hours::display_order() as $day ) {
			self::day_row( $day, $days[ $day ] );
		}

		echo '</div><div class="undt-box__section">';
		printf(
			'<p><button type="button" class="undt-btn undt-btn--secondary undt-btn--small" data-undt-hours-weekdays>%s</button></p>',
			esc_html__( 'Montag auf Dienstag bis Freitag übertragen', 'unternehmensdaten' )
		);
		printf(
			'<label class="undt-mlabel" for="undt-hours-note">%1$s <span class="undt-optional">· %2$s</span></label>',
			esc_html__( 'Hinweis unter den Zeiten', 'unternehmensdaten' ),
			esc_html__( 'optional', 'unternehmensdaten' )
		);
		printf(
			'<textarea id="undt-hours-note" name="%1$s" rows="2" class="undt-minput" placeholder="%2$s">%3$s</textarea>',
			esc_attr( self::name( 'hours', 'note' ) ),
			esc_attr__( 'Etwa: Termine auch außerhalb der Zeiten nach Vereinbarung', 'unternehmensdaten' ),
			esc_textarea( (string) self::value( 'hours', 'note' ) )
		);
		echo '</div></section>';

		// Vorschau.
		echo '<aside class="undt-box undt-mgrid__side">';
		UNDT_Ui::card_head( __( 'So sieht es aus', 'unternehmensdaten' ) );
		echo '<div class="undt-mpreview" data-undt-part="preview">' . self::preview( '[undt_hours]' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in preview() ueber UNDT_Icons::kses().

		if ( UNDT_Access::is_admin() ) {
			echo '<div class="undt-box__section undt-box__section--tight">';
			self::chip( '[undt_hours]', __( 'Öffnungszeiten', 'unternehmensdaten' ) );
			echo '</div>';
		}

		UNDT_Ui::card_foot( __( 'Tage mit gleichen Zeiten werden zusammengefasst. Auf deiner Website übernimmt die Liste Schrift und Farben vom Theme.', 'unternehmensdaten' ) );
		echo '</aside></div>';

		// Feiertage und Urlaub.
		echo '<section class="undt-box">';
		UNDT_Ui::card_head( __( 'Feiertage und Urlaub', 'unternehmensdaten' ), __( 'Haben an diesem Tag Vorrang vor den regulären Zeiten.', 'unternehmensdaten' ) );

		printf( '<div class="undt-special" data-undt-repeater data-undt-next="%d">', count( $special ) );
		printf(
			'<p class="undt-mempty undt-repeater__empty"%1$s><span class="undt-dot-s" aria-hidden="true"></span>%2$s</p>',
			empty( $special ) ? '' : ' hidden',
			esc_html__( 'Noch keine Termine', 'unternehmensdaten' )
		);
		echo '<div class="undt-repeater__rows">';

		foreach ( $special as $index => $row ) {
			self::special_row( (string) $index, is_array( $row ) ? $row : array() );
		}

		echo '</div><template class="undt-repeater__template">';
		self::special_row( UNDT_Controls::INDEX_TOKEN, array( 'closed' => 1 ) );
		echo '</template>';

		echo '<div class="undt-box__section undt-mactions">';
		printf(
			'<button type="button" class="undt-btn undt-btn--secondary undt-btn--small undt-repeater__add">%1$s%2$s</button>',
			UNDT_Ui_Icons::svg( 'plus', 12 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
			esc_html__( 'Termin hinzufügen', 'unternehmensdaten' )
		);

		if ( '' !== $state && isset( $states[ $state ] ) ) {
			$holidays = UNDT_Holidays::upcoming( $state, current_datetime()->format( 'Y-m-d' ) );

			printf(
				'<button type="button" class="undt-btn undt-btn--secondary undt-btn--small" data-undt-holidays="%1$s" data-done="%2$s">%3$s%4$s</button>',
				esc_attr( (string) wp_json_encode( $holidays ) ),
				esc_attr__( 'Alle Feiertage stehen schon in der Liste.', 'unternehmensdaten' ),
				UNDT_Ui_Icons::svg( 'calendar', 13 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
				/* translators: %s: Bundesland. */
				esc_html( sprintf( __( 'Feiertage in %s eintragen', 'unternehmensdaten' ), $states[ $state ] ) )
			);
		}

		echo '</div></div>';

		if ( '' !== $state && isset( $states[ $state ] ) ) {
			UNDT_Ui::card_foot( __( 'Vergangene Termine verschwinden von selbst. Eingetragen werden die Feiertage der nächsten zwölf Monate, die im ganzen Land gelten; Feiertage einzelner Gemeinden trägst du selbst ein.', 'unternehmensdaten' ) );
		} elseif ( UNDT_Access::can_edit() ) {
			// Ein span drumherum: der Fuss ist ein Flex-Container und zerlegte den Satz sonst am Link.
			echo '<div class="undt-box__footer undt-box__footer--note"><span>';
			printf(
				/* translators: %s: Link auf die Seite Firma. */
				esc_html__( 'Vergangene Termine verschwinden von selbst. Trägst du in %s das Bundesland ein, kannst du die Feiertage mit einem Klick übernehmen.', 'unternehmensdaten' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_COMPANY ) ) . '">' . esc_html__( 'Firma', 'unternehmensdaten' ) . '</a>'
			);
			echo '</span></div>';
		}

		echo '</section>';

		// Darstellung.
		echo '<section class="undt-box undt-mfold">';
		UNDT_Ui::details_open( __( 'Darstellung', 'unternehmensdaten' ), __( '„Uhr“, „geschlossen“, Sprache', 'unternehmensdaten' ) );

		$module = UNDT_Modules::get( 'hours' );

		self::row(
			__( 'Zusatz hinter der Uhrzeit', 'unternehmensdaten' ),
			'undt-hours-suffix',
			static function () {
				self::input( self::name( 'hours', 'suffix' ), 'undt-hours-suffix', (string) self::value( 'hours', 'suffix' ) );
			}
		);
		self::row(
			__( 'Text für geschlossen', 'unternehmensdaten' ),
			'undt-hours-closed',
			static function () {
				self::input( self::name( 'hours', 'closed_label' ), 'undt-hours-closed', (string) self::value( 'hours', 'closed_label' ) );
			}
		);
		self::row(
			__( 'Sprache für Tage', 'unternehmensdaten' ),
			'undt-hours-lang',
			static function () use ( $module ) {
				$current = (string) self::value( 'hours', 'day_language' );

				echo '<select id="undt-hours-lang" name="' . esc_attr( self::name( 'hours', 'day_language' ) ) . '">';

				foreach ( $module['fields']['day_language']['choices'] as $key => $label ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
				}

				echo '</select>';
			},
			array( 'help' => $module['fields']['day_language']['help'] )
		);

		UNDT_Ui::details_close();
		echo '</section>';

		self::form_close();
	}

	/**
	 * Eine Zeile der Woche: Schalter, Zeiten, Pause.
	 *
	 * @param string $day  Tagesschluessel.
	 * @param array  $data Normalisierte Angaben des Tages.
	 * @return void
	 */
	private static function day_row( $day, array $data ) {
		$open  = ! $data['closed'];
		$label = UNDT_Hours::day_label( $day );
		$has2  = '' !== $data['slots'][1]['from'] || '' !== $data['slots'][1]['to'];
		$base  = 'undt-' . $day;

		printf( '<div class="undt-day%1$s" data-undt-day="%2$s">', $open ? ' is-open' : '', esc_attr( $day ) );

		echo '<div class="undt-day__switch">';
		UNDT_Controls::toggle(
			self::name( 'hours', 'days', $day, 'open' ),
			$base . '-open',
			$open,
			array(
				'data-undt-day-open' => '1',
				/* translators: %s: Wochentag. */
				'aria-label'         => sprintf( __( '%s geöffnet', 'unternehmensdaten' ), $label ),
			)
		);
		echo '</div>';

		for ( $i = 0; $i < UNDT_Hours::SLOTS; $i++ ) {
			$second = $i > 0;

			printf(
				'<div class="undt-day__col%1$s"%2$s%3$s>',
				$second ? ' undt-day__col--second' : '',
				$second ? ' data-undt-slot2 data-active="' . ( $has2 ? '1' : '0' ) . '"' : '',
				$second && ! ( $open && $has2 ) ? ' hidden' : ''
			);

			echo '<div class="undt-day__head">';

			if ( $second ) {
				printf(
					'<span class="undt-day__and">%1$s<button type="button" class="undt-day__remove" data-undt-remove-slot aria-label="%2$s" title="%3$s">%4$s</button></span>',
					esc_html__( 'und', 'unternehmensdaten' ),
					/* translators: %s: Wochentag. */
					esc_attr( sprintf( __( 'Zweites Zeitfenster am %s entfernen', 'unternehmensdaten' ), $label ) ),
					esc_attr__( 'Zweites Zeitfenster entfernen', 'unternehmensdaten' ),
					UNDT_Ui_Icons::svg( 'x', 9 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
				);
			} else {
				printf(
					'<span class="undt-day__name">%1$s</span><span class="undt-day__state" data-undt-day-state data-open="%2$s" data-closed="%3$s">%4$s</span>',
					esc_html( $label ),
					esc_attr__( 'geöffnet', 'unternehmensdaten' ),
					esc_attr__( 'geschlossen', 'unternehmensdaten' ),
					$open ? esc_html__( 'geöffnet', 'unternehmensdaten' ) : esc_html__( 'geschlossen', 'unternehmensdaten' )
				);
			}

			echo '</div>';

			printf( '<div class="undt-day__times"%s>', $open ? '' : ' hidden' );

			foreach ( array( 'from', 'to' ) as $edge ) {
				$id = $base . '-' . $i . '-' . $edge;

				if ( 'to' === $edge ) {
					echo '<span class="undt-day__dash" aria-hidden="true">–</span>';
				}

				printf(
					'<input type="time" id="%1$s" name="%2$s" value="%3$s" class="undt-time" aria-label="%4$s" />',
					esc_attr( $id ),
					esc_attr( self::name( 'hours', 'days', $day, 'slots', (string) $i, $edge ) ),
					esc_attr( $data['slots'][ $i ][ $edge ] ),
					esc_attr(
						sprintf(
							/* translators: 1: Wochentag, 2: Zusatz fuer das zweite Fenster, 3: von oder bis. */
							__( '%1$s%2$s %3$s', 'unternehmensdaten' ),
							$label,
							$second ? __( ', zweites Zeitfenster', 'unternehmensdaten' ) : '',
							'from' === $edge ? __( 'von', 'unternehmensdaten' ) : __( 'bis', 'unternehmensdaten' )
						)
					)
				);
			}

			echo '</div></div>';
		}

		printf(
			'<button type="button" class="undt-day__add" data-undt-add-slot%1$s>%2$s%3$s</button>',
			$open && ! $has2 ? '' : ' hidden',
			UNDT_Ui_Icons::svg( 'plus', 11 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
			esc_html__( 'Pause', 'unternehmensdaten' )
		);

		echo '</div>';
	}

	/**
	 * Ein Termin unter „Feiertage und Urlaub“.
	 *
	 * @param string $index Zeilenindex oder Platzhalter.
	 * @param array  $row   Gespeicherte Angaben.
	 * @return void
	 */
	private static function special_row( $index, array $row ) {
		$row = array_merge(
			array(
				'date'   => '',
				'closed' => 1,
				'from'   => '',
				'to'     => '',
				'note'   => '',
			),
			$row
		);

		// Ohne Uhrzeiten gilt ein Termin als geschlossen, siehe UNDT_Hours::normalize_special().
		$closed = ! empty( $row['closed'] ) || ( '' === $row['from'] && '' === $row['to'] );
		$id     = 'undt-sp-' . $index;

		echo '<div class="undt-repeater__row undt-srow" data-undt-special-row>';

		printf(
			'<input type="date" class="undt-srow__date" id="%1$s-date" name="%2$s" value="%3$s" aria-label="%4$s" />',
			esc_attr( $id ),
			esc_attr( self::name( 'hours', 'special', $index, 'date' ) ),
			esc_attr( (string) $row['date'] ),
			esc_attr__( 'Datum', 'unternehmensdaten' )
		);
		printf(
			'<input type="text" class="undt-srow__label" id="%1$s-note" name="%2$s" value="%3$s" placeholder="%4$s" aria-label="%4$s" />',
			esc_attr( $id ),
			esc_attr( self::name( 'hours', 'special', $index, 'note' ) ),
			esc_attr( (string) $row['note'] ),
			esc_attr__( 'Bezeichnung', 'unternehmensdaten' )
		);
		printf(
			'<select class="undt-srow__kind" id="%1$s-kind" name="%2$s" aria-label="%3$s" data-undt-special-kind><option value="1"%4$s>%5$s</option><option value="0"%6$s>%7$s</option></select>',
			esc_attr( $id ),
			esc_attr( self::name( 'hours', 'special', $index, 'closed' ) ),
			esc_attr__( 'Art', 'unternehmensdaten' ),
			selected( $closed, true, false ),
			esc_html__( 'Geschlossen', 'unternehmensdaten' ),
			selected( $closed, false, false ),
			esc_html__( 'Andere Zeiten', 'unternehmensdaten' )
		);

		printf( '<span class="undt-srow__times" data-undt-special-times%s>', $closed ? ' hidden' : '' );

		foreach ( array( 'from', 'to' ) as $edge ) {
			if ( 'to' === $edge ) {
				echo '<span class="undt-day__dash" aria-hidden="true">–</span>';
			}

			printf(
				'<input type="time" class="undt-time" id="%1$s-%2$s" name="%3$s" value="%4$s" aria-label="%5$s" />',
				esc_attr( $id ),
				esc_attr( $edge ),
				esc_attr( self::name( 'hours', 'special', $index, $edge ) ),
				esc_attr( (string) $row[ $edge ] ),
				'from' === $edge ? esc_attr__( 'von', 'unternehmensdaten' ) : esc_attr__( 'bis', 'unternehmensdaten' )
			);
		}

		echo '</span>';

		self::icon_button( 'trash', __( 'Termin entfernen', 'unternehmensdaten' ), 'undt-ibtn--bordered undt-repeater__remove' );

		echo '</div>';
	}

	/* ------------------------------------------------------- Social Media --- */

	/**
	 * Seite: Social Media.
	 *
	 * @return void
	 */
	private static function social() {
		$items     = self::value( 'social', 'items' );
		$items     = is_array( $items ) ? array_values( $items ) : array();
		$platforms = UNDT_Modules::platforms();

		self::form_open( 'social', 'undt-social-page' );

		echo '<section class="undt-box">';
		echo '<div class="undt-box__section undt-addbar">';
		printf( '<label class="undt-mlabel" for="undt-social-new">%s</label>', esc_html__( 'Profil hinzufügen', 'unternehmensdaten' ) );
		echo '<div class="undt-addbar__row">';
		self::input( '', 'undt-social-new', '', __( 'Adresse einfügen, etwa https://instagram.com/deinname', 'unternehmensdaten' ), 'url', array( 'data-undt-social-new' => '1' ) );
		printf( '<button type="button" class="undt-btn" data-undt-social-add disabled>%s</button>', esc_html__( 'Hinzufügen', 'unternehmensdaten' ) );
		echo '</div>';
		printf(
			'<p class="undt-addbar__hint" data-undt-social-hint data-idle="%1$s" data-found="%2$s" role="status">%1$s</p>',
			esc_attr__( 'Die Plattform erkennen wir an der Adresse.', 'unternehmensdaten' ),
			/* translators: %s: Name der Plattform. */
			esc_attr__( '%s erkannt', 'unternehmensdaten' )
		);
		echo '</div>';

		printf(
			'<div class="undt-profiles" data-undt-repeater data-undt-next="%1$d" data-undt-platforms="%2$s" data-undt-hosts="%3$s">',
			count( $items ),
			esc_attr( (string) wp_json_encode( $platforms ) ),
			esc_attr( (string) wp_json_encode( UNDT_Modules::platform_hosts() ) )
		);
		printf(
			'<p class="undt-mempty undt-repeater__empty"%1$s><span class="undt-dot-s" aria-hidden="true"></span>%2$s</p>',
			empty( $items ) ? '' : ' hidden',
			esc_html__( 'Noch keine Profile', 'unternehmensdaten' )
		);
		echo '<div class="undt-repeater__rows">';

		foreach ( $items as $index => $item ) {
			self::profile_row( (string) $index, is_array( $item ) ? $item : array() );
		}

		echo '</div><template class="undt-repeater__template">';
		self::profile_row( UNDT_Controls::INDEX_TOKEN, array() );
		echo '</template>';

		echo '<div class="undt-icon-library" hidden>';

		foreach ( array_keys( $platforms ) as $platform ) {
			printf( '<span data-undt-icon="%1$s">%2$s</span>', esc_attr( (string) $platform ), UNDT_Icons::platform( (string) $platform ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ueber wp_kses aufbereitet.
		}

		echo '</div></div>';

		UNDT_Ui::card_foot( __( 'Das Feld rechts ist die Beschriftung. Leer bleibt der Name der Plattform. Die Profile fließen auch in die Angaben für Suchmaschinen ein.', 'unternehmensdaten' ) );
		echo '</section>';

		// Darstellung.
		echo '<section class="undt-box">';
		UNDT_Ui::card_head(
			__( 'Darstellung', 'unternehmensdaten' ),
			'',
			static function () {
				self::chip( '[undt_social]', __( 'Social-Profile', 'unternehmensdaten' ) );
			}
		);

		echo '<div class="undt-split">';
		echo '<div class="undt-split__preview"><div class="undt-split__label">' . esc_html__( 'Vorschau', 'unternehmensdaten' ) . '</div>';
		echo '<div class="undt-mpreview" data-undt-part="preview">' . self::parts( 'social' )['preview'] . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in preview() ueber UNDT_Icons::kses().

		echo '<div class="undt-split__settings">';
		echo '<div class="undt-srow-set"><span class="undt-srow-set__label" id="undt-social-layout-l">' . esc_html__( 'Anordnung', 'unternehmensdaten' ) . '</span>';
		UNDT_Ui::segment(
			self::name( 'social', 'layout' ),
			'undt-social-layout',
			array(
				'list' => __( 'Untereinander', 'unternehmensdaten' ),
				'row'  => __( 'In einer Reihe', 'unternehmensdaten' ),
			),
			(string) self::value( 'social', 'layout' ),
			__( 'Anordnung', 'unternehmensdaten' )
		);
		echo '</div>';

		self::switch_row( 'social', 'show_icons', __( 'Symbole zeigen', 'unternehmensdaten' ) );
		self::switch_row( 'social', 'show_labels', __( 'Plattformnamen zeigen', 'unternehmensdaten' ) );
		self::switch_row( 'social', 'new_tab', __( 'In neuem Tab öffnen', 'unternehmensdaten' ), '', array( 'data-undt-newtab' => '1' ) );
		self::switch_row( 'social', 'new_tab_icon', __( 'Pfeil als Hinweis auf neuen Tab', 'unternehmensdaten' ), '', array( 'data-undt-newtab-arrow' => '1' ), 'undt-srow-set--indent' );

		echo '<details class="undt-details undt-details--inner"><summary class="undt-details__summary">';
		echo UNDT_Ui_Icons::svg( 'caret', 12, 'undt-details__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
		echo '<span class="undt-details__title">' . esc_html__( 'Erweitert', 'unternehmensdaten' ) . ' <span class="undt-optional">· ' . esc_html__( 'optional', 'unternehmensdaten' ) . '</span></span></summary><div class="undt-details__body">';
		self::switch_row( 'social', 'rel_me', __( 'Als eigene Profile auszeichnen (rel="me")', 'unternehmensdaten' ), __( 'Zeigt Plattformen wie Mastodon, dass das Profil zu dieser Website gehört. Schadet nie, deshalb an.', 'unternehmensdaten' ) );
		echo '</div></details>';

		echo '</div></div></section>';

		self::form_close();
	}

	/**
	 * Eine Zeile mit Schalter und Beschriftung.
	 *
	 * @param string $slug  Bereich.
	 * @param string $key   Feld.
	 * @param string $label Beschriftung.
	 * @param string $desc  Erklaerung darunter.
	 * @param array  $attrs Attribute des Schalters.
	 * @param string $class Zusaetzliche Klasse.
	 * @return void
	 */
	private static function switch_row( $slug, $key, $label, $desc = '', array $attrs = array(), $class = '' ) {
		$id = 'undt-' . $slug . '-' . str_replace( '_', '-', $key );

		printf( '<div class="undt-srow-set%s">', '' !== $class ? ' ' . esc_attr( $class ) : '' );
		printf( '<label class="undt-srow-set__label" for="%1$s">%2$s', esc_attr( $id ), esc_html( $label ) );

		if ( '' !== $desc ) {
			echo '<span class="undt-srow-set__desc">' . esc_html( $desc ) . '</span>';
		}

		echo '</label>';
		UNDT_Controls::toggle( self::name( $slug, $key ), $id, (bool) self::value( $slug, $key ), $attrs );
		echo '</div>';
	}

	/**
	 * Ein Profil in der Liste.
	 *
	 * @param string $index Zeilenindex oder Platzhalter.
	 * @param array  $item  Gespeicherte Angaben.
	 * @return void
	 */
	private static function profile_row( $index, array $item ) {
		$platforms = UNDT_Modules::platforms();
		$platform  = isset( $item['platform'] ) ? (string) $item['platform'] : '';
		$url       = isset( $item['url'] ) ? (string) $item['url'] : '';
		$label     = isset( $item['label'] ) ? (string) $item['label'] : '';

		if ( '' === $platform && '' !== $url ) {
			$platform = UNDT_Modules::detect_platform( $url );
		}

		$name = isset( $platforms[ $platform ] ) ? $platforms[ $platform ] : '';

		echo '<div class="undt-repeater__row undt-prow" data-undt-profile>';

		echo '<span class="undt-prow__icon" data-undt-platform-icon>';

		if ( '' !== $platform ) {
			echo UNDT_Icons::platform( $platform ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ueber wp_kses aufbereitet.
		}

		echo '</span>';

		printf(
			'<span class="undt-prow__text"><span class="undt-prow__name" data-undt-platform-name>%1$s</span><span class="undt-prow__url" data-undt-url-text>%2$s</span></span>',
			esc_html( $name ),
			esc_html( preg_replace( '#^https?://(www\.)?#', '', $url ) )
		);

		printf( '<input type="hidden" name="%1$s" value="%2$s" data-undt-platform />', esc_attr( self::name( 'social', 'items', $index, 'platform' ) ), esc_attr( $platform ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" data-undt-url />', esc_attr( self::name( 'social', 'items', $index, 'url' ) ), esc_attr( $url ) );
		printf(
			'<input type="text" class="undt-prow__label" name="%1$s" value="%2$s" placeholder="%3$s" aria-label="%4$s" data-undt-label />',
			esc_attr( self::name( 'social', 'items', $index, 'label' ) ),
			esc_attr( $label ),
			esc_attr( $name ),
			/* translators: %s: Name der Plattform. */
			esc_attr( sprintf( __( 'Beschriftung für %s', 'unternehmensdaten' ), '' !== $name ? $name : __( 'das Profil', 'unternehmensdaten' ) ) )
		);

		echo '<span class="undt-prow__controls">';
		self::icon_button( 'up', __( 'Nach oben', 'unternehmensdaten' ), 'undt-repeater__move', 'up' );
		self::icon_button( 'down', __( 'Nach unten', 'unternehmensdaten' ), 'undt-repeater__move', 'down' );
		self::icon_button( 'trash', __( 'Profil entfernen', 'unternehmensdaten' ), 'undt-repeater__remove' );
		echo '</span></div>';
	}

	/* --------------------------------------------------------- Infobanner --- */

	/**
	 * Der Kopf der Bannerkarte: was gerade gilt.
	 *
	 * @return void
	 */
	public static function banner_head() {
		$enabled = (bool) self::value( 'banner', 'enabled' );
		$from    = UNDT_Hours::date( self::value( 'banner', 'date_from' ) );
		$to      = UNDT_Hours::date( self::value( 'banner', 'date_to' ) );

		if ( ! $enabled ) {
			$title = __( 'Ausgeblendet', 'unternehmensdaten' );
			$text  = __( 'Deine Eingaben bleiben erhalten. Schalte es wieder ein, wenn du es brauchst.', 'unternehmensdaten' );
		} elseif ( ! UNDT_Content::has( 'banner', 'text' ) ) {
			$title = __( 'Noch ohne Text', 'unternehmensdaten' );
			$text  = __( 'Es erscheint, sobald unten ein Text steht.', 'unternehmensdaten' );
		} elseif ( '' !== $from && wp_date( 'Y-m-d' ) < $from ) {
			$title = __( 'Eingeplant', 'unternehmensdaten' );
			/* translators: %s: Datum. */
			$text = sprintf( __( 'Erscheint ab %s.', 'unternehmensdaten' ), UNDT_Hours::date_label( $from ) );
		} elseif ( '' !== $to && wp_date( 'Y-m-d' ) > $to ) {
			$title = __( 'Zeitraum vorbei', 'unternehmensdaten' );
			/* translators: %s: Datum. */
			$text = sprintf( __( 'War bis %s zu sehen. Ändere den Zeitraum, um es wieder zu zeigen.', 'unternehmensdaten' ), UNDT_Hours::date_label( $to ) );
		} else {
			$title = __( 'Wird gerade angezeigt', 'unternehmensdaten' );
			$text  = UNDT_Content::value( 'banner', 'auto_output' )
				? __( 'Oben auf jeder Seite deiner Website.', 'unternehmensdaten' )
				: __( 'Dort, wo du es selbst platziert hast.', 'unternehmensdaten' );
		}

		printf(
			'<h2 class="undt-box__title">%1$s</h2><p class="undt-box__desc">%2$s</p>',
			esc_html( $title ),
			esc_html( $text )
		);
	}

	/**
	 * Seite: Infobanner.
	 *
	 * @return void
	 */
	private static function banner() {
		$module = UNDT_Modules::get( 'banner' );
		$text   = (string) self::value( 'banner', 'text' );
		$from   = UNDT_Hours::date( self::value( 'banner', 'date_from' ) );
		$to     = UNDT_Hours::date( self::value( 'banner', 'date_to' ) );

		// Die Vorschau kommt ohne Shortcode aus und fordert das CSS der Bausteine selbst an.
		UNDT_Shortcodes::need_style();

		self::form_open( 'banner', 'undt-banner-page' );

		echo '<section class="undt-box">';
		echo '<div class="undt-box__head undt-bhead">';
		UNDT_Ui::tile( 'megaphone', 0, self::value( 'banner', 'enabled' ) ? 'brand' : 'muted' );
		echo '<div class="undt-box__heading" data-undt-part="bannerhead">';
		self::banner_head();
		echo '</div><div class="undt-box__aside">';
		UNDT_Controls::toggle(
			self::name( 'banner', 'enabled' ),
			'undt-banner-enabled',
			(bool) self::value( 'banner', 'enabled' ),
			array(
				'aria-label'         => __( 'Banner anzeigen', 'unternehmensdaten' ),
				'data-undt-banner-q' => '1',
			)
		);
		echo '</div></div>';

		// Vorschau auf einer angedeuteten Seite.
		echo '<div class="undt-box__section"><div class="undt-bsite">';
		echo '<div class="undt-bsite__banner" data-undt-part="preview" data-undt-banner-preview>' . wp_kses_post( UNDT_Blocks::banner_markup( true ) ) . '</div>';
		printf(
			'<p class="undt-bsite__gone" data-undt-banner-gone hidden>%1$s <button type="button" class="undt-link-button" data-undt-banner-back>%2$s</button></p>',
			esc_html__( 'Weggeklickt. So sieht es danach für Besucher aus.', 'unternehmensdaten' ),
			esc_html__( 'Wieder einblenden', 'unternehmensdaten' )
		);
		echo '<div class="undt-bsite__page" aria-hidden="true"><div class="undt-bsite__nav"><span></span><span></span><span></span><span></span></div><div class="undt-bsite__hero"></div><div class="undt-bsite__line"></div><div class="undt-bsite__line undt-bsite__line--short"></div></div>';
		echo '</div><p class="undt-bsite__note">' . esc_html__( 'Vorschau. Die Schrift übernimmt das Banner von deinem Theme.', 'unternehmensdaten' ) . '</p></div>';

		echo '<div class="undt-box__body">';

		self::row(
			__( 'Art', 'unternehmensdaten' ),
			'',
			static function () use ( $module ) {
				UNDT_Ui::segment(
					self::name( 'banner', 'type' ),
					'undt-banner-type',
					$module['fields']['type']['choices'],
					UNDT_Blocks::banner_type(),
					__( 'Art', 'unternehmensdaten' )
				);
			}
		);

		self::row(
			__( 'Text', 'unternehmensdaten' ),
			'undt-banner-text',
			static function () use ( $text ) {
				echo '<div class="undt-counted">';
				printf(
					'<textarea id="undt-banner-text" name="%1$s" rows="2" data-undt-count="140" aria-describedby="undt-banner-count">%2$s</textarea>',
					esc_attr( self::name( 'banner', 'text' ) ),
					esc_textarea( $text )
				);
				printf(
					'<div class="undt-counted__foot"><span class="undt-amber" data-undt-count-warn hidden><span class="undt-amber__dot" aria-hidden="true"></span>%1$s</span><span class="undt-counted__num" id="undt-banner-count" data-undt-count-num data-template="%2$s">%3$s</span></div>',
					esc_html__( 'Auf dem Handy wird das mehr als zwei Zeilen lang', 'unternehmensdaten' ),
					/* translators: %d: Anzahl der Zeichen. */
					esc_attr__( '%d / 140 Zeichen', 'unternehmensdaten' ),
					/* translators: %d: Anzahl der Zeichen. */
					esc_html( sprintf( __( '%d / 140 Zeichen', 'unternehmensdaten' ), function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text ) ) )
				);
				echo '</div>';
			}
		);

		self::row(
			__( 'Link', 'unternehmensdaten' ),
			'',
			static function () {
				echo '<div class="undt-frow__fields">';
				echo '<div class="undt-frow__field"><label class="undt-frow__sub" for="undt-banner-lt">' . esc_html__( 'Linktext', 'unternehmensdaten' ) . '</label>';
				self::input( self::name( 'banner', 'link_text' ), 'undt-banner-lt', (string) self::value( 'banner', 'link_text' ), __( 'Mehr erfahren', 'unternehmensdaten' ) );
				echo '</div><div class="undt-frow__field"><label class="undt-frow__sub" for="undt-banner-lu">' . esc_html__( 'Ziel', 'unternehmensdaten' ) . '</label>';
				self::input( self::name( 'banner', 'link_url' ), 'undt-banner-lu', (string) self::value( 'banner', 'link_url' ), __( 'Adresse einfügen, etwa https://…', 'unternehmensdaten' ), 'url' );
				echo '</div></div>';
			},
			array( 'optional' => true )
		);

		self::row(
			__( 'Wegklicken erlauben', 'unternehmensdaten' ),
			'undt-banner-dismissible',
			static function () {
				UNDT_Controls::toggle( self::name( 'banner', 'dismissible' ), 'undt-banner-dismissible', (bool) self::value( 'banner', 'dismissible' ) );
				echo '<span class="undt-frow__inline-help">' . esc_html__( 'Weggeklickt bleibt es aus, bis du den Text änderst.', 'unternehmensdaten' ) . '</span>';
			}
		);

		echo '</div>';

		// Zeitraum.
		$period = '' !== $from || '' !== $to
			? trim( ( '' !== $from ? UNDT_Hours::date_label( $from ) : '' ) . ' – ' . ( '' !== $to ? UNDT_Hours::date_label( $to ) : '' ), ' –' )
			: __( 'Blendet sich selbst ein und aus', 'unternehmensdaten' );

		echo '<div class="undt-box__fold">';
		UNDT_Ui::details_open( __( 'Zeitraum', 'unternehmensdaten' ), $period );
		echo '<div class="undt-frow__fields undt-period">';
		echo '<div class="undt-frow__field"><label class="undt-frow__sub" for="undt-banner-from">' . esc_html__( 'Ab', 'unternehmensdaten' ) . '</label>';
		self::input( self::name( 'banner', 'date_from' ), 'undt-banner-from', $from, '', 'date' );
		echo '</div><div class="undt-frow__field"><label class="undt-frow__sub" for="undt-banner-to">' . esc_html__( 'Bis einschließlich', 'unternehmensdaten' ) . '</label>';
		self::input( self::name( 'banner', 'date_to' ), 'undt-banner-to', $to, '', 'date' );
		echo '</div></div>';
		echo '<p class="undt-frow__help">' . esc_html__( 'Ohne Datum bleibt das Banner sichtbar, bis du es ausschaltest. Nutzt die Website einen Seiten-Cache, erscheint die Änderung erst, wenn er sich erneuert.', 'unternehmensdaten' ) . '</p>';
		UNDT_Ui::details_close();

		// Platzierung, nur fuer Admins.
		if ( UNDT_Access::is_admin() ) {
			$auto = (bool) self::value( 'banner', 'auto_output' );

			UNDT_Ui::details_open( __( 'Platzierung', 'unternehmensdaten' ), $auto ? __( 'Automatisch ganz oben', 'unternehmensdaten' ) : __( 'Selbst platziert', 'unternehmensdaten' ) );
			echo '<div class="undt-choices" role="radiogroup" aria-label="' . esc_attr__( 'Platzierung', 'unternehmensdaten' ) . '">';
			self::choice( self::name( 'banner', 'auto_output' ), 'undt-banner-place-auto', '1', $auto, __( 'Automatisch ganz oben', 'unternehmensdaten' ), __( 'Empfohlen. Funktioniert mit fast allen Themes.', 'unternehmensdaten' ) );
			self::choice( self::name( 'banner', 'auto_output' ), 'undt-banner-place-manual', '0', ! $auto, __( 'Selbst platzieren', 'unternehmensdaten' ), __( 'Nur nötig, wenn dein Theme den Platz oben nicht anbietet. Setze dann den Shortcode an die gewünschte Stelle.', 'unternehmensdaten' ) );
			printf( '<div class="undt-choices__extra" data-undt-banner-manual%s>', $auto ? ' hidden' : '' );
			self::chip( '[undt_banner]', __( 'Infobanner', 'unternehmensdaten' ) );
			echo '</div></div>';
			UNDT_Ui::details_close();
		}

		echo '</div></section>';

		self::form_close();
	}

	/**
	 * Eine Wahl unter mehreren, mit Erklaerung.
	 *
	 * @param string $name    Formularname.
	 * @param string $id      ID.
	 * @param string $value   Wert.
	 * @param bool   $checked Gewaehlt.
	 * @param string $label   Beschriftung.
	 * @param string $desc    Erklaerung.
	 * @return void
	 */
	private static function choice( $name, $id, $value, $checked, $label, $desc = '' ) {
		printf(
			'<label class="undt-choice" for="%1$s"><input type="radio" id="%1$s" name="%2$s" value="%3$s"%4$s /><span class="undt-choice__text"><span class="undt-choice__label">%5$s</span>%6$s</span></label>',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value ),
			checked( $checked, true, false ),
			esc_html( $label ),
			'' !== $desc ? '<span class="undt-choice__desc">' . esc_html( $desc ) . '</span>' : ''
		);
	}

	/* ------------------------------------------------------------- Preise --- */

	/**
	 * Seite: Preise und Leistungen.
	 *
	 * Die Gruppen gibt es nur auf der Seite. Gespeichert wird wie bisher eine
	 * flache Liste, in der jede Leistung den Namen ihrer Gruppe traegt; die
	 * Reihenfolge im Formular ist die Reihenfolge der Ausgabe.
	 *
	 * @return void
	 */
	private static function prices() {
		$items  = self::value( 'prices', 'items' );
		$items  = is_array( $items ) ? array_values( $items ) : array();
		$groups = array();

		foreach ( $items as $index => $item ) {
			$group = isset( $item['group'] ) ? (string) $item['group'] : '';

			if ( ! isset( $groups[ $group ] ) ) {
				$groups[ $group ] = array();
			}

			$groups[ $group ][ $index ] = is_array( $item ) ? $item : array();
		}

		// Eine leere Gruppe zum Anfangen.
		if ( empty( $groups ) ) {
			$groups[''] = array( 0 => array() );
			$items      = array( array() );
		}

		self::form_open( 'prices', 'undt-prices-page', array( 'undt-prices' => '1' ) );

		printf( '<div class="undt-pgroups" data-undt-pgroups data-undt-next="%d">', count( $items ) );

		foreach ( $groups as $name => $group_items ) {
			self::price_group( (string) $name, $group_items );
		}

		echo '</div>';

		echo '<template data-undt-pgroup-template>';
		self::price_group( '', array( UNDT_Controls::INDEX_TOKEN => array() ) );
		echo '</template><template data-undt-pitem-template>';
		self::price_item( UNDT_Controls::INDEX_TOKEN, array(), '' );
		echo '</template>';

		printf(
			'<p class="undt-mactions"><button type="button" class="undt-btn undt-btn--secondary undt-btn--small" data-undt-pgroup-add>%1$s%2$s</button></p>',
			UNDT_Ui_Icons::svg( 'plus', 12 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
			esc_html__( 'Gruppe hinzufügen', 'unternehmensdaten' )
		);

		// Vorschau mit Hinweis.
		echo '<section class="undt-box">';
		UNDT_Ui::card_head(
			__( 'So sieht es aus', 'unternehmensdaten' ),
			'',
			static function () {
				self::chip( '[undt_prices]', __( 'Preise', 'unternehmensdaten' ) );
			}
		);
		echo '<div class="undt-mpreview" data-undt-part="preview">' . self::parts( 'prices' )['preview'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in preview() ueber UNDT_Icons::kses().

		echo '<div class="undt-box__section">';
		self::switch_row( 'prices', 'vat_note', __( 'Hinweis zur Umsatzsteuer darunter zeigen', 'unternehmensdaten' ) );
		echo '<p class="undt-frow__help">' . esc_html__( 'Der Hinweis kommt aus Firma › Steuer, du musst ihn nicht zweimal pflegen.', 'unternehmensdaten' ) . '</p>';
		echo '</div>';

		echo '<div class="undt-box__fold">';
		UNDT_Ui::details_open( __( 'Einleitung und eigener Hinweis', 'unternehmensdaten' ) );
		self::row(
			__( 'Einleitung', 'unternehmensdaten' ),
			'undt-prices-intro',
			static function () {
				printf( '<textarea id="undt-prices-intro" name="%1$s" rows="2">%2$s</textarea>', esc_attr( self::name( 'prices', 'intro' ) ), esc_textarea( (string) self::value( 'prices', 'intro' ) ) );
			},
			array(
				'optional' => true,
				'help'     => __( 'Steht über der Liste.', 'unternehmensdaten' ),
			)
		);
		self::row(
			__( 'Eigener Hinweis', 'unternehmensdaten' ),
			'undt-prices-footnote',
			static function () {
				printf( '<textarea id="undt-prices-footnote" name="%1$s" rows="2">%2$s</textarea>', esc_attr( self::name( 'prices', 'footnote' ) ), esc_textarea( (string) self::value( 'prices', 'footnote' ) ) );
			},
			array(
				'optional' => true,
				'help'     => __( 'Ersetzt den Hinweis aus Firma, solange der Schalter oben an ist.', 'unternehmensdaten' ),
			)
		);
		UNDT_Ui::details_close();
		echo '</div></section>';

		self::form_close();
	}

	/**
	 * Eine Gruppe der Preisliste.
	 *
	 * @param string $name  Name der Gruppe.
	 * @param array  $items Index => Leistung.
	 * @return void
	 */
	private static function price_group( $name, array $items ) {
		echo '<section class="undt-box undt-pgroup" data-undt-pgroup>';
		echo '<div class="undt-pgroup__head">';
		printf(
			'<input type="text" class="undt-pgroup__name" value="%1$s" placeholder="%2$s" aria-label="%2$s" data-undt-pgroup-name />',
			esc_attr( $name ),
			esc_attr__( 'Name der Gruppe', 'unternehmensdaten' )
		);
		self::icon_button( 'up', __( 'Gruppe nach oben', 'unternehmensdaten' ), 'undt-pgroup__move', 'up' );
		self::icon_button( 'down', __( 'Gruppe nach unten', 'unternehmensdaten' ), 'undt-pgroup__move', 'down' );
		self::icon_button( 'trash', __( 'Gruppe entfernen', 'unternehmensdaten' ), 'undt-pgroup__remove' );
		echo '</div>';

		echo '<div class="undt-pgroup__cols" aria-hidden="true"><span>' . esc_html__( 'Leistung', 'unternehmensdaten' ) . '</span><span>' . esc_html__( 'Preis', 'unternehmensdaten' ) . '</span><span></span></div>';
		echo '<div class="undt-pgroup__items" data-undt-pgroup-items>';

		foreach ( $items as $index => $item ) {
			self::price_item( (string) $index, is_array( $item ) ? $item : array(), $name );
		}

		echo '</div>';
		printf(
			'<p class="undt-pgroup__add"><button type="button" class="undt-textbtn" data-undt-pitem-add>%1$s%2$s</button></p>',
			UNDT_Ui_Icons::svg( 'plus', 11 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
			esc_html__( 'Leistung hinzufügen', 'unternehmensdaten' )
		);
		echo '</section>';
	}

	/**
	 * Eine Leistung.
	 *
	 * @param string $index Index oder Platzhalter.
	 * @param array  $item  Gespeicherte Angaben.
	 * @param string $group Name der Gruppe.
	 * @return void
	 */
	private static function price_item( $index, array $item, $group ) {
		echo '<div class="undt-pitem" data-undt-pitem>';
		printf( '<input type="hidden" name="%1$s" value="%2$s" data-undt-pitem-group />', esc_attr( self::name( 'prices', 'items', $index, 'group' ) ), esc_attr( $group ) );
		printf(
			'<input type="text" name="%1$s" value="%2$s" placeholder="%3$s" aria-label="%4$s" />',
			esc_attr( self::name( 'prices', 'items', $index, 'label' ) ),
			esc_attr( isset( $item['label'] ) ? (string) $item['label'] : '' ),
			esc_attr__( 'Etwa Erstberatung, 30 Minuten', 'unternehmensdaten' ),
			esc_attr__( 'Leistung', 'unternehmensdaten' )
		);
		printf(
			'<input type="text" name="%1$s" value="%2$s" placeholder="%3$s" aria-label="%4$s" />',
			esc_attr( self::name( 'prices', 'items', $index, 'price' ) ),
			esc_attr( isset( $item['price'] ) ? (string) $item['price'] : '' ),
			esc_attr__( '45 €', 'unternehmensdaten' ),
			esc_attr__( 'Preis', 'unternehmensdaten' )
		);
		// Der Zusatz hat auf der Seite kein Feld mehr, bleibt aber erhalten.
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::name( 'prices', 'items', $index, 'note' ) ), esc_attr( isset( $item['note'] ) ? (string) $item['note'] : '' ) );
		self::icon_button( 'x', __( 'Leistung entfernen', 'unternehmensdaten' ), 'undt-pitem__remove' );
		echo '</div>';
	}

	/* ---------------------------------------------------------------- FAQ --- */

	/**
	 * Seite: FAQ.
	 *
	 * @return void
	 */
	private static function faq() {
		$items  = self::value( 'faq', 'items' );
		$items  = is_array( $items ) ? array_values( $items ) : array();
		$module = UNDT_Modules::get( 'faq' );

		self::form_open( 'faq', 'undt-faq-page' );

		echo '<section class="undt-box">';
		UNDT_Ui::card_head(
			__( 'Fragen', 'unternehmensdaten' ),
			'',
			static function () {
				self::chip( '[undt_faq]', __( 'FAQ', 'unternehmensdaten' ) );
			}
		);

		printf( '<div class="undt-faqs" data-undt-repeater data-undt-next="%d">', count( $items ) );
		printf(
			'<p class="undt-mempty undt-repeater__empty"%1$s><span class="undt-dot-s" aria-hidden="true"></span>%2$s</p>',
			empty( $items ) ? '' : ' hidden',
			esc_html__( 'Noch keine Fragen', 'unternehmensdaten' )
		);
		echo '<div class="undt-repeater__rows">';

		foreach ( $items as $index => $item ) {
			self::faq_item( (string) $index, is_array( $item ) ? $item : array(), false );
		}

		echo '</div><template class="undt-repeater__template">';
		self::faq_item( UNDT_Controls::INDEX_TOKEN, array(), true );
		echo '</template>';

		printf(
			'<div class="undt-box__section undt-mactions"><button type="button" class="undt-btn undt-btn--secondary undt-btn--small undt-repeater__add">%1$s%2$s</button></div>',
			UNDT_Ui_Icons::svg( 'plus', 12 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
			esc_html__( 'Frage hinzufügen', 'unternehmensdaten' )
		);
		echo '</div></section>';

		echo '<section class="undt-box undt-mfold">';
		UNDT_Ui::details_open( __( 'Darstellung', 'unternehmensdaten' ), __( 'Aufklappbar oder ausgeschrieben', 'unternehmensdaten' ) );
		self::row(
			__( 'Darstellung', 'unternehmensdaten' ),
			'',
			static function () use ( $module ) {
				UNDT_Ui::segment(
					self::name( 'faq', 'style' ),
					'undt-faq-style',
					array(
						'details' => __( 'Aufklappbar', 'unternehmensdaten' ),
						'dl'      => __( 'Ausgeschrieben', 'unternehmensdaten' ),
					),
					(string) self::value( 'faq', 'style' ),
					$module['fields']['style']['label']
				);
			}
		);
		echo '<div class="undt-frow"><div class="undt-frow__main">';
		self::switch_row( 'faq', 'open_first', __( 'Erste Frage aufgeklappt zeigen', 'unternehmensdaten' ) );
		echo '</div></div>';
		UNDT_Ui::details_close();
		echo '</section>';

		echo '<section class="undt-box undt-mfold">';
		UNDT_Ui::details_open( __( 'Für Suchmaschinen', 'unternehmensdaten' ) );
		self::switch_row( 'faq', 'schema', __( 'Als FAQ für Google auszeichnen', 'unternehmensdaten' ), __( 'Standardmäßig aus: Google zeigt für FAQ-Auszeichnungen seit Mai 2026 keine Zusatzanzeige mehr. Schaden tut es nicht.', 'unternehmensdaten' ) );
		self::row(
			__( 'Auch ohne Baustein auf', 'unternehmensdaten' ),
			'undt-faq-schema-page',
			static function () {
				$current = (string) self::value( 'faq', 'schema_page' );
				$choices = array(
					'front' => __( 'Startseite', 'unternehmensdaten' ),
					'block' => __( 'Nirgends, nur wo der Baustein steht', 'unternehmensdaten' ),
				);

				foreach ( get_pages( array( 'post_status' => 'publish' ) ) as $page ) {
					$choices[ (string) $page->ID ] = get_the_title( $page->ID );
				}

				echo '<select id="undt-faq-schema-page" name="' . esc_attr( self::name( 'faq', 'schema_page' ) ) . '">';

				foreach ( $choices as $key => $label ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $key ), selected( $current, (string) $key, false ), esc_html( $label ) );
				}

				echo '</select>';
			},
			array( 'help' => $module['fields']['schema_page']['help'] )
		);
		UNDT_Ui::details_close();
		echo '</section>';

		self::form_close();
	}

	/**
	 * Eine Frage, aufklappbar.
	 *
	 * @param string $index Index oder Platzhalter.
	 * @param array  $item  Gespeicherte Angaben.
	 * @param bool   $open  Aufgeklappt.
	 * @return void
	 */
	private static function faq_item( $index, array $item, $open ) {
		$question = isset( $item['question'] ) ? (string) $item['question'] : '';
		$answer   = isset( $item['answer'] ) ? (string) $item['answer'] : '';
		$body     = 'undt-faq-' . $index;

		echo '<div class="undt-repeater__row undt-faqitem" data-undt-faq>';
		echo '<div class="undt-faqitem__head">';
		printf(
			'<button type="button" class="undt-faqitem__toggle" aria-expanded="%1$s" aria-controls="%2$s" data-undt-faq-toggle>%3$s<span class="undt-faqitem__title" data-undt-faq-title data-empty="%4$s">%5$s</span></button>',
			$open ? 'true' : 'false',
			esc_attr( $body ),
			UNDT_Ui_Icons::svg( 'caret', 12, 'undt-faqitem__chevron' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
			esc_attr__( 'Neue Frage', 'unternehmensdaten' ),
			esc_html( '' !== trim( $question ) ? $question : __( 'Neue Frage', 'unternehmensdaten' ) )
		);
		self::icon_button( 'up', __( 'Frage nach oben', 'unternehmensdaten' ), 'undt-repeater__move', 'up' );
		self::icon_button( 'down', __( 'Frage nach unten', 'unternehmensdaten' ), 'undt-repeater__move', 'down' );
		self::icon_button( 'trash', __( 'Frage entfernen', 'unternehmensdaten' ), 'undt-repeater__remove' );
		echo '</div>';

		printf( '<div class="undt-faqitem__body" id="%1$s"%2$s>', esc_attr( $body ), $open ? '' : ' hidden' );
		printf(
			'<label class="undt-mlabel" for="%1$s-q">%2$s</label><input type="text" id="%1$s-q" name="%3$s" value="%4$s" placeholder="%5$s" data-undt-faq-question />',
			esc_attr( $body ),
			esc_html__( 'Frage', 'unternehmensdaten' ),
			esc_attr( self::name( 'faq', 'items', $index, 'question' ) ),
			esc_attr( $question ),
			esc_attr__( 'Etwa: Gibt es Parkplätze?', 'unternehmensdaten' )
		);
		printf(
			'<label class="undt-mlabel" for="%1$s-a">%2$s</label><textarea id="%1$s-a" name="%3$s" rows="3">%4$s</textarea>',
			esc_attr( $body ),
			esc_html__( 'Antwort', 'unternehmensdaten' ),
			esc_attr( self::name( 'faq', 'items', $index, 'answer' ) ),
			esc_textarea( $answer )
		);
		// Die Gruppe hat auf der Seite kein Feld, bleibt aber erhalten.
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::name( 'faq', 'items', $index, 'group' ) ), esc_attr( isset( $item['group'] ) ? (string) $item['group'] : '' ) );
		echo '</div></div>';
	}

	/* ------------------------------------------------------ Suchmaschinen --- */

	/**
	 * Wer die strukturierten Daten gerade ausgibt.
	 *
	 * @return void
	 */
	public static function seo_status() {
		$mode   = (string) self::value( 'seo', 'output_mode' );
		$plugin = UNDT_SchemaOrg::detect_seo_plugin();

		if ( 'never' === $mode ) {
			UNDT_Ui::status(
				'missing',
				__( 'Dieses Plugin gibt keine strukturierten Daten aus', 'unternehmensdaten' ),
				'Slim SEO' === $plugin
					? __( 'Slim SEO kann deine Firmendaten trotzdem über die Platzhalter unten verwenden.', 'unternehmensdaten' )
					: __( 'Suchmaschinen erfahren dann nur, was auf den Seiten steht.', 'unternehmensdaten' ),
				'info'
			);
			return;
		}

		if ( UNDT_SchemaOrg::should_output() ) {
			UNDT_Ui::status( 'ok', __( 'Dieses Plugin gibt deine Firmendaten aus', 'unternehmensdaten' ), __( 'Auf jeder Seite, unsichtbar für Besucher.', 'unternehmensdaten' ), 'check' );

			if ( '' !== $plugin ) {
				/* translators: %s: Name des SEO-Plugins. */
				UNDT_Ui::amber( sprintf( __( '%s gibt ebenfalls eine Organisation aus. Google sieht dann zwei.', 'unternehmensdaten' ), $plugin ) );
			}

			return;
		}

		UNDT_Ui::status(
			'ok',
			/* translators: %s: Name des SEO-Plugins. */
			sprintf( __( '%s gibt deine Firmendaten aus', 'unternehmensdaten' ), $plugin ),
			'Slim SEO' === $plugin
				? __( 'Damit es keine doppelte Auszeichnung gibt, hält sich dieses Plugin zurück. Die Angaben unten übernimmt Slim SEO über die Platzhalter.', 'unternehmensdaten' )
				: __( 'Damit es keine doppelte Auszeichnung gibt, hält sich dieses Plugin zurück.', 'unternehmensdaten' ),
			'check'
		);
	}

	/**
	 * Die Auszeichnung, wie sie im Quelltext stuende.
	 *
	 * @return string
	 */
	public static function jsonld() {
		$data = UNDT_SchemaOrg::organization();

		return empty( $data ) ? __( 'Noch nichts auszugeben, es fehlen Angaben in Firma.', 'unternehmensdaten' ) : (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Seite: Suchmaschinen.
	 *
	 * @return void
	 */
	private static function seo() {
		$module = UNDT_Modules::get( 'seo' );
		$lat    = trim( (string) self::value( 'seo', 'geo_lat' ) );
		$lng    = trim( (string) self::value( 'seo', 'geo_lng' ) );
		$geo    = '' !== $lat && '' !== $lng ? $lat . ', ' . $lng : '';
		$slim   = 'Slim SEO' === UNDT_SchemaOrg::detect_seo_plugin();

		echo '<p class="undt-intro">' . esc_html__( 'Damit Google und andere Suchmaschinen deine Firma richtig zuordnen: Name, Anschrift, Öffnungszeiten und Profile als strukturierte Daten. Besucher sehen davon nichts.', 'unternehmensdaten' ) . '</p>';

		self::form_open( 'seo', 'undt-seo-page' );

		// Wer gibt aus.
		echo '<section class="undt-box"><div class="undt-box__section undt-box__section--first" data-undt-part="seostatus">';
		self::seo_status();
		echo '</div><div class="undt-box__section"><div class="undt-choices" role="radiogroup" aria-label="' . esc_attr__( 'Wer gibt die strukturierten Daten aus', 'unternehmensdaten' ) . '">';

		$mode = (string) self::value( 'seo', 'output_mode' );

		self::choice( self::name( 'seo', 'output_mode' ), 'undt-seo-mode-auto', 'auto', 'auto' === $mode, __( 'Automatisch', 'unternehmensdaten' ), __( 'Empfohlen. Nur wenn kein SEO-Plugin aktiv ist.', 'unternehmensdaten' ) );
		self::choice( self::name( 'seo', 'output_mode' ), 'undt-seo-mode-always', 'always', 'always' === $mode, __( 'Immer', 'unternehmensdaten' ), __( 'Auch wenn ein SEO-Plugin aktiv ist.', 'unternehmensdaten' ) );
		self::choice( self::name( 'seo', 'output_mode' ), 'undt-seo-mode-never', 'never', 'never' === $mode, __( 'Nie', 'unternehmensdaten' ) );

		echo '</div></div></section>';

		// Angaben.
		$logo = UNDT_SchemaOrg::logo_source();

		if ( 'theme' === $logo ) {
			$logo_text = __( 'Name, Anschrift und Kontakt kommen aus „Firma“, das Logo aus deinem Theme.', 'unternehmensdaten' );
		} elseif ( 'icon' === $logo ) {
			$logo_text = __( 'Name, Anschrift und Kontakt kommen aus „Firma“. Als Logo dient das Website-Icon, weil das Theme kein Logo hat.', 'unternehmensdaten' );
		} else {
			$logo_text = __( 'Name, Anschrift und Kontakt kommen aus „Firma“. Ein Logo fehlt: lege es unter Design › Customizer als Logo oder Website-Icon fest.', 'unternehmensdaten' );
		}

		echo '<section class="undt-box">';
		UNDT_Ui::card_head( __( 'Was Suchmaschinen zusätzlich erfahren', 'unternehmensdaten' ), $logo_text );
		echo '<div class="undt-box__body">';

		self::row(
			__( 'Art der Firma', 'unternehmensdaten' ),
			'undt-seo-type',
			static function () use ( $module ) {
				$current = (string) self::value( 'seo', 'schema_type' );
				$i       = 0;

				echo '<select id="undt-seo-type" name="' . esc_attr( self::name( 'seo', 'schema_type' ) ) . '">';

				foreach ( $module['fields']['schema_type']['choices'] as $key => $label ) {
					// Die ersten sieben sind die haeufigen, der Rest steht darunter.
					if ( 7 === $i ) {
						echo '<optgroup label="' . esc_attr__( 'Weitere', 'unternehmensdaten' ) . '">';
					}

					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
					++$i;
				}

				echo $i > 7 ? '</optgroup></select>' : '</select>';

				self::type_hint( $current, $module['fields']['schema_type']['choices'] );
			},
			array( 'help' => $module['fields']['schema_type']['help'] )
		);

		$price   = (string) self::value( 'seo', 'price_range' );
		$choices = array(
			''     => __( 'Keine Angabe', 'unternehmensdaten' ),
			'€'    => '€',
			'€€'   => '€€',
			'€€€'  => '€€€',
			'€€€€' => '€€€€',
		);

		// Ein frueher frei eingetragenes Preisniveau bleibt waehlbar.
		if ( ! isset( $choices[ $price ] ) ) {
			$choices[ $price ] = $price;
		}

		self::row(
			__( 'Preisniveau', 'unternehmensdaten' ),
			'',
			static function () use ( $choices, $price ) {
				UNDT_Ui::segment( self::name( 'seo', 'price_range' ), 'undt-seo-price', $choices, $price, __( 'Preisniveau', 'unternehmensdaten' ) );
			},
			array( 'optional' => true )
		);

		self::row(
			__( 'Einzugsgebiet', 'unternehmensdaten' ),
			'undt-seo-area',
			static function () {
				self::input( self::name( 'seo', 'area_served' ), 'undt-seo-area', (string) self::value( 'seo', 'area_served' ), __( 'Etwa Landkreis Traunstein oder Chiemgau', 'unternehmensdaten' ) );
			},
			array( 'optional' => true )
		);

		self::row(
			__( 'Standort auf der Karte', 'unternehmensdaten' ),
			'undt-seo-geo',
			static function () use ( $geo ) {
				echo '<div class="undt-geo">';
				self::input(
					self::name( 'seo', 'geo' ),
					'undt-seo-geo',
					$geo,
					'47.9312, 12.6458',
					'text',
					array(
						'data-undt-geo'    => '1',
						'aria-describedby' => 'undt-seo-geo-help',
						'inputmode'        => 'decimal',
					)
				);
				printf(
					'<p class="undt-geo__ok" data-undt-geo-ok data-template="%1$s" hidden></p><p class="undt-amber" data-undt-geo-bad hidden><span class="undt-amber__dot" aria-hidden="true"></span>%2$s</p>',
					/* translators: 1: Breitengrad, 2: Laengengrad. */
					esc_attr__( 'Breitengrad %1$s · Längengrad %2$s', 'unternehmensdaten' ),
					esc_html__( 'Zwei Zahlen mit Komma dazwischen, etwa 47.9312, 12.6458', 'unternehmensdaten' )
				);
				echo '<p class="undt-frow__help" id="undt-seo-geo-help">' . esc_html__( 'Klick in Google Maps mit der rechten Maustaste auf deinen Standort, dann auf die erste Zeile, und füge sie hier ein.', 'unternehmensdaten' ) . '</p>';
				echo '</div>';
			},
			array( 'optional' => true )
		);

		echo '<div class="undt-frow"><div class="undt-frow__main">';
		self::switch_row( 'seo', 'with_hours', __( 'Öffnungszeiten mitgeben', 'unternehmensdaten' ), __( 'Google zeigt sie dann direkt in den Suchergebnissen.', 'unternehmensdaten' ) );
		self::switch_row( 'seo', 'with_social', __( 'Social-Profile mitgeben', 'unternehmensdaten' ), __( 'Verknüpft deine Profile mit deiner Firma.', 'unternehmensdaten' ) );
		echo '</div></div>';

		echo '</div></section>';

		self::form_close();

		// Slim SEO.
		if ( $slim ) {
			self::slim_seo();
		}

		// JSON-LD.
		echo '<section class="undt-box undt-mfold">';
		UNDT_Ui::details_open( __( 'Code ansehen', 'unternehmensdaten' ), __( 'Was im Quelltext steht', 'unternehmensdaten' ) );
		echo '<pre class="undt-jsonld" data-undt-part="jsonld" tabindex="0" aria-label="' . esc_attr__( 'Strukturierte Daten im Quelltext', 'unternehmensdaten' ) . '">' . esc_html( self::jsonld() ) . '</pre>';
		UNDT_Ui::details_close();
		echo '</section>';
	}

	/**
	 * Der Vorschlag der Art aus der Berufsbezeichnung in Firma.
	 *
	 * Nur solange die Art noch allgemein ist (Organisation, Geschäft vor Ort):
	 * wer schon etwas Bestimmtes gewaehlt hat, hat das bewusst getan.
	 *
	 * @param string $current Gewaehlte Art.
	 * @param array  $choices Typ => Bezeichnung.
	 * @return void
	 */
	private static function type_hint( $current, array $choices ) {
		$title     = (string) UNDT_Store::get( 'job_title' );
		$suggested = UNDT_Professions::schema_type( $title );

		if ( '' === $suggested || $suggested === $current || ! in_array( $current, array( 'Organization', 'LocalBusiness', '' ), true ) || ! isset( $choices[ $suggested ] ) ) {
			return;
		}

		printf(
			'<p class="undt-typehint" data-undt-type-hint>%1$s <button type="button" class="undt-link-button" data-undt-type-apply="%2$s">%3$s</button></p>',
			wp_kses(
				sprintf(
					/* translators: 1: Berufsbezeichnung, 2: Art der Firma. */
					__( 'Passt zu deinem Beruf (%1$s): %2$s.', 'unternehmensdaten' ),
					esc_html( $title ),
					'<strong>' . esc_html( $choices[ $suggested ] ) . '</strong>'
				),
				array( 'strong' => array() )
			),
			esc_attr( $suggested ),
			esc_html__( 'Übernehmen', 'unternehmensdaten' )
		);
	}

	/**
	 * Die Platzhalter fuer Slim SEO.
	 *
	 * @return void
	 */
	private static function slim_seo() {
		$all  = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_SCHEMA );
		$main = array_intersect_key( $all, array_flip( array( 'company_name', 'phone', 'email', 'logo_url', 'social_profiles' ) ) );
		$rest = array_diff_key( $all, $main );

		echo '<section class="undt-box">';
		UNDT_Ui::card_head(
			__( 'In Slim SEO verwenden', 'unternehmensdaten' ),
			__( 'In den Feldern von Slim SEO setzt du deine Firmendaten als Platzhalter ein. Ändert sich hier etwas, zieht Slim SEO mit.', 'unternehmensdaten' ),
			static function () {
				printf(
					'<a class="undt-action" href="%1$s">%2$s%3$s</a>',
					esc_url( admin_url( 'options-general.php?page=slim-seo' ) ),
					esc_html__( 'Einstellungen von Slim SEO', 'unternehmensdaten' ),
					UNDT_Ui_Icons::svg( 'caret', 12 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes SVG.
				);
			}
		);

		echo '<div class="undt-box__body undt-slim">';

		foreach ( $main as $key => $label ) {
			self::slim_row( $key, $label );
		}

		if ( ! empty( $rest ) ) {
			UNDT_Ui::details_open( __( 'Weitere Platzhalter', 'unternehmensdaten' ), sprintf( /* translators: %d: Anzahl. */ _n( '%d weiterer', '%d weitere', count( $rest ), 'unternehmensdaten' ), count( $rest ) ) );

			foreach ( $rest as $key => $label ) {
				self::slim_row( $key, $label );
			}

			UNDT_Ui::details_close();
		}

		echo '</div>';
		UNDT_Ui::card_foot( __( 'Slim SEO wandelt reine Ziffernfolgen in Zahlen um. Bei Telefonnummern ohne Leerzeichen geht die führende Null verloren.', 'unternehmensdaten' ) );
		echo '</section>';
	}

	/**
	 * Eine Zeile mit Platzhalter.
	 *
	 * @param string $key   Schluessel.
	 * @param string $label Bezeichnung.
	 * @return void
	 */
	private static function slim_row( $key, $label ) {
		echo '<div class="undt-slim__row"><span class="undt-slim__label">' . esc_html( $label ) . '</span>';
		self::chip( '{{ undt.' . $key . ' }}', $label );
		echo '</div>';
	}

	/* ---------------------------------------------------- Eigene Module --- */

	/**
	 * Das allgemeine Formular fuer Module, die ein Filter ergaenzt.
	 *
	 * @param string $slug Bereich.
	 * @return void
	 */
	private static function generic( $slug ) {
		$module = UNDT_Modules::get( $slug );

		if ( null === $module ) {
			return;
		}

		self::form_open( $slug );
		echo '<div class="undt-box"><div class="undt-box__body"><table class="form-table" role="presentation"><tbody>';
		UNDT_Fields::rows( $module['fields'], UNDT_Content::all( $slug ), $module['option'], array( 'with_copy' => false ) );
		echo '</tbody></table></div></div>';
		self::form_close();
	}
}
