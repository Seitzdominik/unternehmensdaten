<?php
/**
 * Prueft das installierte Plugin in WordPress Playground.
 *
 * Wird aus dev/install-test.json heraus aufgerufen, nachdem das Archiv ueber
 * WordPress' eigenen Installer entpackt wurde. Das Ergebnis wird in eine Datei
 * im gemounteten Projektordner geschrieben, weil die Ausgabe eines runPHP-
 * Schritts nicht auf der Konsole landet.
 */

/*
 * Muss vor wp-load.php stehen. Das Plugin verdrahtet seinen Backend-Teil nur,
 * wenn is_admin() zutrifft, und is_admin() liest genau diese Konstante. Ohne sie
 * laeuft der gesamte Menue- und Settings-Code nie an und liesse sich hier auch
 * nicht pruefen.
 */
define( 'WP_ADMIN', true );

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$lines = array();
$ok    = 0;
$fail  = 0;

/**
 * Notiert eine Pruefung.
 *
 * @param bool   $condition Ergebnis.
 * @param string $message   Beschreibung.
 * @return void
 */
function undt_t( $condition, $message ) {
	global $lines, $ok, $fail;

	if ( $condition ) {
		++$ok;
		$lines[] = '  OK   ' . $message;
	} else {
		++$fail;
		$lines[] = '  FAIL ' . $message;
	}
}

/**
 * Beginnt einen Abschnitt.
 *
 * @param string $title Ueberschrift.
 * @return void
 */
function undt_h( $title ) {
	global $lines;

	$lines[] = '';
	$lines[] = '== ' . $title . ' ==';
}

$slug = 'unternehmensdaten';
$file = $slug . '/' . $slug . '.php';
$dir  = WP_PLUGIN_DIR . '/' . $slug;

/* ------------------------------------------------------ Entpacken -------- */

undt_h( 'Installation aus dem Archiv' );

undt_t( file_exists( WP_PLUGIN_DIR . '/' . $file ), 'Hauptdatei liegt unter wp-content/plugins/' . $file );
undt_t( is_dir( $dir . '/includes' ), 'Unterordner includes wurde als Ordner entpackt' );
undt_t( is_dir( $dir . '/admin/views' ), 'Unterordner admin/views wurde als Ordner entpackt' );
undt_t( is_dir( $dir . '/admin/assets' ), 'Unterordner admin/assets wurde als Ordner entpackt' );

$flat = glob( WP_PLUGIN_DIR . '/*\\\\*' );
undt_t( empty( $flat ), 'Keine flachen Dateinamen mit Backslash im Plugin-Verzeichnis' );

$count = iterator_count(
	new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) )
);
undt_t( $count >= 20, 'Alle Dateien entpackt (' . $count . ' gefunden)' );

/* -------------------------------------------------------- Aktivierung ---- */

undt_h( 'Aktivierung' );

$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );

undt_t( 'Unternehmensdaten' === $data['Name'], 'Plugin-Header wird gelesen: ' . $data['Name'] );
// Bewusst ohne feste Zahl: die Pruefung soll bei jedem Versionssprung
// weiterlaufen und nur verlangen, dass Header und Konstante zusammenpassen.
undt_t( (bool) preg_match( '/^\d+\.\d+\.\d+$/', (string) $data['Version'] ), 'Version im Header: ' . $data['Version'] );
undt_t( is_plugin_active( $file ), 'Plugin ist aktiv' );
undt_t( defined( 'UNDT_VERSION' ) && UNDT_VERSION === $data['Version'], 'Konstante UNDT_VERSION stimmt mit dem Header überein' );
undt_t( class_exists( 'UNDT_Schema' ), 'Autoloader findet UNDT_Schema' );
undt_t( class_exists( 'UNDT_Modules' ), 'Autoloader findet UNDT_Modules' );
undt_t( class_exists( 'UNDT_Hours' ), 'Autoloader findet UNDT_Hours' );

/*
 * Seit 0.5.7 stehen die Werkzeug-Anbindungen und die Eingabeelemente in
 * eigenen Klassen. Der Autoloader leitet den Dateinamen aus dem Klassennamen
 * ab, ein Tippfehler faellt deshalb erst beim ersten Zugriff auf.
 */
foreach ( array( 'UNDT_Dynamic_SlimSeo', 'UNDT_Dynamic_Bricks', 'UNDT_Dynamic_Etch', 'UNDT_Dynamic_Gutenberg', 'UNDT_Controls', 'UNDT_Copy', 'UNDT_Transfer', 'UNDT_Cli' ) as $undt_class ) {
	undt_t( class_exists( $undt_class ), 'Autoloader findet ' . $undt_class );
}

/*
 * Seit 0.5.4 ohne eigenen Sprachordner: der Header darf auf keinen zeigen, die
 * Textdomäne bleibt, und WordPress lädt Übersetzungen bei Bedarf selbst aus
 * wp-content/languages/plugins.
 */
undt_t( 'unternehmensdaten' === $data['TextDomain'], 'Textdomäne im Header: ' . $data['TextDomain'] );
undt_t( '' === (string) $data['DomainPath'], 'Kein Domain Path, der auf einen Ordner ohne Dateien zeigt' );
undt_t( ! is_dir( WP_PLUGIN_DIR . '/' . $slug . '/languages' ), 'Das Archiv bringt keinen leeren Sprachordner mit' );

/* ------------------------------------------------------------ Optionen --- */

undt_h( 'Optionen und autoload' );

global $wpdb;

$expected = array(
	'undt_profile'  => 'yes',
	'undt_company'  => 'yes',
	'undt_settings' => 'yes',
	'undt_hours'    => 'yes',
	'undt_social'   => 'yes',
	'undt_banner'   => 'yes',
	'undt_seo'      => 'yes',
	'undt_prices'   => 'no',
	'undt_faq'      => 'no',
);

foreach ( $expected as $option => $want ) {
	$actual = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option ) );

	// WordPress 6.6 kennt zusaetzlich die Werte auto, on und off.
	$is_auto = in_array( (string) $actual, array( 'yes', 'on', 'auto' ), true );
	$want_on = 'yes' === $want;

	undt_t(
		null !== $actual && $is_auto === $want_on,
		sprintf( '%-14s angelegt, autoload=%s (erwartet %s)', $option, var_export( $actual, true ), $want )
	);
}

/* --------------------------------------------------------- Shortcodes ---- */

undt_h( 'Shortcodes' );

global $shortcode_tags;

$codes = array(
	'undt', 'undt_impressum', 'undt_footer', 'undt_legal_nav', 'undt_address',
	'undt_privacy_block', 'undt_hours', 'undt_hours_today', 'undt_open_now',
	'undt_prices', 'undt_social', 'undt_faq', 'undt_banner',
);

foreach ( $codes as $code ) {
	undt_t( isset( $shortcode_tags[ $code ] ), '[' . $code . '] registriert' );
}

/* ------------------------------------------------------------- Ausgabe --- */

undt_h( 'Ausgabe mit echten Daten' );

require '/wordpress/build/dev/seed.php';

$imprint = do_shortcode( '[undt_impressum]' );

/*
 * Block-Themes und Etch rendern die Vorlage vor wp_head und damit vor
 * wp_enqueue_scripts. Genau diese Lage herrscht hier: der Shortcode muss das
 * CSS trotzdem anfordern koennen.
 */
$undt_inline = implode( '', (array) wp_styles()->get_data( 'undt', 'after' ) );
undt_t( 0 === did_action( 'wp_enqueue_scripts' ) && wp_style_is( 'undt', 'enqueued' ), 'Shortcode fordert das CSS auch vor wp_enqueue_scripts an' );
undt_t( 1 === substr_count( $undt_inline, '.undt-block{' ) && false !== strpos( $undt_inline, '.undt-hours__label::after' ), 'CSS hängt genau einmal am Handle, samt Punktlinie der Öffnungszeiten' );
do_action( 'wp_enqueue_scripts' );
undt_t( 1 === substr_count( implode( '', (array) wp_styles()->get_data( 'undt', 'after' ) ), '.undt-block{' ), 'Späteres wp_enqueue_scripts hängt das CSS nicht ein zweites Mal an' );

undt_t( false !== strpos( $imprint, 'Playground GmbH' ), 'Impressum enthält den Firmennamen' );
undt_t( false !== strpos( $imprint, '§ 5 DDG' ), 'Impressum nennt die richtige Rechtsgrundlage' );
undt_t( false !== strpos( $imprint, 'HRB 12345' ), 'Impressum enthält die Registernummer' );
undt_t( false === stripos( $imprint, 'ec.europa.eu' ), 'Kein Verweis auf die OS-Plattform' );

$hours = do_shortcode( '[undt_hours]' );
undt_t( false !== strpos( $hours, '09:00 – 12:30 Uhr' ), 'Öffnungszeiten werden ausgegeben' );
undt_t( false !== strpos( $hours, 'geschlossen' ), 'Geschlossener Tag erscheint' );

$field = do_shortcode( '[undt key="phone" link="1"]' );
undt_t( false !== strpos( $field, 'href="tel:+49301234567"' ), 'Einzelfeld-Shortcode mit Telefonlink' );

undt_t( '' === do_shortcode( '[undt key="admin_email"]' ), 'Unbekannter Feldschlüssel gibt nichts aus' );

$faq = do_shortcode( '[undt_faq]' );
undt_t( false !== strpos( $faq, '<details' ), 'FAQ nutzt details/summary' );

$social = do_shortcode( '[undt_social]' );
undt_t( false !== strpos( $social, 'rel="me"' ), 'Social-Profile tragen rel=me' );
undt_t( false !== strpos( $social, '<ul class="undt-stack-list undt-social__list">' ), 'Social-Profile stehen untereinander' );
undt_t( false !== strpos( do_shortcode( '[undt_social layout="row"]' ), '<ul class="undt-inline-list undt-social__list">' ), 'Mit layout=row stehen sie nebeneinander' );

