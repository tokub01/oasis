# Bot protection

Oasis protects registration, forms and the network against automated accounts **without captcha, fingerprinting or
third-party services**. Every measure is in our own code; refusals are counted per day and reason without addresses or
accounts and shown on Admin → System.

## Forms

| Measure | Where | How |
|---|---|---|
| Honeypot | registration (`<x-oasis.bot-trap />`), the five public forms (`oasis/forms/_feedback`, `form.website`) | an off-screen field hidden from assistive technology; filled = refused |
| Time trap | registration (hidden `form_token`), public forms (`SubmitsPublicForm::$formToken`, `#[Locked]`) | encrypted time stamp; sent in under 3 seconds, older than 24 hours, missing or forged = refused with a neutral message and a fresh stamp |
| Registrations per IP | `RateLimiter` `registration` (FortifyServiceProvider) | 6 per minute and `registrations_per_ip_per_day` (10) per day; the daily limit shows a message instead of an error page |

`App\Support\Security\BotProtection` holds the checks and the counters (`BotProtection::REASONS`).

## Throwaway addresses

`App\Rules\NotDisposableEmail` refuses throwaway domains (and their subdomains) for **registration, e-mail changes and
the waitlist** – never for content reports (Art. 16 DSA), cancellations (§ 312k BGB) or withdrawals, which must stay
open to everyone.

- List: the open project [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains)
  (CC0), bundled in `resources/data/disposable-email-domains.txt` (9,895 domains on 17 October 2026).
- `oasis:disposable-domains-update` (monthly) downloads the current list into `storage/app/disposable-email-domains.txt`;
  a download with fewer than 1,000 domains or invalid lines is ignored.
- False positive? Add the domain to `oasis.bot_protection.allowed_email_domains`.

## Network

`App\Support\Security\AccountLimits`, enforced in `PublishPost` (every Tropfen, reply and carry), `ManageFollows` and
`ManageConversation::create` (after the privacy checks, so a refused chat stays a 403 and costs no budget):

| Limit | New account (< `new_account_hours`, 24 h) | Established account |
|---|---|---|
| Tropfen per hour | 10 (plus the general 10 per minute in Compose) | – |
| Follows per hour | 30 | 300 |
| New chats per hour | 3 | 30 |
| Links per Tropfen | 2 | – |
| Mentions per Tropfen | 3 | – (hints are capped at 10) |
| Same text again | refused for 10 minutes | refused for 10 minutes |

Messages are friendly German validation errors at the field.

## Tests

`phpunit.xml` sets `OASIS_BOT_TIME_TRAP=false` and `OASIS_NEW_ACCOUNT_HOURS=0`, because most tests submit forms
instantly with fresh accounts; the honeypot and the duplicate rule stay active everywhere.
`tests/Feature/Security/BotProtectionTest.php` switches both on and covers every measure.
