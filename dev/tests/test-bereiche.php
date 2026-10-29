<?php
/**
 * Pruefungen fuer 0.6.0, Etappe 4: die Seiten der Inhaltsbereiche,
 * Einstellungen und Sichern & Uebertragen.
 *
 * Feiertage je Bundesland, Oeffnungszeiten mit „geöffnet“-Schalter, Social
 * Media mit Plattform-Erkennung, Infobanner mit drei Arten und Zeitraum,
 * Preise in Gruppen, FAQ, Suchmaschinen mit einem Koordinaten-Feld und dem
 * Logo aus dem Theme, Einspielen mit Vorschau, Auswahl, Sicherung und
 * Rueckgaengig. Wie die Seiten in WordPress aussehen, zeigt dev/verify.php.
 */
require __DIR__ . '/harness.php';
require UNDT_TEST_BASE . 'admin/class-undt-fields.php';
require UNDT_TEST_BASE . 'admin/class-undt-controls.php';
require UNDT_TEST_BASE . 'admin/class-undt-copy.php';

function settings_fields( $group ) { echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '" />'; }
function do_shortcode( $code ) { return '<p>Ausgabe von ' . esc_html( $code ) . '</p>'; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />'; }
function human_time_diff( $from, $to = 0 ) { return '5 Minuten'; }
function settings_errors() {}
function esc_html_e( $t, $d = null ) { echo esc_html( $t ); }
function esc_attr_e( $t, $d = null ) { echo esc_attr( $t ); }

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
undt_set_modules( array( 'hours' => 1, 'prices' => 1, 'social' => 1, 'faq' => 1, 'banner' => 1, 'seo' => 1 ) );
$GLOBALS['undt_options']['undt_settings']['roles'] = array( 'editor' );
UNDT_Modules::flush();
$GLOBALS['undt_options']['undt_setup_done'] = 1;
undt_as( 'administrator' );

undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'standard' ), array( 'company_name' => 'Praxis Muster', 'owner_name' => 'Erika Muster', 'street' => 'Hauptstraße 4', 'zip' => '83278', 'city' => 'Traunstein', 'email' => 'info@praxis.test', 'state' => 'BY' ) );

/* ------------------------------------------------------ 1. Feiertage ---- */

undt_head( 'Feiertage je Bundesland' );

undt_ok( '2024-03-31' === UNDT_Holidays::easter( 2024 )->format( 'Y-m-d' ) && '2026-04-05' === UNDT_Holidays::easter( 2026 )->format( 'Y-m-d' ) && '2027-03-28' === UNDT_Holidays::easter( 2027 )->format( 'Y-m-d' ), 'Ostersonntag nach der Osterformel' );

$undt_counts = array( 'BW' => 12, 'BY' => 12, 'SL' => 12, 'BB' => 12, 'NW' => 11, 'RP' => 11, 'BE' => 10, 'HE' => 10, 'HB' => 10, 'HH' => 10, 'NI' => 10, 'SH' => 10, 'SN' => 11, 'ST' => 11, 'TH' => 11, 'MV' => 11 );
$undt_wrong  = array();
foreach ( $undt_counts as $undt_state => $undt_count ) {
	if ( count( UNDT_Holidays::for_state( $undt_state, 2026 ) ) !== $undt_count ) {
		$undt_wrong[] = $undt_state . '=' . count( UNDT_Holidays::for_state( $undt_state, 2026 ) );
	}
}
undt_ok( empty( $undt_wrong ), 'Je Land die Zahl der landesweiten Feiertage' . ( $undt_wrong ? ': ' . implode( ', ', $undt_wrong ) : '' ) );

$undt_by = UNDT_Holidays::for_state( 'BY', 2026 );
undt_ok( isset( $undt_by['2026-04-03'], $undt_by['2026-05-14'], $undt_by['2026-06-04'], $undt_by['2026-11-01'] ), 'Bayern: Karfreitag, Himmelfahrt, Fronleichnam, Allerheiligen' );
undt_ok( ! isset( $undt_by['2026-08-15'] ), 'Mariä Himmelfahrt gilt in Bayern nicht landesweit und fehlt' );
undt_ok( isset( UNDT_Holidays::for_state( 'SL', 2026 )['2026-08-15'] ), 'Im Saarland gehört sie dazu' );
undt_ok( 'Buß- und Bettag' === UNDT_Holidays::for_state( 'SN', 2026 )['2026-11-18'] && isset( UNDT_Holidays::for_state( 'SN', 2023 )['2023-11-22'] ), 'Buß- und Bettag: Mittwoch vor dem 23. November' );
undt_ok( isset( UNDT_Holidays::for_state( 'TH', 2026 )['2026-09-20'] ) && isset( UNDT_Holidays::for_state( 'MV', 2026 )['2026-03-08'] ), 'Weltkindertag in Thüringen, Frauentag in Mecklenburg-Vorpommern' );
undt_ok( array() === UNDT_Holidays::for_state( 'XX', 2026 ) && array() === UNDT_Holidays::for_state( '', 2026 ), 'Unbekanntes Land: keine Feiertage' );

$undt_up = UNDT_Holidays::upcoming( 'BY', '2026-10-06' );
undt_ok( '2026-11-01' === $undt_up[0]['date'] && 'Allerheiligen' === $undt_up[0]['label'], 'Die nächsten zwölf Monate beginnen mit dem nächsten Feiertag' );
undt_ok( '2027-10-03' === $undt_up[ count( $undt_up ) - 1 ]['date'] && 12 === count( $undt_up ), 'Und enden vor dem gleichen Tag im nächsten Jahr' );

/* -------------------------------------------------- 2. Oeffnungszeiten ---- */

undt_head( 'Öffnungszeiten' );

