=== Unternehmensdaten ===
Contributors: seitz
Tags: impressum, datenschutz, dsgvo, ddg, oeffnungszeiten
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.5.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zentrale Verwaltung aller Unternehmensangaben: rechtliche Pflichtangaben, Öffnungszeiten, Preise, Social, FAQ, Infobanner und strukturierte Daten.

== Description ==

Alle Angaben, die eine Unternehmenswebsite braucht, werden an einer Stelle gepflegt und überall per Shortcode ausgegeben. Ändert sich die Telefonnummer, ändert sie sich überall.

= Rechtliche Angaben =

**Rechtsformabhängige Felder.** Ein Einrichtungsassistent fragt Rechtsform, reglementierten Beruf, Erlaubnispflicht, Verbrauchergeschäft, redaktionelle Inhalte und Umsatzsteuerstatus ab. Angezeigt werden danach nur die Angaben, die tatsächlich gelten. Unterstützt werden 20 Rechtsformen vom Einzelunternehmen über die eGbR bis zur GmbH & Co. KG mit ihren beiden Registereinträgen.

**Rechtsseiten.** Impressum, Datenschutz, AGB und Barrierefreiheit werden aus Seiten und eigenen Inhaltstypen gewählt oder als eigene Adresse eingetragen. Der Footer verlinkt sie automatisch.

**Prüfung.** Eine Checkliste zeigt fehlende Pflichtangaben mit ihrer Rechtsgrundlage und durchsucht die verknüpften Rechtsseiten nach veralteten Inhalten, etwa dem Link zur abgeschalteten EU-Streitbeilegungsplattform oder der Rechtsgrundlage "§ 5 TMG".

= Inhaltsbereiche =

* **Öffnungszeiten** mit zwei Zeitfenstern pro Tag für Mittagspausen, Sonderöffnungszeiten für Feiertage und Betriebsferien, sowie einer Anzeige, ob gerade geöffnet ist
* **Preise & Leistungen** als Tabelle mit optionalen Gruppen
* **Social Media** als benannte Navigation mit rel="me"
* **FAQ** als aufklappbare Liste ganz ohne JavaScript
* **Infobanner** mit vier Stufen, optional schließbar
* **SEO & Schema** mit JSON-LD, das seine Daten aus allen übrigen Bereichen zieht

Jeder Bereich lässt sich unter Einstellungen abschalten. Dann verschwindet er aus dem Menü, seine Shortcodes geben nichts mehr aus und sein CSS entfällt. Die Daten bleiben erhalten.

= Ausgabe =

Die Blöcke erzeugen semantisches HTML mit address-, dl-, table- und nav-Elementen. Es werden weder Schriftart noch Schriftgröße noch Farben gesetzt, damit die Ausgabe die Gestaltung des Themes vollständig erbt. Einzige Ausnahme ist das Infobanner: ein Warnhinweis ohne visuelle Abgrenzung erfüllt seinen Zweck nicht, und er soll nicht mit dem Inhalt der Seite konkurrieren. Farben und Schriftgröße hängen an CSS-Variablen und lassen sich überschreiben, die Schrift etwa über `--undt-banner-font-size`, Voreinstellung `0.875em`.

Die Öffnungszeiten stehen als schmale Tabelle mit einer Punktlinie zwischen Tag und Uhrzeit. Ihre Breite regelt `--undt-hours-width`, Voreinstellung `25em`, die Linie `--undt-hours-leader`, etwa `none`. Die Blöcke selbst haben keine Außenabstände, das übernehmen Theme oder Builder. Wer sie braucht, setzt `--undt-block-spacing`.

Wochentage und Monatsnamen erscheinen auf Deutsch, auf Englisch eingestellt ist. Unter Öffnungszeiten lässt sich auf die Sprache der Website umstellen.

Die Überschriftenebene ist bei jedem Block einstellbar, damit sich die Ausgabe in die Gliederung der Seite einfügt, statt sie zu brechen.

== Shortcodes ==

= Einzelne Felder =

* `[undt key="phone"]` gibt ein Feld aus
* `[undt key="phone" link="1"]` macht Telefon, E-Mail und URL anklickbar
* `[undt key="email" obfuscate="1"]` verschleiert die Adresse
* `[undt key="phone" before="Telefon: "]` ergänzt Text, der nur erscheint, wenn das Feld gefüllt ist
* `[undt key="maps_google" link="1" text="Route planen"]` setzt einen eigenen Linktext

