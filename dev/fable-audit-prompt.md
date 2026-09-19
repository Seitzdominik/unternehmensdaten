# Prüfauftrag: WordPress-Plugin „Unternehmensdaten" 0.4.0

Du prüfst ein WordPress-Plugin gründlich auf Sicherheitslücken, Kompatibilitätsprobleme und Fehler. Das Plugin ist bereits veröffentlicht und verteilt sich über einen eingebauten Updater selbstständig an Kundenseiten. Was du übersiehst, landet ohne weiteres Zutun in Produktion — und was du behauptest, ohne es belegt zu haben, kostet Zeit bei der Behebung. Beides zählt gleich.

Du hast keinen Kontext aus der Entwicklung. Alles, was du wissen musst, steht hier.

---

## 1. Harte Grenzen

Gelten ohne Ausnahme. Zuerst lesen.

- **Nur prüfen, nichts am Plugin ändern.** Keine Änderung am Plugin-Code, bis dein Bericht vorliegt und die Behebung ausdrücklich freigegeben ist. Beweis-Skripte, Test-Blueprints und Hilfsdateien darfst du unter `dev/` anlegen.
- **Im Git-Ordner nichts committen, pushen oder taggen.** Ein Tag `v*` löst automatisch ein GitHub-Release aus, und der Updater bietet es sofort allen Kundenseiten an. Ein versehentlicher Tag ist ein Produktions-Deployment.
- **Keine lokalen Sites anfassen.** Unter `C:\Users\Seitz\Local Sites` (LocalWP) und `C:\Users\Seitz\Studio` liegen echte Kundenprojekte. Getestet wird ausschließlich in WordPress Playground, das vollständig im Speicher läuft.
- **Keine Passwörter eintippen.** Playground meldet sich mit `--login` selbst an, siehe Abschnitt 8.

---

## 2. Wo was liegt

