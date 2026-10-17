{{-- Manifest: route oasis.manifest, template Manifest.dc.html. Founder, contacts and statutes from config. --}}
@extends('layouts.oasis', [
    'title' => 'Manifest',
    'description' => 'Ein Ort im Netz, der seinen Mitgliedern gehört. Wofür Oasis steht, was wir versprechen und wer dahinter steht.',
    'active' => 'manifest',
])

@php
    // Values in [BRACKETS] are open placeholders (config/oasis.php) and are never shown on the page.
    $isSet = fn (?string $value) => filled($value) && ! \Illuminate\Support\Str::is('[*]', $value);

    $founder = config('oasis.founder');
    $hasFounder = $isSet($founder['name']) && $isSet($founder['text']);
    $statutes = $isSet(config('oasis.statutes')) ? config('oasis.statutes') : null;

    $promises = [
        ['title' => 'Keine Werbung. Nie.', 'text' => 'Oasis verkauft keine Anzeigen und keine Aufmerksamkeit.'],
        ['title' => 'Kein Werbeprofil, kein Datenverkauf.', 'text' => 'Wir verdienen nicht an Daten. Also brauchen wir auch kein Profil über dich.'],
        ['title' => 'Ein Feed mit Ende.', 'text' => 'Wir trimmen nichts auf Verweildauer. Die Quelle läuft der Reihe nach und hört auf.'],
        ['title' => 'Moderation mit Begründung.', 'text' => 'Klare Regeln, Menschen in der Moderation und eine Begründung für jede Entscheidung.'],
        ['title' => 'Eine Stimme für jedes Mitglied.', 'text' => 'Über die Richtung entscheiden die, die Oasis tragen. Unabhängig davon, wie viel sie beitragen.'],
        ['title' => 'Klartext beim Datenschutz.', 'text' => 'Was mit deinen Daten passiert und wie wir sie schützen, erklären wir in klaren Worten.'],
    ];
@endphp