/* ---------------------------------------------------------- Frontend ----- */

undt_h( 'Frontend-Abruf' );

$response = wp_remote_get( home_url( '/' ), array( 'timeout' => 30 ) );

if ( is_wp_error( $response ) ) {
	undt_t( false, 'Startseite abrufbar: ' . $response->get_error_message() );
} else {
	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	undt_t( 200 === $code, 'Startseite antwortet mit HTTP ' . $code );
	undt_t( false === stripos( $body, 'Fatal error' ), 'Kein Fatal Error auf der Startseite' );
	undt_t( false === stripos( $body, 'Warning:' ), 'Keine PHP-Warnung auf der Startseite' );
	undt_t( false === stripos( $body, 'Notice:' ), 'Kein PHP-Notice auf der Startseite' );
	undt_t( false !== strpos( $body, 'application/ld+json' ), 'JSON-LD steht im Kopfbereich' );
	undt_t( false !== strpos( $body, '"@type":"ProfessionalService"' ), 'JSON-LD nutzt den eingestellten Typ' );
	undt_t( false !== strpos( $body, 'openingHoursSpecification' ), 'Öffnungszeiten stehen im JSON-LD' );
	undt_t( false !== strpos( $body, '"hasMap":"https:' ), 'Kartenlink steht als hasMap im JSON-LD' );
	undt_t( false !== strpos( $body, 'undt-banner' ), 'Infobanner erscheint über wp_body_open' );
}

/* ---------------------------------------------------------- Updater ------ */

undt_h( 'Updater' );

undt_t( class_exists( 'UNDT_Updater' ), 'Autoloader findet UNDT_Updater' );
undt_t( 'Seitzdominik/unternehmensdaten' === UNDT_Updater::repo(), 'Repository aus der Konstante: ' . UNDT_Updater::repo() );
undt_t( UNDT_Updater::enabled(), 'Suche nach Aktualisierungen ist in der Voreinstellung an' );

undt_t( false !== has_filter( 'pre_set_site_transient_update_plugins', array( 'UNDT_Updater', 'inject' ) ), 'Haken für den Aktualisierungs-Transient sitzt' );
undt_t( false !== has_filter( 'plugins_api', array( 'UNDT_Updater', 'details' ) ), 'Haken für das Detailfenster sitzt' );
undt_t( false !== has_filter( 'upgrader_source_selection', array( 'UNDT_Updater', 'fix_folder' ) ), 'Haken für den Ordnernamen sitzt' );
undt_t( false !== has_action( 'admin_post_undt_check_update', array( 'UNDT_Updater', 'handle_manual_check' ) ), 'Aktion für die manuelle Prüfung sitzt' );

/*
 * Das Repository existiert in dieser Umgebung nicht. Der Abruf muss deshalb
 * scheitern, ohne den Transient von WordPress zu beschädigen: sonst brächte ein
 * unerreichbares GitHub den gesamten Aktualisierungsbildschirm zum Erliegen.
 */
$vorher  = (object) array( 'response' => array( 'anderes/plugin.php' => 'unberührt' ), 'no_update' => array() );
$nachher = UNDT_Updater::inject( $vorher );

undt_t( is_object( $nachher ), 'Ein fehlgeschlagener Abruf liefert den Transient unverändert zurück' );
undt_t( isset( $nachher->response['anderes/plugin.php'] ), 'Fremde Einträge im Transient bleiben unangetastet' );

$status = UNDT_Updater::status();
undt_t( in_array( $status['state'], array( 'error', 'current', 'update' ), true ), 'Status meldet einen bekannten Zustand: ' . $status['state'] );

// Der Ordner-Haken darf fremde Plugins nicht anfassen.
undt_t(
	'/tmp/fremd' === UNDT_Updater::fix_folder( '/tmp/fremd', '/tmp', null, array( 'plugin' => 'anderes/plugin.php' ) ),
	'Der Ordner-Haken lässt fremde Plugins in Ruhe'
);

/* ------------------------------------------------------------ Backend ---- */

undt_h( 'Backend-Seiten' );

require_once ABSPATH . 'wp-admin/includes/admin.php';

wp_set_current_user( 1 );
set_current_screen( 'dashboard' );

undt_t( current_user_can( 'manage_options' ), 'Testbenutzer darf Optionen verwalten' );
undt_t( is_admin(), 'Kontext gilt als Backend' );

// Menue und Einstellungen werden ueber diese beiden Haken aufgebaut.
do_action( 'admin_menu' );
do_action( 'admin_init' );

global $menu, $submenu;

$has_menu = false;

foreach ( (array) $menu as $entry ) {
	if ( isset( $entry[2] ) && 'undt' === $entry[2] ) {
		$has_menu = true;
	}
}

undt_t( $has_menu, 'Menüpunkt angelegt' );

$menu_title = '';

foreach ( (array) $menu as $entry ) {
	if ( isset( $entry[2] ) && 'undt' === $entry[2] ) {
		$menu_title = $entry[0];
	}
}

undt_t( 'Firmendaten' === $menu_title, 'Er heißt Firmendaten: ' . $menu_title );

$expected_pages = array( 'undt', 'undt-company', 'undt-m-hours', 'undt-m-prices', 'undt-m-social', 'undt-m-faq', 'undt-m-banner', 'undt-m-seo', 'undt-embed', 'undt-transfer', 'undt-settings' );
$actual_pages   = isset( $submenu['undt'] ) ? wp_list_pluck( $submenu['undt'], 2 ) : array();

foreach ( $expected_pages as $page ) {
	undt_t( in_array( $page, $actual_pages, true ), 'Unterseite ' . $page . ' vorhanden' );
}

foreach ( array( 'undt-shortcodes', 'undt-audit', 'undt-profile' ) as $page ) {
	undt_t( ! in_array( $page, $actual_pages, true ), 'Alte Seite ' . $page . ' gibt es nicht mehr' );
}

// Vor der Ausgabe des Menues verschwinden die Unterpunkte; geführt wird über die eigene Seitenleiste.
$undt_submenu_backup = $submenu;
UNDT_Admin::instance()->hide_submenu();
undt_t( ! isset( $submenu['undt'] ), 'Im WordPress-Menü steht Firmendaten ohne Unterpunkte' );
undt_t( false !== has_action( 'admin_head', array( UNDT_Admin::instance(), 'hide_submenu' ) ), 'Das geschieht auf admin_head, nach der Prüfung der Rechte' );
$submenu = $undt_submenu_backup;

undt_t( false !== has_action( 'admin_page_access_denied', array( UNDT_Admin::instance(), 'redirect_old_slugs' ) ), 'Alte Adressen werden umgeleitet' );
undt_t( false !== has_action( 'wp_ajax_undt_save', array( 'UNDT_Autosave', 'handle' ) ) && false === has_action( 'wp_ajax_nopriv_undt_save' ), 'Speichern über admin-ajax, nur angemeldet' );

// Die Settings API traegt die Optionen samt Sanitizer ein. Ohne diese
// Registrierung wuerde options.php ein Speichern ablehnen.
/*
 * Asset-Versionen muessen sich mit der Datei aendern. Bleibt die Kennung gleich,
 * liefern Browser und Object-Cache nach einer Aenderung an CSS oder JavaScript
 * weiter die alte Datei aus, und man sieht neues Markup in alter Gestaltung.
 */
do_action( 'admin_enqueue_scripts', 'toplevel_page_undt' );

foreach ( array( 'style' => wp_styles(), 'script' => wp_scripts() ) as $kind => $registry ) {
	$handle = $registry->registered['undt-admin'] ?? null;
	$ver    = $handle ? (string) $handle->ver : '';

	undt_t(
		$handle && $ver !== UNDT_VERSION && 0 === strpos( $ver, UNDT_VERSION . '.' ),
		sprintf( 'Asset-Version %s enthält die Dateizeit: %s', $kind, '' === $ver ? 'nicht registriert' : $ver )
	);
}

$registered = get_registered_settings();

foreach ( array( 'undt_profile', 'undt_company', 'undt_settings', 'undt_hours', 'undt_prices', 'undt_social', 'undt_faq', 'undt_banner', 'undt_seo' ) as $option ) {
	undt_t(
		isset( $registered[ $option ] ) && ! empty( $registered[ $option ]['sanitize_callback'] ),
		'Option ' . $option . ' ist mit Sanitizer registriert'
	);
}

/* --------------------------------------------- Befunde aus dem Audit ----- */

undt_h( 'Befunde aus dem Audit, die nur WordPress selbst zeigt' );

// F-12: Der Aufruf der Seite mit der Rechtsform schreibt nichts, erst das Speichern.
delete_option( 'undt_setup_done' );
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/company.php';
ob_end_clean();
undt_t( false === get_option( 'undt_setup_done' ), 'Aufruf der Seite mit der Rechtsform schreibt keine Option' );

call_user_func( $registered['undt_profile']['sanitize_callback'], array( 'legal_form' => 'gmbh' ) );
undt_t( 1 === (int) get_option( 'undt_setup_done' ), 'Erst das Speichern des Profils schließt die Einrichtung ab' );

// F-06: Eine verknüpfte Entwurfsseite steht in der Seitenauswahl und bleibt gewählt.
$draft_id                = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Datenschutz im Entwurf' ) );
$company                 = get_option( 'undt_company' );
$company['page_privacy'] = $draft_id;
update_option( 'undt_company', $company );
UNDT_Store::flush();

ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/company.php';
$company_html = ob_get_clean();

// Die Seitenfelder stehen seit 0.6.0 unter „Einbinden“.
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/embed.php';
$pages_html = ob_get_clean();

/**
 * Der Inhalt einer Seitenauswahl im Stammdaten-Formular.
 *
 * @param string $html Markup der Ansicht.
 * @param string $key  Feldschluessel.
 * @return string
 */
