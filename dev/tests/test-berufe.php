<?php
/**
 * Pruefungen fuer 0.6.0, Etappe 6: Berufsdaten.
 *
 * Die erzeugte Datendatei passt zur JSON, Berufe und Kammern werden richtig
 * gefunden, „Übernehmen“ schreibt nur zulaessige Kammern, die Seite Firma
 * zeigt Auswahl und Vorschlag, Suchmaschinen schlaegt die Art vor.
 */
require __DIR__ . '/harness.php';
require UNDT_TEST_BASE . 'admin/class-undt-fields.php';
require UNDT_TEST_BASE . 'admin/class-undt-controls.php';
require UNDT_TEST_BASE . 'admin/class-undt-copy.php';

function settings_fields( $group ) { echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '" />'; }
function wp_list_pluck( $list, $field ) {
	return array_map( static function ( $item ) use ( $field ) { return is_object( $item ) ? $item->$field : $item[ $field ]; }, $list );
}
function do_shortcode( $code ) { return '<p>Ausgabe von ' . esc_html( $code ) . '</p>'; }

function undt_html( $callback ) {
	ob_start();
	$callback();
	return (string) ob_get_clean();
}

$undt_base = UNDT_TEST_BASE;
$undt_dev  = dirname( __DIR__ );

UNDT_Access::register();
UNDT_Admin::instance()->settings();
undt_set_modules( array( 'hours' => 1, 'prices' => 1, 'social' => 1, 'faq' => 1, 'banner' => 1, 'seo' => 1 ) );
$GLOBALS['undt_options']['undt_setup_done'] = 1;
$GLOBALS['undt_user'] = array( 'roles' => array( 'administrator' ), 'caps' => array( 'manage_options' => true, 'edit_posts' => true ) );

$undt_profile = array( 'legal_form' => 'sole', 'is_regulated' => 1, 'is_medical' => 1, 'vat_status' => 'none' );

/* --------------------------------------------------------- 1. Daten ---- */

undt_head( 'Daten' );

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $undt_dev . '/build-berufe.php' ) . ' --check 2>&1', $undt_out, $undt_code );
undt_ok( 0 === $undt_code, 'includes/data/berufe.php passt zu dev/data/berufe.json' . ( 0 === $undt_code ? '' : ': ' . implode( ' ', $undt_out ) ) );

$undt_data = UNDT_Professions::data();
undt_ok( 14 === count( $undt_data['berufe'] ) && count( $undt_data['kammern'] ) >= 180, 'Berufe und Kammern: ' . count( $undt_data['berufe'] ) . ' / ' . count( $undt_data['kammern'] ) );
undt_ok( '' !== $undt_data['stand'] && isset( $undt_data['verzeichnisse']['aerzte'] ) && 0 === strpos( $undt_data['verzeichnisse']['aerzte'], 'https://' ), 'Stand und Verzeichnis je Kammergruppe' );
undt_ok( false === strpos( (string) file_get_contents( $undt_base . 'includes/data/berufe.php' ), "'quelle'" ), 'Quellenangaben bleiben in der JSON, nicht im Plugin' );

$undt_users = array();
foreach ( array( 'includes', 'admin' ) as $undt_dir ) {
	foreach ( glob( $undt_base . $undt_dir . '/*.php' ) as $undt_file ) {
		if ( false !== strpos( (string) file_get_contents( $undt_file ), 'UNDT_Professions::' ) ) {
			$undt_users[] = basename( $undt_file );
		}
	}
}
sort( $undt_users );
undt_ok( array( 'class-undt-company-view.php', 'class-undt-module-view.php' ) === $undt_users, 'Die Daten laden nur Seiten im Backend: ' . implode( ', ', $undt_users ) );

/* ------------------------------------------------- 2. Berufe, Kammern ---- */

undt_head( 'Berufe und Kammern' );

