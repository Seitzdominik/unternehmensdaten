<?php
/**
 * Die Eingabeelemente der Formularseiten.
 *
 * UNDT_Fields baut das Raster aus Zeilen und Zellen, diese Klasse fuellt es:
 * Text- und Auswahlfelder, Schalter, Seitenfelder, Wiederholungsfelder, das
 * Wochenraster der Oeffnungszeiten und die Bildauswahl. Welche Art ein Feld
 * hat, steht im Register, nicht hier.
 *
 * Die Trennung folgt der Frage, die man beim Lesen stellt: wo steht etwas
 * (UNDT_Fields) oder wie sieht es aus (hier).
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Controls
 */
final class UNDT_Controls {

	/**
	 * Platzhalter fuer den Zeilenindex in der Vorlage eines Wiederholungsfeldes.
	 *
	 * Steht auch in admin.js: initRepeaters() ersetzt ihn beim Klonen.
	 */
	const INDEX_TOKEN = '__UNDT_INDEX__';

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
	public static function render( $key, array $field, $value, $name, $id ) {
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

				/*
				 * Alles ausser Uhrzeit und Datum nimmt die ganze Breite der Spalte:
				 * large-text ist die Klasse, die WordPress dafuer vorsieht. Felder
				 * unterschiedlicher Breite untereinander lassen eine Seite
				 * verschachtelt wirken, auch wenn jedes fuer sich passt.
				 */
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
					esc_attr( isset( $classes[ $field['type'] ] ) ? $classes[ $field['type'] ] : 'large-text' ),
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
			'<input type="text" inputmode="url" spellcheck="false" id="%1$s-url" name="%2$s" value="%3$s" class="large-text undt-link__url" placeholder="%4$s" aria-label="%5$s" data-undt-link-url%6$s />',
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

			self::render( $sub_key, $sub, $sub_val, $sub_name, $sub_id );

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

		/*
		 * Die meisten Betriebe haben an den Werktagen dieselben Zeiten. Ohne
		 * diesen Knopf tippt man sie sechsmal ab.
		 */
		$order = UNDT_Hours::display_order();
		$first = reset( $order );

		printf(
			'<p class="undt-hours-edit__actions"><button type="button" class="button undt-hours-copy" data-undt-hours-copy="%1$s">%2$s</button></p>',
			esc_attr( $id ),
			esc_html(
				sprintf(
					/* translators: %s: erster Wochentag, etwa Montag. */
					__( 'Zeiten von %s auf alle Tage übernehmen', 'unternehmensdaten' ),
					UNDT_Hours::day_label( $first )
				)
			)
		);

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
			'<input type="hidden" id="%1$s" name="%2$s" value="%3$s" class="undt-media__value" />',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( (string) $attachment )
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
}
