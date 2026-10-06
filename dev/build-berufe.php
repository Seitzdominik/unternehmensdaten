<?php
/**
 * Erzeugt includes/data/berufe.php aus dev/data/berufe.json.
 *
 * Die JSON-Datei ist die Quelle: dort stehen Berufe, Kammern und Regelwerke
 * samt Quelle und Pruefvermerk, so wie die Recherche sie liefert. Das Plugin
 * braucht davon nur einen Teil und liest ihn als PHP-Array, ohne json_decode
 * bei jedem Aufruf.
 *
 *     php dev/build-berufe.php          schreibt die Datei
 *     php dev/build-berufe.php --check  prueft nur, ob sie zur JSON passt
 *
 * @package Unternehmensdaten
 */

$undt_root   = dirname( __DIR__ );
$undt_plugin = is_file( $undt_root . '/unternehmensdaten/unternehmensdaten.php' ) ? $undt_root . '/unternehmensdaten' : $undt_root;
$undt_source = __DIR__ . '/data/berufe.json';
$undt_target = $undt_plugin . '/includes/data/berufe.php';

$undt_json = json_decode( (string) file_get_contents( $undt_source ), true );

if ( ! is_array( $undt_json ) || empty( $undt_json['berufe'] ) ) {
	fwrite( STDERR, "berufe.json fehlt oder ist kein gueltiges JSON\n" );
	exit( 1 );
}

/**
 * Der Hilfetext zu einem Kammergebiet, fuer die Auswahl bei mehreren Kammern.
 *
 * @param array $kammer Kammer aus der JSON.
 * @param array $laender Kuerzel => Name.
 * @return string
 */
function undt_build_hilfe( array $kammer, array $laender ) {
	if ( ! empty( $kammer['gebiet_hilfe'] ) ) {
		return (string) $kammer['gebiet_hilfe'];
	}

	$gebiet = isset( $kammer['gebiet'] ) && is_array( $kammer['gebiet'] ) ? $kammer['gebiet'] : array();

	if ( ! empty( $gebiet['bundesweit'] ) ) {
		return 'Ganz Deutschland';
	}

	if ( ! empty( $gebiet['regierungsbezirke'] ) ) {
		return ( 1 === count( $gebiet['regierungsbezirke'] ) ? 'Regierungsbezirk ' : 'Regierungsbezirke ' ) . implode( ', ', $gebiet['regierungsbezirke'] );
	}

	if ( ! empty( $gebiet['olg'] ) ) {
		return 'OLG-Bezirk ' . ( is_array( $gebiet['olg'] ) ? implode( ', ', $gebiet['olg'] ) : $gebiet['olg'] );
	}

	if ( ! empty( $gebiet['teil'] ) ) {
		return (string) $gebiet['teil'];
	}

	$namen = array();

	foreach ( isset( $gebiet['laender'] ) ? (array) $gebiet['laender'] : array() as $land ) {
		$namen[] = isset( $laender[ $land ] ) ? $laender[ $land ] : $land;
	}

	return implode( ', ', $namen );
}

$undt_laender = isset( $undt_json['laender'] ) ? $undt_json['laender'] : array();
$undt_data    = array(
	'stand'         => (string) $undt_json['stand'],
	'regelwerke'    => array(),
	'kammern'       => array(),
	'verzeichnisse' => array(),
	'berufe'        => array(),
	'erlaubnis'     => array(),
	'handwerk'      => array(
		'verzeichnis' => isset( $undt_json['handwerkskammern']['verzeichnis'] ) ? (string) $undt_json['handwerkskammern']['verzeichnis'] : '',
	),
);

foreach ( $undt_json['regelwerke'] as $undt_id => $undt_r ) {
	$undt_data['regelwerke'][ $undt_id ] = array(
		'name' => (string) $undt_r['name'],
		'kurz' => isset( $undt_r['kurz'] ) ? (string) $undt_r['kurz'] : '',
		'url'  => isset( $undt_r['url'] ) ? (string) $undt_r['url'] : '',
		'ok'   => ! empty( $undt_r['verifiziert'] ),
	);
}

$undt_quellen = array();

foreach ( $undt_json['kammern'] as $undt_id => $undt_k ) {
	$undt_regeln = array();

	foreach ( isset( $undt_k['regeln'] ) ? $undt_k['regeln'] : array() as $undt_regel ) {
		if ( isset( $undt_regel['ref'] ) ) {
			$undt_regeln[] = array( 'ref' => (string) $undt_regel['ref'] );
		} elseif ( isset( $undt_regel['name'] ) ) {
			$undt_regeln[] = array(
				'name' => (string) $undt_regel['name'],
				'ok'   => ! isset( $undt_regel['titel_exakt'] ) || ! empty( $undt_regel['titel_exakt'] ),
			);
		}
	}

	$undt_gruppen = array_values( (array) $undt_k['gruppe'] );

	$undt_data['kammern'][ $undt_id ] = array(
		'name'       => (string) $undt_k['name'],
		'url'        => isset( $undt_k['url'] ) ? (string) $undt_k['url'] : '',
		'gruppen'    => $undt_gruppen,
		'laender'    => isset( $undt_k['gebiet']['laender'] ) ? array_values( (array) $undt_k['gebiet']['laender'] ) : array(),
		'bundesweit' => ! empty( $undt_k['gebiet']['bundesweit'] ),
		'hilfe'      => undt_build_hilfe( $undt_k, $undt_laender ),
		'regeln'     => $undt_regeln,
		'regeln_url' => isset( $undt_k['regeln_url'] ) ? (string) $undt_k['regeln_url'] : '',
		'ok'         => ! empty( $undt_k['verifiziert'] ),
	);

	// Das Verzeichnis einer Gruppe ist die Liste der Bundeskammer, an der die Kammern geprueft wurden.
	$undt_quelle = isset( $undt_k['quelle'] ) ? $undt_k['quelle'] : '';
	$undt_quelle = is_array( $undt_quelle ) ? (string) reset( $undt_quelle ) : (string) $undt_quelle;

	foreach ( $undt_gruppen as $undt_g ) {
		if ( '' !== $undt_quelle ) {
			$undt_quellen[ $undt_g ][ $undt_quelle ] = isset( $undt_quellen[ $undt_g ][ $undt_quelle ] ) ? $undt_quellen[ $undt_g ][ $undt_quelle ] + 1 : 1;
		}
	}
}

