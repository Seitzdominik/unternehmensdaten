<?php
/**
 * Pruefungen fuer 0.6.0, Etappe 1: Navigation, Rechte, automatisches Speichern
 * und die Bausteine der Oberflaeche.
 *
 * Geprueft wird ohne WordPress: wer welche Seiten sieht, wer was speichern
 * darf, dass der Endpunkt Nonce und Recht verlangt und die Sanitizer der
 * Optionen durchlaeuft. Ob die Seiten in WordPress wirklich so erscheinen und
 * ein fremder Benutzer abgewiesen wird, zeigt dev/verify.php.
 */
require __DIR__ . '/harness.php';
require UNDT_TEST_BASE . 'admin/class-undt-copy.php';

/*
 * wp_send_json_* beenden in WordPress die Anfrage. Hier werfen sie, damit sich
 * die Antwort pruefen laesst.
 */
class UNDT_Test_Json extends Exception {
	public $success;
	public $data;
	public $status;
	public function __construct( $success, $data, $status ) {
		parent::__construct( 'json' );
		$this->success = $success;
		$this->data    = $data;
		$this->status  = $status;
	}
}
function wp_send_json_success( $data = null, $status = null ) { throw new UNDT_Test_Json( true, $data, $status ); }
function wp_send_json_error( $data = null, $status = null ) { throw new UNDT_Test_Json( false, $data, $status ); }
function check_ajax_referer( $action, $field, $die = true ) {
	return isset( $_POST[ $field ] ) && wp_create_nonce( $action ) === $_POST[ $field ];
}
// Die Antwort auf das Speichern bringt die Vorschau eines Bereichs mit.
function do_shortcode( $code ) { return '<p>Ausgabe von ' . esc_html( $code ) . '</p>'; }
function undt_handle( array $post ) {
	$_POST = $post;
	try {
		UNDT_Autosave::handle();
	} catch ( UNDT_Test_Json $answer ) {
		return $answer;
	}
	return null;
}

function undt_as( $role ) {
	$caps = array(
		'administrator' => array( 'manage_options' => true, 'edit_posts' => true ),
		'editor'        => array( 'edit_posts' => true ),
		'author'        => array( 'edit_posts' => true ),
		'subscriber'    => array( 'read' => true ),
	);
	$GLOBALS['undt_user'] = array( 'roles' => array( $role ), 'caps' => $caps[ $role ] );
}

function undt_html( $callback ) {
	ob_start();
	$callback();
	return (string) ob_get_clean();
}

$undt_base = UNDT_TEST_BASE;

UNDT_Access::register();
undt_set_modules( array( 'hours' => 1, 'prices' => 0, 'social' => 1, 'faq' => 0, 'banner' => 1, 'seo' => 1 ) );
undt_seed( array( 'legal_form' => 'gmbh', 'vat_status' => 'standard' ), array( 'company_name' => 'Muster GmbH' ) );
$GLOBALS['undt_options']['undt_setup_done'] = 1;

// Die Sanitizer so anmelden, wie WordPress es auf admin_init tut.
UNDT_Admin::instance()->settings();

/* ------------------------------------------------------ 1. Das Recht ---- */

undt_head( 'Recht zur Pflege der Angaben' );

undt_as( 'administrator' );
undt_ok( current_user_can( UNDT_Access::CAP ), 'Admins pflegen immer' );

undt_as( 'editor' );
undt_ok( ! current_user_can( UNDT_Access::CAP ), 'Ein Redakteur ohne Freigabe pflegt nichts' );

$GLOBALS['undt_options']['undt_settings']['roles'] = array( 'editor' );
UNDT_Modules::flush();
undt_ok( current_user_can( UNDT_Access::CAP ), 'Mit Freigabe seiner Rolle pflegt er' );
undt_ok( ! current_user_can( 'manage_options' ), 'Und bekommt dabei kein Admin-Recht' );

undt_as( 'author' );
undt_ok( ! current_user_can( UNDT_Access::CAP ), 'Eine andere Rolle bleibt aussen vor' );

$roles = UNDT_Access::choosable_roles();
undt_ok( isset( $roles['editor'], $roles['author'], $roles['shop_manager'] ), 'Waehlbar sind Rollen, die Inhalte bearbeiten' );
undt_ok( ! isset( $roles['administrator'] ) && ! isset( $roles['subscriber'] ), 'Nicht Admins (fest) und nicht Abonnenten' );

