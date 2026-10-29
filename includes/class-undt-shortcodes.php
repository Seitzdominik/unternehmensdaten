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

		// Schliessen auch fuer ein in Bricks oder Etch nachgebautes Banner.
		if ( UNDT_Blocks::banner_active() && UNDT_Content::value( 'banner', 'dismissible' ) ) {
			add_action( 'wp_head', array( 'UNDT_Blocks', 'banner_head' ) );
			add_action( 'wp_footer', array( 'UNDT_Blocks', 'banner_footer' ) );
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
		if ( wp_style_is( 'undt', 'registered' ) ) {
			return;
		}

		wp_register_style( 'undt', false, array(), UNDT_VERSION );
		wp_add_inline_style( 'undt', UNDT_Render::css() . UNDT_Blocks::css() );
	}

	/**
	 * Fordert das Stylesheet an.
	 *
	 * Block-Themes und Etch bauen die Seitenvorlage auf, bevor
	 * wp_enqueue_scripts laeuft. Das Stylesheet wird deshalb bei Bedarf sofort
	 * registriert. Sonst fehlte das CSS genau dort, wo Shortcodes in Vorlagen
	 * stehen, und etwa der Hinweis fuer Screenreader wurde sichtbar.
	 *
	 * Oeffentlich, weil die Vorschau des Infobanners im Backend ohne Shortcode
	 * auskommt, siehe UNDT_Module_View::banner().
	 *
	 * @return void
	 */
	public static function need_style() {
		self::register_style();
		wp_enqueue_style( 'undt' );
	}

	/**
	 * Einzelfeld-Shortcode.
	 *
	 * [undt key="phone"]
	 * [undt key="phone" link="1" before="Telefon: "]
	 * [undt key="email" obfuscate="1"]
	 * [undt key="maps_google" link="1" text="Route planen"]
	 *
	 * before und after werden nur ausgegeben, wenn das Feld auch einen Wert hat.
	 * So entsteht bei einem leeren Feld kein verwaistes "Telefon: ". text ersetzt
	 * bei Links den sichtbaren Wert.
	 *
	 * @param array|string $atts Attribute.
	 * @return string
	 */
	public static function field( $atts ) {
		$atts = shortcode_atts(
			array(
				'key'       => '',
				'link'      => '0',
				'text'      => '',
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
		$label    = trim( (string) $atts['text'] );

		if ( '' === trim( $value ) ) {
			return $fallback;
		}

		switch ( $field['type'] ) {
			case 'email':
				$html = UNDT_Render::email_link( $value, $link, $obf, $label );
				break;

			case 'tel':
				$html = $link ? UNDT_Render::tel_link( $value, $label ) : esc_html( $value );
				break;

			case 'url':
				$html = $link
					? '<a href="' . esc_url( $value ) . '">' . esc_html( '' === $label ? $value : $label ) . '</a>'
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
					$html = '<a href="' . esc_url( $target['url'] ) . '">' . esc_html( '' === $label ? $text : $label ) . '</a>';
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
			/*
			 * sanitize_text_field schneidet Leerzeichen am Rand ab. Die gehoeren
			 * hier aber zum Trennzeichen, sonst wird aus „ · “ ein „·“ ohne Abstand.
			 */
			$raw       = (string) $atts['separator'];
			$separator = sanitize_text_field( $raw );

			if ( '' !== $separator ) {
				$separator = ( ' ' === substr( $raw, 0, 1 ) ? ' ' : '' ) . $separator . ( ' ' === substr( $raw, -1 ) ? ' ' : '' );
			}

			$out = UNDT_Render::address_inline( $separator );

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

		$atts = shortcode_atts(
			array(
				'label'  => '',
				'layout' => '',
			),
			$atts,
			'undt_social'
		);

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
		/**
		 * Ob das Banner automatisch am Seitenanfang erscheint.
		 *
		 * @param bool $show Standard: ueberall ausser in der Oberflaeche eines Page Builders.
		 */
		if ( ! apply_filters( 'undt_auto_banner', ! self::in_builder() ) ) {
			return;
		}

		self::need_style();

		echo UNDT_Blocks::banner(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- im Renderer escaped.
	}

	/**
	 * Ob die Seite in der Oberflaeche von Etch oder Bricks laeuft.
	 *
	 * @return bool
	 */
	public static function in_builder() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur gelesen, genau so erkennt Etch seinen Builder.
		$etch = isset( $_GET['etch'] ) && 'magic' === sanitize_key( wp_unslash( $_GET['etch'] ) );

		return $etch || ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() );
	}
}
