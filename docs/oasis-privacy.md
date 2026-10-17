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
