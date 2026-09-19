<?php
/**
 * Pruefungen fuer 0.5.0: Seitenfelder mit eigener Adresse, Sprache der Tage
 * und Monate, dynamische Daten fuer Slim SEO, Bricks und Etch.
 *
 * Was nur in WordPress selbst sichtbar wird, etwa die Auswahl im Slim-SEO-
 * Dialog oder die aufgeloesten Bricks-Tags, pruefen dev/verify.php und der
 * Test auf der Testseite.
 */
require __DIR__ . '/harness.php';
require dirname( __DIR__, 2 ) . '/unternehmensdaten/admin/class-undt-fields.php';

/*
 * Attrappen fuer die Auswahl der Seitenfelder. Sie stehen hier und nicht im
 * Harness, weil nur diese Datei und test-audit-findings.php die Admin-Klasse
 * laden.
 */
class WP_Post {
	public $ID          = 0;
	public $post_title  = '';
	public $post_status = 'publish';
	public $post_parent = 0;
	public $post_type   = 'page';

	public function __construct( array $data = array() ) {
		foreach ( $data as $key => $value ) {
			$this->$key = $value;
		}
	}
}

$GLOBALS['undt_post_types'] = array();
foreach (
	array(
		'post'            => array( 'Beiträge', false, true ),
		'page'            => array( 'Seiten', true, true ),
		'attachment'      => array( 'Medien', false, true ),
		'product'         => array( 'Produkte', false, true ),
		// Wie auf der Testseite: Bricks gibt seine Vorlagen fuer Menues frei.
		'bricks_template' => array( 'Templates', false, true ),
		'ohne_menue'      => array( 'Ohne Menü', false, false ),
		'legal'           => array( 'Rechtstexte', false, true ),
	) as $undt_type => $undt_meta
) {
	$GLOBALS['undt_post_types'][ $undt_type ] = (object) array(
		'name'              => $undt_type,
		'labels'            => (object) array( 'name' => $undt_meta[0] ),
		'hierarchical'      => $undt_meta[1],
		'show_in_nav_menus' => $undt_meta[2],
	);
}

$GLOBALS['undt_all_posts'] = array(
	new WP_Post( array( 'ID' => 10, 'post_title' => 'Impressum', 'post_type' => 'page' ) ),
	new WP_Post( array( 'ID' => 11, 'post_title' => 'Datenschutz alt', 'post_type' => 'page', 'post_status' => 'draft' ) ),
	new WP_Post( array( 'ID' => 12, 'post_title' => 'AGB', 'post_type' => 'legal' ) ),
	new WP_Post( array( 'ID' => 13, 'post_title' => 'Vorlage', 'post_type' => 'bricks_template' ) ),
	new WP_Post( array( 'ID' => 14, 'post_title' => 'Papierkorb', 'post_type' => 'page', 'post_status' => 'trash' ) ),
	new WP_Post( array( 'ID' => 15, 'post_title' => 'Nicht verlinkbar', 'post_type' => 'ohne_menue' ) ),
);
$GLOBALS['undt_get_posts_calls'] = 0;

function get_post_types( $args = array(), $output = 'names' ) { return $GLOBALS['undt_post_types']; }
function post_type_exists( $type ) { return isset( $GLOBALS['undt_post_types'][ $type ] ); }
function is_post_type_hierarchical( $type ) { return ! empty( $GLOBALS['undt_post_types'][ $type ]->hierarchical ); }
function get_post_status_object( $status ) {
	$labels = array( 'draft' => 'Entwurf', 'private' => 'Privat', 'trash' => 'Papierkorb' );
	return isset( $labels[ $status ] ) ? (object) array( 'label' => $labels[ $status ] ) : null;
}
function get_posts( $args ) {
	++$GLOBALS['undt_get_posts_calls'];
	$GLOBALS['undt_get_posts_args'] = $args;
	return array_values(
		array_filter(
			$GLOBALS['undt_all_posts'],
			static function ( $post ) use ( $args ) {
				return $post->post_type === $args['post_type'] && in_array( $post->post_status, (array) $args['post_status'], true );
			}
		)
	);
}
function wp_list_pluck( $list, $field ) {
	return array_map( static function ( $item ) use ( $field ) { return is_object( $item ) ? $item->$field : $item[ $field ]; }, $list );
}
function walk_page_dropdown_tree( $posts, $depth, $args ) {
	$out = '';
	foreach ( $posts as $post ) {
		$out .= '<option class="level-0" value="' . (int) $post->ID . '"' . ( $post->ID === (int) $args['selected'] ? ' selected="selected"' : '' ) . '>'
			. esc_html( apply_filters( 'list_pages', $post->post_title, $post ) ) . '</option>';
	}
	return $out;
}
function wp_trim_words( $text, $num_words = 55, $more = null ) {
	$more  = null === $more ? '&hellip;' : $more;
	$words = preg_split( '/\s+/', trim( strip_tags( $text ) ), -1, PREG_SPLIT_NO_EMPTY );
	return count( $words ) > $num_words ? implode( ' ', array_slice( $words, 0, $num_words ) ) . $more : implode( ' ', $words );
}
function remove_filter( $tag, $cb, $priority = 10 ) {
	if ( isset( $GLOBALS['undt_filters'][ $tag ] ) ) {
		$GLOBALS['undt_filters'][ $tag ] = array_values( array_filter( $GLOBALS['undt_filters'][ $tag ], static function ( $f ) use ( $cb ) { return $f !== $cb; } ) );
	}
	return true;
}

// Veroeffentlicht, Entwurf und ein eigener Inhaltstyp fuer die Ausgabe.
$GLOBALS['undt_posts'] = array(
	10 => array( 'status' => 'publish', 'title' => 'Impressum' ),
	11 => array( 'status' => 'draft', 'title' => 'Datenschutz alt' ),
	12 => array( 'status' => 'publish', 'title' => 'AGB' ),
);

