<?php
/**
 * Pruefungen zu den Befunden aus dem Audit vom 14.09.2026 (dev/audit-report.md).
 *
 * Jede Pruefung formuliert das ERWARTETE Verhalten. Gegen 0.4.0 zeigte ein FAIL
 * den jeweiligen Befund, seit 0.4.1 sichern die Pruefungen die Behebung ab. Die
 * Nummern verweisen auf den Bericht. Was sich nur in WordPress selbst zeigt
 * (F-06 in der echten Seitenauswahl, F-07, F-12, F-13), pruefen dev/verify.php
 * und dev/audit/env.php.
 */
require __DIR__ . '/harness.php';
require dirname( __DIR__, 2 ) . '/unternehmensdaten/admin/class-undt-fields.php';

/*
 * Attrappen fuer die Seitenauswahl. Sie stehen hier und nicht im Harness, weil
 * nur diese Datei die Admin-Klasse laedt.
 */
class WP_Post {
	public $ID          = 0;
	public $post_status = 'publish';
}

function get_post_status_object( $status ) {
	$labels = array( 'draft' => 'Entwurf', 'private' => 'Privat' );

	return isset( $labels[ $status ] ) ? (object) array( 'label' => $labels[ $status ] ) : null;
}

/* ------------------------------------------- Voreinstellungen leeren --- */

undt_head( 'F-05: Geleerte Felder bleiben leer' );

undt_seed_module( 'prices', array( 'items' => array( array( 'group' => '', 'label' => 'Beratung', 'price' => '100 €', 'note' => '' ) ), 'intro' => '', 'footnote' => '' ) );
undt_ok( '' === UNDT_Content::value( 'prices', 'footnote' ), 'Geleerte Fussnote bleibt leer' );
$html = UNDT_Blocks::prices( array( 'heading_level' => 3, 'group' => '', 'intro' => '1', 'footnote' => '1' ) );
undt_ok( false === strpos( $html, 'Umsatzsteuer' ), 'Preisliste zeigt keine Umsatzsteuer-Fussnote, wenn sie geleert wurde' );

undt_seed_module( 'prices', array( 'items' => array( array( 'group' => '', 'label' => 'Beratung', 'price' => '100 €', 'note' => '' ) ) ) );
undt_ok( false !== strpos( (string) UNDT_Content::value( 'prices', 'footnote' ), 'Umsatzsteuer' ), 'Nie gespeicherte Fussnote erhaelt weiter die Voreinstellung' );

undt_seed_module( 'hours', array( 'days' => array( 'mon' => array( 'closed' => 0, 'slots' => array( array( 'from' => '09:00', 'to' => '17:00' ) ) ) ), 'suffix' => '', 'closed_label' => '' ) );
undt_ok( '' === trim( (string) UNDT_Content::value( 'hours', 'suffix' ) ), 'Geleerter Zusatz hinter der Uhrzeit bleibt leer' );
undt_ok( false === strpos( UNDT_Api::format_slots( array( array( 'from' => '09:00', 'to' => '17:00' ) ) ), 'Uhr' ), 'format_slots ohne Zusatz, wenn er geleert wurde' );

undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'small_business' ), array( 'company_name' => 'Test', 'small_business_note' => '' ) );
undt_ok( '' === UNDT_Store::get( 'small_business_note' ), 'Geleerter Kleinunternehmer-Hinweis bleibt leer' );

undt_seed( array( 'legal_form' => 'sole', 'vat_status' => 'small_business' ), array( 'company_name' => 'Test' ) );
undt_ok( false !== strpos( UNDT_Store::get( 'small_business_note' ), '§ 19 UStG' ), 'Nie gespeicherter Kleinunternehmer-Hinweis erhaelt weiter die Voreinstellung' );

/* ------------------------------------------------- Mitternacht ---------- */

undt_head( 'F-09: Zeitfenster ueber Mitternacht' );

