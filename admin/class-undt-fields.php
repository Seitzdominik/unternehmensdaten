<?php
/**
 * Rendert die Eingabefelder aus den Registern.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Fields
 */
final class UNDT_Fields {

	/**
	 * Platzhalter fuer den Zeilenindex in der Vorlage eines Wiederholungsfeldes.
	 */
	const INDEX_TOKEN = '__UNDT_INDEX__';

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
		self::control( $key, $field, $value, $name, $id );

		/*
		 * Die Kopierknoepfe stehen unter dem Eingabefeld. Neben dem Feld ergaeben
		 * sie ueber die Seite eine zweite Spalte aus Schaltflaechen, und in der
		 * Beschriftungsspalte bricht ein laengerer Shortcode um.
		 */
		$shortcode = $args['with_copy'] && ! empty( $field['shortcode'] ) ? '[undt key="' . $key . '"]' : '';
		$dynamic   = '' !== $shortcode ? $key : ( isset( $field['dynamic'] ) ? (string) $field['dynamic'] : '' );

		self::copy_row( $shortcode, $dynamic );

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

			self::control( $key, $field, $value, $name, $id );

			$shortcode = $args['with_copy'] && ! empty( $field['shortcode'] ) ? '[undt key="' . $key . '"]' : '';
			$dynamic   = '' !== $shortcode ? $key : ( isset( $field['dynamic'] ) ? (string) $field['dynamic'] : '' );

			self::copy_row( $shortcode, $dynamic );

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

		self::control( $key, $field, $value, $name, $id );

		echo '</td></tr>';
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

	/**
	 * Das eigentliche Eingabeelement.
	 *
	 * @param string $key   Feldschluessel.
	 * @param array  $field Felddefinition.
	 * @param mixed  $value Aktueller Wert.
	 * @param string $name  Formularname.
	 * @param string $id    Element-ID.
	 * @return void
	 */
	public static function control( $key, array $field, $value, $name, $id ) {
		switch ( $field['type'] ) {
			case 'repeater':
				self::repeater( $field, $value, $name, $id );
				break;

			case 'hours':
				self::hours( $value, $name, $id );
				break;

			case 'media':
				self::media( $value, $name, $id );
				break;

			case 'textarea':
			case 'list':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="%3$d" class="large-text code">%4$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					'list' === $field['type'] ? 4 : 3,
					esc_textarea( (string) $value )
				);
				break;

			case 'select':
				$icons = ! empty( $field['icons'] );

				// Neben der Auswahl das Symbol der gewaehlten Plattform, siehe initIconSelects().
				if ( $icons ) {
					$current = array_key_exists( (string) $value, $field['choices'] ) ? (string) $value : (string) key( $field['choices'] );

					printf(
						'<span class="undt-select-icon"><span class="undt-select-icon__preview" aria-hidden="true">%s</span>',
						UNDT_Icons::platform( $current ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ueber wp_kses aufbereitet.
					);
				}

				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( $icons ? ' data-undt-icon-select' : '' ) . '>';

				foreach ( $field['choices'] as $choice => $label ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $choice ),
						selected( (string) $value, (string) $choice, false ),
						esc_html( $label )
					);
				}

				echo '</select>';

				if ( $icons ) {
					echo '</span>';
				}
				break;

			case 'checkbox':
				self::toggle( $name, $id, '1' === (string) $value );
				break;

			case 'page':
				self::link_control( $field, $value, $name, $id );
				break;

			case 'email':
			case 'url':
			case 'tel':
			case 'time':
			case 'date':
			case 'text':
			default:
				$types = array(
					'email' => 'email',
					'url'   => 'url',
					'tel'   => 'tel',
					'time'  => 'time',
					'date'  => 'date',
				);

				$classes = array(
					'time' => 'undt-input-time',
					'date' => 'undt-input-date',
				);

