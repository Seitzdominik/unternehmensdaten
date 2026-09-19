<?php
require __DIR__ . '/harness.php';

/* ------------------------------------------------- 1. GmbH & Co. KG ------- */

undt_head( 'GmbH & Co. KG mit zwei Registereintraegen' );

undt_seed(
	array(
		'legal_form'         => 'gmbhcokg',
		'sells_to_consumers' => 1,
		'vsbg_participation' => 'no',
		'vat_status'         => 'standard',
	),
	array(
		'company_name'       => 'Muster Handels GmbH & Co. KG',
		'street'             => 'Musterstraße 1',
		'postal_code'        => '12345',
		'city'               => 'Musterstadt',
		'email'              => 'info@example.test',
		'phone'              => '+49 30 1234567',
		'register_court'     => 'Amtsgericht Musterstadt',
		'register_number'    => 'HRA 1234',
		'complementary_name' => 'Muster Verwaltungs GmbH',
		'register_court_2'   => 'Amtsgericht Musterstadt',
		'register_number_2'  => 'HRB 5678',
		'representatives'    => "Erika Mustermann\nMax Mustermann",
		'vat_id'             => 'DE123456789',
		'owner_name'         => 'Sollte verschwinden',
	)
);

$html = UNDT_Render::imprint( array( 'heading_level' => 2 ) );

undt_ok( false !== strpos( $html, 'HRA 1234' ), 'Registernummer der KG erscheint' );
undt_ok( false !== strpos( $html, 'HRB 5678' ), 'Registernummer der Komplementaer-GmbH erscheint' );
undt_ok( false !== strpos( $html, 'Handelsregister A' ), 'Registerart der KG korrekt abgeleitet' );
undt_ok( false !== strpos( $html, 'Handelsregister B' ), 'Registerart der Komplementaerin korrekt abgeleitet' );
undt_ok( false !== strpos( $html, 'Erika Mustermann<br />Max Mustermann' ), 'Beide Vertretungsberechtigten erscheinen' );
undt_ok( false === strpos( $html, 'Sollte verschwinden' ), 'Inhaber-Feld wird bei der KG unterdrueckt' );
undt_ok( false === stripos( $html, 'ec.europa.eu' ), 'Kein Verweis auf die OS-Plattform' );
undt_ok( false === stripos( $html, 'TMG' ), 'Keine Nennung des TMG' );
undt_ok( false !== strpos( $html, '§ 5 DDG' ), 'Ueberschrift nennt das DDG' );
undt_ok( 1 === substr_count( $html, '<address' ), 'Genau ein address-Element' );
undt_ok( false !== strpos( $html, 'nicht bereit und nicht verpflichtet' ), 'VSBG-Hinweis vorhanden' );

/* --------------------------------------------------------- 2. Arztpraxis -- */

undt_head( 'Arztpraxis, reglementierter Heilberuf' );

undt_seed(
	array(
		'legal_form'        => 'freelancer',
		'is_regulated'      => 1,
		'is_medical'        => 1,
		'has_liability_ins' => 1,
		'has_editorial'     => 1,
		'vat_status'        => 'small_business',
	),
	array(
		'company_name'      => 'Praxis Dr. med. Anna Beispiel',
		'owner_name'        => 'Dr. med. Anna Beispiel',
		'street'            => 'Praxisweg 7',
		'postal_code'       => '54321',
		'city'              => 'Beispielheim',
		'email'             => 'praxis@example.test',
		'phone'             => '030 7654321',
		'job_title'         => 'Ärztin',
		'job_title_country' => 'Bundesrepublik Deutschland',
		'specialist_title'  => 'Fachärztin für Innere Medizin',
		'chamber_name'      => 'Landesärztekammer Musterland',
		'chamber_url'       => 'https://example.test/kammer',
		'kv_name'           => 'Kassenärztliche Vereinigung Musterland',
		'prof_rules'        => "Berufsordnung der Landesärztekammer Musterland\nHeilberufe-Kammergesetz Musterland",
		'prof_rules_url'    => 'https://example.test/berufsordnung',
		'insurer_name'      => 'Muster Versicherung AG',
		'insurer_address'   => "Versicherungsplatz 1\n11111 Versicherungsstadt",
		'insurance_scope'   => 'Deutschland',
		'editorial_name'    => 'Dr. med. Anna Beispiel',
		'register_number'   => 'Darf nicht erscheinen',
	)
);

$html = UNDT_Render::imprint( array( 'heading_level' => 3 ) );

