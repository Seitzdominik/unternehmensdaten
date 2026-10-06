<?php
/**
 * Pruefungen fuer 0.6.0, Etappe 2: die Uebersicht.
 *
 * „Zu erledigen“ mit Buendelung und Rechten, die Statuszeile, wo die Bausteine
 * stehen samt Zwischenspeicher, die Regel „falsche Impressum-Seite“, der
 * Erststart, die Kacheln und die neuen Ziele am Speicher-Endpunkt. Wie die
 * fertige Seite in WordPress aussieht, zeigt dev/verify.php.
 */
require __DIR__ . '/harness.php';
require UNDT_TEST_BASE . 'admin/class-undt-copy.php';

function undt_as( $role ) {
	$caps = array(
		'administrator' => array( 'manage_options' => true, 'edit_posts' => true, 'edit_pages' => true ),
		'editor'        => array( 'edit_posts' => true, 'edit_pages' => true ),
		'author'        => array( 'edit_posts' => true ),
	);
	$GLOBALS['undt_user'] = array( 'roles' => array( $role ), 'caps' => $caps[ $role ] );
}

function undt_find( array $items, $needle ) {
	foreach ( $items as $item ) {
		if ( false !== strpos( $item['title'], $needle ) ) {
			return $item;
		}
	}
	return null;
}

function undt_reset_usage( array $posts = array(), array $meta = array() ) {
	$GLOBALS['undt_db_posts'] = $posts;
	$GLOBALS['undt_db_meta']  = $meta;
	UNDT_Usage::flush();
}

function undt_modules( array $map ) {
	undt_set_modules( $map );
	$GLOBALS['undt_options']['undt_settings']['roles'] = array( 'editor' );
	UNDT_Modules::flush();
}

$undt_base = UNDT_TEST_BASE;

UNDT_Access::register();
UNDT_Admin::instance()->settings();
undt_modules( array( 'hours' => 1, 'prices' => 0, 'social' => 1, 'faq' => 0, 'banner' => 1, 'seo' => 1 ) );

undt_seed(
	array( 'legal_form' => 'gmbh', 'is_regulated' => 1, 'has_editorial' => 1, 'vat_status' => 'none' ),
	array(
		'company_name'    => 'Muster GmbH',
		'street'          => 'Weg 1',
		'postal_code'     => '10115',
		'city'            => 'Berlin',
		'email'           => 'info@muster.test',
		'phone'           => '030 1',
		'register_court'  => 'Amtsgericht Berlin',
		'register_number' => 'HRB 1',
		'representatives' => 'Erika Muster',
		'page_imprint'    => 114,
	)
);
$GLOBALS['undt_options']['undt_setup_done'] = 1;
$GLOBALS['undt_posts'][114]                 = array( 'status' => 'draft', 'title' => 'Danke' );
undt_reset_usage();

/* ------------------------------------------------- 1. Zu erledigen ---- */

undt_head( 'Zu erledigen: buendeln, ordnen, springen' );

undt_as( 'administrator' );
$items = UNDT_Todo::items();
$beruf = undt_find( $items, 'Angaben zu deinem Beruf fehlen' );

undt_ok( null !== $beruf && '4 Angaben zu deinem Beruf fehlen' === $beruf['title'], 'Vier fehlende Berufsangaben werden ein Eintrag, wie im Entwurf' );
undt_ok( null !== $beruf && 'Gesetzliche Berufsbezeichnung, Zuständige Kammer, Berufsrechtliche Regelungen und Wo sind die Regelungen zugänglich?' === $beruf['desc'], 'Die Beschreibung zaehlt die Felder auf, ohne doppeltes Satzzeichen' );
undt_ok( null !== $beruf && 'Ergänzen' === $beruf['action'] && 'open' === $beruf['tone'], 'Aktion „Ergänzen“, in Bernstein' );
undt_ok( null !== $beruf && 'https://example.test/wp-admin/admin.php?page=undt-company#undt-job_title' === $beruf['url'], 'Sprung ins erste Feld des Abschnitts' );

