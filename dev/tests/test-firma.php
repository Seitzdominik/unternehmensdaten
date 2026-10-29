<?php
/**
 * Pruefungen fuer 0.6.0, Etappe 3: die Seiten „Firma“ und „Einbinden“.
 *
 * Registerkarten aus dem Register, Zaehler, „optional“, Kopiercodes je Format,
 * Bundesland und Aufsichtsbehoerde, Vorschlaege, Rechtsseiten samt „Seite
 * anlegen“, Bausteine mit Optionen und die Liste der einzelnen Angaben. Wie
 * die Seiten in WordPress aussehen, zeigt dev/verify.php.
 */
require __DIR__ . '/harness.php';
require UNDT_TEST_BASE . 'admin/class-undt-fields.php';
require UNDT_TEST_BASE . 'admin/class-undt-controls.php';
require UNDT_TEST_BASE . 'admin/class-undt-copy.php';

function settings_fields( $group ) { echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '" />'; }
function wp_list_pluck( $list, $field ) {
	return array_map( static function ( $item ) use ( $field ) { return is_object( $item ) ? $item->$field : $item[ $field ]; }, $list );
}
$GLOBALS['undt_inserted'] = array();
function wp_insert_post( $args, $wp_error = false ) {
	$id = 500 + count( $GLOBALS['undt_inserted'] );
	$GLOBALS['undt_inserted'][ $id ] = $args;
	$GLOBALS['undt_posts'][ $id ]    = array( 'status' => $args['post_status'], 'title' => $args['post_title'] );
	return $id;
}
function do_shortcode( $code ) { return '<p>Ausgabe von ' . esc_html( $code ) . '</p>'; }

function undt_as( $role ) {
	$caps = array(
		'administrator' => array( 'manage_options' => true, 'edit_posts' => true, 'edit_pages' => true ),
		'editor'        => array( 'edit_posts' => true, 'edit_pages' => true ),
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
UNDT_Admin::instance()->settings();
undt_set_modules( array( 'hours' => 1, 'prices' => 0, 'social' => 1, 'faq' => 0, 'banner' => 1, 'seo' => 1 ) );
$GLOBALS['undt_options']['undt_settings']['roles'] = array( 'editor' );
UNDT_Modules::flush();
$GLOBALS['undt_options']['undt_setup_done'] = 1;
$GLOBALS['undt_options']['active_plugins']  = array( 'etch/etch.php' );
undt_as( 'administrator' );

undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'none' ), array( 'company_name' => 'Praxis Muster', 'owner_name' => 'Erika Muster', 'email' => 'info@praxis.test' ) );

/* -------------------------------------------- 1. Register und Karten ---- */

undt_head( 'Registerkarten aus dem Register' );

undt_ok( array( 'address', 'contact', 'tax', 'profession', 'content', 'custom' ) === array_keys( UNDT_Company_View::tabs() ), 'Anschrift, Kontakt, Steuer, Beruf, Inhalte & Datenschutz, Eigene Angaben' );
undt_ok( 'address' === UNDT_Company_View::tab_of( 'identity' ) && 'profession' === UNDT_Company_View::tab_of( 'chamber' ) && 'content' === UNDT_Company_View::tab_of( 'privacy' ), 'Jeder Abschnitt gehoert zu einer Karte' );
undt_ok( 'custom' === UNDT_Company_View::tab_of( 'eigener_abschnitt' ) && 'custom' === UNDT_Company_View::tab_of( 'other' ), 'Eigene Abschnitte aus undt_fields landen bei den eigenen Angaben' );
undt_ok( ! UNDT_Company_View::state()['tabs']['custom']['visible'], 'Ohne eigene Felder bleibt die Karte verborgen' );

