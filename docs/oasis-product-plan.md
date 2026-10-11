# Oasis – product plan (draft for approval)

From a public website to a real, ad-free social network with end-to-end encrypted chat. Built in stages; every stage
ends with working software, tests, documentation and a cross-review (Claude ↔ Codex, Antigravity for UX/a11y).
Nothing is pushed or deployed without approval.

## Decisions so far

| Topic | Decision |
|---|---|
| Stack | Laravel 13, Livewire 4 + Alpine, Tailwind 4, MySQL, Pest (existing project) |
| Realtime | Pusher Channels (cluster `eu`) via `pusher-js` / Laravel Echo; credentials only in `.env` |
| Chat confidentiality | True end-to-end encryption: only the participants can read messages – not Pusher, not the Oasis server, not the operator |
| Server storage | Chat messages only as ciphertext; no plaintext of messages anywhere on the server |
| Device storage | Nothing persistent on the device: no messages or keys in localStorage/IndexedDB/cache |
| Retention | Self-deleting messages (configurable lifetime per conversation) |

## Architecture of the encrypted chat

**Keys (per account)**
- On first chat setup the browser creates an identity key pair (X25519 for encryption, Ed25519 for signing) with
  libsodium (`libsodium-wrappers`, audited, WebAssembly).
- The private keys are encrypted on the device with a key derived from a separate **chat passphrase**
  (Argon2id via `crypto_pwhash`) and only this ciphertext is stored on the server. The passphrase never leaves the
  device. Consequence of "nothing on the device": after a reload the chat is unlocked again with the passphrase;
  the decrypted key lives only in memory of the open tab.
- Forgotten passphrase = old messages are unreadable (by design). An optional printable recovery key can be offered.

**Conversations and messages**
- Each conversation has a random symmetric key (XChaCha20-Poly1305). It is wrapped (sealed box) for every member's
  public key; the server stores only the wrapped copies.
- Messages are encrypted and signed in the browser; the server stores `{ciphertext, nonce, sender, sent_at,
  expires_at}` and relays them over a Pusher private channel. Pusher and the server only ever see ciphertext.
- Membership changes in group chats rotate the conversation key (removed members cannot read new messages).
- **Self-deleting**: `expires_at` per message; a scheduled job deletes expired rows; the client drops them from memory.
  Pusher does not store messages.
- **Key verification**: every conversation shows a safety number (fingerprint of both identity keys) so people can
  check in person that the server did not swap a key. A changed key is shown as a warning.

**Honest limits (must be stated in the privacy policy and marketing, UWG)**
- Metadata stays visible to the server: who chats with whom, when, message size.
- In a web app the browser loads the encryption code from the Oasis server; a compromised server could ship
  manipulated code. Mitigations: strict CSP, Subresource Integrity, reproducible builds, later native apps.
- Encrypted chats cannot be moderated by content. Abuse reports work by a participant forwarding the decrypted
  message to moderation (consent-based reporting).
- The crypto design needs an external security review before launch.

## Stages

| # | Stage | Content | Depends on |
|---|---|---|---|
| 0 | Accounts | Sign-up/sign-in (Laravel Fortify, own views), username, birth date (16+), email verification, password ≥ 10, 2FA optional, account deletion and data export (GDPR), connect Testen/Anmelden pages | – |
| 1 | Trial & membership state | 30-day trial, reminder 2 days before, automatic end, read-only after end, retention download; real cancellation/withdrawal linked to accounts | 0 |
| 2 | Core network | Profiles (public / members only), Tropfen (text, images via EU S3 storage), chronological feed with an end, follow, replies, "weitertragen", quiet reactions (counts only for the author), Oasen (spaces with hosts and house rules), group start with invitations | 0 |
| 3 | Encrypted chat | Key setup and unlock, 1:1 chats, group chats, self-deleting messages, safety numbers, Pusher channels and auth, consent-based reporting | 0 |
| 4 | Moderation & DSA | In-app reports for posts/profiles, moderation queue, statement of reasons (Art. 17), internal complaints (Art. 20), second reviewer, transparency data | 2 |
| 5 | Payments | Provider with SEPA, PayPal, card (e.g. Mollie or Stripe), checkout on, invoices, VAT, gift memberships, founding-seat counter, price guarantee | 1 |
| 6 | Admin & transparency | Admin area, real figures on the Transparenz page, member votes | 4, 5 |
| 7 | Launch readiness | Security review (incl. crypto), backups, monitoring, CSP/SRI, performance, legal texts reviewed, DPIA | all |

Proposed order: 0 → 3 (the chat is your priority, it only needs accounts) → 1 → 2 → 4 → 5 → 6 → 7.

## How each stage is built

1. Claude writes the stage brief (scope, data model, acceptance criteria) → you approve.
2. Codex builds backend parts, Claude builds UI/integration (or the other way round), in separate files.
3. Cross-review in both directions + independent tests by the other side; Antigravity reviews UX and accessibility;
   for the crypto stage an additional security-focused review panel.
4. Full test run in Sail, Pint, build; Claude commits in small conventional commits.
5. Docs updated (`docs/oasis-website.md`, this plan, privacy policy).

## Open questions

- Chat passphrase separate from the login password (more secure, recommended) or the same password?
- Message lifetime: fixed (e.g. 7 days) or selectable per conversation (1 day / 7 days / 30 days)?
- Image hosting: IONOS S3 or AWS S3 (eu-central-1)? Mail: Amazon SES (eu-central-1)?
- Payment provider preference?
