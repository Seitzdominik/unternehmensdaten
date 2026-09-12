<?php
/**
 * Das Backend.
 *
 * Eigenes Menue auf oberster Ebene, ein Untermenue je Aufgabe. Assets werden
 * ausschliesslich auf den eigenen Seiten geladen, nicht im gesamten Backend.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Admin
 */
final class UNDT_Admin {

	const SLUG_COMPANY    = 'undt';
	const SLUG_PROFILE    = 'undt-profile';
	const SLUG_SHORTCODES = 'undt-shortcodes';
	const SLUG_AUDIT      = 'undt-audit';
	const SLUG_SETTINGS   = 'undt-settings';

	const GROUP_PROFILE  = 'undt_profile_group';
	const GROUP_COMPANY  = 'undt_company_group';
	const GROUP_SETTINGS = 'undt_settings_group';

	/**
	 * Instanz.
	 *
	 * @var UNDT_Admin|null
	 */
	private static $instance = null;

	/**
	 * Hook-Suffixe der eigenen Seiten.
	 *
	 * @var array
	 */
	private $hooks = array();

	/**
	 * Hook-Suffixe, auf denen die Medienauswahl gebraucht wird.
	 *
	 * @var array
	 */
	private $media_hooks = array();

	/**
	 * Singleton.
	 *
	 * @return UNDT_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Der Menueschluessel eines Moduls.
	 *
	 * @param string $slug Modul-Schluessel.
	 * @return string
	 */
	public static function module_slug( $slug ) {
		return 'undt-m-' . $slug;
	}

	/**
	 * Die Optionsgruppe eines Moduls.
	 *
	 * @param string $slug Modul-Schluessel.
	 * @return string
	 */
	public static function module_group( $slug ) {
		return 'undt_m_' . $slug . '_group';
	}