$undt_days = UNDT_Hours::normalize(
	array(
		'mon' => array( 'open' => '1', 'slots' => array( array( 'from' => '08:00', 'to' => '12:00' ) ) ),
		'tue' => array( 'open' => '0', 'closed' => '0' ),
		'wed' => array( 'closed' => '1' ),
	)
);
undt_ok( 0 === $undt_days['mon']['closed'] && 1 === $undt_days['tue']['closed'] && 1 === $undt_days['wed']['closed'], 'Der Schalter fragt „geöffnet“, gespeichert bleibt closed' );

undt_seed_module(
	'hours',
	array(
		'days'    => array(
			'mon' => array( 'open' => 1, 'slots' => array( array( 'from' => '08:00', 'to' => '12:00' ), array( 'from' => '14:00', 'to' => '18:00' ) ) ),
			'thu' => array( 'open' => 1, 'slots' => array( array( 'from' => '08:00', 'to' => '12:00' ) ) ),
			'sun' => array( 'open' => 0 ),
		),
		'special' => array( array( 'date' => '2026-12-24', 'closed' => 1, 'note' => 'Heiligabend' ) ),
	)
);

$undt_page = undt_html( static function () { UNDT_Module_View::render( 'hours' ); } );
undt_ok( false !== strpos( $undt_page, 'name="undt_hours[days][mon][open]" value="0"' ) && false !== strpos( $undt_page, 'name="undt_hours[days][mon][open]" value="1" checked' ), 'Je Tag ein Schalter mit verstecktem Partner' );
undt_ok( false !== strpos( $undt_page, 'data-undt-slot2 data-active="1"' ) && false !== strpos( $undt_page, 'data-undt-slot2 data-active="0"' ), 'Die Pause steht nur dort, wo es eine gibt' );
undt_ok( false !== strpos( $undt_page, 'Montag auf Dienstag bis Freitag übertragen' ), 'Montag auf die Werktage übertragen' );
undt_ok( false !== strpos( $undt_page, 'data-undt-part="today"' ) && false !== strpos( $undt_page, 'Heute 08:00–12:00 Uhr.' ) && false !== strpos( $undt_page, 'So steht es heute, Donnerstag, auf deiner Website.' ), 'Die Statuszeile sagt, was heute gilt' );
undt_ok( false !== strpos( $undt_page, 'data-undt-part="preview"' ) && false !== strpos( $undt_page, 'Ausgabe von [undt_hours]' ), 'Die Vorschau zeigt die echte Ausgabe' );
undt_ok( false !== strpos( $undt_page, 'Feiertage in Bayern eintragen' ) && false !== strpos( $undt_page, '&quot;2026-10-03&quot;' ), 'Feiertage nach dem Bundesland aus Firma, ab heute' );
undt_ok( false !== strpos( $undt_page, 'name="undt_hours[special][0][closed]"' ) && false !== strpos( $undt_page, '<option value="1" selected="selected">Geschlossen</option>' ), 'Termine mit Art „Geschlossen“ oder „Andere Zeiten“' );
undt_ok( false !== strpos( $undt_page, '__UNDT_INDEX__' ) && false !== strpos( $undt_page, 'data-undt-repeater data-undt-next="1"' ), 'Neue Termine kommen aus der Vorlage' );
undt_ok( false !== strpos( $undt_page, 'Darstellung' ) && false !== strpos( $undt_page, 'name="undt_hours[day_language]"' ), 'Darstellung zugeklappt mit Sprache' );

$GLOBALS['undt_options']['undt_company']['state'] = '';
UNDT_Store::flush();
$undt_page = undt_html( static function () { UNDT_Module_View::render( 'hours' ); } );
undt_ok( false === strpos( $undt_page, 'data-undt-holidays' ) && false !== strpos( $undt_page, 'das Bundesland ein' ), 'Ohne Bundesland ein Hinweis auf Firma statt des Knopfs' );
$GLOBALS['undt_options']['undt_company']['state'] = 'BY';
UNDT_Store::flush();

/* --------------------------------------------------------- 3. Social ---- */

undt_head( 'Social Media' );

$undt_detect = array(
	'https://www.instagram.com/praxis'      => 'instagram',
	'instagram.com/praxis'                  => 'instagram',
	'https://m.facebook.com/praxis'         => 'facebook',
	'https://twitter.com/praxis'            => 'x',
	'https://youtu.be/abc'                  => 'youtube',
	'https://de.linkedin.com/company/x'     => 'linkedin',
	'https://chaos.social/@praxis'          => 'mastodon',
	'https://bsky.app/profile/praxis.de'    => 'bluesky',
	'https://wa.me/4986112345'              => 'whatsapp',
	'https://example.org/team'              => 'other',
	''                                      => '',
);
$undt_wrong = array();
foreach ( $undt_detect as $undt_url => $undt_expected ) {
	if ( UNDT_Modules::detect_platform( $undt_url ) !== $undt_expected ) {
		$undt_wrong[] = $undt_url . ' => ' . UNDT_Modules::detect_platform( $undt_url );
	}
}
undt_ok( empty( $undt_wrong ), 'Die Plattform wird an der Adresse erkannt' . ( $undt_wrong ? ': ' . implode( ', ', $undt_wrong ) : '' ) );
undt_ok( 'facebook' !== UNDT_Modules::detect_platform( 'https://notfacebook.com/x' ), 'Ein Rechner, der nur so endet, zählt nicht' );

undt_seed_module( 'social', array( 'items' => array( array( 'platform' => '', 'url' => 'https://www.xing.com/profile/x', 'label' => '' ) ) ) );
undt_ok( 'xing' === UNDT_Content::value( 'social', 'items' )[0]['platform'], 'Ohne Plattform zählt beim Speichern die Adresse' );

