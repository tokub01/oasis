{{--
    App shell for signed-in members (everything under /app). Used two ways:
      - Blade pages:      @extends('layouts.app', ['title' => …, 'active' => …]) + @section('content')
      - Livewire pages:   ->layout('layouts.app', ['title' => …, 'active' => …]) and the component renders into $slot

    Variables:
      title   string       page title (suffix " · Oasis" is added)
      active  string|null  key of the current navigation item: quelle|entdecken|oasen|nachrichten|profil, or of an
                           account page: hinweise|gemerkt|einstellungen|mitgliedschaft|zahlen|einladen|privatsphaere|sicherheit|abstimmungen|moderation
      wide    bool         wider main column for pages with a side column (Quelle), default false
    Internal links use wire:navigate (Livewire SPA navigation): only the page body is swapped, see docs/oasis-livewire.md.
    Stack 'scripts': page-specific Vite entries in <head> (e.g. the chat page).

    Layout: navigation as a sidebar from 1024px, as a bottom bar below. All pages are noindex (private area).
    The trial banner shows the remaining days while $user->trial_ends_at lies in the future.
--}}
@php
    $user = auth()->user();
    $active ??= null;
    // Main sections: sidebar from 1024px, bottom bar below (at most five so the bar stays usable on phones).
    $nav = [
        'quelle' => ['Quelle', 'check-circle', route('app.home')],
        'entdecken' => ['Entdecken', 'search', route('network.discover')],
        'oasen' => ['Oasen', 'users', route('network.oases.index')],
        'nachrichten' => ['Nachrichten', 'mail', route('app.chat')],
        'profil' => ['Profil', 'eye', $user?->username ? route('oasis.profil', $user->username) : route('network.profile.edit')],
    ];
    // Account pages: below the main sections on desktop, in the "Mehr" menu on phones.
    $more = [
        'hinweise' => ['Hinweise', route('notifications.index')],
        'gemerkt' => ['Gemerkt', route('safety.bookmarks')],
        'einstellungen' => ['Einstellungen', route('app.settings')],
        'mitgliedschaft' => ['Mitgliedschaft', route('app.membership')],
        'zahlen' => ['Deine Zahlen', route('network.stats')],
        'einladen' => ['Menschen einladen', route('network.invitations.index')],
        'privatsphaere' => ['Privatsphäre', route('app.privacy')],
        'sicherheit' => ['Blockiert & stumm', route('safety.index')],
        'abstimmungen' => ['Abstimmungen', route('app.votes')],
    ];
    // Staff (moderators, admins) get a link to the moderation area; the `staff` middleware still checks 2FA.
    if ($user?->role?->isStaff()) {
        $more['moderation'] = ['Moderation', route('moderation.index')];
    }
    // Calm indicator for unread hints: a small dot, never a red counter – and none at all during a pause.
    $hasUnread = $user !== null && ! \App\Models\Notifications\NotificationPreference::where('user_id', $user->id)->first()?->isPaused()
        && $user->unreadNotifications()->exists();
    $trialDaysLeft = $user?->trial_ends_at?->isFuture()
        ? (int) ceil(now()->diffInDays($user->trial_ends_at))
        : null;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' · Oasis' : 'Oasis' }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @include('layouts.partials.pwa')
    <link rel="preload" href="{{ asset('fonts/manrope-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/oasis.js'])
    @livewireStyles
    @stack('scripts')
    <noscript>
        <style>[data-oasis-chat] [data-screen="loading"] { display: none !important; }</style>
    </noscript>
