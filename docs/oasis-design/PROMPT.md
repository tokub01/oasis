# Auftrag: Website für get-oasis.com in diesem Laravel-Projekt umsetzen

## Worum es geht

Oasis (get-oasis.com) ist ein werbefreies soziales Netzwerk für kurze Beiträge („Tropfen“). Es finanziert sich nur über Mitgliedsbeiträge:

- **Solidar** 3 € im Monat
- **Oase** 5 € im Monat, der Standard
- **Mäzen** ab 12 € im Monat

Alle Stufen bekommen dieselben Funktionen. Der Jahresbeitrag kostet zehn Monatsbeiträge. Vorher kann man 30 Tage testen, ohne Zahlungsdaten, und der Test endet von selbst.

Deine Aufgabe ist die **öffentliche Website mit allen Unterseiten** in diesem Laravel-Projekt. Verbindliche Vorlage sind die Design-Dateien in `docs/oasis-design/design/`, die Texte übernimmst du wörtlich. Die Platzhalter und offenen Entscheidungen stehen in `docs/oasis-design/PLATZHALTER.md`.

## So gehst du vor

1. **Projekt verstehen.** Prüfe Laravel-Version, vorhandene Layouts und Views und den CSS-Ansatz (Vite? Tailwind? Plain CSS?). Prüfe außerdem, ob es ein Login-System gibt (Breeze, Fortify, Jetstream), wie Mails eingerichtet sind (geplant: Amazon SES über SMTP) und welche Routen schon existieren. Überschreibe nichts Bestehendes, ohne zu fragen, zum Beispiel eine vorhandene Route `/`.
2. **Plan zeigen.** Liste mir kurz auf, welche Dateien du anlegst oder änderst, und welche Fragen offen sind. Warte auf mein OK.
3. **Auf eigenem Branch arbeiten.** Arbeite auf `feature/oasis-website` und mach kleine, verständliche Commits. Push nur nach Rückfrage.
4. **Prüfen** (siehe „Fertig, wenn …“). Fasse am Ende zusammen, was erledigt ist und was offen bleibt.

## Die Design-Vorlagen lesen

Jede Datei `design/*.dc.html` ist eine Seite aus einem Design-Werkzeug:

- **Inline-Styles sind die exakten Werte** für Farben, Größen und Abstände. Übernimm sie in CSS-Klassen, nicht als Inline-Styles.
- **`{{name}}`** ist ein Wert, den die JavaScript-Klasse am Dateiende in `renderVals()` liefert. Dort steht auch die Logik der interaktiven Teile.
- **`<sc-if>`** ist eine Bedingung, **`<sc-for>`** eine Schleife.
- **`<dc-import name="Header">`** bindet die Kopfzeile aus `Header.dc.html` ein, `Footer` funktioniert genauso.
- **`support.js`, `data-props`, `<x-dc>` und `<helmet>`** gehören nur zum Design-Werkzeug. Lass sie weg.
- **Links** wie `href="Preise.dc.html"` werden zu Laravel-Routen (Tabelle unten).
- **`canvas.json`** beschreibt nur die Anordnung im Design-Werkzeug. Für den Code brauchst du sie nicht.

## Seiten und Routen

Stell den Route-Namen `oasis.` voran, damit es keine Konflikte gibt.

