<?php
/**
 * Seite: Firma.
 *
 * Oben die Rechtsform als Karte, darunter alle Angaben in Registerkarten. Alle
 * Karten stehen in einem einzigen Formular und werden im Browser umgeschaltet:
 * gespeichert wird immer der vollstaendige Datensatz. Aufbau und Inhalt
 * liefert UNDT_Company_View.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap undt-wrap undt-company-page">
	<?php UNDT_Ui::page_head( __( 'Firma', 'unternehmensdaten' ), true ); ?>

	<?php settings_errors(); ?>

	<?php UNDT_Company_View::legal_form_card(); ?>

	<?php UNDT_Company_View::form(); ?>
</div>