Die Kartenlinks `maps_google` und `maps_apple` liegen bei der Anschrift. Bleiben sie leer, entsteht der Link aus Firma und Anschrift.

= Rechtliche Blöcke =

* `[undt_impressum]` das vollständige Impressum, Attribut `heading_level`
* `[undt_footer]` Anschrift, Rechtslinks und Copyright, Attribut `show`
* `[undt_legal_nav]` nur die Links zu den Rechtsseiten
* `[undt_address]` die Anschrift, Attribute `inline`, `separator`, `name`
* `[undt_privacy_block name="controller"]` Datenbausteine für die Datenschutzerklärung, `name` auch `dpo` oder `authority`

= Inhaltsbereiche =

* `[undt_hours]` Tabelle der Öffnungszeiten, Attribute `group`, `short`, `special`, `note`, `heading_level`
* `[undt_hours_today]` die heute geltende Zeit, Attribute `prefix`, `closed_text`
* `[undt_open_now]` ob gerade geöffnet ist, Attribute `open_text`, `closed_text`
* `[undt_prices]` Preisliste, Attribute `group`, `intro`, `footnote`, `heading_level`
* `[undt_social]` die Profile als Navigation, Attribut `label`. Symbole, Plattformnamen und der Pfeil für neue Tabs lassen sich unter Social Media schalten, die Symbolgröße über `--undt-social-icon-size`
* `[undt_faq]` Fragen und Antworten, Attribute `group`, `style`, `heading_level`
* `[undt_banner]` das Infobanner an dieser Stelle

== Dynamische Daten ==

Slim SEO, Bricks und Etch bekommen die Stammdaten als dynamische Werte. In Slim SEO und Bricks stehen sie in der jeweiligen Auswahl unter „Unternehmensdaten“, in Etch werden sie über ihren Namen eingesetzt. Die Referenz im Backend listet alle Werte mit ihrer Schreibweise. In den Stammdaten stehen unter jedem Feld neben dem Shortcode die Kürzel B und E: ein Klick kopiert die Schreibweise für Bricks beziehungsweise Etch, der Tooltip nennt sie.

* **Slim SEO** `{{ undt.phone }}`, etwa in Meta-Titel und Meta-Beschreibung hinter den drei Punkten, und mit Slim SEO Pro ebenso in den Schema-Einstellungen
* **Bricks** `{undt_phone}` in jedem Feld für dynamische Daten, ohne dass Code-Ausführung eingeschaltet sein muss. Die Bricks-Filter für die Wortzahl und den Ersatzwert funktionieren wie gewohnt, etwa `{undt_fax @fallback:'kein Fax'}`
* **Etch** `{options.undt.phone}` in Texten und Attributen, auch mit Modifikatoren wie `{options.undt.company_name.toUpperCase()}`

Enthalten sind alle Stammdaten, die beim eingestellten Profil gelten, die Anschrift in einer Zeile und die Rechtsseiten als Adresse. Bricks und Etch bekommen zusätzlich fertige Links wie `{undt_phone_link}` und `{undt_email_link}`, die heutige Öffnungszeit `{undt_hours_today}`, den Geöffnet-Status `{undt_open_now}` und `{undt_is_open}` sowie alle Angaben des Infobanners: `banner_show`, `banner_type`, `banner_text`, `banner_link_text`, `banner_link_url` und `banner_dismissible`. Damit lässt sich das Banner in Bricks oder Etch selbst gestalten und über eine Bedingung auf `banner_show` ein- und ausblenden. Ja-Nein-Werte liefert Bricks als 1 oder leer, Etch als true oder false. Öffnungsangaben und Banner fehlen bei Slim SEO.

In den Schema-Einstellungen von Slim SEO Pro stehen dieselben Werte in derselben Schreibweise, dazu einige, die nur dort sinnvoll sind: `{{ undt.social_profiles }}` liefert die Adressen aller Social-Profile und `{{ undt.logo_url }}` die Adresse des Logos aus SEO & Schema. In einem Feld, das sich vervielfältigen lässt, etwa `sameAs`, wird aus jedem Profil ein eigener Eintrag. So stehen die Profile nur einmal im Plugin und nicht zusätzlich in den Schema-Einstellungen.

