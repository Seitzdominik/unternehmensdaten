<?php
/**
 * Das Modul-Register fuer die Inhaltsbereiche.
 *
 * Gleiche Idee wie beim Feld-Register der Stammdaten: eine Datenstruktur, aus
 * der Menue, Formular, Sanitisierung, Shortcodes und Ausgabe entstehen.
 *
 * Jedes Modul haelt seine Daten in einer eigenen Option. Die autoload-Angabe
 * ist eine bewusste Entscheidung je Modul: was im Footer oder Kopfbereich jeder
 * Seite gebraucht wird, laedt WordPress ohnehin mit, alles andere kostet nur auf
 * den Seiten etwas, die es tatsaechlich ausgeben.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Modules
 */
final class UNDT_Modules {

	const OPTION_SETTINGS = 'undt_settings';

	/**
	 * Laufzeit-Cache.
	 *
	 * @var array|null
	 */
	private static $modules = null;

	/**
	 * Laufzeit-Cache der aktiven Module.
	 *
	 * @var array|null
	 */
	private static $active = null;

	/**
	 * Laufzeit-Cache der festen Listen.
	 *
	 * @var array
	 */
	private static $lists = array();

	/**
	 * Bekannte Social-Plattformen.
	 *
	 * @return array
	 */
	public static function platforms() {
		if ( ! isset( self::$lists['platforms'] ) ) {
			self::$lists['platforms'] = self::build_platforms();
		}

		return self::$lists['platforms'];
	}

	/**
	 * Baut die Liste der Plattformen auf.
	 *
	 * @return array
	 */
	private static function build_platforms() {
		return array(
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'linkedin'  => 'LinkedIn',
			'x'         => 'X',
			'youtube'   => 'YouTube',
			'tiktok'    => 'TikTok',
			'pinterest' => 'Pinterest',
			'xing'      => 'Xing',
			'whatsapp'  => 'WhatsApp',
			'threads'   => 'Threads',
			'bluesky'   => 'Bluesky',
			'mastodon'  => 'Mastodon',
			'github'    => 'GitHub',
			'spotify'   => 'Spotify',
			'telegram'  => 'Telegram',
			'vimeo'     => 'Vimeo',
			'kununu'    => 'kununu',
			'other'     => __( 'Andere', 'unternehmensdaten' ),
		);
	}

	/**
	 * Auswaehlbare Schema.org-Typen.
	 *
	 * @return array
	 */
	public static function schema_types() {
		if ( ! isset( self::$lists['schema_types'] ) ) {
			self::$lists['schema_types'] = self::build_schema_types();
		}

		return self::$lists['schema_types'];
	}

	/**
	 * Baut die Liste der Schema.org-Typen auf.
	 *
	 * @return array
	 */
	private static function build_schema_types() {
		return array(
			'Organization'        => __( 'Organization (allgemein)', 'unternehmensdaten' ),
			'LocalBusiness'       => __( 'LocalBusiness (Betrieb mit Ladenlokal)', 'unternehmensdaten' ),
			'ProfessionalService' => __( 'ProfessionalService (Dienstleistung)', 'unternehmensdaten' ),
			'MedicalClinic'       => __( 'MedicalClinic (Praxis, MVZ)', 'unternehmensdaten' ),
			'Physician'           => __( 'Physician (einzelne Ärztin, einzelner Arzt)', 'unternehmensdaten' ),
			'Dentist'             => __( 'Dentist (Zahnarztpraxis)', 'unternehmensdaten' ),
			'Pharmacy'            => __( 'Pharmacy (Apotheke)', 'unternehmensdaten' ),
			'VeterinaryCare'      => __( 'VeterinaryCare (Tierarztpraxis)', 'unternehmensdaten' ),
			'LegalService'        => __( 'LegalService (Kanzlei)', 'unternehmensdaten' ),
			'AccountingService'   => __( 'AccountingService (Steuerberatung)', 'unternehmensdaten' ),
			'Restaurant'          => __( 'Restaurant', 'unternehmensdaten' ),
			'Store'               => __( 'Store (Einzelhandel)', 'unternehmensdaten' ),
			'HomeAndConstructionBusiness' => __( 'HomeAndConstructionBusiness (Handwerk)', 'unternehmensdaten' ),
			'HealthAndBeautyBusiness'     => __( 'HealthAndBeautyBusiness (Salon, Kosmetik)', 'unternehmensdaten' ),
			'SportsActivityLocation'      => __( 'SportsActivityLocation (Studio, Verein)', 'unternehmensdaten' ),
			'EducationalOrganization'     => __( 'EducationalOrganization (Bildung)', 'unternehmensdaten' ),
			'NGO'                 => __( 'NGO (Verein, gemeinnützig)', 'unternehmensdaten' ),
		);
	}

