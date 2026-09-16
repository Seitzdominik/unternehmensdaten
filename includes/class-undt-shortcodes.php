<?php
/**
 * Shortcodes.
 *
 * Sicherheitskern: das Attribut key wird ausschliesslich gegen das Feld-Register
 * aufgeloest, niemals als freier Pfad in die Optionen verwendet. Ein Redakteur
 * kann damit nur genau die Felder ausgeben, die das Register kennt, und keine
 * fremden Optionen auslesen.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Shortcodes
 */
final class UNDT_Shortcodes {

	/**
	 * Registriert alle Shortcodes und das zugehoerige Stylesheet.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'undt', array( __CLASS__, 'field' ) );
		add_shortcode( 'undt_impressum', array( __CLASS__, 'imprint' ) );
		add_shortcode( 'undt_footer', array( __CLASS__, 'footer' ) );
		add_shortcode( 'undt_legal_nav', array( __CLASS__, 'legal_nav' ) );
		add_shortcode( 'undt_address', array( __CLASS__, 'address' ) );
		add_shortcode( 'undt_privacy_block', array( __CLASS__, 'privacy_block' ) );

		// Inhaltsmodule. Bewusst immer registriert: ein abgeschaltetes Modul soll
		// nichts ausgeben, nicht den rohen Shortcode im Text stehen lassen.
		add_shortcode( 'undt_hours', array( __CLASS__, 'hours' ) );
		add_shortcode( 'undt_hours_today', array( __CLASS__, 'hours_today' ) );
		add_shortcode( 'undt_open_now', array( __CLASS__, 'open_now' ) );
		add_shortcode( 'undt_prices', array( __CLASS__, 'prices' ) );
		add_shortcode( 'undt_social', array( __CLASS__, 'social' ) );
		add_shortcode( 'undt_faq', array( __CLASS__, 'faq' ) );
		add_shortcode( 'undt_banner', array( __CLASS__, 'banner' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_style' ) );

		if ( UNDT_Blocks::banner_active() && UNDT_Content::value( 'banner', 'auto_output' ) ) {
			add_action( 'wp_body_open', array( __CLASS__, 'auto_banner' ) );
		}

		UNDT_SchemaOrg::register();
	}

	/**
	 * Registriert ein Stylesheet ohne Datei.
	 *
	 * Das CSS haengt als Inline-Style an einem leeren Handle. Es wird erst
	 * ausgegeben, wenn ein Block-Shortcode es tatsaechlich anfordert, und kostet
	 * dann keinen zusaetzlichen HTTP-Request.
	 *
	 * @return void
	 */
	public static function register_style() {
		wp_register_style( 'undt', false, array(), UNDT_VERSION );
		wp_add_inline_style( 'undt', UNDT_Render::css() . UNDT_Blocks::css() );
	}

	/**
	 * Fordert das Stylesheet an.
	 *
	 * @return void
	 */
	private static function need_style() {
		if ( wp_style_is( 'undt', 'registered' ) ) {
			wp_enqueue_style( 'undt' );
		}
	}

