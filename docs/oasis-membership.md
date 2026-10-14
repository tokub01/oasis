# Oasis – Mitgliedschaft & Zahlungen (Stufen 1 & 5)

Dokumentation des Mitgliedschafts-, Berechtigungs- und Zahlungssystems von Oasis. Entwickelt unter vollständiger Einhaltung von SOLID-Prinzipien, strengen Datenschutzvorgaben (DSGVO) und deutschem Verbraucherrecht (§ 312k BGB, § 355/356 BGB).

---

## 1. Zentraler Teilnahme-Check (Stage 1 Participation Check)

Nach Ablauf der 30-tägigen kostenlosen Testphase wechselt ein Konto automatisch in den **Read-Only-Modus** (Lesemodus):
Nutzerinnen und Nutzer können weiterhin alle öffentlichen und privaten Inhalte lesen, ihr Profil einsehen, einen Datenexport herunterladen (DSGVO Art. 15/20) und ihr Konto löschen (Art. 17).
Schreibende Interaktionen (Beiträge/Tropfen erstellen, antworten, reagieren, Oasen gründen, Chat-Nachrichten senden) sind gesperrt, bis eine Mitgliedschaft oder ein Gutschein aktiv ist.

### Methodensignatur auf dem `User`-Model:
```php
$user->canParticipate(): bool
```

**Kriterien für `canParticipate()`:**
1. **Staff**: Team-Mitglieder (Moderation, Admin) dürfen immer teilnehmen (`$user->role->isStaff()`).
2. **Aktive Testphase**: `$user->hasActiveTrial()` (`trial_ends_at !== null && trial_ends_at->isFuture()`).
3. **Aktives Abonnement**: `$user->hasActiveMembership()` (Gültiges Stripe-Abonnement inkl. Grace Period).
4. **Aktive Geschenk-Mitgliedschaft**: `$user->hasActiveGiftMembership()` (Eingelöster Gutschein mit `expires_at > now()`).

### Middleware:
- **Alias**: `'participate'`
- **Klasse**: `\App\Http\Middleware\EnsureUserCanParticipate::class`
- **Registrierung**: In `bootstrap/app.php` via `$middleware->alias(['participate' => ...])`.
- **Verhalten**:
  - API / JSON (`expectsJson()` bzw. `api/*`, `app/chat/api/*`): Gibt HTTP `403 Forbidden` mit deutscher Fehlermeldung zurück (`"Deine Testphase ist abgelaufen. Bitte schließe eine Mitgliedschaft ab, um mitzumachen."`).
  - Web-Anfragen: Leitet auf die Mitgliedschaftsseite `route('app.membership')` weiter mit Hinweistext im Flash-Status.

### Gate:
- **Name**: `'participate'`
- **Registrierung**: In `App\Providers\AppServiceProvider::boot()`.
- **Nutzung**:
  ```php
  Gate::allows('participate');
  $user->can('participate');
  $this->authorize('participate');
  ```
- **In Blade**:
  ```blade
  @can('participate')
      {{-- Beitrags-Eingabe, Reaktionen, Antworten --}}
  @else
      <p>Dein Test ist abgelaufen. <a href="{{ route('oasis.mitglied') }}">Werde Mitglied</a>, um mitzuschreiben.</p>
  @endcan
  ```

---

## 2. Testphase & Erinnerungen (Stage 1)

- **Beginn**: Startet automatisch bei Registrierung (`CreateNewUser`) für `config('oasis.trial_days')` (30 Tage) ohne Erfassung von Zahlungsdaten.
- **Erinnerungs-Mail**: Genau 2 Tage vor Ablauf wird eine E-Mail (`TrialEndingReminderMail`) versendet.
  - Befehl: `php artisan oasis:trial-reminders` (täglich um 09:00 Uhr via `routes/console.php`).
  - **Idempotenz**: Setzt `users.trial_reminder_sent_at = now()`. Accounts mit gesetztem Zeitstempel oder bereits bestehendem Abonnement werden übersprungen.
