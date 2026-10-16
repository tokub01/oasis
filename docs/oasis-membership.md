# Oasis – Mitgliedschaft und Mollie-Zahlungen

Die Teilnahmeprüfung liegt auf `User::canParticipate()`: Team-Mitglieder, laufende kostenlose Tests,
bezahlte Mitgliedschaften und eingelöste, nicht abgelaufene Gutscheine dürfen schreiben. Nach Ablauf bleibt
der Lesemodus mit Datenexport und Kontoverwaltung. Middleware `participate` und Gate gleichen Namens verwenden
diese Prüfung. Ein angelegter Checkout oder ein unbestätigter Zahlungsstatus gewähren keinen Zugang.

## Anbieter und Paketentscheidung

`mollie/laravel-mollie` 4.1.2 und `mollie/mollie-api-php` 3.15.0 unterstützen Laravel 13 und PHP 8.5.
Auch `mollie/laravel-cashier-mollie` 2.21.1 unterstützt Laravel 13. Verwendet wird der Laravel-API-Wrapper mit
nativen Mollie-Abos: Die bestehenden Mitgliedschaftsregeln bleiben im Domain-Service, Mollie übernimmt wiederkehrende
Abbuchungen. Damit entfallen Cashier-Pläne, Cashier-Auftragsabrechnung und deren zusätzlicher Scheduler.
`MollieGateway` kapselt ausschließlich das SDK; `MollieMembershipService` koordiniert die fachlichen Abläufe.

