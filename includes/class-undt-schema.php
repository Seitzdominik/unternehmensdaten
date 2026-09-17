<?php
/**
 * Das Feld-Register: die einzige Quelle der Wahrheit.
 *
 * Aus diesem Register werden erzeugt: das Admin-Formular, der Sanitizer, der
 * Shortcode-Resolver, der Impressum-Renderer, die Shortcode-Referenz und die
 * Vollständigkeitsprüfung. Ein neues Feld = ein Array-Eintrag.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Schema
 */
final class UNDT_Schema {

	/**
	 * Laufzeit-Cache für das aufgebaute Feld-Register.
	 *
	 * @var array|null
	 */
	private static $fields = null;

	/**
	 * Laufzeit-Cache der übrigen Listen.
	 *
	 * applies() fragt die Rechtsform bei jedem Feldzugriff ab. Ohne Cache entstünde
	 * die Liste samt aller Übersetzungsaufrufe dabei jedes Mal neu, für ein
	 * einziges Impressum mehrere hundert Mal.
	 *
	 * @var array
	 */
	private static $lists = array();

	/**
	 * Rechtsformen mit ihren strukturellen Eigenschaften.
	 *
	 * register  - Registerart, steuert die Register-Felder.
	 * rep       - Vertretungsberechtigte erforderlich.
	 * capital   - Kapitalangaben möglich, dann greift die Paarungsregel des DDG.
	 * second    - Zweiter Registerblock, etwa der Komplementär bei GmbH & Co. KG.
	 * board     - Aufsichtsrat vorhanden.
	 * rep_label - Bezeichnung der Vertretungsberechtigten.
	 *
	 * @return array
	 */
	public static function legal_forms() {
		if ( ! isset( self::$lists['legal_forms'] ) ) {
			self::$lists['legal_forms'] = self::build_legal_forms();
		}

		return self::$lists['legal_forms'];
	}

	/**
	 * Baut die Liste der Rechtsformen auf.
	 *
	 * @return array
	 */
	private static function build_legal_forms() {
		return array(
			'sole'       => array(
				'label'    => __( 'Einzelunternehmen (nicht im Register)', 'unternehmensdaten' ),
				'register' => '',
				'rep'      => false,
				'capital'  => false,
			),
			'freelancer' => array(
				'label'    => __( 'Freiberufler / Freiberuflerin', 'unternehmensdaten' ),
				'register' => '',
				'rep'      => false,
				'capital'  => false,
			),
			'ek'         => array(
				'label'    => __( 'Eingetragener Kaufmann (e.K. / e.Kfm. / e.Kfr.)', 'unternehmensdaten' ),
				'register' => 'hra',
				'rep'      => false,
				'capital'  => false,
			),
			'gbr'        => array(
				'label'     => __( 'GbR (nicht eingetragen)', 'unternehmensdaten' ),
				'register'  => '',
				'rep'       => true,
				'capital'   => false,
				'rep_label' => __( 'Gesellschafter', 'unternehmensdaten' ),
			),
			'egbr'       => array(
				'label'     => __( 'eGbR (eingetragene GbR, Gesellschaftsregister)', 'unternehmensdaten' ),
				'register'  => 'gsr',
				'rep'       => true,
				'capital'   => false,
				'rep_label' => __( 'Vertretungsberechtigte Gesellschafter', 'unternehmensdaten' ),
			),
			'partg'      => array(
				'label'     => __( 'Partnerschaftsgesellschaft (PartG)', 'unternehmensdaten' ),
				'register'  => 'pr',
				'rep'       => true,
				'capital'   => false,
				'rep_label' => __( 'Partner', 'unternehmensdaten' ),
			),
			'partgmbb'   => array(
				'label'     => __( 'PartG mbB (mit beschränkter Berufshaftung)', 'unternehmensdaten' ),
				'register'  => 'pr',
				'rep'       => true,
				'capital'   => false,
				'rep_label' => __( 'Partner', 'unternehmensdaten' ),
			),
			'ohg'        => array(
				'label'     => __( 'OHG', 'unternehmensdaten' ),
				'register'  => 'hra',
				'rep'       => true,
				'capital'   => false,
				'rep_label' => __( 'Vertretungsberechtigte Gesellschafter', 'unternehmensdaten' ),
			),
			'kg'         => array(
				'label'     => __( 'KG', 'unternehmensdaten' ),
				'register'  => 'hra',
				'rep'       => true,
				'capital'   => false,
				'rep_label' => __( 'Komplementäre', 'unternehmensdaten' ),
			),
			'gmbhcokg'   => array(
				'label'     => __( 'GmbH & Co. KG', 'unternehmensdaten' ),
				'register'  => 'hra',
				'rep'       => true,
				'capital'   => false,
				'second'    => 'hrb',
				'rep_label' => __( 'Geschäftsführer der Komplementär-GmbH', 'unternehmensdaten' ),
			),
			'gmbh'       => array(
				'label'     => __( 'GmbH', 'unternehmensdaten' ),
				'register'  => 'hrb',
				'rep'       => true,
				'capital'   => true,
				'rep_label' => __( 'Geschäftsführer', 'unternehmensdaten' ),
			),
			'ggmbh'      => array(
				'label'     => __( 'gGmbH (gemeinnützig)', 'unternehmensdaten' ),
				'register'  => 'hrb',
				'rep'       => true,
				'capital'   => true,
				'rep_label' => __( 'Geschäftsführer', 'unternehmensdaten' ),
			),
			'ug'         => array(
				'label'     => __( 'UG (haftungsbeschränkt)', 'unternehmensdaten' ),
				'register'  => 'hrb',
				'rep'       => true,
				'capital'   => true,
				'rep_label' => __( 'Geschäftsführer', 'unternehmensdaten' ),
			),
			'ag'         => array(
				'label'     => __( 'AG', 'unternehmensdaten' ),
				'register'  => 'hrb',
				'rep'       => true,
				'capital'   => true,
				'board'     => true,
				'rep_label' => __( 'Vorstand', 'unternehmensdaten' ),
			),
			'se'         => array(
				'label'     => __( 'SE (Europäische Gesellschaft)', 'unternehmensdaten' ),
				'register'  => 'hrb',
				'rep'       => true,
				'capital'   => true,
				'board'     => true,
				'rep_label' => __( 'Vorstand', 'unternehmensdaten' ),
			),
			'eg'         => array(
				'label'     => __( 'eG (Genossenschaft)', 'unternehmensdaten' ),
				'register'  => 'gnr',
				'rep'       => true,
				'capital'   => false,
				'board'     => true,
				'rep_label' => __( 'Vorstand', 'unternehmensdaten' ),
			),
			'ev'         => array(
				'label'     => __( 'e. V. (eingetragener Verein)', 'unternehmensdaten' ),
				'register'  => 'vr',
				'rep'       => true,
				'capital'   => false,
				'rep_label' => __( 'Vertretungsberechtigter Vorstand', 'unternehmensdaten' ),
			),
			'stiftung'   => array(
				'label'     => __( 'Stiftung', 'unternehmensdaten' ),
				'register'  => 'stift',
				'rep'       => true,
				'capital'   => false,
				'rep_label' => __( 'Vorstand', 'unternehmensdaten' ),
			),
			'mvz'        => array(
				'label'     => __( 'MVZ (Medizinisches Versorgungszentrum)', 'unternehmensdaten' ),
				'register'  => 'hrb',
				'rep'       => true,
				'capital'   => true,
				'rep_label' => __( 'Geschäftsführer', 'unternehmensdaten' ),
			),
			'koer'       => array(
				'label'     => __( 'Körperschaft des öffentlichen Rechts', 'unternehmensdaten' ),
				'register'  => '',
				'rep'       => true,
				'capital'   => false,
				'rep_label' => __( 'Vertretungsberechtigte', 'unternehmensdaten' ),
			),
		);
	}

