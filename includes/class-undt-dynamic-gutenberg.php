<?php
/**
 * Die Stammdaten im Block-Editor.
 *
 * Gutenberg kennt keine Kurzschreibweise mitten im Satz, wie sie Bricks und
 * Etch haben. Was es seit WordPress 6.5 gibt, sind Block-Bindungen: ein Block
 * merkt sich, woher sein Text kommt, und holt ihn bei jeder Ausgabe neu. Diese
 * Klasse meldet dafuer die Quelle „unternehmensdaten/feld“ an.
 *
 * Kopiert wird im Backend deshalb kein Kuerzel, sondern ein fertiger Absatz:
 *
 *   <!-- wp:paragraph {"metadata":{"bindings":{"content":{ … }}}} -->
 *   <p>Telefon</p>
 *   <!-- /wp:paragraph -->
 *
 * Der Block-Editor erkennt beim Einfuegen, dass in der Zwischenablage
 * Block-Auszeichnung steht, und macht daraus einen richtigen Block. Der Text
 * darin ist nur ein Platzhalter fuer den Editor; ausgegeben wird immer der
 * aktuelle Wert. Fuer einen Wert mitten im laufenden Text bleibt der Shortcode
 * [undt key="…"], denn eine Bindung gilt immer fuer den ganzen Block.
 *
 * Die Werte selbst liefert UNDT_Dynamic. Diese Klasse kennt nur Gutenberg.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Dynamic_Gutenberg
 */
final class UNDT_Dynamic_Gutenberg {

	/**
	 * Name der Bindungsquelle, Namensraum und Name durch einen Schraegstrich
	 * getrennt. WordPress laesst nur Kleinbuchstaben, Ziffern und Bindestriche zu.
	 */
	const SOURCE = 'unternehmensdaten/feld';

	/**
	 * Ob diese WordPress-Fassung Block-Bindungen kennt.
	 *
	 * Ab 6.5. Darunter bliebe der eingefuegte Block auf seinem Platzhalter
	 * stehen, deshalb bietet das Backend die Knoepfe dort gar nicht erst an.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'register_block_bindings_source' );
	}

	/**
	 * Meldet die Quelle an.
	 *
	 * Der Aufruf gehoert auf `init`; genau dort laeuft der Start des Plugins.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! self::available() ) {
			return;
		}

		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => self::label(),
				'get_value_callback' => array( __CLASS__, 'value' ),
			)
		);

		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor' ) );
	}

	/**
	 * Die Beschriftung der Quelle, wie sie der Editor anzeigt.
	 *
	 * @return string
	 */
	public static function label() {
		return __( 'Unternehmensdaten', 'unternehmensdaten' );
	}

	/**
	 * Die Werte, die sich binden lassen.
	 *
	 * Wie im Builder, ohne die Ja-Nein-Werte: eine 1 oder ein Leerstring als
	 * Absatz waere niemandem gedient. Fuer Bedingungen gibt es im Block-Editor
	 * ohnehin keine Entsprechung.
	 *
	 * @return array Schluessel => Beschriftung.
	 */
	public static function fields() {
		$fields = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER );

		foreach ( UNDT_Dynamic::FLAGS as $flag ) {
			unset( $fields[ $flag ] );
		}

		return $fields;
	}

	/**
	 * Ob es zu einem Schluessel einen Block gibt.
	 *
	 * @param string $key Schluessel aus UNDT_Dynamic::fields().
	 * @return bool
	 */
	public static function offers( $key ) {
		return array_key_exists( (string) $key, self::fields() );
	}

	/**
	 * Der fertige Absatz zum Einfuegen.
	 *
	 * @param string $key Schluessel aus fields().
	 * @return string Block-Auszeichnung, leer fuer einen unbekannten Schluessel.
	 */
	public static function markup( $key ) {
		$key    = (string) $key;
		$fields = self::fields();

		if ( ! isset( $fields[ $key ] ) ) {
			return '';
		}

		$attributes = array(
			'metadata' => array(
				'bindings' => array(
					'content' => array(
						'source' => self::SOURCE,
						'args'   => array( 'key' => $key ),
					),
				),
			),
		);

		return '<!-- wp:paragraph ' . self::attributes( $attributes ) . " -->\n"
			. '<p>' . esc_html( $fields[ $key ] ) . "</p>\n"
			. '<!-- /wp:paragraph -->';
	}

	/**
	 * Die Attribute eines Blocks als JSON, wie WordPress sie selbst schreibt.
	 *
	 * @param array $attributes Attribute.
	 * @return string
	 */
	private static function attributes( array $attributes ) {
		if ( function_exists( 'serialize_block_attributes' ) ) {
			return serialize_block_attributes( $attributes );
		}

		return (string) wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Liefert WordPress den Wert einer Bindung.
	 *
	 * @param array         $args      Argumente aus der Block-Auszeichnung.
	 * @param WP_Block|null $block     Der Block, hier nicht gebraucht.
	 * @param string        $attribute Gebundenes Attribut, hier nicht gebraucht.
	 * @return string
	 */
	public static function value( $args, $block = null, $attribute = '' ) {
		$key = is_array( $args ) && isset( $args['key'] ) ? (string) $args['key'] : '';

		return self::offers( $key ) ? UNDT_Dynamic::value( $key ) : '';
	}

	/**
	 * Laedt das Skript, das die Werte im Editor sichtbar macht.
	 *
	 * Ohne diesen Schritt stuende im Editor der Platzhalter aus der
	 * Block-Auszeichnung, auf der Website aber der richtige Wert. Die Werte
	 * aendern sich nur ueber die Einstellungsseiten, sie reisen deshalb fertig
	 * mit und kosten keine Abfrage im Editor.
	 *
	 * @return void
	 */
	public static function editor() {
		$handle = 'undt-bindings';
		$file   = 'admin/assets/gutenberg.js';

		wp_enqueue_script(
			$handle,
			UNDT_URL . $file,
			array( 'wp-blocks' ),
			UNDT_Admin::asset_version( $file ),
			true
		);

		/*
		 * Lesbar halten, aber spitze Klammern maskieren: sonst koennte ein Wert
		 * das script-Element beenden.
		 */
		$data = wp_json_encode( self::data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );

		wp_add_inline_script( $handle, 'window.undtBindings = ' . $data . ';', 'before' );
	}

	/**
	 * Was das Skript im Editor braucht.
	 *
	 * @return array
	 */
	public static function data() {
		$fields = self::fields();
		$values = array();

		foreach ( array_keys( $fields ) as $key ) {
			$values[ $key ] = UNDT_Dynamic::value( $key );
		}

		return array(
			'source' => self::SOURCE,
			'label'  => self::label(),
			'labels' => $fields,
			'values' => $values,
		);
	}
}