undt_seed_module( 'hours', array( 'days' => array( 'fri' => array( 'closed' => 0, 'slots' => array( array( 'from' => '22:00', 'to' => '02:00' ) ) ) ) ) );
$GLOBALS['undt_now'] = '2026-09-11 23:30:00'; // Freitag.
undt_ok( true === UNDT_Hours::is_open_now(), 'Freitag 23:30 gilt als geoeffnet' );
$GLOBALS['undt_now'] = '2026-09-12 01:00:00'; // Samstag frueh.
undt_ok( true === UNDT_Hours::is_open_now(), 'Samstag 01:00 gilt noch als geoeffnet, das Freitagsfenster laeuft bis 02:00' );
$GLOBALS['undt_now'] = '2026-09-12 02:00:00';
undt_ok( false === UNDT_Hours::is_open_now(), 'Samstag 02:00 ist das Freitagsfenster vorbei' );
$GLOBALS['undt_now'] = '2026-09-11 01:00:00'; // Freitag frueh, Donnerstag hat kein Fenster.
undt_ok( false === UNDT_Hours::is_open_now(), 'Freitag 01:00 gilt nicht als geoeffnet, das Fenster beginnt erst am Abend' );

undt_seed_module( 'hours', array( 'days' => array( 'fri' => array( 'closed' => 0, 'slots' => array( array( 'from' => '09:00', 'to' => '09:00' ) ) ) ) ) );
$GLOBALS['undt_now'] = '2026-09-11 15:00:00';
undt_ok( false === UNDT_Hours::is_open_now(), 'Fenster 09:00 - 09:00 (Tippfehler) gilt nicht als rund um die Uhr geoeffnet' );
undt_ok( array() === UNDT_Hours::slots( 'fri' ), 'Ein Fenster mit gleichen Uhrzeiten wird verworfen' );

undt_seed_module( 'hours', array( 'days' => array( 'sat' => array( 'closed' => 0, 'slots' => array( array( 'from' => '00:00', 'to' => '00:00' ) ) ) ) ) );
$sat = UNDT_Hours::slots( 'sat' );
undt_ok( isset( $sat[0] ) && '23:59' === $sat[0]['to'], '00:00 bis 00:00 wird zu 00:00 bis 23:59, rund um die Uhr' );
$GLOBALS['undt_now'] = '2026-09-12 23:59:30';
undt_ok( true === UNDT_Hours::is_open_now(), 'Rund um die Uhr gilt auch in der letzten Minute des Tages' );

undt_seed_module( 'hours', array( 'special' => array( array( 'date' => '2026-12-31', 'closed' => 0, 'from' => '20:00', 'to' => '03:00', 'note' => 'Silvester' ) ) ) );
$GLOBALS['undt_now'] = '2027-01-01 01:00:00';
undt_ok( true === UNDT_Hours::is_open_now(), 'Ein Sondertermin ueber Mitternacht gilt am Folgetag bis zu seinem Ende' );
$GLOBALS['undt_now'] = '2026-09-10 10:30:00';

/* ------------------------------------------- Sondertermine ------------- */

undt_head( 'H-03: Sondertermine ohne Uhrzeiten' );

$clean = UNDT_Content::sanitize( 'hours', array( 'special' => array( array( 'date' => '2026-10-03', 'closed' => 0, 'from' => '', 'to' => '', 'note' => 'Feiertag' ) ) ) );
undt_ok( 1 === $clean['special'][0]['closed'], 'Ein Termin ohne Uhrzeiten wird beim Speichern als geschlossen markiert' );
$clean = UNDT_Content::sanitize( 'hours', array( 'special' => array( array( 'date' => '2026-10-04', 'closed' => 0, 'from' => '10:00', 'to' => '14:00', 'note' => '' ) ) ) );
undt_ok( 0 === $clean['special'][0]['closed'], 'Mit Uhrzeiten bleibt der Schalter aus' );

