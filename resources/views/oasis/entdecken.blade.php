{{--
    Entdecken (guest preview): route oasis.entdecken via PageController::discover(), template Entdecken.dc.html.
    Shows invented sample posts, therefore labelled "Beispielansicht" and noindex; disabled by oasis.demo_pages.
--}}
@extends('layouts.oasis', [
    'title' => 'Entdecken',
    'description' => 'Öffentliche Tropfen aus den Oasen, ohne Konto lesbar. Beispielansicht bis zum Start von Oasis.',
    'noindex' => true,
])

@php
    $solidar = config('oasis.tiers.solidar.monthly');

    // Sample content until real posts exist (see config oasis.demo_pages).
    $oasen = [
        ['name' => 'Stadtgarten', 'text' => 'Gärtnern, tauschen, ernten', 'color' => 'bg-spring'],
        ['name' => 'Korn & Licht', 'text' => 'Analoge Fotografie', 'color' => 'bg-sun'],
        ['name' => 'Lesekreis', 'text' => 'Ein Buch im Monat', 'color' => 'bg-night'],
        ['name' => 'Rad & Werkstatt', 'text' => 'Schrauben, flicken, fahren', 'color' => 'bg-dune-line'],
    ];
@endphp

@section('content')
    <div role="region" aria-label="Hinweis für Gäste" class="border-b border-dune-line bg-dune">
        <div class="o-container flex flex-wrap items-center justify-between gap-x-6 gap-y-3 py-[18px]">
            <p class="m-0 flex items-center gap-3 text-base leading-normal text-ink">
                <x-oasis.icon name="eye" :size="22" class="text-night" />
                <span><b>Du liest als Gast.</b> Antworten, folgen und eigene Tropfen schreiben kannst du im kostenlosen Test.</span>
            </p>
            <a href="{{ route('register') }}" class="o-btn o-btn-md o-btn-outline text-base font-extrabold">{{ config('oasis.trial_days') }} Tage kostenlos testen</a>
        </div>
    </div>

    <div class="pt-[clamp(40px,4vw,56px)] pb-[clamp(56px,6vw,88px)]">
        <div class="o-container flex flex-wrap items-start gap-8">

            <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
                <div class="flex flex-col gap-1.5">
                    <h1 class="o-display m-0 text-[length:clamp(36px,4vw,52px)] leading-[1.1] tracking-[-0.015em] text-night">Entdecken</h1>
                    <p class="m-0 text-[17px] text-ink-2">Öffentliche Tropfen aus den Oasen, ohne Konto lesbar.</p>
                </div>

                <p class="m-0 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[15px] leading-normal text-ink-2">
                    <span class="rounded-full bg-night px-3 py-[3px] text-[13px] font-extrabold tracking-[0.5px] text-on-night uppercase">Beispielansicht</span>
                    <span>Die Tropfen, Personen und Oasen auf dieser Seite sind erfundene Beispiele. Echte Tropfen gibt es ab dem Start.</span>
                </p>

                <nav aria-label="Ansicht" class="flex flex-wrap gap-2">
                    <a href="{{ route('oasis.entdecken') }}" aria-current="page"
                       class="inline-flex min-h-11 items-center rounded-full bg-night px-[18px] text-[15px] font-bold text-on-night no-underline">Aus den Oasen</a>
                    <span aria-disabled="true" title="In der Beispielansicht nicht verfügbar"
                       class="inline-flex min-h-11 items-center rounded-full border border-dune-line px-[18px] text-[15px] font-bold text-muted">Neu</span>
                </nav>

                <x-oasis.post variant="card" name="Mira Sol" handle="mira" initials="MS" avatar="night"
                              context="Korn & Licht" time="vor 40 Min." :href="route('oasis.profil', 'mira')">
                    Erster Film aus der alten Kamera meines Opas ist entwickelt. Drei Bilder scharf, eins davon richtig gut.
                    <x-slot:media>
                        <div role="img" aria-label="Foto (Platzhalter)" class="relative h-[280px] overflow-hidden rounded-[18px] bg-spring">
                            <svg viewBox="0 0 600 280" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false" class="absolute inset-0 size-full">
                                <rect width="600" height="280" fill="#2E9E95"/>
                                <circle cx="420" cy="120" r="70" fill="#E06A43"/>
                                <path d="M0 190 q75 -30 150 0 t150 0 t150 0 t150 0 V280 H0 Z" fill="#0E2B2F" opacity="0.55"/>
                                <path d="M0 230 q75 -30 150 0 t150 0 t150 0 t150 0 V280 H0 Z" fill="#0E2B2F"/>
                            </svg>
                            <span class="absolute bottom-3 left-3.5 rounded-full bg-sand px-2.5 py-[3px] text-[13px] font-bold text-ink">Beispielfoto</span>
                        </div>
                    </x-slot:media>
                </x-oasis.post>

                <x-oasis.post variant="card" name="Oase Stadtgarten" handle="stadtgarten" initials="SG" avatar="spring" time="vor 1 Std.">
                    Samstag Setzling-Tausch im Hof. Wer bringt Tomaten mit? Kürbisse haben wir mehr als genug.
                </x-oasis.post>

                <x-oasis.post variant="card" name="Aylin Kaya" handle="aylin" initials="AK" avatar="sun" context="Lesekreis" time="vor 2 Std.">
                    Wir sind bei Kapitel 7. Wer das Ende schon kennt: bitte noch eine Woche durchhalten.
                </x-oasis.post>

                <x-oasis.post variant="card" name="Jonas Weber" handle="jonas" initials="JW" avatar="dune" context="Rad & Werkstatt" time="vor 3 Std.">
                    Kennt jemand eine Werkstatt in Leipzig, die alte Stahlrahmen wieder fit macht?
                </x-oasis.post>

                <div class="flex flex-col items-center gap-3 rounded-[24px] border-2 border-dashed border-spring px-6 py-8 text-center">
                    <p class="m-0 text-[19px] font-extrabold text-spring-text">Das war's fürs Erste.</p>
                    <p class="m-0 max-w-[30em] text-base leading-[1.55] text-ink-2">Als Mitglied siehst du die Tropfen der Menschen und Oasen, denen du folgst. Der Reihe nach, mit Ende.</p>
                    <a href="{{ route('register') }}" class="o-link text-base font-extrabold text-night">{{ config('oasis.trial_days') }} Tage kostenlos testen</a>
                </div>
            </div>

            <aside aria-label="Oasen und Infos" class="flex flex-[1_1_300px] flex-col gap-5">
                <section aria-labelledby="oasen-liste" class="flex flex-col gap-3 rounded-[24px] border border-paper-line bg-paper p-6">
                    <h2 id="oasen-liste" class="m-0 text-[19px] font-extrabold text-night">Oasen entdecken</h2>
                    <ul class="m-0 flex list-none flex-col gap-1 p-0">
                        @foreach ($oasen as $oase)
                            <li class="flex min-h-[52px] items-center gap-3">
                                <span aria-hidden="true" class="size-9 flex-none rounded-[11px] {{ $oase['color'] }}"></span>
                                <span class="flex flex-col leading-[1.3]">
                                    <span class="text-base font-extrabold">{{ $oase['name'] }}</span>
                                    <span class="text-sm text-muted">{{ $oase['text'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section aria-labelledby="was-ist" class="flex flex-col gap-3 rounded-[24px] bg-night p-6 text-on-night">
                    <h2 id="was-ist" class="m-0 text-[19px] font-extrabold">Was ist Oasis?</h2>
                    <p class="m-0 text-base leading-[1.6] text-on-night-2">Ein werbefreies soziales Netzwerk mit Feed, der ein Ende hat. Getragen von Mitgliedern ab {{ $solidar }} € im Monat.</p>
                    <a href="{{ route('oasis.funktionen') }}" class="o-link self-start text-base text-on-night">So funktioniert Oasis</a>
                </section>
            </aside>

        </div>
    </div>
@endsection
