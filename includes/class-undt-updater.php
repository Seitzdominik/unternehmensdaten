<?php
/**
 * Aktualisierung ueber GitHub-Releases.
 *
 * Das Plugin liegt nicht auf wordpress.org, meldet Aktualisierungen aber im
 * gewohnten Bildschirm. Dafuer haengt es sich in dieselben Haken, die WordPress
 * fuer seine eigenen Aktualisierungen nutzt.
 *
 * Bewusst ohne die Releases-API von GitHub: die ist auf 60 Anfragen je Stunde
 * und IP begrenzt, und auf geteiltem Hosting sitzen viele Kundenseiten hinter
 * derselben IP. Gelesen wird stattdessen eine update.json, die als Anhang am
 * Release haengt. Die Adresse releases/latest/download/... zeigt immer auf das
 * neueste Release, laeuft ueber das CDN und kennt kein Limit.
 *
 * @package Unternehmensdaten
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UNDT_Updater
 */
final class UNDT_Updater {

	/**
	 * Zwischenspeicher der abgerufenen Angaben.
	 */
	const TRANSIENT = 'undt_update_info';

	/**
	 * Wie lange erfolgreiche Antworten gelten.
	 */
	const TTL_OK = 6 * HOUR_IN_SECONDS;

	/**
	 * Wie lange nach einem Fehlschlag gewartet wird, bevor erneut gefragt wird.
	 */
	const TTL_FAIL = 30 * MINUTE_IN_SECONDS;

	/**
	 * Hosts, von denen ein Paket geladen werden darf.
	 *
	 * @var array
	 */
	private static $hosts = array( 'github.com', 'codeload.github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com' );

	/**
	 * Haengt sich in die Aktualisierungslogik von WordPress ein.
	 *
	 * @return void
	 */
	public static function register() {
		/*
		 * Nur im Backend und in Cron-Laeufen. Im Frontend prueft WordPress ohnehin
		 * nicht auf Aktualisierungen, dort waeren die Haken totes Gewicht.
		 */
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}

		if ( ! self::enabled() ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
		add_action( 'admin_post_undt_check_update', array( __CLASS__, 'handle_manual_check' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ), 10, 0 );
	}

	/**
	 * Ob die Suche nach Aktualisierungen eingeschaltet ist.
	 *
	 * @return bool
	 */
	public static function enabled() {
		// Fuer verwaltete Installationen, die zentral aktualisiert werden.
		if ( defined( 'UNDT_DISABLE_UPDATES' ) && UNDT_DISABLE_UPDATES ) {
			return false;
		}

		$settings = UNDT_Modules::settings();

		/**
		 * Schaltet die Suche nach Aktualisierungen ab.
		 *
		 * @param bool $enabled Aktueller Zustand.
		 */
		return (bool) apply_filters( 'undt_updates_enabled', ! empty( $settings['updates'] ) );
	}

	/**
	 * Das Repository in der Form inhaber/name.
	 *
	 * @return string
	 */
	public static function repo() {
		$repo = defined( 'UNDT_UPDATE_REPO' ) ? UNDT_UPDATE_REPO : '';

		/**
		 * Erlaubt ein abweichendes Repository, etwa fuer einen Fork.
		 *
		 * @param string $repo Repository als inhaber/name.
		 */
		$repo = (string) apply_filters( 'undt_update_repo', $repo );

		return preg_match( '#^[\w.-]+/[\w.-]+$#', $repo ) ? $repo : '';
	}

	/**
	 * Die Adresse der update.json am neuesten Release.
	 *
	 * @return string
	 */
	private static function manifest_url() {
		$repo = self::repo();

		if ( '' === $repo ) {
			return '';
		}

		return 'https://github.com/' . $repo . '/releases/latest/download/update.json';
	}

	/**
	 * Der Plugin-Pfad, wie WordPress ihn fuehrt.
	 *
	 * @return string
	 */
	private static function basename() {
		return plugin_basename( UNDT_FILE );
	}

	/**
	 * Holt die Angaben zum neuesten Release.
	 *
	 * @param bool $force Zwischenspeicher uebergehen.
	 * @return array|null
	 */
	public static function fetch( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::TRANSIENT );

			if ( is_array( $cached ) ) {
				return $cached;
			}

