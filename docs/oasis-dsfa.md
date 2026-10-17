# Datenschutz-Folgenabschätzung (Art. 35 DSGVO) – Entwurf

Stand 17. Oktober 2026. Entwurf zur Prüfung durch die Anwältin oder den Anwalt; ob eine DSFA Pflicht ist, ist dort zu
klären (Kriterien der Aufsichtsbehörden: neue Technologien, Bewertung von Inhalten, große Zahl Betroffener, Minderjährige
ab 16). Sie betrachtet die zwei riskantesten Verarbeitungen: die automatische Textprüfung und das soziale Netzwerk mit
Minderjährigen.

## 1. Automatische Textprüfung mit Sprachmodell

**Beschreibung:** Jeder öffentliche Text (Tropfen, Antwort, Profil, Oase) geht ohne Namen oder Kontodaten an Mistral
AI (Paris, EU). Zurück kommen Werte für Beleidigung, Hass, Drohung, sexuelle Inhalte und sexuellen Bezug zu
Minderjährigen. Ab Schwellen: Hinweis vor dem Absenden, Moderationsfall, vorläufiges Ausblenden, bei Bezug zu
Minderjährigen Eskalation mit Quarantäne. Konsequenzen entscheidet immer ein Mensch (Art. 22 DSGVO). Chats werden
nicht geprüft.

**Notwendigkeit:** Durchsetzung der Community-Regeln und Erkennung rechtswidriger Inhalte (DSA); Alternative wäre
ausschließlich manuelle Prüfung, die bei Wachstum nicht rechtzeitig wirkt.

| Risiko | Eintritt | Schwere | Maßnahmen | Rest |
|---|---|---|---|---|
| Fehleinstufung blendet zulässige Inhalte aus | mittel | mittel | nur sexuelle Inhalte vorläufig; Mensch entscheidet; Begründung und Beschwerdeweg (Art. 17, 20 DSA) | gering |
| Texte gelangen zum Anbieter und werden dort weiterverwendet | gering | mittel | nur Text, keine Kontodaten; EU-Anbieter; *AV-Vertrag und Ausschluss von Training prüfen* | gering, nach Prüfung |
| Profilbildung über Personen | gering | hoch | keine Speicherung über den Fall hinaus; Einschätzung wird mit dem Text gelöscht | gering |
| Falscher Verdacht auf Missbrauchsdarstellung | gering | hoch | Quarantäne statt Löschung; Mensch prüft; Admin kann aufheben; nichts geht automatisch an Behörden | gering |

## 2. Soziales Netzwerk mit Mitgliedern ab 16

**Risiken:** Bloßstellung durch öffentliche Inhalte, Kontakt durch Fremde (Grooming), Belästigung.

**Maßnahmen:** Privatsphäre durch Voreinstellung (alles nur für Mitglieder, keine Suchmaschinen), feine Regler für
Profil, Ort, Oasen, Erwähnungen, Antworten, Chat; für 16- und 17-Jährige strengere, nicht lockerbare Voreinstellungen
(docs/oasis-privacy.md); Blockieren, Stummschalten, Einschränken, verborgene Wörter; Meldungen nach Art. 16 DSA;
Eskalation bei Gefahr; keine Werbung, kein Ranking-Algorithmus.

**Ergebnis (Entwurf):** Mit den Maßnahmen ist das Restrisiko vertretbar. Eine Konsultation der Aufsichtsbehörde
(Art. 36) erscheint nicht nötig – rechtlich zu bestätigen. Überprüfung jährlich oder bei wesentlicher Änderung.
