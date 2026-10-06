<?php
/**
 * Automatisches Speichern.
 *
 * Jede Formularseite speichert von selbst, kurz nachdem sich etwas geaendert
 * hat. Das Skript schickt dabei das ganze Formular einer Option, genau wie
 * zuvor options.php: ein teilweise uebertragenes Formular koennte sonst Felder
 * leeren, die gar nicht angefasst wurden.
 *
 * Bewusst ueber admin-ajax und nicht als REST-Endpunkt: erreichbar nur fuer
 * angemeldete Benutzer (wp_ajax_, kein nopriv), mit Nonce und Rechtepruefung je
 * Option. Bereinigt wird hier nichts selbst. update_option() ruft die
 * Sanitizer, die UNDT_Admin::settings() ueber register_setting angemeldet hat,
 * also dieselben wie beim Speichern ueber options.php.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Autosave
 */
final class UNDT_Autosave {

	const ACTION = 'undt_save';

	/**
	 * Meldet den Endpunkt an.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Was gespeichert werden kann, und wer es darf.
	 *
	 * @return array Ziel => option, access.
	 */
	public static function targets() {
		$targets = array(
			'company'  => array(
				'option' => UNDT_Store::OPTION_COMPANY,
				'access' => 'edit',
			),
			'profile'  => array(
				'option' => UNDT_Store::OPTION_PROFILE,
				'access' => 'admin',
			),
			'settings' => array(
				'option' => UNDT_Modules::OPTION_SETTINGS,
				'access' => 'admin',
			),
		);

		// Abgeschaltete Bereiche nehmen nichts an, wie ihr Menuepunkt fehlen sie.
		foreach ( UNDT_Modules::all() as $slug => $module ) {
			if ( UNDT_Modules::is_active( $slug ) ) {
				$targets[ 'module:' . $slug ] = array(
					'option' => $module['option'],
					'access' => UNDT_Nav::module_access( $slug ),
				);
			}
		}

		return $targets;
	}