/* ------------------------------------------- Arrays statt Strings ------ */

undt_head( 'F-10: Arrays statt Strings in der Eingabe' );

$warnings = array();
set_error_handler(
	function ( $no, $str ) use ( &$warnings ) {
		$warnings[] = $str;
		return true;
	}
);
$clean   = UNDT_Content::sanitize( 'prices', array( 'intro' => array( 'x' ), 'footnote' => array( 'y' => 'z' ), 'items' => array( array( 'label' => array( 'a' ), 'price' => '1' ) ) ) );
$profile = UNDT_Sanitizer::profile( array( 'legal_form' => array( 'gmbh' ) ) );
$hours   = UNDT_Content::sanitize( 'hours', array( 'days' => array( 'mon' => array( 'slots' => array( array( 'from' => array( '09:00' ), 'to' => '10:00' ) ) ) ), 'special' => array( array( 'date' => array( '2026-01-01' ), 'from' => array(), 'to' => '' ) ) ) );
restore_error_handler();
undt_ok( empty( $warnings ), 'Keine PHP-Warnung bei Array-Eingaben' . ( $warnings ? ' (' . count( $warnings ) . ' x "' . $warnings[0] . '")' : '' ) );
undt_ok( 'Array' !== $clean['intro'] && 'Array' !== $clean['items'][0]['label'], 'Der Text "Array" wird nicht gespeichert' );
undt_ok( 'sole' === $profile['legal_form'], 'Profil faellt bei Array-Eingabe auf die Voreinstellung zurueck' );
undt_ok( '' === $hours['days']['mon']['slots'][0]['from'], 'Eine Uhrzeit als Array wird verworfen' );

/* ------------------------------------------- Entwurfsseiten ------------ */

undt_head( 'F-08: Einzelfeld-Shortcode und unveroeffentlichte Seiten' );

$GLOBALS['undt_posts'][77] = array( 'status' => 'draft', 'title' => 'Datenschutz (Entwurf)' );
$GLOBALS['undt_posts'][78] = array( 'status' => 'publish', 'title' => 'Datenschutz' );

undt_seed( array( 'legal_form' => 'sole' ), array( 'company_name' => 'Test', 'page_privacy' => 77 ) );
$out = UNDT_Shortcodes::field( array( 'key' => 'page_privacy', 'link' => '1' ) );
undt_ok( '' === $out, '[undt key="page_privacy" link="1"] gibt fuer einen Entwurf nichts aus' . ( '' === $out ? '' : ' (' . $out . ')' ) );
undt_ok( 'folgt' === UNDT_Shortcodes::field( array( 'key' => 'page_privacy', 'link' => '1', 'fallback' => 'folgt' ) ), 'Fuer einen Entwurf greift der Ersatztext' );
undt_ok( '' === UNDT_Render::legal_nav(), 'Zum Vergleich: legal_nav verlinkt den Entwurf ebenfalls nicht' );

undt_seed( array( 'legal_form' => 'sole' ), array( 'company_name' => 'Test', 'page_privacy' => 78 ) );
undt_ok( false !== strpos( UNDT_Shortcodes::field( array( 'key' => 'page_privacy', 'link' => '1' ) ), 'href="https://example.test/?p=78"' ), 'Eine veroeffentlichte Seite wird weiter verlinkt' );

undt_head( 'F-06: Seitenauswahl kennzeichnet unveroeffentlichte Seiten' );

$page              = new WP_Post();
$page->post_status = 'draft';
undt_ok( 'Datenschutz (Entwurf)' === UNDT_Fields::page_status_label( 'Datenschutz', $page ), 'Ein Entwurf traegt seinen Status im Namen' );
$page->post_status = 'publish';
undt_ok( 'Datenschutz' === UNDT_Fields::page_status_label( 'Datenschutz', $page ), 'Eine veroeffentlichte Seite bleibt unveraendert' );
undt_ok( 'Datenschutz' === UNDT_Fields::page_status_label( 'Datenschutz', null ), 'Ohne Seitenobjekt bleibt der Titel unveraendert' );

