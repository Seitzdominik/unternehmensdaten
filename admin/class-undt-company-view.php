<?php
/**
 * Die Seite „Firma“.
 *
 * Rechtsform als Karte oben, darunter die Angaben in Registerkarten. Welche
 * Karten, Abschnitte und Felder es gibt, folgt aus dem Feld-Register
 * (UNDT_Schema): jeder Abschnitt gehoert zu genau einer Karte, siehe tabs().
 *
 * Es werden alle Felder ausgegeben, auch die, die fuer die aktuelle Rechtsform
 * nicht gelten. Die stehen verborgen da: aendert sich eine Antwort in der
 * Rechtsform-Karte, blendet das Skript sie ohne Neuladen ein, und was schon
 * eingetragen war, ist noch da. Welche gelten, sagt state().
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Company_View
 */
final class UNDT_Company_View {

	/**
	 * Abschnitte, die auf „Firma“ nicht vorkommen: die Rechtsseiten stehen unter
	 * „Einbinden“.
	 */
	const ELSEWHERE = array( 'pages' );

	/**
	 * Die Registerkarten und ihre Abschnitte, in der Reihenfolge der Seite.
	 *
	 * @return array Karte => array( label, sections ).
	 */
	public static function tabs() {
		return array(
			'address'    => array( __( 'Anschrift', 'unternehmensdaten' ), array( 'identity', 'address' ) ),
			'contact'    => array( __( 'Kontakt', 'unternehmensdaten' ), array( 'contact' ) ),
			'tax'        => array( __( 'Steuer', 'unternehmensdaten' ), array( 'register', 'register2', 'representation', 'capital', 'tax' ) ),
			'profession' => array( __( 'Beruf', 'unternehmensdaten' ), array( 'profession', 'chamber', 'authority', 'insurance' ) ),
			'content'    => array( __( 'Inhalte & Datenschutz', 'unternehmensdaten' ), array( 'editorial', 'vsbg', 'privacy', 'other' ) ),
		);
	}

	/**
	 * Die Karte eines Abschnitts. Unbekannte Abschnitte, etwa aus dem Filter
	 * undt_fields, landen auf der letzten Karte.
	 *
	 * @param string $section Abschnitt.
	 * @return string
	 */
	public static function tab_of( $section ) {
		foreach ( self::tabs() as $tab => $def ) {
			if ( in_array( $section, $def[1], true ) ) {
				return $tab;
			}
		}

		return 'content';
	}

	/**
	 * Die aufklappbaren Gruppen fuer seltene Felder.
	 *
	 * @return array Gruppe => array( summary, note ).
	 */
	public static function more_groups() {
		return array(
			'maps'    => array( __( 'Kartenlinks', 'unternehmensdaten' ), __( 'Werden aus der Anschrift erzeugt', 'unternehmensdaten' ) ),
			'contact' => array( __( 'Weitere Kontaktwege', 'unternehmensdaten' ), __( 'Telefax, Website', 'unternehmensdaten' ) ),
			'chamber' => array( __( 'Mehr zur Kammer', 'unternehmensdaten' ), __( 'Anschrift, Website, KV', 'unternehmensdaten' ) ),
			'dpo'     => array( __( 'Datenschutzbeauftragter', 'unternehmensdaten' ), __( 'Ab 20 Personen, die mit Daten arbeiten', 'unternehmensdaten' ) ),
		);
	}

	/**
	 * Die Felder dieser Seite, nach Karte und Abschnitt geordnet.
	 *
	 * @return array Karte => Abschnitt => Feld => Definition.
	 */
	public static function grouped() {
		$grouped = array_fill_keys( array_keys( self::tabs() ), array() );

		foreach ( UNDT_Schema::fields() as $key => $field ) {
			if ( in_array( $field['section'], self::ELSEWHERE, true ) ) {
				continue;
			}

			$grouped[ self::tab_of( $field['section'] ) ][ $field['section'] ][ $key ] = $field;
		}

		return $grouped;
	}

	/**
	 * Was fuer die aktuelle Rechtsform gilt: Felder, Karten, Zaehler, Titel.
	 *
	 * Die Antwort auf das Speichern der Rechtsform traegt genau das, damit das
	 * Skript die Seite ohne Neuladen anpassen kann.
	 *
	 * @return array applies, missing, tabs (Karte => array( visible, label, count )), title, summary.
	 */
	public static function state() {
		$profile = UNDT_Store::profile();
		$applies = array();
		$missing = array();
		$tabs    = array();

		foreach ( self::grouped() as $tab => $sections ) {
			$count    = 0;
			$visible  = false;
			$register = false;

			foreach ( $sections as $section => $fields ) {
				foreach ( $fields as $key => $field ) {
					$on              = UNDT_Schema::applies( $field['when'], $profile );
					$applies[ $key ] = $on;

					if ( ! $on ) {
						continue;
					}

					$visible = true;

					if ( 'tax' !== $section ) {
						$register = true;
					}

					if ( ! empty( $field['required'] ) && ! UNDT_Store::has( $key ) ) {
						$missing[] = $key;
						++$count;
					}
				}
			}

			$def   = self::tabs()[ $tab ];
			$label = $def[0];

			// Mit Register oder Vertretung heisst die Karte „Register & Steuer“.
			if ( 'tax' === $tab && $register ) {
				$label = __( 'Register & Steuer', 'unternehmensdaten' );
			}

			$tabs[ $tab ] = array(
				'visible' => $visible,
				'label'   => $label,
				'count'   => $count,
			);
		}

		$form = UNDT_Schema::legal_form( $profile['legal_form'] );

		return array(
			'applies' => $applies,
			'missing' => $missing,
			'tabs'    => $tabs,
			'title'   => $form['label'],
			'summary' => self::summary( $profile ),
		);
	}