	/**
	 * Beantwortet die Anfrage aus admin.js.
	 *
	 * @return void
	 */
	public static function handle() {
		if ( ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'code' => 'nonce' ), 403 );
		}

		$target = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';

		// Die Werte bereinigt der Sanitizer der jeweiligen Option, siehe save().
		$result = self::save( $target, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data();

			wp_send_json_error( array( 'code' => $result->get_error_code() ), is_int( $status ) ? $status : 400 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Speichert ein Ziel.
	 *
	 * @param string $target Ziel, siehe targets(), oder quick:banner, quick:copy_format,
	 *                       quick:module, quick:onboarding, quick:suggest,
	 *                       quick:chamber, quick:create_page, quick:preview.
	 * @param array  $post   Uebertragene Daten, bereits ohne Schraegstriche.
	 * @return array|WP_Error Antwort fuer das Skript.
	 */
	public static function save( $target, array $post ) {
		$reload = false;
		$extra  = array();

		if ( 'quick:banner' === $target ) {
			$saved = self::save_banner( ! empty( $post['value'] ) );

			if ( is_wp_error( $saved ) ) {
				return $saved;
			}

			// Steht man gerade auf der Seite Infobanner, zieht deren Kopf mit.
			$extra['parts'] = UNDT_Module_View::parts( 'banner' );
		} elseif ( 'quick:copy_format' === $target ) {
			if ( ! UNDT_Access::is_admin() ) {
				return new WP_Error( 'cap', '', 403 );
			}

			if ( ! UNDT_Copy::set_format( isset( $post['value'] ) ? (string) $post['value'] : '' ) ) {
				return new WP_Error( 'value', '', 400 );
			}
		} elseif ( 'quick:module' === $target ) {
			$saved = self::enable_module( isset( $post['value'] ) ? sanitize_key( $post['value'] ) : '' );

			if ( is_wp_error( $saved ) ) {
				return $saved;
			}

			$reload = true;
		} elseif ( 'quick:onboarding' === $target ) {
			if ( ! UNDT_Access::is_admin() ) {
				return new WP_Error( 'cap', '', 403 );
			}

			if ( ! isset( $post['value'] ) || 'skip' !== $post['value'] ) {
				return new WP_Error( 'value', '', 400 );
			}

			UNDT_Overview::mark( 'skipped' );
			$reload = true;
		} elseif ( 'quick:suggest' === $target ) {
			// Der Wert kommt vom Server, nicht aus der Anfrage: nur der Name des Feldes wird uebertragen.
			if ( ! UNDT_Access::can_edit() ) {
				return new WP_Error( 'cap', '', 403 );
			}

			$value = UNDT_Company_View::apply_suggestion( isset( $post['value'] ) ? sanitize_key( $post['value'] ) : '' );

			if ( null === $value ) {
				return new WP_Error( 'value', '', 400 );
			}

			$extra['value'] = $value;
		} elseif ( 'quick:chamber' === $target ) {
			// Uebernimmt Kammer und Regelungen; welche Kammer zulaessig ist, entscheidet der Server.
			if ( ! UNDT_Access::can_edit() ) {
				return new WP_Error( 'cap', '', 403 );
			}

			$values = UNDT_Company_View::apply_chamber( isset( $post['value'] ) ? sanitize_key( $post['value'] ) : '' );

			if ( null === $values ) {
				return new WP_Error( 'value', '', 400 );
			}

			$extra['values'] = $values;
			$extra['parts']  = array( 'chamberbox' => UNDT_Company_View::chamber_box() );
		} elseif ( 'quick:create_page' === $target ) {
			if ( ! UNDT_Access::is_admin() ) {
				return new WP_Error( 'cap', '', 403 );
			}

			$created = UNDT_Embed::create_page( isset( $post['value'] ) ? sanitize_key( $post['value'] ) : '' );

			if ( is_wp_error( $created ) ) {
				return $created;
			}

			$extra['page'] = $created;
			$reload        = true;
		} elseif ( 'quick:preview' === $target ) {
			// Liest nur: rendert einen Baustein mit den gespeicherten Angaben.
			if ( ! UNDT_Access::is_admin() ) {
				return new WP_Error( 'cap', '', 403 );
			}

			$html = UNDT_Embed::preview( isset( $post['value'] ) ? (string) $post['value'] : '' );

			if ( null === $html ) {
				return new WP_Error( 'value', '', 400 );
			}

			$extra['html'] = $html;
		} else {
			$targets = self::targets();

			if ( ! isset( $targets[ $target ] ) ) {
				return new WP_Error( 'target', '', 400 );
			}

			if ( ! current_user_can( UNDT_Access::cap_for( $targets[ $target ]['access'] ) ) ) {
				return new WP_Error( 'cap', '', 403 );
			}

			$option = $targets[ $target ]['option'];
			$input  = isset( $post[ $option ] ) && is_array( $post[ $option ] ) ? $post[ $option ] : array();

			// Andere Bereiche oder Rechte bedeuten eine andere Navigation.
			$before = 'settings' === $target ? UNDT_Modules::settings() : null;

			update_option( $option, $input );
			self::flush();

			if ( null !== $before ) {
				$after  = UNDT_Modules::settings();
				$reload = $before['modules'] !== $after['modules'];
			}

			// Vorschau, Statuszeile und JSON-LD zeigen danach den neuen Stand.
			if ( 0 === strpos( $target, 'module:' ) ) {
				$parts = UNDT_Module_View::parts( substr( $target, 7 ) );

				if ( ! empty( $parts ) ) {
					$extra['parts'] = $parts;
				}
			}

			// Die Rechtsform aendert, welche Felder auf „Firma“ gelten.
			if ( 'profile' === $target ) {
				$extra['company'] = UNDT_Company_View::state();
			}

			// Beruf oder Bundesland koennen den Vorschlag fuer die Kammer aendern.
			if ( 'company' === $target ) {
				$extra['parts'] = array( 'chamberbox' => UNDT_Company_View::chamber_box() );
			}

			// Die Rechtsseiten auf „Einbinden“ zeigen danach ihren neuen Stand.
			if ( 'company' === $target && ! empty( $post['undt_pages_status'] ) && UNDT_Access::is_admin() ) {
				UNDT_Usage::flush();
				$extra['pages'] = UNDT_Embed::pages();
			}

			// Sind alle Schritte der Einrichtung erledigt, kommt sie nicht wieder.
			if ( UNDT_Access::is_admin() ) {
				UNDT_Overview::maybe_complete();
			}
		}

		$badges = UNDT_Nav::badges();

		return array_merge(
			array(
				'badges' => $badges,
				'total'  => array_sum( array_intersect_key( $badges, UNDT_Nav::visible() ) ),
				'nonce'  => wp_create_nonce( self::ACTION ),
				'reload' => $reload,
			),
			$extra
		);
	}

	/**
	 * Schaltet das Infobanner aus den Schnelleinstellungen.
	 *
	 * Geaendert wird nur dieser eine Schalter, der Rest des Banners bleibt,
	 * wie er gespeichert ist, und laeuft trotzdem erneut durch den Sanitizer.
	 *
	 * @param bool $on Ob das Banner erscheint.
	 * @return true|WP_Error
	 */
	private static function save_banner( $on ) {
		$module = UNDT_Modules::get( 'banner' );

		if ( null === $module || ! UNDT_Modules::is_active( 'banner' ) ) {
			return new WP_Error( 'target', '', 400 );
		}

		if ( ! current_user_can( UNDT_Access::cap_for( UNDT_Nav::module_access( 'banner' ) ) ) ) {
			return new WP_Error( 'cap', '', 403 );
		}

		$stored = get_option( $module['option'], array() );
		$stored = is_array( $stored ) ? $stored : array();

		$stored['enabled'] = $on ? 1 : 0;

		update_option( $module['option'], $stored );
		self::flush();

		return true;
	}

	/**
	 * Schaltet einen Bereich ein, aus „Weitere Bereiche“ der Uebersicht.
	 *
	 * Die uebrigen Einstellungen bleiben, wie sie sind, und laufen wieder durch
	 * den Sanitizer der Einstellungen.
	 *
	 * @param string $slug Modul.
	 * @return true|WP_Error
	 */
	private static function enable_module( $slug ) {
		if ( ! UNDT_Access::is_admin() ) {
			return new WP_Error( 'cap', '', 403 );
		}

		if ( null === UNDT_Modules::get( $slug ) ) {
			return new WP_Error( 'value', '', 400 );
		}

		$settings                     = UNDT_Modules::settings();
		$settings['modules'][ $slug ] = 1;

		update_option( UNDT_Modules::OPTION_SETTINGS, $settings );
		self::flush();

		return true;
	}

	/**
	 * Leert die Laufzeit-Caches, damit die Zaehler den neuen Stand zeigen.
	 *
	 * @return void
	 */
	private static function flush() {
		UNDT_Store::flush();
		UNDT_Content::flush();
		UNDT_Modules::flush();
	}
}