$undt_page = undt_html( static function () { UNDT_Module_View::render( 'social' ); } );
undt_ok( false !== strpos( $undt_page, 'data-undt-social-new' ) && false !== strpos( $undt_page, 'data-undt-social-add disabled' ), 'Profil hinzufügen über die Adresse' );
undt_ok( false !== strpos( $undt_page, 'name="undt_social[items][0][platform]" value="xing"' ) && false !== strpos( $undt_page, 'name="undt_social[items][0][label]"' ), 'Plattform und Adresse versteckt, die Beschriftung bleibt offen' );
undt_ok( false !== strpos( $undt_page, 'data-undt-hosts=' ) && false !== strpos( $undt_page, 'undt-icon-library' ), 'Das Skript bekommt Rechnerliste und Symbole' );
undt_ok( false !== strpos( $undt_page, 'name="undt_social[layout]"' ) && false !== strpos( $undt_page, 'In einer Reihe' ), 'Anordnung als Auswahlleiste' );
undt_ok( false !== strpos( $undt_page, 'Erweitert' ) && false !== strpos( $undt_page, 'rel=&quot;me&quot;' ), 'rel="me" unter Erweitert' );
undt_ok( false !== strpos( $undt_page, 'data-undt-copy="[undt_social]"' ), 'Admins kopieren den Shortcode' );

/* -------------------------------------------------------- 4. Banner ---- */

undt_head( 'Infobanner' );

$undt_types = array( 'info' => 'info', 'success' => 'info', 'warning' => 'warning', 'urgent' => 'warning', 'neutral' => 'neutral', 'quatsch' => 'neutral' );
$undt_wrong = array();
foreach ( $undt_types as $undt_stored => $undt_shown ) {
	$GLOBALS['undt_options']['undt_banner'] = array( 'type' => $undt_stored );
	UNDT_Content::flush();
	if ( UNDT_Blocks::banner_type() !== $undt_shown ) {
		$undt_wrong[] = $undt_stored;
	}
}
undt_ok( empty( $undt_wrong ), 'Drei Arten; Positiv wird Info, Dringend wird Warnung' . ( $undt_wrong ? ': ' . implode( ', ', $undt_wrong ) : '' ) );
undt_ok( array( 'neutral', 'info', 'warning' ) === array_keys( UNDT_Modules::get( 'banner' )['fields']['type']['choices'] ), 'Zur Wahl stehen Neutral, Info, Warnung' );

$undt_css = UNDT_Blocks::css();
undt_ok( false !== strpos( $undt_css, '.undt-banner--info{' ) && false !== strpos( $undt_css, '.undt-banner--warning{' ) && false === strpos( $undt_css, '--success' ) && false === strpos( $undt_css, '--urgent' ), 'Kein Rot und kein Grün mehr im Banner' );

undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => 'Betriebsferien', 'date_from' => '2026-09-01', 'date_to' => '2026-09-10' ) );
undt_ok( UNDT_Blocks::banner_active(), 'Im Zeitraum erscheint das Banner, der letzte Tag zählt mit' );
undt_ok( ! UNDT_Blocks::banner_in_period( '2026-09-11' ) && ! UNDT_Blocks::banner_in_period( '2026-08-31' ) && UNDT_Blocks::banner_in_period( '2026-09-01' ), 'Davor und danach nicht' );
undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => 'Betriebsferien', 'date_from' => '2026-09-11' ) );
undt_ok( ! UNDT_Blocks::banner_active() && '' === UNDT_Dynamic::value( 'banner_show' ), 'Ein eingeplantes Banner ist auch für Bricks und Etch noch aus' );
undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => 'Betriebsferien', 'date_from' => 'morgen' ) );
undt_ok( '' === UNDT_Content::value( 'banner', 'date_from' ) && UNDT_Blocks::banner_active(), 'Ein ungültiges Datum fällt weg' );

undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => '', 'dismissible' => 1 ) );
$undt_markup = UNDT_Blocks::banner_markup( true );
undt_ok( false !== strpos( $undt_markup, 'Hier steht dein Hinweis.' ) && false === strpos( $undt_markup, '<script' ), 'Die Vorschau zeigt einen Platzhalter und kein Skript' );
undt_ok( '' === UNDT_Blocks::banner(), 'Auf der Website erscheint ohne Text nichts' );

undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => 'Betriebsferien', 'auto_output' => 0, 'date_from' => '2026-12-18' ) );
$undt_page = undt_html( static function () { UNDT_Module_View::render( 'banner' ); } );
undt_ok( false !== strpos( $undt_page, 'Eingeplant' ) && false !== strpos( $undt_page, 'Erscheint ab 18. Dezember 2026.' ), 'Der Kopf sagt, wann es erscheint' );
undt_ok( false !== strpos( $undt_page, 'data-undt-banner-preview' ) && false !== strpos( $undt_page, 'undt-bsite__page' ), 'Vorschau auf einer angedeuteten Seite' );
undt_ok( false !== strpos( $undt_page, 'data-undt-count="140"' ) && false !== strpos( $undt_page, '14 / 140 Zeichen' ), 'Der Zähler zählt mit' );
undt_ok( false !== strpos( $undt_page, 'name="undt_banner[date_from]" value="2026-12-18"' ) && false !== strpos( $undt_page, 'name="undt_banner[date_to]"' ), 'Zeitraum mit zwei Daten' );
undt_ok( false !== strpos( $undt_page, 'name="undt_banner[auto_output]" value="0" checked' ) && false !== strpos( $undt_page, 'data-undt-banner-manual>' ), 'Selbst platziert: der Shortcode steht gleich darunter' );

