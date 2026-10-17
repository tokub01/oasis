# Oasis als App (PWA) und Push-Hinweise

Oasis is an installable web app (Progressive Web App) for Android, iPhone/iPad and desktop browsers. There are no
store apps; `config('oasis.apps')` stays `null` so the site never announces them (UWG).

## Installierbar

- `public/manifest.webmanifest`: name, colours, `start_url` `/app`, icons (`public/icons/`, incl. a maskable icon) and
  shortcuts to Quelle, Hinweise and Nachrichten.
- `resources/views/layouts/partials/pwa.blade.php` is included in all three layouts: manifest link, theme colour,
  apple-touch-icon and the iOS web-app meta tags.
- `public/sw.js` (service worker, scope `/`, registered in `resources/js/oasis.js`):
  - pages always come from the network and are never cached (they are personal); without a network the cached
    `/offline` page (route `oasis.offline`, `resources/views/oasis/offline.blade.php`) is shown;
  - `/build/`, `/fonts/` and `/icons/` are cached on first use (content-hashed or static);
  - bump `VERSION` when the offline page or the caching rules change, old caches are then removed.
- `x-oasis.install-app` (Hinweise einstellen) shows the install button where the browser offers one
  (`beforeinstallprompt`), the steps for iPhone/iPad, or "läuft schon als App".

## App-Hülle auf Handys

- `viewport-fit=cover` in all layouts; the app header, bottom bar and content use `env(safe-area-inset-*)` (notch,
  home indicator, landscape sides). The theme colour follows the top bar (`$themeColor` of the PWA partial).
- Phones: sticky header with search, a hints bell (unread dot) and "Mehr"; below 380px only the logo sign. The bottom
  bar uses short labels where needed (`Chats`).
- Installed app only (`@media (display-mode: standalone)`, class `.o-standalone-only`): a back button below the main
  sections (module `back`), since an installed iPhone app has no browser back button.
- Module `offline`: a status line while the device has no connection.
- New-Tropfen composer below 640px (`.o-compose`): only the text field until it is focused or filled, so the feed is
  visible on the first screen.

## Push-Hinweise

Implemented without a package on PHP's OpenSSL (`App\Support\Notifications\WebPush`):

- VAPID (RFC 8292): ES256 JWT for the push service origin, header `Authorization: vapid t=…, k=…`.
- Encryption (RFC 8291, `aes128gcm`): ephemeral P-256 key, ECDH with the browser key, HKDF with the browser's auth
  secret, one AES-128-GCM record. The push service only sees ciphertext.
- Keys: `php artisan oasis:push-keys` prints `OASIS_VAPID_PUBLIC` and `OASIS_VAPID_PRIVATE` for the `.env`. Without
  both keys push is off everywhere (no button, nothing sent). Keep the pair: a new one invalidates every subscription.
- Endpoints are restricted to the known push services (`WebPush::ALLOWED_HOSTS`, https only), because the URL comes
  from the browser and the server posts to it (SSRF).

Flow:

1. The `push` module (Hinweise einstellen) asks for permission, subscribes with the VAPID public key and posts the
   subscription to `notifications.push.store` (`push_subscriptions`, one row per browser, device label from
   `DeviceLabel`). "Ausschalten" posts to `notifications.push.destroy` and unsubscribes. The device list there can
   remove any of the own browsers.
2. `SendHint` dispatches `SendPushHint` after the hint is committed. The job skips members without subscriptions,
   during quiet hours and pause (`NotificationPreference::quietFor`) and within `COOLDOWN_SECONDS` (5 minutes) of the
   last push.
3. The message is always `{"title":"Oasis","body":"Du hast einen neuen Hinweis.","url":"/app/hinweise"}`: no names,
   no texts. The service worker shows it and opens `/app/hinweise` on click.
4. A push service answering 404/410 means the subscription is gone; the row is deleted.

Privacy: Datenschutz section 8 (push service of the browser vendor, encrypted content, consent by switching on);
export field `push_geraete`; rows are deleted with the account (foreign key cascade).

iOS: push works from iOS/iPadOS 16.4 when Oasis was added to the home screen and opened from there.

## Prüfung

```sh
docker compose exec -T -e DB_DATABASE=testing_codex laravel.test php artisan test tests/Feature/Notifications/PushTest.php tests/Feature/PwaTest.php
```
