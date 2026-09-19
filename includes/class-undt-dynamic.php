<?php
/**
 * Stammdaten als dynamische Daten, unabhaengig vom Werkzeug.
 *
 * Diese Klasse beantwortet drei Fragen: welche Werte es gibt (fields), wie
 * ein Werkzeug sie schreibt (syntax) und was gerade darin steht (value). Wer
 * sie abholt, steht je in einer eigenen Klasse:
 *
 *   Slim SEO  {{ undt.phone }}      UNDT_Dynamic_SlimSeo
 *   Bricks    {undt_phone}          UNDT_Dynamic_Bricks
 *   Etch      {options.undt.phone}  UNDT_Dynamic_Etch
 *
 * Die Trennung hat einen praktischen Grund: die Eigenheiten der Werkzeuge
 * aendern sich haeufiger als die Werte, und ein weiteres Werkzeug kostet dann
 * nur eine weitere kleine Klasse.
 *
 * Die Haken sind immer registriert. Ohne das jeweilige Werkzeug ruft sie
 * niemand auf, und die Ladereihenfolge der Plugins spielt keine Rolle.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Dynamic
 */
final class UNDT_Dynamic {

	/**
	 * Werte fuer SEO-Angaben: nur, was sich allein mit den Stammdaten aendert.
	 */
	const CONTEXT_SEO = 'seo';

	/**
	 * Werte fuer Builder: zusaetzlich fertige Links, tagesabhaengige Angaben und
	 * das Infobanner.
	 */
	const CONTEXT_BUILDER = 'builder';

	/**
	 * Werte fuer die strukturierten Daten von Slim SEO Pro: wie SEO, dazu die
	 * Adressen der Social-Profile fuer sameAs und die Adresse des Logos.
	 */
	const CONTEXT_SCHEMA = 'schema';

	/**
	 * Ja-Nein-Werte. Bricks bekommt 1 oder einen Leerstring, Etch echte
	 * Wahrheitswerte fuer seine Bedingungen.
	 */
	const FLAGS = array( 'is_open', 'banner_show', 'banner_dismissible' );

	/**
	 * Werte, die aus mehreren Angaben bestehen.
	 *
	 * Die Schema-Ausgabe kann damit umgehen: steht so ein Wert in einem Feld,
	 * das sich vervielfaeltigen laesst, wird aus jeder Angabe ein Eintrag. Genau
	 * das braucht sameAs. Ueberall sonst stehen sie als Aufzaehlung.
	 */
	const LISTS = array( 'social_profiles', 'hours_days', 'hours_opens', 'hours_closes' );

	/**
	 * Meldet die Werte bei allen Werkzeugen an.
	 *
	 * @return void
	 */
	public static function register() {
		UNDT_Dynamic_SlimSeo::register();
		UNDT_Dynamic_Bricks::register();
		UNDT_Dynamic_Etch::register();
	}

	/**
	 * Die angebotenen Werte mit ihrer Beschriftung.
	 *
	 * Enthalten sind alle Stammdaten, die es auch als [undt key="…"] gibt und die
	 * beim aktuellen Profil gelten, dazu die Anschrift in einer Zeile. Builder
	 * bekommen zusaetzlich fertige tel:- und mailto:-Links und die
	 * tagesabhaengigen Oeffnungsangaben, die in einer Meta-Beschreibung nichts
	 * verloren haben.
	 *
	 * @param string $context CONTEXT_SEO, CONTEXT_BUILDER oder CONTEXT_SCHEMA.
	 * @return array Schluessel => Beschriftung.
	 */
	public static function fields( $context = self::CONTEXT_SEO ) {
		$builder = self::CONTEXT_BUILDER === $context;
		$schema  = self::CONTEXT_SCHEMA === $context;
		$profile = UNDT_Store::profile();
		$fields  = array();

		foreach ( UNDT_Schema::fields() as $key => $field ) {
			if ( empty( $field['shortcode'] ) || ! UNDT_Schema::applies( $field['when'], $profile ) ) {
				continue;
			}

			if ( 'page' === $field['type'] ) {
				/* translators: %s: Feldbezeichnung, etwa „Seite: Impressum“. */
				$fields[ $key ] = sprintf( __( '%s (Link)', 'unternehmensdaten' ), $field['label'] );
				continue;
			}

			$fields[ $key ] = $field['label'];

			if ( $builder && in_array( $field['type'], array( 'tel', 'email' ), true ) ) {
				/* translators: %s: Feldbezeichnung, etwa „Telefon“. */
				$fields[ $key . '_link' ] = sprintf( __( '%s (Link)', 'unternehmensdaten' ), $field['label'] );
			}
		}

		$fields['address'] = __( 'Anschrift in einer Zeile', 'unternehmensdaten' );

		/*
		 * Fuer die strukturierten Daten: die Profile als sameAs und das Logo als
		 * Adresse. Beides steht sonst nur in der eigenen Auszeichnung, und wer sie
		 * Slim SEO ueberlaesst, pflegte es bisher zweimal.
		 */
		if ( ( $builder || $schema ) && UNDT_Modules::is_active( 'seo' ) ) {
			$fields['logo_url'] = __( 'Logo (Link)', 'unternehmensdaten' );
		}

		if ( $schema && UNDT_Modules::is_active( 'social' ) ) {
			$fields['social_profiles'] = __( 'Social-Profile, alle Adressen (Link)', 'unternehmensdaten' );
		}

		/*
		 * Drei gleich lange Listen fuer openingHoursSpecification: je Tag und
		 * Zeitfenster eine Zeile. Slim SEO baut daraus so viele Eintraege, wie die
		 * Listen lang sind, und nimmt aus jeder den passenden Wert.
		 */
		if ( $schema && UNDT_Modules::is_active( 'hours' ) ) {
			$fields['hours_days']   = __( 'Öffnungszeiten: Wochentage (Liste)', 'unternehmensdaten' );
			$fields['hours_opens']  = __( 'Öffnungszeiten: Beginn (Liste)', 'unternehmensdaten' );
			$fields['hours_closes'] = __( 'Öffnungszeiten: Ende (Liste)', 'unternehmensdaten' );
		}

		if ( $builder && UNDT_Modules::is_active( 'hours' ) ) {
			$fields['hours_today'] = __( 'Heutige Öffnungszeit', 'unternehmensdaten' );
			$fields['open_now']    = __( 'Geöffnet-Status', 'unternehmensdaten' );
			$fields['is_open']     = __( 'Geöffnet (ja/nein)', 'unternehmensdaten' );
		}

		// Das Banner, um es in Bricks oder Etch selbst zu gestalten.
		if ( $builder && UNDT_Modules::is_active( 'banner' ) ) {
			$fields['banner_show']        = __( 'Banner: anzeigen (ja/nein)', 'unternehmensdaten' );
			$fields['banner_type']        = __( 'Banner: Art', 'unternehmensdaten' );
			$fields['banner_text']        = __( 'Banner: Text', 'unternehmensdaten' );
			$fields['banner_link_text']   = __( 'Banner: Link-Text', 'unternehmensdaten' );
			$fields['banner_link_url']    = __( 'Banner: Link-Ziel', 'unternehmensdaten' );
			$fields['banner_dismissible'] = __( 'Banner: schließbar (ja/nein)', 'unternehmensdaten' );
		}

		return $fields;
	}