undt_as( 'editor' );
$undt_page = undt_html( static function () { UNDT_Module_View::render( 'banner' ); } );
undt_ok( false === strpos( $undt_page, 'undt_banner[auto_output]' ) && false === strpos( $undt_page, 'data-undt-copy' ), 'Redakteure sehen weder Platzierung noch Kopierknopf' );
undt_ok( false !== strpos( $undt_page, 'name="undt_banner[text]"' ), 'Den Text pflegen sie' );
$undt_before = UNDT_Content::value( 'banner', 'auto_output' );
undt_seed_module( 'banner', array_merge( UNDT_Content::all( 'banner' ), array( 'text' => 'Neu' ) ) );
undt_ok( $undt_before === UNDT_Content::value( 'banner', 'auto_output' ), 'Was nicht im Formular steht, bleibt' );
undt_as( 'administrator' );

/* -------------------------------------------------------- 5. Preise ---- */

undt_head( 'Preise & Leistungen' );

undt_seed_module(
	'prices',
	array(
		'items' => array(
			array( 'group' => 'Beratung', 'label' => 'Erstberatung', 'price' => '45 €', 'note' => 'online' ),
			array( 'group' => 'Beratung', 'label' => 'Folgetermin', 'price' => '35 €' ),
			array( 'group' => 'Kurse', 'label' => 'Rückenkurs', 'price' => '120 €' ),
		),
	)
);
$undt_page = undt_html( static function () { UNDT_Module_View::render( 'prices' ); } );
undt_ok( 2 === substr_count( $undt_page, 'undt-box undt-pgroup" data-undt-pgroup>' ) - 1, 'Zwei Gruppen, dazu die Vorlage' );
undt_ok( false !== strpos( $undt_page, 'name="undt_prices[items][1][group]" value="Beratung"' ) && false !== strpos( $undt_page, 'name="undt_prices[items][2][group]" value="Kurse"' ), 'Jede Leistung trägt ihre Gruppe versteckt' );
undt_ok( false !== strpos( $undt_page, 'name="undt_prices[items][0][note]" value="online"' ), 'Der Zusatz bleibt erhalten' );
undt_ok( false !== strpos( $undt_page, 'data-undt-pgroups data-undt-next="3"' ) && false !== strpos( $undt_page, 'data-undt-pitem-template' ), 'Neue Leistungen und Gruppen aus Vorlagen' );
undt_ok( false !== strpos( $undt_page, 'name="undt_prices[vat_note]"' ) && false !== strpos( $undt_page, 'Firma › Steuer' ), 'Hinweis zur Umsatzsteuer als Schalter' );

undt_seed_module( 'prices', array() );
$undt_page = undt_html( static function () { UNDT_Module_View::render( 'prices' ); } );
undt_ok( false !== strpos( $undt_page, 'name="undt_prices[items][0][label]"' ), 'Ohne Leistungen steht eine leere Gruppe zum Anfangen da' );

/* ----------------------------------------------------------- 6. FAQ ---- */

undt_head( 'FAQ' );

undt_seed_module( 'faq', array( 'items' => array( array( 'group' => 'Allgemein', 'question' => 'Gibt es Parkplätze?', 'answer' => 'Ja.' ) ) ) );
$undt_page = undt_html( static function () { UNDT_Module_View::render( 'faq' ); } );
undt_ok( false !== strpos( $undt_page, 'aria-expanded="false"' ) && false !== strpos( $undt_page, 'Gibt es Parkplätze?</span>' ), 'Gespeicherte Fragen stehen zugeklappt mit ihrem Titel' );
undt_ok( false !== strpos( $undt_page, 'aria-expanded="true"' ), 'Eine neue Frage steht offen' );
undt_ok( false !== strpos( $undt_page, 'name="undt_faq[items][0][group]" value="Allgemein"' ), 'Die Gruppe bleibt erhalten' );
undt_ok( false !== strpos( $undt_page, 'Für Suchmaschinen' ) && false !== strpos( $undt_page, 'name="undt_faq[schema]" value="1" />' ), 'FAQ-Auszeichnung zugeklappt und aus' );

// Seit 0.6.1 auch ohne Shortcode, standardmaessig auf der Startseite.
undt_ok( false !== strpos( $undt_page, 'name="undt_faq[schema_page]"' ) && false !== strpos( $undt_page, '<option value="front" selected="selected">Startseite</option>' ), 'Auswahl der Seite, voreingestellt die Startseite' );
undt_ok( 'front' === UNDT_Content::sanitize( 'faq', array( 'schema_page' => 'irgendwas' ) )['schema_page'] && '42' === UNDT_Content::sanitize( 'faq', array( 'schema_page' => '42' ) )['schema_page'] && 'block' === UNDT_Content::sanitize( 'faq', array( 'schema_page' => 'block' ) )['schema_page'], 'Gespeichert wird front, block oder eine Seiten-ID' );

