<?php
/**
 * Berufe, Kammern und berufsrechtliche Regelungen.
 *
 * Grundlage der Vorschlaege auf der Seite Firma (§ 5 Abs. 1 Nr. 5 DDG) und
 * der Art der Firma auf der Seite Suchmaschinen. Die Daten stehen in
 * includes/data/berufe.php, erzeugt aus dev/data/berufe.json; geladen werden
 * sie nur, wenn eine dieser Seiten sie braucht, nie im Frontend.
 *
 * Vorschlaege werden kopiert, nicht verknuepft: „Uebernehmen“ schreibt Name,
 * Website und Regelungen in die Angaben. Eine spaetere Fassung der Daten
 * aendert ein gespeichertes Impressum also nie von selbst. Die Kennung der
 * uebernommenen Kammer steht in chamber_ref; weicht die Kammer spaeter ab,
 * zeigt die Seite einen Hinweis.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Professions
 */
final class UNDT_Professions {

	/**
	 * Wert der Auswahl fuer eine eigene Berufsbezeichnung.
	 */
	const OTHER = '__other';

	/**
	 * Die Art der Firma je Beruf, fuer den Vorschlag auf Suchmaschinen.
	 * Psychotherapie, Hebammen und Gesundheitshandwerk fehlen: schema.org hat
	 * dafuer keinen passenden Typ.
	 */
	const SCHEMA_TYPES = array(
		'arzt'                 => 'Physician',
		'zahnarzt'             => 'Dentist',
		'tierarzt'             => 'VeterinaryCare',
		'apotheker'            => 'Pharmacy',
		'rechtsanwalt'         => 'Attorney',
		'notar'                => 'Attorney',
		'patentanwalt'         => 'Attorney',
		'steuerberater'        => 'AccountingService',
		'wirtschaftspruefer'   => 'AccountingService',
		'architekt'            => 'ProfessionalService',
		'beratender_ingenieur' => 'ProfessionalService',
	);

	/**
	 * Laufzeit-Cache der Daten.
	 *
	 * @var array|null
	 */
	private static $data = null;

	/**
	 * Die Daten aus includes/data/berufe.php.
	 *
	 * @return array
	 */
	public static function data() {
		if ( null === self::$data ) {
			$data       = require __DIR__ . '/data/berufe.php';
			self::$data = is_array( $data ) ? $data : array();
		}

		return self::$data;
	}

	/**
	 * Ueberschriften der Gruppen in der Auswahl.
	 *
	 * @return array
	 */
	private static function group_labels() {
		return array(
			'heilberufe'            => __( 'Heilberufe', 'unternehmensdaten' ),
			'gesundheitsfachberufe' => __( 'Gesundheitsfachberufe', 'unternehmensdaten' ),
			'rechtsberatung'        => __( 'Recht', 'unternehmensdaten' ),
			'steuer_wirtschaft'     => __( 'Steuern und Wirtschaftsprüfung', 'unternehmensdaten' ),
			'planung'               => __( 'Planen und Bauen', 'unternehmensdaten' ),
			'handwerk'              => __( 'Gesundheitshandwerk', 'unternehmensdaten' ),
		);
	}

	/**
	 * Die gesetzlichen Berufsbezeichnungen fuer die Auswahl, nach Gruppen.
	 *
	 * @return array Gruppe (Ueberschrift) => Liste der Bezeichnungen.
	 */
	public static function title_groups() {
		$data   = self::data();
		$labels = self::group_labels();
		$out    = array();

		foreach ( $data['berufe'] as $beruf ) {
			$group = isset( $labels[ $beruf['gruppe'] ] ) ? $labels[ $beruf['gruppe'] ] : $beruf['gruppe'];

			foreach ( $beruf['titel'] as $pair ) {
				foreach ( array_unique( $pair ) as $title ) {
					$out[ $group ][] = $title;
				}
			}
		}

		return $out;
	}

	/**
	 * Der Beruf zu einer Berufsbezeichnung.
	 *
	 * @param string $title Bezeichnung, wie gespeichert.
	 * @return string Beruf, leer wenn unbekannt.
	 */
	public static function profession_for( $title ) {
		$title = trim( (string) $title );

		if ( '' === $title ) {
			return '';
		}

		foreach ( self::data()['berufe'] as $id => $beruf ) {
			foreach ( $beruf['titel'] as $pair ) {
				if ( in_array( $title, $pair, true ) ) {
					return (string) $id;
				}
			}
		}

		return '';
	}

	/**
	 * Ein Beruf.
	 *
	 * @param string $id Beruf.
	 * @return array|null
	 */
	public static function profession( $id ) {
		$data = self::data();

		return isset( $data['berufe'][ $id ] ) ? $data['berufe'][ $id ] : null;
	}

