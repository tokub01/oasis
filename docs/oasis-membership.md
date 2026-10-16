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

Die öffentliche Seite `/mitglied-werden` (`oasis.mitglied`) läuft als Livewire-4-Vollseitenkomponente
`App\Livewire\Membership\Checkout` (`Route::livewire`). Stufe (`stufe`), Zahlungsrhythmus (`rhythmus`),
Zahlungsmittel (`zahlungsmittel`) und der freie Mäzen-Betrag (`betrag`) werden reaktiv per `wire:model.live`
gebunden; die Live-Zusammenfassung (inkl. Jahresrabatt aus zehn Monatsbeiträgen und 19 % USt.-Anteil) wird direkt
in der Komponente berechnet und ersetzt das frühere JavaScript-Modul `membership` in `resources/js/oasis.js`.
`startCheckout()` prüft Authentifizierung, E-Mail-Bestätigung sowie Tarifdaten und startet für externe
Mollie-Weiterleitungen den Checkout per `$this->redirect($checkoutUrl, navigate: false)`.
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

## Kündigung, Widerruf und Verwaltung (Livewire 4, Stage 12)

Alle internen Mitgliedschaftsseiten unter `routes/membership.php` sind klassenbasierte Livewire-4-Komponenten:

- `/app/mitgliedschaft` (`app.membership`) → `App\Livewire\Membership\Overview`: Übersicht über Status, Gründungsplatz,
  Zahlungen, Gutschein-Einlösung (`redeem(RedeemGiftMembership)`) und Geschenkkauf (`buyGift(MollieMembershipService)`,
  externer Redirect zu Mollie mit `navigate: false`).
- `/app/mitgliedschaft/portal` (`app.membership.portal`) → `App\Livewire\Membership\Portal`: zeigt die eigenen Zahlungen
  und die Zahlungsartänderung (`updatePaymentMethod(MollieMembershipService)`). Eine neue `first`-Zahlung über einen Euro
  richtet ein neues Mandat ein; nach erfolgreichem Austausch wird der Euro erstattet. Der Hinweis steht direkt am Formular.
- `/app/mitgliedschaft/kuendigen` (`app.membership.cancel.confirm`) → `App\Livewire\Membership\Cancel`: Bestätigungsseite
  und `cancel(CancelMembership)`. Stoppt weitere Mollie-Abbuchungen sofort; `ends_at` entspricht dem gespeicherten
  bezahlten Periodenende; Bestätigung und Betreiberkopie werden per E-Mail verschickt.
- `/app/mitgliedschaft/widerrufen` (`app.membership.withdraw.confirm`) → `App\Livewire\Membership\Withdraw`:
  Bestätigungsseite und `withdraw(WithdrawMembership)` innerhalb der 14-Tage-Regel. Beendet den Zugang sofort und
  erstattet bezahlte Zahlungen über die Mollie Refund API.
- Rechnungen unter `/app/mitgliedschaft/rechnungen/{payment}` (`app.membership.invoice`, `InvoiceController`) und der
  Webhook `POST /mollie/webhook` (`mollie.webhook`, `MollieWebhookController`) bleiben dedizierte HTTP-Controller.

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
`tests/Feature/CrossCheck/Stage9` und `tests/Feature/CrossCheck/Stage11`. SDK-Fakes verhindern echte API-Aufrufe.

### Stage 12 completion

The live Mäzen summary and checkout consistently accept whole euros, matching the existing service contract.
Fractional, empty and invalid custom amounts display an inline error; fixed-price tiers ignore a previously
entered custom amount. No silent rounding occurs before starting Mollie checkout. Replaced client-side membership
calculation tests now exercise the real `Checkout` component, including all tiers, annual billing and included VAT.
