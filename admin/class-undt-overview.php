<?php
/**
 * Die Daten der Uebersicht.
 *
 * Statuszeile, Kacheln je Bereich, die Karte „Einbinden“ und der Erststart.
 * Die Ansicht admin/views/overview.php gibt nur noch aus, was hier steht.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Overview
 */
final class UNDT_Overview {

	/**
	 * Zustand des Erststarts: uebersprungen, abgeschlossen, und welche der
	 * Fragen-Schritte erledigt sind.
	 */
	const OPTION_ONBOARDING = 'undt_onboarding';

	/**
	 * Der gespeicherte Zustand des Erststarts.
	 *
	 * @return array skipped, done.
	 */
	public static function onboarding_state() {
		$stored = get_option( self::OPTION_ONBOARDING, null );

		/*
		 * Seiten, die schon vor dem Erststart eingerichtet waren, kennen die
		 * Option nicht. Sie gelten als fertig. Neue Seiten legen die Option beim
		 * ersten Speichern der Rechtsform an, siehe start_onboarding().
		 */
		if ( null === $stored ) {
			$stored = UNDT_Store::is_set_up() ? array( 'done' => 1 ) : array();
		}

		$stored = is_array( $stored ) ? $stored : array();

		return array_merge(
			array(
				'skipped' => 0,
				'done'    => 0,
			),
			$stored
		);
	}

	/**
	 * Legt den Zustand des Erststarts an, wenn die Rechtsform zum ersten Mal
	 * gespeichert wird. Ab dann laeuft er weiter, bis alle Schritte erledigt sind.
	 *
	 * @return void
	 */
	public static function start_onboarding() {
		if ( null === get_option( self::OPTION_ONBOARDING, null ) ) {
			update_option(
				self::OPTION_ONBOARDING,
				array(
					'skipped' => 0,
					'done'    => 0,
				),
				false
			);
		}
	}

	/**
	 * Haelt den Erststart als uebersprungen oder abgeschlossen fest.
	 *
	 * @param string $what skipped oder done.
	 * @return void
	 */
	public static function mark( $what ) {
		if ( ! in_array( $what, array( 'skipped', 'done' ), true ) ) {
			return;
		}

		$state          = self::onboarding_state();
		$state[ $what ] = 1;

		update_option( self::OPTION_ONBOARDING, $state, false );
	}

	/**
	 * Die vier Schritte des Erststarts.
	 *
	 * Rechtsform und Fragen stehen bis Etappe 3 auf einer Seite und werden mit
	 * einem Speichern zusammen erledigt.
	 *
	 * @return array Liste aus array( key, title, desc, state, action ).
	 */
	public static function steps() {
		$set_up  = UNDT_Store::is_set_up();
		$missing = $set_up ? UNDT_Audit::quick_count() : -1;
		$profile = UNDT_Store::profile();
		$form    = UNDT_Schema::legal_form( $profile['legal_form'] );
		$imprint = self::imprint_embedded();

		$done = array(
			'form'      => $set_up,
			'questions' => $set_up,
			'required'  => $set_up && 0 === $missing,
			'imprint'   => $imprint,
		);

		$profile_url = admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_COMPANY ) . '#rechtsform';
		$first_field = self::first_missing_field();