	/**
	 * Haelt die autoload-Entscheidung auch fuer spaeter entstehende Optionen.
	 *
	 * Die Aktivierung legt die Optionen mit dem richtigen Wert an, aber nur auf
	 * der Website, auf der sie laeuft. Bei netzwerkweiter Aktivierung entstehen
	 * die Optionen der uebrigen Websites erst beim ersten Speichern ueber
	 * options.php, und das kennt keine autoload-Angabe: WordPress laedt sie dann
	 * auf jeder Seite mit.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::all() as $module ) {
			if ( ! $module['autoload'] ) {
				add_action( 'add_option_' . $module['option'], array( __CLASS__, 'keep_autoload_off' ) );
			}
		}
	}

	/**
	 * Nimmt eine gerade angelegte Option aus dem Autoload.
	 *
	 * @param string $option Name der Option.
	 * @return void
	 */
	public static function keep_autoload_off( $option ) {
		if ( function_exists( 'wp_set_option_autoload_values' ) ) {
			wp_set_option_autoload_values( array( (string) $option => false ) );
		}
	}

	/**
	 * Das Modul-Register.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$modules ) {
			return self::$modules;
		}

		$modules = array(

			/* ------------------------------------------------ Öffnungszeiten */

			'hours'  => array(
				'label'    => __( 'Öffnungszeiten', 'unternehmensdaten' ),
				'option'   => 'undt_hours',
				// Steht üblicherweise im Footer, wird also überall gebraucht.
				'autoload' => true,
				'fields'   => array(
					'days'    => array(
						'label' => __( 'Reguläre Zeiten', 'unternehmensdaten' ),
						'type'  => 'hours',
						'help'  => __( 'Pro Tag sind zwei Zeitfenster möglich, etwa für eine Mittagspause. Ein leeres zweites Fenster wird nicht ausgegeben. Ein Fenster über Mitternacht, etwa 22:00 bis 02:00, gehört zu dem Tag, an dem es beginnt. Rund um die Uhr geöffnet ist 00:00 bis 23:59.', 'unternehmensdaten' ),
					),
					'special' => array(
						'label'  => __( 'Sonderöffnungszeiten', 'unternehmensdaten' ),
						'type'   => 'repeater',
						'help'   => __( 'Feiertage, Betriebsferien, abweichende Zeiten. Ein Eintrag überschreibt an diesem Datum die reguläre Zeit, ohne Uhrzeiten gilt der Tag als geschlossen. Vergangene Termine werden nicht ausgegeben.', 'unternehmensdaten' ),
						'single' => __( 'Termin', 'unternehmensdaten' ),
						'fields' => array(
							'date'   => array(
								'label' => __( 'Datum', 'unternehmensdaten' ),
								'type'  => 'date',
							),
							'closed' => array(
								'label' => __( 'Geschlossen', 'unternehmensdaten' ),
								'type'  => 'checkbox',
							),
							'from'   => array(
								'label' => __( 'Von', 'unternehmensdaten' ),
								'type'  => 'time',
							),
							'to'     => array(
								'label' => __( 'Bis', 'unternehmensdaten' ),
								'type'  => 'time',
							),
							'note'   => array(
								'label' => __( 'Anlass', 'unternehmensdaten' ),
								'type'  => 'text',
							),
						),
					),
					'note'    => array(
						'label' => __( 'Hinweis', 'unternehmensdaten' ),
						'type'  => 'textarea',
						'help'  => __( 'Erscheint unter der Tabelle, etwa „Termine nach Vereinbarung“.', 'unternehmensdaten' ),
					),
					'suffix'  => array(
						'label'   => __( 'Zusatz hinter der Uhrzeit', 'unternehmensdaten' ),
						'type'    => 'text',
						'default' => 'Uhr',
					),
					'closed_label' => array(
						'label'   => __( 'Bezeichnung für geschlossen', 'unternehmensdaten' ),
						'type'    => 'text',
						'default' => 'geschlossen',
					),
				),
			),