function undt_render_control( $key, $value ) {
	ob_start();
	UNDT_Fields::control( $key, UNDT_Schema::field( $key ), $value, 'undt_company[' . $key . ']', 'undt-' . $key );
	return (string) ob_get_clean();
}

$undt_profile = array( 'legal_form' => 'sole', 'vat_status' => 'standard', 'sells_to_consumers' => 1 );

/* ------------------------------------------- 1. Seitenfeld speichern --- */

undt_head( 'Seitenfeld: Auswahl oder eigene Adresse speichern' );

$undt_cases = array(
	array( array( 'choice' => '12', 'url' => 'https://example.test/egal' ), 12, 'Gewaehlter Eintrag speichert die ID, die Adresse daneben zaehlt nicht' ),
	array( array( 'choice' => 'url', 'url' => 'https://example.test/recht/impressum/' ), 'https://example.test/recht/impressum/', 'Eigene Adresse wird gespeichert' ),
	array( array( 'choice' => 'url', 'url' => '/impressum/' ), '/impressum/', 'Pfad der eigenen Website bleibt relativ' ),
	array( array( 'choice' => 'url', 'url' => 'impressum' ), '/impressum', 'Pfad ohne Schraegstrich wird zum Pfad, nicht zu http://impressum' ),
	array( array( 'choice' => 'url', 'url' => 'example.com/impressum' ), 'https://example.com/impressum', 'Domain ohne Schema bekommt https' ),
	array( array( 'choice' => 'url', 'url' => 'javascript:alert(1)' ), 0, 'javascript: wird verworfen' ),
	array( array( 'choice' => 'url', 'url' => 'data:text/html,x' ), 0, 'data: wird verworfen' ),
	array( array( 'choice' => 'url', 'url' => '' ), 0, 'Eigene Adresse ohne Inhalt ergibt keine Verknuepfung' ),
	array( array( 'choice' => 'url', 'url' => array( 'x' ) ), 0, 'Verschachtelte Adresse ergibt keine Verknuepfung' ),
	array( array( 'choice' => array( '12' ) ), 0, 'Verschachtelte Auswahl ergibt keine Verknuepfung' ),
	array( array( 'choice' => '0', 'url' => '/impressum/' ), 0, '„keine Seite“ gewinnt gegen eine stehengebliebene Adresse' ),
	array( array( 'choice' => '-5' ), 5, 'Negative ID wird wie bisher mit absint behandelt' ),
	array( '12', 12, 'Einzelwert als Zahl bleibt eine ID' ),
	array( 'https://example.test/x', 'https://example.test/x', 'Einzelwert als Adresse bleibt eine Adresse' ),
	array( '', 0, 'Leerer Einzelwert ergibt 0' ),
);

foreach ( $undt_cases as $undt_case ) {
	undt_seed( $undt_profile, array( 'page_imprint' => $undt_case[0] ) );
	$undt_stored = $GLOBALS['undt_options']['undt_company']['page_imprint'];
	undt_ok( $undt_case[1] === $undt_stored, $undt_case[2] . ' (' . var_export( $undt_stored, true ) . ')' );
}

/* -------------------------------------------- 2. Seitenfeld ausgeben --- */

undt_head( 'Seitenfeld: Ziel und Ausgabe' );

undt_seed( $undt_profile, array( 'page_imprint' => '10', 'page_privacy' => '11', 'page_terms' => array( 'choice' => 'url', 'url' => '/agb/' ), 'page_accessibility' => '0' ) );

$undt_link = UNDT_Store::link( 'page_imprint' );
undt_ok( is_array( $undt_link ) && 'https://example.test/?p=10' === $undt_link['url'] && 'Impressum' === $undt_link['title'] && 10 === $undt_link['post_id'], 'Veroeffentlichte Seite liefert Adresse, Titel und ID' );
undt_ok( null === UNDT_Store::link( 'page_privacy' ), 'Entwurf liefert kein Ziel' );
$undt_link = UNDT_Store::link( 'page_terms' );
undt_ok( is_array( $undt_link ) && '/agb/' === $undt_link['url'] && '' === $undt_link['title'] && 0 === $undt_link['post_id'], 'Eigene Adresse liefert sich selbst ohne Titel' );
undt_ok( null === UNDT_Store::link( 'page_accessibility' ), 'Keine Auswahl liefert kein Ziel' );
undt_ok( null === UNDT_Store::link( 'phone' ), 'Kein Seitenfeld liefert kein Ziel' );

undt_ok( UNDT_Store::has( 'page_terms' ), 'Eigene Adresse gilt als ausgefuellt' );
undt_ok( ! UNDT_Store::has( 'page_accessibility' ), 'Die 0 gilt weiter als nicht ausgefuellt' );
undt_ok( 10 === UNDT_Store::page_id( 'page_imprint' ) && 0 === UNDT_Store::page_id( 'page_terms' ), 'page_id liefert nur echte IDs' );

$undt_nav = UNDT_Render::legal_nav();
undt_ok( false !== strpos( $undt_nav, '<a href="https://example.test/?p=10">Impressum</a>' ), 'Footer verlinkt die gewaehlte Seite' );
undt_ok( false !== strpos( $undt_nav, '<a href="/agb/">AGB</a>' ), 'Footer verlinkt die eigene Adresse' );
undt_ok( false === strpos( $undt_nav, 'Datenschutz' ), 'Footer laesst den Entwurf weg' );

undt_ok( '<a href="/agb/">/agb/</a>' === UNDT_Shortcodes::field( array( 'key' => 'page_terms', 'link' => '1' ) ), 'Shortcode mit link zeigt die eigene Adresse als Linktext' );
undt_ok( '/agb/' === UNDT_Shortcodes::field( array( 'key' => 'page_terms' ) ), 'Shortcode ohne link zeigt die eigene Adresse' );
undt_ok( 'Impressum' === UNDT_Shortcodes::field( array( 'key' => 'page_imprint' ) ), 'Shortcode zeigt weiter den Seitentitel' );
undt_ok( 'fehlt' === UNDT_Shortcodes::field( array( 'key' => 'page_privacy', 'fallback' => 'fehlt' ) ), 'Entwurf faellt weiter auf den Ersatztext zurueck' );

