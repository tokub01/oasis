# Design-Vorlagen

Diese 18 Dateien sind die Seiten aus dem Oasis-Website-Design: 16 Seiten plus Kopf- und Fußzeile. Sie sind die verbindliche Vorlage für Texte, Farben, Abstände und Aufbau.

| Datei | Seite |
|---|---|
| `Main.dc.html` | Startseite |
| `Funktionen.dc.html` | Funktionen |
| `Preise.dc.html` | Preise (mit Umschalter Monat/Jahr) |
| `Transparenz.dc.html` | Transparenz |
| `Manifest.dc.html` | Manifest |
| `Gemeinsam.dc.html` | Gemeinsam starten |
| `Gruendung.dc.html` | Gründungsmitglied / Startliste |
| `Testen.dc.html` | 30 Tage testen (Registrierung) |
| `Anmelden.dc.html` | Anmelden |
| `Mitglied.dc.html` | Mitglied werden (Bestellung) |
| `Entdecken.dc.html` | Entdecken (Gastansicht) |
| `Profil.dc.html` | Öffentliches Profil (Gastansicht) |
| `Hilfe.dc.html` | Hilfe & FAQ |
| `Kuendigen.dc.html` | Verträge hier kündigen / Vertrag widerrufen |
| `Rechtliches.dc.html` | Impressum, Datenschutz, AGB, Widerrufsbelehrung, Community-Regeln, Inhalte melden |
| `Fehler404.dc.html` | Fehlerseite |
| `Header.dc.html` | Kopfzeile (Baustein) |
| `Footer.dc.html` | Fußzeile (Baustein) |

## So liest du die Dateien

- **Inline-Styles** enthalten die exakten Werte.
- **`{{…}}`** sind Werte aus der JavaScript-Klasse am Dateiende (`renderVals()`).
- **`<sc-if>`** ist eine Bedingung, **`<sc-for>`** eine Schleife.
- **`<dc-import name="Header">`** bindet `Header.dc.html` ein.
- **`support.js`, `<x-dc>`, `<helmet>` und `data-props`** gehören nur zum Design-Werkzeug. Sie kommen nicht in den Code.
- **`canvas.json`** beschreibt nur die Anordnung der Seiten auf der Design-Arbeitsfläche.