Für `openingHoursSpecification` gibt es drei Listen, die zusammengehören: `{{ undt.hours_days }}`, `{{ undt.hours_opens }}` und `{{ undt.hours_closes }}`. In die drei Felder der Gruppe eingesetzt, entsteht je Tag und Zeitfenster ein Eintrag — eine Mittagspause also zwei, ein geschlossener Tag keinen. Die Gruppe muss dafür vervielfältigbar sein, angelegt wird sie nur einmal.

Ein Hinweis zu Telefon und Postleitzahl: Slim SEO macht aus einem Wert, der nur aus Ziffern besteht, eine Zahl, und dabei geht eine führende Null verloren. Das betrifft jede Variable, nicht nur diese hier. Mit Leerzeichen oder Ländervorwahl geschrieben — `0151 23456789` oder `+49 151 23456789` — bleibt die Nummer als Text stehen. Die eigene Auszeichnung des Plugins und die Shortcodes geben die Nummer ohnehin unverändert aus.

== Page Builder ==

Für Bricks, Breakdance und Etch stehen die Daten zusätzlich als PHP-Funktionen und als Schleifen-Quellen bereit.

= Schleifen-Quellen =

* `undt_hours` — ein Eintrag je Wochentag: day, day_label, day_short, closed, times, slots
* `undt_hours_grouped` — gleiche Tage zusammengefasst: days, days_label, closed, times, slots
* `undt_hours_special` — nur künftige Sondertermine: date, date_label, closed, from, to, times, note
* `undt_prices` — group, label, price, note
* `undt_faq` — group, question, answer
* `undt_social` — platform, platform_label, label, url

Jede Zeile enthält neben den Rohwerten ein fertig formatiertes `times`, weil Page Builder mit verschachtelten Arrays wenig anfangen können.

= Funktionen =

* `undt_get( 'phone', $ersatz )` — ein Stammdaten-Feld
* `undt_has( 'phone' )` — ob das Feld befüllt ist
* `undt_field( 'hours', 'note' )` — ein Feld eines Inhaltsbereichs
* `undt_query( 'undt_faq', array( 'group' => '…', 'limit' => 5 ) )` — die Zeilen einer Quelle
* `undt_loop( 'question' )` — ein Feld der laufenden Bricks-Schleife
* `undt_is_open()` und `undt_today()` — Öffnungsstatus

= Je nach Builder =

**Bricks** zeigt die Query-Namen im Schleifen-Dialog unter „Unternehmensdaten“. Einzelwerte kommen am einfachsten über die dynamischen Daten, siehe oben. In der Schleife liest `{echo:undt_loop('question')}` das Feld der aktuellen Zeile. Das Plugin gibt seine Funktionen für das echo-Tag selbst frei. In Bricks muss dafür zusätzlich unter Einstellungen › Custom code die Code-Ausführung für die eigene Benutzerrolle eingeschaltet sein. Geprüft mit Bricks 2.4.

**Etch** bekommt Einzelwerte unter `{options.undt.…}`. Schleifen entstehen in einem Code-Element über `undt_query()`. Geprüft mit Etch 1.6.

**Breakdance** führt PHP in einem Code-Element aus. Einzelwerte liefert `undt_get()`, Schleifen `undt_query()`.

Ein REST-Endpunkt fehlt bewusst: Page Builder laufen auf dem Server und brauchen keinen, und ein öffentlicher Endpunkt wäre zusätzliche Angriffsfläche ohne Gegenwert.

== Bewusste Entscheidungen ==

**Keine Datenschutz- und AGB-Texte.** Deren Inhalt hängt an den tatsächlich eingesetzten Diensten und gehört in die Hand einer Rechtsberatung. Das Plugin liefert stattdessen die Datenbausteine, die in einen solchen Text eingesetzt werden.

**Keine Markenlogos für Social Media.** Plattformlogos sind geschützte Zeichen, die ein Plugin nicht ungefragt mitbringen sollte. Jeder Link trägt stattdessen eine eigene Klasse und ein `data-platform`-Attribut, an die sich ein Icon-Set des Themes per CSS anhängen lässt.

**FAQPage-Auszeichnung standardmäßig aus.** Google hat FAQ-Rich-Results am 07.05.2026 vollständig eingestellt, auch für die bis dahin noch berechtigten Behörden- und Gesundheitsseiten. Die Auszeichnung bleibt gültiges schema.org und kann für die maschinelle Auswertung nützlich sein, ist aber kein SEO-Vorteil mehr.