$undt_issues = UNDT_Audit::run();
undt_ok( 1 === count( array_filter( $undt_issues, static function ( $i ) { return false !== strpos( $i['title'], 'nicht veröffentlicht' ); } ) ), 'Pruefung meldet den Entwurf, aber nicht die eigene Adresse' );

/* ------------------------------------------ 3. Seitenfeld im Formular --- */

undt_head( 'Seitenfeld: Auswahl im Formular' );

undt_ok( array( 'page' => 'Seiten', 'legal' => 'Rechtstexte' ) === UNDT_Fields::link_post_types(), 'Seiten und eigene Typen, ohne Beitraege, Produkte, Medien, Bricks-Vorlagen und nicht verlinkbare Typen' );

add_filter(
	'undt_link_post_types',
	static function ( $types ) {
		$types['post']  = 'Beiträge';
		$types['nicht'] = 'Gibt es nicht';
		return $types;
	}
);
undt_ok( array( 'page' => 'Seiten', 'legal' => 'Rechtstexte', 'post' => 'Beiträge' ) === UNDT_Fields::link_post_types(), 'Filter ergaenzt Typen, unbekannte fallen weg' );
remove_all_filters( 'undt_link_post_types' );

$undt_html = undt_render_control( 'page_terms', '12' );
undt_ok( false !== strpos( $undt_html, '<select id="undt-page_terms" name="undt_company[page_terms][choice]" data-undt-link>' ), 'Auswahl sendet unter [choice]' );
undt_ok( false !== strpos( $undt_html, '<optgroup label="Rechtstexte"><option class="level-0" value="12" selected="selected">AGB</option></optgroup>' ), 'Eintrag des eigenen Typs steht in seiner Gruppe und ist gewaehlt' );
undt_ok( false !== strpos( $undt_html, '<optgroup label="Seiten">' ), 'Seiten stehen in eigener Gruppe' );
undt_ok( false === strpos( $undt_html, 'Vorlage' ), 'Builder-Vorlagen fehlen' );
undt_ok( false === strpos( $undt_html, 'Nicht verlinkbar' ), 'Nicht fuer Menues freigegebene Typen fehlen' );
undt_ok( false !== strpos( $undt_html, 'Datenschutz alt (Entwurf)' ), 'Entwurf traegt seinen Status' );
undt_ok( false === strpos( $undt_html, 'Papierkorb' ), 'Eintraege im Papierkorb fehlen' );
undt_ok( (bool) preg_match( '/name="undt_company\[page_terms\]\[url\]" value=""[^>]*data-undt-link-url hidden/', $undt_html ), 'Adressfeld ist leer und verborgen' );
undt_ok( false !== strpos( $undt_html, 'aria-label="Seite: AGB, eigene Adresse"' ), 'Adressfeld hat eine eigene Beschriftung' );
undt_ok( false !== strpos( $undt_html, 'class="large-text undt-link__url"' ), 'Adressfeld endet an derselben Kante wie die Auswahl' );
undt_ok( false === strpos( $undt_html, 'Bisherige Auswahl' ), 'Gefundener Eintrag erscheint nicht doppelt' );
undt_ok( empty( $GLOBALS['undt_filters']['list_pages'] ), 'Statusfilter haengt nach dem Aufbau nicht mehr' );

$undt_html = undt_render_control( 'page_imprint', '/impressum/' );
undt_ok( false !== strpos( $undt_html, '<option value="url" selected="selected">' ), 'Eigene Adresse ist gewaehlt' );
undt_ok( false === strpos( $undt_html, ' selected="selected">Impressum' ), 'Kein Eintrag ist zusaetzlich gewaehlt' );
undt_ok( (bool) preg_match( '/value="\/impressum\/"[^>]*data-undt-link-url \/>/', $undt_html ), 'Adressfeld zeigt die Adresse und ist sichtbar' );

$undt_html = undt_render_control( 'page_imprint', '0' );
undt_ok( false !== strpos( $undt_html, '<option value="0" selected="selected">' ), 'Ohne Auswahl ist „keine Seite“ gewaehlt' );

$undt_html = undt_render_control( 'page_imprint', '999' );
undt_ok( false !== strpos( $undt_html, '<optgroup label="Bisherige Auswahl"><option value="999" selected="selected">Nicht mehr vorhanden (ID 999)</option></optgroup>' ), 'Geloeschter Eintrag bleibt als solcher sichtbar und gewaehlt' );

undt_ok( 2 === $GLOBALS['undt_get_posts_calls'], 'Eintraege werden je Typ nur einmal geladen (' . $GLOBALS['undt_get_posts_calls'] . ' Abfragen)' );
undt_ok( array( 'publish', 'private', 'draft', 'pending', 'future' ) === $GLOBALS['undt_get_posts_args']['post_status'], 'Auch Unveroeffentlichtes wird geladen' );
undt_ok( ! empty( $GLOBALS['undt_get_posts_args']['no_found_rows'] ), 'Abfrage verzichtet auf die Gesamtzahl' );

/* ------------------------------------- Knoepfe der Wiederholungsfelder --- */

undt_head( 'Wiederholungsfelder: Knoepfe' );

$undt_social = UNDT_Modules::get( 'social' );
ob_start();
UNDT_Fields::control( 'items', $undt_social['fields']['items'], array( array( 'platform' => 'youtube', 'label' => '', 'url' => 'https://example.test/yt' ) ), 'undt_social[items]', 'undt-items' );
$undt_html = (string) ob_get_clean();

