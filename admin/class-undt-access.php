<?php
/**
 * Wer die Angaben pflegen darf.
 *
 * Zwei Stufen: das Admin-Recht (UNDT_Store::capability(), Standard
 * manage_options) fuer alles, und das eigene Recht undt_edit_data fuer die
 * Pflege der Angaben. Letzteres erhalten Admins immer und dazu die Rollen, die in
 * den Einstellungen gewaehlt sind.
 *
 * Vergeben wird es zur Laufzeit ueber user_has_cap, nicht ueber add_cap: so
 * aendert das Plugin keine Rollen in der Datenbank, und beim Loeschen bleibt
 * nichts zurueck. Abgefragt wird immer das Recht, nie der Rollenname.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Access
 */
final class UNDT_Access {

	const CAP = 'undt_edit_data';

	/**
	 * Haengt die Vergabe des Rechts ein.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'user_has_cap', array( __CLASS__, 'grant' ), 10, 4 );
	}

	/**
	 * Vergibt undt_edit_data, wenn danach gefragt wird.
	 *
	 * @param array   $allcaps Rechte des Benutzers.
	 * @param array   $caps    Angefragte Grundrechte.
	 * @param array   $args    Argumente der Anfrage.
	 * @param WP_User $user    Benutzer.
	 * @return array
	 */
	public static function grant( $allcaps, $caps, $args, $user ) {
		if ( ! in_array( self::CAP, (array) $caps, true ) ) {
			return $allcaps;
		}

		$admin = UNDT_Store::capability();

		if ( ! empty( $allcaps[ $admin ] ) ) {
			$allcaps[ self::CAP ] = true;

			return $allcaps;
		}

		$roles = is_object( $user ) && isset( $user->roles ) ? (array) $user->roles : array();

		if ( array_intersect( $roles, self::roles() ) ) {
			$allcaps[ self::CAP ] = true;
		}

		return $allcaps;
	}

	/**
	 * Die Rollen, die neben den Admins pflegen duerfen.
	 *
	 * @return array
	 */
	public static function roles() {
		$settings = UNDT_Modules::settings();

		return isset( $settings['roles'] ) && is_array( $settings['roles'] ) ? array_values( $settings['roles'] ) : array();
	}

	/**
	 * Ob der aktuelle Benutzer die Angaben pflegen darf.
	 *
	 * @return bool
	 */
	public static function can_edit() {
		return current_user_can( self::CAP );
	}

	/**
	 * Ob der aktuelle Benutzer alles darf.
	 *
	 * @return bool
	 */
	public static function is_admin() {
		return current_user_can( UNDT_Store::capability() );
	}

	/**
	 * Das Recht, das eine Stufe verlangt.
	 *
	 * @param string $level edit oder admin.
	 * @return string
	 */
	public static function cap_for( $level ) {
		return 'edit' === $level ? self::CAP : UNDT_Store::capability();
	}

	/**
	 * Rollen, die sich in den Einstellungen waehlen lassen.
	 *
	 * Nur Rollen, die ueberhaupt Inhalte bearbeiten koennen. Abonnenten und
	 * Kunden eines Shops gehoeren nicht ins Backend. Rollen mit dem Admin-Recht
	 * stehen ohnehin fest und erscheinen nicht.
	 *
	 * @return array Schluessel ist die Rolle, Wert ihr Name.
	 */
	public static function choosable_roles() {
		if ( ! function_exists( 'wp_roles' ) ) {
			return array();
		}

		$admin  = UNDT_Store::capability();
		$choice = array();

		foreach ( wp_roles()->roles as $role => $data ) {
			$caps = isset( $data['capabilities'] ) ? (array) $data['capabilities'] : array();

			if ( empty( $caps['edit_posts'] ) || ! empty( $caps[ $admin ] ) ) {
				continue;
			}

			$choice[ $role ] = translate_user_role( $data['name'] );
		}

		return $choice;
	}

	/**
	 * Bereinigt die gewaehlten Rollen.
	 *
	 * @param mixed $input Rohe Eingabe, Liste oder Rolle => 0/1.
	 * @return array
	 */
	public static function sanitize_roles( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		// Schalter im Formular liefern Rolle => 0/1, das Einspielen eine Liste.
		$list = array();

		foreach ( $input as $key => $value ) {
			if ( is_int( $key ) ) {
				$list[] = sanitize_key( $value );
			} elseif ( ! empty( $value ) ) {
				$list[] = sanitize_key( $key );
			}
		}

		$known = self::choosable_roles();

		// Ohne Rollenverzeichnis (CLI vor init) bleibt die Auswahl, wie sie ist.
		if ( ! empty( $known ) ) {
			$list = array_intersect( $list, array_keys( $known ) );
		}

		return array_values( array_unique( array_filter( $list ) ) );
	}
}