		$steps = array(
			'form'      => array(
				'title'  => __( 'Rechtsform wählen', 'unternehmensdaten' ),
				'desc'   => $set_up ? $form['label'] : __( 'Davon hängt ab, welche Angaben für dich Pflicht sind.', 'unternehmensdaten' ),
				'button' => __( 'Rechtsform wählen', 'unternehmensdaten' ),
				'url'    => $profile_url,
			),
			'questions' => array(
				'title'  => __( 'Ein paar Fragen beantworten', 'unternehmensdaten' ),
				'desc'   => __( 'Etwa ob du einen reglementierten Beruf ausübst oder einen Blog hast. Danach siehst du nur die Angaben, die du wirklich brauchst.', 'unternehmensdaten' ),
				'button' => __( 'Fragen beantworten', 'unternehmensdaten' ),
				'url'    => $profile_url,
			),
			'required'  => array(
				'title'  => __( 'Pflichtangaben eintragen', 'unternehmensdaten' ),
				'desc'   => __( 'Name, Anschrift, Kontakt und was für deine Rechtsform dazugehört.', 'unternehmensdaten' ),
				'button' => __( 'Angaben eintragen', 'unternehmensdaten' ),
				'url'    => '' !== $first_field ? UNDT_Todo::field_url( $first_field ) : admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_COMPANY ),
			),
			'imprint'   => array(
				'title'  => __( 'Impressum einbinden', 'unternehmensdaten' ),
				'desc'   => __( 'Den fertigen Baustein auf deiner Impressum-Seite einfügen.', 'unternehmensdaten' ),
				'button' => __( 'Baustein ansehen', 'unternehmensdaten' ),
				'url'    => admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_EMBED ),
			),
		);

		$current = '';
		$list    = array();

		foreach ( $steps as $key => $step ) {
			if ( $done[ $key ] ) {
				$state = 'done';
			} elseif ( '' === $current ) {
				$state   = 'current';
				$current = $key;
			} else {
				$state = 'open';
			}

			$list[] = array(
				'key'    => $key,
				'title'  => $step['title'],
				'desc'   => $step['desc'],
				'state'  => $state,
				'action' => sprintf( '<a class="undt-btn" href="%s">%s</a>', esc_url( $step['url'] ), esc_html( $step['button'] ) ),
			);
		}

		return $list;
	}

	/**
	 * Wie viele Schritte erledigt sind.
	 *
	 * @param array $steps Ergebnis von steps().
	 * @return int
	 */
	public static function steps_done( array $steps ) {
		return count(
			array_filter(
				$steps,
				static function ( $step ) {
					return 'done' === $step['state'];
				}
			)
		);
	}

	/**
	 * Ob die Uebersicht den Erststart zeigt.
	 *
	 * Nur Admins richten ein. Wer uebersprungen hat oder fertig war, sieht ihn
	 * nicht wieder, auch wenn spaeter ein Feld geleert wird.
	 *
	 * @param array|null $steps Ergebnis von steps(), null fuer neu berechnen.
	 * @return bool
	 */
	public static function show_onboarding( $steps = null ) {
		if ( ! UNDT_Access::is_admin() ) {
			return false;
		}

		$state = self::onboarding_state();

		if ( ! empty( $state['skipped'] ) || ! empty( $state['done'] ) ) {
			return false;
		}

		$steps = null === $steps ? self::steps() : $steps;

		return self::steps_done( $steps ) < count( $steps );
	}

	/**
	 * Haelt fest, dass der Erststart fertig ist, sobald alle Schritte erledigt sind.
	 *
	 * Laeuft beim Speichern, nie beim blossen Aufruf einer Seite.
	 *
	 * @return void
	 */
	public static function maybe_complete() {
		$state = self::onboarding_state();

		if ( ! empty( $state['done'] ) || ! empty( $state['skipped'] ) ) {
			return;
		}

		$steps = self::steps();

		if ( self::steps_done( $steps ) === count( $steps ) ) {
			self::mark( 'done' );
		}
	}

	/**
	 * Ob das Impressum eingebunden ist: auf der verknuepften Seite, oder, wenn
	 * keine verknuepft ist, ueberhaupt irgendwo.
	 *
	 * @return bool
	 */
	public static function imprint_embedded() {
		$id = UNDT_Store::page_id( 'page_imprint' );

		if ( $id > 0 ) {
			return UNDT_Usage::on_post( $id, 'imprint' );
		}

		return ! empty( UNDT_Usage::find( 'imprint' ) );
	}

	/**
	 * Das erste fehlende Pflichtfeld, leer wenn keins fehlt.
	 *
	 * @return string
	 */
	private static function first_missing_field() {
		$profile = UNDT_Store::profile();

		foreach ( UNDT_Schema::fields() as $key => $field ) {
			if ( ! empty( $field['required'] ) && UNDT_Schema::applies( $field['when'], $profile ) && ! UNDT_Store::has( $key ) ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * Die Statuszeile oben.
	 *
	 * @return array done, title, text, imprint (url, title, modified) oder null.
	 */
	public static function status() {
		$missing = UNDT_Audit::quick_count();
		$profile = UNDT_Store::profile();
		$form    = UNDT_Schema::legal_form( $profile['legal_form'] );

		if ( $missing > 0 ) {
			return array(
				'done'    => false,
				'title'   => __( 'Dein Impressum ist noch nicht vollständig', 'unternehmensdaten' ),
				'text'    => sprintf(
					/* translators: %d: Anzahl fehlender Pflichtangaben. */
					_n( '%d Pflichtangabe fehlt. Was du schon eingetragen hast, wird bereits auf deiner Website angezeigt.', '%d Pflichtangaben fehlen. Was du schon eingetragen hast, wird bereits auf deiner Website angezeigt.', $missing, 'unternehmensdaten' ),
					$missing
				),
				'imprint' => null,
			);
		}

		$id      = UNDT_Store::page_id( 'page_imprint' );
		$imprint = null;

		if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
			$imprint = array(
				'url'      => (string) get_permalink( $id ),
				'title'    => get_the_title( $id ),
				'modified' => (string) get_the_modified_date( 'j.n.', $id ),
			);
		}

		return array(
			'done'    => true,
			'title'   => __( 'Dein Impressum ist vollständig', 'unternehmensdaten' ),
			'text'    => sprintf(
				/* translators: %s: Bezeichnung der Rechtsform. */
				__( 'Alle Pflichtangaben für „%s“ sind eingetragen.', 'unternehmensdaten' ),
				$form['label']
			),
			'imprint' => $imprint,
		);
	}

	/**
	 * Eine Kachel je Bereich, den der Benutzer pflegen darf.
	 *
	 * @return array Liste von Argumenten fuer UNDT_Ui::link_card().
	 */
	public static function tiles() {
		$tiles = array();

		foreach ( UNDT_Nav::visible() as $key => $page ) {
			if ( 'company' === $key ) {
				$tiles[] = self::company_tile( $page );
			} elseif ( '' !== $page['module'] ) {
				$tiles[] = self::module_tile( $page );
			}
		}

		return $tiles;
	}

	/**
	 * Die Kachel „Firma“.
	 *
	 * @param array $page Eintrag aus UNDT_Nav.
	 * @return array
	 */
	private static function company_tile( array $page ) {
		$profile = UNDT_Store::profile();
		$form    = UNDT_Schema::legal_form( $profile['legal_form'] );
		$missing = UNDT_Store::is_set_up() ? UNDT_Audit::quick_count() : 0;
		$sub     = $form['label'];

		if ( ! empty( $profile['is_medical'] ) ) {
			$sub .= ' · ' . __( 'Heilberuf', 'unternehmensdaten' );
		} elseif ( ! empty( $profile['is_regulated'] ) ) {
			$sub .= ' · ' . __( 'reglementierter Beruf', 'unternehmensdaten' );
		}

		return array(
			'href'  => UNDT_Nav::url( $page ),
			'icon'  => $page['icon'],
			'title' => $page['title'],
			'sub'   => $sub,
			'line'  => $missing > 0
				/* translators: %d: Anzahl fehlender Pflichtangaben. */
				? sprintf( _n( '%d Pflichtangabe fehlt', '%d Pflichtangaben fehlen', $missing, 'unternehmensdaten' ), $missing )
				: __( 'Alle Pflichtangaben eingetragen', 'unternehmensdaten' ),
			'tone'  => $missing > 0 ? 'open' : '',
			'count' => $missing,
		);
	}

	/**
	 * Die Kachel eines Inhaltsbereichs.
	 *
	 * @param array $page Eintrag aus UNDT_Nav.
	 * @return array
	 */
	private static function module_tile( array $page ) {
		$tile = array(
			'href'  => UNDT_Nav::url( $page ),
			'icon'  => $page['icon'],
			'title' => $page['title'],
			'sub'   => '',
			'line'  => '',
			'tone'  => '',
			'muted' => false,
		);

		switch ( $page['module'] ) {
			case 'hours':
				return array_merge( $tile, self::hours_lines() );

			case 'social':
				$names     = array();
				$platforms = UNDT_Modules::platforms();

				foreach ( UNDT_Content::rows( 'social', 'items' ) as $row ) {
					$platform = isset( $row['platform'] ) ? (string) $row['platform'] : '';
					$names[]  = isset( $platforms[ $platform ] ) ? $platforms[ $platform ] : $platform;
				}

				$names = array_values( array_unique( array_filter( $names ) ) );

				$tile['sub']  = __( 'Profile für Footer und Suchmaschinen', 'unternehmensdaten' );
				$tile['line'] = empty( $names ) ? __( 'Noch kein Profil eingetragen', 'unternehmensdaten' ) : implode( ', ', $names );
				$tile['tone'] = empty( $names ) ? 'missing' : '';

				return $tile;

			case 'banner':
				$on = (bool) UNDT_Content::value( 'banner', 'enabled' );

				$tile['sub']   = __( 'Hinweis oben auf jeder Seite', 'unternehmensdaten' );
				$tile['line']  = $on ? __( 'Wird gerade angezeigt', 'unternehmensdaten' ) : __( 'Ausgeblendet', 'unternehmensdaten' );
				$tile['tone']  = $on ? '' : 'missing';
				$tile['muted'] = ! $on;

				return $tile;

			case 'prices':
				$count = count( UNDT_Content::rows( 'prices', 'items' ) );

				$tile['sub']  = __( 'Leistungen mit Preis', 'unternehmensdaten' );
				/* translators: %d: Anzahl der Leistungen. */
				$tile['line'] = $count > 0 ? sprintf( _n( '%d Leistung', '%d Leistungen', $count, 'unternehmensdaten' ), $count ) : __( 'Noch keine Leistung eingetragen', 'unternehmensdaten' );
				$tile['tone'] = $count > 0 ? '' : 'missing';

				return $tile;

			case 'faq':
				$count = count( UNDT_Content::rows( 'faq', 'items' ) );

				$tile['sub']  = __( 'Häufige Fragen mit Antworten', 'unternehmensdaten' );
				/* translators: %d: Anzahl der Fragen. */
				$tile['line'] = $count > 0 ? sprintf( _n( '%d Frage', '%d Fragen', $count, 'unternehmensdaten' ), $count ) : __( 'Noch keine Frage eingetragen', 'unternehmensdaten' );
				$tile['tone'] = $count > 0 ? '' : 'missing';

				return $tile;

			case 'seo':
				$plugin = UNDT_SchemaOrg::detect_seo_plugin();
				$mode   = (string) UNDT_Content::value( 'seo', 'output_mode' );

				$tile['sub'] = __( 'Strukturierte Daten für Google', 'unternehmensdaten' );

				if ( 'never' === $mode ) {
					$tile['line'] = __( 'Ausgabe abgeschaltet', 'unternehmensdaten' );
					$tile['tone'] = 'missing';
				} elseif ( UNDT_SchemaOrg::should_output() ) {
					$tile['line'] = __( 'Dieses Plugin gibt sie aus', 'unternehmensdaten' );
				} else {
					/* translators: %s: Name des SEO-Plugins. */
					$tile['line'] = sprintf( __( '%s übernimmt die Ausgabe', 'unternehmensdaten' ), $plugin );
				}

				return $tile;
		}

		$module      = UNDT_Modules::get( $page['module'] );
		$tile['sub'] = is_array( $module ) ? $module['label'] : '';

		return $tile;
	}

	/**
	 * Zwei Zeilen zu den Oeffnungszeiten: an welchen Tagen, und was heute gilt.
	 *
	 * @return array sub, line, tone.
	 */
	private static function hours_lines() {
		if ( ! UNDT_Hours::has_data() ) {
			return array(
				'sub'  => __( 'Wann du erreichbar bist', 'unternehmensdaten' ),
				'line' => __( 'Noch keine Zeiten eingetragen', 'unternehmensdaten' ),
				'tone' => 'missing',
			);
		}

		$order = UNDT_Hours::display_order();
		$open  = array();

		foreach ( UNDT_Hours::grouped() as $group ) {
			if ( ! $group['closed'] ) {
				foreach ( $group['days'] as $day ) {
					$open[] = array_search( $day, $order, true );
				}
			}
		}

		sort( $open );

		if ( empty( $open ) ) {
			$sub = __( 'An allen Tagen geschlossen', 'unternehmensdaten' );
		} elseif ( 1 === count( $open ) ) {
			/* translators: %s: Wochentag. */
			$sub = sprintf( __( '%s geöffnet', 'unternehmensdaten' ), UNDT_Hours::day_label( $order[ $open[0] ] ) );
		} elseif ( end( $open ) - $open[0] === count( $open ) - 1 ) {
			/* translators: 1: erster Wochentag, 2: letzter Wochentag. */
			$sub = sprintf( __( '%1$s bis %2$s geöffnet', 'unternehmensdaten' ), UNDT_Hours::day_label( $order[ $open[0] ] ), UNDT_Hours::day_label( $order[ end( $open ) ] ) );
		} else {
			/* translators: %d: Anzahl der Tage. */
			$sub = sprintf( __( 'An %d Tagen geöffnet', 'unternehmensdaten' ), count( $open ) );
		}

		$today = UNDT_Hours::today();

		if ( $today['closed'] || empty( $today['slots'] ) ) {
			$line = __( 'Heute geschlossen', 'unternehmensdaten' );
		} else {
			$windows = array();

			foreach ( $today['slots'] as $slot ) {
				$windows[] = $slot['from'] . '–' . $slot['to'];
			}

			$last = array_pop( $windows );
			$text = empty( $windows ) ? $last : implode( ', ', $windows ) . ' ' . __( 'und', 'unternehmensdaten' ) . ' ' . $last;

			/* translators: %s: Zeitfenster, etwa „08:00–12:00 und 14:00–18:00“. */
			$line = sprintf( __( 'Heute %s Uhr', 'unternehmensdaten' ), $text );
		}

		return array(
			'sub'  => $sub,
			'line' => $line,
			'tone' => '',
		);
	}

	/**
	 * Abgeschaltete Bereiche, die ein Admin hier einschalten kann.
	 *
	 * @return array Modul => Titel.
	 */
	public static function more_modules() {
		if ( ! UNDT_Access::is_admin() ) {
			return array();
		}

		$titles = array(
			'hours'  => __( 'Öffnungszeiten', 'unternehmensdaten' ),
			'prices' => __( 'Preise & Leistungen', 'unternehmensdaten' ),
			'social' => __( 'Social Media', 'unternehmensdaten' ),
			'faq'    => __( 'FAQ', 'unternehmensdaten' ),
			'banner' => __( 'Infobanner', 'unternehmensdaten' ),
			'seo'    => __( 'Suchmaschinen', 'unternehmensdaten' ),
		);

		$more = array();

		foreach ( UNDT_Modules::all() as $slug => $module ) {
			if ( ! UNDT_Modules::is_active( $slug ) ) {
				$more[ $slug ] = isset( $titles[ $slug ] ) ? $titles[ $slug ] : $module['label'];
			}
		}

		return $more;
	}

	/**
	 * Die Bausteine der Karte „Einbinden“, mit dem Ort ihrer Verwendung.
	 *
	 * @return array Liste aus array( key, title, desc, code, usage, unused ).
	 */
	public static function embeds() {
		$list = array();

		foreach ( UNDT_Usage::blocks() as $key => $block ) {
			$list[] = array(
				'key'    => $key,
				'title'  => $block['title'],
				'desc'   => $block['desc'],
				'code'   => $block['code'],
				'usage'  => UNDT_Usage::label( $key ),
				'unused' => empty( UNDT_Usage::find( $key ) ),
			);
		}

		return $list;
	}
}
