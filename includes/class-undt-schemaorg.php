<?php
/**
 * Strukturierte Daten als JSON-LD.
 *
 * Die Daten stammen vollstaendig aus den bereits gepflegten Stammdaten und
 * Modulen. Es gibt kein zweites Formular fuer dieselben Angaben.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_SchemaOrg
 */
final class UNDT_SchemaOrg {

	/**
	 * Haengt die Ausgabe in den Kopfbereich.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 20 );
	}

	/**
	 * Erkennt ein aktives SEO-Plugin.
	 *
	 * Yoast, Rank Math, SEOPress, AIOSEO und Slim SEO geben bereits eine
	 * Organization-Auszeichnung aus. Zwei Auszeichnungen derselben Entitaet auf
	 * einer Seite sind schlechter als eine.
	 *
	 * @return string Name des erkannten Plugins, sonst Leerstring.
	 */
	public static function detect_seo_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}

		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'Rank Math';
		}

		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'SEOPress';
		}

		if ( function_exists( 'aioseo' ) || defined( 'AIOSEO_VERSION' ) ) {
			return 'All in One SEO';
		}

		// Slim SEO nennt seine Konstante SLIM_SEO_VER. Die laengere Form bleibt als
		// Rueckfall stehen, falls eine kuenftige Fassung sie umbenennt.
		if ( defined( 'SLIM_SEO_VER' ) || defined( 'SLIM_SEO_VERSION' ) ) {
			return 'Slim SEO';
		}

		return '';
	}

	/**
	 * Ob ausgegeben werden soll.
	 *
	 * @return bool
	 */
	public static function should_output() {
		if ( ! UNDT_Modules::is_active( 'seo' ) ) {
			return false;
		}

		$mode = (string) UNDT_Content::value( 'seo', 'output_mode' );

		if ( 'never' === $mode ) {
			return false;
		}

		if ( 'always' === $mode ) {
			return true;
		}

		return '' === self::detect_seo_plugin();
	}

	/**
	 * Kodiert und gibt einen Graphen aus.
	 *
	 * JSON_HEX_TAG verhindert, dass ein Wert die Skriptumgebung verlassen kann.
	 *
	 * @param array $data Datenstruktur.
	 * @return string
	 */
	public static function script( array $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		$json = wp_json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		);

		if ( false === $json ) {
			return '';
		}

		return '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}

	/**
	 * Gibt die Auszeichnung der Organisation aus.
	 *
	 * @return void
	 */
	public static function output() {
		if ( ! self::should_output() ) {
			return;
		}

		$data = self::organization();

		if ( empty( $data ) ) {
			return;
		}

		echo self::script( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in script() kodiert.
	}

	/**
	 * Baut die Auszeichnung der Organisation.
	 *
	 * @return array
	 */
	public static function organization() {
		if ( ! UNDT_Store::has( 'company_name' ) ) {
			return array();
		}

		$type = (string) UNDT_Content::value( 'seo', 'schema_type' );

		if ( ! array_key_exists( $type, UNDT_Modules::schema_types() ) ) {
			$type = 'Organization';
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => $type,
			'@id'      => home_url( '/#organization' ),
			'name'     => UNDT_Store::get( 'company_name' ),
			'url'      => home_url( '/' ),
		);

		$address = self::address();

		if ( ! empty( $address ) ) {
			$data['address'] = $address;
		}

		if ( UNDT_Store::has( 'email' ) ) {
			$data['email'] = UNDT_Store::get( 'email' );
		}

		if ( UNDT_Store::has( 'phone' ) ) {
			$data['telephone'] = UNDT_Store::get( 'phone' );
		}

		if ( UNDT_Store::has( 'fax' ) ) {
			$data['faxNumber'] = UNDT_Store::get( 'fax' );
		}

		if ( UNDT_Store::has( 'vat_id' ) ) {
			$data['vatID'] = UNDT_Store::get( 'vat_id' );
		}

		$logo = self::logo();

		if ( ! empty( $logo ) ) {
			$data['logo']  = $logo;
			$data['image'] = $logo['url'];
		}

		$geo = self::geo();

		if ( ! empty( $geo ) ) {
			$data['geo'] = $geo;
		}

		if ( UNDT_Content::has( 'seo', 'price_range' ) ) {
			$data['priceRange'] = (string) UNDT_Content::value( 'seo', 'price_range' );
		}

		if ( UNDT_Content::has( 'seo', 'area_served' ) ) {
			$data['areaServed'] = (string) UNDT_Content::value( 'seo', 'area_served' );
		}

		if ( UNDT_Content::value( 'seo', 'with_hours' ) && UNDT_Modules::is_active( 'hours' ) ) {
			$hours = self::opening_hours();

			if ( ! empty( $hours ) ) {
				$data['openingHoursSpecification'] = $hours;
			}
		}

		if ( UNDT_Content::value( 'seo', 'with_social' ) && UNDT_Modules::is_active( 'social' ) ) {
			$same_as = self::same_as();

			if ( ! empty( $same_as ) ) {
				$data['sameAs'] = $same_as;
			}
		}

		/**
		 * Erlaubt das Anpassen der Auszeichnung.
		 *
		 * @param array $data Datenstruktur.
		 */
		return (array) apply_filters( 'undt_schema_organization', $data );
	}

	/**
	 * Die Anschrift als PostalAddress.
	 *
	 * @return array
	 */
	private static function address() {
		if ( ! UNDT_Store::has( 'street' ) && ! UNDT_Store::has( 'city' ) ) {
			return array();
		}

		$address = array( '@type' => 'PostalAddress' );

		if ( UNDT_Store::has( 'street' ) ) {
			$address['streetAddress'] = UNDT_Store::get( 'street' );
		}

		if ( UNDT_Store::has( 'postal_code' ) ) {
			$address['postalCode'] = UNDT_Store::get( 'postal_code' );
		}

		if ( UNDT_Store::has( 'city' ) ) {
			$address['addressLocality'] = UNDT_Store::get( 'city' );
		}

		$address['addressCountry'] = self::country_code( UNDT_Store::get( 'country' ) );

		return $address;
	}

	/**
	 * Uebersetzt gaengige Landesnamen in einen ISO-Code.
	 *
	 * @param string $value Freitext aus den Stammdaten.
	 * @return string
	 */
	private static function country_code( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			// Das Plugin bildet deutsches Recht ab, DE ist die sinnvolle Annahme.
			return 'DE';
		}

		if ( preg_match( '/^[A-Za-z]{2}$/', $value ) ) {
			return strtoupper( $value );
		}

		$map = array(
			'deutschland' => 'DE',
			'germany'     => 'DE',
			'österreich'  => 'AT',
			'oesterreich' => 'AT',
			'austria'     => 'AT',
			'schweiz'     => 'CH',
			'switzerland' => 'CH',
			'suisse'      => 'CH',
			'luxemburg'   => 'LU',
			'luxembourg'  => 'LU',
			'belgien'     => 'BE',
			'niederlande' => 'NL',
		);

		$key = strtolower( $value );

		return isset( $map[ $key ] ) ? $map[ $key ] : $value;
	}

	/**
	 * Das Logo als ImageObject.
	 *
	 * @return array
	 */
	private static function logo() {
		$id = (int) UNDT_Content::value( 'seo', 'logo' );

		if ( $id <= 0 ) {
			return array();
		}

		$src = wp_get_attachment_image_src( $id, 'full' );

		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			return array();
		}

		return array(
			'@type'  => 'ImageObject',
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
		);
	}

	/**
	 * Die Koordinaten als GeoCoordinates.
	 *
	 * @return array
	 */
	private static function geo() {
		$lat = str_replace( ',', '.', trim( (string) UNDT_Content::value( 'seo', 'geo_lat' ) ) );
		$lng = str_replace( ',', '.', trim( (string) UNDT_Content::value( 'seo', 'geo_lng' ) ) );

		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return array();
		}

		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $lat,
			'longitude' => (float) $lng,
		);
	}

	/**
	 * Die Oeffnungszeiten als openingHoursSpecification.
	 *
	 * Tage mit gleichen Zeiten werden zusammengefasst, wie es die Spezifikation
	 * vorsieht.
	 *
	 * @return array
	 */
	private static function opening_hours() {
		if ( ! UNDT_Hours::has_data() ) {
			return array();
		}

		$buckets = array();

		foreach ( UNDT_Hours::day_keys() as $day ) {
			foreach ( UNDT_Hours::slots( $day ) as $slot ) {
				$key = $slot['from'] . '-' . $slot['to'];

				if ( ! isset( $buckets[ $key ] ) ) {
					$buckets[ $key ] = array(
						'opens'  => $slot['from'],
						'closes' => $slot['to'],
						'days'   => array(),
					);
				}

				$buckets[ $key ]['days'][] = UNDT_Hours::schema_day( $day );
			}
		}

		$out = array();

		foreach ( $buckets as $bucket ) {
			$out[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => array_values( array_unique( $bucket['days'] ) ),
				'opens'     => $bucket['opens'],
				'closes'    => $bucket['closes'],
			);
		}

		return $out;
	}

	/**
	 * Die Social-Profile als sameAs.
	 *
	 * @return array
	 */
	private static function same_as() {
		$urls = array();

		foreach ( UNDT_Content::rows( 'social', 'items' ) as $row ) {
			$url = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';

			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Die Auszeichnung der Fragen und Antworten.
	 *
	 * Wird unmittelbar an der Stelle ausgegeben, an der der Shortcode steht.
	 * JSON-LD darf ueberall im Dokument stehen, und der Shortcode laeuft erst
	 * nach dem Kopfbereich.
	 *
	 * @param array $groups Gruppierte Zeilen.
	 * @return string
	 */
	public static function faq_script( array $groups ) {
		if ( ! UNDT_Content::value( 'faq', 'schema' ) ) {
			return '';
		}

		$entities = array();

		foreach ( $groups as $items ) {
			foreach ( $items as $item ) {
				$question = isset( $item['question'] ) ? trim( (string) $item['question'] ) : '';
				$answer   = isset( $item['answer'] ) ? trim( (string) $item['answer'] ) : '';

				if ( '' === $question || '' === $answer ) {
					continue;
				}

				$entities[] = array(
					'@type'          => 'Question',
					'name'           => $question,
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $answer,
					),
				);
			}
		}

		if ( empty( $entities ) ) {
			return '';
		}

		return self::script(
			array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => $entities,
			)
		);
	}
}