			/* -------------------------------------------------------- Preise */

			'prices' => array(
				'label'    => __( 'Preise & Leistungen', 'unternehmensdaten' ),
				'option'   => 'undt_prices',
				// Nur auf einzelnen Seiten gebraucht, kostet sonst nichts.
				'autoload' => false,
				'fields'   => array(
					'intro'    => array(
						'label' => __( 'Einleitung', 'unternehmensdaten' ),
						'type'  => 'textarea',
					),
					'items'    => array(
						'label'  => __( 'Positionen', 'unternehmensdaten' ),
						'type'   => 'repeater',
						'single' => __( 'Position', 'unternehmensdaten' ),
						'help'   => __( 'Positionen mit derselben Gruppe werden unter einer gemeinsamen Überschrift zusammengefasst. Bleibt die Gruppe leer, entsteht eine einfache Liste.', 'unternehmensdaten' ),
						'fields' => array(
							'group' => array(
								'label' => __( 'Gruppe', 'unternehmensdaten' ),
								'type'  => 'text',
							),
							'label' => array(
								'label' => __( 'Bezeichnung', 'unternehmensdaten' ),
								'type'  => 'text',
							),
							'price' => array(
								'label' => __( 'Preis', 'unternehmensdaten' ),
								'type'  => 'text',
							),
							'note'  => array(
								'label' => __( 'Zusatz', 'unternehmensdaten' ),
								'type'  => 'text',
							),
						),
					),
					'footnote' => array(
						'label'   => __( 'Fußnote', 'unternehmensdaten' ),
						'type'    => 'textarea',
						'help'    => __( 'Etwa der Hinweis auf die Umsatzsteuer. Bei Angeboten an Verbraucher sind Gesamtpreise einschließlich Umsatzsteuer anzugeben.', 'unternehmensdaten' ),
						'basis'   => '§ 3 PAngV',
						'default' => 'Alle Preise verstehen sich als Gesamtpreise inklusive der gesetzlichen Umsatzsteuer.',
					),
				),
			),

			/* -------------------------------------------------------- Social */

			'social' => array(
				'label'    => __( 'Social Media', 'unternehmensdaten' ),
				'option'   => 'undt_social',
				'autoload' => true,
				'fields'   => array(
					'items'  => array(
						'label'  => __( 'Profile', 'unternehmensdaten' ),
						'type'   => 'repeater',
						'single' => __( 'Profil', 'unternehmensdaten' ),
						'fields' => array(
							'platform' => array(
								'label'   => __( 'Plattform', 'unternehmensdaten' ),
								'type'    => 'select',
								'choices' => self::platforms(),
							),
							'label'    => array(
								'label' => __( 'Beschriftung', 'unternehmensdaten' ),
								'type'  => 'text',
								'help'  => __( 'Leer lassen, um den Plattformnamen zu verwenden.', 'unternehmensdaten' ),
							),
							'url'      => array(
								'label' => __( 'Profil-URL', 'unternehmensdaten' ),
								'type'  => 'url',
							),
						),
					),
					'rel_me' => array(
						'label'   => __( 'Profile mit rel="me" auszeichnen', 'unternehmensdaten' ),
						'type'    => 'checkbox',
						'default' => 1,
						'help'    => __( 'Bestätigt gegenüber Mastodon und ähnlichen Diensten, dass Website und Profil zusammengehören.', 'unternehmensdaten' ),
					),
					'new_tab' => array(
						'label' => __( 'In neuem Tab öffnen', 'unternehmensdaten' ),
						'type'  => 'checkbox',
						'help'  => __( 'Setzt zusätzlich rel="noopener". Aus Sicht der Barrierefreiheit ist ein neues Fenster nur sinnvoll, wenn darauf hingewiesen wird.', 'unternehmensdaten' ),
					),
				),
			),

