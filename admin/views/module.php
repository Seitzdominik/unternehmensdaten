<?php
/**
 * Seite: ein Inhaltsbereich.
 *
 * Aufbau und Inhalt liefert UNDT_Module_View, je Bereich nach dem Entwurf.
 *
 * Erwartet die Variable $undt_slug aus UNDT_Admin::render().
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

$undt_module = isset( $undt_slug ) ? UNDT_Modules::get( $undt_slug ) : null;

if ( null === $undt_module ) {
	return;
}

$undt_pages = UNDT_Nav::pages();
$undt_title = isset( $undt_pages[ 'm_' . $undt_slug ] ) ? $undt_pages[ 'm_' . $undt_slug ]['title'] : $undt_module['label'];
?>
<div class="wrap undt-wrap undt-module-page undt-module-<?php echo esc_attr( $undt_slug ); ?>">
	<?php UNDT_Ui::page_head( $undt_title, true ); ?>

	<?php settings_errors(); ?>

	<?php UNDT_Module_View::render( $undt_slug ); ?>
</div>