| Was | Pfad |
|---|---|
| Plugin — **das hier prüfst du** | `C:\Users\Seitz\Documents\Claude\Legal Information Plugin\unternehmensdaten` |
| Testwerkzeuge | `C:\Users\Seitz\Documents\Claude\Legal Information Plugin\dev\` und `build.ps1` daneben |
| Git-Repo, wird ausgeliefert | `C:\Users\Seitz\OneDrive\The good old stuff\Dokumente\GitHub\unternehmensdaten` |
| GitHub | `github.com/Seitzdominik/unternehmensdaten`, öffentlich, Release `v0.4.0` |

Plugin-Ordner und Git-Ordner waren am 14.09.2026 identisch. **Prüfe das als Erstes** mit einem rekursiven Vergleich ohne `.git` und `.gitattributes`. Weichen sie ab, halte an und melde es, bevor du weitermachst — sonst prüfst du womöglich Code, der gar nicht ausgeliefert wird.

Versprochene Mindestversionen laut Plugin-Header und `readme.txt`: **PHP 7.4, WordPress 6.4.** Rund 10.300 Zeilen PHP, JavaScript, CSS und YAML, keine Composer- oder npm-Abhängigkeiten.

---

## 3. Architektur in fünf Minuten

Das Plugin verwaltet rechtliche Pflichtangaben deutscher Unternehmen (Impressum nach § 5 DDG, Datenschutz-Bausteine) und Inhaltsbereiche (Öffnungszeiten, Preise, Social, FAQ, Infobanner, JSON-LD). Präfix überall `undt` / `UNDT_`.

**Zwei Register sind die einzige Quelle der Wahrheit.** Formular, Sanitisierung, Shortcode-Auflösung, Prüfung und Page-Builder-Schnittstelle werden daraus erzeugt. Wer diese beiden Dateien verstanden hat, versteht den Rest:

- `includes/class-undt-schema.php` — 20 Rechtsformen, die Fragen des Einrichtungsassistenten, rund 47 Stammdaten-Felder mit Sichtbarkeitsbedingungen (`when`), Paaren (`pair`), Rechtsgrundlage (`basis`) und Pflichtmarkierung
- `includes/class-undt-modules.php` — sechs Inhaltsmodule, jedes mit eigener Option, eigenem `autoload`-Wert und teils Wiederholungsfeldern

| Schicht | Dateien |
|---|---|
| Einstieg | `unternehmensdaten.php`: Konstanten, Autoloader (`UNDT_Foo` → `class-undt-foo.php` in `includes/` oder `admin/`), Aktivierung, alles weitere auf `init` |
| Daten | `UNDT_Store` (Stammdaten, Profil), `UNDT_Content` (Module, Wiederholungsfelder), `UNDT_Hours` (Zeitlogik, Zeitzone, Sondertermine) |
| Eingabe | `UNDT_Sanitizer` (Stammdaten, zusammenführend statt überschreibend), `UNDT_Content::sanitize` (Module inklusive verschachtelter Wiederholungsfelder), alles über die Settings API und `options.php` |
| Ausgabe | `UNDT_Render` (Impressum, Footer, Datenschutz-Bausteine), `UNDT_Blocks` (Module und deren CSS), `UNDT_Shortcodes`, `UNDT_SchemaOrg` (JSON-LD in `wp_head`) |
| Schnittstelle | `UNDT_Api` plus globale Funktionen `undt_get`, `undt_has`, `undt_field`, `undt_query`, `undt_loop`, `undt_is_open`, `undt_today`; Bricks-Filter `bricks/setup/control_options` und `bricks/query/run` |
| Aktualisierung | `UNDT_Updater`: liest `github.com/…/releases/latest/download/update.json`, prüft Paket-Hosts gegen eine Liste, cacht in einem Site-Transient, bedient `plugins_api` und `upgrader_source_selection`, dazu ein `admin-post`-Handler für die manuelle Prüfung |
| Backend | `UNDT_Admin` (Menü, `register_setting`, Assets nur auf eigenen Seiten mit Dateizeit als Versionskennung), `UNDT_Fields` (Feld-Rendering, Schalter, Wiederholungsfelder, Tooltips), Ansichten in `admin/views/`, `admin/assets/admin.js` ohne jQuery |
| Rechtsprüfung | `UNDT_Audit`: fehlende Pflichtangaben, Strukturregeln, Scan der verknüpften Rechtsseiten auf veraltete Rechtsgrundlagen |
| Auslieferung | `.github/workflows/release.yml`: baut Archiv und `update.json` beim Tag, gleicht Tag, Plugin-Header und `Stable tag` ab |

**Optionen:** `undt_profile`, `undt_company`, `undt_settings`, `undt_hours`, `undt_social`, `undt_banner`, `undt_seo` sind autoloaded; `undt_prices` und `undt_faq` bewusst nicht; dazu `undt_setup_done` und der Site-Transient `undt_update_info`.

---

## 4. Bewusste Entscheidungen — bitte nicht als Fehler melden

Diese Punkte sind abgewogen und gewollt. Melde sie nur, wenn du einen **konkreten** Schaden belegen kannst, der bei der Abwägung übersehen wurde, oder einen klar besseren Weg mit denselben Eigenschaften.

- **Kein REST-Endpunkt, kein AJAX.** Page Builder laufen serverseitig; ein öffentlicher Endpunkt wäre Angriffsfläche ohne Gegenwert.
- **Inline-Skript direkt hinter dem Infobanner.** Nur so verschwindet ein bereits geschlossenes Banner, bevor es aufblitzt. Der Konflikt mit einer strikten Content Security Policy ist bekannt.
- **Frontend-CSS inline** über ein Style-Handle ohne Datei, nur auf Seiten mit einem Block, ohne Schrift- und Farbangaben.
- **Keine Markenlogos** für Social-Plattformen (Markenrecht), stattdessen `data-platform` als CSS-Haken.
- **FAQPage-Auszeichnung standardmäßig aus**, weil Google FAQ-Rich-Results am 07.05.2026 eingestellt hat.
- **`[undt_open_now]` wird serverseitig berechnet** und kann aus Seiten-Caches veralten. Dokumentiert.
- **Updater ohne GitHub-API**, weil die auf 60 Anfragen je Stunde und IP begrenzt ist.
- **Deutsche Quelltexte** in `__()`. Das Plugin bildet deutsches Recht ab und ist nicht für wordpress.org bestimmt.
- **Kein generierter Datenschutz- oder AGB-Text**, nur Datenbausteine.
- **`.undt-wrap [hidden] { display: none !important }`** im Admin-CSS. WordPress setzt `h1…h6 { display: block }` als Autoren-Regel, die sonst `hidden` auf Überschriften aushebelt.
- **Hilfetexte nur im Tooltip.** Nutzerentscheidung; die Prüfseite zeigt sie bei fehlenden Pflichtangaben vollständig.

---

## 5. Bekannte Verdachtsstellen

Beim Erstellen dieses Auftrags fielen die folgenden Punkte auf. Sie sind **Spuren, keine Befunde**: bestätige oder widerlege jeden einzeln und bewerte ihn selbst. Und hör nicht bei dieser Liste auf — sie ist der Anfang, nicht der Umfang.

1. **Private Repositories sind vermutlich doppelt kaputt, obwohl beworben.** `UNDT_GITHUB_TOKEN` wird nur beim Abruf der `update.json` mitgeschickt. Den eigentlichen Paket-Download erledigt WordPress selbst über `download_url()` — ohne Token. Außerdem ist offen, ob ein Bearer-Token auf den `github.com/…/releases/…/download/`-Adressen überhaupt wirkt oder nur über `api.github.com` mit `Accept: application/octet-stream`. Dazu: gibt die HTTP-Bibliothek von WordPress den `Authorization`-Header bei der Umleitung auf `*.githubusercontent.com` weiter?
2. **`admin/assets/admin.js`, Zeile 576:** Die Medienvorschau baut `'<img src="' + url + '" …'` per `innerHTML` zusammen. Die Adresse stammt aus dem Anhang-JSON von `wp.media`. Praktisch ausnutzbar oder nur ein unsauberes Muster?
3. **Aktivierung ruft `__()` vor `init` auf** (`unternehmensdaten.php`, Zeilen 104 und 114, über `UNDT_Modules::all()`). Seit WordPress 6.7 kann das den Hinweis „Translation loading triggered too early" auslösen. Tritt er auf, obwohl `languages/` leer ist?
4. **Netzwerkweite Aktivierung auf Multisite.** `undt_activate()` legt die Optionen mit dem richtigen `autoload`-Wert an — aber nur für die Hauptseite. Werden `undt_prices` und `undt_faq` auf Unterseiten stattdessen autoloaded angelegt? Und auf Multisite haben Site-Admins kein `unfiltered_html`: lässt sich über irgendein Einstellungsfeld trotzdem Skript in die Frontend-Ausgabe bringen?
5. **`uninstall.php` löscht den Site-Transient `undt_update_info` nicht.**
6. **PHP 7.4 wurde nie tatsächlich geprüft.** Jeder Lint-Lauf während der Entwicklung lief auf PHP 8.5. Eine Stichprobe nach offensichtlicher 8.x-Syntax war unauffällig, ersetzt aber keinen Lauf auf 7.4.
7. **`includes/class-undt-schemaorg.php`, Zeile 53:** Erkennung von Slim SEO über `SLIM_SEO_VERSION`. Stimmt der Konstantenname? Prüfe die Erkennung aller fünf SEO-Plugins gegen deren aktuellen Code.
8. **`admin/class-undt-fields.php`, Zeile 742:** `toggle()` gibt Attributnamen durch `esc_attr()`, das für Namen nicht gedacht ist. Heute nur interne Aufrufer mit festen Namen.
9. **`admin/views/profile.php`** schreibt beim bloßen Aufruf per GET eine Option (`mark_set_up()`), ohne Nonce.
10. **Die Bricks-Anbindung wurde nie gegen echtes Bricks getestet** (kostenpflichtig). Gleiche die Filter-Signaturen mit der aktuellen Bricks-Dokumentation ab.
11. **Tooltips** hängen per `aria-describedby` an einem Element mit `display: none`. Lesen gängige Screenreader das vor?
12. **Infobanner:** Nach dem Schließen verschwindet der fokussierte Button, der Fokus landet im Nirgendwo.
13. **Alle Playground-Tests liefen mit `en_US`.** Wochentage, Datumsformate und `start_of_week` wurden nie unter `de_DE` geprüft.

---

## 6. Prüfbereiche

### 6.1 Sicherheit

Kläre zuerst, **wer was erreicht**, und prüfe dann jede Grenze:

- **Unangemeldete Besucher** sehen Shortcode-Ausgabe, JSON-LD und das Banner-Skript. Eingaben von ihnen gibt es nicht — die Frage ist, ob von Admins gespeicherte Daten irgendwo unescaped ausgegeben werden.
- **Mitarbeiter, Autoren, Redakteure** können Shortcodes mit beliebigen Attributen in Beiträge setzen, auch in Vorschauen, die Admins öffnen. Verfolge **jedes** der 23 Attribute aller 13 Shortcodes bis zur Ausgabe: `key`, `link`, `obfuscate`, `before`, `after`, `fallback`, `heading_level`, `show`, `inline`, `separator`, `name`, `bare`, `group`, `style`, `label`, `prefix`, `closed_text`, `open_text`, `intro`, `footnote`, `short`, `special`, `note`. Dasselbe für Bricks-Query-Variablen wie `undt_group`.
- **Admins** speichern über die Settings API. Prüfe die Sanitisierung aller Feldtypen, besonders verschachtelte Wiederholungsfelder mit manipulierten Array-Tiefen, fremden Schlüsseln und Nicht-Strings. Denk an den Fall, dass `undt_capability` die Berechtigung auf Redakteure absenkt.
- **Der Update-Kanal** ist die kritischste Grenze: er reicht Adressen an den WordPress-Installer weiter. Host-Prüfung, Umgehungen über Umleitungen, `fix_folder()` mit `$wp_filesystem->move()`, der Inhalt von `sections` im Detailfenster (läuft durch `wp_kses_post`, wird in einem iframe gerendert), Verhalten bei manipuliertem oder abgeschnittenem Manifest.
- **CSRF** bei jedem zustandsändernden Pfad, **Open Redirects**, **Pfadmanipulation** im Autoloader.
- **JSON-LD:** `JSON_HEX_TAG`, Verhalten der Filter `undt_schema_organization` und `undt_query`.
- **Admin-JavaScript:** jede Stelle, an der Daten per `innerHTML` oder Attribut in den DOM gelangen.

### 6.2 Kompatibilität

Prüfe in Playground, nicht aus dem Kopf. Mindestens diese Umgebungen:

| Umgebung | Warum |
|---|---|
| PHP 7.4, WordPress 6.4 | versprochenes Minimum, nie geprüft |
| PHP 8.3, WordPress aktuell | Hauptfall |
| PHP 8.4 oder neuer mit `E_DEPRECATED` | Null an Pflichtparameter interner Funktionen, sonstige Abkündigungen |
| Multisite, netzwerkweit aktiviert | Punkt 4 aus Abschnitt 5 |
| Blocktheme (Twenty Twenty-Five) und klassisches Theme (Twenty Twenty-One) | `wp_body_open`, Shortcode-Ausgabe, Style-Handle im Footer |
| Sprache `de_DE` | Punkt 13 aus Abschnitt 5 |
| Mit Yoast SEO und Rank Math aktiv | JSON-LD muss sich in der Voreinstellung zurückhalten |
| Mit einem Optimierungs-Plugin wie Autoptimize | spät eingereihtes Inline-CSS, Inline-Skript |

Yoast, Rank Math und Autoptimize liegen auf wordpress.org und lassen sich per Blueprint-Schritt `installPlugin` einspielen.

Achtung: `dev/install-test.json` setzt `preferredVersions`. Ob Kommandozeilen-Schalter wie `--php` und `--wp` diese Angabe überschreiben, ist nicht sicher. **Bestätige die tatsächlich verwendeten Versionen** in jedem Lauf, etwa indem dein Prüfskript `PHP_VERSION` und `$wp_version` mit ausgibt.

### 6.3 Korrektheit und Robustheit

- Leere, halb gefüllte und widersprüchliche Daten in jedem Modul
- Rechtsformwechsel hin und zurück: gehen Daten verloren, erscheinen falsche?
- Doppelter Aufruf der Sanitize-Callbacks, der in WordPress beim ersten Anlegen einer Option vorkommt
- Zeitzonen, Tageswechsel um Mitternacht, Zeitfenster über Mitternacht, Sondertermine am heutigen Tag
- Umgang mit gelöschten oder unveröffentlichten verknüpften Seiten und Anhängen
- Aktivieren, Deaktivieren, Löschen mit und ohne „Daten behalten"
- Verhalten, wenn GitHub nicht erreichbar ist oder Unsinn liefert

### 6.4 Performance

- Zusätzliche Datenbankabfragen je Frontend-Aufruf, mit und ohne Shortcodes auf der Seite. Behauptet wird: null für die autoloaded Module.
- Größe der autoloaded Optionen mit realistisch gefüllten Daten
- Rechenaufwand beim Aufbau der Register: `UNDT_Schema::fields()` und `UNDT_Modules::all()` rufen bei jedem Request dutzendfach `__()` auf
- Zählung im Admin-Menü (`UNDT_Audit::quick_count()`) auf jeder Backend-Seite
- Ob Assets wirklich nur auf den eigenen Seiten laden

### 6.5 Barrierefreiheit der Frontend-Ausgabe

Die Seiten, die das Plugin einsetzen, fallen teils unter das BFSG. Prüfe die Ausgabe von Impressum, Footer, Öffnungszeiten, Preisen, FAQ, Social und Banner auf: Überschriftenhierarchie mit `heading_level`, Semantik von `address`, `dl`, `table` mit `th scope`, benannte Navigationen, `details`/`summary`, Fokusführung, **Kontrast der Banner-Voreinstellungen** in allen vier Stufen nach WCAG 2.2 AA.

### 6.6 Auslieferung und Workflow

`release.yml`: Aktionen per Tag statt Commit-Hash angeheftet, Umfang von `permissions`, Skript-Injektion über `${{ }}` in `run`-Blöcken (auch über einen präparierten Plugin-Header), Vollständigkeit der Ausschlussliste fürs Archiv, Gültigkeit der erzeugten `update.json`. Vergleiche das tatsächlich veröffentlichte Release `v0.4.0` mit dem, was der Workflow erzeugen sollte.

### 6.7 Fachliche Stichprobe zum Rechtsstand

**Getrennt von den technischen Befunden und ausdrücklich keine Rechtsberatung.** Das Plugin enthält Aussagen mit Stand September 2026. Prüfe gegen aktuelle Quellen, ob sich seitdem etwas geändert hat: Pflichtangaben nach § 5 DDG, § 18 Abs. 2 MStV, § 36 VSBG samt Ausnahme für bis zu zehn Beschäftigte und dem Stand der Umsetzung der geänderten ADR-Richtlinie (Frist 20.03.2028), Kleinstunternehmen-Ausnahme nach § 3 Abs. 3 BFSG, Abschaltung der OS-Plattform zum 20.07.2025, Rollout der Wirtschafts-Identifikationsnummer. Melde nur tatsächliche Änderungen, jeweils mit Quelle.

---

## 7. Arbeitsweise und Beweisstandard

1. **Orientieren.** Die beiden Register und die Einstiegsdatei zuerst lesen, dann den Vergleich der beiden Ordner, dann einen Basislauf beider vorhandenen Prüfungen — Offline-Tests und Playground, Abschnitt 8 —, damit du weißt, was vorher grün war.
2. **Bereich für Bereich prüfen.** Jeden Befund, wo irgend möglich, **reproduzieren**: in Playground, mit einem kleinen Beweis-Skript unter `dev/`, oder offline gegen die WordPress-Attrappen in `dev/tests/`.
3. **Kompatibilitätsmatrix** aus 6.2 tatsächlich laufen lassen.

Jeder Befund trägt einen von zwei Status:

- **BESTÄTIGT** — reproduziert. Mit den Schritten oder dem Befehl, der ihn zeigt.
- **VERMUTET** — aus dem Code geschlossen, nicht reproduziert. Mit der Angabe, was zur Bestätigung fehlt.

Kein Befund ohne Datei und Zeile und ohne ein konkretes Szenario, in dem etwas schiefgeht. Keine Stilfragen, außer sie verursachen einen echten Fehler. Ist ein Bereich sauber, sag das in einer Zeile — erfinde nichts, um einen Abschnitt zu füllen.

---

## 8. Werkzeuge und Testumgebung

**Lokal:** PHP 8.5 unter `/c/php/php`, ohne ZipArchive und ohne mbstring. `php -l` prüft damit nur 8.5-Syntax. Node und `npx` sind vorhanden, `gh` nicht.

**WordPress Playground** über `npx @wp-playground/cli@latest`. Unterstützt `--php 7.4` bis `8.5` und `--wp` mit beliebiger Version.

**Vorhandene Prüfung** — `dev/README.md` beschreibt alles ausführlich:

```powershell
Set-Location 'C:\Users\Seitz\Documents\Claude\Legal Information Plugin'
& .\build.ps1
npx --yes '@wp-playground/cli@latest' run-blueprint --blueprint=dev/install-test.json --mount-dir 'C:\Users\Seitz\Documents\Claude\Legal Information Plugin' '/wordpress/build' --verbosity=quiet
Get-Content 'dev\result.txt' -Encoding UTF8
```

Das installiert `unternehmensdaten.zip` über den WordPress-Installer und arbeitet `dev/verify.php` mit 105 Prüfungen ab. `dev/seed.php` legt Beispieldaten an. `dev/dev-server.json` startet einen Server mit direkt eingehängtem Plugin-Ordner. Die `unternehmensdaten.zip` im Projektordner ist derzeit das von GitHub gebaute Release-Archiv.

**Offline-Tests** mit rund 200 Prüfungen gegen WordPress-Attrappen liegen unter `dev/tests/` und laufen in Sekunden, ohne Playground:

```powershell
php dev	ests	est-stammdaten.php
php dev	ests	est-inhaltsbereiche.php
php dev	ests	est-updater.php
```

Sie decken Logik, Sanitisierung, Ausgabe und die Abwehr im Updater ab, nicht aber Admin-Oberfläche, echte Datenbank und das Zusammenspiel mit WordPress selbst. `dev/README.md` beschreibt, was in welcher Datei steckt. Nutze sie als schnellen Basislauf und lege Reproduktionen für Befunde, die sich ohne WordPress zeigen lassen, als weitere `test-*.php` daneben.

**Fallstricke, die bereits Zeit gekostet haben:**

- **PowerShell verwenden, nicht Git Bash.** MSYS schreibt VFS-Pfade wie `/wordpress/build` in Windows-Pfade um, und der Mount schlägt fehl.
- **`--mount-dir "<Host>" "<VFS>"` mit zwei Argumenten.** `--mount` verträgt den Doppelpunkt im Laufwerksbuchstaben nicht.
- **Meldungen `lockWholeFile: unlock failed` sind harmlos.**
- **Die Ausgabe eines `runPHP`-Schritts erscheint nicht auf der Konsole.** Schreib Ergebnisse in eine Datei im eingehängten Ordner, wie `verify.php` es tut.
- **Browser-Prüfungen:** WordPress-Cookies gelten je Host, nicht je Port. Jede neue Playground-Instanz braucht einen Host, der noch nie ein Cookie gesehen hat, etwa `--site-url http://127.0.0.5:9500` — Playground lauscht auf allen Loopback-Adressen. Öffne sie über `preview_start` des Browser-Werkzeugs; ein einfaches `navigate` zu einer neuen Loopback-Adresse wurde zuletzt verweigert. Einmal abgemeldet, meldet `--login` nicht erneut an.
- **Ein verdeckter Browser-Bereich zeichnet nicht neu.** `getComputedStyle` kann dann während einer CSS-Transition eingefrorene Zwischenwerte liefern. Transition abschalten, um den Endzustand zu lesen.
- **`hidden` wirkt in WordPress-Admin nicht auf Überschriften**, siehe Abschnitt 4 — falls du selbst etwas ausblendest.