function undt_select_of( $html, $key ) {
	return preg_match( '/<select[^>]*name="undt_company\[' . preg_quote( $key, '/' ) . '\]\[choice\]"[^>]*>(.*?)<\/select>/s', $html, $match ) ? $match[1] : '';
}

undt_t(
	(bool) preg_match( '/value="' . (int) $draft_id . '"[^>]*selected/', undt_select_of( $pages_html, 'page_privacy' ) ),
	'Seitenauswahl enthält die verknüpfte Entwurfsseite und wählt sie aus'
);

// F-07: Eine erst später angelegte Modul-Option bleibt aus dem Autoload.
delete_option( 'undt_faq' );
update_option( 'undt_faq', array( 'items' => array( array( 'group' => '', 'question' => 'Neu?', 'answer' => 'Ja.' ) ) ) );
$faq_autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'undt_faq' ) );
undt_t( in_array( (string) $faq_autoload, array( 'no', 'off' ), true ), 'Neu angelegte Option undt_faq bleibt aus dem Autoload: ' . var_export( $faq_autoload, true ) );

/* ------------------------------------------------------- Neu in 0.5.0 ---- */

undt_h( 'Seitenfelder, Sprache und dynamische Daten' );

// Ein eigener Inhaltstyp fuer Rechtstexte, einer, der nicht in Menues erscheint
// (so halten es Plugins fuer Rechtstexte oft), und einer ohne Eintraege.
register_post_type( 'undt_recht', array( 'public' => true, 'label' => 'Rechtstexte' ) );
register_post_type( 'undt_ohnemenue', array( 'public' => true, 'label' => 'Rechtstexte ohne Menü', 'show_in_nav_menus' => false ) );
register_post_type( 'undt_leer', array( 'public' => true, 'label' => 'Leer' ) );

// So registriert Bricks 2.4 seine Vorlagen: oeffentlich und fuer Menues freigegeben.
if ( ! post_type_exists( 'bricks_template' ) ) {
	register_post_type( 'bricks_template', array( 'public' => true, 'label' => 'My templates', 'exclude_from_search' => true, 'show_in_nav_menus' => true ) );
}

$recht_id = wp_insert_post( array( 'post_type' => 'undt_recht', 'post_status' => 'publish', 'post_title' => 'AGB als Rechtstext' ) );
wp_insert_post( array( 'post_type' => 'undt_ohnemenue', 'post_status' => 'publish', 'post_title' => 'Datenschutz ohne Menü' ) );
wp_insert_post( array( 'post_type' => 'bricks_template', 'post_status' => 'publish', 'post_title' => 'Nur eine Vorlage' ) );

$link_types = UNDT_Controls::link_post_types();
undt_t( isset( $link_types['page'], $link_types['undt_recht'], $link_types['undt_ohnemenue'] ), 'Seitenfelder wählen aus Seiten und eigenen Inhaltstypen, auch ohne Menü-Freigabe: ' . implode( ', ', array_keys( $link_types ) ) );
undt_t( ! isset( $link_types['undt_leer'] ) && ! isset( $link_types['bricks_template'] ) && ! isset( $link_types['post'] ) && ! isset( $link_types['attachment'] ), 'Leere Typen, Bricks-Vorlagen, Beiträge und Medien fehlen in der Auswahl' );

$company = call_user_func(
	$registered['undt_company']['sanitize_callback'],
	array(
		'page_terms'         => array(
			'choice' => (string) $recht_id,
			'url'    => 'https://example.test/steht-noch-da',
		),
		'page_accessibility' => array(
			'choice' => 'url',
			'url'    => 'barrierefreiheit',
		),
	)
);

undt_t( $recht_id === $company['page_terms'], 'Gewählter Rechtstext wird als ID gespeichert' );
undt_t( '/barrierefreiheit' === $company['page_accessibility'], 'Eigene Adresse ohne Schrägstrich wird zum Pfad: ' . var_export( $company['page_accessibility'], true ) );

update_option( 'undt_company', $company );
UNDT_Store::flush();

$nav = UNDT_Render::legal_nav();
undt_t( false !== strpos( $nav, 'href="' . esc_url( get_permalink( $recht_id ) ) . '"' ), 'Footer verlinkt den Rechtstext' );
undt_t( false !== strpos( $nav, 'href="/barrierefreiheit"' ), 'Footer verlinkt die eigene Adresse' );

ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/company.php';
$company_html = ob_get_clean();

// Die Seitenfelder stehen seit 0.6.0 unter „Einbinden“.
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/embed.php';
$pages_html = ob_get_clean();
$terms_select = undt_select_of( $pages_html, 'page_terms' );

undt_t( (bool) preg_match( '/<optgroup label="Rechtstexte">[^<]*<option[^>]*value="' . (int) $recht_id . '"[^>]*selected/', $terms_select ), 'Auswahl zeigt den Rechtstext in eigener Gruppe und wählt ihn' );
undt_t( false !== strpos( $terms_select, '<optgroup label="' . esc_attr( get_post_type_object( 'page' )->labels->name ) . '">' ), 'Seiten stehen in eigener Gruppe' );
undt_t( false === strpos( $pages_html, 'Nur eine Vorlage' ) && false !== strpos( $pages_html, 'Datenschutz ohne Menü' ), 'Vorlagen stehen nicht in der Auswahl, Rechtstexte ohne Menü schon' );
undt_t( (bool) preg_match( '/name="undt_company\[page_terms\]\[url\]" value=""[^>]*data-undt-link-url hidden/', $pages_html ), 'Adressfeld neben einer Auswahl ist leer und verborgen' );
undt_t( (bool) preg_match( '/name="undt_company\[page_accessibility\]\[url\]" value="\/barrierefreiheit"[^>]*data-undt-link-url \/>/', $pages_html ), 'Adressfeld zeigt die eigene Adresse und ist sichtbar' );
undt_t( false !== strpos( undt_select_of( $pages_html, 'page_accessibility' ), '<option value="url" selected' ), '„Eigene Adresse …“ ist gewählt' );
undt_t( '' === $wpdb->last_error, 'Auswahl ohne Datenbankfehler aufgebaut' . ( '' === $wpdb->last_error ? '' : ': ' . $wpdb->last_error ) );
undt_t( false !== strpos( $company_html, '<noscript><p class="undt-noscript"><input type="submit" name="submit" id="submit" class="button button-primary" value="Speichern"' ) && false === strpos( $company_html, 'Save Changes' ), 'Speichern-Knopf nur ohne Skript, und deutsch' );
undt_t( false !== strpos( $company_html, 'data-undt-autosave="company"' ) && false !== strpos( $company_html, 'data-undt-save data-state="saved"' ), 'Die Firma speichert von selbst und zeigt den Stand' );
undt_t( false !== strpos( $company_html, 'undt-fcopy' ) && false !== strpos( $company_html, '{undt_company_name}' ) && false !== strpos( $company_html, '{options.undt.company_name}' ), 'Am Feld steht ein Kopierknopf, der Bricks und Etch kennt' );

// Deutsch, auch wenn WordPress selbst englisch laeuft.
undt_t( 'Montag' === UNDT_Hours::day_label( 'mon' ) && 'Fr' === UNDT_Hours::day_label( 'fri', true ), 'Wochentage deutsch bei Sprache ' . get_locale() );
undt_t( '24. Dezember 2026' === UNDT_Hours::date_label( '2026-12-24' ), 'Datum deutsch bei Sprache ' . get_locale() );
$hours = do_shortcode( '[undt_hours special="1"]' );
undt_t( false === strpos( $hours, 'Fri' ) && false === strpos( $hours, 'Sun' ) && false === strpos( $hours, 'December' ), 'Öffnungszeiten ohne englische Tage und Monate' );

// Dynamische Daten, so wie Slim SEO, Bricks und Etch sie abrufen.
$vars  = apply_filters( 'slim_seo_variables', array() );
$group = is_array( $vars ) ? end( $vars ) : null;
undt_t( is_array( $group ) && 'Unternehmensdaten' === $group['label'] && isset( $group['options']['undt.company_name'], $group['options']['undt.page_terms'] ), 'Slim SEO bekommt die Gruppe Unternehmensdaten' );

$seo_data = apply_filters( 'slim_seo_data', array( 'post' => array() ), 0, 0 );
undt_t( isset( $seo_data['undt']['company_name'] ) && 'Playground GmbH' === $seo_data['undt']['company_name'], 'Slim SEO bekommt die Werte' );
undt_t( isset( $seo_data['undt']['page_terms'] ) && get_permalink( $recht_id ) === $seo_data['undt']['page_terms'], 'Seitenfeld liefert die Adresse des Rechtstexts' );

// Die Schema-Einstellungen von Slim SEO Pro fragen über eigene Haken.
$schema_vars  = apply_filters( 'slim_seo_schema_variables', array() );
$schema_group = is_array( $schema_vars ) ? end( $schema_vars ) : null;
undt_t( is_array( $schema_group ) && 'Unternehmensdaten' === $schema_group['label'] && isset( $schema_group['options']['undt.company_name'], $schema_group['options']['undt.social_profiles'] ), 'Die Schema-Einstellungen bekommen dieselbe Gruppe samt Social-Profilen' );

$schema_data = apply_filters( 'slim_seo_schema_data', array( 'post' => array() ) );
undt_t( isset( $schema_data['undt']['company_name'] ) && 'Playground GmbH' === $schema_data['undt']['company_name'], 'Die Schema-Einstellungen bekommen die Werte' );
undt_t( isset( $schema_data['undt']['social_profiles'] ) && is_array( $schema_data['undt']['social_profiles'] ) && 3 === count( $schema_data['undt']['social_profiles'] ), 'Die Social-Profile kommen als Liste, für sameAs' );
undt_t( ! isset( $seo_data['undt']['social_profiles'] ), 'In den Meta-Angaben steht die Liste nicht' );

