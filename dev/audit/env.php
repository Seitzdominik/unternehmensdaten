<?php
/**
 * Audit-Umgebungspruefung (kein Teil des Plugins).
 *
 * Laeuft als runPHP-Schritt in WordPress Playground, nachdem seed-audit.php
 * die Daten angelegt und render.php die Frontend-Seiten als Dateien abgelegt
 * hat. Schreibt dev/audit/result-<name>.txt. Bewusst PHP-7.4-kompatibel.
 */
define( 'WP_ADMIN', true );

$GLOBALS['undt_audit_php_errors'] = array();

set_error_handler(
	function ( $errno, $errstr, $errfile, $errline ) {
		$file = str_replace( '\\', '/', (string) $errfile );
		if ( false !== strpos( $file, '/plugins/unternehmensdaten/' ) ) {
			$GLOBALS['undt_audit_php_errors'][] = sprintf( '[%d] %s in %s:%d', $errno, $errstr, $file, $errline );
		}
		return false;
	}
);

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

global $wpdb, $wp_version;

// Der Name kommt aus einer Datei, die der erste Blueprint-Schritt schreibt; die
// Konstante aus dem Blueprint kam nicht zuverlaessig an.
$undt_name = file_exists( '/wordpress/undt-audit-name.txt' )
	? trim( (string) file_get_contents( '/wordpress/undt-audit-name.txt' ) )
	: ( defined( 'UNDT_AUDIT_NAME' ) ? UNDT_AUDIT_NAME : 'default' );
$lines     = array();

function undt_a( $ok, $msg ) { global $lines; $lines[] = ( $ok ? '  OK   ' : '  FAIL ' ) . $msg; }
function undt_i( $msg ) { global $lines; $lines[] = '  INFO ' . $msg; }
function undt_s( $t ) { global $lines; $lines[] = ''; $lines[] = '== ' . $t . ' =='; }

/**
 * Liest eine von render.php abgelegte Frontend-Seite.
 */
function undt_html( $slug ) {
	global $undt_name;
	$path = '/wordpress/build/dev/audit/html-' . $undt_name . '-' . $slug . '.html';
	if ( ! file_exists( $path ) ) {
		return array( 'ok' => false, 'body' => '', 'error' => 'Datei fehlt: ' . $path );
	}
	$body = (string) file_get_contents( $path );
	return array( 'ok' => strlen( $body ) > 500, 'body' => $body, 'error' => strlen( $body ) > 500 ? '' : 'nur ' . strlen( $body ) . ' Byte' );
}

function undt_headings( $html ) {
	preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $h ) {
		$out[] = 'h' . $h[1] . ':' . trim( wp_strip_all_tags( $h[2] ) );
	}
	return $out;
}

function undt_no_php_messages( $b ) {
	return false === stripos( $b, 'Fatal error' ) && false === stripos( $b, 'Warning:' ) && false === stripos( $b, 'Notice:' ) && false === stripos( $b, 'Deprecated:' );
}

function undt_php_errors_flush( $label ) {
	$errs = $GLOBALS['undt_audit_php_errors'];
	$GLOBALS['undt_audit_php_errors'] = array();
	undt_a( empty( $errs ), $label . ': keine PHP-Meldungen aus Plugin-Dateien' . ( $errs ? ' -> ' . implode( ' || ', array_unique( $errs ) ) : '' ) );
}

$file = 'unternehmensdaten/unternehmensdaten.php';
$ids  = json_decode( (string) file_get_contents( '/wordpress/undt-audit-ids.json' ), true );
$ids  = is_array( $ids ) ? $ids : array();

$imprint_id = isset( $ids['imprint'] ) ? (int) $ids['imprint'] : 0;
$privacy_id = isset( $ids['privacy'] ) ? (int) $ids['privacy'] : 0;
$all_id     = isset( $ids['all'] ) ? (int) $ids['all'] : 0;
$plain_id   = isset( $ids['plain'] ) ? (int) $ids['plain'] : 0;

/* ------------------------------------------------------------ Umgebung --- */

