# Oasis encrypted chat – stage 3 API contract

All endpoints below use web session authentication, verified email and CSRF on writes. Send
`Accept: application/json` and `Content-Type: application/json`. Responses have `Cache-Control: no-store, private`.
No chat data belongs in cookies, localStorage, sessionStorage, IndexedDB or service-worker caches; the existing
authentication session cookie contains no chat keys/messages. IDs in crypto objects and responses are strings.

## Routes

Prefix: `/app/chat/api`; name prefix: `chat.api.`. Error JSON is Laravel's `{"message":"…","errors":{…}}` for
422 validation; 401 unauthenticated, 403 unverified/non-member/non-owner, 404 missing resource, 409 stale
key version or rotation pending, 429 rate limit. All authenticated API/channel requests share 120/minute/account;
message writes additionally allow 30/minute/account, identity writes 5/minute/account, report writes 5/minute/account. Read-only accounts
(trial ended, no membership; `participate` middleware) get 403 on creating conversations, adding members and
sending; reading, key handling, leaving and reporting stay available.

| Method | URL after prefix | Route name suffix | Request | Response |
|---|---|---|---|---|
| GET | /identity | identity.show | none | 200 `{"data":Identity|null}` |
| PUT | /identity | identity.update | `{"locked_identity":Locked,"public_keys":Bundle}` | 200 `{"data":Identity}` |
| GET | /users?username=alice | users.index | exact username, 3–30 characters | 200 `{"data":[PublicUser]}` (empty if unknown/unverified/not chat-ready) |
| GET | /conversations | conversations.index | optional `page` | 200 Laravel paginator of Conversation, 30/page |
| POST | /conversations | conversations.store | `{"type":"direct"|"group","title":string|null,"member_ids":["2"],"lifetime_days":1|7|30}` | 201 `{"data":Conversation}` |
| GET | /conversations/{conversation} | conversations.show | none | 200 `{"data":Conversation}` |
| PATCH | /conversations/{conversation} | conversations.update | `{"lifetime_days":1|7|30,"title":string|null}`; owner only | 200 `{"data":Conversation}` |
| GET | /conversations/{conversation}/public-keys | keys.index | none | 200 `{"data":[PublicUser]}`; includes past members for verifying historical senders, no locked blobs |
| POST | /conversations/{conversation}/members | members.store | `{"user_id":"3"}`; group owner only | 200 `{"data":Conversation}` |
| DELETE | /conversations/{conversation}/members/{user} | members.destroy | none; group owner, cannot remove owner | 200 `{"data":Conversation}` |
| GET | /conversations/{conversation}/key-grants | grants.index | none | 200 `{"data":[Grant]}`; only caller's grants, all versions |
| POST | /conversations/{conversation}/key-grants | grants.store | `{"key_version":1,"grants":[Grant,…]}`; owner only, complete batch for every active member incl. owner | 200 `{"data":Conversation}` |
| POST | /conversations/{conversation}/rotate | rotation.store | `{"key_version":1}`; owner only | 200 `{"data":Conversation}`; activates current version once grants are complete |
| GET | /conversations/{conversation}/messages | messages.index | optional `before_id` positive integer | 200 `{"data":[Message,…],"next_before_id":string|null}`; 50 newest first |
| POST | /conversations/{conversation}/messages | messages.store | `{"envelope":Envelope}` | 201 `{"data":Message}` |
| POST | /conversations/{conversation}/reports | reports.store | `{"envelope":Envelope,"plaintext":string,"reason":string}` | 201 `{"data":ChatReport}` |
| POST | /broadcasting/auth (outside prefix) | broadcasting.auth | standard Echo `socket_id`, `channel_name` | standard Reverb (Pusher-protocol) auth JSON; same auth, verification and membership checks |

`Identity = {user_id,locked_identity,public_keys,key_changed_at}`. Initial setup has null key_changed_at;
relocking identical public keys changes only the encrypted blob. Replacing either public key records server time,
invalidates all grants addressed to that identity, and makes all its active conversations require rotation.
The locked blob's embedded public keys must match the published bundle. All nested objects reject extra fields;
plaintext, private-key and passphrase fields are never accepted or retained.

