# Oasis privacy settings (stage 13)

Members decide who sees them and who may contact them, in the Privatsphäre-Center (`/app/privatsphaere`, route
`app.privacy`) or step by step in the Privatsphäre-Check (`/app/privatsphaere/check`, route `app.privacy.check`).
Every change is saved at once; the server enforces every setting, the pages only change the values.

## Model

`App\Models\PrivacySetting` (table `privacy_settings`, one row per account). Accounts without a row use the defaults
(`$user->privacy()` returns an unsaved instance), so opening a page never writes.

| Setting | Choices | Default | Enforced in |
|---|---|---|---|
| `profile_audience` | Alle · Mitglieder · Menschen, denen du folgst | Mitglieder | profile page, Entdecken |
| `default_post_visibility` | Öffentlich · Nur Mitglieder | Nur Mitglieder | composer preselection |
| `follower_count_audience` | Alle · Mitglieder · Folge ich · Niemand | Mitglieder | Karawane size on the profile |
| `mentions_audience` | Mitglieder · Folge ich · Niemand | Mitglieder | mention hint and profile link |
| `replies_audience` | Mitglieder · Folge ich · Niemand | Mitglieder | `PostPolicy::reply` |
| `carry_audience` | Mitglieder · Folge ich · Niemand | Mitglieder | `PostPolicy::carry` |
| `chat_invites_audience` | Mitglieder · Folge ich · Niemand | Mitglieder | starting or joining chats |
| `searchable` | ja/nein | ja | Oasis people search |
| `search_engines` | ja/nein | nein | `noindex` on public profile and Tropfen pages |

Defaults follow privacy by default (GDPR Art. 25). The migration carried over existing profiles: a public profile
stayed public, its new Tropfen stayed public by default and it stayed visible to search engines until the member
changes it.

## The rule

`$owner->privacy()->allows($setting, $viewer)`:

- the owner always passes;
- `everyone`: also guests; `members`: verified members; `following`: verified members the owner follows; `nobody`.
- "Karawane" (followers) is deliberately not an audience: anyone can follow without consent, so it would not protect.
- Blocks are not part of the rule and are checked separately – a block always wins.

## Pieces

| Piece | Role |
|---|---|
| `App\Enums\Privacy\Audience` | the four audiences with German labels |
| `App\Support\Privacy\PrivacyOptions` | titles, explanations, choices and validation per setting; one source for both pages |
| `App\Actions\Privacy\UpdatePrivacySetting` | stores one setting, rejects unknown keys and values outside the choices |
| `App\Livewire\Privacy\Center`, `Checkup` | the two pages; shared saving in `Concerns\SavesPrivacySettings` |

Tests: `tests/Feature/Privacy` (pages, saving, refused values, the audience matrix) plus the enforcement tests in the
network, search and chat areas.

## Live E2E against the running app

This opt-in test drives the running local app over real HTTP, like the chat E2E (`docs/oasis-chat-client.md`). It is
separate from the offline test globs and needs no new packages. Start Sail (app, database; Reverb is not needed), then
run from the WSL repository:

```sh
./vendor/bin/sail node --test tests/e2e/privacy.e2e.mjs
```

`tests/e2e/support/privacy-fixtures.php` requires `APP_ENV=local`; `tests/e2e/support/browser.mjs` refuses a
non-loopback URL (`E2E_URL` may select another local port). The fixture creates three verified
`privacy-e2e-<run>-…@demo.oasis.tld` trial accounts: the **owner**, a person the owner **follows**, and a **stranger**
who follows the owner (the Karawane is no audience, so this must not help). A **guest** without login completes the
set. Follows and the block are made through the real Livewire actions of the profile page.

For every setting and every offered audience the owner saves the value through a real Livewire request of
`privacy.center` (`settings.<key>`, exactly as `wire:model.live` sends it) and the test checks the stored row, then
the effect: profile 200/404 and Entdecken, composer preselection and the stored visibility, the Karawane number,
mention hint (database and `/app/hinweise`) and profile link, reply/carry buttons, pages and `publish` (denied viewers
replay a composer snapshot taken while it was still allowed and must get 403), chat user search, direct chat and adding
to a group, people search, and `noindex` on the guest profile and Tropfen pages for all six `search_engines` ×
`profile_audience` combinations. It also checks that a block wins over open settings and that refused values, replacing
the whole `settings` array, unknown keys and the locked `status` property change nothing. A separate subtest checks the
public topic page `/themen/{tag}` for the same `search_engines` rule.

Each check is printed as `OK`/`FEHLER` with expected and actual value; a setting's subtest fails if any of its checks
fails, the other settings still run. Finally the fixture removes the run's content checks and pending queue jobs and
deletes the three accounts through `DeleteAccount`, then verifies that users, privacy rows, Tropfen, hints, follows,
blocks, chat identities, conversations, memberships and sessions are gone and that counts and digests of all other
accounts (demo data) are unchanged — also when an assertion fails. Interrupting the process forcibly can skip this
cleanup. Output contains no cookies, CSRF values or passwords. The moderation language model is called for each
published test Tropfen when it is configured.
