# Oasis accounts (stage 0)

Laravel Fortify supplies the session authentication, password-reset broker, signed email verification and optional
TOTP two-factor flows. Oasis owns the validation actions and Blade views. No social login or tracking is enabled.

## Routes and responsibilities

| Endpoint | Name | Responsibility |
|---|---|---|
| GET/POST `/testen` | `register` / `register.store` | Register and sign in an unverified account |
| GET/POST `/anmelden` | `login` / `login.store` | Email-or-username/password sign-in |
| POST `/abmelden` | `logout` | End the session |
| GET/POST `/passwort-vergessen` | `password.request` / `password.email` | Request a reset notification |
| GET `/passwort-zuruecksetzen/{token}` | `password.reset` | Reset form |
| POST `/passwort-zuruecksetzen` | `password.update` | Validate token and replace password |
| GET `/email/bestaetigen` | `verification.notice` | Verification instructions |
| GET `/email/bestaetigen/{id}/{hash}` | `verification.verify` | Signed verification link |
| POST `/email/bestaetigung-senden` | `verification.send` | Resend verification notification |
| GET/POST `/passwort-bestaetigen` | `password.confirm` / `password.confirm.store` | Recent-password confirmation |
| GET/POST `/zwei-faktor-anmeldung` | `two-factor.login` / `two-factor.login.store` | TOTP or recovery challenge |
| GET `/app` | `app.home` | Authenticated, verified app landing page |
| GET `/app/einstellungen` | `app.settings` | Authenticated, verified account settings |
| PUT `/app/konto/profil` | `user-profile-information.update` | Display name and email changes |
| PUT `/app/konto/passwort` | `user-password.update` | Current-password-protected password change |
| GET `/app/konto/export` | `account.export` | Download the signed-in user's profile JSON |
| DELETE `/app/konto` | `account.destroy` | Delete the signed-in user after recent password confirmation |

Fortify's remaining `two-factor.*` management routes retain its default `/user/...` URLs. They require authentication
and recent password confirmation. Setup stays pending until the user submits a valid authenticator code. Recovery
codes are consumed once. The UI always uses named routes.

`config/fortify.php` defines features, paths and redirects; `App\Providers\FortifyServiceProvider` connects actions,
views and limiters. `routes/app.php` is included from `routes/web.php`. Public marketing routes stay in
`routes/oasis.php`; the former non-submitting auth preview routes and `/login` redirect are removed.

The form field `login` accepts email (contains `@`) or a lowercase username. The custom authentication callback
uses Laravel's user provider and preserves password rehashing and Fortify's 200 ms credential-check timing floor.
Password confirmation checks the already authenticated user; `login` is a form field, not a database column.

## Account data and validation

The stage migration renames `users.name` to `display_name`, preserving existing names. It adds a unique `username`,
`birth_date`, `terms_accepted_at`, `trial_ends_at` and Fortify's three two-factor columns. Unknown values for existing
starter accounts remain nullable: the migration does not invent age, consent or usernames. Newly registered accounts
must provide all required profile fields. `display_name` is limited to 50 characters by validation.

`CreateNewUser` lowercases username/email, enforces a 3–30 character username using ASCII letters, digits and
underscores, and rejects reserved service/moderation names. Age is calculated from the registration date in
`Europe/Berlin`: the sixteenth birthday is accepted. Consent is explicitly required; the server supplies its timestamp.
The trial end is the registration time plus `oasis.trial_days`. Expiry enforcement and reminders belong to stage 1.

`PasswordValidationRules` applies a ten-character minimum, confirmation and the existing `Password::defaults()`.
Production defaults can be stricter (currently twelve characters, mixed case, numbers, symbols and breach checking).
The User model's `hashed` cast performs hashing; Fortify encrypts TOTP secrets and recovery codes. Those values,
password hashes and remember tokens are hidden in model serialization and excluded from account exports.

`UpdateUserProfileInformation` accepts display name/email only; username and birth date remain fixed. An email change
clears verification and sends a new link. Named error bags match the settings forms: `updateProfileInformation`,
`updatePassword`, and Fortify's `confirmTwoFactorAuthentication`.

German validation/auth/reset messages and notification copy live in `lang/de/` and `lang/de.json`. Claude owns
`auth.php` and `passwords.php`; the backend owns `validation.php`. The application
locale defaults to `de`; `.env.example` uses `APP_LOCALE=de`. An explicit deployment locale setting takes precedence.

## Privacy and request limits

Exports use an explicit field list from the authenticated user, no request-supplied user ID, and `no-store` caching.
Deletion logs out **before** deleting the model: Laravel rotates and saves the remember token on logout, which would
otherwise recreate a deleted model. It removes the account, its password reset tokens and database sessions, then
invalidates the browser session. Legacy public-form submissions are independent records and are not matched/deleted
by email. Future stages must add their account-owned records to export/deletion when introducing them.

Export/deletion remain accessible to authenticated users who have not yet verified their email. App pages require
verification. Deletion uses `password.confirm` with the existing `auth.password_timeout` (seconds).

Sign-in allows five requests per minute per lowercase login/IP; the two-factor challenge allows five per account/IP.
Registration allows six per minute per IP, including invalid requests. Fortify has no registration limiter config key,
so `ThrottleRegistration` delegates only `register.store` to Laravel's named throttle middleware. Other auth endpoints
are unaffected by that registration budget. All browser POST/PUT/DELETE routes retain web/CSRF middleware.

`NeutralPasswordResetLinkResponse` makes unknown-address and broker-throttled reset requests return the same neutral
message, HTTP status and browser/JSON response shape as a successful request. Exposing broker throttling would reveal
known accounts on a second request. Malformed email input still fails validation; reset-token failures still use
the `email` error field. The neutral response does not claim constant end-to-end mail delivery latency.

## Setup and verification

Install existing lockfile dependencies with `./vendor/bin/sail composer install`, then run
`./vendor/bin/sail artisan migrate`. Mail uses the configured Laravel mail transport (Mailpit locally).

Backend regressions are in `tests/Feature/Auth/` and `tests/Feature/Account/`: registration/age/consent/passwords,
duplicate/reserved names, rate limits, sign-in/out, email verification, reset tokens, two-factor setup/challenges,
single-use recovery codes, export isolation, deletion/logout, and profile/password updates.

Run `./vendor/bin/sail artisan test`, `./vendor/bin/sail pint --test app tests routes config` and `npm run build`.
The stage handoff in `docs/oasis-stage0-backend-report.md` records actual check results and integration notes.