`PublicUser = {id,username,display_name,public_keys:Bundle|null,key_changed_at}`.
`Conversation = {id,type,title,created_by,lifetime_days,key_version,rotation_required,members:[Member],
server_time}`. `Member = {user_id,username,display_name,role,joined_at}`; active members only.
Creation includes the caller as owner; direct requires exactly one other member, group 1–49 other members.
All members must have verified email and a chat identity. Initial key_version = 1, rotation_required = true.
Title is metadata visible to the operator; do not put confidential chat content in it. Lifetime updates apply
only to new messages; existing signed expiries cannot be rewritten.

`Message = {id,envelope,created_at,expires_at}`. Only unexpired messages since the caller's current joined_at
are returned. `Envelope` is the exact object returned by `encryptMessage`:
`{v:1,conversation_id:string,key_version:integer,sender_id:string,nonce:base64url,ciphertext:base64url,
signature:base64url,expires_at:UTC-string}`. Nonce 24 bytes, signature 64 bytes, ciphertext 16–6000 bytes
(including AEAD tag). This transport limit leaves room below Reverb's default 10 KiB message limit: UI must limit UTF-8
plaintext to 5984 bytes in addition to the crypto core's character limit. Expiry is mandatory, whole seconds
`YYYY-MM-DDTHH:mm:ssZ`, within ±120 seconds of server_time + lifetime_days. Compute it using Conversation.server_time
and send promptly. Server preserves the signed instant exactly, stores it as expires_at, and never extends it.
The server validates shape, sender, conversation, current version and signature, but cannot inspect plaintext.

`Locked` is exactly `lockIdentity` output: `{version:1,kdf:{alg:"argon2id13",opslimit:3,memlimit:268435456,
salt:base64url},nonce:base64url,ciphertext:base64url,publicKeys:{encryption:base64url,signing:base64url}}`.
Salt 16 bytes, nonce 24 bytes, ciphertext 112 bytes, public keys 32 bytes each. Production KDF costs are required.
`Bundle = {encryption:base64url,signing:base64url,binding:base64url}` is `exportPublicKeys` output.
Binding is the 64-byte signature over the core's canonical key-binding fields and is verified server-side as well
as by `verifyPublicKeys` in the browser.

`Grant = {v:1,conversation_id:string,key_version:integer,recipient_id:string,granter_id:string,
wrapped_key:base64url,signature:base64url}` is `createKeyGrant` output.
Wrapped key 80 bytes, signature 64 bytes; all IDs/version must match the route, caller and active recipient.
Only the owner distributes keys in this stage. The server verifies the grant signature against the owner's
current signing key and recipient's current encryption key, and stores the encrypted grant unchanged.

`ChatReport = {id:string,conversation_id:string,created_at:UTC-string,status:"received"}` is the response
returned by `POST /conversations/{conversation}/reports`.

## Channels and events

Echo subscribes with `Echo.private('conversation.' + id)` via the `reverb` broadcaster; wire channel `private-conversation.{id}`.
Only active, verified members can authorize. Never use a public channel or a presence member list.
Event alias `.MessageSent`: the **envelope itself**, without model, user profile or plaintext.
No DB message ID is added to the signed envelope: deduplicate realtime/history by signature and reload history
to obtain database IDs. Event alias `.KeyRotated`: `{conversation_id:string,key_version:integer,
rotation_required:boolean}`. Events are dispatched after commit. Membership/identity changes also send
KeyRotated with rotation_required=true so open tabs lock sending and reload keys/membership.

A previously authorized WebSocket connection cannot be remotely revoked by Laravel's auth endpoint alone.
Removed members can still see subsequent ciphertext/metadata until they disconnect, but must never receive the new
key grant and cannot decrypt new messages. UI unsubscribes when its next authorized request fails. The server
never broadcasts grants or identity blobs.

## Rotation procedure

1. Creation starts pending at version 1. The owner fetches public keys, verifies bindings and compares safety numbers.
2. The owner's browser creates a random conversation key, makes one signed createKeyGrant for each active member
   (including itself), and POSTs the complete batch using the current version.
