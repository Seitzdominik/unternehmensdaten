# Entwicklungswerkzeuge

Alles hier laeuft in WordPress Playground: WASM-PHP mit SQLite im Speicher,
vollstaendig ephemer. LocalWP-Sites und Studio-Umgebungen werden nicht
angefasst.

## Archiv bauen

    powershell -File build.ps1

`Compress-Archive` aus Windows PowerShell 5.1 schreibt Backslashes als
Pfadtrenner ins Archiv. Die ZIP-Spezifikation verlangt Forward-Slashes, PHP
liest solche Eintraege als einen einzigen flachen Dateinamen, und WordPress
meldet dann, die Plugin-Dateien existierten nicht. `build.ps1` legt die
Eintraege deshalb selbst an und prueft das Ergebnis nach. Es bricht ab, statt
ein unbrauchbares Archiv zu hinterlassen.

## Installation pruefen

    npx @wp-playground/cli run-blueprint --blueprint=dev/install-test.json --mount-dir "<Projektordner>" "/wordpress/build"

Installiert das Archiv ueber WordPress' eigenen Installer, aktiviert das Plugin
und arbeitet `dev/verify.php` ab. Das Ergebnis landet in `dev/result.txt`,
weil die Ausgabe eines runPHP-Schritts nicht auf der Konsole erscheint.

Geprueft werden Entpacken, Aktivierung, autoload-Werte der Optionen,
Shortcode-Registrierung, Ausgabe mit echten Daten, ein Frontend-Abruf auf
PHP-Fehler, alle Backend-Ansichten und die Befunde aus dem Audit, die sich nur
in WordPress selbst zeigen: Entwurfsseiten in der Seitenauswahl, der
Einrichtungshinweis und autoload bei nachtraeglich angelegten Optionen. Dazu
seit 0.5.0 die Seitenfelder mit eigenen Inhaltstypen und eigener Adresse, die
deutschen Tage und Monate bei englischem WordPress, die dynamischen Daten, so
wie Slim SEO, Bricks und Etch sie abrufen, und das Banner in der Oberflaeche
von Etch. Seit 0.5.2 ausserdem, dass Shortcodes das CSS auch vor
`wp_enqueue_scripts` anfordern, wie Block-Themes und Etch es brauchen, die
Social-Symbole aus WordPress samt wp_kses, Kartenlinks und die Banner-Werte.
Seit 0.5.3 ausserdem die Oberflaeche des Backends: die weisse Karte mit
ihrem Fuss, die Registerkarten, die einheitliche Feldbreite, die zwei Spalten
der Rechtsform und die Schalter unter Social Media. Seit 0.5.4 ausserdem, dass
der Plugin-Header keinen Sprachordner mehr verspricht, den das Archiv nicht
mitbringt, seit 0.5.5 die Haken fuer die Schema-Einstellungen von Slim SEO Pro
und seit 0.5.6 das Sichern und Einspielen in echtem WordPress. Stand 0.5.6
sind es 192 Pruefungen.

Weder Slim SEO noch Bricks noch Etch laufen im Playground. Ihre Haken werden
dort direkt aufgerufen. Wie sich die Anbindung mit dem echten Werkzeug
verhaelt, zeigt nur ein Test auf einer Website mit diesem Werkzeug, siehe
unten.

## Test auf der Testseite

Fuer 0.5.0 lief das Archiv zusaetzlich auf der Testseite mit Bricks 2.4,
Slim SEO 4.10 und Etch 1.6, installiert per Novamira (`create-upload-link`,
dann `run-wp-cli plugin install <zip> --force`). Geprueft wurden dort:

* Slim SEO: Gruppe „Unternehmensdaten“ im Menue neben der Meta-Beschreibung,
  ein Klick setzt `{{ undt.phone }}` ein, `SlimSEO\MetaTags\Helper::render()`
  ersetzt die Werte
* Bricks: `bricks-list-dynamic-data` mit Suche `undt`,
  `bricks-resolve-dynamic-data` fuer Text- und Link-Kontext samt Filtern, und
  `\Bricks\Frontend::render_data()` mit Text, Button und Ueberschrift