**`[undt_open_now]` und Seiten-Caches.** Der Status wird auf dem Server in der Zeitzone der Website berechnet. Auf Seiten, die aus einem Seiten-Cache ausgeliefert werden, kann er deshalb veralten. Die Ausgabe trägt ein `data-undt-checked`-Attribut mit dem Zeitpunkt der Berechnung. Wer den Status prominent einsetzt, sollte die betreffende Seite vom Cache ausnehmen oder auf `[undt_hours_today]` ausweichen, das nur tagesgenau sein muss.

**Kein Verweis auf die OS-Plattform der EU.** Die ODR-Verordnung wurde durch die Verordnung (EU) 2024/3228 aufgehoben, die Plattform ist seit dem 20.07.2025 abgeschaltet.

Das Plugin ist keine Rechtsberatung. Es verwaltet Angaben und gibt sie strukturiert aus. Rechtsstand der hinterlegten Hinweise: September 2026.

== Berücksichtigte Rechtsgrundlagen ==

* § 5 DDG, seit 14.05.2024 an Stelle von § 5 TMG
* § 18 Abs. 2 MStV bei journalistisch-redaktionellen Inhalten
* § 2 Abs. 1 Nr. 11 DL-InfoV zur Berufshaftpflichtversicherung
* § 36 VSBG einschließlich der Ausnahme für zehn oder weniger Beschäftigte
* §§ 35a GmbHG, 80 AktG, 125a HGB zu Pflichtangaben auf Geschäftsbriefen
* § 27a UStG und § 139c AO zu Umsatzsteuer- und Wirtschafts-Identifikationsnummer
* Art. 13 DSGVO und § 38 BDSG für die Datenschutz-Bausteine
* Anlage 3 zu §§ 14, 28 BFSG zur Erklärung über die Barrierefreiheit
* § 3 PAngV zur Angabe von Gesamtpreisen

== Performance ==

* Keine zusätzliche Datenbankabfrage im Frontend für Stammdaten, Öffnungszeiten, Social, Banner und Schema: diese Optionen sind autoloaded
* Preise und FAQ sind bewusst nicht autoloaded und kosten nur auf den Seiten etwas, die sie ausgeben
* Keine Asset-Datei im Frontend. Das CSS wird inline ausgegeben, nur auf Seiten mit einem Block, und enthält ausschließlich die aktiven Bereiche
* Das einzige Frontend-JavaScript ist das Schließen des Infobanners, unter einem Kilobyte, nur wenn das Banner aktiv und schließbar ist
* Backend-Assets ausschließlich auf den eigenen Seiten, die Medienauswahl nur auf der Seite, die sie braucht
* Keine AJAX-Endpunkte und keine REST-Routen

== Filter ==

* `undt_fields` ergänzt eigene Stammdaten-Felder, die automatisch Sanitisierung, Escaping und Shortcode-Auflösung durchlaufen
* `undt_modules` ergänzt eigene Inhaltsbereiche
* `undt_capability` ändert die erforderliche Berechtigung, Standard `manage_options`
* `undt_css` passt das strukturelle CSS an
* `undt_inline_css` schaltet das mitgelieferte CSS ab
* `undt_imprint_html` und `undt_footer_html` bearbeiten die fertige Ausgabe nach
* `undt_schema_organization` passt die JSON-LD-Auszeichnung an
* `undt_audit_issues` ergänzt eigene Prüfungen
* `undt_query` passt die Zeilen einer Schleifen-Quelle an
* `undt_update_allow_prerelease` bietet Vorabversionen als Aktualisierung an, etwa auf einer Testseite
* `undt_social_icon` ersetzt das Symbol einer Plattform, etwa für Xing oder kununu, die WordPress nicht mitbringt
* `undt_auto_banner` legt fest, wo das Banner automatisch am Seitenanfang erscheint. Standard ist überall außer in der Oberfläche von Etch und Bricks
* `undt_link_post_types` legt fest, aus welchen Inhaltstypen die Rechtsseiten gewählt werden. Standard sind Seiten und eigene Inhaltstypen, die in Menüs erscheinen dürfen, ohne Beiträge und Produkte

== Sichern und übertragen ==

Unter Einstellungen liegt eine Sicherung aller Angaben als JSON-Datei: Stammdaten, Rechtsform, Inhaltsbereiche und die Einstellungen selbst. Dieselbe Datei auf einer anderen Website eingespielt, ist die Einrichtung dort zur Hälfte erledigt — gerade wenn mehrere Websites ähnlich aufgebaut sind.