	/**
	 * Registerarten mit ihrer amtlichen Bezeichnung.
	 *
	 * @return array
	 */
	public static function register_types() {
		if ( ! isset( self::$lists['register_types'] ) ) {
			self::$lists['register_types'] = self::build_register_types();
		}

		return self::$lists['register_types'];
	}

	/**
	 * Baut die Liste der Registerarten auf.
	 *
	 * @return array
	 */
	private static function build_register_types() {
		return array(
			'hra'   => __( 'Handelsregister A', 'unternehmensdaten' ),
			'hrb'   => __( 'Handelsregister B', 'unternehmensdaten' ),
			'gsr'   => __( 'Gesellschaftsregister', 'unternehmensdaten' ),
			'pr'    => __( 'Partnerschaftsregister', 'unternehmensdaten' ),
			'gnr'   => __( 'Genossenschaftsregister', 'unternehmensdaten' ),
			'vr'    => __( 'Vereinsregister', 'unternehmensdaten' ),
			'stift' => __( 'Stiftungsverzeichnis', 'unternehmensdaten' ),
		);
	}

	/**
	 * Registerarten der Rechtsform.
	 *
	 * @param string $key Rechtsform-Schlüssel.
	 * @return string
	 */
	public static function register_label( $key ) {
		$types = self::register_types();

		return isset( $types[ $key ] ) ? $types[ $key ] : '';
	}

	/**
	 * Tabs der Stammdaten-Seite.
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			'company'    => __( 'Unternehmen', 'unternehmensdaten' ),
			'contact'    => __( 'Kontakt', 'unternehmensdaten' ),
			'register'   => __( 'Register & Vertretung', 'unternehmensdaten' ),
			'profession' => __( 'Beruf & Aufsicht', 'unternehmensdaten' ),
			'consumer'   => __( 'Recht & Verbraucher', 'unternehmensdaten' ),
		);
	}

	/**
	 * Überschriften der Abschnitte innerhalb der Tabs.
	 *
	 * @return array
	 */
	public static function sections() {
		return array(
			'identity'       => __( 'Identität', 'unternehmensdaten' ),
			'address'        => __( 'Ladungsfähige Anschrift', 'unternehmensdaten' ),
			'contact'        => __( 'Kontaktwege', 'unternehmensdaten' ),
			'register'       => __( 'Registereintrag', 'unternehmensdaten' ),
			'register2'      => __( 'Zweiter Registereintrag', 'unternehmensdaten' ),
			'representation' => __( 'Vertretung', 'unternehmensdaten' ),
			'capital'        => __( 'Kapital und Abwicklung', 'unternehmensdaten' ),
			'tax'            => __( 'Steuerliche Angaben', 'unternehmensdaten' ),
			'profession'     => __( 'Reglementierter Beruf', 'unternehmensdaten' ),
			'authority'      => __( 'Aufsichtsbehörde', 'unternehmensdaten' ),
			'insurance'      => __( 'Berufshaftpflichtversicherung', 'unternehmensdaten' ),
			'editorial'      => __( 'Redaktionelle Verantwortung', 'unternehmensdaten' ),
			'vsbg'           => __( 'Verbraucherstreitbeilegung', 'unternehmensdaten' ),
			'privacy'        => __( 'Datenschutz', 'unternehmensdaten' ),
			'pages'          => __( 'Verknüpfte Seiten', 'unternehmensdaten' ),
			'other'          => __( 'Weitere Angaben', 'unternehmensdaten' ),
		);
	}