* Etch: `do_blocks()` mit `etch/element` und `etch/text`, darin
  `{options.undt.…}` in Text, `href` und mit `.toUpperCase()`. Die Werte
  liefert das Plugin roh, Etch escaped Text und Attribute selbst, prueft
  Adressen aber nicht. `/etch-api/options` liefert dem Builder die Gruppe
  `undt`, abrufbar im angemeldeten Browser mit einer Nonce aus
  `admin-ajax.php?action=rest-nonce`. Im Builder (`?etch=magic`) erscheint
  kein automatisches Banner mehr
* Backend im Browser ueber `create-admin-access-link`: Trefferflaeche der
  Schalter per `elementFromPoint`, Knoepfe der Wiederholungsfelder, Speichern
  einer eigenen Adresse und Zuruecksetzen

Fuer 0.5.5 kam die Anbindung an die Schema-Einstellungen von Slim SEO Pro
1.11 dazu. Sie hat eigene Haken (`slim_seo_schema_variables` und
`slim_seo_schema_data`), die Meta-Angaben-Haken greifen dort nicht. Geprueft
auf der Testseite ueber `execute-php`: die Gruppe „Unternehmensdaten“ steht in
`SlimSEOPro\Schema\Support\Data::get_variables()`, und ein selbst gebauter
`SchemaRenderer` mit einer Organization loest `{{ undt.company_name }}`,
`{{ undt.email }}` und die Adresse auf. Aus dem einen Wert
`{{ undt.social_profiles }}` in `sameAs` werden dabei mehrere Eintraege, weil
Slim SEO ein Array in einem vervielfaeltigbaren Feld aufteilt. Die Testdaten
kamen ueber einen Filter, der nur fuer den Aufruf hing; die Social-Profile der
Testseite haben keine Adressen.

Dabei aufgefallen: `Normalizer::process()` macht aus einem Wert, der nur aus
Ziffern besteht, eine Zahl. `SKIP_NUMERIC_CONTEXTS` (phone, postal_code) greift
nicht, wenn im Feld nur eine einzige Variable steht, weil
`VariableRenderer::render()` den Kontext in diesem Zweig nicht weiterreicht.
Aus `0156518135` wird so `156518135`. Nicht unser Fehler und von hier aus nicht
zu beheben, ohne den eingetragenen Wert zu verfaelschen; in der readme steht
deshalb der Hinweis, die Nummer mit Leerzeichen oder Vorwahl zu schreiben.

Bricks registriert das `echo`-Tag nur, wenn unter Einstellungen › Custom code
die Code-Ausfuehrung an ist. Auf der Testseite ist sie aus, `{echo:…}` bleibt
dort deshalb stehen. Die `{undt_…}`-Tags brauchen sie nicht.

Ist das Browserfenster verdeckt, meldet der Browser eine Groesse von 0 x 0 und
Screenshots schlagen fehl. Mit einer festen Viewport-Groesse stimmen wenigstens
Layout und Trefferflaechen. Ist die feste Groesse breiter als der
Browserbereich, wird die Seite verkleinert und Klicks landen rund 10 % daneben;
1280 x 800 passt.

Fuer die Kopierknoepfe (0.5.1) wurde vor dem Klick `navigator.clipboard` in der
Seite durch eine Attrappe ersetzt. So laesst sich pruefen, was kopiert wird,
ohne die echte Zwischenablage anzufassen.

## Oberflaeche ansehen

    npx @wp-playground/cli server --blueprint=dev/dev-server.json --mount-dir "<Projektordner>\unternehmensdaten" "/wordpress/wp-content/plugins/unternehmensdaten" --mount-dir "<Projektordner>" "/wordpress/build" --port 9400 --login

Hier wird der Plugin-Ordner direkt eingehaengt, Aenderungen sind also nach dem
Neuladen sofort sichtbar. `dev/seed.php` legt Beispieldaten an.

