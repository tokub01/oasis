@extends('layouts.oasis', [
    'title' => 'Transparenz',
    'description' => 'Oasis lebt nur von Mitgliedsbeiträgen. Hier siehst du jeden Monat, was hereinkommt, was hinausgeht und wofür.',
    'active' => 'transparenz',
])

@php
    $t = config('oasis.transparency');
    $notYet = 'Veröffentlichen wir ab dem Start';

    // German number formats. Whole euro amounts without cents, otherwise two decimals.
    $euro = fn ($value) => number_format((float) $value, floor((float) $value) == (float) $value ? 0 : 2, ',', '.').' €';
    $count = fn ($value) => number_format((int) $value, 0, ',', '.');

    $costItems = $t['cost_items'] ?? [];
    $itemsComplete = count($costItems) > 0 && ! in_array(null, $costItems, true);

    // Total running costs: explicit value first, otherwise the sum of fully published items.
    $costs = $t['costs'] ?? ($itemsComplete ? array_sum($costItems) : null);
    $income = $t['income'] ?? null;

    // Coverage is only shown when both inputs are published. Never a fixed example value.
    $coverage = ($costs !== null && $income !== null && $costs > 0)
        ? (int) round($income / $costs * 100)
        : null;
    $coverageBar = $coverage === null ? null : max(0, min(100, $coverage));

    $figures = [
        ['label' => 'zahlende Mitglieder', 'value' => $t['members'] ?? null, 'format' => $count],
        ['label' => 'laufende Kosten im Monat', 'value' => $costs, 'format' => $euro],
        ['label' => 'Einnahmen im Monat', 'value' => $income, 'format' => $euro],
        ['label' => 'getragene Solidar- und Patenplätze', 'value' => $t['sponsored_seats'] ?? null, 'format' => $count],
    ];

    $votes = $t['votes'] ?? [];
@endphp

