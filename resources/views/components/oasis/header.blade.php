{{--
    Site header, rebuilt from Header.dc.html. Rendered by layouts/oasis.blade.php.

    Props:
      active    string|null  key of the current main navigation item (funktionen|preise|transparenz|manifest|
                             gemeinsam); that link gets aria-current="page"
      minimal   bool         slim header for sign-up/sign-in/checkout: only "Hilfe" and "Zur Startseite"
      announce  bool         show the founding-phase banner above the header (never in minimal mode)
--}}
@props(['active' => null, 'minimal' => false, 'announce' => true])

@php
    $nav = [
        'funktionen' => ['label' => 'Funktionen', 'route' => 'oasis.funktionen'],
        'preise' => ['label' => 'Preise', 'route' => 'oasis.preise'],
        'transparenz' => ['label' => 'Transparenz', 'route' => 'oasis.transparenz'],
        'manifest' => ['label' => 'Manifest', 'route' => 'oasis.manifest'],
        'gemeinsam' => ['label' => 'Gemeinsam starten', 'route' => 'oasis.gemeinsam'],
    ];
@endphp

<header class="border-b border-dune-line bg-sand text-ink">
    @if ($announce && ! $minimal)
        <div class="bg-night text-on-night">
            <div class="o-container flex flex-wrap items-center justify-center gap-x-3.5 gap-y-1 py-2.5 text-center text-sm leading-[1.45]">
                <span>Gründungsphase: Die ersten {{ number_format(config('oasis.founding.seats'), 0, ',', '.') }} Mitglieder bekommen eine Preisgarantie.</span>
                <a href="{{ route('oasis.gruendung') }}" class="inline-flex min-h-11 items-center font-bold text-sun-on-night underline underline-offset-[3px]">Mehr erfahren</a>
            </div>
        </div>
    @endif

    <div class="o-container flex flex-wrap items-center justify-between gap-x-8 gap-y-3 py-4">
        <a href="{{ route('oasis.home') }}" aria-label="Oasis – zur Startseite" class="flex min-h-11 items-center text-night no-underline">
            <x-oasis.logo />
        </a>

        @if ($minimal)
            <div class="flex flex-wrap items-center gap-x-[22px] gap-y-2 text-base font-semibold">
                <a href="{{ route('oasis.hilfe') }}" class="inline-flex min-h-11 items-center text-ink-2 no-underline">Hilfe</a>
                <a href="{{ route('oasis.home') }}" class="inline-flex min-h-11 items-center text-night underline underline-offset-4">Zur Startseite</a>
            </div>
        @else
            <nav aria-label="Hauptnavigation" class="flex flex-wrap items-center gap-x-[26px]">
                @foreach ($nav as $key => $item)
                    @if ($key === $active)
                        <a href="{{ route($item['route']) }}" aria-current="page"
                           class="inline-flex min-h-11 items-center text-base font-bold text-night underline decoration-sun decoration-2 underline-offset-8">{{ $item['label'] }}</a>
                    @else
                        <a href="{{ route($item['route']) }}"
                           class="inline-flex min-h-11 items-center text-base font-semibold text-ink-2 no-underline">{{ $item['label'] }}</a>
                    @endif
                @endforeach
            </nav>
            <div class="flex flex-wrap items-center gap-x-[18px] gap-y-2">
                <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center text-base font-semibold text-night no-underline">Anmelden</a>
                <a href="{{ route('register') }}" class="o-btn o-btn-sm o-btn-primary">{{ config('oasis.trial_days') }} Tage testen</a>
            </div>
        @endif
    </div>
</header>