## Kompatibilitaetsmatrix

    powershell -NoProfile -ExecutionPolicy Bypass -File dev\audit\run-matrix.ps1

Elf Umgebungen nacheinander: PHP 7.4 mit WordPress 6.4 bis PHP 8.5, dazu
Multisite, ein Block- und ein klassisches Theme, de_DE sowie Yoast, Rank Math
und Autoptimize. Rund eine Minute je Umgebung. Laeuft laenger als die zehn
Minuten, die ein Hintergrundaufruf im Werkzeug bekommt, deshalb losgeloest per
`Start-Process` starten. Einzelheiten in `dev/audit/README.md`, die Ergebnisse
des Audits gegen 0.4.0 liegen in `dev/audit/ergebnisse-0.4.0/`.

## Plugin Check

Der Plugin Check laeuft auf der Testseite ueber Werkzeuge > Plugin Check, per
CLI als `wp plugin check unternehmensdaten --format=csv`, ueber Novamira also
`run-wp-cli` mit diesen Argumenten. Zwei Gruppen von Befunden bleiben bewusst
stehen, beide betreffen nur das Verzeichnis auf wordpress.org, in das dieses
Plugin nicht gehoert:

* `plugin_updater_detected` und `update_modification_detected`: das Plugin
  aktualisiert sich ueber GitHub-Releases. Genau das ist fuer gehostete Plugins
  verboten, hier aber der Zweck. Der Check zeigt als Fundstelle `uninstall.php`,
  gemeint ist `includes/class-undt-updater.php`
* `readme_short_description_non_official_language` und
  `readme_description_non_official_language`: die readme bleibt auf Deutsch,
  Entscheidung des Nutzers am 19.09.2026. Beschreibung und Changelog erscheinen
  so auch im Plugin-Fenster der Kundenseiten auf Deutsch

In 0.5.4 behoben wurden die uebrigen Befunde: eine nicht abgesicherte Ausgabe im
Bildfeld sowie die Kopfzeile `Domain Path` samt `load_plugin_textdomain()`, die
auf einen Ordner zeigten, den das Archiv gar nicht enthaelt. Uebersetzungen
gehoeren nach `wp-content/languages/plugins/`; WordPress laedt sie von dort bei
Bedarf selbst, und eine Aktualisierung ueberschreibt sie nicht.

## Pruefungen auf GitHub

Seit 0.5.6 liegen diese Werkzeuge mit im Repository, und zwei Workflows lassen
sie dort laufen:

* `.github/workflows/tests.yml` bei jedem Push auf main und bei jedem Pull
  Request. Der Job `offline` arbeitet `dev/tests/test-*.php` mit PHP 7.4, 8.3
  und 8.5 ab und prueft nebenbei die Syntax aller PHP-Dateien. Der Job
  `plugin-check` spiegelt den Archivinhalt nach `build/unternehmensdaten` und
  laesst den offiziellen Plugin Check darauf los, ohne `plugin_updater` und
  `plugin_readme`
* `.github/workflows/release.yml` arbeitet dieselben Pruefdateien vor dem Bauen
  ab. Ein Tag erzeugt also kein Release, wenn eine Pruefung fehlschlaegt

Playground und die Matrix laufen bewusst nicht auf GitHub: sie brauchen mehrere
Minuten je Umgebung und laden bei jedem Lauf WordPress-Fassungen herunter. Vor
einer Veroeffentlichung gehoeren sie trotzdem dazu, siehe oben.

Die Artefakte der Laeufe (`dev/result.txt`, `dev/audit/html-*.html`,
`dev/audit/result-*.txt`, Protokolle und `dev/audit/ergebnisse-0.4.0/`) stehen
in `.gitignore`. Sie entstehen bei jedem Durchlauf neu.

## Hinweise

* Aus Git Bash heraus schlagen die VFS-Pfade fehl, weil MSYS `/wordpress/...`
  in einen Windows-Pfad umschreibt. PowerShell verwenden.
* `--mount` verträgt keine Laufwerksbuchstaben, deshalb ueberall `--mount-dir`
  mit zwei getrennten Argumenten.
