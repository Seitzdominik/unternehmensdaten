<?php
/**
 * Das Backend.
 *
 * Ein Menuepunkt „Firmendaten“ auf oberster Ebene, alle weiteren Seiten ohne
 * eigenen Eintrag im WordPress-Menue: gefuehrt wird ueber die Seitenleiste des
 * Plugins, siehe UNDT_Nav. Assets werden ausschliesslich auf den eigenen Seiten
 * geladen, nicht im gesamten Backend.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Admin
 */
final class UNDT_Admin {

	const SLUG_OVERVIEW = 'undt';
	const SLUG_COMPANY  = 'undt-company';
	const SLUG_EMBED    = 'undt-embed';
	const SLUG_TRANSFER = 'undt-transfer';
	const SLUG_SETTINGS = 'undt-settings';

	/**
	 * Adressen frueherer Fassungen und wohin sie heute fuehren.
	 */
	const OLD_SLUGS = array(
		'undt-shortcodes' => self::SLUG_EMBED,
		'undt-audit'      => self::SLUG_OVERVIEW,
		'undt-profile'    => self::SLUG_COMPANY,
	);

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
	 * Hook-Suffixe der eigenen Seiten, Schluessel ist der Name der Seite.
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
		add_action( 'admin_page_access_denied', array( $this, 'redirect_old_slugs' ) );
		add_action( 'admin_head', array( $this, 'hide_submenu' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UNDT_FILE ), array( $this, 'action_links' ) );