$redaktion = undt_find( $items, 'Verantwortliche Person für Inhalte fehlt' );
undt_ok( null !== $redaktion && 'Eintragen' === $redaktion['action'] && false !== strpos( $redaktion['url'], '#undt-editorial_name' ), 'Ein einzelnes Feld: eigener Titel, „Eintragen“' );
undt_ok( null !== $redaktion && false !== strpos( $redaktion['law'], 'MStV' ), 'Mit Rechtsgrundlage daneben' );

$behoerde = undt_find( $items, 'Datenschutz-Aufsichtsbehörde' );
undt_ok( null !== $behoerde && 'missing' === $behoerde['tone'] && false !== strpos( $behoerde['url'], '#undt-privacy_authority' ), 'Ein Hinweis bleibt grau und springt ins Feld' );
undt_ok( null !== $behoerde && false === strpos( $behoerde['desc'], 'koordiniert' ), 'Nur der erste Satz des Hinweises' );
undt_ok( null !== $behoerde && 'Eintragen' === $behoerde['action'], 'Fehlt die Angabe ganz, heisst die Aktion „Eintragen“' );
$kapital = UNDT_Todo::items( array( array( 'level' => 'notice', 'title' => 'Ausstehende Einlagen prüfen', 'detail' => 'X.', 'basis' => '', 'tab' => 'register', 'field' => 'company_name' ) ) );
undt_ok( 1 === count( $kapital ) && 'Prüfen' === $kapital[0]['action'], 'Steht schon etwas darin, heisst sie „Prüfen“' );

$seite = undt_find( $items, 'ist nicht veröffentlicht' );
undt_ok( null !== $seite && 'Seite wählen' === $seite['action'] && false !== strpos( $seite['url'], '#undt-page_imprint' ), 'Rechtsseiten: „Seite wählen“' );

$tones      = array_column( $items, 'tone' );
$first_grey = array_search( 'missing', $tones, true );
undt_ok( false !== $first_grey && ! in_array( 'open', array_slice( $tones, $first_grey ), true ), 'Erst Pflicht und Dringendes, dann Hinweise' );
undt_ok( false !== strpos( $items[0]['title'], 'fehl' ), 'Fehlende Pflichtangaben stehen oben' );

undt_as( 'editor' );
$items_editor = UNDT_Todo::items();
undt_ok( null !== undt_find( $items_editor, 'Angaben zu deinem Beruf' ), 'Ein Redakteur sieht die Angaben, die er eintragen darf' );
undt_ok( null === undt_find( $items_editor, 'ist nicht veröffentlicht' ), 'Aber nicht die Rechtsseiten, die bleiben Admins' );

undt_as( 'author' );
undt_ok( array() === UNDT_Todo::items(), 'Ohne Recht zur Pflege: keine Punkte' );

undt_as( 'administrator' );
$issues = array(
	array( 'level' => 'warning', 'title' => 'Veraltet', 'detail' => 'Erster Satz. Zweiter Satz.', 'basis' => '', 'tab' => '', 'edit' => 'https://example.test/edit?p=7' ),
);
$one = UNDT_Todo::items( $issues );
undt_ok( 1 === count( $one ) && 'Seite bearbeiten' === $one[0]['action'] && 'https://example.test/edit?p=7' === $one[0]['url'] && 'Erster Satz.' === $one[0]['desc'], 'Funde in Rechtsseiten: „Seite bearbeiten“' );
undt_as( 'author' );
undt_ok( array() === UNDT_Todo::items( $issues ), 'Wer keine Seiten bearbeiten darf, sieht ihn nicht' );
undt_as( 'administrator' );

/* ------------------------------------------------- 2. Statuszeile ---- */

undt_head( 'Statuszeile' );

$status  = UNDT_Overview::status();
$missing = UNDT_Audit::quick_count();
undt_ok( ! $status['done'] && 'Dein Impressum ist noch nicht vollständig' === $status['title'], 'Offen: „noch nicht vollständig“' );
undt_ok( 0 === strpos( $status['text'], $missing . ' Pflichtangaben fehlen.' ), 'Mit der Zahl: ' . $status['text'] );

