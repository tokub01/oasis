{{--
    App shell for signed-in members (everything under /app). Used two ways:
      - Blade pages:      @extends('layouts.app', ['title' => …, 'active' => …]) + @section('content')
      - Livewire pages:   ->layout('layouts.app', ['title' => …, 'active' => …]) and the component renders into $slot

    Variables:
      title   string       page title (suffix " · Oasis" is added)
      active  string|null  key of the current navigation item: quelle|entdecken|oasen|nachrichten|profil|einstellungen
    Stack 'scripts': page-specific Vite entries in <head> (e.g. the chat page).

    Layout: navigation as a sidebar from 1024px, as a bottom bar below. All pages are noindex (private area).
    The trial banner shows the remaining days while $user->trial_ends_at lies in the future.
--}}
@php
    $user = auth()->user();
    $active ??= null;
    $nav = [
        'quelle' => ['Quelle', 'check-circle', route('app.home')],
        'entdecken' => ['Entdecken', 'search', route('oasis.entdecken')],
        'nachrichten' => ['Nachrichten', 'mail', route('app.chat')],
        'oasen' => ['Oasen', 'users', null],
        'einstellungen' => ['Einstellungen', 'shield', route('app.settings')],
    ];
    // Staff (moderators, admins) get a link to the moderation area; the `staff` middleware still checks 2FA.
    if ($user?->role?->isStaff()) {
        $nav['moderation'] = ['Moderation', 'flag', route('moderation.index')];
    }
    $trialDaysLeft = $user?->trial_ends_at?->isFuture()
        ? (int) ceil(now()->diffInDays($user->trial_ends_at))
        : null;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' · Oasis' : 'Oasis' }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preload" href="{{ asset('fonts/manrope-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/oasis.js'])
    @livewireStyles
    @stack('scripts')
</head>
<body class="bg-sand text-ink antialiased">
    <a href="#inhalt" class="o-skip-link">Zum Inhalt springen</a>

    <div class="lg:grid lg:min-h-screen lg:grid-cols-[260px_1fr]">
        <header class="border-b border-dune-line bg-paper lg:sticky lg:top-0 lg:h-screen lg:border-r lg:border-b-0">
            <div class="flex items-center justify-between gap-4 px-5 py-4 lg:h-full lg:flex-col lg:items-stretch lg:justify-start lg:gap-8 lg:px-6 lg:py-8">
                <a href="{{ route('app.home') }}" aria-label="Oasis – zur Quelle" class="flex min-h-11 items-center text-night no-underline">
                    <x-oasis.logo :size="36" />
                </a>

                <nav aria-label="App-Navigation"
                     class="fixed inset-x-0 bottom-0 z-20 border-t border-dune-line bg-paper pb-[env(safe-area-inset-bottom)] lg:static lg:border-0 lg:bg-transparent lg:pb-0">
                    <ul class="m-0 flex list-none justify-around p-0 lg:flex-col lg:justify-start lg:gap-1">
                        @foreach ($nav as $key => [$label, $icon, $href])
                            <li>
                                @if ($href)
                                    <a href="{{ $href }}" @if ($active === $key) aria-current="page" @endif
                                       @class([
                                           'flex min-h-12 flex-col items-center justify-center gap-1 rounded-2xl px-3 text-xs font-bold no-underline lg:flex-row lg:justify-start lg:gap-3 lg:px-4 lg:text-base',
                                           'bg-night text-on-night' => $active === $key,
                                           'text-ink-2' => $active !== $key,
                                       ])>
                                        <x-oasis.icon :name="$icon" :size="22" />
                                        <span>{{ $label }}</span>
                                    </a>
                                @else
                                    {{-- Sections of later stages: visible so the structure is clear, but not a dead link. --}}
                                    <span aria-disabled="true" title="Kommt bald"
                                          class="flex min-h-12 flex-col items-center justify-center gap-1 px-3 text-xs font-bold text-muted lg:flex-row lg:justify-start lg:gap-3 lg:px-4 lg:text-base">
                                        <x-oasis.icon :name="$icon" :size="22" />
                                        <span>{{ $label }}</span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <form method="POST" action="{{ route('logout') }}" class="lg:mt-auto">
                    @csrf
                    <button type="submit" class="o-link cursor-pointer border-none bg-transparent text-base text-night">Abmelden</button>
                </form>
            </div>
        </header>

        <div class="flex min-w-0 flex-col pb-24 lg:pb-0">
            @if ($trialDaysLeft !== null)
                <p role="status" class="m-0 bg-night px-5 py-2.5 text-center text-sm text-on-night">
                    Noch {{ $trialDaysLeft }} {{ $trialDaysLeft === 1 ? 'Tag' : 'Tage' }} in deinem Test. Der Test endet von selbst.
                </p>
            @endif

            <main id="inhalt" tabindex="-1" class="mx-auto w-full max-w-[760px] px-5 py-8 outline-none lg:px-10 lg:py-12">
                @if (session('status') && ! in_array(session('status'), ['profile-information-updated', 'password-updated', 'two-factor-authentication-enabled', 'two-factor-authentication-confirmed', 'two-factor-authentication-disabled', 'recovery-codes-generated'], true))
                    <p role="status" class="mb-6 rounded-2xl bg-dune px-[18px] py-3.5">{{ session('status') }}</p>
                @endif

                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