		UNDT_Autosave::register();
	}

	/**
	 * Legt den Menuepunkt und die Seiten an.
	 *
	 * Die Uebersicht ist der Menuepunkt selbst, alle anderen Seiten haengen als
	 * gewoehnliche Unterseiten darunter. So bestimmt WordPress Titel, Rechte und
	 * Hervorhebung wie immer. Im Menue erscheinen sie trotzdem nicht, siehe
	 * hide_submenu().
	 *
	 * @return void
	 */
	public function menu() {
		foreach ( UNDT_Nav::pages() as $key => $page ) {
			if ( '' !== $page['url'] ) {
				continue;
			}

			$cap      = UNDT_Access::cap_for( $page['access'] );
			$callback = function () use ( $key ) {
				$this->render( $key );
			};

			if ( 'overview' === $key ) {
				$hook = add_menu_page(
					$page['title'],
					__( 'Firmendaten', 'unternehmensdaten' ),
					$cap,
					$page['slug'],
					$callback,
					UNDT_Ui_Icons::data_uri( 'buildings' ),
					25
				);
			} else {
				$hook = add_submenu_page( self::SLUG_OVERVIEW, $page['title'], $page['title'], $cap, $page['slug'], $callback );
			}

			if ( ! $hook ) {
				continue;
			}

			$this->hooks[ $key ] = $hook;

			if ( '' !== $page['module'] && $this->has_media_field( UNDT_Modules::get( $page['module'] ) ) ) {
				$this->media_hooks[] = $hook;
			}
		}
	}

	/**
	 * Nimmt die Unterseiten aus dem WordPress-Menue, kurz bevor es ausgegeben wird.
	 *
	 * Zu diesem Zeitpunkt hat WordPress Recht, Titel und Elternseite bereits
	 * bestimmt. „Firmendaten“ steht danach ohne aufklappende Liste im Menue und
	 * ist auf jeder eigenen Seite hervorgehoben. Ein Elternteil leer zu lassen
	 * genuegt dafuer nicht: dann setzt WordPress die Elternseite nach dem Filter
	 * parent_file wieder zurueck, und Titel und Hervorhebung fehlen.
	 *
	 * @return void
	 */
	public function hide_submenu() {
		global $submenu;

		unset( $submenu[ self::SLUG_OVERVIEW ] );
	}

	/**
	 * Markiert die eigenen Seiten fuer das Stylesheet.
	 *
	 * @param string $classes Klassen des body-Elements.
	 * @return string
	 */
	public function body_class( $classes ) {
		return $this->current_key() ? $classes . ' undt-screen' : $classes;
	}

	/**
	 * Der Name der aktuellen Seite, leer ausserhalb des Plugins.
	 *
	 * @return string
	 */
	private function current_key() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen instanceof WP_Screen ) {
			return '';
		}

		$key = array_search( $screen->id, $this->hooks, true );

		return false === $key ? '' : (string) $key;
	}

	/**
	 * Leitet Adressen frueherer Fassungen um, etwa aus Lesezeichen.
	 *
	 * WordPress lehnt eine unbekannte Seite ab, bevor ein spaeterer Hook greift.
	 * Erst in diesem Moment ist klar, dass es die Seite nicht mehr gibt.
	 *
	 * @return void
	 */
	public function redirect_old_slugs() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur gelesen, um weiterzuleiten.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! isset( self::OLD_SLUGS[ $page ] ) ) {
			return;
		}

		// Die Rechtsform steht seit 0.6.0 als Karte oben auf „Firma“.
		$anchor = 'undt-profile' === $page ? '#rechtsform' : '';

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::OLD_SLUGS[ $page ] ) . $anchor );
		exit;
	}

	/**
	 * Ob ein Modul ein Medienfeld enthaelt.
	 *
	 * @param array|null $module Moduldefinition.
	 * @return bool
	 */
	private function has_media_field( $module ) {
		if ( ! is_array( $module ) ) {
			return false;
		}

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
	 * Die Sanitizer greifen damit bei jedem update_option(), ob ueber das
	 * automatische Speichern (UNDT_Autosave) oder ueber options.php, das ohne
	 * JavaScript einspringt. Dort laufen Nonce- und Rechtepruefung ueber die
	 * Filter option_page_capability_*.
	 *
	 * @return void
	 */
	public function settings() {
		$admin = array( 'UNDT_Store', 'capability' );
		$edit  = static function () {
			return UNDT_Access::CAP;
		};

		register_setting(
			self::GROUP_PROFILE,
			UNDT_Store::OPTION_PROFILE,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_profile' ),
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

		add_filter( 'option_page_capability_' . self::GROUP_PROFILE, $admin );
		add_filter( 'option_page_capability_' . self::GROUP_COMPANY, $edit );
		add_filter( 'option_page_capability_' . self::GROUP_SETTINGS, $admin );

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

			add_filter( 'option_page_capability_' . $group, 'edit' === UNDT_Nav::module_access( $slug ) ? $edit : $admin );
		}
	}

	/**
	 * Sanitisiert das Profil und haelt die Einrichtung fest.
	 *
	 * Als abgeschlossen gilt die Einrichtung erst mit dem Speichern, nicht schon
	 * mit dem Aufruf der Seite: eine GET-Anfrage soll nichts veraendern, und hier
	 * ist die Nonce bereits geprueft.
	 *
	 * @param mixed $input Rohe Eingabe.
	 * @return array
	 */
	public static function sanitize_profile( $input ) {
		if ( ! UNDT_Store::is_set_up() ) {
			UNDT_Store::mark_set_up();
			UNDT_Overview::start_onboarding();
		}

		return UNDT_Sanitizer::profile( $input );
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

		/*
		 * Seit WordPress 7 sind Eingabefelder 40 statt 30 Pixel hoch. Die Knoepfe
		 * der Wiederholungsfelder ziehen mit, damit sie buendig neben den Feldern
		 * stehen.
		 */
		if ( version_compare( (string) get_bloginfo( 'version' ), '7.0-alpha', '>=' ) ) {
			wp_add_inline_style( 'undt-admin', '.undt-wrap{--undt-control-size:40px}' );
		}

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
				'hoursCopied' => __( 'Zeiten auf alle Tage übernommen', 'unternehmensdaten' ),
				'saved'       => __( 'Alle Änderungen gespeichert', 'unternehmensdaten' ),
				'saveFailed'  => __( 'Nicht gespeichert. Neuer Versuch in 10 Sekunden.', 'unternehmensdaten' ),
				'openItems'   => __( 'offene Punkte', 'unternehmensdaten' ),
				'copiedPrefix' => __( 'Kopiert:', 'unternehmensdaten' ),
				/* translators: %d: Anzahl der Feiertage. */
				'holidaysAdded' => __( '%d Feiertage eingetragen', 'unternehmensdaten' ),
				/* translators: %s: Name der Plattform. */
				'labelFor'    => __( 'Beschriftung für %s', 'unternehmensdaten' ),
				'confirmGroup' => __( 'Diese Gruppe mit allen Leistungen entfernen?', 'unternehmensdaten' ),
				/* translators: %s: Adresse der Website, von der die Sicherung stammt. */
				'importFrom'  => __( 'von %s', 'unternehmensdaten' ),
				'importReady' => __( 'Datei gelesen. Wähle jetzt, was übernommen wird.', 'unternehmensdaten' ),
				'chamberApplied' => __( 'Kammer und Regelungen übernommen', 'unternehmensdaten' ),
				'format'      => UNDT_Access::is_admin() ? UNDT_Copy::format() : 'shortcode',
				'save'        => array(
					'url'    => admin_url( 'admin-ajax.php' ),
					'action' => UNDT_Autosave::ACTION,
					'nonce'  => wp_create_nonce( UNDT_Autosave::ACTION ),
				),
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
	 * Kostet einen stat-Aufruf, und das nur dort, wo die Datei auch geladen
	 * wird: auf den eigenen Seiten und im Block-Editor.
	 *
	 * @param string $relative Pfad unterhalb des Plugin-Ordners.
	 * @return string
	 */
	public static function asset_version( $relative ) {
		$path = UNDT_DIR . $relative;
		$time = file_exists( $path ) ? filemtime( $path ) : 0;

		return $time ? UNDT_VERSION . '.' . $time : UNDT_VERSION;
	}

	/**
	 * Weist einmalig auf die Einrichtung hin, ausserhalb der eigenen Seiten.
	 *
	 * @return void
	 */
	public function setup_notice() {
		if ( UNDT_Store::is_set_up() || ! UNDT_Access::is_admin() || $this->current_key() ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p><strong>%s</strong> %s <a href="%s" class="button button-primary button-small">%s</a></p></div>',
			esc_html__( 'Firmendaten:', 'unternehmensdaten' ),
			esc_html__( 'Lege zuerst die Rechtsform fest. Davon hängt ab, welche Angaben rechtlich erforderlich sind.', 'unternehmensdaten' ),
			esc_url( admin_url( 'admin.php?page=' . self::SLUG_COMPANY ) . '#rechtsform' ),
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
				esc_url( admin_url( 'admin.php?page=' . self::SLUG_SETTINGS ) ),
				esc_html__( 'Einstellungen', 'unternehmensdaten' )
			)
		);

		return $links;
	}

	/**
	 * Gibt eine Seite in der Huelle aus.
	 *
	 * WordPress hat das Recht bereits beim Aufruf geprueft. Die Pruefung hier
	 * steht trotzdem, falls eine Ansicht einmal auf anderem Weg erreicht wird.
	 *
	 * @param string $key Name der Seite.
	 * @return void
	 */
	public function render( $key ) {
		$pages = UNDT_Nav::pages();
		$page  = isset( $pages[ $key ] ) ? $pages[ $key ] : null;

		if ( null === $page || ! current_user_can( UNDT_Access::cap_for( $page['access'] ) ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für diese Seite.', 'unternehmensdaten' ), 403 );
		}

		// Die Ansicht eines Moduls erwartet dessen Schluessel.
		$undt_slug = $page['module'];

		UNDT_Nav::open( $key );
		require UNDT_DIR . 'admin/views/' . $page['view'] . '.php';
		UNDT_Nav::close( $key );
	}
}
