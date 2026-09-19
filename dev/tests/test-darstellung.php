<?php
/**
 * Pruefungen fuer 0.5.2: Darstellung der Oeffnungszeiten und Social-Profile,
 * Kartenlinks, Banner-Werte fuer Bricks und Etch und die Logos an den
 * Kopierknoepfen.
 *
 * Ob das CSS in Block-Themes rechtzeitig registriert wird, zeigt nur WordPress
 * selbst, siehe dev/verify.php.
 */
require __DIR__ . '/harness.php';
require UNDT_TEST_BASE . 'admin/class-undt-fields.php';
require UNDT_TEST_BASE . 'admin/class-undt-controls.php';
require UNDT_TEST_BASE . 'admin/class-undt-copy.php';

function wp_list_pluck( $list, $field ) {
	return array_map( static function ( $item ) use ( $field ) { return is_object( $item ) ? $item->$field : $item[ $field ]; }, $list );
}

function undt_render_rows( array $keys, array $args = array( 'with_copy' => true ) ) {
	ob_start();
	UNDT_Fields::rows( array_intersect_key( UNDT_Schema::fields(), array_flip( $keys ) ), UNDT_Store::company(), 'undt_company', $args );
	return (string) ob_get_clean();
}

$undt_company = array(
	'company_name' => 'Müller & Söhne',
	'street'       => 'Hauptstraße 5',
	'postal_code'  => '12345',
	'city'         => 'Musterstadt',
	'phone'        => '+49 30 1',
	'email'        => 'info@example.test',
);

/* ------------------------------------------------ 1. Oeffnungszeiten ---- */

undt_head( 'Oeffnungszeiten: Punktlinie und Abstaende' );

undt_set_modules( array( 'hours' => 1, 'social' => 1, 'banner' => 1 ) );
undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'standard' ), $undt_company );
undt_seed_module(
	'hours',
	array(
		'days'    => array( 'mon' => array( 'closed' => 0, 'slots' => array( array( 'from' => '09:00', 'to' => '11:00' ) ) ) ),
		'special' => array( array( 'date' => '2026-12-24', 'closed' => 1, 'note' => 'Heiligabend' ) ),
		'note'    => 'Termine nach Vereinbarung.',
	)
);

$html = UNDT_Blocks::hours( array( 'heading_level' => 3, 'short' => '1', 'group' => '0', 'special' => '1', 'note' => '1' ) );
undt_ok( false !== strpos( $html, '<th scope="row"><span class="undt-hours__label">Mo</span></th><td>09:00 – 11:00 Uhr</td>' ), 'Tag steht in der Beschriftung mit Punktlinie, die Zeit daneben' );
undt_ok( false !== strpos( $html, '<th scope="row"><span class="undt-hours__label"><time datetime="2026-12-24">24. Dezember 2026</time></span><span class="undt-hours__occasion">Heiligabend</span></th>' ), 'Sondertermin: Punktlinie hinter dem Datum, Anlass darunter' );

$css = UNDT_Blocks::css();
undt_ok( false !== strpos( $css, '.undt-hours__table{width:100%;max-width:var(--undt-hours-width,25em);border-collapse:collapse;margin:0}' ), 'Tabelle schmal und ohne Aussenabstand' );
undt_ok( false !== strpos( $css, '.undt-hours__label::after{content:"";flex:1 1 auto;min-width:1em;border-bottom:1px var(--undt-hours-leader,dotted) currentColor}' ), 'Punktlinie in Textfarbe, abschaltbar' );
undt_ok( false !== strpos( $css, '.undt-hours__table td{padding-left:.6em;text-align:right;white-space:nowrap}' ), 'Uhrzeiten rechtsbuendig und ohne Umbruch' );

$css = UNDT_Render::css();
undt_ok( 0 === strpos( $css, '.undt-block{margin:0 0 var(--undt-block-spacing,0)}.undt-block>:last-child{margin-bottom:0}' ), 'Bloecke ohne Aussenabstand, einstellbar' );

/* ---------------------------------------------------- 2. Kartenlinks ---- */

undt_head( 'Kartenlinks' );

undt_ok( 'https://www.google.com/maps/search/?api=1&query=M%C3%BCller%20%26%20S%C3%B6hne%2C%20Hauptstra%C3%9Fe%205%2C%2012345%20Musterstadt' === UNDT_Store::get( 'maps_google' ), 'Google: Suche nach Firma und Anschrift' );
undt_ok( 'https://maps.apple.com/?address=Hauptstra%C3%9Fe%205%2C%2012345%20Musterstadt&q=M%C3%BCller%20%26%20S%C3%B6hne' === UNDT_Store::get( 'maps_apple' ), 'Apple: Anschrift mit Firmennamen' );
undt_ok( UNDT_Store::has( 'maps_google' ), 'Ein abgeleiteter Link zaehlt als vorhanden' );

undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'standard' ), array_merge( $undt_company, array( 'country' => 'Österreich', 'maps_google' => 'https://maps.app.goo.gl/abc' ) ) );
undt_ok( 'https://maps.app.goo.gl/abc' === UNDT_Store::get( 'maps_google' ), 'Ein eingetragener Link hat Vorrang' );
undt_ok( false !== strpos( UNDT_Store::get( 'maps_apple' ), 'address=Hauptstra%C3%9Fe%205%2C%2012345%20Musterstadt%2C%20%C3%96sterreich' ), 'Das Land gehoert zur Anschrift' );
undt_ok( array() === array_diff_key( array( 'maps_google' => 1 ), $GLOBALS['undt_options']['undt_company'] ), 'Gespeichert wird nur, was eingetragen ist' );
undt_ok( ! isset( $GLOBALS['undt_options']['undt_company']['maps_apple'] ), 'Der abgeleitete Link landet nicht in der Datenbank' );

undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'standard' ), array( 'company_name' => 'Nur Name', 'city' => 'Ort' ) );
undt_ok( '' === UNDT_Store::get( 'maps_google' ) && ! UNDT_Store::has( 'maps_apple' ), 'Ohne Strasse kein Link' );

undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'standard' ), array( 'street' => 'Weg 1', 'postal_code' => '1', 'city' => 'Ort' ) );
undt_ok( 'https://www.google.com/maps/search/?api=1&query=Weg%201%2C%201%20Ort' === UNDT_Store::get( 'maps_google' ), 'Ohne Firmennamen nur die Anschrift' );
undt_ok( 'https://maps.apple.com/?address=Weg%201%2C%201%20Ort' === UNDT_Store::get( 'maps_apple' ), 'Apple ohne Beschriftung' );

undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'standard' ), $undt_company );
$html = undt_render_rows( array( 'maps_google', 'maps_apple' ) );
undt_ok( false !== strpos( $html, 'name="undt_company[maps_google]" value="" class="large-text" placeholder="https://www.google.com/maps/search/?api=1&amp;query=Müller &amp; Söhne, Hauptstraße 5, 12345 Musterstadt"' ), 'Das leere Feld zeigt den erzeugten Link lesbar und grau an' );
undt_ok( false !== strpos( $html, 'data-undt-copy="{undt_maps_apple}"' ), 'Beide Kartenlinks haben Kopierknoepfe' );
undt_ok( false !== strpos( $html, '<span class="undt-pair-title">Kartenlinks</span>' ), 'Beide stehen in einer Zeile' );

undt_ok( '<a href="' . esc_url( UNDT_Store::get( 'maps_google' ) ) . '">Route planen</a>' === UNDT_Shortcodes::field( array( 'key' => 'maps_google', 'link' => '1', 'text' => 'Route planen' ) ), 'Shortcode mit eigenem Linktext' );
undt_ok( '<a href="tel:+49301">Anrufen</a>' === UNDT_Shortcodes::field( array( 'key' => 'phone', 'link' => '1', 'text' => 'Anrufen' ) ), 'Linktext auch fuer Telefon' );
undt_ok( '<a href="mailto:info@example.test">Schreiben</a>' === UNDT_Shortcodes::field( array( 'key' => 'email', 'link' => '1', 'text' => 'Schreiben' ) ), 'und fuer E-Mail' );
undt_ok( '+49 30 1' === UNDT_Shortcodes::field( array( 'key' => 'phone', 'text' => 'Anrufen' ) ), 'Ohne link bleibt der Wert' );
undt_ok( '<a href="tel:+49301">&lt;b&gt;</a>' === UNDT_Shortcodes::field( array( 'key' => 'phone', 'link' => '1', 'text' => '<b>' ) ), 'Linktext wird escaped' );

undt_seed_module( 'seo', array( 'schema_type' => 'ProfessionalService', 'output_mode' => 'always' ) );
$data = UNDT_SchemaOrg::organization();
undt_ok( isset( $data['hasMap'] ) && UNDT_Store::get( 'maps_google' ) === $data['hasMap'], 'JSON-LD nennt die Karte als hasMap' );
undt_seed_module( 'seo', array( 'schema_type' => 'Organization', 'output_mode' => 'always' ) );
undt_ok( ! isset( UNDT_SchemaOrg::organization()['hasMap'] ), 'Organization ist kein Ort und bekommt kein hasMap' );