// Drei gleich lange Listen für openingHoursSpecification.
$tage   = isset( $schema_data['undt']['hours_days'] ) ? $schema_data['undt']['hours_days'] : array();
$von    = isset( $schema_data['undt']['hours_opens'] ) ? $schema_data['undt']['hours_opens'] : array();
$bis    = isset( $schema_data['undt']['hours_closes'] ) ? $schema_data['undt']['hours_closes'] : array();

undt_t( ! empty( $tage ) && count( $tage ) === count( $von ) && count( $von ) === count( $bis ), 'Öffnungszeiten als drei gleich lange Listen: ' . count( $tage ) . ' Einträge' );
undt_t( in_array( 'Monday', $tage, true ) && ! in_array( '', $von, true ), 'Mit englischen Tagen und gesetzten Uhrzeiten' );

$tags = apply_filters( 'bricks/dynamic_tags_list', array() );
undt_t( in_array( '{undt_phone_link}', wp_list_pluck( $tags, 'name' ), true ), 'Bricks bekommt die Tags' );
$rendered = apply_filters( 'bricks/dynamic_data/render_content', 'Tel. {undt_phone} {post_title}', null, 'text' );
undt_t( 'Tel. +49 30 1234567 {post_title}' === $rendered, 'Bricks ersetzt nur die eigenen Tags: ' . $rendered );
undt_t( 'tel:+49301234567' === apply_filters( 'bricks/dynamic_data/render_tag', 'undt_phone_link', null, 'link' ), 'Bricks bekommt im Link-Kontext die Adresse' );
undt_t( 'kein Fax' === apply_filters( 'bricks/dynamic_data/render_tag', "{undt_fax @fallback:'kein Fax'}", null, 'text' ), 'Bricks-Filter @fallback greift bei leerem Feld' );
undt_t( 'Playground…' === apply_filters( 'bricks/dynamic_data/render_tag', '{undt_company_name:1}', null, 'text' ), 'Bricks-Filter für die Wortzahl greift' );

// Das automatische Banner bleibt in der Oberflaeche von Etch aus, im Inhalt nicht.
$_GET['etch'] = 'magic';
UNDT_Blocks::reset();
ob_start();
UNDT_Shortcodes::auto_banner();
$builder_banner = ob_get_clean();
UNDT_Blocks::reset();
$builder_shortcode = do_shortcode( '[undt_banner]' );
unset( $_GET['etch'] );
UNDT_Blocks::reset();
ob_start();
UNDT_Shortcodes::auto_banner();
$site_banner = ob_get_clean();
UNDT_Blocks::reset();
undt_t( '' === $builder_banner && false !== strpos( $builder_shortcode, 'undt-banner' ), 'Automatisches Banner bleibt im Etch-Builder aus, der Shortcode nicht' );
undt_t( false !== strpos( $site_banner, 'undt-banner' ), 'Automatisches Banner erscheint auf der Website' );

$etch = apply_filters( 'etch/dynamic_data/option', array() );
undt_t( isset( $etch['undt']['email_link'] ) && 'mailto:info@playground.test' === $etch['undt']['email_link'], 'Etch bekommt die Werte unter options.undt' );

ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/embed.php';
$shortcodes_html = ob_get_clean();
undt_t( false !== strpos( $shortcodes_html, 'data-undt-values' ) && false !== strpos( $shortcodes_html, '{options.undt.phone}' ) && false !== strpos( $shortcodes_html, '[undt key=\\&quot;phone\\&quot;]' ), 'Einbinden nennt die einzelnen Angaben in allen Formaten' );

/* ------------------------------------------------------- Neu in 0.5.2 ---- */

undt_h( 'Darstellung, Kartenlinks, Social-Symbole und Banner-Werte' );

// Die Symbole kommen aus dem Social-Icons-Block dieser WordPress-Fassung.
$icon = UNDT_Icons::platform( 'instagram' );
undt_t( 0 === strpos( $icon, '<svg width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false"' ) && false !== strpos( $icon, '<path' ), 'Instagram-Symbol aus WordPress, in Textfarbe' );
undt_t( false !== stripos( $icon, 'viewbox="0 0 24 24"' ), 'viewBox bleibt erhalten' );

add_filter(
	'undt_social_icon',
	static function () {
		return '<svg viewBox="0 0 1 1" onload="alert(1)"><script>alert(1)</script><path d="M0 0h1" onclick="x()"/></svg>';
	}
);
UNDT_Icons::flush();
$icon = UNDT_Icons::platform( 'xing' );
undt_t( false === stripos( $icon, 'script' ) && false === stripos( $icon, 'onload' ) && false === stripos( $icon, 'onclick' ) && false !== strpos( $icon, '<path' ), 'Symbole aus dem Filter laufen durch wp_kses' );
remove_all_filters( 'undt_social_icon' );
UNDT_Icons::flush();

$social_opt               = get_option( 'undt_social' );
$social_opt['show_icons'] = 1;
$social_opt['new_tab']    = 1;
update_option( 'undt_social', $social_opt );
UNDT_Content::flush();
$social = do_shortcode( '[undt_social]' );
undt_t( 3 === substr_count( $social, 'class="undt-social__icon"' ) && 3 === substr_count( $social, 'class="undt-social__external"' ), 'Social mit Symbolen und Pfeil für den neuen Tab' );
undt_t( false !== strpos( $social, 'aria-label="LinkedIn (öffnet in neuem Tab)"' ) && false === strpos( wp_strip_all_tags( $social ), 'neuem Tab' ), 'Hinweis auf den neuen Tab nur im Namen des Links' );

$maps = UNDT_Store::get( 'maps_google' );
undt_t( 0 === strpos( $maps, 'https://www.google.com/maps/search/?api=1&query=Playground%20GmbH%2C%20Teststra%C3%9Fe%207%2C%2010115%20Berlin' ), 'Google-Maps-Link aus Firma und Anschrift: ' . $maps );
undt_t( '<a href="' . esc_url( $maps ) . '">Route planen</a>' === do_shortcode( '[undt key="maps_google" link="1" text="Route planen"]' ), 'Kartenlink per Shortcode mit eigenem Text' );
undt_t( false !== strpos( $company_html, 'name="undt_company[maps_google]" value="" class="large-text" placeholder="https://www.google.com/maps/search/' ), 'Leeres Kartenfeld zeigt den erzeugten Link grau an' );

$etch = apply_filters( 'etch/dynamic_data/option', array() );
undt_t( true === $etch['undt']['banner_show'] && true === $etch['undt']['banner_dismissible'] && 'warning' === $etch['undt']['banner_type'], 'Etch bekommt die Banner-Angaben, Ja-Nein-Werte als true oder false' );
undt_t( is_bool( $etch['undt']['is_open'] ), 'Etch bekommt is_open als Wahrheitswert' );
undt_t( '1' === apply_filters( 'bricks/dynamic_data/render_tag', '{undt_banner_show}', null, 'text' ), 'Bricks bekommt banner_show als 1' );
undt_t( 'https://example.test/oeffnungszeiten' === apply_filters( 'bricks/dynamic_data/render_tag', '{undt_banner_link_url}', null, 'link' ), 'Bricks bekommt das Link-Ziel des Banners' );

$undt_slug = 'banner';
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/module.php';
$banner_html = ob_get_clean();
undt_t( false !== strpos( $banner_html, 'data-undt-banner-preview' ) && false !== strpos( $banner_html, 'data-undt-count="140"' ) && false !== strpos( $banner_html, 'name="undt_banner[date_from]"' ), 'Banner-Seite: Vorschau, Zeichenzähler, Zeitraum' );
undt_t( false !== strpos( (string) wp_json_encode( UNDT_Embed::values() ), 'banner_show' ), 'Die Werte für Bricks und Etch stehen unter Einbinden' );

$undt_slug = 'social';
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/module.php';
$social_html = ob_get_clean();
undt_t( false !== strpos( $social_html, 'data-undt-social-new' ) && false !== strpos( $social_html, 'class="undt-icon-library" hidden' ), 'Social-Seite: Adresse einfügen, Symbole aus der Sammlung' );
undt_t( false !== strpos( $social_html, 'name="undt_social[layout]"' ) && false !== strpos( $social_html, 'value="row"' ), 'Social-Seite fragt nach der Darstellung' );

/* ------------------------------------------------------- Neu in 0.5.8 ---- */

undt_h( 'Block-Bindungen für den Block-Editor' );

undt_t( UNDT_Dynamic_Gutenberg::available(), 'Diese WordPress-Fassung kennt Block-Bindungen' );

$undt_sources = class_exists( 'WP_Block_Bindings_Registry' ) ? WP_Block_Bindings_Registry::get_instance()->get_all_registered() : array();
$undt_source  = isset( $undt_sources[ UNDT_Dynamic_Gutenberg::SOURCE ] ) ? $undt_sources[ UNDT_Dynamic_Gutenberg::SOURCE ] : null;

undt_t( null !== $undt_source, 'WordPress führt die Quelle ' . UNDT_Dynamic_Gutenberg::SOURCE );
undt_t( null !== $undt_source && 'Unternehmensdaten' === $undt_source->label, 'Im Editor heißt sie Unternehmensdaten' );

/*
 * Der eigentliche Beweis: WordPress rendert den Block, den das Backend zum
 * Kopieren anbietet, mit dem aktuellen Wert statt mit dem Platzhalter.
 */
$undt_block    = UNDT_Dynamic_Gutenberg::markup( 'phone' );
$undt_parsed   = array_values( array_filter( parse_blocks( $undt_block ), static function ( $b ) { return ! empty( $b['blockName'] ); } ) );
$undt_rendered = do_blocks( $undt_block );

