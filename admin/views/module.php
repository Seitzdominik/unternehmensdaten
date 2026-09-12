<?php
/**
 * Seite: ein Inhaltsmodul.
 *
 * Eine Ansicht fuer alle Module. Was hier erscheint, steht vollstaendig im
 * Modul-Register.
 *
 * Erwartet die Variable $undt_slug aus UNDT_Admin::render_module().
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

$undt_module = isset( $undt_slug ) ? UNDT_Modules::get( $undt_slug ) : null;

if ( null === $undt_module ) {
	return;
}

$undt_data = UNDT_Content::all( $undt_slug );
?>
<div class="wrap undt-wrap">
	<h1><?php echo esc_html( $undt_module['label'] ); ?></h1>

	<?php settings_errors(); ?>

	<?php if ( 'seo' === $undt_slug ) : ?>
		<?php $undt_seo_plugin = UNDT_SchemaOrg::detect_seo_plugin(); ?>
		<?php if ( '' !== $undt_seo_plugin ) : ?>
			<div class="notice notice-info inline undt-disclaimer">
				<p>
					<?php
					printf(
						/* translators: %s: Name des erkannten SEO-Plugins. */
						esc_html__( '%s ist aktiv und gibt bereits eine Organization-Auszeichnung aus. In der Voreinstellung hält sich dieses Plugin deshalb zurück.', 'unternehmensdaten' ),
						'<strong>' . esc_html( $undt_seo_plugin ) . '</strong>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( 'banner' === $undt_slug && ! UNDT_Content::value( 'banner', 'enabled' ) ) : ?>
		<div class="notice notice-info inline undt-disclaimer">
			<p><?php esc_html_e( 'Das Banner ist derzeit abgeschaltet und erscheint nirgends auf der Website.', 'unternehmensdaten' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php" class="undt-form">
		<?php settings_fields( UNDT_Admin::module_group( $undt_slug ) ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<?php
				// Einzelfeld-Shortcodes gibt es nur für die Stammdaten.
				UNDT_Fields::rows(
					$undt_module['fields'],
					$undt_data,
					$undt_module['option'],
					array( 'with_copy' => false )
				);
				?>
			</tbody>
		</table>

		<?php submit_button(); ?>
	</form>

	<?php
	$undt_own = array_filter(
		UNDT_Shortcodes::module_catalog(),
		static function ( $item ) use ( $undt_slug ) {
			return 0 === strpos( $item['code'], '[undt_' . $undt_slug )
				|| ( 'hours' === $undt_slug && ( 0 === strpos( $item['code'], '[undt_open_now' ) ) );
		}
	);
	?>

	<?php if ( ! empty( $undt_own ) ) : ?>
		<h2 class="undt-section-title"><?php esc_html_e( 'Shortcodes dieses Bereichs', 'unternehmensdaten' ); ?></h2>

		<table class="widefat striped undt-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Ausgabe', 'unternehmensdaten' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Shortcode', 'unternehmensdaten' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Attribute', 'unternehmensdaten' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $undt_own as $undt_item ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $undt_item['title'] ); ?></strong>
							<p class="description"><?php echo esc_html( $undt_item['desc'] ); ?></p>
						</td>
						<td><?php UNDT_Fields::copy_button( $undt_item['code'] ); ?></td>
						<td class="undt-atts"><?php echo esc_html( $undt_item['atts'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