| Seite | URL | Route-Name | Vorlage | Kopfzeile |
|---|---|---|---|---|
| Startseite | `/` | `oasis.home` | `Main.dc.html` | voll |
| Funktionen | `/funktionen` | `oasis.funktionen` | `Funktionen.dc.html` | voll, „Funktionen“ aktiv |
| Preise | `/preise` | `oasis.preise` | `Preise.dc.html` | voll, „Preise“ aktiv |
| Transparenz | `/transparenz` | `oasis.transparenz` | `Transparenz.dc.html` | voll, „Transparenz“ aktiv |
| Manifest | `/manifest` | `oasis.manifest` | `Manifest.dc.html` | voll, „Manifest“ aktiv |
| Gemeinsam starten | `/gemeinsam-starten` | `oasis.gemeinsam` | `Gemeinsam.dc.html` | voll, „Gemeinsam starten“ aktiv |
| Gründungsmitglied (Startliste) | `/gruendung` | `oasis.gruendung` | `Gruendung.dc.html` | voll, ohne Hinweisbalken |
| 30 Tage testen | `/testen` | `oasis.testen` | `Testen.dc.html` | minimal |
| Anmelden | `/anmelden` | `oasis.anmelden` | `Anmelden.dc.html` | minimal |
| Mitglied werden | `/mitglied-werden` | `oasis.mitglied` | `Mitglied.dc.html` | minimal |
| Entdecken (Gastansicht) | `/entdecken` | `oasis.entdecken` | `Entdecken.dc.html` | voll |
| Öffentliches Profil | `/profil/{handle}` | `oasis.profil` | `Profil.dc.html` | voll |
| Hilfe & FAQ | `/hilfe` | `oasis.hilfe` | `Hilfe.dc.html` | voll |
| Verträge hier kündigen | `/vertraege-kuendigen` | `oasis.kuendigen` | `Kuendigen.dc.html`, Ansicht „Kündigen“ | voll |
| Vertrag widerrufen | `/vertrag-widerrufen` | `oasis.widerrufen` | `Kuendigen.dc.html`, Ansicht „Widerruf“ | voll |
| Impressum | `/impressum` | `oasis.impressum` | `Rechtliches.dc.html` | voll |
| Datenschutz | `/datenschutz` | `oasis.datenschutz` | `Rechtliches.dc.html` | voll |
| AGB | `/agb` | `oasis.agb` | `Rechtliches.dc.html` | voll |
| Widerrufsbelehrung | `/widerrufsbelehrung` | `oasis.widerrufsbelehrung` | `Rechtliches.dc.html` | voll |
| Community-Regeln | `/community-regeln` | `oasis.regeln` | `Rechtliches.dc.html` | voll |
| Inhalte melden | `/inhalte-melden` | `oasis.melden` | `Rechtliches.dc.html`, Ansicht „Inhalte melden“ | voll |
| Fehlerseite | – (Laravel-404) | – | `Fehler404.dc.html` | voll |

Hinweise zu einzelnen Seiten:

- **Rechtliche Seiten:** Die Vorlage schaltet Impressum, Datenschutz und so weiter per Tab um. Setz sie als **eigene URLs** um. Die Seitenleiste besteht dann aus Links mit `aria-current="page"`, und der Footer verlinkt direkt auf die jeweilige Seite.
- **Kündigen und Widerrufen** bekommen ebenfalls **zwei eigene URLs**, gleiches Aussehen. Der Umschalter oben wird zu zwei Links.
- **Login-System:** Gibt es schon eins, sollen `Anmelden` und `Testen` dessen Routen und Controller nutzen. Du gestaltest nur die Views neu. Frag nach, bevor du die `users`-Tabelle änderst, zum Beispiel für Benutzername oder Geburtsdatum.

## Aufbau im Code

- **Layout:** `resources/views/layouts/oasis.blade.php` mit
  - `lang="de"` und Viewport-Meta
  - Titel und Description pro Seite
  - Open-Graph- und Twitter-Card-Tags
  - Favicon (`assets/favicon.svg`)
  - einem Link „Zum Inhalt springen“
- **Komponenten** unter `resources/views/components/oasis/`:
  - `header`, ein exakter Nachbau von `Header.dc.html`, mit den Props `active`, `minimal` und `announce`
  - `footer`, ein exakter Nachbau von `Footer.dc.html`
  - `logo`
  - `icon`: Inline-SVG im Strich-Stil mit `aria-hidden="true"`; die Pfade stehen in den Vorlagen
  - `faq-item` (`<details>`/`<summary>`)
  - `post` (Tropfen-Karte mit Props, damit später echte Daten hineinpassen)
  - `price-card`
  - Wo es sich lohnt, kleine Bausteine wie `button`, `eyebrow` und `check-list`
- **Seiten:** `resources/views/oasis/*.blade.php`, die Fehlerseite unter `resources/views/errors/404.blade.php`.
- **Routen:** `routes/oasis.php`, in `routes/web.php` eingebunden. Statische Seiten laufen über `Route::view`, Formulare über Controller.
- **Konfiguration:** In `config/oasis.php` stehen alle Platzhalter, Zahlen und Schalter: Preise, Testtage, Startdatum, Gründungsplätze, Kontaktadressen, Betreiber, Feature-Flags. Views schreiben diese Werte **nie** fest hinein.
- **CSS:** Nutzt das Projekt Tailwind, erweitere das Theme um die Tokens unten. Sonst lege `resources/css/oasis.css` an, über Vite. Ohne Build-Schritt nimm `public/css/oasis.css`. Startpunkt ist `docs/oasis-design/assets/tokens.css`.
- **JavaScript:** Kleines Vanilla-JS ohne Framework. Alle Seiten müssen auch ohne JavaScript benutzbar bleiben.

## Design-Tokens

### Farben

