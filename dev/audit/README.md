# Audit-Werkzeuge (Prüfung vom 14.09.2026)

Alles hier gehört zum Prüfbericht `dev/audit-report.md` und ist kein Teil des
Plugins. Nichts davon wird ausgeliefert.

## Dateien

* `bp-<name>.json` — je ein Blueprint pro Umgebung der Kompatibilitätsmatrix.
  PHP- und WordPress-Version stehen in `preferredVersions`, weil die
  CLI-Schalter `--php` und `--wp` bei `run-blueprint` in der geprüften
  Playground-Version wirkungslos sind.
* `seed-audit.php` — legt Beispieldaten (`dev/seed.php`) und vier Testseiten
  an (Impressum veröffentlicht, Datenschutz als Entwurf, eine Seite mit allen
  Blöcken, eine ohne) und schreibt die IDs nach `/wordpress/undt-audit-ids.json`.
* `render.php` — rendert eine Frontend-Adresse in-process über `index.php`
  und legt das HTML als `html-<name>-<slug>.html` ab. Ersetzt
  `wp_remote_get()` auf die eigene Site, das in Playground unter Last mit
  einem SQLite-Verbindungsfehler abbricht. Setzt `SCRIPT_NAME`, weil
  `is_login()` sonst anschlägt und Autoptimize nicht puffert.
* `env.php` — die eigentliche Umgebungsprüfung, rund 90 Punkte je Lauf:
  Klassen laden, Optionen und autoload, die gerenderten Frontend-Seiten,
  Sprache und Datum, Datenbankabfragen und `__()`-Aufrufe, Backend-Ansichten,
  Sanitisierung gegen Skript, Multisite, Deaktivieren und Löschen,
  `debug.log`. Schreibt `result-<name>.txt`.
* `uninstall-keep.php` — zweiter Deinstallationsdurchlauf in eigenem Prozess
  (`keep_data=1`), hängt sein Ergebnis an `result-<name>.txt` an.
* `mu-undt-audit.php` — Mess-Mu-Plugin: zählt je Request Datenbankabfragen und
  `__()`-Aufrufe der Textdomain und schreibt `requests-<name>.log`.
* `run-matrix.ps1` — führt alle Blueprints nacheinander aus und protokolliert
  nach `matrix.log`. Nacheinander, weil parallele Playground-Instanzen die
  verschachtelten Frontend-Abrufe (`wp_remote_get` auf die eigene Site) mit
  einem SQLite-Verbindungsfehler abbrechen lassen.
* `run-rerun.ps1` — Teillauf der Matrix für ausgewählte Umgebungen, etwa nach
  einer gezielten Änderung: `-Names php74-wp64,multisite`. Ohne Angabe laufen
  die sieben Umgebungen ohne Fremd-Plugins und ohne Multisite.
* `run-server.ps1` — startet den Entwicklungsserver auf `http://127.0.0.5:9500`
  für die Browser-Prüfungen.
* `ergebnisse-0.4.0/` — die Ergebnisse des Audits gegen 0.4.0, mit den damals
  erwarteten FAIL-Zeilen.

## Aufruf

    powershell -NoProfile -ExecutionPolicy Bypass -File dev\audit\run-matrix.ps1

Die Multisite-Umgebung braucht `--site-url http://multisite.test`, weil
WordPress-Multisite keinen Port in der Adresse verträgt; das Skript setzt ihn.

## Ergebnisse lesen

Jede `result-<name>.txt` beginnt mit der tatsächlich verwendeten PHP- und
WordPress-Version und endet mit `ENV <name> | PHP x | WP y | n OK, m FAIL`.
Seit 0.4.1 sind die Befunde aus dem Bericht behoben, ein FAIL vor der ENV-Zeile
ist also ein neuer Fehler. Die Ergebnisse gegen 0.4.0 mit den damals erwarteten
FAIL-Zeilen liegen in `ergebnisse-0.4.0/`.

Der Abschnitt hinter der ENV-Zeile stammt aus `uninstall-keep.php` und zählt
nicht mit. Dort scheitert die Neuinstallation regelmäßig an einem
Playground-Artefakt: der im Vorprozess gelöschte Plugin-Ordner gilt als noch
vorhanden. Aussagekräftig ist der Abschnitt nur, wenn die Neuinstallation
gelingt.
