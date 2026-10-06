<?php
/**
 * Die Seite „Einbinden“.
 *
 * Drei Teile: die Rechtsseiten mit ihrem Stand, die fertigen Bausteine mit
 * Vorschau und Optionen, und die einzelnen Angaben fuer eigene Layouts. Fertige
 * Bausteine sind in jedem Format Shortcodes; einzelne Angaben folgen dem Format
 * aus „Kopieren als“.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Embed
 */
final class UNDT_Embed {

	/**
	 * Wie viele einzelne Angaben ohne „Alle anzeigen“ sichtbar sind.
	 */
	const VISIBLE_VALUES = 8;

	/**
	 * Erklaerung je Format, unter dem Segment-Schalter.
	 *
	 * @return array Format => Satz.
	 */
	public static function format_help() {
		return array(
			'etch'      => __( 'In Etch schreibst du die Angabe als {options.undt.…} in einen Text oder ein Attribut. Fertige Bausteine bleiben Shortcodes.', 'unternehmensdaten' ),
			'bricks'    => __( 'In Bricks stehen die Angaben unter „Dynamic Data“. Fertige Bausteine bleiben Shortcodes.', 'unternehmensdaten' ),
			'shortcode' => __( 'Shortcodes funktionieren in jedem Editor, in Widgets und in Page Buildern.', 'unternehmensdaten' ),
			'gutenberg' => __( 'Für den Block-Editor kopierst du einen fertigen Absatz, der die Angabe anzeigt. Füge ihn mit Strg+V in den Editor ein.', 'unternehmensdaten' ),
		);
	}

	/* ------------------------------------------------------ Rechtsseiten ---- */

	/**
	 * Die Rechtsseiten mit ihrem Stand.
	 *
	 * @return array Feld => array( label, tone (ok|open|missing), status, create, view ).
	 */
	public static function pages() {
		$profile = UNDT_Store::profile();
		$rows    = array();

		$consumer    = ! empty( $profile['sells_to_consumers'] );
		$a11y_needed = $consumer && empty( $profile['bfsg_exempt'] );

		$defs = array(
			'page_imprint'       => array( __( 'Impressum', 'unternehmensdaten' ), 'imprint', true ),
			'page_privacy'       => array( __( 'Datenschutzerklärung', 'unternehmensdaten' ), 'privacy', true ),
			'page_terms'         => array( __( 'AGB', 'unternehmensdaten' ), '', false ),
			'page_accessibility' => array( __( 'Barrierefreiheit', 'unternehmensdaten' ), '', $a11y_needed ),
		);

		foreach ( $defs as $key => $def ) {
			list( $label, $block, $needed ) = $def;

			$row = array(
				'label'  => $label,
				'tone'   => 'ok',
				'status' => __( 'Verknüpft', 'unternehmensdaten' ),
				'create' => '',
				'view'   => '',
			);

			$id     = UNDT_Store::page_id( $key );
			$target = UNDT_Store::link( $key );

			if ( $id > 0 && 'publish' !== get_post_status( $id ) ) {
				$row['tone']   = 'open';
				$row['status'] = __( 'Entwurf, noch nicht veröffentlicht', 'unternehmensdaten' );
			} elseif ( $id > 0 ) {
				$row['view'] = (string) get_permalink( $id );

				if ( 'imprint' === $block ) {
					if ( UNDT_Usage::on_post( $id, 'imprint' ) ) {
						$row['status'] = __( 'Enthält das Impressum', 'unternehmensdaten' );
					} else {
						$row['tone']   = 'open';
						/* translators: %s: Titel der Seite. */
						$row['status'] = sprintf( __( 'Auf „%s“ steht kein Impressum', 'unternehmensdaten' ), get_the_title( $id ) );
						$row['create'] = $block;
					}
				}
			} elseif ( null !== $target ) {
				$row['status'] = __( 'Eigene Adresse', 'unternehmensdaten' );
				$row['view']   = $target['url'];
			} elseif ( $needed ) {
				$row['tone']   = 'open';
				$row['status'] = __( 'Keine Seite gewählt', 'unternehmensdaten' );
				$row['create'] = $block;
			} else {
				$row['tone']   = 'missing';
				$row['status'] = 'page_terms' === $key
					? ( $consumer ? __( 'Keine Seite gewählt', 'unternehmensdaten' ) : __( 'Nur nötig, wenn du an Verbraucher verkaufst', 'unternehmensdaten' ) )
					: ( $consumer ? __( 'Als Kleinstunternehmen nicht nötig', 'unternehmensdaten' ) : __( 'Nur nötig bei Verträgen mit Verbrauchern', 'unternehmensdaten' ) );
			}

			$rows[ $key ] = $row;
		}

		return $rows;
	}