	/**
	 * Die Zusammenfassung unter dem Namen der Rechtsform.
	 *
	 * @param array $profile Profil.
	 * @return string
	 */
	public static function summary( array $profile ) {
		$parts = array();

		if ( ! empty( $profile['is_regulated'] ) ) {
			$parts[] = ! empty( $profile['is_medical'] ) ? __( 'Heilberuf', 'unternehmensdaten' ) : __( 'Reglementierter Beruf', 'unternehmensdaten' );
		}

		if ( ! empty( $profile['needs_authority'] ) ) {
			$parts[] = __( 'erlaubnispflichtig', 'unternehmensdaten' );
		}

		if ( ! empty( $profile['has_editorial'] ) ) {
			$parts[] = __( 'redaktionelle Inhalte', 'unternehmensdaten' );
		}

		if ( ! empty( $profile['sells_to_consumers'] ) ) {
			$parts[] = __( 'Verträge mit Verbrauchern', 'unternehmensdaten' );
		}

		$vat = array(
			'small_business' => __( 'Kleinunternehmer nach § 19 UStG', 'unternehmensdaten' ),
			'standard'       => __( 'umsatzsteuerpflichtig', 'unternehmensdaten' ),
		);

		if ( isset( $vat[ $profile['vat_status'] ] ) ) {
			$parts[] = $vat[ $profile['vat_status'] ];
		}

		return empty( $parts ) ? __( 'Keine besonderen Angaben', 'unternehmensdaten' ) : implode( ' · ', $parts );
	}

	/**
	 * Vorschlaege, die ein Klick in ein leeres Feld uebernimmt.
	 *
	 * @return array Feld => array( value, label ).
	 */
	public static function suggestions() {
		$suggestions = array();
		$owner       = UNDT_Store::get( 'owner_name' );

		if ( '' !== $owner && ! UNDT_Store::has( 'editorial_name' ) ) {
			$suggestions['editorial_name'] = array(
				'value' => $owner,
				/* translators: %s: Name des Inhabers. */
				'label' => sprintf( __( 'Inhaber übernehmen: %s', 'unternehmensdaten' ), $owner ),
			);
		}

		$state = UNDT_Store::get( 'state' );
		$text  = UNDT_Authorities::text( $state );

		if ( '' !== $text && ! UNDT_Store::has( 'privacy_authority' ) ) {
			$authority = UNDT_Authorities::for_state( $state );

			$suggestions['privacy_authority'] = array(
				'value' => $text,
				/* translators: %s: Name der Behoerde. */
				'label' => sprintf( __( 'Übernehmen: %s', 'unternehmensdaten' ), $authority['name'] ),
			);
		}

		return $suggestions;
	}

	/**
	 * Uebernimmt einen Vorschlag in die gespeicherten Angaben.
	 *
	 * @param string $key Feld.
	 * @return string|null Der uebernommene Wert, null wenn es keinen Vorschlag gibt.
	 */
	public static function apply_suggestion( $key ) {
		$suggestions = self::suggestions();

		if ( ! isset( $suggestions[ $key ] ) ) {
			return null;
		}

		$company         = get_option( UNDT_Store::OPTION_COMPANY, array() );
		$company         = is_array( $company ) ? $company : array();
		$company[ $key ] = $suggestions[ $key ]['value'];

		update_option( UNDT_Store::OPTION_COMPANY, $company );
		UNDT_Store::flush();

		return UNDT_Store::get( $key );
	}

	/* ---------------------------------------------------------- Ausgabe ---- */

