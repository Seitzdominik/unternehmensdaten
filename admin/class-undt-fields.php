<?php
/**
 * Das Raster einer Formularseite.
 *
 * Diese Klasse beantwortet nur die Frage, wo etwas steht: Zeilen im
 * form-table-Raster, Feldpaare, Zellen eines zweispaltigen Rasters,
 * Beschriftungen, Fundstellen und das Fragezeichen mit seinem Hinweis. Die
 * Eingabeelemente selbst stehen in UNDT_Controls, die Kopierknoepfe darunter
 * in UNDT_Copy.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Fields
 */
final class UNDT_Fields {

	/**
	 * Rendert eine Gruppe von Feldern als Formularzeilen.
	 *
	 * Felder mit gleichem pair teilen sich dabei eine Zeile.
	 *
	 * @param array  $fields      Felddefinitionen, Schluessel ist der Feldname.
	 * @param array  $values      Gespeicherte Werte.
	 * @param string $option_name Name der Option, wird zum Formularnamen.
	 * @param array  $args        with_copy und section_basis.
	 * @return void
	 */
	public static function rows( array $fields, array $values, $option_name, array $args = array() ) {
		$args = array_merge(
			array(
				'with_copy'     => true,
				'section_basis' => '',
			),
			$args
		);

		$handled = array();

		foreach ( $fields as $key => $field ) {
			if ( isset( $handled[ $key ] ) ) {
				continue;
			}

			if ( '' !== $field['pair'] ) {
				$group = array();

				foreach ( $fields as $other_key => $other ) {
					if ( $other['pair'] === $field['pair'] ) {
						$group[ $other_key ] = $other;
					}
				}

				// Ein Paar, von dem nur eine Haelfte gilt, bleibt eine normale Zeile.
				if ( count( $group ) > 1 ) {
					foreach ( array_keys( $group ) as $member ) {
						$handled[ $member ] = true;
					}

					self::pair_row( $group, $values, $option_name, $args );

					continue;
				}
			}

			/*
			 * Mehrere Schalter hintereinander stehen in zwei Spalten. Einzeln
			 * bliebe neben jedem eine halbe Zeile leer.
			 */
			if ( 'checkbox' === $field['type'] && '' === $field['pair'] ) {
				$run = self::toggle_run( $fields, $key );

				if ( count( $run ) > 1 ) {
					foreach ( array_keys( $run ) as $member ) {
						$handled[ $member ] = true;
					}

					self::toggle_grid( $run, $values, $option_name, $args );

					continue;
				}
			}

			$handled[ $key ] = true;

			$value = array_key_exists( $key, $values ) ? $values[ $key ] : $field['default'];

			self::row( $key, $field, $value, $option_name, $args );
		}
	}

	/**
	 * Die Rechtsgrundlage eines Abschnitts, sofern alle Felder dieselbe nennen.
	 *
	 * Vier Adressfelder mit viermal derselben Fussnote sind Rauschen. Steht die
	 * Angabe einmal am Abschnitt, verschwindet sie unter den einzelnen Feldern.
	 * Felder ohne eigene Angabe sind genau die optionalen, die schon am fehlenden
	 * Sternchen zu erkennen sind.
	 *
	 * @param array $fields Felder des Abschnitts.
	 * @return string
	 */
	public static function section_basis( array $fields ) {
		$bases = array_unique( array_filter( wp_list_pluck( $fields, 'basis' ) ) );

		return 1 === count( $bases ) ? (string) reset( $bases ) : '';
	}