undt_s( 'Umgebung' );
undt_i( 'PHP_VERSION=' . PHP_VERSION );
undt_i( 'wp_version=' . $wp_version );
undt_i( 'multisite=' . ( is_multisite() ? 'yes' : 'no' ) );
undt_i( 'locale=' . get_locale() );
undt_i( 'theme=' . wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ) . ' block_theme=' . ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ? 'yes' : 'no' ) );
undt_i( 'active_plugins=' . implode( ', ', (array) get_option( 'active_plugins' ) ) );
if ( is_multisite() ) {
	undt_i( 'network_plugins=' . implode( ', ', array_keys( (array) get_site_option( 'active_sitewide_plugins' ) ) ) );
}
undt_i( 'UNDT_AUDIT_NAME-Konstante=' . ( defined( 'UNDT_AUDIT_NAME' ) ? UNDT_AUDIT_NAME : '(nicht definiert)' ) . ' WP_DEBUG_LOG=' . ( defined( 'WP_DEBUG_LOG' ) ? var_export( WP_DEBUG_LOG, true ) : '(nicht definiert)' ) );
undt_i( 'WP_DEBUG=' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? '1' : '0' ) . ' SAVEQUERIES=' . ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ? '1' : '0' ) );
undt_i( 'timezone=' . wp_timezone_string() . ' start_of_week=' . get_option( 'start_of_week' ) . ' date_format=' . get_option( 'date_format' ) );
undt_i( 'gettext-calls bis Skriptstart (init inkl. Registeraufbau)=' . ( isset( $GLOBALS['undt_audit_gettext'] ) ? $GLOBALS['undt_audit_gettext'] : 'n/a' ) );
undt_i( 'Seiten: imprint=' . $imprint_id . ' privacy(draft)=' . $privacy_id . ' all=' . $all_id . ' plain=' . $plain_id );
undt_a( class_exists( 'UNDT_Store' ), 'Plugin geladen, UNDT_VERSION=' . ( defined( 'UNDT_VERSION' ) ? UNDT_VERSION : '-' ) );
undt_a( is_plugin_active( $file ) || ( is_multisite() && is_plugin_active_for_network( $file ) ), 'Plugin aktiv (site oder netzwerkweit)' );
undt_a( $imprint_id > 0 && $privacy_id > 0 && $all_id > 0 && $plain_id > 0, 'Testseiten aus seed-audit.php vorhanden' );

undt_s( 'Alle Klassen laden (Parse-Fehler waeren hier fatal)' );
foreach ( array( 'UNDT_Schema', 'UNDT_Modules', 'UNDT_Store', 'UNDT_Content', 'UNDT_Hours', 'UNDT_Sanitizer', 'UNDT_Render', 'UNDT_Blocks', 'UNDT_Shortcodes', 'UNDT_SchemaOrg', 'UNDT_Api', 'UNDT_Updater', 'UNDT_Audit', 'UNDT_Admin', 'UNDT_Fields', 'UNDT_Controls', 'UNDT_Copy', 'UNDT_Dynamic', 'UNDT_Dynamic_SlimSeo', 'UNDT_Dynamic_Bricks', 'UNDT_Dynamic_Etch', 'UNDT_Dynamic_Gutenberg', 'UNDT_Transfer' ) as $cls ) {
	undt_a( class_exists( $cls ), 'Klasse ' . $cls );
}
undt_php_errors_flush( 'Klassen laden' );

if ( class_exists( 'autoptimizeMain' ) && function_exists( 'autoptimize' ) ) {
	undt_s( 'Autoptimize-Diagnose' );
	undt_i(
		'cacheavail=' . var_export( autoptimizeCache::cacheavail(), true )
		. ' js=' . var_export( get_option( 'autoptimize_js' ), true )
		. ' css=' . var_export( get_option( 'autoptimize_css' ), true )
		. ' html=' . var_export( get_option( 'autoptimize_html' ), true )
		. ' hook(template_redirect)=' . var_export( has_action( 'template_redirect', array( autoptimize(), 'start_buffering' ) ), true )
		. ' hook(init)=' . var_export( has_action( 'init', array( autoptimize(), 'start_buffering' ) ), true )
		. ' AUTOPTIMIZE_NOBUFFER_OPTIMIZE=' . var_export( defined( 'AUTOPTIMIZE_NOBUFFER_OPTIMIZE' ), true )
		. ' cache_dir=' . ( defined( 'AUTOPTIMIZE_CACHE_DIR' ) ? AUTOPTIMIZE_CACHE_DIR : '-' )
		. ' writable=' . var_export( defined( 'AUTOPTIMIZE_CACHE_DIR' ) && is_writable( AUTOPTIMIZE_CACHE_DIR ), true )
	);
	$ao_html = undt_html( 'home' );
	undt_i( 'Rendered home enthaelt "<!-- Autoptimize": ' . var_export( false !== strpos( $ao_html['body'], 'Autoptimize' ), true ) );
}

/* ------------------------------------------------------------ Optionen --- */