			/* ----------------------------------------------------------- FAQ */

			'faq'    => array(
				'label'    => __( 'FAQ', 'unternehmensdaten' ),
				'option'   => 'undt_faq',
				'autoload' => false,
				'fields'   => array(
					'items'  => array(
						'label'  => __( 'Fragen', 'unternehmensdaten' ),
						'type'   => 'repeater',
						'single' => __( 'Frage', 'unternehmensdaten' ),
						'help'   => __( 'Fragen mit derselben Gruppe erscheinen unter einer gemeinsamen Überschrift.', 'unternehmensdaten' ),
						'fields' => array(
							'group'    => array(
								'label' => __( 'Gruppe', 'unternehmensdaten' ),
								'type'  => 'text',
							),
							'question' => array(
								'label' => __( 'Frage', 'unternehmensdaten' ),
								'type'  => 'text',
							),
							'answer'   => array(
								'label' => __( 'Antwort', 'unternehmensdaten' ),
								'type'  => 'textarea',
							),
						),
					),
					'style'  => array(
						'label'   => __( 'Darstellung', 'unternehmensdaten' ),
						'type'    => 'select',
						'default' => 'details',
						'choices' => array(
							'details' => __( 'Aufklappbar (details/summary, ohne JavaScript)', 'unternehmensdaten' ),
							'dl'      => __( 'Ausgeschrieben (Definitionsliste)', 'unternehmensdaten' ),
						),
					),
					'open_first' => array(
						'label' => __( 'Erste Frage aufgeklappt zeigen', 'unternehmensdaten' ),
						'type'  => 'checkbox',
					),
					'schema' => array(
						'label' => __( 'FAQPage-Auszeichnung ausgeben', 'unternehmensdaten' ),
						'type'  => 'checkbox',
						'help'  => __( 'Google hat FAQ-Rich-Results am 07.05.2026 eingestellt, auch für Behörden- und Gesundheitsseiten. Die Auszeichnung erzeugt also keine erweiterten Suchergebnisse mehr. Sie bleibt gültiges schema.org und kann für die maschinelle Auswertung nützlich sein, ist aber kein SEO-Vorteil mehr. Deshalb standardmäßig aus.', 'unternehmensdaten' ),
					),
				),
			),

			/* ---------------------------------------------------- Infobanner */

			'banner' => array(
				'label'    => __( 'Infobanner', 'unternehmensdaten' ),
				'option'   => 'undt_banner',
				'autoload' => true,
				'fields'   => array(
					'enabled'     => array(
						'label' => __( 'Banner anzeigen', 'unternehmensdaten' ),
						'type'  => 'checkbox',
					),
					'type'        => array(
						'label'   => __( 'Art', 'unternehmensdaten' ),
						'type'    => 'select',
						'default' => 'info',
						'choices' => array(
							'info'    => __( 'Information (neutral)', 'unternehmensdaten' ),
							'success' => __( 'Positiv', 'unternehmensdaten' ),
							'warning' => __( 'Achtung', 'unternehmensdaten' ),
							'urgent'  => __( 'Dringend', 'unternehmensdaten' ),
						),
					),
					'text'        => array(
						'label' => __( 'Text', 'unternehmensdaten' ),
						'type'  => 'textarea',
					),
					'link_text'   => array(
						'label' => __( 'Link-Text', 'unternehmensdaten' ),
						'type'  => 'text',
					),
					'link_url'    => array(
						'label' => __( 'Link-Ziel', 'unternehmensdaten' ),
						'type'  => 'url',
					),
					'dismissible' => array(
						'label'   => __( 'Schließbar', 'unternehmensdaten' ),
						'type'    => 'checkbox',
						'default' => 1,
						'help'    => __( 'Die Entscheidung wird lokal im Browser gespeichert. Ändert sich der Text, erscheint das Banner erneut.', 'unternehmensdaten' ),
					),
					'auto_output' => array(
						'label'   => __( 'Automatisch am Seitenanfang ausgeben', 'unternehmensdaten' ),
						'type'    => 'checkbox',
						'default' => 1,
						'help'    => __( 'Nutzt wp_body_open. Themes ohne diesen Haken benötigen stattdessen den Shortcode. Ist die Option aus, erscheint das Banner ausschließlich dort, wo der Shortcode steht.', 'unternehmensdaten' ),
					),
				),
			),

