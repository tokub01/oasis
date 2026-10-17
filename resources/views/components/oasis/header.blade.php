{{--
    Site header, rebuilt from Header.dc.html. Rendered by layouts/oasis.blade.php.

    Props:
      active    string|null  key of the current main navigation item (funktionen|preise|transparenz|manifest|
                             gemeinsam); that link gets aria-current="page"
      minimal   bool         slim header for sign-up/sign-in/checkout: only "Hilfe" and "Zur Startseite"
      announce  bool         show the founding-phase banner above the header (never in minimal mode)

    The full navigation needs about 1000px, so from 1280px (xl) it sits in the header row; below that the row holds
    only logo, primary button and a menu button that opens the same links in a <details> panel (works without JS).
--}}
@props(['active' => null, 'minimal' => false, 'announce' => true])

@php
    $nav = [
        'funktionen' => ['label' => 'Funktionen', 'route' => 'oasis.funktionen'],
        'preise' => ['label' => 'Preise', 'route' => 'oasis.preise'],
        'sicherheit' => ['label' => 'Sicherheit', 'route' => 'oasis.sicherheit'],
        'manifest' => ['label' => 'Manifest', 'route' => 'oasis.manifest'],
        'gemeinsam' => ['label' => 'Gemeinsam starten', 'route' => 'oasis.gemeinsam'],
    ];
    $seats = number_format(config('oasis.founding.seats'), 0, ',', '.');
@endphp

<header class="border-b border-dune-line bg-sand text-ink">
    @if ($announce && ! $minimal)
        <div class="bg-night text-on-night">
            <div class="o-container flex flex-wrap items-center justify-center gap-x-3.5 py-1 text-center text-sm leading-[1.45]">
                <span class="sm:hidden">{{ $seats }} Plätze mit Preisgarantie.</span>
                <span class="hidden sm:inline">Gründungsphase: Die ersten {{ $seats }} Mitglieder bekommen eine Preisgarantie.</span>
                <a href="{{ route('oasis.gruendung') }}" wire:navigate class="inline-flex min-h-11 items-center font-bold text-sun-on-night underline underline-offset-[3px]">Mehr erfahren</a>
            </div>
        </div>
    @endif

    <div class="o-container flex flex-nowrap items-center justify-between gap-2 py-4 sm:gap-4 xl:gap-8">
        <a href="{{ route('oasis.home') }}" wire:navigate aria-label="Oasis – zur Startseite" class="flex min-h-11 shrink-0 items-center text-night no-underline">
            <x-oasis.logo />
        </a>

        @if ($minimal)
            <div class="flex flex-wrap items-center justify-end gap-x-[22px] gap-y-2 text-base font-semibold">
                <a href="{{ route('oasis.hilfe') }}" wire:navigate class="inline-flex min-h-11 items-center text-ink-2 no-underline">Hilfe</a>
                <a href="{{ route('oasis.home') }}" wire:navigate class="inline-flex min-h-11 items-center text-night underline underline-offset-4">Zur Startseite</a>
            </div>
        @else
            <nav aria-label="Hauptnavigation" class="hidden items-center gap-x-[26px] xl:flex">
                @foreach ($nav as $key => $item)
                    <a href="{{ route($item['route']) }}" wire:navigate @if ($key === $active) aria-current="page" @endif
                       @class([
                           'inline-flex min-h-11 items-center text-base',
                           'font-bold text-night underline decoration-sun decoration-2 underline-offset-8' => $key === $active,
                           'font-semibold text-ink-2 no-underline' => $key !== $active,
                       ])>{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2 sm:gap-x-[18px]">
                @auth
                    <a href="{{ route('app.home') }}" wire:navigate class="o-btn o-btn-sm o-btn-primary whitespace-nowrap max-sm:px-4">Zur App</a>
                @else
                    <a href="{{ route('login') }}" wire:navigate class="hidden min-h-11 items-center text-base font-semibold text-night no-underline xl:inline-flex">Anmelden</a>
                    <a href="{{ route('register') }}" wire:navigate class="o-btn o-btn-sm o-btn-primary whitespace-nowrap max-sm:px-4">{{ config('oasis.trial_days') }} Tage testen</a>
                @endauth

                <details class="group relative xl:hidden">
                    <summary aria-label="Menü" class="flex size-11 items-center justify-center rounded-full border border-dune-line text-night">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
                            <path class="group-open:hidden" d="M4 7h16M4 12h16M4 17h16"/>
                            <path class="hidden group-open:block" d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                    </summary>
                    <div class="absolute top-full right-0 z-30 mt-2 flex w-64 flex-col gap-1 rounded-2xl border border-dune-line bg-paper p-2 shadow-lg">
                        <nav aria-label="Hauptnavigation">
                            <ul class="m-0 flex list-none flex-col gap-1 p-0">
                                @foreach ($nav as $key => $item)
                                    <li>
                                        <a href="{{ route($item['route']) }}" wire:navigate @if ($key === $active) aria-current="page" @endif
                                           @class([
                                               'flex min-h-11 items-center rounded-xl px-3 font-semibold no-underline',
                                               'bg-dune font-bold text-night' => $key === $active,
                                               'text-ink-2' => $key !== $active,
                                           ])>{{ $item['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                        @guest
                            <div aria-hidden="true" class="mx-3 my-1 h-px bg-paper-line"></div>
                            <a href="{{ route('login') }}" wire:navigate class="flex min-h-11 items-center rounded-xl px-3 font-bold text-night no-underline">Anmelden</a>
                        @endguest
                    </div>
                </details>
            </div>
        @endif
    </div>
</header>