	/**
	 * Die Karte „Rechtsform“ mit den Fragen zum Aufklappen.
	 *
	 * @return void
	 */
	public static function legal_form_card() {
		$state   = self::state();
		$profile = UNDT_Store::profile();
		$admin   = UNDT_Access::is_admin();

		echo '<section class="undt-box undt-legalform" id="rechtsform">';
		echo '<div class="undt-legalform__head">';
		echo '<span class="undt-legalform__icon">';
		UNDT_Ui::icon( 'scales', 18 );
		echo '</span>';
		echo '<div class="undt-legalform__text">';
		echo '<h2 class="undt-legalform__title" data-undt-legal-title>' . esc_html( $state['title'] ) . '</h2>';
		echo '<div class="undt-legalform__summary" data-undt-legal-summary>' . esc_html( $state['summary'] ) . '</div>';
		echo '</div>';

		if ( $admin ) {
			printf(
				'<button type="button" class="undt-btn undt-btn--secondary" aria-expanded="false" aria-controls="undt-legalform-body" data-undt-legal-toggle data-label-open="%1$s" data-label-close="%2$s">%1$s</button>',
				esc_attr__( 'Ändern', 'unternehmensdaten' ),
				esc_attr__( 'Fertig', 'unternehmensdaten' )
			);
		}

		echo '</div>';

		if ( $admin ) {
			echo '<div class="undt-legalform__body" id="undt-legalform-body" hidden>';
			echo '<form method="post" action="options.php" class="undt-form" data-undt-autosave="profile">';
			settings_fields( UNDT_Admin::GROUP_PROFILE );

			foreach ( UNDT_Schema::profile_questions() as $key => $question ) {
				self::question( $key, $question, isset( $profile[ $key ] ) ? $profile[ $key ] : '' );
			}

			UNDT_Ui::info( esc_html__( 'Deine Antworten wirken sofort: Angaben erscheinen oder verschwinden. Was du schon eingetragen hast, bleibt erhalten.', 'unternehmensdaten' ) );
			UNDT_Ui::noscript_submit();
			echo '</form></div>';
		}

		echo '</section>';
	}

	/**
	 * Eine Frage der Rechtsform: Text links, Schalter oder Auswahl rechts.
	 *
	 * @param string $key      Schluessel.
	 * @param array  $question Definition.
	 * @param mixed  $value    Antwort.
	 * @return void
	 */
	private static function question( $key, array $question, $value ) {
		$question = array_merge(
			array(
				'label'   => $key,
				'type'    => 'checkbox',
				'choices' => array(),
				'basis'   => '',
				'when'    => array(),
				'help'    => '',
			),
			$question
		);

		$id   = 'undt-' . $key;
		$name = UNDT_Store::OPTION_PROFILE . '[' . $key . ']';

		// Eine Frage, die von einer anderen abhaengt, ist eingerueckt.
		$nested = ! empty( $question['when'] ) && 'legal_form' !== key( $question['when'] );

		printf(
			'<div class="undt-question-row%1$s%2$s"%3$s>',
			'checkbox' === $question['type'] ? '' : ' undt-question-row--select',
			$nested ? ' undt-question-row--nested' : '',
			empty( $question['when'] ) ? '' : ' data-undt-when="' . esc_attr( (string) wp_json_encode( $question['when'] ) ) . '"'
		);

		echo '<div class="undt-question-row__text">';
		echo '<label for="' . esc_attr( $id ) . '" class="undt-question-row__label">' . esc_html( $question['label'] ) . '</label>';

		if ( '' !== $question['basis'] ) {
			echo '<div class="undt-question-row__basis">' . esc_html( $question['basis'] ) . '</div>';
		}

		echo '</div><div class="undt-question-row__control">';
		UNDT_Controls::render( $key, $question, $value, $name, $id );
		echo '</div></div>';
	}

	/**
	 * Das Formular mit den Registerkarten.
	 *
	 * @return void
	 */
	public static function form() {
		$state       = self::state();
		$company     = UNDT_Store::company();
		$sections    = UNDT_Schema::sections();
		$suggestions = self::suggestions();
		$copy        = UNDT_Access::is_admin();
		$first       = '';

		foreach ( $state['tabs'] as $tab => $info ) {
			if ( $info['visible'] && '' === $first ) {
				$first = $tab;
			}
		}

		echo '<form method="post" action="options.php" class="undt-form undt-box undt-company" data-undt-autosave="company">';
		settings_fields( UNDT_Admin::GROUP_COMPANY );

		echo '<div class="undt-tabs" role="tablist" aria-label="' . esc_attr__( 'Angaben', 'unternehmensdaten' ) . '">';

		foreach ( $state['tabs'] as $tab => $info ) {
			$active = $tab === $first;

			printf(
				'<button type="button" role="tab" id="undt-tab-%1$s" class="undt-tab%2$s" aria-controls="undt-panel-%1$s" aria-selected="%3$s" data-undt-tab="%1$s"%4$s><span data-undt-tab-label>%5$s</span><span class="undt-tab__count" data-undt-tab-count%6$s>%7$d<span class="screen-reader-text"> %8$s</span></span></button>',
				esc_attr( $tab ),
				$active ? ' is-active' : '',
				$active ? 'true' : 'false',
				$info['visible'] ? '' : ' hidden',
				esc_html( $info['label'] ),
				$info['count'] > 0 ? '' : ' hidden',
				(int) $info['count'],
				esc_html__( 'fehlen', 'unternehmensdaten' )
			);
		}

		echo '</div>';

		foreach ( self::grouped() as $tab => $tab_sections ) {
			printf(
				'<div id="undt-panel-%1$s" class="undt-panel" role="tabpanel" aria-labelledby="undt-tab-%1$s"%2$s>',
				esc_attr( $tab ),
				$tab === $first ? '' : ' hidden'
			);

			foreach ( $tab_sections as $section => $fields ) {
				self::section( $section, isset( $sections[ $section ] ) ? $sections[ $section ] : $section, $fields, $company, $state['applies'], $suggestions, $copy );
			}

			echo '</div>';
		}

		UNDT_Ui::noscript_submit();
		echo '</form>';
	}

