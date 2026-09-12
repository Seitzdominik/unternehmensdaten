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

// Der Hinweis im Backend hat seinen Zweck erfuellt, sobald diese Seite offen ist.
if ( ! UNDT_Store::is_set_up() ) {
	UNDT_Store::mark_set_up();
}
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

		<table class="form-table" role="presentation">
			<tbody>
				<?php
				foreach ( $undt_questions as $undt_key => $undt_question ) {
					$undt_question = array_merge(
						array(
							'label'     => $undt_key,
							'type'      => 'checkbox',
							'choices'   => array(),
							'help'      => '',
							'basis'     => '',
							'when'      => array(),
							'required'  => false,
							'shortcode' => false,
							'default'   => '',
						),
						$undt_question
					);

					$undt_value = isset( $undt_profile[ $undt_key ] ) ? $undt_profile[ $undt_key ] : '';

					// Abhaengige Fragen werden per JavaScript ein- und ausgeblendet.
					$undt_attr = '';

					if ( ! empty( $undt_question['when'] ) ) {
						$undt_attr = ' data-undt-when="' . esc_attr( (string) wp_json_encode( $undt_question['when'] ) ) . '"';
					}

					echo '<tr class="undt-row undt-question"' . $undt_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits escaped.
					echo '<th scope="row"><label for="undt-' . esc_attr( $undt_key ) . '">' . esc_html( $undt_question['label'] ) . '</label>';

					// Das Fragezeichen gehoert neben die Frage, die Fundstelle darunter.
					UNDT_Fields::help( $undt_key, $undt_question );

					if ( '' !== $undt_question['basis'] ) {
						echo '<span class="undt-basis">' . esc_html( $undt_question['basis'] ) . '</span>';
					}

					echo '</th><td>';

					UNDT_Fields::control( $undt_key, $undt_question, $undt_value, $undt_option . '[' . $undt_key . ']', 'undt-' . $undt_key );

					echo '</td></tr>';
				}
				?>
			</tbody>
		</table>

		<?php submit_button( __( 'Speichern und Felder anpassen', 'unternehmensdaten' ) ); ?>
	</form>
</div>
