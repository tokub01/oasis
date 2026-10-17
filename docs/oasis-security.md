# Oasis security and launch readiness (stage 7)

What is enforced in code, and what must be done outside the code before launch.

## In code

| Area | Measure | Where |
|---|---|---|
| Headers | CSP with per-request nonce (`script-src 'self' 'nonce-…' 'wasm-unsafe-eval'`, no `unsafe-eval`, `object-src 'none'`, `frame-ancestors 'none'`, `form-action` only self + Mollie Checkout, `connect-src` only self + our Reverb WebSocket origin), `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP, HSTS on HTTPS | `App\Http\Middleware\SecurityHeaders` (web group) |
| Scripts | Vite and Livewire tags carry the nonce; Livewire uses the CSP-safe Alpine build (`livewire.csp_safe`); no inline scripts or handlers in views; chat crypto, Echo and pusher-js run with code generation from strings disabled (checked with `node --disallow-code-generation-from-strings`) | `config/livewire.php`, `tests/Feature/SecurityHeadersTest.php` |
| Accounts | Fortify, password ≥ 10, 2FA (mandatory for staff), throttled login/registration/reset, neutral reset responses | docs/oasis-accounts.md |
| Chat | end-to-end encryption in the browser, server stores ciphertext only, production Argon2id costs enforced, signatures verified server-side, nothing persisted on the device | docs/oasis-chat.md, docs/oasis-chat-client.md |
| Network | visibility checked in policies (404 for unreadable content), images re-encoded without metadata and served after the visibility check, rate limit for posts | docs/oasis-network.md |
| Payments | Mollie hosted checkout, API-verified payment webhooks, keys only in `.env` | docs/oasis-membership.md |
| Privacy | retention via `model:prune`, export and self-service deletion, secret ballots, statistics as sums only | docs/oasis-admin.md |
| Secrets | `.env` only (gitignored); `.env.example` has empty placeholders | – |
| Disclosure | `/.well-known/security.txt` (RFC 9116, generated, `Expires` always 180 days ahead, contact `oasis.mail.security`) and the section "Sicherheitslücke gefunden?" on `/sicherheit` (safe harbour for good-faith research) | `App\Http\Controllers\Oasis\SecurityTxtController` |
| Dependencies | `composer audit` and `npm audit --audit-level=moderate` on every push, together with Pint, PHPStan, the build and all PHP and JS tests | `.github/workflows/ci.yml` |

## Before launch (outside the code)

- [ ] External security review incl. the crypto design and the web-delivery risk of browser crypto (product plan).
- [ ] Run the self-hosted Laravel Reverb daemon under Supervisor/systemd behind TLS (`REVERB_SCHEME=https`), set production `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET`, and restrict `allowed_origins` in `config/reverb.php` to `get-oasis.com` (client events are disabled via `accept_client_events_from => 'none'`).
- [ ] Switch Mollie to a live key, configure the public HTTPS `/mollie/webhook` endpoint, activate the required
      payment methods in the dashboard and complete Mollie's data-processing arrangements.
- [ ] Hosting: HTTPS only (HSTS active), `APP_DEBUG=false`, `APP_ENV=production`, queue worker and scheduler
      (`model:prune`, trial reminders) running, `php artisan config:cache route:cache view:cache`.
- [ ] Backups: daily encrypted database backups off-site (EU), tested restore; image storage included.
- [ ] Image storage: move from the local private disk to EU object storage (IONOS or AWS eu-central-1) – decision open.
- [ ] Mail provider (planned Amazon SES eu-central-1) with SPF/DKIM/DMARC; fill `oasis.privacy.mail_provider`.
- [ ] Monitoring: uptime check on `/up`, error tracking without personal data, log retention (`oasis.privacy.server_log_days`).
- [ ] Moderation audit trail as database triggers (needs SUPER/trigger rights on the production database).
- [ ] Legal: lawyer review of Impressum, Datenschutz, AGB, Widerruf; DPIA for the chat and moderation; fill the
      open retention periods in `config/oasis.php` (`[noch festzulegen]`).
- [ ] Subresource Integrity is not used: all scripts are same-origin and nonce-bound; revisit if assets move to a CDN.
- [x] Composer and npm advisories: none open (17 October 2026); checked on every push by CI.
- [ ] Rotate the Mistral API key (it appeared in a chat) and set `OASIS_MAIL_SECURITY` to a dedicated inbox.