/* ----------------------------------------------------- 3. Social -------- */

undt_head( 'Social: Symbole, Namen und neuer Tab' );

$items = array(
	array( 'platform' => 'facebook', 'label' => '', 'url' => 'https://example.test/f' ),
	array( 'platform' => 'xing', 'label' => 'Mein Xing', 'url' => 'https://example.test/x' ),
);

undt_seed_module( 'social', array( 'items' => $items, 'new_tab' => 1 ) );
$html = UNDT_Blocks::social( array( 'label' => '' ) );
undt_ok( false !== strpos( $html, '<a class="undt-social__link undt-social__link--facebook" data-platform="facebook" href="https://example.test/f" rel="me noopener" target="_blank" aria-label="Facebook (öffnet in neuem Tab)"><span class="undt-social__label">Facebook</span><svg class="undt-social__external"' ), 'Voreinstellung: Name, Pfeil, Hinweis nur fuer Screenreader im Namen' );
undt_ok( false === strpos( $html, 'undt-social__icon' ) && false === strpos( $html, 'undt-sr' ), 'Keine Symbole und kein versteckter Zusatztext' );
undt_ok( false === strpos( strip_tags( $html ), 'neuem Tab' ), 'Der Hinweis steht nirgends als Text' );
undt_ok( false !== strpos( $html, '<ul class="undt-stack-list undt-social__list">' ), 'Voreinstellung: jedes Profil in einer eigenen Zeile' );

// Nebeneinander gibt es weiterhin, ueber den Bereich oder am Shortcode.
undt_seed_module( 'social', array( 'items' => $items, 'layout' => 'row' ) );
undt_ok( false !== strpos( UNDT_Blocks::social( array( 'label' => '' ) ), '<ul class="undt-inline-list undt-social__list">' ), 'Eingestellte Reihe stellt die Profile nebeneinander' );
undt_ok( false !== strpos( UNDT_Blocks::social( array( 'label' => '', 'layout' => 'list' ) ), '<ul class="undt-stack-list undt-social__list">' ), 'Das Attribut sticht die Einstellung' );

undt_seed_module( 'social', array( 'items' => $items ) );
undt_ok( false !== strpos( UNDT_Blocks::social( array( 'label' => '', 'layout' => 'row' ) ), '<ul class="undt-inline-list undt-social__list">' ), 'Und umgekehrt' );
undt_ok( false !== strpos( UNDT_Blocks::social( array( 'label' => '', 'layout' => 'quer' ) ), '<ul class="undt-stack-list undt-social__list">' ), 'Ein unbekanntes Attribut faellt auf die Einstellung zurueck' );

$css = UNDT_Render::css();
undt_ok( false !== strpos( $css, '.undt-stack-list{' ) && false !== strpos( $css, 'flex-direction:column' ), 'Das Stylesheet kennt die Klasse' );

undt_seed_module( 'social', array( 'items' => $items, 'new_tab' => 1 ) );
$html = UNDT_Blocks::social( array( 'label' => '' ) );

undt_seed_module( 'social', array( 'items' => $items, 'new_tab' => 1, 'new_tab_icon' => 0 ) );
undt_ok( false === strpos( UNDT_Blocks::social( array( 'label' => '' ) ), 'undt-social__external' ), 'Pfeil laesst sich abschalten' );

undt_seed_module( 'social', array( 'items' => $items, 'show_icons' => 1 ) );
$html = UNDT_Blocks::social( array( 'label' => '' ) );
undt_ok( false !== strpos( $html, '<a class="undt-social__link undt-social__link--facebook" data-platform="facebook" href="https://example.test/f" rel="me"><span class="undt-social__icon" aria-hidden="true"><svg width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg"><path d="M1 1h2"></path></svg></span><span class="undt-social__label">Facebook</span></a>' ), 'Symbol aus WordPress, Groesse und Farbe vom Text, ohne neuen Tab kein aria-label' );
undt_ok( false !== strpos( $html, '<span class="undt-social__icon" aria-hidden="true"><svg width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg"><path d="M9 9h9"></path></svg></span><span class="undt-social__label">Mein Xing</span>' ), 'Xing bekommt das Link-Symbol' );
undt_ok( false !== strpos( $html, '<nav class="undt-block undt-social undt-social--icons"' ), 'Navigation kennzeichnet die Symbole' );