	/**
	 * Voreinstellungen des Profils, also der Antworten des Assistenten.
	 *
	 * @return array
	 */
	public static function profile_defaults() {
		return array(
			'legal_form'         => 'sole',
			'is_regulated'       => 0,
			'is_medical'         => 0,
			'needs_authority'    => 0,
			'sells_to_consumers' => 0,
			'has_editorial'      => 0,
			'vat_status'         => 'none',
			'has_liability_ins'  => 0,
			'is_liquidating'     => 0,
			'vsbg_participation' => 'no',
			'vsbg_exempt'        => 0,
			'bfsg_exempt'        => 0,
		);
	}

	/**
	 * Fragen des Einrichtungsassistenten.
	 *
	 * Jede Antwort schaltet Feldblöcke frei oder aus.
	 *
	 * @return array
	 */
	public static function profile_questions() {
		$forms = array();
		foreach ( self::legal_forms() as $key => $form ) {
			$forms[ $key ] = $form['label'];
		}

		return array(
			'legal_form'         => array(
				'label'   => __( 'Welche Rechtsform hat das Unternehmen?', 'unternehmensdaten' ),
				'type'    => 'select',
				'choices' => $forms,
				'help'    => __( 'Steuert, welche Register-, Vertretungs- und Kapitalangaben verlangt werden.', 'unternehmensdaten' ),
			),
			'is_regulated'       => array(
				'label' => __( 'Wird ein reglementierter Beruf ausgeübt?', 'unternehmensdaten' ),
				'type'  => 'checkbox',
				'help'  => __( 'Ärzte, Zahnärzte, Apotheker, Tierärzte, Psychotherapeuten, Rechtsanwälte, Steuerberater, Wirtschaftsprüfer, Architekten, Notare, beratende Ingenieure. Schaltet Kammer, Berufsbezeichnung und berufsrechtliche Regelungen frei.', 'unternehmensdaten' ),
				'basis' => '§ 5 Abs. 1 Nr. 5 DDG',
			),
			'is_medical'         => array(
				'label' => __( 'Heilberuf (Arzt, Zahnarzt, Psychotherapeut, MVZ)?', 'unternehmensdaten' ),
				'type'  => 'checkbox',
				'help'  => __( 'Ergänzt Facharztbezeichnung, Kassenärztliche Vereinigung und ärztliche Leitung.', 'unternehmensdaten' ),
				'when'  => array( 'is_regulated' => 1 ),
			),
			'needs_authority'    => array(
				'label' => __( 'Ist die Tätigkeit erlaubnispflichtig?', 'unternehmensdaten' ),
				'type'  => 'checkbox',
				'help'  => __( 'Makler (§ 34c GewO), Versicherungsvermittler (§ 34d), Finanzanlagenvermittler (§ 34f), Bewachung (§ 34a), Gastronomie, zulassungspflichtiges Handwerk. Schaltet die Aufsichtsbehörde frei.', 'unternehmensdaten' ),
				'basis' => '§ 5 Abs. 1 Nr. 3 DDG',
			),
			'sells_to_consumers' => array(
				'label' => __( 'Werden Verträge mit Verbrauchern geschlossen?', 'unternehmensdaten' ),
				'type'  => 'checkbox',
				'help'  => __( 'Schaltet den Hinweis zur Verbraucherstreitbeilegung frei.', 'unternehmensdaten' ),
				'basis' => '§ 36 VSBG',
			),
			'vsbg_exempt'        => array(
				'label' => __( 'Zum 31.12. des Vorjahres zehn oder weniger Personen beschäftigt?', 'unternehmensdaten' ),
				'type'  => 'checkbox',
				'help'  => __( 'Befreit von der allgemeinen Informationspflicht nach § 36 VSBG. Die Pflicht aus § 37 VSBG im konkreten Streitfall bleibt davon unberührt.', 'unternehmensdaten' ),
				'when'  => array( 'sells_to_consumers' => 1 ),
			),
			'vsbg_participation' => array(
				'label'   => __( 'Teilnahme an der Verbraucherschlichtung', 'unternehmensdaten' ),
				'type'    => 'select',
				'choices' => array(
					'no'        => __( 'Nicht bereit und nicht verpflichtet', 'unternehmensdaten' ),
					'voluntary' => __( 'Freiwillig bereit', 'unternehmensdaten' ),
					'obliged'   => __( 'Gesetzlich verpflichtet', 'unternehmensdaten' ),
				),
				'when'    => array( 'sells_to_consumers' => 1 ),
			),
			'has_editorial'      => array(
				'label' => __( 'Gibt es journalistisch-redaktionelle Inhalte (Blog, Magazin, News)?', 'unternehmensdaten' ),
				'type'  => 'checkbox',
				'help'  => __( 'Verlangt einen Verantwortlichen für den Inhalt mit Name und Anschrift. Wird sehr häufig vergessen.', 'unternehmensdaten' ),
				'basis' => '§ 18 Abs. 2 MStV',
			),
			'vat_status'         => array(
				'label'   => __( 'Umsatzsteuerlicher Status', 'unternehmensdaten' ),
				'type'    => 'select',
				'choices' => array(
					'none'           => __( 'Keine Angabe / nicht zutreffend', 'unternehmensdaten' ),
					'standard'       => __( 'Regelbesteuert, USt-IdNr. vorhanden', 'unternehmensdaten' ),
					'small_business' => __( 'Kleinunternehmer nach § 19 UStG', 'unternehmensdaten' ),
				),
			),
			'has_liability_ins'  => array(
				'label' => __( 'Besteht eine Berufshaftpflichtversicherung?', 'unternehmensdaten' ),
				'type'  => 'checkbox',
				'help'  => __( 'Dienstleister müssen dann Name, Anschrift des Versicherers und den räumlichen Geltungsbereich angeben.', 'unternehmensdaten' ),
				'basis' => '§ 2 Abs. 1 Nr. 11 DL-InfoV',
			),
			'is_liquidating'     => array(
				'label' => __( 'Befindet sich die Gesellschaft in Abwicklung oder Liquidation?', 'unternehmensdaten' ),
				'type'  => 'checkbox',
				'basis' => '§ 5 Abs. 1 Nr. 7 DDG',
			),
			'bfsg_exempt'        => array(
				'label' => __( 'Kleinstunternehmen im Sinne des BFSG?', 'unternehmensdaten' ),
				'type'  => 'checkbox',
				'help'  => __( 'Weniger als 10 Beschäftigte UND höchstens 2 Mio. Euro Jahresumsatz oder Bilanzsumme. Beides muss gleichzeitig zutreffen, und die Ausnahme gilt nur für Dienstleistungen, nicht für in Verkehr gebrachte Produkte.', 'unternehmensdaten' ),
				'basis' => '§ 3 Abs. 3 BFSG',
			),
		);
	}