	/**
	 * Ein Abschnitt: Ueberschrift, Zeilen, seltene Felder zum Aufklappen.
	 *
	 * @param string $section     Abschnitt.
	 * @param string $title       Titel.
	 * @param array  $fields      Felder.
	 * @param array  $company     Gespeicherte Angaben.
	 * @param array  $applies     Welche Felder gelten.
	 * @param array  $suggestions Vorschlaege.
	 * @param bool   $copy        Ob Kopierknoepfe erscheinen.
	 * @return void
	 */
	private static function section( $section, $title, array $fields, array $company, array $applies, array $suggestions, $copy ) {
		$visible = false;

		foreach ( array_keys( $fields ) as $key ) {
			$visible = $visible || ! empty( $applies[ $key ] );
		}

		$basis = UNDT_Fields::section_basis( $fields );

		printf( '<div class="undt-fsection" data-undt-section="%s"%s>', esc_attr( $section ), $visible ? '' : ' hidden' );
		echo '<h2 class="undt-fsection__title">' . esc_html( $title );

		if ( '' !== $basis ) {
			echo ' <span class="undt-fsection__basis">' . esc_html( $basis ) . '</span>';
		}

		echo '</h2>';

		if ( 'chamber' === $section ) {
			echo '<div data-undt-part="chamberbox">' . self::chamber_box() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in chamber_box() escaped.
		}

		if ( 'authority' === $section ) {
			self::permit_helper();
		}

		$main = array();
		$more = array();

		foreach ( $fields as $key => $field ) {
			// Felder ohne Zeile, etwa die Kennung der uebernommenen Kammer.
			if ( 'hidden' === $field['control'] ) {
				printf(
					'<input type="hidden" id="%1$s" name="%2$s" value="%3$s" />',
					esc_attr( 'undt-' . $key ),
					esc_attr( UNDT_Store::OPTION_COMPANY . '[' . $key . ']' ),
					esc_attr( isset( $company[ $key ] ) ? (string) $company[ $key ] : '' )
				);
				continue;
			}

			if ( '' !== $field['more'] ) {
				$more[ $field['more'] ][ $key ] = $field;
			} else {
				$main[ $key ] = $field;
			}
		}

		foreach ( self::rows( $main ) as $row ) {
			self::row( $row, $company, $applies, $suggestions, $copy, $basis );
		}

		$groups = self::more_groups();

		foreach ( $more as $group => $group_fields ) {
			$any = false;

			foreach ( array_keys( $group_fields ) as $key ) {
				$any = $any || ! empty( $applies[ $key ] );
			}

			$label = isset( $groups[ $group ] ) ? $groups[ $group ] : array( $group, '' );

			printf( '<div class="undt-fmore" data-undt-more%s>', $any ? '' : ' hidden' );
			UNDT_Ui::details_open( $label[0], $label[1] );

			foreach ( self::rows( $group_fields ) as $row ) {
				self::row( $row, $company, $applies, $suggestions, $copy, $basis );
			}

			UNDT_Ui::details_close();
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Fasst Felder mit gleichem pair zu einer Zeile zusammen.
	 *
	 * @param array $fields Felder.
	 * @return array Liste von Zeilen, jede ein Array Feld => Definition.
	 */
	private static function rows( array $fields ) {
		$rows  = array();
		$pairs = array();

		foreach ( $fields as $key => $field ) {
			if ( '' === $field['pair'] ) {
				$rows[] = array( $key => $field );
				continue;
			}

			if ( ! isset( $pairs[ $field['pair'] ] ) ) {
				$pairs[ $field['pair'] ] = count( $rows );
				$rows[]                  = array();
			}

			$rows[ $pairs[ $field['pair'] ] ][ $key ] = $field;
		}

		return $rows;
	}

	/**
	 * Eine Zeile: Beschriftung links, Feld oder Feldpaar rechts.
	 *
	 * @param array  $row         Feld => Definition.
	 * @param array  $company     Gespeicherte Angaben.
	 * @param array  $applies     Welche Felder gelten.
	 * @param array  $suggestions Vorschlaege.
	 * @param bool   $copy        Ob Kopierknoepfe erscheinen.
	 * @param string $basis       Rechtsgrundlage des Abschnitts.
	 * @return void
	 */
	private static function row( array $row, array $company, array $applies, array $suggestions, $copy, $basis ) {
		$keys     = array_keys( $row );
		$first    = reset( $row );
		$pair     = count( $row ) > 1;
		$visible  = false;
		$required = false;
		$missing  = false;

		foreach ( $row as $key => $field ) {
			$on       = ! empty( $applies[ $key ] );
			$visible  = $visible || $on;
			$required = $required || ( $on && ! empty( $field['required'] ) );
			$missing  = $missing || ( $on && ! empty( $field['required'] ) && ! UNDT_Store::has( $key ) );
		}

		$title    = $pair && '' !== $first['pair_label'] ? $first['pair_label'] : $first['label'];
		$optional = ! $required && empty( $first['quiet'] );
		$law      = '' !== (string) $first['basis'] ? (string) $first['basis'] : $basis;

		printf(
			'<div class="undt-frow%1$s" data-undt-row="%2$s"%3$s>',
			$missing ? ' is-missing' : '',
			esc_attr( implode( ' ', $keys ) ),
			$visible ? '' : ' hidden'
		);

		echo '<div class="undt-frow__label">';

		if ( $pair ) {
			echo '<span class="undt-frow__title">' . esc_html( $title ) . '</span>';
		} else {
			echo '<label class="undt-frow__title" for="' . esc_attr( 'undt-' . $keys[0] ) . '">' . esc_html( $title ) . '</label>';
		}

		if ( $optional ) {
			echo '<div class="undt-frow__optional">' . esc_html__( 'optional', 'unternehmensdaten' ) . '</div>';
		}

		echo '</div><div class="undt-frow__main"><div class="undt-frow__fields">';

		foreach ( $row as $key => $field ) {
			$value = array_key_exists( $key, $company ) ? $company[ $key ] : $field['default'];

			self::field( $key, $field, $value, $pair, ! empty( $applies[ $key ] ), $copy );
		}

		echo '</div>';

		// Leere Pflicht: Bernstein-Zeile unter dem Feld, das Skript schaltet sie mit.
		printf(
			'<p class="undt-amber undt-frow__missing"%1$s>%2$s</p>',
			$missing ? '' : ' hidden',
			esc_html( '' !== $law ? sprintf( /* translators: %s: Rechtsgrundlage. */ __( 'Pflichtangabe fehlt · %s', 'unternehmensdaten' ), $law ) : __( 'Pflichtangabe fehlt', 'unternehmensdaten' ) )
		);

		// Je Feld mit erwartetem Format ein Hinweis, auch im Paar.
		foreach ( $row as $key => $field ) {
			if ( '' !== $field['pattern_hint'] ) {
				printf(
					'<p class="undt-amber undt-frow__pattern" data-undt-pattern-for="%1$s" hidden>%2$s</p>',
					esc_attr( $key ),
					esc_html( $field['pattern_hint'] )
				);
			}
		}

		if ( '' !== (string) $first['help'] && ! $pair ) {
			echo '<p class="undt-frow__help">' . esc_html( $first['help'] ) . '</p>';
		}

		if ( isset( $suggestions[ $keys[0] ] ) ) {
			printf(
				'<div class="undt-frow__suggest"><button type="button" class="undt-btn undt-btn--secondary undt-btn--small" data-undt-suggest="%1$s">%2$s</button></div>',
				esc_attr( $keys[0] ),
				esc_html( $suggestions[ $keys[0] ]['label'] )
			);
		} elseif ( 'privacy_authority' === $keys[0] && '' === UNDT_Store::get( 'state' ) && ! UNDT_Store::has( 'privacy_authority' ) ) {
			echo '<p class="undt-frow__help">' . esc_html__( 'Wähle unter „Anschrift“ dein Bundesland, dann schlagen wir die zuständige Behörde vor.', 'unternehmensdaten' ) . '</p>';
		}

		echo '</div></div>';
	}

	/**
	 * Ein Feld mit Eingabe und Kopier-Symbol.
	 *
	 * @param string $key     Feld.
	 * @param array  $field   Definition.
	 * @param mixed  $value   Wert.
	 * @param bool   $pair    Ob es Teil eines Paares ist.
	 * @param bool   $applies Ob es gilt.
	 * @param bool   $copy    Ob ein Kopierknopf erscheint.
	 * @return void
	 */
	private static function field( $key, array $field, $value, $pair, $applies, $copy ) {
		$id   = 'undt-' . $key;
		$name = UNDT_Store::OPTION_COMPANY . '[' . $key . ']';

		printf(
			'<div class="undt-frow__field%1$s" data-undt-field="%2$s"%3$s%4$s%5$s>',
			'textarea' === $field['type'] || 'list' === $field['type'] ? ' undt-frow__field--wide' : '',
			esc_attr( $key ),
			empty( $field['required'] ) ? '' : ' data-undt-required',
			'' === $field['pattern'] ? '' : ' data-undt-pattern="' . esc_attr( $field['pattern'] ) . '"',
			$applies ? '' : ' hidden'
		);

		if ( $pair ) {
			echo '<label class="undt-frow__sub" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
		}

		echo '<div class="undt-frow__control">';

		if ( 'country' === $field['control'] ) {
			self::country( $value, $name, $id );
		} elseif ( 'profession' === $field['control'] ) {
			self::profession_control( $value, $name, $id );
		} else {
			UNDT_Controls::render( $key, $field, $value, $name, $id );
		}

		if ( $copy && ! empty( $field['shortcode'] ) && 'page' !== $field['type'] ) {
			UNDT_Copy::field_button( $key, $field['label'] );
		}

		echo '</div>';

		if ( $copy && ! empty( $field['shortcode'] ) ) {
			echo '<span class="undt-frow__copied" role="status"></span>';
		}

		echo '</div>';
	}

	/**
	 * Die Berufsbezeichnung: Auswahl der gesetzlichen Bezeichnungen, dazu
	 * „Andere Bezeichnung …“ mit freiem Text.
	 *
	 * Gespeichert wird immer das Textfeld. Die Auswahl hat keinen Namen; das
	 * Skript traegt die gewaehlte Bezeichnung ins Textfeld ein und blendet es
	 * aus. Ohne Skript bleibt das Textfeld sichtbar und genuegt allein.
	 *
	 * @param string $value Wert.
	 * @param string $name  Formularname.
	 * @param string $id    ID des Textfelds.
	 * @return void
	 */
	private static function profession_control( $value, $name, $id ) {
		$value  = (string) $value;
		$groups = UNDT_Professions::title_groups();
		$known  = '' !== UNDT_Professions::profession_for( $value );
		$other  = '' !== $value && ! $known;

		echo '<div class="undt-profession" data-undt-profession>';
		printf(
			'<select id="%1$s-choice" data-undt-profession-choice aria-label="%2$s"><option value="">%3$s</option>',
			esc_attr( $id ),
			esc_attr__( 'Gesetzliche Berufsbezeichnung', 'unternehmensdaten' ),
			esc_html__( '— auswählen —', 'unternehmensdaten' )
		);

		foreach ( $groups as $group => $titles ) {
			echo '<optgroup label="' . esc_attr( $group ) . '">';

			foreach ( $titles as $title ) {
				printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $title ), selected( $value, $title, false ) );
			}

			echo '</optgroup>';
		}

		printf(
			'<option value="%1$s"%2$s>%3$s</option></select>',
			esc_attr( UNDT_Professions::OTHER ),
			selected( $other, true, false ),
			esc_html__( 'Andere Bezeichnung …', 'unternehmensdaten' )
		);

		// Sichtbar ohne Skript; das Skript blendet es aus, solange eine Bezeichnung aus der Liste gilt.
		printf(
			'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="large-text" placeholder="%4$s" data-undt-profession-text%5$s />',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr__( 'Deine gesetzliche Berufsbezeichnung', 'unternehmensdaten' ),
			$known || '' === $value ? ' data-undt-hide' : ''
		);
		echo '</div>';
	}