Was nur zur Ursprungsseite gehört, bleibt beim Einspielen weg: die Verknüpfungen zu Seiten und das Logo, denn deren IDs zeigen auf der neuen Website auf etwas anderes. Eine eigene Adresse wie `/impressum/` bleibt dagegen erhalten. Welche Felder neu zu wählen sind, nennt die Meldung nach dem Einspielen. Alle Werte durchlaufen dieselbe Prüfung wie das Formular.

Mit WP-CLI geht dasselbe ohne Backend:

* `wp undt export > firma.json` schreibt die Sicherung
* `wp undt import firma.json` spielt sie ein, mit `--yes` ohne Rückfrage

== Changelog ==

= 0.5.6 =
* Neu: Alle Angaben lassen sich unter Einstellungen als Datei sichern und auf einer anderen Website einspielen. Verknüpfte Seiten und Bilder bleiben dabei außen vor, sie gehören zur Ursprungsseite
* Neu: Dieselbe Sicherung über WP-CLI mit `wp undt export` und `wp undt import`
* Neu: Die Öffnungszeiten füllen openingHoursSpecification in den Schema-Einstellungen von Slim SEO Pro, über drei Listen, die zusammengehören
* Neu: Unter Öffnungszeiten überträgt ein Knopf die Zeiten des ersten Tages auf alle übrigen
* Neu: Wer eine Seite des Plugins mit ungespeicherten Änderungen verlässt, wird vom Browser gefragt
* Die Prüfungen des Plugins laufen jetzt bei jeder Änderung automatisch mit, zusätzlich der offizielle Plugin Check. Ein Release entsteht nur, wenn sie bestehen

= 0.5.5 =
* Neu: Die Schema-Einstellungen von Slim SEO Pro führen die Unternehmensdaten jetzt ebenfalls in ihrer Auswahl. Sie haben eine eigene Liste, deshalb blieben sie bisher leer
* Neu: `{{ undt.social_profiles }}` liefert dort die Adressen aller Social-Profile. In einem Feld, das sich vervielfältigen lässt, etwa sameAs, wird aus jedem Profil ein Eintrag
* Neu: `{{ undt.logo_url }}` liefert die Adresse des Logos aus SEO & Schema, in den Schema-Einstellungen sowie in Bricks und Etch

= 0.5.4 =
* Behoben: Im Bildfeld ging ein Wert ungeprüft in die Ausgabe. Er war zwar immer eine Zahl, wird jetzt aber wie jede andere Ausgabe abgesichert
* „Domain Path“ und load_plugin_textdomain() sind entfallen: Übersetzungen gehören nach wp-content/languages/plugins/, im Plugin-Ordner wären sie nach der nächsten Aktualisierung fort

= 0.5.3 =
* Die Seiten des Plugins stehen in einer weißen Karte von 960 Pixeln Breite, abgesetzt vom grauen Hintergrund des Backends
* Alle Eingabefelder enden an derselben Kante, statt je nach Feldart unterschiedlich weit zu reichen. Uhrzeit und Datum bleiben schmal
* Der Speichern-Knopf sitzt im Fuß der Karte
* Die Registerkarten der Stammdaten und der Shortcode-Referenz tragen nur noch eine Linie unter dem aktiven Reiter
* Rechtsform & Umfang zeigt die Fragen in zwei Spalten, die Rechtsform selbst über beide
* Mehrere Schalter hintereinander, etwa unter Social Media, stehen ebenfalls in zwei Spalten
* Die Kopierknöpfe für Bricks und Etch stehen am rechten Rand des Feldes, mit Abstand zum Shortcode
* Feine Trennlinien zwischen den Formularzeilen
* Bei den Rechtsseiten stehen Auswahl und eigene Adresse untereinander und sind gleich breit

