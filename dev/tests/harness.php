<?php
/**
 * Minimale WordPress-Stubs, um Schema, Store, Sanitizer und Render ohne
 * WordPress-Installation ausfuehren zu koennen.
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['undt_options'] = array();
$GLOBALS['undt_posts']   = array();
$GLOBALS['undt_filters'] = array();
$GLOBALS['undt_gettext'] = 0;

// Zaehlt mit, damit sich unnoetig wiederholte Uebersetzungsaufrufe pruefen lassen.
function __( $t, $d = null ) { ++$GLOBALS['undt_gettext']; return $t; }
function _n( $einzahl, $mehrzahl, $anzahl, $d = null ) { ++$GLOBALS['undt_gettext']; return 1 === (int) $anzahl ? $einzahl : $mehrzahl; }
function esc_html__( $t, $d = null ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $t, $d = null ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $u, $protocols = null ) {
	$u = trim( (string) $u );
	if ( '' === $u ) { return ''; }
	// Wie WordPress: Pfade ohne Schema bleiben relativ und brauchen kein erlaubtes Protokoll.
	if ( in_array( $u[0], array( '/', '#', '?' ), true ) ) { return $u; }
	$scheme = strtolower( (string) parse_url( $u, PHP_URL_SCHEME ) );
	if ( null !== $protocols && ! in_array( $scheme, $protocols, true ) ) { return ''; }
	return $u;
}
function wp_strip_all_tags( $t, $breaks = false ) {
	$t = preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', (string) $t );
	$t = strip_tags( (string) $t );
	return $breaks ? trim( preg_replace( '/[
	 ]+/', ' ', $t ) ) : trim( $t );
}
function sanitize_text_field( $t ) { return wp_strip_all_tags( $t ); }
function sanitize_textarea_field( $t ) { return wp_strip_all_tags( $t ); }
function sanitize_email( $t ) { return filter_var( (string) $t, FILTER_VALIDATE_EMAIL ) ? (string) $t : ''; }
function sanitize_key( $k ) { return is_scalar( $k ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) : ''; }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function absint( $n ) { return abs( (int) $n ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function antispambot( $e, $hex = 0 ) { return str_replace( '@', '&#64;', (string) $e ); }

// Filter und Aktionen als einfache Liste, ohne Prioritaeten.
function add_filter( $tag, $cb, $priority = 10, $accepted = 1 ) { $GLOBALS['undt_filters'][ $tag ][] = $cb; return true; }
function add_action( $tag, $cb, $priority = 10, $accepted = 1 ) { return add_filter( $tag, $cb, $priority, $accepted ); }
function remove_all_filters( $tag ) { unset( $GLOBALS['undt_filters'][ $tag ] ); return true; }
function apply_filters( $tag, $value, ...$args ) {
	$callbacks = isset( $GLOBALS['undt_filters'][ $tag ] ) ? $GLOBALS['undt_filters'][ $tag ] : array();
	foreach ( $callbacks as $cb ) {
		$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
	}
	return $value;
}
function __return_true() { return true; }
function __return_false() { return false; }

function add_shortcode( $tag, $cb ) { $GLOBALS['undt_shortcodes'][ $tag ] = $cb; }
function wp_register_style() {}
function wp_add_inline_style() {}
function wp_enqueue_style() {}
function wp_style_is() { return true; }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function date_i18n( $f, $t = null ) { return date( $f, null === $t ? time() : $t ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['undt_options'] ) ? $GLOBALS['undt_options'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['undt_options'][ $k ] = $v; return true; }
function get_permalink( $id ) { return 'https://example.test/?p=' . (int) $id; }
function get_post_status( $id ) { return isset( $GLOBALS['undt_posts'][ $id ] ) ? $GLOBALS['undt_posts'][ $id ]['status'] : false; }
function get_the_title( $id ) { return isset( $GLOBALS['undt_posts'][ $id ] ) ? $GLOBALS['undt_posts'][ $id ]['title'] : ''; }
function get_post( $id ) { return null; }
function get_edit_post_link( $id, $c = null ) { return 'https://example.test/edit?p=' . (int) $id; }

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}

/* --------------------------------------------------- Stubs für Phase 2 --- */