	/* ------------------------------------------------ Kammer und Berufsrecht ---- */

	/**
	 * Der Kasten am Kopf von „Kammer und Berufsrecht“: Vorschlag aus Beruf und
	 * Bundesland, oder der Stand der uebernommenen Kammer.
	 *
	 * Steht in einem Element mit data-undt-part="chamberbox" und kommt nach dem
	 * Speichern neu mit, siehe UNDT_Autosave.
	 *
	 * @return string HTML.
	 */
	public static function chamber_box() {
		$company = UNDT_Store::company();
		$sugg    = UNDT_Professions::suggestion( $company );

		if ( '' === $sugg['profession'] ) {
			return '';
		}

		$beruf   = UNDT_Professions::profession( $sugg['profession'] );
		$states  = UNDT_Schema::states();
		$land    = isset( $states[ $sugg['state'] ] ) && '' !== $sugg['state'] ? $states[ $sugg['state'] ] : '';
		$title   = (string) $company['job_title'];
		$link    = '' !== $sugg['directory'] ? sprintf( '<a class="undt-action" href="%1$s" target="_blank" rel="noopener">%2$s<span class="screen-reader-text"> %3$s</span></a>', esc_url( $sugg['directory'] ), esc_html__( 'Zuständige Kammer prüfen', 'unternehmensdaten' ), esc_html__( '(öffnet in neuem Tab)', 'unternehmensdaten' ) ) : '';
		$out     = '';
		$button  = static function ( $value, $label ) {
			return sprintf( '<button type="button" class="undt-btn undt-btn--secondary undt-btn--small" data-undt-chamber-apply="%1$s">%2$s</button>', esc_attr( $value ), esc_html( $label ) );
		};
		$rules   = static function ( array $lines ) {
			$html = '<ul class="undt-csuggest__rules">';

			foreach ( $lines as $line ) {
				$html .= '<li>' . esc_html( $line['text'] ) . ( $line['ok'] ? '' : ' <span class="undt-csuggest__check">' . esc_html__( 'Bezeichnung noch prüfen', 'unternehmensdaten' ) . '</span>' ) . '</li>';
			}

			return $html . '</ul>';
		};

		// Uebernommen: Stand zeigen, Abweichungen melden.
		if ( '' !== $sugg['applied'] && UNDT_Store::has( 'chamber_name' ) ) {
			$open = array_filter(
				UNDT_Professions::rules( $sugg['profession'], $sugg['applied'] ),
				static function ( $line ) {
					return ! $line['ok'];
				}
			);

			$out .= '<p class="undt-csuggest__text">' . esc_html( sprintf( /* translators: %s: Stand der Daten, etwa 2026-10. */ __( 'Kammer und Regelungen stammen aus den Kammerdaten des Plugins, Stand %s. Gespeichert ist eine Kopie, die du anpassen kannst.', 'unternehmensdaten' ), UNDT_Professions::data()['stand'] ) ) . '</p>';

			if ( ! empty( $sugg['changed'] ) ) {
				$labels = array(
					'chamber_name' => __( 'Name', 'unternehmensdaten' ),
					'chamber_url'  => __( 'Website', 'unternehmensdaten' ),
				);
				$parts  = array();

				foreach ( $sugg['changed'] as $field => $new ) {
					$parts[] = $labels[ $field ] . ': ' . $new;
				}

				$out .= '<p class="undt-amber"><span class="undt-amber__dot" aria-hidden="true"></span>' . esc_html( sprintf( /* translators: %s: geaenderte Angaben. */ __( 'Die Kammerdaten haben sich geändert. %s', 'unternehmensdaten' ), implode( ' · ', $parts ) ) ) . '</p>';
				$out .= '<div class="undt-csuggest__actions">' . $button( $sugg['applied'], __( 'Neue Angaben übernehmen', 'unternehmensdaten' ) ) . $link . '</div>';
			} elseif ( ! empty( $open ) ) {
				$out .= '<p class="undt-csuggest__text">' . esc_html__( 'Diese Bezeichnungen sind nicht an der Quelle geprüft:', 'unternehmensdaten' ) . '</p>' . $rules( array_values( $open ) );
				$out .= '<div class="undt-csuggest__actions">' . $link . '</div>';
			} else {
				$out .= '<div class="undt-csuggest__actions">' . $link . '</div>';
			}

			return '<div class="undt-csuggest">' . $out . '</div>';
		}

		// Berufe ohne Kammerdaten: Hebamme (keine Kammer), Gesundheitshandwerk (Kammer selbst eintragen).
		if ( '' === $beruf['kammer_gruppe'] || 'handwerkskammern' === $beruf['kammer_gruppe'] ) {
			$lines = UNDT_Professions::rules( $sugg['profession'] );

			$out .= '<p class="undt-csuggest__text">' . esc_html(
				'' === $beruf['kammer_gruppe']
					/* translators: %s: Berufsbezeichnung. */
					? sprintf( __( 'Für %s gibt es keine Kammer. Vorschlag für die berufsrechtlichen Regelungen:', 'unternehmensdaten' ), $title )
					: __( 'Die Handwerkskammer trägst du selbst ein; sie richtet sich nach dem Kreis deines Betriebs. Vorschlag für die berufsrechtlichen Regelungen:', 'unternehmensdaten' )
			) . '</p>' . $rules( $lines );
			$out .= '<div class="undt-csuggest__actions">' . $button( '', __( 'Regelungen übernehmen', 'unternehmensdaten' ) ) . $link . '</div>';

			return '<div class="undt-csuggest">' . $out . '</div>';
		}

		$chambers = $sugg['chambers'];

		if ( empty( $chambers ) ) {
			$out .= '<p class="undt-csuggest__text">' . esc_html__( 'Wähle unter „Anschrift“ dein Bundesland, dann schlagen wir Kammer und Regelungen vor.', 'unternehmensdaten' ) . '</p>';
			$out .= '' !== $link ? '<div class="undt-csuggest__actions">' . $link . '</div>' : '';

			return '<div class="undt-csuggest">' . $out . '</div>';
		}

		if ( 1 === count( $chambers ) ) {
			$id     = (string) key( $chambers );
			$kammer = reset( $chambers );

			$out .= '<p class="undt-csuggest__text">' . esc_html(
				$kammer['bundesweit']
					/* translators: %s: Name der Kammer. */
					? sprintf( __( 'Zuständig ist bundesweit die %s. Sie bringt diese Regelungen mit:', 'unternehmensdaten' ), $kammer['name'] )
					/* translators: 1: Bundesland, 2: Name der Kammer. */
					: sprintf( __( 'Für %1$s passt die %2$s. Sie bringt diese Regelungen mit:', 'unternehmensdaten' ), $land, $kammer['name'] )
			) . '</p>' . $rules( UNDT_Professions::rules( $sugg['profession'], $id ) );
			$out .= '<div class="undt-csuggest__actions">' . $button( $id, __( 'Übernehmen', 'unternehmensdaten' ) ) . $link . '</div>';

			return '<div class="undt-csuggest">' . $out . '</div>';
		}

		// Mehrere Kammern im Land: der Nutzer waehlt, das Teilgebiet hilft.
		/* translators: %s: Bundesland. */
		$out .= '<p class="undt-csuggest__text">' . esc_html( sprintf( __( 'In %s gibt es mehrere Kammern. Maßgeblich ist der Ort deiner Niederlassung.', 'unternehmensdaten' ), $land ) ) . '</p>';
		$out .= '<label class="undt-mlabel" for="undt-chamber-choice">' . esc_html__( 'Deine Kammer', 'unternehmensdaten' ) . '</label><select id="undt-chamber-choice" class="undt-csuggest__choice" data-undt-chamber-choice><option value="">' . esc_html__( '— auswählen —', 'unternehmensdaten' ) . '</option>';

		foreach ( $chambers as $id => $kammer ) {
			$out .= '<option value="' . esc_attr( (string) $id ) . '">' . esc_html( $kammer['name'] . ' – ' . $kammer['hilfe'] ) . '</option>';
		}

		$out .= '</select>';
		$out .= '<div class="undt-csuggest__actions">' . str_replace( '<button ', '<button disabled ', $button( '', __( 'Übernehmen', 'unternehmensdaten' ) ) ) . $link . '</div>';

		return '<div class="undt-csuggest">' . $out . '</div>';
	}

