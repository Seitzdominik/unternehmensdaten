<?php
/**
 * Die Navigation des Backends.
 *
 * Ein Register aller Seiten: Adresse, Titel, Symbol, Gruppe, noetiges Recht.
 * Daraus entstehen die Seiten in WordPress (UNDT_Admin::menu()), die eigene
 * Seitenleiste, die Kopfleiste unter 783 px und das Vollbild-Menue. Eine neue
 * Seite braucht deshalb genau einen Eintrag in pages().
 *
 * Im WordPress-Menue steht nur „Firmendaten“ ohne Unterpunkte. Gefuehrt wird
 * ueber die eigene Seitenleiste; zwei Navigationen nebeneinander wuerden nur
 * verwirren.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Nav
 */
final class UNDT_Nav {

	const HELP_URL = 'https://github.com/Seitzdominik/unternehmensdaten#readme';

	/**
	 * Module, die auch Rollen ohne Admin-Recht pflegen, mit Titel und Symbol.
	 *
	 * Die Reihenfolge ist die des Menues. Suchmaschinen und Module, die per
	 * Filter dazukommen, bleiben Admins vorbehalten.
	 *
	 * @return array
	 */
	private static function module_meta() {
		return array(
			'hours'  => array( __( 'Öffnungszeiten', 'unternehmensdaten' ), 'clock', 'edit' ),
			'prices' => array( __( 'Preise & Leistungen', 'unternehmensdaten' ), 'tag', 'edit' ),
			'social' => array( __( 'Social Media', 'unternehmensdaten' ), 'share', 'edit' ),
			'faq'    => array( __( 'FAQ', 'unternehmensdaten' ), 'chat', 'edit' ),
			'banner' => array( __( 'Infobanner', 'unternehmensdaten' ), 'megaphone', 'edit' ),
			'seo'    => array( __( 'Suchmaschinen', 'unternehmensdaten' ), 'search', 'admin' ),
		);
	}

	/**
	 * Welche Stufe ein Modul verlangt.
	 *
	 * @param string $slug Modul.
	 * @return string edit oder admin.
	 */
	public static function module_access( $slug ) {
		$meta = self::module_meta();

		return isset( $meta[ $slug ] ) ? $meta[ $slug ][2] : 'admin';
	}

	/**
	 * Die Gruppen der Seitenleiste.
	 *
	 * @return array
	 */
	public static function groups() {
		return array(
			''      => '',
			'setup' => __( 'Einrichten', 'unternehmensdaten' ),
			'use'   => __( 'Verwenden', 'unternehmensdaten' ),
			'more'  => __( 'Mehr', 'unternehmensdaten' ),
		);
	}

	/**
	 * Ein Eintrag im Register.
	 *
	 * @param string $slug   Adresse unter admin.php?page=.
	 * @param string $title  Titel.
	 * @param string $icon   Symbol.
	 * @param string $group  Gruppe.
	 * @param string $access edit oder admin.
	 * @param string $view   Ansicht in admin/views.
	 * @param array  $extra  Weitere Angaben: module, hidden, url.
	 * @return array
	 */
	private static function page( $slug, $title, $icon, $group, $access, $view, array $extra = array() ) {
		return array_merge(
			array(
				'slug'   => $slug,
				'title'  => $title,
				'icon'   => $icon,
				'group'  => $group,
				'access' => $access,
				'view'   => $view,
				'module' => '',
				'hidden' => false,
				'url'    => '',
			),
			$extra
		);
	}

