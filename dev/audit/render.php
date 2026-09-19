<?php
/**
 * Rendert eine Frontend-Adresse in-process ueber index.php und legt das HTML
 * unter dev/audit/html-<name>-<slug>.html ab. Ersetzt den verschachtelten
 * HTTP-Abruf, der in Playground nicht zuverlaessig ist.
 *
 * Erwartet $undt_uri (etwa '/alle-bloecke/') und $undt_out (Dateikuerzel).
 * Das Mu-Plugin schaltet bei gesetzter Konstante UNDT_AUDIT_RENDER die
 * kanonische Umleitung ab. Die Datei wird auch dann geschrieben, wenn
 * WordPress den Prozess per exit beendet.
 */
define( 'UNDT_AUDIT_RENDER', true );

$undt_name = file_exists( '/wordpress/undt-audit-name.txt' )
	? trim( (string) file_get_contents( '/wordpress/undt-audit-name.txt' ) )
	: 'default';

$GLOBALS['undt_render_out']   = '/wordpress/build/dev/audit/html-' . $undt_name . '-' . $undt_out . '.html';
$GLOBALS['undt_render_level'] = ob_get_level();
$GLOBALS['undt_render_done']  = false;

$_SERVER['REQUEST_URI']     = $undt_uri;
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['QUERY_STRING']    = '';
// is_login() vergleicht SCRIPT_NAME mit der Login-Adresse; ein leerer Wert
// gilt als Treffer, und Autoptimize puffert dann nicht.
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = '/wordpress/index.php';
$_GET                       = array();

function undt_render_finish( $how ) {
	if ( $GLOBALS['undt_render_done'] ) {
		return;
	}
	$GLOBALS['undt_render_done'] = true;

	// Autoptimize und aehnliche Plugins oeffnen eigene Puffer; alle schliessen,
	// damit ihre Callbacks laufen und das Ergebnis im aeusseren Puffer landet.
	while ( ob_get_level() > $GLOBALS['undt_render_level'] + 1 ) {
		ob_end_flush();
	}

	$html = ob_get_level() > $GLOBALS['undt_render_level'] ? ob_get_clean() : '';
	$meta = '<!-- undt-render: ' . $how . ' | headers: ' . implode( ' | ', headers_list() ) . ' -->' . "\n";

	file_put_contents( $GLOBALS['undt_render_out'], $meta . $html );
}

register_shutdown_function(
	function () {
		undt_render_finish( 'shutdown' );
	}
);

ob_start();

require '/wordpress/index.php';

undt_render_finish( 'normal' );