$undt_faq_head = static function () {
	UNDT_SchemaOrg::reset_faq();
	ob_start();
	UNDT_SchemaOrg::faq_head();
	return (string) ob_get_clean();
};
undt_seed_module( 'faq', array( 'schema' => 1, 'items' => array( array( 'group' => '', 'question' => 'Gibt es Parkplätze?', 'answer' => 'Ja.' ) ) ) );
$GLOBALS['undt_front'] = true;
$undt_head_html = $undt_faq_head();
undt_ok( false !== strpos( $undt_head_html, '"@type":"FAQPage"' ) && false !== strpos( $undt_head_html, 'Gibt es Parkplätze?' ), 'Auf der Startseite im Kopf, ohne Shortcode' );
undt_ok( '' === UNDT_SchemaOrg::faq_script( UNDT_Content::group_rows( UNDT_Content::rows( 'faq', 'items' ) ) ), 'Steht dort auch der Shortcode, kommt sie nicht ein zweites Mal' );
$GLOBALS['undt_front'] = false;
undt_ok( '' === $undt_faq_head(), 'Auf anderen Seiten nicht' );
undt_seed_module( 'faq', array( 'schema' => 1, 'schema_page' => '42', 'items' => array( array( 'group' => '', 'question' => 'Gibt es Parkplätze?', 'answer' => 'Ja.' ) ) ) );
$GLOBALS['undt_queried'] = 42;
undt_ok( '' !== $undt_faq_head(), 'Auf der gewählten Seite' );
undt_seed_module( 'faq', array( 'schema' => 1, 'schema_page' => 'block', 'items' => array( array( 'group' => '', 'question' => 'Gibt es Parkplätze?', 'answer' => 'Ja.' ) ) ) );
undt_ok( '' === $undt_faq_head(), '„Nur wo der Baustein steht“: im Kopf nichts' );
undt_seed_module( 'faq', array( 'schema' => 0, 'items' => array( array( 'group' => '', 'question' => 'Gibt es Parkplätze?', 'answer' => 'Ja.' ) ) ) );
$GLOBALS['undt_front']   = true;
undt_ok( '' === $undt_faq_head(), 'Ohne den Schalter nirgends' );
$GLOBALS['undt_front']   = false;
$GLOBALS['undt_queried'] = 0;
UNDT_SchemaOrg::reset_faq();

/* -------------------------------------------------- 7. Suchmaschinen ---- */

undt_head( 'Suchmaschinen' );

$undt_geo = array(
	'47.9312, 12.6458'   => array( '47.9312', '12.6458' ),
	'47.9312,12.6458'    => array( '47.9312', '12.6458' ),
	'47,9312 12,6458'    => array( '47.9312', '12.6458' ),
	'47,9312; 12,6458'   => array( '47.9312', '12.6458' ),
	'-33.86, 151.21'     => array( '-33.86', '151.21' ),
	'91, 12'             => null,
	'Traunstein'         => null,
	''                   => null,
);
$undt_wrong = array();
foreach ( $undt_geo as $undt_in => $undt_out ) {
	if ( UNDT_Content::parse_geo( $undt_in ) !== $undt_out ) {
		$undt_wrong[] = $undt_in;
	}
}
undt_ok( empty( $undt_wrong ), 'Koordinaten aus einem Feld, so wie Google Maps sie kopiert' . ( $undt_wrong ? ': ' . implode( ' | ', $undt_wrong ) : '' ) );

undt_seed_module( 'seo', array( 'geo' => '47.9312, 12.6458', 'schema_type' => 'Attorney' ) );
undt_ok( '47.9312' === UNDT_Content::value( 'seo', 'geo_lat' ) && '12.6458' === UNDT_Content::value( 'seo', 'geo_lng' ), 'Gespeichert wird weiter getrennt' );
undt_ok( ! array_key_exists( 'geo', UNDT_Content::all( 'seo' ) ), 'Das Eingabefeld selbst wird nicht gespeichert' );
undt_ok( 'Attorney' === UNDT_SchemaOrg::organization()['@type'], 'Kanzlei wird als Attorney ausgegeben' );
undt_ok( 'Kanzlei' === UNDT_Modules::schema_types()['Attorney'] && isset( UNDT_Modules::schema_types()['LegalService'] ), 'LegalService bleibt für bestehende Angaben' );
undt_ok( array( 'Organization', 'LocalBusiness', 'Physician', 'Dentist', 'VeterinaryCare', 'Attorney', 'NGO' ) === array_slice( array_keys( UNDT_Modules::schema_types() ), 0, 7 ), 'Die sieben aus dem Entwurf stehen vorn' );
undt_seed_module( 'seo', array( 'geo' => 'irgendwo' ) );
undt_ok( '' === UNDT_Content::value( 'seo', 'geo_lat' ) && '' === UNDT_Content::value( 'seo', 'geo_lng' ), 'Unlesbare Koordinaten werden geleert' );

undt_ok( ! isset( UNDT_Modules::get( 'seo' )['fields']['logo'] ), 'Kein Logo-Feld mehr' );
$GLOBALS['undt_theme_mods'] = array();
$GLOBALS['undt_options']['site_icon'] = 9;
undt_ok( 9 === UNDT_SchemaOrg::logo_id() && 'icon' === UNDT_SchemaOrg::logo_source(), 'Ohne Theme-Logo dient das Website-Icon' );
$GLOBALS['undt_theme_mods']['custom_logo'] = 7;
undt_ok( 7 === UNDT_SchemaOrg::logo_id() && 'theme' === UNDT_SchemaOrg::logo_source(), 'Das Logo des Themes geht vor' );
unset( $GLOBALS['undt_options']['site_icon'] );

undt_seed_module( 'seo', array( 'geo_lat' => '47.9312', 'geo_lng' => '12.6458', 'price_range' => '10 – 50 €' ) );
$undt_page = undt_html( static function () { UNDT_Module_View::render( 'seo' ); } );
undt_ok( false !== strpos( $undt_page, 'name="undt_seo[geo]"' ) && false !== strpos( $undt_page, 'value="47.9312, 12.6458"' ), 'Ein Feld für den Standort' );
undt_ok( false !== strpos( $undt_page, 'name="undt_seo[price_range]"' ) && false !== strpos( $undt_page, 'value="10 – 50 €" checked' ), 'Preisniveau als Knöpfe, ein freier Wert bleibt wählbar' );
undt_ok( false !== strpos( $undt_page, '<optgroup label="Weitere">' ), 'Seltene Arten unter „Weitere“' );
undt_ok( false !== strpos( $undt_page, 'data-undt-part="jsonld"' ) && false !== strpos( $undt_page, '&quot;@context&quot;' ), 'Der Code steht zum Ansehen da' );
undt_ok( false !== strpos( $undt_page, 'das Logo aus deinem Theme' ), 'Woher das Logo kommt' );
undt_ok( false === strpos( $undt_page, 'In Slim SEO verwenden' ), 'Ohne Slim SEO keine Platzhalter' );

