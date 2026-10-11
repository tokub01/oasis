# Oasis-Website: Übergabe an Claude Code

Mit diesem Paket baut deine Claude-Code-Session die Website für get-oasis.com in deinem Laravel-Projekt nach.

## So benutzt du es

1. Kopiere den Ordner `oasis-design` in dein Laravel-Projekt nach `docs/oasis-design/`.
2. Öffne Claude Code im Projektordner.
3. Schreib Claude Code diesen Satz:

   > Lies docs/oasis-design/PROMPT.md und setz die Oasis-Website danach in diesem Projekt um. Zeig mir zuerst deinen Plan.

Claude Code untersucht dann dein Projekt und zeigt dir einen Plan. Erst nach deinem OK legt es los, auf einem eigenen Branch.

## Was drin ist

| Datei | Inhalt |
|---|---|
| `PROMPT.md` | Der vollständige Auftrag: Seiten und Routen, Aufbau, Farben und Schriften, Interaktionen, Formulare, rechtliche Pflichten, Prüfliste |
| `PLATZHALTER.md` | Alle offenen Werte in [Klammern] mit Vorschlag für `config/oasis.php`, dazu die Entscheidungen, die du noch bestätigen solltest |
| `design/` | Die 18 Design-Vorlagen (16 Seiten, Kopf- und Fußzeile) als verbindliche Vorlage |
| `assets/` | Logo-Zeichen und Favicon als SVG, `tokens.css` mit allen Farben, Schriftgrößen und Abständen |

## Gut zu wissen

- **Schriften:** Fraunces und Manrope werden selbst gehostet, nicht von Google geladen. Das ist in Deutschland wegen der DSGVO der sichere Weg.
- **Formulare:** Kündigen, Widerrufen, Inhalte melden und die Startliste werden echt angebunden, mit Bestätigungs-Mails.
- **Bestellung:** Die Seite „Mitglied werden“ bleibt abgeschaltet, bis ein Zahlungsanbieter angebunden ist.
- **Rechtstexte:** Impressum, Datenschutz, AGB und Widerrufsbelehrung bleiben Platzhalter. Lass sie vor dem Start anwaltlich erstellen oder prüfen.
- **Design ansehen:** Das Design ist auch auf deiner Design-Arbeitsfläche in Claude zu sehen. Dort sind die Seiten miteinander verlinkt.
