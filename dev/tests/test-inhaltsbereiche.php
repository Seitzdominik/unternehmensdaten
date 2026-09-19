<?php
require __DIR__ . '/harness.php';

// Grunddaten für die Schema-Tests.
undt_seed(
	array( 'legal_form' => 'gmbh', 'vat_status' => 'standard' ),
	array(
		'company_name' => 'Beispiel GmbH',
		'street'       => 'Musterstraße 1',
		'postal_code'  => '12345',
		'city'         => 'Musterstadt',
		'email'        => 'info@example.test',
		'phone'        => '+49 30 1234567',
		'vat_id'       => 'DE123456789',
	)
);

/* ------------------------------------------------- 1. Öffnungszeiten ------ */

undt_head( 'Öffnungszeiten: Struktur und Gruppierung' );

$week = array();
foreach ( array( 'mon', 'tue', 'wed', 'thu', 'fri' ) as $d ) {
	$week[ $d ] = array( 'closed' => 0, 'slots' => array( array( 'from' => '09:00', 'to' => '12:30' ), array( 'from' => '14:00', 'to' => '18:00' ) ) );
}
$week['sat'] = array( 'closed' => 0, 'slots' => array( array( 'from' => '10:00', 'to' => '14:00' ), array( 'from' => '', 'to' => '' ) ) );
$week['sun'] = array( 'closed' => 1, 'slots' => array() );

undt_seed_module(
	'hours',
	array(
		'days'    => $week,
		'note'    => 'Termine nach Vereinbarung.',
		'special' => array(
			array( 'date' => '2020-12-24', 'closed' => 1, 'note' => 'Vergangen' ),
			array( 'date' => '2026-12-31', 'closed' => 0, 'from' => '09:00', 'to' => '13:00', 'note' => 'Silvester' ),
			array( 'date' => '2026-12-24', 'closed' => 1, 'note' => 'Heiligabend' ),
		),
	)
);

$groups = UNDT_Hours::grouped();
undt_ok( 3 === count( $groups ), 'Sieben Tage werden zu drei Gruppen zusammengefasst' );
undt_ok( 5 === count( $groups[0]['days'] ), 'Mo bis Fr bilden eine Gruppe' );
undt_ok( true === $groups[2]['closed'], 'Sonntag ist als geschlossen erkannt' );
undt_ok( 2 === count( $groups[0]['slots'] ), 'Beide Zeitfenster bleiben erhalten' );
undt_ok( 1 === count( UNDT_Hours::slots( 'sat' ) ), 'Halb leeres Zeitfenster wird verworfen' );

$special = UNDT_Hours::upcoming_special();
undt_ok( 2 === count( $special ), 'Vergangener Sondertermin wird ausgeblendet' );
undt_ok( '2026-12-24' === $special[0]['date'], 'Sondertermine sind aufsteigend sortiert' );

undt_ok( '' === UNDT_Hours::time( '25:00' ), 'Ungültige Uhrzeit wird verworfen' );
undt_ok( '09:30' === UNDT_Hours::time( '09:30' ), 'Gültige Uhrzeit bleibt' );
undt_ok( '' === UNDT_Hours::date( '2026-02-30' ), 'Nicht existierendes Datum wird verworfen' );

$html = UNDT_Blocks::hours( array( 'heading_level' => 3, 'short' => '1', 'group' => '1', 'special' => '1', 'note' => '1' ) );
undt_ok( false !== strpos( $html, '<th scope="row"><span class="undt-hours__label">Mo – Fr</span></th>' ), 'Tagesbereich wird zusammengefasst ausgegeben' );
undt_ok( false !== strpos( $html, '09:00 – 12:30 Uhr<br />14:00 – 18:00 Uhr' ), 'Mittagspause erscheint als zwei Zeilen' );
undt_ok( false !== strpos( $html, 'geschlossen' ), 'Geschlossen-Text erscheint' );
undt_ok( false !== strpos( $html, 'Silvester' ), 'Künftiger Sondertermin erscheint' );
undt_ok( false === strpos( $html, 'Vergangen' ), 'Vergangener Sondertermin erscheint NICHT' );
undt_ok( false !== strpos( $html, '<time datetime="2026-12-24">' ), 'Sondertermin trägt ein maschinenlesbares Datum' );

/* ---------------------------------------------------- 2. Zeitzone/Status -- */

