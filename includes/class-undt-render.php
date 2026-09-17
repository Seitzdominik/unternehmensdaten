<?php
/**
 * HTML-Ausgabe.
 *
 * Grundsatz: semantisch korrektes HTML ohne jede Typografie. Es werden weder
 * Schriftart noch Schriftgroesse noch Farben gesetzt, damit die Ausgabe die
 * Gestaltung des Themes vollstaendig erbt. Das mitgelieferte CSS beschraenkt
 * sich auf Struktur, also Abstaende und Spalten.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Render
 */
final class UNDT_Render {

	/**
	 * Begrenzt die Ueberschriftenebene auf einen gueltigen Bereich.
	 *
	 * Die Ebene ist einstellbar, damit sich die Ausgabe in die
	 * Ueberschriftenhierarchie der Seite einfuegt, statt sie zu brechen.
	 * Eine luecken- und sprungfreie Hierarchie ist ein Kriterium der
	 * WCAG und damit auch des BFSG.
	 *
	 * @param mixed $level Gewuenschte Ebene.
	 * @return int
	 */
	private static function level( $level ) {
		$level = (int) $level;

		return min( 6, max( 2, $level ) );
	}

	/**
	 * Eine Ueberschrift.
	 *
	 * @param int    $level Ebene.
	 * @param string $text  Text, bereits unescaped erwartet.
	 * @return string
	 */
	private static function h( $level, $text ) {
		return sprintf( '<h%1$d class="undt-block__title">%2$s</h%1$d>', $level, esc_html( $text ) );
	}

	/**
	 * Mehrzeiligen Klartext escapen und Zeilenumbrueche erhalten.
	 *
	 * @param string $value Rohwert.
	 * @return string
	 */
	private static function multiline( $value ) {
		return nl2br( esc_html( trim( $value ) ) );
	}

	/**
	 * Eine Definitionsliste aus Beschriftung und bereits fertigem HTML.
	 *
	 * Die Gruppierung von dt und dd in einem div ist seit HTML 5.2 zulaessig
	 * und die einzige Moeglichkeit, Paare sauber als Raster zu setzen.
	 *
	 * @param array $rows Liste aus array( label, html ).
	 * @return string
	 */
	private static function dl( array $rows ) {
		$rows = array_filter(
			$rows,
			static function ( $row ) {
				return isset( $row[1] ) && '' !== trim( (string) $row[1] );
			}
		);

		if ( empty( $rows ) ) {
			return '';
		}

		$out = '<dl class="undt-dl">';

		foreach ( $rows as $row ) {
			$out .= '<div class="undt-dl__row">';
			$out .= '<dt>' . esc_html( $row[0] ) . '</dt>';
			$out .= '<dd>' . $row[1] . '</dd>';
			$out .= '</div>';
		}

		return $out . '</dl>';
	}

	/**
	 * Eine Liste von Namen als Absatz mit Zeilenumbruechen.
	 *
	 * @param array $lines Zeilen.
	 * @return string
	 */
	private static function lines( array $lines ) {
		if ( empty( $lines ) ) {
			return '';
		}

		return '<p>' . implode( '<br />', array_map( 'esc_html', $lines ) ) . '</p>';
	}

	/**
	 * Telefonnummer als waehlbarer Link.
	 *
	 * @param string $number Nummer.
	 * @param string $text   Sichtbarer Text, leer fuer die Nummer.
	 * @return string
	 */
	public static function tel_link( $number, $text = '' ) {
		$href = preg_replace( '#[^0-9+]#', '', $number );

		if ( '' === $href ) {
			return esc_html( $number );
		}

		return sprintf( '<a href="tel:%s">%s</a>', esc_attr( $href ), esc_html( '' === $text ? $number : $text ) );
	}

