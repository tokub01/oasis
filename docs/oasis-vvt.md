# Verzeichnis von Verarbeitungstätigkeiten (Art. 30 Abs. 1 DSGVO)

Entwurf, Stand 17. Oktober 2026 – vom Betreiber zu prüfen und aktuell zu halten. Grundlage ist der Code; die
Abschnittsnummern verweisen auf die Datenschutzerklärung (`resources/views/oasis/legal/datenschutz.blade.php`).

**Verantwortlicher:** Tobias Kubina, B.Sc. – VelWebSolutions, Gubener Str. 14, 47829 Krefeld (`config('oasis.operator')`),
Datenschutz-Kontakt `config('oasis.mail.privacy')`. Kein Datenschutzbeauftragter benannt (keine Pflicht nach
§ 38 BDSG – bei wachsendem Team prüfen).

**Technische und organisatorische Maßnahmen:** für alle Tätigkeiten docs/oasis-toms.md.

| # | Tätigkeit | Zweck, Rechtsgrundlage | Betroffene | Datenkategorien | Empfänger (Art. 28 / eigene) | Drittland | Löschung |
|---|---|---|---|---|---|---|---|
| 1 | Auslieferung der Website, Server-Logs (DSE 3) | Betrieb, Angriffsabwehr – Art. 6 Abs. 1 lit. f | Besucher:innen | IP, Zeit, URL, Browser | IONOS (Hosting) | nein | `server_log_days` (7) |
| 2 | Missbrauchsschutz/Rate-Limits (DSE 3) | Schutz vor Missbrauch – lit. f | Besucher:innen | IP, Login-Kennung | IONOS | nein | Minuten (Cache) |
| 3 | Sitzung und Cookies, Echtzeit-Verbindung (DSE 4) | Vertrag, Sicherheit – lit. b, f; § 25 Abs. 2 Nr. 2 TDDDG | Besucher:innen, Mitglieder | Sitzungs-ID, IP, Browser, letzte Aktivität | IONOS | nein | Sitzungsende / 120 Min. Inaktivität |
| 4 | Konto, Anmeldung, 2FA, bekannte Geräte (DSE 5) | Vertrag – lit. b; Kontoschutz – lit. f, Art. 32 | Mitglieder | Stammdaten, Geburtsdatum, Passwort-Hash, 2FA (verschlüsselt), Browser/System | IONOS, Mail-Anbieter | nein | mit Konto; Geräte nach 1 Jahr ohne Anmeldung |
| 5 | Profil, Tropfen, Bilder, Oasen, Reaktionen, Folgen, Einladungen, Abstimmungsteilnahme (DSE 6) | Vertrag – lit. b | Mitglieder | Inhalte, Bilder (ohne Metadaten), Verbindungen | IONOS, AWS (Bilder, Frankfurt) | AWS-Konzern USA: DPF + SCC | mit Inhalt bzw. Konto; 30 Tage nach Vertragsende |
| 6 | Privatsphäre-Einstellungen, verborgene Wörter, Einschränkungen (DSE 6, 7) | Vertrag – lit. b | Mitglieder | Einstellungen, Wortlisten, eingeschränkte Konten | IONOS | nein | mit Konto |
| 7 | Hinweise, E-Mail-Zusammenfassung (DSE 8) | Vertrag – lit. b | Mitglieder | Hinweis-Daten (wer, was, wann) | IONOS, Mail-Anbieter | nein | 90 Tage |
| 8 | Mitgliedschaft, Zahlung, Belege (DSE 9) | Vertrag – lit. b; Aufbewahrung – lit. c (§ 147 AO, § 257 HGB) | Mitglieder, Schenkende | Stufe, Beträge, Zahlungsart (letzte 4 Ziffern), Mollie-IDs, Rechnungsdaten | Mollie (teils eigener Verantwortlicher) | nein | Belege `payment_records_years` (8) ab Jahresende; unbezahlte Versuche 90 Tage |
| 9 | Verschlüsselter Chat, Chat-Meldungen (DSE 10) | Vertrag – lit. b; Meldungen – lit. c i. V. m. Art. 16 DSA | Mitglieder | Schlüssel, Chiffrate, Metadaten; gemeldete Nachricht im Klartext | IONOS | nein | Nachrichten 1/7/30 Tage; Meldungen `records_years` (3) |
| 10 | Startliste mit Double-Opt-in (DSE 11) | Einwilligung – lit. a | Interessierte | E-Mail, Gruppe, Zeitpunkte | IONOS, Mail-Anbieter | nein | unbestätigt 14 Tage, bestätigt 6 Monate nach Start |
| 11 | Kündigung, Widerruf (DSE 12) | Vertrag, Pflichten – lit. b, c (§§ 312k, 356a BGB); Nachweis – lit. f | Vertragspartner:innen | Name, E-Mail, Erklärung | IONOS, Mail-Anbieter | nein | `records_years` (3) |
| 12 | Inhaltsmeldungen nach Art. 16 DSA (DSE 13) | lit. c i. V. m. DSA | Meldende, Gemeldete | Link, Begründung, Name, E-Mail | IONOS, Mail-Anbieter | nein | `records_years` (3) |
| 13 | Automatische Textprüfung, Moderation, Beschwerden (DSE 14) | lit. f (Regeln durchsetzen), lit. c (DSA) | Mitglieder | Texte (an Mistral), Einschätzungen, Fälle, Entscheidungen | Mistral (EU) | nein | Einschätzungen mit Text bzw. Fall; Fälle `records_years` (3) |
| 14 | Eskalation: Beweise bei Gefahr für Leib und Leben / Missbrauchsdarstellungen (DSE 14) | lit. c, f; Art. 17 Abs. 3 lit. e; Art. 18 DSA | Mitglieder | Text, Kontoangaben, Bildkopien mit Prüfsumme | Strafverfolgung nur bei Anzeige durch eine Person | nein | `moderation.evidence_days` (90), verlängerbar |
| 15 | Organisationsanfragen (DSE 15) | lit. b bzw. f | Ansprechpersonen | Organisation, Personenzahl, E-Mail | IONOS, Mail-Anbieter | nein | `records_years` (3) |
| 16 | E-Mail-Kontakt (DSE 16) | lit. b, c, f | Anfragende | Adresse, Inhalt | Mail-Anbieter | nein | nach Erledigung |
| 17 | Datenpannen-Register | lit. c (Art. 33 Abs. 5) | Betroffene der Panne (indirekt) | Beschreibung, Kategorien, Anzahl | – | nein | `records_years` nach Abschluss |
| 18 | Admin-Zahlungssuche, Mollie-Abfragen | lit. c, f (Buchhaltung, Rückfragen) | Zahlende | wie 8 | Mollie | nein | wie 8; Protokoll im Log `server_log_days` |

**Offen:** Mail-Anbieter verbindlich festlegen (Entwurf IONOS), AV-Verträge ablegen (IONOS, AWS, Mistral, Mail),
Rolle von Mollie prüfen (docs/oasis-legal-review.md).