3. Owner POSTs /rotate with that version. Server transaction checks active recipients all have grants for the
   current version, then sets rotation_required=false and broadcasts KeyRotated. Only then messages can be sent.
4. Add/remove of a member atomically increments key_version and sets rotation_required=true. Removed members lose
   all API/channel authorization immediately. Old grants remain accessible only to their active recipients; a
   returning member's old grants are deleted. Sending and stale grant/activation attempts return 409.
5. Owner repeats steps 1–3 with a **new random key**, wrapping it only for the new active set. Readers fetch only
   their own grants, verify owner signature with openKeyGrant, and select keys by envelope.key_version.
6. An identity replacement triggers the same pending rotation in every active conversation. UI must visibly warn
   on key_changed_at and require renewed safety-number verification. Browser safety-number comparisons are the
   protection against a server swapping a whole identity bundle; the server cannot establish personal trust.

Mutations lock the conversation row, so membership changes, identity changes, grant writes, activation and sends
cannot publish a message in an obsolete version. Replacing the owner identity requires unlocking the new identity
before distributing a new key. Old messages may become unreadable after identity reset; there is no recovery of
lost private keys. Account deletion removes that account's owned conversations, ciphertext sent by it and grants,
and rotates surviving conversations where it was a member.

## Consent-based reporting

A conversation participant may forward a single decrypted message to moderation via
`POST /app/chat/api/conversations/{conversation}/reports` with payload `{envelope, plaintext, reason}`.

1. **Authorization**: Caller must be an active verified member of the target conversation (non-members receive 403).
2. **Server-side validation**: The submitted envelope must belong to the conversation (`envelope.conversation_id === conversation.id`),
   the sender must be a participant (current or historical) of the conversation, and the envelope signature must verify
   cryptographically against the sender's published signing key (`CryptoPayload::messageValid`). Tampered or foreign envelopes are rejected with 422.
3. **Plaintext handling & disclaimer**: Because the server never possesses conversation decryption keys, it cannot verify
   the reporter-supplied plaintext against the stored ciphertext. The plaintext is explicitly recorded with the permanent mark:
   `"reported by participant, not verifiable by the server"`, alongside verified envelope metadata (sender ID, conversation ID, message expiry).
4. **Moderation case creation**: Storing a report automatically creates a work item through `App\Actions\Moderation\OpenCase`
   using morph source alias `chat_report`, with `subject_user_id` set to the message sender. Staff members see only the
   reported plaintext, verification disclaimer, and reason; no other conversation history is exposed.
5. **Retention**: The report record uses `PrunedAfterRecordRetention` linked to `oasis.privacy.records_years`. The report row
   is retained while its moderation case remains open, and is pruned only after the case is closed and the retention period has elapsed.
6. **Rate limiting**: Report submissions are limited to 5 requests per minute per authenticated account (`throttle:chat-reports`).

## Benachrichtigungen (unread chats)

Metadata only – the server never needs the content:

- `conversation_members.last_read_at`: set by `POST /app/chat/api/conversations/{id}/read` (route `chat.api.read`,
  sent by the client when a chat is opened and when a message arrives while it is visible) and for the sender when
  they send. `App\Support\Chat\UnreadChats`: a chat is unread when it holds an unexpired message from someone else,
  not blocked, newer than `last_read_at` (or the join).
- In the app: `GET /app/chat/api/unread` (`chat.api.unread`) feeds the count at "Chats" in the app layout and the
  toast "Neue Chat-Nachricht" (module `chat-unread` in `resources/js/oasis.js`, every 30 s while the page is visible,
  no toast on the chat page). The conversation list marks unread chats with "Neu" (`unread` in the list API).
- E-mail/push: privacy settings `chat_email` and `chat_push`, **off by default**, explained in the Privatsphäre-Center
  ("benachrichtigungen"). `SendMessage` dispatches `NotifyChatRecipients` after commit with a 2-minute delay; it skips
  members who read the chat meanwhile, blocked senders, quiet hours and pauses, and chats already notified since the
  last read (`notified_at`). Mail `NewChatMessage` and the push say only "neue Chat-Nachricht" – no name, no text.

