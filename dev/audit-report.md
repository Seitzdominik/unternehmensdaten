# Prüfbericht: WordPress-Plugin „Unternehmensdaten" 0.4.0

Stand: 14.09.2026. Geprüft wurde der Ordner `unternehmensdaten` im Projektordner. Er ist mit dem Git-Ordner identisch (rekursiver Vergleich ohne `.git` und `.gitattributes`), und sein Inhalt entspricht bis auf Zeilenenden dem veröffentlichten Release v0.4.0 (siehe Abschnitt Auslieferung). Am Plugin wurde nichts geändert; alle Hilfsdateien liegen unter `dev/audit/` und `dev/tests/test-audit-findings.php`.

## 1. Zusammenfassung

| Schwere | Anzahl |
|---|---|
| Kritisch | 0 |
| Hoch | 0 |
| Mittel | 6 |
| Niedrig | 11 |
| Hinweis | 6 |

Gesamturteil: Die Sicherheitsgrenzen des Plugins halten. Alle 23 Shortcode-Attribute, alle gespeicherten Werte und der Update-Kanal sind so abgesichert, dass weder Besucher noch Redakteure noch ein manipuliertes Manifest Schaden anrichten können, und das Plugin läuft fehlerfrei von PHP 7.4 / WordPress 6.4 bis PHP 8.5 / WordPress 7.1. Die gefundenen Punkte sind Funktions- und Prozessfehler: zwei davon betreffen den Auslieferungsweg (Vorabversionen landen bei allen Kunden, das beworbene private Repository funktioniert nicht), die übrigen brechen einzelne Funktionen unter realistischen Bedingungen.

Die drei dringendsten Punkte:

1. **[F-02]** Ein Tag wie `v0.5.0-beta.1` erzeugt ein normales „Latest"-Release, und der Updater bietet die Vorabversion sofort allen Kundenseiten an.
2. **[F-06]** Die Seitenauswahl in den Stammdaten listet nur veröffentlichte Seiten. Ist eine verknüpfte Rechtsseite gerade Entwurf, privat oder ausstehend, setzt jedes Speichern der Stammdaten die Verknüpfung stillschweigend auf „keine Seite".
3. **[F-05]** Fußnote der Preisliste, Zusatz „Uhr", Bezeichnung „geschlossen" und Kleinunternehmer-Hinweis lassen sich nicht leeren: ein leeres Feld fällt beim Lesen immer auf die Voreinstellung zurück. Wer Nettopreise ausweist, bekommt trotzdem „inklusive der gesetzlichen Umsatzsteuer" ausgegeben.

## 2. Befunde

### Mittel

### [F-01] Privates Repository: Token wirkt nicht beim Manifest, fehlt beim Paket und wird an das CDN weitergereicht
Schwere: Mittel
Status: BESTÄTIGT (Teile b und c), VERMUTET (Teil a)
Bereich: Sicherheit / Korrektheit
Ort: `includes/class-undt-updater.php:123` (Manifest-Adresse), `:168-170` (Token nur beim Manifest), `:292` (Paketadresse an WordPress); `unternehmensdaten.php:29`
Beschreibung: Die Hauptdatei bewirbt `UNDT_GITHUB_TOKEN` für ein privates Repository. Das funktioniert aus drei Gründen nicht:
(a) Das Manifest wird über die Web-Adresse `github.com/…/releases/latest/download/update.json` geholt. Diese Route authentifiziert keine API-Token; für private Repositories liefert sie „Not Found". Assets privater Repositories sind nur über `api.github.com/repos/{owner}/{repo}/releases/assets/{id}` mit `Accept: application/octet-stream` erreichbar (GitHub-REST-Dokumentation, GitHub-Community-Diskussion #47453).
(b) Das Paket lädt WordPress selbst über `download_url()`, das nur `timeout`, `stream` und `filename` an `wp_safe_remote_get()` gibt (WordPress 7.1, `wp-admin/includes/file.php:1177-1184`). Der Token wird dort nie mitgeschickt.
(c) Die HTTP-Bibliothek von WordPress (Requests 2.0.17) reicht beim Folgen einer Umleitung alle ursprünglichen Kopfzeilen an den neuen Host weiter (`wp-includes/Requests/src/Requests.php:809`, `self::request($location, $req_headers, …)`), unabhängig vom Transport. Der Token verlässt damit `github.com` in Richtung `release-assets.githubusercontent.com`.
Szenario: Reproduktion zu (c): `dev/audit/`-Beweis mit zwei lokalen Node-Servern (127.0.0.1:9701 leitet per 302 auf 127.0.0.2:9702 um, der die Kopfzeilen zurückgibt) und der unveränderten Requests-Bibliothek aus WordPress 7.1: `Transport fsockopen: final host=127.0.0.2:9702 … Authorization an Ziel-Host weitergegeben: JA (Bearer SECRET-TOKEN)`. Zu (b): Code-Lesung von `download_url()`. Zu (a): nicht mit einem echten privaten Repository reproduziert, weil kein Token und kein privates Repository vorlagen; die Aussage stützt sich auf die GitHub-Dokumentation. Geprüft wurde ergänzend, dass ein mitgeschickter Bearer-Header bei einem öffentlichen Repository nichts kaputt macht: `github.com` antwortet weiterhin 302, das CDN mit 200.
Auswirkung: Wer der Anleitung folgt und das Plugin aus einem privaten Repository verteilen will, bekommt auf jeder Kundenseite dauerhaft den Zustand „Es konnten keine Angaben abgerufen werden", und selbst mit funktionierendem Manifest würde der Paket-Download mit 404 scheitern. Zusätzlich landet ein Zugriffstoken in den Zugriffsprotokollen eines Drittsystems, wenn auch eines von GitHub betriebenen.
Vorschlag: Entweder die Zusage streichen (Konstante und Kommentar entfernen) oder den Weg über die API umsetzen: bei gesetztem Token `api.github.com/repos/{repo}/releases/latest` lesen, die Asset-IDs von `update.json` und `unternehmensdaten.zip` ermitteln, das Manifest über `releases/assets/{id}` mit `Accept: application/octet-stream` holen und den Token über den Filter `http_request_args` (nur für `api.github.com`) auch an den Paket-Download hängen. Das Ratenlimit ist mit Token 5.000 Anfragen je Stunde und damit kein Argument mehr gegen die API. Für die Umleitung den Hook `requests-requests.before_redirect` nutzen und den `Authorization`-Header entfernen, sobald der Host wechselt.

### [F-02] Vorabversionen werden als reguläres Release veröffentlicht und sofort verteilt
Schwere: Mittel
Status: BESTÄTIGT (Updater), Workflow-Verhalten aus der Dokumentation der Action
Bereich: Auslieferung
Ort: `.github/workflows/release.yml:6-9` und `:166-173`; `includes/class-undt-updater.php:211` und `:287`
Beschreibung: Der Workflow läuft für jeden Tag `v*`. `softprops/action-gh-release@v2` legt ohne `prerelease: true` ein normales Release an, und `make_latest` folgt der GitHub-Voreinstellung, sodass es zum „Latest"-Release wird. Genau dieses Release liefert `releases/latest/download/update.json` aus. Der Updater akzeptiert Versionsnummern mit Suffix (`-beta.1`, `-rc1`) ausdrücklich und `version_compare('0.5.0-beta.1', '0.4.0', '>')` ist wahr.
Szenario: Ein Tag `v0.5.0-beta.1` wird gepusht, um einen Testlauf zu machen. Innerhalb von sechs Stunden (TTL des Transients) bieten alle Kundenseiten die Beta als Aktualisierung an; Seiten mit eingeschalteten automatischen Aktualisierungen installieren sie ohne Zutun. Reproduktion des Updater-Teils: `php dev/tests/test-audit-findings.php`, Abschnitt „Vorabversionen im Updater": ein Manifest mit `9.9.9-beta.1` landet in `$transient->response`.
Auswirkung: Ein Test-Tag ist ein Produktions-Deployment für alle Kunden.
Vorschlag: Im Workflow `prerelease: ${{ contains(github.ref_name, '-') }}` setzen (und `make_latest: false` für diesen Fall). Zusätzlich im Updater Versionen mit Bindestrich-Suffix verwerfen, sofern nicht ein Filter `undt_update_allow_prerelease` das erlaubt — dann schützt der Updater auch vor einem versehentlich manuell erstellten Release.