undt_head( 'Geöffnet-Status' );

// 10.09.2026 ist ein Donnerstag.
$GLOBALS['undt_now'] = '2026-09-10 10:30:00';
undt_ok( true === UNDT_Hours::is_open_now(), 'Donnerstag 10:30 gilt als geöffnet' );

$GLOBALS['undt_now'] = '2026-09-10 13:00:00';
undt_ok( false === UNDT_Hours::is_open_now(), 'Mittagspause gilt als geschlossen' );

$GLOBALS['undt_now'] = '2026-09-13 11:00:00';
undt_ok( false === UNDT_Hours::is_open_now(), 'Sonntag gilt als geschlossen' );

$GLOBALS['undt_now'] = '2026-12-24 10:00:00';
undt_ok( false === UNDT_Hours::is_open_now(), 'Sonderschließung überschreibt die reguläre Zeit' );

$GLOBALS['undt_now'] = '2026-12-31 10:00:00';
undt_ok( true === UNDT_Hours::is_open_now(), 'Sonderöffnungszeit wird berücksichtigt' );

$today = UNDT_Blocks::hours_today( array( 'prefix' => 'Heute', 'closed_text' => '' ) );
undt_ok( false !== strpos( $today, 'Heute 09:00 – 13:00' ), 'Heutige Zeit stammt aus dem Sondertermin' );
undt_ok( false !== strpos( $today, 'Silvester' ), 'Anlass des Sondertermins wird genannt' );

$GLOBALS['undt_now'] = '2026-09-10 10:30:00';

/* ------------------------------------------------ 3. Wiederholungsfelder -- */

undt_head( 'Wiederholungsfelder' );

$clean = UNDT_Content::sanitize(
	'prices',
	array(
		'items' => array(
			2 => array( 'label' => 'C', 'price' => '3', 'boese' => 'weg' ),
			0 => array( 'label' => 'A', 'price' => '1' ),
			1 => array( 'label' => 'B', 'price' => '2' ),
		),
	)
);

undt_ok( 3 === count( $clean['items'] ), 'Alle Zeilen bleiben erhalten' );
undt_ok( 'C' === $clean['items'][0]['label'], 'Die Reihenfolge des Formulars bestimmt die Ausgabe, nicht der Index' );
undt_ok( ! array_key_exists( 'boese', $clean['items'][0] ), 'Unbekanntes Unterfeld wird verworfen' );
undt_ok( array_key_exists( 'group', $clean['items'][0] ), 'Fehlendes Unterfeld wird ergänzt' );
undt_ok( array_keys( $clean['items'] ) === array( 0, 1, 2 ), 'Zeilen werden lückenlos neu nummeriert' );

undt_seed_module( 'prices', array( 'items' => array( array( 'label' => '', 'price' => '', 'group' => '', 'note' => '' ) ) ) );
undt_ok( 0 === count( UNDT_Content::rows( 'prices', 'items' ) ), 'Vollständig leere Zeile wird nicht ausgegeben' );

/* ----------------------------------------------------------- 4. Preise ---- */

undt_head( 'Preise' );

undt_seed_module(
	'prices',
	array(
		'items' => array(
			array( 'group' => 'Beratung', 'label' => 'Erstgespräch', 'price' => 'kostenfrei', 'note' => '30 Minuten' ),
			array( 'group' => 'Beratung', 'label' => 'Folgetermin', 'price' => '90,00 €' ),
			array( 'group' => 'Workshops', 'label' => 'Tagesworkshop', 'price' => '750,00 €' ),
		),
		'intro' => 'Auszug aus dem Leistungsangebot.',
	)
);

$html = UNDT_Blocks::prices( array( 'heading_level' => 3, 'group' => '', 'intro' => '1', 'footnote' => '1' ) );
undt_ok( 2 === substr_count( $html, '<h3' ), 'Zwei Gruppen ergeben zwei Überschriften' );
undt_ok( false !== strpos( $html, '30 Minuten' ), 'Zusatz erscheint' );
undt_ok( false !== strpos( $html, 'Umsatzsteuer' ), 'Fußnote aus der Voreinstellung erscheint' );