- **Automatisches Testende**: Keine automatische Verlängerung, keine Kostenfalle.

---

## 3. Aufbewahrungsfrist & Löschung (Retention)

- Nach Ablauf des Tests oder nach Beendigung einer Mitgliedschaft besteht eine `config('oasis.retention_days')` (30 Tage) lange Frist, in der Daten heruntergeladen werden können.
- **Bereinigungsbefehl**: `php artisan oasis:prune-expired-accounts` (täglich um 03:00 Uhr via `routes/console.php`).
- **Sicherheits-Schalter**: Gesteuert über `config('oasis.retention.auto_delete_expired')` (Standard: `false`, konfigurierbar via `OASIS_RETENTION_AUTO_DELETE`).
  - Wenn `false`: Löscht keine Konten, sondern gibt einen Bericht über abgelaufene Konten aus.
  - Wenn `true` oder mit `--force`: Löscht Konten, deren Testphase länger als 30 Tage zurückliegt und die kein aktives Abonnement besitzen.

---

## 4. Stripe-Integration & Laravel Cashier (Stage 5)

- **Paket**: `laravel/cashier` v16.8 (Stripe).
- **Environment**: Keys `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` in `.env` (Platzhalter in `.env.example`).
- **Standardwährung & Locale**: Euro (`eur`) und deutsches Format (`de`) in `config/cashier.php`.
- **Datenbank**: Eigene Migration `2026_10_12_200000_create_membership_tables.php` mit `stripe_id`, `pm_type`, `pm_last_four`, `subscriptions`, `subscription_items` und `gift_memberships`.

### Synchronisation der Tarife (`oasis:stripe-sync`):
Befehl: `php artisan oasis:stripe-sync`
- Liest `config('oasis.tiers')` und `config('oasis.year_months')` (10 Monate = 2 Monate geschenkt bei Jahreszahlung):
  - **Solidar**: 3 € / Monat, 30 € / Jahr (inkl. 19 % MwSt.)
  - **Oase**: 5 € / Monat, 50 € / Jahr (inkl. 19 % MwSt.)
  - **Mäzen**: ab 12 € / Monat, ab 120 € / Jahr (inkl. 19 % MwSt.)
- Erstellt Produkte und wiederkehrende Preise idempotent in Stripe über `lookup_key` (`oasis_{tier}_monthly`, `oasis_{tier}_yearly`).

### Behandlung individueller Mäzen-Beiträge:
- Mäzen-Zahler können beliebige Beträge ab 12 € / Monat frei wählen.
- **Implementierungsentscheidung**: Bei Wahl eines benutzerdefinierten Betrags generiert `StripeMembershipService` on-demand einen wiederkehrenden Stripe-Preis, verknüpft mit dem kanonischen Mäzen-Produkt und einem deterministischen Lookup-Key (`oasis_maezen_{intervall}_{betrag}`).
- **Begründung**:
  1. Abonnements in Stripe erfordern ein wiederkehrendes Price-Objekt.
  2. Alle Mäzen-Zahler verbleiben unter demselben Stripe-Produkt für konsistentes Reporting.
  3. Der Lookup-Key stellt sicher, dass gleiche Beträge wiederverwendet werden und keine Preis-Duplikate entstehen.

### Hosted Stripe Checkout:
- Route: `POST /mitglied-werden/checkout`
- Aktiviert, sobald `config('oasis.checkout_enabled')` auf `true` gesetzt ist (standardmäßig `false`, liefert 404 bei Inaktivität).
- Unterstützt SEPA-Lastschrift (`sepa_debit`), Kreditkarte (`card`) und PayPal (`paypal`).
- **Robustheits-Fallback**: Sollte eine Zahlungsart im Stripe-Dashboard noch nicht aktiviert sein, fängt der Service die Stripe-Ausnahme ab und fällt transparent auf Standard-Kartenzahlung zurück.

