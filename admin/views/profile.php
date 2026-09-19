<?php
/**
 * Seite: Rechtsform und Umfang.
 *
 * Der Einrichtungsassistent. Die Antworten hier entscheiden, welche Felder auf
 * der Stammdatenseite ueberhaupt erscheinen und welche Angaben die Pruefung als
 * Pflicht behandelt.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

$undt_profile   = UNDT_Store::profile();
$undt_questions = UNDT_Schema::profile_questions();
$undt_option    = UNDT_Store::OPTION_PROFILE;

// Als eingerichtet gilt das Plugin erst nach dem Speichern, nicht schon beim
// Aufruf dieser Seite. Siehe UNDT_Admin::sanitize_profile().
?>
<div class="wrap undt-wrap">
	<h1><?php esc_html_e( 'Rechtsform & Umfang', 'unternehmensdaten' ); ?></h1>

	<p class="undt-context">
		<?php esc_html_e( 'Diese Angaben steuern, welche Pflichtinformationen für dieses Unternehmen gelten. Ein Einzelunternehmen braucht andere Angaben als eine GmbH, und eine Arztpraxis wiederum andere als beide.', 'unternehmensdaten' ); ?>
	</p>

	<?php settings_errors(); ?>

	<div class="notice notice-warning inline undt-disclaimer">
		<p>
			<strong><?php esc_html_e( 'Keine Rechtsberatung.', 'unternehmensdaten' ); ?></strong>
			<?php esc_html_e( 'Dieses Plugin verwaltet Angaben und gibt sie strukturiert aus. Ob die Angaben im konkreten Fall vollständig und richtig sind, kann nur eine rechtliche Prüfung klären. Der Rechtsstand der hinterlegten Hinweise ist September 2026.', 'unternehmensdaten' ); ?>
		</p>
	</div>

	<form method="post" action="options.php" class="undt-form">
		<?php settings_fields( UNDT_Admin::GROUP_PROFILE ); ?>

		<div class="undt-box">
			<div class="undt-box__body">
				<div class="undt-cards">
					<?php
					foreach ( $undt_questions as $undt_key => $undt_question ) {
						$undt_value = isset( $undt_profile[ $undt_key ] ) ? $undt_profile[ $undt_key ] : '';

						UNDT_Fields::question_card( $undt_key, $undt_question, $undt_value, $undt_option );
					}
					?>
				</div>
			</div>

			<div class="undt-box__footer">
				<?php submit_button( __( 'Speichern und Felder anpassen', 'unternehmensdaten' ), 'primary', 'submit', false ); ?>
			</div>
		</div>
	</form>
</div>