class WP_Locale {
	private $days   = array( 'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag' );
	private $abbrev = array( 'Sonntag' => 'So', 'Montag' => 'Mo', 'Dienstag' => 'Di', 'Mittwoch' => 'Mi', 'Donnerstag' => 'Do', 'Freitag' => 'Fr', 'Samstag' => 'Sa' );
	public function get_weekday( $i ) { return $this->days[ (int) $i ]; }
	public function get_weekday_abbrev( $name ) { return isset( $this->abbrev[ $name ] ) ? $this->abbrev[ $name ] : $name; }
}

$GLOBALS['wp_locale'] = new WP_Locale();
$GLOBALS['undt_now']  = '2026-09-10 10:30:00';

function current_datetime() { return new DateTimeImmutable( $GLOBALS['undt_now'], new DateTimeZone( 'Europe/Berlin' ) ); }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_get_attachment_image_src( $id, $size = 'full' ) { return $id > 0 ? array( 'https://example.test/logo.png', 512, 512 ) : false; }
function wp_kses_post( $t ) { return $t; }
function wp_kses( $t, $a ) { return $t; }
function selected( $a, $b, $e = true ) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function checked( $a, $b = true, $e = true ) { return (string) $a === (string) $b ? ' checked="checked"' : ''; }


/* ------------------------------------------------ Stubs fuer den Updater --- */

define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
/*
 * Zwei Ablagen, ein Plugin: im Entwicklungsordner liegt es unter
 * unternehmensdaten/, im Repository bildet es selbst die Wurzel. Der Pfad wird
 * deshalb gesucht, damit dieselben Pruefungen hier wie dort laufen.
 */
$undt_wurzel = dirname( __DIR__, 2 );

define(
	'UNDT_TEST_BASE',
	is_file( $undt_wurzel . '/unternehmensdaten/unternehmensdaten.php' )
		? $undt_wurzel . '/unternehmensdaten/'
		: $undt_wurzel . '/'
);

define( 'UNDT_FILE', UNDT_TEST_BASE . 'unternehmensdaten.php' );
define( 'UNDT_UPDATE_REPO', 'Seitzdominik/unternehmensdaten' );

$GLOBALS['undt_transients'] = array();
$GLOBALS['undt_http']       = array();   // Antwort, die wp_remote_get liefern soll.
$GLOBALS['undt_http_calls'] = 0;