undt_ok( 'arzt' === UNDT_Professions::profession_for( 'Ärztin' ) && 'arzt' === UNDT_Professions::profession_for( 'Arzt' ), 'Beide Formen führen zum Beruf' );
undt_ok( 'psychotherapeut' === UNDT_Professions::profession_for( 'Psychologischer Psychotherapeut' ) && 'steuerberater' === UNDT_Professions::profession_for( 'Steuerbevollmächtigte' ), 'Auch die weiteren Bezeichnungen' );
undt_ok( '' === UNDT_Professions::profession_for( 'Heilpraktiker' ) && '' === UNDT_Professions::profession_for( 'Coach' ), 'Heilpraktiker und eigene Titel haben keine Kammerdaten' );

$undt_groups = UNDT_Professions::title_groups();
undt_ok( isset( $undt_groups['Heilberufe'], $undt_groups['Recht'] ) && in_array( 'Zahnärztin', $undt_groups['Heilberufe'], true ), 'Auswahl nach Gruppen' );
undt_ok( 1 === array_sum( array_map( static function ( $t ) { return count( array_keys( $t, 'Hebamme', true ) ); }, $undt_groups ) ), 'Gleiche Formen stehen nur einmal da (Hebamme)' );

undt_ok( array( 'aek-by' ) === array_keys( UNDT_Professions::chambers( 'arzt', 'BY' ) ), 'Arzt in Bayern: die Landesärztekammer' );
undt_ok( 2 === count( UNDT_Professions::chambers( 'arzt', 'NW' ) ), 'Arzt in NRW: Nordrhein oder Westfalen-Lippe' );
undt_ok( 3 === count( UNDT_Professions::chambers( 'rechtsanwalt', 'NW' ) ) && ! isset( UNDT_Professions::chambers( 'rechtsanwalt', 'NW' )['rak-bgh'] ), 'Rechtsanwalt in NRW: drei Kammern, nicht die beim BGH' );
undt_ok( 1 === count( UNDT_Professions::chambers( 'rechtsanwalt', 'BE' ) ), 'In Berlin genau eine' );
undt_ok( array( 'wpk' ) === array_keys( UNDT_Professions::chambers( 'wirtschaftspruefer', '' ) ), 'Wirtschaftsprüfer: bundesweit, auch ohne Land' );
undt_ok( array() === UNDT_Professions::chambers( 'arzt', '' ) && array() === UNDT_Professions::chambers( 'hebamme', 'BY' ), 'Ohne Land oder ohne Kammer: keine' );
undt_ok( 1 === count( UNDT_Professions::chambers( 'psychotherapeut', 'SN' ) ), 'Psychotherapeut in Sachsen: die gemeinsame Ostkammer' );

$undt_rules = UNDT_Professions::rules( 'arzt', 'aek-by' );
$undt_texts = array_column( $undt_rules, 'text' );
undt_ok( 'Bundesärzteordnung (BÄO)' === $undt_texts[0], 'Erst das Bundesrecht, mit Abkürzung' );
undt_ok( in_array( 'Berufsordnung der Bayerischen Landesärztekammer', $undt_texts, true ) && count( $undt_texts ) >= 3, 'Dann Landesgesetz und Berufsordnung' );
undt_ok( count( $undt_texts ) === count( array_unique( $undt_texts ) ), 'Keine Zeile doppelt' );

/* ------------------------------------------------------ 3. Übernehmen ---- */

undt_head( 'Übernehmen' );

undt_seed( $undt_profile, array( 'company_name' => 'Praxis Muster', 'job_title' => 'Ärztin', 'state' => 'BY' ) );

undt_ok( null === UNDT_Company_View::apply_chamber( 'aek-nr' ), 'Eine Kammer aus einem anderen Land wird abgelehnt' );
undt_ok( null === UNDT_Company_View::apply_chamber( '' ), 'Ohne Kammer geht es bei Ärzten nicht' );

