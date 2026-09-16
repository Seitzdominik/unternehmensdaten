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
	 * Werte fuer Builder: zusaetzlich fertige Links und tagesabhaengige Angaben.
	 */
	const CONTEXT_BUILDER = 'builder';

	/**
	 * Haengt sich in Slim SEO, Bricks und Etch ein.
	 *
	 * @return void
	 */
	public static function register() {
		// Slim SEO: Auswahl hinter den drei Punkten und die Werte beim Rendern.
		add_filter( 'slim_seo_variables', array( __CLASS__, 'slim_seo_variables' ) );
		add_filter( 'slim_seo_data', array( __CLASS__, 'slim_seo_data' ) );

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
	 * @param string $context CONTEXT_SEO oder CONTEXT_BUILDER.
	 * @return array Schluessel => Beschriftung.
	 */
	public static function fields( $context = self::CONTEXT_SEO ) {
		$builder = self::CONTEXT_BUILDER === $context;
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

		if ( $builder && UNDT_Modules::is_active( 'hours' ) ) {
			$fields['hours_today'] = __( 'Heutige Öffnungszeit', 'unternehmensdaten' );
			$fields['open_now']    = __( 'Geöffnet-Status', 'unternehmensdaten' );
		}

		return $fields;
	}

	/**
	 * Der Wert eines Schluessels als schlichter Text.
	 *
	 * @param string $key Schluessel aus fields().
	 * @return string
	 */
	public static function value( $key ) {
		$key = (string) $key;

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
	 * @param string $context CONTEXT_SEO oder CONTEXT_BUILDER.
	 * @return array
	 */
	public static function values( $context = self::CONTEXT_SEO ) {
		$values = array();

		foreach ( array_keys( self::fields( $context ) ) as $key ) {
			$values[ $key ] = self::value( $key );
		}

		return $values;
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
		if ( ! is_array( $variables ) ) {
			return $variables;
		}

		$options = array();

		foreach ( self::fields( self::CONTEXT_SEO ) as $key => $label ) {
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
			$tags[] = array(
				'name'  => '{undt_' . $key . '}',
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
		if ( '_link' === substr( $key, -5 ) ) {
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
		if ( is_array( $data ) ) {
			$data['undt'] = self::values( self::CONTEXT_BUILDER );
		}

		return $data;
	}
}