undt_ok( array( 'editor' ) === UNDT_Access::sanitize_roles( array( 'editor' => '1', 'author' => '0' ) ), 'Schalter aus dem Formular werden zur Liste' );
undt_ok( array( 'editor' ) === UNDT_Access::sanitize_roles( array( 'editor', 'subscriber', 'administrator', '<b>x</b>' ) ), 'Unbekannte, ungeeignete und Admin-Rollen fallen heraus' );
undt_ok( array() === UNDT_Access::sanitize_roles( 'editor' ), 'Kein Feld, keine Rolle' );

$settings = UNDT_Modules::sanitize_settings( array( 'modules' => array( 'hours' => 1 ), 'roles' => array( 'author' => 1 ) ) );
undt_ok( array( 'author' ) === $settings['roles'], 'Die Einstellungen speichern die Rollen mit' );
undt_ok( array() === UNDT_Modules::settings_defaults()['roles'], 'Voreingestellt pflegen nur Admins' );

/* --------------------------------------------------- 2. Navigation ---- */

undt_head( 'Navigation je Rolle' );

undt_as( 'administrator' );
$pages = UNDT_Nav::pages();
undt_ok( array( 'overview', 'company', 'm_hours', 'm_social', 'm_banner', 'm_seo', 'embed', 'transfer', 'settings', 'help' ) === array_keys( $pages ), 'Reihenfolge wie im Entwurf, abgeschaltete Bereiche fehlen' );
undt_ok( 'undt' === $pages['overview']['slug'] && 'undt-company' === $pages['company']['slug'], 'Die Uebersicht ist der Menuepunkt selbst, Firma hat eine eigene Adresse' );
undt_ok( 'Suchmaschinen' === $pages['m_seo']['title'] && 'search' === $pages['m_seo']['icon'], 'SEO heisst jetzt Suchmaschinen' );
undt_ok( UNDT_Nav::HELP_URL === UNDT_Nav::url( $pages['help'] ) && false !== strpos( UNDT_Nav::HELP_URL, 'github.com/Seitzdominik/unternehmensdaten' ), 'Hilfe fuehrt zur readme auf GitHub' );
undt_ok( ! isset( $pages['profile'] ), 'Die Rechtsform ist keine eigene Seite mehr, sie steht als Karte auf „Firma“' );

$groups = array();
foreach ( UNDT_Nav::visible() as $key => $page ) {
	$groups[ $page['group'] ][] = $key;
}
undt_ok( array( 'overview' ) === $groups[''] && array( 'embed' ) === $groups['use'] && array( 'transfer', 'settings', 'help' ) === $groups['more'], 'Gruppen: ohne Titel, Einrichten, Verwenden, Mehr' );

undt_set_modules( array( 'hours' => 1, 'prices' => 1, 'social' => 1, 'faq' => 1, 'banner' => 1, 'seo' => 1 ) );
$GLOBALS['undt_options']['undt_settings']['roles'] = array( 'editor' );
UNDT_Modules::flush();
undt_ok( array( 'm_hours', 'm_prices', 'm_social', 'm_faq', 'm_banner', 'm_seo' ) === array_values( array_filter( array_keys( UNDT_Nav::pages() ), static function ( $k ) { return 0 === strpos( $k, 'm_' ); } ) ), 'Eingeschaltet stehen Preise und FAQ an ihrem Platz, Suchmaschinen zuletzt' );

undt_as( 'editor' );
$visible = array_keys( UNDT_Nav::visible() );
undt_ok( array( 'overview', 'company', 'm_hours', 'm_prices', 'm_social', 'm_faq', 'm_banner', 'help' ) === $visible, 'Ein Redakteur sieht Uebersicht, Firma, die Inhaltsbereiche und Hilfe' );
undt_ok( ! UNDT_Access::is_admin(), 'Die Rechtsform aendern bleibt ihm verschlossen' );

undt_as( 'author' );
undt_ok( array() === array_keys( UNDT_Nav::visible() ), 'Eine Rolle ohne Freigabe sieht nichts' );

undt_set_modules( array( 'hours' => 1, 'prices' => 0, 'social' => 1, 'faq' => 0, 'banner' => 1, 'seo' => 1 ) );
$GLOBALS['undt_options']['undt_settings']['roles'] = array( 'editor' );
UNDT_Modules::flush();

/* --------------------------------------------- 3. Huelle und Menue ---- */

undt_head( 'Seitenleiste, Kopfleiste, Vollbild-Menue' );

