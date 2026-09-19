<?php
/**
 * Mess-Mu-Plugin fuer das Audit: zaehlt je Request Datenbankabfragen und
 * __()-Aufrufe der Textdomain unternehmensdaten und haengt sie an eine Datei.
 * Wird vom Blueprint nach wp-content/mu-plugins kopiert. Kein Teil des Plugins.
 */
if ( ! defined( 'UNDT_AUDIT_NAME' ) ) {
	// Der Name kommt aus der Datei, die der erste Blueprint-Schritt schreibt.
	define(
		'UNDT_AUDIT_NAME',
		file_exists( '/wordpress/undt-audit-name.txt' ) ? trim( (string) file_get_contents( '/wordpress/undt-audit-name.txt' ) ) : 'default'
	);
}

$GLOBALS['undt_audit_gettext'] = 0;

/*
 * Beim In-Process-Rendern (render.php) darf WordPress nicht per Umleitung
 * aussteigen: redirect_canonical() beendet den Prozess nur, wenn wp_redirect()
 * wahr liefert.
 */
if ( defined( 'UNDT_AUDIT_RENDER' ) ) {
	add_filter( 'redirect_canonical', '__return_false' );
	add_filter( 'wp_redirect', '__return_false' );
}

add_filter(
	'gettext',
	function ( $translation, $text, $domain ) {
		if ( 'unternehmensdaten' === $domain ) {
			$GLOBALS['undt_audit_gettext']++;
		}
		return $translation;
	},
	10,
	3
);

add_action(
	'shutdown',
	function () {
		global $wpdb;

		if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
			return;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'cli';
		$all  = is_array( $wpdb->queries ) ? $wpdb->queries : array();
		$undt = array();
		$post = array();

		foreach ( $all as $q ) {
			$sql = preg_replace( '/\s+/', ' ', $q[0] );
			if ( false !== stripos( $sql, 'undt' ) ) {
				$undt[] = $sql;
			}
			if ( preg_match( '/FROM \w*posts WHERE ID/i', $sql ) ) {
				$post[] = $sql;
			}
		}

		$line = sprintf(
			"%s | plugin_active=%s | queries=%d | undt-queries=%d | single-post-lookups=%d | gettext(unternehmensdaten)=%d\n%s%s",
			$uri,
			class_exists( 'UNDT_Store' ) ? 'yes' : 'no',
			count( $all ),
			count( $undt ),
			count( $post ),
			$GLOBALS['undt_audit_gettext'],
			$undt ? '  UNDT: ' . implode( "\n  UNDT: ", $undt ) . "\n" : '',
			$post ? '  POST: ' . implode( "\n  POST: ", $post ) . "\n" : ''
		);

		file_put_contents( '/wordpress/build/dev/audit/requests-' . UNDT_AUDIT_NAME . '.log', $line, FILE_APPEND );
	},
	9999
);