$filtered = UNDT_Blocks::prices( array( 'heading_level' => 3, 'group' => 'Workshops', 'intro' => '0', 'footnote' => '0' ) );
undt_ok( false === strpos( $filtered, 'Erstgespräch' ), 'group-Attribut filtert andere Gruppen aus' );
undt_ok( false !== strpos( $filtered, 'Tagesworkshop' ), 'Gefilterte Gruppe bleibt' );
undt_ok( false === strpos( $filtered, 'Umsatzsteuer' ), 'footnote=0 blendet die Fußnote aus' );

/* ----------------------------------------------------------- 5. Social ---- */

undt_head( 'Social' );

undt_seed_module(
	'social',
	array(
		'items'  => array(
			array( 'platform' => 'instagram', 'url' => 'https://example.test/insta', 'label' => '' ),
			array( 'platform' => 'mastodon', 'url' => 'javascript:alert(1)', 'label' => 'Mastodon' ),
			array( 'platform' => 'linkedin', 'url' => 'https://example.test/li', 'label' => 'Unser Team' ),
		),
		'rel_me' => 1,
	)
);

$html = UNDT_Blocks::social( array( 'label' => '' ) );
undt_ok( 2 === substr_count( $html, '<li' ), 'Profil mit verworfener URL entfällt' );
undt_ok( false === strpos( $html, 'javascript' ), 'javascript:-URL kommt nicht durch' );
undt_ok( false !== strpos( $html, '>Instagram<' ), 'Leere Beschriftung fällt auf den Plattformnamen zurück' );
undt_ok( false !== strpos( $html, '>Unser Team<' ), 'Eigene Beschriftung wird verwendet' );
undt_ok( false !== strpos( $html, 'rel="me"' ), 'rel=me wird gesetzt' );
undt_ok( false !== strpos( $html, 'data-platform="instagram"' ), 'data-platform als CSS-Haken vorhanden' );
undt_ok( false !== strpos( $html, '<nav' ) && false !== strpos( $html, 'aria-label' ), 'Ausgabe ist eine benannte Navigation' );

/* -------------------------------------------------------------- 6. FAQ ---- */

undt_head( 'FAQ' );

undt_seed_module(
	'faq',
	array(
		'items' => array(
			array( 'group' => '', 'question' => 'Wie lange dauert das?', 'answer' => "Etwa zwei Wochen.\n\nBei Rückfragen länger." ),
			array( 'group' => '', 'question' => 'Was kostet das?', 'answer' => 'Kommt darauf an.' ),
		),
		'style' => 'details',
	)
);

$html = UNDT_Blocks::faq( array( 'heading_level' => 2, 'group' => '', 'style' => '' ) );
undt_ok( 2 === substr_count( $html, '<details' ), 'Zwei aufklappbare Einträge' );
undt_ok( false === strpos( $html, ' open>' ), 'Ohne open_first ist nichts aufgeklappt' );
undt_ok( 2 === substr_count( $html, '<p>' ) - 1, 'Leerzeile im Antworttext erzeugt zwei Absätze' );
undt_ok( '' === UNDT_SchemaOrg::faq_script( UNDT_Content::group_rows( UNDT_Content::rows( 'faq', 'items' ) ) ), 'FAQPage-Auszeichnung ist standardmäßig aus' );

undt_seed_module( 'faq', array( 'items' => array( array( 'question' => 'A', 'answer' => 'B', 'group' => '' ) ), 'schema' => 1 ) );
$json = UNDT_SchemaOrg::faq_script( UNDT_Content::group_rows( UNDT_Content::rows( 'faq', 'items' ) ) );
undt_ok( false !== strpos( $json, '"@type":"FAQPage"' ), 'Eingeschaltet erscheint die FAQPage-Auszeichnung' );

$dl = UNDT_Blocks::faq( array( 'heading_level' => 2, 'group' => '', 'style' => 'dl' ) );
undt_ok( false !== strpos( $dl, '<dl' ) && false === strpos( $dl, '<details' ), 'style=dl wechselt die Darstellung' );

/* ----------------------------------------------------------- 7. Banner ---- */

undt_head( 'Infobanner' );

undt_seed_module( 'banner', array( 'enabled' => 0, 'text' => 'Wir sind umgezogen.' ) );
undt_ok( '' === UNDT_Blocks::banner(), 'Abgeschaltetes Banner gibt nichts aus' );

undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => 'Wir sind umgezogen.', 'type' => 'warning', 'dismissible' => 1 ) );
$html = UNDT_Blocks::banner();
undt_ok( false !== strpos( $html, 'undt-banner--warning' ), 'Bannerart landet in der Klasse' );
undt_ok( false !== strpos( $html, 'role="region"' ), 'Banner ist als Bereich ausgezeichnet' );
undt_ok( false !== strpos( $html, '<script>' ), 'Schließen-Skript wird mitgegeben' );

preg_match( '/data-undt-banner="([a-f0-9]+)"/', $html, $m );
$first_key = $m[1];

undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => 'Neuer Text.', 'type' => 'warning', 'dismissible' => 1 ) );
preg_match( '/data-undt-banner="([a-f0-9]+)"/', UNDT_Blocks::banner(), $m2 );
undt_ok( $first_key !== $m2[1], 'Geänderter Text erzeugt eine neue Kennung, das Banner erscheint erneut' );

undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => 'Kurz.', 'dismissible' => 0 ) );
undt_ok( false === strpos( UNDT_Blocks::banner(), '<script>' ), 'Ohne Schließen-Funktion kein Skript' );

// Auto-Ausgabe und Shortcode zugleich dürfen kein zweites Banner erzeugen.
undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => 'Einmalig.', 'dismissible' => 1 ) );
undt_ok( '' !== UNDT_Blocks::banner(), 'Erste Ausgabe liefert das Banner' );
undt_ok( '' === UNDT_Blocks::banner(), 'Zweite Ausgabe im selben Aufruf bleibt leer' );

undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => 'Suchbar.', 'dismissible' => 1 ) );
$html = UNDT_Blocks::banner();
preg_match( '/data-undt-banner="([a-f0-9]+)"/', $html, $m3 );
undt_ok( false !== strpos( $html, "var k='" . $m3[1] . "'" ), 'Skript sucht das Banner über seine Kennung, nicht über die Nachbarschaft' );
undt_ok( false === strpos( $html, 'previousElementSibling' ), 'Keine Abhängigkeit von der DOM-Nachbarschaft' );

/* -------------------------------------------------------- 8. Schema.org --- */

undt_head( 'JSON-LD' );

undt_seed_module( 'seo', array( 'schema_type' => 'MedicalClinic', 'output_mode' => 'auto', 'with_hours' => 1, 'with_social' => 1, 'geo_lat' => '52,520008', 'geo_lng' => '13.404954', 'logo' => 7 ) );

$org = UNDT_SchemaOrg::organization();
undt_ok( 'MedicalClinic' === $org['@type'], 'Gewählter Typ wird übernommen' );
undt_ok( 'DE' === $org['address']['addressCountry'], 'Leeres Land wird zu DE' );
undt_ok( 'DE123456789' === $org['vatID'], 'USt-IdNr. aus den Stammdaten' );
undt_ok( isset( $org['geo']['latitude'] ) && 52.520008 === $org['geo']['latitude'], 'Komma im Breitengrad wird zu Punkt' );
undt_ok( isset( $org['logo']['width'] ) && 512 === $org['logo']['width'], 'Logo bringt seine Abmessungen mit' );
undt_ok( isset( $org['openingHoursSpecification'] ), 'Öffnungszeiten sind enthalten' );
undt_ok( 2 === count( $org['sameAs'] ), 'sameAs enthält die gültigen Profile' );

$spec = $org['openingHoursSpecification'];
$mo_fr = null;
foreach ( $spec as $s ) {
	if ( '09:00' === $s['opens'] && '12:30' === $s['closes'] ) { $mo_fr = $s; }
}
undt_ok( null !== $mo_fr && 5 === count( $mo_fr['dayOfWeek'] ), 'Gleiche Zeiten werden zu einem Eintrag mit fünf Tagen' );
undt_ok( in_array( 'Monday', $mo_fr['dayOfWeek'], true ), 'Tagesnamen entsprechen schema.org' );

$script = UNDT_SchemaOrg::script( array( 'name' => 'Ende </script><script>alert(1)</script>' ) );
undt_ok( false === strpos( $script, '</script><script>' ), 'Ausbruch aus dem Skript ist nicht möglich' );
undt_ok( false !== strpos( UNDT_SchemaOrg::script( array( 'n' => 'Größe' ) ), 'Größe' ), 'Umlaute bleiben lesbar' );

