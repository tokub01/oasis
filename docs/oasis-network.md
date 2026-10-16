# Oasis core network (stage 2)

Profiles, Tropfen, the Quelle, following, quiet reactions, oases, invitation links and the member/host statistics.
Everything renders server-side and works without JavaScript (plain forms and links).

## Data model (`database/migrations/2026_10_12_100000_create_network_tables.php`)

| Table | Model | Purpose |
|---|---|---|
| `profiles` | `Profile` | bio, location, visibility (`public`/`members`, default members), avatar colour; one per account, created on first save (`User::profileOrDefault()`) |
| `posts` | `Post` | a Tropfen: body ≤ 500, visibility, optional `oasis_id`, `reply_to_id`, `carried_post_id` (weitertragen), `removed_from_oasis_at` |
| `post_images` | `PostImage` | up to 4 per Tropfen, required alt text, WebP on the private media disk (`oasis.media_disk`: `local` or `s3`) |
| `follows` | `Follow` | follower → person or oasis (`followable` morph, class names) |
| `reactions` | `Reaction` | one quiet reaction per member and Tropfen |
| `oases`, `oasis_members` | `Oasis` | themed space with slug, description, house rules, visibility; members with role `host`/`member` |
| `invitations` | `Invitation` | personal links, `oasis.invites_per_month` per calendar month, valid 30 days |

Relations of an account live in `App\Models\Concerns\HasNetwork` (used by `User`). Deleting an account cascades to
its profile, Tropfen, images (rows), follows, reactions, memberships and invitations; replies/carries of deleted
Tropfen keep their (dangling) reference, stay replies/carries and show "nicht mehr verfügbar". Image files of deleted Tropfen are removed by `DeletePost`.

## Rules

- **Visibility** (`Post::scopeVisibleTo` / `isVisibleTo`, `Profile`/`Oasis::isVisibleTo`): public content is
  readable by guests, members-only content by verified members. Unknown and unreadable resources are both 404.
  A Tropfen in a members-only oasis, and a reply to or carry of a members-only Tropfen, is always members-only.
- **Participation**: every write (post, reply, carry, react, follow, found an oasis, create an invitation) needs the
  stage 1 gate `participate` (`User::canParticipate()`, see oasis-membership.md). Read-only accounts can still
  read, delete their Tropfen, take back reactions and unfollow.
- **Quelle** (`PostFeed::source`): own Tropfen and those of followed people and oases, newest first, cursor pages of
  20, replies only on the Tropfen page. No ranking, no recommendations; the last page says
  "Du bist auf dem neuesten Stand."
- **Leise Zahlen**: reaction and reply counts are shown only to the author (`PostPolicy::viewStats`); nobody sees
  who reacted. The Karawane (follower count) is shown on profiles.
- **Oases**: following an oasis = membership (posting allowed). The founder becomes host; hosts edit texts,
  appoint/demote hosts and remove Tropfen that break the house rules (`removed_from_oasis_at`: hidden for everyone
  but the author). The last host can neither step down nor leave.
- **Images** (`StorePostImage`): decoded and re-encoded as WebP with GD (drops EXIF/GPS and trailing data), longest
  edge ≤ 2048 px, served by `PostImageController` after the Tropfen's visibility check; members-only images are
  `Cache-Control: private`.
- **Reports**: "Melden" on a Tropfen opens the existing Art. 16 DSA form prefilled with its URL.
- **Invitations**: `/einladung/{token}` stores the token in the session; on `Registered` the new account and the
  inviter follow each other (`NetworkServiceProvider`). Group trials ("Gemeinsam starten") belong to stage 1.
- **Rate limit**: `throttle:network-posts`, 10 Tropfen per minute and account.

## Routes (`routes/network.php`, names `network.*`)

Guests: `/tropfen/{id}`, `/tropfen/{id}/bild/{image}`, `/oasen/{slug}`, `/einladung/{token}`, `/profil/{handle}`
(route `oasis.profil`; real members first, sample profiles of the guest preview as fallback while
`oasis.demo_pages` is on). Members: `/app` (Quelle, `app.home`), `/app/entdecken`, `/app/oasen`, `/app/oasen/neu`,
`/oasen/{slug}/bearbeiten|statistik`, `/app/profil`, `/app/statistik`, `/app/einladen`, plus the POST/PUT/DELETE
actions for Tropfen, reactions, follows, oases and roles.