// Eigene Felder ueber den Filter (0.6.1): ohne Abschnitt, mit eigenem und mit vorhandenem.
$undt_schema_cache = new ReflectionProperty( 'UNDT_Schema', 'fields' );
if ( PHP_VERSION_ID < 80100 ) {
	$undt_schema_cache->setAccessible( true ); // Ab 8.1 ohne Wirkung, ab 8.5 veraltet.
}
add_filter(
	'undt_fields',
	static function ( $fields ) {
		$fields['shop_note']   = array( 'label' => 'Hinweis' );
		$fields['shop_street'] = array( 'label' => 'Straße Laden', 'section' => 'shop', 'section_label' => 'Ladengeschäft' );
		$fields['branch_city'] = array( 'label' => 'Ort der Filiale', 'section' => 'address' );
		return $fields;
	}
);
$undt_schema_cache->setValue( null, null );
$grouped = UNDT_Company_View::grouped();
undt_ok( isset( $grouped['custom']['other']['shop_note'], $grouped['custom']['shop']['shop_street'], $grouped['address']['address']['branch_city'] ), 'Ohne Abschnitt und mit neuem Abschnitt: Eigene Angaben; mit vorhandenem Abschnitt: dort' );
undt_ok( UNDT_Company_View::state()['tabs']['custom']['visible'] && 'Eigene Angaben' === UNDT_Company_View::state()['tabs']['custom']['label'], 'Mit eigenen Feldern erscheint die Karte' );
undt_ok( 'Ladengeschäft' === UNDT_Company_View::section_title( 'shop' ) && 'Eigene Felder' === UNDT_Company_View::section_title( 'other' ) && 'Anschrift' === UNDT_Company_View::section_title( 'address' ), 'Titel aus section_label, sonst aus dem Register' );
$undt_form = undt_html( static function () { UNDT_Company_View::form(); } );
undt_ok( false !== strpos( $undt_form, 'id="undt-panel-custom"' ) && false !== strpos( $undt_form, 'Ladengeschäft</h2>' ), 'Die Seite zeigt Karte und Abschnitt' );
remove_all_filters( 'undt_fields' );
$undt_schema_cache->setValue( null, null );

$grouped = UNDT_Company_View::grouped();
$all     = array();
foreach ( $grouped as $sections ) {
	foreach ( $sections as $fields ) {
		$all = array_merge( $all, array_keys( $fields ) );
	}
}
undt_ok( ! in_array( 'page_imprint', $all, true ) && ! in_array( 'page_privacy', $all, true ), 'Die Rechtsseiten stehen nicht mehr auf „Firma“' );
undt_ok( count( $all ) === count( UNDT_Schema::fields() ) - 4, 'Alle anderen Felder schon' );

$state = UNDT_Company_View::state();
undt_ok( ! $state['tabs']['profession']['visible'], 'Ohne reglementierten Beruf keine Karte „Beruf“' );
undt_ok( 'Steuer' === $state['tabs']['tax']['label'], 'Einzelunternehmen ohne Register: „Steuer“' );
undt_ok( ! $state['applies']['register_court'] && $state['applies']['owner_name'], 'Registerfelder gelten nicht, der Inhaber schon' );
undt_ok( array( 'street', 'postal_code', 'city' ) === $state['missing'], 'Fehlend: Strasse, PLZ, Ort; Telefon ist keine Pflicht mehr' );
undt_ok( 3 === $state['tabs']['address']['count'] && 0 === $state['tabs']['contact']['count'], 'Zaehler je Karte aus den leeren Pflichtfeldern' );
undt_ok( 'Einzelunternehmen (nicht im Register)' === $state['title'], 'Titel ist die Rechtsform' );

undt_seed( array( 'legal_form' => 'gmbh', 'is_regulated' => 1, 'is_medical' => 1, 'has_editorial' => 1, 'vat_status' => 'small_business' ), array( 'company_name' => 'Praxis GmbH' ) );
$state = UNDT_Company_View::state();
undt_ok( $state['tabs']['profession']['visible'] && 'Register & Steuer' === $state['tabs']['tax']['label'], 'GmbH mit Heilberuf: Karte „Beruf“, „Register & Steuer“' );
undt_ok( ! $state['applies']['owner_name'] && $state['applies']['register_court'] && $state['applies']['kv_name'], 'Felder folgen der Rechtsform' );
undt_ok( 'Heilberuf · redaktionelle Inhalte · Kleinunternehmer nach § 19 UStG' === $state['summary'], 'Zusammenfassung: ' . $state['summary'] );
undt_ok( 'chamber' === UNDT_Schema::field( 'prof_rules' )['section'] && 'profession' === UNDT_Schema::field( 'job_title' )['section'], 'Kammer und Berufsrecht sind ein eigener Abschnitt' );

/* ---------------------------------------------- 2. Felder im Register ---- */

undt_head( 'Neue Angaben im Register' );