undt_seed_module( 'seo', array( 'output_mode' => 'never' ) );
undt_ok( false === UNDT_SchemaOrg::should_output(), 'never unterdrückt die Ausgabe' );

/* --------------------------------------------------- 9. Modulschaltung ---- */

undt_head( 'Modulschaltung' );

undt_set_modules( array( 'hours' => 0, 'prices' => 1, 'social' => 1, 'faq' => 1, 'banner' => 1, 'seo' => 1 ) );
undt_ok( false === UNDT_Modules::is_active( 'hours' ), 'Modul lässt sich abschalten' );
undt_ok( '' === UNDT_Shortcodes::hours( array() ), 'Abgeschaltetes Modul gibt nichts aus' );
undt_ok( false === strpos( UNDT_Blocks::css(), 'undt-hours__table' ), 'CSS des abgeschalteten Moduls entfällt' );
undt_ok( false !== strpos( UNDT_Blocks::css(), 'undt-prices__table' ), 'CSS des aktiven Moduls bleibt' );

$codes = wp_json_encode( array_column( UNDT_Shortcodes::module_catalog(), 'code' ) );
undt_ok( false === strpos( $codes, '[undt_hours]' ), 'Abgeschaltetes Modul fehlt in der Referenz' );

undt_set_modules( array( 'hours' => 1, 'prices' => 1, 'social' => 1, 'faq' => 1, 'banner' => 1, 'seo' => 1 ) );
undt_ok( true === UNDT_Modules::is_active( 'hours' ), 'Wieder eingeschaltet ist das Modul zurück' );
undt_ok( UNDT_Hours::has_data(), 'Die Daten haben das Abschalten überstanden' );

/* ------------------------------------------------ 10. Schnittstelle ------- */

undt_head( 'Query-Schnittstelle für Page Builder' );

$GLOBALS['undt_now'] = '2026-09-10 10:30:00';

undt_set_modules( array( 'hours' => 1, 'prices' => 1, 'social' => 1, 'faq' => 1, 'banner' => 1, 'seo' => 1 ) );

$week = array();
foreach ( array( 'mon', 'tue', 'wed', 'thu', 'fri' ) as $d ) {
	$week[ $d ] = array( 'closed' => 0, 'slots' => array( array( 'from' => '09:00', 'to' => '18:00' ) ) );
}
$week['sat'] = array( 'closed' => 0, 'slots' => array( array( 'from' => '10:00', 'to' => '14:00' ) ) );
$week['sun'] = array( 'closed' => 1, 'slots' => array() );

undt_seed_module( 'hours', array( 'days' => $week, 'suffix' => 'Uhr', 'closed_label' => 'geschlossen',
	'special' => array( array( 'date' => '2026-12-24', 'closed' => 1, 'note' => 'Heiligabend' ) ) ) );

undt_seed_module( 'faq', array( 'items' => array(
	array( 'group' => 'Allgemein', 'question' => 'Frage A', 'answer' => 'Antwort A' ),
	array( 'group' => 'Preise', 'question' => 'Frage B', 'answer' => 'Antwort B' ),
	array( 'group' => 'Allgemein', 'question' => 'Frage C', 'answer' => 'Antwort C' ),
) ) );

undt_seed_module( 'social', array( 'items' => array(
	array( 'platform' => 'linkedin', 'label' => '', 'url' => 'https://example.test/li' ),
	array( 'platform' => 'mastodon', 'label' => 'Bei uns', 'url' => 'https://example.test/m' ),
) ) );

undt_ok( array() === UNDT_Api::query( 'gibt_es_nicht' ), 'Unbekannte Quelle liefert ein leeres Array' );

$faq = UNDT_Api::query( 'undt_faq' );
undt_ok( 3 === count( $faq ), 'FAQ-Quelle liefert alle Zeilen' );
undt_ok( isset( $faq[0]['question'], $faq[0]['answer'], $faq[0]['group'] ), 'Zeilen tragen die dokumentierten Schlüssel' );

$gefiltert = UNDT_Api::query( 'undt_faq', array( 'group' => 'allgemein' ) );
undt_ok( 2 === count( $gefiltert ), 'group filtert, unabhängig von Groß- und Kleinschreibung' );
undt_ok( 1 === count( UNDT_Api::query( 'undt_faq', array( 'limit' => 1 ) ) ), 'limit begrenzt die Anzahl' );

