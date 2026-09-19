<?php
require __DIR__ . '/harness.php';

/**
 * Ein gültiges Manifest, das einzelne Felder überschreiben lässt.
 */
function undt_manifest( array $overrides = array() ) {
	return array_merge(
		array(
			'name'         => 'Unternehmensdaten',
			'slug'         => 'unternehmensdaten',
			'version'      => '9.9.9',
			'homepage'     => 'https://github.com/Seitzdominik/unternehmensdaten',
			'requires'     => '6.4',
			'tested'       => '6.9',
			'requires_php' => '7.4',
			'last_updated' => '2026-09-10 12:00:00',
			'download_url' => 'https://github.com/Seitzdominik/unternehmensdaten/releases/download/v9.9.9/unternehmensdaten.zip',
			'sections'     => array( 'changelog' => '<h4>9.9.9</h4><ul><li>Neu</li></ul>' ),
		),
		$overrides
	);
}

/**
 * Der Eintrag, den WordPress für unser Plugin erhalten würde.
 */
function undt_entry( $key = 'response' ) {
	$transient = (object) array( 'response' => array(), 'no_update' => array() );
	$result    = UNDT_Updater::inject( $transient );
	$file      = 'unternehmensdaten/unternehmensdaten.php';

	return isset( $result->{$key}[ $file ] ) ? $result->{$key}[ $file ] : null;
}

/* ------------------------------------------------------- 1. Normalfall ---- */

undt_head( 'Neue Fassung wird gemeldet' );

undt_set_http( 200, undt_manifest() );

$eintrag = undt_entry( 'response' );
undt_ok( null !== $eintrag, 'Aktualisierung landet in der Antwortliste' );
undt_ok( '9.9.9' === $eintrag->new_version, 'Versionsnummer wird übernommen' );
undt_ok( 'unternehmensdaten/unternehmensdaten.php' === $eintrag->plugin, 'Plugin-Pfad stimmt' );
undt_ok( 'unternehmensdaten' === $eintrag->slug, 'Slug stimmt' );
undt_ok( false !== strpos( $eintrag->package, 'releases/download/v9.9.9' ), 'Paketadresse zeigt auf das Release' );
undt_ok( null === undt_entry( 'no_update' ), 'Bei einer neuen Fassung steht nichts in no_update' );

/* --------------------------------------------------- 2. Aktueller Stand --- */

undt_head( 'Gleiche oder ältere Fassung' );

undt_set_http( 200, undt_manifest( array( 'version' => UNDT_VERSION ) ) );
undt_ok( null === undt_entry( 'response' ), 'Gleiche Version erzeugt keine Aktualisierung' );

undt_set_http( 200, undt_manifest( array( 'version' => UNDT_VERSION ) ) );
undt_ok( null !== undt_entry( 'no_update' ), 'Steht aber in no_update, damit der Auto-Update-Schalter erscheint' );

undt_set_http( 200, undt_manifest( array( 'version' => '0.0.1' ) ) );
undt_ok( null === undt_entry( 'response' ), 'Ältere Version erzeugt keine Aktualisierung' );

/* ----------------------------------------------------------- 3. Abwehr ---- */

undt_head( 'Abwehr fremder Paketquellen' );

$boese = array(
	'https://evil.example.com/paket.zip'                  => 'fremder Host',
	'http://github.com/seitz/x/releases/download/a.zip'   => 'unverschlüsselt',
	'https://github.evil.com/seitz/x.zip'                 => 'ähnlich aussehender Host',
	'javascript:alert(1)'                                 => 'javascript-Protokoll',
	'https://raw.githubusercontent.com.evil.com/x.zip'    => 'Host mit angehängter Domain',
);

foreach ( $boese as $url => $warum ) {
	undt_set_http( 200, undt_manifest( array( 'download_url' => $url ) ) );
	undt_ok( null === undt_entry( 'response' ), 'Abgelehnt: ' . $warum );
}

undt_set_http( 200, undt_manifest( array( 'download_url' => 'https://objects.githubusercontent.com/x/unternehmensdaten.zip' ) ) );
undt_ok( null !== undt_entry( 'response' ), 'Der Umleitungs-Host von GitHub bleibt erlaubt' );

undt_head( 'Abwehr unsinniger Versionsangaben' );

foreach ( array( 'neueste', '<script>', '9.9.9; rm -rf /', '', 'v9.9.9' ) as $version ) {
	undt_set_http( 200, undt_manifest( array( 'version' => $version ) ) );
	undt_ok( null === undt_entry( 'response' ), 'Abgelehnt: Version „' . $version . '“' );
}

undt_head( 'Vorabversionen' );

undt_set_http( 200, undt_manifest( array( 'version' => '10.2.3-beta.1' ) ) );
undt_ok( null === undt_entry( 'response' ), 'Eine Vorabversion wird nicht als Aktualisierung angeboten' );
undt_ok( 'current' === UNDT_Updater::status()['state'], 'Der Status meldet dann den aktuellen Stand, keinen Fehler' );