undt_ok( empty( UNDT_Schema::field( 'phone' )['required'] ) && UNDT_Schema::field( 'phone' )['quiet'], 'Telefon: keine Pflicht, aber ohne „optional“' );
undt_ok( 'select' === UNDT_Schema::field( 'state' )['type'] && 17 === count( UNDT_Schema::field( 'state' )['choices'] ) && empty( UNDT_Schema::field( 'state' )['shortcode'] ), 'Bundesland als Auswahl aus 16 Laendern, ohne Ausgabe' );
undt_ok( 'BY' === UNDT_Sanitizer::company( array( 'state' => 'BY' ) )['state'] && '' === UNDT_Sanitizer::company( array( 'state' => 'XX' ) )['state'], 'Nur bekannte Laender werden gespeichert' );
undt_ok( 'maps' === UNDT_Schema::field( 'maps_google' )['more'] && 'dpo' === UNDT_Schema::field( 'dpo_email' )['more'] && 'chamber' === UNDT_Schema::field( 'kv_name' )['more'], 'Seltene Felder haben eine Gruppe zum Aufklappen' );
$pattern = '/' . UNDT_Schema::field( 'business_id' )['pattern'] . '/i';
undt_ok( preg_match( $pattern, 'DE123456789' ) && preg_match( $pattern, 'DE123456789-00001' ) && ! preg_match( $pattern, 'DE12345' ) && ! preg_match( $pattern, '123456789' ), 'W-IdNr.: DE, neun Ziffern, optional -00001' );
undt_ok( 'country' === UNDT_Schema::field( 'country' )['control'] && 'text' === UNDT_Schema::field( 'country' )['type'], 'Land: Auswahl, gespeichert als Text' );
$questions = UNDT_Schema::profile_questions();
undt_ok( ! in_array( 'sole', $questions['is_liquidating']['when']['legal_form'], true ) && in_array( 'gmbh', $questions['is_liquidating']['when']['legal_form'], true ), 'Die Abwicklung fragt nur Gesellschaften' );

/* ----------------------------------------------- 3. Ausgabe der Seite ---- */

undt_head( 'Firma: Karte, Zeilen, Kopier-Symbol' );

undt_seed( array( 'legal_form' => 'sole', 'is_regulated' => 0, 'has_editorial' => 1, 'vat_status' => 'none' ), array( 'company_name' => 'Praxis Muster', 'owner_name' => 'Erika Muster', 'email' => 'info@praxis.test', 'country' => 'Belgien', 'state' => 'BY' ) );

$card = undt_html( static function () { UNDT_Company_View::legal_form_card(); } );
undt_ok( false !== strpos( $card, 'id="rechtsform"' ) && false !== strpos( $card, 'data-undt-legal-toggle' ) && false !== strpos( $card, 'aria-expanded="false"' ), 'Rechtsform als Karte mit „Ändern“' );
undt_ok( false !== strpos( $card, 'data-undt-autosave="profile"' ) && false !== strpos( $card, 'id="undt-legalform-body" hidden' ), 'Die Fragen speichern selbst und sind zugeklappt' );
undt_ok( false !== strpos( $card, 'undt-question-row--nested' ) && false !== strpos( $card, 'data-undt-when=' ), 'Abhaengige Fragen eingerueckt und mit Bedingung' );

undt_as( 'editor' );
$card_editor = undt_html( static function () { UNDT_Company_View::legal_form_card(); } );
undt_ok( false === strpos( $card_editor, 'data-undt-legal-toggle' ) && false === strpos( $card_editor, 'undt_profile' ), 'Redakteure sehen die Rechtsform, aendern sie aber nicht' );
$form_editor = undt_html( static function () { UNDT_Company_View::form(); } );
undt_ok( false === strpos( $form_editor, 'undt-fcopy' ), 'Und sehen keine Kopierknoepfe' );
undt_as( 'administrator' );

