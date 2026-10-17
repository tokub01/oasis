# Technische und organisatorische Maßnahmen (Art. 32 DSGVO)

Entwurf, Stand 17. Oktober 2026. Beschreibt, was im Code umgesetzt ist (Belege: docs/oasis-security.md und die genannten
Dateien), und kennzeichnet, was im Betrieb noch eingerichtet werden muss. Bei jeder Änderung an Sicherheit oder
Hosting aktualisieren.

## Vertraulichkeit

- **Zutritt/Zugang zu Servern:** Rechenzentrum IONOS (Deutschland), Bilder bei AWS Frankfurt; Zugänge nur für den
  Betreiber. *Betrieb: SSH nur mit Schlüssel, kein Root-Login – einzurichten und zu dokumentieren.*
- **Zugriff in der App:** Rollen Mitglied/Moderation/Admin (`App\Enums\Role`); Moderations- und Admin-Bereich nur mit
  bestätigter Zwei-Faktor-Anmeldung (Middleware `staff`, Gate `administrate`); jede Aktion prüft die Berechtigung
  erneut. Meldende Personen sind für die Moderation unsichtbar.
- **Kontoschutz:** Passwörter als Hash (mind. 10 Zeichen), 2FA für alle verfügbar, Anmelde-Warnung bei neuem Gerät,
  Sitzungsliste mit Abmelden, Rate-Limits für Anmeldung, Registrierung, Passwort-Reset und Formulare.
- **Verschlüsselung:** HTTPS mit HSTS; Chat Ende-zu-Ende im Browser (Server sieht nur Chiffrate); 2FA-Geheimnisse
  verschlüsselt (`APP_KEY`); Bilder bei AWS serverseitig verschlüsselt. *Backups verschlüsselt: siehe
  docs/oasis-operations.md.*
- **Datensparsamkeit:** Bilder ohne Metadaten (Neukodierung), keine Analyse-/Tracking-Werkzeuge, keine externen
  Skripte oder Schriften, Logs ohne Inhalte, Admin-Statistik nur als Summen, Mollie-Abfragen ohne Namen im Log.
- **Web-Sicherheit:** Content-Security-Policy mit Nonce, CSRF-Schutz, `frame-ancestors 'none'`, Sichtbarkeitsprüfung
  vor jeder Bildauslieferung (`PostImageController`), Quarantäne verdächtiger Bilder auch gegenüber dem Team.

## Integrität

- Validierung aller Eingaben auf dem Server, Policies für jede Aktion, Livewire-Eigenschaften gesperrt (`#[Locked]`).
- Moderations- und Pannen-Verlauf nur ergänzbar, nicht änderbar; Beweise mit SHA-256-Prüfsumme.
- Zahlungen nur über die Mollie-API bestätigt, nie über Webhook-Inhalte; idempotente Verarbeitung.

## Verfügbarkeit und Belastbarkeit

- *Tägliche verschlüsselte Datenbank-Sicherung mit automatischem Wiederherstellungstest und Gesundheitsprüfung:*
  docs/oasis-operations.md.
- Warteschlange und Scheduler für Fristen, Abgleich und Mails; Mollie-Abgleich alle 10 Minuten.

## Wiederherstellung, Überprüfung, Bewertung

- Notfallplan für Datenpannen: docs/oasis-incident-response.md, Register im Admin-Bereich.
- Automatisierte Tests (über 1.000) und statische Analyse vor jedem Release; Abhängigkeits-Prüfung (`composer audit`,
  `npm audit`) – docs/oasis-operations.md.
- *Externer Penetrationstest vor dem Start – offen.*

## Auftragsverarbeitung

AV-Verträge mit IONOS, AWS, Mistral und dem Mail-Anbieter – *abzuschließen und abzulegen* (docs/oasis-vvt.md).
