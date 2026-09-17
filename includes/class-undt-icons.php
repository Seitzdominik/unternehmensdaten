<?php
/**
 * SVG-Symbole fuer Backend und Ausgabe.
 *
 * Die Plattform-Symbole stammen aus dem Social-Icons-Block von WordPress und
 * werden zur Laufzeit von dort geholt: das Plugin bringt dafuer keine eigenen
 * Markenzeichen mit und zieht bei Aktualisierungen von WordPress mit. Die Logos
 * von Bricks und Etch fuer die Kopierknoepfe hat der Betreiber beigesteuert.
 *
 * Alle Symbole uebernehmen Groesse und Farbe vom umgebenden Text.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Icons
 */
final class UNDT_Icons {

	/**
	 * Plattformen des Social-Moduls und ihr Dienst im Social-Icons-Block.
	 *
	 * Was hier fehlt, etwa Xing oder kununu, bekommt das Link-Symbol.
	 */
	const PLATFORM_SERVICES = array(
		'facebook'  => 'facebook',
		'instagram' => 'instagram',
		'linkedin'  => 'linkedin',
		'x'         => 'x',
		'youtube'   => 'youtube',
		'tiktok'    => 'tiktok',
		'pinterest' => 'pinterest',
		'whatsapp'  => 'whatsapp',
		'threads'   => 'threads',
		'bluesky'   => 'bluesky',
		'mastodon'  => 'mastodon',
		'github'    => 'github',
		'spotify'   => 'spotify',
		'telegram'  => 'telegram',
		'vimeo'     => 'vimeo',
	);

	/**
	 * Bereits aufbereitete Plattform-Symbole.
	 *
	 * @var array
	 */
	private static $platforms = array();

	/**
	 * Die Dienste des Social-Icons-Blocks.
	 *
	 * @var array|null
	 */
	private static $services = null;

	/**
	 * Ein eigenes Symbol.
	 *
	 * @param string $name  bricks, etch oder external.
	 * @param string $class Klasse am svg-Element.
	 * @return string SVG-Markup, leer bei unbekanntem Namen.
	 */
	public static function svg( $name, $class = '' ) {
		$filled = 'fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"';

		$icons = array(
			'bricks'   => array(
				'0 0 79 101',
				$filled,
				'<g transform="matrix(1,0,0,1,-38.768425,-26.866258)"><g transform="matrix(0.323032,0,0,0.329659,-343.308979,-501.545633)"><path d="M1184.457,1902.99C1183.179,1902.99 1182.83,1902.559 1182.83,1901.415C1182.83,1901.218 1182.785,1900.962 1182.785,1614.043C1182.785,1611.505 1182.817,1609.91 1185.554,1609.558C1185.974,1609.504 1241.329,1603.293 1244.883,1602.943C1249.1,1602.528 1252.326,1605.568 1252.326,1607.19C1252.326,1608.397 1252.369,1614.111 1252.367,1695.511C1256.542,1693.201 1259.488,1689.321 1279.579,1682.698C1296.924,1676.98 1331.526,1672.822 1363.56,1688.193C1398.39,1704.906 1419.493,1738.401 1424.591,1771.346C1429.328,1801.955 1424.118,1836.683 1405.01,1862.231C1396.966,1872.985 1388.237,1883.251 1370.161,1894.093C1351.028,1905.569 1313.721,1914.915 1278.622,1902.276C1255.098,1893.805 1243.456,1880.29 1242.914,1879.689C1241.771,1879.689 1241.618,1879.688 1241.618,1879.688C1241.618,1879.688 1241.581,1898.564 1241.581,1900.574C1241.581,1902.361 1241.445,1902.836 1239.544,1902.836C1235.848,1902.836 1186.348,1902.99 1184.457,1902.99ZM1249.095,1802.58C1253.181,1836.662 1283.307,1854.999 1314.968,1848.953C1339.577,1844.254 1349.312,1825.765 1351.832,1821.022C1360.591,1804.532 1364.316,1765.242 1333.932,1744.495C1319.252,1734.471 1295.844,1732.878 1279.269,1740.655C1275.723,1742.318 1243.681,1756.075 1249.095,1802.58Z"/></g></g>',
			),
			'etch'     => array(
				'0 0 110 87',
				$filled,
				'<g transform="matrix(1,0,0,1,-28.99555,-32.488924)"><path d="M57.898,118.99C57.48,119.112 57.038,119.178 56.581,119.178L33.708,119.178C31.107,119.178 28.996,117.066 28.996,114.465L28.996,101.806C28.996,99.205 31.107,97.094 33.708,97.094L56.581,97.094C56.848,97.094 57.111,97.116 57.366,97.159C57.588,97.116 57.814,97.094 58.047,97.094C78.391,97.094 67.405,67.169 87.526,64.924C87.884,64.837 88.257,64.791 88.641,64.791L134.149,64.791C136.75,64.791 138.862,66.903 138.862,69.504L138.862,82.163C138.862,84.764 136.75,86.875 134.149,86.875L90.024,86.875C70.607,86.875 76.669,119.178 59.546,119.178C58.959,119.178 58.411,119.113 57.898,118.99ZM54.786,86.875L33.708,86.875C31.107,86.875 28.996,84.764 28.996,82.163L28.996,69.504C28.996,66.903 31.107,64.791 33.708,64.791L53.012,64.791C69.752,64.791 55.072,33.888 82.054,32.535C82.269,32.505 82.489,32.489 82.712,32.489L134.149,32.489C136.75,32.489 138.862,34.6 138.862,37.201L138.862,49.86C138.862,52.461 136.75,54.573 134.149,54.573L83.929,54.573C60.498,54.573 66.007,82.988 56.59,86.517C56.035,86.748 55.425,86.875 54.786,86.875ZM134.149,97.094C136.75,97.094 138.862,99.205 138.862,101.806L138.862,114.465C138.862,117.066 136.75,119.178 134.149,119.178L88.641,119.178C86.04,119.178 83.929,117.066 83.929,114.465L83.929,101.806C83.929,99.205 86.04,97.094 88.641,97.094L134.149,97.094Z"/></g>',
			),
			// Schraeger Pfeil fuer Links, die einen neuen Tab oeffnen.
			'external' => array(
				'0 0 24 24',
				'fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"',
				'<path d="M7 17 17 7M9 7h8v8"/>',
			),
		);

		if ( ! isset( $icons[ $name ] ) ) {
			return '';
		}

		list( $box, $paint, $body ) = $icons[ $name ];

		return sprintf(
			'<svg%1$s viewBox="%2$s" width="1em" height="1em" %3$s aria-hidden="true" focusable="false">%4$s</svg>',
			'' === $class ? '' : ' class="' . esc_attr( $class ) . '"',
			$box,
			$paint,
			$body
		);
	}