| Token | Wert | Einsatz |
|---|---|---|
| `--night` | `#0E2B2F` | Nachtwasser: dunkle Abschnitte, Überschriften auf Sand |
| `--night-2` | `#12353A` | Karten auf Nacht |
| `--night-line` | `#2C4B4F` | Linien auf Nacht |
| `--sand` | `#F4EDE1` | Seitenhintergrund |
| `--paper` | `#FBF8F2` | Karten, helle Abschnitte |
| `--field` | `#FFFDF8` | Eingabefelder |
| `--dune` | `#EADFCC` | alternative Abschnitte, Chips |
| `--dune-line` | `#D6C8B0` | Rahmen auf Sand |
| `--paper-line` | `#E5DBC9` | Rahmen auf Papier |
| `--field-line` | `#9C8E77` | Rahmen von Eingabefeldern |
| `--ink` | `#1C2422` | Fließtext |
| `--ink-2` | `#3E4A47` | Sekundärtext |
| `--muted` | `#5E6B67` | Metatext |
| `--sun` | `#E06A43` | Abendsonne: Hauptbuttons, Logo, Akzentflächen |
| `--sun-ink` | `#0B2326` | Text auf `--sun` |
| `--sun-text` | `#A3441F` | Koralle als Text auf hellem Grund |
| `--sun-on-night` | `#F2906B` | Koralle als Text auf Nacht |
| `--spring` | `#2E9E95` | Quelle: Wellen, Fortschritt, Fokusring |
| `--spring-text` | `#1F6F69` | Türkis als Text oder Icon auf hell |
| `--on-night` | `#F4EDE1` | Text auf Nacht |
| `--on-night-2` | `#C9D6D2` | Sekundärtext auf Nacht |
| `--on-night-muted` | `#A9BDB8` | Labels auf Nacht |

Kontrastregeln:

- `#E06A43` nie als kleiner Text auf Sand verwenden, dafür gibt es `--sun-text`.
- Kein weißer Text auf Koralle, sondern `--sun-ink`.
- Fließtext braucht mindestens 4,5:1, Rahmen von Bedienelementen mindestens 3:1.

### Schrift

- **Fraunces** für Überschriften und die Wortmarke. Gewicht 500–600, mit `font-variation-settings: 'SOFT' 100`.
- **Manrope** für Text und Oberfläche, Gewicht 400–800.

Größen:

| Element | Wert |
|---|---|
| H1 Startseite | `clamp(44px, 6.2vw, 88px)`, Zeilenhöhe 1.02, Laufweite −0.02em |
| H1 Unterseiten | `clamp(40px, 5vw, 72px)` |
| H2 | `clamp(32px, 3.6vw, 52px)`, kleinere Variante `clamp(30px, 3.2vw, 46px)` |
| H3 | Manrope 800, 21–24px |
| Fließtext | 17px, Zeilenhöhe 1.6 |
| Lead | `clamp(18px, 1.6vw, 21px)` |
| Eyebrow | 14px, 700, Großbuchstaben, Laufweite 2.5px, Farbe `--sun-text` |

### Abstände, Ecken, Schatten

- **Container:** `max-width: 1296px`, Innenabstand `clamp(20px, 4vw, 48px)`, Inhaltsbreite 1200px.
- **Abschnitte:** vertikal `clamp(72px, 8vw, 120px)`.
- **Raster:** `repeat(auto-fit, minmax(min(<min>px, 100%), 1fr))`. So brechen Spalten am Handy um, ohne eigene Media-Queries.
- **Radien:** 32 / 28 / 24 / 20 / 14px, Buttons und Chips 999px.
- **Schatten:** Handy-Mockup `0 30px 60px -24px rgba(14,43,47,.5)`.

### Buttons

- **Primär:** Hintergrund `--sun`, Text `--sun-ink`, Gewicht 800, mindestens 56px hoch (klein: 44px).
- **Dunkel:** `--night` mit Text `--on-night`.
- **Kontur:** 2px `--night`.
- **Textlink:** unterstrichen mit Abstand 5px, Gewicht 700, optional mit Pfeil-Icon.

Pro Seite gibt es **eine** Hauptaktion: „30 Tage kostenlos testen“.

### Logo

`assets/logo-zeichen.svg` zeigt eine halbe Sonne über zwei Wellen. Die Wortmarke ist „oasis“ in Kleinbuchstaben, Fraunces 600, Laufweite −0.5px.

## Schriften selbst hosten (Pflicht)

Lade Fraunces und Manrope **nicht** von `fonts.googleapis.com`. Das LG München I (20.01.2022, Az. 3 O 17493/20) hat das Einbinden von Google Fonts ohne Einwilligung als DSGVO-Verstoß gewertet.

