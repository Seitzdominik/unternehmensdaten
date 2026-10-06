<?php
/**
 * Kopierknoepfe und das Format „Kopieren als“.
 *
 * Shortcodes und die Schreibweisen fuer Bricks und Etch stehen im Backend
 * ueberall dort, wo sie gebraucht werden: neben den Angaben in Firma und
 * auf Einbinden. Gemeinsam ist ihnen, dass ein Klick den Text in die Zwischenablage
 * legt, siehe initCopy() in admin.js.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Copy
 */
final class UNDT_Copy {

	/**
	 * Nutzer-Meta mit dem gewaehlten Format fuer „Kopieren als“.
	 *
	 * Je Benutzer, nicht je Website: in einer Agentur arbeitet nicht jeder im
	 * selben Werkzeug.
	 */
	const META_FORMAT = 'undt_copy_format';

	/**
	 * Die Formate, in denen ein Feld kopiert werden kann.
	 *
	 * Angeboten wird, was installiert ist. „active“ markiert das Werkzeug, mit dem
	 * die Website gerade gebaut wird; es ist vorausgewaehlt und gilt als erkannt.
	 * Der Shortcode geht immer.
	 *
	 * @return array Schluessel ist das Format, Wert label, installed, active.
	 */
	public static function formats() {
		$etch_file   = 'etch/etch.php';
		$etch_active = in_array( $etch_file, (array) get_option( 'active_plugins', array() ), true );
		$bricks      = function_exists( 'wp_get_theme' ) ? wp_get_theme( 'bricks' ) : null;
		$gutenberg   = UNDT_Dynamic_Gutenberg::available();

		$formats = array(
			'etch'      => array(
				'label'     => 'Etch',
				'installed' => $etch_active || ( defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/' . $etch_file ) ),
				'active'    => $etch_active,
			),
			'bricks'    => array(
				'label'     => 'Bricks',
				'installed' => $bricks && $bricks->exists(),
				'active'    => function_exists( 'get_template' ) && 'bricks' === get_template(),
			),
			'shortcode' => array(
				'label'     => __( 'Shortcode', 'unternehmensdaten' ),
				'installed' => true,
				'active'    => false,
			),
			'gutenberg' => array(
				'label'     => __( 'Block-Editor', 'unternehmensdaten' ),
				'installed' => $gutenberg,
				'active'    => false,
			),
		);

		// Ohne Builder wird im Block-Editor gebaut.
		if ( ! $formats['etch']['active'] && ! $formats['bricks']['active'] && $gutenberg ) {
			$formats['gutenberg']['active'] = true;
		}

		return array_filter(
			$formats,
			static function ( $format ) {
				return $format['installed'];
			}
		);
	}

	/**
	 * Das Format, in dem der aktuelle Benutzer kopiert.
	 *
	 * @return string
	 */
	public static function format() {
		$formats = self::formats();
		$chosen  = (string) get_user_meta( get_current_user_id(), self::META_FORMAT, true );

		if ( isset( $formats[ $chosen ] ) ) {
			return $chosen;
		}

		foreach ( $formats as $key => $format ) {
			if ( $format['active'] ) {
				return $key;
			}
		}

		return 'shortcode';
	}

	/**
	 * Speichert das Format des aktuellen Benutzers.
	 *
	 * @param string $format Format.
	 * @return bool Ob es ein bekanntes Format war.
	 */
	public static function set_format( $format ) {
		$format = sanitize_key( $format );

		if ( ! isset( self::formats()[ $format ] ) ) {
			return false;
		}

		update_user_meta( get_current_user_id(), self::META_FORMAT, $format );

		return true;
	}

	/**
	 * Der Code einer Angabe in jedem Format.
	 *
	 * Der Block-Editor bekommt einen fertigen Absatz mit Bindung, sofern
	 * WordPress Bindungen kennt und die Angabe als Text taugt, sonst den
	 * Shortcode.
	 *
	 * @param string $key Schluessel aus UNDT_Dynamic::fields().
	 * @return array Format => Code.
	 */
	public static function codes( $key ) {
		$syntax    = UNDT_Dynamic::syntax( $key );
		$shortcode = self::shortcode_for( $key );
		$codes     = array(
			'etch'      => $syntax['etch'],
			'bricks'    => $syntax['bricks'],
			'shortcode' => $shortcode,
			'gutenberg' => $shortcode,
		);

		if ( UNDT_Dynamic_Gutenberg::available() && UNDT_Dynamic_Gutenberg::offers( $key ) ) {
			$codes['gutenberg'] = UNDT_Dynamic_Gutenberg::markup( $key );
		}

		return $codes;
	}

	/**
	 * Der Shortcode einer Angabe, leer wenn es keinen gibt.
	 *
	 * Abgeleitete Angaben aus UNDT_Dynamic haben teils eigene Shortcodes, teils
	 * nur ein Attribut, und manche gibt es nur fuer die Builder, etwa ob gerade
	 * geoeffnet ist.
	 *
	 * @param string $key Schluessel aus UNDT_Dynamic::fields().
	 * @return string
	 */
	public static function shortcode_for( $key ) {
		$special = array(
			'address'     => '[undt_address inline="1"]',
			'hours_today' => '[undt_hours_today]',
			'open_now'    => '[undt_open_now]',
		);

		if ( isset( $special[ $key ] ) ) {
			return $special[ $key ];
		}

		$field = UNDT_Schema::field( $key );

		if ( null !== $field && ! empty( $field['shortcode'] ) ) {
			return 'page' === $field['type'] ? '[undt key="' . $key . '" link="1"]' : '[undt key="' . $key . '"]';
		}

		// Telefon oder E-Mail als Link.
		if ( '_link' === substr( $key, -5 ) ) {
			$base = UNDT_Schema::field( substr( $key, 0, -5 ) );

			if ( null !== $base && in_array( $base['type'], array( 'tel', 'email' ), true ) ) {
				return '[undt key="' . substr( $key, 0, -5 ) . '" link="1"]';
			}
		}

		return '';
	}

	/**
	 * Wie ein kopierter Code angezeigt wird: der Absatz fuer den Block-Editor
	 * ist zu lang fuer eine Zeile und erscheint als „Absatz: Telefon“.
	 *
	 * @param string $format Format.
	 * @param string $code   Code.
	 * @param string $label  Bezeichnung der Angabe.
	 * @return string
	 */
	public static function display( $format, $code, $label ) {
		if ( 'gutenberg' === $format && 0 === strpos( $code, '<!--' ) ) {
			/* translators: %s: Bezeichnung der Angabe, etwa „Telefon“. */
			return sprintf( __( 'Absatz: %s', 'unternehmensdaten' ), $label );
		}

		return $code;
	}

	/**
	 * Das graue Kopier-Symbol neben einem Feld.
	 *
	 * Kopiert im Format aus „Kopieren als“. Alle Formate stehen im Attribut
	 * data-undt-codes, damit ein Wechsel des Formats ohne Neuladen greift.
	 *
	 * @param string $key   Feld.
	 * @param string $label Bezeichnung.
	 * @return void
	 */
	public static function field_button( $key, $label ) {
		$format = self::format();
		$codes  = self::codes( $key );
		$shown  = array();

		foreach ( $codes as $name => $code ) {
			$shown[ $name ] = self::display( $name, $code, $label );
		}

		/* translators: %s: Bezeichnung der Angabe. */
		$aria = sprintf( __( 'Angabe kopieren: %s', 'unternehmensdaten' ), $label );

		printf(
			'<button type="button" class="undt-copy undt-fcopy" data-undt-copy="%1$s" data-undt-codes="%2$s" data-undt-shown="%3$s" aria-label="%4$s" title="%4$s">%5$s%6$s</button>',
			esc_attr( $codes[ $format ] ),
			esc_attr( (string) wp_json_encode( $codes ) ),
			esc_attr( (string) wp_json_encode( $shown ) ),
			esc_attr( $aria ),
			UNDT_Ui_Icons::svg( 'copy', 16, 'undt-fcopy__idle' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes Markup.
			UNDT_Ui_Icons::svg( 'check', 16, 'undt-fcopy__done' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes Markup.
		);
	}


	/**
	 * Die Kopierknoepfe unter einem Feld.
	 *
	 * Stammdaten zeigen den Shortcode, daneben stehen die Logos von Bricks und
	 * Etch fuer die jeweilige Schreibweise und das von WordPress fuer den
	 * fertigen Block. Den Tag selbst nennt nur der Tooltip, damit unter jedem
	 * Feld eine Zeile genuegt. Felder der Inhaltsbereiche haben keinen
	 * Shortcode, manche aber einen Wert fuer die Builder, etwa die Angaben des
	 * Infobanners.
	 *
	 * @param string $shortcode Shortcode, leer fuer keinen.
	 * @param string $dynamic   Schluessel fuer Bricks und Etch, leer fuer keinen.
	 * @return void
	 */
	public static function row( $shortcode, $dynamic ) {
		// Nur, was die Builder auch tatsaechlich aufloesen.
		$fields  = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER );
		$builder = '' !== $dynamic && array_key_exists( $dynamic, $fields );

		if ( '' === $shortcode && ! $builder ) {
			return;
		}

		echo '<div class="undt-copy-row">';

		if ( '' !== $shortcode ) {
			self::button( $shortcode, 'inline' );
		}

		if ( $builder ) {
			$syntax = UNDT_Dynamic::syntax( $dynamic );

			// Die Logos stehen am rechten Rand des Feldes, mit Abstand zum Shortcode.
			echo '<span class="undt-copy-row__keys">';

			self::icon( $syntax['bricks'], 'bricks', 'Bricks' );
			self::icon( $syntax['etch'], 'etch', 'Etch' );

			/*
			 * Gutenberg bekommt keinen Tag, sondern einen fertigen Absatz. Der
			 * Tooltip nennt deshalb das Feld statt des kopierten Textes.
			 */
			if ( UNDT_Dynamic_Gutenberg::available() && UNDT_Dynamic_Gutenberg::offers( $dynamic ) ) {
				self::icon(
					UNDT_Dynamic_Gutenberg::markup( $dynamic ),
					'gutenberg',
					'Gutenberg',
					sprintf(
						/* translators: %s: Feldbezeichnung, etwa „Telefon“. */
						__( 'Gutenberg: Absatz mit „%s“ kopieren und im Editor einfügen', 'unternehmensdaten' ),
						$fields[ $dynamic ]
					)
				);
			}

			echo '</span>';
		}

		echo '</div>';
	}

	/**
	 * Ein Kopierknopf, der statt des Textes nur ein Logo zeigt.
	 *
	 * @param string $text  Zu kopierender Text.
	 * @param string $icon  Name des Symbols, siehe UNDT_Icons::svg().
	 * @param string $tool  Name des Werkzeugs fuer Tooltip und Screenreader.
	 * @param string $label Eigener Tooltip, leer fuer „Werkzeug: Tag kopieren“.
	 * @return void
	 */
	private static function icon( $text, $icon, $tool, $label = '' ) {
		if ( '' === $label ) {
			$label = sprintf(
				/* translators: 1: Werkzeug, etwa Bricks, 2: zu kopierender Tag. */
				__( '%1$s: %2$s kopieren', 'unternehmensdaten' ),
				$tool,
				$text
			);
		}

		// Das Logo sitzt in einem kleinen Rahmen, damit es als Knopf erkennbar ist.
		printf(
			'<button type="button" class="undt-copy undt-copy--icon undt-copy--%1$s" data-undt-copy="%2$s" title="%3$s"><span class="undt-copy__badge" aria-hidden="true">%4$s</span><span class="screen-reader-text">%3$s</span></button>',
			esc_attr( $icon ),
			esc_attr( $text ),
			esc_attr( $label ),
			self::glyph( $icon ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes Markup.
		);
	}

	/**
	 * Das Zeichen im Rahmen eines Symbol-Knopfes.
	 *
	 * Bricks und Etch bringen ihr Logo als SVG mit. Fuer WordPress selbst gibt
	 * es kein eigenes: das W steht in den Dashicons, die im Backend ohnehin
	 * geladen sind.
	 *
	 * @param string $icon Name des Symbols.
	 * @return string
	 */
	private static function glyph( $icon ) {
		if ( 'gutenberg' === $icon ) {
			return '<span class="dashicons dashicons-wordpress-alt"></span>';
		}

		return UNDT_Icons::svg( $icon, 'undt-copy__icon' );
	}

	/**
	 * Ein Button, der den uebergebenen Text in die Zwischenablage kopiert.
	 *
	 * Zwei Auspraegungen: „button“ fuer die Referenzseiten, wo das Kopieren die
	 * Hauptsache ist, und „inline“ fuer die Feldbeschriftungen, wo eine
	 * vollwertige Schaltflaeche je Feld die Seite zustellen wuerde.
	 *
	 * @param string $text    Zu kopierender Text.
	 * @param string $style   button oder inline.
	 * @param string $caption Sichtbare Beschriftung, leer fuer den Text selbst.
	 * @return void
	 */
	public static function button( $text, $style = 'button', $caption = '' ) {
		// Was kopiert wird, passt nicht immer auf einen Knopf: ein ganzer Block etwa.
		$shown = '' === $caption ? $text : $caption;

		$label = sprintf(
			/* translators: %s: Shortcode, Tag oder Funktionsaufruf. */
			__( '%s kopieren', 'unternehmensdaten' ),
			$shown
		);

		if ( 'inline' === $style ) {
			printf(
				'<button type="button" class="undt-copy undt-copy--inline" data-undt-copy="%1$s" title="%2$s"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><code>%3$s</code><span class="screen-reader-text">%2$s</span></button>',
				esc_attr( $text ),
				esc_attr( $label ),
				esc_html( $shown )
			);

			return;
		}

		printf(
			'<button type="button" class="button button-small undt-copy" data-undt-copy="%1$s"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <code>%2$s</code><span class="screen-reader-text">%3$s</span></button>',
			esc_attr( $text ),
			esc_html( $shown ),
			esc_attr( $label )
		);
	}
}