/* ------------------------------------------- SEO-Erkennung ------------- */

undt_head( 'F-03: Erkennung von Slim SEO' );

// So heisst die Konstante in Slim SEO 4.10.1 (slim-seo.php, Zeile 35).
define( 'SLIM_SEO_VER', '4.10.1' );
undt_ok( 'Slim SEO' === UNDT_SchemaOrg::detect_seo_plugin(), 'Slim SEO wird ueber seine tatsaechliche Konstante SLIM_SEO_VER erkannt' );

/* ------------------------------------------- Bricks -------------------- */

undt_head( 'F-04 und H-05: Bricks' );

$names = UNDT_Api::bricks_echo_functions( array( 'eigene_funktion' ) );
undt_ok( in_array( 'undt_get', $names, true ) && in_array( 'eigene_funktion', $names, true ), 'Die Funktionen landen in der Freigabeliste, bestehende Eintraege bleiben' );
undt_ok( ! in_array( 'undt_query', $names, true ), 'undt_query liefert ein Array und bleibt draussen' );
undt_ok( true === UNDT_Api::bricks_echo_functions( false, 'undt_today' ), 'Prueft Bricks einen einzelnen Namen, wird die eigene Funktion erlaubt' );
undt_ok( false === UNDT_Api::bricks_echo_functions( false, 'system' ), 'Fremde Funktionen gibt das Plugin nicht frei' );
undt_ok( true === UNDT_Api::bricks_echo_functions( 'undt_has' ), 'Auch wenn der Name als erstes Argument ankommt' );

undt_seed_module( 'faq', array( 'items' => array( array( 'group' => '', 'question' => 'A', 'answer' => 'B' ), array( 'group' => '', 'question' => 'C', 'answer' => 'D' ) ) ) );
$query = (object) array( 'object_type' => 'undt_faq', 'settings' => array( 'query' => array( 'posts_per_page' => 1 ) ) );
undt_ok( 1 === count( UNDT_Api::bricks_run( array(), $query ) ), 'Die Begrenzung kommt auch aus den Einstellungen des Elements an' );
$query->query_vars = array( 'posts_per_page' => 2 );
undt_ok( 2 === count( UNDT_Api::bricks_run( array(), $query ) ), 'query_vars hat Vorrang vor den Einstellungen' );

/* ------------------------------------------- Vorabversionen ------------ */

undt_head( 'F-02: Vorabversionen im Updater' );

undt_set_http(
	200,
	array(
		'version'      => '9.9.9-beta.1',
		'download_url' => 'https://github.com/Seitzdominik/unternehmensdaten/releases/download/v9.9.9-beta.1/unternehmensdaten.zip',
	)
);
$transient = UNDT_Updater::inject( (object) array( 'response' => array(), 'no_update' => array() ) );
undt_ok( ! isset( $transient->response['unternehmensdaten/unternehmensdaten.php'] ), 'Eine Vorabversion (9.9.9-beta.1) wird nicht als Aktualisierung angeboten' );

/* ------------------------------------------- Workflow ------------------ */

undt_head( 'F-02 und F-14: Release-Workflow' );

$workflows = dirname( __DIR__, 2 ) . '/unternehmensdaten/.github/workflows/';
$yml       = (string) file_get_contents( $workflows . 'release.yml' );
$tests_yml = (string) file_get_contents( $workflows . 'tests.yml' );

undt_ok( false !== strpos( $yml, 'prerelease: ${{ steps.version.outputs.prerelease }}' ), 'Ein Tag mit Bindestrich wird als Vorabversion veroeffentlicht' );
undt_ok( false !== strpos( $yml, "--exclude '.gitattributes'" ), '.gitattributes gelangt nicht ins Archiv' );