$undt_values = UNDT_Company_View::apply_chamber( 'aek-by' );
undt_ok( is_array( $undt_values ) && 'Bayerische Landesärztekammer' === UNDT_Store::get( 'chamber_name' ) && 'https://www.blaek.de' === UNDT_Store::get( 'chamber_url' ), 'Name und Website stehen danach in Firma' );
undt_ok( 0 === strpos( (string) UNDT_Store::get( 'prof_rules' ), "Bundesärzteordnung (BÄO)\n" ) && 'aek-by' === UNDT_Store::get( 'chamber_ref' ), 'Dazu die Regelungen und die Kennung der Kammer' );
undt_ok( 'Bundesrepublik Deutschland' === UNDT_Store::get( 'job_title_country' ) && 'Praxis Muster' === UNDT_Store::get( 'company_name' ) && ! isset( $undt_values['job_title_country'] ), 'Alles andere bleibt, auch der Staat der Verleihung' );

$undt_box = UNDT_Company_View::chamber_box();
undt_ok( false !== strpos( $undt_box, 'Stand 2026-10' ) && false !== strpos( $undt_box, 'Bezeichnung noch prüfen' ), 'Danach zeigt der Kasten den Stand und was noch zu prüfen ist' );

$GLOBALS['undt_options']['undt_company']['chamber_url'] = 'https://alt.example';
UNDT_Store::flush();
$undt_sugg = UNDT_Professions::suggestion( UNDT_Store::company() );
undt_ok( array( 'chamber_url' => 'https://www.blaek.de' ) === $undt_sugg['changed'], 'Weicht die Kammer von den Daten ab, fällt das auf' );
undt_ok( false !== strpos( UNDT_Company_View::chamber_box(), 'Die Kammerdaten haben sich geändert' ), 'Und die Seite sagt es' );

$GLOBALS['undt_options']['undt_company']['job_title'] = 'Zahnärztin';
UNDT_Store::flush();
undt_ok( '' === UNDT_Professions::suggestion( UNDT_Store::company() )['applied'] && false !== strpos( UNDT_Company_View::chamber_box(), 'Bayerische Landeszahnärztekammer' ), 'Nach einem Wechsel des Berufs gilt wieder der Vorschlag' );
$GLOBALS['undt_options']['undt_company']['job_title'] = 'Ärztin';
$GLOBALS['undt_options']['undt_company']['state']     = 'HH';
UNDT_Store::flush();
undt_ok( '' === UNDT_Professions::suggestion( UNDT_Store::company() )['applied'], 'Ebenso nach einem Wechsel des Landes' );

undt_seed( $undt_profile, array( 'job_title' => 'Hebamme', 'state' => 'BY' ) );
$undt_values = UNDT_Company_View::apply_chamber( '' );
undt_ok( is_array( $undt_values ) && 'Hebammengesetz (HebG)' === UNDT_Store::get( 'prof_rules' ) && '' === UNDT_Store::get( 'chamber_name' ), 'Hebamme: nur die Regelungen, keine Kammer' );
undt_ok( null === UNDT_Company_View::apply_chamber( 'aek-by' ), 'Eine Kammer lässt sich dort nicht unterschieben' );

/* -------------------------------------------------- 4. Seite Firma ---- */

undt_head( 'Seite Firma' );

undt_seed( $undt_profile, array( 'job_title' => 'Arzt', 'state' => 'NW' ) );
$undt_page = undt_html( array( 'UNDT_Company_View', 'form' ) );
undt_ok( false !== strpos( $undt_page, 'data-undt-profession-choice' ) && false !== strpos( $undt_page, '<optgroup label="Heilberufe">' ) && false !== strpos( $undt_page, '<option value="Arzt" selected="selected">Arzt</option>' ), 'Berufsbezeichnung als Auswahl nach Gruppen' );
undt_ok( false !== strpos( $undt_page, 'name="undt_company[job_title]" value="Arzt"' ) && false !== strpos( $undt_page, 'data-undt-profession-text data-undt-hide' ), 'Gespeichert wird das Textfeld, bei bekannter Bezeichnung ausgeblendet' );
undt_ok( false !== strpos( $undt_page, 'type="hidden" id="undt-chamber_ref" name="undt_company[chamber_ref]"' ) && false === strpos( $undt_page, 'Übernommene Kammer' ), 'Die Kennung der Kammer steht versteckt im Formular, ohne Zeile' );
undt_ok( false !== strpos( $undt_page, 'data-undt-part="chamberbox"' ) && false !== strpos( $undt_page, 'mehrere Kammern' ) && false !== strpos( $undt_page, 'data-undt-chamber-choice' ), 'NRW: Auswahl der Kammer mit Teilgebiet' );
undt_ok( false !== strpos( $undt_page, 'Regierungsbezirke Arnsberg, Detmold, Münster' ) && false !== strpos( $undt_page, 'Zuständige Kammer prüfen' ), 'Mit Hilfetext und Link auf das Verzeichnis' );
undt_ok( false !== strpos( $undt_page, '<div class="undt-fsection" data-undt-section="authority" hidden>' ), 'Ohne Erlaubnispflicht bleibt der Abschnitt Aufsichtsbehörde verborgen, samt Hilfe' );