	/**
	 * Haengt sich in WordPress ein.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UNDT_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Legt Menue und Untermenues an.
	 *
	 * @return void
	 */
	public function menu() {
		$cap = UNDT_Store::capability();

		$this->hooks['company'] = add_menu_page(
			__( 'Unternehmensdaten', 'unternehmensdaten' ),
			__( 'Unternehmensdaten', 'unternehmensdaten' ),
			$cap,
			self::SLUG_COMPANY,
			array( $this, 'render_company' ),
			'dashicons-building',
			25
		);

		add_submenu_page(
			self::SLUG_COMPANY,
			__( 'Stammdaten', 'unternehmensdaten' ),
			__( 'Stammdaten', 'unternehmensdaten' ),
			$cap,
			self::SLUG_COMPANY,
			array( $this, 'render_company' )
		);

		$this->hooks['profile'] = add_submenu_page(
			self::SLUG_COMPANY,
			__( 'Rechtsform & Umfang', 'unternehmensdaten' ),
			__( 'Rechtsform & Umfang', 'unternehmensdaten' ),
			$cap,
			self::SLUG_PROFILE,
			array( $this, 'render_profile' )
		);

		// Ein Untermenue je aktivem Inhaltsmodul.
		foreach ( UNDT_Modules::all() as $slug => $module ) {
			if ( ! UNDT_Modules::is_active( $slug ) ) {
				continue;
			}

			$hook = add_submenu_page(
				self::SLUG_COMPANY,
				$module['label'],
				$module['label'],
				$cap,
				self::module_slug( $slug ),
				function () use ( $slug ) {
					$this->render_module( $slug );
				}
			);

			$this->hooks[ 'm_' . $slug ] = $hook;

			if ( $this->has_media_field( $module ) ) {
				$this->media_hooks[] = $hook;
			}
		}

		$this->hooks['shortcodes'] = add_submenu_page(
			self::SLUG_COMPANY,
			__( 'Shortcodes', 'unternehmensdaten' ),
			__( 'Shortcodes', 'unternehmensdaten' ),
			$cap,
			self::SLUG_SHORTCODES,
			array( $this, 'render_shortcodes' )
		);

		// Nur die abfragefreie Zaehlung, damit das Menue keine Datenbanklast erzeugt.
		$open  = UNDT_Store::is_set_up() ? UNDT_Audit::quick_count() : 0;
		$title = __( 'Prüfung', 'unternehmensdaten' );

		if ( $open > 0 ) {
			$title .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $open . '</span></span>';
		}

		$this->hooks['audit'] = add_submenu_page(
			self::SLUG_COMPANY,
			__( 'Prüfung', 'unternehmensdaten' ),
			$title,
			$cap,
			self::SLUG_AUDIT,
			array( $this, 'render_audit' )
		);

		$this->hooks['settings'] = add_submenu_page(
			self::SLUG_COMPANY,
			__( 'Einstellungen', 'unternehmensdaten' ),
			__( 'Einstellungen', 'unternehmensdaten' ),
			$cap,
			self::SLUG_SETTINGS,
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Ob ein Modul ein Medienfeld enthaelt.
	 *
	 * @param array $module Moduldefinition.
	 * @return bool
	 */
	private function has_media_field( array $module ) {
		foreach ( $module['fields'] as $field ) {
			if ( 'media' === $field['type'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Registriert alle Optionen ueber die Settings API.
	 *
	 * Dadurch laufen Nonce-Pruefung und Berechtigungspruefung ueber options.php,
	 * es gibt keinen eigenen Speicher-Handler und damit auch keine eigene
	 * Angriffsflaeche.
	 *
	 * @return void
	 */
	public function settings() {
		register_setting(
			self::GROUP_PROFILE,
			UNDT_Store::OPTION_PROFILE,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'UNDT_Sanitizer', 'profile' ),
				'default'           => UNDT_Schema::profile_defaults(),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::GROUP_COMPANY,
			UNDT_Store::OPTION_COMPANY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'UNDT_Sanitizer', 'company' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::GROUP_SETTINGS,
			UNDT_Modules::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'UNDT_Modules', 'sanitize_settings' ),
				'default'           => UNDT_Modules::settings_defaults(),
				'show_in_rest'      => false,
			)
		);

		add_filter( 'option_page_capability_' . self::GROUP_PROFILE, array( 'UNDT_Store', 'capability' ) );
		add_filter( 'option_page_capability_' . self::GROUP_COMPANY, array( 'UNDT_Store', 'capability' ) );
		add_filter( 'option_page_capability_' . self::GROUP_SETTINGS, array( 'UNDT_Store', 'capability' ) );

		// Je Modul eine eigene Option in einer eigenen Gruppe.
		foreach ( UNDT_Modules::all() as $slug => $module ) {
			$group = self::module_group( $slug );

			register_setting(
				$group,
				$module['option'],
				array(
					'type'              => 'array',
					'sanitize_callback' => static function ( $input ) use ( $slug ) {
						return UNDT_Content::sanitize( $slug, $input );
					},
					'default'           => array(),
					'show_in_rest'      => false,
				)
			);

			add_filter( 'option_page_capability_' . $group, array( 'UNDT_Store', 'capability' ) );
		}
	}

	/**
	 * Laedt Skript und Stylesheet, aber nur auf den eigenen Seiten.
	 *
	 * @param string $hook Hook-Suffix der aktuellen Seite.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( ! in_array( $hook, $this->hooks, true ) ) {
			return;
		}

		// Die Medienauswahl ist umfangreich und wird nur dort geladen, wo sie
		// tatsaechlich gebraucht wird.
		if ( in_array( $hook, $this->media_hooks, true ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_style( 'undt-admin', UNDT_URL . 'admin/assets/admin.css', array(), self::asset_version( 'admin/assets/admin.css' ) );
		wp_enqueue_script( 'undt-admin', UNDT_URL . 'admin/assets/admin.js', array(), self::asset_version( 'admin/assets/admin.js' ), true );

		wp_localize_script(
			'undt-admin',
			'undtL10n',
			array(
				'copied'      => __( 'Kopiert', 'unternehmensdaten' ),
				'failed'      => __( 'Kopieren fehlgeschlagen', 'unternehmensdaten' ),
				'mediaTitle'  => __( 'Bild auswählen', 'unternehmensdaten' ),
				'mediaButton' => __( 'Übernehmen', 'unternehmensdaten' ),
				'confirmRow'  => __( 'Diesen Eintrag entfernen?', 'unternehmensdaten' ),
				'rowAdded'    => __( 'Eintrag hinzugefügt', 'unternehmensdaten' ),
				'rowRemoved'  => __( 'Eintrag entfernt', 'unternehmensdaten' ),
				'rowMoved'    => __( 'Reihenfolge geändert', 'unternehmensdaten' ),
			)
		);
	}

	/**
	 * Versionskennung einer Asset-Datei.
	 *
	 * Haengt die Aenderungszeit der Datei an die Plugin-Version. Ohne sie liefern
	 * Browser und Server-Caches nach einer Aenderung an CSS oder JavaScript so
	 * lange die alte Datei aus, wie die Plugin-Version gleich bleibt. Das ergibt
	 * ein Backend, in dem das Markup schon neu und die Gestaltung noch alt ist.
	 *
	 * Kostet einen stat-Aufruf, und das ausschliesslich auf den eigenen Seiten.
	 *
	 * @param string $relative Pfad unterhalb des Plugin-Ordners.
	 * @return string
	 */
	private static function asset_version( $relative ) {
		$path = UNDT_DIR . $relative;
		$time = file_exists( $path ) ? filemtime( $path ) : 0;

		return $time ? UNDT_VERSION . '.' . $time : UNDT_VERSION;
	}

	/**
	 * Weist einmalig auf den Einrichtungsassistenten hin.
	 *
	 * @return void
	 */
	public function setup_notice() {
		if ( UNDT_Store::is_set_up() || ! current_user_can( UNDT_Store::capability() ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( $screen instanceof WP_Screen && false !== strpos( $screen->id, self::SLUG_PROFILE ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p><strong>%s</strong> %s <a href="%s" class="button button-primary button-small">%s</a></p></div>',
			esc_html__( 'Unternehmensdaten:', 'unternehmensdaten' ),
			esc_html__( 'Bitte zuerst Rechtsform und Umfang festlegen. Davon hängt ab, welche Angaben rechtlich erforderlich sind.', 'unternehmensdaten' ),
			esc_url( admin_url( 'admin.php?page=' . self::SLUG_PROFILE ) ),
			esc_html__( 'Jetzt einrichten', 'unternehmensdaten' )
		);
	}

	/**
	 * Zusaetzlicher Link in der Plugin-Liste.
	 *
	 * @param array $links Bestehende Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::SLUG_COMPANY ) ),
				esc_html__( 'Einstellungen', 'unternehmensdaten' )
			)
		);

		return $links;
	}

	/**
	 * Prueft die Berechtigung und bricht sonst ab.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! current_user_can( UNDT_Store::capability() ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für diese Seite.', 'unternehmensdaten' ), 403 );
		}
	}

	/**
	 * Seite: Stammdaten.
	 *
	 * @return void
	 */
	public function render_company() {
		$this->guard();

		require UNDT_DIR . 'admin/views/company.php';
	}

	/**
	 * Seite: Rechtsform und Umfang.
	 *
	 * @return void
	 */
	public function render_profile() {
		$this->guard();

		require UNDT_DIR . 'admin/views/profile.php';
	}

	/**
	 * Seite: ein Inhaltsmodul.
	 *
	 * @param string $undt_slug Modul-Schluessel.
	 * @return void
	 */
	public function render_module( $undt_slug ) {
		$this->guard();

		require UNDT_DIR . 'admin/views/module.php';
	}

	/**
	 * Seite: Shortcodes.
	 *
	 * @return void
	 */
	public function render_shortcodes() {
		$this->guard();

		require UNDT_DIR . 'admin/views/shortcodes.php';
	}

	/**
	 * Seite: Prüfung.
	 *
	 * @return void
	 */
	public function render_audit() {
		$this->guard();

		require UNDT_DIR . 'admin/views/audit.php';
	}

	/**
	 * Seite: Einstellungen.
	 *
	 * @return void
	 */
	public function render_settings() {
		$this->guard();

		require UNDT_DIR . 'admin/views/settings.php';
	}
}
