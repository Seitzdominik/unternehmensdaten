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

undt_t( $has_menu, 'Menüpunkt Unternehmensdaten angelegt' );

$expected_pages = array( 'undt', 'undt-profile', 'undt-m-hours', 'undt-m-prices', 'undt-m-social', 'undt-m-faq', 'undt-m-banner', 'undt-m-seo', 'undt-shortcodes', 'undt-audit', 'undt-settings' );
$actual_pages   = isset( $submenu['undt'] ) ? wp_list_pluck( $submenu['undt'], 2 ) : array();

foreach ( $expected_pages as $page ) {
	undt_t( in_array( $page, $actual_pages, true ), 'Unterseite ' . $page . ' vorhanden' );
}

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

// F-12: Der Aufruf der Profilseite schreibt nichts, erst das Speichern.
delete_option( 'undt_setup_done' );
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/profile.php';
ob_end_clean();
undt_t( false === get_option( 'undt_setup_done' ), 'Aufruf der Profilseite schreibt keine Option' );

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
	(bool) preg_match( '/value="' . (int) $draft_id . '"[^>]*selected/', undt_select_of( $company_html, 'page_privacy' ) ),
	'Seitenauswahl enthält die verknüpfte Entwurfsseite und wählt sie aus'
);

// F-07: Eine erst später angelegte Modul-Option bleibt aus dem Autoload.
delete_option( 'undt_faq' );
update_option( 'undt_faq', array( 'items' => array( array( 'group' => '', 'question' => 'Neu?', 'answer' => 'Ja.' ) ) ) );
$faq_autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'undt_faq' ) );
undt_t( in_array( (string) $faq_autoload, array( 'no', 'off' ), true ), 'Neu angelegte Option undt_faq bleibt aus dem Autoload: ' . var_export( $faq_autoload, true ) );

/* ------------------------------------------------------- Neu in 0.5.0 ---- */

undt_h( 'Seitenfelder, Sprache und dynamische Daten' );

// Ein eigener Inhaltstyp fuer Rechtstexte und einer, der nicht in Menues erscheint.
register_post_type( 'undt_recht', array( 'public' => true, 'label' => 'Rechtstexte' ) );
register_post_type( 'undt_vorlage', array( 'public' => true, 'label' => 'Vorlagen', 'show_in_nav_menus' => false ) );

// So registriert Bricks 2.4 seine Vorlagen: oeffentlich und fuer Menues freigegeben.
if ( ! post_type_exists( 'bricks_template' ) ) {
	register_post_type( 'bricks_template', array( 'public' => true, 'label' => 'My templates', 'exclude_from_search' => true, 'show_in_nav_menus' => true ) );
}

$recht_id = wp_insert_post( array( 'post_type' => 'undt_recht', 'post_status' => 'publish', 'post_title' => 'AGB als Rechtstext' ) );
wp_insert_post( array( 'post_type' => 'undt_vorlage', 'post_status' => 'publish', 'post_title' => 'Nur eine Vorlage' ) );

$link_types = UNDT_Fields::link_post_types();
undt_t( isset( $link_types['page'], $link_types['undt_recht'] ), 'Seitenfelder wählen aus Seiten und eigenen Inhaltstypen: ' . implode( ', ', array_keys( $link_types ) ) );
undt_t( ! isset( $link_types['undt_vorlage'] ) && ! isset( $link_types['bricks_template'] ) && ! isset( $link_types['post'] ) && ! isset( $link_types['attachment'] ), 'Vorlagen, Bricks-Vorlagen, Beiträge und Medien fehlen in der Auswahl' );

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
$terms_select = undt_select_of( $company_html, 'page_terms' );

undt_t( (bool) preg_match( '/<optgroup label="Rechtstexte">[^<]*<option[^>]*value="' . (int) $recht_id . '"[^>]*selected/', $terms_select ), 'Auswahl zeigt den Rechtstext in eigener Gruppe und wählt ihn' );
undt_t( false !== strpos( $terms_select, '<optgroup label="' . esc_attr( get_post_type_object( 'page' )->labels->name ) . '">' ), 'Seiten stehen in eigener Gruppe' );
undt_t( false === strpos( $company_html, 'Nur eine Vorlage' ), 'Vorlagen stehen nicht in der Auswahl' );
undt_t( (bool) preg_match( '/name="undt_company\[page_terms\]\[url\]" value=""[^>]*data-undt-link-url hidden/', $company_html ), 'Adressfeld neben einer Auswahl ist leer und verborgen' );
undt_t( (bool) preg_match( '/name="undt_company\[page_accessibility\]\[url\]" value="\/barrierefreiheit"[^>]*data-undt-link-url \/>/', $company_html ), 'Adressfeld zeigt die eigene Adresse und ist sichtbar' );
undt_t( false !== strpos( undt_select_of( $company_html, 'page_accessibility' ), '<option value="url" selected' ), '„Eigene Adresse …“ ist gewählt' );
undt_t( '' === $wpdb->last_error, 'Auswahl ohne Datenbankfehler aufgebaut' . ( '' === $wpdb->last_error ? '' : ': ' . $wpdb->last_error ) );
undt_t( false !== strpos( $company_html, 'value="Änderungen speichern"' ) && false === strpos( $company_html, 'Save Changes' ), 'Speichern-Knopf ist deutsch' );
undt_t( false !== strpos( $company_html, 'data-undt-copy="{undt_company_name}"' ) && false !== strpos( $company_html, 'data-undt-copy="{options.undt.company_name}"' ), 'Unter den Feldern stehen Kopierknöpfe für Bricks und Etch' );

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
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/shortcodes.php';
$shortcodes_html = ob_get_clean();
undt_t( false !== strpos( $shortcodes_html, 'id="undt-panel-dynamic"' ) && false !== strpos( $shortcodes_html, 'data-undt-copy="{{ undt.phone }}"' ) && false !== strpos( $shortcodes_html, 'data-undt-copy="{options.undt.phone}"' ), 'Referenz nennt die dynamischen Daten' );

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
undt_t( false !== strpos( $banner_html, 'data-undt-copy="{undt_banner_show}"' ) && false !== strpos( $banner_html, 'data-undt-copy="{options.undt.banner_text}"' ), 'Banner-Seite hat Kopierknöpfe für Bricks und Etch' );
undt_t( false !== strpos( $banner_html, 'undt-copy--bricks' ) && false !== strpos( $banner_html, 'viewBox="0 0 79 101"' ), 'Kopierknöpfe zeigen die Logos' );