				// Ein Feld, das sich leer aus anderen Angaben ergibt, zeigt diesen Wert grau an.
				$placeholder = isset( $field['derived'] ) && is_callable( $field['derived'] )
					? (string) call_user_func( $field['derived'], $key )
					: '';

				// Adressen lesbar, also „Straße 11“ statt „Stra%C3%9Fe%2011“.
				if ( 'url' === $field['type'] ) {
					$placeholder = rawurldecode( $placeholder );
				}

				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="%5$s"%6$s />',
					esc_attr( isset( $types[ $field['type'] ] ) ? $types[ $field['type'] ] : 'text' ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( isset( $classes[ $field['type'] ] ) ? $classes[ $field['type'] ] : 'regular-text' ),
					'' === $placeholder ? '' : ' placeholder="' . esc_attr( $placeholder ) . '"'
				);
		}
	}

	/* -------------------------------------------------------- Seitenfelder */

	/**
	 * Hoechstzahl der Eintraege je Inhaltstyp in der Auswahl.
	 */
	const LINK_LIMIT = 500;

	/**
	 * Die geladenen Eintraege je Inhaltstyp, geteilt von allen Seitenfeldern.
	 *
	 * @var array
	 */
	private static $link_posts = array();

	/**
	 * Inhaltstypen, die nie als Rechtsseite taugen.
	 *
	 * Beitraege, Produkte und Anhaenge machten die Liste nur lang. Die Vorlagen
	 * der Page Builder sind oeffentlich und teils sogar fuer Menues
	 * freigegeben, Bricks etwa, aber keine eigenstaendigen Seiten.
	 */
	const LINK_EXCLUDED_TYPES = array(
		'attachment',
		'post',
		'product',
		'bricks_template',
		'elementor_library',
		'et_pb_layout',
		'fl-builder-template',
		'ct_template',
		'breakdance_template',
		'breakdance_header',
		'breakdance_footer',
		'breakdance_popup',
		'breakdance_block',
	);

	/**
	 * Die Inhaltstypen, aus denen Seitenfelder waehlen.
	 *
	 * Neben Seiten alle eigenen Inhaltstypen, die sich in Menues verlinken
	 * lassen, denn Rechtstexte liegen oft in einem eigenen Typ.
	 *
	 * @return array Name => Bezeichnung.
	 */
	public static function link_post_types() {
		$types = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type => $object ) {
			if ( in_array( $type, self::LINK_EXCLUDED_TYPES, true ) || empty( $object->show_in_nav_menus ) ) {
				continue;
			}

			$types[ $type ] = (string) $object->labels->name;
		}

		/**
		 * Erlaubt das Anpassen der Inhaltstypen in der Auswahl der Seitenfelder.
		 *
		 * @param array $types Name => Bezeichnung.
		 */
		$types = apply_filters( 'undt_link_post_types', $types );

		if ( ! is_array( $types ) ) {
			return array();
		}

		return array_filter(
			$types,
			static function ( $type ) {
				return is_string( $type ) && post_type_exists( $type );
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Die waehlbaren Eintraege eines Inhaltstyps.
	 *
	 * @param string $type Inhaltstyp.
	 * @return array WP_Post-Objekte.
	 */
	private static function link_posts( $type ) {
		if ( ! isset( self::$link_posts[ $type ] ) ) {
			self::$link_posts[ $type ] = get_posts(
				array(
					'post_type'              => $type,
					'post_status'            => array( 'publish', 'private', 'draft', 'pending', 'future' ),
					'posts_per_page'         => self::LINK_LIMIT,
					'orderby'                => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
		}

		return self::$link_posts[ $type ];
	}

	/**
	 * Auswahl eines Eintrags oder eine eigene Adresse.
	 *
	 * Gespeichert wird genau eines davon, siehe UNDT_Sanitizer::page_value().
	 * Die Auswahl zeigt auch Eintraege, die gerade nicht veroeffentlicht sind.
	 * Fehlte der gespeicherte Eintrag darin, waehlte der Browser „keine Seite“,
	 * und das naechste Speichern loeste die Verknuepfung stillschweigend. Ob
	 * verlinkt wird, entscheidet die Ausgabe.
	 *
	 * @param array  $field Felddefinition.
	 * @param mixed  $value Gespeicherte ID oder Adresse.
	 * @param string $name  Formularname.
	 * @param string $id    Element-ID.
	 * @return void
	 */
	private static function link_control( array $field, $value, $name, $id ) {
		$value    = is_scalar( $value ) ? trim( (string) $value ) : '';
		$is_id    = (bool) preg_match( '/^\d+$/', $value );
		$selected = $is_id ? (int) $value : 0;
		$url      = $is_id ? '' : $value;
		$choice   = '' === $url ? (string) $selected : 'url';
		$found    = 0 === $selected;
		$walk     = array(
			'selected'    => $selected,
			'value_field' => 'ID',
		);
		$groups   = '';

		add_filter( 'list_pages', array( __CLASS__, 'page_status_label' ), 10, 2 );

		foreach ( self::link_post_types() as $type => $label ) {
			$posts = self::link_posts( $type );

			if ( empty( $posts ) ) {
				continue;
			}

			if ( ! $found && in_array( $selected, wp_list_pluck( $posts, 'ID' ), true ) ) {
				$found = true;
			}

			$groups .= '<optgroup label="' . esc_attr( $label ) . '">';
			$groups .= walk_page_dropdown_tree( $posts, is_post_type_hierarchical( $type ) ? 0 : -1, $walk );
			$groups .= '</optgroup>';
		}

		// Ein gespeicherter Eintrag ausserhalb der Liste bleibt sichtbar, statt beim Speichern zu verschwinden.
		if ( ! $found ) {
			$post = get_post( $selected );

			if ( $post instanceof WP_Post ) {
				$current = walk_page_dropdown_tree( array( $post ), -1, $walk );
			} else {
				$current = sprintf(
					'<option value="%1$d" selected="selected">%2$s</option>',
					$selected,
					/* translators: %d: ID des gespeicherten Eintrags. */
					esc_html( sprintf( __( 'Nicht mehr vorhanden (ID %d)', 'unternehmensdaten' ), $selected ) )
				);
			}

			$groups = '<optgroup label="' . esc_attr__( 'Bisherige Auswahl', 'unternehmensdaten' ) . '">' . $current . '</optgroup>' . $groups;
		}

		remove_filter( 'list_pages', array( __CLASS__, 'page_status_label' ), 10 );

		echo '<div class="undt-link">';

		printf(
			'<select id="%1$s" name="%2$s" data-undt-link><option value="0"%3$s>%4$s</option><option value="url"%5$s>%6$s</option>',
			esc_attr( $id ),
			esc_attr( $name . '[choice]' ),
			selected( $choice, '0', false ),
			esc_html__( '— keine Seite —', 'unternehmensdaten' ),
			selected( $choice, 'url', false ),
			esc_html__( 'Eigene Adresse …', 'unternehmensdaten' )
		);

		echo wp_kses(
			$groups,
			array(
				'optgroup' => array(
					'label' => array(),
				),
				'option'   => array(
					'value'    => array(),
					'selected' => array(),
					'class'    => array(),
				),
			)
		);

		echo '</select>';

		printf(
			'<input type="text" inputmode="url" spellcheck="false" id="%1$s-url" name="%2$s" value="%3$s" class="regular-text undt-link__url" placeholder="%4$s" aria-label="%5$s" data-undt-link-url%6$s />',
			esc_attr( $id ),
			esc_attr( $name . '[url]' ),
			esc_attr( $url ),
			esc_attr__( 'https://… oder /pfad/', 'unternehmensdaten' ),
			/* translators: %s: Feldbezeichnung, etwa „Seite: Impressum“. */
			esc_attr( sprintf( __( '%s, eigene Adresse', 'unternehmensdaten' ), $field['label'] ) ),
			'url' === $choice ? '' : ' hidden'
		);

		echo '</div>';
	}

	/**
	 * Kennzeichnet nicht veroeffentlichte Eintraege in der Auswahl.
	 *
	 * Haengt nur waehrend des Aufbaus der Auswahl am Filter list_pages.
	 *
	 * @param string $title Seitentitel.
	 * @param mixed  $page  Seite.
	 * @return string
	 */
	public static function page_status_label( $title, $page = null ) {
		if ( ! $page instanceof WP_Post || 'publish' === $page->post_status ) {
			return $title;
		}

		$status = get_post_status_object( $page->post_status );

		return null === $status ? $title : sprintf( '%1$s (%2$s)', $title, $status->label );
	}

	/* ------------------------------------------------- Wiederholungsfelder */

	/**
	 * Ein Wiederholungsfeld.
	 *
	 * Die Reihenfolge im Formular bestimmt die Reihenfolge in der Ausgabe: PHP
	 * baut das Array in der Reihenfolge auf, in der die Felder gesendet werden,
	 * und der Sanitizer nummeriert anschliessend neu. Zeilen zu verschieben
	 * heisst deshalb nur, die Knoten zu tauschen.
	 *
	 * @param array  $field Felddefinition.
	 * @param mixed  $value Gespeicherte Zeilen.
	 * @param string $name  Basis-Formularname.
	 * @param string $id    Element-ID.
	 * @return void
	 */
	private static function repeater( array $field, $value, $name, $id ) {
		$rows = is_array( $value ) ? array_values( $value ) : array();

		printf(
			'<div class="undt-repeater" id="%s" data-undt-repeater data-undt-next="%d">',
			esc_attr( $id ),
			(int) count( $rows )
		);

		echo '<div class="undt-repeater__rows">';

		foreach ( $rows as $index => $row ) {
			self::repeater_row( $field, $name, (string) $index, is_array( $row ) ? $row : array() );
		}

		echo '</div>';

		// Vorlage fuer neue Zeilen. Inhalt eines template-Elements wird vom
		// Browser nicht gerendert und nicht mit abgesendet.
		echo '<template class="undt-repeater__template">';
		self::repeater_row( $field, $name, self::INDEX_TOKEN, array() );
		echo '</template>';

		// Die Symbole aller Auswahlen, aus denen das Skript beim Umschalten nimmt.
		foreach ( $field['fields'] as $sub ) {
			if ( 'select' !== $sub['type'] || empty( $sub['icons'] ) ) {
				continue;
			}

			echo '<div class="undt-icon-library" hidden>';

			foreach ( array_keys( $sub['choices'] ) as $choice ) {
				printf(
					'<span data-undt-icon="%1$s">%2$s</span>',
					esc_attr( $choice ),
					UNDT_Icons::platform( (string) $choice ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ueber wp_kses aufbereitet.
				);
			}

			echo '</div>';
			break;
		}

		printf(
			'<p class="undt-repeater__actions"><button type="button" class="button undt-repeater__add">%s</button></p>',
			esc_html(
				sprintf(
					/* translators: %s: Bezeichnung eines Eintrags, etwa Position. */
					__( '%s hinzufügen', 'unternehmensdaten' ),
					$field['single']
				)
			)
		);

		printf(
			'<p class="undt-repeater__empty description"%s>%s</p>',
			empty( $rows ) ? '' : ' hidden',
			esc_html__( 'Noch keine Einträge.', 'unternehmensdaten' )
		);

		echo '</div>';
	}

	/**
	 * Eine Zeile eines Wiederholungsfeldes.
	 *
	 * @param array  $field  Felddefinition.
	 * @param string $name   Basis-Formularname.
	 * @param string $index  Zeilenindex oder Platzhalter.
	 * @param array  $values Werte der Zeile.
	 * @return void
	 */
	private static function repeater_row( array $field, $name, $index, array $values ) {
		echo '<div class="undt-repeater__row">';

		echo '<div class="undt-repeater__fields">';

		foreach ( $field['fields'] as $sub_key => $sub ) {
			$sub_name = $name . '[' . $index . '][' . $sub_key . ']';
			$sub_val  = array_key_exists( $sub_key, $values ) ? $values[ $sub_key ] : $sub['default'];

			/*
			 * Der Index steht am Ende der ID, damit das Skript ihn beim Klonen der
			 * Vorlage genauso ersetzen kann wie im Formularnamen. Sonst traegt jede
			 * neu hinzugefuegte Zeile dieselbe ID und die Beschriftungen zeigen
			 * alle auf dasselbe Feld.
			 */
			$sub_id = 'undt-f-' . substr( md5( $name . '|' . $sub_key ), 0, 8 ) . '-' . $index;

			printf(
				'<div class="undt-repeater__field undt-repeater__field--%s">',
				esc_attr( $sub['type'] )
			);

			printf(
				'<label class="undt-repeater__label" for="%s">%s</label>',
				esc_attr( $sub_id ),
				esc_html( $sub['label'] )
			);

			self::control( $sub_key, $sub, $sub_val, $sub_name, $sub_id );

			if ( '' !== $sub['help'] ) {
				echo '<span class="undt-repeater__help description">' . esc_html( $sub['help'] ) . '</span>';
			}

			echo '</div>';
		}

		echo '</div>';

		/*
		 * Verschieben ueber Schaltflaechen statt Ziehen: mit Tastatur bedienbar
		 * und ohne zusaetzliche Bibliothek. Die beiden Pfeile bilden eine Gruppe,
		 * das Entfernen steht mit Abstand daneben, damit es nicht versehentlich
		 * getroffen wird.
		 */
		echo '<div class="undt-repeater__controls"><span class="undt-repeater__order">';
		self::icon_button( 'undt-repeater__move', 'up', __( 'Nach oben verschieben', 'unternehmensdaten' ), 'up' );
		self::icon_button( 'undt-repeater__move', 'down', __( 'Nach unten verschieben', 'unternehmensdaten' ), 'down' );
		echo '</span>';
		self::icon_button( 'undt-repeater__remove undt-icon-button--danger', 'remove', __( 'Eintrag entfernen', 'unternehmensdaten' ) );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Eine Schaltflaeche, die nur ein Symbol zeigt.
	 *
	 * Die Symbole sind eigene SVG-Pfade statt Dashicons: sie sitzen pixelgenau in
	 * der Schaltflaeche und haengen an keiner Schriftdatei.
	 *
	 * @param string $class Zusaetzliche Klassen.
	 * @param string $icon  up, down oder remove.
	 * @param string $label Beschriftung fuer Screenreader und Tooltip.
	 * @param string $dir   Richtung fuer das Skript, leer ohne.
	 * @return void
	 */
	private static function icon_button( $class, $icon, $label, $dir = '' ) {
		$paths = array(
			'up'     => 'M5 12.5l5-5 5 5',
			'down'   => 'M5 7.5l5 5 5-5',
			'remove' => 'M3.5 5.5h13M8 5.5V3.75h4V5.5M5.25 5.5l.8 10.75h7.9l.8-10.75M8.5 8.5v5M11.5 8.5v5',
		);

		if ( ! isset( $paths[ $icon ] ) ) {
			return;
		}

		printf(
			'<button type="button" class="undt-icon-button %1$s"%2$s aria-label="%3$s" title="%3$s"><svg class="undt-icon-button__icon" viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="%4$s"/></svg></button>',
			esc_attr( $class ),
			'' === $dir ? '' : ' data-dir="' . esc_attr( $dir ) . '"',
			esc_attr( $label ),
			esc_attr( $paths[ $icon ] )
		);
	}

	/* ---------------------------------------------------- Öffnungszeiten */

	/**
	 * Das Wochenraster der Oeffnungszeiten.
	 *
	 * @param mixed  $value Gespeicherte Zeiten.
	 * @param string $name  Basis-Formularname.
	 * @param string $id    Element-ID.
	 * @return void
	 */
	private static function hours( $value, $name, $id ) {
		$data = UNDT_Hours::normalize( $value );

		echo '<table class="undt-hours-edit widefat" id="' . esc_attr( $id ) . '">';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Tag', 'unternehmensdaten' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Geschlossen', 'unternehmensdaten' ) . '</th>';
		echo '<th scope="col" colspan="2">' . esc_html__( 'Zeitfenster 1', 'unternehmensdaten' ) . '</th>';
		echo '<th scope="col" colspan="2">' . esc_html__( 'Zeitfenster 2', 'unternehmensdaten' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( UNDT_Hours::display_order() as $day ) {
			$row      = $data[ $day ];
			$day_name = $name . '[' . $day . ']';

			echo '<tr class="undt-hours-edit__row" data-undt-day>';
			echo '<th scope="row">' . esc_html( UNDT_Hours::day_label( $day ) ) . '</th>';

			echo '<td>';
			self::toggle(
				$day_name . '[closed]',
				'undt-' . $day . '-closed',
				'1' === (string) $row['closed'],
				array(
					'data-undt-closed' => '1',
					'aria-label'       => sprintf(
						/* translators: %s: Wochentag. */
						__( 'Am %s geschlossen', 'unternehmensdaten' ),
						UNDT_Hours::day_label( $day )
					),
				)
			);
			echo '</td>';

			for ( $i = 0; $i < UNDT_Hours::SLOTS; $i++ ) {
				foreach ( array( 'from', 'to' ) as $edge ) {
					$field_name = $day_name . '[slots][' . $i . '][' . $edge . ']';
					$field_id   = 'undt-' . $day . '-' . $i . '-' . $edge;

					echo '<td>';
					printf(
						'<label class="screen-reader-text" for="%1$s">%2$s</label>',
						esc_attr( $field_id ),
						esc_html(
							sprintf(
								/* translators: 1: Wochentag, 2: Nummer des Zeitfensters, 3: von oder bis. */
								__( '%1$s, Zeitfenster %2$d, %3$s', 'unternehmensdaten' ),
								UNDT_Hours::day_label( $day ),
								$i + 1,
								'from' === $edge ? __( 'von', 'unternehmensdaten' ) : __( 'bis', 'unternehmensdaten' )
							)
						)
					);
					printf(
						'<input type="time" id="%1$s" name="%2$s" value="%3$s" class="undt-input-time" />',
						esc_attr( $field_id ),
						esc_attr( $field_name ),
						esc_attr( $row['slots'][ $i ][ $edge ] )
					);
					echo '</td>';
				}
			}

			echo '</tr>';
		}

		echo '</tbody></table>';

		printf(
			'<p class="undt-hours-edit__hint description">%s</p>',
			esc_html__( 'Ein Zeitfenster wird nur ausgegeben, wenn beide Uhrzeiten gesetzt sind.', 'unternehmensdaten' )
		);
	}

	/* -------------------------------------------------------------- Medien */

	/**
	 * Ein Medienfeld mit Vorschau.
	 *
	 * @param mixed  $value Anhang-ID.
	 * @param string $name  Formularname.
	 * @param string $id    Element-ID.
	 * @return void
	 */
	private static function media( $value, $name, $id ) {
		$attachment = (int) $value;
		$preview    = $attachment > 0 ? wp_get_attachment_image( $attachment, 'thumbnail', false, array( 'alt' => '' ) ) : '';

		echo '<div class="undt-media" data-undt-media>';

		printf(
			'<input type="hidden" id="%1$s" name="%2$s" value="%3$d" class="undt-media__value" />',
			esc_attr( $id ),
			esc_attr( $name ),
			$attachment
		);

		echo '<div class="undt-media__preview">';

		if ( '' !== $preview ) {
			echo wp_kses_post( $preview );
		}

		echo '</div>';

		echo '<p class="undt-media__actions">';
		printf(
			'<button type="button" class="button undt-media__select">%s</button> ',
			esc_html__( 'Bild auswählen', 'unternehmensdaten' )
		);
		printf(
			'<button type="button" class="button-link undt-media__clear"%s>%s</button>',
			$attachment > 0 ? '' : ' hidden',
			esc_html__( 'Entfernen', 'unternehmensdaten' )
		);
		echo '</p>';

		echo '</div>';
	}

	/**
	 * Ein Schalter statt eines Ankreuzfeldes.
	 *
	 * Darunter liegt weiterhin ein echtes Ankreuzfeld: es bleibt mit der Tastatur
	 * bedienbar, laesst sich ueber die Beschriftung in der ersten Spalte
	 * ansprechen und sendet ganz normal mit. Sichtbar ist stattdessen die
	 * gezeichnete Schiene. role="switch" sorgt dafuer, dass Screenreader „an“ und
	 * „aus“ ansagen statt „angekreuzt“.
	 *
	 * @param string $name    Formularname.
	 * @param string $id      Element-ID.
	 * @param bool   $checked Aktueller Zustand.
	 * @param array  $attrs   Zusaetzliche Attribute des Eingabefeldes.
	 * @return void
	 */
	public static function toggle( $name, $id, $checked, array $attrs = array() ) {
		// Verstecktes Feld, damit das Abwaehlen auch wirklich ankommt.
		printf( '<input type="hidden" name="%s" value="0" />', esc_attr( $name ) );

		$extra = '';

		foreach ( $attrs as $attr => $attr_value ) {
			// esc_attr() maskiert Werte, nicht Namen. Deshalb nur schlichte Attributnamen.
			if ( ! preg_match( '/^[a-z][a-z0-9-]*$/', (string) $attr ) ) {
				continue;
			}

			$extra .= sprintf(
				' %s="%s"',
				esc_attr( $attr ),
				esc_attr( (string) $attr_value )
			);
		}

		printf(
			'<span class="undt-switch"><input type="checkbox" role="switch" class="undt-switch__input" id="%1$s" name="%2$s" value="1"%3$s%4$s /><span class="undt-switch__track" aria-hidden="true"></span></span>',
			esc_attr( $id ),
			esc_attr( $name ),
			checked( $checked, true, false ),
			$extra // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escaped.
		);
	}

	/**
	 * Eine Referenztabelle aus Ausgabe, Shortcode und Attributen.
	 *
	 * @param array $items Eintraege aus einem Katalog.
	 * @return void
	 */
	public static function shortcode_table( array $items ) {
		if ( empty( $items ) ) {
			return;
		}

		echo '<table class="widefat striped undt-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Ausgabe', 'unternehmensdaten' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Shortcode', 'unternehmensdaten' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Attribute', 'unternehmensdaten' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $items as $item ) {
			printf(
				'<tr class="undt-searchable" data-undt-text="%s">',
				esc_attr( strtolower( $item['title'] . ' ' . $item['code'] . ' ' . $item['desc'] ) )
			);

			echo '<td><strong>' . esc_html( $item['title'] ) . '</strong>';
			echo '<p class="description">' . esc_html( $item['desc'] ) . '</p></td>';

			echo '<td>';
			self::copy_button( $item['code'] );
			echo '</td>';

			echo '<td class="undt-atts">' . esc_html( $item['atts'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Die Kopierknoepfe unter einem Feld.
	 *
	 * Stammdaten zeigen den Shortcode, daneben stehen die Logos von Bricks und
	 * Etch fuer die jeweilige Schreibweise. Den Tag selbst nennt nur der
	 * Tooltip, damit unter jedem Feld eine Zeile genuegt. Felder der
	 * Inhaltsbereiche haben keinen Shortcode, manche aber einen Wert fuer die
	 * Builder, etwa die Angaben des Infobanners.
	 *
	 * @param string $shortcode Shortcode, leer fuer keinen.
	 * @param string $dynamic   Schluessel fuer Bricks und Etch, leer fuer keinen.
	 * @return void
	 */
	private static function copy_row( $shortcode, $dynamic ) {
		// Nur, was die Builder auch tatsaechlich aufloesen.
		$builder = '' !== $dynamic && array_key_exists( $dynamic, UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER ) );

		if ( '' === $shortcode && ! $builder ) {
			return;
		}

		echo '<div class="undt-copy-row">';

		if ( '' !== $shortcode ) {
			self::copy_button( $shortcode, 'inline' );
		}

		if ( $builder ) {
			$syntax = UNDT_Dynamic::syntax( $dynamic );

			self::copy_icon( $syntax['bricks'], 'bricks', 'Bricks' );
			self::copy_icon( $syntax['etch'], 'etch', 'Etch' );
		}

		echo '</div>';
	}

	/**
	 * Ein Kopierknopf, der statt des Textes nur ein Logo zeigt.
	 *
	 * @param string $text Zu kopierender Text.
	 * @param string $icon Name des Symbols, siehe UNDT_Icons::svg().
	 * @param string $tool Name des Werkzeugs fuer Tooltip und Screenreader.
	 * @return void
	 */
	private static function copy_icon( $text, $icon, $tool ) {
		$label = sprintf(
			/* translators: 1: Werkzeug, etwa Bricks, 2: zu kopierender Tag. */
			__( '%1$s: %2$s kopieren', 'unternehmensdaten' ),
			$tool,
			$text
		);

		// Das Logo sitzt in einem kleinen Rahmen, damit es als Knopf erkennbar ist.
		printf(
			'<button type="button" class="undt-copy undt-copy--icon undt-copy--%1$s" data-undt-copy="%2$s" title="%3$s"><span class="undt-copy__badge" aria-hidden="true">%4$s</span><span class="screen-reader-text">%3$s</span></button>',
			esc_attr( $icon ),
			esc_attr( $text ),
			esc_attr( $label ),
			UNDT_Icons::svg( $icon, 'undt-copy__icon' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes Markup.
		);
	}

	/**
	 * Ein Button, der den uebergebenen Text in die Zwischenablage kopiert.
	 *
	 * Zwei Auspraegungen: „button“ fuer die Referenzseiten, wo das Kopieren die
	 * Hauptsache ist, und „inline“ fuer die Feldbeschriftungen, wo eine
	 * vollwertige Schaltflaeche je Feld die Seite zustellen wuerde.
	 *
	 * @param string $text  Zu kopierender Text.
	 * @param string $style button oder inline.
	 * @return void
	 */
	public static function copy_button( $text, $style = 'button' ) {
		$label = sprintf(
			/* translators: %s: Shortcode, Tag oder Funktionsaufruf. */
			__( '%s kopieren', 'unternehmensdaten' ),
			$text
		);

		if ( 'inline' === $style ) {
			printf(
				'<button type="button" class="undt-copy undt-copy--inline" data-undt-copy="%1$s" title="%2$s"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><code>%3$s</code><span class="screen-reader-text">%2$s</span></button>',
				esc_attr( $text ),
				esc_attr( $label ),
				esc_html( $text )
			);

			return;
		}

		printf(
			'<button type="button" class="button button-small undt-copy" data-undt-copy="%1$s"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <code>%2$s</code><span class="screen-reader-text">%3$s</span></button>',
			esc_attr( $text ),
			esc_html( $text ),
			esc_attr( $label )
		);
	}
}