So geht es stattdessen:

1. Lade die woff2-Dateien herunter. Beide Schriften stehen unter der SIL Open Font License und dürfen selbst gehostet werden.
   - Fraunces als variable Schrift **mit SOFT-Achse**, z. B. die „full“-Dateien aus `@fontsource-variable/fraunces`
   - Manrope z. B. aus `@fontsource-variable/manrope`
2. Lege sie unter `public/fonts/` ab.
3. Binde sie per `@font-face` mit `font-display: swap` ein und preloade die wichtigsten Dateien.

## Interaktive Teile (Vanilla JS)

1. **Preise**, Umschalter Monat/Jahr:
   - Schaltet Preise, Einheit, Tagespreis und Hinweis um.
   - Die Buttons tragen `aria-pressed`.
   - Werte aus der Konfiguration berechnen:
     - Tagespreis monatlich = Preis × 12 / 365
     - Tagespreis jährlich = Jahrespreis / 365
     - „entspricht X € im Monat“ = Jahrespreis / 12
   - Ohne JS ist „Monatlich“ sichtbar.
2. **Mitglied werden:** Stufe, Mäzen-Betrag (mindestens 12 €), Zahlweise und Zahlungsart steuern die Zusammenfassung.
   - Betrag = Monatsbetrag × 10 bei Jahreszahlung.
   - Darin enthaltene USt. = Betrag × 19 / 119.
   - Beträge im deutschen Format, z. B. „5,00 €“.
   - Laufzeittext:
     - monatlich: „Monatlich kündbar …“
     - jährlich: „Läuft 12 Monate. Danach kannst du monatlich kündigen oder ein neues Jahr abschließen.“
   - Die SEPA-Felder erscheinen nur bei SEPA, für PayPal und Karte gibt es je einen Hinweistext.
3. **Kündigen:**
   - Bei „Außerordentlich“ erscheint das Feld „Grund“ und wird Pflicht.
   - Bei „Zu einem bestimmten Datum“ wird das Datum Pflicht.
4. **FAQ:** natives `<details>`/`<summary>`, kein JavaScript nötig.
5. **Navigation am Handy:** Die Links dürfen umbrechen wie in der Vorlage. Wenn du ein Menü einbaust, muss es mit der Tastatur bedienbar sein.

Alle Übergänge respektieren `prefers-reduced-motion`.

## Formulare und Backend: minimal, aber echt

Für alle Formulare gilt:

- CSRF-Schutz und `throttle`-Middleware
- ein unsichtbares Honeypot-Feld gegen Spam
- deutsche Fehlermeldungen
- eigene Bestätigungsseiten
- Mails über den vorhandenen Mailer; lokal `MAIL_MAILER=log`
- nirgends einen Erfolg vortäuschen, der nicht passiert ist

| Formular | Route (POST) | Speichern | Mails |
|---|---|---|---|
| Startliste (Gründung) | `/gruendung` | Tabelle `waitlist_entries`: E-Mail (eindeutig), Gruppe, Einwilligungszeitpunkt, Bestätigungstoken, `confirmed_at` | Double-Opt-in-Mail mit signiertem Bestätigungslink |
| Verträge kündigen | `/vertraege-kuendigen` | `cancellation_requests` | sofortige Bestätigung an die Person, Kopie an den Betreiber |
| Vertrag widerrufen | `/vertrag-widerrufen` | `withdrawal_requests` | sofortige Eingangsbestätigung, Kopie an den Betreiber |
| Inhalte melden | `/inhalte-melden` | `content_reports` | Eingangsbestätigung, Benachrichtigung an die Moderation |
| Anfrage für Organisationen | `/gemeinsam-starten` | `organisation_requests` oder nur Mail | Mail an den Betreiber |

Rechtliche Anforderungen (das ersetzt keine Rechtsberatung; die Texte prüft vor dem Start eine Anwältin oder ein Anwalt):

- **Kündigung (§ 312k BGB):**
  - Der Button „Verträge hier kündigen“ steht im Footer jeder Seite. Die Seite ist **ohne Login** erreichbar (BGH, I ZR 161/24).
  - Felder:
    - Art der Kündigung, bei außerordentlicher Kündigung mit Grund
    - Name und E-Mail zur Identifizierung
    - der Vertrag
    - der Zeitpunkt
  - Der Button heißt „Jetzt kündigen“.
  - Danach folgt **sofort** eine Bestätigung in Textform (E-Mail) mit Datum, Uhrzeit und Inhalt.
  - Auf diesem Weg gibt es **keine Halteangebote und keine Umwege**.