$form = undt_html( static function () { UNDT_Company_View::form(); } );
undt_ok( false !== strpos( $form, 'data-undt-autosave="company"' ) && false !== strpos( $form, 'role="tablist"' ), 'Ein Formular mit Registerkarten' );
undt_ok( false !== strpos( $form, 'id="undt-tab-profession"' ) && preg_match( '/id="undt-tab-profession"[^>]*hidden/', $form ), 'Karten, die nicht gelten, sind verborgen statt weggelassen' );
undt_ok( preg_match( '/data-undt-row="street"[^>]*>/', $form ) && false !== strpos( $form, '<div class="undt-frow is-missing" data-undt-row="street">' ), 'Leere Pflicht: die Zeile ist markiert' );
undt_ok( false !== strpos( $form, 'Pflichtangabe fehlt · § 5 Abs. 1 Nr. 1 DDG' ), 'Mit Bernstein-Zeile und Rechtsgrundlage' );
undt_ok( preg_match( '/data-undt-row="address_addition".*?undt-frow__optional">optional</s', $form ) && ! preg_match( '/data-undt-row="phone".*?undt-frow__optional/s', substr( $form, (int) strpos( $form, 'data-undt-row="phone"' ), 600 ) ), 'Markiert wird „optional“, nicht beim Telefon' );
undt_ok( false !== strpos( $form, '<span class="undt-frow__title">Postleitzahl und Ort</span>' ) || false !== strpos( $form, 'data-undt-row="postal_code city"' ), 'PLZ und Ort in einer Zeile' );
undt_ok( false !== strpos( $form, '<option value="Belgien" selected="selected">Belgien</option>' ) && false !== strpos( $form, 'Deutschland (nicht ausgeben)' ), 'Land als Auswahl, ein frueherer Eintrag bleibt waehlbar' );
undt_ok( false !== strpos( $form, '<summary class="undt-details__summary">' ) && false !== strpos( $form, 'Kartenlinks' ) && false !== strpos( $form, '· optional' ), 'Kartenlinks zugeklappt, mit „· optional“' );
undt_ok( false !== strpos( $form, 'data-undt-pattern="^DE[0-9]{9}(-[0-9]{5})?$"' ) && false !== strpos( $form, 'undt-frow__pattern" data-undt-pattern-for="business_id" hidden>Das Format stimmt nicht' ), 'W-IdNr. mit Formatpruefung' );
undt_ok( false !== strpos( $form, 'class="undt-copy undt-fcopy" data-undt-copy="{options.undt.company_name}"' ), 'Kopier-Symbol im erkannten Format, hier Etch' );
undt_ok( false !== strpos( $form, 'data-undt-codes="' ) && false !== strpos( $form, 'aria-label="Angabe kopieren: Firma / Name"' ), 'Mit allen Formaten und Beschriftung' );
undt_ok( false === strpos( $form, 'undt-copy-row' ), 'Die alte Reihe aus Shortcode und Logos ist weg' );
undt_ok( false !== strpos( $form, 'data-undt-suggest="editorial_name">Inhaber übernehmen: Erika Muster' ), 'Vorschlag: Inhaber als verantwortliche Person' );
undt_ok( false !== strpos( $form, 'data-undt-suggest="privacy_authority">Übernehmen: Bayerisches Landesamt für Datenschutzaufsicht' ), 'Vorschlag: Aufsichtsbehoerde nach Bundesland' );

/* ------------------------------------------------- 4. Kopiercodes ---- */

undt_head( 'Kopieren als: Code je Format' );

$codes = UNDT_Copy::codes( 'phone' );
undt_ok( '{options.undt.phone}' === $codes['etch'] && '{undt_phone}' === $codes['bricks'] && '[undt key="phone"]' === $codes['shortcode'], 'Etch, Bricks, Shortcode' );
undt_ok( 0 === strpos( $codes['gutenberg'], '<!-- wp:paragraph' ), 'Block-Editor: ein gebundener Absatz' );
undt_ok( 'Absatz: Telefon' === UNDT_Copy::display( 'gutenberg', $codes['gutenberg'], 'Telefon' ), 'Angezeigt als „Absatz: Telefon“' );
undt_ok( '[undt key="phone" link="1"]' === UNDT_Copy::shortcode_for( 'phone_link' ), 'Telefon als Link: Shortcode mit link="1"' );
undt_ok( '[undt_hours_today]' === UNDT_Copy::shortcode_for( 'hours_today' ) && '[undt_address inline="1"]' === UNDT_Copy::shortcode_for( 'address' ), 'Abgeleitete Angaben mit eigenem Shortcode' );
undt_ok( '' === UNDT_Copy::shortcode_for( 'is_open' ) && '' === UNDT_Copy::shortcode_for( 'banner_show' ), 'Nur fuer Builder: kein Shortcode' );
undt_ok( '[undt key="page_imprint" link="1"]' === UNDT_Copy::shortcode_for( 'page_imprint' ), 'Rechtsseiten als Link' );