	/**
	 * Einzelfeld-Shortcode.
	 *
	 * [undt key="phone"]
	 * [undt key="phone" link="1" before="Telefon: "]
	 * [undt key="email" obfuscate="1"]
	 *
	 * before und after werden nur ausgegeben, wenn das Feld auch einen Wert hat.
	 * So entsteht bei einem leeren Feld kein verwaistes "Telefon: ".
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function field( $atts ) {
		$atts = shortcode_atts(
			array(
				'key'       => '',
				'link'      => '0',
				'obfuscate' => '0',
				'before'    => '',
				'after'     => '',
				'fallback'  => '',
			),
			$atts,
			'undt'
		);

		$key   = sanitize_key( $atts['key'] );
		$field = UNDT_Schema::field( $key );

		// Unbekannter Schluessel: nichts ausgeben, nichts verraten.
		if ( null === $field || empty( $field['shortcode'] ) ) {
			return '';
		}

		$value    = UNDT_Store::get( $key );
		$link     = ! empty( $atts['link'] ) && '0' !== $atts['link'];
		$obf      = ! empty( $atts['obfuscate'] ) && '0' !== $atts['obfuscate'];
		$fallback = '' === $atts['fallback'] ? '' : esc_html( $atts['fallback'] );

		if ( '' === trim( $value ) ) {
			return $fallback;
		}

		switch ( $field['type'] ) {
			case 'email':
				$html = UNDT_Render::email_link( $value, $link, $obf );
				break;

			case 'tel':
				$html = $link ? UNDT_Render::tel_link( $value ) : esc_html( $value );
				break;

			case 'url':
				$html = $link
					? '<a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>'
					: esc_html( $value );
				break;

			case 'page':
				// Wie im Footer nur veroeffentlichte Beitraege, siehe UNDT_Store::link().
				// Eine eigene Adresse hat keinen Titel und steht deshalb selbst da.
				$target = UNDT_Store::link( $key );
				$text   = null === $target ? '' : ( '' === $target['title'] ? $target['url'] : $target['title'] );

				if ( '' === $text ) {
					$html = '';
				} elseif ( $link ) {
					$html = '<a href="' . esc_url( $target['url'] ) . '">' . esc_html( $text ) . '</a>';
				} else {
					$html = esc_html( $text );
				}
				break;

			case 'list':
			case 'textarea':
				$html = nl2br( esc_html( $value ) );
				break;

			default:
				$html = esc_html( $value );
		}

		// Ein Wert, der sich nicht ausgeben laesst, zaehlt wie ein leeres Feld.
		if ( '' === $html ) {
			return $fallback;
		}

		return esc_html( $atts['before'] ) . $html . esc_html( $atts['after'] );
	}

	/**
	 * Vollstaendiges Impressum.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function imprint( $atts ) {
		$atts = shortcode_atts( array( 'heading_level' => 2 ), $atts, 'undt_impressum' );

		self::need_style();

		return UNDT_Render::imprint( $atts );
	}

	/**
	 * Footer-Block.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function footer( $atts ) {
		$atts = shortcode_atts( array( 'show' => 'address,legal,copyright' ), $atts, 'undt_footer' );

		self::need_style();

		return UNDT_Render::footer( $atts );
	}

	/**
	 * Nur die Linkliste zu den Rechtsseiten.
	 *
	 * @return string
	 */
	public static function legal_nav() {
		self::need_style();

		return UNDT_Render::legal_nav();
	}

	/**
	 * Anschrift, mehrzeilig oder einzeilig.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function address( $atts ) {
		$atts = shortcode_atts(
			array(
				'inline'    => '0',
				'separator' => ' · ',
				'name'      => '1',
			),
			$atts,
			'undt_address'
		);

		self::need_style();

		if ( ! empty( $atts['inline'] ) && '0' !== $atts['inline'] ) {
			$out = UNDT_Render::address_inline( sanitize_text_field( $atts['separator'] ) );

			return '' === $out ? '' : '<span class="undt-address-inline">' . $out . '</span>';
		}

		$out = UNDT_Render::postal_address( '0' !== $atts['name'] );

		return '' === $out ? '' : '<address class="undt-address">' . $out . '</address>';
	}

	/**
	 * Datenbaustein fuer die Datenschutzerklaerung.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function privacy_block( $atts ) {
		$atts = shortcode_atts(
			array(
				'name'          => 'controller',
				'heading_level' => 3,
				'bare'          => '0',
			),
			$atts,
			'undt_privacy_block'
		);

		$atts['bare'] = ! empty( $atts['bare'] ) && '0' !== $atts['bare'];

		self::need_style();

		return UNDT_Render::privacy_block( $atts );
	}

	/* ------------------------------------------------------ Inhaltsmodule */