add_filter( 'undt_update_allow_prerelease', '__return_true' );
undt_set_http( 200, undt_manifest( array( 'version' => '10.2.3-beta.1' ) ) );
undt_ok( null !== undt_entry( 'response' ), 'Mit dem Filter undt_update_allow_prerelease wird sie angeboten' );
remove_all_filters( 'undt_update_allow_prerelease' );

undt_set_http( 200, undt_manifest( array( 'version' => '10.2.3' ) ) );
undt_ok( null !== undt_entry( 'response' ), 'Eine fertige Fassung wird wie gewohnt angeboten' );

/* ------------------------------------------------------ 3b. Kein Token ---- */

undt_head( 'Kein Zugriffstoken' );

// Die Konstante gab es bis 0.4.0. Wer sie noch in der wp-config.php stehen hat,
// darf damit keinen Token mehr an GitHub und dessen CDN schicken.
define( 'UNDT_GITHUB_TOKEN', 'geheim' );
undt_set_http( 200, undt_manifest() );
UNDT_Updater::fetch();
undt_ok( ! isset( $GLOBALS['undt_last_args']['headers']['Authorization'] ), 'Auch mit gesetzter Konstante geht kein Authorization-Header hinaus' );

/* --------------------------------------------------------- 4. Fehlerfall -- */

undt_head( 'Fehlerhafte Antworten' );

undt_set_http( 404, '' );
undt_ok( null === undt_entry( 'response' ), 'HTTP 404 erzeugt keine Aktualisierung' );

undt_set_http( 200, 'kein json' );
undt_ok( null === undt_entry( 'response' ), 'Unlesbare Antwort erzeugt keine Aktualisierung' );

undt_set_http( 200, array( 'version' => '9.9.9' ) );
undt_ok( null === undt_entry( 'response' ), 'Manifest ohne Paketadresse wird verworfen' );

/* ------------------------------------------------- 5. Zwischenspeicher ---- */

undt_head( 'Zwischenspeicher' );

undt_set_http( 200, undt_manifest() );
UNDT_Updater::fetch();
UNDT_Updater::fetch();
UNDT_Updater::fetch();
undt_ok( 1 === $GLOBALS['undt_http_calls'], 'Drei Abrufe erzeugen eine einzige Anfrage' );

undt_ok( false !== strpos( $GLOBALS['undt_last_url'], 'releases/latest/download/update.json' ), 'Abgefragt wird das Anhang-Manifest, nicht die API' );
undt_ok( false === strpos( $GLOBALS['undt_last_url'], 'api.github.com' ), 'Die ratenbegrenzte API wird nicht angefasst' );

undt_set_http( 500, '' );
UNDT_Updater::fetch();
UNDT_Updater::fetch();
UNDT_Updater::fetch();
undt_ok( 1 === $GLOBALS['undt_http_calls'], 'Auch ein Fehlschlag wird gemerkt und nicht wiederholt' );

undt_set_http( 200, undt_manifest() );
UNDT_Updater::fetch();
$vorher = $GLOBALS['undt_http_calls'];
UNDT_Updater::fetch( true );
undt_ok( $vorher + 1 === $GLOBALS['undt_http_calls'], 'Ein erzwungener Abruf übergeht den Zwischenspeicher' );

/* ------------------------------------------------------------- 6. Status -- */

undt_head( 'Anzeige in den Einstellungen' );

undt_set_http( 200, undt_manifest() );
$status = UNDT_Updater::status();
undt_ok( 'update' === $status['state'], 'Status meldet eine verfügbare Fassung' );
undt_ok( '9.9.9' === $status['version'], 'Status nennt die Versionsnummer' );

undt_set_http( 200, undt_manifest( array( 'version' => UNDT_VERSION ) ) );
undt_ok( 'current' === UNDT_Updater::status()['state'], 'Status meldet den aktuellen Stand' );

undt_set_http( 500, '' );
undt_ok( 'error' === UNDT_Updater::status()['state'], 'Status meldet einen Fehlschlag' );

/* --------------------------------------------------- 7. Detailfenster ----- */

undt_head( 'Detailfenster' );

undt_set_http( 200, undt_manifest() );
$details = UNDT_Updater::details( false, 'plugin_information', (object) array( 'slug' => 'unternehmensdaten' ) );
undt_ok( is_object( $details ) && '9.9.9' === $details->version, 'Details werden geliefert' );
undt_ok( false !== strpos( $details->sections['changelog'], 'Neu' ), 'Änderungen erscheinen im Detailfenster' );

$fremd = UNDT_Updater::details( false, 'plugin_information', (object) array( 'slug' => 'anderes-plugin' ) );
undt_ok( false === $fremd, 'Für ein fremdes Plugin bleibt die Anfrage unberührt' );

$andere = UNDT_Updater::details( false, 'query_plugins', (object) array( 'slug' => 'unternehmensdaten' ) );
undt_ok( false === $andere, 'Andere Aktionen bleiben unberührt' );

/* ------------------------------------------------------------ Ergebnis ---- */

$fails = $GLOBALS['undt_fails'] ?? 0;
echo "\n" . ( $fails ? "\033[31m{$fails} Test(s) fehlgeschlagen\033[0m" : "\033[32mAlle Tests bestanden\033[0m" ) . "\n";
exit( $fails ? 1 : 0 );