// Eine Zeile plus Vorlage: jeder Knopf kommt zweimal vor.
undt_ok( 2 === substr_count( $undt_html, '<button type="button" class="undt-icon-button undt-repeater__move" data-dir="up" aria-label="Nach oben verschieben" title="Nach oben verschieben">' ), 'Pfeil nach oben mit Beschriftung und Tooltip' );
undt_ok( 2 === substr_count( $undt_html, 'class="undt-icon-button undt-repeater__move" data-dir="down"' ), 'Pfeil nach unten' );
undt_ok( 2 === substr_count( $undt_html, 'class="undt-icon-button undt-repeater__remove undt-icon-button--danger" aria-label="Eintrag entfernen"' ), 'Entfernen als eigener, roter Knopf' );
undt_ok( 2 === substr_count( $undt_html, '<span class="undt-repeater__order">' ), 'Pfeile bilden eine Gruppe' );
undt_ok( 6 === substr_count( $undt_html, 'aria-hidden="true" focusable="false"><path d="' ), 'Symbole sind SVG und fuer Screenreader verborgen' );
undt_ok( false === strpos( $undt_html, 'button-link' ) && false === strpos( $undt_html, 'dashicons-trash' ), 'Keine unterstrichenen Textlinks und keine Dashicons mehr' );

/* --------------------------------------------- 4. Tage und Monate ------- */

undt_head( 'Sprache fuer Tage und Monate' );

class UNDT_English_Locale extends WP_Locale {
	private $en = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
	public function get_weekday( $i ) { return $this->en[ (int) $i ]; }
	public function get_weekday_abbrev( $name ) { return substr( $name, 0, 3 ); }
}
$GLOBALS['wp_locale'] = new UNDT_English_Locale();
$GLOBALS['undt_options']['date_format'] = 'd.m.Y';

undt_set_modules( array( 'hours' => 1 ) );
undt_seed_module( 'hours', array( 'days' => array( 'mon' => array( 'closed' => 0, 'slots' => array( array( 'from' => '09:00', 'to' => '17:00' ) ) ) ) ) );

undt_ok( 'de' === UNDT_Hours::language(), 'Deutsch ist voreingestellt' );
undt_ok( 'Montag' === UNDT_Hours::day_label( 'mon' ) && 'Mo' === UNDT_Hours::day_label( 'mon', true ), 'Deutsch trotz englischer Website' );
undt_ok( 'So' === UNDT_Hours::day_label( 'sun', true ) && 'Donnerstag' === UNDT_Hours::day_label( 'thu' ), 'Alle Tage deutsch' );
undt_ok( '' === UNDT_Hours::day_label( 'xyz' ), 'Unbekannter Tag bleibt leer' );
undt_ok( '24. Dezember 2026' === UNDT_Hours::date_label( '2026-12-24' ), 'Datum deutsch ausgeschrieben' );
undt_ok( '1. März 2026' === UNDT_Hours::date_label( '2026-03-01' ), 'Umlaut im Monat, Tag ohne fuehrende Null' );
undt_ok( '' === UNDT_Hours::date_label( '2026-02-30' ) && '' === UNDT_Hours::date_label( array() ), 'Ungueltiges Datum bleibt leer' );

undt_seed_module( 'hours', array( 'day_language' => 'klingonisch' ) );
undt_ok( 'de' === UNDT_Content::value( 'hours', 'day_language' ), 'Unbekannte Sprache faellt auf Deutsch zurueck' );

undt_seed_module(
	'hours',
	array(
		'day_language' => 'site',
		'days'         => array( 'mon' => array( 'closed' => 0, 'slots' => array( array( 'from' => '09:00', 'to' => '17:00' ) ) ) ),
		'special'      => array( array( 'date' => '2026-12-24', 'closed' => 1, 'note' => 'Heiligabend' ) ),
	)
);
undt_ok( 'site' === UNDT_Hours::language(), 'Sprache der Website laesst sich waehlen' );
undt_ok( 'Monday' === UNDT_Hours::day_label( 'mon' ) && 'Mon' === UNDT_Hours::day_label( 'mon', true ), 'Dann kommen die Tage von WordPress' );
undt_ok( '24.12.2026' === UNDT_Hours::date_label( '2026-12-24' ), 'Dann folgt das Datum dem Format der Website' );

$undt_rows = UNDT_Api::query( 'undt_hours_special' );
undt_ok( isset( $undt_rows[0]['date_label'] ) && '24.12.2026' === $undt_rows[0]['date_label'], 'Schleifen-Quelle nutzt dieselbe Beschriftung' );

undt_seed_module(
	'hours',
	array(
		'days'    => array( 'mon' => array( 'closed' => 0, 'slots' => array( array( 'from' => '09:00', 'to' => '17:00' ) ) ) ),
		'special' => array( array( 'date' => '2026-12-24', 'closed' => 1, 'note' => 'Heiligabend' ) ),
	)
);
$undt_html = UNDT_Blocks::hours( array( 'heading_level' => 3, 'short' => '0', 'group' => '0', 'special' => '1', 'note' => '0' ) );
undt_ok( false !== strpos( $undt_html, '<th scope="row"><span class="undt-hours__label">Montag</span></th>' ), 'Tabelle nennt den Tag deutsch' );
undt_ok( false !== strpos( $undt_html, '<time datetime="2026-12-24">24. Dezember 2026</time>' ), 'Sondertermin nennt das Datum deutsch' );
undt_ok( false === strpos( $undt_html, 'Monday' ) && false === strpos( $undt_html, 'December' ), 'Nichts Englisches in der Ausgabe' );

$GLOBALS['wp_locale'] = new WP_Locale();

/* --------------------------------------------- 5. Dynamische Werte ------ */

undt_head( 'Dynamische Werte' );

undt_set_modules( array( 'hours' => 1 ) );
undt_seed(
	array( 'legal_form' => 'sole', 'vat_status' => 'standard' ),
	array(
		'company_name'   => 'Müller & Söhne',
		'owner_name'     => 'Erika Müller',
		'street'         => 'Hauptstraße 5',
		'postal_code'    => '12345',
		'city'           => 'Musterstadt',
		'email'          => 'info@example.test',
		'phone'          => '+49 (30) 123-45',
		'page_imprint'   => '10',
		'page_privacy'   => array( 'choice' => 'url', 'url' => '/datenschutz/' ),
		'website'        => 'https://example.test',
		'share_capital'  => '25.000 EUR',
	)
);

