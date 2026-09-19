<?php
/**
 * Legt Beispieldaten und Testseiten an und schreibt die Seiten-IDs nach
 * /wordpress/undt-audit-ids.json. Eigener Blueprint-Schritt, damit die
 * anschliessenden Render-Schritte die Daten bereits vorfinden.
 */
define( 'WP_ADMIN', true );

require_once '/wordpress/wp-load.php';

require '/wordpress/build/dev/seed.php';

$imprint_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Impressum', 'post_content' => '[undt_impressum]' ) );
$privacy_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Datenschutz Entwurf', 'post_content' => 'Angaben gemäß § 5 TMG. Plattform: https://ec.europa.eu/consumers/odr' ) );
$all_id     = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Alle Bloecke', 'post_name' => 'alle-bloecke', 'post_content' => "[undt_impressum]\n\n[undt_hours]\n\n[undt_hours_today prefix=\"Heute\"]\n\n[undt_open_now]\n\n[undt_prices]\n\n[undt_social]\n\n[undt_faq]\n\n[undt_footer]\n\n[undt_address]\n\n[undt_privacy_block name=\"controller\"]\n\n[undt key=\"phone\" link=\"1\"]\n\n[undt key=\"page_privacy\" link=\"1\"]" ) );
$plain_id   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Ohne Bloecke', 'post_name' => 'ohne-bloecke', 'post_content' => 'Nur Text.' ) );

$company                 = get_option( 'undt_company' );
$company['page_imprint'] = $imprint_id;
$company['page_privacy'] = $privacy_id;
update_option( 'undt_company', $company );

// Sprechende Adressen, damit die Render-Schritte feste URIs nutzen koennen.
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();

file_put_contents(
	'/wordpress/undt-audit-ids.json',
	wp_json_encode(
		array(
			'imprint' => (int) $imprint_id,
			'privacy' => (int) $privacy_id,
			'all'     => (int) $all_id,
			'plain'   => (int) $plain_id,
		)
	)
);