---

## 5. Gründungsmitglieder & Preisgarantie

- **Limit**: `config('oasis.founding.seats')` (1.000 Plätze).
- **Zuweisung**: Die ersten 1.000 zahlenden Mitglieder (oder Gutschein-Einlöser) erhalten `founding_member_at = now()` und `founding_price_guarantee = true`.
- **Sitzzähler**:
  - `StripeMembershipService::remainingFoundingSeats(): int` liefert die echten verbleibenden Plätze.
  - Anzeige auf `/app/mitgliedschaft` und öffentlichen Seiten.

---

## 6. Geschenke (Gift Memberships)

- **Tabelle**: `gift_memberships` (`code`, `tier`, `months`, `amount`, `purchaser_user_id`, `redeemed_by_user_id`, `expires_at`).
- **Code-Format**: `OASIS-XXXX-XXXX` (Großbuchstaben und Ziffern).
- **Kauf**: `POST /app/mitgliedschaft/geschenke/kaufen` startet einen 12-Monats-Checkout. Bei erfolgreicher Zahlung erzeugt der Webhook den Gutschein.
- **Einlösung**: `POST /app/mitgliedschaft/einloesen` validiert den Code, weist die Mitgliedschaft für die Dauer zu und schaltet den Account sofort frei. Ein Code kann nur einmal eingelöst werden.

---

## 7. Kündigung (§ 312k BGB) & 14-Tage-Widerruf (§ 355/356 BGB)

### Kündigungs-Button (§ 312k BGB):
1. **Schritt 1**: Auf `/app/mitgliedschaft` Klick auf "Vertrag kündigen".
2. **Schritt 2**: Bestätigungsseite `/app/mitgliedschaft/kuendigen` zeigt Vertragsdaten, Name, E-Mail, ordentliches Kündigungsdatum (Ende der Periode) und den Button **"Jetzt kündigen"**.
3. **Schritt 3**: `POST /app/mitgliedschaft/kuendigen` setzt `subscription->cancel()` (Grace Period bis Periodenende).
4. **Bestätigung**: Sofortige Textform-Bestätigung per E-Mail (`MembershipCancelledMail`) an den Nutzer sowie Betreiberkopie an `config('oasis.mail.operator')` mit Datum und Uhrzeit in Berliner Zeit (`Europe/Berlin`).

### 14-Tage-Widerruf (§ 355, 356 BGB) & Vollständige Erstattung (§ 357 Abs. 1 BGB):
- Bei Verträgen, die innerhalb der letzten 14 Tage geschlossen wurden (Berechnung: `now()->lessThanOrEqualTo($createdAt->addDays(14)->endOfDay())`), steht die Option "Mitgliedschaft widerrufen (14-Tage-Frist)" zur Verfügung.
- Bestätigungsseite `/app/mitgliedschaft/widerrufen` → Klick auf "Jetzt widerrufen".
- Beendet das Abonnement mit sofortiger Wirkung (`cancelNow`), erstattet alle bezahlten Rechnungen der Mitgliedschaft via Stripe Refund (`StripeMembershipService::refundSubscriptionInvoices`) vollumfänglich und versendet die Widerrufsbestätigung (`MembershipWithdrawnMail`) mit Erstattungsvermerk an den Nutzer und Betreiber.

---

## 8. Gruppenstarts & Synchronisierte Testphasen ("Gemeinsam starten")

- **Service**: `App\Services\Membership\GroupTrialService`
- **Konfiguration**: `config('oasis.group_start_days')` (7 Tage) und `config('oasis.trial_days')` (30 Tage).
- **Logik**:
  - Eine Gruppe hat ein Zeitfenster von bis zu 7 Tagen (`group_start_days`), um alle Mitglieder einzuladen.
  - Sobald die Gruppe vollständig ist oder die 7 Tage ablaufen (`shouldAutoStartGroup()`), startet der 30-Tage-Test für alle Gruppenmitglieder synchron (`startGroupTrial($users)`).
  - Direkt auf dem User-Model: `$user->startGroupTrial(?int $trialDays = null)`.

