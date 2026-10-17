# Rechtstexte: Prüfauftrag und offene Entscheidungen

Stand: 17. Oktober 2026. Alle Rechtstexte von Oasis sind **Entwürfe**. Sie beschreiben, was der Code heute tatsächlich
tut, sind aber nicht anwaltlich geprüft. Jede Seite zeigt deshalb den Hinweis „Entwurf – vor dem Start anwaltlich
prüfen lassen“. Er bleibt stehen, bis eine Anwältin oder ein Anwalt die Texte freigibt.

Dieses Dokument ist der Prüfauftrag: welche Texte es gibt, welche Werte als Entwurf gesetzt wurden und warum, und was
vor dem Start noch entschieden oder im Code geändert werden muss.

## Texte und ihre Quellen

| Seite | Datei | Werte aus |
| --- | --- | --- |
| Impressum | `resources/views/oasis/legal/impressum.blade.php` | `config('oasis.operator')`, `config('oasis.mail')` |
| Datenschutzerklärung | `resources/views/oasis/legal/datenschutz.blade.php` | `config('oasis.privacy')`, `config('session.lifetime')`, `oasis.retention_days` |
| AGB | `resources/views/oasis/legal/agb.blade.php` | `oasis.tiers`, `oasis.trial_days`, `oasis.founding.seats`, `oasis.apps`, `oasis.promises_codified` |
| Widerrufsbelehrung | `resources/views/oasis/legal/widerrufsbelehrung.blade.php` | gesetzliches Muster (Anlage 1 zu Art. 246a § 1 Abs. 2 S. 2 EGBGB), Betreiberdaten |
| Community-Regeln | `resources/views/oasis/legal/regeln.blade.php` | – |
| Inhalte melden (Art. 16 DSA) | `resources/views/oasis/legal/melden.blade.php` | – |
| Datenschutz & Sicherheit | `resources/views/oasis/sicherheit.blade.php` | `config('oasis.privacy')` |

Ein Wert `null` erscheint auf der Seite als „[noch festzulegen]“. Fristen werden automatisch durchgesetzt (täglicher
`model:prune`); solange eine Frist `null` ist, wird nichts gelöscht.

## Als Entwurf gesetzte Werte

Alle Werte stehen in `config/oasis.php`. Bitte jeden einzeln bestätigen oder ändern.

| Wert | Entwurf | Begründung | Zu prüfen |
| --- | --- | --- | --- |
| `privacy.server_log_days` | 7 Tage | Für Sicherheitsauswertung ausreichend; kurze Frist entspricht Art. 5 Abs. 1 lit. c, e DSGVO und gängiger Aufsichtspraxis. | Stimmt die Logrotation auf dem IONOS-Server damit überein? |
| `privacy.unconfirmed_waitlist_days` | 14 Tage | Genug Zeit für verspätete Bestätigungsmails; danach kein Zweck mehr. | – |
| `privacy.confirmed_waitlist_months` | 6 Monate nach Start | Zweck „Nachrichten rund um den Start“ ist danach erfüllt. | Startdatum `OASIS_LAUNCH_DATE` setzen. |
| `privacy.payment_records_years` | 8 Jahre ab Ende des Kalenderjahres | § 147 Abs. 3 AO (Buchungsbelege seit 2025: 8 Jahre), § 257 HGB. | Mit Steuerberatung bestätigen; Rechnungen und Bücher ggf. länger (10 Jahre). |
| `privacy.records_years` | 3 Jahre | Regelverjährung (§§ 195, 199 BGB) für Ansprüche, die Kündigungen, Widerrufe, Meldungen und Moderationsentscheidungen belegen müssen. | Gilt für Kündigungen, Widerrufe, Meldungen, Organisationsanfragen, Chat-Meldungen und Moderationsfälle. Für Organisationsanfragen ggf. kürzer (z. B. 1 Jahr). |
| `privacy.mail_provider` | IONOS SE, Server in Deutschland | Gleicher Anbieter wie das Hosting, keine Übermittlung in Drittländer. Bisher war Amazon SES geplant. | **Entscheidung:** IONOS oder Amazon SES? Muss zu `MAIL_*` in der Produktiv-`.env` passen; AV-Vertrag abschließen. |
| `server_location` (Hilfe) | IONOS in Deutschland, Bilder bei AWS Frankfurt | Kurzfassung von Abschnitt 17 der Datenschutzerklärung. | Echten Serverstandort bestätigen. |
| `statutes` | AGB | Die Versprechen gehören in AGB § 10. Eine Satzung gibt es bei einem Einzelunternehmen nicht. | Erst `OASIS_PROMISES_CODIFIED=true` setzen, wenn § 10 freigegeben ist. |
| `apps` | `null` | Es gibt noch keine Apps. Angekündigte, aber nicht vorhandene Apps wären irreführend (§ 5 UWG). Die Funktionen-Karte und der App-Store-Absatz in AGB § 6 erscheinen erst, wenn hier etwas steht. | – |
| `founder.name` / `founder.text` | Tobias Kubina, kurzer Text in Ich-Form | Name wie im Impressum (§ 18 Abs. 2 MStV). Der Text behauptet nur, was AGB und Datenschutzerklärung zusagen. | Text persönlich anpassen; Foto unter `founder.photo` ergänzen (optional). |