undt_t( 1 === count( $undt_parsed ) && 'core/paragraph' === $undt_parsed[0]['blockName'], 'Der kopierte Text ergibt genau einen Absatz-Block' );
undt_t( isset( $undt_parsed[0]['attrs']['metadata']['bindings']['content']['args']['key'] ) && 'phone' === $undt_parsed[0]['attrs']['metadata']['bindings']['content']['args']['key'], 'Mit der Bindung an das Feld' );
undt_t( false !== strpos( $undt_rendered, '>' . UNDT_Store::get( 'phone' ) . '<' ), 'Ausgegeben wird der Wert: ' . wp_strip_all_tags( $undt_rendered ) );
undt_t( false === strpos( $undt_rendered, 'Telefon' ), 'Der Platzhalter aus dem Block steht nicht mehr da' );
undt_t( false !== strpos( do_blocks( UNDT_Dynamic_Gutenberg::markup( 'address' ) ), UNDT_Store::get( 'city' ) ), 'Auch zusammengesetzte Werte wie die Anschrift' );

// Das Skript, das im Editor die Werte zeigt.
UNDT_Dynamic_Gutenberg::editor();
$undt_scripts = wp_scripts();
$undt_inline  = (array) $undt_scripts->get_data( 'undt-bindings', 'before' );

undt_t( isset( $undt_scripts->registered['undt-bindings'] ), 'Das Skript für den Editor ist angemeldet' );
undt_t( isset( $undt_scripts->registered['undt-bindings'] ) && in_array( 'wp-blocks', (array) $undt_scripts->registered['undt-bindings']->deps, true ), 'Es wartet auf wp-blocks' );
undt_t( false !== strpos( implode( '', $undt_inline ), '"phone":"' . UNDT_Store::get( 'phone' ) . '"' ), 'Und bekommt die Werte mitgeliefert' );
undt_t( file_exists( WP_PLUGIN_DIR . '/' . $slug . '/admin/assets/gutenberg.js' ), 'Die Skriptdatei liegt im Paket' );

// Kopierknöpfe unter den Feldern und in der Referenz.
undt_t( false !== strpos( $company_html, '&lt;!-- wp:paragraph' ), 'Der Knopf am Feld kennt den Absatz für den Block-Editor' );
undt_t( false !== strpos( $shortcodes_html, '&lt;!-- wp:paragraph' ), 'Einbinden bietet den Absatz für den Block-Editor an' );

/* ---------------------------------------------- Oberfläche des Backends -- */

undt_h( 'Weiße Karte, Registerkarten und zwei Spalten' );

ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/company.php';
$ui_html = ob_get_clean();

undt_t( false !== strpos( $ui_html, 'class="undt-form undt-box undt-company"' ) && false !== strpos( $ui_html, 'class="undt-box undt-legalform"' ), 'Firma: Karte Rechtsform und Karte mit den Angaben' );
undt_t( false !== strpos( $ui_html, 'class="undt-tab is-active"' ) && false === strpos( $ui_html, 'nav-tab' ), 'Registerkarten tragen die eigenen Klassen' );
undt_t( false !== strpos( $ui_html, 'class="large-text"' ) && false === strpos( $ui_html, 'class="regular-text"' ), 'Alle Textfelder nehmen die Breite der Spalte ein' );
undt_t( 0 === substr_count( $ui_html, 'class="undt-box__footer"' ) && substr_count( $ui_html, 'id="submit"' ) === substr_count( $ui_html, '<noscript>' ), 'Kein Speichern-Fuß mehr, Knöpfe nur für Browser ohne Skript' );

undt_t( false !== strpos( $ui_html, 'id="rechtsform"' ) && substr_count( $ui_html, 'class="undt-question-row' ) >= 6, 'Rechtsform: die Fragen stehen einspaltig in der Karte' );
undt_t( false !== strpos( $ui_html, 'data-undt-when=' ), 'Abhängige Fragen behalten ihre Bedingung' );

$undt_slug = 'hours';
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/module.php';
$ui_html = ob_get_clean();

undt_t( false !== strpos( $ui_html, 'data-undt-autosave="module:hours"' ) && false === strpos( $ui_html, 'class="undt-box__footer"' ), 'Auch ein Inhaltsbereich speichert von selbst' );
undt_t( false !== strpos( $ui_html, 'class="undt-time"' ) && 7 === substr_count( $ui_html, 'data-undt-day="' ), 'Je Tag eine Zeile mit schmalen Uhrzeiten' );
undt_t( false !== strpos( $ui_html, 'data-undt-hours-weekdays' ) && false !== strpos( $ui_html, 'Montag auf Dienstag bis Freitag übertragen' ), 'Der Knopf überträgt Montag auf die Werktage' );

$undt_slug = 'social';
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/module.php';
$ui_html = ob_get_clean();

undt_t( substr_count( $ui_html, 'class="undt-srow-set' ) >= 5 && false !== strpos( $ui_html, 'Erweitert' ), 'Social Media: Schalter neben der Vorschau, rel="me" unter Erweitert' );
undt_t( false === strpos( $company_html, 'undt-copy-row' ), 'Die alte Reihe aus Shortcode und Logos ist fort' );

// Jede Ansicht einmal rendern, um Fehler in den Templates zu finden.
$views = array(
	'company.php'     => 'Firma',
	'embed.php'       => 'Einbinden',
	'overview.php'    => 'Übersicht',
	'transfer.php'    => 'Sichern & Übertragen',
	'settings.php'    => 'Einstellungen',
);

foreach ( $views as $view => $label ) {
	ob_start();
	$error = '';

	try {
		include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/' . $view;
	} catch ( Throwable $e ) {
		$error = $e->getMessage();
	}

	$html = ob_get_clean();

	undt_t( '' === $error, 'Ansicht ' . $label . ' rendert ohne Fehler' . ( '' === $error ? '' : ': ' . $error ) );
	undt_t( '' === $error && false !== strpos( $html, 'wrap undt-wrap' ), 'Ansicht ' . $label . ' erzeugt Markup' );
}

// Die Modulansicht braucht ihre Variable.
foreach ( array( 'hours', 'prices', 'social', 'faq', 'banner', 'seo' ) as $undt_slug ) {
	ob_start();
	$error = '';

	try {
		include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/module.php';
	} catch ( Throwable $e ) {
		$error = $e->getMessage();
	}

	$html = ob_get_clean();

	undt_t( '' === $error && false !== strpos( $html, 'undt-form' ), 'Modulansicht ' . $undt_slug . ' rendert' . ( '' === $error ? '' : ': ' . $error ) );
}

/* --------------------------------------------------- Sichern und Einspielen */

undt_h( 'Sichern und übertragen' );

UNDT_Transfer::register();
undt_t( has_action( 'admin_post_undt_export' ) && has_action( 'admin_post_undt_import' ), 'Die Formulare sind angemeldet' );

$sicherung = UNDT_Transfer::export();
undt_t( 'unternehmensdaten' === $sicherung['format'] && UNDT_VERSION === $sicherung['plugin_version'], 'Die Sicherung nennt Herkunft und Fassung' );
undt_t( count( $sicherung['options'] ) === count( UNDT_Transfer::options() ), 'Alle Optionen sind enthalten: ' . count( $sicherung['options'] ) );
undt_t( 'Playground GmbH' === $sicherung['options']['undt_company']['company_name'], 'Mit den echten Werten' );
undt_t( (int) $recht_id === (int) $sicherung['options']['undt_company']['page_terms'], 'Auch die verknüpfte Seite, als ID der Ursprungsseite' );

// Einspielen einer Datei „von einer anderen Website“.
$fremd = array(
	'format'  => 'unternehmensdaten',
	'schema'  => 1,
	'site'    => 'https://andere-seite.test/',
	'options' => array(
		'undt_company' => array(
			'company_name' => 'Übernommen GmbH',
			'page_terms'   => 4242,
			'page_privacy' => array( 'choice' => 'url', 'url' => '/datenschutz/' ),
		),
	),
);

$bericht = UNDT_Transfer::import( $fremd );

undt_t( ! is_wp_error( $bericht ), 'Eine fremde Sicherung wird angenommen' );
undt_t( 'Übernommen GmbH' === UNDT_Store::get( 'company_name' ), 'Die Werte stehen danach in der Option' );
undt_t( in_array( 'page_terms', $bericht['dropped'], true ) && 0 === UNDT_Store::page_id( 'page_terms' ), 'Die Seiten-ID der anderen Website ist verworfen, statt auf Beitrag 4242 zu zeigen' );
$ziel = UNDT_Store::link( 'page_privacy' );
undt_t( is_array( $ziel ) && '/datenschutz/' === $ziel['url'], 'Eine eigene Adresse bleibt erhalten' );

// Und zurueck: die eigene Sicherung stellt den Stand wieder her.
$zurueck = UNDT_Transfer::import( $sicherung );

undt_t( ! is_wp_error( $zurueck ) && 'Playground GmbH' === UNDT_Store::get( 'company_name' ), 'Die eigene Sicherung stellt die Angaben wieder her' );
undt_t( 3 === count( UNDT_Content::rows( 'social', 'items' ) ), 'Mit den Zeilen der Wiederholungsfelder' );
undt_t( 1 === (int) get_option( 'undt_setup_done' ), 'Die Einrichtung gilt weiter als erledigt' );

// Die verknüpfte Seite muss nach dem Einspielen neu gewählt werden, sie stand als ID in der Datei.
$company                = get_option( 'undt_company' );
$company['page_terms']  = $recht_id;
update_option( 'undt_company', $company );
UNDT_Store::flush();

ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/transfer.php';
$transfer_html = ob_get_clean();

undt_t( false !== strpos( $transfer_html, 'value="undt_export"' ) && false !== strpos( $transfer_html, 'value="undt_import"' ), 'Die Seite Sichern & Übertragen bietet beides an' );
undt_t( false !== strpos( $transfer_html, 'enctype="multipart/form-data"' ) && false !== strpos( $transfer_html, 'name="undt_file"' ), 'Mit einem Feld für die Datei' );

