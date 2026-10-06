<?php
/**
 * Seite: Einstellungen.
 *
 * Bereiche, wer pflegen darf, Aktualisierungen und was beim Loeschen des
 * Plugins mit den Angaben geschieht. Optionsnamen und Autoload stehen seit
 * 0.6.0 nicht mehr auf der Seite, sie gehoeren in die Entwickler-Doku.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

$undt_settings = UNDT_Modules::settings();
$undt_option   = UNDT_Modules::OPTION_SETTINGS;
$undt_roles    = UNDT_Access::choosable_roles();
$undt_status   = UNDT_Updater::status();
$undt_areas    = array(
	'hours'  => array( 'clock', __( 'Reguläre Zeiten, Feiertage und „Heute geöffnet“', 'unternehmensdaten' ) ),
	'prices' => array( 'tag', __( 'Preislisten mit Gruppen', 'unternehmensdaten' ) ),
	'social' => array( 'share', __( 'Profile für Footer und Suchmaschinen', 'unternehmensdaten' ) ),
	'faq'    => array( 'chat', __( 'Häufige Fragen als aufklappbare Liste', 'unternehmensdaten' ) ),
	'banner' => array( 'megaphone', __( 'Ein Hinweis oben auf jeder Seite', 'unternehmensdaten' ) ),
	'seo'    => array( 'search', __( 'Strukturierte Daten für Google & Co.', 'unternehmensdaten' ) ),
);
?>
<div class="wrap undt-wrap undt-settings-page">
	<?php UNDT_Ui::page_head( __( 'Einstellungen', 'unternehmensdaten' ), true ); ?>

	<?php settings_errors(); ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeige nach dem Redirect. ?>
	<?php if ( isset( $_GET['undt-checked'] ) ) : ?>
		<?php UNDT_Ui::info( esc_html__( 'Die Suche nach Aktualisierungen wurde durchgeführt.', 'unternehmensdaten' ), 'check' ); ?>
	<?php endif; ?>

	<form method="post" action="options.php" class="undt-form undt-mpage" data-undt-autosave="settings">
		<?php settings_fields( UNDT_Admin::GROUP_SETTINGS ); ?>

		<section class="undt-box">
			<?php UNDT_Ui::card_head( __( 'Bereiche', 'unternehmensdaten' ), __( 'Schalte nur ein, was du brauchst.', 'unternehmensdaten' ) ); ?>

			<div class="undt-arealist">
				<?php foreach ( UNDT_Modules::all() as $undt_slug => $undt_module ) : ?>
					<?php
					$undt_on   = ! empty( $undt_settings['modules'][ $undt_slug ] );
					$undt_area = isset( $undt_areas[ $undt_slug ] ) ? $undt_areas[ $undt_slug ] : array( 'grid', '' );
					$undt_id   = 'undt-module-' . $undt_slug;
					?>
					<div class="undt-arealist__row">
						<?php UNDT_Ui::tile( $undt_area[0], 0, $undt_on ? 'brand' : 'muted' ); ?>
						<label class="undt-arealist__text" for="<?php echo esc_attr( $undt_id ); ?>">
							<span class="undt-arealist__label"><?php echo esc_html( $undt_module['label'] ); ?></span>
							<?php if ( '' !== $undt_area[1] ) : ?>
								<span class="undt-arealist__desc"><?php echo esc_html( $undt_area[1] ); ?></span>
							<?php endif; ?>
						</label>
						<?php UNDT_Controls::toggle( $undt_option . '[modules][' . $undt_slug . ']', $undt_id, $undt_on ); ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php UNDT_Ui::card_foot( __( 'Abgeschaltete Bereiche verschwinden aus dem Menü und laden nichts auf deiner Website. Was du eingetragen hast, bleibt erhalten.', 'unternehmensdaten' ) ); ?>
		</section>

		<section class="undt-box">
			<?php UNDT_Ui::card_head( __( 'Wer darf die Angaben pflegen?', 'unternehmensdaten' ), __( 'Etwa deine Kundin oder ein Mitarbeiter, ohne Zugriff auf die Technik.', 'unternehmensdaten' ), null, 'undt-roles-title' ); ?>

			<div class="undt-box__section">
				<fieldset class="undt-roles" aria-labelledby="undt-roles-title">
					<label class="undt-roles__item">
						<input type="checkbox" checked disabled />
						<span class="undt-roles__name"><?php echo esc_html( translate_user_role( 'Administrator' ) ); ?></span>
						<span class="undt-roles__note"><?php esc_html_e( 'Immer, mit allen Rechten', 'unternehmensdaten' ); ?></span>
					</label>
					<?php foreach ( $undt_roles as $undt_role => $undt_role_name ) : ?>
						<label class="undt-roles__item">
							<input type="hidden" name="<?php echo esc_attr( $undt_option . '[roles][' . $undt_role . ']' ); ?>" value="0" />
							<input type="checkbox" name="<?php echo esc_attr( $undt_option . '[roles][' . $undt_role . ']' ); ?>" value="1"<?php checked( in_array( $undt_role, (array) $undt_settings['roles'], true ) ); ?> />
							<span class="undt-roles__name"><?php echo esc_html( $undt_role_name ); ?></span>
							<?php if ( 'shop_manager' === $undt_role ) : ?>
								<span class="undt-roles__note"><?php esc_html_e( 'von WooCommerce', 'unternehmensdaten' ); ?></span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<?php
				UNDT_Ui::info(
					esc_html__( 'Diese Rollen sehen nur Übersicht, Firma, Öffnungszeiten, Social Media und Infobanner, dazu Preise und FAQ, wenn sie eingeschaltet sind. Kopierknöpfe, Rechtsform, Einbinden, Suchmaschinen und Einstellungen bleiben Admins vorbehalten.', 'unternehmensdaten' ),
					'lock'
				);
				?>
			</div>
		</section>

		<section class="undt-box">
			<?php UNDT_Ui::card_head( __( 'Aktualisierungen', 'unternehmensdaten' ) ); ?>

			<div class="undt-box__section undt-update">
				<p class="undt-update__state">
					<?php
					switch ( $undt_status['state'] ) {
						case 'update':
							printf(
								/* translators: 1: verfügbare Version, 2: installierte Version. */
								esc_html__( '%1$s Installiert ist %2$s, du aktualisierst wie gewohnt unter Plugins.', 'unternehmensdaten' ),
								'<strong>' . esc_html( sprintf( /* translators: %s: Version. */ __( 'Version %s steht bereit.', 'unternehmensdaten' ), $undt_status['version'] ) ) . '</strong>',
								esc_html( UNDT_VERSION )
							);
							break;

						case 'current':
							printf(
								'<strong>%s</strong>',
								esc_html( sprintf( /* translators: %s: installierte Version. */ __( 'Version %s ist die neueste.', 'unternehmensdaten' ), UNDT_VERSION ) )
							);
							break;

						default:
							echo esc_html( $undt_status['message'] );
					}

					if ( ! empty( $undt_status['checked'] ) ) {
						echo ' <span class="undt-update__checked">';
						printf(
							/* translators: %s: Zeitspanne, etwa „5 Minuten“. */
							esc_html__( 'Zuletzt geprüft vor %s.', 'unternehmensdaten' ),
							esc_html( human_time_diff( (int) $undt_status['checked'] ) )
						);
						echo '</span>';
					}
					?>
				</p>

				<?php if ( UNDT_Updater::enabled() && '' !== UNDT_Updater::repo() ) : ?>
					<?php // Gehoert zum Formular darunter, Formulare lassen sich nicht verschachteln. ?>
					<button type="submit" form="undt-recheck" class="undt-btn undt-btn--secondary undt-btn--small"><?php esc_html_e( 'Jetzt prüfen', 'unternehmensdaten' ); ?></button>
				<?php endif; ?>
			</div>

			<div class="undt-box__section">
				<div class="undt-srow-set">
					<label class="undt-srow-set__label" for="undt-updates"><?php esc_html_e( 'Automatisch nach neuen Versionen suchen', 'unternehmensdaten' ); ?></label>
					<?php UNDT_Controls::toggle( $undt_option . '[updates]', 'undt-updates', ! empty( $undt_settings['updates'] ) ); ?>
				</div>
			</div>

			<?php UNDT_Ui::card_foot( __( 'Neue Versionen erscheinen wie gewohnt unter Plugins. Abgefragt wird nur eine kleine Textdatei bei GitHub, Angaben von dir werden nicht übertragen.', 'unternehmensdaten' ) ); ?>
		</section>

		<section class="undt-box">
			<?php UNDT_Ui::card_head( __( 'Wenn du das Plugin löschst', 'unternehmensdaten' ) ); ?>

			<div class="undt-box__section">
				<div class="undt-choices" role="radiogroup" aria-label="<?php esc_attr_e( 'Beim Löschen', 'unternehmensdaten' ); ?>">
					<?php foreach ( array( '0' => array( __( 'Alle Angaben entfernen', 'unternehmensdaten' ), __( 'Standard. Nach dem Löschen bleibt nichts in der Datenbank zurück.', 'unternehmensdaten' ) ), '1' => array( __( 'Angaben behalten', 'unternehmensdaten' ), __( 'Sinnvoll, wenn du das Plugin nur kurz entfernst oder neu installierst.', 'unternehmensdaten' ) ) ) as $undt_value => $undt_choice ) : ?>
						<label class="undt-choice" for="undt-keep-<?php echo esc_attr( (string) $undt_value ); ?>">
							<input type="radio" id="undt-keep-<?php echo esc_attr( (string) $undt_value ); ?>" name="<?php echo esc_attr( $undt_option . '[keep_data]' ); ?>" value="<?php echo esc_attr( (string) $undt_value ); ?>"<?php checked( (int) $undt_value, empty( $undt_settings['keep_data'] ) ? 0 : 1 ); ?> />
							<span class="undt-choice__text">
								<span class="undt-choice__label"><?php echo esc_html( $undt_choice[0] ); ?></span>
								<span class="undt-choice__desc"><?php echo esc_html( $undt_choice[1] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="undt-update undt-update--info">
					<?php UNDT_Ui::info( esc_html__( 'Deaktivieren löscht nie etwas. Es geht nur um das endgültige Löschen unter Plugins.', 'unternehmensdaten' ), 'info' ); ?>
					<a class="undt-action" href="<?php echo esc_url( admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_TRANSFER ) ); ?>"><?php esc_html_e( 'Vorher eine Sicherung laden', 'unternehmensdaten' ); ?><?php UNDT_Ui::icon( 'caret', 12 ); ?></a>
				</div>
			</div>
		</section>

		<?php UNDT_Ui::noscript_submit(); ?>
	</form>

	<?php if ( UNDT_Updater::enabled() && '' !== UNDT_Updater::repo() ) : ?>
		<?php // Der Knopf „Jetzt prüfen“ oben schickt dieses Formular ab. ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="undt-recheck" class="undt-recheck" hidden>
			<input type="hidden" name="action" value="undt_check_update" />
			<?php wp_nonce_field( 'undt_check_update' ); ?>
		</form>
	<?php endif; ?>
</div>