undt_seed_module( 'social', array( 'items' => $items, 'show_icons' => 1, 'show_labels' => 0, 'new_tab' => 1 ) );
$html = UNDT_Blocks::social( array( 'label' => '' ) );
undt_ok( false === strpos( $html, 'undt-social__label' ) && false === strpos( $html, 'undt-social__external' ), 'Nur Symbole: kein Name, kein Pfeil' );
undt_ok( false !== strpos( $html, 'aria-label="Mein Xing (öffnet in neuem Tab)"' ), 'Der Name bleibt fuer Screenreader erhalten' );
undt_ok( false !== strpos( $html, 'undt-social--icons-only' ), 'Navigation kennzeichnet die reinen Symbole' );

undt_seed_module( 'social', array( 'items' => $items, 'show_icons' => 0, 'show_labels' => 0 ) );
$html = UNDT_Blocks::social( array( 'label' => '' ) );
undt_ok( false !== strpos( $html, '<span class="undt-social__label">Facebook</span>' ) && false === strpos( $html, 'aria-label="Facebook' ), 'Ohne Symbole bleiben die Namen sichtbar' );

add_filter(
	'undt_social_icon',
	static function ( $svg, $platform ) {
		return 'xing' === $platform ? '<svg viewBox="0 0 10 10"><path d="M0 0h1"/></svg>' : $svg;
	},
	10,
	2
);
UNDT_Icons::flush();
undt_ok( '<svg width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false" viewBox="0 0 10 10"><path d="M0 0h1"/></svg>' === UNDT_Icons::platform( 'xing' ), 'Filter ersetzt das Symbol einer Plattform' );
remove_all_filters( 'undt_social_icon' );
UNDT_Icons::flush();
undt_ok( false !== strpos( UNDT_Icons::platform( 'x' ), 'M2 2h3' ) && false !== strpos( UNDT_Icons::platform( 'unbekannt' ), 'M9 9h9' ), 'X hat ein eigenes Symbol, Unbekanntes das Link-Symbol' );
undt_ok( '' === UNDT_Icons::svg( 'gibt-es-nicht' ), 'Unbekanntes eigenes Symbol bleibt leer' );

$rows = UNDT_Api::query( 'undt_social' );
undt_ok( isset( $rows[0]['icon'], $rows[0]['new_tab'] ) && false !== strpos( $rows[0]['icon'], '<svg' ) && false === $rows[0]['new_tab'], 'Schleifen-Quelle liefert Symbol und neuen Tab' );

$social = UNDT_Modules::get( 'social' );
ob_start();
UNDT_Controls::render( 'items', $social['fields']['items'], $items, 'undt_social[items]', 'undt-items' );
$html = (string) ob_get_clean();
undt_ok( false !== strpos( $html, '<span class="undt-select-icon"><span class="undt-select-icon__preview" aria-hidden="true"><svg' ), 'Im Backend steht das Symbol neben der Auswahl' );
undt_ok( false !== strpos( $html, 'name="undt_social[items][0][platform]" data-undt-icon-select>' ), 'Die Auswahl meldet sich beim Skript' );
undt_ok( 1 === substr_count( $html, '<div class="undt-icon-library" hidden>' ) && false !== strpos( $html, '<span data-undt-icon="kununu"><svg' ), 'Eine Symbolsammlung je Wiederholungsfeld, mit allen Plattformen' );
undt_ok( 3 === substr_count( $html, 'undt-select-icon__preview' ), 'Zwei Zeilen und die Vorlage' );

/* --------------------------------------------- 4. Banner fuer Builder --- */

undt_head( 'Infobanner fuer Bricks und Etch' );

undt_seed_module( 'banner', array( 'enabled' => 1, 'type' => 'warning', 'text' => "Betriebsferien\nbis Montag", 'link_url' => 'https://example.test/ferien', 'link_text' => '', 'dismissible' => 0 ) );

$fields = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER );
foreach ( array( 'banner_show', 'banner_type', 'banner_text', 'banner_link_text', 'banner_link_url', 'banner_dismissible', 'is_open' ) as $key ) {
	undt_ok( isset( $fields[ $key ] ), 'Builder bekommen ' . $key );
}
undt_ok( ! isset( UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_SEO )['banner_show'] ), 'Slim SEO bekommt keine Banner-Werte' );

undt_ok( '1' === UNDT_Dynamic::value( 'banner_show' ), 'Banner aktiv' );
undt_ok( 'warning' === UNDT_Dynamic::value( 'banner_type' ), 'Art' );
undt_ok( "Betriebsferien\nbis Montag" === UNDT_Dynamic::value( 'banner_text' ), 'Text' );
undt_ok( 'Mehr erfahren' === UNDT_Dynamic::value( 'banner_link_text' ), 'Link ohne Text heisst wie im Banner' );
undt_ok( '' === UNDT_Dynamic::value( 'banner_dismissible' ), 'Nicht schliessbar ergibt leer' );
undt_ok( 'https://example.test/ferien' === UNDT_Dynamic_Bricks::render_tag( '{undt_banner_link_url}', null, 'link' ), 'Link-Ziel im Link-Kontext von Bricks' );
undt_ok( '1' === UNDT_Dynamic_Bricks::render_tag( '{undt_banner_show}', null, 'text' ), 'Bricks bekommt 1' );