undt_as( 'administrator' );
$missing = UNDT_Audit::quick_count();
$html    = undt_html( static function () { UNDT_Nav::open( 'company' ); echo '<p>INHALT</p>'; UNDT_Nav::close( 'company' ); } );

undt_ok( $missing > 0, 'Fuer die Probe fehlen Pflichtangaben' );
undt_ok( false !== strpos( $html, '<aside class="undt-side">' ) && false !== strpos( $html, '<main class="undt-main" id="undt-main"><p>INHALT</p></main>' ), 'Seitenleiste links, Inhalt im main-Element' );
undt_ok( 2 === substr_count( $html, 'aria-current="page"' ), 'Die aktuelle Seite ist ausgezeichnet, einmal je Navigation' );
undt_ok( 2 === substr_count( $html, 'class="undt-nav__item is-current" href="https://example.test/wp-admin/admin.php?page=undt-company" aria-current="page"' ), 'In Seitenleiste und Vollbild-Menue dieselbe' );
undt_ok( 2 === substr_count( $html, '<div class="undt-nav__group">Einrichten</div>' ) && 2 === substr_count( $html, '<div class="undt-nav__group">Verwenden</div>' ), 'Gruppen mit Titel' );
undt_ok( false !== strpos( $html, '<span class="undt-badge" data-undt-badge>' . $missing . '<span class="screen-reader-text"> offene Punkte</span></span>' ), 'Der Zaehler sitzt auf der Kachel und ist vorlesbar' );
undt_ok( false !== strpos( $html, 'data-undt-badge-for="company"' ), 'Das Skript findet die Kachel zum Nachfuehren' );
undt_ok( false === strpos( $html, 'undt-tab-dot' ), 'Keine Punkte vor Titeln mehr' );
undt_ok( false !== strpos( $html, 'target="_blank" rel="noopener noreferrer"' ) && false !== strpos( $html, '(öffnet GitHub in einem neuen Tab)' ), 'Hilfe verlaesst WordPress und sagt es' );
undt_ok( 2 === substr_count( $html, UNDT_Ui_Icons::PATHS['out'] ), 'Pfeil-Regel: arrow-square-out nur an der Hilfe' );
undt_ok( count( UNDT_Nav::visible() ) - 1 === substr_count( $html, UNDT_Ui_Icons::PATHS['caret'] ), 'caret-right an jeder anderen Seite, nur im Vollbild-Menue' );
undt_ok( false !== strpos( $html, '<button type="button" class="undt-mbar__menu" aria-expanded="false" aria-controls="undt-mmenu" data-undt-menu-open>Menü<span data-undt-badge-total><span class="undt-badge"' ), 'Kopfleiste: Textknopf „Menü“ mit Gesamtzaehler' );
undt_ok( false !== strpos( $html, '<div class="undt-mmenu" id="undt-mmenu" role="dialog" aria-modal="true" aria-label="Menü" hidden>' ), 'Das Vollbild-Menue ist ein Dialog, zunaechst verborgen' );
undt_ok( false !== strpos( $html, 'aria-label="Menü schließen" data-undt-menu-close>' ), 'Mit beschriftetem Schliessen-Knopf' );
undt_ok( false !== strpos( $html, 'Version ' . UNDT_VERSION ), 'Kopf der Seitenleiste nennt die Version' );
undt_ok( false !== strpos( $html, '<div class="undt-side__name">Firmendaten</div>' ) && false !== strpos( $html, '<span class="undt-mmenu__name">Firmendaten</span>' ), 'Name „Firmendaten“ in Seitenleiste und Menue' );

undt_ok( 2 === substr_count( $html, 'data-undt-quick="banner"' ), 'Infobanner zeigen in Seitenleiste und Vollbild-Menue' );
undt_ok( 1 === substr_count( $html, 'data-undt-quick="copy_format"' ), 'Kopieren als nur in der Seitenleiste' );
undt_ok( false !== strpos( $html, 'role="switch" aria-checked="false"' ), 'Der Banner-Schalter ist ein Schalter mit Zustand' );
undt_ok( false !== strpos( $html, '<option value="gutenberg" selected="selected">Block-Editor</option>' ), 'Vorausgewaehlt ist das erkannte Werkzeug, ohne Builder der Block-Editor' );