$tage = UNDT_Api::query( 'undt_hours' );
undt_ok( 7 === count( $tage ), 'Ein Eintrag je Wochentag' );
undt_ok( false !== strpos( $tage[0]['times'], 'Uhr' ), 'Zeiten kommen als fertiger Text mit Zusatz' );
$sonntag = array_values( array_filter( $tage, static function ( $t ) { return 'sun' === $t['day']; } ) )[0];
undt_ok( true === $sonntag['closed'] && 'geschlossen' === $sonntag['times'], 'Geschlossener Tag ist als solcher gekennzeichnet' );

$gruppiert = UNDT_Api::query( 'undt_hours_grouped' );
undt_ok( 3 === count( $gruppiert ), 'Gruppierte Quelle fasst gleiche Tage zusammen' );
undt_ok( false !== strpos( $gruppiert[0]['days_label'], '–' ), 'Tagesbereich als Text, etwa Mo – Fr' );

$sonder = UNDT_Api::query( 'undt_hours_special' );
undt_ok( 1 === count( $sonder ) && 'Heiligabend' === $sonder[0]['note'], 'Sondertermine kommen mit Anlass' );
undt_ok( '' !== $sonder[0]['date_label'], 'Sondertermin bringt ein formatiertes Datum mit' );

$profile = UNDT_Api::query( 'undt_social' );
undt_ok( 'LinkedIn' === $profile[0]['label'], 'Leere Beschriftung fällt auf den Plattformnamen zurück' );
undt_ok( 'Bei uns' === $profile[1]['label'], 'Eigene Beschriftung bleibt erhalten' );

// Abgeschaltete Bereiche liefern nichts, genau wie ihre Shortcodes.
undt_set_modules( array( 'hours' => 0, 'prices' => 1, 'social' => 1, 'faq' => 0, 'banner' => 1, 'seo' => 1 ) );
undt_ok( array() === UNDT_Api::query( 'undt_faq' ), 'Abgeschalteter Bereich liefert keine Zeilen' );
undt_ok( array() === UNDT_Api::query( 'undt_hours' ), 'Gilt auch für die Öffnungszeiten' );
undt_ok( false === undt_is_open(), 'undt_is_open meldet bei abgeschaltetem Bereich false' );
undt_ok( '' === undt_today(), 'undt_today bleibt dann leer' );

undt_set_modules( array( 'hours' => 1, 'prices' => 1, 'social' => 1, 'faq' => 1, 'banner' => 1, 'seo' => 1 ) );
undt_ok( true === undt_is_open(), 'Donnerstag 10:30 gilt wieder als geöffnet' );
undt_ok( false !== strpos( undt_today(), '09:00' ), 'undt_today liefert die heutigen Zeiten' );

undt_ok( 'Beispiel GmbH' === undt_get( 'company_name' ), 'undt_get liest ein Stammdaten-Feld' );
undt_ok( 'Ersatz' === undt_get( 'fax', 'Ersatz' ), 'undt_get nutzt den Ersatzwert bei leerem Feld' );
undt_ok( '' === undt_get( 'gibt_es_nicht' ), 'Unbekannter Schlüssel liefert nichts' );
undt_ok( true === undt_has( 'company_name' ) && false === undt_has( 'fax' ), 'undt_has unterscheidet gefüllt und leer' );
undt_ok( 'Uhr' === undt_field( 'hours', 'suffix' ), 'undt_field liest ein Feld eines Bereichs' );
undt_ok( '' === undt_loop( 'question' ), 'undt_loop liefert außerhalb einer Schleife nichts' );

// Jede dokumentierte Quelle muss auch wirklich antworten.
foreach ( array_keys( UNDT_Api::sources() ) as $quelle ) {
	undt_ok( is_array( UNDT_Api::query( $quelle ) ), 'Dokumentierte Quelle ' . $quelle . ' antwortet' );
}

/* ------------------------------------------------------------ Ergebnis ---- */

$fails = $GLOBALS['undt_fails'] ?? 0;
echo "\n" . ( $fails ? "\033[31m{$fails} Test(s) fehlgeschlagen\033[0m" : "\033[32mAlle Tests bestanden\033[0m" ) . "\n";
exit( $fails ? 1 : 0 );