/* ------------------------------------------ 5. Bundesland und Behoerde ---- */

undt_head( 'Aufsichtsbehoerde nach Bundesland' );

$authorities = UNDT_Authorities::all();
undt_ok( 16 === count( $authorities ) && array() === array_diff( array_keys( $authorities ), array_keys( UNDT_Schema::states() ) ), 'Je Land eine Behoerde, passend zur Auswahl' );
$complete = true;
foreach ( $authorities as $authority ) {
	$complete = $complete && '' !== $authority['name'] && '' !== $authority['street'] && preg_match( '/^\d{5}$/', $authority['postal_code'] ) && 0 === strpos( $authority['url'], 'https://' ) && 0 === strpos( $authority['source'], 'https://' );
}
undt_ok( $complete, 'Jede mit Name, Hausanschrift, Website und Quelle' );
undt_ok( "Bayerisches Landesamt für Datenschutzaufsicht\nPromenade 18\n91522 Ansbach\nhttps://www.lda.bayern.de" === UNDT_Authorities::text( 'BY' ), 'Bayern: das Landesamt, nicht der Landesbeauftragte' );
undt_ok( '' === UNDT_Authorities::text( '' ) && null === UNDT_Authorities::for_state( 'XX' ), 'Ohne Land kein Vorschlag' );

$suggestions = UNDT_Company_View::suggestions();
undt_ok( isset( $suggestions['editorial_name'], $suggestions['privacy_authority'] ), 'Beide Vorschlaege stehen bereit' );

$answer = UNDT_Autosave::save( 'quick:suggest', array( 'value' => 'privacy_authority' ) );
undt_ok( is_array( $answer ) && 0 === strpos( $answer['value'], 'Bayerisches Landesamt' ) && UNDT_Store::has( 'privacy_authority' ), 'Übernehmen schreibt die Behoerde in die Angaben' );
undt_ok( ! isset( UNDT_Company_View::suggestions()['privacy_authority'] ), 'Danach gibt es dafuer keinen Vorschlag mehr' );
$answer = UNDT_Autosave::save( 'quick:suggest', array( 'value' => 'privacy_authority' ) );
undt_ok( is_wp_error( $answer ) && 400 === $answer->get_error_data(), 'Ein zweites Mal: abgelehnt, nichts ueberschrieben' );

undt_as( 'editor' );
$answer = UNDT_Autosave::save( 'quick:suggest', array( 'value' => 'editorial_name' ) );
undt_ok( is_array( $answer ) && 'Erika Muster' === UNDT_Store::get( 'editorial_name' ), 'Redakteure duerfen Vorschlaege uebernehmen' );
undt_as( 'administrator' );

undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'none' ), array( 'company_name' => 'X', 'state' => 'HH' ) );
$todo = null;
foreach ( UNDT_Todo::items() as $item ) {
	if ( 'privacy_authority' === $item['suggest'] ) {
		$todo = $item;
	}
}
undt_ok( null !== $todo && 'Übernehmen' === $todo['action'] && false !== strpos( $todo['desc'], 'Für Hamburg ist zuständig: Der Hamburgische Beauftragte' ), 'Übersicht: „Übernehmen“ mit Land und Behoerde' );

/* -------------------------------------------- 6. Rechtsform speichern ---- */

undt_head( 'Rechtsform speichern: Antwort fuer die Seite' );

$answer = UNDT_Autosave::save( 'profile', array( 'undt_profile' => array( 'legal_form' => 'gmbh', 'is_regulated' => 1, 'vat_status' => 'standard' ) ) );
undt_ok( is_array( $answer ) && isset( $answer['company']['applies'], $answer['company']['tabs'] ), 'Die Antwort sagt, welche Felder und Karten gelten' );
undt_ok( $answer['company']['tabs']['profession']['visible'] && $answer['company']['applies']['register_court'] && $answer['company']['applies']['vat_id'], 'Nach dem Wechsel zur GmbH: Register, USt-IdNr., Beruf' );
undt_ok( 'Register & Steuer' === $answer['company']['tabs']['tax']['label'], 'Und die Karte heisst „Register & Steuer“' );
$answer = UNDT_Autosave::save( 'company', array( 'undt_company' => array( 'company_name' => 'Y' ) ) );
undt_ok( ! isset( $answer['company'] ), 'Die Firma selbst schickt das nicht mit' );

