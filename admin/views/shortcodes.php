<?php
/**
 * Seite: Shortcode- und Schnittstellen-Referenz.
 *
 * Aufgeteilt in Registerkarten, weil die vollstaendige Liste sonst mehrere
 * Bildschirme lang wird. Waehrend einer Suche treten die Karten zurueck und alle
 * Treffer stehen untereinander, sonst faende man nur, was im offenen Reiter
 * liegt.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

$undt_profile  = UNDT_Store::profile();
$undt_tabs     = UNDT_Schema::tabs();
$undt_modules  = UNDT_Shortcodes::module_catalog();
$undt_sources  = UNDT_Api::sources();

$undt_grouped = array();

foreach ( UNDT_Schema::fields() as $undt_key => $undt_field ) {
	if ( empty( $undt_field['shortcode'] ) ) {
		continue;
	}

	if ( ! UNDT_Schema::applies( $undt_field['when'], $undt_profile ) ) {
		continue;
	}

	$undt_grouped[ $undt_field['tab'] ][ $undt_key ] = $undt_field;
}

$undt_panels = array(
	'blocks'  => __( 'Rechtliche Blöcke', 'unternehmensdaten' ),
	'modules' => __( 'Inhaltsbereiche', 'unternehmensdaten' ),
	'fields'  => __( 'Einzelne Felder', 'unternehmensdaten' ),
	'builder' => __( 'Page Builder', 'unternehmensdaten' ),
);

if ( empty( $undt_modules ) ) {
	unset( $undt_panels['modules'] );
}

$undt_first = key( $undt_panels );

?>
<div class="wrap undt-wrap" id="undt-shortcodes">
	<h1><?php esc_html_e( 'Shortcodes', 'unternehmensdaten' ); ?></h1>

	<p class="undt-context">
		<?php esc_html_e( 'Ein Klick auf den Code kopiert ihn in die Zwischenablage. Die Suche durchsucht alle Registerkarten auf einmal.', 'unternehmensdaten' ); ?>
	</p>

	<p class="undt-search">
		<label for="undt-filter" class="screen-reader-text"><?php esc_html_e( 'Durchsuchen', 'unternehmensdaten' ); ?></label>
		<input type="search" id="undt-filter" class="regular-text" placeholder="<?php esc_attr_e( 'Suchen, etwa Telefon, Öffnungszeiten oder Query …', 'unternehmensdaten' ); ?>" />
	</p>

	<div class="nav-tab-wrapper undt-tabs" role="tablist">
		<?php foreach ( $undt_panels as $undt_panel => $undt_label ) : ?>
			<button
				type="button"
				role="tab"
				id="undt-tab-<?php echo esc_attr( $undt_panel ); ?>"
				class="nav-tab<?php echo $undt_panel === $undt_first ? ' nav-tab-active' : ''; ?>"
				aria-controls="undt-panel-<?php echo esc_attr( $undt_panel ); ?>"
				aria-selected="<?php echo $undt_panel === $undt_first ? 'true' : 'false'; ?>"
			><?php echo esc_html( $undt_label ); ?></button>
		<?php endforeach; ?>
	</div>

	<!-- Rechtliche Blöcke -->
	<div id="undt-panel-blocks" class="undt-panel" role="tabpanel" aria-labelledby="undt-tab-blocks"<?php echo 'blocks' === $undt_first ? '' : ' hidden'; ?>>
		<p class="description undt-section-hint">
			<?php esc_html_e( 'Diese Blöcke geben semantisches HTML aus und übernehmen Schrift und Farben vom Theme. Die Überschriftenebene lässt sich anpassen, damit sich der Block in die Gliederung der Seite einfügt.', 'unternehmensdaten' ); ?>
		</p>
		<?php UNDT_Fields::shortcode_table( UNDT_Shortcodes::catalog() ); ?>
	</div>

	<?php if ( isset( $undt_panels['modules'] ) ) : ?>
		<!-- Inhaltsbereiche -->
		<div id="undt-panel-modules" class="undt-panel" role="tabpanel" aria-labelledby="undt-tab-modules"<?php echo 'modules' === $undt_first ? '' : ' hidden'; ?>>
			<p class="description undt-section-hint">
				<?php esc_html_e( 'Aufgeführt ist nur, was gerade aktiv ist. Abgeschaltete Bereiche geben nichts aus.', 'unternehmensdaten' ); ?>
			</p>
			<?php UNDT_Fields::shortcode_table( $undt_modules ); ?>
		</div>
	<?php endif; ?>

	<!-- Einzelne Felder -->
	<div id="undt-panel-fields" class="undt-panel" role="tabpanel" aria-labelledby="undt-tab-fields"<?php echo 'fields' === $undt_first ? '' : ' hidden'; ?>>
		<p class="description undt-section-hint">
			<?php esc_html_e( 'Zusätzliche Attribute: link="1" macht Telefon, E-Mail und URL anklickbar, obfuscate="1" verschleiert E-Mail-Adressen, before und after ergänzen Text, der nur erscheint, wenn das Feld auch gefüllt ist.', 'unternehmensdaten' ); ?>
		</p>

		<?php foreach ( $undt_tabs as $undt_tab => $undt_tab_label ) : ?>
			<?php if ( empty( $undt_grouped[ $undt_tab ] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>

			<h3 class="undt-section-title"><?php echo esc_html( $undt_tab_label ); ?></h3>

			<table class="widefat striped undt-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Feld', 'unternehmensdaten' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Shortcode', 'unternehmensdaten' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Aktueller Wert', 'unternehmensdaten' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $undt_grouped[ $undt_tab ] as $undt_key => $undt_field ) : ?>
						<?php
						$undt_value = UNDT_Store::get( $undt_key );

						if ( 'page' === $undt_field['type'] && '' !== $undt_value ) {
							$undt_value = (string) get_the_title( (int) $undt_value );
						}

						$undt_value = trim( preg_replace( '/\s+/', ' ', $undt_value ) );
						$undt_short = mb_substr( $undt_value, 0, 70 ) . ( mb_strlen( $undt_value ) > 70 ? '…' : '' );
						?>
						<tr class="undt-searchable" data-undt-text="<?php echo esc_attr( strtolower( $undt_field['label'] . ' ' . $undt_key . ' ' . $undt_value ) ); ?>">
							<td>
								<strong><?php echo esc_html( $undt_field['label'] ); ?></strong>
								<?php if ( ! empty( $undt_field['basis'] ) ) : ?>
									<span class="undt-basis"><?php echo esc_html( $undt_field['basis'] ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php UNDT_Fields::copy_button( '[undt key="' . $undt_key . '"]' ); ?></td>
							<td>
								<?php if ( '' === $undt_short ) : ?>
									<em class="undt-empty"><?php esc_html_e( 'noch nicht ausgefüllt', 'unternehmensdaten' ); ?></em>
								<?php else : ?>
									<?php echo esc_html( $undt_short ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
	</div>

	<!-- Page Builder -->
	<div id="undt-panel-builder" class="undt-panel" role="tabpanel" aria-labelledby="undt-tab-builder"<?php echo 'builder' === $undt_first ? '' : ' hidden'; ?>>
		<p class="description undt-section-hint">
			<?php esc_html_e( 'Für Bricks, Breakdance und Etch stehen die Daten zusätzlich als PHP-Funktionen und als Schleifen-Quellen bereit. Einen REST-Endpunkt gibt es bewusst nicht: Page Builder laufen auf dem Server und brauchen keinen, und ein öffentlicher Endpunkt wäre zusätzliche Angriffsfläche ohne Gegenwert.', 'unternehmensdaten' ); ?>
		</p>

		<h3 class="undt-section-title"><?php esc_html_e( 'Schleifen-Quellen', 'unternehmensdaten' ); ?></h3>
		<p class="description undt-section-hint">
			<?php esc_html_e( 'In Bricks erscheinen diese Namen direkt in der Auswahl des Schleifen-Dialogs. In Breakdance und Etch werden sie über undt_query() geholt. Innerhalb einer Bricks-Schleife liest undt_loop() das jeweilige Feld der aktuellen Zeile.', 'unternehmensdaten' ); ?>
		</p>

		<table class="widefat striped undt-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Query-Name', 'unternehmensdaten' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Inhalt', 'unternehmensdaten' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Felder je Zeile', 'unternehmensdaten' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $undt_sources as $undt_source => $undt_meta ) : ?>
					<tr class="undt-searchable" data-undt-text="<?php echo esc_attr( strtolower( $undt_source . ' ' . $undt_meta['label'] . ' ' . $undt_meta['fields'] ) ); ?>">
						<td>
							<?php UNDT_Fields::copy_button( $undt_source ); ?>
							<?php if ( ! UNDT_Modules::is_active( $undt_meta['module'] ) ) : ?>
								<p class="description undt-empty"><?php esc_html_e( 'Bereich abgeschaltet, liefert derzeit nichts.', 'unternehmensdaten' ); ?></p>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $undt_meta['label'] ); ?></td>
						<td class="undt-atts"><code><?php echo esc_html( $undt_meta['fields'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3 class="undt-section-title"><?php esc_html_e( 'Funktionen', 'unternehmensdaten' ); ?></h3>

		<table class="widefat striped undt-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Aufruf', 'unternehmensdaten' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Liefert', 'unternehmensdaten' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$undt_functions = array(
					"undt_get( 'phone' )"            => __( 'Ein Stammdaten-Feld. Zweites Argument ist ein Ersatzwert.', 'unternehmensdaten' ),
					"undt_has( 'phone' )"            => __( 'Ob das Feld befüllt ist. Für Bedingungen.', 'unternehmensdaten' ),
					"undt_field( 'hours', 'note' )"  => __( 'Ein Feld eines Inhaltsbereichs.', 'unternehmensdaten' ),
					"undt_query( 'undt_faq' )"       => __( 'Alle Zeilen einer Quelle als Array. Zweites Argument akzeptiert group und limit.', 'unternehmensdaten' ),
					"undt_loop( 'question' )"        => __( 'Ein Feld der gerade laufenden Bricks-Schleife.', 'unternehmensdaten' ),
					'undt_is_open()'                 => __( 'Ob gerade geöffnet ist. Achtung bei Seiten-Caches.', 'unternehmensdaten' ),
					'undt_today()'                   => __( 'Die heute geltenden Zeiten als Text.', 'unternehmensdaten' ),
				);
				?>
				<?php foreach ( $undt_functions as $undt_call => $undt_desc ) : ?>
					<tr class="undt-searchable" data-undt-text="<?php echo esc_attr( strtolower( $undt_call . ' ' . $undt_desc ) ); ?>">
						<td><?php UNDT_Fields::copy_button( $undt_call ); ?></td>
						<td><?php echo esc_html( $undt_desc ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3 class="undt-section-title"><?php esc_html_e( 'Je nach Builder', 'unternehmensdaten' ); ?></h3>

		<table class="widefat striped undt-table">
			<tbody>
				<tr class="undt-searchable" data-undt-text="bricks query loop echo dynamic data">
					<td><strong>Bricks</strong></td>
					<td>
						<p class="description">
							<?php esc_html_e( 'Die Query-Namen stehen im Schleifen-Dialog unter „Unternehmensdaten“ zur Auswahl. Einzelwerte holt man über ein Feld für dynamische Daten:', 'unternehmensdaten' ); ?>
						</p>
						<?php UNDT_Fields::copy_button( "{echo:undt_get('phone')}" ); ?>
						<?php UNDT_Fields::copy_button( "{echo:undt_loop('question')}" ); ?>
						<p class="description">
							<?php esc_html_e( 'Bricks muss dafür die Ausführung von Code erlauben. Die Einstellung dazu liegt unter Bricks, Einstellungen, Allgemein.', 'unternehmensdaten' ); ?>
						</p>
					</td>
				</tr>
				<tr class="undt-searchable" data-undt-text="breakdance etch php code block loop">
					<td><strong>Breakdance, Etch</strong></td>
					<td>
						<p class="description">
							<?php esc_html_e( 'Beide führen PHP in einem Code-Element aus. Eine Schleife entsteht damit direkt über undt_query():', 'unternehmensdaten' ); ?>
						</p>
						<?php UNDT_Fields::copy_button( "foreach ( undt_query( 'undt_faq' ) as \$row ) { echo esc_html( \$row['question'] ); }" ); ?>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="notice notice-info inline undt-disclaimer">
			<p>
				<?php esc_html_e( 'Die Funktionen und Query-Namen sind in WordPress geprüft. Die Anbindung an Bricks selbst konnte hier nicht getestet werden, weil das Plugin kostenpflichtig ist. Sollte die Auswahl im Schleifen-Dialog fehlen, funktionieren die Funktionen trotzdem, und die Schleife lässt sich über ein Code-Element bauen.', 'unternehmensdaten' ); ?>
			</p>
		</div>
	</div>

	<p class="undt-no-results" hidden><?php esc_html_e( 'Keine Treffer.', 'unternehmensdaten' ); ?></p>
</div>
