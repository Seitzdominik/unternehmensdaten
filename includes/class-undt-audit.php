<?php
/**
 * Vollstaendigkeitspruefung.
 *
 * Prueft die gespeicherten Angaben gegen die Pflichten, die sich aus dem
 * gewaehlten Profil ergeben. Keine Rechtsberatung, sondern eine Checkliste, die
 * genau die Luecken zeigt, die das Register kennt.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Audit
 */
final class UNDT_Audit {

	/**
	 * Fuehrt alle Pruefungen durch.
	 *
	 * @return array Liste aus array( level, title, detail, basis ).
	 */
	public static function run() {
		$issues = array_merge(
			self::missing_required(),
			self::structural_rules(),
			self::content_scan()
		);

		/**
		 * Erlaubt eigene Pruefungen.
		 *
		 * @param array $issues Gefundene Punkte.
		 */
		return apply_filters( 'undt_audit_issues', $issues );
	}

	/**
	 * Zaehlt nur die fehlenden Pflichtfelder.
	 *
	 * Bewusst ohne den Inhalts-Scan: diese Zahl wird fuer die Zahlenblase im
	 * Menue gebraucht und damit bei jedem Backend-Aufruf berechnet. Sie darf
	 * deshalb keine einzige Datenbankabfrage ausloesen und liest ausschliesslich
	 * aus den ohnehin geladenen Optionen.
	 *
	 * @return int
	 */
	public static function quick_count() {
		return count( self::missing_required() );
	}

	/**
	 * Zaehlt die Punkte je Schweregrad.
	 *
	 * @param array $issues Ergebnis von run().
	 * @return array
	 */
	public static function summary( array $issues ) {
		$counts = array(
			'error'   => 0,
			'warning' => 0,
			'notice'  => 0,
		);

		foreach ( $issues as $issue ) {
			if ( isset( $counts[ $issue['level'] ] ) ) {
				++$counts[ $issue['level'] ];
			}
		}

		return $counts;
	}

	/**
	 * Fehlende Pflichtfelder, die beim aktuellen Profil gelten.
	 *
	 * @return array
	 */
	private static function missing_required() {
		$issues  = array();
		$profile = UNDT_Store::profile();

		foreach ( UNDT_Schema::fields() as $key => $field ) {
			if ( empty( $field['required'] ) ) {
				continue;
			}

			if ( ! UNDT_Schema::applies( $field['when'], $profile ) ) {
				continue;
			}

			if ( UNDT_Store::has( $key ) ) {
				continue;
			}

			$issues[] = array(
				'level'  => 'error',
				'title'  => sprintf(
					/* translators: %s: Feldbezeichnung. */
					__( '%s fehlt', 'unternehmensdaten' ),
					$field['label']
				),
				'detail' => $field['help'],
				'basis'  => $field['basis'],
				'tab'    => $field['tab'],
			);
		}

		return $issues;
	}