* Die Meldungen `lockWholeFile: unlock failed` beim Start sind normal.
* `run-blueprint` ignoriert `--php` und `--wp`. Wirksam sind nur die
  `preferredVersions` im Blueprint, und ohne Angabe laeuft PHP 8.5 statt der in
  der Hilfe genannten 8.3. Die verwendeten Versionen deshalb immer im Ergebnis
  mitprotokollieren.

## Offline-Tests

Ueber 500 Pruefungen gegen WordPress-Attrappen, ohne Playground und in wenigen
Sekunden:

    php dev/tests/test-stammdaten.php
    php dev/tests/test-inhaltsbereiche.php
    php dev/tests/test-updater.php
    php dev/tests/test-audit-findings.php
    php dev/tests/test-dynamik.php
    php dev/tests/test-darstellung.php
    php dev/tests/test-oberflaeche.php
    php dev/tests/test-transfer.php

`harness.php` stellt die benoetigten WordPress-Funktionen als Attrappen bereit
und laedt die Plugin-Klassen direkt aus `unternehmensdaten/`. Filter lassen sich
dort registrieren, und `__()` zaehlt seine Aufrufe mit. Die Versionsnummer liest
es aus dem Plugin-Header. Jede Datei endet mit Exitcode 0, wenn alles besteht,
und mit 1 bei einem Fehlschlag.

* `test-stammdaten.php` — Impressum fuer mehrere Rechtsformen, Einzelfeld-Shortcode,
  Sanitisierung, Footer, Rechtspruefung, Seiten- und Medienfelder mit Wert 0
* `test-inhaltsbereiche.php` — Oeffnungszeiten samt Zeitzone und Sonderterminen,
  Wiederholungsfelder, Preise, Social, FAQ, Infobanner, JSON-LD, Modulschaltung,
  Query-Schnittstelle fuer Page Builder
* `test-updater.php` — Aktualisierungserkennung, Vorabversionen, Abwehr fremder
  Paketquellen und unsinniger Versionsangaben, kein Zugriffstoken,
  Zwischenspeicher, Detailfenster
* `test-audit-findings.php` — je Befund aus `dev/audit-report.md` die Pruefung,
  die seine Behebung absichert, einschliesslich des Release-Workflows; dazu seit
  0.5.4 die behobenen Befunde des Plugin Checks
* `test-dynamik.php` — Seitenfelder mit Auswahl oder eigener Adresse,
  Knoepfe der Wiederholungsfelder, Sprache fuer Tage und Monate, dynamische
  Daten fuer Slim SEO (Meta-Angaben und Schema-Einstellungen), Bricks samt
  Filtern und Etch, Kopierknoepfe fuer Bricks und Etch unter den Feldern, kein
  automatisches Banner in der Oberflaeche von Etch und Bricks
* `test-darstellung.php` — Punktlinie der Oeffnungszeiten, Kartenlinks,
  Linktext im Shortcode, Social-Symbole und neuer Tab, Banner-Werte fuer
  Bricks und Etch, Logos an den Kopierknoepfen
* `test-oberflaeche.php` — die Fragen der Rechtsform und die Schalter der
  Inhaltsbereiche als Zellen eines zweispaltigen Rasters, die einheitliche
  Feldbreite, die Kopierknoepfe am rechten Rand und ob Markup, CSS und Skript
  dieselben Klassen verwenden
* `test-transfer.php` — Sicherung und Einspielen: was in der Datei steht, was
  abgewiesen wird, dass fremde Optionen nicht geschrieben werden und dass IDs
  verknuepfter Seiten und Bilder beim Einspielen wegfallen, eigene Adressen
  aber bleiben

Abgedeckt sind Logik, Sanitisierung, Ausgabe, die Abwehr im Updater und das
Markup der Felder. Nicht abgedeckt sind die fertigen Ansichten, eine echte
Datenbank und das Zusammenspiel mit WordPress selbst — dafuer sind `install-test.json` und die Matrix da.