	/**
	 * Eine vollstaendige Zeile im form-table-Raster.
	 *
	 * @param string $key         Feldschluessel.
	 * @param array  $field       Felddefinition.
	 * @param mixed  $value       Aktueller Wert.
	 * @param string $option_name Name der Option, wird zum Formularnamen.
	 * @param array  $args        with_copy und section_basis.
	 * @return void
	 */
	public static function row( $key, array $field, $value, $option_name, $args = array() ) {
		// Rueckwaertskompatibel: frueher stand hier ein einfaches bool.
		if ( ! is_array( $args ) ) {
			$args = array( 'with_copy' => (bool) $args );
		}

		$args = array_merge(
			array(
				'with_copy'     => true,
				'section_basis' => '',
			),
			$args
		);

		$id   = 'undt-' . $key;
		$name = $option_name . '[' . $key . ']';

		/*
		 * Wiederholungsfelder und das Wochenraster brauchen die volle Breite. Ein
		 * display:block auf th und td genuegt dafuer nicht: die Zelle landet dann
		 * in einer anonymen Tabellenzelle und bleibt so schmal wie die
		 * Beschriftungsspalte. Nur colspan verlaesst das Spaltenraster wirklich.
		 */
		if ( in_array( $field['type'], array( 'repeater', 'hours' ), true ) ) {
			self::wide_row( $key, $field, $value, $name, $id, $args );

			return;
		}

		echo '<tr class="undt-row">';

		echo '<th scope="row">';
		self::label( $key, $field, $id );
		self::basis( $field, $args['section_basis'] );
		echo '</th>';

		echo '<td>';
		UNDT_Controls::render( $key, $field, $value, $name, $id );

		/*
		 * Die Kopierknoepfe stehen unter dem Eingabefeld. Neben dem Feld ergaeben
		 * sie ueber die Seite eine zweite Spalte aus Schaltflaechen, und in der
		 * Beschriftungsspalte bricht ein laengerer Shortcode um.
		 */
		$shortcode = $args['with_copy'] && ! empty( $field['shortcode'] ) ? '[undt key="' . $key . '"]' : '';
		$dynamic   = '' !== $shortcode ? $key : ( isset( $field['dynamic'] ) ? (string) $field['dynamic'] : '' );

		UNDT_Copy::row( $shortcode, $dynamic );

		echo '</td>';

		echo '</tr>';
	}

	/**
	 * Zwei Felder in einer Zeile.
	 *
	 * @param array  $group       Felddefinitionen des Paares.
	 * @param array  $values      Gespeicherte Werte.
	 * @param string $option_name Name der Option.
	 * @param array  $args        with_copy und section_basis.
	 * @return void
	 */
	private static function pair_row( array $group, array $values, $option_name, array $args ) {
		$first = reset( $group );
		$title = '' !== $first['pair_label'] ? $first['pair_label'] : $first['label'];

		// Eine gemeinsame Rechtsgrundlage gehoert an die Zeile, nicht an jedes Feld.
		$bases = array_unique( array_filter( wp_list_pluck( $group, 'basis' ) ) );
		$shared = 1 === count( $bases ) ? (string) reset( $bases ) : '';

		echo '<tr class="undt-row undt-row--pair">';

		echo '<th scope="row">';
		echo '<span class="undt-pair-title">' . esc_html( $title ) . '</span>';

		if ( '' !== $shared ) {
			self::basis( array( 'basis' => $shared ), $args['section_basis'] );
		}

		echo '</th>';

		echo '<td><div class="undt-pair">';

		foreach ( $group as $key => $field ) {
			$id    = 'undt-' . $key;
			$name  = $option_name . '[' . $key . ']';
			$value = array_key_exists( $key, $values ) ? $values[ $key ] : $field['default'];

			echo '<div class="undt-pair__field">';

			self::label( $key, $field, $id, 'undt-pair__label' );

			if ( '' === $shared ) {
				self::basis( $field, $args['section_basis'] );
			}

			UNDT_Controls::render( $key, $field, $value, $name, $id );

			$shortcode = $args['with_copy'] && ! empty( $field['shortcode'] ) ? '[undt key="' . $key . '"]' : '';
			$dynamic   = '' !== $shortcode ? $key : ( isset( $field['dynamic'] ) ? (string) $field['dynamic'] : '' );

			UNDT_Copy::row( $shortcode, $dynamic );

			echo '</div>';
		}

		echo '</div></td>';

		echo '</tr>';
	}

	/**
	 * Eine Zeile ueber die volle Tabellenbreite.
	 *
	 * Die Beschriftung wird zur Ueberschrift, weil diese Felder aus mehreren
	 * Eingaben bestehen und ein einzelnes label-Element deshalb ins Leere zeigen
	 * wuerde.
	 *
	 * @param string $key   Feldschluessel.
	 * @param array  $field Felddefinition.
	 * @param mixed  $value Aktueller Wert.
	 * @param string $name  Formularname.
	 * @param string $id    Element-ID.
	 * @param array  $args  with_copy und section_basis.
	 * @return void
	 */
	private static function wide_row( $key, array $field, $value, $name, $id, array $args ) {
		echo '<tr class="undt-row undt-row--wide"><td colspan="2">';

		echo '<h3 class="undt-field-title">' . esc_html( $field['label'] );

		if ( ! empty( $field['required'] ) ) {
			echo ' <span class="undt-required" aria-hidden="true">*</span>';
			echo '<span class="screen-reader-text">' . esc_html__( '(Pflichtangabe)', 'unternehmensdaten' ) . '</span>';
		}

		self::help( $key, $field );

		echo '</h3>';

		if ( ! empty( $field['basis'] ) && $field['basis'] !== $args['section_basis'] ) {
			echo '<p class="undt-basis undt-basis--block">' . esc_html( $field['basis'] ) . '</p>';
		}

		UNDT_Controls::render( $key, $field, $value, $name, $id );

		echo '</td></tr>';
	}

