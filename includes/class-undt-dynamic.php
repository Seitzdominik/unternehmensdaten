<?php
/**
 * Stammdaten als dynamische Daten fuer Slim SEO, Bricks und Etch.
 *
 * Alle drei Werkzeuge bieten eine Auswahl dynamischer Werte an. Diese Klasse
 * traegt die Unternehmensdaten dort ein, damit sie sich per Klick einsetzen
 * lassen, statt ueber Shortcodes oder PHP-Aufrufe:
 *
 *   Slim SEO  {{ undt.phone }}      Meta-Titel, Meta-Beschreibung, Social
 *   Bricks    {undt_phone}          Auswahl dynamischer Daten im Builder
 *   Etch      {options.undt.phone}  ueber den Filter etch/dynamic_data/option
 *
 * Slim SEO fuehrt zwei getrennte Listen: eine fuer die Meta-Angaben und eine
 * fuer die Schema-Einstellungen von Slim SEO Pro. Dieselbe Schreibweise, aber
 * eigene Haken, deshalb stehen die Werte hier zweimal.
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
	 * Haengt sich in Slim SEO, Bricks und Etch ein.
	 *
	 * @return void
	 */
	public static function register() {
		// Slim SEO: Auswahl hinter den drei Punkten und die Werte beim Rendern.
		add_filter( 'slim_seo_variables', array( __CLASS__, 'slim_seo_variables' ) );
		add_filter( 'slim_seo_data', array( __CLASS__, 'slim_seo_data' ) );

		// Slim SEO Pro: dieselbe Auswahl in den Schema-Einstellungen.
		add_filter( 'slim_seo_schema_variables', array( __CLASS__, 'slim_seo_schema_variables' ) );
		add_filter( 'slim_seo_schema_data', array( __CLASS__, 'slim_seo_schema_data' ) );

		// Bricks: Tags in der Auswahl dynamischer Daten, einzeln und im Fliesstext.
		add_filter( 'bricks/dynamic_tags_list', array( __CLASS__, 'bricks_tags' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( __CLASS__, 'bricks_render_tag' ), 20, 3 );
		add_filter( 'bricks/dynamic_data/render_content', array( __CLASS__, 'bricks_render_content' ), 20, 3 );
		add_filter( 'bricks/frontend/render_data', array( __CLASS__, 'bricks_render_content' ), 20, 2 );

		// Etch: Werte unter {options.undt.…}.
		add_filter( 'etch/dynamic_data/option', array( __CLASS__, 'etch_options' ) );
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

	/* --------------------------------------------------------- Slim SEO --- */

	/**
	 * Traegt die Werte in die Auswahl von Slim SEO ein.
	 *
	 * @param mixed $variables Gruppen aus label und options.
	 * @return mixed
	 */
	public static function slim_seo_variables( $variables ) {
		return self::slim_seo_group( $variables, self::CONTEXT_SEO );
	}

	/**
	 * Traegt die Werte in die Auswahl der Schema-Einstellungen ein.
	 *
	 * Slim SEO Pro baut seine Auswahl aus einem eigenen Haken. Ohne ihn stehen in
	 * den Schema-Einstellungen nur Beitrag, Begriff, Benutzer und Website, und
	 * Angaben wie die Social-Profile muesste man dort ein zweites Mal pflegen.
	 *
	 * @param mixed $variables Gruppen aus label und options.
	 * @return mixed
	 */
	public static function slim_seo_schema_variables( $variables ) {
		return self::slim_seo_group( $variables, self::CONTEXT_SCHEMA );
	}

	/**
	 * Haengt die eigene Gruppe an eine Auswahl von Slim SEO an.
	 *
	 * @param mixed  $variables Gruppen aus label und options.
	 * @param string $context   Kontext, dessen Werte angeboten werden.
	 * @return mixed
	 */
	private static function slim_seo_group( $variables, $context ) {
		if ( ! is_array( $variables ) ) {
			return $variables;
		}

		$options = array();

		foreach ( self::fields( $context ) as $key => $label ) {
			$options[ 'undt.' . $key ] = $label;
		}

		$variables[] = array(
			'label'   => __( 'Unternehmensdaten', 'unternehmensdaten' ),
			'options' => $options,
		);

		return $variables;
	}

	/**
	 * Liefert Slim SEO die Werte fuer {{ undt.… }}.
	 *
	 * @param mixed $data Daten, mit denen Slim SEO die Variablen ersetzt.
	 * @return mixed
	 */
	public static function slim_seo_data( $data ) {
		if ( is_array( $data ) ) {
			$data['undt'] = self::values( self::CONTEXT_SEO );
		}

		return $data;
	}

	/**
	 * Liefert den Schema-Einstellungen die Werte fuer {{ undt.… }}.
	 *
	 * @param mixed $data Daten, mit denen Slim SEO Pro die Variablen ersetzt.
	 * @return mixed
	 */
	public static function slim_seo_schema_data( $data ) {
		if ( is_array( $data ) ) {
			$data['undt'] = self::values( self::CONTEXT_SCHEMA );
		}

		return $data;
	}

	/* ----------------------------------------------------------- Bricks --- */

	/**
	 * Traegt die Tags in die Auswahl dynamischer Daten von Bricks ein.
	 *
	 * @param mixed $tags Bisher registrierte Tags.
	 * @return mixed
	 */
	public static function bricks_tags( $tags ) {
		if ( ! is_array( $tags ) ) {
			return $tags;
		}

		foreach ( self::fields( self::CONTEXT_BUILDER ) as $key => $label ) {
			$syntax = self::syntax( $key );
			$tags[] = array(
				'name'  => $syntax['bricks'],
				'label' => $label,
				'group' => __( 'Unternehmensdaten', 'unternehmensdaten' ),
			);
		}

		return $tags;
	}

	/**
	 * Loest einen einzelnen Tag auf.
	 *
	 * Bricks reicht Tags, die keiner seiner eigenen Quellen gehoeren, mit
	 * geschweiften Klammern und samt Filtern weiter, etwa
	 * {undt_company_name:3}. Ohne Klammern wird der Tag ebenso verstanden,
	 * fremde Tags bleiben unangetastet.
	 *
	 * @param mixed  $tag     Name des Tags.
	 * @param mixed  $post    Beitrag, nur fuer verschachtelte Ersatzwerte.
	 * @param string $context Bricks-Kontext wie text, link oder image.
	 * @return mixed
	 */
	public static function bricks_render_tag( $tag, $post = null, $context = 'text' ) {
		if ( ! is_string( $tag ) ) {
			return $tag;
		}

		$value = self::bricks_resolve( $tag, $post, is_string( $context ) ? $context : 'text' );

		return null === $value ? $tag : $value;
	}

	/**
	 * Ersetzt die Tags in einem Text.
	 *
	 * @param mixed $content Inhalt mit Tags.
	 * @param mixed $post    Beitrag, nur fuer verschachtelte Ersatzwerte.
	 * @param mixed $context Bricks-Kontext, beim Frontend-Filter der Bereich.
	 * @return mixed
	 */
	public static function bricks_render_content( $content, $post = null, $context = 'text' ) {
		if ( ! is_string( $content ) || false === strpos( $content, '{undt_' ) ) {
			return $content;
		}

		$context = is_string( $context ) ? $context : 'text';

		return (string) preg_replace_callback(
			'/\{undt_[^{}]*\}/',
			static function ( $match ) use ( $post, $context ) {
				$value = self::bricks_resolve( $match[0], $post, $context );

				return null === $value ? $match[0] : $value;
			},
			$content
		);
	}

	/**
	 * Der Wert eines Bricks-Tags in der Form, die Bricks im Kontext erwartet.
	 *
	 * Unterstuetzt die Filter, die Bricks fuer Texte kennt: eine Zahl begrenzt
	 * die Woerter, @fallback liefert einen Ersatz fuer leere Werte. Alles
	 * Weitere wird ignoriert, wie Bricks es bei unbekannten Filtern tut.
	 *
	 * @param string $tag     Tag mit oder ohne Klammern.
	 * @param mixed  $post    Beitrag.
	 * @param string $context Bricks-Kontext.
	 * @return string|null Null, wenn der Tag nicht zu diesem Plugin gehoert.
	 */
	private static function bricks_resolve( $tag, $post, $context ) {
		$tag = trim( $tag );

		if ( '{' === substr( $tag, 0, 1 ) && '}' === substr( $tag, -1 ) ) {
			$tag = substr( $tag, 1, -1 );
		}

		if ( 0 !== strpos( $tag, 'undt_' ) ) {
			return null;
		}

		list( $name, $args ) = self::bricks_parse( $tag );

		$key = substr( $name, 5 );

		if ( ! array_key_exists( $key, self::fields( self::CONTEXT_BUILDER ) ) ) {
			return null;
		}

		$link  = 'link' === $context && self::is_link_key( $key );
		$value = self::value( $key );

		foreach ( $args as $index => $arg ) {
			if ( is_int( $index ) && ! $link && is_scalar( $arg ) && ctype_digit( (string) $arg ) && (int) $arg > 0 ) {
				$value = wp_trim_words( $value, (int) $arg, '…' );
			}
		}

		if ( '' === $value && isset( $args['fallback'] ) && is_scalar( $args['fallback'] ) ) {
			return self::bricks_fallback( (string) $args['fallback'], $post, $context );
		}

		// In Link-Feldern erwartet Bricks eine Adresse, sonst fertiges HTML.
		return $link ? esc_url_raw( $value, array( 'http', 'https', 'tel', 'mailto' ) ) : esc_html( $value );
	}

	/**
	 * Zerlegt einen Tag in Namen und Filter.
	 *
	 * Nutzt den Parser von Bricks, damit sich die Tags genau wie die eigenen
	 * von Bricks verhalten. Ohne ihn, etwa in Tests, genuegt eine einfache
	 * Zerlegung an Doppelpunkten.
	 *
	 * @param string $tag Tag ohne Klammern.
	 * @return array array( Name, Filter ).
	 */
	private static function bricks_parse( $tag ) {
		$class = '\Bricks\Integrations\Dynamic_Data\Dynamic_Data_Parser';

		if ( class_exists( $class ) && method_exists( $class, 'parse' ) ) {
			$parser = new $class();
			$parsed = $parser->parse( $tag );

			if ( is_array( $parsed ) && isset( $parsed['tag'] ) && is_string( $parsed['tag'] ) ) {
				return array( $parsed['tag'], isset( $parsed['args'] ) && is_array( $parsed['args'] ) ? $parsed['args'] : array() );
			}
		}

		$args = array();

		if ( preg_match( '/\s+@fallback:(.*)$/s', $tag, $match ) ) {
			$args['fallback'] = $match[1];
			$tag              = substr( $tag, 0, -strlen( $match[0] ) );
		}

		$parts = explode( ':', trim( $tag ) );
		$name  = (string) array_shift( $parts );

		foreach ( $parts as $part ) {
			if ( '' !== $part ) {
				$args[] = $part;
			}
		}

		return array( $name, $args );
	}

	/**
	 * Der Ersatzwert aus @fallback, so wie Bricks ihn behandelt.
	 *
	 * Der Text stammt aus dem Builder und wird wie dort unveraendert
	 * ausgegeben. Enthaelt er selbst Tags, loest Bricks sie auf.
	 *
	 * @param string $fallback Rohtext nach @fallback:.
	 * @param mixed  $post     Beitrag.
	 * @param string $context  Bricks-Kontext.
	 * @return string
	 */
	private static function bricks_fallback( $fallback, $post, $context ) {
		$fallback = stripslashes( $fallback );

		if ( strlen( $fallback ) > 1 && "'" === $fallback[0] && "'" === substr( $fallback, -1 ) ) {
			$fallback = substr( $fallback, 1, -1 );
		}

		$render = array( '\Bricks\Integrations\Dynamic_Data\Providers', 'render_content' );

		if ( false !== strpos( $fallback, '{' ) && is_callable( $render ) ) {
			$fallback = call_user_func( $render, $fallback, $post instanceof WP_Post ? $post->ID : 0, $context );
		}

		return (string) $fallback;
	}

	/**
	 * Ob ein Schluessel eine Adresse liefert.
	 *
	 * @param string $key Schluessel.
	 * @return bool
	 */
	private static function is_link_key( $key ) {
		if ( '_link' === substr( $key, -5 ) || 'banner_link_url' === $key ) {
			return true;
		}

		$field = UNDT_Schema::field( $key );

		return null !== $field && in_array( $field['type'], array( 'page', 'url' ), true );
	}

	/* ------------------------------------------------------------- Etch --- */

	/**
	 * Liefert Etch die Werte unter {options.undt.…}.
	 *
	 * @param mixed $data Bisherige Optionsdaten.
	 * @return mixed
	 */
	public static function etch_options( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$values = self::values( self::CONTEXT_BUILDER );

		// Echte Wahrheitswerte, damit Bedingungen wie {options.undt.banner_show} greifen.
		foreach ( self::FLAGS as $flag ) {
			if ( array_key_exists( $flag, $values ) ) {
				$values[ $flag ] = '1' === $values[ $flag ];
			}
		}

		$data['undt'] = $values;

		return $data;
	}
}
