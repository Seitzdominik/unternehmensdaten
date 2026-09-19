<?php
/**
 * Pruefungen fuer 0.5.6: Angaben sichern und einspielen.
 *
 * Geprueft wird der Datensatz selbst und was beim Einspielen mit ihm geschieht.
 * Das Formular und der WP-CLI-Befehl brauchen WordPress, siehe dev/verify.php
 * und den Test auf der Testseite.
 */
require __DIR__ . '/harness.php';

$undt_profile = array( 'legal_form' => 'gmbh', 'vat_status' => 'standard', 'is_regulated' => 1 );
$undt_company = array(
	'company_name' => 'Müller & Söhne',
	'street'       => 'Hauptstraße 5',
	'postal_code'  => '12345',
	'city'         => 'Musterstadt',
	'email'        => 'info@example.test',
	'page_imprint' => 10,
	'page_terms'   => array( 'choice' => 'url', 'url' => '/agb/' ),
);

undt_seed( $undt_profile, $undt_company );
undt_set_modules( array( 'hours' => 1, 'social' => 1, 'seo' => 1, 'faq' => 0 ) );
undt_seed_module( 'seo', array( 'logo' => 42, 'price_range' => '€€' ) );
undt_seed_module( 'social', array( 'items' => array( array( 'platform' => 'linkedin', 'url' => 'https://example.test/firma' ) ) ) );

/* ------------------------------------------------- 1. Sicherung --------- */

undt_head( 'Sicherung' );

$undt_export = UNDT_Transfer::export();

undt_ok( 'unternehmensdaten' === $undt_export['format'] && 1 === $undt_export['schema'], 'Die Datei nennt Herkunft und Aufbau' );
undt_ok( UNDT_VERSION === $undt_export['plugin_version'] && 'https://example.test/' === $undt_export['site'], 'Sie nennt Fassung und Website' );
undt_ok( isset( $undt_export['options']['undt_profile'], $undt_export['options']['undt_company'], $undt_export['options']['undt_settings'] ), 'Profil, Stammdaten und Einstellungen sind enthalten' );
undt_ok( isset( $undt_export['options']['undt_faq'] ) || ! array_key_exists( 'undt_faq', $GLOBALS['undt_options'] ), 'Auch abgeschaltete Bereiche gehoeren dazu, sofern sie Daten haben' );
undt_ok( 'Müller & Söhne' === $undt_export['options']['undt_company']['company_name'], 'Die Werte stehen unveraendert darin' );
undt_ok( 9 === count( UNDT_Transfer::options() ), 'Neun Optionen: Profil, Stammdaten, Einstellungen und sechs Bereiche' );

$undt_json = json_encode( $undt_export, JSON_UNESCAPED_UNICODE );
undt_ok( false !== strpos( $undt_json, 'Müller & Söhne' ), 'Umlaute ueberstehen die Datei' );
undt_ok( false === strpos( strtolower( $undt_json ), 'password' ) && false === strpos( $undt_json, 'undt_update_info' ), 'Keine Passwoerter, kein Zwischenspeicher des Updaters' );

undt_ok( 'unternehmensdaten-example.test-' . gmdate( 'Y-m-d' ) . '.json' === UNDT_Transfer::filename(), 'Der Dateiname nennt Website und Tag' );

/* ------------------------------------------------- 2. Abwehr ------------ */

undt_head( 'Was nicht eingespielt wird' );

undt_ok( is_wp_error( UNDT_Transfer::import( 'kein Array' ) ), 'Eine fremde Struktur wird abgewiesen' );
undt_ok( is_wp_error( UNDT_Transfer::import( array( 'format' => 'anderes-plugin', 'options' => array() ) ) ), 'Eine Datei aus einem anderen Plugin wird abgewiesen' );

$undt_fehler = UNDT_Transfer::import( array( 'format' => 'unternehmensdaten', 'schema' => 99, 'options' => array( 'undt_company' => array() ) ) );
undt_ok( is_wp_error( $undt_fehler ) && false !== strpos( $undt_fehler->get_error_message(), 'neueren' ), 'Eine neuere Fassung wird abgewiesen, mit Hinweis' );
undt_ok( is_wp_error( UNDT_Transfer::import( array( 'format' => 'unternehmensdaten', 'schema' => 1, 'options' => array() ) ) ), 'Eine Datei ohne Angaben wird abgewiesen' );

$undt_vorher = UNDT_Store::get( 'company_name' );
$undt_bericht = UNDT_Transfer::import(
	array(
		'format'  => 'unternehmensdaten',
		'schema'  => 1,
		'options' => array( 'wp_user_roles' => array( 'administrator' => 'x' ), 'undt_unbekannt' => array( 'a' => 'b' ) ),
	)
);
undt_ok( ! is_wp_error( $undt_bericht ) && array( 'wp_user_roles', 'undt_unbekannt' ) === $undt_bericht['ignored'], 'Fremde Optionen werden uebergangen, nicht geschrieben' );
undt_ok( ! array_key_exists( 'wp_user_roles', $GLOBALS['undt_options'] ), 'Eine fremde Option entsteht dabei nicht' );
undt_ok( $undt_vorher === UNDT_Store::get( 'company_name' ), 'Die vorhandenen Angaben bleiben unberuehrt' );