/* ------------------------------------------- Neu in 0.6.0: Rollen ---- */

undt_h( 'Rollen, Navigation und automatisches Speichern' );

/*
 * Mit echten Benutzern und Rollen: das Recht kommt ueber user_has_cap aus den
 * Einstellungen, nicht aus der Rollentabelle.
 */
$undt_editor_id = wp_insert_user( array( 'user_login' => 'redaktion', 'user_pass' => wp_generate_password(), 'user_email' => 'redaktion@playground.test', 'role' => 'editor' ) );
$undt_author_id = wp_insert_user( array( 'user_login' => 'autorin', 'user_pass' => wp_generate_password(), 'user_email' => 'autorin@playground.test', 'role' => 'author' ) );

undt_t( ! is_wp_error( $undt_editor_id ) && ! is_wp_error( $undt_author_id ), 'Redakteur und Autorin angelegt' );

$settings          = UNDT_Modules::settings();
$settings['roles'] = array();
update_option( 'undt_settings', $settings );
UNDT_Modules::flush();

undt_t( user_can( 1, UNDT_Access::CAP ), 'Der Admin pflegt immer' );
undt_t( ! user_can( $undt_editor_id, UNDT_Access::CAP ), 'Ohne Freigabe pflegt der Redakteur nicht' );

$settings['roles'] = array( 'editor', 'subscriber' );
update_option( 'undt_settings', $settings );
UNDT_Modules::flush();

undt_t( array( 'editor' ) === UNDT_Modules::settings()['roles'], 'Gespeichert wird nur eine geeignete Rolle, kein Abonnent' );
undt_t( user_can( $undt_editor_id, UNDT_Access::CAP ) && ! user_can( $undt_editor_id, 'manage_options' ), 'Mit Freigabe pflegt er, ohne Admin-Recht' );
undt_t( ! user_can( $undt_author_id, UNDT_Access::CAP ), 'Die Autorin bleibt außen vor' );
undt_t( ! in_array( UNDT_Access::CAP, array_keys( (array) get_role( 'editor' )->capabilities ), true ), 'Die Rolle in der Datenbank bleibt unverändert' );

$choosable = UNDT_Access::choosable_roles();
undt_t( isset( $choosable['editor'], $choosable['author'] ) && ! isset( $choosable['administrator'], $choosable['subscriber'] ), 'Wählbar: ' . implode( ', ', array_keys( $choosable ) ) );

wp_set_current_user( $undt_editor_id );

$visible = array_keys( UNDT_Nav::visible() );
undt_t( in_array( 'company', $visible, true ) && in_array( 'm_banner', $visible, true ) && ! in_array( 'settings', $visible, true ) && ! in_array( 'embed', $visible, true ) && ! in_array( 'm_seo', $visible, true ), 'Der Redakteur sieht: ' . implode( ', ', $visible ) );

ob_start();
UNDT_Nav::open( 'company' );
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/company.php';
UNDT_Nav::close( 'company' );
$editor_html = ob_get_clean();

undt_t( false === strpos( $editor_html, 'data-undt-copy=' ), 'Er sieht keine Kopierknöpfe' );
undt_t( false === strpos( $editor_html, 'page=undt-profile' ) && false === strpos( $editor_html, 'Kopieren als' ), 'Keine Rechtsform, kein „Kopieren als“' );
undt_t( false !== strpos( $editor_html, 'Infobanner zeigen' ), 'Aber den Banner-Schalter' );

$nonce = wp_create_nonce( UNDT_Autosave::ACTION );
undt_t( false !== wp_verify_nonce( $nonce, UNDT_Autosave::ACTION ), 'Die Nonce des Endpunkts ist gültig' );

$answer = UNDT_Autosave::save( 'company', array( 'undt_company' => array( 'company_name' => 'Redaktion <b>GmbH</b>' ) ) );
UNDT_Store::flush();
undt_t( ! is_wp_error( $answer ) && 'Redaktion GmbH' === UNDT_Store::get( 'company_name' ), 'Er speichert die Firma, durch den Sanitizer' );
undt_t( ! is_wp_error( $answer ) && isset( $answer['badges']['company'], $answer['nonce'] ), 'Die Antwort bringt Zähler und Nonce' );

$answer = UNDT_Autosave::save( 'settings', array( 'undt_settings' => array( 'roles' => array( 'author' => 1 ) ) ) );
undt_t( is_wp_error( $answer ) && 403 === $answer->get_error_data() && array( 'editor' ) === UNDT_Modules::settings()['roles'], 'Die Einstellungen darf er nicht' );

$answer = UNDT_Autosave::save( 'profile', array( 'undt_profile' => array( 'legal_form' => 'ug' ) ) );
undt_t( is_wp_error( $answer ) && 403 === $answer->get_error_data(), 'Die Rechtsform auch nicht' );

$banner_before = (array) get_option( 'undt_banner' );
$answer        = UNDT_Autosave::save( 'quick:banner', array( 'value' => '0' ) );
UNDT_Content::flush();
$banner_after  = (array) get_option( 'undt_banner' );
undt_t( ! is_wp_error( $answer ) && 0 === (int) UNDT_Content::value( 'banner', 'enabled' ), 'Den Banner-Schalter darf er' );
undt_t( isset( $banner_before['text'] ) && $banner_before['text'] === $banner_after['text'], 'Der Rest des Banners bleibt dabei unverändert' );
UNDT_Autosave::save( 'quick:banner', array( 'value' => '1' ) );
UNDT_Content::flush();

update_user_meta( $undt_editor_id, 'undt_copy_format', 'etch' );

wp_set_current_user( $undt_author_id );
$answer = UNDT_Autosave::save( 'company', array( 'undt_company' => array( 'company_name' => 'Fremd' ) ) );
UNDT_Store::flush();
undt_t( is_wp_error( $answer ) && 403 === $answer->get_error_data() && 'Redaktion GmbH' === UNDT_Store::get( 'company_name' ), 'Die Autorin speichert nichts' );

wp_set_current_user( 1 );
$answer = UNDT_Autosave::save( 'quick:copy_format', array( 'value' => 'shortcode' ) );
undt_t( ! is_wp_error( $answer ) && 'shortcode' === get_user_meta( 1, 'undt_copy_format', true ), 'Der Admin wählt sein Kopierformat' );

$company                 = get_option( 'undt_company' );
$company['company_name'] = 'Playground GmbH';
update_option( 'undt_company', $company );
UNDT_Store::flush();

// Die Seite in der Huelle, wie WordPress sie ausgibt.
ob_start();
UNDT_Admin::instance()->render( 'settings' );
$shell_html = ob_get_clean();
undt_t( false !== strpos( $shell_html, '<aside class="undt-side">' ) && false !== strpos( $shell_html, 'Wer darf die Angaben pflegen?' ) && false !== strpos( $shell_html, 'id="undt-mmenu"' ), 'Einstellungen in der Hülle, mit Rollen und Vollbild-Menü' );
undt_t( (bool) preg_match( '/name="undt_settings\[roles\]\[editor\]" value="1" checked/', $shell_html ), 'Die freigegebene Rolle ist angehakt' );

/* ------------------------------------------ Neu in 0.6.0: Übersicht ---- */

undt_h( 'Übersicht: wo verwendet, Zu erledigen, Erststart' );

wp_set_current_user( 1 );
UNDT_Usage::flush();

$undt_page_imprint = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Impressum Übersicht', 'post_content' => '<!-- wp:shortcode -->[undt_impressum]<!-- /wp:shortcode -->' ) );
$undt_page_thanks  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Danke', 'post_content' => 'Vielen Dank für deine Nachricht.' ) );
$undt_part_footer  = wp_insert_post( array( 'post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_title' => 'Footer', 'post_name' => 'footer', 'post_content' => '<!-- wp:shortcode -->[undt_footer]<!-- /wp:shortcode -->' ) );
$undt_page_bricks  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Bricks-Seite', 'post_content' => '' ) );
update_post_meta( $undt_page_bricks, '_bricks_page_content_2', array( array( 'name' => 'text', 'settings' => array( 'text' => '[undt_hours]' ) ) ) );

undt_t( 'Auf „Impressum Übersicht“' === UNDT_Usage::label( 'imprint' ), 'Impressum gefunden: ' . UNDT_Usage::label( 'imprint' ) );
undt_t( 'Im Footer' === UNDT_Usage::label( 'footer' ), 'Footer im Template-Part erkannt: ' . UNDT_Usage::label( 'footer' ) );
undt_t( 'Auf „Bricks-Seite“' === UNDT_Usage::label( 'hours' ), 'Öffnungszeiten in den Seitendaten von Bricks gefunden: ' . UNDT_Usage::label( 'hours' ) );
undt_t( false !== get_transient( UNDT_Usage::TRANSIENT ), 'Ergebnis liegt im Zwischenspeicher' );

wp_update_post( array( 'ID' => $undt_page_thanks, 'post_content' => 'Jetzt mit [undt_privacy_block name="controller"]' ) );
undt_t( false === get_transient( UNDT_Usage::TRANSIENT ), 'Speichern einer Seite verwirft den Zwischenspeicher' );
undt_t( 'Auf „Danke“' === UNDT_Usage::label( 'privacy' ), 'Und die neue Fundstelle steht danach da' );

// Falsche Impressum-Seite.
$company                 = get_option( 'undt_company' );
$undt_company_backup     = $company;
$company['page_imprint'] = $undt_page_thanks;
update_option( 'undt_company', $company );
UNDT_Store::flush();

$undt_titles = wp_list_pluck( UNDT_Audit::run(), 'title' );
undt_t( in_array( 'Impressum ist mit der Seite „Danke“ verknüpft', $undt_titles, true ), 'Prüfung meldet die falsche Impressum-Seite' );

