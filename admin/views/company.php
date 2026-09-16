<?php
/**
 * Seite: Stammdaten.
 *
 * Alle Tabs stehen in einem einzigen Formular und werden clientseitig
 * umgeschaltet. Das hat zwei Gruende: das Umschalten braucht keinen Seitenaufbau,
 * und ein Speichern uebertraegt immer den vollstaendigen Datensatz. Ein
 * teilweise uebertragenes Formular koennte sonst Felder anderer Tabs leeren.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

$undt_profile  = UNDT_Store::profile();
$undt_company  = UNDT_Store::company();
$undt_tabs     = UNDT_Schema::tabs();
$undt_sections = UNDT_Schema::sections();
$undt_option   = UNDT_Store::OPTION_COMPANY;

// Felder nach Tab und Abschnitt gruppieren, aber nur die, die gerade gelten.
$undt_grouped = array();

foreach ( UNDT_Schema::fields() as $undt_key => $undt_field ) {
	if ( ! UNDT_Schema::applies( $undt_field['when'], $undt_profile ) ) {
		continue;
	}

	$undt_grouped[ $undt_field['tab'] ][ $undt_field['section'] ][ $undt_key ] = $undt_field;
}

/*
 * Stand der Pflichtangaben je Tab. Ohne diese Zahl muesste man jeden Tab
 * einzeln oeffnen, um zu sehen, wo noch etwas fehlt.
 */
$undt_progress = array();

foreach ( $undt_grouped as $undt_tab => $undt_tab_sections ) {
	$undt_total = 0;
	$undt_done  = 0;

	foreach ( $undt_tab_sections as $undt_section_fields ) {
		foreach ( $undt_section_fields as $undt_key => $undt_field ) {
			if ( empty( $undt_field['required'] ) ) {
				continue;
			}

			++$undt_total;

			if ( UNDT_Store::has( $undt_key ) ) {
				++$undt_done;
			}
		}
	}

	$undt_progress[ $undt_tab ] = array(
		'total' => $undt_total,
		'done'  => $undt_done,
	);
}

$undt_form  = UNDT_Schema::legal_form( $undt_profile['legal_form'] );
$undt_first = key( $undt_tabs );

?>
<div class="wrap undt-wrap">
	<h1><?php esc_html_e( 'Stammdaten', 'unternehmensdaten' ); ?></h1>

	<p class="undt-context">
		<?php
		printf(
			/* translators: %s: Bezeichnung der Rechtsform. */
			esc_html__( 'Angezeigt werden die Angaben, die für die Rechtsform %s erforderlich sind.', 'unternehmensdaten' ),
			'<strong>' . esc_html( $undt_form['label'] ) . '</strong>'
		);
		?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_PROFILE ) ); ?>"><?php esc_html_e( 'Rechtsform und Umfang ändern', 'unternehmensdaten' ); ?></a>
	</p>

	<?php settings_errors(); ?>

	<form method="post" action="options.php" class="undt-form">
		<?php settings_fields( UNDT_Admin::GROUP_COMPANY ); ?>

		<div class="nav-tab-wrapper undt-tabs" role="tablist">
			<?php foreach ( $undt_tabs as $undt_tab => $undt_label ) : ?>
				<?php if ( empty( $undt_grouped[ $undt_tab ] ) ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<?php
				$undt_bar      = $undt_progress[ $undt_tab ];
				$undt_complete = $undt_bar['total'] > 0 && $undt_bar['done'] >= $undt_bar['total'];
				?>
				<button
					type="button"
					role="tab"
					id="undt-tab-<?php echo esc_attr( $undt_tab ); ?>"
					class="nav-tab<?php echo $undt_tab === $undt_first ? ' nav-tab-active' : ''; ?>"
					aria-controls="undt-panel-<?php echo esc_attr( $undt_tab ); ?>"
					aria-selected="<?php echo $undt_tab === $undt_first ? 'true' : 'false'; ?>"
					title="<?php echo esc_attr( sprintf( /* translators: 1: ausgefüllte Pflichtangaben, 2: Pflichtangaben insgesamt. */ _n( '%1$d von %2$d Pflichtangabe ausgefüllt', '%1$d von %2$d Pflichtangaben ausgefüllt', (int) $undt_bar['total'], 'unternehmensdaten' ), (int) $undt_bar['done'], (int) $undt_bar['total'] ) ); ?>"
				><?php echo esc_html( $undt_label ); ?><?php if ( $undt_bar['total'] > 0 && ! $undt_complete ) : ?><span class="undt-tab-dot" aria-hidden="true"></span><span class="screen-reader-text">
						<?php
						printf(
							/* translators: 1: fehlende Pflichtangaben. */
							esc_html( _n( '%d Pflichtangabe fehlt noch', '%d Pflichtangaben fehlen noch', (int) $undt_bar['total'] - (int) $undt_bar['done'], 'unternehmensdaten' ) ),
							(int) $undt_bar['total'] - (int) $undt_bar['done']
						);
						?>
					</span><?php endif; ?></button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $undt_tabs as $undt_tab => $undt_label ) : ?>
			<?php if ( empty( $undt_grouped[ $undt_tab ] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>

			<div
				id="undt-panel-<?php echo esc_attr( $undt_tab ); ?>"
				class="undt-panel"
				role="tabpanel"
				aria-labelledby="undt-tab-<?php echo esc_attr( $undt_tab ); ?>"
				<?php echo $undt_tab === $undt_first ? '' : 'hidden'; ?>
			>
				<?php foreach ( $undt_grouped[ $undt_tab ] as $undt_section => $undt_section_fields ) : ?>
					<?php $undt_section_basis = UNDT_Fields::section_basis( $undt_section_fields ); ?>

					<h2 class="undt-section-title">
						<?php echo esc_html( isset( $undt_sections[ $undt_section ] ) ? $undt_sections[ $undt_section ] : $undt_section ); ?>
						<?php if ( '' !== $undt_section_basis ) : ?>
							<span class="undt-basis undt-basis--section"><?php echo esc_html( $undt_section_basis ); ?></span>
						<?php endif; ?>
					</h2>

					<?php if ( 'representation' === $undt_section && ! empty( $undt_form['rep_label'] ) ) : ?>
						<p class="description undt-section-hint">
							<?php
							printf(
								/* translators: %s: Bezeichnung der Vertretungsberechtigten. */
								esc_html__( 'Bei dieser Rechtsform sind das: %s.', 'unternehmensdaten' ),
								esc_html( $undt_form['rep_label'] )
							);
							?>
						</p>
					<?php endif; ?>

					<table class="form-table" role="presentation">
						<tbody>
							<?php
							UNDT_Fields::rows(
								$undt_section_fields,
								$undt_company,
								$undt_option,
								array(
									'with_copy'     => true,
									'section_basis' => $undt_section_basis,
								)
							);
							?>
						</tbody>
					</table>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>

		<?php submit_button( __( 'Änderungen speichern', 'unternehmensdaten' ) ); ?>
	</form>
</div>