	/**
	 * E-Mail-Adresse, auf Wunsch gegen einfache Harvester verschleiert.
	 *
	 * @param string $email     Adresse.
	 * @param bool   $link      Als Link ausgeben.
	 * @param bool   $obfuscate Zeichen als HTML-Entities kodieren.
	 * @param string $text      Sichtbarer Text eines Links, leer fuer die Adresse.
	 * @return string
	 */
	public static function email_link( $email, $link = true, $obfuscate = false, $text = '' ) {
		if ( ! is_email( $email ) ) {
			return '';
		}

		$display = $obfuscate ? antispambot( $email ) : esc_html( $email );

		if ( ! $link ) {
			return $display;
		}

		$href = $obfuscate ? antispambot( $email, 1 ) : esc_attr( $email );

		return sprintf( '<a href="mailto:%s">%s</a>', $href, '' === $text ? $display : esc_html( $text ) );
	}

	/**
	 * Das vollstaendige Impressum.
	 *
	 * @param array $atts Shortcode-Attribute.
	 * @return string
	 */
	public static function imprint( array $atts ) {
		$level   = self::level( isset( $atts['heading_level'] ) ? $atts['heading_level'] : 2 );
		$profile = UNDT_Store::profile();
		$form    = UNDT_Schema::legal_form( $profile['legal_form'] );

		$out = '<div class="undt-block undt-imprint">';

		// 1. Anbieterkennzeichnung.
		$address = self::postal_address();

		if ( '' !== $address ) {
			$out .= self::h( $level, __( 'Angaben gemäß § 5 DDG', 'unternehmensdaten' ) );
			$out .= '<address class="undt-address">' . $address . '</address>';
		}

		// 2. Vertretung.
		$reps = UNDT_Store::get_lines( 'representatives' );

		if ( ! empty( $reps ) ) {
			$out .= self::h( $level, __( 'Vertreten durch', 'unternehmensdaten' ) );
			$out .= self::lines( $reps );
		}

		if ( UNDT_Store::has( 'board_chair' ) ) {
			$out .= self::h( $level, __( 'Vorsitzender des Aufsichtsrats', 'unternehmensdaten' ) );
			$out .= '<p>' . esc_html( UNDT_Store::get( 'board_chair' ) ) . '</p>';
		}

		// 3. Kontakt.
		$contact = self::dl(
			array(
				array( __( 'Telefon', 'unternehmensdaten' ), UNDT_Store::has( 'phone' ) ? self::tel_link( UNDT_Store::get( 'phone' ) ) : '' ),
				array( __( 'Telefax', 'unternehmensdaten' ), UNDT_Store::has( 'fax' ) ? esc_html( UNDT_Store::get( 'fax' ) ) : '' ),
				array( __( 'E-Mail', 'unternehmensdaten' ), self::email_link( UNDT_Store::get( 'email' ) ) ),
				array(
					__( 'Kontaktformular', 'unternehmensdaten' ),
					UNDT_Store::has( 'contact_form_url' )
						? '<a href="' . esc_url( UNDT_Store::get( 'contact_form_url' ) ) . '">' . esc_html( UNDT_Store::get( 'contact_form_url' ) ) . '</a>'
						: '',
				),
			)
		);

		if ( '' !== $contact ) {
			$out .= self::h( $level, __( 'Kontakt', 'unternehmensdaten' ) );
			$out .= $contact;
		}

		// 4. Registereintrag.
		$register = self::dl(
			array(
				array( __( 'Register', 'unternehmensdaten' ), esc_html( UNDT_Schema::register_label( $form['register'] ) ) ),
				array( __( 'Registergericht', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'register_court' ) ) ),
				array( __( 'Registernummer', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'register_number' ) ) ),
			)
		);

		if ( '' !== $register && UNDT_Store::has( 'register_number' ) ) {
			$out .= self::h( $level, __( 'Registereintrag', 'unternehmensdaten' ) );
			$out .= $register;
		}

		// 4b. Zweiter Registereintrag, etwa die Komplementaer-GmbH.
		if ( UNDT_Store::has( 'register_number_2' ) ) {
			$out .= self::h( $level, __( 'Persönlich haftende Gesellschafterin', 'unternehmensdaten' ) );
			$out .= self::dl(
				array(
					array( __( 'Firma', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'complementary_name' ) ) ),
					array( __( 'Register', 'unternehmensdaten' ), esc_html( UNDT_Schema::register_label( $form['second'] ) ) ),
					array( __( 'Registergericht', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'register_court_2' ) ) ),
					array( __( 'Registernummer', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'register_number_2' ) ) ),
				)
			);
		}

		// 5. Kapital.
		$capital = self::dl(
			array(
				array( __( 'Stammkapital', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'share_capital' ) ) ),
				array( __( 'Ausstehende Einlagen', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'outstanding_contributions' ) ) ),
			)
		);

		if ( '' !== $capital ) {
			$out .= self::h( $level, __( 'Kapital der Gesellschaft', 'unternehmensdaten' ) );
			$out .= $capital;
		}

		// 6. Abwicklung.
		$liquidators = UNDT_Store::get_lines( 'liquidator' );

		if ( ! empty( $liquidators ) ) {
			$out .= self::h( $level, __( 'Abwicklung', 'unternehmensdaten' ) );
			$out .= '<p>' . esc_html__( 'Die Gesellschaft befindet sich in Liquidation.', 'unternehmensdaten' ) . '</p>';
			$out .= self::lines( $liquidators );
		}

		// 7. Steuerliche Angaben.
		$tax = self::dl(
			array(
				array( __( 'Umsatzsteuer-Identifikationsnummer', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'vat_id' ) ) ),
				array( __( 'Wirtschafts-Identifikationsnummer', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'business_id' ) ) ),
			)
		);

		if ( '' !== $tax ) {
			$out .= self::h( $level, __( 'Steuerliche Angaben', 'unternehmensdaten' ) );
			$out .= $tax;
		}

		if ( UNDT_Store::has( 'small_business_note' ) ) {
			$out .= '<p>' . self::multiline( UNDT_Store::get( 'small_business_note' ) ) . '</p>';
		}

		// 8. Aufsichtsbehoerde.
		if ( UNDT_Store::has( 'authority_name' ) ) {
			$out .= self::h( $level, __( 'Zuständige Aufsichtsbehörde', 'unternehmensdaten' ) );

			if ( UNDT_Store::has( 'permit_note' ) ) {
				$out .= '<p>' . esc_html( UNDT_Store::get( 'permit_note' ) ) . '</p>';
			}

			/*
			 * Kein address-Element: das steht fuer die Kontaktdaten des Anbieters
			 * selbst, nicht fuer die Anschrift einer Behoerde.
			 */
			$out .= '<p class="undt-address">' . esc_html( UNDT_Store::get( 'authority_name' ) );

			if ( UNDT_Store::has( 'authority_address' ) ) {
				$out .= '<br />' . self::multiline( UNDT_Store::get( 'authority_address' ) );
			}

			$out .= '</p>';

			if ( UNDT_Store::has( 'authority_url' ) ) {
				$out .= '<p><a href="' . esc_url( UNDT_Store::get( 'authority_url' ) ) . '">' . esc_html( UNDT_Store::get( 'authority_url' ) ) . '</a></p>';
			}
		}

		// 9. Reglementierter Beruf.
		if ( UNDT_Store::has( 'job_title' ) ) {
			$out .= self::h( $level, __( 'Berufsbezeichnung und berufsrechtliche Regelungen', 'unternehmensdaten' ) );

			$out .= self::dl(
				array(
					array( __( 'Berufsbezeichnung', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'job_title' ) ) ),
					array( __( 'Verliehen in', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'job_title_country' ) ) ),
					array( __( 'Facharztbezeichnung', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'specialist_title' ) ) ),
					array( __( 'Zuständige Kammer', 'unternehmensdaten' ), UNDT_Store::has( 'chamber_name' ) ? self::chamber() : '' ),
					array( __( 'Kassenärztliche Vereinigung', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'kv_name' ) ) ),
					array( __( 'Ärztliche Leitung', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'medical_director' ) ) ),
				)
			);

			$rules = UNDT_Store::get_lines( 'prof_rules' );

			if ( ! empty( $rules ) ) {
				$out .= '<p>' . esc_html__( 'Es gelten die folgenden berufsrechtlichen Regelungen:', 'unternehmensdaten' ) . '</p>';
				$out .= '<ul class="undt-list">';

				foreach ( $rules as $rule ) {
					$out .= '<li>' . esc_html( $rule ) . '</li>';
				}

				$out .= '</ul>';
			}

			if ( UNDT_Store::has( 'prof_rules_url' ) ) {
				$out .= '<p>' . sprintf(
					/* translators: %s: Link zu den berufsrechtlichen Regelungen. */
					esc_html__( 'Die Regelungen sind einsehbar unter %s', 'unternehmensdaten' ),
					'<a href="' . esc_url( UNDT_Store::get( 'prof_rules_url' ) ) . '">' . esc_html( UNDT_Store::get( 'prof_rules_url' ) ) . '</a>'
				) . '</p>';
			}
		}

		// 10. Berufshaftpflichtversicherung.
		if ( UNDT_Store::has( 'insurer_name' ) ) {
			$out .= self::h( $level, __( 'Berufshaftpflichtversicherung', 'unternehmensdaten' ) );
			$out .= self::dl(
				array(
					array( __( 'Versicherer', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'insurer_name' ) ) ),
					array( __( 'Anschrift', 'unternehmensdaten' ), self::multiline( UNDT_Store::get( 'insurer_address' ) ) ),
					array( __( 'Räumlicher Geltungsbereich', 'unternehmensdaten' ), esc_html( UNDT_Store::get( 'insurance_scope' ) ) ),
				)
			);
		}

		// 11. Redaktionelle Verantwortung.
		if ( UNDT_Store::has( 'editorial_name' ) ) {
			$out .= self::h( $level, __( 'Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV', 'unternehmensdaten' ) );

			$editorial = UNDT_Store::has( 'editorial_address' )
				? self::multiline( UNDT_Store::get( 'editorial_address' ) )
				: self::postal_address( false );

			$out .= '<address class="undt-address">' . esc_html( UNDT_Store::get( 'editorial_name' ) ) . '<br />' . $editorial . '</address>';
		}

		// 12. Verbraucherstreitbeilegung.
		$out .= self::dispute_resolution( $level );

		$out .= '</div>';

		/**
		 * Erlaubt das Nachbearbeiten des fertigen Impressums.
		 *
		 * @param string $out   Fertiges HTML.
		 * @param array  $atts  Shortcode-Attribute.
		 */
		return apply_filters( 'undt_imprint_html', $out, $atts );
	}

	/**
	 * Kammer mit optionaler Anschrift und Link.
	 *
	 * @return string
	 */
	private static function chamber() {
		$out = esc_html( UNDT_Store::get( 'chamber_name' ) );

		if ( UNDT_Store::has( 'chamber_address' ) ) {
			$out .= '<br />' . self::multiline( UNDT_Store::get( 'chamber_address' ) );
		}

		if ( UNDT_Store::has( 'chamber_url' ) ) {
			$out .= '<br /><a href="' . esc_url( UNDT_Store::get( 'chamber_url' ) ) . '">' . esc_html( UNDT_Store::get( 'chamber_url' ) ) . '</a>';
		}

		return $out;
	}

	/**
	 * Hinweis zur Verbraucherstreitbeilegung.
	 *
	 * Bewusst ohne jeden Verweis auf die OS-Plattform der EU: die
	 * ODR-Verordnung wurde durch die Verordnung (EU) 2024/3228 aufgehoben, die
	 * Plattform ist seit dem 20.07.2025 abgeschaltet. Ein Link dorthin ist
	 * heute nicht nur ueberfluessig, sondern irrefuehrend.
	 *
	 * @param int $level Ueberschriftenebene.
	 * @return string
	 */
	private static function dispute_resolution( $level ) {
		$profile = UNDT_Store::profile();

		if ( empty( $profile['sells_to_consumers'] ) ) {
			return '';
		}

		if ( ! empty( $profile['vsbg_exempt'] ) && 'no' === $profile['vsbg_participation'] ) {
			return '';
		}

		$out = self::h( $level, __( 'Verbraucherstreitbeilegung', 'unternehmensdaten' ) );

		switch ( $profile['vsbg_participation'] ) {
			case 'obliged':
				$out .= '<p>' . esc_html__( 'Wir sind gesetzlich verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.', 'unternehmensdaten' ) . '</p>';
				break;

			case 'voluntary':
				$out .= '<p>' . esc_html__( 'Wir sind bereit, an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.', 'unternehmensdaten' ) . '</p>';
				break;

			default:
				$out .= '<p>' . esc_html__( 'Wir sind nicht bereit und nicht verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.', 'unternehmensdaten' ) . '</p>';
		}

		// Die Schlichtungsstelle ist nicht der Anbieter, deshalb kein address-Element.
		if ( UNDT_Store::has( 'vsbg_authority' ) ) {
			$out .= '<p class="undt-address">' . self::multiline( UNDT_Store::get( 'vsbg_authority' ) ) . '</p>';
		}

		return $out;
	}

	/**
	 * Die Anschrift als HTML-Fragment.
	 *
	 * @param bool $with_name Firmennamen und Inhaber voranstellen.
	 * @return string
	 */
	public static function postal_address( $with_name = true ) {
		$parts = array();

		if ( $with_name ) {
			if ( UNDT_Store::has( 'company_name' ) ) {
				$parts[] = esc_html( UNDT_Store::get( 'company_name' ) );
			}

			if ( UNDT_Store::has( 'owner_name' ) ) {
				$parts[] = esc_html( UNDT_Store::get( 'owner_name' ) );
			}
		}

		if ( UNDT_Store::has( 'address_addition' ) ) {
			$parts[] = esc_html( UNDT_Store::get( 'address_addition' ) );
		}

		if ( UNDT_Store::has( 'street' ) ) {
			$parts[] = esc_html( UNDT_Store::get( 'street' ) );
		}

		$locality = trim( UNDT_Store::get( 'postal_code' ) . ' ' . UNDT_Store::get( 'city' ) );

		if ( '' !== $locality ) {
			$parts[] = esc_html( $locality );
		}

		if ( UNDT_Store::has( 'country' ) ) {
			$parts[] = esc_html( UNDT_Store::get( 'country' ) );
		}

		return implode( '<br />', $parts );
	}

	/**
	 * Die Teile der einzeiligen Anschrift als schlichter Text.
	 *
	 * @return array Firma, Strasse und Ort, leere Teile ausgelassen.
	 */
	public static function address_parts() {
		return array_values(
			array_filter(
				array(
					trim( UNDT_Store::get( 'company_name' ) ),
					trim( UNDT_Store::get( 'street' ) ),
					trim( UNDT_Store::get( 'postal_code' ) . ' ' . UNDT_Store::get( 'city' ) ),
				),
				'strlen'
			)
		);
	}

	/**
	 * Die Anschrift einzeilig, etwa fuer den Footer.
	 *
	 * @param string $separator Trennzeichen.
	 * @return string
	 */
	public static function address_inline( $separator = ' · ' ) {
		return esc_html( implode( $separator, self::address_parts() ) );
	}

	/**
	 * Der Footer-Block.
	 *
	 * @param array $atts Shortcode-Attribute.
	 * @return string
	 */
	public static function footer( array $atts ) {
		$show = array_map( 'trim', explode( ',', isset( $atts['show'] ) ? $atts['show'] : '' ) );
		$show = array_filter( $show, 'strlen' );

		if ( empty( $show ) ) {
			$show = array( 'address', 'legal', 'copyright' );
		}

		$out = '<div class="undt-block undt-footer">';

		if ( in_array( 'address', $show, true ) ) {
			$address = self::address_inline();

			if ( '' !== $address ) {
				$out .= '<p class="undt-footer__address">' . $address . '</p>';
			}
		}

		if ( in_array( 'legal', $show, true ) ) {
			$out .= self::legal_nav();
		}

		if ( in_array( 'copyright', $show, true ) && UNDT_Store::has( 'company_name' ) ) {
			$out .= '<p class="undt-footer__copyright">&copy; ' . esc_html( date_i18n( 'Y' ) ) . ' ' . esc_html( UNDT_Store::get( 'company_name' ) ) . '</p>';
		}

		$out .= '</div>';

		/**
		 * Erlaubt das Nachbearbeiten des Footers.
		 *
		 * @param string $out  Fertiges HTML.
		 * @param array  $atts Shortcode-Attribute.
		 */
		return apply_filters( 'undt_footer_html', $out, $atts );
	}

	/**
	 * Navigation zu den Rechtsseiten.
	 *
	 * @return string
	 */
	public static function legal_nav() {
		$pages = array(
			'page_imprint'       => __( 'Impressum', 'unternehmensdaten' ),
			'page_privacy'       => __( 'Datenschutz', 'unternehmensdaten' ),
			'page_terms'         => __( 'AGB', 'unternehmensdaten' ),
			'page_accessibility' => __( 'Barrierefreiheit', 'unternehmensdaten' ),
		);

		$items = array();

		foreach ( $pages as $key => $label ) {
			// Nur veroeffentlichte Beitraege und eigene Adressen, siehe UNDT_Store::link().
			$link = UNDT_Store::link( $key );

			if ( null === $link ) {
				continue;
			}

			$items[] = sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( $link['url'] ),
				esc_html( $label )
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		return sprintf(
			'<nav class="undt-footer__nav" aria-label="%s"><ul class="undt-inline-list">%s</ul></nav>',
			esc_attr__( 'Rechtliche Hinweise', 'unternehmensdaten' ),
			implode( '', $items )
		);
	}

	/**
	 * Datenbausteine fuer die Datenschutzerklaerung.
	 *
	 * Das Plugin erzeugt bewusst keinen Datenschutztext: der haengt an den
	 * tatsaechlich eingesetzten Diensten und gehoert in die Hand einer
	 * Rechtsberatung. Es liefert die Angaben, die dort eingesetzt werden.
	 *
	 * @param array $atts Shortcode-Attribute.
	 * @return string
	 */
	public static function privacy_block( array $atts ) {
		$name  = isset( $atts['name'] ) ? sanitize_key( $atts['name'] ) : '';
		$level = self::level( isset( $atts['heading_level'] ) ? $atts['heading_level'] : 3 );

		switch ( $name ) {
			case 'controller':
				$address = self::postal_address();

				if ( '' === $address ) {
					return '';
				}

				$out = '<div class="undt-block undt-privacy-block">';

				if ( empty( $atts['bare'] ) ) {
					$out .= self::h( $level, __( 'Verantwortlicher im Sinne der DSGVO', 'unternehmensdaten' ) );
				}

				$out .= '<address class="undt-address">' . $address . '</address>';
				$out .= self::dl(
					array(
						array( __( 'Telefon', 'unternehmensdaten' ), UNDT_Store::has( 'phone' ) ? self::tel_link( UNDT_Store::get( 'phone' ) ) : '' ),
						array( __( 'E-Mail', 'unternehmensdaten' ), self::email_link( UNDT_Store::get( 'email' ) ) ),
					)
				);

				return $out . '</div>';

			case 'dpo':
				if ( ! UNDT_Store::has( 'dpo_name' ) && ! UNDT_Store::has( 'dpo_email' ) ) {
					return '';
				}

				$out = '<div class="undt-block undt-privacy-block">';

				if ( empty( $atts['bare'] ) ) {
					$out .= self::h( $level, __( 'Datenschutzbeauftragter', 'unternehmensdaten' ) );
				}

				$out .= '<address class="undt-address">';
				$out .= esc_html( UNDT_Store::get( 'dpo_name' ) );

				if ( UNDT_Store::has( 'dpo_address' ) ) {
					$out .= '<br />' . self::multiline( UNDT_Store::get( 'dpo_address' ) );
				}

				if ( UNDT_Store::has( 'dpo_email' ) ) {
					$out .= '<br />' . self::email_link( UNDT_Store::get( 'dpo_email' ) );
				}

				return $out . '</address></div>';

			case 'authority':
				if ( ! UNDT_Store::has( 'privacy_authority' ) ) {
					return '';
				}

				$out = '<div class="undt-block undt-privacy-block">';

				if ( empty( $atts['bare'] ) ) {
					$out .= self::h( $level, __( 'Zuständige Aufsichtsbehörde', 'unternehmensdaten' ) );
				}

				// Die Behoerde ist nicht der Verantwortliche, deshalb kein address-Element.
				$out .= '<p class="undt-address">' . self::multiline( UNDT_Store::get( 'privacy_authority' ) ) . '</p>';

				return $out . '</div>';
		}

		return '';
	}

	/**
	 * Das strukturelle CSS.
	 *
	 * Bewusst ohne font-family, font-size und Farben: alles davon erbt die
	 * Ausgabe vom Theme. Gesetzt werden nur Abstaende, Listenstruktur und das
	 * Raster der Definitionslisten. font-weight ist relativ angegeben, damit es
	 * sich an die Grundschrift des Themes anpasst statt sie zu ueberschreiben.
	 *
	 * @return string
	 */
	public static function css() {
		/**
		 * Erlaubt es, das mitgelieferte CSS vollstaendig abzuschalten.
		 *
		 * @param bool $enabled Standard: true.
		 */
		if ( ! apply_filters( 'undt_inline_css', true ) ) {
			return '';
		}

		/*
		 * Keine Aussenabstaende: Builder wie Bricks und Etch regeln Abstaende
		 * ueber ihre Container. Wer welche braucht, setzt --undt-block-spacing.
		 */
		$css = '.undt-block{margin:0 0 var(--undt-block-spacing,0)}'
			. '.undt-block>:last-child{margin-bottom:0}'
			. '.undt-block__title{margin:1.5em 0 .4em}'
			. '.undt-block__title:first-child{margin-top:0}'
			. '.undt-address{font-style:normal;margin:0 0 1em}'
			. '.undt-dl{margin:0 0 1em}'
			. '.undt-dl__row{display:grid;grid-template-columns:minmax(9em,max-content) 1fr;gap:0 1.5em;margin:0 0 .4em}'
			. '.undt-dl dt{margin:0;font-weight:bolder}'
			. '.undt-dl dd{margin:0}'
			. '.undt-list{margin:0 0 1em 1.25em;padding:0}'
			. '.undt-inline-list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:.4em 1.25em}'
			. '.undt-footer p{margin:0 0 .5em}'
			. '@media(max-width:480px){.undt-dl__row{grid-template-columns:1fr;gap:0}}';

		/**
		 * Erlaubt das Anpassen des strukturellen CSS.
		 *
		 * @param string $css Das mitgelieferte CSS.
		 */
		return (string) apply_filters( 'undt_css', $css );
	}
}
