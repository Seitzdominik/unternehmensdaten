<?php
/**
 * Kopierknoepfe und Referenztabellen.
 *
 * Shortcodes und die Schreibweisen fuer Bricks und Etch stehen im Backend
 * ueberall dort, wo sie gebraucht werden: unter den Feldern und in der
 * Referenz. Gemeinsam ist ihnen, dass ein Klick den Text in die Zwischenablage
 * legt, siehe initCopy() in admin.js.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Copy
 */
final class UNDT_Copy {

	/**
	 * Eine Referenztabelle aus Ausgabe, Shortcode und Attributen.
	 *
	 * @param array $items Eintraege aus einem Katalog.
	 * @return void
	 */
	public static function table( array $items ) {
		if ( empty( $items ) ) {
			return;
		}

		echo '<table class="widefat striped undt-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Ausgabe', 'unternehmensdaten' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Shortcode', 'unternehmensdaten' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Attribute', 'unternehmensdaten' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $items as $item ) {
			printf(
				'<tr class="undt-searchable" data-undt-text="%s">',
				esc_attr( strtolower( $item['title'] . ' ' . $item['code'] . ' ' . $item['desc'] ) )
			);

			echo '<td><strong>' . esc_html( $item['title'] ) . '</strong>';
			echo '<p class="description">' . esc_html( $item['desc'] ) . '</p></td>';

			echo '<td>';
			self::button( $item['code'] );
			echo '</td>';

			echo '<td class="undt-atts">' . esc_html( $item['atts'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Die Kopierknoepfe unter einem Feld.
	 *
	 * Stammdaten zeigen den Shortcode, daneben stehen die Logos von Bricks und
	 * Etch fuer die jeweilige Schreibweise. Den Tag selbst nennt nur der
	 * Tooltip, damit unter jedem Feld eine Zeile genuegt. Felder der
	 * Inhaltsbereiche haben keinen Shortcode, manche aber einen Wert fuer die
	 * Builder, etwa die Angaben des Infobanners.
	 *
	 * @param string $shortcode Shortcode, leer fuer keinen.
	 * @param string $dynamic   Schluessel fuer Bricks und Etch, leer fuer keinen.
	 * @return void
	 */
	public static function row( $shortcode, $dynamic ) {
		// Nur, was die Builder auch tatsaechlich aufloesen.
		$builder = '' !== $dynamic && array_key_exists( $dynamic, UNDT_Dynamic::fields( UNDT_Dynamic::CONTEXT_BUILDER ) );

		if ( '' === $shortcode && ! $builder ) {
			return;
		}

		echo '<div class="undt-copy-row">';

		if ( '' !== $shortcode ) {
			self::button( $shortcode, 'inline' );
		}

		if ( $builder ) {
			$syntax = UNDT_Dynamic::syntax( $dynamic );

			// Die Logos stehen am rechten Rand des Feldes, mit Abstand zum Shortcode.
			echo '<span class="undt-copy-row__keys">';

			self::icon( $syntax['bricks'], 'bricks', 'Bricks' );
			self::icon( $syntax['etch'], 'etch', 'Etch' );

			echo '</span>';
		}

		echo '</div>';
	}

	/**
	 * Ein Kopierknopf, der statt des Textes nur ein Logo zeigt.
	 *
	 * @param string $text Zu kopierender Text.
	 * @param string $icon Name des Symbols, siehe UNDT_Icons::svg().
	 * @param string $tool Name des Werkzeugs fuer Tooltip und Screenreader.
	 * @return void
	 */
	private static function icon( $text, $icon, $tool ) {
		$label = sprintf(
			/* translators: 1: Werkzeug, etwa Bricks, 2: zu kopierender Tag. */
			__( '%1$s: %2$s kopieren', 'unternehmensdaten' ),
			$tool,
			$text
		);

		// Das Logo sitzt in einem kleinen Rahmen, damit es als Knopf erkennbar ist.
		printf(
			'<button type="button" class="undt-copy undt-copy--icon undt-copy--%1$s" data-undt-copy="%2$s" title="%3$s"><span class="undt-copy__badge" aria-hidden="true">%4$s</span><span class="screen-reader-text">%3$s</span></button>',
			esc_attr( $icon ),
			esc_attr( $text ),
			esc_attr( $label ),
			UNDT_Icons::svg( $icon, 'undt-copy__icon' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes Markup.
		);
	}

	/**
	 * Ein Button, der den uebergebenen Text in die Zwischenablage kopiert.
	 *
	 * Zwei Auspraegungen: „button“ fuer die Referenzseiten, wo das Kopieren die
	 * Hauptsache ist, und „inline“ fuer die Feldbeschriftungen, wo eine
	 * vollwertige Schaltflaeche je Feld die Seite zustellen wuerde.
	 *
	 * @param string $text  Zu kopierender Text.
	 * @param string $style button oder inline.
	 * @return void
	 */
	public static function button( $text, $style = 'button' ) {
		$label = sprintf(
			/* translators: %s: Shortcode, Tag oder Funktionsaufruf. */
			__( '%s kopieren', 'unternehmensdaten' ),
			$text
		);

		if ( 'inline' === $style ) {
			printf(
				'<button type="button" class="undt-copy undt-copy--inline" data-undt-copy="%1$s" title="%2$s"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><code>%3$s</code><span class="screen-reader-text">%2$s</span></button>',
				esc_attr( $text ),
				esc_attr( $label ),
				esc_html( $text )
			);

			return;
		}

		printf(
			'<button type="button" class="button button-small undt-copy" data-undt-copy="%1$s"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <code>%2$s</code><span class="screen-reader-text">%3$s</span></button>',
			esc_attr( $text ),
			esc_html( $text ),
			esc_attr( $label )
		);
	}
}