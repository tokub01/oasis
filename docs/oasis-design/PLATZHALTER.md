# Platzhalter und offene Entscheidungen

Alles in den Design-Vorlagen, was in [ECKIGEN KLAMMERN] steht, ist noch offen. Im Code gehören diese Werte nach `config/oasis.php`. Werte, die man je Umgebung ändert, kommen zusätzlich in die `.env`. Die Spalte „Konfig-Schlüssel“ ist ein Vorschlag.

## Betreiber und Kontakt

| Platzhalter | Wo | Konfig-Schlüssel |
|---|---|---|
| [BETREIBER] | Footer (©), SEPA-Mandat bei „Mitglied werden“ | `oasis.operator.name` |
| [NAME ODER FIRMA], [STRASSE HAUSNUMMER], [PLZ ORT] | Impressum | `oasis.operator.address` |
| [TELEFON] | Impressum | `oasis.operator.phone` |
| [USt-IdNr.] | Impressum | `oasis.operator.vat_id` |
| [KONTAKT-E-MAIL] | Footer, Hilfe, Manifest, Impressum | `oasis.mail.contact` |
| [PRESSE-E-MAIL] | Manifest | `oasis.mail.press` |
| [DATENSCHUTZ-E-MAIL] | Hilfe | `oasis.mail.privacy` |
| [DSA-KONTAKT-E-MAIL] | Impressum (Kontaktstelle nach DSA) | `oasis.mail.dsa` |
| [DEIN NAME], [FOTO], Gründer-Text | Manifest, „Wer dahinter steht“ | `oasis.founder.*` |
| [Hinweis nach § 36 VSBG] | Impressum | Text |

## Start und Gründungsphase

| Platzhalter | Wo | Konfig-Schlüssel |
|---|---|---|
| [STARTDATUM] | Gründungsseite | `oasis.launch_date` |
| [1.000] Gründungsplätze | Hinweisbalken, Preise, Gründungsseite | `oasis.founding.seats` |
| [ZAHL] vergebene Plätze | Gründungsseite (Zähler) | live aus `waitlist_entries` oder `null` |
| [Namen der Gründungs-Mäzen:innen] | Gründungsseite (Dankesliste) | später aus der Datenbank, nur mit Einwilligung |

## Produkt und Regeln

| Platzhalter | Wo | Konfig-Schlüssel |
|---|---|---|
| 30 Tage Test | überall | `oasis.trial_days` |
| [30] Tage zum Herunterladen nach Testende bzw. Kündigung | Hilfe, Kündigen | `oasis.retention_days` |
| [3] Einladungen pro Monat | Gemeinsam starten | `oasis.invites_per_month` |
| [7] Tage Frist beim Gruppenstart | Gemeinsam starten | `oasis.group_start_days` |
| [2] Werktage Antwortzeit | Hilfe | `oasis.support_reply_days` |
| [iOS und Android] | Funktionen | `oasis.apps` |
| [5] Tage Rest im Test, [DATUM] Testende | Mitglied werden, Testen | dynamisch aus dem Konto |
| [AGB / Satzung] | Manifest („Festgeschrieben“) | Text |

## Preise (aus der Recherche, vor dem Start mit echten Kosten prüfen)

| Stufe | Monat | Jahr |
|---|---|---|
| Solidar | 3 € | 30 € |
| Oase (Standard) | 5 € | 50 € |
| Mäzen | ab 12 € | ab 120 € |

Konfig-Schlüssel: `oasis.tiers.*`. Alle Preise inklusive 19 % USt.

## Zahlung

| Platzhalter | Wo | Konfig-Schlüssel |
|---|---|---|
| [ZAHLUNGSANBIETER] | Mitglied werden (Hinweis zur Karte) | `oasis.payment.provider` |
| [GLÄUBIGER-ID] | Mitglied werden (SEPA-Mandat) | `oasis.payment.sepa_creditor_id` |
| Checkout an/aus | Mitglied werden | `oasis.checkout_enabled` (vorerst `false`) |

## Transparenz

| Platzhalter | Wo | Konfig-Schlüssel |
|---|---|---|
| [MONAT JAHR] Stand | Transparenz | `oasis.transparency.as_of` |
| [ZAHL] Mitglieder, [BETRAG] Kosten und Einnahmen, [ZAHL] getragene Plätze | Transparenz (Kennzahlen) | `oasis.transparency.*`, `null` = „Veröffentlichen wir ab dem Start“ |
| [BETRAG] je Kostenposten | Transparenz (Tabelle) | `oasis.transparency.costs` |
| [THEMA DER ABSTIMMUNG], [ERGEBNIS], [DATUM] | Transparenz (Abstimmungen) | später aus der Datenbank, bis dahin ausblenden |
| Deckungsgrad 60 % (Beispielwert) | Transparenz | berechnet, nie fest eingetragen |

## Datenschutz und Recht

| Platzhalter | Wo |
|---|---|
| [SERVERSTANDORT UND DIENSTLEISTER] | Hilfe, Datenschutz: z. B. IONOS (Server), Amazon Web Services (S3-Speicher, SES-Mailversand) mit Region |
| Datenschutzerklärung, AGB, Widerrufsbelehrung | anwaltlich erstellen lassen; bis dahin klar gekennzeichnete Platzhalter |

## Entscheidungen, die noch zu bestätigen sind

Diese Punkte sind im Design so angenommen. Bitte bestätigen oder ändern:

- **Zahlungsarten:** SEPA-Lastschrift, PayPal und Kreditkarte, in den Apps zusätzlich App Store und Google Play.
- **Altersgrenzen:** Testen ab 16. Mitglied werden ab 18, mit 16 und 17 nur mit Zustimmung der Eltern oder als Geschenk-Mitgliedschaft.
- **Sichtbarkeit:** Profil und Tropfen sind standardmäßig öffentlich. Man kann auf „nur für Mitglieder“ umstellen.
- **Passwort:** Mindestlänge 10 Zeichen.
- **Geschenk-Mitgliedschaft:** 3 oder 12 Monate, endet von selbst.
- **Widerspruch:** Gegen eine Moderationsentscheidung kann man widersprechen. Dann prüft eine zweite Person aus dem Team.
- **Versprechen festschreiben:** „Keine Werbung, kein Werbeprofil, kein Datenverkauf, Änderung nur mit Zustimmung der Mitglieder“ muss wirklich in AGB oder Satzung stehen, bevor die Website so damit wirbt.