/* ------------------------------------------------- 3. Einspielen -------- */

undt_head( 'Einspielen' );

$undt_datei = array(
	'format'  => 'unternehmensdaten',
	'schema'  => 1,
	'site'    => 'https://andere-seite.test/',
	'options' => array(
		'undt_profile' => array( 'legal_form' => 'sole', 'vat_status' => 'small_business' ),
		'undt_company' => array(
			'company_name'  => '<script>alert(1)</script>Neue Firma',
			'city'          => 'Neustadt',
			'email'         => 'javascript:alert(1)',
			'page_imprint'  => 77,
			'page_privacy'  => array( 'choice' => 'url', 'url' => '/datenschutz/' ),
		),
		'undt_seo'     => array( 'logo' => 99, 'price_range' => '€' ),
	),
);

$undt_bericht = UNDT_Transfer::import( $undt_datei );

undt_ok( ! is_wp_error( $undt_bericht ), 'Die Datei wird angenommen' );
undt_ok( array( 'undt_profile', 'undt_company', 'undt_seo' ) === $undt_bericht['imported'], 'Drei Bereiche eingespielt' );
undt_ok( 'sole' === UNDT_Store::profile()['legal_form'], 'Die Rechtsform ist uebernommen' );
undt_ok( 'Neue Firma' === UNDT_Store::get( 'company_name' ), 'Der Firmenname kommt ohne Script-Tag an' );
undt_ok( '' === UNDT_Store::get( 'email' ), 'Eine javascript:-Adresse ueberlebt die Pruefung nicht' );
undt_ok( 'Neustadt' === UNDT_Store::get( 'city' ), 'Gewoehnliche Werte kommen unveraendert an' );

undt_ok( in_array( 'page_imprint', $undt_bericht['dropped'], true ), 'Die Seiten-ID der anderen Website wird verworfen' );
undt_ok( 0 === UNDT_Store::page_id( 'page_imprint' ), 'Und ist danach leer, statt auf einen fremden Beitrag zu zeigen' );
undt_ok( in_array( 'seo.logo', $undt_bericht['dropped'], true ) && 0 === (int) UNDT_Content::value( 'seo', 'logo' ), 'Auch die Bild-ID' );

$undt_ziel = UNDT_Store::link( 'page_privacy' );
undt_ok( is_array( $undt_ziel ) && '/datenschutz/' === $undt_ziel['url'], 'Eine eigene Adresse bleibt, sie gilt auf jeder Website' );
undt_ok( '€' === UNDT_Content::value( 'seo', 'price_range' ), 'Werte der Inhaltsbereiche kommen an' );
undt_ok( 1 === (int) get_option( 'undt_setup_done' ), 'Nach dem Einspielen gilt die Einrichtung als erledigt' );

/* ------------------------------------------------- 4. Rundlauf ---------- */

undt_head( 'Sichern und wieder einspielen' );

undt_seed( $undt_profile, $undt_company );
undt_seed_module( 'social', array( 'items' => array( array( 'platform' => 'mastodon', 'url' => 'https://example.test/@firma' ) ) ) );

$undt_sicherung = UNDT_Transfer::export();

undt_seed( array( 'legal_form' => 'ug' ), array( 'company_name' => 'Zwischendurch' ) );
undt_seed_module( 'social', array( 'items' => array() ) );

$undt_bericht = UNDT_Transfer::import( $undt_sicherung );

undt_ok( ! is_wp_error( $undt_bericht ), 'Die eigene Sicherung laesst sich einspielen' );
undt_ok( 'Müller & Söhne' === UNDT_Store::get( 'company_name' ) && 'gmbh' === UNDT_Store::profile()['legal_form'], 'Firma und Rechtsform sind zurueck' );
$undt_rows = UNDT_Content::rows( 'social', 'items' );
undt_ok( 1 === count( $undt_rows ) && 'https://example.test/@firma' === $undt_rows[0]['url'], 'Auch die Wiederholungsfelder' );
undt_ok( in_array( 'page_imprint', $undt_bericht['dropped'], true ), 'Die Seiten-ID faellt auch bei der eigenen Sicherung weg, sie koennte von woanders stammen' );

/* ------------------------------------------------- 5. Bericht ----------- */

undt_head( 'Rueckmeldung' );

$undt_text = UNDT_Transfer::summary( $undt_bericht );
undt_ok( false !== strpos( $undt_text, 'Bereiche eingespielt' ), 'Der Bericht nennt die Zahl der Bereiche' );
undt_ok( false !== strpos( $undt_text, 'page_imprint' ), 'Und was neu auszuwaehlen ist' );
undt_ok( 'Kaputt.' === UNDT_Transfer::summary( array( 'error' => 'Kaputt.' ) ), 'Ein Fehler steht als Satz darin' );

/* ---------------------------------------------------------- Ergebnis ---- */

echo "\n";

if ( ! empty( $GLOBALS['undt_fails'] ) ) {
	echo "\033[31m" . (int) $GLOBALS['undt_fails'] . " Pruefungen fehlgeschlagen\033[0m\n";
	exit( 1 );
}

echo "\033[32mAlle Pruefungen bestanden\033[0m\n";