### [F-03] Slim SEO wird nicht erkannt, JSON-LD wird doppelt ausgegeben
Schwere: Mittel
Status: BESTÄTIGT
Bereich: Kompatibilität
Ort: `includes/class-undt-schemaorg.php:53`
Beschreibung: Die Erkennung prüft `SLIM_SEO_VERSION`. Slim SEO 4.10.1 definiert `SLIM_SEO_VER` (`slim-seo.php`, Zeile 35), daneben `SLIM_SEO_DIR`, `SLIM_SEO_URL`, `SLIM_SEO_REDIRECTS`, `SLIM_SEO_DB_VER`. Die Konstante `SLIM_SEO_VERSION` existiert nicht. Die anderen vier Erkennungen stimmen: `WPSEO_VERSION` (Yoast 28.4, `wp-seo-main.php:18`), `RANK_MATH_VERSION` und Klasse `RankMath` (Rank Math 1.0.278, `rank-math.php:30, :248`), `SEOPRESS_VERSION` (SEOPress 10.2, `seopress.php:40`), globale Funktion `aioseo()` (AIOSEO 5.0.1.1, `app/AIOSEO.php:443`, im globalen Namensraum) und `AIOSEO_VERSION`.
Szenario: Slim SEO ist aktiv, das Plugin steht auf „Nur wenn kein SEO-Plugin aktiv ist". `detect_seo_plugin()` liefert `''`, `should_output()` wahr. Reproduktion: `php dev/tests/test-audit-findings.php`, Abschnitt „Erkennung von Slim SEO".
Auswirkung: Zwei Organization-Auszeichnungen auf jeder Seite, genau das, was die Einstellung verhindern soll.
Vorschlag: `defined( 'SLIM_SEO_VER' ) || defined( 'SLIM_SEO_VERSION' )`.