	/**
	 * Regeln, die sich nicht als einzelnes Pflichtfeld abbilden lassen.
	 *
	 * @return array
	 */
	private static function structural_rules() {
		$issues  = array();
		$profile = UNDT_Store::profile();

		// Zweiter Kommunikationsweg neben der E-Mail-Adresse.
		if ( UNDT_Store::has( 'email' ) && ! UNDT_Store::has( 'phone' ) && ! UNDT_Store::has( 'contact_form_url' ) ) {
			$issues[] = array(
				'level'  => 'error',
				'title'  => __( 'Zweiter Kommunikationsweg fehlt', 'unternehmensdaten' ),
				'detail' => __( 'Neben der E-Mail-Adresse ist ein weiterer, unmittelbarer Kommunikationsweg erforderlich. Üblicherweise eine Telefonnummer, ersatzweise ein Kontaktformular mit zugesagter Antwort binnen 60 Minuten.', 'unternehmensdaten' ),
				'basis'  => '§ 5 Abs. 1 Nr. 2 DDG, EuGH C-649/17',
				'tab'    => 'contact',
			);
		}

		// Der Rechtsformzusatz der UG muss ausgeschrieben sein.
		if ( 'ug' === $profile['legal_form'] && UNDT_Store::has( 'company_name' ) ) {
			/*
			 * Gesucht wird nur der Wortanfang vor dem Umlaut. WordPress fuellt
			 * mb_substr und mb_strlen bei fehlendem mbstring auf, mb_strtolower
			 * und mb_strpos jedoch nicht. Mit einem rein aus ASCII bestehenden
			 * Suchbegriff genuegt stripos und die Pruefung laeuft auf jedem Server.
			 */
			if ( false === stripos( UNDT_Store::get( 'company_name' ), 'haftungsbeschr' ) ) {
				$issues[] = array(
					'level'  => 'warning',
					'title'  => __( 'Rechtsformzusatz der UG unvollständig', 'unternehmensdaten' ),
					'detail' => __( 'Der Zusatz muss als "UG (haftungsbeschränkt)" ausgeschrieben werden. Die Abkürzung allein genügt nicht und kann zur persönlichen Haftung führen.', 'unternehmensdaten' ),
					'basis'  => '§ 5a Abs. 1 GmbHG',
					'tab'    => 'company',
				);
			}
		}

		// Kapitalangaben sind nur paarweise zulaessig.
		if ( UNDT_Store::has( 'share_capital' ) && ! UNDT_Store::has( 'outstanding_contributions' ) ) {
			$issues[] = array(
				'level'  => 'notice',
				'title'  => __( 'Ausstehende Einlagen prüfen', 'unternehmensdaten' ),
				'detail' => __( 'Sobald das Stammkapital genannt wird, muss auch der Gesamtbetrag der ausstehenden Einlagen angegeben werden, falls nicht alle in Geld zu leistenden Einlagen eingezahlt sind. Sind alle Einlagen voll eingezahlt, kann dieser Punkt ignoriert werden.', 'unternehmensdaten' ),
				'basis'  => '§ 5 Abs. 1 Nr. 1 DDG',
				'tab'    => 'register',
			);
		}

		// Barrierefreiheitserklaerung seit dem 28.06.2025.
		if ( empty( $profile['bfsg_exempt'] ) && ! empty( $profile['sells_to_consumers'] ) && ! UNDT_Store::has( 'page_accessibility' ) ) {
			$issues[] = array(
				'level'  => 'warning',
				'title'  => __( 'Erklärung zur Barrierefreiheit fehlt', 'unternehmensdaten' ),
				'detail' => __( 'Das BFSG gilt seit dem 28.06.2025. Für Dienstleistungen an Verbraucher ist eine Erklärung zur Barrierefreiheit erforderlich, sofern die Kleinstunternehmen-Ausnahme nicht greift. Sie ist eine eigene, dauerhaft erreichbare Seite neben Impressum und Datenschutz.', 'unternehmensdaten' ),
				'basis'  => 'Anlage 3 zu §§ 14, 28 BFSG',
				'tab'    => 'consumer',
			);
		}

		// Datenschutz-Aufsichtsbehoerde fuer das Beschwerderecht.
		if ( ! UNDT_Store::has( 'privacy_authority' ) ) {
			$issues[] = array(
				'level'  => 'notice',
				'title'  => __( 'Datenschutz-Aufsichtsbehörde nicht hinterlegt', 'unternehmensdaten' ),
				'detail' => __( 'Für den Hinweis auf das Beschwerderecht in der Datenschutzerklärung wird die zuständige Landesbehörde benötigt. Die europäischen Aufsichtsbehörden prüfen die Informationspflichten nach Art. 12 bis 14 DSGVO 2026 koordiniert.', 'unternehmensdaten' ),
				'basis'  => 'Art. 13 Abs. 2 lit. d, Art. 77 DSGVO',
				'tab'    => 'consumer',
			);
		}

		// Verknuepfte Rechtsseiten.
		foreach ( array( 'page_imprint' => __( 'Impressum', 'unternehmensdaten' ), 'page_privacy' => __( 'Datenschutzerklärung', 'unternehmensdaten' ) ) as $key => $label ) {
			$id = (int) UNDT_Store::get( $key );

			if ( $id > 0 && 'publish' !== get_post_status( $id ) ) {
				$issues[] = array(
					'level'  => 'warning',
					'title'  => sprintf(
						/* translators: %s: Seitenname. */
						__( 'Seite "%s" ist nicht veröffentlicht', 'unternehmensdaten' ),
						$label
					),
					'detail' => __( 'Die Angaben müssen ständig verfügbar sein. Eine Seite im Entwurfsstatus wird im Footer nicht verlinkt.', 'unternehmensdaten' ),
					'basis'  => '§ 5 Abs. 1 DDG',
					'tab'    => 'consumer',
				);
			}
		}

		return $issues;
	}