$company['page_imprint'] = $undt_page_imprint;
update_option( 'undt_company', $company );
UNDT_Store::flush();
undt_t( ! in_array( 'Impressum ist mit der Seite „Impressum Übersicht“ verknüpft', wp_list_pluck( UNDT_Audit::run(), 'title' ), true ), 'Mit der richtigen Seite nicht mehr' );

// Die Seite in der Huelle, als Admin und als Redakteur.
update_option( UNDT_Overview::OPTION_ONBOARDING, array( 'skipped' => 1 ) );
ob_start();
UNDT_Admin::instance()->render( 'overview' );
$overview_html = ob_get_clean();

undt_t( false !== strpos( $overview_html, 'undt-statuscard' ) && false !== strpos( $overview_html, 'Deine Angaben' ), 'Übersicht mit Statuszeile und Kacheln' );
undt_t( false !== strpos( $overview_html, 'id="undt-embed-title"' ) && false !== strpos( $overview_html, 'Auf „Impressum Übersicht“' ), 'Admins sehen „Einbinden“ mit Fundort' );
undt_t( false !== strpos( $overview_html, 'class="undt-linkcard" href="' . esc_url( admin_url( 'admin.php?page=undt-company' ) ) . '"' ), 'Kachel Firma führt zur Seite Firma' );

wp_set_current_user( $undt_editor_id );
ob_start();
UNDT_Admin::instance()->render( 'overview' );
$overview_editor = ob_get_clean();
undt_t( false !== strpos( $overview_editor, 'undt-statuscard' ) && false === strpos( $overview_editor, 'id="undt-embed-title"' ) && false === strpos( $overview_editor, 'data-undt-action="module"' ), 'Redakteure: ohne Einbinden und ohne Weitere Bereiche' );
wp_set_current_user( 1 );

// Erststart.
delete_option( UNDT_Overview::OPTION_ONBOARDING );
update_option( 'undt_setup_done', 0 );
ob_start();
UNDT_Admin::instance()->render( 'overview' );
$onboarding_html = ob_get_clean();
undt_t( false !== strpos( $onboarding_html, 'Richte deine Firmendaten ein' ) && false !== strpos( $onboarding_html, 'undt-steps__item--current' ) && false === strpos( $onboarding_html, 'undt-statuscard' ), 'Vor der Einrichtung: Erststart statt Statuszeile' );

$answer = UNDT_Autosave::save( 'quick:onboarding', array( 'value' => 'skip' ) );
undt_t( ! is_wp_error( $answer ) && 1 === (int) get_option( UNDT_Overview::OPTION_ONBOARDING )['skipped'], '„Einrichtung überspringen“ wird gespeichert' );
update_option( 'undt_setup_done', 1 );

$answer = UNDT_Autosave::save( 'quick:module', array( 'value' => 'faq' ) );
undt_t( ! is_wp_error( $answer ) && UNDT_Modules::is_active( 'faq' ), 'Ein Bereich lässt sich aus der Übersicht einschalten' );

update_option( 'undt_company', $undt_company_backup );
UNDT_Store::flush();

/* --------------------------------- Neu in 0.6.0: Firma und Einbinden ---- */

undt_h( 'Firma und Einbinden' );

wp_set_current_user( 1 );
$undt_company_backup3 = get_option( 'undt_company' );

// Bundesland und Vorschlag der Aufsichtsbehoerde.
$company                      = get_option( 'undt_company' );
$company['state']             = 'NW';
$company['privacy_authority'] = '';
update_option( 'undt_company', $company );
UNDT_Store::flush();

$answer = UNDT_Autosave::save( 'quick:suggest', array( 'value' => 'privacy_authority' ) );
undt_t( ! is_wp_error( $answer ) && 0 === strpos( (string) UNDT_Store::get( 'privacy_authority' ), 'Landesbeauftragte für Datenschutz und Informationsfreiheit Nordrhein-Westfalen' ), 'Behörde für NRW übernommen' );
undt_t( false !== strpos( do_shortcode( '[undt_privacy_block name="authority"]' ), 'Kavalleriestraße 2-4' ), 'Und erscheint im Datenschutz-Baustein' );

// Rechtsform speichern: die Antwort traegt, was auf „Firma“ gilt.
$answer = UNDT_Autosave::save( 'profile', array( 'undt_profile' => array( 'legal_form' => 'sole', 'vat_status' => 'none' ) ) );
undt_t( ! is_wp_error( $answer ) && isset( $answer['company']['applies']['owner_name'] ) && true === $answer['company']['applies']['owner_name'] && false === $answer['company']['applies']['register_court'], 'Nach dem Wechsel zum Einzelunternehmen: Inhaber ja, Register nein' );
undt_t( 'Steuer' === $answer['company']['tabs']['tax']['label'], 'Die Karte heißt dann „Steuer“' );

// Seite anlegen.
$answer = UNDT_Autosave::save( 'quick:create_page', array( 'value' => 'imprint' ) );
$undt_new_page = is_wp_error( $answer ) ? 0 : (int) $answer['page'];
undt_t( $undt_new_page > 0 && 'draft' === get_post_status( $undt_new_page ) && 'page' === get_post_type( $undt_new_page ), 'Seite „Impressum“ als Entwurf angelegt' );
undt_t( $undt_new_page === UNDT_Store::page_id( 'page_imprint' ) && false !== strpos( (string) get_post_field( 'post_content', $undt_new_page ), '[undt_impressum]' ), 'Mit dem Baustein und gleich verknüpft' );
undt_t( UNDT_Usage::on_post( $undt_new_page, 'imprint' ), '„wo verwendet“ findet ihn dort' );

$pages = UNDT_Embed::pages();
undt_t( 'open' === $pages['page_imprint']['tone'] && 'Entwurf, noch nicht veröffentlicht' === $pages['page_imprint']['status'], 'Einbinden: Entwurf noch nicht veröffentlicht' );
wp_update_post( array( 'ID' => $undt_new_page, 'post_status' => 'publish' ) );
UNDT_Store::flush();
$pages = UNDT_Embed::pages();
undt_t( 'ok' === $pages['page_imprint']['tone'] && 'Enthält das Impressum' === $pages['page_imprint']['status'], 'Veröffentlicht: enthält das Impressum' );

// Vorschau mit echten Angaben.
$undt_preview = UNDT_Embed::preview( '[undt_address inline="1"]' );
undt_t( null !== $undt_preview && false !== strpos( $undt_preview, (string) UNDT_Store::get( 'city' ) ), 'Vorschau der Anschrift zeigt den Ort' );
undt_t( null === UNDT_Embed::preview( '[embed]https://example.test[/embed]' ), 'Fremde Shortcodes bekommen keine Vorschau' );

// Die Seiten in der Huelle.
ob_start();
UNDT_Admin::instance()->render( 'company' );
$firma_html = ob_get_clean();
undt_t( false !== strpos( $firma_html, 'Bundesland' ) && (bool) preg_match( '/<option value="NW" selected=[\'"]selected[\'"]>Nordrhein-Westfalen<\/option>/', $firma_html ), 'Firma: Bundesland als Auswahl' );
undt_t( false === strpos( $firma_html, 'name="undt_company[page_imprint]' ), 'Firma ohne Rechtsseiten' );

ob_start();
UNDT_Admin::instance()->render( 'embed' );
$embed_html = ob_get_clean();
undt_t( false !== strpos( $embed_html, 'name="undt_company[page_imprint][choice]"' ) && false !== strpos( $embed_html, 'Enthält das Impressum' ), 'Einbinden: Rechtsseiten mit Stand' );
undt_t( false !== strpos( $embed_html, 'data-undt-tag="undt_address"' ) && false !== strpos( $embed_html, 'data-on="inline=&quot;1&quot;"' ), 'Bausteine mit Häkchen' );
undt_t( substr_count( $embed_html, 'data-undt-value ' ) >= 10, 'Einzelne Angaben aufgelistet: ' . substr_count( $embed_html, 'data-undt-value ' ) );

undt_t( isset( UNDT_Admin::OLD_SLUGS['undt-profile'] ) && UNDT_Admin::SLUG_COMPANY === UNDT_Admin::OLD_SLUGS['undt-profile'], 'Die alte Adresse der Rechtsform führt auf Firma' );

wp_delete_post( $undt_new_page, true );
update_option( 'undt_company', $undt_company_backup3 );
UNDT_Store::flush();

/* ------------------------------------------- Neu in 0.6.0: Etappe 6 ---- */

undt_h( 'Berufsdaten' );

wp_set_current_user( 1 );
$undt_profile_backup6 = get_option( 'undt_profile' );
$undt_company_backup6 = get_option( 'undt_company' );
$undt_seo_backup6     = get_option( 'undt_seo' );

update_option( 'undt_profile', array_merge( (array) $undt_profile_backup6, array( 'is_regulated' => 1, 'is_medical' => 1 ) ) );
update_option( 'undt_company', array_merge( (array) $undt_company_backup6, array( 'job_title' => 'Zahnärztin', 'state' => 'BY', 'chamber_name' => '', 'chamber_url' => '', 'prof_rules' => '', 'chamber_ref' => '' ) ) );
UNDT_Store::flush();

$undt_box = UNDT_Company_View::chamber_box();
undt_t( false !== strpos( $undt_box, 'Bayerische Landeszahnärztekammer' ) && false !== strpos( $undt_box, 'data-undt-chamber-apply=' ), 'Vorschlag: Zahnärztin in Bayern' );