	/**
	 * Eine Frage des Einrichtungsassistenten als Zelle eines zweispaltigen Rasters.
	 *
	 * Die Fragen sind kurz und fast alle mit einem Schalter beantwortet. In einer
	 * einzigen Spalte stuende neben jeder Frage eine halbe Bildschirmbreite
	 * Nichts, und die Seite waere doppelt so lang. Eine Auswahl braucht dagegen
	 * die ganze Zelle, ihre Beschriftung steht deshalb darueber.
	 *
	 * @param string $key         Feldschluessel.
	 * @param array  $question    Fragedefinition.
	 * @param mixed  $value       Aktueller Wert.
	 * @param string $option_name Name der Option, wird zum Formularnamen.
	 * @return void
	 */
	public static function question_card( $key, array $question, $value, $option_name ) {
		$question = array_merge(
			array(
				'label'     => $key,
				'type'      => 'checkbox',
				'choices'   => array(),
				'help'      => '',
				'basis'     => '',
				'when'      => array(),
				'required'  => false,
				'shortcode' => false,
				'default'   => '',
				'full'      => false,
			),
			$question
		);

		self::card(
			$key,
			$question,
			$value,
			$option_name,
			array(
				'with_copy'   => false,
				'extra_class' => 'undt-question',
			)
		);
	}