undt_seed( $undt_profile, array( 'job_title' => 'Life Coach' ) );
$undt_page = undt_html( array( 'UNDT_Company_View', 'form' ) );
undt_ok( false !== strpos( $undt_page, '<option value="__other" selected="selected">' ) && false === strpos( $undt_page, 'data-undt-profession-text data-undt-hide' ), 'Eine eigene Bezeichnung: „Andere Bezeichnung …“ mit sichtbarem Textfeld' );
undt_ok( '' === UNDT_Company_View::chamber_box(), 'Dazu kein Vorschlag' );

undt_seed( $undt_profile, array( 'job_title' => 'Zahnarzt' ) );
undt_ok( false !== strpos( UNDT_Company_View::chamber_box(), 'Wähle unter „Anschrift“ dein Bundesland' ), 'Ohne Bundesland ein Hinweis darauf' );

undt_seed( $undt_profile, array( 'job_title' => 'Augenoptikerin', 'state' => 'BY' ) );
$undt_box = UNDT_Company_View::chamber_box();
undt_ok( false !== strpos( $undt_box, 'Handwerkskammer trägst du selbst ein' ) && false !== strpos( $undt_box, 'Handwerksordnung (HwO)' ) && false !== strpos( $undt_box, 'zdh.de' ), 'Gesundheitshandwerk: Regelungen, Kammer selbst, Link zum ZDH' );

$undt_permits = UNDT_Professions::permits();
undt_ok( 4 === count( $undt_permits ) && 'Erlaubnis nach § 34c GewO' === $undt_permits['immobilienmakler']['note'] && '' !== $undt_permits['heilpraktiker']['authority'], 'Erlaubnispflichtige Tätigkeiten mit Text und zuständiger Stelle' );
undt_seed( array_merge( $undt_profile, array( 'needs_authority' => 1 ) ), array() );
$undt_page = undt_html( array( 'UNDT_Company_View', 'form' ) );
undt_ok( false !== strpos( $undt_page, 'data-undt-permit-choice' ) && false !== strpos( $undt_page, 'data-note="Erlaubnis nach § 34d GewO"' ), 'Die Auswahl steht im Abschnitt Aufsichtsbehörde' );

$undt_fields = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER );
undt_ok( ! isset( $undt_fields['chamber_ref'] ), 'Die Kennung ist kein dynamischer Wert und kein Shortcode' );

/* ------------------------------------------------- 5. Speichern ---- */

undt_head( 'Speichern' );

undt_seed( $undt_profile, array( 'job_title' => 'Steuerberaterin', 'state' => 'HH' ) );
$undt_answer = UNDT_Autosave::save( 'quick:chamber', array( 'value' => key( UNDT_Professions::chambers( 'steuerberater', 'HH' ) ) ) );
undt_ok( ! is_wp_error( $undt_answer ) && isset( $undt_answer['values']['chamber_name'], $undt_answer['parts']['chamberbox'] ), 'quick:chamber antwortet mit den Angaben und dem neuen Kasten' );
undt_ok( is_wp_error( UNDT_Autosave::save( 'quick:chamber', array( 'value' => 'aek-by' ) ) ), 'Eine unpassende Kammer gibt einen Fehler' );
$undt_answer = UNDT_Autosave::save( 'company', array( 'undt_company' => array_merge( UNDT_Store::company(), array( 'job_title' => 'Steuerberater' ) ) ) );
undt_ok( isset( $undt_answer['parts']['chamberbox'] ), 'Nach jedem Speichern von Firma kommt der Kasten neu mit' );