undt_s( 'autoload der Optionen (Hauptseite)' );
$expected = array( 'undt_profile' => true, 'undt_company' => true, 'undt_settings' => true, 'undt_hours' => true, 'undt_social' => true, 'undt_banner' => true, 'undt_seo' => true, 'undt_prices' => false, 'undt_faq' => false, 'undt_setup_done' => true );
$total    = 0;
foreach ( $expected as $option => $want_on ) {
	$actual  = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option ) );
	$is_auto = in_array( (string) $actual, array( 'yes', 'on', 'auto', 'auto-on' ), true );
	$size    = strlen( serialize( get_option( $option ) ) );
	if ( $want_on ) {
		$total += $size;
	}
	undt_a( null !== $actual && $is_auto === $want_on, sprintf( '%-16s autoload=%-5s (erwartet %s) groesse=%d B', $option, var_export( $actual, true ), $want_on ? 'an' : 'aus', $size ) );
}
undt_i( 'Summe autoloaded undt-Optionen mit Beispieldaten: ' . $total . ' B; alloptions gesamt: ' . strlen( serialize( wp_load_alloptions() ) ) . ' B' );

/* ----------------------------------------------------------- Frontend ---- */

undt_s( 'Frontend: Startseite (in-process gerendert)' );
$home = undt_html( 'home' );
undt_a( $home['ok'], 'Startseite gerendert ' . $home['error'] );
$b = $home['body'];
undt_a( undt_no_php_messages( $b ), 'Keine PHP-Meldungen im HTML' );
undt_a( false !== strpos( $b, 'undt-banner' ), 'Infobanner ueber wp_body_open vorhanden' );
/*
 * Autoptimize setzt Inline-Skripte als base64-kodierte data:-Adresse an dieselbe
 * Stelle. Gesucht wird deshalb auch im dekodierten Inhalt solcher Skripte, sonst
 * meldete die Pruefung dort einen Fehler, den es nicht gibt.
 */
$scripts = $b;
if ( preg_match_all( '#src=["\']data:text/javascript;base64,([A-Za-z0-9+/=]+)["\']#', $b, $m ) ) {
	foreach ( $m[1] as $encoded ) {
		$scripts .= (string) base64_decode( $encoded );
	}
}
undt_a( false !== strpos( $b, 'data-undt-banner=' ) && false !== strpos( $scripts, 'localStorage' ), 'Banner-Schliessskript vorhanden (auch als data:-Skript)' );
undt_a( false !== strpos( $scripts, 'compareDocumentPosition' ), 'Banner-Skript gibt den Fokus nach dem Schliessen an das naechste Element weiter' );
undt_a( (bool) preg_match( '/<style[^>]*id=.undt-inline-css/', $b ) || false !== strpos( $b, '.undt-banner{--undt-bg' ), 'Banner-CSS vorhanden (Inline-Handle undt-inline-css oder aggregiert)' );
undt_i( 'Inline-Handle undt-inline-css im HTML: ' . ( preg_match( '/<style[^>]*id=.undt-inline-css/', $b ) ? 'ja' : 'nein' ) );
undt_i( 'ld+json-Skripte: ' . substr_count( $b, 'application/ld+json' ) . '; Plugin-JSON-LD (ProfessionalService): ' . ( false !== strpos( $b, '"@type":"ProfessionalService"' ) ? 'ja' : 'nein' ) );
undt_i( 'detect_seo_plugin=' . var_export( UNDT_SchemaOrg::detect_seo_plugin(), true ) . ' should_output=' . var_export( UNDT_SchemaOrg::should_output(), true ) );
if ( preg_match_all( '/(?:src|href)=["\']([^"\']*\/cache\/autoptimize\/[^"\']*\.(?:js|css))[^"\']*["\']/i', $b, $m ) ) {
	foreach ( array_unique( $m[1] ) as $asset ) {
		$rel   = preg_replace( '#^.*?/wp-content/#', '', $asset );
		$path  = WP_CONTENT_DIR . '/' . $rel;
		$blob  = file_exists( $path ) ? (string) file_get_contents( $path ) : '';
		$found = array();
		if ( false !== strpos( $blob, 'undt-banner-' ) ) {
			$found[] = 'Banner-Skript';
		}
		if ( false !== strpos( $blob, '--undt-bg' ) ) {
			$found[] = 'Banner-CSS';
		}
		undt_i( 'Autoptimize-Datei ' . $rel . ' (' . strlen( $blob ) . ' B): ' . ( $found ? implode( ', ', $found ) : 'nichts vom Plugin' ) );
	}
	undt_i( 'Banner-Skript noch inline im HTML: ' . ( false !== strpos( $b, "var k='" ) ? 'ja' : 'nein' ) . '; Banner-CSS noch inline: ' . ( false !== strpos( $b, '--undt-bg' ) ? 'ja' : 'nein' ) );
}