$GLOBALS['undt_options']['undt_profile'] = UNDT_Sanitizer::profile( array( 'legal_form' => 'gmbh', 'vat_status' => 'none' ) );
UNDT_Store::flush();
$GLOBALS['undt_posts'][114] = array( 'status' => 'publish', 'title' => 'Impressum' );
$status                     = UNDT_Overview::status();
undt_ok( $status['done'] && 'Dein Impressum ist vollständig' === $status['title'] && false !== strpos( $status['text'], 'Alle Pflichtangaben für „' ), 'Fertig: „vollständig“, mit der Rechtsform' );
undt_ok( is_array( $status['imprint'] ) && 'https://example.test/?p=114' === $status['imprint']['url'] && 'Impressum' === $status['imprint']['title'] && '' !== $status['imprint']['modified'], 'Mit Link, Seitenname und Datum' );

/* ------------------------------------------- 3. Wo die Bausteine stehen ---- */

undt_head( 'Wo verwendet' );

undt_ok( UNDT_Usage::matches( 'x [undt_hours] y', 'hours' ) && UNDT_Usage::matches( '[undt_hours special="1"]', 'hours' ), 'Öffnungszeiten werden erkannt, auch mit Attributen' );
undt_ok( ! UNDT_Usage::matches( '[undt_hours_today]', 'hours' ), 'Aber nicht [undt_hours_today]' );
undt_ok( UNDT_Usage::matches( '[undt_privacy_block name="controller"]', 'privacy' ) && UNDT_Usage::matches( '[undt_privacy_block name=\"controller\"]', 'privacy' ), 'Datenschutz, auch mit maskierten Anfuehrungszeichen wie bei Bricks' );
undt_ok( ! UNDT_Usage::matches( '[undt_privacy_block name="dpo"]', 'privacy' ), 'Ein anderer Datenschutz-Block zaehlt nicht' );
undt_ok( UNDT_Usage::matches( '<!-- wp:shortcode -->[undt_impressum]<!-- /wp:shortcode -->', 'imprint' ) && UNDT_Usage::matches( '[undt_impressum heading_level="3"]', 'imprint' ), 'Impressum im Shortcode-Block und mit Attribut' );

undt_reset_usage();
undt_ok( 'Noch auf keiner Seite' === UNDT_Usage::label( 'imprint' ), 'Nirgends: „Noch auf keiner Seite“' );

undt_reset_usage(
	array(
		array( 'ID' => 114, 'post_type' => 'page', 'post_title' => 'Impressum', 'post_name' => 'impressum', 'post_content' => '[undt_impressum]' ),
		array( 'ID' => 20, 'post_type' => 'page', 'post_title' => 'Kontakt', 'post_name' => 'kontakt', 'post_content' => '[undt_hours] [undt_privacy_block name="controller"]' ),
		array( 'ID' => 21, 'post_type' => 'page', 'post_title' => 'Praxis', 'post_name' => 'praxis', 'post_content' => '[undt_hours]' ),
		array( 'ID' => 30, 'post_type' => 'wp_template_part', 'post_title' => '', 'post_name' => 'footer', 'post_content' => '<!-- wp:shortcode -->[undt_footer]<!-- /wp:shortcode -->' ),
	),
	array(
		array( 'ID' => 21, 'meta_key' => '_bricks_page_content_2', 'meta_value' => 'a:1:{s:4:"text";s:12:"[undt_hours]";}', 'post_type' => 'page', 'post_title' => 'Praxis', 'post_name' => 'praxis' ),
		array( 'ID' => 40, 'meta_key' => '_bricks_page_footer_2', 'meta_value' => '[undt_hours]', 'post_type' => 'bricks_template', 'post_title' => 'Fuss', 'post_name' => 'fuss' ),
	)
);
$q0 = $GLOBALS['undt_db_queries'];
undt_ok( 'Auf „Impressum“' === UNDT_Usage::label( 'imprint' ), 'Eine Seite: mit Namen' );
undt_ok( 'Im Footer' === UNDT_Usage::label( 'footer' ), 'Template-Part „footer“: „Im Footer“' );
undt_ok( 'Im Footer und auf 2 Seiten' === UNDT_Usage::label( 'hours' ), 'Footer von Bricks und zwei Seiten, eine Seite mit Inhalt und Meta-Feld zaehlt einmal' );
undt_ok( 'Auf „Kontakt“' === UNDT_Usage::label( 'privacy' ), 'Datenschutz auf „Kontakt“' );
undt_ok( 2 === $GLOBALS['undt_db_queries'] - $q0, 'Genau zwei Abfragen fuer alle Bausteine' );
undt_ok( isset( $GLOBALS['undt_transients']['undt_usage'] ), 'Das Ergebnis liegt als Transient' );
UNDT_Usage::label( 'footer' );
undt_ok( 2 === $GLOBALS['undt_db_queries'] - $q0, 'Weitere Fragen suchen nicht erneut' );
undt_ok( UNDT_Usage::on_post( 114, 'imprint' ) && ! UNDT_Usage::on_post( 20, 'imprint' ), 'Steht auf der Seite, oder eben nicht' );