undt_as( 'editor' );
$html = undt_html( static function () { UNDT_Nav::open( 'overview' ); UNDT_Nav::close( 'overview' ); } );
undt_ok( false === strpos( $html, 'data-undt-quick="copy_format"' ), 'Redakteure sehen kein „Kopieren als“' );
undt_ok( false !== strpos( $html, 'data-undt-quick="banner"' ), 'Aber den Banner-Schalter' );
undt_ok( false === strpos( $html, 'page=undt-settings' ) && false === strpos( $html, 'page=undt-embed' ) && false === strpos( $html, 'page=undt-m-seo' ), 'Und keine Admin-Seiten' );
undt_ok( false === strpos( $html, 'undt-nav__group">Verwenden' ), 'Leere Gruppen entfallen' );

/* ----------------------------------------------- 4. Kopierformate ---- */

undt_head( 'Kopieren als' );

undt_as( 'administrator' );
$GLOBALS['undt_options']['active_plugins'] = array( 'etch/etch.php' );
$formats = UNDT_Copy::formats();
undt_ok( array( 'etch', 'bricks', 'shortcode', 'gutenberg' ) === array_keys( $formats ), 'Angeboten wird, was installiert ist' );
undt_ok( $formats['etch']['active'] && ! $formats['bricks']['active'] && ! $formats['gutenberg']['active'], 'Aktiv ist das Werkzeug, mit dem gebaut wird' );
undt_ok( 'etch' === UNDT_Copy::format(), 'Ohne Wahl gilt das erkannte Werkzeug' );

$GLOBALS['undt_options']['active_plugins'] = array();
$GLOBALS['undt_template']                  = 'bricks';
undt_ok( 'bricks' === UNDT_Copy::format(), 'Mit Bricks als Theme ist es Bricks' );
$GLOBALS['undt_template'] = 'twentytwentyfive';
$GLOBALS['undt_themes']   = array();
undt_ok( 'gutenberg' === UNDT_Copy::format() && ! isset( UNDT_Copy::formats()['bricks'] ), 'Ohne Builder ist es der Block-Editor, Bricks fehlt, wenn es nicht installiert ist' );
$GLOBALS['undt_options']['active_plugins'] = array( 'etch/etch.php' );
unset( $GLOBALS['undt_template'], $GLOBALS['undt_themes'] );

/* ------------------------------------- 5. Automatisches Speichern ---- */

undt_head( 'Speichern: Nonce, Recht, Sanitizer' );

$nonce = wp_create_nonce( UNDT_Autosave::ACTION );

$answer = undt_handle( array( 'target' => 'company', 'undt_company' => array( 'company_name' => 'X' ) ) );
undt_ok( $answer && ! $answer->success && 403 === $answer->status && 'nonce' === $answer->data['code'], 'Ohne Nonce: abgelehnt mit 403' );

$answer = undt_handle( array( 'nonce' => 'falsch', 'target' => 'company', 'undt_company' => array( 'company_name' => 'X' ) ) );
undt_ok( $answer && ! $answer->success && 403 === $answer->status, 'Mit falscher Nonce: abgelehnt' );
undt_ok( 'Muster GmbH' === UNDT_Store::get( 'company_name' ), 'Und nichts geschrieben' );

$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'irgendwas' ) );
undt_ok( $answer && ! $answer->success && 400 === $answer->status && 'target' === $answer->data['code'], 'Ein unbekanntes Ziel wird abgelehnt' );

$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'company', 'undt_company' => array( 'company_name' => '<b>Neue</b> GmbH', 'street' => 'Weg 1' ) ) );
undt_ok( $answer && $answer->success, 'Ein Admin speichert die Firma' );
UNDT_Store::flush();
undt_ok( 'Neue GmbH' === UNDT_Store::get( 'company_name' ), 'Der Sanitizer der Option ist gelaufen' );
undt_ok( 'nonce-undt_save' === $answer->data['nonce'] && false === $answer->data['reload'], 'Die Antwort bringt eine frische Nonce mit' );
undt_ok( isset( $answer->data['badges']['company'] ) && $answer->data['badges']['company'] === UNDT_Audit::quick_count() && $answer->data['badges']['company'] < $missing, 'Und den neuen Zaehler, eine Angabe weniger offen' );
undt_ok( $answer->data['total'] === $answer->data['badges']['company'], 'Der Gesamtzaehler stimmt' );

$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'company' ) );
UNDT_Store::flush();
undt_ok( $answer && $answer->success && 'Neue GmbH' === UNDT_Store::get( 'company_name' ), 'Was nicht mitkommt, bleibt stehen: der Sanitizer der Firma ergaenzt nur' );