	/**
	 * Tabelle der Oeffnungszeiten.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function hours( $atts ) {
		if ( ! UNDT_Modules::is_active( 'hours' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'heading_level' => 3,
				'short'         => '1',
				'group'         => '1',
				'special'       => '1',
				'note'          => '1',
			),
			$atts,
			'undt_hours'
		);

		self::need_style();

		return UNDT_Blocks::hours( $atts );
	}

	/**
	 * Die heutigen Zeiten.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function hours_today( $atts ) {
		if ( ! UNDT_Modules::is_active( 'hours' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'prefix'      => '',
				'closed_text' => '',
			),
			$atts,
			'undt_hours_today'
		);

		self::need_style();

		return UNDT_Blocks::hours_today( $atts );
	}

	/**
	 * Ob gerade geoeffnet ist.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function open_now( $atts ) {
		if ( ! UNDT_Modules::is_active( 'hours' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'open_text'   => '',
				'closed_text' => '',
			),
			$atts,
			'undt_open_now'
		);

		self::need_style();

		return UNDT_Blocks::open_now( $atts );
	}

	/**
	 * Preisliste.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function prices( $atts ) {
		if ( ! UNDT_Modules::is_active( 'prices' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'heading_level' => 3,
				'group'         => '',
				'intro'         => '1',
				'footnote'      => '1',
			),
			$atts,
			'undt_prices'
		);

		self::need_style();

		return UNDT_Blocks::prices( $atts );
	}

	/**
	 * Social-Profile.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function social( $atts ) {
		if ( ! UNDT_Modules::is_active( 'social' ) ) {
			return '';
		}

		$atts = shortcode_atts( array( 'label' => '' ), $atts, 'undt_social' );

		self::need_style();

		return UNDT_Blocks::social( $atts );
	}

	/**
	 * Fragen und Antworten, auf Wunsch mit FAQPage-Auszeichnung.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function faq( $atts ) {
		if ( ! UNDT_Modules::is_active( 'faq' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'heading_level' => 2,
				'group'         => '',
				'style'         => '',
			),
			$atts,
			'undt_faq'
		);

		self::need_style();

		$html = UNDT_Blocks::faq( $atts );

		if ( '' === $html ) {
			return '';
		}

		$groups = UNDT_Content::group_rows(
			UNDT_Content::rows( 'faq', 'items' ),
			(string) $atts['group']
		);

		return $html . UNDT_SchemaOrg::faq_script( $groups );
	}

	/**
	 * Infobanner an der Stelle des Shortcodes.
	 *
	 * @return string
	 */
	public static function banner() {
		if ( ! UNDT_Modules::is_active( 'banner' ) ) {
			return '';
		}

		self::need_style();

		return UNDT_Blocks::banner();
	}