- **Widerruf:**
  - Ein eigener Button „Vertrag widerrufen“ führt auf die eigene Seite, dort bestätigt man mit „Widerruf bestätigen“. Danach folgt sofort eine Eingangsbestätigung.
  - Laut Recherche ist der Button seit 19.06.2026 Pflicht; den genauen Gesetzesstand prüfen lassen.
- **Bestellung:**
  - Der Button heißt genau „Zahlungspflichtig bestellen“ (§ 312j Abs. 3 BGB), Preise stehen inklusive USt.
  - Solange kein Zahlungsanbieter angebunden ist, liefert `/mitglied-werden` über das Flag `oasis.checkout_enabled = false` einen 404. Es darf keine Schein-Bestellungen geben.
- **Meldeformular (Digital Services Act, Art. 16):**
  - Felder: Link, Begründung, Name, E-Mail und die Bestätigung, dass die Angaben nach bestem Wissen stimmen.
  - Funktioniert ohne Konto und schickt eine Eingangsbestätigung.
- **Startliste:** Double-Opt-in, Einwilligung und Zeitpunkt speichern, Abmeldelink in jeder Mail.
- **Test und Login:** Die Testlogik (30 Tage, Erinnerung zwei Tage vorher, automatisches Ende) baust du **nicht** jetzt. Liste sie als nächsten Schritt.

## Ehrliche Inhalte

- **Entdecken und Profil** zeigen Beispielinhalte. Bis es echte Tropfen gibt, entweder deutlich als „Beispielansicht“ kennzeichnen und mit `noindex` versehen, oder über das Flag `oasis.demo_pages` in der Produktion ausblenden.
- **Transparenz und Gründungszähler** lesen ihre Zahlen aus der Konfiguration. Ist ein Wert `null`, steht dort „Veröffentlichen wir ab dem Start“. Die 60 % und 35 % in den Vorlagen sind nur Beispielwerte; in der Produktion erscheinen **keine erfundenen Zahlen**.
- **Werbeaussagen müssen wörtlich stimmen** (UWG). Bau keine Tracking- oder Werbeskripte ein: kein Meta-Pixel, kein Google-Ads-Tag, keine Analyse, die Profile bildet. Stelle gesetzliche Rechte wie Datenauskunft oder Löschung nicht als Besonderheit dar.

## Barrierefreiheit und Qualität

- **Struktur:** Landmarks, genau ein `h1` pro Seite, saubere Überschriftenfolge.
- **Bedienung:**
  - Labels an allen Feldern
  - sichtbarer Fokus (3px `--spring`, Abstand 3px)
  - Klickflächen mindestens 44px
  - `aria-current="page"` in der Navigation
- **Responsiv:** Prüfe bei 390, 768 und 1280px aufwärts. Es darf keinen horizontalen Scrollbalken geben.
- **SEO und Teilen:**
  - eigener Titel und eigene Description pro Seite
  - Open-Graph-Tags mit `og:image`: Erstelle ein schlichtes Bild in 1200×630 aus Logo und Claim oder lass einen klar benannten Platzhalter
  - `robots.txt`, optional eine `sitemap.xml`
- **Rechtliche Seiten:**
  - Impressum nach § 5 DDG
  - Kontaktstelle nach DSA Art. 11 und 12
  - Hinweis nach § 36 VSBG
  - Einen Link zur EU-OS-Plattform brauchst du nach unserem Stand nicht mehr, sie wurde 2025 eingestellt; prüfen lassen.
  - Die Rechtstexte bleiben klar gekennzeichnete Platzhalter.

## Fertig, wenn …

- jede Route Status 200 liefert und die 404-Seite greift
- `php artisan route:list` alle Routen zeigt und `php artisan view:cache` fehlerfrei durchläuft
- Feature-Tests für alle Formulare laufen (Validierung, Speichern, `Mail::fake()`)
- die Seiten bei 1440px der Vorlage entsprechen und bei 390px sauber funktionieren
- alles allein mit der Tastatur bedienbar ist
- alle Platzhalter aus `config/oasis.php` kommen und du mir die noch offenen in deiner Zusammenfassung auflistest
- nichts ohne Rückfrage gepusht ist

## Nicht Teil dieses Auftrags

Diese Punkte notierst du als nächste Schritte, du baust sie aber nicht:

- Zahlungsanbindung
- Test- und Abo-Logik
- die eigentliche Netzwerk-App mit Feeds, Tropfen und Folgen
- ein Admin-Bereich