// Dieselben Regeln fuer jeden Workflow, auch fuer spaeter hinzugekommene.
foreach ( array( 'release.yml' => $yml, 'tests.yml' => $tests_yml ) as $name => $inhalt ) {
	undt_ok( '' !== $inhalt, $name . ' ist vorhanden' );
	undt_ok( ! preg_match( '/uses:\s*\S+@(?![0-9a-f]{40}(\s|$))/m', $inhalt ), $name . ': alle Actions sind an Commit-Hashes gebunden' );

	$unsafe = array();

	foreach ( preg_split( '/\R/', $inhalt ) as $line ) {
		// Ausdruecke duerfen nur als YAML-Wert stehen, nie im Shell-Code eines run-Blocks.
		if ( false !== strpos( $line, '${{' ) && ! preg_match( '/^\s*[A-Za-z_-]+:\s/', $line ) ) {
			$unsafe[] = trim( $line );
		}
	}

	undt_ok( empty( $unsafe ), $name . ': kein ${{ }}-Ausdruck im Shell-Code' . ( $unsafe ? ' -> ' . implode( ' | ', $unsafe ) : '' ) );
}

// Die Pruefungen sollen nicht nur nebenher laufen, sondern ein Release verhindern.
undt_ok( false !== strpos( $yml, 'dev/tests/test-*.php' ), 'Der Release-Workflow arbeitet die Offline-Pruefungen ab' );
undt_ok( false !== strpos( $tests_yml, 'dev/tests/test-*.php' ) && false !== strpos( $tests_yml, "php: ['7.4', '8.3', '8.5']" ), 'Der Pruef-Workflow laeuft ueber drei PHP-Versionen' );
undt_ok( false !== strpos( $tests_yml, "exclude-checks: 'plugin_updater,plugin_readme'" ), 'Der Plugin Check laesst genau die zwei bewusst offenen Befunde aus' );

/* ------------------------------------------- Banner und Kontrast ------- */

undt_head( 'F-15 und F-16: Infobanner und Kontrast' );

undt_seed_module( 'banner', array( 'enabled' => 1, 'text' => 'Fokus.', 'dismissible' => 1 ) );
$banner = UNDT_Blocks::banner();
undt_ok( false !== strpos( $banner, 'compareDocumentPosition' ) && false !== strpos( $banner, '.focus()' ), 'Beim Schliessen wandert der Fokus auf das naechste bedienbare Element' );
undt_ok( false === strpos( UNDT_Blocks::css(), 'opacity' ), 'Das CSS der Bloecke kommt ohne opacity aus' );

/* ------------------------------------------- Semantik ------------------ */

undt_head( 'H-02: address-Elemente und neue Tabs' );

undt_seed_module( 'social', array( 'items' => array( array( 'platform' => 'instagram', 'label' => '', 'url' => 'https://example.test/i' ) ), 'new_tab' => 1 ) );
$social = UNDT_Blocks::social( array( 'label' => '' ) );
// Seit 0.5.2 ueber den Namen des Links statt ueber einen versteckten Zusatztext.
undt_ok( false !== strpos( $social, 'target="_blank"' ) && false !== strpos( $social, 'aria-label="Instagram (öffnet in neuem Tab)"' ), 'Ein Link in neuem Tab kuendigt das fuer Screenreader an' );

undt_seed_module( 'social', array( 'items' => array( array( 'platform' => 'instagram', 'label' => '', 'url' => 'https://example.test/i' ) ), 'new_tab' => 0 ) );
undt_ok( false === strpos( UNDT_Blocks::social( array( 'label' => '' ) ), 'neuem Tab' ), 'Ohne neuen Tab entfaellt der Hinweis' );