undt_s( 'Frontend: Seite mit allen Bloecken' );
$all = undt_html( 'all' );
$b   = $all['body'];
undt_a( $all['ok'], 'Seite gerendert ' . $all['error'] );
undt_a( undt_no_php_messages( $b ), 'Keine PHP-Meldungen im HTML' );
foreach ( array( 'undt-imprint', 'undt-hours__table', 'undt-hours-today', 'undt-open', 'undt-prices__table', 'undt-social', 'undt-faq__item', 'undt-footer__nav', 'undt-address', 'undt-privacy-block', 'href="tel:+49301234567"' ) as $needle ) {
	undt_a( false !== strpos( $b, $needle ), 'enthaelt ' . $needle );
}
undt_i( 'Inline-Handle undt-inline-css: ' . preg_match_all( '/<style[^>]*id=.undt-inline-css/', $b ) . ' mal' );
undt_a( false !== strpos( $b, '<ul class="undt-stack-list undt-social__list">' ), 'Social-Profile stehen untereinander' );
undt_a( false !== strpos( $b, '>Impressum</a>' ), 'Footer verlinkt die veroeffentlichte Impressum-Seite' );
undt_a( false === strpos( $b, '>Datenschutz</a>' ), 'Footer verlinkt den Entwurf NICHT' );
undt_a( false === strpos( $b, 'Datenschutz Entwurf' ), '[undt key=page_privacy link=1] verlinkt den Entwurf NICHT (erwartet: kein Link auf unveroeffentlichte Seite)' );
undt_i( 'Ueberschriften: ' . implode( ' | ', undt_headings( $b ) ) );
undt_i( 'Hours-Zeilen: ' . implode( ' | ', array_map( 'wp_strip_all_tags', ( preg_match_all( '/<th scope="row">(.*?)<\/th>/s', $b, $m ) ? $m[1] : array() ) ) ) );
undt_i( 'Heute: ' . ( preg_match( '/<span class="undt-hours-today[^"]*">(.*?)<\/span>/s', $b, $m ) ? wp_strip_all_tags( $m[1] ) : '-' ) );
undt_i( 'Sondertermine: ' . implode( ' | ', ( preg_match_all( '/<time datetime="[^"]+">(.*?)<\/time>/', $b, $m ) ? $m[1] : array() ) ) );

undt_s( 'Frontend: Seite ohne Bloecke' );
$plain = undt_html( 'plain' );
undt_a( $plain['ok'], 'Seite gerendert ' . $plain['error'] );
undt_i( 'Mit aktivem Auto-Banner: inline-css=' . ( preg_match( '/<style[^>]*id=.undt-inline-css/', $plain['body'] ) ? 'ja' : 'nein' ) . ' banner=' . ( false !== strpos( $plain['body'], 'undt-banner' ) ? 'ja' : 'nein' ) );
$plain2 = undt_html( 'plain-nobanner' );
undt_a( $plain2['ok'], 'Seite ohne Banner gerendert ' . $plain2['error'] );
undt_a( ! preg_match( '/<style[^>]*id=.undt-inline-css/', $plain2['body'] ) && false === strpos( $plain2['body'], '.undt-block{' ), 'Ohne Banner und ohne Block: kein Plugin-CSS' );
undt_a( false === strpos( $plain2['body'], 'undt-banner' ), 'Ohne Banner: kein Banner-Markup' );

/* ----------------------------------------------------------- Sprache ----- */

undt_s( 'Sprache und Datum' );
undt_i( 'day_label(mon)=' . UNDT_Hours::day_label( 'mon' ) . ' short=' . UNDT_Hours::day_label( 'mon', true ) . ' sun=' . UNDT_Hours::day_label( 'sun' ) . ' short=' . UNDT_Hours::day_label( 'sun', true ) );
undt_i( 'display_order=' . implode( ',', UNDT_Hours::display_order() ) );
$sp = UNDT_Api::query( 'undt_hours_special' );
undt_i( 'special date_label=' . ( isset( $sp[0]['date_label'] ) ? $sp[0]['date_label'] : '-' ) );
$grp = UNDT_Api::query( 'undt_hours_grouped' );
undt_i( 'grouped labels=' . implode( ' / ', wp_list_pluck( $grp, 'days_label' ) ) );
undt_i( 'undt_today()=' . undt_today() . ' undt_is_open()=' . var_export( undt_is_open(), true ) . ' now=' . current_datetime()->format( 'c' ) );
undt_i( 'Copyright-Jahr: ' . date_i18n( 'Y' ) );
undt_php_errors_flush( 'Frontend-Funktionen' );