---

## 9. Webhook-Sicherheit & Signaturprüfung

- **CSRF-Exemption**: In `bootstrap/app.php` ist `stripe/webhook` von der CSRF-Prüfung ausgenommen.
- **Signatur-Zwang**: `StripeWebhookController::handleWebhook` erzwingt ein konfiguriertes `cashier.webhook.secret` und prüft den `Stripe-Signature`-Header via Stripe SDK (`WebhookSignature::verifyHeader`). Unsignierte oder ungültig signierte Requests werden mit HTTP 403 abgewiesen.
- **Fehlerbehandlung**: Fachliche Fehler werfen `MembershipDomainException` (nutzerfreundliche Nachricht), unerwartete System-/API-Fehler werden ohne PII geloggt und zeigen eine neutrale deutsche Fehlermeldung. `session('error')` wird in `layouts/app.blade.php` und `app/membership/index.blade.php` mit `role="alert"` gerendert.

---

## 8. Statistik-Schnittstelle für Stufe 6 (`MembershipStats`)

Klasse: `App\Queries\Membership\MembershipStats`
Aggregierte Abfragen aus lokalen Datenbanktabellen, keine API-Netzwerkaufrufe, keine personenbezogenen Listen:

| Methode | Rückgabe | Beschreibung |
|---|---|---|
| `trialsStarted(CarbonInterface $from, CarbonInterface $to)` | `int` | Gestartete Testphasen im Zeitraum |
| `trialsActive()` | `int` | Aktuell laufende Testphasen |
| `trialsConverted(CarbonInterface $from, CarbonInterface $to)` | `int` | In Abo oder Gutschein konvertierte Tests |
| `trialsExpired(CarbonInterface $from, CarbonInterface $to)` | `int` | Abgelaufene Tests ohne Konvertierung |
| `activeMembersByTier()` | `array<string, int>` | Aktive Mitglieder nach Stufe (`solidar`, `oase`, `maezen`) |
| `activeMembersCount()` | `int` | Gesamtzahl aktiver Mitglieder |
| `monthlyRecurringRevenue()` | `float` | Monatlich wiederkehrender Umsatz (MRR) in Euro |
| `cancellations(CarbonInterface $from, CarbonInterface $to)` | `int` | Kündigungen im Zeitraum |
| `foundingSeatsUsed()` | `int` | Vergebene Gründungsplätze |
| `foundingSeatsRemaining()` | `int` | Verbleibende Gründungsplätze |
| `giftsSold(CarbonInterface $from, CarbonInterface $to)` | `int` | Verkaufte Geschenk-Gutscheine |
| `giftsRedeemed(CarbonInterface $from, CarbonInterface $to)` | `int` | Eingelöste Geschenk-Gutscheine |

## Stage 9: paid, idempotent gift fulfillment and payment logs

Gift Checkout completion issues a voucher only when `payment_status` is exactly `paid` and a Checkout Session ID
is present. Delayed SEPA payments are fulfilled on `checkout.session.async_payment_succeeded`;
`checkout.session.async_payment_failed` is acknowledged without creating a voucher or logging purchaser metadata.
A unique database index on `gift_memberships.stripe_checkout_session_id` and Laravel's `firstOrCreate` recovery
prevent duplicate vouchers from retries or competing inserts. Existing vouchers without a Stripe session remain
valid; non-null duplicate session IDs must be reconciled before applying the unique-index migration.

Unexpected payment failures log only the exception class and provider error code, never the raw exception message
or response body. Domain errors remain translated user-facing messages. Password reset link requests additionally
have a six-per-minute IP budget; account-specific broker throttling and neutral known/unknown-address responses
remain in place.