	/**
	 * Das Symbol einer Plattform.
	 *
	 * @param string $platform Plattform-Schluessel aus UNDT_Modules::platforms().
	 * @return string SVG-Markup, leer, wenn WordPress keine Symbole liefert.
	 */
	public static function platform( $platform ) {
		$platform = (string) $platform;

		if ( ! isset( self::$platforms[ $platform ] ) ) {
			$map     = self::PLATFORM_SERVICES;
			$service = isset( $map[ $platform ] ) ? $map[ $platform ] : 'chain';
			$svg     = self::core_icon( $service );

			// X heisst in aelteren WordPress-Fassungen noch twitter.
			if ( '' === $svg && 'x' === $service ) {
				$svg = self::core_icon( 'twitter' );
			}

			if ( '' === $svg ) {
				$svg = self::core_icon( 'chain' );
			}

			/**
			 * Erlaubt ein eigenes Symbol fuer eine Plattform.
			 *
			 * @param string $svg      SVG-Markup.
			 * @param string $platform Plattform-Schluessel.
			 */
			$svg = apply_filters( 'undt_social_icon', $svg, $platform );

			self::$platforms[ $platform ] = self::prepare( is_string( $svg ) ? $svg : '' );
		}

		return self::$platforms[ $platform ];
	}

	/**
	 * Das Symbol eines Dienstes aus dem Social-Icons-Block.
	 *
	 * @param string $service Dienst, etwa facebook.
	 * @return string
	 */
	private static function core_icon( $service ) {
		if ( null === self::$services ) {
			self::$services = function_exists( 'block_core_social_link_services' ) ? (array) block_core_social_link_services() : array();
		}

		return isset( self::$services[ $service ]['icon'] ) && is_string( self::$services[ $service ]['icon'] )
			? self::$services[ $service ]['icon']
			: '';
	}

	/**
	 * Bereitet fremdes SVG fuer die Ausgabe auf.
	 *
	 * Groesse und Farbe folgen dem Text, und es bleiben nur Elemente und
	 * Attribute, die ein Symbol braucht.
	 *
	 * @param string $svg SVG-Markup.
	 * @return string
	 */
	private static function prepare( $svg ) {
		$svg = trim( $svg );

		if ( 0 !== strpos( $svg, '<svg' ) ) {
			return '';
		}

		$svg = (string) preg_replace_callback(
			'/^<svg\b[^>]*>/',
			static function ( $match ) {
				$tag = (string) preg_replace( '/\s(?:width|height|fill|class|role|aria-hidden|focusable)="[^"]*"/', '', $match[0] );

				return '<svg width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false"' . substr( $tag, 4 );
			},
			$svg
		);

		return wp_kses( $svg, self::allowed() );
	}

	/**
	 * Die erlaubten Elemente und Attribute eines Symbols.
	 *
	 * @return array
	 */
	public static function allowed() {
		$paint = array(
			'fill'            => true,
			'fill-rule'       => true,
			'clip-rule'       => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'opacity'         => true,
			'transform'       => true,
		);

		return array(
			'svg'      => array_merge(
				$paint,
				array(
					'xmlns'       => true,
					'viewbox'     => true,
					'width'       => true,
					'height'      => true,
					'version'     => true,
					'class'       => true,
					'aria-hidden' => true,
					'focusable'   => true,
				)
			),
			'g'        => $paint,
			'path'     => array_merge( $paint, array( 'd' => true ) ),
			'circle'   => array_merge( $paint, array( 'cx' => true, 'cy' => true, 'r' => true ) ),
			'ellipse'  => array_merge( $paint, array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ) ),
			'rect'     => array_merge( $paint, array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ) ),
			'line'     => array_merge( $paint, array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ) ),
			'polygon'  => array_merge( $paint, array( 'points' => true ) ),
			'polyline' => array_merge( $paint, array( 'points' => true ) ),
		);
	}

	/**
	 * Leert den Zwischenspeicher, etwa nach einer Aenderung am Filter.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$platforms = array();
		self::$services  = null;
	}
}