$undt_seo     = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_SEO );
$undt_builder = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER );

undt_ok( isset( $undt_seo['company_name'], $undt_seo['phone'], $undt_seo['email'], $undt_seo['address'], $undt_seo['owner_name'] ), 'SEO-Auswahl enthaelt Firma, Kontakt, Inhaber und Anschrift' );
undt_ok( 'Seite: Impressum (Link)' === $undt_seo['page_imprint'], 'Seitenfelder sind als Link gekennzeichnet' );
undt_ok( ! isset( $undt_seo['share_capital'] ), 'Felder, die beim Profil nicht gelten, fehlen' );
undt_ok( ! isset( $undt_seo['phone_link'] ) && ! isset( $undt_seo['hours_today'] ) && ! isset( $undt_seo['open_now'] ), 'SEO-Auswahl ohne Links und Tageswerte' );
undt_ok( isset( $undt_builder['phone_link'], $undt_builder['email_link'], $undt_builder['fax_link'] ), 'Builder bekommen tel:- und mailto:-Links' );
undt_ok( ! isset( $undt_builder['website_link'] ), 'URL-Felder brauchen keinen eigenen Link-Schluessel' );
undt_ok( isset( $undt_builder['hours_today'], $undt_builder['open_now'] ), 'Builder bekommen die Oeffnungsangaben' );
undt_ok( array_keys( $undt_seo ) === array_values( array_intersect( array_keys( $undt_builder ), array_keys( $undt_seo ) ) ), 'SEO-Auswahl ist eine Teilmenge in gleicher Reihenfolge' );

undt_ok( 'Müller & Söhne' === UNDT_Dynamic::value( 'company_name' ), 'Wert kommt als schlichter Text' );
undt_ok( 'tel:+493012345' === UNDT_Dynamic::value( 'phone_link' ), 'tel:-Link ohne Leer- und Sonderzeichen' );
undt_ok( 'mailto:info@example.test' === UNDT_Dynamic::value( 'email_link' ), 'mailto:-Link' );
undt_ok( '' === UNDT_Dynamic::value( 'fax_link' ), 'Leeres Feld ergibt leeren Link' );
undt_ok( 'Müller & Söhne · Hauptstraße 5 · 12345 Musterstadt' === UNDT_Dynamic::value( 'address' ), 'Anschrift in einer Zeile' );
undt_ok( 'https://example.test/?p=10' === UNDT_Dynamic::value( 'page_imprint' ), 'Seitenfeld liefert die Adresse' );
undt_ok( '/datenschutz/' === UNDT_Dynamic::value( 'page_privacy' ), 'Seitenfeld mit eigener Adresse liefert diese' );
undt_ok( '' === UNDT_Dynamic::value( 'share_capital' ), 'Nicht geltendes Feld bleibt leer' );
undt_ok( '' === UNDT_Dynamic::value( 'gibt_es_nicht' ) && '' === UNDT_Dynamic::value( 'gibt_es_nicht_link' ), 'Unbekannter Schluessel bleibt leer' );
undt_ok( '' === UNDT_Dynamic::value( 'company_name_link' ), 'Link-Endung an einem Textfeld bleibt leer' );

$GLOBALS['undt_now'] = '2026-09-14 10:00:00'; // Montag.
undt_ok( '09:00 – 17:00 Uhr' === UNDT_Dynamic::value( 'hours_today' ), 'Heutige Oeffnungszeit' );
undt_ok( 'Jetzt geöffnet' === UNDT_Dynamic::value( 'open_now' ), 'Geoeffnet-Status' );
$GLOBALS['undt_now'] = '2026-09-15 10:00:00'; // Dienstag, nichts hinterlegt.
undt_ok( 'geschlossen' === UNDT_Dynamic::value( 'hours_today' ) && 'Zurzeit geschlossen' === UNDT_Dynamic::value( 'open_now' ), 'Geschlossener Tag' );
$GLOBALS['undt_now'] = '2026-09-10 10:30:00';

undt_set_modules( array( 'hours' => 0 ) );
undt_ok( ! isset( UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER )['hours_today'] ), 'Abgeschaltete Oeffnungszeiten fehlen in der Auswahl' );
undt_ok( '' === UNDT_Dynamic::value( 'hours_today' ) && '' === UNDT_Dynamic::value( 'open_now' ) && '' === undt_today(), 'und liefern nichts' );
undt_set_modules( array( 'hours' => 1 ) );

undt_seed_module( 'hours', array() );
undt_ok( '' === UNDT_Api::today_text() && '' === UNDT_Dynamic::value( 'open_now' ), 'Ohne hinterlegte Zeiten keine Aussage statt „geschlossen“' );

$undt_values = UNDT_Dynamic::values( UNDT_Dynamic::CONTEXT_SEO );
undt_ok( array_keys( $undt_values ) === array_keys( $undt_seo ), 'Alle angebotenen Schluessel haben einen Wert' );
undt_ok( array_key_exists( 'fax', $undt_values ) && '' === $undt_values['fax'], 'Auch leere Felder, damit kein Platzhalter stehen bleibt' );

/* ------------------------------------------------------ 6. Slim SEO ----- */

undt_head( 'Slim SEO' );

