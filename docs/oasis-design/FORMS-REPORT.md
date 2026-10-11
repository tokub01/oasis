# Oasis: Formulare und Backend – Übergabe

Alle fünf Formularabläufe und die zugewiesenen Rechtsseiten sind implementiert. Keine Commits, kein Push,
kein Branchwechsel, keine neuen Abhängigkeiten. Dateien anderer Seiten wurden nicht verändert.

## Neue Dateien

- app/Http/Controllers/Oasis/CancellationController.php
- app/Http/Controllers/Oasis/ContentReportController.php
- app/Http/Controllers/Oasis/OrganisationController.php
- app/Http/Controllers/Oasis/WaitlistController.php
- app/Http/Controllers/Oasis/WithdrawalController.php
- app/Http/Requests/Oasis/CancellationFormRequest.php
- app/Http/Requests/Oasis/ContentReportFormRequest.php
- app/Http/Requests/Oasis/OasisFormRequest.php
- app/Http/Requests/Oasis/OrganisationFormRequest.php
- app/Http/Requests/Oasis/WaitlistFormRequest.php
- app/Http/Requests/Oasis/WithdrawalFormRequest.php
- app/Mail/Oasis/CancellationReceipt.php
- app/Mail/Oasis/ContentReportNotice.php
- app/Mail/Oasis/ContentReportReceipt.php
- app/Mail/Oasis/OrganisationNotice.php
- app/Mail/Oasis/WaitlistConfirmation.php
- app/Mail/Oasis/WithdrawalReceipt.php
- app/Models/WaitlistEntry.php
- app/Models/CancellationRequest.php
- app/Models/WithdrawalRequest.php
- app/Models/ContentReport.php
- app/Models/OrganisationRequest.php
- database/migrations/2026_10_11_100000_create_waitlist_entries_table.php
- database/migrations/2026_10_11_100001_create_cancellation_requests_table.php
- database/migrations/2026_10_11_100002_create_withdrawal_requests_table.php
- database/migrations/2026_10_11_100003_create_content_reports_table.php
- database/migrations/2026_10_11_100004_create_organisation_requests_table.php
- resources/views/oasis/gruendung.blade.php
- resources/views/oasis/gemeinsam.blade.php
- resources/views/oasis/kuendigen.blade.php
- resources/views/oasis/widerrufen.blade.php
- resources/views/oasis/bestaetigung/abmelden.blade.php
- resources/views/oasis/bestaetigung/abgemeldet.blade.php
- resources/views/oasis/bestaetigung/gemeinsam.blade.php
- resources/views/oasis/bestaetigung/gruendung.blade.php
- resources/views/oasis/bestaetigung/kuendigen.blade.php
- resources/views/oasis/bestaetigung/melden.blade.php
- resources/views/oasis/bestaetigung/startliste.blade.php
- resources/views/oasis/bestaetigung/widerrufen.blade.php
- resources/views/oasis/forms/_contract.blade.php
- resources/views/oasis/forms/_feedback.blade.php
- resources/views/oasis/legal/_frame.blade.php
- resources/views/oasis/legal/agb.blade.php
- resources/views/oasis/legal/datenschutz.blade.php
- resources/views/oasis/legal/impressum.blade.php
- resources/views/oasis/legal/melden.blade.php
- resources/views/oasis/legal/regeln.blade.php
- resources/views/oasis/legal/widerrufsbelehrung.blade.php
- resources/views/oasis/mail/cancellationrequest.blade.php
- resources/views/oasis/mail/contentreport.blade.php
- resources/views/oasis/mail/organisationrequest.blade.php
- resources/views/oasis/mail/waitlist.blade.php
- resources/views/oasis/mail/withdrawalrequest.blade.php
- tests/Feature/Oasis/FormsTest.php
- tests/Feature/Oasis/ReviewRegressionTest.php
- tests/Feature/Oasis/Pages/PublicPagesTest.php
- tests/JavaScript/oasis-cancellation.test.cjs

## Ergänzte gemeinsame Dateien

- routes/oasis.php: POST-Routen im vorgesehenen Block, gedrosselt auf sechs Anfragen pro Minute;
  eigene Bestätigungsseiten mit formularbezogenem Session-Nachweis; signierte Startlisten-Routen (GET zur Bestätigung/Abmeldeansicht, POST zur Abmeldung).
- resources/js/oasis.js: eigener cancellation-Block vor der Modulinitialisierung.
  Ohne JavaScript bleiben Grund und Datum erreichbar; die bedingten Pflichtfelder werden serverseitig geprüft.