= 0.5.2 =
* Behoben: In Block-Themes und mit Etch fehlte das CSS des Plugins, wenn ein Shortcode in der Seitenvorlage stand und das automatische Banner aus war. Dadurch wurde der Hinweis „(öffnet in neuem Tab)“ sichtbar und Listen bekamen die Abstände des Browsers
* Öffnungszeiten: schmalere Tabelle mit Punktlinie zwischen Tag und Uhrzeit, Uhrzeiten rechtsbündig, keine Außenabstände mehr
* Die Blöcke setzen keine Außenabstände mehr, einstellbar über --undt-block-spacing
* Neu: Kartenlinks für Google Maps und Apple Maps in den Stammdaten, leer gelassen aus Firma und Anschrift erzeugt. Google Maps erscheint im JSON-LD als hasMap
* Neu: [undt] kennt das Attribut text für einen eigenen Linktext
* Neu: Social Media mit Symbolen der Plattformen aus dem Social-Icons-Block von WordPress, abschaltbar, auf Wunsch ohne Namen. Im Backend steht das Symbol neben der Auswahl
* Social Media: Statt des Textes „(öffnet in neuem Tab)“ zeigt ein kleiner Pfeil den neuen Tab an, Screenreader erfahren es über den Namen des Links
* Neu: Die Angaben des Infobanners stehen Bricks und Etch zur Verfügung, samt banner_show für Bedingungen. Dazu kommt is_open als Ja-Nein-Wert. Etch bekommt echte Wahrheitswerte
* Neu: Kopierknöpfe für Bricks und Etch auch unter den Feldern des Infobanners
* Die Kopierknöpfe zeigen die Logos von Bricks und Etch in kleinen Rahmen statt der Buchstaben
* Die Schleifen-Quelle undt_social liefert zusätzlich icon und new_tab

= 0.5.1 =
* Neu: Unter jedem Feld der Stammdaten kopieren zwei Kürzel neben dem Shortcode die Schreibweise für Bricks ({undt_phone}) und Etch ({options.undt.phone}). Der Tag selbst steht nur im Tooltip

= 0.5.0 =
* Neu: Slim SEO führt die Stammdaten in seiner Auswahl dynamischer Daten, etwa hinter den drei Punkten neben Meta-Titel und Meta-Beschreibung, als {{ undt.phone }}
* Neu: Bricks führt die Stammdaten in seiner Auswahl dynamischer Daten, als {undt_phone}, dazu fertige tel:- und mailto:-Links, die heutige Öffnungszeit und den Geöffnet-Status. Die Filter :Wortzahl und @fallback werden unterstützt
* Neu: Etch bekommt dieselben Werte unter {options.undt.phone}, geprüft mit Etch 1.6
* Neu: Rechtsseiten lassen sich aus eigenen Inhaltstypen wählen oder als eigene Adresse eintragen. Ein Pfad ohne Schrägstrich wie „impressum“ wird zu „/impressum“ statt zu einer toten http-Adresse
* Neu: Wochentage und Monatsnamen erscheinen auf Deutsch, auch wenn WordPress auf Englisch läuft. Unter Öffnungszeiten lässt sich auf die Sprache der Website umstellen
* Neu: Registerkarte „Dynamische Daten“ in der Shortcode-Referenz mit der Schreibweise für Slim SEO, Bricks und Etch
* Das Infobanner setzt seine Schrift etwas kleiner als den Fließtext, einstellbar über --undt-banner-font-size
* Die Knöpfe zum Verschieben und Entfernen in Listen wie Social Media sind jetzt gerahmte Schaltflächen mit größerer Klickfläche. Entfernen steht abgesetzt daneben und färbt sich rot
* Behoben: Schalter reagierten nur auf Klicks in ihrer linken Hälfte, weil WordPress die Größe von Ankreuzfeldern mit höherem Vorrang festlegt
* Behoben: Der Speichern-Knopf war englisch beschriftet
* Behoben: Das automatische Banner erschien auch über der Oberfläche des Etch-Builders. In den Buildern von Etch und Bricks bleibt es jetzt aus
* Behoben: Sondertermine nannten den Monat auf einer englisch eingestellten Website auf Englisch
* undt_today() bleibt leer, solange keine Öffnungszeiten hinterlegt sind, statt „geschlossen“ zu melden

