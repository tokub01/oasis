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
table filled on publish) – the controller and views do not need to change.

## Tests

`tests/Feature/Search/SearchTest.php`.