define( 'SLIM_SEO_VER', '4.0' );
$undt_page = undt_html( static function () { UNDT_Module_View::render( 'seo' ); } );
undt_ok( false !== strpos( $undt_page, 'In Slim SEO verwenden' ) && false !== strpos( $undt_page, 'data-undt-copy="{{ undt.company_name }}"' ), 'Mit Slim SEO die Platzhalter zum Kopieren' );
undt_ok( false !== strpos( $undt_page, 'Slim SEO gibt deine Firmendaten aus' ), 'Die Statuszeile nennt, wer ausgibt' );
undt_seed_module( 'seo', array( 'output_mode' => 'always' ) );
$undt_status = undt_html( array( 'UNDT_Module_View', 'seo_status' ) );
undt_ok( false !== strpos( $undt_status, 'Dieses Plugin gibt deine Firmendaten aus' ) && false !== strpos( $undt_status, 'Google sieht dann zwei' ), 'Bei „Immer“ eine Warnung vor doppelter Ausgabe' );

/* ------------------------------------------------ 8. Antwort nach Speichern ---- */

undt_head( 'Teile, die nach dem Speichern nachziehen' );

undt_ok( array( 'today', 'preview' ) === array_keys( UNDT_Module_View::parts( 'hours' ) ), 'Öffnungszeiten: Statuszeile und Vorschau' );
undt_ok( array( 'preview', 'bannerhead' ) === array_keys( UNDT_Module_View::parts( 'banner' ) ), 'Infobanner: Vorschau und Kopf' );
undt_ok( array( 'seostatus', 'jsonld' ) === array_keys( UNDT_Module_View::parts( 'seo' ) ), 'Suchmaschinen: Status und Code' );
undt_ok( array() === UNDT_Module_View::parts( 'faq' ), 'FAQ hat nichts nachzuziehen' );

$undt_save = UNDT_Autosave::save( 'module:hours', array( 'undt_hours' => array( 'note' => 'Termine nach Vereinbarung' ) ) );
undt_ok( isset( $undt_save['parts']['preview'], $undt_save['parts']['today'] ), 'Die Antwort bringt die Teile mit' );
$undt_save = UNDT_Autosave::save( 'quick:banner', array( 'value' => '1' ) );
undt_ok( isset( $undt_save['parts']['bannerhead'] ), 'Auch der Schalter in den Schnelleinstellungen' );

/* ------------------------------------------------------ 9. Einstellungen ---- */

undt_head( 'Einstellungen' );

$GLOBALS['undt_options']['undt_settings']['updates'] = 1;
UNDT_Modules::flush();

$undt_page = undt_html(
	static function () {
		require UNDT_TEST_BASE . 'admin/views/settings.php';
	}
);
undt_ok( false === strpos( $undt_page, 'Daten liegen in der Option' ) && false === strpos( $undt_page, 'Autoload' ), 'Keine Optionsnamen und kein Autoload mehr' );
undt_ok( 6 === substr_count( $undt_page, 'class="undt-arealist__row"' ) && false !== strpos( $undt_page, 'Preislisten mit Gruppen' ), 'Bereiche mit Erklärung' );
undt_ok( false !== strpos( $undt_page, 'name="undt_settings[keep_data]" value="0" checked' ) && false !== strpos( $undt_page, 'Angaben behalten' ), 'Löschen als Wahl, Standard ist entfernen' );
undt_ok( false !== strpos( $undt_page, 'form="undt-recheck"' ) && false !== strpos( $undt_page, 'id="undt-recheck"' ), '„Jetzt prüfen“ schickt das eigene Formular ab' );
undt_ok( false !== strpos( $undt_page, 'Vorher eine Sicherung laden' ), 'Verweis auf Sichern' );
undt_ok( 0 === UNDT_Modules::sanitize_settings( array( 'keep_data' => '0' ) )['keep_data'] && 1 === UNDT_Modules::sanitize_settings( array( 'keep_data' => '1' ) )['keep_data'], 'Die Wahl wird wie der frühere Schalter gespeichert' );

/* ------------------------------------------------ 10. Sichern & Einspielen ---- */

undt_head( 'Einspielen mit Vorschau, Auswahl und Rückgängig' );

undt_set_modules( array( 'hours' => 1, 'prices' => 1, 'social' => 1, 'faq' => 1, 'banner' => 1, 'seo' => 1 ) );
undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => 'Alt' ) );
undt_seed_module( 'hours', array( 'note' => 'Gleich' ) );
$undt_file = UNDT_Transfer::export();
$undt_file['options']['undt_company']['company_name'] = 'Neue Praxis';
$undt_file['options']['undt_company']['city']         = 'Siegsdorf';
$undt_file['options']['undt_banner']['text']          = 'Neu';
unset( $undt_file['options']['undt_faq'] );

$undt_preview = UNDT_Transfer::preview( $undt_file );
$undt_areas   = array();
foreach ( $undt_preview['areas'] as $undt_area ) {
	$undt_areas[ $undt_area['key'] ] = $undt_area;
}
undt_ok( array( 'company', 'hours', 'prices', 'social', 'faq', 'banner', 'seo', 'settings' ) === array_keys( $undt_areas ), 'Firma, jeder Bereich und die Einstellungen' );
undt_ok( 'change' === $undt_areas['company']['state'] && '2 Angaben ändern sich' === $undt_areas['company']['summary'], 'Firma: zwei Angaben ändern sich' );
undt_ok( 'Firma / Name' === $undt_areas['company']['diff'][0]['k'] && 'Praxis Muster' === $undt_areas['company']['diff'][0]['old'] && 'Neue Praxis' === $undt_areas['company']['diff'][0]['new'], 'Mit altem und neuem Wert' );
undt_ok( 'same' === $undt_areas['hours']['state'] && 'Gleich wie hier' === $undt_areas['hours']['summary'], 'Gleiches heißt gleich' );
undt_ok( 'missing' === $undt_areas['faq']['state'], 'Was fehlt, heißt „Nicht in der Datei“' );
undt_ok( 'Praxis Muster' === UNDT_Store::get( 'company_name' ) && 'Alt' === UNDT_Content::value( 'banner', 'text' ), 'Die Vorschau ändert nichts' );
undt_ok( 'example.test' === $undt_preview['source'], 'Und nennt die Herkunft' );