	/**
	 * Das vollständige Feld-Register.
	 *
	 * Schlüssel je Feld:
	 *   label     - Beschriftung im Admin.
	 *   type      - Steuert Eingabefeld, Sanitizer und Ausgabe.
	 *   tab       - Zuordnung zur Stammdaten-Registerkarte.
	 *   section   - Überschrift innerhalb des Tabs.
	 *   when      - Sichtbarkeitsbedingung gegen das Profil. UND über die
	 *               Schlüssel hinweg, ODER innerhalb eines Arrays.
	 *   required  - Pflichtfeld für die Vollständigkeitsprüfung.
	 *   basis     - Rechtsgrundlage, wird im Admin und in der Prüfung gezeigt.
	 *   shortcode - Ob ein Einzelfeld-Shortcode angeboten wird.
	 *
	 * @return array
	 */
	public static function fields() {
		if ( null !== self::$fields ) {
			return self::$fields;
		}

		$fields = array(

			// -------------------------------------------------------- Unternehmen.

			'company_name'              => array(
				'label'    => __( 'Firma / Name', 'unternehmensdaten' ),
				'type'     => 'text',
				'tab'      => 'company',
				'section'  => 'identity',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 1 DDG',
				'help'     => __( 'Vollständiger Name einschließlich Rechtsformzusatz, genau wie im Register eingetragen.', 'unternehmensdaten' ),
			),
			'owner_name'                => array(
				'label'    => __( 'Inhaber / Inhaberin', 'unternehmensdaten' ),
				'type'     => 'text',
				'tab'      => 'company',
				'section'  => 'identity',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 1 DDG',
				'when'     => array( 'legal_form' => array( 'sole', 'freelancer', 'ek' ) ),
				'help'     => __( 'Vor- und Nachname der natürlichen Person. Bei Einzelunternehmen zwingend, ein Fantasiename allein genügt nicht.', 'unternehmensdaten' ),
			),
			'street'                    => array(
				'label'    => __( 'Straße und Hausnummer', 'unternehmensdaten' ),
				'type'     => 'text',
				'tab'      => 'company',
				'section'  => 'address',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 1 DDG',
				'help'     => __( 'Ladungsfähige Anschrift. Ein Postfach genügt nicht.', 'unternehmensdaten' ),
			),
			'address_addition'          => array(
				'label'   => __( 'Adresszusatz', 'unternehmensdaten' ),
				'type'    => 'text',
				'tab'     => 'company',
				'section' => 'address',
				'help'    => __( 'Etwa c/o, Etage oder Gebäude.', 'unternehmensdaten' ),
			),
			'postal_code'               => array(
				'label'      => __( 'Postleitzahl', 'unternehmensdaten' ),
				'type'       => 'text',
				'pair'       => 'locality',
				'pair_label' => __( 'Postleitzahl und Ort', 'unternehmensdaten' ),
				'tab'      => 'company',
				'section'  => 'address',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 1 DDG',
			),
			'city'                      => array(
				'label'    => __( 'Ort', 'unternehmensdaten' ),
				'type'     => 'text',
				'pair'     => 'locality',
				'tab'      => 'company',
				'section'  => 'address',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 1 DDG',
			),
			'country'                   => array(
				'label'   => __( 'Land', 'unternehmensdaten' ),
				'type'    => 'text',
				'tab'     => 'company',
				'section' => 'address',
				'help'    => __( 'Leer lassen, wenn die Anschrift im Inland liegt und das Land nicht ausgegeben werden soll.', 'unternehmensdaten' ),
			),
			'maps_google'               => array(
				'label'      => __( 'Google Maps', 'unternehmensdaten' ),
				'type'       => 'url',
				'pair'       => 'maps',
				'pair_label' => __( 'Kartenlinks', 'unternehmensdaten' ),
				'tab'        => 'company',
				'section'    => 'address',
				'derived'    => array( 'UNDT_Store', 'maps_link' ),
				'help'       => __( 'Link zum Eintrag, etwa über „Teilen“ in Google Maps. Leer gelassen, entsteht eine Suche nach Firma und Anschrift, die grau im Feld steht.', 'unternehmensdaten' ),
			),
			'maps_apple'                => array(
				'label'   => __( 'Apple Maps', 'unternehmensdaten' ),
				'type'    => 'url',
				'pair'    => 'maps',
				'tab'     => 'company',
				'section' => 'address',
				'derived' => array( 'UNDT_Store', 'maps_link' ),
				'help'    => __( 'Link zum Eintrag in Apple Maps. Leer gelassen, zeigt der Link die Anschrift mit dem Firmennamen.', 'unternehmensdaten' ),
			),

			// ----------------------------------------------------------- Kontakt.

			'email'                     => array(
				'label'    => __( 'E-Mail-Adresse', 'unternehmensdaten' ),
				'type'     => 'email',
				'tab'      => 'contact',
				'section'  => 'contact',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 2 DDG',
				'help'     => __( 'Zwingend. Ein reines Kontaktformular ersetzt die E-Mail-Adresse nicht.', 'unternehmensdaten' ),
			),
			'phone'                     => array(
				'label'    => __( 'Telefon', 'unternehmensdaten' ),
				'type'     => 'tel',
				'tab'      => 'contact',
				'section'  => 'contact',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 2 DDG',
				'help'     => __( 'Zweiter, schneller Kommunikationsweg. Alternativ genügt nach dem EuGH-Urteil C-649/17 ein Kontaktformular mit zugesagter Antwort binnen 60 Minuten.', 'unternehmensdaten' ),
			),
			'fax'                       => array(
				'label'   => __( 'Telefax', 'unternehmensdaten' ),
				'type'    => 'tel',
				'tab'     => 'contact',
				'section' => 'contact',
			),
			'contact_form_url'          => array(
				'label'   => __( 'URL des Kontaktformulars', 'unternehmensdaten' ),
				'type'    => 'url',
				'tab'     => 'contact',
				'section' => 'contact',
				'help'    => __( 'Kann den zweiten Kommunikationsweg abdecken, wenn keine Telefonnummer veröffentlicht wird.', 'unternehmensdaten' ),
			),
			'website'                   => array(
				'label'   => __( 'Website', 'unternehmensdaten' ),
				'type'    => 'url',
				'tab'     => 'contact',
				'section' => 'contact',
			),

			// ------------------------------------------- Register und Vertretung.

			'register_court'            => array(
				'label'      => __( 'Registergericht', 'unternehmensdaten' ),
				'type'       => 'text',
				'pair'       => 'register',
				'pair_label' => __( 'Registergericht und Nummer', 'unternehmensdaten' ),
				'tab'      => 'register',
				'section'  => 'register',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 4 DDG',
				'when'     => array( '_has_register' => true ),
				'help'     => __( 'Etwa Amtsgericht München.', 'unternehmensdaten' ),
			),
			'register_number'           => array(
				'label'    => __( 'Registernummer', 'unternehmensdaten' ),
				'type'     => 'text',
				'pair'     => 'register',
				'tab'      => 'register',
				'section'  => 'register',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 4 DDG',
				'when'     => array( '_has_register' => true ),
				'help'     => __( 'Etwa HRB 123456.', 'unternehmensdaten' ),
			),
			'complementary_name'        => array(
				'label'    => __( 'Name der Komplementär-GmbH', 'unternehmensdaten' ),
				'type'     => 'text',
				'tab'      => 'register',
				'section'  => 'register2',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 4 DDG, § 125a HGB',
				'when'     => array( 'legal_form' => array( 'gmbhcokg' ) ),
			),
			'register_court_2'          => array(
				'label'    => __( 'Registergericht der Komplementär-GmbH', 'unternehmensdaten' ),
				'type'     => 'text',
				'tab'      => 'register',
				'section'  => 'register2',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 4 DDG, § 125a HGB',
				'when'     => array( 'legal_form' => array( 'gmbhcokg' ) ),
			),
			'register_number_2'         => array(
				'label'    => __( 'Registernummer der Komplementär-GmbH', 'unternehmensdaten' ),
				'type'     => 'text',
				'tab'      => 'register',
				'section'  => 'register2',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 4 DDG, § 125a HGB',
				'when'     => array( 'legal_form' => array( 'gmbhcokg' ) ),
				'help'     => __( 'Die KG steht im Handelsregister A, die Komplementär-GmbH zusätzlich im Handelsregister B. Beide Eintragungen gehören ins Impressum.', 'unternehmensdaten' ),
			),
			'representatives'           => array(
				'label'    => __( 'Vertretungsberechtigte', 'unternehmensdaten' ),
				'type'     => 'list',
				'tab'      => 'register',
				'section'  => 'representation',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 1 DDG, § 35a GmbHG, § 80 AktG',
				'when'     => array( '_needs_rep' => true ),
				'help'     => __( 'Eine Person pro Zeile. Es müssen alle Vertretungsberechtigten genannt werden, nicht nur eine Auswahl.', 'unternehmensdaten' ),
			),
			'board_chair'               => array(
				'label'   => __( 'Vorsitzender des Aufsichtsrats', 'unternehmensdaten' ),
				'type'    => 'text',
				'tab'     => 'register',
				'section' => 'representation',
				'basis'   => '§ 80 AktG',
				'when'    => array( '_has_board' => true ),
			),
			'share_capital'             => array(
				'label'      => __( 'Stamm- bzw. Grundkapital', 'unternehmensdaten' ),
				'type'       => 'text',
				'pair'       => 'capital',
				'pair_label' => __( 'Kapital und ausstehende Einlagen', 'unternehmensdaten' ),
				'tab'     => 'register',
				'section' => 'capital',
				'basis'   => '§ 5 Abs. 1 Nr. 1 DDG',
				'when'    => array( '_has_capital' => true ),
				'help'    => __( 'Freiwillig. Sobald das Kapital genannt wird, müssen auch ausstehende Einlagen angegeben werden.', 'unternehmensdaten' ),
			),
			'outstanding_contributions' => array(
				'label'   => __( 'Gesamtbetrag der ausstehenden Einlagen', 'unternehmensdaten' ),
				'type'    => 'text',
				'pair'    => 'capital',
				'tab'     => 'register',
				'section' => 'capital',
				'basis'   => '§ 5 Abs. 1 Nr. 1 DDG',
				'when'    => array( '_has_capital' => true ),
				'help'    => __( 'Nur auszufüllen, wenn nicht alle in Geld zu leistenden Einlagen eingezahlt sind.', 'unternehmensdaten' ),
			),
			'liquidator'                => array(
				'label'    => __( 'Liquidator / Abwickler', 'unternehmensdaten' ),
				'type'     => 'list',
				'tab'      => 'register',
				'section'  => 'capital',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 7 DDG',
				'when'     => array( 'is_liquidating' => 1 ),
			),
			'vat_id'                    => array(
				'label'      => __( 'Umsatzsteuer-Identifikationsnummer', 'unternehmensdaten' ),
				'type'       => 'text',
				'pair'       => 'taxids',
				'pair_label' => __( 'Steuerliche Kennnummern', 'unternehmensdaten' ),
				'tab'      => 'register',
				'section'  => 'tax',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 6 DDG, § 27a UStG',
				'when'     => array( 'vat_status' => array( 'standard' ) ),
				'help'     => __( 'Format DE123456789. Nur angeben, wenn tatsächlich vorhanden.', 'unternehmensdaten' ),
			),
			'business_id'               => array(
				'label'   => __( 'Wirtschafts-Identifikationsnummer', 'unternehmensdaten' ),
				'type'    => 'text',
				'pair'    => 'taxids',
				'tab'     => 'register',
				'section' => 'tax',
				'basis'   => '§ 5 Abs. 1 Nr. 6 DDG, § 139c AO',
				'help'    => __( 'Format DE123456789-00001. Seit November 2024 im Rollout. Das DDG nennt sie ausdrücklich als Alternative zur USt-IdNr.', 'unternehmensdaten' ),
			),
			'small_business_note'       => array(
				'label'   => __( 'Kleinunternehmer-Hinweis', 'unternehmensdaten' ),
				'type'    => 'textarea',
				'tab'     => 'register',
				'section' => 'tax',
				'when'    => array( 'vat_status' => array( 'small_business' ) ),
				'default' => 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.',
			),

			// ------------------------------------------------ Beruf und Aufsicht.

			'job_title'                 => array(
				'label'      => __( 'Gesetzliche Berufsbezeichnung', 'unternehmensdaten' ),
				'type'       => 'text',
				'pair'       => 'jobtitle',
				'pair_label' => __( 'Berufsbezeichnung und Staat der Verleihung', 'unternehmensdaten' ),
				'tab'      => 'profession',
				'section'  => 'profession',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 5 DDG',
				'when'     => array( 'is_regulated' => 1 ),
				'help'     => __( 'Etwa Ärztin, Rechtsanwalt, Steuerberaterin.', 'unternehmensdaten' ),
			),
			'job_title_country'         => array(
				'label'    => __( 'Staat der Verleihung', 'unternehmensdaten' ),
				'type'     => 'text',
				'pair'     => 'jobtitle',
				'tab'      => 'profession',
				'section'  => 'profession',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 5 DDG',
				'when'     => array( 'is_regulated' => 1 ),
				'default'  => 'Bundesrepublik Deutschland',
				'help'     => __( 'Wird regelmäßig vergessen, ist aber ausdrücklich vorgeschrieben.', 'unternehmensdaten' ),
			),
			'specialist_title'          => array(
				'label'   => __( 'Facharztbezeichnung', 'unternehmensdaten' ),
				'type'    => 'text',
				'tab'     => 'profession',
				'section' => 'profession',
				'when'    => array( 'is_medical' => 1 ),
				'help'    => __( 'Nach der Weiterbildungsordnung der zuständigen Landesärztekammer.', 'unternehmensdaten' ),
			),
			'chamber_name'              => array(
				'label'    => __( 'Zuständige Kammer', 'unternehmensdaten' ),
				'type'     => 'text',
				'tab'      => 'profession',
				'section'  => 'profession',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 5 DDG',
				'when'     => array( 'is_regulated' => 1 ),
			),
			'chamber_address'           => array(
				'label'   => __( 'Anschrift der Kammer', 'unternehmensdaten' ),
				'type'    => 'textarea',
				'tab'     => 'profession',
				'section' => 'profession',
				'when'    => array( 'is_regulated' => 1 ),
			),
			'chamber_url'               => array(
				'label'   => __( 'Website der Kammer', 'unternehmensdaten' ),
				'type'    => 'url',
				'tab'     => 'profession',
				'section' => 'profession',
				'when'    => array( 'is_regulated' => 1 ),
			),
			'kv_name'                   => array(
				'label'   => __( 'Kassenärztliche Vereinigung', 'unternehmensdaten' ),
				'type'    => 'text',
				'tab'     => 'profession',
				'section' => 'profession',
				'when'    => array( 'is_medical' => 1 ),
				'help'    => __( 'Bei Vertragsärzten anzugeben.', 'unternehmensdaten' ),
			),
			'medical_director'          => array(
				'label'   => __( 'Ärztliche Leitung', 'unternehmensdaten' ),
				'type'    => 'text',
				'tab'     => 'profession',
				'section' => 'profession',
				'when'    => array( 'legal_form' => array( 'mvz' ) ),
			),
			'prof_rules'                => array(
				'label'    => __( 'Berufsrechtliche Regelungen', 'unternehmensdaten' ),
				'type'     => 'list',
				'tab'      => 'profession',
				'section'  => 'profession',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 5 DDG',
				'when'     => array( 'is_regulated' => 1 ),
				'help'     => __( 'Eine Regelung pro Zeile, etwa Berufsordnung der Landesärztekammer Bayern, Heilberufe-Kammergesetz Bayern.', 'unternehmensdaten' ),
			),
			'prof_rules_url'            => array(
				'label'    => __( 'Wo sind die Regelungen zugänglich?', 'unternehmensdaten' ),
				'type'     => 'url',
				'tab'      => 'profession',
				'section'  => 'profession',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 5 DDG',
				'when'     => array( 'is_regulated' => 1 ),
				'help'     => __( 'Das DDG verlangt nicht nur die Nennung der Regelungen, sondern auch die Angabe, wie sie zugänglich sind. Ein Link genügt.', 'unternehmensdaten' ),
			),
			'authority_name'            => array(
				'label'    => __( 'Zuständige Aufsichtsbehörde', 'unternehmensdaten' ),
				'type'     => 'text',
				'tab'      => 'profession',
				'section'  => 'authority',
				'required' => true,
				'basis'    => '§ 5 Abs. 1 Nr. 3 DDG',
				'when'     => array( 'needs_authority' => 1 ),
			),
			'authority_address'         => array(
				'label'   => __( 'Anschrift der Aufsichtsbehörde', 'unternehmensdaten' ),
				'type'    => 'textarea',
				'tab'     => 'profession',
				'section' => 'authority',
				'when'    => array( 'needs_authority' => 1 ),
			),
			'authority_url'             => array(
				'label'   => __( 'Website der Aufsichtsbehörde', 'unternehmensdaten' ),
				'type'    => 'url',
				'tab'     => 'profession',
				'section' => 'authority',
				'when'    => array( 'needs_authority' => 1 ),
			),
			'permit_note'               => array(
				'label'   => __( 'Erlaubnis nach', 'unternehmensdaten' ),
				'type'    => 'text',
				'tab'     => 'profession',
				'section' => 'authority',
				'when'    => array( 'needs_authority' => 1 ),
				'help'    => __( 'Etwa: Erlaubnis nach § 34c Abs. 1 Satz 1 Nr. 1 GewO.', 'unternehmensdaten' ),
			),
			'insurer_name'              => array(
				'label'    => __( 'Berufshaftpflicht: Versicherer', 'unternehmensdaten' ),
				'type'     => 'text',
				'tab'      => 'profession',
				'section'  => 'insurance',
				'required' => true,
				'basis'    => '§ 2 Abs. 1 Nr. 11 DL-InfoV',
				'when'     => array( 'has_liability_ins' => 1 ),
			),
			'insurer_address'           => array(
				'label'    => __( 'Berufshaftpflicht: Anschrift des Versicherers', 'unternehmensdaten' ),
				'type'     => 'textarea',
				'tab'      => 'profession',
				'section'  => 'insurance',
				'required' => true,
				'basis'    => '§ 2 Abs. 1 Nr. 11 DL-InfoV',
				'when'     => array( 'has_liability_ins' => 1 ),
			),
			'insurance_scope'           => array(
				'label'    => __( 'Räumlicher Geltungsbereich', 'unternehmensdaten' ),
				'type'     => 'text',
				'tab'      => 'profession',
				'section'  => 'insurance',
				'required' => true,
				'basis'    => '§ 2 Abs. 1 Nr. 11 DL-InfoV',
				'when'     => array( 'has_liability_ins' => 1 ),
				'default'  => 'Deutschland',
				'help'     => __( 'Ausdrücklich vorgeschrieben. Einschränkungen des Versicherungsschutzes müssen erkennbar sein.', 'unternehmensdaten' ),
			),

			// -------------------------------------------- Recht und Verbraucher.

			'editorial_name'            => array(
				'label'    => __( 'Verantwortlich für den Inhalt: Name', 'unternehmensdaten' ),
				'type'     => 'text',
				'tab'      => 'consumer',
				'section'  => 'editorial',
				'required' => true,
				'basis'    => '§ 18 Abs. 2 MStV',
				'when'     => array( 'has_editorial' => 1 ),
				'help'     => __( 'Muss eine natürliche Person sein, die ihren ständigen Aufenthalt im Inland oder in der EU hat.', 'unternehmensdaten' ),
			),
			'editorial_address'         => array(
				'label'   => __( 'Verantwortlich für den Inhalt: Anschrift', 'unternehmensdaten' ),
				'type'    => 'textarea',
				'tab'     => 'consumer',
				'section' => 'editorial',
				'basis'   => '§ 18 Abs. 2 MStV',
				'when'    => array( 'has_editorial' => 1 ),
				'help'    => __( 'Leer lassen, wenn die Anschrift des Unternehmens übernommen werden soll.', 'unternehmensdaten' ),
			),
			'vsbg_authority'            => array(
				'label'   => __( 'Zuständige Verbraucherschlichtungsstelle', 'unternehmensdaten' ),
				'type'    => 'textarea',
				'tab'     => 'consumer',
				'section' => 'vsbg',
				'basis'   => '§ 36 Abs. 1 Nr. 2 VSBG',
				'when'    => array( 'vsbg_participation' => array( 'voluntary', 'obliged' ) ),
				'help'    => __( 'Name, Anschrift und Website der Stelle. Nur erforderlich, wenn eine Teilnahme erfolgt, etwa Universalschlichtungsstelle des Bundes in Kehl.', 'unternehmensdaten' ),
			),
			'dpo_name'                  => array(
				'label'   => __( 'Datenschutzbeauftragter: Name', 'unternehmensdaten' ),
				'type'    => 'text',
				'tab'     => 'consumer',
				'section' => 'privacy',
				'basis'   => 'Art. 13 Abs. 1 lit. b DSGVO',
				'help'    => __( 'Pflicht, wenn in der Regel mindestens 20 Personen ständig mit automatisierter Verarbeitung personenbezogener Daten beschäftigt sind (§ 38 BDSG).', 'unternehmensdaten' ),
			),
			'dpo_email'                 => array(
				'label'   => __( 'Datenschutzbeauftragter: E-Mail', 'unternehmensdaten' ),
				'type'    => 'email',
				'tab'     => 'consumer',
				'section' => 'privacy',
				'basis'   => 'Art. 13 Abs. 1 lit. b DSGVO',
			),
			'dpo_address'               => array(
				'label'   => __( 'Datenschutzbeauftragter: Anschrift', 'unternehmensdaten' ),
				'type'    => 'textarea',
				'tab'     => 'consumer',
				'section' => 'privacy',
			),
			'privacy_authority'         => array(
				'label'   => __( 'Zuständige Datenschutz-Aufsichtsbehörde', 'unternehmensdaten' ),
				'type'    => 'textarea',
				'tab'     => 'consumer',
				'section' => 'privacy',
				'basis'   => 'Art. 13 Abs. 2 lit. d, Art. 77 DSGVO',
				'help'    => __( 'Die Landesbehörde am Sitz des Unternehmens. Wird für den Hinweis auf das Beschwerderecht benötigt.', 'unternehmensdaten' ),
			),
			'page_imprint'              => array(
				'label'   => __( 'Seite: Impressum', 'unternehmensdaten' ),
				'type'    => 'page',
				'tab'     => 'consumer',
				'section' => 'pages',
				'help'    => __( 'Wird für die Links im Footer verwendet.', 'unternehmensdaten' ),
			),
			'page_privacy'              => array(
				'label'   => __( 'Seite: Datenschutzerklärung', 'unternehmensdaten' ),
				'type'    => 'page',
				'tab'     => 'consumer',
				'section' => 'pages',
			),
			'page_terms'                => array(
				'label'   => __( 'Seite: AGB', 'unternehmensdaten' ),
				'type'    => 'page',
				'tab'     => 'consumer',
				'section' => 'pages',
			),
			'page_accessibility'        => array(
				'label'   => __( 'Seite: Erklärung zur Barrierefreiheit', 'unternehmensdaten' ),
				'type'    => 'page',
				'tab'     => 'consumer',
				'section' => 'pages',
				'basis'   => 'Anlage 3 zu §§ 14, 28 BFSG',
				'help'    => __( 'Seit dem 28.06.2025 für B2C-Dienstleistungen erforderlich, sofern keine Kleinstunternehmen-Ausnahme greift.', 'unternehmensdaten' ),
			),
		);

		/**
		 * Erlaubt das Ergänzen eigener Felder.
		 *
		 * Ergänzte Felder durchlaufen automatisch Sanitisierung, Escaping und
		 * Shortcode-Auflösung.
		 *
		 * @param array $fields Feld-Register.
		 */
		$fields = apply_filters( 'undt_fields', $fields );

		// Voreinstellungen ergänzen, damit der restliche Code nie prüfen muss.
		foreach ( $fields as $key => $field ) {
			$fields[ $key ] = array_merge(
				array(
					'label'      => $key,
					'type'       => 'text',
					'tab'        => 'company',
					'section'    => 'other',
					'when'       => array(),
					'required'   => false,
					'basis'      => '',
					'help'       => '',
					'default'    => '',
					'shortcode'  => true,
					// Liefert den Wert eines leeren Feldes aus anderen Angaben, siehe UNDT_Store::get().
					'derived'    => null,
					'choices'    => array(),
					// Felder mit gleichem pair teilen sich eine Formularzeile.
					'pair'       => '',
					'pair_label' => '',
				),
				$field
			);
		}

		self::$fields = $fields;

		return $fields;
	}