undt_ok( false !== strpos( $html, '<h3' ), 'heading_level wird uebernommen' );
undt_ok( false === strpos( $html, '<h2' ), 'Keine h2 bei heading_level 3' );
undt_ok( false !== strpos( $html, 'Bundesrepublik Deutschland' ), 'Staat der Verleihung erscheint' );
undt_ok( false !== strpos( $html, 'Fachärztin für Innere Medizin' ), 'Facharztbezeichnung erscheint' );
undt_ok( false !== strpos( $html, 'Kassenärztliche Vereinigung' ), 'KV erscheint' );
undt_ok( 2 === substr_count( $html, '<li>' ), 'Beide berufsrechtlichen Regelungen als Listeneintraege' );
undt_ok( false !== strpos( $html, 'berufsordnung' ), 'Link zu den Regelungen erscheint' );
undt_ok( false !== strpos( $html, 'Räumlicher Geltungsbereich' ), 'DL-InfoV Geltungsbereich erscheint' );
undt_ok( false !== strpos( $html, '18 Abs. 2 MStV' ), 'MStV-Verantwortlicher erscheint' );
undt_ok( false !== strpos( $html, '§ 19 UStG' ), 'Kleinunternehmer-Hinweis aus dem Default' );
undt_ok( false === strpos( $html, 'Darf nicht erscheinen' ), 'Registerfeld beim Freiberufler unterdrueckt' );
undt_ok( false === strpos( $html, 'Verbraucherstreitbeilegung' ), 'Kein VSBG-Block ohne Verbrauchergeschaeft' );

/* ------------------------------------------------- 3. Feld-Shortcode ------ */

undt_head( 'Einzelfeld-Shortcode' );

undt_ok( 'Praxis Dr. med. Anna Beispiel' === UNDT_Shortcodes::field( array( 'key' => 'company_name' ) ), 'Feld wird ausgegeben' );
undt_ok( '' === UNDT_Shortcodes::field( array( 'key' => 'register_number' ) ), 'Nicht geltendes Feld gibt nichts aus' );
undt_ok( '' === UNDT_Shortcodes::field( array( 'key' => 'admin_email' ) ), 'Unbekannter Schluessel gibt nichts aus' );
undt_ok( '' === UNDT_Shortcodes::field( array( 'key' => '../../wp-config' ) ), 'Pfadartiger Schluessel gibt nichts aus' );

$with_before = UNDT_Shortcodes::field( array( 'key' => 'phone', 'before' => 'Telefon: ' ) );
undt_ok( 'Telefon: 030 7654321' === $with_before, 'before erscheint bei gefuelltem Feld' );

$empty_before = UNDT_Shortcodes::field( array( 'key' => 'fax', 'before' => 'Fax: ' ) );
undt_ok( '' === $empty_before, 'before erscheint NICHT bei leerem Feld' );

$linked = UNDT_Shortcodes::field( array( 'key' => 'phone', 'link' => '1' ) );
undt_ok( false !== strpos( $linked, 'href="tel:0307654321"' ), 'Telefonlink wird korrekt normalisiert' );

$xss = UNDT_Shortcodes::field( array( 'key' => 'company_name', 'before' => '<script>alert(1)</script>' ) );
undt_ok( false === strpos( $xss, '<script' ), 'before wird escaped' );

/* ------------------------------------------------------ 4. Sanitizer ------ */

undt_head( 'Sanitisierung' );

$clean = UNDT_Sanitizer::company(
	array(
		'website'      => 'javascript:alert(1)',
		'chamber_url'  => 'https://example.test/ok',
		'company_name' => '<b>Fett</b> & Co',
		'email'        => 'not-an-email',
		'phone'        => '+49 (30) 12-34 <script>',
		'evil_key'     => 'sollte verschwinden',
	)
);

undt_ok( '' === $clean['website'], 'javascript:-URL wird verworfen' );
undt_ok( 'https://example.test/ok' === $clean['chamber_url'], 'https-URL bleibt erhalten' );
undt_ok( false === strpos( $clean['company_name'], '<b>' ), 'HTML wird aus Textfeldern entfernt' );
undt_ok( '' === $clean['email'], 'Ungueltige E-Mail wird verworfen' );
undt_ok( false === strpos( $clean['phone'], 'script' ), 'Telefonnummer wird auf erlaubte Zeichen reduziert' );
undt_ok( ! array_key_exists( 'evil_key', $clean ), 'Unbekannter Schluessel wird nicht gespeichert' );

// Merge-Semantik: ein Teilformular darf keine anderen Felder leeren.
$GLOBALS['undt_options']['undt_company'] = array( 'company_name' => 'Bestand', 'city' => 'Bestandsstadt' );
UNDT_Store::flush();
$merged = UNDT_Sanitizer::company( array( 'city' => 'Neustadt' ) );
undt_ok( 'Bestand' === $merged['company_name'], 'Nicht uebermitteltes Feld bleibt erhalten' );
undt_ok( 'Neustadt' === $merged['city'], 'Uebermitteltes Feld wird ueberschrieben' );

/* ---------------------------------------------------------- 5. Footer ----- */

undt_head( 'Footer und Rechtslinks' );

$GLOBALS['undt_posts'] = array(
	10 => array( 'title' => 'Impressum', 'status' => 'publish' ),
	11 => array( 'title' => 'Datenschutz', 'status' => 'draft' ),
);

undt_seed(
	array( 'legal_form' => 'gmbh' ),
	array(
		'company_name' => 'Beispiel GmbH',
		'street'       => 'Weg 2',
		'postal_code'  => '10000',
		'city'         => 'Stadt',
		'page_imprint' => '10',
		'page_privacy' => '11',
	)
);