= 0.4.1 =
* Behoben: Eine Vorabversion wie 0.5.0-beta.1 wäre allen Websites sofort als Aktualisierung angeboten worden. Der Workflow veröffentlicht sie jetzt als Vorabversion, und der Updater bietet sie nur noch an, wenn der Filter undt_update_allow_prerelease das erlaubt
* Behoben: Die Seitenauswahl in den Stammdaten zeigte nur veröffentlichte Seiten, eine verknüpfte Entwurfsseite ging deshalb beim nächsten Speichern verloren. Entwürfe, private und geplante Seiten stehen jetzt gekennzeichnet in der Auswahl
* Behoben: Geleerte Felder wie die Fußnote der Preisliste, der Zusatz „Uhr“ oder der Kleinunternehmer-Hinweis fielen auf die Voreinstellung zurück
* Behoben: Slim SEO wurde nicht erkannt, das JSON-LD erschien dort doppelt
* Behoben: Bricks führte {echo:undt_get(…)} nicht aus, weil die Funktionen nicht freigegeben waren
* Behoben: Ein Zeitfenster über Mitternacht galt am Folgetag als geschlossen, und gleiche Anfangs- und Endzeiten galten als rund um die Uhr geöffnet. 00:00 bis 00:00 wird jetzt zu 00:00 bis 23:59
* Behoben: [undt key="page_privacy" link="1"] verlinkte auch unveröffentlichte Seiten
* Behoben: Auf den Unterseiten eines Netzwerks wurden Preise und FAQ auf jeder Seite mitgeladen
* Behoben: Arrays in manipulierten Formulardaten erzeugten PHP-Warnungen
* Sondertermine ohne Uhrzeiten werden beim Speichern als geschlossen markiert, so wie die Ausgabe sie schon behandelt hat
* Barrierefreiheit: Nach dem Schließen des Infobanners bleibt der Tastaturfokus an seiner Stelle auf der Seite, Zusatztexte sind nicht mehr abgeschwächt, Links in neuen Tabs kündigen das für Screenreader an, Behördenanschriften stehen nicht mehr in address-Elementen
* Die Einrichtung gilt erst nach dem Speichern von Rechtsform & Umfang als abgeschlossen, nicht schon beim Aufruf der Seite
* Die Deinstallation entfernt auch den Zwischenspeicher des Updaters
* Entfernt: die Konstante UNDT_GITHUB_TOKEN. Private Repositories funktionierten damit nie, und der Token wurde beim Abruf über die Umleitung an das CDN von GitHub weitergereicht
* Workflow: Actions an Commit-Hashes gebunden, Versionsangaben nur über Umgebungsvariablen, Syntaxprüfung mit PHP 7.4

= 0.4.0 =
* Neu: Aktualisierung über GitHub-Releases, gemeldet im gewohnten Plugin-Bildschirm
* Der Updater liest eine update.json am Release statt der ratenbegrenzten GitHub-API
* Paketadressen werden gegen eine Liste erlaubter Hosts geprüft, bevor sie an den Installer gehen
* Neu: Workflow, der Archiv und update.json baut und dabei Tag, Plugin-Header und Stable tag abgleicht

= 0.3.1 =
* Neu: Schleifen-Quellen und PHP-Funktionen für Bricks, Breakdance und Etch
* Die Shortcode-Referenz liegt in Registerkarten, die Suche deckt währenddessen alle auf einmal auf
* Lesebreite von 700 auf 950 Pixel

= 0.3.0 =
* Alle Ankreuzfelder sind Schalter, mit role="switch" und unveränderter Tastaturbedienung
* Hinweise erscheinen als Infobox beim Überfahren, per Klick feststellbar, das Symbol ohne Rahmen
* Reiter zeigen einen Punkt statt einer Zahl, und nur dort, wo noch Pflichtangaben fehlen
* Gepaarte Felder stehen in gleich breiten Spalten und damit bündig
* Der Kopierbutton steht unter dem Eingabefeld
* Alle Seiten haben eine Lesebreite von 700 Pixeln
* Behoben: Seiten- und Medienfelder galten mit dem gespeicherten Wert 0 als ausgefüllt, wodurch die Prüfung eine nie ausgewählte Seite für hinterlegt hielt
* Behoben: Asset-Versionen tragen jetzt die Dateizeit, damit Caches nach einer Änderung an CSS oder JavaScript nicht die alte Datei ausliefern

= 0.2.0 =
* Neu: Öffnungszeiten mit zwei Zeitfenstern je Tag, Sonderöffnungszeiten und Geöffnet-Status
* Neu: Preise & Leistungen, Social Media, FAQ und Infobanner
* Neu: JSON-LD mit Organization, openingHoursSpecification und sameAs, mit Erkennung vorhandener SEO-Plugins
* Neu: Einstellungsseite zum Ein- und Ausschalten der Bereiche und zum Verhalten beim Deinstallieren

= 0.1.0 =
* Erste Fassung.