$undt_vars = UNDT_Dynamic::slim_seo_variables( array( array( 'label' => 'Post', 'options' => array( 'post.title' => 'Titel' ) ) ) );
$undt_last = end( $undt_vars );
undt_ok( 2 === count( $undt_vars ) && 'Post' === $undt_vars[0]['label'], 'Bestehende Gruppen bleiben' );
undt_ok( 'Unternehmensdaten' === $undt_last['label'], 'Eigene Gruppe „Unternehmensdaten“' );
undt_ok( isset( $undt_last['options']['undt.phone'] ) && 'Telefon' === $undt_last['options']['undt.phone'], 'Schluessel in der Form undt.phone' );
undt_ok( count( $undt_last['options'] ) === count( $undt_seo ), 'Genau die SEO-Auswahl' );
undt_ok( 'kaputt' === UNDT_Dynamic::slim_seo_variables( 'kaputt' ), 'Fremder Typ wird durchgereicht' );

$undt_data = UNDT_Dynamic::slim_seo_data( array( 'post' => array( 'title' => 'X' ) ), 5, 0 );
undt_ok( 'X' === $undt_data['post']['title'] && 'Müller & Söhne' === $undt_data['undt']['company_name'], 'Werte stehen unter undt bereit' );
undt_ok( null === UNDT_Dynamic::slim_seo_data( null ), 'Fremder Typ wird durchgereicht' );

/* ---------------------------------------- 6b. Slim SEO: Schema ---------- */

undt_head( 'Slim SEO Pro: Schema-Einstellungen' );

undt_set_modules( array( 'hours' => 1, 'social' => 1, 'seo' => 1 ) );
undt_seed_module(
	'social',
	array(
		'items' => array(
			array( 'platform' => 'linkedin', 'url' => 'https://linkedin.test/firma' ),
			array( 'platform' => 'instagram', 'url' => 'https://instagram.test/firma' ),
			array( 'platform' => 'facebook', 'url' => '' ),
		),
	)
);
undt_seed_module( 'seo', array( 'logo' => 7 ) );

$undt_schema = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_SCHEMA );
undt_ok( isset( $undt_schema['company_name'] ) && isset( $undt_schema['street'] ), 'Die Stammdaten stehen auch im Schema zur Verfuegung' );
undt_ok( isset( $undt_schema['social_profiles'] ) && isset( $undt_schema['logo_url'] ), 'Dazu die Social-Profile und das Logo' );
undt_ok( ! isset( $undt_schema['banner_text'] ) && ! isset( $undt_schema['open_now'] ), 'Banner und Oeffnungsangaben bleiben den Buildern vorbehalten' );
undt_ok( ! isset( $undt_seo['social_profiles'] ), 'In den Meta-Angaben gibt es die Profilliste nicht, dort waere sie eine Aufzaehlung' );

$undt_vars = UNDT_Dynamic::slim_seo_schema_variables( array( array( 'label' => 'Post', 'options' => array( 'post.title' => 'Titel' ) ) ) );
$undt_last = end( $undt_vars );
undt_ok( 2 === count( $undt_vars ) && 'Unternehmensdaten' === $undt_last['label'], 'Eigene Gruppe neben den Gruppen von Slim SEO' );
undt_ok( isset( $undt_last['options']['undt.social_profiles'] ), 'Die Profile stehen als undt.social_profiles in der Auswahl' );
undt_ok( 'kaputt' === UNDT_Dynamic::slim_seo_schema_variables( 'kaputt' ), 'Fremder Typ wird durchgereicht' );

$undt_data = UNDT_Dynamic::slim_seo_schema_data( array( 'post' => array( 'title' => 'X' ) ) );
undt_ok( 'X' === $undt_data['post']['title'] && 'Müller & Söhne' === $undt_data['undt']['company_name'], 'Werte stehen unter undt bereit' );
undt_ok( array( 'https://linkedin.test/firma', 'https://instagram.test/firma' ) === $undt_data['undt']['social_profiles'], 'Die Profile kommen als Liste, leere Zeilen fallen weg' );
undt_ok( 'https://example.test/logo.png' === $undt_data['undt']['logo_url'], 'Das Logo kommt als Adresse' );
undt_ok( null === UNDT_Dynamic::slim_seo_schema_data( null ), 'Fremder Typ wird durchgereicht' );

undt_ok( 'https://linkedin.test/firma, https://instagram.test/firma' === UNDT_Dynamic::value( 'social_profiles' ), 'Als Text eine Aufzaehlung, etwa in der Referenz' );

undt_set_modules( array( 'social' => 0, 'seo' => 0 ) );
$undt_schema = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_SCHEMA );
undt_ok( ! isset( $undt_schema['social_profiles'] ) && ! isset( $undt_schema['logo_url'] ), 'Abgeschaltete Bereiche bieten nichts an' );
undt_ok( array() === UNDT_Dynamic::list_value( 'social_profiles' ) && '' === UNDT_Dynamic::value( 'logo_url' ), 'Und liefern auch nichts' );

undt_set_modules( array( 'hours' => 1, 'social' => 1, 'seo' => 1 ) );

/* -------------------------------------------------------- 7. Bricks ----- */

undt_head( 'Bricks' );

$undt_tags = UNDT_Dynamic::bricks_tags( array( array( 'name' => '{post_title}', 'label' => 'Titel', 'group' => 'Post' ) ) );
$undt_names = wp_list_pluck( $undt_tags, 'name' );
undt_ok( '{post_title}' === $undt_names[0], 'Bestehende Tags bleiben' );
undt_ok( in_array( '{undt_phone}', $undt_names, true ) && in_array( '{undt_phone_link}', $undt_names, true ), 'Tags in der Form {undt_phone}' );
undt_ok( 'Unternehmensdaten' === $undt_tags[1]['group'], 'Eigene Gruppe' );
undt_ok( 'kaputt' === UNDT_Dynamic::bricks_tags( 'kaputt' ), 'Fremder Typ wird durchgereicht' );