UNDT_Transfer::backup();
$undt_report = UNDT_Transfer::import( $undt_file, array( 'banner' ) );
undt_ok( array( 'undt_banner' ) === $undt_report['imported'], 'Nur der gewählte Bereich wird eingespielt' );
undt_ok( 'Neu' === UNDT_Content::value( 'banner', 'text' ) && 'Praxis Muster' === UNDT_Store::get( 'company_name' ), 'Firma bleibt, wie sie war' );
undt_ok( ! empty( UNDT_Transfer::last_backup() ), 'Der Stand davor ist gesichert' );
undt_ok( UNDT_Transfer::undo() && 'Alt' === UNDT_Content::value( 'banner', 'text' ), 'Rückgängig spielt ihn zurück' );
undt_ok( array() === UNDT_Transfer::last_backup() && false === UNDT_Transfer::undo(), 'Und räumt die Sicherung weg' );

unset( $GLOBALS['undt_options']['undt_faq'] );
UNDT_Transfer::backup();
UNDT_Transfer::import( $undt_file );
$GLOBALS['undt_options']['undt_faq'] = array( 'items' => array() );
UNDT_Transfer::undo();
undt_ok( ! array_key_exists( 'undt_faq', $GLOBALS['undt_options'] ), 'Was es vorher nicht gab, gibt es danach wieder nicht' );

undt_ok( is_wp_error( UNDT_Transfer::preview( array( 'format' => 'fremd' ) ) ), 'Eine fremde Datei wird abgelehnt' );

$undt_page = undt_html(
	static function () {
		require UNDT_TEST_BASE . 'admin/views/transfer.php';
	}
);
undt_ok( false !== strpos( $undt_page, 'data-undt-import data-nonce="nonce-undt_import"' ) && false !== strpos( $undt_page, 'data-undt-import-file' ), 'Die Schritte bekommen Nonce und Datei' );
undt_ok( 3 === substr_count( $undt_page, 'data-undt-istep=' ), 'Drei Schritte' );
undt_ok( false !== strpos( $undt_page, 'name="_wpnonce" value="nonce-undt_import"' ) && false !== strpos( $undt_page, 'value="undt_import"' ), 'Ohne Skript geht es wie bisher über admin-post' );
undt_ok( false !== strpos( $undt_page, 'data-undt-import-done hidden' ), 'Ohne Sicherung kein „Rückgängig“' );
undt_ok( false !== strpos( $undt_page, 'wp undt export' ) && false !== strpos( $undt_page, 'wp undt import' ), 'Die Kommandozeile zugeklappt' );

$undt_uninstall = (string) file_get_contents( $undt_base . 'uninstall.php' );
undt_ok( false !== strpos( $undt_uninstall, "'undt_backup'," ), 'Beim Löschen geht auch die Sicherung' );

/* ------------------------------------------------ 11. Skript und Gestaltung ---- */

undt_head( 'Skript und Gestaltung' );

$undt_js  = (string) file_get_contents( $undt_base . 'admin/assets/admin.js' );
$undt_css = (string) file_get_contents( $undt_base . 'admin/assets/admin.css' );
$undt_mv  = (string) file_get_contents( $undt_base . 'admin/class-undt-module-view.php' ) . (string) file_get_contents( $undt_base . 'admin/views/transfer.php' );

$undt_missing = array();
foreach ( array( 'data-undt-day-open', 'data-undt-add-slot', 'data-undt-remove-slot', 'data-undt-hours-weekdays', 'data-undt-holidays', 'data-undt-special-kind', 'data-undt-social-new', 'data-undt-social-add', 'data-undt-banner-preview', 'data-undt-banner-back', 'data-undt-count', 'data-undt-pgroups', 'data-undt-pgroup-name', 'data-undt-pitem-add', 'data-undt-faq-toggle', 'data-undt-faq-question', 'data-undt-geo', 'data-undt-import', 'data-undt-import-run', 'data-undt-import-undo' ) as $undt_attr ) {
	if ( false === strpos( $undt_mv, $undt_attr ) || false === strpos( $undt_js, '[' . $undt_attr ) ) {
		$undt_missing[] = $undt_attr;
	}
}
undt_ok( empty( $undt_missing ), 'Ansicht und Skript verwenden dieselben Attribute' . ( $undt_missing ? ': ' . implode( ', ', $undt_missing ) : '' ) );
undt_ok( false !== strpos( $undt_js, 'function applyParts( data )' ) && 2 === substr_count( $undt_js, 'applyParts( result.data );' ), 'Nach jedem Speichern ziehen die Teile nach' );
undt_ok( false !== strpos( $undt_js, 'function detectPlatform( url, hosts )' ) && false !== strpos( $undt_js, 'function parseGeo( value )' ), 'Erkennung und Koordinaten wie auf dem Server' );
undt_ok( false === strpos( $undt_css, '.undt-block ' ), 'Keine Backend-Regel auf die Frontend-Klasse .undt-block' );
undt_ok( '<p>Hinweis</p>' === UNDT_Icons::kses( "<p>Hinweis</p><script>(function(){var k='x';}());</script>" ), 'Vorschauen: ein Skript fällt samt Inhalt weg, statt als Text stehen zu bleiben' );
undt_ok( false !== strpos( (string) file_get_contents( $undt_base . 'admin/class-undt-embed.php' ), 'return UNDT_Icons::kses( $html );' ), 'Auch auf Einbinden, dort fehlten sonst die Social-Symbole' );
undt_ok( false !== strpos( $undt_css, '.undt-drop__input {' ) && false !== strpos( $undt_css, '.undt-drop:focus-within' ), 'Die Ablage zeigt den Tastaturfokus' );
undt_ok( ! preg_match( '/#(?:d63638|00a32a|dc3232|46b450)/i', $undt_css ), 'Kein Rot und kein Grün im Backend' );