/* ---------------------------------------------------- 7. Einbinden ---- */

undt_head( 'Einbinden: Rechtsseiten' );

undt_seed( array( 'legal_form' => 'sole', 'sells_to_consumers' => 0, 'vat_status' => 'none' ), array( 'company_name' => 'X', 'page_imprint' => 114 ) );
$GLOBALS['undt_posts'][114] = array( 'status' => 'publish', 'title' => 'Danke' );
$GLOBALS['undt_db_posts']   = array();
UNDT_Usage::flush();

$pages = UNDT_Embed::pages();
undt_ok( array( 'page_imprint', 'page_privacy', 'page_terms', 'page_accessibility' ) === array_keys( $pages ), 'Vier Rechtsseiten' );
undt_ok( 'open' === $pages['page_imprint']['tone'] && 'Auf „Danke“ steht kein Impressum' === $pages['page_imprint']['status'] && 'imprint' === $pages['page_imprint']['create'], 'Impressum auf der falschen Seite: Bernstein, mit „Seite anlegen“' );
undt_ok( 'open' === $pages['page_privacy']['tone'] && 'Keine Seite gewählt' === $pages['page_privacy']['status'] && 'privacy' === $pages['page_privacy']['create'], 'Keine Datenschutzerklaerung: Bernstein' );
undt_ok( 'missing' === $pages['page_terms']['tone'] && false !== strpos( $pages['page_terms']['status'], 'Verbraucher' ), 'AGB ohne Verbraucher: grau, nicht noetig' );
undt_ok( 'missing' === $pages['page_accessibility']['tone'], 'Barrierefreiheit ohne Verbraucher: grau' );

$GLOBALS['undt_db_posts'] = array( array( 'ID' => 114, 'post_type' => 'page', 'post_title' => 'Danke', 'post_name' => 'danke', 'post_content' => '[undt_impressum]' ) );
UNDT_Usage::flush();
$pages = UNDT_Embed::pages();
undt_ok( 'ok' === $pages['page_imprint']['tone'] && 'Enthält das Impressum' === $pages['page_imprint']['status'] && '' !== $pages['page_imprint']['view'], 'Mit Baustein: in Ordnung, mit „Ansehen“' );

undt_as( 'editor' );
$answer = UNDT_Autosave::save( 'quick:create_page', array( 'value' => 'privacy' ) );
undt_ok( is_wp_error( $answer ) && 403 === $answer->get_error_data() && empty( $GLOBALS['undt_inserted'] ), 'Seite anlegen nur fuer Admins' );
undt_as( 'administrator' );
$answer = UNDT_Autosave::save( 'quick:create_page', array( 'value' => 'nichts' ) );
undt_ok( is_wp_error( $answer ) && 400 === $answer->get_error_data(), 'Unbekannte Seite: abgelehnt' );
$answer = UNDT_Autosave::save( 'quick:create_page', array( 'value' => 'privacy' ) );
$new    = is_array( $answer ) ? $answer['page'] : 0;
undt_ok( $new > 0 && 'draft' === $GLOBALS['undt_inserted'][ $new ]['post_status'] && 'Datenschutzerklärung' === $GLOBALS['undt_inserted'][ $new ]['post_title'], 'Datenschutz: ein Entwurf' );
undt_ok( false !== strpos( $GLOBALS['undt_inserted'][ $new ]['post_content'], '[undt_privacy_block name="controller"]' ) && false === stripos( $GLOBALS['undt_inserted'][ $new ]['post_content'], 'Cookies' ), 'Nur mit den Bausteinen, kein Volltext' );
undt_ok( $new === UNDT_Store::page_id( 'page_privacy' ) && true === $answer['reload'], 'Und gleich verknuepft' );

undt_head( 'Einbinden: Bausteine' );

$groups = UNDT_Embed::groups();
undt_ok( array( 'legal', 'privacy', 'contact', 'hours', 'more' ) === array_keys( $groups ), 'Gruppen wie im Entwurf' );
undt_ok( isset( $groups['more']['items']['social'], $groups['more']['items']['banner'] ) && ! isset( $groups['more']['items']['prices'] ), 'Nur Bausteine eingeschalteter Bereiche' );