undt_as( 'editor' );
$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'company', 'undt_company' => array( 'company_name' => 'Vom Redakteur' ) ) );
UNDT_Store::flush();
undt_ok( $answer && $answer->success && 'Vom Redakteur' === UNDT_Store::get( 'company_name' ), 'Ein freigegebener Redakteur speichert die Firma' );

$before = $GLOBALS['undt_options']['undt_profile'];
$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'profile', 'undt_profile' => array( 'legal_form' => 'ug' ) ) );
undt_ok( $answer && ! $answer->success && 403 === $answer->status && $before === $GLOBALS['undt_options']['undt_profile'], 'Aber nicht die Rechtsform' );

$before = $GLOBALS['undt_options']['undt_settings'];
$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'settings', 'undt_settings' => array( 'roles' => array( 'author' => 1 ) ) ) );
undt_ok( $answer && ! $answer->success && 403 === $answer->status && $before === $GLOBALS['undt_options']['undt_settings'], 'Nicht die Einstellungen' );

$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'module:seo', 'undt_seo' => array() ) );
undt_ok( $answer && ! $answer->success && 403 === $answer->status, 'Nicht die Suchmaschinen' );

$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'module:hours', 'undt_hours' => array( 'note' => '<script>x</script>Nach Vereinbarung' ) ) );
UNDT_Content::flush();
undt_ok( $answer && $answer->success && 'Nach Vereinbarung' === UNDT_Content::value( 'hours', 'note' ), 'Aber die Oeffnungszeiten, durch ihren Sanitizer' );

$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'module:faq', 'undt_faq' => array() ) );
undt_ok( $answer && ! $answer->success && 400 === $answer->status, 'Ein abgeschalteter Bereich nimmt nichts an' );

$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'quick:banner', 'value' => '1' ) );
UNDT_Content::flush();
undt_ok( $answer && $answer->success && 1 === (int) UNDT_Content::value( 'banner', 'enabled' ), 'Der Banner-Schalter in den Schnelleinstellungen' );
undt_handle( array( 'nonce' => $nonce, 'target' => 'quick:banner', 'value' => '0' ) );
UNDT_Content::flush();
undt_ok( 0 === (int) UNDT_Content::value( 'banner', 'enabled' ), 'Schaltet auch wieder aus' );

$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'quick:copy_format', 'value' => 'bricks' ) );
undt_ok( $answer && ! $answer->success && 403 === $answer->status, 'Kopieren als bleibt Admins vorbehalten' );

undt_as( 'author' );
$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'company', 'undt_company' => array( 'company_name' => 'Fremd' ) ) );
UNDT_Store::flush();
undt_ok( $answer && ! $answer->success && 403 === $answer->status && 'Vom Redakteur' === UNDT_Store::get( 'company_name' ), 'Eine Rolle ohne Freigabe speichert nichts, trotz gueltiger Nonce' );
$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'quick:banner', 'value' => '1' ) );
undt_ok( $answer && ! $answer->success && 403 === $answer->status, 'Auch nicht das Banner' );

undt_as( 'administrator' );
$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'quick:copy_format', 'value' => 'bricks' ) );
undt_ok( $answer && $answer->success && 'bricks' === UNDT_Copy::format(), 'Ein Admin waehlt sein Format, gemerkt am Benutzer' );
$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'quick:copy_format', 'value' => 'oxygen' ) );
undt_ok( $answer && ! $answer->success && 400 === $answer->status && 'bricks' === UNDT_Copy::format(), 'Ein unbekanntes Format wird abgelehnt' );

$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'settings', 'undt_settings' => array( 'modules' => array( 'hours' => 1, 'social' => 1, 'banner' => 1, 'seo' => 1 ), 'roles' => array( 'editor' => 1 ), 'updates' => 1 ) ) );
undt_ok( $answer && $answer->success && false === $answer->data['reload'], 'Einstellungen ohne andere Bereiche: kein Neuladen' );
$answer = undt_handle( array( 'nonce' => $nonce, 'target' => 'settings', 'undt_settings' => array( 'modules' => array( 'hours' => 1, 'social' => 1, 'banner' => 1, 'seo' => 1, 'faq' => 1 ), 'roles' => array( 'editor' => 1 ), 'updates' => 1 ) ) );
undt_ok( $answer && true === $answer->data['reload'], 'Ein Bereich mehr aendert das Menue, die Seite laedt neu' );
undt_ok( UNDT_Modules::is_active( 'faq' ) && array( 'editor' ) === UNDT_Modules::settings()['roles'], 'Und beides ist gespeichert' );