	/**
	 * Eine Kammer.
	 *
	 * @param string $id Kammer.
	 * @return array|null
	 */
	public static function chamber( $id ) {
		$data = self::data();

		return isset( $data['kammern'][ $id ] ) ? $data['kammern'][ $id ] : null;
	}

	/**
	 * Die Kammern, die fuer einen Beruf in einem Land in Frage kommen.
	 *
	 * Bundesweite Kammern nur, wenn der Beruf keine Kammern in den Laendern
	 * hat (Wirtschaftspruefer, Patentanwaelte). Die Rechtsanwaltskammer beim
	 * BGH ist zwar bundesweit, betrifft aber nur die wenigen BGH-Anwaelte und
	 * stuende sonst bei jedem Rechtsanwalt als zweite Wahl da.
	 *
	 * @param string $profession Beruf.
	 * @param string $state      Kuerzel des Landes, etwa BY.
	 * @return array Kammer-ID => Kammer.
	 */
	public static function chambers( $profession, $state ) {
		$beruf = self::profession( $profession );

		if ( null === $beruf || '' === $beruf['kammer_gruppe'] ) {
			return array();
		}

		$land  = array();
		$bund  = array();
		$local = false;

		foreach ( self::data()['kammern'] as $id => $kammer ) {
			if ( ! in_array( $beruf['kammer_gruppe'], $kammer['gruppen'], true ) ) {
				continue;
			}

			if ( $kammer['bundesweit'] ) {
				$bund[ $id ] = $kammer;
				continue;
			}

			$local = true;

			if ( '' !== $state && in_array( $state, $kammer['laender'], true ) ) {
				$land[ $id ] = $kammer;
			}
		}

		return $local ? $land : $bund;
	}

	/**
	 * Ein Regelwerk als Zeile, etwa „Bundesärzteordnung (BÄO)“.
	 *
	 * @param array $regel Regelwerk.
	 * @return string
	 */
	private static function rule_line( array $regel ) {
		return '' !== $regel['kurz'] && false === strpos( $regel['name'], $regel['kurz'] ) ? $regel['name'] . ' (' . $regel['kurz'] . ')' : $regel['name'];
	}

	/**
	 * Die berufsrechtlichen Regelungen fuer Beruf und Kammer.
	 *
	 * Erst das Bundesrecht des Berufs, dann, was die Kammer mitbringt:
	 * Landesgesetz und Berufsordnung.
	 *
	 * @param string $profession Beruf.
	 * @param string $chamber    Kammer, leer ohne.
	 * @return array Liste aus text und ok (an der Quelle geprueft).
	 */
	public static function rules( $profession, $chamber = '' ) {
		$beruf  = self::profession( $profession );
		$data   = self::data();
		$kammer = '' !== $chamber ? self::chamber( $chamber ) : null;
		$out    = array();

		if ( null === $beruf ) {
			return array();
		}

		foreach ( $beruf['regeln_bund'] as $ref ) {
			if ( isset( $data['regelwerke'][ $ref ] ) ) {
				$out[] = array(
					'text' => self::rule_line( $data['regelwerke'][ $ref ] ),
					'ok'   => $data['regelwerke'][ $ref ]['ok'],
				);
			}
		}

		if ( null !== $kammer ) {
			foreach ( $kammer['regeln'] as $regel ) {
				if ( isset( $regel['ref'] ) && isset( $data['regelwerke'][ $regel['ref'] ] ) ) {
					$out[] = array(
						'text' => self::rule_line( $data['regelwerke'][ $regel['ref'] ] ),
						'ok'   => $data['regelwerke'][ $regel['ref'] ]['ok'],
					);
				} elseif ( isset( $regel['name'] ) ) {
					$out[] = array(
						'text' => $regel['name'],
						'ok'   => $regel['ok'],
					);
				}
			}
		}

		// Doppelte Zeilen, etwa ein Gesetz fuer Architekten und Ingenieure, nur einmal.
		$seen = array();

		return array_values(
			array_filter(
				$out,
				static function ( $line ) use ( &$seen ) {
					if ( isset( $seen[ $line['text'] ] ) ) {
						return false;
					}

					$seen[ $line['text'] ] = true;

					return true;
				}
			)
		);
	}

	/**
	 * Wo die Kammern eines Berufs verzeichnet sind, fuer „Zuständige Kammer prüfen“.
	 *
	 * @param string $profession Beruf.
	 * @return string
	 */
	public static function directory( $profession ) {
		$beruf = self::profession( $profession );
		$data  = self::data();

		if ( null === $beruf ) {
			return '';
		}

		if ( 'handwerkskammern' === $beruf['kammer_gruppe'] ) {
			return (string) $data['handwerk']['verzeichnis'];
		}

		return isset( $data['verzeichnisse'][ $beruf['kammer_gruppe'] ] ) ? (string) $data['verzeichnisse'][ $beruf['kammer_gruppe'] ] : '';
	}