	/**
	 * Durchsucht die verknuepften Rechtsseiten nach veralteten Inhalten.
	 *
	 * Der Verweis auf die OS-Plattform der EU steht heute noch in sehr vielen
	 * Impressen. Die ODR-Verordnung wurde durch die Verordnung (EU) 2024/3228
	 * aufgehoben, die Plattform am 20.07.2025 abgeschaltet. Der Hinweis geht
	 * damit ins Leere und sollte entfernt werden.
	 *
	 * Diese Abfrage laeuft ausschliesslich beim Aufruf der Pruefseite, nicht bei
	 * jedem Seitenaufruf im Frontend.
	 *
	 * @return array
	 */
	private static function content_scan() {
		$issues   = array();
		$patterns = array(
			'ec.europa.eu/consumers/odr' => array(
				'title'  => __( 'Veralteter Link zur OS-Plattform gefunden', 'unternehmensdaten' ),
				'detail' => __( 'Die EU-Plattform zur Online-Streitbeilegung wurde am 20.07.2025 abgeschaltet und die zugrunde liegende ODR-Verordnung aufgehoben. Der Link führt ins Leere und sollte aus Impressum, AGB, Footer und E-Mail-Signaturen entfernt werden.', 'unternehmensdaten' ),
				'basis'  => 'Verordnung (EU) 2024/3228',
			),
			'§ 5 TMG'                    => array(
				'title'  => __( 'Veraltete Rechtsgrundlage "§ 5 TMG"', 'unternehmensdaten' ),
				'detail' => __( 'Das Telemediengesetz wurde am 14.05.2024 durch das Digitale-Dienste-Gesetz abgelöst. Die Überschrift sollte "Angaben gemäß § 5 DDG" lauten.', 'unternehmensdaten' ),
				'basis'  => '§ 5 DDG',
			),
			'§ 55 RStV'                  => array(
				'title'  => __( 'Veraltete Rechtsgrundlage "§ 55 RStV"', 'unternehmensdaten' ),
				'detail' => __( 'Der Rundfunkstaatsvertrag wurde 2020 durch den Medienstaatsvertrag abgelöst. Die korrekte Angabe lautet "§ 18 Abs. 2 MStV".', 'unternehmensdaten' ),
				'basis'  => '§ 18 Abs. 2 MStV',
			),
			'TTDSG'                      => array(
				'title'  => __( 'Veraltete Bezeichnung "TTDSG"', 'unternehmensdaten' ),
				'detail' => __( 'Das Gesetz heißt seit dem 14.05.2024 TDDDG. Die Cookie-Vorschrift ist weiterhin § 25.', 'unternehmensdaten' ),
				'basis'  => '§ 25 TDDDG',
			),
		);

		$ids = array();

		foreach ( array( 'page_imprint', 'page_privacy', 'page_terms', 'page_accessibility' ) as $key ) {
			$id = (int) UNDT_Store::get( $key );

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		if ( empty( $ids ) ) {
			return $issues;
		}

		$found = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			foreach ( $patterns as $needle => $issue ) {
				if ( isset( $found[ $needle ] ) ) {
					continue;
				}

				if ( false !== stripos( $post->post_content, $needle ) ) {
					$found[ $needle ] = true;

					$issues[] = array(
						'level'  => 'warning',
						'title'  => $issue['title'],
						'detail' => sprintf(
							/* translators: 1: Beschreibung, 2: Seitenname. */
							__( '%1$s Gefunden auf der Seite "%2$s".', 'unternehmensdaten' ),
							$issue['detail'],
							get_the_title( $id )
						),
						'basis'  => $issue['basis'],
						'tab'    => '',
						'edit'   => (string) get_edit_post_link( $id, 'raw' ),
					);
				}
			}
		}

		return $issues;
	}
}