	/**
	 * Ein einzelnes Feld aus dem Register.
	 *
	 * @param string $key Feldschlüssel.
	 * @return array|null
	 */
	public static function field( $key ) {
		$fields = self::fields();

		return isset( $fields[ $key ] ) ? $fields[ $key ] : null;
	}

	/**
	 * Prüft, ob ein Feld beim aktuellen Profil überhaupt gilt.
	 *
	 * Abgeleitete Bedingungen beginnen mit einem Unterstrich und werden aus der
	 * Rechtsform berechnet, statt sie in jedem Feld einzeln aufzuzählen.
	 *
	 * @param array $when    Bedingungen des Feldes.
	 * @param array $profile Profil.
	 * @return bool
	 */
	public static function applies( array $when, array $profile ) {
		if ( empty( $when ) ) {
			return true;
		}

		$form = self::legal_form( isset( $profile['legal_form'] ) ? $profile['legal_form'] : '' );

		foreach ( $when as $key => $expected ) {
			switch ( $key ) {
				case '_has_register':
					$actual = ! empty( $form['register'] );
					break;
				case '_needs_rep':
					$actual = ! empty( $form['rep'] );
					break;
				case '_has_capital':
					$actual = ! empty( $form['capital'] );
					break;
				case '_has_board':
					$actual = ! empty( $form['board'] );
					break;
				default:
					$actual = isset( $profile[ $key ] ) ? $profile[ $key ] : null;
			}

			if ( is_array( $expected ) ) {
				if ( ! in_array( (string) $actual, array_map( 'strval', $expected ), true ) ) {
					return false;
				}
			} elseif ( is_bool( $expected ) ) {
				if ( (bool) $actual !== $expected ) {
					return false;
				}
			} elseif ( (int) $actual !== (int) $expected ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Liefert die Eigenschaften einer Rechtsform.
	 *
	 * @param string $key Rechtsform-Schlüssel.
	 * @return array
	 */
	public static function legal_form( $key ) {
		$forms = self::legal_forms();
		$key   = is_string( $key ) && isset( $forms[ $key ] ) ? $key : 'sole';

		if ( ! isset( self::$lists['legal_form'][ $key ] ) ) {
			self::$lists['legal_form'][ $key ] = array_merge(
				array(
					'label'     => '',
					'register'  => '',
					'rep'       => false,
					'capital'   => false,
					'board'     => false,
					'second'    => '',
					'rep_label' => __( 'Vertretungsberechtigte', 'unternehmensdaten' ),
				),
				$forms[ $key ]
			);
		}

		return self::$lists['legal_form'][ $key ];
	}
}