$address = $groups['contact']['items']['address'];
undt_ok( '[undt_address]' === UNDT_Embed::code( $address[2], $address[3] ), 'Anschrift in der Voreinstellung' );
$opts = $address[3];
$opts[0]['checked'] = true;
$opts[1]['checked'] = false;
undt_ok( '[undt_address inline="1" name="0"]' === UNDT_Embed::code( $address[2], $opts ), 'Haekchen setzen die Attribute' );

$footer = $groups['contact']['items']['footer'];
undt_ok( '[undt_footer]' === UNDT_Embed::code( $footer[2], $footer[3] ), 'Footer mit allem: ohne show' );
$opts = $footer[3];
$opts[2]['checked'] = false;
undt_ok( '[undt_footer show="address,legal"]' === UNDT_Embed::code( $footer[2], $opts ), 'Ohne Copyright: show mit den uebrigen' );
$hours = $groups['hours']['items']['hours'];
$opts  = $hours[3];
$opts[0]['checked'] = false;
undt_ok( '[undt_hours group="0"]' === UNDT_Embed::code( $hours[2], $opts ), 'Öffnungszeiten einzeln: group="0"' );
undt_ok( '[undt_privacy_block name="controller"]' === UNDT_Embed::code( $groups['privacy']['items']['privacy'][2], $groups['privacy']['items']['privacy'][3] ), 'Datenschutz mit Ueberschrift: wie gewohnt' );

undt_ok( '<p>Ausgabe von [undt_footer show=&quot;address&quot;]</p>' === UNDT_Embed::preview( '[undt_footer show="address"]' ), 'Vorschau rendert den Baustein' );
undt_ok( null === UNDT_Embed::preview( '[gallery ids="1"]' ) && null === UNDT_Embed::preview( '[undt_footer]<script>' ) && null === UNDT_Embed::preview( '[undt_footer onload=x]' ), 'Andere Shortcodes und Beiwerk werden abgelehnt' );
$answer = UNDT_Autosave::save( 'quick:preview', array( 'value' => '[undt_impressum]' ) );
undt_ok( is_array( $answer ) && false !== strpos( $answer['html'], 'undt_impressum' ), 'Vorschau ueber den Endpunkt' );
undt_as( 'editor' );
$answer = UNDT_Autosave::save( 'quick:preview', array( 'value' => '[undt_impressum]' ) );
undt_ok( is_wp_error( $answer ) && 403 === $answer->get_error_data(), 'Nur fuer Admins' );
undt_as( 'administrator' );

// Gefunden ueber die Vorschau: das Trennzeichen verlor seine Leerzeichen.
undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'none' ), array( 'company_name' => 'X', 'street' => 'Weg 1', 'postal_code' => '10115', 'city' => 'Berlin' ) );
undt_ok( false !== strpos( UNDT_Shortcodes::address( array( 'inline' => '1' ) ), 'X · Weg 1 · 10115 Berlin' ), 'Anschrift in einer Zeile: „ · “ mit Leerzeichen' );
undt_ok( false !== strpos( UNDT_Shortcodes::address( array( 'inline' => '1', 'separator' => ', ' ) ), 'X, Weg 1, 10115 Berlin' ), 'Eigenes Trennzeichen behaelt sein Leerzeichen' );
undt_ok( false !== strpos( UNDT_Shortcodes::address( array( 'inline' => '1', 'separator' => ' <b>|</b> ' ) ), 'X | Weg 1' ), 'Und bleibt ohne HTML' );
undt_seed( array( 'legal_form' => 'sole', 'sells_to_consumers' => 0, 'vat_status' => 'none' ), array( 'company_name' => 'X', 'page_imprint' => 114 ) );

undt_head( 'Einbinden: einzelne Angaben' );