$footer = UNDT_Render::footer( array( 'show' => 'address,legal,copyright' ) );

undt_ok( false !== strpos( $footer, 'Beispiel GmbH · Weg 2 · 10000 Stadt' ), 'Einzeilige Anschrift korrekt' );
undt_ok( false !== strpos( $footer, '>Impressum<' ), 'Veroeffentlichte Seite wird verlinkt' );
undt_ok( false === strpos( $footer, '>Datenschutz<' ), 'Entwurf wird NICHT verlinkt' );
undt_ok( false !== strpos( $footer, 'aria-label' ), 'Navigation hat ein aria-label' );
undt_ok( false !== strpos( $footer, '&copy; ' . date( 'Y' ) ), 'Copyright mit laufendem Jahr' );

$only_nav = UNDT_Render::footer( array( 'show' => 'legal' ) );
undt_ok( false === strpos( $only_nav, 'copyright' ), 'show-Attribut begrenzt die Ausgabe' );

/* ----------------------------------------------------------- 6. Audit ----- */

undt_head( 'Pruefung' );

undt_seed(
	array( 'legal_form' => 'ug', 'sells_to_consumers' => 1, 'vsbg_participation' => 'no' ),
	array(
		'company_name' => 'Beispiel UG',
		'street'       => 'Weg 2',
		'postal_code'  => '10000',
		'city'         => 'Stadt',
		'email'        => 'a@example.test',
	)
);

$issues = UNDT_Audit::run();
$titles = implode( ' | ', array_column( $issues, 'title' ) );

undt_ok( false !== strpos( $titles, 'Rechtsformzusatz der UG' ), 'Fehlendes "(haftungsbeschränkt)" wird erkannt' );
undt_ok( false !== strpos( $titles, 'Zweiter Kommunikationsweg' ), 'Fehlender zweiter Kontaktweg wird erkannt' );
undt_ok( false !== strpos( $titles, 'Registernummer' ), 'Fehlende Registernummer wird erkannt' );
undt_ok( UNDT_Audit::quick_count() > 0, 'quick_count liefert einen Wert' );

// Mit korrektem Namen darf die UG-Warnung verschwinden.
undt_seed(
	array( 'legal_form' => 'ug' ),
	array( 'company_name' => 'Beispiel UG (haftungsbeschränkt)' )
);
$titles = implode( ' | ', array_column( UNDT_Audit::run(), 'title' ) );
undt_ok( false === strpos( $titles, 'Rechtsformzusatz der UG' ), 'Korrekter UG-Zusatz erzeugt keine Warnung' );

/* ------------------------------------------- 7. Nullwerte bei ID-Feldern -- */

undt_head( 'Seiten- und Medienfelder mit Wert 0' );

$GLOBALS['undt_posts'] = array();

undt_seed(
	array( 'legal_form' => 'gmbh', 'sells_to_consumers' => 1 ),
	array(
		'company_name'       => 'Beispiel GmbH',
		// Genau das speichert das Formular, wenn keine Seite ausgewaehlt ist.
		'page_accessibility' => '0',
		'page_imprint'       => '0',
	)
);

undt_ok( false === UNDT_Store::has( 'page_accessibility' ), 'Seitenfeld mit Wert 0 gilt als nicht ausgefuellt' );
undt_ok( false === UNDT_Store::has( 'page_imprint' ), 'Auch beim Impressum zaehlt die 0 nicht als Seite' );
undt_ok( true === UNDT_Store::has( 'company_name' ), 'Ein normales Textfeld bleibt davon unberuehrt' );

$titles = implode( ' | ', array_column( UNDT_Audit::run(), 'title' ) );
undt_ok(
	false !== strpos( $titles, 'Barrierefreiheit' ),
	'Pruefung meldet die fehlende Barrierefreiheitserklaerung trotz gespeicherter 0'
);

// Eine echte Seite muss den Hinweis dagegen verstummen lassen.
$GLOBALS['undt_posts'] = array( 42 => array( 'title' => 'Barrierefreiheit', 'status' => 'publish' ) );
undt_seed(
	array( 'legal_form' => 'gmbh', 'sells_to_consumers' => 1 ),
	array( 'company_name' => 'Beispiel GmbH', 'page_accessibility' => '42' )
);
undt_ok( true === UNDT_Store::has( 'page_accessibility' ), 'Mit echter Seiten-ID gilt das Feld als ausgefuellt' );
$titles = implode( ' | ', array_column( UNDT_Audit::run(), 'title' ) );
undt_ok( false === strpos( $titles, 'Barrierefreiheit' ), 'Hinweis verschwindet, sobald eine Seite verknuepft ist' );

/* ------------------------------------------------------------ Ergebnis ---- */

$fails = $GLOBALS['undt_fails'] ?? 0;
echo "\n" . ( $fails ? "\033[31m{$fails} Test(s) fehlgeschlagen\033[0m" : "\033[32mAlle Tests bestanden\033[0m" ) . "\n";
exit( $fails ? 1 : 0 );