@section('content')
    {{-- Intro --}}
    <section aria-labelledby="m-titel" class="overflow-hidden bg-night py-[clamp(64px,7vw,112px)] text-on-night">
        <div class="o-container o-auto-grid items-center gap-12 [--min:420px]">
            <div class="flex flex-col gap-[22px]">
                <p class="o-eyebrow o-eyebrow-night">Manifest</p>
                <h1 id="m-titel" class="o-h1 text-[clamp(44px,6vw,84px)] leading-[1.02]">Ein Ort im Netz, der seinen Mitgliedern gehört.</h1>
                <p class="max-w-[32em] text-[clamp(18px,1.5vw,20px)] leading-[1.6] text-on-night-2">Wofür Oasis steht und was wir versprechen{{ $hasFounder ? ' – und wer dahinter steht' : '' }}.</p>
            </div>
            <svg viewBox="0 0 600 520" aria-hidden="true" focusable="false" class="h-auto w-[min(520px,100%)] justify-self-center">
                <path d="M80 280 A220 220 0 0 1 520 280 Z" class="fill-sun"></path>
                <path d="M50 350 q50 -38 100 0 t100 0 t100 0 t100 0 t100 0" fill="none" stroke-width="18" stroke-linecap="round" class="stroke-spring"></path>
                <path d="M100 420 q50 -38 100 0 t100 0 t100 0 t100 0" fill="none" stroke-width="18" stroke-linecap="round" opacity="0.6" class="stroke-spring"></path>
                <path d="M200 490 q50 -38 100 0 t100 0" fill="none" stroke-width="18" stroke-linecap="round" opacity="0.35" class="stroke-spring"></path>
            </svg>
        </div>
    </section>

    {{-- Why --}}
    <section aria-labelledby="warum-titel" class="py-[clamp(64px,7vw,104px)]">
        <div class="o-container o-auto-grid items-start gap-x-[72px] gap-y-8 [--min:340px]">
            <h2 id="warum-titel" class="o-h2-s text-night">Warum es Oasis gibt</h2>
            <div class="flex max-w-[40em] flex-col gap-[22px] text-xl leading-[1.65] text-ink">
                <p>Soziale Netzwerke haben uns verbunden. Aber die meisten verdienen ihr Geld damit, dass wir möglichst lange bleiben. Das prägt, was wir sehen: Was aufregt, steigt. Was leise ist, geht unter.</p>
                <p>Wir glauben, dass es anders geht. Ein Netzwerk, das von seinen Mitgliedern getragen wird, muss niemanden festhalten. Es kann ruhig sein, ehrlich, und fertig, wenn du fertig bist.</p>
                <p>Oasis ist dieser Versuch: klein angefangen, offen in den Zahlen und gemeinsam entschieden.</p>
            </div>
        </div>
    </section>

    {{-- Promises --}}
    <section aria-labelledby="versprechen-titel" class="border-y border-paper-line bg-paper py-[clamp(64px,7vw,104px)]">
        <div class="o-container flex flex-col gap-11">
            <h2 id="versprechen-titel" class="o-h2-s text-night">Unsere Versprechen</h2>
            <ol class="o-auto-grid gap-x-10 gap-y-9 [--min:320px]">
                @foreach ($promises as $promise)
                    <li class="flex flex-col gap-2.5 border-t-2 border-night pt-5">
                        <span aria-hidden="true" class="o-display text-[22px] font-semibold text-sun-text">{{ sprintf('%02d', $loop->iteration) }}</span>
                        <h3 class="text-[22px] leading-[1.3] font-extrabold text-night">{{ $promise['title'] }}</h3>
                        <p class="text-[17px] leading-[1.6] text-ink-2">{{ $promise['text'] }}</p>
                    </li>
                @endforeach
            </ol>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-3.5 rounded-[20px] bg-dune px-7 py-6">
                <x-oasis.icon name="lock" :size="26" :stroke="1.8" class="text-night" />
                <p class="flex-[1_1_480px] text-lg leading-[1.55] text-ink">
                    @if (config('oasis.promises_codified'))
                        <b>Festgeschrieben:</b> @if ($statutes) Diese Versprechen stehen in unseren {{ $statutes }}.@else Diese Versprechen sind verbindlich festgeschrieben.@endif Ändern ließen sie sich nur, wenn die Mitglieder zustimmen.
                    @else
                        <b>Wird festgeschrieben:</b> @if ($statutes) Vor dem Start schreiben wir diese Versprechen in unsere {{ $statutes }}.@else Vor dem Start schreiben wir diese Versprechen verbindlich fest.@endif Ändern ließen sie sich dann nur, wenn die Mitglieder zustimmen.
                    @endif
                </p>
            </div>
        </div>
    </section>

    {{-- Founder; until name and text are filled in, only the contact addresses are shown. --}}
    <section aria-labelledby="wer-titel" class="py-[clamp(64px,7vw,104px)]">
        <div class="o-container o-auto-grid items-center gap-x-[72px] gap-y-10 [--min:340px]">
            @if ($hasFounder && $isSet($founder['photo']))
                <img src="{{ asset($founder['photo']) }}" alt="Foto von {{ $founder['name'] }}" class="aspect-[4/3] w-full rounded-[28px] object-cover">
            @endif
            <div class="flex flex-col gap-[18px]">
                @if ($hasFounder)
                    <h2 id="wer-titel" class="o-h2-s text-night">Wer dahinter steht</h2>
                    <p class="text-xl font-extrabold text-night">{{ $founder['name'] }}, Gründer:in</p>
                    <p class="max-w-[34em] text-lg leading-[1.6] text-ink-2">{{ $founder['text'] }}</p>
                @else
                    <h2 id="wer-titel" class="o-h2-s text-night">Kontakt</h2>
                @endif
                <div class="flex flex-col gap-1 text-[17px]">
                    <span>@if ($hasFounder)<b>Kontakt:</b> @endif<a href="mailto:{{ config('oasis.mail.contact') }}" class="text-night underline underline-offset-4 [overflow-wrap:anywhere]">{{ config('oasis.mail.contact') }}</a></span>
                    @if (config('oasis.mail.press') !== config('oasis.mail.contact'))
                        <span><b>Presse:</b> <a href="mailto:{{ config('oasis.mail.press') }}" class="text-night underline underline-offset-4 [overflow-wrap:anywhere]">{{ config('oasis.mail.press') }}</a></span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Call to action --}}
    <section aria-labelledby="m-cta" class="bg-sun py-[clamp(64px,7vw,104px)] text-sun-ink">
        <div class="o-container flex flex-wrap items-center justify-between gap-x-12 gap-y-7">
            <div class="flex max-w-[640px] flex-col gap-2.5">
                <h2 id="m-cta" class="o-h2">Teil der Oase werden.</h2>
                <p class="text-lg leading-[1.6]">{{ config('oasis.trial_days') }} Tage kostenlos, ohne Zahlungsdaten.</p>
            </div>
            <a href="{{ route('register') }}" class="o-btn o-btn-dark px-[30px]">{{ config('oasis.trial_days') }} Tage kostenlos testen</a>
        </div>
    </section>
@endsection