## Vor dem Start im Code oder Betrieb zu klären

Diese Punkte sind keine Formulierungsfragen. Ohne sie wären einzelne Aussagen der Texte falsch.

1. **Erledigt: Zahlungsbelege überleben die Kontolöschung.** `membership_payments.user_id` wird beim Löschen auf
   `null` gesetzt; Rechnungsdaten und Mollie-IDs bleiben, gesperrt für alles außer Buchhaltung und die Admin-
   Zahlungssuche (`/admin/zahlungen`, docs/oasis-admin.md). Nach `payment_records_years` werden sie automatisch
   gelöscht. Zu prüfen: ob die Admin-Suche als Zugriff auf gesperrte Daten so zulässig ist (Art. 18 DSGVO analog).
2. **Erledigt: Automatische Löschung abgelaufener Konten ist an.** AGB § 13 und Datenschutzerklärung Abschnitt 18
   sagen zu, ein Konto 30 Tage nach Test- oder Mitgliedschaftsende zu löschen. `oasis:prune-expired-accounts` läuft
   täglich um 3 Uhr und ist ab Werk eingeschaltet (`OASIS_RETENTION_AUTO_DELETE`, Standard `true`). Er löscht genauso
   wie die Löschung durch das Mitglied (Bilder, Oasen-Übergabe, Prüf-Einträge) und hält die 30-Tage-Frist nach dem Ende
   einer bezahlten Mitgliedschaft oder eines Geschenks ein. Voraussetzung im Betrieb: der Scheduler läuft
   (`schedule:run` jede Minute per Cron).
3. **Auftragsverarbeitungsverträge (Art. 28 DSGVO)** mit IONOS (Hosting, ggf. E-Mail), AWS (Bilder) und Mistral
   (Textprüfung) abschließen und ablegen. Für AWS die DPF-Zertifizierung und die Standardvertragsklauseln prüfen. Für
   Mistral prüfen, dass Eingaben nicht zum Training genutzt und nur kurz gespeichert werden.
4. **Rolle von Mollie** (Abschnitt 17): Der Entwurf behandelt Mollie als Zahlungsdienstleister, der teilweise als
   eigener Verantwortlicher handelt. Bitte gegen Mollies aktuelle Vertragsbedingungen prüfen.
5. **Verzeichnis von Verarbeitungstätigkeiten** (Art. 30 DSGVO) anlegen. Die Abschnitte der Datenschutzerklärung
   bilden die Gliederung.
6. **Mistral-Schlüssel rotieren.** Der aktuelle Schlüssel stand in einem Chat-Verlauf.

## Fragen an die Anwältin oder den Anwalt

- Reicht Art. 6 Abs. 1 lit. f DSGVO als Rechtsgrundlage für die automatische Textprüfung durch ein Sprachmodell? Ist
  eine Datenschutz-Folgenabschätzung (Art. 35 DSGVO) nötig?
- Mindestalter 16 Jahre für das Konto, 18 Jahre für die Bezahlmitgliedschaft (AGB § 3): passt das zu Art. 8 DSGVO und
  §§ 106 ff. BGB?
- AGB § 14: Änderungen nur mit ausdrücklicher Zustimmung. Ist das für den Betrieb praktikabel, oder braucht es ein
  Sonderkündigungsrecht als Alternative?
- AGB § 12: freiwilliges internes Beschwerdeverfahren nach Art. 20 DSA, obwohl Oasis als Klein- oder
  Kleinstunternehmen ausgenommen sein könnte (Art. 19 DSA). Ist die Formulierung so richtig?
- Widerrufsbelehrung: Passt das Muster für Dienstleistungen zur Mitgliedschaft als digitale Dienstleistung, auch mit
  dem kostenlosen Test vorab?

## Was sich im Code geändert hat (Art. 17 DSGVO)

- Automatische Prüf-Einträge (`content_checks`) werden gelöscht, sobald ihr Text gelöscht wird: beim Löschen eines
  Tropfens, beim Schließen einer Oase und beim Löschen eines Kontos. Ausgenommen sind Einträge, auf denen ein
  Moderationsfall beruht; sie bleiben als Fallunterlage bis zum Ende von `records_years`. Der tägliche `model:prune`
  entfernt außerdem verwaiste Einträge, die durch Löschketten in der Datenbank entstehen.
- `oasis:prune-expired-accounts` löscht über `DeleteAccount` statt direkt per `$user->delete()`. Zuvor blieben Bilder
  im Speicher und Oasen ohne Gastgeber:in zurück, und die Downloadfrist nach einer bezahlten Mitgliedschaft wurde nicht
  eingehalten.
