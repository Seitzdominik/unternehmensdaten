<?php
/**
 * Plugin Name:       Unternehmensdaten
 * Description:       Zentrale Verwaltung aller Unternehmensangaben: rechtliche Pflichtangaben, Oeffnungszeiten, Preise, Social, FAQ, Infobanner und strukturierte Daten. Mit Shortcode fuer jedes Feld und fertigen Bloecken fuer Impressum und Footer.
 * Version:           0.5.6
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Seitz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       unternehmensdaten
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

define( 'UNDT_VERSION', '0.5.6' );
define( 'UNDT_FILE', __FILE__ );
define( 'UNDT_DIR', plugin_dir_path( __FILE__ ) );
define( 'UNDT_URL', plugin_dir_url( __FILE__ ) );

/*
 * Das GitHub-Repository, aus dem Aktualisierungen bezogen werden, als
 * inhaber/name. Hier eintragen, oder in der wp-config.php ueberschreiben. Das
 * Repository muss oeffentlich sein: die Adressen, ueber die Manifest und Paket
 * geladen werden, nehmen keinen Zugriffstoken an.
 *
 * UNDT_DISABLE_UPDATES = true schaltet die Suche ganz ab, etwa auf
 * Installationen, die zentral gepflegt werden.
 */
if ( ! defined( 'UNDT_UPDATE_REPO' ) ) {
	define( 'UNDT_UPDATE_REPO', 'Seitzdominik/unternehmensdaten' );
}

/**
 * Klassen-Autoloader.
 *
 * UNDT_Schema -> includes/class-undt-schema.php
 * UNDT_Admin  -> admin/class-undt-admin.php
 *
 * @param string $class Klassenname.
 * @return void
 */
function undt_autoload( $class ) {
	if ( 0 !== strpos( $class, 'UNDT_' ) ) {
		return;
	}

	$file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

	foreach ( array( 'includes', 'admin' ) as $dir ) {
		$path = UNDT_DIR . $dir . '/' . $file;
		if ( is_readable( $path ) ) {
			require_once $path;
			return;
		}
	}
}
spl_autoload_register( 'undt_autoload' );

/**
 * Startet das Plugin, sobald WordPress bereit ist.
 *
 * Shortcodes werden bewusst erst auf `init` registriert, nicht beim Laden der
 * Datei: so kosten sie auf Requests, die gar kein Rendering ausloesen, nichts.
 *
 * @return void
 */
function undt_bootstrap() {
	/*
	 * Kein load_plugin_textdomain: Uebersetzungen gehoeren nach
	 * wp-content/languages/plugins/unternehmensdaten-<locale>.mo. WordPress laedt
	 * sie von dort selbst, sobald die erste Zeichenkette gebraucht wird. Im
	 * Plugin-Ordner waeren sie bei der naechsten Aktualisierung fort, denn die
	 * ersetzt den ganzen Ordner.
	 */
	UNDT_Shortcodes::register();
	UNDT_Api::register();
	UNDT_Dynamic::register();
	UNDT_Updater::register();
	UNDT_Modules::register();

	if ( is_admin() ) {
		UNDT_Admin::instance()->init();
		UNDT_Transfer::register();
	}

	// Sichern und Einspielen auch ohne Backend, etwa beim Einrichten neuer Seiten.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		UNDT_Cli::register();
	}
}
add_action( 'init', 'undt_bootstrap' );

/**
 * Legt beim Aktivieren die Optionen an und merkt sich, dass der Assistent
 * noch nicht durchlaufen wurde.
 *
 * @return void
 */
function undt_activate() {
	if ( false === get_option( UNDT_Store::OPTION_PROFILE ) ) {
		add_option( UNDT_Store::OPTION_PROFILE, UNDT_Schema::profile_defaults(), '', 'yes' );
	}

	if ( false === get_option( UNDT_Store::OPTION_COMPANY ) ) {
		add_option( UNDT_Store::OPTION_COMPANY, array(), '', 'yes' );
	}

	if ( false === get_option( UNDT_Store::OPTION_SETUP ) ) {
		add_option( UNDT_Store::OPTION_SETUP, 0, '', 'yes' );
	}

	if ( false === get_option( UNDT_Modules::OPTION_SETTINGS ) ) {
		add_option( UNDT_Modules::OPTION_SETTINGS, UNDT_Modules::settings_defaults(), '', 'yes' );
	}

	/*
	 * Die autoload-Angabe je Modul ist eine Performance-Entscheidung und laesst
	 * sich ueber register_setting nicht setzen. Neue Optionen werden deshalb hier
	 * mit dem richtigen Wert angelegt, bestehende bei Bedarf nachgezogen.
	 */
	$existing = array();

	foreach ( UNDT_Modules::all() as $module ) {
		if ( false === get_option( $module['option'] ) ) {
			add_option( $module['option'], array(), '', $module['autoload'] ? 'yes' : 'no' );
			continue;
		}

		$existing[ $module['option'] ] = (bool) $module['autoload'];
	}

	if ( ! empty( $existing ) && function_exists( 'wp_set_option_autoload_values' ) ) {
		wp_set_option_autoload_values( $existing );
	}
}
register_activation_hook( __FILE__, 'undt_activate' );