- config/oasis.php: eigener Block oasis.legal mit text, privacy_rights, privacy_data, contract_term,
  promises, prices, withdrawal. Diese Werte sind ausdrücklich Platzhalter.

Verwendete bestehende Konfiguration: oasis.operator.*, oasis.mail.*, oasis.launch_date,
oasis.founding.seats, oasis.trial_days, oasis.retention_days, oasis.invites_per_month,
oasis.group_start_days, oasis.tiers.*, oasis.server_location.
Der Gründungszähler und die Dankesliste bleiben bis zur Veröffentlichung ohne erfundene Werte.

## Verhalten

- Startliste: eindeutige normalisierte E-Mail-Adresse, Gruppe, Einwilligungszeitpunkt, zufälliger Token.
  Signierter Bestätigungslink mit sieben Tagen Gültigkeit; signierter Abmeldelink ohne Ablaufdatum.
  Wiederholte Bestätigung erhält den ersten Zeitpunkt. Erneute Anmeldung einer unbestätigten Adresse sendet
  den Link erneut; bestätigte Adressen bleiben unverändert und erhalten keine weitere Mail. Alle Fälle führen
  zum selben neutral formulierten Redirect. Ein GET-Abmeldelink zeigt nur die Bestätigungsseite; erst der
  signierte, CSRF-geschützte POST löscht den Eintrag (auch bei wiederholtem POST ohne Erfolgsbehauptung über eine neue Anmeldung).
- Kündigung: Name, Konto-E-Mail, Vertrag, Art, Zeitpunkt und optionale Kennung.
  Außerordentlich verlangt einen Grund, ein Wunschdatum verlangt ein gültiges Datum ab heute.
  Sofortiger Versand einer Bestätigung mit Datum, Uhrzeit (Europe/Berlin) und allen Angaben an die Person
  und separat als gekennzeichnete Betreiberkopie an den Betreiber. Kein Login erforderlich.
- Widerruf: Speicherung und Eingangsbestätigung an die Person sowie Betreiberkopie.
- Inhaltsmeldung: HTTP(S)-Link, Begründung, Name, E-Mail, bestätigte Richtigkeit mit Zeitpunkt.
  Eingangsbestätigung und separate Moderationsmail mit eigenem Betreff und Prüfauftrag.
  Der Bestätigungszeitpunkt heißt truthful_confirmed_at.
- Organisationsanfrage: Speicherung von Organisation, Personenanzahl und E-Mail; Mail an den Betreiber.
- Alle Formulare: CSRF durch die vorhandene Web-Middleware, deutsche Fehlermeldungen, Honeypot.
  Ein ausgefüllter Honeypot löst einen Validierungsfehler aus: kein Datensatz, keine Mail.
- Ein Versandfehler löst keinen Erfolgsredirect aus. Der vorher gespeicherte Eingang bleibt erhalten.
  Bei einem Fehler des Mailtransports ist eine betriebliche Nachbearbeitung notwendig.
- Rechtsseiten: eigene URLs und gemeinsame Navigation mit aria-current; Rechtstexte als Platzhalter,
  Impressum mit DDG-, DSA- und VSBG-Abschnitten aus Konfiguration.

## Prüfungen

Erfolgreich ausgeführt:

- node --test tests/JavaScript/oasis-cancellation.test.cjs: zwei Tests bestanden.
- node --check resources/js/oasis.js: erfolgreich.
- git diff --check: erfolgreich.
- npm run build: erfolgreich. Warnungen zu drei noch fehlenden Font-Dateien im gemeinsamen Grundgerüst.
- Quellprüfung der zugewiesenen Vorlagen: keine Design-Werkzeug-Tags, .dc.html-Links oder Inline-Styles.

Versucht, aber blockiert:

- ./vendor/bin/sail artisan test --filter=Oasis: Sail is not running.
- ./vendor/bin/sail artisan route:list --name=oasis: Sail is not running.
- ./vendor/bin/sail artisan view:cache: Sail is not running.
- ./vendor/bin/pint --test für die neuen PHP-Dateien: PHP in WSL nicht installiert.
- Larastan/PHP-Syntaxprüfung und echte Seitenprüfung bei 390/768/1280/1440 px:
  ohne PHP/Sail nicht durchgeführt.

