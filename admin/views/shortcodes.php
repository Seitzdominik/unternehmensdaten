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
	'dynamic' => __( 'Dynamische Daten', 'unternehmensdaten' ),
	'builder' => __( 'Page Builder', 'unternehmensdaten' ),
);

$undt_dynamic_builder = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER );
$undt_dynamic_seo     = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_SEO );
$undt_dynamic_schema  = UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_SCHEMA );

// Alles, was irgendwo angeboten wird; die Spalten sagen, wo.
$undt_dynamic = $undt_dynamic_builder + $undt_dynamic_schema;

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

	<div class="undt-box">

	<div class="undt-tabs" role="tablist">
		<?php foreach ( $undt_panels as $undt_panel => $undt_label ) : ?>
			<button
				type="button"
				role="tab"
				id="undt-tab-<?php echo esc_attr( $undt_panel ); ?>"
				class="undt-tab<?php echo $undt_panel === $undt_first ? ' is-active' : ''; ?>"
				aria-controls="undt-panel-<?php echo esc_attr( $undt_panel ); ?>"
				aria-selected="<?php echo $undt_panel === $undt_first ? 'true' : 'false'; ?>"
			><?php echo esc_html( $undt_label ); ?></button>
		<?php endforeach; ?>
	</div>

	<div class="undt-box__body">

	<!-- Rechtliche Blöcke -->
	<div id="undt-panel-blocks" class="undt-panel" role="tabpanel" aria-labelledby="undt-tab-blocks"<?php echo 'blocks' === $undt_first ? '' : ' hidden'; ?>>
		<p class="description undt-section-hint">
			<?php esc_html_e( 'Diese Blöcke geben semantisches HTML aus und übernehmen Schrift und Farben vom Theme. Die Überschriftenebene lässt sich anpassen, damit sich der Block in die Gliederung der Seite einfügt.', 'unternehmensdaten' ); ?>
		</p>
		<?php UNDT_Copy::table( UNDT_Shortcodes::catalog() ); ?>
	</div>

	<?php if ( isset( $undt_panels['modules'] ) ) : ?>
		<!-- Inhaltsbereiche -->
		<div id="undt-panel-modules" class="undt-panel" role="tabpanel" aria-labelledby="undt-tab-modules"<?php echo 'modules' === $undt_first ? '' : ' hidden'; ?>>
			<p class="description undt-section-hint">
				<?php esc_html_e( 'Aufgeführt ist nur, was gerade aktiv ist. Abgeschaltete Bereiche geben nichts aus.', 'unternehmensdaten' ); ?>
			</p>
			<?php UNDT_Copy::table( $undt_modules ); ?>
		</div>
	<?php endif; ?>

	<!-- Einzelne Felder -->
	<div id="undt-panel-fields" class="undt-panel" role="tabpanel" aria-labelledby="undt-tab-fields"<?php echo 'fields' === $undt_first ? '' : ' hidden'; ?>>
		<p class="description undt-section-hint">
			<?php esc_html_e( 'Zusätzliche Attribute: link="1" macht Telefon, E-Mail, URL und Seiten anklickbar, text ersetzt dabei den sichtbaren Wert, etwa text="Route planen". obfuscate="1" verschleiert E-Mail-Adressen, before und after ergänzen Text, der nur erscheint, wenn das Feld auch gefüllt ist.', 'unternehmensdaten' ); ?>
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

						// Seitenfelder zeigen den Titel des Eintrags oder die eigene Adresse.
						if ( 'page' === $undt_field['type'] ) {
							$undt_page  = UNDT_Store::page_id( $undt_key );
							$undt_value = $undt_page > 0 ? (string) get_the_title( $undt_page ) : ( preg_match( '/^\d*$/', trim( $undt_value ) ) ? '' : $undt_value );
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
							<td><?php UNDT_Copy::button( '[undt key="' . $undt_key . '"]' ); ?></td>
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

	<!-- Dynamische Daten -->
	<div id="undt-panel-dynamic" class="undt-panel" role="tabpanel" aria-labelledby="undt-tab-dynamic"<?php echo 'dynamic' === $undt_first ? '' : ' hidden'; ?>>
		<p class="description undt-section-hint">
			<?php esc_html_e( 'Slim SEO und Bricks führen diese Werte in ihrer Auswahl dynamischer Daten unter „Unternehmensdaten“, etwa hinter den drei Punkten neben der Meta-Beschreibung und in den Schema-Einstellungen von Slim SEO Pro. In Etch steht die Schreibweise aus der letzten Spalte in Texten und Attributen, auch mit Modifikatoren wie .toUpperCase().', 'unternehmensdaten' ); ?>
		</p>

		<table class="widefat striped undt-table undt-table--dynamic">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Wert', 'unternehmensdaten' ); ?></th>
					<th scope="col">Slim SEO</th>
					<th scope="col">Bricks</th>
					<th scope="col">Etch</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $undt_dynamic as $undt_key => $undt_label ) : ?>
					<?php
					$undt_syntax = UNDT_Dynamic::syntax( $undt_key );
					$undt_value  = trim( preg_replace( '/\s+/', ' ', UNDT_Dynamic::value( $undt_key ) ) );
					$undt_short  = mb_substr( $undt_value, 0, 60 ) . ( mb_strlen( $undt_value ) > 60 ? '…' : '' );
					?>
					<tr class="undt-searchable" data-undt-text="<?php echo esc_attr( strtolower( $undt_label . ' ' . $undt_key . ' ' . $undt_value ) ); ?>">
						<td>
							<strong><?php echo esc_html( $undt_label ); ?></strong>
							<p class="description">
								<?php if ( '' === $undt_short ) : ?>
									<em class="undt-empty"><?php esc_html_e( 'derzeit leer', 'unternehmensdaten' ); ?></em>
								<?php else : ?>
									<?php echo esc_html( $undt_short ); ?>
								<?php endif; ?>
							</p>
						</td>
						<td>
							<?php if ( isset( $undt_dynamic_seo[ $undt_key ] ) || isset( $undt_dynamic_schema[ $undt_key ] ) ) : ?>
								<?php UNDT_Copy::button( $undt_syntax['slim_seo'], 'inline' ); ?>
								<?php if ( ! isset( $undt_dynamic_seo[ $undt_key ] ) ) : ?>
									<span class="undt-basis"><?php esc_html_e( 'nur in den Schema-Einstellungen', 'unternehmensdaten' ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span class="undt-empty" title="<?php esc_attr_e( 'Nur für Builder gedacht', 'unternehmensdaten' ); ?>">–</span>
							<?php endif; ?>
						</td>
						<?php if ( isset( $undt_dynamic_builder[ $undt_key ] ) ) : ?>
							<td><?php UNDT_Copy::button( $undt_syntax['bricks'], 'inline' ); ?></td>
							<td><?php UNDT_Copy::button( $undt_syntax['etch'], 'inline' ); ?></td>
						<?php else : ?>
							<td><span class="undt-empty" title="<?php esc_attr_e( 'Nur für die strukturierten Daten gedacht', 'unternehmensdaten' ); ?>">–</span></td>
							<td><span class="undt-empty" title="<?php esc_attr_e( 'Nur für die strukturierten Daten gedacht', 'unternehmensdaten' ); ?>">–</span></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description undt-section-hint">
			<?php esc_html_e( 'Werte mit „(Link)“ liefern eine Adresse und gehören in Link-Felder. Werte mit „(ja/nein)“ eignen sich für Bedingungen: Bricks bekommt 1 oder nichts, Etch true oder false. Öffnungsangaben und Banner fehlen bei Slim SEO; die Öffnungsangaben zeigen hinter einem Seiten-Cache den Stand der Zwischenspeicherung. Alle Adressen der Social-Profile auf einmal gibt es nur in den Schema-Einstellungen: in einem Feld, das sich vervielfältigen lässt, etwa sameAs, wird daraus je ein Eintrag pro Profil.', 'unternehmensdaten' ); ?>
		</p>
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
							<?php UNDT_Copy::button( $undt_source ); ?>
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
						<td><?php UNDT_Copy::button( $undt_call ); ?></td>
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
							<?php esc_html_e( 'Einzelwerte stehen in der Auswahl dynamischer Daten unter „Unternehmensdaten“, siehe Registerkarte „Dynamische Daten“. Die Query-Namen stehen im Schleifen-Dialog zur Auswahl, die Felder der laufenden Schleife liest undt_loop() über das echo-Tag:', 'unternehmensdaten' ); ?>
						</p>
						<?php UNDT_Copy::button( '{undt_phone}' ); ?>
						<?php UNDT_Copy::button( "{echo:undt_loop('question')}" ); ?>
						<p class="description">
							<?php esc_html_e( 'Das Plugin gibt seine Funktionen für das echo-Tag selbst frei. Zusätzlich muss unter Bricks, Einstellungen, Custom code die Code-Ausführung für die eigene Benutzerrolle eingeschaltet sein. Die Tags aus der Auswahl brauchen das nicht.', 'unternehmensdaten' ); ?>
						</p>
					</td>
				</tr>
				<tr class="undt-searchable" data-undt-text="etch dynamic data options php code block loop">
					<td><strong>Etch</strong></td>
					<td>
						<p class="description">
							<?php esc_html_e( 'Einzelwerte stehen unter options.undt bereit. Schleifen entstehen in einem Code-Element über undt_query():', 'unternehmensdaten' ); ?>
						</p>
						<?php UNDT_Copy::button( '{options.undt.phone}' ); ?>
						<?php UNDT_Copy::button( "foreach ( undt_query( 'undt_faq' ) as \$row ) { echo esc_html( \$row['question'] ); }" ); ?>
					</td>
				</tr>
				<tr class="undt-searchable" data-undt-text="breakdance php code block loop">
					<td><strong>Breakdance</strong></td>
					<td>
						<p class="description">
							<?php esc_html_e( 'Führt PHP in einem Code-Element aus. Einzelwerte liefert undt_get(), Schleifen undt_query().', 'unternehmensdaten' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="notice notice-info inline undt-disclaimer">
			<p>
				<?php esc_html_e( 'Die Anbindung ist mit Bricks 2.4 und Etch 1.6 geprüft. Fehlt in einer anderen Fassung ein Wert, hilft undt_get() in einem Code-Element weiter.', 'unternehmensdaten' ); ?>
			</p>
		</div>
	</div>

	</div><!-- .undt-box__body -->
	</div><!-- .undt-box -->

	<p class="undt-no-results" hidden><?php esc_html_e( 'Keine Treffer.', 'unternehmensdaten' ); ?></p>
</div>