/* ---------------------------------------------------------- Abfragen ----- */

undt_s( 'Datenbankabfragen der Bloecke (in-process, nach Cache-Leerung)' );
if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
	wp_cache_flush();
	UNDT_Store::flush();
	UNDT_Content::flush();
	$wpdb->queries = array();
	$g0            = isset( $GLOBALS['undt_audit_gettext'] ) ? $GLOBALS['undt_audit_gettext'] : 0;
	$html          = do_shortcode( '[undt_impressum][undt_footer][undt_hours][undt_social][undt_banner][undt_hours_today][undt_open_now]' );
	$org           = UNDT_SchemaOrg::organization();
	$g1            = isset( $GLOBALS['undt_audit_gettext'] ) ? $GLOBALS['undt_audit_gettext'] : 0;
	$qs            = array();
	foreach ( $wpdb->queries as $q ) {
		$qs[] = preg_replace( '/\s+/', ' ', $q[0] );
	}
	undt_i( 'autoloaded Bloecke + JSON-LD: ' . count( $qs ) . ' Abfragen, gettext(unternehmensdaten)=' . ( $g1 - $g0 ) );
	foreach ( $qs as $q ) {
		undt_i( '   ' . substr( $q, 0, 160 ) );
	}
	$wpdb->queries = array();
	$html          = do_shortcode( '[undt_prices][undt_faq]' );
	$qs            = array();
	foreach ( $wpdb->queries as $q ) {
		$qs[] = preg_replace( '/\s+/', ' ', $q[0] );
	}
	undt_i( 'Preise + FAQ: ' . count( $qs ) . ' Abfragen' );
	foreach ( $qs as $q ) {
		undt_i( '   ' . substr( $q, 0, 160 ) );
	}
	$g0 = $GLOBALS['undt_audit_gettext'];
	do_shortcode( '[undt_impressum]' );
	undt_i( 'gettext(unternehmensdaten) fuer ein weiteres [undt_impressum]: ' . ( $GLOBALS['undt_audit_gettext'] - $g0 ) );
	$g0 = $GLOBALS['undt_audit_gettext'];
	$t0 = microtime( true );
	$n  = UNDT_Audit::quick_count();
	undt_i( 'quick_count()=' . $n . ' gettext=' . ( $GLOBALS['undt_audit_gettext'] - $g0 ) . ' dauer=' . round( ( microtime( true ) - $t0 ) * 1000, 1 ) . ' ms (WASM)' );
	$g0 = $GLOBALS['undt_audit_gettext'];
	$t0 = microtime( true );
	for ( $i = 0; $i < 20; $i++ ) {
		UNDT_Schema::legal_forms();
	}
	undt_i( '20x legal_forms(): gettext=' . ( $GLOBALS['undt_audit_gettext'] - $g0 ) . ' dauer=' . round( ( microtime( true ) - $t0 ) * 1000, 1 ) . ' ms (WASM)' );
} else {
	undt_i( 'SAVEQUERIES nicht gesetzt' );
}

/* ------------------------------------------------------------ Backend ---- */

undt_s( 'Backend' );
wp_set_current_user( 1 );
set_current_screen( 'dashboard' );
do_action( 'admin_menu' );
/*
 * SEO-Plugins leiten bei admin_init auf ihren Einrichtungsassistenten um und
 * beenden damit den Prozess. Die Umleitung wird hier in eine Ausnahme
 * verwandelt und abgefangen.
 */
add_filter(
	'wp_redirect',
	function ( $location ) {
		throw new RuntimeException( 'Umleitung abgefangen: ' . $location );
	},
	0
);
try {
	do_action( 'admin_init' );
} catch ( RuntimeException $e ) {
	undt_i( $e->getMessage() );
}
undt_php_errors_flush( 'admin_menu/admin_init' );

do_action( 'admin_enqueue_scripts', 'index.php' );
undt_a( ! wp_style_is( 'undt-admin', 'enqueued' ) && ! wp_script_is( 'undt-admin', 'enqueued' ), 'Auf dem Dashboard keine Plugin-Assets' );
do_action( 'admin_enqueue_scripts', 'toplevel_page_undt' );
undt_a( wp_style_is( 'undt-admin', 'enqueued' ) && wp_script_is( 'undt-admin', 'enqueued' ), 'Auf der eigenen Seite Plugin-Assets geladen' );