$admin = (string) file_get_contents( $undt_base . 'admin/class-undt-autosave.php' );
undt_ok( false !== strpos( $admin, "add_action( 'wp_ajax_' . self::ACTION" ) && false === strpos( $admin, 'wp_ajax_nopriv' ), 'Nur fuer Angemeldete, kein nopriv' );
undt_ok( false === strpos( $admin, 'register_rest_route' ), 'Kein REST-Endpunkt' );

/* ------------------------------------ 6. options.php als Rueckfallweg ---- */

undt_head( 'Rechte auch beim Speichern ohne Skript' );

undt_ok( UNDT_Access::CAP === apply_filters( 'option_page_capability_' . UNDT_Admin::GROUP_COMPANY, 'manage_options' ), 'Firma: Recht zur Pflege' );
undt_ok( 'manage_options' === apply_filters( 'option_page_capability_' . UNDT_Admin::GROUP_PROFILE, 'x' ), 'Rechtsform: Admin-Recht' );
undt_ok( 'manage_options' === apply_filters( 'option_page_capability_' . UNDT_Admin::GROUP_SETTINGS, 'x' ), 'Einstellungen: Admin-Recht' );
undt_ok( UNDT_Access::CAP === apply_filters( 'option_page_capability_' . UNDT_Admin::module_group( 'banner' ), 'x' ), 'Infobanner: Recht zur Pflege' );
undt_ok( 'manage_options' === apply_filters( 'option_page_capability_' . UNDT_Admin::module_group( 'seo' ), 'x' ), 'Suchmaschinen: Admin-Recht' );

undt_ok( array( 'undt-shortcodes' => 'undt-embed', 'undt-audit' => 'undt', 'undt-profile' => 'undt-company' ) === UNDT_Admin::OLD_SLUGS, 'Alte Adressen fuehren auf Einbinden, die Uebersicht und Firma' );

/* --------------------------------------------------- 7. Bausteine ---- */

undt_head( 'Bausteine und Icons' );

$html = undt_html( static function () { UNDT_Ui::page_head( 'Firma', true ); } );
undt_ok( false !== strpos( $html, '<h1 class="undt-head__title">Firma</h1>' ), 'Seitenkopf mit Titel' );
undt_ok( false !== strpos( $html, 'data-undt-save data-state="saved" role="status"' ), 'Speicherstatus, anfangs gespeichert' );
undt_ok( false !== strpos( $html, 'Wird gespeichert …' ) && false !== strpos( $html, 'Alle Änderungen gespeichert' ) && false !== strpos( $html, 'Nicht gespeichert. Neuer Versuch in 10 Sekunden.' ) && false !== strpos( $html, 'data-undt-save-retry>Jetzt versuchen</button>' ), 'Alle drei Zustaende samt „Jetzt versuchen“' );
undt_ok( false !== strpos( $html, '<hr class="wp-header-end">' ), 'Fremde Hinweise landen unter dem Kopf, nicht darin' );
undt_ok( false === strpos( undt_html( static function () { UNDT_Ui::page_head( 'Einbinden' ); } ), 'data-undt-save' ), 'Ohne Formular kein Speicherstatus' );

