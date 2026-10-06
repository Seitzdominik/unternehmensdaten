<?php
/**
 * Seite: Einbinden.
 *
 * Rechtsseiten, fertige Bausteine und einzelne Angaben. Ersetzt seit 0.6.0 die
 * Seite „Shortcodes“; die Filter fuer Entwickler stehen unten, aufgeklappt bei
 * Bedarf. Daten liefert UNDT_Embed.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

$undt_formats = UNDT_Copy::formats();
$undt_format  = UNDT_Copy::format();
$undt_help    = UNDT_Embed::format_help();
$undt_marks   = array();
$undt_choices = array();

foreach ( $undt_formats as $undt_key => $undt_def ) {
	$undt_choices[ $undt_key ] = $undt_def['label'];

	if ( $undt_def['active'] ) {
		$undt_marks[ $undt_key ] = __( 'erkannt', 'unternehmensdaten' );
	}
}
?>
<div class="wrap undt-wrap undt-embed-page">
	<div class="undt-embed-head">
		<?php UNDT_Ui::page_head( __( 'Einbinden', 'unternehmensdaten' ), true ); ?>
		<p class="undt-embed-head__intro"><?php esc_html_e( 'Kopiere einen Baustein und füge ihn auf einer Seite, im Footer oder in deinem Page Builder ein. Ändert sich eine Angabe, ist sie überall sofort aktuell.', 'unternehmensdaten' ); ?></p>

		<div class="undt-embed-head__tools">
			<div class="undt-embed-head__format">
				<span class="undt-embed-head__label" id="undt-format-label"><?php esc_html_e( 'Kopieren als', 'unternehmensdaten' ); ?></span>
				<?php UNDT_Ui::segment( 'undt_format_choice', 'undt-format', $undt_choices, $undt_format, __( 'Kopieren als', 'unternehmensdaten' ), $undt_marks ); ?>
			</div>
			<label class="undt-search">
				<span class="screen-reader-text"><?php esc_html_e( 'Bausteine und Angaben durchsuchen', 'unternehmensdaten' ); ?></span>
				<?php UNDT_Ui::icon( 'search', 16, 'undt-search__icon' ); ?>
				<input type="search" id="undt-embed-search" placeholder="<?php esc_attr_e( 'Suchen, etwa Telefon oder Öffnungszeiten', 'unternehmensdaten' ); ?>" />
			</label>
		</div>
		<?php foreach ( $undt_help as $undt_key => $undt_text ) : ?>
			<?php if ( isset( $undt_formats[ $undt_key ] ) ) : ?>
				<p class="undt-embed-head__help" data-undt-format-help="<?php echo esc_attr( $undt_key ); ?>"<?php echo $undt_key === $undt_format ? '' : ' hidden'; ?>><?php echo esc_html( $undt_text ); ?></p>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<section class="undt-section" data-undt-searchless aria-labelledby="undt-pages-title">
		<h2 class="undt-section__title" id="undt-pages-title"><?php esc_html_e( 'Rechtsseiten', 'unternehmensdaten' ); ?></h2>

		<form method="post" action="options.php" class="undt-form undt-box" data-undt-autosave="company">
			<?php settings_fields( UNDT_Admin::GROUP_COMPANY ); ?>
			<input type="hidden" name="undt_pages_status" value="1" />

			<?php foreach ( UNDT_Embed::pages() as $undt_key => $undt_page ) : ?>
				<?php $undt_field = UNDT_Schema::field( $undt_key ); ?>
				<div class="undt-pagerow" data-undt-page="<?php echo esc_attr( $undt_key ); ?>">
					<label class="undt-pagerow__label" for="<?php echo esc_attr( 'undt-' . $undt_key ); ?>"><?php echo esc_html( $undt_page['label'] ); ?></label>
					<div class="undt-pagerow__control">
						<?php UNDT_Controls::render( $undt_key, $undt_field, isset( UNDT_Store::company()[ $undt_key ] ) ? UNDT_Store::company()[ $undt_key ] : 0, UNDT_Store::OPTION_COMPANY . '[' . $undt_key . ']', 'undt-' . $undt_key ); ?>
					</div>
					<div class="undt-pagerow__status undt-pagerow__status--<?php echo esc_attr( $undt_page['tone'] ); ?>" data-undt-page-status>
						<?php if ( 'ok' !== $undt_page['tone'] ) : ?>
							<span class="undt-dot-s" aria-hidden="true"></span>
						<?php endif; ?>
						<span><span class="screen-reader-text"><?php echo 'open' === $undt_page['tone'] ? esc_html__( 'Problem:', 'unternehmensdaten' ) : ''; ?> </span><?php echo esc_html( $undt_page['status'] ); ?></span>
					</div>
					<div class="undt-pagerow__actions">
						<?php if ( '' !== $undt_page['create'] ) : ?>
							<button type="button" class="undt-btn undt-btn--secondary undt-btn--small" data-undt-action="create_page" data-undt-value="<?php echo esc_attr( $undt_page['create'] ); ?>">
								<?php
								printf(
									/* translators: %s: Name der Seite, etwa Impressum. */
									esc_html__( 'Seite „%s“ anlegen', 'unternehmensdaten' ),
									esc_html( $undt_page['label'] )
								);
								?>
							</button>
						<?php endif; ?>
						<?php if ( '' !== $undt_page['view'] ) : ?>
							<a class="undt-action" href="<?php echo esc_url( $undt_page['view'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ansehen', 'unternehmensdaten' ); ?><?php UNDT_Ui::icon( 'out', 13 ); ?><span class="screen-reader-text"> <?php esc_html_e( '(öffnet in einem neuen Tab)', 'unternehmensdaten' ); ?></span></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<?php UNDT_Ui::noscript_submit(); ?>
			<?php UNDT_Ui::card_foot( __( 'Verknüpfte Seiten erscheinen automatisch im Footer und in den Rechtslinks. Eine neue Seite wird als Entwurf mit dem passenden Baustein angelegt.', 'unternehmensdaten' ) ); ?>
		</form>
	</section>

	<section class="undt-section" aria-labelledby="undt-blocks-title">
		<h2 class="undt-section__title" id="undt-blocks-title"><?php esc_html_e( 'Fertige Bausteine', 'unternehmensdaten' ); ?></h2>
		<p class="undt-embed-empty" data-undt-blocks-empty hidden><?php esc_html_e( 'Kein Baustein passt zur Suche.', 'unternehmensdaten' ); ?></p>

		<?php foreach ( UNDT_Embed::groups() as $undt_group_key => $undt_group ) : ?>
			<div class="undt-box undt-blockgroup" data-undt-blockgroup>
				<div class="undt-blockgroup__head">
					<?php UNDT_Ui::tile( $undt_group['icon'], 0, 'brand' ); ?>
					<h3 class="undt-blockgroup__title"><?php echo esc_html( $undt_group['title'] ); ?></h3>
				</div>

				<?php foreach ( $undt_group['items'] as $undt_block_key => $undt_block ) : ?>
					<?php
					list( $undt_title, $undt_desc, $undt_tag, $undt_options ) = $undt_block;

					$undt_code   = UNDT_Embed::code( $undt_tag, $undt_options );
					$undt_places = UNDT_Usage::find( $undt_block_key );
					$undt_id     = 'undt-block-' . $undt_block_key;
					?>
					<div class="undt-eblock" data-undt-block data-undt-search="<?php echo esc_attr( strtolower( $undt_group['title'] . ' ' . $undt_title . ' ' . $undt_desc . ' ' . $undt_code ) ); ?>" data-undt-tag="<?php echo esc_attr( $undt_tag ); ?>">
						<div class="undt-eblock__row">
							<div class="undt-eblock__text">
								<div class="undt-eblock__title"><?php echo esc_html( $undt_title ); ?></div>
								<div class="undt-eblock__desc"><?php echo esc_html( $undt_desc ); ?></div>
							</div>
							<div class="undt-embed__usage<?php echo empty( $undt_places ) ? ' undt-embed__usage--unused' : ''; ?>">
								<?php if ( empty( $undt_places ) ) : ?>
									<span class="undt-dot-s" aria-hidden="true"></span>
								<?php endif; ?>
								<?php echo esc_html( UNDT_Usage::label( $undt_block_key ) ); ?>
							</div>
							<button type="button" class="undt-link-button undt-eblock__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $undt_id ); ?>" data-undt-preview-toggle data-label-open="<?php esc_attr_e( 'Vorschau', 'unternehmensdaten' ); ?>" data-label-close="<?php esc_attr_e( 'Vorschau schließen', 'unternehmensdaten' ); ?>"><?php esc_html_e( 'Vorschau', 'unternehmensdaten' ); ?></button>
							<div class="undt-embed__code">
								<button type="button" class="undt-copy undt-chip" data-undt-copy="<?php echo esc_attr( $undt_code ); ?>" data-undt-block-code aria-label="<?php echo esc_attr( sprintf( /* translators: 1: Baustein, 2: Shortcode. */ __( '%1$s kopieren: %2$s', 'unternehmensdaten' ), $undt_title, $undt_code ) ); ?>">
									<code class="undt-chip__code" data-undt-code-label><?php echo esc_html( $undt_code ); ?></code>
									<span class="undt-chip__label undt-chip__label--idle"><?php UNDT_Ui::icon( 'copy', 14 ); ?><?php esc_html_e( 'Kopieren', 'unternehmensdaten' ); ?></span>
									<span class="undt-chip__label undt-chip__label--done"><?php UNDT_Ui::icon( 'check', 14 ); ?><?php esc_html_e( 'Kopiert', 'unternehmensdaten' ); ?></span>
								</button>
							</div>
						</div>

						<div class="undt-eblock__more" id="<?php echo esc_attr( $undt_id ); ?>" hidden>
							<div class="undt-preview">
								<div class="undt-preview__label"><?php esc_html_e( 'Vorschau mit deinen Angaben', 'unternehmensdaten' ); ?></div>
								<div class="undt-preview__body" data-undt-preview><?php echo UNDT_Embed::preview( $undt_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in preview() ueber UNDT_Icons::kses(). ?></div>
							</div>

							<?php if ( ! empty( $undt_options ) ) : ?>
								<fieldset class="undt-eblock__options">
									<legend class="undt-eblock__options-title"><?php esc_html_e( 'Anpassen', 'unternehmensdaten' ); ?></legend>
									<?php foreach ( $undt_options as $undt_option ) : ?>
										<label class="undt-eblock__option">
											<input type="checkbox" data-undt-option data-on="<?php echo esc_attr( $undt_option['on'] ); ?>" data-off="<?php echo esc_attr( $undt_option['off'] ); ?>" data-list="<?php echo esc_attr( $undt_option['list'] ); ?>" data-item="<?php echo esc_attr( $undt_option['item'] ); ?>"<?php checked( $undt_option['checked'] ); ?> />
											<?php echo esc_html( $undt_option['label'] ); ?>
										</label>
									<?php endforeach; ?>
									<p class="undt-eblock__options-hint"><?php esc_html_e( 'Der Code oben passt sich an.', 'unternehmensdaten' ); ?></p>
								</fieldset>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</section>

	<?php
	$undt_values = UNDT_Embed::values();
	$undt_groups = UNDT_Embed::value_groups();
	?>
	<section class="undt-section" aria-labelledby="undt-values-title">
		<div class="undt-section__head">
			<h2 class="undt-section__title" id="undt-values-title"><?php esc_html_e( 'Einzelne Angaben', 'unternehmensdaten' ); ?></h2>
			<span class="undt-section__note"><?php esc_html_e( 'Für eigene Layouts, etwa die Telefonnummer im Header', 'unternehmensdaten' ); ?></span>
		</div>

		<div class="undt-box" data-undt-values>
			<div class="undt-filters" role="group" aria-label="<?php esc_attr_e( 'Angaben filtern', 'unternehmensdaten' ); ?>">
				<button type="button" class="undt-filter" aria-pressed="true" data-undt-filter=""><?php esc_html_e( 'Alle', 'unternehmensdaten' ); ?></button>
				<?php foreach ( $undt_groups as $undt_key => $undt_label ) : ?>
					<button type="button" class="undt-filter" aria-pressed="false" data-undt-filter="<?php echo esc_attr( $undt_key ); ?>"><?php echo esc_html( $undt_label ); ?></button>
				<?php endforeach; ?>
			</div>

			<p class="undt-embed-empty undt-embed-empty--values" data-undt-values-empty hidden><?php esc_html_e( 'Keine Angabe passt zur Suche.', 'unternehmensdaten' ); ?></p>

			<?php foreach ( $undt_values as $undt_index => $undt_value ) : ?>
				<?php
				$undt_codes = UNDT_Copy::codes( $undt_value['key'] );
				$undt_shown = array();

				foreach ( $undt_codes as $undt_format_key => $undt_code_value ) {
					$undt_shown[ $undt_format_key ] = '' === $undt_code_value ? __( 'in diesem Format nicht verfügbar', 'unternehmensdaten' ) : UNDT_Copy::display( $undt_format_key, $undt_code_value, $undt_value['label'] );
				}

				$undt_current = isset( $undt_codes[ $undt_format ] ) ? $undt_codes[ $undt_format ] : '';
				?>
				<div class="undt-value" data-undt-value data-undt-group="<?php echo esc_attr( $undt_value['group'] ); ?>" data-undt-search="<?php echo esc_attr( strtolower( $undt_value['label'] . ' ' . $undt_value['key'] ) ); ?>"<?php echo $undt_index >= UNDT_Embed::VISIBLE_VALUES ? ' data-undt-beyond hidden' : ''; ?>>
					<div class="undt-value__label"><?php echo esc_html( $undt_value['label'] ); ?></div>
					<div class="undt-value__value<?php echo '' === $undt_value['value'] ? ' undt-value__value--empty' : ''; ?>"><?php echo esc_html( '' === $undt_value['value'] ? __( 'noch leer', 'unternehmensdaten' ) : $undt_value['value'] ); ?></div>
					<div class="undt-embed__code">
						<button type="button" class="undt-copy undt-chip" data-undt-copy="<?php echo esc_attr( $undt_current ); ?>" data-undt-codes="<?php echo esc_attr( (string) wp_json_encode( $undt_codes ) ); ?>" data-undt-shown="<?php echo esc_attr( (string) wp_json_encode( $undt_shown ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Bezeichnung der Angabe. */ __( '%s kopieren', 'unternehmensdaten' ), $undt_value['label'] ) ); ?>">
							<code class="undt-chip__code" data-undt-code-label><?php echo esc_html( isset( $undt_shown[ $undt_format ] ) ? $undt_shown[ $undt_format ] : '' ); ?></code>
							<span class="undt-chip__label undt-chip__label--idle"><?php UNDT_Ui::icon( 'copy', 14 ); ?><?php esc_html_e( 'Kopieren', 'unternehmensdaten' ); ?></span>
							<span class="undt-chip__label undt-chip__label--done"><?php UNDT_Ui::icon( 'check', 14 ); ?><?php esc_html_e( 'Kopiert', 'unternehmensdaten' ); ?></span>
						</button>
					</div>
				</div>
			<?php endforeach; ?>

			<?php if ( count( $undt_values ) > UNDT_Embed::VISIBLE_VALUES ) : ?>
				<div class="undt-todo__more">
					<button type="button" class="undt-link-button" data-undt-values-more>
						<?php
						printf(
							/* translators: %d: Anzahl aller Angaben. */
							esc_html__( 'Alle %d Angaben anzeigen', 'unternehmensdaten' ),
							(int) count( $undt_values )
						);
						?>
					</button>
				</div>
			<?php endif; ?>

			<?php UNDT_Ui::card_foot( __( 'Den Knopf zum Kopieren findest du auch direkt neben jeder Angabe in Firma. Wer keine Admin-Rechte hat, sieht ihn nicht.', 'unternehmensdaten' ) ); ?>
		</div>
	</section>

	<div class="undt-box undt-devbox" data-undt-searchless>
		<?php UNDT_Ui::details_open( __( 'Für Entwickler', 'unternehmensdaten' ), __( 'PHP-Funktionen, Schleifen, Filter', 'unternehmensdaten' ) ); ?>
			<dl class="undt-dev">
				<dt><code>undt_get( 'phone' )</code></dt><dd><?php esc_html_e( 'Gibt eine Angabe zurück, bereits bereinigt.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_has( 'fax' )</code></dt><dd><?php esc_html_e( 'Prüft, ob eine Angabe eingetragen ist.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_query( 'undt_hours' )</code></dt><dd><?php esc_html_e( 'Liefert Zeilen für Schleifen in Page Buildern: Öffnungszeiten, Preise, Social, FAQ.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_is_open()</code></dt><dd><?php esc_html_e( 'Ob gerade geöffnet ist, mit Sonderzeiten.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_fields</code></dt><dd><?php esc_html_e( 'Filter: eigene Felder ergänzen. Sie durchlaufen automatisch Bereinigung, Ausgabe und Shortcodes.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_modules</code></dt><dd><?php esc_html_e( 'Filter: eigene Inhaltsbereiche ergänzen.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_capability</code></dt><dd><?php esc_html_e( 'Filter: das Admin-Recht ändern. Standard ist manage_options.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_css</code>, <code>undt_inline_css</code></dt><dd><?php esc_html_e( 'Filter: das mitgelieferte CSS anpassen oder ganz abschalten.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_imprint_html</code>, <code>undt_footer_html</code></dt><dd><?php esc_html_e( 'Filter: die fertige Ausgabe nachbearbeiten.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_schema_organization</code></dt><dd><?php esc_html_e( 'Filter: die JSON-LD-Auszeichnung anpassen.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_audit_issues</code></dt><dd><?php esc_html_e( 'Filter: eigene Prüfungen ergänzen.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_auto_banner</code></dt><dd><?php esc_html_e( 'Filter: wo das Banner automatisch am Seitenanfang erscheint.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_social_icon</code></dt><dd><?php esc_html_e( 'Filter: das Symbol einer Plattform ersetzen.', 'unternehmensdaten' ); ?></dd>
				<dt><code>undt_link_post_types</code></dt><dd><?php esc_html_e( 'Filter: aus welchen Inhaltstypen die Seitenfelder wählen.', 'unternehmensdaten' ); ?></dd>
			</dl>
		<?php UNDT_Ui::details_close(); ?>
	</div>
</div>
