# Oasis search and topics (stage 8)

Members search people, oases and Tropfen; everyone can open a public `#Thema` page. Nothing is ranked by reach,
clicks or payment.

## Routes (`routes/search.php`)

| Route | Name | Who | What |
|---|---|---|---|
| `GET /app/suche?q=…` | `search.index` | verified members | people and oases (side column), Tropfen newest first |
| `GET /themen/{tag}` | `search.tags.show` | everyone | Tropfen with `#tag`, newest first; guests see public Tropfen only |

Both are limited by `throttle:search` (30 per minute per account, guests per IP; `NetworkServiceProvider`).
A term that is exactly one `#Thema` redirects to its topic page. Terms shorter than 2 characters show the empty
search page.

## Rules

- **People** (`App\Queries\Search\DirectorySearch::people`): verified accounts whose username or display name
  contains the term (`@` optional; a term of only `@` searches display names only), exact username first, then
  alphabetical; never people with a blockade in either direction (`User::blockedUserIds()`, excluded in SQL so the
  limit counts only visible results). At most 12. The page is members-only and members may open every profile.
- **Oases** (`DirectorySearch::oases`): name or description contains the term, alphabetical, at most 12 (members
  may open every oasis).
- **Tropfen** (`PostFeed::matching`): text contains the term, through the same pagination as every feed, so
  visibility, blockades and mutes apply in one place. LIKE wildcards (`%`, `_`, `\`) are escaped.
- **Topics** (`PostFeed::tagged`): `#` + 2–40 letters (incl. umlauts), digits or `_`, matched as a whole word,
  case-insensitive (`#garten` does not match `#gartenzaun`). Links in Tropfen come from `x-network.rich-text`.

## Scaling note

LIKE/REGEXP scan the `posts` table. Fine for the start; with large volumes add a MySQL FULLTEXT index (or a tags
table filled on publish) – the query contract and Livewire views can remain the same.

## Tests

`tests/Feature/Search/SearchTest.php`.

## Stage 12: reactive search

`App\Livewire\Search\Index` and `Tag` are class-based full-page components with unchanged route names and URIs.
Search input uses `wire:model.live.debounce.300ms` and `#[Url]` (`q`); changing the term resets the cursor.
`WithPagination` changes pages without a full navigation. Internal result links use `wire:navigate`.

Every reactive directory request rechecks the verified account, validates a maximum of 100 characters, and enforces
the 30/min search budget, including pagination and manual refreshes. Topic identifiers are `#[Locked]`; topic
refreshes retain visibility/block/mute filtering and their guest IP or account budget. Initial GETs retain the
existing `throttle:search` middleware. `DirectorySearch` owns the shared minimum length and topic pattern.
Tests in `LiveSearchTest.php` cover URL-driven live updates, locked topics, redirects and request budgets;
`SearchTest.php` retains directory, visibility, escaping and topic-matching regression coverage.

Long URL and live search terms share the same inline validation. Exhausted reactive search/topic budgets display an
inline message and skip result queries; retrying after the budget recovers clears that message. Initial GET route
throttling remains unchanged.