$views = array( 'company.php', 'profile.php', 'shortcodes.php', 'audit.php', 'settings.php' );
foreach ( $views as $view ) {
	ob_start();
	$error = '';
	try {
		include WP_PLUGIN_DIR . '/unternehmensdaten/admin/views/' . $view;
	} catch ( Throwable $e ) {
		$error = get_class( $e ) . ': ' . $e->getMessage();
	}
	$html = ob_get_clean();
	undt_a( '' === $error && false !== strpos( $html, 'undt-wrap' ), 'Ansicht ' . $view . ' rendert' . ( $error ? ': ' . $error : '' ) );
	if ( 'company.php' === $view ) {
		// Seit 0.5.0 sendet die Auswahl unter [choice], daneben steht das Feld fuer eine eigene Adresse.
		$has_select = preg_match( '/<select[^>]*name=["\']undt_company\[page_privacy\]\[choice\]["\'][^>]*>(.*?)<\/select>/s', $html, $m );
		undt_a( $has_select && preg_match( '/value=["\']' . $privacy_id . '["\'][^>]*selected/', $m[1] ), 'Seitenauswahl page_privacy enthaelt die gespeicherte Entwurfsseite ' . $privacy_id . ' und waehlt sie (sonst geht die Verknuepfung beim Speichern verloren)' );
		$has_select2 = preg_match( '/<select[^>]*name=["\']undt_company\[page_imprint\]\[choice\]["\'][^>]*>(.*?)<\/select>/s', $html, $m2 );
		undt_a( $has_select2 && preg_match( '/value=["\']' . $imprint_id . '["\'][^>]*selected/', $m2[1] ), 'Seitenauswahl page_imprint enthaelt die veroeffentlichte Seite und waehlt sie' );
		undt_a( false !== strpos( $html, 'name="undt_company[page_imprint][url]"' ), 'Neben der Auswahl steht das Feld fuer eine eigene Adresse' );
		undt_i( 'Auswahl page_privacy: ' . ( $has_select ? preg_replace( '/\s+/', ' ', substr( $m[1], 0, 400 ) ) : 'select nicht gefunden' ) );

		/*
		 * Seit 0.5.8 steht neben Bricks und Etch ein Knopf, der einen fertigen
		 * Block fuer Gutenberg kopiert. Block-Bindungen gibt es erst ab
		 * WordPress 6.5; darunter fehlt der Knopf, statt ins Leere zu greifen.
		 */
		undt_a(
			( false !== strpos( $html, 'undt-copy--gutenberg' ) ) === function_exists( 'register_block_bindings_source' ),
			'Knopf fuer den Block-Editor erscheint genau dann, wenn WordPress Block-Bindungen kennt (WP ' . $wp_version . ')'
		);
	}
}
foreach ( array( 'hours', 'prices', 'social', 'faq', 'banner', 'seo' ) as $undt_slug ) {
	ob_start();
	$error = '';
	try {
		include WP_PLUGIN_DIR . '/unternehmensdaten/admin/views/module.php';
	} catch ( Throwable $e ) {
		$error = get_class( $e ) . ': ' . $e->getMessage();
	}
	$html = ob_get_clean();
	undt_a( '' === $error && false !== strpos( $html, 'undt-form' ), 'Modulansicht ' . $undt_slug . ' rendert' . ( $error ? ': ' . $error : '' ) );
}
undt_php_errors_flush( 'Ansichten' );

$issues = wp_list_pluck( UNDT_Audit::run(), 'title' );
undt_a( (bool) preg_grep( '/TMG/', $issues ), 'Pruefung findet "§ 5 TMG" auf der verknuepften Entwurfsseite' );
undt_a( (bool) preg_grep( '/OS-Plattform/', $issues ), 'Pruefung findet den OS-Plattform-Link' );
undt_a( (bool) preg_grep( '/nicht veröffentlicht/u', $issues ), 'Pruefung meldet die unveroeffentlichte Seite' );
undt_i( 'Pruefpunkte: ' . implode( ' | ', $issues ) );

