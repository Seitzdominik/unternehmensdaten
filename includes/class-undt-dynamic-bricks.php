<?php
/**
 * Die Stammdaten in Bricks.
 *
 * Bricks fuehrt die Werte als `{undt_phone}` in seiner Auswahl dynamischer
 * Daten. Aufgeloest wird sowohl ein einzelner Tag als auch ein Fliesstext, in
 * dem mehrere stehen, jeweils mit den Filtern, die Bricks fuer Texte kennt.
 *
 * Die Werte selbst liefert UNDT_Dynamic. Diese Klasse kennt nur Bricks.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Dynamic_Bricks
 */
final class UNDT_Dynamic_Bricks {

	/**
	 * Haengt sich in Bricks ein.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'bricks/dynamic_tags_list', array( __CLASS__, 'tags' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( __CLASS__, 'render_tag' ), 20, 3 );
		add_filter( 'bricks/dynamic_data/render_content', array( __CLASS__, 'render_content' ), 20, 3 );
		add_filter( 'bricks/frontend/render_data', array( __CLASS__, 'render_content' ), 20, 2 );
	}

	/**
	 * Traegt die Tags in die Auswahl dynamischer Daten von Bricks ein.
	 *
	 * @param mixed $tags Bisher registrierte Tags.
	 * @return mixed
	 */
	public static function tags( $tags ) {
		if ( ! is_array( $tags ) ) {
			return $tags;
		}

		foreach ( UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER ) as $key => $label ) {
			$syntax = UNDT_Dynamic::syntax( $key );
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
	public static function render_tag( $tag, $post = null, $context = 'text' ) {
		if ( ! is_string( $tag ) ) {
			return $tag;
		}

		$value = self::resolve( $tag, $post, is_string( $context ) ? $context : 'text' );

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
	public static function render_content( $content, $post = null, $context = 'text' ) {
		if ( ! is_string( $content ) || false === strpos( $content, '{undt_' ) ) {
			return $content;
		}

		$context = is_string( $context ) ? $context : 'text';

		return (string) preg_replace_callback(
			'/\{undt_[^{}]*\}/',
			static function ( $match ) use ( $post, $context ) {
				$value = self::resolve( $match[0], $post, $context );

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
	private static function resolve( $tag, $post, $context ) {
		$tag = trim( $tag );

		if ( '{' === substr( $tag, 0, 1 ) && '}' === substr( $tag, -1 ) ) {
			$tag = substr( $tag, 1, -1 );
		}

		if ( 0 !== strpos( $tag, 'undt_' ) ) {
			return null;
		}

		list( $name, $args ) = self::parse( $tag );

		$key = substr( $name, 5 );

		if ( ! array_key_exists( $key, UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER ) ) ) {
			return null;
		}

		$link  = 'link' === $context && self::is_link_key( $key );
		$value = UNDT_Dynamic::value( $key );

		foreach ( $args as $index => $arg ) {
			if ( is_int( $index ) && ! $link && is_scalar( $arg ) && ctype_digit( (string) $arg ) && (int) $arg > 0 ) {
				$value = wp_trim_words( $value, (int) $arg, '…' );
			}
		}

		if ( '' === $value && isset( $args['fallback'] ) && is_scalar( $args['fallback'] ) ) {
			return self::fallback( (string) $args['fallback'], $post, $context );
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
	private static function parse( $tag ) {
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
	private static function fallback( $fallback, $post, $context ) {
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
}