$GLOBALS['undt_user'] = array( 'roles' => array( 'subscriber' ), 'caps' => array( 'read' => true ) );
undt_ok( is_wp_error( UNDT_Autosave::save( 'quick:chamber', array( 'value' => '' ) ) ), 'Ohne Recht keine Übernahme' );
$GLOBALS['undt_user'] = array( 'roles' => array( 'administrator' ), 'caps' => array( 'manage_options' => true, 'edit_posts' => true ) );

/* --------------------------------------------- 6. Suchmaschinen ---- */

undt_head( 'Art der Firma aus dem Beruf' );

undt_ok( 'Dentist' === UNDT_Professions::schema_type( 'Zahnärztin' ) && 'Attorney' === UNDT_Professions::schema_type( 'Rechtsanwalt' ) && 'AccountingService' === UNDT_Professions::schema_type( 'Steuerberater' ), 'Zahnarztpraxis, Kanzlei, Steuerberatung' );
undt_ok( '' === UNDT_Professions::schema_type( 'Psychotherapeutin' ) && '' === UNDT_Professions::schema_type( 'Hebamme' ), 'Ohne passenden Typ kein Vorschlag' );

undt_seed( $undt_profile, array( 'job_title' => 'Zahnärztin' ) );
undt_seed_module( 'seo', array( 'schema_type' => 'Organization' ) );
$undt_page = undt_html( static function () { UNDT_Module_View::render( 'seo' ); } );
undt_ok( false !== strpos( $undt_page, 'data-undt-type-apply="Dentist"' ) && false !== strpos( $undt_page, 'Passt zu deinem Beruf (Zahnärztin): <strong>Zahnarztpraxis</strong>.' ), 'Bei „Organisation“ schlägt die Seite die Zahnarztpraxis vor' );
undt_seed_module( 'seo', array( 'schema_type' => 'Restaurant' ) );
$undt_page = undt_html( static function () { UNDT_Module_View::render( 'seo' ); } );
undt_ok( false === strpos( $undt_page, 'data-undt-type-apply' ), 'Eine bewusst gewählte Art bleibt ohne Vorschlag' );

/* --------------------------------------------- 7. Skript ---- */

undt_head( 'Skript' );

$undt_js      = (string) file_get_contents( $undt_base . 'admin/assets/admin.js' );
$undt_src     = (string) file_get_contents( $undt_base . 'admin/class-undt-company-view.php' ) . (string) file_get_contents( $undt_base . 'admin/class-undt-module-view.php' );
$undt_missing = array();
foreach ( array( 'data-undt-profession-choice', 'data-undt-profession-text', 'data-undt-chamber-choice', 'data-undt-chamber-apply', 'data-undt-permit-choice', 'data-undt-permit-apply', 'data-undt-type-apply' ) as $undt_attr ) {
	if ( false === strpos( $undt_src, $undt_attr ) || false === strpos( $undt_js, '[' . $undt_attr ) ) {
		$undt_missing[] = $undt_attr;
	}
}
undt_ok( empty( $undt_missing ), 'Ansicht und Skript verwenden dieselben Attribute' . ( $undt_missing ? ': ' . implode( ', ', $undt_missing ) : '' ) );
undt_ok( false !== strpos( $undt_js, "var PROFESSION_OTHER = '__other';" ) && '__other' === UNDT_Professions::OTHER, 'Skript und Klasse kennen denselben Wert für „Andere Bezeichnung“' );
undt_ok( false !== strpos( $undt_js, "quickSave( 'chamber'," ), 'Übernehmen geht über quick:chamber' );

/* ---------------------------------------------------------- Ergebnis ---- */

echo "\n";

if ( ! empty( $GLOBALS['undt_fails'] ) ) {
	echo "\033[31m" . (int) $GLOBALS['undt_fails'] . " Pruefungen fehlgeschlagen\033[0m\n";
	exit( 1 );
}

echo "\033[32mAlle Pruefungen bestanden\033[0m\n";