class WP_Error {
	public $code;
	public $msg;
	// Wie in WordPress: erst der Schluessel, dann die Meldung.
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->msg = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->msg; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $n ); }
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['undt_transients'] ) ? $GLOBALS['undt_transients'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['undt_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['undt_transients'][ $k ] ); return true; }
function get_current_user_id() { return 1; }
function wp_doing_cron() { return false; }
function is_admin() { return true; }
function plugin_basename( $f ) { return 'unternehmensdaten/unternehmensdaten.php'; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function get_site_transient( $k ) { return array_key_exists( $k, $GLOBALS['undt_transients'] ) ? $GLOBALS['undt_transients'][ $k ] : false; }
function set_site_transient( $k, $v, $t = 0 ) { $GLOBALS['undt_transients'][ $k ] = $v; return true; }
function delete_site_transient( $k ) { unset( $GLOBALS['undt_transients'][ $k ] ); return true; }
function get_plugin_data( $f, $a = true, $b = true ) { return array( 'Name' => 'Unternehmensdaten', 'Author' => 'Seitz', 'Version' => UNDT_VERSION ); }

function wp_remote_get( $url, $args = array() ) {
	++$GLOBALS['undt_http_calls'];
	$GLOBALS['undt_last_url']  = $url;
	$GLOBALS['undt_last_args'] = $args;
	return $GLOBALS['undt_http'];
}
function wp_remote_retrieve_response_code( $r ) { return isset( $r['response']['code'] ) ? $r['response']['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return isset( $r['body'] ) ? $r['body'] : ''; }

/**
 * Setzt die Antwort, die der naechste Abruf liefern soll.
 */
function undt_set_http( $code, $body ) {
	$GLOBALS['undt_http'] = array( 'response' => array( 'code' => $code ), 'body' => is_array( $body ) ? json_encode( $body ) : $body );
	$GLOBALS['undt_transients'] = array();
	$GLOBALS['undt_http_calls'] = 0;
}

// Aus dem Plugin-Header lesen, damit die Tests nach einem Versionssprung nicht
// stillschweigend gegen eine veraltete Nummer pruefen.
preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', (string) file_get_contents( UNDT_FILE ), $undt_version_match );
define( 'UNDT_VERSION', isset( $undt_version_match[1] ) ? $undt_version_match[1] : '0.0.0' );
define( 'UNDT_DIR', __DIR__ );

$base = UNDT_TEST_BASE;

require $base . 'includes/class-undt-schema.php';
require $base . 'includes/class-undt-store.php';
require $base . 'includes/class-undt-sanitizer.php';
require $base . 'includes/class-undt-render.php';
require $base . 'includes/class-undt-shortcodes.php';
require $base . 'includes/class-undt-audit.php';
require $base . 'includes/class-undt-modules.php';
require $base . 'includes/class-undt-content.php';
require $base . 'includes/class-undt-hours.php';
require $base . 'includes/class-undt-blocks.php';
require $base . 'includes/class-undt-schemaorg.php';
require $base . 'includes/class-undt-api.php';
require $base . 'includes/class-undt-dynamic.php';
require $base . 'includes/class-undt-dynamic-slimseo.php';
require $base . 'includes/class-undt-dynamic-bricks.php';
require $base . 'includes/class-undt-dynamic-etch.php';
require $base . 'includes/class-undt-icons.php';
require $base . 'includes/class-undt-transfer.php';

/*
 * Attrappe fuer die Dienste des Social-Icons-Blocks. Wie im Original liefert
 * ein unbekannter Dienst die ganze Liste statt eines Symbols.
 */
function block_core_social_link_services( $service = '', $field = '' ) {
	$data = array(
		'facebook' => array( 'name' => 'Facebook', 'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M1 1h2"></path></svg>' ),
		'x'        => array( 'name' => 'X', 'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M2 2h3"></path></svg>' ),
		'chain'    => array( 'name' => 'Link', 'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M9 9h9"></path></svg>' ),
	);
	if ( '' !== $service && '' !== $field && isset( $data[ $service ] ) ) {
		return $data[ $service ][ $field ];
	}
	return isset( $data[ $service ] ) ? $data[ $service ] : $data;
}
require $base . 'includes/class-undt-updater.php';

/**
 * Setzt die Daten eines Inhaltsmoduls.
 */
function undt_seed_module( $slug, array $data ) {
	$module = UNDT_Modules::get( $slug );
	$GLOBALS['undt_options'][ $module['option'] ] = array();
	UNDT_Content::flush();
	$GLOBALS['undt_options'][ $module['option'] ] = UNDT_Content::sanitize( $slug, $data );
	UNDT_Content::flush();
}

/**
 * Schaltet Module an oder aus.
 */
function undt_set_modules( array $map ) {
	$GLOBALS['undt_options']['undt_settings'] = UNDT_Modules::sanitize_settings( array( 'modules' => $map ) );
	UNDT_Content::flush();
}

/**
 * Setzt Profil und Daten und leert den Laufzeit-Cache.
 */
function undt_seed( array $profile, array $company ) {
	$GLOBALS['undt_options']['undt_profile'] = UNDT_Sanitizer::profile( $profile );
	UNDT_Store::flush();
	$GLOBALS['undt_options']['undt_company'] = array();
	UNDT_Store::flush();
	$GLOBALS['undt_options']['undt_company'] = UNDT_Sanitizer::company( $company );
	UNDT_Store::flush();
}

function undt_head( $t ) { echo "\n\033[1m== $t ==\033[0m\n"; }
function undt_ok( $c, $t ) { echo ( $c ? "  OK   " : "  FAIL " ) . $t . "\n"; if ( ! $c ) { $GLOBALS['undt_fails'] = ( $GLOBALS['undt_fails'] ?? 0 ) + 1; } }
