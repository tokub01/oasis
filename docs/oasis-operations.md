# Oasis operations: backups, health check, error mails

Everything runs inside the app without third-party services or extra packages. It only works if the **scheduler**
and a **queue worker** run on the server – the health check says so loudly when they do not.

## Server setup (once)

```sh
# Scheduler: deletion periods, Mollie sync, backups, health check, mails
* * * * * cd /path/to/oasis && php artisan schedule:run >> /dev/null 2>&1

# Queue worker under Supervisor or systemd (content checks, mails)
php artisan queue:work --tries=3 --max-time=3600
```

`.env` (see `.env.example`):

| Variable | Purpose |
|---|---|
| `OASIS_BACKUP_KEY` | key from `php artisan oasis:backup-key` – **also store it outside the server** (password manager); without it no backup can be read |
| `OASIS_BACKUP_DISK` | `backups` (separate EU bucket, recommended) or `local` (only for development – a backup on the same server does not survive the server) |
| `BACKUP_AWS_*` | bucket and write-only credentials for the `backups` disk; enable versioning and an object lock / lifecycle rule in the bucket |
| `OASIS_BACKUP_VERIFY_DATABASE` | a separate database name the restore test may create and drop (the database user needs `CREATE`/`DROP` for it) |
| `OASIS_HEALTH_TOKEN` | long random secret for `/gesundheit` |
| `OASIS_ERROR_MAILS` | `true` (default): mail unexpected errors in production |

Alerts go to `oasis.mail.operator`.

## Backups

- `oasis:backup` (daily 02:30): `DatabaseDump` writes a SQL dump in PHP (no `mysqldump` needed; rows of `cache`,
  `sessions` and the queue are skipped), `BackupCipher` compresses and encrypts it with libsodium secretstream
  (XChaCha20-Poly1305, own key, tamper-evident). Stored as `backups/oasis-<UTC time>.sql.gz.enc` plus a `.json`
  manifest (row counts, size, SHA-256 – no personal data). Backups older than `backup_keep_days` (14) are removed.
- `oasis:backup-verify` (Sundays 04:30): downloads the latest backup, checks the SHA-256, decrypts, restores it into
  `OASIS_BACKUP_VERIFY_DATABASE` over separate connections and compares every table's row count, then drops that
  database. Only a verified backup is a backup.
- Encrypted chat messages are in the database and therefore in the backup – still only as ciphertext.
- **Images** live on the media disk. On S3, enable bucket versioning (EU region) so deleted or overwritten images can
  be recovered; the local media disk must be part of the server backup.

### Restoring (a deliberate human step)

1. Put the app into maintenance mode: `php artisan down`.
2. `php artisan oasis:backup-decrypt oasis-20261017-023000 /tmp/restore.sql` (never overwrites an existing file).
3. Import into the (emptied) production database, e.g. `mysql oasis < /tmp/restore.sql`.
4. **Delete `/tmp/restore.sql`** – it holds personal data in plain text.
5. `php artisan up`, then check `/admin/system`.
6. Data changed after the backup is lost; record the incident in the breach register if personal data were affected.

## Health check

`App\Support\Operations\HealthCheck` checks: database, cache, media storage (write/read), free disk space (≥ 1 GB),
queue (no job waiting longer than 15 minutes, no failed jobs in 24 hours), scheduler heartbeat (≤ 5 minutes), Mollie
sync (≤ 30 minutes), backup (≤ 26 hours), restore test (≤ 8 days). Heartbeats are stored in `system_heartbeats`.

- **Uptime monitor:** point any EU uptime service at `https://<domain>/gesundheit` with the header
  `X-Health-Token: <OASIS_HEALTH_TOKEN>` (or `?token=`); it answers 200 or 503 with JSON. Without a valid token: 404.
  Laravel's plain `/up` stays available for "is the app up at all".
- **Alerts:** `oasis:health-check` (every 15 minutes) mails failing checks, each at most every 6 hours.
- **Admin page:** Admin → System (`/admin/system`) shows the checks, the latest backup and all heartbeats.

## Error mails

`App\Support\Operations\ErrorAlert` is registered in `bootstrap/app.php`. In production every unexpected (reported)
exception mails class, file:line and request path – never the message, input or user, which may contain personal
data – at most once per hour per place. Details stay in `storage/logs`; keep log retention at
`oasis.privacy.server_log_days` (e.g. `LOG_CHANNEL=daily` with `LOG_DAILY_DAYS=7`).

## Mail deliverability

Without these, Gmail, Yahoo and others put Oasis mails into spam or reject them (requirements since 2024):

| DNS record (domain of `MAIL_FROM_ADDRESS`) | Content |
|---|---|
| SPF – TXT on the domain | `v=spf1 include:<provider's SPF host> -all` – the provider (IONOS or Amazon SES) names its include |
| DKIM – TXT/CNAME the provider gives | switch on DKIM signing in the provider's dashboard and publish its key records |
| DMARC – TXT on `_dmarc.<domain>` | start with `v=DMARC1; p=none; rua=mailto:<operator inbox>`; after two weeks of clean reports `p=quarantine`, later `p=reject` |

- `MAIL_FROM_ADDRESS` on the same domain as SPF/DKIM (e.g. `hallo@<domain>`), `MAIL_FROM_NAME="Oasis"`.
- Mails people subscribed to carry `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click`
  (`App\Support\Mail\OneClickUnsubscribe`): the daily summary (`notifications.digest.one-click`) and the waitlist
  (`oasis.waitlist.one-click`). The URLs are signed and accept a POST without login or CSRF token; the links in the
  mail open a confirmation page first, so mail scanners change nothing.
- Check with a test mail to a Gmail address ("Original anzeigen": SPF, DKIM, DMARC = PASS) or mail-tester.com.

## Local development

Sail runs no cron: `vendor/bin/sail artisan schedule:work` and `vendor/bin/sail artisan queue:work`. For the restore
test locally set `OASIS_BACKUP_VERIFY_DATABASE=testing_restore_check` (the Sail user may create `testing*` databases).

Tests: `tests/Feature/Operations/OperationsTest.php`.