/* ------------------------------------- 12. Texte und Barrierefreiheit ---- */

undt_head( 'Texte und Barrierefreiheit' );

// Alle Texte der Oberflaeche, aus admin/ und includes/.
$undt_strings = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $undt_base . 'admin', FilesystemIterator::SKIP_DOTS ) ) as $undt_file ) {
	if ( '.php' === substr( $undt_file->getFilename(), -4 ) ) {
		preg_match_all( "/(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e|_n)\( '((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents( $undt_file->getPathname() ), $undt_m );
		$undt_strings = array_merge( $undt_strings, $undt_m[1] );
	}
}
foreach ( array( 'includes/class-undt-transfer.php', 'includes/class-undt-modules.php', 'includes/class-undt-shortcodes.php' ) as $undt_rel ) {
	preg_match_all( "/(?:__|_n)\( '((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents( $undt_base . $undt_rel ), $undt_m );
	$undt_strings = array_merge( $undt_strings, $undt_m[1] );
}

$undt_formal = array_filter( $undt_strings, static function ( $s ) { return (bool) preg_match( '/\b(Ihre[mnrs]?|Ihnen|Klicken Sie|Bitte\b|bitte\b)/', $s ); } );
undt_ok( count( $undt_strings ) > 500 && empty( $undt_formal ), 'Die Oberfläche duzt und bittet nicht' . ( $undt_formal ? ': ' . implode( ' | ', array_slice( $undt_formal, 0, 3 ) ) : '' ) );
$undt_feld = array_filter( $undt_strings, static function ( $s ) { return (bool) preg_match( '/neben (jedem|dem) Feld|die Felder|Felder erscheinen/', $s ); } );
undt_ok( empty( $undt_feld ), '„Angabe“ statt „Feld“' . ( $undt_feld ? ': ' . implode( ' | ', $undt_feld ) : '' ) );

undt_ok( false !== strpos( $undt_mv, 'class="undt-jsonld" data-undt-part="jsonld" tabindex="0"' ), 'Der Code auf Suchmaschinen lässt sich mit der Tastatur scrollen' );
undt_ok( (bool) preg_match( '/\.undt-drop:focus-within \{\n\toutline: 2px solid/', $undt_css ), 'Die Ablage zeigt einen Fokusring' );
undt_ok( false !== strpos( $undt_js, "again = document.querySelector( '[data-undt-banner-preview] .undt-banner__close' )" ), 'Nach „Wieder einblenden“ kehrt der Fokus zurück' );
undt_ok( (bool) preg_match( '/\.undt-value__value--empty \{\n\tcolor: var\(--undt-muted\)/', $undt_css ), 'Leere Angaben in dunklerem Grau, das Grau #8E8E98 bleibt Symbolen vorbehalten' );
undt_ok( false === strpos( $undt_css, '.undt-dot {' ) && false === strpos( $undt_css, '.undt-section-title' ), 'Keine Regeln mehr für Klassen, die keine Seite verwendet' );
undt_ok( ! method_exists( 'UNDT_Shortcodes', 'catalog' ) && ! method_exists( 'UNDT_Copy', 'table' ) && ! method_exists( 'UNDT_Fields', 'question_card' ), 'Die Reste der alten Seiten sind fort' );
undt_ok( 'Seite: Impressum' === UNDT_Transfer::dropped_label( 'page_imprint' ) && 'Suchmaschinen: Typ' === UNDT_Transfer::dropped_label( 'seo.schema_type' ), 'Der Importbericht nennt Bezeichnungen statt Schlüssel' );

/* ------------------------------- Neuer Wert in derselben Anfrage (0.6.1) --- */

undt_head( 'Eigenes update_option: der neue Wert gilt sofort' );

UNDT_Store::register();
update_option( 'undt_banner', array( 'text' => 'Alt' ) );
undt_ok( 'Alt' === undt_field( 'banner', 'text' ), 'Ausgangswert gelesen und zwischengespeichert' );
update_option( 'undt_banner', array( 'text' => 'Neu' ) );
undt_ok( 'Neu' === undt_field( 'banner', 'text' ), 'Nach update_option ohne eigenes flush() der neue Wert' );
update_option( 'undt_company', array_merge( (array) get_option( 'undt_company', array() ), array( 'company_name' => 'Sofort GmbH' ) ) );
undt_ok( 'Sofort GmbH' === undt_get( 'company_name' ), 'Auch bei den Stammdaten' );
delete_option( 'undt_banner' );
undt_ok( '' === undt_field( 'banner', 'text' ), 'Und nach delete_option der leere Stand' );

/* ---------------------------------------------------------- Ergebnis ---- */

echo "\n";

if ( ! empty( $GLOBALS['undt_fails'] ) ) {
	echo "\033[31m" . (int) $GLOBALS['undt_fails'] . " Pruefungen fehlgeschlagen\033[0m\n";
	exit( 1 );
}

echo "\033[32mAlle Pruefungen bestanden\033[0m\n";