undt_seed(
	array( 'legal_form' => 'sole', 'needs_authority' => 1, 'sells_to_consumers' => 1, 'vsbg_participation' => 'voluntary' ),
	array( 'company_name' => 'Test', 'street' => 'Weg 1', 'postal_code' => '12345', 'city' => 'Stadt', 'authority_name' => 'Gewerbeamt Stadt', 'vsbg_authority' => 'Universalschlichtungsstelle' )
);
$imprint = UNDT_Render::imprint( array( 'heading_level' => 2 ) );
undt_ok( 1 === substr_count( $imprint, '<address' ), 'Nur die Anschrift des Anbieters steht in einem address-Element' );
undt_ok( false !== strpos( $imprint, 'Gewerbeamt Stadt' ) && false !== strpos( $imprint, 'Universalschlichtungsstelle' ), 'Behoerde und Schlichtungsstelle erscheinen weiterhin' );

/* ------------------------------------------- Schalter ------------------ */

undt_head( 'H-04: Attributnamen im Schalter' );

ob_start();
UNDT_Fields::toggle( 'feld', 'undt-feld', false, array( 'data-undt-ok' => '1', 'onmouseover="alert(1)" x' => 'y' ) );
$toggle = ob_get_clean();
undt_ok( false !== strpos( $toggle, 'data-undt-ok="1"' ) && false === strpos( $toggle, 'onmouseover' ), 'Nur schlichte Attributnamen gelangen in den Schalter' );

/* ------------------------------------------- Laufzeit-Cache ------------ */

undt_head( 'F-17: Rechtsformen werden nicht je Feldzugriff neu aufgebaut' );

UNDT_Schema::legal_form( 'gmbh' );
$vorher = $GLOBALS['undt_gettext'];

for ( $i = 0; $i < 20; $i++ ) {
	UNDT_Schema::applies( array( '_has_register' => true ), array( 'legal_form' => 'gmbh' ) );
}

undt_ok( $vorher === $GLOBALS['undt_gettext'], '20 Feldpruefungen kommen ohne einen einzigen Uebersetzungsaufruf aus' );

/* ------------------------------------------- Plugin Check 0.5.4 -------- */

undt_head( 'Plugin Check: Ausgabe und Textdomaene' );

// Attrappe fuer die Vorschau des Bildfeldes.
if ( ! function_exists( 'wp_get_attachment_image' ) ) {
	function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) {
		return '<img src="https://example.test/bild-' . (int) $id . '.jpg" alt="" />';
	}
}

ob_start();
UNDT_Fields::control( 'logo', array( 'type' => 'media', 'choices' => array() ), '12"><script>alert(1)</script>', 'undt_seo[logo]', 'undt-logo' );
$media = (string) ob_get_clean();

undt_ok( false !== strpos( $media, 'value="12"' ), 'Das Bildfeld gibt die ID aus' );
undt_ok( false === strpos( $media, '<script' ) && false === strpos( $media, 'alert' ), 'Alles andere kommt nicht durch' );

$haupt = (string) file_get_contents( dirname( __DIR__, 2 ) . '/unternehmensdaten/unternehmensdaten.php' );

undt_ok( false === strpos( $haupt, 'Domain Path' ), 'Keine Kopfzeile, die auf einen Ordner zeigt, den es nicht gibt' );
undt_ok( false === strpos( $haupt, 'load_plugin_textdomain(' ), 'Uebersetzungen laedt WordPress selbst aus wp-content/languages/plugins' );
undt_ok( false !== strpos( $haupt, 'Text Domain:       unternehmensdaten' ), 'Die Textdomaene bleibt, sonst faende WordPress die Dateien dort nicht' );

/* ------------------------------------------------------------ Ergebnis --- */

$fails = $GLOBALS['undt_fails'] ?? 0;
echo "\n" . ( $fails ? "\033[31m{$fails} Pruefung(en) fehlgeschlagen\033[0m" : "\033[32mAlle Pruefungen bestanden\033[0m" ) . "\n";
exit( $fails ? 1 : 0 );