undt_s( 'Sanitisierung gegen Skript (unfiltered_html-unabhaengig)' );
$c = UNDT_Sanitizer::company( array( 'company_name' => '<img src=x onerror=alert(1)>Firma', 'website' => 'javascript:alert(1)', 'email' => '"><script>x</script>@a.de' ) );
undt_a( 'Firma' === $c['company_name'] && '' === $c['website'] && false === strpos( $c['email'], '<' ), 'Stammdaten: Tags und javascript: entfernt' );
$bn = UNDT_Content::sanitize( 'banner', array( 'text' => "<script>alert(1)</script>Hallo\n<b>fett</b>", 'link_url' => 'javascript:alert(1)', 'type' => '<x>' ) );
undt_a( false === strpos( $bn['text'], '<' ) && '' === $bn['link_url'] && 'info' === $bn['type'], 'Banner: Tags entfernt, URL verworfen, Typ auf Voreinstellung' );
$fq = UNDT_Content::sanitize( 'faq', array( 'items' => array( array( 'question' => '<svg onload=alert(1)>F', 'answer' => '<a href="x" onclick="y">A</a>', 'group' => array( 'x' ) ) ) ) );
undt_a( false === strpos( $fq['items'][0]['question'], '<' ) && false === strpos( $fq['items'][0]['answer'], '<' ), 'FAQ: Tags aus Wiederholungsfeld entfernt' );
undt_i( 'Array statt String im Unterfeld group ergibt: ' . var_export( $fq['items'][0]['group'], true ) );
undt_php_errors_flush( 'Sanitisierung mit Arrays statt Strings' );
// Das Banner bringt sein eigenes Schliessskript mit, gesucht wird deshalb nach den eingeschleusten Nutzlasten.
$rendered = do_shortcode( '[undt_faq]' ) . do_shortcode( '[undt_banner]' ) . do_shortcode( '[undt_impressum]' );
undt_a( false === strpos( $rendered, 'alert(' ) && false === strpos( $rendered, 'onerror' ) && false === strpos( $rendered, 'onload' ) && false === strpos( $rendered, 'onclick' ), 'Nichts davon erreicht die Ausgabe' );

/* ---------------------------------------------------------- Multisite ---- */

if ( is_multisite() ) {
	undt_s( 'Multisite' );
	undt_a( is_plugin_active_for_network( $file ), 'Netzwerkweit aktiv' );
	$admin_id = wp_insert_user( array( 'user_login' => 'siteadmin', 'user_pass' => wp_generate_password(), 'user_email' => 'siteadmin@example.test', 'role' => 'administrator' ) );
	undt_i( 'Site-Admin (kein Super-Admin) hat unfiltered_html: ' . var_export( user_can( $admin_id, 'unfiltered_html' ), true ) . ', manage_options: ' . var_export( user_can( $admin_id, 'manage_options' ), true ) );
	$site = wp_insert_site( array( 'domain' => wp_parse_url( home_url(), PHP_URL_HOST ), 'path' => '/zwei/', 'title' => 'Zweite Seite', 'user_id' => 1 ) );
	if ( is_wp_error( $site ) ) {
		undt_a( false, 'Unterseite anlegen: ' . $site->get_error_message() );
	} else {
		undt_i( 'Unterseite ' . $site . ' angelegt' );
		switch_to_blog( (int) $site );
		undt_i( 'Unterseite: undt_prices vorhanden=' . var_export( false !== get_option( 'undt_prices' ), true ) . ' undt_profile vorhanden=' . var_export( false !== get_option( 'undt_profile' ), true ) );
		// So speichert options.php auf der Unterseite: update_option ohne autoload-Angabe.
		update_option( 'undt_prices', UNDT_Content::sanitize( 'prices', array( 'items' => array( array( 'group' => '', 'label' => 'Test', 'price' => '1 €', 'note' => '' ) ) ) ) );
		update_option( 'undt_faq', UNDT_Content::sanitize( 'faq', array( 'items' => array( array( 'group' => '', 'question' => 'F?', 'answer' => 'A.' ) ) ) ) );
		update_option( 'undt_hours', UNDT_Content::sanitize( 'hours', array( 'suffix' => 'Uhr' ) ) );
		foreach ( array( 'undt_prices' => false, 'undt_faq' => false, 'undt_hours' => true ) as $option => $want_on ) {
			$actual  = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option ) );
			$is_auto = in_array( (string) $actual, array( 'yes', 'on', 'auto', 'auto-on' ), true );
			undt_a( $is_auto === $want_on, sprintf( 'Unterseite %-12s autoload=%-6s (erwartet %s) Tabelle=%s', $option, var_export( $actual, true ), $want_on ? 'an' : 'aus', $wpdb->options ) );
		}
		UNDT_Content::flush();
		$sub_html = do_shortcode( '[undt_prices]' );
		undt_a( false !== strpos( $sub_html, 'Test' ), 'Unterseite: eigene Daten werden ausgegeben' );
		restore_current_blog();
		$sub_id = (int) $site;
	}
	undt_php_errors_flush( 'Multisite' );
}