undt_ok( 'Müller &amp; Söhne' === UNDT_Dynamic::bricks_render_tag( 'undt_company_name', null, 'text' ), 'Tag ohne Klammern wird escaped aufgeloest' );
undt_ok( 'Müller &amp; Söhne' === UNDT_Dynamic::bricks_render_tag( '{undt_company_name}', null, 'text' ), 'Tag mit Klammern ebenso' );
undt_ok( 'post_title' === UNDT_Dynamic::bricks_render_tag( 'post_title', null, 'text' ), 'Fremder Tag bleibt unberuehrt' );
undt_ok( 'undt_gibt_es_nicht' === UNDT_Dynamic::bricks_render_tag( 'undt_gibt_es_nicht', null, 'text' ), 'Unbekannter eigener Tag bleibt unberuehrt' );
undt_ok( 'undt_share_capital' === UNDT_Dynamic::bricks_render_tag( 'undt_share_capital', null, 'text' ), 'Nicht geltender Tag bleibt unberuehrt' );
undt_ok( 'tel:+493012345' === UNDT_Dynamic::bricks_render_tag( 'undt_phone_link', null, 'link' ), 'Link-Kontext liefert die rohe Adresse' );
undt_ok( 'https://example.test/?p=10' === UNDT_Dynamic::bricks_render_tag( 'undt_page_imprint', null, 'link' ), 'Seitenfeld im Link-Kontext' );
undt_ok( '+49 (30) 123-45' === UNDT_Dynamic::bricks_render_tag( 'undt_phone', null, 'link' ), 'Textwert im Link-Kontext bleibt Text' );
undt_ok( array( 'x' ) === UNDT_Dynamic::bricks_render_tag( array( 'x' ) ), 'Fremder Typ wird durchgereicht' );

undt_ok(
	'Tel. +49 (30) 123-45, {post_title}, {undt_gibt_es_nicht}, Müller &amp; Söhne' === UNDT_Dynamic::bricks_render_content( 'Tel. {undt_phone}, {post_title}, {undt_gibt_es_nicht}, {undt_company_name}', null, 'text' ),
	'Im Fliesstext werden nur die eigenen Tags ersetzt'
);
undt_ok( 'ohne Tags' === UNDT_Dynamic::bricks_render_content( 'ohne Tags' ), 'Text ohne Tags bleibt gleich' );
undt_ok( '<a href="tel:+493012345">' === UNDT_Dynamic::bricks_render_content( '<a href="{undt_phone_link}">', null, 'link' ), 'Link im Fliesstext' );
undt_ok( 42 === UNDT_Dynamic::bricks_render_content( 42 ), 'Fremder Typ wird durchgereicht' );

// Bricks reicht fremde Tags samt Klammern und Filtern weiter.
undt_ok( 'Müller…' === UNDT_Dynamic::bricks_render_tag( '{undt_company_name:1}', null, 'text' ), 'Zahl begrenzt die Woerter' );
undt_ok( 'Müller &amp; Söhne…' === UNDT_Dynamic::bricks_render_tag( '{undt_address:3}', null, 'text' ), 'Wortgrenze vor dem Escaping' );
undt_ok( 'keine Faxnummer' === UNDT_Dynamic::bricks_render_tag( "{undt_fax @fallback:'keine Faxnummer'}", null, 'text' ), '@fallback ersetzt einen leeren Wert' );
undt_ok( 'keins' === UNDT_Dynamic::bricks_render_tag( '{undt_fax @fallback:keins}', null, 'text' ), '@fallback auch ohne Anfuehrungszeichen' );
undt_ok( '+49 (30) 123-45' === UNDT_Dynamic::bricks_render_tag( "{undt_phone @fallback:'x'}", null, 'text' ), '@fallback greift nur bei leerem Wert' );
undt_ok( 'tel:+493012345' === UNDT_Dynamic::bricks_render_tag( '{undt_phone_link:1}', null, 'link' ), 'Wortgrenze gilt nicht fuer Adressen' );
undt_ok( '{undt_gibt_es_nicht:3}' === UNDT_Dynamic::bricks_render_tag( '{undt_gibt_es_nicht:3}', null, 'text' ), 'Unbekannter Tag mit Filter bleibt unberuehrt' );
undt_ok( '{post_title:3}' === UNDT_Dynamic::bricks_render_tag( '{post_title:3}', null, 'text' ), 'Fremder Tag mit Filter bleibt unberuehrt' );
undt_ok(
	"A – B Müller… C {undt_nix @fallback:'y'}" === UNDT_Dynamic::bricks_render_content( "A {undt_fax @fallback:'–'} B {undt_company_name:1} C {undt_nix @fallback:'y'}", null, 'text' ),
	'Filter wirken auch im Fliesstext'
);

/* ---------------------------------------------------------- 8. Etch ----- */

undt_head( 'Etch' );

$undt_etch = UNDT_Dynamic::etch_options( array( 'andere' => 1 ) );
undt_ok( 1 === $undt_etch['andere'] && 'Müller & Söhne' === $undt_etch['undt']['company_name'], 'Werte stehen unter options.undt bereit' );
undt_ok( 'tel:+493012345' === $undt_etch['undt']['phone_link'], 'Mit den Builder-Links' );
undt_ok( false === UNDT_Dynamic::etch_options( false ), 'Fremder Typ wird durchgereicht' );

/* ------------------------------------------ 9. Banner und Page Builder --- */

undt_head( 'Automatisches Banner in Page Buildern' );

function bricks_is_builder() { return ! empty( $GLOBALS['undt_bricks_builder'] ); }

function undt_auto_banner_html() {
	UNDT_Blocks::reset();
	ob_start();
	UNDT_Shortcodes::auto_banner();
	return (string) ob_get_clean();
}

undt_set_modules( array( 'banner' => 1 ) );
undt_seed_module( 'banner', array( 'enabled' => 1, 'type' => 'info', 'text' => 'Betriebsferien', 'auto_output' => 1 ) );

undt_ok( false !== strpos( undt_auto_banner_html(), 'Betriebsferien' ), 'Auf der Website erscheint das Banner' );

$_GET['etch'] = 'magic';
undt_ok( '' === undt_auto_banner_html(), 'In der Oberflaeche von Etch nicht' );
$_GET['etch'] = array( 'magic' );
undt_ok( false !== strpos( undt_auto_banner_html(), 'Betriebsferien' ), 'Ein manipulierter Parameter schaltet es nicht ab' );
unset( $_GET['etch'] );

