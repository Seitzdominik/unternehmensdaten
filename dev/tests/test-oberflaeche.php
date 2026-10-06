<?php
/**
 * Pruefungen fuer 0.5.3: die Oberflaeche des Backends.
 *
 * Geprueft wird hier, was sich ohne WordPress zeigen laesst: das Markup der
 * Felder, die zweispaltigen Fragen der Rechtsform und die einheitliche Breite
 * der Eingaben. Ob die fertigen Ansichten die Karte samt Fuss und die
 * Registerkarten erzeugen, zeigt dev/verify.php in WordPress selbst.
 */
require __DIR__ . '/harness.php';
require UNDT_TEST_BASE . 'admin/class-undt-fields.php';
require UNDT_TEST_BASE . 'admin/class-undt-controls.php';
require UNDT_TEST_BASE . 'admin/class-undt-copy.php';

function wp_list_pluck( $list, $field ) {
	return array_map( static function ( $item ) use ( $field ) { return is_object( $item ) ? $item->$field : $item[ $field ]; }, $list );
}

$undt_base = UNDT_TEST_BASE;

undt_seed(
	array( 'legal_form' => 'gmbh', 'is_regulated' => 1, 'vat_status' => 'standard' ),
	array( 'company_name' => 'Muster GmbH', 'street' => 'Hauptstraße 5' )
);


/**
 * Das Markup einzelner Stammdatenfelder.
 *
 * @param array $keys Feldschluessel.
 * @return string
 */
function undt_rows( array $keys ) {
	ob_start();
	UNDT_Fields::rows( array_intersect_key( UNDT_Schema::fields(), array_flip( $keys ) ), UNDT_Store::company(), 'undt_company' );

	return (string) ob_get_clean();
}

/* ------------------------------------------- 2. Einheitliche Breite ----- */

undt_head( 'Felder enden an derselben Kante' );

$html = undt_rows( array( 'company_name', 'email' ) );
undt_ok( 2 === substr_count( $html, 'class="large-text"' ), 'Text und E-Mail nehmen die Breite der Spalte' );
undt_ok( false === strpos( $html, 'regular-text' ), 'Keine halbbreiten Felder mehr' );

// Das Seitenfeld braucht Attrappen fuer die Inhaltstypen, es steht deshalb in
// test-dynamik.php.

ob_start();
UNDT_Controls::render( 'when', array( 'type' => 'time', 'choices' => array() ), '09:00', 'undt_x[when]', 'undt-when' );
$html = (string) ob_get_clean();
undt_ok( false !== strpos( $html, 'class="undt-input-time"' ), 'Uhrzeiten bleiben schmal' );

/* ------------------------------------------- 3. Kopierknoepfe rechts ---- */

undt_head( 'Kopierknoepfe am rechten Rand' );

$html = undt_rows( array( 'phone' ) );
undt_ok( false !== strpos( $html, '<span class="undt-copy-row__keys"><button type="button" class="undt-copy undt-copy--icon undt-copy--bricks"' ), 'Die Logos stehen in einer eigenen Gruppe hinter dem Shortcode' );
undt_ok( false !== strpos( $html, 'data-undt-copy="{options.undt.phone}" title="Etch: {options.undt.phone} kopieren"><span class="undt-copy__badge"' ) && false !== strpos( $html, '</button></span></div>' ), 'Die Gruppe schliesst die Kopierzeile ab' );

/* ------------------------------------------- 4. Schalter in zwei Spalten */

undt_head( 'Schalter nebeneinander' );

undt_set_modules( array( 'social' => 1, 'banner' => 1, 'faq' => 1 ) );
undt_seed_module( 'social', array( 'items' => array(), 'show_icons' => 1, 'show_labels' => 1 ) );

ob_start();
$undt_social = UNDT_Modules::get( 'social' );
UNDT_Fields::rows( $undt_social['fields'], UNDT_Content::all( 'social' ), $undt_social['option'], array( 'with_copy' => false ) );
$html = (string) ob_get_clean();

undt_ok( 1 === substr_count( $html, '<tr class="undt-row undt-row--wide undt-row--toggles"><td colspan="2"><div class="undt-cards">' ), 'Die fuenf Schalter stehen zusammen in einem Raster' );
undt_ok( 5 === substr_count( $html, '<div class="undt-card">' ), 'Jeder Schalter bekommt eine Zelle' );
undt_ok( false !== strpos( $html, '<label for="undt-show_icons">Symbole anzeigen</label>' ) && false !== strpos( $html, 'name="undt_social[show_icons]"' ), 'Beschriftung und Formularname bleiben unveraendert' );
undt_ok( false !== strpos( $html, 'class="undt-help-toggle"' ), 'Die Hinweise bleiben an den Schaltern' );
undt_ok( false === strpos( $html, 'undt-card--stacked' ), 'Ein Schalter braucht keine eigene Zeile in der Zelle' );

// Das Wiederholungsfeld davor bleibt eine eigene Zeile.
undt_ok( false !== strpos( $html, 'undt-row--wide"><td colspan="2"><h3 class="undt-field-title">Profile' ), 'Das Wiederholungsfeld steht weiter fuer sich' );

$undt_banner = UNDT_Modules::get( 'banner' );
undt_seed_module( 'banner', array( 'enabled' => 1 ) );
ob_start();
UNDT_Fields::rows( $undt_banner['fields'], UNDT_Content::all( 'banner' ), $undt_banner['option'], array( 'with_copy' => false ) );
$html = (string) ob_get_clean();

