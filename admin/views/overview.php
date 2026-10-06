<?php
/**
 * Seite: Übersicht.
 *
 * Oben ein Satz, ob alles passt, darunter, was zu tun ist, dann je Bereich eine
 * Kachel und fuer Admins die wichtigsten Bausteine. Beim ersten Start stehen
 * statt dessen die vier Schritte der Einrichtung da.
 *
 * Nur hier laufen der Inhalts-Scan der Pruefung und die Suche nach den
 * Bausteinen, beide nicht im Frontend.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

$undt_steps      = UNDT_Overview::steps();
$undt_onboarding = UNDT_Overview::show_onboarding( $undt_steps );
$undt_is_admin   = UNDT_Access::is_admin();
?>
<div class="wrap undt-wrap undt-overview">
	<?php UNDT_Ui::page_head( __( 'Übersicht', 'unternehmensdaten' ), false, false ); ?>

	<?php if ( $undt_onboarding ) : ?>
		<?php $undt_done = UNDT_Overview::steps_done( $undt_steps ); ?>

		<section class="undt-box undt-onboarding" aria-labelledby="undt-onboarding-title">
			<div class="undt-onboarding__head">
				<div class="undt-onboarding__intro">
					<h2 class="undt-onboarding__title" id="undt-onboarding-title"><?php esc_html_e( 'Richte deine Firmendaten ein', 'unternehmensdaten' ); ?></h2>
					<p class="undt-onboarding__desc"><?php esc_html_e( 'Danach erzeugt das Plugin dein Impressum und hält deine Angaben auf allen Seiten aktuell.', 'unternehmensdaten' ); ?></p>
				</div>
				<div class="undt-onboarding__aside">
					<?php $undt_percent = (int) round( 100 * $undt_done / max( 1, count( $undt_steps ) ) ); ?>
					<div class="undt-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Fortschritt der Einrichtung', 'unternehmensdaten' ); ?>" aria-valuemin="0" aria-valuemax="<?php echo (int) count( $undt_steps ); ?>" aria-valuenow="<?php echo (int) $undt_done; ?>">
						<span class="undt-progress__bar" style="width: <?php echo (int) $undt_percent; ?>%"></span>
					</div>
					<span class="undt-onboarding__count">
						<?php
						printf(
							/* translators: 1: erledigte Schritte, 2: alle Schritte. */
							esc_html__( '%1$s von %2$s erledigt', 'unternehmensdaten' ),
							'<strong>' . (int) $undt_done . '</strong>',
							(int) count( $undt_steps )
						);
						?>
					</span>
					<button type="button" class="undt-link-button" data-undt-action="onboarding" data-undt-value="skip"><?php esc_html_e( 'Einrichtung überspringen', 'unternehmensdaten' ); ?></button>
				</div>
			</div>
			<div class="undt-onboarding__steps">
				<?php UNDT_Ui::steps( $undt_steps ); ?>
			</div>
		</section>

		<?php UNDT_Ui::info( esc_html__( 'Alles, was du einträgst, bleibt in deiner WordPress-Datenbank. Das Plugin schickt keine Firmendaten an fremde Server.', 'unternehmensdaten' ), 'lock', true ); ?>

	<?php else : ?>
		<?php
		$undt_status = UNDT_Overview::status();
		$undt_todos  = UNDT_Todo::items();
		?>

		<section class="undt-box undt-statuscard">
			<?php UNDT_Ui::status( $undt_status['done'] ? 'ok' : 'open', $undt_status['title'], $undt_status['text'], '', true ); ?>

			<?php if ( $undt_status['done'] && null !== $undt_status['imprint'] ) : ?>
				<div class="undt-statuscard__aside">
					<a class="undt-btn undt-btn--secondary" href="<?php echo esc_url( $undt_status['imprint']['url'] ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Impressum ansehen', 'unternehmensdaten' ); ?>
						<?php UNDT_Ui::icon( 'out', 14 ); ?>
						<span class="screen-reader-text"><?php esc_html_e( '(öffnet in einem neuen Tab)', 'unternehmensdaten' ); ?></span>
					</a>
					<span class="undt-statuscard__meta">
						<?php
						printf(
							/* translators: 1: Titel der Seite, 2: Datum der letzten Aenderung. */
							esc_html__( 'Seite „%1$s“ · geändert am %2$s', 'unternehmensdaten' ),
							esc_html( $undt_status['imprint']['title'] ),
							esc_html( $undt_status['imprint']['modified'] )
						);
						?>
					</span>
				</div>
			<?php endif; ?>
		</section>

		<?php if ( ! empty( $undt_todos ) ) : ?>
			<section class="undt-section" aria-labelledby="undt-todo-title">
				<h2 class="undt-section__title" id="undt-todo-title">
					<?php esc_html_e( 'Zu erledigen', 'unternehmensdaten' ); ?>
					<span class="undt-section__count"><?php echo (int) count( $undt_todos ); ?></span>
				</h2>

				<div class="undt-box">
					<div class="undt-todo" data-undt-todo>
						<?php foreach ( $undt_todos as $undt_index => $undt_todo ) : ?>
							<div class="undt-todo__row"<?php echo $undt_index >= UNDT_Todo::VISIBLE ? ' hidden' : ''; ?>>
								<span class="undt-todo__dot undt-todo__dot--<?php echo esc_attr( $undt_todo['tone'] ); ?>" aria-hidden="true"></span>
								<div class="undt-todo__text">
									<div class="undt-todo__title">
										<span class="screen-reader-text"><?php echo 'open' === $undt_todo['tone'] ? esc_html__( 'Pflicht:', 'unternehmensdaten' ) : esc_html__( 'Empfohlen:', 'unternehmensdaten' ); ?> </span>
										<?php echo esc_html( $undt_todo['title'] ); ?>
										<?php if ( '' !== $undt_todo['law'] ) : ?>
											<span class="undt-todo__law"><?php echo esc_html( $undt_todo['law'] ); ?></span>
										<?php endif; ?>
									</div>
									<?php if ( '' !== $undt_todo['desc'] ) : ?>
										<div class="undt-todo__desc"><?php echo esc_html( $undt_todo['desc'] ); ?></div>
									<?php endif; ?>
								</div>
								<?php if ( ! empty( $undt_todo['suggest'] ) ) : ?>
									<?php // Aktion auf der Seite: ohne Pfeil, als Knopf. ?>
									<button type="button" class="undt-btn undt-btn--secondary" data-undt-action="suggest" data-undt-value="<?php echo esc_attr( $undt_todo['suggest'] ); ?>"><?php echo esc_html( $undt_todo['action'] ); ?></button>
								<?php elseif ( '' !== $undt_todo['url'] && '' !== $undt_todo['action'] ) : ?>
									<a class="undt-action" href="<?php echo esc_url( $undt_todo['url'] ); ?>"><?php echo esc_html( $undt_todo['action'] ); ?><?php UNDT_Ui::icon( 'caret', 13 ); ?></a>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>

						<?php if ( count( $undt_todos ) > UNDT_Todo::VISIBLE ) : ?>
							<div class="undt-todo__more">
								<button type="button" class="undt-link-button" data-undt-todo-more>
									<?php
									printf(
										/* translators: %d: Anzahl aller Punkte. */
										esc_html__( 'Alle %d anzeigen', 'unternehmensdaten' ),
										(int) count( $undt_todos )
									);
									?>
								</button>
							</div>
						<?php endif; ?>
					</div>

					<?php UNDT_Ui::details_open( __( 'Was geprüft wird', 'unternehmensdaten' ), '', false ); ?>
						<ul class="undt-list">
							<li><?php esc_html_e( 'Alle Pflichtangaben nach § 5 DDG, soweit sie sich aus deiner Rechtsform ergeben.', 'unternehmensdaten' ); ?></li>
							<li><?php esc_html_e( 'Der zweite Kontaktweg neben der E-Mail-Adresse.', 'unternehmensdaten' ); ?></li>
							<li><?php esc_html_e( 'Berufsrechtliche Angaben bei reglementierten Berufen, einschließlich der Angabe, wo die Regelungen zugänglich sind.', 'unternehmensdaten' ); ?></li>
							<li><?php esc_html_e( 'Angaben zur Berufshaftpflichtversicherung, sofern eine besteht.', 'unternehmensdaten' ); ?></li>
							<li><?php esc_html_e( 'Die verantwortliche Person bei redaktionellen Inhalten.', 'unternehmensdaten' ); ?></li>
							<li><?php esc_html_e( 'Der Hinweis zur Verbraucherstreitbeilegung und die Erklärung zur Barrierefreiheit.', 'unternehmensdaten' ); ?></li>
							<li><?php esc_html_e( 'Ob das Impressum auf der richtigen Seite steht, und veraltete Rechtsgrundlagen auf deinen Rechtsseiten.', 'unternehmensdaten' ); ?></li>
						</ul>
					<?php UNDT_Ui::details_close(); ?>

					<?php UNDT_Ui::card_foot( __( 'Die Prüfung ist eine Checkliste, keine Rechtsberatung. Rechtsstand der Hinweise: September 2026.', 'unternehmensdaten' ) ); ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="undt-section" aria-labelledby="undt-tiles-title">
			<h2 class="undt-section__title" id="undt-tiles-title"><?php esc_html_e( 'Deine Angaben', 'unternehmensdaten' ); ?></h2>

			<div class="undt-tiles">
				<?php foreach ( UNDT_Overview::tiles() as $undt_tile ) : ?>
					<?php UNDT_Ui::link_card( $undt_tile ); ?>
				<?php endforeach; ?>

				<?php $undt_more = UNDT_Overview::more_modules(); ?>
				<?php if ( ! empty( $undt_more ) ) : ?>
					<div class="undt-morecard">
						<div class="undt-morecard__title"><?php esc_html_e( 'Weitere Bereiche', 'unternehmensdaten' ); ?></div>
						<p class="undt-morecard__desc"><?php esc_html_e( 'Nur bei Bedarf. Abgeschaltete Bereiche laden nichts auf deiner Website.', 'unternehmensdaten' ); ?></p>
						<div class="undt-morecard__actions">
							<?php foreach ( $undt_more as $undt_slug_more => $undt_title_more ) : ?>
								<button type="button" class="undt-btn undt-btn--secondary undt-btn--small" data-undt-action="module" data-undt-value="<?php echo esc_attr( $undt_slug_more ); ?>">
									<?php UNDT_Ui::icon( 'plus', 12 ); ?>
									<?php echo esc_html( $undt_title_more ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<?php if ( $undt_is_admin ) : ?>
			<section class="undt-section" aria-labelledby="undt-embed-title">
				<div class="undt-section__head">
					<h2 class="undt-section__title" id="undt-embed-title"><?php esc_html_e( 'Einbinden', 'unternehmensdaten' ); ?></h2>
					<a class="undt-action" href="<?php echo esc_url( admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_EMBED ) ); ?>"><?php esc_html_e( 'Alle Bausteine', 'unternehmensdaten' ); ?><?php UNDT_Ui::icon( 'caret', 13 ); ?></a>
				</div>

				<div class="undt-box">
					<?php foreach ( UNDT_Overview::embeds() as $undt_embed ) : ?>
						<div class="undt-embed">
							<div class="undt-embed__text">
								<div class="undt-embed__title"><?php echo esc_html( $undt_embed['title'] ); ?></div>
								<div class="undt-embed__desc"><?php echo esc_html( $undt_embed['desc'] ); ?></div>
							</div>
							<div class="undt-embed__usage<?php echo $undt_embed['unused'] ? ' undt-embed__usage--unused' : ''; ?>">
								<?php if ( $undt_embed['unused'] ) : ?>
									<span class="undt-dot-s" aria-hidden="true"></span>
								<?php endif; ?>
								<?php echo esc_html( $undt_embed['usage'] ); ?>
							</div>
							<div class="undt-embed__code">
								<button type="button" class="undt-copy undt-chip" data-undt-copy="<?php echo esc_attr( $undt_embed['code'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: Baustein, 2: Shortcode. */ __( '%1$s kopieren: %2$s', 'unternehmensdaten' ), $undt_embed['title'], $undt_embed['code'] ) ); ?>">
									<code class="undt-chip__code"><?php echo esc_html( $undt_embed['code'] ); ?></code>
									<span class="undt-chip__label undt-chip__label--idle"><?php UNDT_Ui::icon( 'copy', 14 ); ?><?php esc_html_e( 'Kopieren', 'unternehmensdaten' ); ?></span>
									<span class="undt-chip__label undt-chip__label--done"><?php UNDT_Ui::icon( 'check', 14 ); ?><?php esc_html_e( 'Kopiert', 'unternehmensdaten' ); ?></span>
								</button>
							</div>
						</div>
					<?php endforeach; ?>

					<?php UNDT_Ui::card_foot( __( 'Einzelne Angaben wie Telefon oder Anschrift kopierst du direkt in Firma, im Format aus den Schnelleinstellungen.', 'unternehmensdaten' ) ); ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</div>
