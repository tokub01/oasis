# Oasis on Livewire 4 (stage 12)

The app behaves like a single-page app: links swap only the page body (`wire:navigate`) and actions update the screen
without a reload. Every interactive screen is a Livewire component; plain controllers remain only where a browser
request must not be a Livewire request (see the end).

## Component format

Class-based components, as configured in `config/livewire.php` (`make_command.type = class`):

| Part | Place |
|---|---|
| Class | `app/Livewire/<Area>/<Name>.php` |
| View | `resources/views/livewire/<area>/<name>.blade.php` (one root element) |
| Create | `./vendor/bin/sail artisan make:livewire <Area>/<Name> --class` |

Reference implementation: `App\Livewire\Notifications\Inbox` and `Settings` with `tests/Feature/Notifications`.

## Pages

- A whole page is a full-page component: `Route::livewire('<uri>', Component::class)->name('<name>')`. URIs and
  route names stay stable – mails, policies and tests use them.
- The layout is chosen in `render()`:
  `view('livewire.…', [...])->layout('layouts.app', ['title' => '…', 'active' => '…'])`. Layouts: `layouts.app`
  (members), `layouts.staff` (moderation, admin), `layouts.oasis` (public pages). All accept `$slot`.

## Navigation

- Internal links carry `wire:navigate`. Not on external links, file downloads, logout or payment redirects.
- After an action that leads elsewhere: `$this->redirectRoute('name', [...], navigate: true)`.
- Livewire's assets load on every layout; the progress bar uses the brand colour (`config/livewire.php`).
- `resources/js/oasis.js` starts its modules on `livewire:navigated` (fired after the first load and after every
  navigation). Page scripts must do the same and clean up when the page is left (`livewire:navigating`).

## Components stay thin

A component validates, authorises, calls an existing Action/Query/Service and sets what the view shows. Business
rules live in `app/Actions`, `app/Queries`, `app/Services` – never duplicated in a component.

## Security – every public method is an endpoint

- Authorise inside each action (`Gate::authorize`, `$this->authorize`, or an owner-scoped query that fails with 404),
  never only while rendering.
- Values the client must not change are `#[Locked]`. All input is validated (`$this->validate()` or `#[Validate]`).
- Public properties end up in the HTML snapshot: no foreign e-mail addresses, tokens or moderation internals for
  non-staff. Derive such data in `render()` or `#[Computed]`.
- Rate limits of the former routes are kept with `RateLimiter`.
- `csp_safe` is on: no inline JavaScript, Alpine only with simple expressions.

## UX

- `wire:key` in every loop; `wire:loading.attr="disabled"` on buttons that change data.
- Search and filters: `wire:model.live.debounce.300ms` and `#[Url]`, so a filtered view can be shared and reloaded.
- Feedback lives in the component (`public ?string $status`, rendered in `role="status"`), errors with `role="alert"`.
- German UI, informal "du", calm (no counters that create pressure), accessible labels and focus.

## Tests

- Page: `get(route(...))->assertOk()->assertSeeLivewire(Component::class)`.
- Actions: `Livewire::test(Component::class)->set(...)->call(...)`, including foreign accounts
  (`->assertStatus(403|404)`) and manipulated properties.

## Public forms

The five public forms (start list, cancellation § 312k BGB, withdrawal, DSA notice, organisation enquiry) are
components in `app/Livewire/Oasis`, embedded in otherwise static pages (`<livewire:oasis.… />`).

- Rules live in Livewire form objects (`app/Livewire/Forms/Oasis`), sharing `PublicForm`: honeypot `website`,
  lower-cased e-mail, German messages, empty optional fields stored as null. Errors are named `form.<field>`.
- Storing and mailing is done by Actions in `app/Actions/Oasis` (`RecordCancellation`, `JoinWaitlist`, …).
- `SubmitsPublicForm` gives every form its own budget of six submissions per minute and IP (error `throttle`) and
  hands over to `oasis.bestaetigung.<form>` via a flash, so a confirmation is never shown without a submission.
- Tests submit like a visitor: `Tests\Support\PublicForms::submit('kuendigen', [...])`.

## What stays a controller

Chat JSON API, Mollie webhook, image delivery, invoice and account export downloads, signed confirmation links and
Fortify's login, registration, password and two-factor endpoints.
