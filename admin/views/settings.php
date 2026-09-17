<?php
/**
 * Seite: Einstellungen.
 *
 * Steuert, welche Bereiche im Menue erscheinen, und was beim Deinstallieren mit
 * den Daten geschieht.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

$undt_settings = UNDT_Modules::settings();
$undt_option   = UNDT_Modules::OPTION_SETTINGS;
?>
<div class="wrap undt-wrap">
	<h1><?php esc_html_e( 'Einstellungen', 'unternehmensdaten' ); ?></h1>

	<?php settings_errors(); ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeige nach dem Redirect. ?>
	<?php if ( isset( $_GET['undt-checked'] ) ) : ?>
		<div class="notice notice-success is-dismissible inline">
			<p><?php esc_html_e( 'Die Suche nach Aktualisierungen wurde durchgeführt.', 'unternehmensdaten' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php" class="undt-form">
		<?php settings_fields( UNDT_Admin::GROUP_SETTINGS ); ?>

		<h2 class="undt-section-title"><?php esc_html_e( 'Sichtbare Bereiche', 'unternehmensdaten' ); ?></h2>
		<p class="description undt-section-hint">
			<?php esc_html_e( 'Abgeschaltete Bereiche verschwinden aus dem Menü, ihre Shortcodes geben nichts mehr aus und ihr CSS entfällt. Die eingetragenen Daten bleiben erhalten und sind nach dem Wiedereinschalten unverändert da.', 'unternehmensdaten' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ( UNDT_Modules::all() as $undt_slug => $undt_module ) : ?>
					<tr class="undt-row">
						<th scope="row">
							<label for="undt-module-<?php echo esc_attr( $undt_slug ); ?>">
								<?php echo esc_html( $undt_module['label'] ); ?>
							</label>
						</th>
						<td>
							<?php
							UNDT_Fields::toggle(
								$undt_option . '[modules][' . $undt_slug . ']',
								'undt-module-' . $undt_slug,
								! empty( $undt_settings['modules'][ $undt_slug ] )
							);
							?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: Name der Option in der Datenbank. */
									esc_html__( 'Daten liegen in der Option %s.', 'unternehmensdaten' ),
									'<code>' . esc_html( $undt_module['option'] ) . '</code>'
								);

								echo ' ';

								echo $undt_module['autoload']
									? esc_html__( 'Wird mit den Autoload-Optionen geladen, weil der Inhalt üblicherweise auf jeder Seite gebraucht wird.', 'unternehmensdaten' )
									: esc_html__( 'Wird nur auf Seiten geladen, die den Inhalt tatsächlich ausgeben.', 'unternehmensdaten' );
								?>
							</p>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2 class="undt-section-title"><?php esc_html_e( 'Aktualisierungen', 'unternehmensdaten' ); ?></h2>

		<?php $undt_status = UNDT_Updater::status(); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr class="undt-row">
					<th scope="row">
						<label for="undt-updates"><?php esc_html_e( 'Auf GitHub nach Aktualisierungen suchen', 'unternehmensdaten' ); ?></label>
					</th>
					<td>
						<?php
						UNDT_Fields::toggle(
							$undt_option . '[updates]',
							'undt-updates',
							! empty( $undt_settings['updates'] )
						);
						?>
						<p class="description">
							<?php esc_html_e( 'Meldet neue Fassungen im gewohnten Plugin-Bildschirm. Abgerufen wird ausschließlich eine kleine Textdatei am neuesten Release, keine Daten werden übertragen.', 'unternehmensdaten' ); ?>
						</p>
					</td>
				</tr>

				<tr class="undt-row">
					<th scope="row"><?php esc_html_e( 'Stand', 'unternehmensdaten' ); ?></th>
					<td>
						<p class="undt-update-state undt-update-state--<?php echo esc_attr( $undt_status['state'] ); ?>">
							<span class="undt-dot undt-dot--<?php echo esc_attr( 'update' === $undt_status['state'] ? 'warning' : ( 'current' === $undt_status['state'] ? 'ok' : 'notice' ) ); ?>" aria-hidden="true"></span>
							<?php
							switch ( $undt_status['state'] ) {
								case 'update':
									printf(
										/* translators: 1: verfügbare Version, 2: installierte Version. */
										esc_html__( 'Version %1$s steht bereit, installiert ist %2$s.', 'unternehmensdaten' ),
										'<strong>' . esc_html( $undt_status['version'] ) . '</strong>',
										esc_html( UNDT_VERSION )
									);
									break;

								case 'current':
									printf(
										/* translators: %s: installierte Version. */
										esc_html__( 'Version %s ist die neueste.', 'unternehmensdaten' ),
										'<strong>' . esc_html( UNDT_VERSION ) . '</strong>'
									);
									break;

								default:
									echo esc_html( $undt_status['message'] );
							}
							?>
						</p>

						<?php if ( ! empty( $undt_status['repo'] ) ) : ?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: Repository als inhaber/name. */
									esc_html__( 'Repository: %s', 'unternehmensdaten' ),
									'<a href="' . esc_url( 'https://github.com/' . $undt_status['repo'] ) . '"><code>' . esc_html( $undt_status['repo'] ) . '</code></a>'
								);

								if ( ! empty( $undt_status['checked'] ) ) {
									echo ' &middot; ';
									printf(
										/* translators: %s: Zeitspanne, etwa „5 Minuten“. */
										esc_html__( 'zuletzt geprüft vor %s', 'unternehmensdaten' ),
										esc_html( human_time_diff( (int) $undt_status['checked'] ) )
									);
								}
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<h2 class="undt-section-title"><?php esc_html_e( 'Deinstallation', 'unternehmensdaten' ); ?></h2>

		<table class="form-table" role="presentation">
			<tbody>
				<tr class="undt-row">
					<th scope="row">
						<label for="undt-keep-data"><?php esc_html_e( 'Daten beim Löschen behalten', 'unternehmensdaten' ); ?></label>
					</th>
					<td>
						<?php
						UNDT_Fields::toggle(
							$undt_option . '[keep_data]',
							'undt-keep-data',
							! empty( $undt_settings['keep_data'] )
						);
						?>
						<p class="description">
							<?php esc_html_e( 'Standardmäßig entfernt das Plugin beim Löschen alle eigenen Optionen restlos. Wer das Plugin nur vorübergehend löscht oder auf eine andere Version wechselt, setzt hier den Haken. Ein bloßes Deaktivieren löscht ohnehin nichts.', 'unternehmensdaten' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Änderungen speichern', 'unternehmensdaten' ) ); ?>
	</form>

	<?php if ( UNDT_Updater::enabled() && '' !== UNDT_Updater::repo() ) : ?>
		<?php
		/*
		 * Eigenes Formular, weil Formulare sich nicht verschachteln lassen. Der
		 * Knopf verwirft den Zwischenspeicher und fragt sofort neu an.
		 */
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="undt-recheck">
			<input type="hidden" name="action" value="undt_check_update" />
			<?php wp_nonce_field( 'undt_check_update' ); ?>
			<?php submit_button( __( 'Jetzt nach Aktualisierungen suchen', 'unternehmensdaten' ), 'secondary', 'submit', false ); ?>
		</form>
	<?php endif; ?>

	<h2 class="undt-section-title"><?php esc_html_e( 'Für Entwicklerinnen und Entwickler', 'unternehmensdaten' ); ?></h2>
	<p class="description undt-section-hint">
		<?php esc_html_e( 'Diese Filter stehen bereit:', 'unternehmensdaten' ); ?>
	</p>
	<table class="widefat striped undt-table">
		<tbody>
			<tr><td><code>undt_fields</code></td><td><?php esc_html_e( 'Eigene Stammdaten-Felder ergänzen. Sie durchlaufen automatisch Sanitisierung, Escaping und Shortcode-Auflösung.', 'unternehmensdaten' ); ?></td></tr>
			<tr><td><code>undt_modules</code></td><td><?php esc_html_e( 'Eigene Inhaltsmodule ergänzen.', 'unternehmensdaten' ); ?></td></tr>
			<tr><td><code>undt_capability</code></td><td><?php esc_html_e( 'Erforderliche Berechtigung ändern. Standard ist manage_options.', 'unternehmensdaten' ); ?></td></tr>
			<tr><td><code>undt_css</code></td><td><?php esc_html_e( 'Das strukturelle CSS anpassen.', 'unternehmensdaten' ); ?></td></tr>
			<tr><td><code>undt_inline_css</code></td><td><?php esc_html_e( 'Das mitgelieferte CSS vollständig abschalten.', 'unternehmensdaten' ); ?></td></tr>
			<tr><td><code>undt_imprint_html</code>, <code>undt_footer_html</code></td><td><?php esc_html_e( 'Die fertige Ausgabe nachbearbeiten.', 'unternehmensdaten' ); ?></td></tr>
			<tr><td><code>undt_schema_organization</code></td><td><?php esc_html_e( 'Die JSON-LD-Auszeichnung anpassen.', 'unternehmensdaten' ); ?></td></tr>
			<tr><td><code>undt_audit_issues</code></td><td><?php esc_html_e( 'Eigene Prüfungen ergänzen.', 'unternehmensdaten' ); ?></td></tr>
			<tr><td><code>undt_auto_banner</code></td><td><?php esc_html_e( 'Festlegen, wo das Banner automatisch am Seitenanfang erscheint. Standard ist überall außer in der Oberfläche von Etch und Bricks.', 'unternehmensdaten' ); ?></td></tr>
			<tr><td><code>undt_social_icon</code></td><td><?php esc_html_e( 'Das Symbol einer Plattform ersetzen, etwa für Xing oder kununu, die WordPress nicht mitbringt.', 'unternehmensdaten' ); ?></td></tr>
			<tr><td><code>undt_link_post_types</code></td><td><?php esc_html_e( 'Festlegen, aus welchen Inhaltstypen die Seitenfelder wählen. Standard sind Seiten und eigene Inhaltstypen, die in Menüs erscheinen dürfen.', 'unternehmensdaten' ); ?></td></tr>
		</tbody>
	</table>
</div>