$values = UNDT_Embed::values();
$keys   = array_column( $values, 'key' );
undt_ok( in_array( 'phone_link', $keys, true ) && in_array( 'hours_today', $keys, true ) && in_array( 'banner_text', $keys, true ), 'Auch abgeleitete Angaben, Öffnungszeiten und Banner' );
$by_key = array_combine( $keys, $values );
undt_ok( 'contact' === $by_key['email']['group'] && 'hours' === $by_key['hours_today']['group'] && 'banner' === $by_key['banner_text']['group'] && 'company' === $by_key['company_name']['group'], 'Jede in ihrer Filtergruppe' );
undt_ok( '' === $by_key['fax']['value'] && 'X' === $by_key['company_name']['value'], 'Mit dem aktuellen Wert, leer wenn nichts eingetragen ist' );
undt_ok( array( 'company', 'contact', 'hours', 'banner' ) === array_keys( UNDT_Embed::value_groups() ), 'Filter ohne „Beruf“, wenn kein Beruf gilt' );
undt_ok( isset( UNDT_Embed::format_help()['bricks'] ) && false !== strpos( UNDT_Embed::format_help()['etch'], 'Fertige Bausteine bleiben Shortcodes' ), 'Erklaerung je Format' );

/* ------------------------------------------------ 8. Dateien im Ganzen ---- */

undt_head( 'Ansichten und Skript' );

undt_ok( ! is_file( $undt_base . 'admin/views/profile.php' ) && ! is_file( $undt_base . 'admin/views/shortcodes.php' ) && is_file( $undt_base . 'admin/views/embed.php' ), 'Rechtsform und Shortcodes sind keine eigenen Seiten mehr' );
undt_ok( 'embed' === UNDT_Nav::pages()['embed']['view'], 'Einbinden zeigt die neue Ansicht' );
$settings_view = (string) file_get_contents( $undt_base . 'admin/views/settings.php' );
$embed_view    = (string) file_get_contents( $undt_base . 'admin/views/embed.php' );
undt_ok( false === strpos( $settings_view, 'undt_link_post_types' ) && false !== strpos( $embed_view, 'undt_link_post_types' ), 'Die Filter fuer Entwickler stehen unter Einbinden' );
undt_ok( false !== strpos( $embed_view, "UNDT_Ui::segment( 'undt_format_choice'" ) && false !== strpos( $embed_view, 'id="undt-embed-search"' ), 'Kopf mit „Kopieren als“ und Suche' );

// Ansichten laufen im globalen Bereich. dev/audit/env.php benennt seine Ergebnisdatei mit $undt_name.
$collide = array();
foreach ( glob( $undt_base . 'admin/views/*.php' ) as $view_file ) {
	if ( false !== strpos( (string) file_get_contents( $view_file ), '$undt_name' ) ) {
		$collide[] = basename( $view_file );
	}
}
undt_ok( empty( $collide ), 'Keine Ansicht ueberschreibt $undt_name' . ( $collide ? ': ' . implode( ', ', $collide ) : '' ) );

$js = (string) file_get_contents( $undt_base . 'admin/assets/admin.js' );
undt_ok( false !== strpos( $js, 'function applyCompanyState( state )' ) && false !== strpos( $js, "event.detail.company" ), 'Das Skript passt Firma nach dem Speichern der Rechtsform an' );
undt_ok( false !== strpos( $js, 'function refreshMissing( form )' ) && false !== strpos( $js, "new RegExp( pattern.getAttribute( 'data-undt-pattern' ), 'i' )" ), 'Leere Pflicht und Format werden beim Tippen geprueft' );
undt_ok( false !== strpos( $js, 'function blockCode( block )' ) && false !== strpos( $js, "quickSave( 'preview', code" ), 'Haekchen aendern Code und Vorschau' );
undt_ok( false !== strpos( $js, "closest( '[data-undt-block]' )" ) && false !== strpos( $embed_view, 'data-undt-block data-undt-search=' ) && false !== strpos( $embed_view, 'data-undt-block-code' ), 'Ansicht und Skript verwenden dieselben Attribute' );
undt_ok( false === strpos( (string) file_get_contents( $undt_base . 'admin/assets/admin.css' ), '.undt-block ' ), 'Keine Backend-Regel auf .undt-block, das ist die Klasse der Bausteine im Frontend' );
undt_ok( false !== strpos( $js, 'function applyFormat( format )' ) && false !== strpos( $js, "'#rechtsform' === window.location.hash" ), 'Format wechselt ohne Neuladen, #rechtsform oeffnet die Karte' );

/* ---------------------------------------------------------- Ergebnis ---- */

echo "\n";

if ( ! empty( $GLOBALS['undt_fails'] ) ) {
	echo "\033[31m" . (int) $GLOBALS['undt_fails'] . " Pruefungen fehlgeschlagen\033[0m\n";
	exit( 1 );
}

echo "\033[32mAlle Pruefungen bestanden\033[0m\n";