foreach ( $undt_quellen as $undt_g => $undt_urls ) {
	arsort( $undt_urls );
	$undt_data['verzeichnisse'][ $undt_g ] = (string) key( $undt_urls );
}

foreach ( $undt_json['berufe'] as $undt_b ) {
	$undt_titel = array( array( (string) $undt_b['bezeichnung']['w'], (string) $undt_b['bezeichnung']['m'] ) );

	foreach ( isset( $undt_b['alt_bezeichnungen'] ) ? $undt_b['alt_bezeichnungen'] : array() as $undt_alt ) {
		$undt_titel[] = array( (string) $undt_alt['w'], (string) $undt_alt['m'] );
	}

	$undt_data['berufe'][ (string) $undt_b['id'] ] = array(
		'titel'          => $undt_titel,
		'gruppe'         => (string) $undt_b['gruppe'],
		'zustaendigkeit' => (string) $undt_b['zustaendigkeit'],
		'kammer_gruppe'  => isset( $undt_b['kammer_gruppe'] ) ? (string) $undt_b['kammer_gruppe'] : '',
		'regeln_bund'    => isset( $undt_b['regeln_bund'] ) ? array_values( $undt_b['regeln_bund'] ) : array(),
		'regeln_land'    => isset( $undt_b['regeln_land'] ) ? (string) $undt_b['regeln_land'] : '',
		'regeln_url'     => isset( $undt_b['regeln_url'] ) ? (string) $undt_b['regeln_url'] : '',
	);
}

foreach ( $undt_json['erlaubnispflichtig'] as $undt_e ) {
	$undt_data['erlaubnis'][ (string) $undt_e['id'] ] = array(
		'titel'       => array( (string) $undt_e['bezeichnung']['w'], (string) $undt_e['bezeichnung']['m'] ),
		'aufsicht'    => (string) $undt_e['aufsicht'],
		'regeln_bund' => isset( $undt_e['regeln_bund'] ) ? array_values( $undt_e['regeln_bund'] ) : array(),
		'register'    => isset( $undt_e['register'] ) ? (string) $undt_e['register'] : '',
	);
}

/**
 * Schreibt einen Wert als kompakten PHP-Ausdruck.
 *
 * var_export rueckt jede Ebene ein und verdreifacht damit die Datei. Hier steht
 * jeder Eintrag der zweiten Ebene in einer eigenen Zeile, darunter ohne
 * Leerraum. Listen ohne Schluessel bleiben ohne Schluessel.
 *
 * @param mixed $value Wert.
 * @param int   $depth Ebene.
 * @return string
 */
function undt_build_export( $value, $depth = 0 ) {
	if ( ! is_array( $value ) ) {
		return var_export( $value, true );
	}

	$list  = array_keys( $value ) === range( 0, count( $value ) - 1 );
	$parts = array();

	foreach ( $value as $key => $item ) {
		$parts[] = ( $list ? '' : var_export( $key, true ) . '=>' ) . undt_build_export( $item, $depth + 1 );
	}

	if ( $depth < 2 && ! $list && ! empty( $parts ) ) {
		$pad = str_repeat( "\t", $depth + 1 );

		return "array(\n" . $pad . implode( ",\n" . $pad, $parts ) . ",\n" . str_repeat( "\t", $depth ) . ')';
	}

	return 'array(' . implode( ',', $parts ) . ')';
}

$undt_php = "<?php\n"
	. "/**\n"
	. " * Berufe, Kammern und Regelwerke fuer die Vorschlaege auf der Seite Firma.\n"
	. " *\n"
	. " * Erzeugt von dev/build-berufe.php aus dev/data/berufe.json. Nicht von Hand\n"
	. " * aendern: die JSON-Datei ist die Quelle, mit Quellenangabe je Eintrag.\n"
	. " *\n"
	. " * @package Unternehmensdaten\n"
	. " */\n\n"
	. "defined( 'ABSPATH' ) || exit;\n\n"
	. 'return ' . undt_build_export( $undt_data ) . ";\n";

$undt_php = str_replace( "\r\n", "\n", $undt_php );

if ( in_array( '--check', $argv, true ) ) {
	$undt_current = is_file( $undt_target ) ? str_replace( "\r\n", "\n", (string) file_get_contents( $undt_target ) ) : '';

	if ( $undt_current !== $undt_php ) {
		fwrite( STDERR, "includes/data/berufe.php passt nicht zu dev/data/berufe.json. php dev/build-berufe.php ausfuehren.\n" );
		exit( 1 );
	}

	echo "berufe.php passt zur JSON\n";
	exit( 0 );
}

if ( ! is_dir( dirname( $undt_target ) ) ) {
	mkdir( dirname( $undt_target ), 0755, true );
}

file_put_contents( $undt_target, $undt_php );
echo 'geschrieben: includes/data/berufe.php, ' . strlen( $undt_php ) . " Bytes\n";