$undt_slug = 'social';
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/module.php';
$social_html = ob_get_clean();
undt_t( false !== strpos( $social_html, 'class="undt-select-icon__preview"' ) && false !== strpos( $social_html, 'class="undt-icon-library" hidden' ), 'Social-Seite zeigt Symbole neben der Plattform-Auswahl' );

/* ---------------------------------------------- Oberfläche des Backends -- */

undt_h( 'Weiße Karte, Registerkarten und zwei Spalten' );

ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/company.php';
$ui_html = ob_get_clean();

undt_t( false !== strpos( $ui_html, '<div class="undt-box">' ) && false !== strpos( $ui_html, 'class="undt-box__body"' ) && false !== strpos( $ui_html, 'class="undt-box__footer"' ), 'Stammdaten stehen in einer Karte mit eigenem Fuß' );
undt_t( false !== strpos( $ui_html, 'class="undt-tab is-active"' ) && false === strpos( $ui_html, 'nav-tab' ), 'Registerkarten tragen die eigenen Klassen' );
undt_t( false !== strpos( $ui_html, 'class="large-text"' ) && false === strpos( $ui_html, 'class="regular-text"' ), 'Alle Textfelder nehmen die Breite der Spalte ein' );
undt_t( 1 === substr_count( $ui_html, 'class="undt-box__footer"' ) && false !== strpos( $ui_html, 'id="submit"' ), 'Genau eine Schaltfläche zum Speichern, im Fuß der Karte' );

ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/profile.php';
$ui_html = ob_get_clean();

undt_t( false !== strpos( $ui_html, '<div class="undt-cards">' ), 'Rechtsform: die Fragen stehen in einem Raster' );
undt_t( false !== strpos( $ui_html, 'undt-card--full' ) && substr_count( $ui_html, '<div class="undt-card undt-question">' ) >= 6, 'Die Leitfrage nimmt beide Spalten, die Fragen mit Schalter je eine Zelle' );
undt_t( false !== strpos( $ui_html, 'data-undt-when=' ), 'Abhängige Fragen behalten ihre Bedingung' );

$undt_slug = 'hours';
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/module.php';
$ui_html = ob_get_clean();

undt_t( false !== strpos( $ui_html, 'class="undt-box__footer"' ), 'Auch ein Inhaltsbereich speichert im Fuß der Karte' );
undt_t( false !== strpos( $ui_html, 'class="undt-input-time"' ) && false === strpos( $ui_html, 'class="regular-text"' ), 'Uhrzeiten bleiben schmal, alles andere nicht' );

$undt_slug = 'social';
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/module.php';
$ui_html = ob_get_clean();

undt_t( 1 === substr_count( $ui_html, 'undt-row--toggles' ) && 5 === substr_count( $ui_html, '<div class="undt-card">' ), 'Social Media: die fünf Schalter stehen in zwei Spalten' );
undt_t( false !== strpos( $company_html, '<span class="undt-copy-row__keys">' ), 'Die Kopierknöpfe für Bricks und Etch stehen rechts im Feld' );

// Jede Ansicht einmal rendern, um Fehler in den Templates zu finden.
$views = array(
	'company.php'     => 'Stammdaten',
	'profile.php'     => 'Rechtsform & Umfang',
	'shortcodes.php'  => 'Shortcodes',
	'audit.php'       => 'Prüfung',
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

$undt_slug = 'settings';
ob_start();
include WP_PLUGIN_DIR . '/' . $slug . '/admin/views/settings.php';
$transfer_html = ob_get_clean();

undt_t( false !== strpos( $transfer_html, 'value="undt_export"' ) && false !== strpos( $transfer_html, 'value="undt_import"' ), 'Die Einstellungsseite bietet beides an' );
undt_t( false !== strpos( $transfer_html, 'enctype="multipart/form-data"' ) && false !== strpos( $transfer_html, 'name="undt_file"' ), 'Mit einem Feld für die Datei' );

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

/* ----------------------------------------------------------- Ergebnis ---- */

$lines[] = '';
$lines[] = $fail > 0
	? sprintf( '%d von %d Prüfungen FEHLGESCHLAGEN', $fail, $ok + $fail )
	: sprintf( 'Alle %d Prüfungen bestanden', $ok );
$lines[] = '';

file_put_contents( '/wordpress/build/dev/result.txt', implode( "\n", $lines ) );