UNDT_Usage::flush_meta( 1, 5, '_edit_lock' );
undt_ok( isset( $GLOBALS['undt_transients']['undt_usage'] ), 'Fremde Meta-Felder lassen den Zwischenspeicher stehen' );
UNDT_Usage::flush_meta( 1, 5, '_bricks_page_content_2' );
undt_ok( ! isset( $GLOBALS['undt_transients']['undt_usage'] ), 'Seitendaten von Bricks verwerfen ihn' );
UNDT_Usage::label( 'imprint' );
UNDT_Usage::flush();
undt_ok( ! isset( $GLOBALS['undt_transients']['undt_usage'] ), 'Speichern eines Inhalts verwirft ihn' );

UNDT_Usage::register();
undt_ok( ! empty( $GLOBALS['undt_filters']['save_post'] ) && ! empty( $GLOBALS['undt_filters']['deleted_post'] ) && ! empty( $GLOBALS['undt_filters']['updated_post_meta'] ), 'Eingehaengt an save_post, deleted_post und die Meta-Felder' );

undt_modules( array( 'hours' => 0, 'social' => 1, 'banner' => 1, 'seo' => 1 ) );
undt_ok( ! isset( UNDT_Usage::blocks()['hours'] ), 'Ohne Bereich Öffnungszeiten kein Baustein dafuer' );
undt_modules( array( 'hours' => 1, 'social' => 1, 'banner' => 1, 'seo' => 1 ) );

/* --------------------------------------- 4. Falsche Impressum-Seite ---- */

undt_head( 'Impressum auf der falschen Seite' );

$GLOBALS['undt_posts'][114] = array( 'status' => 'publish', 'title' => 'Danke' );
undt_reset_usage();
$wrong = null;
foreach ( UNDT_Audit::run() as $issue ) {
	if ( isset( $issue['field'] ) && 'page_imprint' === $issue['field'] && 'warning' === $issue['level'] ) {
		$wrong = $issue;
	}
}
undt_ok( null !== $wrong && 'Impressum ist mit der Seite „Danke“ verknüpft' === $wrong['title'], 'Ohne Baustein auf der Seite: Warnung mit dem Seitennamen' );
undt_ok( null !== $wrong && 'Auf dieser Seite steht kein Impressum. Wähle die richtige Seite aus.' === $wrong['detail'], 'Und dem Satz aus dem Entwurf' );
$todo = undt_find( UNDT_Todo::items(), 'verknüpft' );
undt_ok( null !== $todo && 'Seite wählen' === $todo['action'] && 'open' === $todo['tone'], 'In der Liste: „Seite wählen“, in Bernstein' );

undt_reset_usage( array( array( 'ID' => 114, 'post_type' => 'page', 'post_title' => 'Danke', 'post_name' => 'danke', 'post_content' => '[undt_impressum]' ) ) );
$still = false;
foreach ( UNDT_Audit::run() as $issue ) {
	$still = $still || ( false !== strpos( $issue['title'], 'verknüpft' ) );
}
undt_ok( ! $still, 'Steht der Baustein dort, entfaellt die Warnung' );