Tests: `tests/Feature/Chat/ChatNotificationsTest.php`.

## Retention and limits

Message uses MassPrunable on expires_at; the existing daily model:prune schedule deletes rows.
History queries and sends enforce expiry independently of the scheduler. Broadcasting is synchronous after commit;
transport failure is logged without request contents and does not lose the stored message; history is authoritative.
Account export remains profile-only in stage 0; encrypted chat history is available through the authorized API.
No attachments, voice, read receipts, typing, push or per-message forward secrecy. Whoever obtains a version's key
can decrypt retained ciphertext of that version. Metadata remains visible; web-delivered crypto and the design require
an external security review before launch.


## Stage 9: blocks in existing conversations

Sending is refused with 403 whenever the sender and any active conversation member have blocked each other.
This applies to existing direct and group conversations; no message is stored or broadcast. A block between two
other members does not prevent an uninvolved sender from writing. Departed members do not affect this send check.

History excludes blocked senders before the 50-message cursor page is selected, preserving full pages and valid
cursors. Exact-username chat discovery also hides blocked accounts in either direction. The historical public-key
directory remains available to active members for verification of old signatures. Blocking cannot erase ciphertext
or keys already obtained by a client.

## Invitation privacy (stage 13)

Creating a conversation and adding/readding a group member require every invitee's `chat_invites_audience` to allow
the inviting owner. The check runs server-side inside the existing membership transaction/locks and returns HTTP
403 with `Diese Person erlaubt dir keine Chat-Einladungen.` on refusal. Members-only defaults also apply without a
stored privacy row. `following` means the recipient follows the inviter, not the reverse. Existing membership,
message history and public-key verification remain available when invitation preferences change.

The exact-username picker omits accounts that reject the searcher's invitation, as well as blocked or non-chat-ready
accounts. `searchable` controls the Oasis people search; it does not override the separate chat invitation setting.
Bidirectional blocks continue to win above a permissive audience. See `tests/Feature/Chat/ChatPrivacyTest.php` and
the opt-in real Reverb test in `tests/e2e/chat-reverb.e2e.mjs` (instructions in `oasis-chat-client.md`).

## Nachrichtenanfragen

- `conversation_members.accepted_at`: null while a chat is a request for that member. Chats start accepted for the
  owner and for members who follow the owner or are friends with them (`ManageConversation::acceptingFrom`, also when
  added to a group); everyone else gets a request.
- Requests never count at "Chats", never trigger e-mail or push (`UnreadChats`, `NotifyChatRecipients` skip them);
  `GET /app/chat/api/unread` reports them separately as `requests`. Read state is never shown to anyone.
- Accept: `POST …/conversations/{id}/accept`, or simply reply (`SendMessage`). Decline: leave the chat
  (`…/leave`), optionally block. In a direct chat the sender may send at most `SendMessage::REQUEST_MESSAGE_LIMIT` (3)
  messages until the other person accepts (429 afterwards).
- The chat list shows requests in their own section; the conversation shows a request banner. The list also offers
  "Wer darf dir schreiben?" (`chat_invites_audience`, incl. "Freund:innen und Menschen, denen du folgst") and
  "Freundschaftsanfragen erhalten" via `GET/PATCH /app/chat/api/settings` (`UpdatePrivacySetting`).

Tests: `tests/Feature/Chat/MessageRequestsTest.php`.

## Schreiben aus Oasen und Profilen

`App\Support\Chat\ChatReach` decides where a "Schreiben" button appears: the other person has set up the chat, nobody
blocked anybody, their "Wer darf dir schreiben?" (`chat_invites_audience`) allows the writer, and the writer may
participate. Oasis pages show members of the oasis a member list with a name filter (`OasisShow::$memberQuery`,
12 at a time) and the button; profiles show it next to "Folgen". The button opens `/app/nachrichten#neu/{username}`,
which preselects the person in the new-chat screen (`ui/route.js`, `NewConversation#open`). The chat checks
everything again when the chat is created.

Tests: `tests/Feature/Chat/ChatReachTest.php`.
