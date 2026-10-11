# Oasis security and launch readiness (stage 7)

What is enforced in code, and what must be done outside the code before launch.

## In code

| Area | Measure | Where |
|---|---|---|
| Headers | CSP with per-request nonce (`script-src 'self' 'nonce-…' 'wasm-unsafe-eval'`, no `unsafe-eval`, `object-src 'none'`, `frame-ancestors 'none'`, `form-action` only self + Stripe Checkout/Portal, `connect-src` only self + Pusher), `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP, HSTS on HTTPS | `App\Http\Middleware\SecurityHeaders` (web group) |
| Scripts | Vite and Livewire tags carry the nonce; Livewire uses the CSP-safe Alpine build (`livewire.csp_safe`); no inline scripts or handlers in views; chat crypto, Echo and pusher-js run with code generation from strings disabled (checked with `node --disallow-code-generation-from-strings`) | `config/livewire.php`, `tests/Feature/SecurityHeadersTest.php` |
| Accounts | Fortify, password ≥ 10, 2FA (mandatory for staff), throttled login/registration/reset, neutral reset responses | docs/oasis-accounts.md |
| Chat | end-to-end encryption in the browser, server stores ciphertext only, production Argon2id costs enforced, signatures verified server-side, nothing persisted on the device | docs/oasis-chat.md, docs/oasis-chat-client.md |
| Network | visibility checked in policies (404 for unreadable content), images re-encoded without metadata and served after the visibility check, rate limit for posts | docs/oasis-network.md |
| Payments | Stripe hosted Checkout/Portal, signed webhooks, keys only in `.env` | docs/oasis-membership.md |
| Privacy | retention via `model:prune`, export and self-service deletion, secret ballots, statistics as sums only | docs/oasis-admin.md |
| Secrets | `.env` only (gitignored); `.env.example` has empty placeholders | – |

## Before launch (outside the code)

- [ ] External security review incl. the crypto design and the web-delivery risk of browser crypto (product plan).
- [ ] Rotate the Pusher secret that was shared in chat; restrict allowed origins in the Pusher dashboard; enable
      client event restrictions (none are used).
- [ ] Switch Stripe to live keys, set `STRIPE_WEBHOOK_SECRET` from the live endpoint, activate SEPA/PayPal in the
      dashboard, sign Stripe's DPA.
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
- [ ] 21 composer advisories in docs/oasis-stage0-backend-report.md – update after approval.