	/**
	 * Alle Seiten in der Reihenfolge des Menues.
	 *
	 * @return array Schluessel ist der Name der Seite.
	 */
	public static function pages() {
		$pages = array(
			'overview' => self::page( UNDT_Admin::SLUG_OVERVIEW, __( 'Übersicht', 'unternehmensdaten' ), 'grid', '', 'edit', 'overview' ),
			'company'  => self::page( UNDT_Admin::SLUG_COMPANY, __( 'Firma', 'unternehmensdaten' ), 'buildings', 'setup', 'edit', 'company' ),
		);

		$meta    = self::module_meta();
		$modules = array();

		foreach ( UNDT_Modules::all() as $slug => $module ) {
			if ( ! UNDT_Modules::is_active( $slug ) ) {
				continue;
			}

			$known = isset( $meta[ $slug ] );

			$modules[ 'm_' . $slug ] = self::page(
				UNDT_Admin::module_slug( $slug ),
				$known ? $meta[ $slug ][0] : $module['label'],
				$known ? $meta[ $slug ][1] : 'file',
				'setup',
				self::module_access( $slug ),
				'module',
				array( 'module' => $slug )
			);
		}

		// Bekannte Module in fester Reihenfolge, Suchmaschinen zuletzt, eigene davor.
		$order = array_keys( $meta );

		uksort(
			$modules,
			static function ( $a, $b ) use ( $order ) {
				$rank_a = array_search( substr( $a, 2 ), $order, true );
				$rank_b = array_search( substr( $b, 2 ), $order, true );
				$rank_a = false === $rank_a ? 4.5 : ( 'seo' === substr( $a, 2 ) ? 9 : $rank_a );
				$rank_b = false === $rank_b ? 4.5 : ( 'seo' === substr( $b, 2 ) ? 9 : $rank_b );

				return $rank_a <=> $rank_b;
			}
		);

		$pages = array_merge( $pages, $modules );

		$pages['embed']    = self::page( UNDT_Admin::SLUG_EMBED, __( 'Einbinden', 'unternehmensdaten' ), 'code', 'use', 'admin', 'embed' );
		$pages['transfer'] = self::page( UNDT_Admin::SLUG_TRANSFER, __( 'Sichern & Übertragen', 'unternehmensdaten' ), 'transfer', 'more', 'admin', 'transfer' );
		$pages['settings'] = self::page( UNDT_Admin::SLUG_SETTINGS, __( 'Einstellungen', 'unternehmensdaten' ), 'gear', 'more', 'admin', 'settings' );
		$pages['help']     = self::page( '', __( 'Hilfe', 'unternehmensdaten' ), 'help', 'more', 'edit', '', array( 'url' => self::HELP_URL ) );

		return $pages;
	}

	/**
	 * Die Seiten, die der aktuelle Benutzer sehen darf.
	 *
	 * @param bool $with_hidden Ob Seiten ohne Menueeintrag dabei sind.
	 * @return array
	 */
	public static function visible( $with_hidden = false ) {
		$visible = array();

		foreach ( self::pages() as $key => $page ) {
			if ( $page['hidden'] && ! $with_hidden ) {
				continue;
			}

			if ( current_user_can( UNDT_Access::cap_for( $page['access'] ) ) ) {
				$visible[ $key ] = $page;
			}
		}

		return $visible;
	}

	/**
	 * Die Adresse einer Seite.
	 *
	 * @param array $page Eintrag.
	 * @return string
	 */
	public static function url( array $page ) {
		return '' !== $page['url'] ? $page['url'] : admin_url( 'admin.php?page=' . $page['slug'] );
	}

	/**
	 * Die Zaehler der Seiten.
	 *
	 * Nur die abfragefreie Zaehlung, damit die Navigation keine Datenbanklast
	 * erzeugt. Vor der Einrichtung gibt es nichts zu zaehlen, denn erst die
	 * Rechtsform legt fest, was Pflicht ist.
	 *
	 * @return array Name der Seite => Anzahl.
	 */
	public static function badges() {
		return array(
			'company' => UNDT_Store::is_set_up() ? UNDT_Audit::quick_count() : 0,
		);
	}

