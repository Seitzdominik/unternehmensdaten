<?php
/**
 * Seite: Sichern und übertragen.
 *
 * Herunterladen, und Einspielen in drei Schritten: Datei waehlen, Bereiche
 * auswaehlen, einspielen. Die Schritte laufen ueber admin.js und
 * UNDT_Transfer (admin-ajax). Ohne JavaScript schickt das Formular die Datei
 * wie bisher ab und spielt alle Bereiche ein, nach derselben Sicherung.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

// Ergebnis eines Imports ohne JavaScript, einmalig nach der Weiterleitung.
$undt_report = UNDT_Transfer::take_report();
$undt_backup = UNDT_Transfer::last_backup();
?>
<div class="wrap undt-wrap undt-transfer-page">
	<?php UNDT_Ui::page_head( __( 'Sichern & Übertragen', 'unternehmensdaten' ) ); ?>

	<p class="undt-intro"><?php esc_html_e( 'Nimm deine Angaben auf eine andere Website mit, oder sichere sie, bevor du größere Änderungen machst.', 'unternehmensdaten' ); ?></p>

	<?php if ( ! empty( $undt_report ) ) : ?>
		<?php if ( isset( $undt_report['error'] ) ) : ?>
			<?php UNDT_Ui::amber( UNDT_Transfer::summary( $undt_report ) ); ?>
		<?php else : ?>
			<?php
			$undt_relink = UNDT_Transfer::relink_url( $undt_report );
			UNDT_Ui::info(
				esc_html( UNDT_Transfer::summary( $undt_report ) )
					. ( '' === $undt_relink ? '' : ' <a href="' . esc_url( $undt_relink ) . '">' . esc_html__( 'Seiten neu wählen', 'unternehmensdaten' ) . '</a>' ),
				'check'
			);
			?>
		<?php endif; ?>
	<?php endif; ?>

	<section class="undt-box">
		<div class="undt-box__head undt-thead">
			<?php UNDT_Ui::tile( 'download', 0, 'brand' ); ?>
			<div class="undt-box__heading">
				<h2 class="undt-box__title"><?php esc_html_e( 'Sicherung herunterladen', 'unternehmensdaten' ); ?></h2>
				<p class="undt-box__desc"><?php esc_html_e( 'Eine Datei mit allen Angaben: Firma, Rechtsform, Bereiche und Einstellungen.', 'unternehmensdaten' ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="undt-box__aside">
				<input type="hidden" name="action" value="undt_export" />
				<?php wp_nonce_field( 'undt_export' ); ?>
				<button type="submit" class="undt-btn"><?php UNDT_Ui::icon( 'download', 14 ); ?><?php esc_html_e( 'Datei herunterladen', 'unternehmensdaten' ); ?></button>
			</form>
		</div>
		<div class="undt-box__section">
			<?php UNDT_Ui::info( esc_html__( 'Enthält keine Passwörter und keine Angaben über Besucher.', 'unternehmensdaten' ), 'lock' ); ?>
		</div>
		<?php UNDT_Ui::card_foot( __( 'Verknüpfte Seiten und Bilder bleiben außen vor, sie gehören zu dieser Website.', 'unternehmensdaten' ) ); ?>
	</section>

	<section class="undt-box" data-undt-import data-nonce="<?php echo esc_attr( wp_create_nonce( UNDT_Transfer::NONCE ) ); ?>" data-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<div class="undt-box__head undt-thead">
			<?php UNDT_Ui::tile( 'upload', 0, 'brand' ); ?>
			<div class="undt-box__heading">
				<h2 class="undt-box__title"><?php esc_html_e( 'Sicherung einspielen', 'unternehmensdaten' ); ?></h2>
				<p class="undt-box__desc"><?php esc_html_e( 'Du siehst vorher genau, was sich ändert.', 'unternehmensdaten' ); ?></p>
			</div>
		</div>

		<div class="undt-box__section undt-idone" data-undt-import-done<?php echo empty( $undt_backup ) ? ' hidden' : ''; ?>>
			<div class="undt-idone__text">
				<strong data-undt-import-title>
					<?php
					echo esc_html(
						empty( $undt_backup )
							? ''
							: sprintf(
								/* translators: 1: Datum, 2: Uhrzeit. */
								__( 'Zuletzt eingespielt am %1$s um %2$s Uhr.', 'unternehmensdaten' ),
								wp_date( 'j.n.Y', (int) $undt_backup['time'] ),
								wp_date( 'H:i', (int) $undt_backup['time'] )
							)
					);
					?>
				</strong>
				<span class="undt-idone__more"><?php esc_html_e( 'Den Stand davor haben wir automatisch gesichert.', 'unternehmensdaten' ); ?></span>
				<span class="undt-idone__more" data-undt-import-dropped hidden><span data-undt-import-summary></span> <a href="#" data-undt-import-relink><?php esc_html_e( 'Seiten neu wählen', 'unternehmensdaten' ); ?></a></span>
			</div>
			<button type="button" class="undt-btn undt-btn--secondary undt-btn--small" data-undt-import-undo><?php UNDT_Ui::icon( 'undo', 14 ); ?><?php esc_html_e( 'Rückgängig machen', 'unternehmensdaten' ); ?></button>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="undt-box__section undt-iform" data-undt-import-form>
			<input type="hidden" name="action" value="undt_import" />
			<?php wp_nonce_field( UNDT_Transfer::NONCE ); ?>

			<ol class="undt-steps undt-isteps">
				<li class="undt-steps__item undt-steps__item--current" data-undt-istep="1">
					<span class="undt-steps__circle"><span class="undt-isteps__num">1</span><span class="undt-isteps__done"><span class="screen-reader-text"><?php esc_html_e( 'Erledigt:', 'unternehmensdaten' ); ?></span><?php UNDT_Ui::icon( 'check', 14 ); ?></span></span>
					<div class="undt-steps__body">
						<div class="undt-steps__title"><?php esc_html_e( 'Datei wählen', 'unternehmensdaten' ); ?></div>
						<div class="undt-steps__action">
							<label class="undt-drop" data-undt-drop>
								<input type="file" name="undt_file" accept="application/json,.json" class="undt-drop__input" data-undt-import-file />
								<?php UNDT_Ui::icon( 'upload', 20 ); ?>
								<span class="undt-drop__text"><strong><?php esc_html_e( 'Datei auswählen', 'unternehmensdaten' ); ?></strong> <?php esc_html_e( 'oder hierher ziehen', 'unternehmensdaten' ); ?></span>
								<span class="undt-drop__hint"><?php esc_html_e( 'Eine .json-Datei aus „Sicherung herunterladen“', 'unternehmensdaten' ); ?></span>
							</label>
							<div class="undt-ifile" data-undt-import-chosen hidden>
								<span><?php UNDT_Ui::icon( 'file', 16 ); ?><strong data-undt-import-name></strong> <span class="undt-ifile__meta" data-undt-import-meta></span></span>
								<button type="button" class="undt-link-button" data-undt-import-other><?php esc_html_e( 'Andere Datei', 'unternehmensdaten' ); ?></button>
							</div>
							<p class="undt-amber" data-undt-import-error hidden></p>
						</div>
					</div>
				</li>
				<li class="undt-steps__item undt-steps__item--open" data-undt-istep="2">
					<span class="undt-steps__circle">2</span>
					<div class="undt-steps__body">
						<div class="undt-steps__title"><?php esc_html_e( 'Auswählen, was übernommen wird', 'unternehmensdaten' ); ?></div>
						<div class="undt-steps__action undt-iareas" data-undt-import-areas hidden></div>
					</div>
				</li>
				<li class="undt-steps__item undt-steps__item--open" data-undt-istep="3">
					<span class="undt-steps__circle">3</span>
					<div class="undt-steps__body">
						<div class="undt-steps__title"><?php esc_html_e( 'Einspielen', 'unternehmensdaten' ); ?></div>
						<div class="undt-steps__action undt-irun" data-undt-import-runbox hidden>
							<button type="button" class="undt-btn" data-undt-import-run data-label="<?php /* translators: %d: Anzahl der Bereiche. */ esc_attr_e( '%d Bereiche einspielen', 'unternehmensdaten' ); ?>" data-label-one="<?php esc_attr_e( '1 Bereich einspielen', 'unternehmensdaten' ); ?>"><?php esc_html_e( 'Einspielen', 'unternehmensdaten' ); ?></button>
							<span class="undt-irun__note"><?php esc_html_e( 'Die gewählten Bereiche werden ersetzt. Den Stand davor sichern wir automatisch.', 'unternehmensdaten' ); ?></span>
						</div>
					</div>
				</li>
			</ol>

			<noscript>
				<p class="undt-noscript"><button type="submit" class="undt-btn"><?php esc_html_e( 'Datei einspielen (alle Bereiche)', 'unternehmensdaten' ); ?></button></p>
			</noscript>
		</form>

		<?php UNDT_Ui::card_foot( __( 'Alle Angaben durchlaufen dieselbe Prüfung wie beim Eintippen. Verknüpfte Seiten wählst du danach neu aus.', 'unternehmensdaten' ) ); ?>
	</section>

	<section class="undt-box undt-mfold">
		<?php UNDT_Ui::details_open( __( 'Per Kommandozeile', 'unternehmensdaten' ), __( 'Für viele Websites auf einmal', 'unternehmensdaten' ) ); ?>
		<div class="undt-cli">
			<div class="undt-cli__row"><code>wp undt export &gt; firma.json</code><span><?php esc_html_e( 'Schreibt die Sicherung in eine Datei.', 'unternehmensdaten' ); ?></span></div>
			<div class="undt-cli__row"><code>wp undt import firma.json</code><span><?php esc_html_e( 'Spielt sie ein, mit derselben Prüfung wie hier.', 'unternehmensdaten' ); ?></span></div>
		</div>
		<?php UNDT_Ui::details_close(); ?>
	</section>
</div>