	/**
	 * Gibt das Banner automatisch am Seitenanfang aus.
	 *
	 * Nicht in der Oberflaeche eines Page Builders: Etch baut seinen Builder mit
	 * ?etch=magic auf der Startseite auf, Bricks mit ?bricks=run, und dort saesse
	 * das Banner ueber den Bedienelementen statt ueber der Seite. Ein Shortcode
	 * im Inhalt ist davon nicht betroffen.
	 *
	 * @return void
	 */
	public static function auto_banner() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur gelesen, genau so erkennt Etch seinen Builder.
		$etch    = isset( $_GET['etch'] ) && 'magic' === sanitize_key( wp_unslash( $_GET['etch'] ) );
		$builder = $etch || ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() );

		/**
		 * Ob das Banner automatisch am Seitenanfang erscheint.
		 *
		 * @param bool $show Standard: ueberall ausser in der Oberflaeche eines Page Builders.
		 */
		if ( ! apply_filters( 'undt_auto_banner', ! $builder ) ) {
			return;
		}

		self::need_style();

		echo UNDT_Blocks::banner(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- im Renderer escaped.
	}

	/**
	 * Katalog der Block-Shortcodes fuer die Referenzseite.
	 *
	 * @return array
	 */
	public static function catalog() {
		return array(
			array(
				'code'  => '[undt_impressum]',
				'title' => __( 'Vollständiges Impressum', 'unternehmensdaten' ),
				'desc'  => __( 'Alle Pflichtangaben in der üblichen Reihenfolge, als semantisches HTML mit address-, dl- und Überschriften-Elementen.', 'unternehmensdaten' ),
				'atts'  => __( 'heading_level: Überschriftenebene, 2 bis 6. Standard 2.', 'unternehmensdaten' ),
			),
			array(
				'code'  => '[undt_footer]',
				'title' => __( 'Footer-Block', 'unternehmensdaten' ),
				'desc'  => __( 'Anschrift einzeilig, Navigation zu den Rechtsseiten und Copyright-Zeile mit laufendem Jahr.', 'unternehmensdaten' ),
				'atts'  => __( 'show: Kommaliste aus address, legal, copyright. Standard alle drei.', 'unternehmensdaten' ),
			),
			array(
				'code'  => '[undt_legal_nav]',
				'title' => __( 'Nur die Rechtslinks', 'unternehmensdaten' ),
				'desc'  => __( 'Liste mit Links zu Impressum, Datenschutz, AGB und Barrierefreiheit. Nur veröffentlichte Seiten werden verlinkt.', 'unternehmensdaten' ),
				'atts'  => '',
			),
			array(
				'code'  => '[undt_address]',
				'title' => __( 'Anschrift', 'unternehmensdaten' ),
				'desc'  => __( 'Die ladungsfähige Anschrift als address-Element.', 'unternehmensdaten' ),
				'atts'  => __( 'inline: 1 für einzeilig. separator: Trennzeichen. name: 0 blendet den Firmennamen aus.', 'unternehmensdaten' ),
			),
			array(
				'code'  => '[undt_privacy_block name="controller"]',
				'title' => __( 'Datenschutz: Verantwortlicher', 'unternehmensdaten' ),
				'desc'  => __( 'Der Verantwortliche nach Art. 4 Nr. 7 DSGVO mit Anschrift und Kontaktwegen. Zum Einsetzen in die Datenschutzerklärung.', 'unternehmensdaten' ),
				'atts'  => __( 'name: controller, dpo oder authority. bare: 1 unterdrückt die Überschrift.', 'unternehmensdaten' ),
			),
			array(
				'code'  => '[undt_privacy_block name="dpo"]',
				'title' => __( 'Datenschutz: Datenschutzbeauftragter', 'unternehmensdaten' ),
				'desc'  => __( 'Name, Anschrift und E-Mail des Datenschutzbeauftragten. Gibt nichts aus, solange die Felder leer sind.', 'unternehmensdaten' ),
				'atts'  => __( 'bare: 1 unterdrückt die Überschrift.', 'unternehmensdaten' ),
			),
			array(
				'code'  => '[undt_privacy_block name="authority"]',
				'title' => __( 'Datenschutz: Aufsichtsbehörde', 'unternehmensdaten' ),
				'desc'  => __( 'Die zuständige Datenschutz-Aufsichtsbehörde für den Hinweis auf das Beschwerderecht nach Art. 77 DSGVO.', 'unternehmensdaten' ),
				'atts'  => __( 'bare: 1 unterdrückt die Überschrift.', 'unternehmensdaten' ),
			),
		);
	}

	/**
	 * Katalog der Shortcodes aus den Inhaltsmodulen.
	 *
	 * Aufgefuehrt wird nur, was auch aktiv ist: ein Shortcode fuer ein
	 * abgeschaltetes Modul gibt nichts aus und gehoert deshalb nicht in die
	 * Referenz.
	 *
	 * @return array
	 */
	public static function module_catalog() {
		$catalog = array();

		if ( UNDT_Modules::is_active( 'hours' ) ) {
			$catalog[] = array(
				'code'  => '[undt_hours]',
				'title' => __( 'Öffnungszeiten', 'unternehmensdaten' ),
				'desc'  => __( 'Tabelle der regulären Zeiten. Aufeinanderfolgende Tage mit gleichen Zeiten werden zusammengefasst. Künftige Sonderzeiten hängen darunter, vergangene entfallen automatisch.', 'unternehmensdaten' ),
				'atts'  => __( 'group: 0 zeigt jeden Tag einzeln. short: 0 schreibt die Wochentage aus. special: 0 blendet Sonderzeiten aus. note: 0 blendet den Hinweis aus. heading_level.', 'unternehmensdaten' ),
			);

			$catalog[] = array(
				'code'  => '[undt_hours_today]',
				'title' => __( 'Heutige Öffnungszeit', 'unternehmensdaten' ),
				'desc'  => __( 'Die heute geltende Zeit als kurzer Text. Eine Sonderöffnungszeit für den heutigen Tag hat Vorrang.', 'unternehmensdaten' ),
				'atts'  => __( 'prefix: Text davor, etwa „Heute“. closed_text: eigener Text für geschlossen.', 'unternehmensdaten' ),
			);

			$catalog[] = array(
				'code'  => '[undt_open_now]',
				'title' => __( 'Geöffnet-Status', 'unternehmensdaten' ),
				'desc'  => __( 'Gibt aus, ob gerade geöffnet ist. Wird auf dem Server in der Zeitzone der Website berechnet. Achtung: auf Seiten aus einem Seiten-Cache kann der Status veralten.', 'unternehmensdaten' ),
				'atts'  => __( 'open_text und closed_text für eigene Formulierungen.', 'unternehmensdaten' ),
			);
		}

		if ( UNDT_Modules::is_active( 'prices' ) ) {
			$catalog[] = array(
				'code'  => '[undt_prices]',
				'title' => __( 'Preisliste', 'unternehmensdaten' ),
				'desc'  => __( 'Tabelle aus Bezeichnung und Preis. Positionen mit derselben Gruppe stehen unter einer gemeinsamen Überschrift.', 'unternehmensdaten' ),
				'atts'  => __( 'group: nur diese eine Gruppe ausgeben. intro und footnote: 0 blendet sie aus. heading_level.', 'unternehmensdaten' ),
			);
		}

		if ( UNDT_Modules::is_active( 'social' ) ) {
			$catalog[] = array(
				'code'  => '[undt_social]',
				'title' => __( 'Social-Profile', 'unternehmensdaten' ),
				'desc'  => __( 'Liste der Profile als Navigation. Jeder Link trägt eine eigene Klasse und ein data-platform-Attribut, an die sich Icons per CSS anhängen lassen.', 'unternehmensdaten' ),
				'atts'  => __( 'label: eigene Beschriftung für das aria-label der Navigation.', 'unternehmensdaten' ),
			);
		}

		if ( UNDT_Modules::is_active( 'faq' ) ) {
			$catalog[] = array(
				'code'  => '[undt_faq]',
				'title' => __( 'Fragen und Antworten', 'unternehmensdaten' ),
				'desc'  => __( 'Aufklappbare Fragen über details und summary, ganz ohne JavaScript, oder wahlweise als ausgeschriebene Definitionsliste.', 'unternehmensdaten' ),
				'atts'  => __( 'group: nur diese eine Gruppe. style: details oder dl. heading_level.', 'unternehmensdaten' ),
			);
		}

		if ( UNDT_Modules::is_active( 'banner' ) ) {
			$catalog[] = array(
				'code'  => '[undt_banner]',
				'title' => __( 'Infobanner', 'unternehmensdaten' ),
				'desc'  => __( 'Setzt das Banner an genau dieser Stelle. Nur nötig, wenn die automatische Ausgabe am Seitenanfang abgeschaltet ist oder das Theme wp_body_open nicht unterstützt.', 'unternehmensdaten' ),
				'atts'  => '',
			);
		}

		return $catalog;
	}
}