/* ---------------------------------------------------- 5. Erststart ---- */

undt_head( 'Erststart' );

$GLOBALS['undt_options']['undt_setup_done'] = 0;
unset( $GLOBALS['undt_options']['undt_onboarding'] );
undt_reset_usage();
$steps = UNDT_Overview::steps();
undt_ok( 4 === count( $steps ) && 'current' === $steps[0]['state'] && 'open' === $steps[3]['state'], 'Vor der Einrichtung: Schritt 1 ist dran' );
undt_ok( false !== strpos( $steps[0]['action'], 'page=undt-company#rechtsform' ) && false !== strpos( $steps[0]['action'], 'class="undt-btn"' ), 'Mit Knopf zur Rechtsform' );
undt_ok( UNDT_Overview::show_onboarding( $steps ) && 0 === UNDT_Overview::steps_done( $steps ), 'Die Uebersicht zeigt den Erststart, 0 von 4' );

// Das erste Speichern der Rechtsform legt den Zustand an, siehe UNDT_Admin::sanitize_profile().
UNDT_Admin::sanitize_profile( array( 'legal_form' => 'gmbh', 'vat_status' => 'none' ) );
undt_ok( 1 === (int) $GLOBALS['undt_options']['undt_setup_done'] && array( 'skipped' => 0, 'done' => 0 ) === $GLOBALS['undt_options']['undt_onboarding'], 'Erstes Speichern der Rechtsform: eingerichtet, Erststart laeuft weiter' );
undt_seed( array( 'legal_form' => 'gmbh', 'vat_status' => 'none' ), array( 'company_name' => 'Muster GmbH', 'page_imprint' => 114 ) );
$steps = UNDT_Overview::steps();
undt_ok( UNDT_Overview::show_onboarding( $steps ), 'Und wird weiter gezeigt' );
undt_ok( 'done' === $steps[0]['state'] && 'done' === $steps[1]['state'] && 'current' === $steps[2]['state'], 'Nach dem Speichern der Rechtsform: Pflichtangaben sind dran' );
undt_ok( false !== strpos( $steps[0]['desc'], 'GmbH' ), 'Der erledigte Schritt nennt die Rechtsform' );
undt_ok( false !== strpos( $steps[2]['action'], '#undt-' ), 'Der Knopf springt ins erste fehlende Feld' );

undt_seed(
	array( 'legal_form' => 'gmbh', 'vat_status' => 'none' ),
	array( 'company_name' => 'Muster GmbH', 'street' => 'Weg 1', 'postal_code' => '10115', 'city' => 'Berlin', 'email' => 'a@b.test', 'phone' => '1', 'register_court' => 'AG', 'register_number' => 'HRB 1', 'representatives' => 'E. M.', 'page_imprint' => 114 )
);
$steps = UNDT_Overview::steps();
undt_ok( 0 === UNDT_Audit::quick_count() && 'done' === $steps[2]['state'] && 'current' === $steps[3]['state'], 'Alles eingetragen: Impressum einbinden ist dran' );
undt_ok( false !== strpos( $steps[3]['action'], 'page=undt-embed' ), 'Der Knopf fuehrt zu Einbinden' );

undt_reset_usage( array( array( 'ID' => 114, 'post_type' => 'page', 'post_title' => 'Impressum', 'post_name' => 'impressum', 'post_content' => '[undt_impressum]' ) ) );
$steps = UNDT_Overview::steps();
undt_ok( 4 === UNDT_Overview::steps_done( $steps ) && ! UNDT_Overview::show_onboarding( $steps ), '4 von 4: die normale Uebersicht' );

UNDT_Overview::maybe_complete();
undt_ok( 1 === (int) $GLOBALS['undt_options']['undt_onboarding']['done'], 'Beim Speichern wird das festgehalten' );
undt_reset_usage();
undt_ok( ! UNDT_Overview::show_onboarding(), 'Und der Erststart kommt nicht wieder, auch wenn der Baustein spaeter fehlt' );