			// Ein gemerkter Fehlschlag verhindert das Klopfen im Minutentakt.
			if ( 'fail' === $cached ) {
				return null;
			}
		}

		$url = self::manifest_url();

		if ( '' === $url ) {
			return null;
		}

		$args = array(
			'timeout'    => 10,
			'user-agent' => 'Unternehmensdaten/' . UNDT_VERSION . '; ' . home_url( '/' ),
			'headers'    => array( 'Accept' => 'application/json' ),
		);

		// Fuer ein privates Repository. Der Wert gehoert in die wp-config.php.
		if ( defined( 'UNDT_GITHUB_TOKEN' ) && UNDT_GITHUB_TOKEN ) {
			$args['headers']['Authorization'] = 'Bearer ' . UNDT_GITHUB_TOKEN;
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( self::TRANSIENT, 'fail', self::TTL_FAIL );

			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			set_site_transient( self::TRANSIENT, 'fail', self::TTL_FAIL );

			return null;
		}

		$info = self::normalize( $data );

		if ( null === $info ) {
			set_site_transient( self::TRANSIENT, 'fail', self::TTL_FAIL );

			return null;
		}

		set_site_transient( self::TRANSIENT, $info, self::TTL_OK );

		return $info;
	}

	/**
	 * Prueft und normalisiert die gelesenen Angaben.
	 *
	 * @param array $data Rohe Angaben aus der update.json.
	 * @return array|null
	 */
	private static function normalize( array $data ) {
		$version = trim( (string) $data['version'] );

		// Nur eine erkennbare Versionsnummer, kein beliebiger Text.
		if ( ! preg_match( '/^\d+(\.\d+){1,3}(-[\w.]+)?$/', $version ) ) {
			return null;
		}

		$package = self::validate_package( (string) $data['download_url'] );

		if ( '' === $package ) {
			return null;
		}

		$sections = isset( $data['sections'] ) && is_array( $data['sections'] ) ? $data['sections'] : array();

		return array(
			'version'      => $version,
			'package'      => $package,
			'homepage'     => esc_url_raw( isset( $data['homepage'] ) ? (string) $data['homepage'] : '', array( 'https' ) ),
			'requires'     => sanitize_text_field( isset( $data['requires'] ) ? (string) $data['requires'] : '' ),
			'tested'       => sanitize_text_field( isset( $data['tested'] ) ? (string) $data['tested'] : '' ),
			'requires_php' => sanitize_text_field( isset( $data['requires_php'] ) ? (string) $data['requires_php'] : '' ),
			'last_updated' => sanitize_text_field( isset( $data['last_updated'] ) ? (string) $data['last_updated'] : '' ),
			'author'       => sanitize_text_field( isset( $data['author'] ) ? (string) $data['author'] : '' ),
			'sections'     => array(
				'description' => isset( $sections['description'] ) ? wp_kses_post( $sections['description'] ) : '',
				'changelog'   => isset( $sections['changelog'] ) ? wp_kses_post( $sections['changelog'] ) : '',
			),
			'checked'      => time(),
		);
	}

	/**
	 * Laesst nur Paketadressen von GitHub durch.
	 *
	 * Die update.json kommt zwar ueber HTTPS von GitHub, aber die Adresse aus
	 * ihr landet ungeprueft im Installer. Eine Wirtsliste kostet nichts und
	 * verhindert, dass ein manipuliertes Manifest irgendwohin zeigt.
	 *
	 * @param string $url Adresse aus dem Manifest.
	 * @return string Leerstring, wenn nicht zulaessig.
	 */
	private static function validate_package( $url ) {
		$url = esc_url_raw( trim( $url ), array( 'https' ) );

		if ( '' === $url ) {
			return '';
		}

		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		/**
		 * Erlaubte Hosts fuer das Aktualisierungspaket.
		 *
		 * @param array $hosts Liste von Hostnamen.
		 */
		$hosts = (array) apply_filters( 'undt_update_hosts', self::$hosts );

		return in_array( $host, $hosts, true ) ? $url : '';
	}

	/**
	 * Traegt eine verfuegbare Aktualisierung in den Transient von WordPress ein.
	 *
	 * @param mixed $transient Der Transient update_plugins.
	 * @return mixed
	 */
	public static function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$info = self::fetch();

		if ( null === $info ) {
			return $transient;
		}

		$file   = self::basename();
		$newer  = version_compare( $info['version'], UNDT_VERSION, '>' );
		$entry  = (object) array(
			'id'           => 'github.com/' . self::repo(),
			'slug'         => dirname( $file ),
			'plugin'       => $file,
			'new_version'  => $info['version'],
			'url'          => $info['homepage'],
			'package'      => $info['package'],
			'tested'       => $info['tested'],
			'requires_php' => $info['requires_php'],
			'icons'        => array(),
			'banners'      => array(),
			'banners_rtl'  => array(),
		);

		if ( $newer ) {
			$transient->response[ $file ] = $entry;

			return $transient;
		}

		/*
		 * Auch ohne Aktualisierung eintragen: WordPress blendet den Schalter fuer
		 * automatische Aktualisierungen nur ein, wenn das Plugin in einer der
		 * beiden Listen steht.
		 */
		$entry->new_version = UNDT_VERSION;

		$transient->no_update[ $file ] = $entry;

		return $transient;
	}

	/**
	 * Fuellt das Fenster mit den Plugin-Details.
	 *
	 * @param mixed  $result Bisheriges Ergebnis.
	 * @param string $action Angefragte Aktion.
	 * @param object $args   Argumente der Anfrage.
	 * @return mixed
	 */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || dirname( self::basename() ) !== $args->slug ) {
			return $result;
		}

		$info = self::fetch();

		if ( null === $info ) {
			return $result;
		}

		$data = get_plugin_data( UNDT_FILE, false, false );

		return (object) array(
			'name'           => $data['Name'],
			'slug'           => $args->slug,
			'version'        => $info['version'],
			'author'         => '' === $info['author'] ? $data['Author'] : $info['author'],
			'homepage'       => $info['homepage'],
			'requires'       => $info['requires'],
			'tested'         => $info['tested'],
			'requires_php'   => $info['requires_php'],
			'last_updated'   => $info['last_updated'],
			'download_link'  => $info['package'],
			'trunk'          => $info['package'],
			'sections'       => $info['sections'],
			'banners'        => array(),
			'external'       => true,
		);
	}

	/**
	 * Benennt den entpackten Ordner auf den Plugin-Ordner um.
	 *
	 * Der Workflow baut ein Archiv mit dem richtigen Ordner, dieser Haken ist
	 * also normalerweise wirkungslos. Er greift nur, wenn jemand das automatisch
	 * erzeugte Quell-Archiv von GitHub nimmt: das entpackt zu repo-1.2.3, und
	 * WordPress wuerde daraus ein zweites, unabhaengiges Plugin machen.
	 *
	 * @param string $source        Entpackter Ordner.
	 * @param string $remote_source Uebergeordneter Ordner.
	 * @param object $upgrader      Der Upgrader.
	 * @param array  $args          Weitere Angaben.
	 * @return string|WP_Error
	 */
	public static function fix_folder( $source, $remote_source, $upgrader = null, $args = array() ) {
		if ( ! isset( $args['plugin'] ) || self::basename() !== $args['plugin'] ) {
			return $source;
		}

		$wanted = trailingslashit( $remote_source ) . dirname( self::basename() );

		if ( untrailingslashit( $source ) === $wanted ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem || ! $wp_filesystem->move( $source, $wanted, true ) ) {
			return $source;
		}

		return trailingslashit( $wanted );
	}

	/**
	 * Verwirft den Zwischenspeicher.
	 *
	 * @return void
	 */
	public static function flush() {
		delete_site_transient( self::TRANSIENT );
	}

	/**
	 * Verarbeitet den Knopf „Jetzt nach Aktualisierungen suchen“.
	 *
	 * @return void
	 */
	public static function handle_manual_check() {
		if ( ! current_user_can( UNDT_Store::capability() ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für diese Aktion.', 'unternehmensdaten' ), 403 );
		}

		check_admin_referer( 'undt_check_update' );

		self::flush();
		self::fetch( true );

		// Auch WordPress' eigene Sammlung leeren, sonst zeigt der Plugin-Bildschirm
		// weiter den alten Stand.
		delete_site_transient( 'update_plugins' );

		wp_safe_redirect(
			add_query_arg(
				'undt-checked',
				'1',
				admin_url( 'admin.php?page=' . UNDT_Admin::SLUG_SETTINGS )
			)
		);

		exit;
	}

	/**
	 * Der aktuelle Stand fuer die Anzeige in den Einstellungen.
	 *
	 * @return array
	 */
	public static function status() {
		$repo = self::repo();

		if ( '' === $repo ) {
			return array(
				'state'   => 'unconfigured',
				'message' => __( 'Kein Repository hinterlegt. In der Hauptdatei des Plugins die Konstante UNDT_UPDATE_REPO setzen.', 'unternehmensdaten' ),
			);
		}

		if ( ! self::enabled() ) {
			return array(
				'state'   => 'off',
				'repo'    => $repo,
				'message' => __( 'Die Suche nach Aktualisierungen ist abgeschaltet.', 'unternehmensdaten' ),
			);
		}

		$info = self::fetch();

		if ( null === $info ) {
			return array(
				'state'   => 'error',
				'repo'    => $repo,
				'message' => __( 'Es konnten keine Angaben abgerufen werden. Entweder gibt es noch kein Release mit einer update.json, oder der Server kam nicht an GitHub heran.', 'unternehmensdaten' ),
			);
		}

		return array(
			'state'    => version_compare( $info['version'], UNDT_VERSION, '>' ) ? 'update' : 'current',
			'repo'     => $repo,
			'version'  => $info['version'],
			'checked'  => $info['checked'],
			'package'  => $info['package'],
			'homepage' => $info['homepage'],
		);
	}
}
