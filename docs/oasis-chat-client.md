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
| `client/realtime.js` | Laravel Echo (`broadcaster: 'reverb'`) + pusher-js on `private-conversation.{id}`: `.MessageSent`, `.KeyRotated` |
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
- **Realtime** is optional: without `VITE_REVERB_APP_KEY` the chat works with history only.

## Owner key distribution

When a conversation has `rotation_required` (new chat, member added/removed, someone's identity replaced), the
owner's open conversation view verifies all member bundles, creates a fresh key, grants it to every active member
and activates the version. Other members see "Der Chat bekommt einen neuen Schlüssel" until KeyRotated arrives.
If a member has no valid keys, distribution stops with a notice naming them.

## Consent-based reporting

"Melden" appears on readable messages of other members. The dialog explains that this one message goes unencrypted to
moderation with sender and times, and posts `{envelope, plaintext, reason}` to
`POST /app/chat/api/conversations/{id}/reports` (server side: see oasis-chat.md).

## Schutz im Chat

`client/safety.js` runs only in the browser, on decrypted text; nothing is sent unless the member reports:

- incoming messages (`scanIncoming`): requests for passwords, codes, card or bank data or the address (`phishing`),
  sexual offers or requests incl. coded ones (`solicitation`), and words from the server word list (insult, hate,
  threat, sexual, extremism). The newest flagged message from someone else shows a banner in the conversation:
  "Blockieren und Chat löschen" (`POST /app/chat/api/blocks`, then `POST …/conversations/{id}/leave`; a group owner
  cannot leave, the block alone stops the messages), "Nachricht melden" (consent-based report dialog) or
  "Ist in Ordnung" (dismissed in memory). Within a sender's first three messages the text calls it a typical warning
  sign.
- outgoing drafts (`scanOutgoing`): card numbers (Luhn), IBANs (mod 97), "mein Passwort ist …" and street/postcode
  addresses ask for a second press on "Senden"; slang addresses get the same tone hint as Tropfen (`toneHint`, same
  rule as `App\Support\Moderation\ToneHint`).
- The word lists come from `resources/moderation/wortliste.php` and `umgangston.php` (`data-safety` on the chat page).
- Automatic reporting of chat content is deliberately not built: it would break end-to-end encryption and the
  secrecy of communications (§ 3 TDDDG).

Tests: `tests/js/chat/safety.test.mjs`, `tests/Feature/Chat/ChatSafetyTest.php`.

## Tests

- `tests/js/chat/client.test.mjs` – client layer with real libsodium and an in-memory API fake.
- `tests/js/chat/hygiene.test.mjs` – static checks over all chat sources.
- `tests/Feature/Chat/ChatPageTest.php` – page access, screens, navigation.

Run JS tests with `./vendor/bin/sail node --test tests/js/chat/*.test.mjs`.

## Known limits

- The chat bundle includes libsodium (≈ 660 kB, 225 kB gzip); it is only loaded on the chat page.
- Verified safety numbers are not remembered (nothing on the device, and the server must not vouch for them).
- No unread counts, typing or read receipts (by design in this stage).

## Locking & `wire:navigate` Lifecycle (stage 9 & stage 12)

- `ConversationView::close()` (called on "Sperren", `pagehide`, `livewire:navigating`, idle lock and every screen change)
  also removes the rendered messages, notices, title and the unsent draft from the page; the report dialog forgets its quote.
- `ChatSession::generation` increases on every lock. Key distribution and opening grants capture it before their
  first request and may only keep a key if it is unchanged – a key arriving after locking is wiped
  (`rememberConversationKey` returns false). Results of history loads and realtime messages for a closed
  conversation are dropped (`ConversationView::isCurrent`).
- **SPA Navigation (`wire:navigate`)**: `bindChatNavigation()` in `resources/js/chat/app.js` starts the chat client both on
  initial load and on `document` `livewire:navigated` whenever `[data-oasis-chat]` is present (guarded against double-start via
  `data-oasis-chat-started="1"`). On `livewire:navigating` (leaving the chat page) and `pagehide`, the teardown callback locks
  `ChatSession`, closes the active conversation view, disconnects `Realtime` (`Realtime::disconnect()`), clears timers and
  removes event listeners so no WebSocket or timer leaks across `wire:navigate` transitions.
- Tests: `tests/js/chat/client.test.mjs` (stale generation, `Realtime::disconnect`), `tests/js/chat/view-lock.test.mjs`
  (page cleared and `wire:navigate` lifecycle).

### Navigation and pending cryptographic work

On `livewire:navigating`, the chat stops timers, removes window listeners, clears password inputs, closes the
conversation/report dialog, wipes its session and disconnects realtime. Repeated initialization on the same root
is ignored; returning creates a new session. Identity setup/unlock captures the session generation before awaiting
cryptographic work: a lock or navigation invalidates that generation, so late private keys are wiped instead of
reopening the abandoned session. Late announcements cannot start timers after teardown.

## Live E2E over Reverb

This opt-in test runs the actual `ChatApi`, `ChatSession`, keyring, message crypto, `Realtime`, Laravel Echo and
Pusher modules against the running local app. It is separate from the offline JS tests and needs no new packages.
Start Sail (including `reverb` and the database), then run from the WSL repository:

```sh
./vendor/bin/sail node --test tests/e2e/chat-reverb.e2e.mjs
```

The fixture helper requires `APP_ENV=local` and existing migrations; the test refuses a remote app URL. It creates
three unique, verified `chat-e2e-…@demo.oasis.tld` trial accounts, leaving the demo accounts unchanged. Mira and Jonas
set up and unlock identities using production Argon2id costs, create a direct conversation and exchange signed key
grants. The third account is also chat-ready but is refused private-channel authorization with HTTP 403.

Both clients subscribe through real `/broadcasting/auth` requests. Jonas must receive `.MessageSent` over Reverb
and verify/decrypt it **without any history request or polling**. A tampered signature must fail. The test inspects
the persisted ciphertext envelope and signed expiry, checks that API writes contain neither message plaintext nor
chat passphrase, and verifies that the nonmember receives no event during the observation window.

The Node adaptation supplies only a same-origin in-memory cookie jar and the XHR HTTP adapter for Pusher's channel
authorization. Pusher's installed Node WebSocket transport, Echo and the app's Realtime code stay real. Inside Sail,
`http://localhost` reaches the app; `reverb:8080` reaches the same Reverb service exposed as `localhost:8080` on the
host. Override `CHAT_E2E_REVERB_HOST` / `CHAT_E2E_REVERB_PORT` if the local Docker service uses a different address;
`CHAT_E2E_URL` may select a different loopback HTTP port.

`MessageSent` implements `ShouldBroadcastNow`; `ChatBroadcast::afterCommit` dispatches it synchronously after the DB
commit. No queue worker is needed or started, even when `QUEUE_CONNECTION=database`. The test asserts that contract
rather than silently passing through a history fallback.

Finally, all WebSocket clients disconnect, in-memory keys are wiped, and the fixture helper deletes only this run's
three accounts through `DeleteAccount`. It verifies that users, identities, conversations, memberships, messages,
grants and authenticated sessions are gone, including when an assertion or network request fails. Interrupting the
process forcibly can prevent `finally` cleanup; normal test failures still clean up. Output contains results, never
cookies, CSRF values, passwords, private keys or environment secrets. This exercises the client/transport protocol,
not browser DOM rendering or navigation.