$undt_chamber = (string) key( UNDT_Professions::chambers( 'zahnarzt', 'BY' ) );
$answer       = UNDT_Autosave::save( 'quick:chamber', array( 'value' => $undt_chamber ) );
undt_t( ! is_wp_error( $answer ) && 'Bayerische Landeszahnärztekammer' === UNDT_Store::get( 'chamber_name' ) && $undt_chamber === UNDT_Store::get( 'chamber_ref' ), 'Übernommen über quick:chamber' );
undt_t( false !== strpos( do_shortcode( '[undt_impressum]' ), 'Bayerische Landeszahnärztekammer' ), 'Die Kammer steht danach im Impressum' );
undt_t( is_wp_error( UNDT_Autosave::save( 'quick:chamber', array( 'value' => 'aek-nr' ) ) ), 'Eine Kammer aus einem anderen Land wird abgelehnt' );

ob_start();
UNDT_Admin::instance()->render( 'company' );
$undt_page = ob_get_clean();
undt_t( false !== strpos( $undt_page, 'data-undt-profession-choice' ) && false !== strpos( $undt_page, '<optgroup label="Heilberufe">' ), 'Firma: Berufsbezeichnung als Auswahl' );
undt_t( false !== strpos( $undt_page, 'name="undt_company[chamber_ref]"' ) && false !== strpos( $undt_page, 'data-undt-part="chamberbox"' ), 'Firma: Kennung der Kammer und Vorschlagskasten' );

update_option( 'undt_seo', array_merge( (array) $undt_seo_backup6, array( 'schema_type' => 'Organization' ) ) );
UNDT_Content::flush();
ob_start();
UNDT_Admin::instance()->render( 'm_seo' );
$undt_page = ob_get_clean();
undt_t( false !== strpos( $undt_page, 'data-undt-type-apply="Dentist"' ), 'Suchmaschinen schlägt die Zahnarztpraxis vor' );

update_option( 'undt_profile', $undt_profile_backup6 );
update_option( 'undt_company', $undt_company_backup6 );
update_option( 'undt_seo', $undt_seo_backup6 );
UNDT_Store::flush();
UNDT_Content::flush();

/* ------------------------------------------- Neu in 0.6.0: Etappe 4 ---- */

undt_h( 'Bereiche, Einstellungen, Sichern' );

wp_set_current_user( 1 );
$undt_banner_backup4 = get_option( 'undt_banner' );
$undt_seo_backup4    = get_option( 'undt_seo' );
$undt_hours_backup4  = get_option( 'undt_hours' );

// Feiertage mit der echten Zeitzone.
$undt_feiertage = UNDT_Holidays::upcoming( 'BY', wp_date( 'Y-m-d' ) );
undt_t( count( $undt_feiertage ) >= 11 && count( $undt_feiertage ) <= 13, 'Bayern: die Feiertage der nächsten zwölf Monate: ' . count( $undt_feiertage ) );

// Oeffnungszeiten: geoeffnet-Schalter und die Antwort mit Vorschau.
$answer = UNDT_Autosave::save( 'module:hours', array( 'undt_hours' => array( 'days' => array( 'mon' => array( 'open' => '1', 'slots' => array( array( 'from' => '09:00', 'to' => '13:00' ) ) ), 'tue' => array( 'open' => '0' ) ) ) ) );
$undt_days = UNDT_Hours::normalize( UNDT_Content::value( 'hours', 'days' ) );
undt_t( ! is_wp_error( $answer ) && 0 === $undt_days['mon']['closed'] && 1 === $undt_days['tue']['closed'], 'Der Schalter „geöffnet“ wird als closed gespeichert' );
undt_t( ! is_wp_error( $answer ) && isset( $answer['parts']['preview'], $answer['parts']['today'] ) && false !== strpos( $answer['parts']['preview'], '09:00' ), 'Die Antwort bringt Vorschau und Statuszeile mit' );

// Banner: drei Arten, Zeitraum.
$answer = UNDT_Autosave::save( 'module:banner', array( 'undt_banner' => array( 'enabled' => '1', 'type' => 'info', 'text' => 'Betriebsferien', 'date_from' => wp_date( 'Y-m-d', time() + 2 * DAY_IN_SECONDS ) ) ) );
undt_t( ! UNDT_Blocks::banner_active() && false !== strpos( $answer['parts']['bannerhead'], 'Eingeplant' ), 'Ein eingeplantes Banner erscheint noch nicht' );
undt_t( false !== strpos( UNDT_Blocks::banner_markup( true ), 'undt-banner--info' ), 'Art Info als eigene Klasse' );

// Suchmaschinen: ein Feld fuer den Standort, Logo aus dem Theme.
UNDT_Autosave::save( 'module:seo', array( 'undt_seo' => array( 'geo' => '47.9312, 12.6458', 'schema_type' => 'Attorney' ) ) );
undt_t( '47.9312' === UNDT_Content::value( 'seo', 'geo_lat' ) && '12.6458' === UNDT_Content::value( 'seo', 'geo_lng' ), 'Koordinaten aus einem Feld' );
$undt_logo = wp_insert_attachment( array( 'post_title' => 'Logo', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ) );
set_theme_mod( 'custom_logo', $undt_logo );
undt_t( $undt_logo === UNDT_SchemaOrg::logo_id() && 'theme' === UNDT_SchemaOrg::logo_source(), 'Das Logo kommt aus dem Theme' );
remove_theme_mod( 'custom_logo' );
wp_delete_attachment( $undt_logo, true );

// Seiten in der Huelle.
foreach ( array( 'm_hours' => 'Feiertage und Urlaub', 'm_social' => 'Profil hinzufügen', 'm_banner' => 'Zeitraum', 'm_prices' => 'Gruppe hinzufügen', 'm_faq' => 'Frage hinzufügen', 'm_seo' => 'Standort auf der Karte', 'settings' => 'Wenn du das Plugin löschst', 'transfer' => 'Auswählen, was übernommen wird' ) as $undt_key => $undt_text ) {
	ob_start();
	UNDT_Admin::instance()->render( $undt_key );
	$undt_page = ob_get_clean();
	undt_t( false !== strpos( $undt_page, $undt_text ) && false !== strpos( $undt_page, 'undt-app' ), 'Seite ' . $undt_key . ' in der Hülle: ' . $undt_text );
}

// Einspielen mit Vorschau, Auswahl, Sicherung und Rueckgaengig.
$undt_file = UNDT_Transfer::export();
$undt_file['options']['undt_banner']['text'] = 'Aus der Datei';
$undt_preview = UNDT_Transfer::preview( $undt_file );
$undt_states  = array();
foreach ( $undt_preview['areas'] as $undt_area ) {
	$undt_states[ $undt_area['key'] ] = $undt_area['state'];
}
undt_t( 'change' === $undt_states['banner'] && 'same' === $undt_states['hours'], 'Vorschau: Banner ändert sich, Öffnungszeiten bleiben gleich' );
UNDT_Transfer::backup();
UNDT_Transfer::import( $undt_file, array( 'banner' ) );
undt_t( 'Aus der Datei' === UNDT_Content::value( 'banner', 'text' ), 'Nur der gewählte Bereich ist eingespielt' );
undt_t( is_array( get_option( UNDT_Transfer::OPTION_BACKUP ) ), 'Die Sicherung liegt in einer eigenen Option' );
global $wpdb;
$undt_autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", UNDT_Transfer::OPTION_BACKUP ) );
undt_t( in_array( $undt_autoload, array( 'no', 'off', 'auto-off' ), true ), 'Ohne Autoload: ' . $undt_autoload );
undt_t( UNDT_Transfer::undo() && 'Betriebsferien' === UNDT_Content::value( 'banner', 'text' ) && false === get_option( UNDT_Transfer::OPTION_BACKUP ), 'Rückgängig stellt den Stand wieder her und räumt auf' );

update_option( 'undt_banner', $undt_banner_backup4 );
update_option( 'undt_seo', $undt_seo_backup4 );
update_option( 'undt_hours', $undt_hours_backup4 );
UNDT_Content::flush();

/* ------------------------------------------------------- Deinstallation -- */

undt_h( 'Deinstallation' );

/*
 * Wie bei wp plugin uninstall --deactivate: das Plugin ist in diesem Aufruf noch
 * geladen. WordPress schreibt update_plugins nach dem Loeschen neu, und das darf
 * den Zwischenspeicher des Updaters nicht wieder anlegen. Steht am Ende, weil
 * danach alle Optionen fort sind.
 */
set_site_transient( 'undt_update_info', 'fail', 600 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', $file );
}

include WP_PLUGIN_DIR . '/' . $slug . '/uninstall.php';
set_site_transient( 'update_plugins', (object) array( 'response' => array(), 'no_update' => array() ) );

// Direkt in der Tabelle: get_option() liefert fuer registrierte Einstellungen
// auch nach dem Loeschen deren Voreinstellung statt false.
$undt_left = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'undt\_%'" );
undt_t( empty( $undt_left ), 'Deinstallation entfernt die Optionen' . ( $undt_left ? ': ' . implode( ', ', $undt_left ) : '' ) );
undt_t( false === get_site_transient( 'undt_update_info' ), 'Zwischenspeicher des Updaters bleibt nach der Deinstallation fort, auch wenn WordPress update_plugins neu schreibt' );
undt_t( false === get_transient( 'undt_usage' ), 'Der Zwischenspeicher für „wo verwendet“ ist fort' );
undt_t( '' === get_user_meta( 1, 'undt_copy_format', true ) && '' === get_user_meta( $undt_editor_id, 'undt_copy_format', true ), 'Auch die Wahl „Kopieren als“ ist bei allen Benutzern fort' );

/* ----------------------------------------------------------- Ergebnis ---- */

$lines[] = '';
$lines[] = $fail > 0
	? sprintf( '%d von %d Prüfungen FEHLGESCHLAGEN', $fail, $ok + $fail )
	: sprintf( 'Alle %d Prüfungen bestanden', $ok );
$lines[] = '';

file_put_contents( '/wordpress/build/dev/result.txt', implode( "\n", $lines ) );