---

## 9. Ergebnis

Schreib den Bericht nach **`dev/audit-report.md`** im Projektordner — **nicht** in den Git-Ordner. Aufbau:

1. **Zusammenfassung:** Anzahl nach Schwere, Gesamturteil in zwei Sätzen, die drei dringendsten Punkte
2. **Befunde**, nach Schwere sortiert
3. **Kompatibilitätsmatrix** als Tabelle: Umgebung, tatsächlich verwendete Versionen, Ergebnis
4. **Geprüft und unauffällig:** kurze Liste, damit sichtbar ist, was abgedeckt wurde
5. **Fachliche Stichprobe** (6.7), klar abgesetzt
6. **Empfohlene Reihenfolge** der Behebung

Jeder Befund:

```
### [S-01] Kurzer Titel
Schwere: Kritisch | Hoch | Mittel | Niedrig | Hinweis
Status: BESTÄTIGT | VERMUTET
Bereich: Sicherheit | Kompatibilität | Korrektheit | Performance | Barrierefreiheit | Auslieferung
Ort: pfad/zur/datei.php:123
Beschreibung: Was falsch ist.
Szenario: Wer tut was, mit welchem Ergebnis. Bei BESTÄTIGT die Schritte zur Reproduktion.
Auswirkung: Was auf einer Kundenseite konkret passiert.
Vorschlag: Wie es sich beheben ließe. Code-Skizze erlaubt, aber nicht anwenden.
```

**Schweregrade**, auf WordPress bezogen:

- **Kritisch** — ausnutzbar ohne Anmeldung; Kompromittierung des Update-Kanals; Codeausführung; Rechteausweitung; gespeichertes XSS, das eine niedrige Rolle auslöst und das bei Admins ausgeführt wird
- **Hoch** — ausnutzbar durch Rollen unterhalb von `manage_options`; Datenverlust; Fatal Error auf einer versprochenen PHP- oder WordPress-Version; Aktualisierungen, die fehlschlagen oder das Plugin beschädigen
- **Mittel** — setzt Admin-Rechte voraus; oder ein Fehler, der eine Funktion unter realistischen Bedingungen bricht; oder eine Inkompatibilität mit versprochener Umgebung, für die es einen Ausweg gibt
- **Niedrig** — fehlende zweite Verteidigungslinie, Randfälle, Datenreste
- **Hinweis** — Beobachtung ohne unmittelbaren Fehler

Zum Schluss im Chat: eine kurze Zusammenfassung mit dem Pfad zum Bericht. **Frag, bevor du irgendetwas behebst.**