$etch = UNDT_Dynamic_Etch::options( array() );
undt_ok( true === $etch['undt']['banner_show'] && false === $etch['undt']['banner_dismissible'], 'Etch bekommt echte Wahrheitswerte' );
undt_ok( 'warning' === $etch['undt']['banner_type'] && is_string( $etch['undt']['banner_text'] ), 'Die uebrigen Werte bleiben Text' );

undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => '' ) );
undt_ok( '' === UNDT_Dynamic::value( 'banner_show' ), 'Ohne Text gilt das Banner als aus' );
undt_ok( 'info' === UNDT_Dynamic::value( 'banner_type' ) && '' === UNDT_Dynamic::value( 'banner_link_text' ), 'Voreinstellungen ohne Link' );
undt_ok( false === UNDT_Dynamic_Etch::options( array() )['undt']['banner_show'], 'Etch bekommt false' );

undt_set_modules( array( 'hours' => 1, 'social' => 1, 'banner' => 0 ) );
undt_ok( ! isset( UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER )['banner_text'] ) && '' === UNDT_Dynamic::value( 'banner_type' ), 'Abgeschaltetes Banner liefert nichts' );
undt_set_modules( array( 'hours' => 1, 'social' => 1, 'banner' => 1 ) );

$GLOBALS['undt_now'] = '2026-09-14 10:00:00'; // Montag.
undt_ok( '1' === UNDT_Dynamic::value( 'is_open' ) && true === UNDT_Dynamic_Etch::options( array() )['undt']['is_open'], 'Geoeffnet als Ja-Nein-Wert' );
$GLOBALS['undt_now'] = '2026-09-14 12:00:00';
undt_ok( '' === UNDT_Dynamic::value( 'is_open' ) && false === UNDT_Dynamic_Etch::options( array() )['undt']['is_open'], 'Geschlossen als Ja-Nein-Wert' );
$GLOBALS['undt_now'] = '2026-09-10 10:30:00';

// Die Felder des Banners bekommen Kopierknoepfe, aber keinen Shortcode.
$banner = UNDT_Modules::get( 'banner' );
ob_start();
UNDT_Fields::rows( $banner['fields'], UNDT_Content::all( 'banner' ), 'undt_banner', array( 'with_copy' => false ) );
$html = (string) ob_get_clean();
undt_ok( false !== strpos( $html, 'data-undt-copy="{undt_banner_show}"' ) && false !== strpos( $html, 'data-undt-copy="{options.undt.banner_show}"' ), 'Banner anzeigen: Kopierknoepfe fuer Bricks und Etch' );
undt_ok( 16 === substr_count( $html, 'undt-copy--icon' ), 'Sechs Felder mit Kuerzeln, vier davon zusaetzlich mit Block' );
undt_ok( 4 === substr_count( $html, 'undt-copy--gutenberg' ), 'Die beiden Ja-Nein-Felder bekommen keinen Block' );
undt_ok( false === strpos( $html, '[undt key=' ), 'Kein Shortcode im Banner' );

/* ------------------------------------------------------- 5. Logos ------- */

undt_head( 'Logos und Pfeil' );

undt_ok( 0 === strpos( UNDT_Icons::svg( 'bricks', 'x' ), '<svg class="x" viewBox="0 0 79 101" width="1em" height="1em" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" aria-hidden="true" focusable="false"><g transform="matrix(1,0,0,1,-38.768425,-26.866258)">' ), 'Bricks-Logo in Textfarbe' );
undt_ok( 0 === strpos( UNDT_Icons::svg( 'etch' ), '<svg viewBox="0 0 110 87" width="1em" height="1em" fill="currentColor"' ), 'Etch-Logo in Textfarbe' );
undt_ok( false !== strpos( UNDT_Icons::svg( 'external' ), 'stroke="currentColor"' ), 'Pfeil als Linie' );

echo empty( $GLOBALS['undt_fails'] ) ? "\n\033[32mAlle Pruefungen bestanden\033[0m\n" : "\n\033[31m" . $GLOBALS['undt_fails'] . " Pruefungen fehlgeschlagen\033[0m\n";
exit( empty( $GLOBALS['undt_fails'] ) ? 0 : 1 );