			/* ---------------------------------------------------- SEO/Schema */

			'seo'    => array(
				'label'    => __( 'SEO & Schema', 'unternehmensdaten' ),
				'option'   => 'undt_seo',
				'autoload' => true,
				'fields'   => array(
					'output_mode' => array(
						'label'   => __( 'JSON-LD ausgeben', 'unternehmensdaten' ),
						'type'    => 'select',
						'default' => 'auto',
						'choices' => array(
							'auto'   => __( 'Nur wenn kein SEO-Plugin aktiv ist (empfohlen)', 'unternehmensdaten' ),
							'always' => __( 'Immer', 'unternehmensdaten' ),
							'never'  => __( 'Nie', 'unternehmensdaten' ),
						),
						'help'    => __( 'Yoast, Rank Math, SEOPress, AIOSEO und Slim SEO geben bereits eine Organization-Auszeichnung aus. Zwei davon auf einer Seite sind schlechter als eine.', 'unternehmensdaten' ),
					),
					'schema_type' => array(
						'label'   => __( 'Typ', 'unternehmensdaten' ),
						'type'    => 'select',
						'default' => 'Organization',
						'choices' => self::schema_types(),
						'help'    => __( 'LocalBusiness und die davon abgeleiteten Typen setzen eine Anschrift voraus, an der Kundschaft empfangen wird.', 'unternehmensdaten' ),
					),
					'logo'        => array(
						'label' => __( 'Logo', 'unternehmensdaten' ),
						'type'  => 'media',
						'help'  => __( 'Wird als logo und image ausgegeben. Empfohlen sind mindestens 112 × 112 Pixel.', 'unternehmensdaten' ),
					),
					'price_range' => array(
						'label' => __( 'Preisniveau', 'unternehmensdaten' ),
						'type'  => 'text',
						'help'  => __( 'Etwa €€ oder „10 – 50 €“. Nur bei LocalBusiness sinnvoll.', 'unternehmensdaten' ),
					),
					'geo_lat'     => array(
						'label'      => __( 'Breitengrad', 'unternehmensdaten' ),
						'type'       => 'text',
						'help'       => __( 'Etwa 52.520008. Punkt als Dezimaltrennzeichen.', 'unternehmensdaten' ),
						'pair'       => 'geo',
						'pair_label' => __( 'Koordinaten', 'unternehmensdaten' ),
					),
					'geo_lng'     => array(
						'label' => __( 'Längengrad', 'unternehmensdaten' ),
						'type'  => 'text',
						'help'  => __( 'Etwa 13.404954.', 'unternehmensdaten' ),
						'pair'  => 'geo',
					),
					'area_served' => array(
						'label' => __( 'Einzugsgebiet', 'unternehmensdaten' ),
						'type'  => 'text',
						'help'  => __( 'Etwa „Berlin und Brandenburg“.', 'unternehmensdaten' ),
					),
					'with_hours'  => array(
						'label'   => __( 'Öffnungszeiten mit ausgeben', 'unternehmensdaten' ),
						'type'    => 'checkbox',
						'default' => 1,
						'help'    => __( 'Ergänzt openingHoursSpecification aus dem Modul Öffnungszeiten.', 'unternehmensdaten' ),
					),
					'with_social' => array(
						'label'   => __( 'Social-Profile mit ausgeben', 'unternehmensdaten' ),
						'type'    => 'checkbox',
						'default' => 1,
						'help'    => __( 'Ergänzt sameAs aus dem Modul Social Media.', 'unternehmensdaten' ),
					),
				),
			),
		);

		/**
		 * Erlaubt das Ergaenzen eigener Module.
		 *
		 * @param array $modules Modul-Register.
		 */
		$modules = apply_filters( 'undt_modules', $modules );

		// Voreinstellungen ergaenzen, damit der restliche Code nie pruefen muss.
		foreach ( $modules as $slug => $module ) {
			$module = array_merge(
				array(
					'label'    => $slug,
					'option'   => 'undt_' . $slug,
					'autoload' => false,
					'fields'   => array(),
				),
				$module
			);

			foreach ( $module['fields'] as $key => $field ) {
				$module['fields'][ $key ] = self::normalize_field( $field );
			}

			$modules[ $slug ] = $module;
		}

		self::$modules = $modules;

		return $modules;
	}

	/**
	 * Ergaenzt fehlende Schluessel einer Felddefinition.
	 *
	 * @param array $field Rohe Definition.
	 * @return array
	 */
	private static function normalize_field( array $field ) {
		$field = array_merge(
			array(
				'label'      => '',
				'type'       => 'text',
				'help'       => '',
				'basis'      => '',
				'default'    => '',
				'choices'    => array(),
				'fields'     => array(),
				'single'     => __( 'Eintrag', 'unternehmensdaten' ),
				'required'   => false,
				// Einzelfeld-Shortcodes gibt es nur fuer die Stammdaten.
				'shortcode'  => false,
				// Felder mit gleichem pair teilen sich eine Formularzeile.
				'pair'       => '',
				'pair_label' => '',
			),
			$field
		);

		if ( 'repeater' === $field['type'] ) {
			foreach ( $field['fields'] as $sub_key => $sub ) {
				$field['fields'][ $sub_key ] = self::normalize_field( $sub );
			}
		}

		return $field;
	}

	/**
	 * Ein einzelnes Modul.
	 *
	 * @param string $slug Modul-Schluessel.
	 * @return array|null
	 */
	public static function get( $slug ) {
		$modules = self::all();

		return isset( $modules[ $slug ] ) ? $modules[ $slug ] : null;
	}

	/**
	 * Voreinstellung, welche Module aktiv sind.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array();

		foreach ( array_keys( self::all() ) as $slug ) {
			$defaults[ $slug ] = 1;
		}

		return $defaults;
	}

	/**
	 * Voreinstellungen der Plugin-Einstellungen.
	 *
	 * @return array
	 */
	public static function settings_defaults() {
		return array(
			'modules'   => self::defaults(),
			'keep_data' => 0,
			'updates'   => 1,
		);
	}

	/**
	 * Die gespeicherten Plugin-Einstellungen.
	 *
	 * @return array
	 */
	public static function settings() {
		$stored = get_option( self::OPTION_SETTINGS, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$settings = array_merge( self::settings_defaults(), $stored );

		$settings['modules'] = array_merge(
			self::defaults(),
			isset( $stored['modules'] ) && is_array( $stored['modules'] ) ? $stored['modules'] : array()
		);

		return $settings;
	}

	/**
	 * Sanitisiert die Plugin-Einstellungen.
	 *
	 * @param mixed $input Rohe Eingabe.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array( 'modules' => array() );

		$raw_modules = isset( $input['modules'] ) && is_array( $input['modules'] ) ? $input['modules'] : array();

		foreach ( array_keys( self::all() ) as $slug ) {
			$clean['modules'][ $slug ] = empty( $raw_modules[ $slug ] ) ? 0 : 1;
		}

		$clean['keep_data'] = empty( $input['keep_data'] ) ? 0 : 1;
		$clean['updates']   = empty( $input['updates'] ) ? 0 : 1;

		self::flush();

		return $clean;
	}

	/**
	 * Aktivierungszustand aller Module.
	 *
	 * @return array
	 */
	public static function active_map() {
		if ( null === self::$active ) {
			$settings = self::settings();

			self::$active = $settings['modules'];
		}

		return self::$active;
	}

	/**
	 * Ob ein Modul aktiv ist.
	 *
	 * @param string $slug Modul-Schluessel.
	 * @return bool
	 */
	public static function is_active( $slug ) {
		$map = self::active_map();

		return ! empty( $map[ $slug ] );
	}

	/**
	 * Leert den Laufzeit-Cache.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$active = null;
	}
}