	/**
	 * Eine Zelle des zweispaltigen Rasters.
	 *
	 * Links die Beschriftung, rechts das Eingabeelement. Eine Auswahl bekommt die
	 * ganze Zelle, ihre Beschriftung steht dann darueber.
	 *
	 * @param string $key         Feldschluessel.
	 * @param array  $field       Felddefinition.
	 * @param mixed  $value       Aktueller Wert.
	 * @param string $option_name Name der Option, wird zum Formularnamen.
	 * @param array  $args        with_copy, section_basis und extra_class.
	 * @return void
	 */
	private static function card( $key, array $field, $value, $option_name, array $args ) {
		$args = array_merge(
			array(
				'with_copy'     => true,
				'section_basis' => '',
				'extra_class'   => '',
			),
			$args
		);

		$id   = 'undt-' . $key;
		$name = $option_name . '[' . $key . ']';

		$classes = 'undt-card';

		if ( '' !== $args['extra_class'] ) {
			$classes .= ' ' . $args['extra_class'];
		}

		if ( 'checkbox' !== $field['type'] ) {
			$classes .= ' undt-card--stacked';
		}

		if ( ! empty( $field['full'] ) ) {
			$classes .= ' undt-card--full';
		}

		// Abhaengige Felder werden per JavaScript ein- und ausgeblendet.
		$attr = empty( $field['when'] )
			? ''
			: ' data-undt-when="' . esc_attr( (string) wp_json_encode( $field['when'] ) ) . '"';

		echo '<div class="' . esc_attr( $classes ) . '"' . $attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits escaped.

		echo '<div class="undt-card__text">';
		self::label( $key, $field, $id, 'undt-card__label' );
		self::basis( $field, $args['section_basis'] );

		$shortcode = $args['with_copy'] && ! empty( $field['shortcode'] ) ? '[undt key="' . $key . '"]' : '';
		$dynamic   = '' !== $shortcode ? $key : ( isset( $field['dynamic'] ) ? (string) $field['dynamic'] : '' );

		UNDT_Copy::row( $shortcode, $dynamic );

		echo '</div>';

		echo '<div class="undt-card__control">';
		UNDT_Controls::render( $key, $field, $value, $name, $id );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Mehrere Schalter nebeneinander in zwei Spalten.
	 *
	 * Ein Schalter braucht keine halbe Bildschirmbreite. Stehen mehrere
	 * hintereinander, wie bei Social Media, ergibt eine Spalte eine unnoetig
	 * lange Seite.
	 *
	 * @param array  $group       Felddefinitionen der Schalter.
	 * @param array  $values      Gespeicherte Werte.
	 * @param string $option_name Name der Option.
	 * @param array  $args        with_copy und section_basis.
	 * @return void
	 */
	private static function toggle_grid( array $group, array $values, $option_name, array $args ) {
		echo '<tr class="undt-row undt-row--wide undt-row--toggles"><td colspan="2">';
		echo '<div class="undt-cards">';

		foreach ( $group as $key => $field ) {
			$value = array_key_exists( $key, $values ) ? $values[ $key ] : $field['default'];

			self::card( $key, $field, $value, $option_name, $args );
		}

		echo '</div></td></tr>';
	}

	/**
	 * Die Schalter, die unmittelbar auf einen Schalter folgen.
	 *
	 * @param array  $fields Alle Felder des Abschnitts.
	 * @param string $start  Schluessel des ersten Schalters.
	 * @return array Felddefinitionen, Schluessel ist der Feldname.
	 */
	private static function toggle_run( array $fields, $start ) {
		$run     = array();
		$reached = false;

		foreach ( $fields as $key => $field ) {
			if ( $key === $start ) {
				$reached = true;
			}

			if ( ! $reached ) {
				continue;
			}

			if ( 'checkbox' !== $field['type'] || '' !== $field['pair'] ) {
				break;
			}

			$run[ $key ] = $field;
		}

		return $run;
	}

	/* ------------------------------------------------- Bausteine der Zeile */

	/**
	 * Beschriftung samt Pflichtmarkierung und Hilfe-Schalter.
	 *
	 * @param string $key   Feldschluessel.
	 * @param array  $field Felddefinition.
	 * @param string $id    Element-ID des Eingabefeldes.
	 * @param string $class Zusaetzliche Klasse.
	 * @return void
	 */
	private static function label( $key, array $field, $id, $class = '' ) {
		/*
		 * Beschriftung, Pflichtmarkierung und Fragezeichen stehen in einem
		 * gemeinsamen Block. Ohne ihn braeche das Sternchen bei gepaarten Feldern
		 * in die naechste Zeile um, weil die Beschriftung dort selbst ein Block
		 * ist.
		 */
		printf(
			'<span class="undt-label-line%s">',
			'' === $class ? '' : ' ' . esc_attr( $class )
		);

		printf(
			'<label for="%s">%s</label>',
			esc_attr( $id ),
			esc_html( $field['label'] )
		);

		if ( ! empty( $field['required'] ) ) {
			echo ' <span class="undt-required" aria-hidden="true">*</span>';
			echo '<span class="screen-reader-text">' . esc_html__( '(Pflichtangabe)', 'unternehmensdaten' ) . '</span>';
		}

		self::help( $key, $field );

		echo '</span>';
	}

	/**
	 * Die Rechtsgrundlage, sofern sie nicht schon am Abschnitt steht.
	 *
	 * @param array  $field         Felddefinition.
	 * @param string $section_basis Rechtsgrundlage des Abschnitts.
	 * @return void
	 */
	private static function basis( array $field, $section_basis ) {
		$basis = isset( $field['basis'] ) ? (string) $field['basis'] : '';

		if ( '' === $basis || $basis === $section_basis ) {
			return;
		}

		echo '<span class="undt-basis">' . esc_html( $basis ) . '</span>';
	}

	/**
	 * Das Fragezeichen samt Infobox.
	 *
	 * Kein Aufklapp-Absatz, sondern ein Hinweisfeld: es erscheint beim Ueberfahren
	 * und beim Tastaturfokus und laesst sich per Klick feststellen, damit der Text
	 * markierbar bleibt. Das Symbol traegt keinen Rahmen, sonst stuenden auf einer
	 * Seite drei Dutzend Schaltflaechen.
	 *
	 * @param string $key   Feldschluessel.
	 * @param array  $field Felddefinition.
	 * @return void
	 */
	public static function help( $key, array $field ) {
		if ( empty( $field['help'] ) ) {
			return;
		}

		$id = 'undt-help-' . $key;

		echo '<span class="undt-help-wrap" data-undt-help>';

		printf(
			'<button type="button" class="undt-help-toggle" aria-describedby="%1$s"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span><span class="screen-reader-text">%2$s</span></button>',
			esc_attr( $id ),
			esc_html(
				sprintf(
					/* translators: %s: Feldbezeichnung. */
					__( 'Hinweis zu %s', 'unternehmensdaten' ),
					$field['label']
				)
			)
		);

		printf(
			'<span class="undt-help" id="%s" role="tooltip">%s</span>',
			esc_attr( $id ),
			esc_html( $field['help'] )
		);

		echo '</span>';
	}
}