$GLOBALS['undt_options']['undt_onboarding'] = array( 'skipped' => 0, 'done' => 0 );
undt_ok( UNDT_Overview::show_onboarding(), 'Ohne festgehaltenen Abschluss fehlt Schritt 4 wieder' );
UNDT_Overview::mark( 'skipped' );
undt_ok( ! UNDT_Overview::show_onboarding() && 1 === (int) $GLOBALS['undt_options']['undt_onboarding']['skipped'], '„Einrichtung überspringen“ blendet ihn aus' );

// Seiten, die schon vor 0.6.0 eingerichtet waren, kennen die Option nicht.
unset( $GLOBALS['undt_options']['undt_onboarding'] );
undt_ok( ! UNDT_Overview::show_onboarding() && 1 === UNDT_Overview::onboarding_state()['done'], 'Bestehende, eingerichtete Seite ohne Option: gilt als fertig' );
undt_ok( ! isset( $GLOBALS['undt_options']['undt_onboarding'] ), 'Ohne dafuer etwas zu schreiben' );
$GLOBALS['undt_options']['undt_setup_done'] = 0;
undt_ok( UNDT_Overview::show_onboarding(), 'Eine noch nicht eingerichtete Seite sieht ihn' );
$GLOBALS['undt_options']['undt_setup_done'] = 1;
UNDT_Overview::start_onboarding();
undt_ok( array( 'skipped' => 0, 'done' => 0 ) === $GLOBALS['undt_options']['undt_onboarding'], 'start_onboarding legt die Option an' );
$GLOBALS['undt_options']['undt_onboarding'] = array( 'skipped' => 1, 'done' => 0 );
UNDT_Overview::start_onboarding();
undt_ok( 1 === $GLOBALS['undt_options']['undt_onboarding']['skipped'], 'Und ueberschreibt keinen vorhandenen Zustand' );

$GLOBALS['undt_options']['undt_onboarding'] = array( 'skipped' => 0, 'done' => 0 );
undt_as( 'editor' );
undt_ok( ! UNDT_Overview::show_onboarding(), 'Redakteure sehen nie den Erststart' );
undt_as( 'administrator' );

/* ------------------------------------------------------ 6. Kacheln ---- */

undt_head( 'Kacheln je Bereich' );

$undt_two = array( array( 'from' => '08:00', 'to' => '12:00' ), array( 'from' => '14:00', 'to' => '18:00' ) );
$undt_one = array( array( 'from' => '08:00', 'to' => '12:00' ) );
undt_seed_module(
	'hours',
	array(
		'days' => array(
			'mon' => array( 'closed' => 0, 'slots' => $undt_two ),
			'tue' => array( 'closed' => 0, 'slots' => $undt_two ),
			'wed' => array( 'closed' => 0, 'slots' => $undt_one ),
			'thu' => array( 'closed' => 0, 'slots' => $undt_two ),
			'fri' => array( 'closed' => 0, 'slots' => $undt_one ),
			'sat' => array( 'closed' => 1, 'slots' => array() ),
			'sun' => array( 'closed' => 1, 'slots' => array() ),
		),
	)
);
undt_seed_module( 'social', array( 'items' => array( array( 'platform' => 'instagram', 'url' => 'https://instagram.com/x' ), array( 'platform' => 'linkedin', 'url' => 'https://linkedin.com/in/x' ) ) ) );
undt_seed_module( 'banner', array( 'enabled' => 0, 'text' => 'Hallo' ) );

$tiles = array();
foreach ( UNDT_Overview::tiles() as $tile ) {
	$tiles[ $tile['title'] ] = $tile;
}

