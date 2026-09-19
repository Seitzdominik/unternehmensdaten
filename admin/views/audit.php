<?php
/**
 * Seite: Prüfung.
 *
 * Checkliste gegen die Pflichten, die sich aus dem gewaehlten Profil ergeben,
 * einschliesslich eines Scans der verknuepften Rechtsseiten auf veraltete
 * Rechtsgrundlagen. Diese Abfragen laufen ausschliesslich hier.
 *
 * Die Punkte stehen bewusst in einer Tabelle und nicht in je einer notice-Box:
 * bei einem Dutzend offener Punkte waere die Seite sonst mehrere Bildschirme
 * lang, ohne mehr auszusagen.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

$undt_issues = UNDT_Audit::run();
$undt_counts = UNDT_Audit::summary( $undt_issues );
$undt_tabs   = UNDT_Schema::tabs();
$undt_target = admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_COMPANY );

$undt_levels = array(
	'error'   => array(
		'label'  => __( 'fehlende Pflichtangabe', 'unternehmensdaten' ),
		'plural' => __( 'fehlende Pflichtangaben', 'unternehmensdaten' ),
		'short'  => __( 'Pflicht', 'unternehmensdaten' ),
		'order' => 0,
	),
	'warning' => array(
		'label'  => __( 'dringender Punkt', 'unternehmensdaten' ),
		'plural' => __( 'dringende Punkte', 'unternehmensdaten' ),
		'short'  => __( 'Prüfen', 'unternehmensdaten' ),
		'order' => 1,
	),
	'notice'  => array(
		'label'  => __( 'Hinweis', 'unternehmensdaten' ),
		'plural' => __( 'Hinweise', 'unternehmensdaten' ),
		'short'  => __( 'Hinweis', 'unternehmensdaten' ),
		'order' => 2,
	),
);

// Schwerste Punkte zuerst, innerhalb einer Stufe in der gefundenen Reihenfolge.
usort(
	$undt_issues,
	static function ( $a, $b ) use ( $undt_levels ) {
		$rank_a = isset( $undt_levels[ $a['level'] ] ) ? $undt_levels[ $a['level'] ]['order'] : 9;
		$rank_b = isset( $undt_levels[ $b['level'] ] ) ? $undt_levels[ $b['level'] ]['order'] : 9;

		return $rank_a <=> $rank_b;
	}
);
?>
<div class="wrap undt-wrap">
	<h1><?php esc_html_e( 'Prüfung', 'unternehmensdaten' ); ?></h1>

	<?php if ( empty( $undt_issues ) ) : ?>
		<div class="notice notice-success inline">
			<p>
				<strong><?php esc_html_e( 'Keine offenen Punkte.', 'unternehmensdaten' ); ?></strong>
				<?php esc_html_e( 'Alle Angaben, die sich aus dem gewählten Profil ergeben, sind hinterlegt, und auf den verknüpften Rechtsseiten wurden keine veralteten Rechtsgrundlagen gefunden.', 'unternehmensdaten' ); ?>
			</p>
		</div>
	<?php else : ?>
		<p class="undt-audit-summary">
			<?php foreach ( $undt_levels as $undt_level => $undt_meta ) : ?>
				<?php if ( empty( $undt_counts[ $undt_level ] ) ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<span class="undt-audit-tally">
					<span class="undt-dot undt-dot--<?php echo esc_attr( $undt_level ); ?>" aria-hidden="true"></span>
					<strong><?php echo (int) $undt_counts[ $undt_level ]; ?></strong>
					<?php echo esc_html( 1 === (int) $undt_counts[ $undt_level ] ? $undt_meta['label'] : $undt_meta['plural'] ); ?>
				</span>
			<?php endforeach; ?>
		</p>

		<div class="undt-box"><div class="undt-box__body">

		<table class="widefat striped undt-table undt-audit">
			<thead>
				<tr>
					<th scope="col" class="undt-audit__level"><?php esc_html_e( 'Stufe', 'unternehmensdaten' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Punkt', 'unternehmensdaten' ); ?></th>
					<th scope="col" class="undt-audit__action"><?php esc_html_e( 'Aktion', 'unternehmensdaten' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $undt_issues as $undt_issue ) : ?>
					<?php $undt_meta = isset( $undt_levels[ $undt_issue['level'] ] ) ? $undt_levels[ $undt_issue['level'] ] : $undt_levels['notice']; ?>
					<tr class="undt-audit__row undt-audit__row--<?php echo esc_attr( $undt_issue['level'] ); ?>">
						<td class="undt-audit__level">
							<span class="undt-dot undt-dot--<?php echo esc_attr( $undt_issue['level'] ); ?>" aria-hidden="true"></span>
							<span class="undt-audit__level-text"><?php echo esc_html( $undt_meta['short'] ); ?></span>
						</td>
						<td>
							<strong><?php echo esc_html( $undt_issue['title'] ); ?></strong>

							<?php if ( ! empty( $undt_issue['basis'] ) ) : ?>
								<span class="undt-basis undt-basis--inline"><?php echo esc_html( $undt_issue['basis'] ); ?></span>
							<?php endif; ?>

							<?php if ( ! empty( $undt_issue['detail'] ) ) : ?>
								<p class="description"><?php echo esc_html( $undt_issue['detail'] ); ?></p>
							<?php endif; ?>
						</td>
						<td class="undt-audit__action">
							<?php if ( ! empty( $undt_issue['edit'] ) ) : ?>
								<a href="<?php echo esc_url( $undt_issue['edit'] ); ?>"><?php esc_html_e( 'Seite bearbeiten', 'unternehmensdaten' ); ?></a>
							<?php elseif ( ! empty( $undt_issue['tab'] ) && isset( $undt_tabs[ $undt_issue['tab'] ] ) ) : ?>
								<a href="<?php echo esc_url( $undt_target . '#' . $undt_issue['tab'] ); ?>">
									<?php echo esc_html( $undt_tabs[ $undt_issue['tab'] ] ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		</div></div>
	<?php endif; ?>

	<h2 class="undt-section-title"><?php esc_html_e( 'Was geprüft wird', 'unternehmensdaten' ); ?></h2>
	<div class="undt-box"><div class="undt-box__body">
	<ul class="undt-list">
		<li><?php esc_html_e( 'Alle Pflichtangaben nach § 5 DDG, soweit sie sich aus der gewählten Rechtsform ergeben.', 'unternehmensdaten' ); ?></li>
		<li><?php esc_html_e( 'Der zweite Kommunikationsweg neben der E-Mail-Adresse.', 'unternehmensdaten' ); ?></li>
		<li><?php esc_html_e( 'Berufsrechtliche Angaben bei reglementierten Berufen, einschließlich der Angabe, wo die Regelungen zugänglich sind.', 'unternehmensdaten' ); ?></li>
		<li><?php esc_html_e( 'Angaben zur Berufshaftpflichtversicherung nach der DL-InfoV, sofern eine besteht.', 'unternehmensdaten' ); ?></li>
		<li><?php esc_html_e( 'Der Verantwortliche nach § 18 Abs. 2 MStV bei redaktionellen Inhalten.', 'unternehmensdaten' ); ?></li>
		<li><?php esc_html_e( 'Der Hinweis zur Verbraucherstreitbeilegung nach § 36 VSBG.', 'unternehmensdaten' ); ?></li>
		<li><?php esc_html_e( 'Die Erklärung zur Barrierefreiheit nach dem BFSG.', 'unternehmensdaten' ); ?></li>
		<li><?php esc_html_e( 'Veraltete Rechtsgrundlagen und der abgeschaltete Link zur EU-Streitbeilegungsplattform auf den verknüpften Rechtsseiten.', 'unternehmensdaten' ); ?></li>
	</ul>
	</div></div>

	<div class="notice notice-warning inline undt-disclaimer">
		<p>
			<strong><?php esc_html_e( 'Keine Rechtsberatung.', 'unternehmensdaten' ); ?></strong>
			<?php esc_html_e( 'Diese Prüfung ist eine Checkliste, kein Gutachten. Sie kann weder alle Konstellationen abdecken noch beurteilen, ob die eingetragenen Werte inhaltlich richtig sind. Rechtsstand der hinterlegten Hinweise: September 2026.', 'unternehmensdaten' ); ?>
		</p>
	</div>
</div>