	/**
	 * Hilfe im Abschnitt Aufsichtsbehoerde: die erlaubnispflichtige Taetigkeit
	 * waehlen, „Erlaubnis nach“ wird vorgeschlagen und die zustaendige Stelle
	 * genannt. Das Skript traegt den Text ins Feld ein, gespeichert wird wie
	 * bei jeder Eingabe.
	 *
	 * @return void
	 */
	private static function permit_helper() {
		$permits = UNDT_Professions::permits();

		if ( empty( $permits ) ) {
			return;
		}

		echo '<div class="undt-csuggest" data-undt-permit>';
		echo '<label class="undt-mlabel" for="undt-permit-choice">' . esc_html__( 'Deine Tätigkeit', 'unternehmensdaten' ) . ' <span class="undt-optional">· ' . esc_html__( 'optional', 'unternehmensdaten' ) . '</span></label>';
		echo '<select id="undt-permit-choice" class="undt-csuggest__choice" data-undt-permit-choice><option value="">' . esc_html__( '— auswählen —', 'unternehmensdaten' ) . '</option>';

		foreach ( $permits as $id => $permit ) {
			printf(
				'<option value="%1$s" data-note="%2$s" data-authority="%3$s" data-register="%4$s">%5$s</option>',
				esc_attr( (string) $id ),
				esc_attr( $permit['note'] ),
				esc_attr( $permit['authority'] ),
				esc_attr( $permit['register'] ),
				esc_html( $permit['label'] )
			);
		}

		echo '</select>';
		printf(
			'<p class="undt-csuggest__text" data-undt-permit-hint hidden data-template="%1$s"></p>',
			/* translators: %s: zustaendige Stelle. */
			esc_attr__( 'Zuständig ist: %s. Name und Anschrift der Stelle trägst du unten ein.', 'unternehmensdaten' )
		);
		printf(
			'<div class="undt-csuggest__actions"><button type="button" class="undt-btn undt-btn--secondary undt-btn--small" data-undt-permit-apply disabled>%s</button></div>',
			esc_html__( 'In „Erlaubnis nach“ übernehmen', 'unternehmensdaten' )
		);
		echo '</div>';
	}