Die Pest-Feature-Tests sind geschrieben, aber nicht ausgeführt. Sie decken jedes Formular mit
Validierung, Speicherung, Honeypot, Mail-Empfängern und Drosselung ab. Zusätzlich: bedingte Kündigungsfelder,
Mailinhalt, normalisierte/duplizierte E-Mails, gültige/ungültige/abgelaufene signierte Links, Abmeldung,
öffentliche Seiten, Rechtsnavigation, isolierte Erfolgsseiten und Fehler beim Mailversand.

## Offene Punkte für die Integration

- Der frühere Syntaxfehler in routes/web.php wurde vom Orchestrator mit 977282c korrigiert.
  Diese Datei wurde nicht von mir geändert.
- Nach Reparatur von Docker/Sail: Migrationen anwenden, obige PHP-Prüfungen ausführen,
  Pint-Formatierung und gegebenenfalls Larastan-Befunde korrigieren; responsive/tastaturbasierte Prüfung.
- Reale Betreiber-/Kontaktwerte, Startdatum, Infrastruktur und anwaltlich geprüfte Rechtstexte fehlen.
  oasis.mail.operator und oasis.mail.moderation müssen vor produktivem Versand echte Empfänger enthalten.
- SMTP/SES und gegebenenfalls Wiederzustellung bei Transportfehlern produktiv prüfen.
- Gruppen-/Test-/Abologik, Zahlungen und die Netzwerk-App gehören weiterhin nicht zu diesem Auftrag.


## Fortsetzung nach Neustart (2026-10-11)

Claudes Review: SHOULD 1–4 und NIT truthful_confirmed_at umgesetzt. Tests aktualisiert und sechs
zusätzliche Regressionstests geschrieben (neutrale Doppelanmeldung, unveränderte Einwilligung/Token,
vergangenes Datum/heute, Berliner Zeit, Betreiber- und Moderationsansprache).
Die fremden CrossCheck-Tests wurden nicht verändert.

NIT DB::transaction bewusst nicht übernommen: Ein erfolgreich versandtes Mail kann durch eine spätere
Datenbank-Rückrolle nicht zurückgenommen werden. Insbesondere ein Betreiberkopie-Fehler nach erfolgreicher
Personenbestätigung darf den dokumentierten Kündigungseingang nicht verlieren. Es gibt weiterhin keinen
Erfolgsredirect bei Transportfehlern; Eingang und betriebliche Nachbearbeitung bleiben erhalten.
Eine robuste automatische Wiederzustellung wäre ein eigener Integrationsschritt.

Gegentestabweichungen an Claude gemeldet:
- Inhaltsmeldung: Moderation erhält ContentReportNotice, nicht ContentReportReceipt.
- 14:07-Test: App-Zeitzone ist UTC. 03.11.2026 14:07 UTC entspricht 15:07 Europe/Berlin.
  Der neue eigene Test setzt explizit 13:07 UTC und erwartet 14:07 CET.

Eigenes Review von Claudes Seiten liegt in C:/Users/tobia/oasis-handoff/review-codex-to-claude.md.
Unabhängige Seitentests unter tests/Feature/Oasis/Pages/PublicPagesTest.php decken alle öffentlichen
Routen, Checkout und Demo-Schalter, 404, Login-Redirect, h1/Landmarks/Vertragslinks, Titel/Descriptions,
Konfigvarianten, Preis-/Tagespreis-/Jahresberechnung, Transparenz und aktive Navigation ab.
Sie enthalten bewusst Erwartungen an die konfigurierte Testdauer und Centpreise, die bis zur Korrektur
von Claudes SHOULD 1/2 voraussichtlich fehlschlagen. Kein Laufzeitresultat behauptet.

Erneut tatsächlich ausgeführt:
- node --test tests/JavaScript/oasis-cancellation.test.cjs: 2/2 bestanden.
- node --check resources/js/oasis.js und git diff --check: erfolgreich.
- npm run build: erfolgreich, drei Font-URL-Warnungen; die Dateien sind in public/fonts vorhanden.
- sail artisan test --filter=Oasis, route:list --name=oasis, view:cache: jeweils Sail is not running.
- vendor/bin/pint --test (Controller, Requests, Mailables, ContentReport, eigene Tests): PHP fehlt in WSL.

Nicht ausgeführt: PHP-Feature-Tests, PHP-Syntaxcheck, Larastan, Browserlayout und Tastaturprüfung.
Keine Dependencies installiert, keine Konfiguration materiell geändert, kein Commit/Push/Branchwechsel.