undt_ok( 1 === substr_count( $html, 'undt-row--toggles' ), 'Beim Banner stehen nur die beiden letzten Schalter nebeneinander' );
undt_ok( false !== strpos( $html, '<tr class="undt-row"><th scope="row"><span class="undt-label-line"><label for="undt-enabled">' ), 'Der einzelne Schalter oben bleibt eine normale Zeile' );
undt_ok( false !== strpos( $html, 'data-undt-copy="{options.undt.banner_dismissible}"' ), 'Kopierknoepfe stehen auch in einer Zelle' );

/* ------------------------------------------- 5. Handgriffe im Backend -- */

undt_head( 'Zeiten uebernehmen und automatisches Speichern' );

undt_set_modules( array( 'hours' => 1 ) );
undt_seed_module( 'hours', array( 'days' => array( 'mon' => array( 'closed' => 0, 'slots' => array( array( 'from' => '09:00', 'to' => '17:00' ) ) ) ) ) );

ob_start();
UNDT_Controls::render( 'days', array( 'type' => 'hours', 'choices' => array() ), UNDT_Content::value( 'hours', 'days' ), 'undt_hours[days]', 'undt-days' );
$html = (string) ob_get_clean();

undt_ok( false !== strpos( $html, 'data-undt-hours-copy="undt-days"' ), 'Der Knopf kennt die Tabelle, zu der er gehoert' );
undt_ok( false !== strpos( $html, 'Zeiten von Montag auf alle Tage übernehmen' ), 'Und nennt den Tag, von dem er nimmt' );
undt_ok( strpos( $html, 'undt-hours-copy' ) > strpos( $html, '</table>' ), 'Er steht unter der Tabelle' );

$js = (string) file_get_contents( $undt_base . 'admin/assets/admin.js' );
undt_ok( false !== strpos( $js, 'data-undt-hours-copy' ) && false !== strpos( $js, 'l10n.hoursCopied' ), 'Das Skript bedient den Knopf und meldet es Screenreadern' );
// Seit 0.6.0 speichert jede Seite selbst, die Warnung vor ungespeicherten Aenderungen entfaellt.
undt_ok( false === strpos( $js, 'initDirtyGuard' ) && false === strpos( $js, "'beforeunload'" ), 'Die Warnung vor ungespeicherten Aenderungen ist entfallen' );
undt_ok( false !== strpos( $js, "changed( table );" ) && false !== strpos( $js, "changed( repeater );" ) && false !== strpos( $js, "changed( moveRow );" ), 'Zeiten uebertragen, Zeilen entfernen und verschieben melden sich zum Speichern' );

$admin = (string) file_get_contents( $undt_base . 'admin/class-undt-admin.php' );
undt_ok( false !== strpos( $admin, "'hoursCopied'" ), 'Der Meldetext ist uebersetzbar' );

/* ------------------------------------------- 6. Markup und Stylesheet --- */

undt_head( 'Karte, Registerkarten und Stylesheet passen zusammen' );

$css  = (string) file_get_contents( $undt_base . 'admin/assets/admin.css' );
$js   = (string) file_get_contents( $undt_base . 'admin/assets/admin.js' );
$view = (string) file_get_contents( $undt_base . 'admin/views/company.php' );

undt_ok( false !== strpos( $css, '--undt-width: 1000px;' ), 'Eine Breite fuer alle Seiten des Plugins' );
undt_ok( false !== strpos( $css, '.undt-box {' ) && false !== strpos( $css, '.undt-box__body {' ) && false !== strpos( $css, '.undt-box__footer {' ), 'Die Karte samt Fuss ist gestaltet' );
undt_ok( false !== strpos( $css, '.undt-cards {' ) && false !== strpos( $css, 'grid-template-columns: repeat(2, minmax(0, 1fr));' ), 'Das Fragenraster hat zwei Spalten' );
undt_ok( false !== strpos( $css, '.undt-copy-row__keys {' ) && false !== strpos( $css, 'margin-left: auto;' ), 'Die Kopierknoepfe ruecken nach rechts' );
undt_ok( false !== strpos( $css, 'tr.undt-row--toggles > td {' ), 'Das Raster sitzt auch in einer Formularzeile eng' );
undt_ok( false !== strpos( $css, '.undt-tab.is-active {' ), 'Der aktive Reiter ist gestaltet' );
undt_ok( false !== strpos( $js, "classList.toggle( 'is-active', selected )" ) && false === strpos( $js, 'nav-tab-active' ), 'Das Skript schaltet dieselbe Klasse' );
undt_ok( false === strpos( $view, 'nav-tab' ), 'Die Ansicht verwendet keine WordPress-Reiter mehr' );
undt_ok( false !== strpos( $css, '.undt-copy__badge .dashicons {' ), 'Das W von WordPress ist im Rahmen gestaltet' );

// Seit 0.6.0 ersetzt „Einbinden“ die Referenz; der Block-Editor ist dort ein Format von „Kopieren als“.
$reference = (string) file_get_contents( $undt_base . 'admin/views/embed.php' );
$copy      = (string) file_get_contents( $undt_base . 'admin/class-undt-copy.php' );
undt_ok( false !== strpos( $reference, 'UNDT_Copy::codes( $undt_value[\'key\'] )' ), 'Einbinden bietet jede Angabe in allen Formaten an' );
undt_ok( false !== strpos( $copy, "\$codes['gutenberg'] = UNDT_Dynamic_Gutenberg::markup( \$key );" ), 'Fuer den Block-Editor derselbe Absatz wie am Feld' );

/* ---------------------------------------------------------- Ergebnis ---- */

echo "\n";

if ( ! empty( $GLOBALS['undt_fails'] ) ) {
	echo "\033[31m" . (int) $GLOBALS['undt_fails'] . " Pruefungen fehlgeschlagen\033[0m\n";
	exit( 1 );
}

echo "\033[32mAlle Pruefungen bestanden\033[0m\n";
