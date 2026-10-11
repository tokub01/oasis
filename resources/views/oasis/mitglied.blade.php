{{--
    Mitglied werden (checkout): route oasis.mitglied via PageController::membership(), template Mitglied.dc.html.
    404 while oasis.checkout_enabled is false. The order summary is the JS module "membership"; without JS the
    default tier (Oase, monthly, SEPA) is preselected and its summary is rendered server-side.
    Optional variables from a future checkout controller: $trialDaysLeft (int), $trialEndDate (string).
--}}
@extends('layouts.oasis', [
    'title' => 'Mitglied werden',
    'description' => 'Mitglied bei Oasis werden: Stufe, Zahlweise und Zahlungsart wählen. Alle Mitglieder bekommen dasselbe.',
    'minimal' => true,
    'noindex' => true,
])

@php
    $tiers = config('oasis.tiers');
    $yearMonths = config('oasis.year_months');
    $vatRate = config('oasis.vat_rate');
    $minMaezen = $tiers['maezen']['monthly'];
    $defaultTier = collect($tiers)->search(fn ($tier) => $tier['default'] ?? false) ?: 'oase';

    $euro = fn ($amount) => \App\Support\OasisFormat::euro($amount);
    $money = fn ($amount) => \App\Support\OasisFormat::money($amount);

    $tierTexts = [
        'solidar' => 'Für alle, denen '.$euro($tiers['oase']['monthly']).' gerade zu viel sind. Ohne Nachweis.',
        'oase' => 'Der faire Standard. Deckt deinen eigenen Platz.',
        'maezen' => 'Trägt rechnerisch drei Solidarplätze mit. Nach oben offen.',
    ];

    $rhythms = [
        'monat' => ['label' => 'Monatlich', 'summary' => 'monatlich', 'unit' => 'pro Monat',
            'term' => 'Monatlich kündbar, jederzeit über „Verträge hier kündigen“.'],
        'jahr' => ['label' => 'Jährlich', 'summary' => 'jährlich', 'unit' => 'pro Jahr',
            'term' => 'Läuft 12 Monate. Danach kannst du monatlich kündigen oder ein neues Jahr abschließen.'],
    ];

    $payments = ['sepa' => 'SEPA-Lastschrift', 'paypal' => 'PayPal', 'karte' => 'Kreditkarte'];

    // Defaults shown without JavaScript: standard tier, monthly, SEPA.
    $defaultAmount = $tiers[$defaultTier]['monthly'];
    $defaultVat = $defaultAmount * $vatRate / (100 + $vatRate);

    // Trial data comes from the account once the trial logic exists.
    // Passed in by the checkout controller once trial accounts exist; null until then.
    $trialDaysLeft = $trialDaysLeft ?? null;
    $trialEndDate = $trialEndDate ?? null;

    $choiceClasses = 'flex min-h-14 cursor-pointer items-center gap-3 rounded-2xl border border-dune-line bg-field px-[18px] text-[17px] font-bold';
    $inputClasses = 'min-h-[52px] rounded-xl border border-field-line bg-field px-4 text-[17px] text-ink';
@endphp