</head>
<body class="bg-sand text-ink antialiased">
    <a href="#inhalt" class="o-skip-link">Zum Inhalt springen</a>

    <div class="lg:grid lg:min-h-screen lg:grid-cols-[260px_1fr]">
        <header class="border-b border-dune-line bg-paper lg:sticky lg:top-0 lg:h-screen lg:border-r lg:border-b-0">
            <div class="flex items-center justify-between gap-4 px-5 py-4 lg:h-full lg:flex-col lg:items-stretch lg:justify-start lg:gap-5 lg:overflow-y-auto lg:px-6 lg:py-6">
                <a href="{{ route('app.home') }}" wire:navigate aria-label="Oasis – zur Quelle" class="flex min-h-11 items-center text-night no-underline">
                    <x-oasis.logo :size="36" />
                </a>

                {{-- Search: a field in the sidebar from 1024px, an icon link on phones (next to "Mehr").
                     The search page has its own field, so the sidebar field would only duplicate it there. --}}
                @unless (request()->routeIs('search.index'))
                    <form method="GET" action="{{ route('search.index') }}" role="search" class="hidden lg:block">
                        <label for="app-search" class="sr-only">Suchen</label>
                        <input id="app-search" type="search" name="q" maxlength="100" placeholder="Suchen …"
                               class="o-input min-h-11 w-full py-2">
                    </form>
                @endunless
                <a href="{{ route('search.index') }}" wire:navigate aria-label="Suchen" class="ml-auto flex min-h-11 min-w-11 items-center justify-center rounded-full text-night lg:hidden">
                    <x-oasis.icon name="search" :size="22" />
                </a>

                <nav aria-label="App-Navigation"
                     class="fixed inset-x-0 bottom-0 z-20 border-t border-dune-line bg-paper pb-[env(safe-area-inset-bottom)] lg:static lg:border-0 lg:bg-transparent lg:pb-0">
                    <ul class="m-0 flex list-none justify-around p-0 px-2 py-1 lg:flex-col lg:px-0 lg:justify-start lg:gap-1 lg:py-0">
                        {{-- Below 380 px five labels do not fit: the bar shows icons, the labels stay for screen readers. --}}
                        @foreach ($nav as $key => [$label, $icon, $href])
                            <li class="min-w-0 flex-1 lg:flex-none">
                                <a href="{{ $href }}" wire:navigate @if ($active === $key) aria-current="page" @endif
                                   @class([
                                       'flex min-h-12 flex-col items-center justify-center gap-1 rounded-2xl px-1 text-xs font-bold no-underline sm:px-3 lg:flex-row lg:justify-start lg:gap-3 lg:px-4 lg:text-base',
                                       'bg-night text-on-night' => $active === $key,
                                       'text-ink-2' => $active !== $key,
                                   ])>
                                    <x-oasis.icon :name="$icon" :size="22" />
                                    <span class="max-w-full truncate max-[379px]:sr-only">{{ $label }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                {{-- Desktop: account pages as a plain list under the main navigation. --}}
                <nav aria-label="Konto" class="hidden lg:block">
                    <ul class="m-0 flex list-none flex-col gap-0 p-0">
                        @foreach ($more as $key => [$label, $href])
                            <li><a href="{{ $href }}" wire:navigate @if ($active === $key) aria-current="page" @endif
                                   @class(['flex min-h-11 items-center gap-2 rounded-xl px-4 no-underline', 'font-bold text-night bg-dune' => $active === $key, 'text-ink-2' => $active !== $key])>{{ $label }}@if ($key === 'hinweise' && $hasUnread)<span class="size-2 rounded-full bg-spring" aria-hidden="true"></span><span class="sr-only">(neue Hinweise)</span>@endif</a></li>
                        @endforeach
                    </ul>
                </nav>

                <form method="POST" action="{{ route('logout') }}" class="hidden lg:mt-auto lg:block">
                    @csrf
                    <button type="submit" class="o-link cursor-pointer border-none bg-transparent text-base text-night">Abmelden</button>
                </form>

                {{-- Phones: the same account pages and logout in a "Mehr" disclosure (works without JavaScript). --}}
                <details class="relative lg:hidden">
                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-full border border-dune-line px-4 font-bold text-night">Mehr
                        @if ($hasUnread)<span class="size-2 rounded-full bg-spring" aria-hidden="true"></span><span class="sr-only">(neue Hinweise)</span>@endif</summary>
                    <div class="absolute right-0 z-30 mt-2 flex w-60 flex-col gap-1 rounded-2xl border border-dune-line bg-paper p-2 shadow-lg">
                        <nav aria-label="Konto">
                            <ul class="m-0 flex list-none flex-col gap-1 p-0">
                                @foreach ($more as $key => [$label, $href])
                                    <li><a href="{{ $href }}" wire:navigate @if ($active === $key) aria-current="page" @endif class="flex min-h-11 items-center gap-2 rounded-xl px-3 text-night no-underline">{{ $label }}@if ($key === 'hinweise' && $hasUnread)<span class="size-2 rounded-full bg-spring" aria-hidden="true"></span><span class="sr-only">(neue Hinweise)</span>@endif</a></li>
                                @endforeach
                            </ul>
                        </nav>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex min-h-11 w-full cursor-pointer items-center rounded-xl border-none bg-transparent px-3 text-base text-night">Abmelden</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <div class="flex min-w-0 flex-col pb-24 lg:pb-0">
            @if ($trialDaysLeft !== null)
                <p role="status" class="m-0 bg-night px-5 py-2.5 text-center text-sm text-balance text-on-night">
                    Noch {{ $trialDaysLeft }} {{ $trialDaysLeft === 1 ? 'Tag' : 'Tage' }} in deinem Test. Der Test endet von selbst.
                </p>
            @endif

            <main id="inhalt" tabindex="-1" @class(["mx-auto w-full px-5 py-8 outline-none lg:px-10 lg:py-12", "max-w-[1180px]" => $wide ?? false, "max-w-[760px]" => ! ($wide ?? false)])>
                @if (session('status') && ! in_array(session('status'), ['profile-information-updated', 'password-updated', 'two-factor-authentication-enabled', 'two-factor-authentication-confirmed', 'two-factor-authentication-disabled', 'recovery-codes-generated'], true))
                    <p role="status" class="mb-6 rounded-2xl bg-dune px-[18px] py-3.5">{{ session('status') }}</p>
                @endif

                @if (session('error'))
                    <p role="alert" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-[18px] py-3.5 text-sm font-bold text-rose-800">{{ session('error') }}</p>
                @endif

                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