	/**
	 * Die Schreibweise eines Schluessels in Slim SEO, Bricks und Etch.
	 *
	 * @param string $key Schluessel aus fields().
	 * @return array slim_seo, bricks und etch.
	 */
	public static function syntax( $key ) {
		return array(
			'slim_seo' => '{{ undt.' . $key . ' }}',
			'bricks'   => '{undt_' . $key . '}',
			'etch'     => '{options.undt.' . $key . '}',
		);
	}

	/**
	 * Der Wert eines Schluessels als schlichter Text.
	 *
	 * @param string $key Schluessel aus fields().
	 * @return string
	 */
	public static function value( $key ) {
		$key = (string) $key;

		// Was aus mehreren Angaben besteht, steht als Text als Aufzaehlung da.
		if ( in_array( $key, self::LISTS, true ) ) {
			return implode( ', ', self::list_value( $key ) );
		}

		switch ( $key ) {
			case 'address':
				return implode( ' · ', UNDT_Render::address_parts() );

			case 'hours_today':
				return UNDT_Api::today_text();

			case 'open_now':
				if ( ! UNDT_Modules::is_active( 'hours' ) || ! UNDT_Hours::has_data() ) {
					return '';
				}

				return UNDT_Hours::is_open_now()
					? __( 'Jetzt geöffnet', 'unternehmensdaten' )
					: __( 'Zurzeit geschlossen', 'unternehmensdaten' );

			case 'is_open':
				return self::flag( UNDT_Modules::is_active( 'hours' ) && UNDT_Hours::has_data() && UNDT_Hours::is_open_now() );

			case 'logo_url':
				if ( ! UNDT_Modules::is_active( 'seo' ) ) {
					return '';
				}

				$logo = UNDT_SchemaOrg::logo();

				return isset( $logo['url'] ) ? (string) $logo['url'] : '';
		}

		if ( 0 === strpos( $key, 'banner_' ) ) {
			return self::banner_value( substr( $key, 7 ) );
		}

		if ( '_link' === substr( $key, -5 ) ) {
			return self::contact_link( substr( $key, 0, -5 ) );
		}

		$field = UNDT_Schema::field( $key );

		if ( null === $field || empty( $field['shortcode'] ) ) {
			return '';
		}

		if ( 'page' === $field['type'] ) {
			$target = UNDT_Store::link( $key );

			return null === $target ? '' : $target['url'];
		}

		// Meta-Tags und Builder-Felder vertragen mehrzeilige Angaben nur als eine Zeile.
		if ( in_array( $field['type'], array( 'list', 'textarea' ), true ) ) {
			return implode( ', ', UNDT_Store::get_lines( $key ) );
		}

		return trim( UNDT_Store::get( $key ) );
	}