@section('content')
    <div class="pt-[clamp(40px,5vw,64px)] pb-[clamp(56px,6vw,88px)]">
        <div class="o-container flex flex-col gap-8">

            <div class="flex flex-col gap-3">
                <p class="m-0 inline-flex items-center gap-2 self-start rounded-full bg-dune px-3.5 py-1.5 text-[15px] font-bold text-night">
                    <x-oasis.icon name="clock" :size="16" :stroke="2.2" />
                    @if ($trialDaysLeft !== null)
                        Noch {{ $trialDaysLeft }} Tage in deinem Test
                    @else
                        Du testest gerade
                    @endif
                </p>
                <h1 class="o-display m-0 text-[length:clamp(36px,4.2vw,56px)] leading-[1.08] tracking-[-0.015em] text-night">Bleib in der Oase.</h1>
                <p class="m-0 max-w-[36em] text-lg text-ink-2">Wähle, wie viel du beitragen möchtest. Alle Mitglieder bekommen dasselbe.</p>
            </div>

            <div data-oasis="membership" data-year-months="{{ $yearMonths }}" data-vat-rate="{{ $vatRate }}"
                 data-min="{{ $minMaezen }}" class="flex flex-wrap items-start gap-8">

                {{-- Payment integration is the next step: until then checkout_enabled is false
                     and this page is not reachable. --}}
                <form id="mitglied-form" action="#" method="post" aria-label="Mitgliedschaft auswählen"
                      class="flex min-w-0 flex-[999_1_520px] flex-col gap-7 rounded-[32px] border border-paper-line bg-paper p-[clamp(24px,3vw,40px)]">
                    @csrf

                    <fieldset class="m-0 flex flex-col gap-3 border-none p-0">
                        <legend class="mb-3 p-0 text-xl font-extrabold text-night">1. Deine Stufe</legend>
                        @foreach ($tiers as $key => $tier)
                            <label class="flex cursor-pointer items-start gap-3.5 rounded-[18px] border-2 border-dune-line bg-paper px-5 py-[18px] has-checked:border-night has-checked:bg-field">
                                <input type="radio" name="stufe" value="{{ $key }}" data-label="{{ $tier['name'] }}"
                                       data-monthly="{{ $tier['monthly'] }}" @checked($key === $defaultTier)
                                       class="mt-1 size-5 flex-none accent-night">
                                <span class="flex flex-1 flex-col gap-0.5">
                                    <span class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                        <span class="text-lg font-extrabold text-night">{{ $tier['name'] }}@if ($tier['default'] ?? false) <span class="ml-1.5 rounded-full bg-sun px-2.5 py-[3px] text-[13px] font-extrabold text-sun-ink">Standard</span>@endif</span>
                                        <span class="text-[17px] font-bold" data-price
                                              data-month="{{ ($tier['min'] ? 'ab ' : '').$euro($tier['monthly']) }} / Monat"
                                              data-year="{{ ($tier['min'] ? 'ab ' : '').$euro($tier['monthly'] * $yearMonths) }} / Jahr">{{ ($tier['min'] ? 'ab ' : '').$euro($tier['monthly']) }} / Monat</span>
                                    </span>
                                    <span class="text-[15px] text-ink-2">{{ $tierTexts[$key] ?? '' }}</span>
                                </span>
                            </label>
                        @endforeach

                        <div class="flex flex-wrap items-center gap-x-3.5 gap-y-2 pr-1 pl-[54px]">
                            <label for="m-betrag" class="text-[15px] font-bold">Dein Mäzen-Beitrag im Monat</label>
                            <div class="flex items-center gap-1.5">
                                <input id="m-betrag" name="betrag" type="number" min="{{ $minMaezen }}" step="1" value="{{ $minMaezen }}"
                                       aria-describedby="m-betrag-fehler"
                                       class="min-h-11 w-24 rounded-[10px] border border-field-line bg-field px-3 text-[17px] text-ink">
                                <span class="text-[17px] font-bold">€</span>
                            </div>
                            <span id="m-betrag-fehler" aria-live="polite" class="text-sm font-bold text-sun-text"><span data-amount-error hidden>Mindestens {{ $euro($minMaezen) }}</span></span>
                        </div>
                    </fieldset>

                    <fieldset class="m-0 flex flex-col gap-3 border-none p-0">
                        <legend class="mb-3 p-0 text-xl font-extrabold text-night">2. Zahlweise</legend>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($rhythms as $key => $rhythm)
                                <label class="{{ $choiceClasses }} flex-[1_1_220px]">
                                    <input type="radio" name="rhythmus" value="{{ $key }}" @checked($key === 'monat')
                                           data-label="{{ $rhythm['summary'] }}" data-unit="{{ $rhythm['unit'] }}" data-term="{{ $rhythm['term'] }}"
                                           class="m-0 size-5 accent-night">{{ $rhythm['label'] }}@if ($key === 'jahr') <span class="font-semibold text-spring-text">· {{ 12 - $yearMonths }} Monate geschenkt</span>@endif
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="m-0 flex flex-col gap-3 border-none p-0">
                        <legend class="mb-3 p-0 text-xl font-extrabold text-night">3. Zahlungsart</legend>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($payments as $key => $label)
                                <label class="{{ $choiceClasses }} flex-[1_1_160px]">
                                    <input type="radio" name="zahlart" value="{{ $key }}" data-label="{{ $label }}" @checked($key === 'sepa')
                                           class="m-0 size-5 accent-night">{{ $label }}
                                </label>
                            @endforeach
                        </div>

                        <div data-pay="sepa" class="flex flex-col gap-3">
                            <div class="o-auto-grid mt-1.5 gap-x-5 gap-y-4 [--min:240px]">
                                <div class="flex flex-col gap-1.5">
                                    <label for="m-inhaber" class="text-[15px] font-bold">Kontoinhaber:in</label>
                                    <input id="m-inhaber" name="inhaber" type="text" autocomplete="name" class="{{ $inputClasses }}">
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label for="m-iban" class="text-[15px] font-bold">IBAN</label>
                                    <input id="m-iban" name="iban" type="text" inputmode="text" placeholder="DE00 0000 0000 0000 0000 00" class="{{ $inputClasses }}">
                                </div>
                            </div>
                            <p class="m-0 text-sm leading-normal text-ink-2">Mit der Bestellung erteilst du {{ config('oasis.operator.name') }} ein SEPA-Lastschriftmandat. Gläubiger-ID: {{ config('oasis.payment.sepa_creditor_id') }}.</p>
                        </div>
                        <p data-pay="paypal" hidden class="mt-1.5 mb-0 text-[15px] text-ink-2">Nach der Bestellung leiten wir dich zu PayPal weiter, um die Zahlung zu bestätigen.</p>
                        <p data-pay="karte" hidden class="mt-1.5 mb-0 text-[15px] text-ink-2">Deine Kartendaten gibst du im nächsten Schritt direkt bei {{ config('oasis.payment.provider') }} ein. Wir speichern sie nicht.</p>
                    </fieldset>
                </form>

                <aside aria-labelledby="summe-titel" class="flex flex-[1_1_340px] flex-col gap-[18px] rounded-[32px] bg-night p-[clamp(24px,3vw,36px)] text-on-night">
                    <h2 id="summe-titel" class="m-0 text-xl font-extrabold">Deine Mitgliedschaft</h2>
                    <dl class="m-0 flex flex-col gap-3 text-base">
                        <div class="flex justify-between gap-4"><dt class="text-on-night-muted">Stufe</dt><dd class="m-0 font-bold" data-out="stufe">{{ $tiers[$defaultTier]['name'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-on-night-muted">Zahlweise</dt><dd class="m-0 font-bold" data-out="rhythmus">{{ $rhythms['monat']['summary'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-on-night-muted">Zahlungsart</dt><dd class="m-0 font-bold" data-out="zahlart">{{ $payments['sepa'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-on-night-muted">Erste Zahlung</dt><dd class="m-0 text-right font-bold">@if ($trialEndDate !== null)am {{ $trialEndDate }}, @endif zum Ende deines Tests</dd></div>
                    </dl>
                    <div aria-live="polite" class="flex flex-col gap-1 border-y border-night-line py-[18px]">
                        <p class="m-0 flex items-baseline gap-2"><span class="o-display text-[44px] leading-[1.1] font-semibold" data-out="betrag">{{ $money($defaultAmount) }}</span><span class="text-base text-on-night-2" data-out="einheit">{{ $rhythms['monat']['unit'] }}</span></p>
                        <p class="m-0 text-sm text-on-night-muted">inklusive {{ $vatRate }} % Umsatzsteuer (<span data-out="ust">darin {{ $money($defaultVat) }}</span>)</p>
                    </div>
                    <p class="m-0 text-[15px] leading-[1.55] text-on-night-2" data-out="laufzeit">{{ $rhythms['monat']['term'] }}</p>
                    <button type="submit" form="mitglied-form"
                            class="min-h-14 cursor-pointer rounded-full border-none bg-sun text-lg font-extrabold text-sun-ink">Zahlungspflichtig bestellen</button>
                    <p class="m-0 text-sm leading-[1.55] text-on-night-2">Es gelten unsere <a href="{{ route('oasis.agb') }}" class="text-on-night underline">AGB</a>. Du kannst innerhalb von 14 Tagen widerrufen, Details in der <a href="{{ route('oasis.widerrufsbelehrung') }}" class="text-on-night underline">Widerrufsbelehrung</a>.</p>
                    <p class="m-0 border-t border-night-line pt-3.5 text-[15px] leading-[1.55] text-on-night">Lieber noch nicht? Kein Problem: Dein Test endet einfach von selbst.</p>
                </aside>

            </div>
        </div>
    </div>
@endsection