Quellen: [Laravel-Mollie](https://github.com/mollie/laravel-mollie/tree/v4.1.2),
[Cashier-Mollie](https://github.com/mollie/laravel-cashier-mollie),
[wiederkehrende Zahlungen](https://docs.mollie.com/docs/recurring-payments).

## Einrichtung und Umzug

- `MOLLIE_KEY` nur in der Umgebung hinterlegen; Test- und Live-Schlüssel niemals ausgeben oder committen.
- `config('oasis.payment.provider')` ist `Mollie`; Preise und Umsatzsteuersatz kommen weiterhin aus `oasis.tiers`,
  `oasis.year_months` und `oasis.vat_rate`. `oasis.checkout_enabled` bleibt der Freigabeschalter.
- Der Webhook `POST /mollie/webhook` muss öffentlich per HTTPS erreichbar sein. Lokales `localhost` reicht für
  Mollies Server nicht; für End-to-End-Tests eine erreichbare Testumgebung verwenden.
- Die Migrationen erstellen `users.mollie_customer_id`, lokale `subscriptions`, `membership_payments` und
  `gift_memberships.mollie_payment_id`. Die Zahlungstabelle hält Bestellungen, Verarbeitungsstatus und Rechnungsdaten.
- Die Brückenmigration unterstützt leere Altdatenbanken. Bei vorhandenen Kunden-, Abo- oder Geschenkkaufreferenzen
  stoppt sie vor Änderungen, damit alte Abos und Zahlungsnachweise nicht gelöscht oder als Mollie-Mandate behandelt
  werden. Solche Daten brauchen einen individuellen Umzug einschließlich Beendigung alter Abbuchungen und neuer
  Zustimmung zum Mollie-Mandat. Die Brücke ist absichtlich nicht rückwärts ausführbar; vorher ein Backup erstellen.

## Checkout, Mandate und wiederkehrende Zahlungen

`POST /mitglied-werden/checkout` prüft Stufe, Rhythmus, Zahlungsmittel und den optionalen Mäzen-Betrag.
Monatliche Beiträge sind 3/5/ab 12 Euro. Jahresbeiträge werden aus zehn Monatsbeiträgen berechnet und decken zwölf
Monate ab. Der tatsächliche Betrag in Cent und der Rhythmus werden dauerhaft gespeichert; spätere Preisänderungen
verändern bestehende Abos nicht. MRR verwendet diese Beträge und teilt Jahresbeträge durch zwölf.

Mollie erhält eine `first`-Zahlung und erstellt dadurch ein Mandat. Erst eine per API bestätigte bezahlte Zahlung
startet das Abo mit `1 month` oder `12 months`; dessen Startdatum liegt am Ende des bereits bezahlten ersten
Zeitraums. Kreditkarte heißt bei Mollie `creditcard`, PayPal `paypal`. Bei Auswahl SEPA öffnet der Checkout die
verfügbaren geeigneten Erstzahlungsarten. Eine erste SEPA-Lastschrift ist bei Mollie kein Mandats-Checkout:
SEPA-Folgezahlungen benötigen ein gültiges Mandat aus einer unterstützten Erstzahlung und die Freischaltung der
Zahlungsart im Dashboard. Ohne diese Freischaltung bleiben Kreditkarte/PayPal verwendbar.

Bestellungen werden vor dem API-Aufruf gespeichert. Geht die Antwort verloren, kann der Webhook über die von der
API zurückgegebene `intent_id` die Bestellung wiederfinden. Stabile Idempotenzschlüssel schützen API-Wiederholungen.
Zwei bezahlte Checkout-Tabs führen zu einem Abo; die zweite Erstzahlung wird erstattet.

## Webhook und Abgleich

Mollies klassische Zahlungswebhooks sind unsigniert. Der Controller akzeptiert ausschließlich ein begrenztes
`tr_…`-Zahlungskennzeichen und lädt den Datensatz über das authentifizierte SDK nach. Kunde, Betrag und Währung müssen
mit der gespeicherten Bestellung übereinstimmen. Werte im eingehenden Webhook und Tarifdaten aus Zahlungsmetadaten
werden nicht zur Freischaltung verwendet. Status `paid` und ein gesetzter Bezahlzeitpunkt sind erforderlich.
API-/Verarbeitungsfehler liefern HTTP 503 für einen späteren Wiederholungsversuch; Logs enthalten nur Fehlerklassen.

Der eindeutige Zahlungsindex und `processed_at` verhindern doppelte Gutscheine und doppelte Verlängerungen.
`vendor/bin/sail artisan oasis:mollie-sync` holt offene lokale Zahlungen und alle Seiten der Mollie-Abo-Zahlungen
nach und gleicht Kündigungen im Dashboard ab. Der Scheduler führt ihn alle zehn Minuten ohne Überlappung aus.
Fehlgeschlagene Zahlungen verlängern `paid_until` nicht; nach Ablauf endet die Schreibberechtigung.

## Kündigung, Widerruf und Verwaltung

- Die bestehende Kündigung über Bestätigungsseite und „Jetzt kündigen“ stoppt weitere Mollie-Abbuchungen sofort.
  `ends_at` entspricht dem gespeicherten bezahlten Periodenende; ein Jahresvertrag erhält kein erfundenes Monatsende.
  Bestätigung und Betreiberkopie werden weiterhin per E-Mail verschickt.
- Der Widerruf innerhalb der bestehenden 14-Tage-Regel beendet den Zugang sofort und erstattet bezahlte Zahlungen
  über die Mollie Refund API. Der stabile Refund-Schlüssel und der lokale Erstattungsstatus verhindern Doppelzahlungen.
  Bei einem API-Fehler erhält der Betreiber eine konkrete Aufgabe zur manuellen Erstattung.
- Mollie besitzt kein entsprechendes gehostetes Kundenportal. `/app/mitgliedschaft/portal` zeigt die eigenen Zahlungen
  und eine CSRF-geschützte Zahlungsartänderung. Eine neue `first`-Zahlung über einen Euro richtet ein neues Mandat ein;
  nach erfolgreichem Austausch wird der Euro erstattet. Der Hinweis steht direkt am Formular.
- Rechnungen unter `/app/mitgliedschaft/rechnungen/{payment}` sind ausschließlich für den Zahler erreichbar. Sie zeigen
  Betrag, enthaltene Umsatzsteuer, Zahlungsreferenz und gespeicherte Rechnungsdaten. Der Browser kann sie drucken oder
  als PDF speichern. Adressdaten werden übernommen, soweit die Zahlungsart sie zurückliefert.

## Geschenke und Gründungsplätze

Ein Geschenkkauf ist eine einmalige `oneoff`-Zahlung für zwölf Monate und erzeugt kein Abo. Der Gutschein entsteht
erst nach bezahlter API-Bestätigung und bleibt bei Wiederholungen derselbe. Die Einlösung aktiviert die gespeicherte
Stufe bis `expires_at`. Bezahlte neue Mitgliedschaften und Gutschein-Einlösungen können einen der konfigurierten
Gründungsplätze mit Preisgarantie erhalten. `remainingFoundingSeats()` liest die echte lokale Belegung.

## Testphase, Aufbewahrung und Statistik

Kostenlose Tests starten bei Registrierung ohne Zahlungsdaten, enden selbständig und verlängern sich nicht.
`oasis:trial-reminders` erinnert zwei Tage vorher; Gruppenstarts verwenden weiterhin `GroupTrialService`.
`oasis:prune-expired-accounts` bleibt über `oasis.retention.auto_delete_expired` standardmäßig deaktiviert.
`MembershipStats` liefert lokale aggregierte Test-, Mitglieder-, Umsatz-, Kündigungs-, Geschenk- und Gründungszahlen
ohne externe API-Aufrufe. Bezahlte Restlaufzeiten zählen weiterhin als Zugang; gekündigte Abos erzeugen keinen MRR.

## Verifikation

Mitgliedschaftstests liegen in `tests/Feature/Membership`, angepasste unabhängige Regressionen in
`tests/Feature/CrossCheck/Stage9`. SDK-Fakes verhindern echte API-Aufrufe. Codex verwendet ausschließlich die
Testdatenbank `testing_codex` und führt Prüfungen im Sail-Container aus.