$html = undt_html( static function () { UNDT_Ui::status( 'open', '5 Pflichtangaben fehlen', 'Noch offen.' ); } );
undt_ok( false !== strpos( $html, 'undt-status--open' ) && false !== strpos( $html, '<span class="screen-reader-text">Pflicht: </span>' ), 'Statuszeile: der Zustand steht auch im Text' );
$html = undt_html( static function () { UNDT_Ui::info( 'Bleibt in deiner Datenbank.', 'lock' ); } );
undt_ok( 0 === strpos( $html, '<div class="undt-info">' ) && false !== strpos( $html, 'aria-hidden="true"' ), 'Info-Card mit stummem Symbol' );
$html = undt_html( static function () { UNDT_Ui::amber( 'Pflichtangabe fehlt' ); } );
undt_ok( '<p class="undt-amber"><span class="undt-amber__dot" aria-hidden="true"></span>Pflichtangabe fehlt</p>' === $html, 'Bernstein-Zeile' );
undt_ok( '' === UNDT_Ui::badge_html( 0 ) && false !== strpos( UNDT_Ui::badge_html( 1 ), '1<span class="screen-reader-text"> offener Punkt</span>' ), 'Zaehler: keiner bei null, Einzahl bei eins' );
$html = undt_html( static function () { UNDT_Ui::segment( 'x[p]', 'p', array( 'a' => 'Automatisch', 'b' => 'Immer' ), 'b', 'Ausgabe', array( 'a' => 'erkannt' ) ); } );
undt_ok( false !== strpos( $html, 'role="radiogroup" aria-label="Ausgabe"' ) && 2 === substr_count( $html, 'type="radio"' ) && false !== strpos( $html, 'value="b" checked="checked"' ) && false !== strpos( $html, 'undt-segment__mark">erkannt' ), 'Segment-Schalter aus echten Radioknoepfen' );
$html = undt_html( static function () { UNDT_Ui::details_open( 'Darstellung', '„Uhr“, Sprache' ); echo 'X'; UNDT_Ui::details_close(); } );
undt_ok( 0 === strpos( $html, '<details class="undt-details">' ) && false !== strpos( $html, '<span class="undt-optional">· optional</span>' ) && false !== strpos( $html, 'X</div></details>' ), 'Aufklappbar mit „· optional“' );
$html = undt_html( static function () { UNDT_Ui::steps( array( array( 'title' => 'Rechtsform', 'state' => 'done' ), array( 'title' => 'Fragen', 'state' => 'current', 'action' => '<a href="#">Los</a>' ), array( 'title' => 'Impressum' ) ) ); } );
undt_ok( false !== strpos( $html, 'undt-steps__item--done' ) && false !== strpos( $html, 'Erledigt:' ) && false !== strpos( $html, '<a href="#">Los</a>' ) && false !== strpos( $html, '<span class="undt-steps__circle">3</span>' ), 'Schrittliste: erledigt, aktuell mit Aktion, offen mit Nummer' );
$html = undt_html( static function () { UNDT_Ui::link_card( array( 'href' => '/x', 'icon' => 'clock', 'title' => 'Öffnungszeiten', 'line' => '5 fehlen', 'tone' => 'open', 'count' => 5 ) ); } );
undt_ok( 0 === strpos( $html, '<a class="undt-linkcard" href="/x">' ) && false !== strpos( $html, 'undt-linkcard__caret' ) && false !== strpos( $html, 'undt-linkcard__line--open' ), 'Link-Card: ganze Karte als Link, mit Pfeil' );
$html = undt_html( static function () { UNDT_Ui::card_head( 'Bereiche', 'Nur, was du brauchst.', static function () { echo '<b>S</b>'; } ); } );
undt_ok( false !== strpos( $html, '<h2 class="undt-box__title">Bereiche</h2>' ) && false !== strpos( $html, '<div class="undt-box__aside"><b>S</b></div>' ), 'Kartenkopf mit Inhalt rechts' );

foreach ( array( 'grid', 'buildings', 'clock', 'tag', 'share', 'chat', 'megaphone', 'search', 'code', 'transfer', 'gear', 'help', 'caret', 'out', 'check', 'excl', 'x', 'lock', 'info', 'scales' ) as $icon ) {
	if ( ! UNDT_Ui_Icons::exists( $icon ) ) {
		undt_ok( false, 'Symbol fehlt: ' . $icon );
	}
}
undt_ok( true, 'Alle Symbole der Navigation und der Bausteine sind da' );
undt_ok( 0 === strpos( UNDT_Ui_Icons::svg( 'grid' ), '<svg class="undt-icon" width="16" height="16" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true" focusable="false"><path d="M' ), 'Inline-SVG, Farbe aus dem CSS, fuer Screenreader stumm' );
undt_ok( '' === UNDT_Ui_Icons::svg( 'gibtsnicht' ), 'Unbekanntes Symbol: nichts' );
undt_ok( 0 === strpos( UNDT_Ui_Icons::data_uri( 'buildings' ), 'data:image/svg+xml;base64,' ), 'Menuesymbol als Daten-Adresse, WordPress faerbt es ein' );

/* ------------------------------------------- 8. Dateien im Ganzen ---- */

undt_head( 'Ansichten, Skript und Stylesheet' );

$css = (string) file_get_contents( $undt_base . 'admin/assets/admin.css' );
$js  = (string) file_get_contents( $undt_base . 'admin/assets/admin.js' );