undt_ok( array( 'Firma', 'Öffnungszeiten', 'Social Media', 'Infobanner', 'Suchmaschinen' ) === array_keys( $tiles ), 'Admin: Firma, Bereiche, Suchmaschinen' );
undt_ok( 'Alle Pflichtangaben eingetragen' === $tiles['Firma']['line'] && 0 === $tiles['Firma']['count'], 'Firma ohne Luecken' );
undt_ok( 'Montag bis Freitag geöffnet' === $tiles['Öffnungszeiten']['sub'], 'Öffnungszeiten: ' . $tiles['Öffnungszeiten']['sub'] );
undt_ok( 'Heute 08:00–12:00 und 14:00–18:00 Uhr' === $tiles['Öffnungszeiten']['line'], 'Und heute (Donnerstag): ' . $tiles['Öffnungszeiten']['line'] );
undt_ok( 'Instagram, LinkedIn' === $tiles['Social Media']['line'], 'Social: die Plattformen' );
undt_ok( 'Ausgeblendet' === $tiles['Infobanner']['line'] && $tiles['Infobanner']['muted'] && 'missing' === $tiles['Infobanner']['tone'], 'Banner aus: grau und „Ausgeblendet“' );
undt_ok( 0 === strpos( $tiles['Firma']['href'], 'https://example.test/wp-admin/admin.php?page=undt-company' ), 'Die ganze Kachel fuehrt zur Seite' );

undt_seed( array( 'legal_form' => 'gmbh', 'is_regulated' => 1, 'is_medical' => 1, 'vat_status' => 'none' ), array( 'company_name' => 'Praxis GmbH' ) );
$firma = UNDT_Overview::tiles()[0];
undt_ok( false !== strpos( $firma['sub'], ' · Heilberuf' ) && $firma['count'] > 0 && 'open' === $firma['tone'] && false !== strpos( $firma['line'], 'Pflichtangaben fehlen' ), 'Firma mit Heilberuf und offenen Angaben in Bernstein' );

undt_ok( array( 'prices' => 'Preise & Leistungen', 'faq' => 'FAQ' ) === UNDT_Overview::more_modules(), '„Weitere Bereiche“ nennt die abgeschalteten' );
undt_as( 'editor' );
undt_ok( array() === UNDT_Overview::more_modules(), 'Nur fuer Admins' );
undt_ok( array( 'Firma', 'Öffnungszeiten', 'Social Media', 'Infobanner' ) === array_column( UNDT_Overview::tiles(), 'title' ), 'Redakteure: ohne Suchmaschinen' );
undt_as( 'administrator' );

$embeds = UNDT_Overview::embeds();
undt_ok( array( 'imprint', 'footer', 'privacy', 'hours' ) === array_column( $embeds, 'key' ), 'Einbinden: die vier Bausteine aus dem Entwurf' );
undt_ok( '[undt_privacy_block name="controller"]' === $embeds[2]['code'] && $embeds[2]['unused'], 'Als Shortcode, und ob er schon irgendwo steht' );

/* --------------------------------------- 7. Ziele am Speicher-Endpunkt ---- */

undt_head( 'Bereich einschalten, Einrichtung ueberspringen' );

$answer = UNDT_Autosave::save( 'quick:module', array( 'value' => 'prices' ) );
undt_ok( is_array( $answer ) && true === $answer['reload'] && UNDT_Modules::is_active( 'prices' ), 'Ein Admin schaltet Preise ein, die Seite laedt neu' );
undt_ok( array( 'editor' ) === UNDT_Modules::settings()['roles'] && UNDT_Modules::is_active( 'hours' ), 'Die uebrigen Einstellungen bleiben' );
$answer = UNDT_Autosave::save( 'quick:module', array( 'value' => 'gibtsnicht' ) );
undt_ok( is_wp_error( $answer ) && 400 === $answer->get_error_data(), 'Ein unbekannter Bereich wird abgelehnt' );

undt_as( 'editor' );
$answer = UNDT_Autosave::save( 'quick:module', array( 'value' => 'faq' ) );
undt_ok( is_wp_error( $answer ) && 403 === $answer->get_error_data() && ! UNDT_Modules::is_active( 'faq' ), 'Redakteure schalten nichts ein' );
$answer = UNDT_Autosave::save( 'quick:onboarding', array( 'value' => 'skip' ) );
undt_ok( is_wp_error( $answer ) && 403 === $answer->get_error_data(), 'Und ueberspringen die Einrichtung nicht' );

