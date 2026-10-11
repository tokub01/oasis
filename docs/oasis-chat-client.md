# Oasis encrypted chat – browser client (stage 3)

The page `/app/nachrichten` (route `app.chat`) is the chat UI. It builds on the crypto core in `resources/js/chat/*.js`
and talks to the API described in [oasis-chat.md](oasis-chat.md). This document covers the browser side.

## Why one page

Unlocked keys may only live in the memory of the open tab ("nothing persisted on the device"). A normal page load
would wipe them and ask for the chat passphrase again, so the chat is a single page with client-side screens, routed
by URL hash:

| Hash | Screen | Module |
|---|---|---|
| – (not unlocked) | `setup` (no keys yet / new keys) or `unlock` | `ui/identity-screens.js` |
| `` / `#` | `list` – conversations | `ui/conversation-list.js` |
| `#neu` | `new` – start a 1:1 chat or group by exact username | `ui/new-conversation.js` |
| `#c/{id}` | `conversation` – read and write | `ui/conversation-view.js` |
| `#c/{id}/info` | `details` – lifetime, members, safety numbers | `ui/conversation-details.js` |
| (dialog) | report one message to moderation | `ui/report-dialog.js` |

Markup: `resources/views/app/chat/index.blade.php` and one partial per screen in `resources/views/app/chat/partials/`.
The Vite entry `resources/js/chat/app.js` builds the shared context and routes. Without JavaScript only a notice is
shown – encryption happens in the browser.

## Layers

| Module | Responsibility |
|---|---|
| `client/api.js` | one method per API route; CSRF on writes, `cache: 'no-store'`, `ApiError` with the status |
| `client/session.js` | in-memory vault: unlocked identity, conversation keys per version, verified public keys; `lock()` wipes |
| `client/clock.js` | server clock offset from `server_time`; signed expiry = server now + lifetime (API allows ±120 s) |
| `client/keyring.js` | identity setup/unlock, verify the member key directory, open grants, owner key distribution, safety numbers |
| `client/messages.js` | seal/open envelopes, draft limits, in-memory `Timeline` de-duplicated by signature |
| `client/realtime.js` | Laravel Echo + pusher-js on `private-conversation.{id}`: `.MessageSent`, `.KeyRotated` |
| `ui/*` | screens; German texts; `ui/errors.js` maps API/crypto errors to user texts |

## Security behaviour

- **Lock**: keys and plaintext are wiped on "Sperren", on `pagehide` (tab closed or left) and after 30 minutes without
  pointer or keyboard input. A page restored from the back-forward cache shows the unlock screen.
- **Passphrase**: separate from the login password, at least 12 characters, Argon2id with the production costs
  (MODERATE); it is never sent. "Passphrase vergessen" creates new keys: old messages become unreadable and other
  members see a key-change warning.
- **Published keys**: after unlocking, the server's published bundle must match the unlocked identity, otherwise the
  chat refuses to continue (`PublishedKeysMismatchError`).
- **Grants**: only grants signed by the conversation owner (`created_by`) and addressed to the caller for this
  conversation are accepted; others are ignored.
- **Messages**: every envelope is verified against the sender's verified signing key and the shown conversation;
  failures are shown as "ließ sich nicht prüfen" instead of text. Expired messages are dropped from memory every 15 s.
- **Key warnings**: a member with `key_changed_at`, a signing key that changes while the tab is open, or an invalid
  bundle produces a visible notice; the details screen shows the pairwise safety numbers to compare in person.
- **No HTML injection**: all names and message texts are set with `textContent`; `tests/js/chat/hygiene.test.mjs`
  checks that no chat source uses `innerHTML` & co., browser storage, cookies or the console.
- **Realtime** is optional: without `VITE_PUSHER_APP_KEY` the chat works with history only.

## Owner key distribution

When a conversation has `rotation_required` (new chat, member added/removed, someone's identity replaced), the
owner's open conversation view verifies all member bundles, creates a fresh key, grants it to every active member
and activates the version. Other members see "Der Chat bekommt einen neuen Schlüssel" until KeyRotated arrives.
If a member has no valid keys, distribution stops with a notice naming them.

## Consent-based reporting

"Melden" appears on readable messages of other members. The dialog explains that this one message goes unencrypted to
moderation with sender and times, and posts `{envelope, plaintext, reason}` to
`POST /app/chat/api/conversations/{id}/reports` (server side: see oasis-chat.md).

## Tests

- `tests/js/chat/client.test.mjs` – client layer with real libsodium and an in-memory API fake.
- `tests/js/chat/hygiene.test.mjs` – static checks over all chat sources.
- `tests/Feature/Chat/ChatPageTest.php` – page access, screens, navigation.

Run JS tests with `./vendor/bin/sail node --test tests/js/chat/*.test.mjs`.

## Known limits

- The chat bundle includes libsodium (≈ 660 kB, 225 kB gzip); it is only loaded on the chat page.
- Verified safety numbers are not remembered (nothing on the device, and the server must not vouch for them).
- No unread counts, typing or read receipts (by design in this stage).