foreach ( array( '--undt-blue: #2271b1;', '--undt-petrol: #0f6e68;', '--undt-amber-text: #8a4a12;', '--undt-amber: #e0923a;', '--undt-grey: #8e8e98;', '--undt-surface: #f2f2f1;', '--undt-border: #e4e4e1;', '--undt-foot: #fafaf9;' ) as $token ) {
	undt_ok( false !== strpos( $css, $token ), 'Token ' . $token );
}
undt_ok( ! preg_match( '/#(d63638|00a32a|dba617|46b450)/i', $css ), 'Kein Rot und kein Gruen im Stylesheet' );
undt_ok( false === stripos( $css, 'font-family:' ) || ! preg_match( '/font-family:\s*["\']?(?!inherit)[A-Za-z]/', $css ), 'Keine eigene Schrift, es gilt die von WordPress' );
undt_ok( false !== strpos( $css, '@media screen and (max-width: 782px) {' . "\n\t.undt-side {\n\t\tdisplay: none;" ), 'Unter 783 px keine Seitenleiste' );
undt_ok( false !== strpos( $css, ".undt-mbar__menu {\n\tdisplay: flex;\n\talign-items: center;\n\tgap: 6px;\n\tmin-width: 44px;\n\tmin-height: 44px;" ), 'Der Menueknopf ist ein Tippziel von 44 px' );
undt_ok( false !== strpos( $css, ".undt-mmenu .undt-nav__item {\n\tmin-height: 52px;" ), 'Eintraege im Vollbild-Menue sind 52 px hoch' );
undt_ok( false !== strpos( $css, '.undt-app [hidden],' ), 'hidden wirkt auch ausserhalb der Formulare' );

undt_ok( false !== strpos( $js, 'function initAutosave()' ) && false !== strpos( $js, 'initAutosave();' ), 'Das Skript speichert automatisch' );
undt_ok( false !== strpos( $js, 'var RETRY   = 10000;' ) && false !== strpos( $js, "saveState( 'error' )" ), 'Bei Fehler neuer Versuch nach zehn Sekunden' );
undt_ok( false !== strpos( $js, "navigator.sendBeacon( save.url" ) && false !== strpos( $js, "'pagehide'" ), 'Was beim Verlassen noch wartet, geht hinterher' );
undt_ok( false !== strpos( $js, "if ( 'Escape' === event.key ) {\n\t\t\t\tclose( true );" ) && false !== strpos( $js, 'Der Fokus bleibt im Menue' ), 'Das Menue schliesst mit Esc und haelt den Fokus' );

// Die Seite Firma baut UNDT_Company_View, die Ansicht ruft sie nur auf.
// Die Bereiche baut seit Etappe 4 UNDT_Module_View.
foreach ( array( 'admin/class-undt-company-view.php', 'admin/class-undt-module-view.php', 'admin/views/settings.php' ) as $view ) {
	$code = (string) file_get_contents( $undt_base . $view );
	undt_ok( false !== strpos( $code, 'data-undt-autosave="' ) && false === strpos( $code, "'Änderungen speichern'" ) && false !== strpos( $code, 'UNDT_Ui::noscript_submit()' ), $view . ': speichert selbst, Knopf nur ohne Skript' );
}

$company = (string) file_get_contents( $undt_base . 'admin/class-undt-company-view.php' );
undt_ok( false !== strpos( $company, '$copy        = UNDT_Access::is_admin();' ), 'Kopierknoepfe an den Feldern nur fuer Admins' );

$settings_view = (string) file_get_contents( $undt_base . 'admin/views/settings.php' );
undt_ok( false !== strpos( $settings_view, 'Wer darf die Angaben pflegen?' ) && false === strpos( $settings_view, 'undt_import' ), 'Einstellungen: Rollen ja, Sichern ist umgezogen' );
undt_ok( false !== strpos( (string) file_get_contents( $undt_base . 'admin/views/transfer.php' ), 'undt_import' ), 'Sichern & Übertragen hat eine eigene Seite' );

$uninstall = (string) file_get_contents( $undt_base . 'uninstall.php' );
undt_ok( false !== strpos( $uninstall, "delete_metadata( 'user', 0, 'undt_copy_format', '', true );" ), 'Beim Loeschen geht auch die Wahl „Kopieren als“' );

/* ---------------------------------------------------------- Ergebnis ---- */

echo "\n";

if ( ! empty( $GLOBALS['undt_fails'] ) ) {
	echo "\033[31m" . (int) $GLOBALS['undt_fails'] . " Pruefungen fehlgeschlagen\033[0m\n";
	exit( 1 );
}

echo "\033[32mAlle Pruefungen bestanden\033[0m\n";