## Statistics

| Module | Who sees it | Source |
|---|---|---|
| Deine Zahlen (`/app/statistik`) | the member only | `App\Queries\Network\MemberStats`: totals (Tropfen, reactions/replies/carries received, Karawane, following), 12 calendar weeks, top Tropfen of 30 days |
| Oase · Statistik (`/oasen/{slug}/statistik`) | hosts of that oasis | `App\Queries\Network\OasisStats`: members, hosts, Tropfen (total/30 days), active authors, removed Tropfen, 12 weeks of joins and Tropfen |

Only counts, never lists of people. Weekly grouping happens in PHP by ISO week so it works on every database.
Charts are tables with CSS bars (`x-network.week-chart`), readable by screen readers.

## Views and components

`resources/views/app/home.blade.php` (Quelle), `resources/views/app/network/**`, components `x-network.post-card`,
`x-network.compose`, `x-network.feed`, `x-network.avatar`, `x-network.stat`, `x-network.week-chart`.
`app/network/frame.blade.php` renders guest-readable pages in the app shell for members and in the website layout
(with a sign-up hint) for guests.

## Tests

`tests/Feature/Network/*` with builders in `tests/Support/NetworkFixtures.php`.

## Open

- Image storage: S3 bucket `get-oasis.com` (eu-central-1, Block Public Access on) via `OASIS_MEDIA_DISK=s3`; prefixes
  `network/posts/{id}/` for images and `backups/database/` for backups. Images are streamed through the app.
- Optional recommendations ("nur wenn du sie einschaltest") are not built; the feed is chronological only.

## Stage 9 changes

- **Members-only switch**: when hosts switch an oasis to members-only, all its Tropfen (incl. replies) become
  members-only in the same transaction (`ManageOasis::update`); switching back does not publish them again.
  Already downloaded public content cannot be recalled; public image responses were cacheable for one day.
- **Closing an oasis**: hosts close it via a confirmation page (`/oasen/{slug}/schliessen`, `DELETE /oasen/{slug}`);
  Tropfen stay with their authors without an oasis, memberships and follows are removed.
- **Account deletion** (`App\Actions\Account\DeleteAccount`): oases where the account is the only host get the
  longest member as host or are closed when empty; uploaded images are removed from the media disk after the commit.
- **Reporting**: "Melden" on foreign profiles and oases as well; the report form is prefilled for members.

## Stage 10: read before carrying

- **External links**: `x-network.rich-text` makes valid HTTP/HTTPS URLs clickable, showing the destination host.
  Text, URL attributes and titles are escaped; external links use `target="_blank"` and
  `rel="noopener noreferrer nofollow"`. Other schemes, credentials and backslash URLs remain plain text.
  Sentence punctuation stays outside links; balanced parentheses in URL paths are preserved.
  URL ranges take precedence over mention/topic tokens, so paths and fragments are not split into internal links.
- **Read before carrying**: when the original Tropfen contains such a link, `network.posts.carry` shows
  “Hast du den Link gelesen? Weitertragen heißt: Du stehst dafür ein.”, the destinations and the required
  “Ich habe den Inhalt gelesen” checkbox. Its HTML `form` attribute associates it with the compose form without JS.
  `PostRequest` validates `link_read` as accepted against the original Tropfen's body, including direct POSTs.
  New posts, replies and carries without external links need no confirmation. Acceptance is a submission check,
  not evidence that a person visited or read a page, and is not stored as a separate tracking record.
- **No previews or tracking**: `App\Support\Network\TextLinks` only parses text; it never resolves or fetches URLs.
  The check does not assess the truth or trustworthiness of linked content.
- **Tests**: `tests/Feature/Network/ReadBeforeCarryTest.php` covers validation, accessible form association,
  error/old-input handling, dangerous input, URL/token overlap and punctuation; outbound HTTP is prohibited.
