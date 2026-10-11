# Stage 0 backend handoff

Implemented on the existing branch without commits or pushes. Backend reference: [oasis-accounts.md](oasis-accounts.md).

## Files delivered

- `composer.json`, `composer.lock`: explicitly requested `laravel/fortify` ^1.41 (installed 1.41.0 and its dependencies).
- `config/fortify.php`, `app/Providers/FortifyServiceProvider.php`, `bootstrap/providers.php`: features, German paths,
  action/view registration, login/registration/challenge limits.
- `app/Http/Middleware/ThrottleRegistration.php`: registration-only named Laravel throttle.
- `app/Http/Responses/NeutralPasswordResetLinkResponse.php`: neutral browser/JSON replies for unknown addresses and
  broker throttling, integrated with Claude's initial response implementation.
- `app/Actions/Fortify/{CreateNewUser,PasswordValidationRules,ResetUserPassword,UpdateUserPassword,
  UpdateUserProfileInformation}.php`: account/consent/age validation and password/profile updates.
- `database/migrations/2026_10_11_200000_add_account_fields_to_users_table.php`, `app/Models/User.php`: account fields,
  email verification, hashing and optional encrypted two-factor data; `name` renamed to `display_name`.
- `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`: updated for the renamed account model.
- `routes/app.php`, `routes/web.php`: app landing/settings, account export/deletion, remove obsolete login redirect.
- `routes/oasis.php`: removed exactly the two obsolete auth preview routes and their comment.
- `app/Http/Controllers/Account/{ExportAccountController,DeleteAccountController}.php`: own-profile JSON download;
  password-confirmed deletion with logout before removal, reset-token/session cleanup.
- `config/app.php`, `.env.example`, `lang/de/validation.php`, `lang/de.json`: German locale defaults, validation and
  verification/reset notification text. `lang/de/auth.php` and `passwords.php` are Claude's per addendum 3; his restored
  versions are preserved, including the identical neutral `passwords.sent`/`passwords.user` message.
- `tests/Feature/Auth/{RegistrationTest,AuthenticationTest,TwoFactorAuthenticationTest}.php`,
  `tests/Feature/Account/AccountTest.php`: registration/auth/account regressions, extended for username login, password
  rehashing and neutral reset responses.
- `tests/Feature/Oasis/Pages/PublicPagesTest.php`: updated auth route resolution in the public-page datasets;
  replaced the obsolete login-redirect and disabled-password-recovery expectations with real form checks.
- `docs/oasis-website.md`, `docs/oasis-accounts.md`, this report: backend architecture, URLs, setup, validation,
  privacy and actual checks.

No new `oasis.*` configuration keys. Trial duration still comes from `oasis.trial_days`. The new Fortify configuration
is documented inline. The settings route is `/app/einstellungen`, name `app.settings`, matching the UI comment.
Profile updates accept `display_name`/`email`; username and birth date remain fixed.

## Actual checks

| Check | Result |
|---|---|
| `sail composer require laravel/fortify` | Successful; 15 installs, existing dependency versions unchanged |
| `sail artisan migrate` | Account migration applied successfully to the local development database |
| `sail artisan route:list --path=app` | Successful; app/account/update routes registered |
| `sail artisan view:cache` | Successful |
| Initial `sail artisan test tests/Feature/Auth tests/Feature/Account` | **35 passed, 218 assertions** before addenda integration |
| Final `sail artisan test --parallel --processes=1` | **209 passed, 1232 assertions**, including all backend and UI tests |
| Final `sail artisan test --parallel --processes=1 tests/Feature/Account` | **8 passed, 50 assertions** |
| Pint on all delivered PHP files, including migration/factory/seeder/language files | **25 files passed**, public-page test also passed |
| `sail pint --test app tests routes config` | **6 style issues outside the backend assignment**, below |
| `npm run build` | Successful; three existing font URL warnings, runtime font files present |
| `sail php vendor/bin/phpstan analyse --no-progress` | **7 errors outside backend assignment**; no backend action/model errors |
| `git diff --check` | Successful |
| `sail composer audit --locked --format=json` | 21 advisories in 5 existing packages; details below |

## Addenda integration and remaining checks

The brief's addenda were read on receipt of Claude's follow-up. Login now uses `login` (email or lowercase username),
with a lowercase login/IP limiter; forgot/reset password retain `email`. The callback preserves Laravel's credential
provider, password rehashing and Fortify's timing floor. Claude's password-confirmation callback was retained and its
Hash import restored. The two-factor regression now signs in with the username. The invalid-reset-token test continues
to assert errors on `email`, not `login`.

Claude fixed settings handle rendering (`@{{ ... }}` escaped interpolation). All public-page route resolution and
the two obsolete preview expectations are updated. The final complete test suite is green. Claude's initial neutral
reset response was extended to JSON and broker throttling: a second request must not disclose a registered address.
The added regression checks known/unknown/recently requested addresses and the equality of the two translation keys.

1. **Help copy still describes password recovery as unavailable until launch**, in
   `resources/views/oasis/hilfe.blade.php:35`; update it to the real flow if not already integrated.
2. **Global Pint failures:** ordered imports in `CancellationRequest`, `ContentReport`, `OrganisationRequest`,
   `WithdrawalRequest`; trailing blank line in `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php`.
3. **PHPStan outside scope:** `ConfirmationController.php:27` view-string type, `SideBar.php:9` missing return type,
   missing Builder generics in `PrunedAfterRecordRetention.php:22` (four model contexts) and `WaitlistEntry.php:34`.

The shared working tree changed during the checks as other nodes worked. The ordinary test run collided with another
node's migration of the shared `testing` database; `--parallel --processes=1` used Laravel's isolated test database.
Counts describe the stated actual runs, not a guarantee about subsequent edits.

## Existing dependency audit findings

The five affected packages have the same versions in HEAD and the resulting lockfile: `guzzlehttp/guzzle` 7.12.3
(6 advisories), `laravel/framework` 13.17.0 (1), `league/commonmark` 2.8.2 (12), `league/flysystem` 3.35.1 (1),
`livewire/livewire` 4.3.3 (1). Composer's installation already reported these 21 findings. No unrelated dependency
upgrade was performed. Re-run `sail composer audit --locked` to obtain the full advisory links; resolve through an
approved dependency-maintenance change.

## Scope limits

Trial expiration/reminders, subscriptions, payments, chat and social data remain later stages. Legacy public-form
submissions have no account foreign key and are not deleted by matching email. Existing starter accounts preserve
unknown age/consent/username as null. No browser/a11y verification was performed by this backend node.