	/**
	 * Legt eine Rechtsseite als Entwurf an, mit dem passenden Baustein, und
	 * verknuepft sie.
	 *
	 * Nur die Bausteine, keine Volltexte: eine Datenschutzerklaerung schreibt
	 * das Plugin bewusst nicht.
	 *
	 * @param string $kind imprint oder privacy.
	 * @return int|WP_Error ID der neuen Seite.
	 */
	public static function create_page( $kind ) {
		$kinds = array(
			'imprint' => array(
				'field'   => 'page_imprint',
				'title'   => __( 'Impressum', 'unternehmensdaten' ),
				'content' => "<!-- wp:shortcode -->\n[undt_impressum]\n<!-- /wp:shortcode -->",
			),
			'privacy' => array(
				'field'   => 'page_privacy',
				'title'   => __( 'Datenschutzerklärung', 'unternehmensdaten' ),
				'content' => "<!-- wp:shortcode -->\n[undt_privacy_block name=\"controller\"]\n<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->\n[undt_privacy_block name=\"authority\"]\n<!-- /wp:shortcode -->",
			),
		);

		if ( ! isset( $kinds[ $kind ] ) ) {
			return new WP_Error( 'value', '', 400 );
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => $kinds[ $kind ]['title'],
				'post_content' => $kinds[ $kind ]['content'],
			),
			true
		);

		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'create', '', 500 );
		}

		$company                            = get_option( UNDT_Store::OPTION_COMPANY, array() );
		$company                            = is_array( $company ) ? $company : array();
		$company[ $kinds[ $kind ]['field'] ] = array(
			'choice' => (string) $id,
			'url'    => '',
		);

		update_option( UNDT_Store::OPTION_COMPANY, $company );
		UNDT_Store::flush();
		UNDT_Usage::flush();

		return (int) $id;
	}

	/* ---------------------------------------------------------- Bausteine ---- */

	/**
	 * Die Bausteine in Gruppen, wie im Entwurf.
	 *
	 * Optionen sind Haekchen, die ein Attribut setzen: on/off ist das Attribut,
	 * das im jeweiligen Zustand in den Code kommt, leer fuer keins.
	 *
	 * @return array Gruppe => array( title, icon, items ).
	 */
	public static function groups() {
		$groups = array(
			'legal'   => array(
				'title' => __( 'Impressum und Rechtliches', 'unternehmensdaten' ),
				'icon'  => 'file_text',
				'items' => array(
					'imprint'   => array( __( 'Impressum', 'unternehmensdaten' ), __( 'Alle Pflichtangaben als fertiger Abschnitt, mit Überschriften', 'unternehmensdaten' ), 'undt_impressum', array() ),
					'legal_nav' => array( __( 'Rechtslinks', 'unternehmensdaten' ), __( 'Links zu Impressum, Datenschutz, AGB und Barrierefreiheit', 'unternehmensdaten' ), 'undt_legal_nav', array() ),
				),
			),
			'privacy' => array(
				'title' => __( 'Datenschutzerklärung', 'unternehmensdaten' ),
				'icon'  => 'lock',
				'items' => array(
					'privacy'   => array( __( 'Verantwortlicher', 'unternehmensdaten' ), __( 'Name, Anschrift und Kontakt nach Art. 4 Nr. 7 DSGVO', 'unternehmensdaten' ), 'undt_privacy_block name="controller"', array( self::option( __( 'Mit Überschrift', 'unternehmensdaten' ), '', 'bare="1"' ) ) ),
					'authority' => array( __( 'Aufsichtsbehörde', 'unternehmensdaten' ), __( 'Für den Hinweis auf das Beschwerderecht', 'unternehmensdaten' ), 'undt_privacy_block name="authority"', array( self::option( __( 'Mit Überschrift', 'unternehmensdaten' ), '', 'bare="1"' ) ) ),
					'dpo'       => array( __( 'Datenschutzbeauftragter', 'unternehmensdaten' ), __( 'Nur nötig, wenn du einen benannt hast', 'unternehmensdaten' ), 'undt_privacy_block name="dpo"', array( self::option( __( 'Mit Überschrift', 'unternehmensdaten' ), '', 'bare="1"' ) ) ),
				),
			),
			'contact' => array(
				'title' => __( 'Footer und Kontakt', 'unternehmensdaten' ),
				'icon'  => 'buildings',
				'items' => array(
					'footer'  => array(
						__( 'Footer', 'unternehmensdaten' ),
						__( 'Anschrift, Rechtslinks und Copyright mit laufendem Jahr', 'unternehmensdaten' ),
						'undt_footer',
						array(
							self::option( __( 'Anschrift', 'unternehmensdaten' ), '', '', 'show', 'address' ),
							self::option( __( 'Rechtslinks', 'unternehmensdaten' ), '', '', 'show', 'legal' ),
							self::option( __( 'Copyright', 'unternehmensdaten' ), '', '', 'show', 'copyright' ),
						),
					),
					'address' => array(
						__( 'Anschrift', 'unternehmensdaten' ),
						__( 'Die Anschrift als address-Element', 'unternehmensdaten' ),
						'undt_address',
						array(
							self::option( __( 'In einer Zeile', 'unternehmensdaten' ), 'inline="1"', '', '', '', false ),
							self::option( __( 'Firmennamen zeigen', 'unternehmensdaten' ), '', 'name="0"' ),
						),
					),
				),
			),
		);

		if ( UNDT_Modules::is_active( 'hours' ) ) {
			$groups['hours'] = array(
				'title' => __( 'Öffnungszeiten', 'unternehmensdaten' ),
				'icon'  => 'clock',
				'items' => array(
					'hours'    => array(
						__( 'Öffnungszeiten', 'unternehmensdaten' ),
						__( 'Tabelle mit Sonderöffnungszeiten darunter', 'unternehmensdaten' ),
						'undt_hours',
						array(
							self::option( __( 'Gleiche Tage zusammenfassen', 'unternehmensdaten' ), '', 'group="0"' ),
							self::option( __( 'Sonderöffnungszeiten zeigen', 'unternehmensdaten' ), '', 'special="0"' ),
						),
					),
					'today'    => array( __( 'Heute geöffnet', 'unternehmensdaten' ), __( 'Die heutige Zeit als kurzer Text', 'unternehmensdaten' ), 'undt_hours_today', array() ),
					'open_now' => array( __( 'Gerade geöffnet', 'unternehmensdaten' ), __( 'Geöffnet oder geschlossen, mit Sonderzeiten', 'unternehmensdaten' ), 'undt_open_now', array() ),
				),
			);
		}

		$more = array();

		if ( UNDT_Modules::is_active( 'social' ) ) {
			$more['social'] = array( __( 'Social-Profile', 'unternehmensdaten' ), __( 'Liste der Profile, auf Wunsch mit Symbolen', 'unternehmensdaten' ), 'undt_social', array( self::option( __( 'In einer Reihe', 'unternehmensdaten' ), 'layout="row"', '', '', '', false ) ) );
		}

		if ( UNDT_Modules::is_active( 'banner' ) ) {
			$more['banner'] = array( __( 'Infobanner', 'unternehmensdaten' ), __( 'Nur nötig, wenn das Banner nicht automatisch oben erscheint', 'unternehmensdaten' ), 'undt_banner', array() );
		}

		if ( UNDT_Modules::is_active( 'prices' ) ) {
			$more['prices'] = array( __( 'Preisliste', 'unternehmensdaten' ), __( 'Leistungen mit Preisen, nach Gruppen', 'unternehmensdaten' ), 'undt_prices', array() );
		}

		if ( UNDT_Modules::is_active( 'faq' ) ) {
			$more['faq'] = array( __( 'Fragen und Antworten', 'unternehmensdaten' ), __( 'Zum Aufklappen', 'unternehmensdaten' ), 'undt_faq', array() );
		}

		if ( ! empty( $more ) ) {
			$groups['more'] = array(
				'title' => __( 'Weitere Bereiche', 'unternehmensdaten' ),
				'icon'  => 'share',
				'items' => $more,
			);
		}

		return $groups;
	}

	/**
	 * Eine Option eines Bausteins.
	 *
	 * @param string $label   Beschriftung.
	 * @param string $on      Attribut, wenn angehakt.
	 * @param string $off     Attribut, wenn nicht angehakt.
	 * @param string $list    Name eines Listen-Attributs, etwa show beim Footer.
	 * @param string $item    Eintrag in dieser Liste.
	 * @param bool   $checked Voreinstellung.
	 * @return array
	 */
	private static function option( $label, $on = '', $off = '', $list = '', $item = '', $checked = true ) {
		return array(
			'label'   => $label,
			'on'      => $on,
			'off'     => $off,
			'list'    => $list,
			'item'    => $item,
			'checked' => $checked,
		);
	}

	/**
	 * Der Code eines Bausteins in der Voreinstellung seiner Optionen.
	 *
	 * Spiegelt code() in admin.js, damit die Seite ohne Skript dasselbe zeigt.
	 *
	 * @param string $tag     Tag samt festen Attributen.
	 * @param array  $options Optionen.
	 * @return string
	 */
	public static function code( $tag, array $options ) {
		$attrs = array();
		$lists = array();

		foreach ( $options as $option ) {
			if ( '' !== $option['list'] ) {
				$lists[ $option['list'] ][] = $option['checked'] ? $option['item'] : null;
				continue;
			}

			$attr = $option['checked'] ? $option['on'] : $option['off'];

			if ( '' !== $attr ) {
				$attrs[] = $attr;
			}
		}

		// Eine Liste erscheint nur, wenn nicht alles gewaehlt ist.
		foreach ( $lists as $name => $items ) {
			if ( in_array( null, $items, true ) ) {
				$attrs[] = $name . '="' . implode( ',', array_filter( $items ) ) . '"';
			}
		}

		return '[' . $tag . ( empty( $attrs ) ? '' : ' ' . implode( ' ', $attrs ) ) . ']';
	}

	/**
	 * Die Vorschau eines Bausteins mit den echten Angaben.
	 *
	 * Nimmt nur Shortcodes des Plugins an, mit einfachen Attributen. Die
	 * Ausgabe laeuft durch wp_kses_post.
	 *
	 * @param string $code Shortcode.
	 * @return string|null HTML, null wenn der Code nicht angenommen wird.
	 */
	public static function preview( $code ) {
		$code = trim( (string) $code );

		if ( ! preg_match( '/^\[undt_[a-z_]+(\s+[a-z_]+="[^"\[\]]*")*\]$/', $code ) ) {
			return null;
		}

		$html = trim( (string) do_shortcode( $code ) );

		if ( '' === wp_strip_all_tags( $html ) ) {
			return '<p class="undt-preview__empty">' . esc_html__( 'Noch nichts auszugeben, es fehlen Angaben.', 'unternehmensdaten' ) . '</p>';
		}

		return UNDT_Icons::kses( $html );
	}

	/* --------------------------------------------------- Einzelne Angaben ---- */

	/**
	 * Die Filtergruppen der einzelnen Angaben.
	 *
	 * @return array Gruppe => Beschriftung.
	 */
	public static function value_groups() {
		$groups = array(
			'company' => __( 'Firma', 'unternehmensdaten' ),
			'contact' => __( 'Kontakt', 'unternehmensdaten' ),
		);

		if ( UNDT_Store::profile_value( 'is_regulated' ) || UNDT_Store::profile_value( 'needs_authority' ) || UNDT_Store::profile_value( 'has_liability_ins' ) ) {
			$groups['profession'] = __( 'Beruf', 'unternehmensdaten' );
		}

		if ( UNDT_Modules::is_active( 'hours' ) ) {
			$groups['hours'] = __( 'Öffnungszeiten', 'unternehmensdaten' );
		}

		if ( UNDT_Modules::is_active( 'banner' ) ) {
			$groups['banner'] = __( 'Infobanner', 'unternehmensdaten' );
		}

		return $groups;
	}

	/**
	 * Die einzelnen Angaben fuer eigene Layouts.
	 *
	 * @return array Liste aus array( key, label, group, value ).
	 */
	public static function values() {
		$list = array();

		foreach ( UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER ) as $key => $label ) {
			$list[] = array(
				'key'   => $key,
				'label' => $label,
				'group' => self::group_of( $key ),
				'value' => self::display_value( $key ),
			);
		}

		return $list;
	}

	/**
	 * Die Gruppe einer Angabe.
	 *
	 * @param string $key Schluessel.
	 * @return string
	 */
	private static function group_of( $key ) {
		if ( 0 === strpos( $key, 'banner_' ) ) {
			return 'banner';
		}

		if ( in_array( $key, array( 'hours_today', 'open_now', 'is_open' ), true ) ) {
			return 'hours';
		}

		$base  = '_link' === substr( $key, -5 ) ? substr( $key, 0, -5 ) : $key;
		$field = UNDT_Schema::field( $base );

		if ( null === $field ) {
			return 'company';
		}

		if ( 'contact' === $field['section'] ) {
			return 'contact';
		}

		if ( in_array( $field['section'], array( 'profession', 'chamber', 'authority', 'insurance' ), true ) ) {
			return 'profession';
		}

		return 'company';
	}

	/**
	 * Der Wert einer Angabe in einer Zeile, fuer die Liste.
	 *
	 * @param string $key Schluessel.
	 * @return string
	 */
	private static function display_value( $key ) {
		$value = UNDT_Dynamic::value( $key );

		if ( is_bool( $value ) ) {
			return $value ? __( 'ja', 'unternehmensdaten' ) : __( 'nein', 'unternehmensdaten' );
		}

		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}

		$value = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $value ) ) );

		return $value;
	}
}
