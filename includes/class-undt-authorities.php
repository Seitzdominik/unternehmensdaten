<?php
/**
 * Die Datenschutz-Aufsichtsbehoerden der Laender.
 *
 * Zustaendig fuer Unternehmen, Selbstaendige und Praxen ist die Behoerde des
 * Landes, in dem die Niederlassung liegt (Art. 55 DSGVO, § 40 BDSG). In Bayern
 * ist das das Landesamt fuer Datenschutzaufsicht, nicht der Landesbeauftragte,
 * der nur fuer oeffentliche Stellen zustaendig ist.
 *
 * Die Angaben dienen als Vorschlag: auf „Firma“ uebernimmt ein Klick Name und
 * Hausanschrift in das Feld der Aufsichtsbehoerde. Kopiert, nicht verknuepft,
 * damit eine spaetere Korrektur hier keine gespeicherte Angabe aendert.
 *
 * Stand 06.10.2026, je Land auf der Website der Behoerde selbst nachgesehen
 * (Impressum oder Kontakt, Quelle in source).
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Authorities
 */
final class UNDT_Authorities {

	/**
	 * Die Behoerden, Schluessel ist das Kuerzel des Landes.
	 *
	 * @return array Land => name, street, postal_code, city, url, source.
	 */
	public static function all() {
		return array(
			'BW' => array(
				'name'        => 'Der Landesbeauftragte für den Datenschutz und die Informationsfreiheit Baden-Württemberg',
				'street'      => 'Heilbronner Straße 35',
				'postal_code' => '70191',
				'city'        => 'Stuttgart',
				'url'         => 'https://www.baden-wuerttemberg.datenschutz.de',
				'source'      => 'https://www.baden-wuerttemberg.datenschutz.de/impressum/',
			),
			'BY' => array(
				'name'        => 'Bayerisches Landesamt für Datenschutzaufsicht',
				'street'      => 'Promenade 18',
				'postal_code' => '91522',
				'city'        => 'Ansbach',
				'url'         => 'https://www.lda.bayern.de',
				'source'      => 'https://www.lda.bayern.de/de/impressum.html',
			),
			'BE' => array(
				'name'        => 'Berliner Beauftragte für Datenschutz und Informationsfreiheit',
				'street'      => 'Alt-Moabit 59-61',
				'postal_code' => '10555',
				'city'        => 'Berlin',
				'url'         => 'https://www.datenschutz-berlin.de',
				'source'      => 'https://www.datenschutz-berlin.de/impressum/',
			),
			'BB' => array(
				'name'        => 'Die Landesbeauftragte für den Datenschutz und für das Recht auf Akteneinsicht Brandenburg',
				'street'      => 'Stahnsdorfer Damm 77',
				'postal_code' => '14532',
				'city'        => 'Kleinmachnow',
				'url'         => 'https://www.lda.brandenburg.de',
				'source'      => 'https://www.lda.brandenburg.de/lda/de/fusszeile/impressum/',
			),
			'HB' => array(
				'name'        => 'Der Landesbeauftragte für Datenschutz und Informationsfreiheit der Freien Hansestadt Bremen',
				'street'      => 'Georgstraße 122-124',
				'postal_code' => '27570',
				'city'        => 'Bremerhaven',
				'url'         => 'https://www.datenschutz.bremen.de',
				'source'      => 'https://www.datenschutz.bremen.de/impressum-1478',
			),
			'HH' => array(
				'name'        => 'Der Hamburgische Beauftragte für Datenschutz und Informationsfreiheit',
				'street'      => 'Ludwig-Erhard-Straße 22',
				'postal_code' => '20459',
				'city'        => 'Hamburg',
				'url'         => 'https://datenschutz-hamburg.de',
				'source'      => 'https://datenschutz-hamburg.de/impressum',
			),
			'HE' => array(
				'name'        => 'Der Hessische Beauftragte für Datenschutz und Informationsfreiheit',
				'street'      => 'Wilhelmstraße 7',
				'postal_code' => '65185',
				'city'        => 'Wiesbaden',
				'url'         => 'https://datenschutz.hessen.de',
				'source'      => 'https://datenschutz.hessen.de/impressum',
			),
			'MV' => array(
				'name'        => 'Der Landesbeauftragte für Datenschutz und Informationsfreiheit Mecklenburg-Vorpommern',
				'street'      => 'Werderstraße 74a',
				'postal_code' => '19055',
				'city'        => 'Schwerin',
				'url'         => 'https://www.datenschutz-mv.de',
				'source'      => 'https://www.datenschutz-mv.de/impressum/',
			),
			'NI' => array(
				'name'        => 'Der Landesbeauftragte für den Datenschutz Niedersachsen',
				'street'      => 'Prinzenstraße 5',
				'postal_code' => '30159',
				'city'        => 'Hannover',
				'url'         => 'https://www.lfd.niedersachsen.de',
				'source'      => 'https://www.lfd.niedersachsen.de/startseite/wir_uber_uns/kontakt_und_anfahrt/kontakt-und-anreise-56182.html',
			),
			'NW' => array(
				'name'        => 'Landesbeauftragte für Datenschutz und Informationsfreiheit Nordrhein-Westfalen',
				'street'      => 'Kavalleriestraße 2-4',
				'postal_code' => '40213',
				'city'        => 'Düsseldorf',
				'url'         => 'https://www.ldi.nrw.de',
				'source'      => 'https://www.ldi.nrw.de/impressum',
			),
			'RP' => array(
				'name'        => 'Der Landesbeauftragte für den Datenschutz und die Informationsfreiheit Rheinland-Pfalz',
				'street'      => 'Hintere Bleiche 34',
				'postal_code' => '55116',
				'city'        => 'Mainz',
				'url'         => 'https://www.datenschutz.rlp.de',
				'source'      => 'https://www.datenschutz.rlp.de/ueber-den-lfdi/impressum',
			),
			'SL' => array(
				'name'        => 'Unabhängiges Datenschutzzentrum Saarland',
				'street'      => 'Fritz-Dobisch-Straße 12',
				'postal_code' => '66111',
				'city'        => 'Saarbrücken',
				'url'         => 'https://www.datenschutz.saarland.de',
				'source'      => 'https://www.datenschutz.saarland.de/impressum',
			),
			'SN' => array(
				'name'        => 'Sächsische Datenschutz- und Transparenzbeauftragte',
				'street'      => 'Maternistraße 17',
				'postal_code' => '01067',
				'city'        => 'Dresden',
				'url'         => 'https://www.datenschutz.sachsen.de',
				'source'      => 'https://www.datenschutz.sachsen.de/impressum.html',
			),
			'ST' => array(
				'name'        => 'Die Landesbeauftragte für den Datenschutz Sachsen-Anhalt',
				'street'      => 'Otto-von-Guericke-Straße 34a',
				'postal_code' => '39104',
				'city'        => 'Magdeburg',
				'url'         => 'https://datenschutz.sachsen-anhalt.de',
				'source'      => 'https://datenschutz.sachsen-anhalt.de/impressum',
			),
			'SH' => array(
				'name'        => 'Unabhängiges Landeszentrum für Datenschutz Schleswig-Holstein',
				'street'      => 'Holstenstraße 98',
				'postal_code' => '24103',
				'city'        => 'Kiel',
				'url'         => 'https://www.datenschutzzentrum.de',
				'source'      => 'https://www.datenschutzzentrum.de/anfahrt/',
			),
			'TH' => array(
				'name'        => 'Thüringer Landesbeauftragter für den Datenschutz und die Informationsfreiheit',
				'street'      => 'Häßlerstraße 8',
				'postal_code' => '99096',
				'city'        => 'Erfurt',
				'url'         => 'https://tlfdi.de',
				'source'      => 'https://tlfdi.de/impressum/',
			),
		);
	}

	/**
	 * Die Behoerde eines Landes, null wenn das Land unbekannt ist.
	 *
	 * @param string $state Kuerzel des Landes.
	 * @return array|null
	 */
	public static function for_state( $state ) {
		$all = self::all();

		return isset( $all[ $state ] ) ? $all[ $state ] : null;
	}

	/**
	 * Der Text, den „Übernehmen“ in das Feld der Aufsichtsbehoerde schreibt:
	 * Name, Strasse, Ort, Website, je eine Zeile.
	 *
	 * @param string $state Kuerzel des Landes.
	 * @return string
	 */
	public static function text( $state ) {
		$authority = self::for_state( $state );

		if ( null === $authority ) {
			return '';
		}

		return implode(
			"\n",
			array(
				$authority['name'],
				$authority['street'],
				$authority['postal_code'] . ' ' . $authority['city'],
				$authority['url'],
			)
		);
	}
}
