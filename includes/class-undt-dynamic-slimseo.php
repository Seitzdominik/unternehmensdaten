<?php
/**
 * Die Stammdaten in Slim SEO.
 *
 * Slim SEO fuehrt zwei getrennte Auswahlen: eine fuer die Meta-Angaben und
 * eine fuer die Schema-Einstellungen von Slim SEO Pro. Dieselbe Schreibweise
 * `{{ undt.phone }}`, aber eigene Haken, deshalb stehen die Werte hier
 * zweimal.
 *
 * Die Werte selbst liefert UNDT_Dynamic. Diese Klasse kennt nur die Haken.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Dynamic_SlimSeo
 */
final class UNDT_Dynamic_SlimSeo {

	/**
	 * Haengt sich in Slim SEO ein.
	 *
	 * @return void
	 */
	public static function register() {
		// Auswahl hinter den drei Punkten und die Werte beim Rendern.
		add_filter( 'slim_seo_variables', array( __CLASS__, 'variables' ) );
		add_filter( 'slim_seo_data', array( __CLASS__, 'data' ) );

		// Slim SEO Pro: dieselbe Auswahl in den Schema-Einstellungen.
		add_filter( 'slim_seo_schema_variables', array( __CLASS__, 'schema_variables' ) );
		add_filter( 'slim_seo_schema_data', array( __CLASS__, 'schema_data' ) );
	}

	/**
	 * Traegt die Werte in die Auswahl der Meta-Angaben ein.
	 *
	 * @param mixed $variables Gruppen aus label und options.
	 * @return mixed
	 */
	public static function variables( $variables ) {
		return self::group( $variables, UNDT_Dynamic::CONTEXT_SEO );
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
	public static function schema_variables( $variables ) {
		return self::group( $variables, UNDT_Dynamic::CONTEXT_SCHEMA );
	}

	/**
	 * Haengt die eigene Gruppe an eine Auswahl von Slim SEO an.
	 *
	 * @param mixed  $variables Gruppen aus label und options.
	 * @param string $context   Kontext, dessen Werte angeboten werden.
	 * @return mixed
	 */
	private static function group( $variables, $context ) {
		if ( ! is_array( $variables ) ) {
			return $variables;
		}

		$options = array();

		foreach ( UNDT_Dynamic::fields( $context ) as $key => $label ) {
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
	public static function data( $data ) {
		if ( is_array( $data ) ) {
			$data['undt'] = UNDT_Dynamic::values( UNDT_Dynamic::CONTEXT_SEO );
		}

		return $data;
	}

	/**
	 * Liefert den Schema-Einstellungen die Werte fuer {{ undt.… }}.
	 *
	 * @param mixed $data Daten, mit denen Slim SEO Pro die Variablen ersetzt.
	 * @return mixed
	 */
	public static function schema_data( $data ) {
		if ( is_array( $data ) ) {
			$data['undt'] = UNDT_Dynamic::values( UNDT_Dynamic::CONTEXT_SCHEMA );
		}

		return $data;
	}
}