/* ------------------------------------------ Deaktivieren / Loeschen ---- */

undt_s( 'Deaktivieren, erneut aktivieren, loeschen' );
deactivate_plugins( $file, false, is_multisite() );
undt_a( ! is_plugin_active( $file ) && ! ( is_multisite() && is_plugin_active_for_network( $file ) ), 'Deaktiviert' );
undt_a( false !== get_option( 'undt_company' ), 'Daten bleiben beim Deaktivieren erhalten' );
$wpdb->update( $wpdb->options, array( 'autoload' => 'yes' ), array( 'option_name' => 'undt_prices' ) );
wp_cache_flush();
$res = activate_plugin( $file, '', is_multisite() );
undt_a( null === $res, 'Erneut aktiviert' . ( is_wp_error( $res ) ? ': ' . $res->get_error_message() : '' ) );
$prices_after = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'undt_prices' ) );
undt_a( ! in_array( (string) $prices_after, array( 'yes', 'on', 'auto', 'auto-on' ), true ), 'Aktivierung zieht autoload von undt_prices nach (vorher manipuliert auf yes, jetzt ' . var_export( $prices_after, true ) . ')' );
undt_a( false !== get_option( 'undt_company' ) && 'Playground GmbH' === UNDT_Store::get( 'company_name' ), 'Daten nach erneuter Aktivierung unveraendert' );
set_site_transient( 'undt_update_info', 'fail', 600 );

/*
 * Nur EINE Deinstallation je Prozess: WordPress bindet uninstall.php per
 * include_once ein, ein zweiter Durchlauf im selben Prozess liefe ins Leere.
 * Die Variante mit keep_data=1 prueft deshalb uninstall-keep.php in einem
 * eigenen runPHP-Schritt.
 */
$settings              = get_option( 'undt_settings' );
$settings['keep_data'] = 0;
update_option( 'undt_settings', $settings );
deactivate_plugins( $file, false, is_multisite() );
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();
$del = delete_plugins( array( $file ) );
undt_a( true === $del, 'Plugin geloescht (keep_data=0)' . ( is_wp_error( $del ) ? ': ' . $del->get_error_message() : '' ) );
undt_a( ! file_exists( WP_PLUGIN_DIR . '/unternehmensdaten' ), 'Plugin-Ordner entfernt' );
$left = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'undt_%'" );
undt_a( empty( $left ), 'keep_data=0: alle undt_-Optionen entfernt' . ( $left ? ' -> uebrig: ' . implode( ', ', $left ) : '' ) );
undt_a( false === get_site_transient( 'undt_update_info' ), 'Site-Transient undt_update_info nach Deinstallation entfernt (erwartet)' );
if ( is_multisite() && isset( $sub_id ) ) {
	switch_to_blog( $sub_id );
	$left2 = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'undt_%'" );
	undt_a( empty( $left2 ), 'Unterseite: alle undt_-Optionen entfernt' . ( $left2 ? ' -> uebrig: ' . implode( ', ', $left2 ) : '' ) );
	restore_current_blog();
}
undt_php_errors_flush( 'Deinstallation' );

/* ------------------------------------------------------------ Debug-Log -- */

undt_s( 'debug.log (Zeilen mit unternehmensdaten oder undt)' );
$log = defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
if ( file_exists( $log ) ) {
	$all_lines = file( $log );
	$hits      = preg_grep( '/unternehmensdaten|undt/i', $all_lines );
	undt_i( $log . ': ' . count( $hits ) . ' Treffer von ' . count( $all_lines ) . ' Zeilen' );
	foreach ( array_slice( array_values( array_unique( $hits ) ), 0, 40 ) as $h ) {
		undt_i( '   ' . trim( $h ) );
	}
} else {
	undt_i( 'kein Log (' . $log . ')' );
}

/* ------------------------------------------------------------ Ergebnis --- */

$ok   = 0;
$fail = 0;
foreach ( $lines as $l ) {
	if ( 0 === strpos( $l, '  OK' ) ) {
		++$ok;
	}
	if ( 0 === strpos( $l, '  FAIL' ) ) {
		++$fail;
	}
}
$lines[] = '';
$lines[] = sprintf( 'ENV %s | PHP %s | WP %s | %d OK, %d FAIL', $undt_name, PHP_VERSION, $wp_version, $ok, $fail );
$lines[] = '';
file_put_contents( '/wordpress/build/dev/audit/result-' . $undt_name . '.txt', implode( "\n", $lines ) );
