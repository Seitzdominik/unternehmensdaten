<?php
/**
 * Legt Beispieldaten an, damit sich die Oberflaeche mit echtem Inhalt ansehen
 * laesst. Wird sowohl vom Pruefskript als auch vom Entwicklungsserver genutzt.
 *
 * @package Unternehmensdaten
 */

update_option(
	'undt_profile',
	array_merge(
		UNDT_Schema::profile_defaults(),
		array(
			'legal_form'         => 'gmbh',
			'vat_status'         => 'standard',
			'sells_to_consumers' => 1,
			'vsbg_participation' => 'no',
			'has_editorial'      => 1,
		)
	)
);

update_option(
	'undt_company',
	array(
		'company_name'      => 'Playground GmbH',
		'street'            => 'Teststraße 7',
		'postal_code'       => '10115',
		'city'              => 'Berlin',
		'email'             => 'info@playground.test',
		'phone'             => '+49 30 1234567',
		'register_court'    => 'Amtsgericht Berlin-Charlottenburg',
		'register_number'   => 'HRB 123456 B',
		'representatives'   => "Erika Mustermann\nMax Mustermann",
		'vat_id'            => 'DE123456789',
		'editorial_name'    => 'Erika Mustermann',
		'privacy_authority' => "Berliner Beauftragte für Datenschutz und Informationsfreiheit\nAlt-Moabit 59-61\n10555 Berlin",
	)
);

$undt_days = array();

foreach ( array( 'mon', 'tue', 'wed', 'thu', 'fri' ) as $undt_day ) {
	$undt_days[ $undt_day ] = array(
		'closed' => 0,
		'slots'  => array(
			array( 'from' => '09:00', 'to' => '12:30' ),
			array( 'from' => '14:00', 'to' => '18:00' ),
		),
	);
}

$undt_days['sat'] = array( 'closed' => 0, 'slots' => array( array( 'from' => '10:00', 'to' => '14:00' ) ) );
$undt_days['sun'] = array( 'closed' => 1, 'slots' => array() );

update_option(
	'undt_hours',
	array(
		'days'         => $undt_days,
		'suffix'       => 'Uhr',
		'closed_label' => 'geschlossen',
		'note'         => 'Termine außerhalb der Öffnungszeiten nach Vereinbarung.',
		'special'      => array(
			array( 'date' => '2026-12-24', 'closed' => 1, 'from' => '', 'to' => '', 'note' => 'Heiligabend' ),
			array( 'date' => '2026-12-31', 'closed' => 0, 'from' => '09:00', 'to' => '13:00', 'note' => 'Silvester' ),
		),
	)
);

update_option(
	'undt_prices',
	array(
		'items'    => array(
			array( 'group' => 'Beratung', 'label' => 'Erstgespräch', 'price' => 'kostenfrei', 'note' => 'bis 30 Minuten' ),
			array( 'group' => 'Beratung', 'label' => 'Folgetermin', 'price' => '90,00 €', 'note' => 'je angefangene Stunde' ),
			array( 'group' => 'Workshops', 'label' => 'Tagesworkshop', 'price' => '750,00 €', 'note' => 'bis 8 Personen' ),
		),
		'intro'    => 'Ein Auszug aus unserem Leistungsangebot.',
		'footnote' => 'Alle Preise verstehen sich als Gesamtpreise inklusive der gesetzlichen Umsatzsteuer.',
	)
);

update_option(
	'undt_social',
	array(
		'items'   => array(
			array( 'platform' => 'linkedin', 'label' => '', 'url' => 'https://example.test/company/playground' ),
			array( 'platform' => 'instagram', 'label' => '', 'url' => 'https://example.test/playground' ),
			array( 'platform' => 'mastodon', 'label' => 'Mastodon', 'url' => 'https://example.test/@playground' ),
		),
		'rel_me'  => 1,
		'new_tab' => 0,
	)
);

update_option(
	'undt_faq',
	array(
		'items'      => array(
			array( 'group' => 'Allgemein', 'question' => 'Wie erreiche ich Sie am besten?', 'answer' => "Telefonisch während der Öffnungszeiten oder jederzeit per E-Mail.\n\nAuf E-Mails antworten wir in der Regel am selben Werktag." ),
			array( 'group' => 'Allgemein', 'question' => 'Bieten Sie Termine außerhalb der Öffnungszeiten an?', 'answer' => 'Ja, nach vorheriger Absprache.' ),
			array( 'group' => 'Abrechnung', 'question' => 'Welche Zahlungsarten akzeptieren Sie?', 'answer' => 'Überweisung und Lastschrift.' ),
		),
		'style'      => 'details',
		'open_first' => 1,
		'schema'     => 0,
	)
);

update_option(
	'undt_banner',
	array(
		'enabled'     => 1,
		'type'        => 'warning',
		'text'        => 'Vom 24.12. bis zum 02.01. bleibt unser Büro geschlossen.',
		'link_text'   => 'Zu den Öffnungszeiten',
		'link_url'    => 'https://example.test/oeffnungszeiten',
		'dismissible' => 1,
		'auto_output' => 1,
	)
);

update_option(
	'undt_seo',
	array(
		'output_mode' => 'auto',
		'schema_type' => 'ProfessionalService',
		'price_range' => '€€',
		'geo_lat'     => '52.520008',
		'geo_lng'     => '13.404954',
		'area_served' => 'Berlin und Brandenburg',
		'with_hours'  => 1,
		'with_social' => 1,
	)
);

update_option( 'undt_setup_done', 1 );

UNDT_Store::flush();
UNDT_Content::flush();