@section('content')
    {{-- Intro --}}
    <section aria-labelledby="t-titel" class="pt-[clamp(56px,6vw,88px)] pb-[clamp(40px,4vw,64px)]">
        <div class="o-container flex flex-col gap-5">
            <p class="o-eyebrow">Transparenz</p>
            <h1 id="t-titel" class="o-h1 text-night">Jeder Euro, offen gelegt.</h1>
            <p class="max-w-[38em] text-[clamp(18px,1.5vw,20px)] leading-[1.6] text-ink-2">Oasis lebt nur von Mitgliedsbeiträgen. Hier siehst du jeden Monat, was hereinkommt, was hinausgeht und wofür.</p>
            <p class="text-[15px] text-muted">Stand: {{ $t['as_of'] ?? $notYet }} · wird monatlich aktualisiert</p>
        </div>
    </section>

    {{-- Key figures --}}
    <section aria-label="Kennzahlen" class="pb-[clamp(56px,6vw,88px)]">
        <div class="o-container">
            <div class="flex flex-col gap-9 rounded-[32px] bg-night p-[clamp(28px,3.5vw,48px)] text-on-night">
                <dl class="o-auto-grid gap-x-8 gap-y-7 [--min:220px]">
                    @foreach ($figures as $figure)
                        <div class="flex flex-col-reverse gap-1.5">
                            <dt class="text-[15px] text-on-night-muted">{{ $figure['label'] }}</dt>
                            @if ($figure['value'] === null)
                                <dd class="text-xl leading-[1.3] font-bold text-on-night-2">{{ $notYet }}</dd>
                            @else
                                <dd class="o-display text-[44px] leading-[1.1] font-semibold">{{ $figure['format']($figure['value']) }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap justify-between gap-x-6 gap-y-2 text-base">
                        <span class="font-bold">Kosten dieses Monats gedeckt</span>
                        <span class="text-on-night-2">{{ $coverage === null ? $notYet : $coverage.' %' }}</span>
                    </div>
                    @if ($coverage !== null)
                        <div role="img" aria-label="Fortschrittsbalken: Kostendeckung {{ $coverage }} %" class="h-4 overflow-hidden rounded-lg bg-night-line">
                            {{-- SVG width attribute instead of an inline style for the dynamic bar width. --}}
                            <svg class="block h-4 w-full" aria-hidden="true" focusable="false">
                                <rect width="{{ $coverageBar }}%" height="16" rx="8" class="fill-spring" />
                            </svg>
                        </div>
                        <p class="text-sm text-on-night-muted">Berechnet aus den tatsächlichen Beiträgen.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Costs --}}
    <section aria-labelledby="kosten-titel" class="o-section-s border-y border-paper-line bg-paper">
        <div class="o-container o-auto-grid items-start gap-x-[72px] gap-y-8 [--min:360px]">
            <div class="flex flex-col gap-4">
                <h2 id="kosten-titel" class="o-h2-s text-night">Wohin das Geld geht</h2>
                <p class="max-w-[28em] text-lg leading-[1.6] text-ink-2">Die laufenden Kosten im Überblick. Kleine Posten fassen wir zusammen, große schlüsseln wir einzeln auf.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[420px] border-collapse text-[17px]">
                    <thead>
                        <tr>
                            <th scope="col" class="border-b-2 border-night pb-3.5 text-left text-sm font-bold tracking-[1.5px] text-muted uppercase">Posten</th>
                            <th scope="col" class="border-b-2 border-night pb-3.5 text-right text-sm font-bold tracking-[1.5px] text-muted uppercase">Im Monat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($costItems as $label => $amount)
                            <tr>
                                <td class="border-b border-dune-line py-4">{{ $label }}</td>
                                @if ($amount === null)
                                    <td class="border-b border-dune-line py-4 text-right text-[15px] text-muted">{{ $notYet }}</td>
                                @else
                                    <td class="border-b border-dune-line py-4 text-right">{{ $euro($amount) }}</td>
                                @endif
                            </tr>
                        @endforeach
                        <tr>
                            <td class="py-4 font-extrabold">Summe</td>
                            @if ($costs === null)
                                <td class="py-4 text-right text-[15px] font-bold text-muted">{{ $notYet }}</td>
                            @else
                                <td class="py-4 text-right font-extrabold">{{ $euro($costs) }}</td>
                            @endif
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Surpluses and promises --}}
    <section aria-label="Überschüsse und Versprechen" class="o-section-s">
        <div class="o-container o-auto-grid gap-6 [--min:420px]">
            <div class="flex flex-col gap-4 rounded-[28px] bg-dune p-9">
                <h2 class="text-2xl leading-[1.3] font-extrabold text-night">Wenn mehr reinkommt als rausgeht</h2>
                <p class="text-[17px] leading-[1.6] text-ink-2">Überschüsse gehören der Gemeinschaft. Was damit passiert, entscheiden die Mitglieder per Abstimmung.</p>
                <ul class="flex flex-wrap gap-2.5">
                    @foreach (['mehr Patenplätze', 'eine Rücklage', 'neue Funktionen', 'niedrigere Preise'] as $option)
                        <li class="rounded-full bg-paper px-4 py-2 text-base font-bold">{{ $option }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="flex flex-col gap-4 rounded-[28px] bg-night p-9 text-on-night">
                <h2 class="text-2xl leading-[1.3] font-extrabold">Was wir nie tun</h2>
                <ul class="flex flex-col gap-2.5 text-[17px] leading-normal text-on-night">
                    @foreach (['Werbung verkaufen', 'ein Werbeprofil über dich anlegen', 'Daten verkaufen', 'den Feed auf Verweildauer trimmen'] as $never)
                        <li class="flex items-start gap-3">
                            <x-oasis.icon name="close" :size="20" :stroke="2.2" class="mt-[3px] text-sun-on-night" />
                            <span>{{ $never }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="text-[15px] leading-[1.55] text-on-night-2">Diese Punkte stehen in unseren Regeln. Ändern ließen sie sich nur mit Zustimmung der Mitglieder.</p>
            </div>
        </div>
    </section>

    {{-- Votes: hidden until real votes exist. --}}
    @if (count($votes) > 0)
        <section aria-labelledby="abstimmung-titel" class="pb-[clamp(72px,8vw,120px)]">
            <div class="o-container flex flex-col gap-7">
                <div class="flex max-w-[760px] flex-col gap-3">
                    <h2 id="abstimmung-titel" class="o-h2-s text-night">Abstimmungen</h2>
                    <p class="text-lg leading-[1.6] text-ink-2">Jedes Mitglied hat eine Stimme, egal welche Stufe. Ergebnisse veröffentlichen wir hier.</p>
                </div>
                @foreach ($votes as $vote)
                    @if ($vote['open'] ?? false)
                        <div class="flex flex-col gap-[18px] rounded-[28px] border border-dune-line bg-paper p-8">
                            <div class="flex flex-wrap items-center gap-x-3.5 gap-y-2">
                                <span class="rounded-full bg-spring px-3 py-[5px] text-sm font-extrabold text-sun-ink">Läuft</span>
                                @if (! empty($vote['ends']))
                                    <span class="text-[15px] text-muted">endet am {{ $vote['ends'] }}</span>
                                @endif
                            </div>
                            <h3 class="text-2xl leading-[1.3] font-extrabold text-night">{{ $vote['title'] }}</h3>
                            <fieldset class="flex flex-col gap-2.5">
                                <legend class="sr-only">Antwortmöglichkeiten</legend>
                                @foreach ($vote['options'] ?? [] as $option)
                                    <label class="flex min-h-12 items-center gap-3 rounded-[14px] border border-dune-line px-4 text-[17px]"><input type="radio" name="abstimmung-{{ $loop->parent->index }}" disabled class="size-[18px] accent-night">{{ $option }}</label>
                                @endforeach
                            </fieldset>
                            <p class="flex flex-wrap items-center gap-x-4 gap-y-2 text-base text-ink-2">Abstimmen können Mitglieder nach der Anmeldung.
                                <a href="{{ route('oasis.anmelden') }}" class="inline-flex min-h-11 items-center font-bold text-night underline underline-offset-[5px]">Anmelden</a>
                            </p>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 rounded-[20px] border border-dune-line px-7 py-[22px]">
                            <div class="flex flex-wrap items-center gap-x-3.5 gap-y-2">
                                <span class="rounded-full bg-dune px-3 py-[5px] text-sm font-extrabold text-night">Abgeschlossen</span>
                                <span class="text-[17px] font-bold">{{ $vote['title'] }}</span>
                            </div>
                            @if (! empty($vote['result']))
                                <span class="text-base text-ink-2">Ergebnis: {{ $vote['result'] }}</span>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
@endsection