	/**
	 * Alle Werte eines Kontexts.
	 *
	 * Jeder angebotene Schluessel ist enthalten, auch mit leerem Wert: Slim SEO
	 * liesse einen fehlenden Schluessel sonst als {{ undt.… }} im Text stehen.
	 *
	 * @param string $context CONTEXT_SEO, CONTEXT_BUILDER oder CONTEXT_SCHEMA.
	 * @return array
	 */
	public static function values( $context = self::CONTEXT_SEO ) {
		$values = array();

		foreach ( array_keys( self::fields( $context ) ) as $key ) {
			// Nur die strukturierten Daten koennen mehrere Angaben verarbeiten.
			$values[ $key ] = self::CONTEXT_SCHEMA === $context && in_array( $key, self::LISTS, true )
				? self::list_value( $key )
				: self::value( $key );
		}

		return $values;
	}

	/**
	 * Ein Wert, der aus mehreren Angaben besteht.
	 *
	 * @param string $key Schluessel aus LISTS.
	 * @return array Liste von Zeichenketten.
	 */
	public static function list_value( $key ) {
		if ( 'social_profiles' === $key ) {
			return UNDT_Modules::is_active( 'social' ) ? UNDT_SchemaOrg::same_as() : array();
		}

		$spalten = array(
			'hours_days'   => 'day',
			'hours_opens'  => 'opens',
			'hours_closes' => 'closes',
		);

		if ( isset( $spalten[ $key ] ) ) {
			$werte = array();

			foreach ( self::hours_rows() as $row ) {
				$werte[] = $row[ $spalten[ $key ] ];
			}

			return $werte;
		}

		return array();
	}

	/**
	 * Die Oeffnungszeiten als flache Zeilen, je Tag und Zeitfenster eine.
	 *
	 * Die eigene Auszeichnung fasst gleiche Zeiten zu einem Eintrag mit mehreren
	 * Tagen zusammen. Hier geht das nicht: die Schema-Einstellungen bauen aus
	 * gleich langen Listen je einen Eintrag und nehmen aus jeder Liste den
	 * Wert an derselben Stelle. Ein Eintrag mit mehreren Tagen liesse sich darin
	 * nicht abbilden. Je Tag ein eigener Eintrag ist genauso gueltig.
	 *
	 * @return array Zeilen aus day, opens und closes.
	 */
	private static function hours_rows() {
		if ( ! UNDT_Modules::is_active( 'hours' ) || ! UNDT_Hours::has_data() ) {
			return array();
		}

		$rows = array();

		foreach ( UNDT_Hours::day_keys() as $day ) {
			foreach ( UNDT_Hours::slots( $day ) as $slot ) {
				$rows[] = array(
					'day'    => UNDT_Hours::schema_day( $day ),
					'opens'  => $slot['from'],
					'closes' => $slot['to'],
				);
			}
		}

		return $rows;
	}

	/**
	 * Ein Ja-Nein-Wert als Text.
	 *
	 * @param bool $state Zustand.
	 * @return string 1 oder Leerstring.
	 */
	private static function flag( $state ) {
		return $state ? '1' : '';
	}

	/**
	 * Eine Angabe des Infobanners.
	 *
	 * @param string $part show, type, text, link_text, link_url oder dismissible.
	 * @return string
	 */
	private static function banner_value( $part ) {
		if ( ! UNDT_Modules::is_active( 'banner' ) ) {
			return '';
		}

		switch ( $part ) {
			case 'show':
				// Dieselbe Bedingung wie fuer das mitgelieferte Banner.
				return self::flag( UNDT_Blocks::banner_active() );

			case 'type':
				$type = (string) UNDT_Content::value( 'banner', 'type' );

				return in_array( $type, array( 'info', 'success', 'warning', 'urgent' ), true ) ? $type : 'info';

			case 'text':
				return trim( (string) UNDT_Content::value( 'banner', 'text' ) );

			case 'link_url':
				return trim( (string) UNDT_Content::value( 'banner', 'link_url' ) );

			case 'link_text':
				$text = trim( (string) UNDT_Content::value( 'banner', 'link_text' ) );

				// Wie das mitgelieferte Banner: ein Link ohne Text heisst „Mehr erfahren“.
				if ( '' === $text && '' !== self::banner_value( 'link_url' ) ) {
					$text = __( 'Mehr erfahren', 'unternehmensdaten' );
				}

				return $text;

			case 'dismissible':
				return self::flag( (bool) UNDT_Content::value( 'banner', 'dismissible' ) );
		}

		return '';
	}

	/**
	 * Ein tel:- oder mailto:-Link zu einem Kontaktfeld.
	 *
	 * @param string $key Feldschluessel ohne die Endung _link.
	 * @return string
	 */
	private static function contact_link( $key ) {
		$field = UNDT_Schema::field( $key );

		if ( null === $field || ! UNDT_Store::has( $key ) ) {
			return '';
		}

		$value = trim( UNDT_Store::get( $key ) );

		if ( 'tel' === $field['type'] ) {
			$number = (string) preg_replace( '#[^0-9+]#', '', $value );

			return '' === $number ? '' : 'tel:' . $number;
		}

		if ( 'email' === $field['type'] && is_email( $value ) ) {
			return 'mailto:' . $value;
		}

		return '';
	}

}