	/**
	 * Uebernimmt Kammer und Regelungen in die gespeicherten Angaben.
	 *
	 * Geprueft wird gegen den Vorschlag, den der Server selbst berechnet: nur
	 * eine Kammer, die fuer Beruf und Land in Frage kommt, laesst sich
	 * uebernehmen.
	 *
	 * @param string $chamber Kammer, leer fuer Berufe ohne Kammerdaten.
	 * @return array|null Die geschriebenen Angaben, null wenn nicht zulaessig.
	 */
	public static function apply_chamber( $chamber ) {
		$company = UNDT_Store::company();
		$sugg    = UNDT_Professions::suggestion( $company );

		if ( '' === $sugg['profession'] ) {
			return null;
		}

		$beruf = UNDT_Professions::profession( $sugg['profession'] );
		$free  = '' === $beruf['kammer_gruppe'] || 'handwerkskammern' === $beruf['kammer_gruppe'];

		if ( $free ? '' !== $chamber : ! isset( $sugg['chambers'][ $chamber ] ) ) {
			return null;
		}

		$values = UNDT_Professions::values( $sugg['profession'], $chamber );
		$stored = get_option( UNDT_Store::OPTION_COMPANY, array() );
		$stored = is_array( $stored ) ? $stored : array();

		update_option( UNDT_Store::OPTION_COMPANY, array_merge( $stored, $values ) );
		UNDT_Store::flush();

		$out = array();

		foreach ( array_keys( $values ) as $key ) {
			$out[ $key ] = (string) UNDT_Store::get( $key );
		}

		return $out;
	}

	/**
	 * Das Land als Auswahl. Ein frueher frei eingetragenes Land bleibt waehlbar.
	 *
	 * @param string $value Wert.
	 * @param string $name  Formularname.
	 * @param string $id    ID.
	 * @return void
	 */
	private static function country( $value, $name, $id ) {
		$choices = array(
			''            => __( 'Deutschland (nicht ausgeben)', 'unternehmensdaten' ),
			'Deutschland' => __( 'Deutschland', 'unternehmensdaten' ),
			'Österreich'  => __( 'Österreich', 'unternehmensdaten' ),
			'Schweiz'     => __( 'Schweiz', 'unternehmensdaten' ),
		);

		$value = (string) $value;

		if ( '' !== $value && ! isset( $choices[ $value ] ) ) {
			$choices[ $value ] = $value;
		}

		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';

		foreach ( $choices as $choice => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $choice ),
				selected( $value, (string) $choice, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}
}
