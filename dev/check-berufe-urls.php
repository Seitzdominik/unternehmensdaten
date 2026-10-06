<?php
/**
 * Prueft alle Adressen in dev/data/berufe.json.
 *
 * Kammern, Regelwerke und Verzeichnisse werden abgerufen. Als in Ordnung gilt
 * Status 200, auch nach Weiterleitungen; landet eine Weiterleitung auf einem
 * anderen Rechner, steht das als Hinweis da, denn dann hat sich meist die
 * Adresse der Kammer geaendert.
 *
 * Bewusst nicht Teil der CI: das Ergebnis haengt von fremden Seiten ab. Einmal
 * im Jahr laufen lassen, zusammen mit einem Blick in die Recherche.
 *
 *     php dev/check-berufe-urls.php
 *
 * Braucht das Programm curl (Windows 10+ und Linux bringen es mit).
 *
 * @package Unternehmensdaten
 */

$undt_json = json_decode( (string) file_get_contents( __DIR__ . '/data/berufe.json' ), true );

if ( ! is_array( $undt_json ) ) {
	fwrite( STDERR, "berufe.json nicht lesbar\n" );
	exit( 1 );
}

$undt_urls = array();

foreach ( $undt_json['kammern'] as $undt_id => $undt_k ) {
	foreach ( array( 'url', 'regeln_url' ) as $undt_key ) {
		if ( ! empty( $undt_k[ $undt_key ] ) ) {
			$undt_urls[ $undt_k[ $undt_key ] ][] = 'Kammer ' . $undt_id;
		}
	}
}

foreach ( $undt_json['regelwerke'] as $undt_id => $undt_r ) {
	if ( ! empty( $undt_r['url'] ) ) {
		$undt_urls[ $undt_r['url'] ][] = 'Regelwerk ' . $undt_id;
	}
}

if ( ! empty( $undt_json['handwerkskammern']['verzeichnis'] ) ) {
	$undt_urls[ $undt_json['handwerkskammern']['verzeichnis'] ][] = 'Handwerk';
}

// Manche Kammerseiten weisen Abrufe ohne Browserkennung ab (403).
$undt_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';
$undt_null  = '\\' === DIRECTORY_SEPARATOR ? 'NUL' : '/dev/null';
$undt_bad   = 0;
$undt_moved = 0;

// Das Ausgabeformat kommt aus einer Datei: cmd.exe wuerde %{…}% sonst als Variable lesen.
$undt_format = tempnam( sys_get_temp_dir(), 'undt' );
file_put_contents( $undt_format, '%{http_code} %{url_effective}' );

foreach ( $undt_urls as $undt_url => $undt_where ) {
	$undt_cmd = sprintf(
		'curl -s -L --max-time 25 -A %1$s -o %2$s -w %3$s %4$s',
		escapeshellarg( $undt_agent ),
		$undt_null,
		escapeshellarg( '@' . $undt_format ),
		escapeshellarg( $undt_url )
	);

	$undt_out  = trim( (string) shell_exec( $undt_cmd ) );
	$undt_part = explode( ' ', $undt_out, 2 );
	$undt_code = (int) $undt_part[0];
	$undt_to   = isset( $undt_part[1] ) ? $undt_part[1] : '';
	$undt_host = static function ( $u ) {
		return preg_replace( '/^www\./', '', (string) parse_url( $u, PHP_URL_HOST ) );
	};

	if ( 200 !== $undt_code ) {
		++$undt_bad;
		printf( "FEHLER %3d  %s  (%s)\n", $undt_code, $undt_url, implode( ', ', $undt_where ) );
	} elseif ( '' !== $undt_to && $undt_host( $undt_to ) !== $undt_host( $undt_url ) ) {
		++$undt_moved;
		printf( "UMZUG       %s -> %s  (%s)\n", $undt_url, $undt_to, implode( ', ', $undt_where ) );
	}
}

unlink( $undt_format );

printf( "\n%d Adressen geprueft, %d Fehler, %d Weiterleitungen auf einen anderen Rechner\n", count( $undt_urls ), $undt_bad, $undt_moved );
exit( $undt_bad > 0 ? 1 : 0 );
