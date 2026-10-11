# Oasis website – how it is built

The public website of get-oasis.com: marketing pages, legal pages and five real forms. The future social network
app is **not** part of this; it will live next to these routes. The binding design and brief are in
`docs/oasis-design/` (`PROMPT.md`, templates in `design/*.dc.html`, open values in `PLATZHALTER.md`).

## Where things are

| What | Where | Notes |
|---|---|---|
| Routes | `routes/oasis.php` (included from `routes/web.php`) | All names start with `oasis.` |
| Settings, prices, placeholders, flags | `config/oasis.php` | Views never hard-code these; every key is documented there |
| Layout | `resources/views/layouts/oasis.blade.php` | `<head>`, SEO/OG tags, skip link, header, `<main>`, footer |
| Pages | `resources/views/oasis/*.blade.php` | One file per page; header comment names route and template |
| Legal pages | `resources/views/oasis/legal/` | Share `_frame.blade.php` (sidebar with `aria-current`) |
| Confirmation pages | `resources/views/oasis/bestaetigung/` | Shown after a form was stored |
| Mails | `app/Mail/Oasis/` + `resources/views/oasis/mail/` | German, Berlin time |
| UI components | `resources/views/components/oasis/` | `header`, `footer`, `logo`, `icon`, `faq-item`, `post`, `price-card`, `check-list` |
| Design tokens + component classes | `resources/css/oasis.css` | Tailwind v4 `@theme` colors (`bg-night`, `text-sun-text` …) and `.o-*` classes |
| Fonts | `public/fonts/` | Fraunces + Manrope, self-hosted (no Google Fonts, see PROMPT.md) |
| JavaScript | `resources/js/oasis.js` | Small modules, see below |
| Formatting helpers | `app/Support/OasisFormat.php` | German euro amounts (`5 €`, `7,25 €`, `5,00 €`) |
| Sample data | `app/Support/DemoProfiles.php` | Guest preview profiles until real accounts exist |

## Request flow

- **Static pages** (`Route::view`): the view reads `config('oasis.…')` directly.
- **Flagged pages** (`App\Http\Controllers\Oasis\PageController`): checkout (404 while
  `checkout_enabled` is false) and the guest preview (404 while `demo_pages` is false).
- **Forms**: `GET` shows the page, `POST` goes to a controller in `app/Http/Controllers/Oasis/`:
  1. A Form Request in `app/Http/Requests/Oasis/` validates (German messages, honeypot field `website`).
  2. The controller stores the submission (one table per form) and sends the mails.
  3. It redirects to `/bestaetigung/<form>` with the flash key `oasis.submitted.<form>`;
     `ConfirmationController` shows the page only when that key is present.
- **Waitlist double opt-in**: the mail contains signed URLs. Confirming is a GET; unsubscribing is GET (confirm page)
  + POST (delete), because mail scanners open GET links automatically.
- **Rate limits**: every POST route has its own throttle key, so one form can never block another.

## JavaScript

Pages must work without JavaScript. `resources/js/oasis.js` runs a module for every element with
`data-oasis="<name>"`:

| Module | Page | Without JS |
|---|---|---|
| `pricing` | Preise: monthly/yearly toggle | Monthly prices, toggle hidden |
| `share` | Profil: share/copy the profile link | Button hidden |
| `help-search` | Hilfe: filter the FAQ | Search field not rendered |
| `membership` | Mitglied werden: live order summary | Default selection with server-rendered summary |
| `cancellation` | Kündigen: show/require the reason and date fields | All fields visible; the server validates |

New module: add `modules.name = (root) => { … }` with a header comment, put `data-oasis="name"` on the markup.

## Legal requirements that shape the code

- **§ 312k BGB (cancellation button)**: "Verträge hier kündigen" in the footer of every page, reachable without login,
  button "Jetzt kündigen", immediate confirmation by mail with date, time and content, no retention offers.
- **Withdrawal button**: own page and button "Widerruf bestätigen", immediate receipt.
- **§ 312j BGB**: the order button reads "Zahlungspflichtig bestellen"; no checkout without a payment provider.
- **DSA Art. 16**: content report form without account, good-faith confirmation, receipt mail.
- **UWG**: no invented figures (null → "Veröffentlichen wir ab dem Start"); promises are only called
  "festgeschrieben" when `promises_codified` is true. No tracking or ad scripts.
- Legal texts are placeholders and must be written or reviewed by a lawyer before launch.

## Adding a page

1. Add a route in `routes/oasis.php` (`Route::view` if it has no logic).
2. Create `resources/views/oasis/<name>.blade.php` with a header comment, then
   `@extends('layouts.oasis', ['title' => …, 'description' => …, 'active' => …])` and `@section('content')`.
3. Use the components and `.o-*` classes; put every number or contact into `config/oasis.php`.
4. Add a test in `tests/Feature/Oasis/Pages/`.

## Tests

| Folder | What |
|---|---|
| `tests/Feature/Oasis/FormsTest.php`, `ReviewRegressionTest.php` | Forms: validation, storing, mails, regressions |
| `tests/Feature/Oasis/CrossCheck/` | Independent checks of the forms against PROMPT.md |
| `tests/Feature/Oasis/Pages/` | Every page: status, one h1, meta, flags, config values |
| `tests/js/`, `tests/JavaScript/` | JS modules with Node's test runner (`node --test tests/js/ tests/JavaScript/`) |

Run with Sail: `./vendor/bin/sail up -d`, `./vendor/bin/sail artisan migrate`, `./vendor/bin/sail artisan test`.
Feature tests use the MySQL `testing` database from `phpunit.xml`.

## Not built yet (next steps)

Payment integration, trial and subscription logic (30 days, reminder two days before, automatic end), sign-up and
sign-in (an auth system), the social network app itself, an admin area, the OG image `public/images/og-oasis.png`.
