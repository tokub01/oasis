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
| `location_audience` | Alle · Mitglieder · Folge ich · Niemand | Mitglieder | location on the profile |
| `oases_audience` | Alle · Mitglieder · Folge ich · Niemand | Mitglieder | oasis list on the profile |
| `mentions_audience` | Mitglieder · Folge ich · Niemand | Mitglieder | mention hint and profile link |
| `replies_audience` | Mitglieder · Folge ich · Niemand | Mitglieder | `PostPolicy::reply` |
| `carry_audience` | Mitglieder · Folge ich · Niemand | Mitglieder | `PostPolicy::carry` |
| `chat_invites_audience` | Mitglieder · Folge ich · Niemand | Mitglieder | starting or joining chats |
| `searchable` | ja/nein | ja | Oasis people search |
| `search_engines` | ja/nein | nein | `noindex` on public profile and Tropfen pages |
| `login_alerts` | ja/nein | ja | `RecordLoginDevice` mails on a browser/system not seen before |
| `hidden_words` | up to 50 words (own page) | none | see "Further controls" |

Defaults follow privacy by default (GDPR Art. 25). The migration carried over existing profiles: a public profile
stayed public, its new Tropfen stayed public by default and it stayed visible to search engines until the member
changes it.

`location_audience` and `oases_audience` were added later with the members-only default, also for existing public
profiles: guests no longer see the location and the oasis list until the member opens them up.

## The rule

`$owner->privacy()->allows($setting, $viewer)`:

- the owner always passes;
- `everyone`: also guests; `members`: verified members; `following`: verified members the owner follows; `nobody`.
- "Karawane" (followers) is deliberately not an audience: anyone can follow without consent, so it would not protect.
- Blocks are not part of the rule and are checked separately – a block always wins.

## Unter 18

Members under 18 (by `users.birth_date`; Oasis is open from 16) get stricter limits until their 18th birthday:
nothing public (profile, Tropfen, Karawane size, location, oasis list), no search engines, chats only from people
they follow. `PrivacySetting::MINOR_CHOICES` lists the allowed values, `MINOR_DEFAULTS` the strict fallback.

- **Read:** `User::privacy()` applies `limitForMinor()` in memory, so every check (`allows`, `indexable`, the composer
  preselection) sees strict values even if looser ones are stored.
- **Write:** `PrivacyOptions::rule()`/`choices()` offer and accept only allowed values; a first row starts from
  `MINOR_DEFAULTS`, never from the looser column defaults. `PublishPost` stores every Tropfen of a minor members-only
  and the composer offers no public option.
- **Existing data:** migration `2026_10_17_700000_limit_privacy_of_minors` clamped stored rows and public Tropfen.
- On the 18th birthday nothing changes by itself; the member can then open things up. Tests:
  `tests/Feature/Privacy/MinorProtectionTest.php`.

## Further controls

Modelled on the controls large networks offer, limited to what Oasis actually does (no ads, no face recognition):

| Control | Page | Effect, enforced in |
|---|---|---|
| Frühere Tropfen begrenzen | center, card | all own public Tropfen become members-only (`LimitPastPosts`) |
| Verborgene Wörter | `/app/privatsphaere/woerter` (`app.privacy.words`) | Tropfen with a word disappear from the member's own lists – own Tropfen stay (`PostFeed::filterSafety`); replies with a word under the member's Tropfen are seen only by their writer (`PostFeed::withoutHiddenReplies`, `Post::isVisibleTo`); no hints with a word (`SendHint::allowed`) |
| Einschränken | ⋯ menu of a Tropfen, profile, `/app/sicherheit` | replies of the restricted person under the member's Tropfen are seen only by that person; no hints from them (`User::hidesReplyFrom`, `SendHint::allowed`). Table `restrictions`. |
| Geräte und Anmeldungen | `/app/privatsphaere/geraete` (`app.privacy.devices`) | lists the member's sessions (database session driver) with browser, system and a shortened IP; signs out one or all other devices and renews the remember-me token (`EndSessions`); lists known devices for login alerts |
| Wessen Bilder siehst du sofort? | setting `images_audience` (default: people the member follows; under 18 never "all members") | images of everyone else are covered in the Tropfen card and not even requested until "Bild anzeigen" is tapped (module `reveal-image`); own images always show, guests see public images (`User::showsImagesOf`, follow ids cached per request) |
| Anmelde-Warnung | setting `login_alerts` | `RecordLoginDevice` (on `Login`) stores browser and system in `known_devices` – no IP – and mails `LoginAlert` for a new one; the first device of an account is stored silently. Unused devices are pruned after a year. |

Words match case-insensitively anywhere in the text (MySQL collation for queries, `mb_strtolower` in PHP), like the
search. Whoever wrote a hidden reply keeps seeing it, so nobody learns that they were restricted or filtered. Staff
still open every Tropfen from a moderation case. Hidden words, restrictions and known devices are part of the data
export and are deleted with the account.

## Pieces

| Piece | Role |
|---|---|
| `App\Enums\Privacy\Audience` | the four audiences with German labels |
| `App\Support\Privacy\PrivacyOptions` | titles, explanations, choices and validation per setting; one source for both pages |
| `App\Actions\Privacy\UpdatePrivacySetting` | stores one setting, rejects unknown keys and values outside the choices |
| `App\Livewire\Privacy\Center`, `Checkup` | the two pages; shared saving in `Concerns\SavesPrivacySettings` |
| `App\Livewire\Privacy\HiddenWords`, `Devices` | hidden words; sessions and known devices |
| `App\Actions\Privacy\UpdateHiddenWords`, `LimitPastPosts`, `EndSessions` | the further controls |
| `App\Actions\Safety\ManageRestriction`, `App\Models\Restriction` | restricting accounts |
| `App\Listeners\Privacy\RecordLoginDevice`, `App\Models\KnownDevice`, `App\Mail\Privacy\LoginAlert`, `App\Support\Privacy\DeviceLabel` | login alerts |

Tests: `tests/Feature/Privacy` (pages, saving, refused values, the audience matrix, `PrivacyControlsTest` for the
further controls) plus the enforcement tests in the network, search and chat areas.

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