	/**
	 * Oeffnet die Huelle einer Seite: Seitenleiste, Kopfleiste, Inhaltsbereich.
	 *
	 * @param string $current Name der aktuellen Seite.
	 * @return void
	 */
	public static function open( $current ) {
		$pages  = self::visible();
		$all    = self::visible( true );
		$badges = self::badges();
		$title  = isset( $all[ $current ] ) ? $all[ $current ]['title'] : '';
		$total  = array_sum( array_intersect_key( $badges, $pages ) );

		echo '<div class="undt-app">';

		// Seitenleiste ab 783 px.
		echo '<aside class="undt-side">';
		self::brand( 'undt-side__head' );
		self::nav( $pages, $current, $badges, false );
		self::quick( false );
		echo '</aside>';

		echo '<div class="undt-app__main">';

		// Kopfleiste unter 783 px.
		echo '<div class="undt-mbar">';
		UNDT_Ui::tile( 'buildings', 0, 'active' );
		echo '<span class="undt-mbar__title">' . esc_html( $title ) . '</span>';
		printf(
			'<button type="button" class="undt-mbar__menu" aria-expanded="false" aria-controls="undt-mmenu" data-undt-menu-open>%s<span data-undt-badge-total>%s</span></button>',
			esc_html__( 'Menü', 'unternehmensdaten' ),
			UNDT_Ui::badge_html( $total ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in badge_html() escaped.
		);
		echo '</div>';

		echo '<main class="undt-main" id="undt-main">';
	}

	/**
	 * Schliesst die Huelle und haengt das Vollbild-Menue an.
	 *
	 * @param string $current Name der aktuellen Seite.
	 * @return void
	 */
	public static function close( $current ) {
		echo '</main></div>';

		printf(
			'<div class="undt-mmenu" id="undt-mmenu" role="dialog" aria-modal="true" aria-label="%s" hidden>',
			esc_attr__( 'Menü', 'unternehmensdaten' )
		);
		echo '<div class="undt-mmenu__head">';
		UNDT_Ui::tile( 'buildings', 0, 'active' );
		echo '<span class="undt-mmenu__name">' . esc_html__( 'Firmendaten', 'unternehmensdaten' ) . '</span>';
		printf(
			'<button type="button" class="undt-mmenu__close" aria-label="%s" data-undt-menu-close>',
			esc_attr__( 'Menü schließen', 'unternehmensdaten' )
		);
		UNDT_Ui::icon( 'x', 18 );
		echo '</button></div>';
		self::nav( self::visible(), $current, self::badges(), true );
		self::quick( true );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Kopf der Seitenleiste: Zeichen, Name, Version.
	 *
	 * @param string $class Klasse.
	 * @return void
	 */
	private static function brand( $class ) {
		echo '<div class="' . esc_attr( $class ) . '">';
		UNDT_Ui::tile( 'buildings', 0, 'active' );
		echo '<div class="undt-side__brand"><div class="undt-side__name">' . esc_html__( 'Firmendaten', 'unternehmensdaten' ) . '</div>';
		printf(
			'<div class="undt-side__version">%s</div></div>',
			/* translators: %s: Versionsnummer. */
			esc_html( sprintf( __( 'Version %s', 'unternehmensdaten' ), UNDT_VERSION ) )
		);
		echo '</div>';
	}

	/**
	 * Die Liste der Seiten in Gruppen.
	 *
	 * @param array  $pages   Sichtbare Seiten.
	 * @param string $current Aktuelle Seite.
	 * @param array  $badges  Zaehler.
	 * @param bool   $mobile  Ob fuer das Vollbild-Menue.
	 * @return void
	 */
	private static function nav( array $pages, $current, array $badges, $mobile ) {
		printf(
			'<nav class="undt-nav%s" aria-label="%s">',
			$mobile ? ' undt-nav--mobile' : '',
			esc_attr__( 'Firmendaten', 'unternehmensdaten' )
		);

		foreach ( self::groups() as $group => $label ) {
			$items = array_filter(
				$pages,
				static function ( $page ) use ( $group ) {
					return $page['group'] === $group;
				}
			);

			if ( empty( $items ) ) {
				continue;
			}

			if ( '' !== $label ) {
				echo '<div class="undt-nav__group">' . esc_html( $label ) . '</div>';
			}

			foreach ( $items as $key => $page ) {
				self::item( $key, $page, $key === $current, isset( $badges[ $key ] ) ? (int) $badges[ $key ] : 0, $mobile );
			}
		}

		echo '</nav>';
	}

	/**
	 * Ein Eintrag der Navigation.
	 *
	 * @param string $key     Name der Seite.
	 * @param array  $page    Eintrag.
	 * @param bool   $active  Ob es die aktuelle Seite ist.
	 * @param int    $count   Zaehler.
	 * @param bool   $mobile  Ob fuer das Vollbild-Menue.
	 * @return void
	 */
	private static function item( $key, array $page, $active, $count, $mobile ) {
		$external = '' !== $page['url'];

		printf(
			'<a class="undt-nav__item%1$s" href="%2$s"%3$s%4$s>',
			$active ? ' is-current' : '',
			esc_url( self::url( $page ) ),
			$active ? ' aria-current="page"' : '',
			$external ? ' target="_blank" rel="noopener noreferrer"' : ''
		);

		UNDT_Ui::tile( $page['icon'], $count, $active ? 'active' : '', $key );

		echo '<span class="undt-nav__label">' . esc_html( $page['title'] ) . '</span>';

		if ( $external ) {
			UNDT_Ui::icon( 'out', 13, 'undt-nav__arrow' );
			echo '<span class="screen-reader-text"> ' . esc_html__( '(öffnet GitHub in einem neuen Tab)', 'unternehmensdaten' ) . '</span>';
		} elseif ( $mobile ) {
			UNDT_Ui::icon( 'caret', 13, 'undt-nav__arrow' );
		}

		echo '</a>';
	}

	/**
	 * Die Schnelleinstellungen am Fuss der Navigation.
	 *
	 * Infobanner zeigen fuer alle, die das Banner pflegen; „Kopieren als“ nur
	 * fuer Admins, denn nur sie sehen Kopierknoepfe. Im Vollbild-Menue steht nur
	 * der Banner-Schalter.
	 *
	 * @param bool $mobile Ob fuer das Vollbild-Menue.
	 * @return void
	 */
	private static function quick( $mobile ) {
		$banner = UNDT_Modules::is_active( 'banner' ) && UNDT_Access::can_edit();
		$copy   = ! $mobile && UNDT_Access::is_admin();

		if ( ! $banner && ! $copy ) {
			return;
		}

		$suffix = $mobile ? '-m' : '';

		echo '<div class="undt-quick">';
		echo '<div class="undt-quick__title">' . esc_html__( 'Schnelleinstellungen', 'unternehmensdaten' ) . '</div>';

		if ( $banner ) {
			echo '<div class="undt-quick__row">';
			printf( '<label class="undt-quick__label" for="undt-quick-banner%s">%s</label>', esc_attr( $suffix ), esc_html__( 'Infobanner zeigen', 'unternehmensdaten' ) );
			UNDT_Ui::switch_button( 'undt-quick-banner' . $suffix, (bool) UNDT_Content::value( 'banner', 'enabled' ), array( 'undt-quick' => 'banner' ) );
			echo '</div>';
		}

		if ( $copy ) {
			$formats  = UNDT_Copy::formats();
			$chosen   = UNDT_Copy::format();
			$detected = '';

			foreach ( $formats as $format ) {
				if ( $format['active'] ) {
					$detected = $format['label'];
				}
			}

			$tip = __( 'In diesem Format kopiert der Knopf neben jeder Angabe ihren Platzhalter.', 'unternehmensdaten' );

			if ( '' !== $detected ) {
				/* translators: %s: Name des Werkzeugs, etwa Etch. */
				$tip .= ' ' . sprintf( __( 'Erkannt wurde %s.', 'unternehmensdaten' ), $detected );
			}

			echo '<div class="undt-quick__row">';
			printf(
				'<label class="undt-quick__label" for="undt-quick-format"><span class="undt-term" title="%s">%s</span></label>',
				esc_attr( $tip ),
				esc_html__( 'Kopieren als', 'unternehmensdaten' )
			);
			echo '<select id="undt-quick-format" class="undt-quick__select" data-undt-quick="copy_format">';

			foreach ( $formats as $key => $format ) {
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $key ),
					selected( $chosen, $key, false ),
					esc_html( $format['label'] )
				);
			}

			echo '</select></div>';
		}

		echo '</div>';
	}
}