undt_as( 'administrator' );
unset( $GLOBALS['undt_options']['undt_onboarding'] );
$answer = UNDT_Autosave::save( 'quick:onboarding', array( 'value' => 'weg' ) );
undt_ok( is_wp_error( $answer ) && 400 === $answer->get_error_data(), 'Nur „skip“ wird angenommen' );
$answer = UNDT_Autosave::save( 'quick:onboarding', array( 'value' => 'skip' ) );
undt_ok( is_array( $answer ) && true === $answer['reload'] && 1 === (int) $GLOBALS['undt_options']['undt_onboarding']['skipped'], 'Ein Admin ueberspringt sie' );

/* ------------------------------------------------ 8. Dateien im Ganzen ---- */

undt_head( 'Ansicht, Skript, Deinstallation' );

undt_ok( is_file( $undt_base . 'admin/views/overview.php' ) && ! is_file( $undt_base . 'admin/views/audit.php' ), 'Die Uebersicht ersetzt die Pruefseite' );
undt_ok( 'overview' === UNDT_Nav::pages()['overview']['view'], 'Und steht als Ansicht im Register' );

$view = (string) file_get_contents( $undt_base . 'admin/views/overview.php' );
undt_ok( false !== strpos( $view, "UNDT_Ui::page_head( __( 'Übersicht', 'unternehmensdaten' ), false, false )" ), 'Der Titel steht nur fuer Screenreader da' );
undt_ok( false !== strpos( $view, 'data-undt-action="onboarding" data-undt-value="skip"' ) && false !== strpos( $view, 'data-undt-action="module"' ), 'Knoepfe fuer Ueberspringen und Einschalten' );
undt_ok( false !== strpos( $view, 'UNDT_Todo::VISIBLE' ) && false !== strpos( $view, 'data-undt-todo-more' ), 'Ab mehr als vier Punkten „Alle anzeigen“' );
undt_ok( false !== strpos( $view, 'Die Prüfung ist eine Checkliste, keine Rechtsberatung.' ), 'Der Hinweis auf die fehlende Rechtsberatung bleibt' );
undt_ok( false !== strpos( $view, '<?php if ( $undt_is_admin ) : ?>' ), 'Einbinden nur fuer Admins' );

$js = (string) file_get_contents( $undt_base . 'admin/assets/admin.js' );
undt_ok( false !== strpos( $js, 'function initJump()' ) && false !== strpos( $js, 'field.focus( { preventScroll: true } )' ) && false !== strpos( $js, 'tab.click()' ), 'Sprung ins Feld: Registerkarte oeffnen, Fokus setzen' );
undt_ok( false !== strpos( $js, 'function initActions()' ) && false !== strpos( $js, 'window.location.reload()' ), 'Aktionsknoepfe laden danach neu' );

$css = (string) file_get_contents( $undt_base . 'admin/assets/admin.css' );
undt_ok( false === strpos( $css, 'undt-audit' ) && false === stripos( $css, '#72aee6' ), 'Die Regeln der alten Pruefseite sind fort, kein Blau fuer Hinweise' );

$uninstall = (string) file_get_contents( $undt_base . 'uninstall.php' );
undt_ok( false !== strpos( $uninstall, "'undt_onboarding'," ) && false !== strpos( $uninstall, "delete_transient( 'undt_usage' );" ), 'Beim Loeschen gehen Erststart und Zwischenspeicher mit' );

$main = (string) file_get_contents( $undt_base . 'unternehmensdaten.php' );
undt_ok( false !== strpos( $main, "\tUNDT_Usage::register();\n\n\tif ( is_admin() ) {" ), 'Der Zwischenspeicher wird auch beim Speichern ueber die REST-API verworfen' );

/* ---------------------------------------------------------- Ergebnis ---- */

echo "\n";

if ( ! empty( $GLOBALS['undt_fails'] ) ) {
	echo "\033[31m" . (int) $GLOBALS['undt_fails'] . " Pruefungen fehlgeschlagen\033[0m\n";
	exit( 1 );
}

echo "\033[32mAlle Pruefungen bestanden\033[0m\n";