	/**
	 * Die Angaben, die „Übernehmen“ schreibt.
	 *
	 * @param string $profession Beruf.
	 * @param string $chamber    Kammer, leer bei Berufen ohne Kammerdaten.
	 * @return array Feld => Wert.
	 */
	public static function values( $profession, $chamber ) {
		$beruf  = self::profession( $profession );
		$kammer = '' !== $chamber ? self::chamber( $chamber ) : null;

		if ( null === $beruf ) {
			return array();
		}

		$lines = array();

		foreach ( self::rules( $profession, $chamber ) as $rule ) {
			$lines[] = $rule['text'];
		}

		$values = array(
			'prof_rules'     => implode( "\n", $lines ),
			'prof_rules_url' => null !== $kammer && '' !== $kammer['regeln_url'] ? $kammer['regeln_url'] : $beruf['regeln_url'],
			'chamber_ref'    => null !== $kammer ? (string) $chamber : '',
		);

		if ( null !== $kammer ) {
			$values['chamber_name'] = $kammer['name'];
			$values['chamber_url']  = $kammer['url'];
		}

		return $values;
	}

	/**
	 * Was die Seite Firma zu Kammer und Berufsrecht vorschlaegt.
	 *
	 * @param array $company Gespeicherte Angaben.
	 * @return array profession, state, chambers, rules, directory, changed (Kammer weicht ab), applied (Kammer uebernommen).
	 */
	public static function suggestion( array $company ) {
		$profession = self::profession_for( isset( $company['job_title'] ) ? $company['job_title'] : '' );
		$state      = isset( $company['state'] ) ? (string) $company['state'] : '';
		$ref        = isset( $company['chamber_ref'] ) ? (string) $company['chamber_ref'] : '';
		$out        = array(
			'profession' => $profession,
			'state'      => $state,
			'chambers'   => array(),
			'directory'  => '',
			'applied'    => '',
			'changed'    => array(),
		);

		if ( '' === $profession ) {
			return $out;
		}

		$out['chambers']  = self::chambers( $profession, $state );
		$out['directory'] = self::directory( $profession );

		// Die uebernommene Kammer: steht sie noch so in den Daten, und passt sie
		// noch zu Beruf und Land? Nach einem Wechsel gilt wieder der Vorschlag.
		$kammer = '' !== $ref ? self::chamber( $ref ) : null;

		if ( null !== $kammer && ! isset( $out['chambers'][ $ref ] ) ) {
			$kammer = null;
		}

		if ( null !== $kammer ) {
			$out['applied'] = $ref;

			foreach ( array( 'chamber_name' => 'name', 'chamber_url' => 'url' ) as $field => $key ) {
				$stored = isset( $company[ $field ] ) ? trim( (string) $company[ $field ] ) : '';

				if ( '' !== $stored && $stored !== $kammer[ $key ] ) {
					$out['changed'][ $field ] = $kammer[ $key ];
				}
			}
		}

		return $out;
	}

	/**
	 * Die Art der Firma, die zum Beruf passt.
	 *
	 * @param string $title Berufsbezeichnung.
	 * @return string Schema-Typ, leer ohne Vorschlag.
	 */
	public static function schema_type( $title ) {
		$profession = self::profession_for( $title );

		return isset( self::SCHEMA_TYPES[ $profession ] ) ? self::SCHEMA_TYPES[ $profession ] : '';
	}

	/**
	 * Erlaubnispflichtige Taetigkeiten fuer den Abschnitt Aufsichtsbehoerde.
	 *
	 * @return array ID => label, note (fuer „Erlaubnis nach“), authority (Hinweis auf die Stelle), register.
	 */
	public static function permits() {
		$notes = array(
			'heilpraktiker'           => __( 'Erlaubnis nach § 1 Heilpraktikergesetz', 'unternehmensdaten' ),
			'versicherungsvermittler' => __( 'Erlaubnis nach § 34d GewO', 'unternehmensdaten' ),
			'finanzanlagenvermittler' => __( 'Erlaubnis nach § 34f GewO', 'unternehmensdaten' ),
			'immobilienmakler'        => __( 'Erlaubnis nach § 34c GewO', 'unternehmensdaten' ),
		);
		$out   = array();

		foreach ( self::data()['erlaubnis'] as $id => $permit ) {
			$out[ $id ] = array(
				'label'     => $permit['titel'][0] . ' / ' . $permit['titel'][1],
				'note'      => isset( $notes[ $id ] ) ? $notes[ $id ] : '',
				'authority' => $permit['aufsicht'],
				'register'  => $permit['register'],
			);
		}

		return $out;
	}

	/**
	 * Leert den Laufzeit-Cache, etwa in den Pruefungen.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$data = null;
	}
}