$GLOBALS['undt_bricks_builder'] = true;
undt_ok( '' === undt_auto_banner_html(), 'In der Oberflaeche von Bricks nicht' );
$GLOBALS['undt_bricks_builder'] = false;

add_filter( 'undt_auto_banner', '__return_false' );
undt_ok( '' === undt_auto_banner_html(), 'Filter schaltet die automatische Ausgabe ab' );
remove_all_filters( 'undt_auto_banner' );

$_GET['etch'] = 'magic';
add_filter( 'undt_auto_banner', '__return_true' );
undt_ok( false !== strpos( undt_auto_banner_html(), 'Betriebsferien' ), 'und auf Wunsch auch im Builder ein' );
remove_all_filters( 'undt_auto_banner' );

UNDT_Blocks::reset();
undt_ok( false !== strpos( UNDT_Shortcodes::banner(), 'Betriebsferien' ), 'Ein Shortcode im Inhalt gibt das Banner auch im Builder aus' );
unset( $_GET['etch'] );

/* ------------------------------------ 10. Kopierknoepfe unter den Feldern --- */

undt_head( 'Kopierknoepfe unter den Stammdaten-Feldern' );

undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'standard' ), array( 'phone' => '+49 30 1' ) );

$undt_schema = UNDT_Schema::fields();
$undt_fields = array_intersect_key( $undt_schema, array_flip( array( 'postal_code', 'city', 'phone', 'share_capital' ) ) );

ob_start();
UNDT_Fields::rows( $undt_fields, UNDT_Store::company(), 'undt_company', array( 'with_copy' => true ) );
$undt_html = (string) ob_get_clean();

undt_ok( 4 === substr_count( $undt_html, '<div class="undt-copy-row">' ), 'Jedes Feld mit Shortcode bekommt eine Kopierzeile, auch im Feldpaar' );
undt_ok( false !== strpos( $undt_html, 'data-undt-copy="[undt key=&quot;phone&quot;]"' ), 'Der Shortcode bleibt' );
undt_ok( false !== strpos( $undt_html, '<button type="button" class="undt-copy undt-copy--icon undt-copy--bricks" data-undt-copy="{undt_phone}" title="Bricks: {undt_phone} kopieren"><span class="undt-copy__badge" aria-hidden="true"><svg class="undt-copy__icon" viewBox="0 0 79 101"' ), 'Bricks-Logo im Rahmen kopiert den Bricks-Tag und nennt ihn im Tooltip' );
undt_ok( false !== strpos( $undt_html, '</svg></span><span class="screen-reader-text">Bricks: {undt_phone} kopieren</span></button>' ), 'Screenreader hoeren, was kopiert wird' );
undt_ok( false !== strpos( $undt_html, 'data-undt-copy="{options.undt.phone}" title="Etch: {options.undt.phone} kopieren"><span class="undt-copy__badge" aria-hidden="true"><svg class="undt-copy__icon" viewBox="0 0 110 87"' ), 'Etch-Logo im Rahmen kopiert den Etch-Ausdruck' );
undt_ok( false === strpos( $undt_html, 'aria-hidden="true">B</span>' ) && false === strpos( $undt_html, 'aria-hidden="true">E</span>' ), 'Keine Buchstaben mehr' );
undt_ok( false !== strpos( $undt_html, 'data-undt-copy="{undt_postal_code}"' ) && false !== strpos( $undt_html, 'data-undt-copy="{options.undt.city}"' ), 'Auch die Felder eines Paares' );
undt_ok( 6 === substr_count( $undt_html, 'undt-copy--icon' ), 'Zwei Kuerzel je Feld, das nicht geltende ausgenommen' );
undt_ok( false === strpos( $undt_html, '{undt_share_capital}' ) && false !== strpos( $undt_html, 'data-undt-copy="[undt key=&quot;share_capital&quot;]"' ), 'Ein Feld, das die Builder nicht aufloesen, behaelt nur den Shortcode' );
undt_ok( false === strpos( $undt_html, '{undt_phone_link}' ), 'Die Link-Varianten stehen nur in der Referenz' );

ob_start();
UNDT_Fields::rows( $undt_fields, UNDT_Store::company(), 'undt_company', array( 'with_copy' => false ) );
$undt_html = (string) ob_get_clean();
undt_ok( false === strpos( $undt_html, 'undt-copy' ), 'Ohne with_copy keine Kopierknoepfe, etwa in den Inhaltsbereichen' );

$undt_syntax = UNDT_Dynamic::syntax( 'phone' );
undt_ok( array( 'slim_seo' => '{{ undt.phone }}', 'bricks' => '{undt_phone}', 'etch' => '{options.undt.phone}' ) === $undt_syntax, 'Schreibweisen an einer Stelle' );

/* ------------------------------------------------- 11. Registrierung ---- */

undt_head( 'Registrierung' );

$GLOBALS['undt_filters'] = array();
UNDT_Dynamic::register();
foreach ( array( 'slim_seo_variables', 'slim_seo_data', 'slim_seo_schema_variables', 'slim_seo_schema_data', 'bricks/dynamic_tags_list', 'bricks/dynamic_data/render_tag', 'bricks/dynamic_data/render_content', 'bricks/frontend/render_data', 'etch/dynamic_data/option' ) as $undt_hook ) {
	undt_ok( ! empty( $GLOBALS['undt_filters'][ $undt_hook ] ), 'Haken ' . $undt_hook );
}

echo empty( $GLOBALS['undt_fails'] ) ? "\n\033[32mAlle Pruefungen bestanden\033[0m\n" : "\n\033[31m" . $GLOBALS['undt_fails'] . " Pruefungen fehlgeschlagen\033[0m\n";
exit( empty( $GLOBALS['undt_fails'] ) ? 0 : 1 );