### [F-04] Bricks: die dokumentierten `{echo:undt_get(…)}`-Aufrufe sind seit Bricks 1.9.7 gesperrt
Schwere: Mittel
Status: VERMUTET
Bereich: Kompatibilität
Ort: `admin/views/shortcodes.php:221-222`, `readme.txt:93`; keine Freigabe in `includes/class-undt-api.php`
Beschreibung: Seit Bricks 1.9.7 (Februar 2024) führt der Dynamic-Data-Tag `{echo:…}` nur Funktionen aus, die über den Filter `bricks/code/echo_function_names` freigegeben sind („Starting at Bricks 1.9.7, you must explicitly allow any function names you want to call via Bricks' dynamic data echo tag"). Das Plugin gibt seine Funktionen nicht frei, dokumentiert die Aufrufe aber als Standardweg.
Szenario: Ein Kunde folgt der Anleitung auf der Shortcode-Seite und setzt `{echo:undt_get('phone')}` in ein Bricks-Element. Die Ausgabe bleibt leer. Nicht reproduziert, weil Bricks kostenpflichtig ist; die Aussage stützt sich auf die Bricks-Academy-Dokumentation und den Changelog 1.9.7.
Auswirkung: Die beworbene Bricks-Anbindung für Einzelwerte funktioniert nicht ohne eigenen Code im Child-Theme.
Vorschlag: In `UNDT_Api::register()`:
```php
add_filter( 'bricks/code/echo_function_names', static function ( $names ) {
    return array_merge( (array) $names, array( 'undt_get', 'undt_has', 'undt_field', 'undt_query', 'undt_loop', 'undt_is_open', 'undt_today' ) );
} );
```
Die Filter `bricks/setup/control_options` (`$options['queryTypes'][…]`) und `bricks/query/run` (`$results, $query_obj`, Prüfung über `$query_obj->object_type`) entsprechen der aktuellen Dokumentation. Offen bleibt, ob Bricks für eigene Query-Typen `$query_obj->query_vars` befüllt; die Dokumentation nennt `$query_obj->settings`. Die Argumente `undt_group` und `posts_per_page` kämen dann nicht an, die Schleife liefe aber mit allen Zeilen (siehe [H-05]).

### [F-05] Geleerte Felder fallen auf die Voreinstellung zurück
Schwere: Mittel
Status: BESTÄTIGT
Bereich: Korrektheit
Ort: `includes/class-undt-content.php:73-75`; `includes/class-undt-store.php:87-89`
Beschreibung: `UNDT_Content::value()` und `UNDT_Store::get()` liefern die Voreinstellung nicht nur, wenn ein Feld nie gespeichert wurde, sondern auch, wenn der gespeicherte Wert ein Leerstring ist. Betroffen sind alle Felder mit `default`: Fußnote der Preisliste (PAngV-Text), `suffix` („Uhr"), `closed_label` („geschlossen"), `small_business_note`, `job_title_country`, `insurance_scope`.
Szenario: Ein B2B-Anbieter leert die Fußnote, weil er Nettopreise ausweist, und speichert. Die Preisliste zeigt weiterhin „Alle Preise verstehen sich als Gesamtpreise inklusive der gesetzlichen Umsatzsteuer." Reproduktion: `php dev/tests/test-audit-findings.php`, Abschnitt „Geleerte Felder", fünf Prüfungen zeigen den Befund.
Auswirkung: Eine inhaltlich falsche, rechtlich relevante Aussage (Umsatzsteuer) lässt sich nur über `footnote="0"` an jedem Shortcode oder durch Überschreiben mit anderem Text loswerden. Beim Kleinunternehmer-Hinweis gilt dasselbe.
Vorschlag: Die Voreinstellung nur greifen lassen, wenn der Schlüssel im gespeicherten Array fehlt (`! array_key_exists`), nicht bei Leerstring. Da die Formulare alle sichtbaren Felder senden und die Sanitizer zusammenführen, bleibt ein bewusst geleertes Feld dann leer, ein nie gespeichertes bekommt weiter die Voreinstellung. Für Felder, die per `when` ausgeblendet sind, ändert sich nichts, weil sie nicht gesendet werden.

### [F-06] Die Seitenauswahl listet nur veröffentlichte Seiten, Verknüpfungen gehen beim Speichern verloren
Schwere: Mittel
Status: BESTÄTIGT
Bereich: Korrektheit
Ort: `admin/class-undt-fields.php:405-414`
Beschreibung: `wp_dropdown_pages()` ruft `get_pages()` mit der Voreinstellung `post_status => 'publish'` auf (WordPress 7.1, `wp-includes/post.php`, Funktion `get_pages`, Zeile 17 der Funktion). Eine gespeicherte Seite im Status Entwurf, Privat, Ausstehend oder Geplant erscheint deshalb nicht in der Auswahl; der Browser wählt „— keine Seite —" (Wert 0).
Szenario: Der Admin nimmt die Datenschutzerklärung zum Überarbeiten in den Entwurf zurück, ändert danach die Telefonnummer in den Stammdaten und speichert. `page_privacy` wird als 0 übermittelt und gespeichert. Nach dem erneuten Veröffentlichen fehlt der Link im Footer, und die Prüfung meldet nichts, weil die Seite gar nicht mehr verknüpft ist. Reproduktion in Playground (`dev/audit/env.php`, Abschnitt Backend): mit gespeicherter Entwurfsseite 5 enthält das Select `undt_company[page_privacy]` nur „— keine Seite —", „Alle Bloecke", „Impressum", „Ohne Bloecke" und „Sample Page", keine Option `value="5"`; gleiches Bild unter WordPress 6.4.10 / PHP 7.4.33 und unter WordPress 7.1 / PHP 8.3 bis 8.5.
Auswirkung: Stiller Verlust einer rechtlich relevanten Verknüpfung; der Footer verliert den Link auf Impressum oder Datenschutzerklärung.
Vorschlag: `'post_status' => array( 'publish', 'private', 'draft', 'pending', 'future' )` an `wp_dropdown_pages()` geben; `get_pages()` akzeptiert Arrays. Die Ausgabe im Frontend prüft ohnehin auf `publish`. Optional den Status in der Beschriftung kennzeichnen.

### Niedrig

### [F-07] Multisite: auf Unterseiten werden `undt_prices` und `undt_faq` autoloaded angelegt
Schwere: Niedrig
Status: BESTÄTIGT
Bereich: Kompatibilität / Performance
Ort: `unternehmensdaten.php:90-126`; `admin/class-undt-admin.php:258-269`
Beschreibung: Bei netzwerkweiter Aktivierung ruft WordPress den Aktivierungshaken nur im Kontext der Hauptseite auf. Auf Unterseiten entstehen die Optionen erst beim ersten Speichern über `options.php`, das `update_option()` ohne `autoload` aufruft. Für eine fehlende Option ruft `update_option()` `add_option( …, '', $autoload )` mit `null` auf; WordPress 6.6+ speichert dann `auto` (autoloaded, solange die Option klein ist), WordPress 6.4 speichert `yes`.
Szenario: Playground, Multisite (PHP 8.3.33, WordPress 7.1, `--site-url http://multisite.test`), Plugin per `activate_plugin( …, '', true )` netzwerkweit aktiviert, Unterseite 2 per `wp_insert_site()` angelegt, dort `update_option( 'undt_prices', … )` wie durch `options.php`: Spalte `autoload` in `wp_2_options` ist `auto` für `undt_prices` und `undt_faq` (erwartet `off`). Auf der Hauptseite stehen die Werte richtig (`on`/`off`). Der Site-Admin der Unterseite hat dabei `manage_options`, aber kein `unfiltered_html`.
Auswirkung: Wenige Kilobyte je Seitenaufruf mehr in `alloptions`; funktional kein Fehler.
Vorschlag: Beim Anlegen neuer Seiten (`wp_initialize_site`, Priorität nach WordPress' eigenen Haken) und einmalig auf `admin_init` je Site prüfen, ob die Modul-Optionen existieren, und sie sonst mit `add_option( …, '', 'no' )` anlegen; alternativ in den Sanitize-Callbacks `wp_set_option_autoload_values()` nachziehen.

### [F-08] Der Einzelfeld-Shortcode verlinkt unveröffentlichte Seiten
Schwere: Niedrig
Status: BESTÄTIGT
Bereich: Korrektheit
Ort: `includes/class-undt-shortcodes.php:135-146`
Beschreibung: Für Felder vom Typ `page` prüft `field()` nur, ob ein Titel existiert, nicht den Status. `legal_nav()` prüft dagegen auf `publish`.
Szenario: `[undt key="page_privacy" link="1"]` mit einer Entwurfsseite gibt `<a href="…?p=77">Datenschutz (Entwurf)</a>` aus; Besucher landen auf 404, bei privaten Seiten steht „Privat:" im Linktext. Reproduktion: `php dev/tests/test-audit-findings.php`, Abschnitt „Einzelfeld-Shortcode und unveröffentlichte Seiten"; in Playground auf der Seite „Alle Bloecke" (Matrix).
Auswirkung: Toter Link und Preisgabe eines Entwurfstitels.
Vorschlag: In `case 'page'` zusätzlich `'publish' !== get_post_status( $id )` abfangen und `''` liefern.

### [F-09] Zeitfenster über Mitternacht endet rechnerisch am selben Tag, gleiche Uhrzeiten gelten als rund um die Uhr
Schwere: Niedrig
Status: BESTÄTIGT
Bereich: Korrektheit
Ort: `includes/class-undt-hours.php:393-400`; `:129-162` (keine Prüfung `to > from`)
Beschreibung: `is_open_now()` betrachtet nur die Fenster des heutigen Wochentags. Ein Fenster 22:00 – 02:00 am Freitag gilt am Samstag um 01:00 nicht mehr, weil dann Samstags Fenster geprüft werden. Zudem wird `to <= from` als „über Mitternacht" gewertet, sodass `09:00 – 09:00` (Tippfehler) `$now >= from || $now < to` immer erfüllt: dauerhaft geöffnet.
Szenario: Gastronomie mit Freitag 22:00 – 02:00; `[undt_open_now]` zeigt Samstag um 01:00 „Zurzeit geschlossen". Reproduktion: `php dev/tests/test-audit-findings.php`, Abschnitt „Zeitfenster über Mitternacht".
Auswirkung: Falscher Öffnungsstatus in genau dem Fall, für den die Mitternachtslogik gedacht ist; ein Tippfehler macht „Jetzt geöffnet" zum Dauerzustand.
Vorschlag: In `normalize()` Fenster mit `to === from` verwerfen. In `is_open_now()` zusätzlich die Fenster des Vortags prüfen, die über Mitternacht reichen (`to < from` und `$now < to`). In `today()` diese Fenster ebenfalls berücksichtigen.

### [F-10] Arrays statt Strings in der Moduleingabe erzeugen PHP-Warnungen und speichern „Array"
Schwere: Niedrig
Status: BESTÄTIGT
Bereich: Sicherheit (Robustheit)
Ort: `includes/class-undt-content.php:260-290` (jeder `(string) $value`-Cast); `includes/class-undt-sanitizer.php:37`
Beschreibung: `UNDT_Sanitizer::value()` fängt Arrays ab (`is_array → ''`), `UNDT_Content::sanitize_value()` und `UNDT_Sanitizer::profile()` nicht. Ein Feldname mit `[]` in einem präparierten POST (`undt_prices[intro][]=x`) ergibt `Array to string conversion` und den gespeicherten Text „Array".
Szenario: Ein Admin (oder ein CSRF-Opfer, wenn die Nonce bekannt wäre; realistisch also nur der Admin selbst mit manipuliertem Formular) sendet Arrays. Bei `display_errors` erscheint die Warnung vor der Umleitung von `options.php`, was „headers already sent" auslöst. Reproduktion offline: `php dev/tests/test-audit-findings.php`, Abschnitt „Arrays statt Strings" (vier Warnungen, `intro="Array"`); in Playground in jedem Matrixlauf (`class-undt-content.php:290`, unter PHP 7.4 ein Notice, ab 8.0 eine Warnung).
Auswirkung: Kein Sicherheitsproblem, aber eine fehlende zweite Verteidigungslinie und unsaubere Daten.
Vorschlag: Am Anfang von `sanitize_value()` für alle Nicht-Repeater-Typen `if ( is_array( $value ) ) { $value = ''; }`; in `profile()` ebenso vor dem Cast.

### [F-11] Medienvorschau im Backend baut `<img src>` per String in `innerHTML`
Schwere: Niedrig
Status: BESTÄTIGT (Muster), Ausnutzbarkeit nicht gezeigt
Bereich: Sicherheit
Ort: `admin/assets/admin.js:576-577`
Beschreibung: Die URL aus `wp.media` wird unmaskiert in HTML eingebettet. Die Quelle ist `wp_prepare_attachment_for_js()` → `wp_get_attachment_url()`, deren Bestandteile (Upload-Basis-URL, Dateiname) vom Server stammen. `sanitize_file_name()` entfernt `"`, `'`, `<`, `>`, `&`, `(`, `)` und weitere Zeichen aus Dateinamen (WordPress 7.1, `wp-includes/formatting.php`, Funktion `sanitize_file_name`, Liste `$special_chars`), und `_wp_attached_file` ist geschütztes Meta. Ein Autor, der Dateien hochlädt, kann die URL daher nicht mit einem Attributabbruch versehen. Ein Weg über `upload_url_path`, `siteurl` oder ein anderes Plugin, das URLs umschreibt, bliebe offen, setzt aber Admin-Rechte voraus.
Szenario: Kein praktischer Angriffspfad gefunden. Der Sink ist dennoch die einzige Stelle im Admin-JavaScript, die Daten unmaskiert in HTML setzt.
Auswirkung: Heute keine; das Muster ist die Sorte, die bei der nächsten Änderung zur Lücke wird.
Vorschlag: `var img = document.createElement( 'img' ); img.src = url; img.alt = ''; preview.replaceChildren( img );`

### [F-12] `profile.php` schreibt beim bloßen Aufruf per GET eine Option ohne Nonce
Schwere: Niedrig
Status: BESTÄTIGT
Bereich: Sicherheit
Ort: `admin/views/profile.php:19-21`; `includes/class-undt-store.php:167-169`
Beschreibung: Der Aufruf der Seite „Rechtsform & Umfang" setzt `undt_setup_done` auf 1. Das ist ein Zustandswechsel per GET ohne Nonce; er ist idempotent und blendet nur den Einrichtungshinweis aus.
Szenario: Ein Link auf `admin.php?page=undt-profile` in einer E-Mail; der Admin klickt, der Hinweis verschwindet dauerhaft. Reproduktion: der Playground-Prüflauf rendert `profile.php` und setzt damit die Option.
Auswirkung: Praktisch keine. Es bleibt ein Verstoß gegen das Prinzip, dass GET nichts ändert.
Vorschlag: Die Markierung im Sanitize-Callback des Profils setzen (beim ersten Speichern), oder den Hinweis mit einem nonce-geschützten „Ausblenden"-Link versehen.

### [F-13] `uninstall.php` lässt den Site-Transient `undt_update_info` stehen
Schwere: Niedrig
Status: BESTÄTIGT
Bereich: Korrektheit
Ort: `uninstall.php:30-45`
Beschreibung: Gelöscht werden die zehn Optionen; der Site-Transient des Updaters (auf Einzelinstallationen die Optionen `_site_transient_undt_update_info` und `_site_transient_timeout_undt_update_info`) bleibt bis zum Ablauf, bei einem gemerkten Fehlschlag 30 Minuten, sonst sechs Stunden.
Szenario: Playground, jeder Matrixlauf: nach `delete_plugins()` mit `keep_data=0` sind alle `undt_*`-Optionen weg, `get_site_transient( 'undt_update_info' )` liefert weiterhin einen Wert.
Auswirkung: Datenrest von wenigen hundert Byte, der sich selbst aufräumt.
Vorschlag: `delete_site_transient( 'undt_update_info' )` einmal (nicht je Site) in `uninstall.php`.

### [F-14] Release-Workflow: Actions nur per Tag angeheftet, Ausdrücke in `run`-Blöcken, `.gitattributes` im Archiv
Schwere: Niedrig
Status: BESTÄTIGT (`.gitattributes` im veröffentlichten Archiv, Tag-Pinning), VERMUTET (Injektion)
Bereich: Auslieferung
Ort: `.github/workflows/release.yml:23, 26, 167` (Tags), `:98, :120-121, :141-143` (`${{ }}` in `run`), `:74-83` (Ausschlussliste)
Beschreibung: (1) `actions/checkout@v4`, `shivammathur/setup-php@v2` und `softprops/action-gh-release@v2` sind an bewegliche Tags gebunden. Dieser Workflow erzeugt mit `contents: write` das Artefakt, das alle Kundenseiten installieren; eine kompromittierte Action könnte das Archiv verändern. (2) `${{ steps.version.outputs.requires }}`, `tested`, `php` und `version` werden direkt in Shell-Code interpoliert. Die Werte stammen aus `readme.txt` und dem Tag-Namen. Ein Tag darf Zeichen wie `"`, `;`, `$`, `|` enthalten; die Header-Prüfung verlangt aber, dass Plugin-Header und Tag gleich sind, sodass nur jemand mit Schreibrecht auf Repository und Tags einen Wert unterbringen kann, der ohnehin den Workflow ändern könnte. Praktischer Unterschied: ein Token mit `contents`-, aber ohne `workflow`-Berechtigung. (3) Die rsync-Ausschlussliste kennt `.gitattributes` nicht; das veröffentlichte Archiv v0.4.0 enthält `unternehmensdaten/.gitattributes` (32 Einträge, 27 Dateien; das lokale `build.ps1` erzeugt 26 Dateien). Der übrige Inhalt ist nach Normalisierung der Zeilenenden identisch (`diff -r --strip-trailing-cr`).
Szenario: Siehe Beschreibung; das erzeugte `update.json` des Releases ist gültiges JSON mit den erwarteten Feldern.
Auswirkung: (1) Lieferkettenrisiko für alle Kundenseiten. (2) Gering. (3) Eine überflüssige Datei im Plugin-Ordner.
Vorschlag: Actions an Commit-SHAs anheften (`actions/checkout@<sha> # v4.x`), Ausdrücke in `env:` legen und im Skript als `"$VERSION"` verwenden, `--exclude '.gitattributes'` ergänzen.

### [F-15] Infobanner: nach dem Schließen geht der Tastaturfokus verloren
Schwere: Niedrig
Status: BESTÄTIGT
Bereich: Barrierefreiheit
Ort: `includes/class-undt-blocks.php:668`
Beschreibung: Der Schließen-Button versteckt das Banner (`e.hidden = true`), während er selbst den Fokus hat. Der Fokus fällt auf `body` zurück; Screenreader und Tastaturnutzer verlieren ihre Position (WCAG 2.4.3 Fokus-Reihenfolge, 3.2.2).
Szenario: Playground-Server (PHP 8.3, Twenty Twenty-Five) im Chromium der Browser-Ansicht: Button fokussiert (`document.activeElement === btn`), Klick, 300 ms gewartet, `document.activeElement.tagName` ist `BODY`. Unmittelbar nach dem Klick meldet der Browser noch den Button, weil die Fokus-Korrektur bei `display:none` asynchron läuft. Das Schließen selbst funktioniert: der Schlüssel `undt-banner-<id>` liegt in `localStorage`, nach dem Neuladen ist das Banner bereits beim Laden versteckt.
Auswirkung: Für Tastaturnutzer beginnt die Seite nach dem Schließen von vorn.
Vorschlag: Vor dem Verstecken das nächste fokussierbare Element bestimmen oder dem auf das Banner folgenden Landmark `tabindex="-1"` geben und es fokussieren; alternativ `main` fokussieren.

### [F-16] `opacity` auf Hinweistexten und Schließen-Button drückt den Kontrast unter 4,5:1
Schwere: Niedrig
Status: BESTÄTIGT (rechnerisch)
Bereich: Barrierefreiheit
Ort: `includes/class-undt-blocks.php:687, 696` (`opacity:.75`), `:728` (`opacity:.7` beim Hover)
Beschreibung: Das Plugin setzt bewusst keine Farben, `opacity` mischt die Themefarbe aber mit dem Hintergrund. Bei Themetext `#555` auf Weiß ergibt `.75` ein Verhältnis von 3,95:1 (Grenze 4,5:1; Anlass bei Sondertermin, Zusatz bei Preisen, beide zusätzlich `font-size:.9em`). Bei `#333` bleiben 5,74:1. Der Schließen-Button erreicht beim Hover mit `.7` auf den Voreinstellungen 3,87:1 (success), 3,80:1 (warning), 4,27:1 (urgent). Ob das reicht, hängt von der Grundschrift des Themes ab: `1.4em` ergibt in Twenty Twenty-Five 30,5 px (im Browser gemessen), also großen Text mit Grenze 3:1, bei 16 px Basis aber 22,4 px, also normalen Text mit Grenze 4,5:1. Die Bannerfarben selbst sind in Ordnung: Text 13,08 / 8,15 / 7,94 / 8,65 : 1; der Akzentbalken liegt bei 4,60 / 4,49 / 2,96 / 4,95 : 1 und ist als Dekoration nicht kontrastpflichtig.
Szenario: Berechnung nach WCAG-Formel, `dev/audit`-Skript `contrast.js` (im Scratchpad ausgeführt); Werte oben.
Auswirkung: Bei verbreiteten Themefarben unterschreiten zwei Textarten die AA-Grenze, auf Seiten, die unter das BFSG fallen.
Vorschlag: `opacity` entfernen und nur die Schriftgröße reduzieren; beim Hover statt `opacity` eine Unterstreichung oder einen Rahmen verwenden.

### [F-17] `legal_forms()` wird bei jedem Feldzugriff neu aufgebaut
Schwere: Niedrig
Status: BESTÄTIGT (Messung)
Bereich: Performance
Ort: `includes/class-undt-schema.php:38-182` (kein Laufzeit-Cache), `:977-980` (Aufruf aus `applies()`), `includes/class-undt-store.php:80`
Beschreibung: `UNDT_Store::get()` ruft `applies()`, das `legal_form()` und damit `legal_forms()` aufruft: 37 `__()`-Aufrufe je Feldzugriff. Gemessen in Playground über den Filter `gettext`: 151 Aufrufe beim `init` jedes Requests (Registeraufbau), 786 für ein `[undt_impressum]`, 912 für `quick_count()` (auf jeder Backend-Seite), 979 für die Kombination aus Impressum, Footer, Öffnungszeiten, Social, Banner und JSON-LD. 20 Aufrufe von `legal_forms()` kosten in WASM 1 bis 2 ms; nativ liegt der Aufwand für eine Impressumsseite unter 1 ms, mit geladener Übersetzung entsprechend mehr.
Szenario: Siehe Messung (`dev/audit/result-*.txt`, Abschnitt „Datenbankabfragen der Bloecke").
Auswirkung: Messbar, nicht spürbar. Datenbankabfragen entstehen dadurch keine.
Vorschlag: `legal_forms()`, `register_types()`, `profile_questions()`, `tabs()`, `sections()` und `platforms()` wie `fields()` in statischen Eigenschaften zwischenspeichern.

### Hinweise

### [H-01] `Tested up to: 6.4` führt zu „Nicht getestet" in der Aktualisierungsliste
Schwere: Hinweis
Status: BESTÄTIGT (Code-Lesung)
Bereich: Auslieferung
Ort: `readme.txt:5`; `update.json` des Releases (`"tested": "6.4"`)
Beschreibung: `wp-admin/update-core.php` zeigt „Compatibility with WordPress 7.1: Not tested", wenn `tested` kleiner als die laufende Version ist, und das Detailfenster warnt „This plugin has not been tested with your current version of WordPress". Getestet wurde tatsächlich bis 7.1.
Vorschlag: `Tested up to` mit jedem Release auf die geprüfte Hauptversion setzen; der Workflow übernimmt den Wert automatisch.

### [H-02] `<address>` für fremde Anschriften, `target="_blank"` ohne Hinweis
Schwere: Hinweis
Bereich: Barrierefreiheit
Ort: `includes/class-undt-render.php:273-279, 417, 614, 638`; `includes/class-undt-blocks.php:432`
Beschreibung: Die HTML-Spezifikation: „The address element represents the contact information for its nearest article or body element ancestor" und „must not be used to represent arbitrary addresses (e.g. postal addresses), unless those addresses are in fact the relevant contact information". Aufsichtsbehörde, Versicherer, Schlichtungsstelle und Datenschutz-Aufsichtsbehörde sind keine Kontaktdaten des Anbieters. Für Firmenanschrift, Datenschutzbeauftragten und Verantwortlichen ist `<address>` richtig. Social-Links mit `new_tab` erhalten `target="_blank"` ohne Text- oder `aria-label`-Hinweis; die Hilfe im Backend weist darauf hin, die Ausgabe nicht.
Vorschlag: Für die fremden Anschriften `<p>` oder `<div>`; beim neuen Tab `aria-label` oder ein `screen-reader-text`-Zusatz „(öffnet in neuem Tab)".

### [H-03] Ein Sondertermin ohne Zeiten und ohne Schalter gilt als geschlossen
Schwere: Hinweis
Bereich: Korrektheit
Ort: `includes/class-undt-hours.php:354-356`; `includes/class-undt-blocks.php:217`
Beschreibung: Wer nur Datum und Anlass einträgt („Betriebsausflug"), aber weder Zeiten noch „Geschlossen" setzt, erzeugt für diesen Tag „geschlossen" in Tabelle, `[undt_hours_today]` und `[undt_open_now]`. Die Hilfe sagt nur, dass ein Eintrag die reguläre Zeit überschreibt.
Vorschlag: Entweder im Sanitizer solche Zeilen als `closed` markieren und das in der Hilfe sagen, oder Zeilen ohne Zeiten und ohne Schalter als bloßen Hinweis behandeln und die regulären Zeiten beibehalten.

### [H-04] `esc_attr()` für Attributnamen in `toggle()`
Schwere: Hinweis
Bereich: Sicherheit
Ort: `admin/class-undt-fields.php:742`
Beschreibung: Verdachtsstelle aus dem Auftrag, geprüft und als unkritisch bewertet. Beide Aufrufer (`hours()` in derselben Datei, `settings.php`, `profile.php`) übergeben feste Namen (`data-undt-closed`, `aria-label`). `esc_attr()` maskiert `"`, `'`, `<`, `>`, `&`; ein Attributname kann damit kein Attribut schließen. Kein Fehler, solange keine externen Aufrufer Attributnamen liefern.
Vorschlag: Falls die Methode je Attribute von außen annehmen soll, Namen gegen `/^[a-z][a-z0-9-]*$/` prüfen.

### [H-05] Bricks-Query-Argumente `undt_group` und `posts_per_page` ungeprüft
Schwere: Hinweis
Bereich: Kompatibilität
Ort: `includes/class-undt-api.php:374-382`
Beschreibung: `bricks_run()` liest `$query->query_vars`. Ob Bricks diese Eigenschaft für eigene Query-Typen befüllt, konnte ohne Bricks nicht geprüft werden; die Dokumentation zu `bricks/query/run` nennt `$query_obj->settings`. Kommen die Argumente nicht an, liefert die Schleife alle Zeilen ohne Gruppenfilter und ohne Begrenzung, bleibt aber lauffähig.
Vorschlag: Zusätzlich `$query->settings['query']` auswerten, sobald ein Test gegen Bricks möglich ist.

### [H-06] Werkzeughinweis: Playground-CLI ignoriert `--php` und `--wp` bei `run-blueprint`
Schwere: Hinweis
Bereich: Auslieferung (Testwerkzeuge, nicht das Plugin)
Ort: `dev/README.md`, `dev/install-test.json`
Beschreibung: Mit `@wp-playground/cli` (Stand 14.09.2026) liefert `run-blueprint --php 7.4 --wp 6.4` PHP 8.5.10 und WordPress 7.1; nur `preferredVersions` im Blueprint wirkt (Sonde: PHP 7.4.33, WordPress 6.4.10). Die Voreinstellung ohne Angabe ist entgegen der Hilfe nicht 8.3, sondern 8.5. Bisherige Läufe mit `install-test.json` (`"php": "8.3"`) waren davon nicht betroffen, aber jede Version muss im Blueprint stehen und im Ergebnis mitprotokolliert werden. Die Blueprints unter `dev/audit/` tun beides.

## 3. Kompatibilitätsmatrix

Jeder Lauf installiert `unternehmensdaten.zip` (aus `build.ps1`, inhaltsgleich mit dem Release) über den WordPress-Installer, legt Beispieldaten und vier Testseiten an, rendert Startseite, eine Seite mit allen Blöcken und eine Seite ohne Blöcke (mit und ohne Banner) und prüft rund 90 Punkte (`dev/audit/env.php`, Ergebnisse in `dev/audit/result-<name>.txt`). Die vier in jeder Umgebung fehlschlagenden Punkte sind die Befunde [F-06], [F-08], [F-10] und [F-13]; die tatsächlich verwendeten Versionen stammen aus `PHP_VERSION` und `$wp_version` des jeweiligen Laufs.

| Umgebung | Tatsächlich verwendet | Ergebnis |
|---|---|---|
| PHP 7.4, WordPress 6.4 (versprochenes Minimum) | PHP 7.4.33, WordPress 6.4.10, Twenty Twenty-Four 1.0 | 86 OK, 4 erwartete FAIL. Alle 15 Klassen laden, alle Ansichten und Shortcodes laufen, kein Fatal Error, keine Meldung aus Plugin-Dateien außer dem Notice aus [F-10]. `autoload` als `yes`/`no` gespeichert. |
| PHP 8.3, WordPress aktuell (Hauptfall) | PHP 8.3.33, WordPress 7.1, Twenty Twenty-Five 1.5 | 86 OK, 4 erwartete FAIL. |
| PHP 8.4 mit `WP_DEBUG` | PHP 8.4.25, WordPress 7.1 | 86 OK, 4 erwartete FAIL. Keine Abkündigung, kein Notice aus Plugin-Dateien (eigener Error-Handler auf `/plugins/unternehmensdaten/` plus `debug.log`). |
| PHP 8.5 mit `WP_DEBUG` | PHP 8.5.10, WordPress 7.1 | 86 OK, 4 erwartete FAIL. Ebenfalls keine Abkündigung. |
| Multisite, netzwerkweit aktiviert | PHP 8.3.33, WordPress 7.1, Subdirectory-Netzwerk `multisite.test` | 91 OK, 6 FAIL: die vier erwarteten plus [F-07] für `undt_prices` und `undt_faq` auf der Unterseite. Deinstallation räumt Haupt- und Unterseite auf. |
| Blocktheme Twenty Twenty-Five | PHP 8.3.33, WordPress 7.1, Twenty Twenty-Five 1.5 | 86 OK, 4 erwartete FAIL. Banner über `wp_body_open`, Inline-CSS einmal im Footer, ohne Banner und Block kein CSS. |
| Klassisches Theme Twenty Twenty-One | PHP 8.3.33, WordPress 7.1, Twenty Twenty-One 2.9 | 86 OK, 4 erwartete FAIL. Gleiches Verhalten wie im Blocktheme. |
| Sprache de_DE | PHP 8.3.33, WordPress 7.1, `locale=de_DE` | 86 OK, 4 erwartete FAIL. Wochentage „Montag", Kürzel „Mo. – Fr." (WordPress-de_DE-Kürzel enden mit Punkt), Monatsname „Dezember", `start_of_week` 1, Öffnungsstatus richtig. |
| Yoast SEO 28.4 aktiv | PHP 8.3.33, WordPress 7.1 | 86 OK, 4 erwartete FAIL. Erkannt als „Yoast SEO", `should_output()` falsch, im HTML nur Yoasts JSON-LD. Yoasts Umleitung auf den Einrichtungsassistenten bei `admin_init` musste die Prüfung abfangen. |
| Rank Math 1.0.278 aktiv | PHP 8.3.33, WordPress 7.1 | 86 OK, 4 erwartete FAIL. Erkannt als „Rank Math", kein Plugin-JSON-LD. |
| Autoptimize 3.1.15.1 aktiv (JS, CSS und HTML optimieren, jeweils mit Inline-Aggregation) | PHP 8.3.33, WordPress 7.1 | 85 OK, 5 FAIL: die vier erwarteten plus ein Prüfpunkt, der das Banner-Skript im Klartext sucht. Autoptimize ersetzt das Inline-Skript hinter dem Banner durch `<script src="data:text/javascript;base64,…">` an derselben Stelle; dekodiert ist es das unveränderte Plugin-Skript, es läuft also weiter synchron direkt hinter dem Banner. Das späte Inline-CSS (`<style id="undt-inline-css">`) bleibt unverändert im Footer, Banner-Markup und JSON-LD sind vorhanden. Wer neben Autoptimize eine Content Security Policy ohne `data:` in `script-src` fährt, verliert das Schließen des Banners; das ist die bekannte CSP-Einschränkung aus Abschnitt 4 des Auftrags in neuer Form. |

Werkzeughinweise zu den Läufen: Die CLI-Schalter `--php`/`--wp` sind wirkungslos, die Versionen stehen deshalb in den Blueprints ([H-06]). Frontend-Seiten werden in eigenen `runPHP`-Schritten über `index.php` in-process gerendert, weil `wp_remote_get()` auf die eigene Playground-Site unter Last mit einem SQLite-Verbindungsfehler abbricht; Playground-Multisite braucht `--site-url` ohne Port. Der zweite Deinstallationsdurchlauf (`keep_data=1`) scheiterte in den späteren Läufen an einem Playground-Artefakt (das im Vorprozess gelöschte Plugin-Verzeichnis gilt als noch vorhanden, „The destination directory already exists"); der Beleg für `keep_data=1` stammt aus einem früheren Lauf mit Yoast, in dem Neuinstallation, Aktivierung und Löschung durchliefen und alle zehn Optionen erhalten blieben.

## 4. Verdachtsstellen aus Abschnitt 5 des Auftrags

| Nr. | Verdacht | Ergebnis |
|---|---|---|
| 1 | Private Repositories doppelt kaputt | Bestätigt, dreifach: [F-01] |
| 2 | `admin.js:576` innerHTML | Unsauberes Muster, kein Angriffspfad gefunden: [F-11] |
| 3 | `__()` vor `init` bei Aktivierung | Widerlegt. Die Aktivierung läuft in `plugins.php` und damit nach `init`; die Hauptdatei ruft beim Laden keine Übersetzungsfunktion auf. `_load_textdomain_just_in_time()` meldet nur, wenn eine Übersetzungsdatei für die Domain gefunden wird (WordPress 7.1, `wp-includes/l10n.php`), was ohne `.mo` nie eintritt. In keinem Matrixlauf steht eine `_doing_it_wrong`-Meldung im `debug.log`. |
| 4 | Multisite: autoload auf Unterseiten, Skript über Einstellungsfelder | Teil 1 siehe [F-07]. Teil 2 widerlegt: alle Feldtypen laufen durch `sanitize_text_field`, `sanitize_textarea_field`, `esc_url_raw` (nur http/https), `sanitize_email`, `absint` oder Auswahllisten; die Ausgabe ist überall mit `esc_html`, `esc_attr`, `esc_url` oder `JSON_HEX_TAG` maskiert. Playground-Prüfung „Sanitisierung gegen Skript": `<img onerror>`, `<script>`, `javascript:` und `<svg onload>` erreichen die Ausgabe nicht. `unfiltered_html` spielt keine Rolle, weil das Plugin nirgends `wp_kses_post` für Nutzereingaben verwendet. |
| 5 | `uninstall.php` und Site-Transient | Bestätigt: [F-13] |
| 6 | PHP 7.4 nie geprüft | Jetzt geprüft: PHP 7.4.33 mit WordPress 6.4.10, alle 15 Klassen laden, alle Ansichten und Shortcodes laufen, keine Meldung aus Plugin-Dateien außer dem Notice aus [F-10]. Ein Syntax-Scan nach 8.x-Konstrukten war leer. |
| 7 | `SLIM_SEO_VERSION` | Bestätigt, Konstante heißt `SLIM_SEO_VER`: [F-03]. Die vier anderen Erkennungen stimmen. |
| 8 | `esc_attr()` für Attributnamen | Kein Fehler: [H-04] |
| 9 | `profile.php` schreibt per GET | Bestätigt, geringe Auswirkung: [F-12] |
| 10 | Bricks-Signaturen | Filter-Signaturen stimmen mit der Dokumentation überein; die `{echo:}`-Aufrufe brauchen eine Freigabe: [F-04], [H-05] |
| 11 | Tooltip an `display:none` | Widerlegt. Die Accessible-Name-and-Description-Berechnung (accname 1.2, Schritt 2A) nimmt versteckte Knoten ausdrücklich in die Beschreibung auf, wenn sie direkt per `aria-describedby` referenziert sind. Das ist die etablierte Technik für versteckte Beschreibungen; NVDA, JAWS und VoiceOver lesen sie vor. |
| 12 | Banner-Fokus | Bestätigt: [F-15] |
| 13 | de_DE nie geprüft | Jetzt geprüft, siehe Matrix. |

## 5. Geprüft und unauffällig

- Shortcode-Attribute: alle 23 (`key` über `sanitize_key` gegen das Register; `link`, `obfuscate`, `inline`, `bare`, `intro`, `footnote`, `short`, `special`, `note`, `group` als Schalter; `before`, `after`, `fallback`, `prefix`, `closed_text`, `open_text` per `esc_html`; `heading_level` auf 2 bis 6 begrenzt; `show`, `style`, `name` gegen Listen; `separator` per `sanitize_text_field` und `esc_html`; `label` per `esc_attr`). Bricks: `undt_group` nur als Vergleichswert, `posts_per_page` als `int`.
- Ausgabe gespeicherter Daten: `UNDT_Render`, `UNDT_Blocks`, `UNDT_SchemaOrg` maskieren jeden Wert; das Banner-Skript nimmt nur einen auf Hexziffern gefilterten Schlüssel auf; JSON-LD mit `JSON_HEX_TAG | JSON_HEX_AMP` (`</script>` unmöglich, offline geprüft). Die Filter `undt_schema_organization` und `undt_query` werden auf Array gecastet und laufen durch dieselbe Kodierung beziehungsweise Maskierung.
- Backend-Formulare: alle über die Settings API mit `settings_fields()`; `option_page_capability_*` folgt `undt_capability`; der Admin-Post-Handler prüft Berechtigung und Nonce; Umleitung nur per `wp_safe_redirect` auf `admin_url()`; kein Open Redirect. Wird `undt_capability` auf Redakteure gesenkt, können diese nichts speichern, was nicht auch ein Admin dürfte, und keinen HTML-Code unterbringen.
- Update-Kanal: Hostliste greift vor `esc_url_raw` (nur https); `https://github.com@evil.example`, `https://evil.example#@github.com`, `github.com.`-Varianten und ähnliche Hosts werden abgelehnt (Offline-Tests plus Code-Lesung von `wp_parse_url`). Unsinnige oder abgeschnittene Manifeste (404, kein JSON, ohne Paket, Versionstext) führen zu einem 30-Minuten-Fehlschlag-Transient und lassen den WordPress-Transient unangetastet. `sections` laufen durch `wp_kses_post` und im Detailfenster nochmals durch `wp_kses( …, $plugins_allowedtags )` (`plugin-install.php:582`). `fix_folder()` fasst nur das eigene Plugin an, `move()` mit Überschreiben ist für den Zielordner im Upgrade-Verzeichnis richtig.
- Autoloader: Klassennamen können keine `.` enthalten, ein `\` bleibt unterhalb von `includes/` oder `admin/`, das Präfix `class-undt-` und die Endung `.php` sind fest. Keine Pfadmanipulation möglich.
- Doppelter Sanitize-Aufruf beim ersten `add_option`: alle Callbacks sind idempotent (Textfilter, Listenabgleich, Normalisierung der Zeiten), die zusammenführende Logik liest den Datenbankstand, nicht die Eingabe.
- Rechtsformwechsel: ausgeblendete Felder werden nicht übermittelt und bleiben erhalten; die Ausgabe filtert über `applies()`, sodass nichts Falsches erscheint. Offline-Tests „GmbH & Co. KG" und „Arztpraxis" bestehen.
- Zeitzone und Tageswechsel: `current_datetime()` in der Zeitzone der Website für `today()`, `upcoming_special()` und `is_open_now()`; `format( 'D' )` ist nicht sprachabhängig; Sondertermin am heutigen Tag hat Vorrang (Offline-Tests).
- Gelöschte oder unveröffentlichte Seiten und Anhänge: Footer und Prüfung reagieren richtig (Entwurf wird nicht verlinkt, Prüfung meldet ihn, findet dort „§ 5 TMG" und den OS-Link); gelöschtes Logo fällt aus dem JSON-LD; Ausnahme siehe [F-08].
- Aktivieren, Deaktivieren, Löschen: Daten überleben Deaktivierung und erneute Aktivierung; die Aktivierung zieht einen manipulierten autoload-Wert von `undt_prices` nach; `keep_data=0` entfernt alle zehn Optionen, `keep_data=1` lässt sie stehen (Playground, jeweils eigener Prozess).
- Performance: null zusätzliche Datenbankabfragen für Stammdaten, Öffnungszeiten, Social, Banner und JSON-LD (Playground, `SAVEQUERIES`: nach Cache-Leerung nur die `alloptions`-Abfrage plus je eine `wp_posts`-Abfrage je verknüpfter Rechtsseite für `legal_nav()`, bis zu vier, die WordPress danach zwischenspeichert); genau je eine Abfrage für `undt_prices` und `undt_faq` beim ersten Zugriff. Autoloaded Optionen mit realistischen Beispieldaten: 3.452 Byte gesamt. Admin-Assets nur auf den eigenen Seiten (Dashboard: keine; `toplevel_page_undt`: beide).
- Frontend-Markup: Überschriftenhierarchie folgt `heading_level` und ist innerhalb der Blöcke sprungfrei; `dl` mit `div`-Gruppen (HTML 5.2), `th scope="row"` in allen Tabellen, `nav` mit `aria-label` für Rechtslinks und Social, `details`/`summary` ohne JavaScript, `time datetime` bei Sonderterminen, `role="region"` mit `aria-label` am Banner.
- Themes: Twenty Twenty-Five (Block) und Twenty Twenty-One (klassisch) geben das Banner über `wp_body_open` aus, das Style-Handle wird einmal im Footer gedruckt, ohne Block und ohne Banner erscheint kein CSS.
- SEO-Plugins und Optimierung: Yoast SEO 28.4 und Rank Math 1.0.278 werden erkannt, das Plugin-JSON-LD unterbleibt in der Voreinstellung; Autoptimize 3.1.15.1 lässt Banner, Inline-CSS und JSON-LD funktionsfähig (Details in der Matrix). Sprache de_DE: Wochentage, Kürzel und Monatsnamen kommen aus `WP_Locale` und `date_i18n()`, `start_of_week` wird übernommen.
- PHP 8.4 und 8.5 mit `WP_DEBUG`: keine Abkündigung und kein Notice aus Plugin-Dateien; PHP 7.4 / WordPress 6.4: kein Fatal Error, alle Klassen, Ansichten und Shortcodes laufen.
- Übersetzungsladen: kein `_doing_it_wrong` in einem der elf `debug.log` (siehe Abschnitt 4, Punkt 3).
- Zeilenenden: im Arbeitsordner haben 16 Dateien CRLF, das Release hat LF (`* text=auto`). Für PHP, CSS und JavaScript ohne Belang.

## 6. Fachliche Stichprobe zum Rechtsstand

Keine Rechtsberatung. Geprüft wurde, ob sich seit dem im Plugin genannten Stand (September 2026) etwas geändert hat. Ergebnis: keine Änderung, die eine Aussage des Plugins falsch macht.

- § 5 DDG: Letzte Änderung mit Wirkung vom 19.02.2026 (Art. 5 des Gesetzes zur Änderung des Produktsicherheitsgesetzes, BGBl. 2026 I Nr. 29), eine rein redaktionelle Korrektur in Abs. 1 Nr. 8. Die Pflichtangaben Nr. 1 bis 7, auf die sich das Plugin stützt, sind unverändert. Quelle: https://www.buzer.de/5_DDG.htm
- § 18 Abs. 2 MStV: unverändert; der Medienstaatsvertrag gilt in der Fassung des Siebten Medienänderungsstaatsvertrags seit 01.12.2025. Quellen: https://www.die-medienanstalten.de/fileadmin/user_upload/Rechtsgrundlagen/Gesetze_Staatsvertraege/Medienstaatsvertrag_MStV.pdf, https://www.it-recht-kanzlei.de/impressum-betreiber-journalistisch-redaktionelle-angebote-medienstaatsvertrag.html
- § 36 VSBG: unverändert, einschließlich der Ausnahme in Abs. 3 für Unternehmer mit zehn oder weniger Beschäftigten am 31.12. des Vorjahres. Quelle: https://www.gesetze-im-internet.de/vsbg/__36.html
- ADR-Richtlinie: Richtlinie (EU) 2025/2647 vom 16.12.2025 (ABl. L vom 30.12.2025), in Kraft seit 19.01.2026, Umsetzung bis 20.03.2028, Anwendung ab 20.09.2028. Ein deutsches Umsetzungsgesetz liegt nicht vor; der frühere Referentenentwurf des BMJ, der die Informationspflicht nach § 36 VSBG streichen wollte, ist der Diskontinuität zum Opfer gefallen. Quellen: https://eur-lex.europa.eu/legal-content/DE/TXT/?uri=CELEX%3A32025L2647, https://www.twobirds.com/en/insights/2026/eu-the-new-alternative-dispute-resolution-framework--directive-20252647, https://shopbetreiber-blog.de/aenderungen-des-vsbg-geplant-informationspflichten-sollen-wegfallen
- § 3 Abs. 3 BFSG: unverändert (Kleinstunternehmen mit weniger als zehn Beschäftigten und höchstens 2 Mio. Euro Jahresumsatz oder Bilanzsumme, nur für Dienstleistungen). Die Bundesfachstelle bietet seit Juli 2026 Sprechstunden für Kleinstunternehmen an, was den Text nicht berührt. Quellen: https://bfsg-gesetz.de/3-bfsg/, https://www.bundesfachstelle-barrierefreiheit.de/DE/Barrierefreiheitsstaerkungsgesetz
- OS-Plattform: Abschaltung zum 20.07.2025 durch Verordnung (EU) 2024/3228, wie im Plugin beschrieben; die Richtlinie 2025/2647 vollzieht die Einstellung in den Folgeregelungen nach. Quelle: siehe EUR-Lex oben.
- Wirtschafts-Identifikationsnummer: Vergabe läuft seit November 2024 in Stufen; ab dem vierten Quartal 2026 erhalten körperschaftsteuerpflichtige und feststellungspflichtige Unternehmen ihre W-IdNr., die Einführungsphase soll 2026 abgeschlossen werden; seit 30.09.2026 elektronische Zustellung über DIVA. Die Formulierung „Seit November 2024 im Rollout" bleibt richtig. Quellen: https://www.bzst.de/DE/Unternehmen/Identifikationsnummern/Wirtschafts-Identifikationsnummer/wirtschaftsidentifikationsnummer.html, https://www.bzst.de/DE/Unternehmen/Identifikationsnummern/Wirtschafts-Identifikationsnummer/FAQ/faq_widnr.html

## 7. Empfohlene Reihenfolge der Behebung

1. [F-02] Vorabversionen im Workflow als `prerelease` markieren und im Updater ausfiltern. Kleinste Änderung, größter Schaden vermieden.
2. [F-06] Seitenauswahl um alle Status erweitern.
3. [F-05] Voreinstellung nur bei fehlendem Schlüssel.
4. [F-03] Slim-SEO-Konstante.
5. [F-04] Bricks-Freigabe der Funktionen.
6. [F-01] Private Repositories: Zusage streichen oder API-Weg umsetzen.
7. [F-08], [F-09], [F-10], [F-13] als Sammelrunde in der Datenschicht.
8. [F-07] Multisite-Optionen beim Anlegen von Sites.
9. [F-14] Workflow härten (SHA-Pinning, `env:`, Ausschlussliste).
10. [F-15], [F-16], [H-02] Barrierefreiheit in einem Durchgang.
11. [F-11], [F-12], [F-17] und die Hinweise.

## 8. Werkzeuge und Belege

- Offline: `php dev/tests/test-audit-findings.php` (12 Prüfungen, die je einen Befund zeigen; nach der Behebung sollten alle bestehen). Die drei bestehenden Suiten bleiben grün.
- Playground: `dev/audit/bp-*.json` mit `dev/audit/env.php` (je Umgebung rund 90 Prüfungen, Ergebnis in `dev/audit/result-<name>.txt`), `dev/audit/mu-undt-audit.php` (zählt je Request Datenbankabfragen und `__()`-Aufrufe in `dev/audit/requests-<name>.log`), `dev/audit/uninstall-keep.php`, `dev/audit/run-matrix.ps1`.
- Beweis für [F-01] (c): Node-Redirect-Server plus `test-requests.php` gegen die Requests-Bibliothek aus WordPress 7.1 (im Scratchpad ausgeführt, Ausgabe im Befund zitiert).
- Vergleich Release gegen Arbeitsstand: Download von `releases/download/v0.4.0/unternehmensdaten.zip` und `update.json`, `diff -r --strip-trailing-cr` gegen das Ergebnis von `build.ps1`.
