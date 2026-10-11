@extends('layouts.oasis', [
    'title' => 'Preise',
    'description' => 'Dieselbe Oase für alle: Solidar, Oase oder Mäzen. Jede Mitgliedschaft enthält alle Funktionen. Erst '.config('oasis.trial_days').' Tage kostenlos testen, ohne Zahlungsdaten.',
    'active' => 'preise',
])

@php
    $tiers = config('oasis.tiers');
    $trialDays = config('oasis.trial_days');
    $freeMonths = 12 - config('oasis.year_months');
@endphp

@section('content')
    <div data-oasis="pricing">
        <section aria-labelledby="p-titel" class="pt-[clamp(56px,6vw,88px)] pb-[clamp(40px,4vw,56px)]">
            <div class="o-container flex flex-col items-center gap-5 text-center">
                <p class="o-eyebrow">Mitgliedschaft</p>
                <h1 id="p-titel" class="o-h1 max-w-[16em] text-night">Dieselbe Oase für alle. Du wählst, wie viel du beiträgst.</h1>
                <p class="max-w-[36em] text-[clamp(18px,1.5vw,20px)] leading-[1.6] text-ink-2">Jede Mitgliedschaft enthält alle Funktionen. Starte mit {{ $trialDays }} Tagen kostenlos und ohne Zahlungsdaten, entscheiden kannst du danach.</p>
                {{-- Only useful with JS; hidden until the pricing module enables it. --}}
                <div role="group" aria-label="Abrechnungszeitraum" data-period-toggle hidden class="mt-3 inline-flex flex-wrap justify-center gap-1 rounded-full bg-dune p-1.5">
                    <button type="button" aria-pressed="true" data-period="month" class="min-h-11 rounded-full px-[22px] text-base font-bold text-night aria-pressed:bg-night aria-pressed:text-on-night">Monatlich</button>
                    <button type="button" aria-pressed="false" data-period="year" class="min-h-11 rounded-full px-[22px] text-base font-bold text-night aria-pressed:bg-night aria-pressed:text-on-night">Jährlich · {{ $freeMonths }} Monate geschenkt</button>
                </div>
            </div>
        </section>

        <section aria-label="Preisstufen" class="pb-[clamp(56px,6vw,88px)]">
            <div class="o-container flex flex-col gap-6">
                <div class="o-auto-grid items-stretch gap-6 [--min:320px]">
                    <x-oasis.price-card tier="solidar" subtitle="Für alle, denen {{ \App\Support\OasisFormat::euro($tiers['oase']['monthly']) }} gerade zu viel sind">
                        Fester Betrag, ohne Nachweis. Welche Stufe du gewählt hast, sieht niemand außer dir.
                    </x-oasis.price-card>
                    <x-oasis.price-card tier="oase" subtitle="Der faire Standard" :featured="true">
                        Deckt deinen eigenen Platz in der Oase. Die Stufe, die wir allen empfehlen, die sie sich leisten können.
                    </x-oasis.price-card>
                    <x-oasis.price-card tier="maezen" subtitle="Trägt andere mit, nach oben offen">
                        Dein Beitrag trägt rechnerisch drei Solidarplätze mit. Mehr Funktionen gibt es dafür nicht, aber auf Wunsch einen Platz auf unserer Dankesliste.
                    </x-oasis.price-card>
                </div>
                <p class="text-center text-[15px] text-ink-2">Alle Preise inklusive {{ config('oasis.vat_rate') }} % Umsatzsteuer. Nach dem Test wirst du nur Mitglied, wenn du aktiv zustimmst.</p>
            </div>
        </section>
    </div>

    <section aria-labelledby="inklusive-titel" class="o-section-s border-y border-paper-line bg-paper">
        <div class="o-container flex flex-col gap-9">
            <h2 id="inklusive-titel" class="o-display text-[clamp(28px,3vw,40px)] leading-[1.15] text-night">In jeder Mitgliedschaft</h2>
            <ul class="o-auto-grid gap-x-8 gap-y-5 text-lg leading-[1.5] [--min:260px]">
                @foreach (['Alle Funktionen, ohne Einschränkung', 'Eine Stimme bei jeder Abstimmung', 'Keine Werbung, kein Werbeprofil', 'Monatlich kündbar, der Jahresbeitrag zum Ende des Jahres'] as $item)
                    <li class="flex items-start gap-3">
                        <x-oasis.icon name="check" :size="22" :stroke="2.4" class="mt-0.5 text-spring-text" />
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section aria-label="Patenplätze und Gründungsmitgliedschaft" class="o-section-s">
        <div class="o-container o-auto-grid gap-6 [--min:420px]">
            <div class="flex flex-col gap-3.5 rounded-[28px] bg-dune p-9">
                <x-oasis.icon name="gift" :size="30" class="text-night" />
                <h2 class="text-2xl leading-[1.3] font-extrabold text-night">Auch {{ \App\Support\OasisFormat::euro($tiers['solidar']['monthly']) }} sind gerade zu viel?</h2>
                <p class="text-[17px] leading-[1.6] text-ink-2">Mitglieder und Partnerorganisationen verschenken Patenplätze. Schreib uns einfach, wir fragen nicht nach Nachweisen.</p>
                <a href="{{ route('oasis.hilfe') }}" class="o-link self-start text-[17px] text-night">Patenplatz anfragen</a>
            </div>
            <div class="flex flex-col gap-3.5 rounded-[28px] bg-night p-9 text-on-night">
                <x-oasis.logo :size="34" :wordmark="false" :wave-width="8" />
                <h2 class="text-2xl leading-[1.3] font-extrabold">Gründungsmitglied werden</h2>
                <p class="text-[17px] leading-[1.6] text-on-night-2">Die ersten {{ number_format(config('oasis.founding.seats'), 0, ',', '.') }} Mitglieder bekommen eine Preisgarantie: Dein Beitrag bleibt, solange du Mitglied bist.</p>
                <a href="{{ route('oasis.gruendung') }}" class="o-link self-start text-[17px] text-on-night">Mehr zur Gründungsphase</a>
            </div>
        </div>
    </section>

    <section aria-labelledby="pfaq-titel" class="pb-[clamp(72px,8vw,120px)]">
        <div class="o-container o-auto-grid items-start gap-x-[72px] gap-y-8 [--min:360px]">
            <h2 id="pfaq-titel" class="o-h2 text-night">Fragen zu Preisen</h2>
            <div class="flex flex-col border-t border-dune-line">
                <x-oasis.faq-item question="Bekomme ich als Mäzen mehr Funktionen?" :open="true">
                    <p>Nein. Alle Mitglieder bekommen dasselbe. Mäzen-Beiträge tragen Solidar- und Patenplätze mit, damit niemand wegen Geld draußen bleiben muss.</p>
                </x-oasis.faq-item>
                <x-oasis.faq-item question="Kann ich meine Stufe wechseln?">
                    <p>Ja, jederzeit zum nächsten Abrechnungszeitraum. Einmal im Jahr laden wir dich ein, deinen Beitrag kurz zu prüfen.</p>
                </x-oasis.faq-item>
                <x-oasis.faq-item question="Wie bezahle ich?">
                    <p>Per SEPA-Lastschrift, PayPal oder Kreditkarte. Wer die App nutzt, kann auch über den App Store oder Google Play zahlen.</p>
                </x-oasis.faq-item>
                <x-oasis.faq-item question="Kann ich eine Mitgliedschaft verschenken?">
                    <p>Ja, für 3 oder 12 Monate. Eine Geschenk-Mitgliedschaft endet von selbst und verlängert sich nicht.</p>
                </x-oasis.faq-item>
                <x-oasis.faq-item question="Ab welchem Alter kann ich Mitglied werden?">
                    <p>Ab 18. Mit 16 und 17 brauchst du die Zustimmung deiner Eltern, oder jemand schenkt dir eine Mitgliedschaft.</p>
                </x-oasis.faq-item>
                <x-oasis.faq-item question="Wie kündige ich?">
                    <p>Über „Verträge hier kündigen“ unten auf jeder Seite, auch ohne Anmeldung. Du bekommst sofort eine Bestätigung per E-Mail.</p>
                </x-oasis.faq-item>
            </div>
        </div>
    </section>
@endsection
