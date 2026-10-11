{{-- Startseite: route oasis.home (/), template Main.dc.html. Prices and trial days from config/oasis.php. --}}
@extends('layouts.oasis', [
    'description' => 'Oasis zeigt dir, was deine Leute teilen: in Ruhe, der Reihe nach und mit einem Ende. Keine Werbung, kein Algorithmus, der dich festhält.',
])

@php
    $tiers = config('oasis.tiers');
    $trialDays = config('oasis.trial_days');
@endphp

@section('content')
    <section aria-labelledby="hero-titel" class="overflow-hidden pt-[clamp(48px,6vw,88px)] pb-[clamp(64px,8vw,112px)]">
        <div class="o-container o-auto-grid items-center gap-[clamp(40px,5vw,72px)] [--min:440px]">
            <div class="flex flex-col gap-7">
                <p class="o-eyebrow">Das werbefreie soziale Netzwerk</p>
                <h1 id="hero-titel" class="o-h1-home text-night">Social Media, das dir <em class="italic text-sun-text">Zeit zurückgibt.</em></h1>
                <p class="o-lead max-w-[34em] text-ink-2">Oasis zeigt dir, was deine Leute teilen: in Ruhe, der Reihe nach und mit einem Ende. Keine Werbung, kein Algorithmus, der dich festhält. Getragen von Mitgliedern statt von Werbekunden.</p>
                <div class="flex flex-wrap items-center gap-x-7 gap-y-4">
                    <a href="{{ route('oasis.testen') }}" class="o-btn o-btn-primary">{{ $trialDays }} Tage kostenlos testen</a>
                    <a href="{{ route('oasis.entdecken') }}" class="o-link text-[17px] text-night">Erst mal reinlesen <x-oasis.icon name="arrow-right" :size="18" :stroke="2" /></a>
                </div>
                <p class="flex items-center gap-2 text-[15px] text-ink-2">
                    <x-oasis.icon name="check" :size="18" :stroke="2.2" class="text-spring-text" />
                    <span>Keine Zahlungsdaten nötig. Der Test endet von selbst.</span>
                </p>
            </div>

            <div class="relative flex min-h-[620px] items-center justify-center">
                <svg viewBox="0 0 600 560" aria-hidden="true" focusable="false" class="absolute top-5 left-1/2 h-auto w-[min(580px,100%)] -translate-x-1/2">
                    <path d="M70 300 A230 230 0 0 1 530 300 Z" fill="#E06A43"/>
                    <path d="M20 370 q50 -38 100 0 t100 0 t100 0 t100 0 t100 0 t100 0" fill="none" stroke="#2E9E95" stroke-width="18" stroke-linecap="round"/>
                    <path d="M90 440 q50 -38 100 0 t100 0 t100 0 t100 0" fill="none" stroke="#2E9E95" stroke-width="18" stroke-linecap="round" opacity="0.6"/>
                    <path d="M190 510 q50 -38 100 0 t100 0" fill="none" stroke="#2E9E95" stroke-width="18" stroke-linecap="round" opacity="0.35"/>
                </svg>
                <figure aria-label="Beispiel: die Quelle in der Oasis-App" class="relative flex w-80 max-w-full flex-col gap-3 rounded-[48px] border-[9px] border-ink bg-paper px-4 pt-[22px] pb-[18px] shadow-float">
                    <div class="flex items-center justify-between px-1">
                        <span class="o-display text-[26px] font-semibold text-night">Quelle</span>
                        <x-oasis.logo :size="30" :wordmark="false" :wave-width="9" />
                    </div>
                    <div class="flex gap-1.5 px-0.5">
                        <span class="rounded-full bg-night px-3 py-[5px] text-[13px] font-bold text-on-night">Gefolgt</span>
                        <span class="rounded-full border border-dune-line px-[11px] py-1 text-[13px] font-semibold text-ink-2">Oasen</span>
                        <span class="rounded-full border border-dune-line px-[11px] py-1 text-[13px] font-semibold text-ink-2">Neu</span>
                    </div>
                    <x-oasis.post name="Lena Berger" handle="lena" time="vor 12 Min." initials="LB" avatar="sun">Handy blieb heute am See in der Tasche. Herrlich.</x-oasis.post>
                    <x-oasis.post name="Oase Stadtgarten" handle="stadtgarten" time="vor 1 Std." initials="SG" avatar="spring">Samstag Setzling-Tausch im Hof. Wer bringt Tomaten mit?</x-oasis.post>
                    <div class="flex flex-col gap-0.5 rounded-[20px] border-2 border-dashed border-spring p-3.5 text-center">
                        <span class="text-sm font-bold text-spring-text">Du bist auf dem neuesten Stand.</span>
                        <span class="text-sm text-ink-2">Zeit zum Durchatmen.</span>
                    </div>
                </figure>
            </div>
        </div>
    </section>

    <section aria-labelledby="anders-titel" class="o-section border-y border-paper-line bg-paper">
        <div class="o-container flex flex-col gap-12">
            <div class="flex max-w-[760px] flex-col gap-4">
                <p class="o-eyebrow">Was Oasis anders macht</p>
                <h2 id="anders-titel" class="o-h2 text-night">Alles, was Social Media gut macht. Ohne den Sog.</h2>
            </div>
            <div class="o-auto-grid gap-6 [--min:240px]">
                @foreach ([
                    ['check-circle', 'Ein Feed mit Ende', 'Du siehst, was Menschen und Oasen teilen, denen du folgst, der Reihe nach. Wenn du alles gesehen hast, sagt Oasis es dir.'],
                    ['eye-off', 'Leise Zahlen', 'Wie viele reagiert haben, sieht nur, wer geschrieben hat. Kein öffentliches Wettrennen um Likes.'],
                    ['shield', 'Moderation, die da ist', 'Klare Regeln, Menschen in der Moderation und eine Begründung für jede Entscheidung.'],
                    ['check-square', 'Eine Stimme für alle', 'Jedes Mitglied stimmt mit ab, was als Nächstes kommt. Egal, wie viel es beiträgt.'],
                ] as [$icon, $heading, $text])
                    <div class="flex flex-col gap-3.5 rounded-[24px] bg-sand p-7">
                        <div aria-hidden="true" class="flex size-12 items-center justify-center rounded-full bg-dune text-night">
                            <x-oasis.icon :name="$icon" />
                        </div>
                        <h3 class="text-[21px] leading-[1.3] font-extrabold text-night">{{ $heading }}</h3>
                        <p class="text-base leading-[1.6] text-ink-2">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section aria-labelledby="warum-titel" class="o-section bg-night text-on-night">
        <div class="o-container flex flex-col gap-12">
            <div class="flex max-w-[900px] flex-col gap-[18px]">
                <p class="o-eyebrow o-eyebrow-night">Warum Oasis Geld kostet</p>
                <h2 id="warum-titel" class="o-h2 leading-[1.12] text-on-night">Andere verdienen an deiner Aufmerksamkeit. Wir an deinem Beitrag.</h2>
                <p class="max-w-[40em] text-[19px] leading-[1.6] text-on-night-2">Ein Netzwerk, das von Werbung lebt, braucht jede Minute, die du bleibst. Oasis lebt von seinen Mitgliedern und kann es sich deshalb leisten, ruhig zu sein.</p>
            </div>
            <div class="o-auto-grid gap-6 [--min:360px]">
                <div class="flex flex-col gap-[18px] rounded-[24px] border border-night-line bg-night-2 p-8">
                    <p class="text-sm font-bold tracking-[2px] text-on-night-muted uppercase">Werbefinanzierte Netzwerke</p>
                    <ul class="flex flex-col gap-3.5 text-lg leading-[1.5] text-on-night-2">
                        @foreach (['verdienen an der Zeit, die du dort verbringst', 'sortieren den Feed so, dass du bleibst', 'nutzen Daten über dich für passende Anzeigen'] as $item)
                            <li class="flex items-start gap-3"><span aria-hidden="true" class="mt-[13px] h-0.5 w-2.5 flex-none bg-on-night-muted"></span><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                </div>
                <div class="flex flex-col gap-[18px] rounded-[24px] bg-sand p-8 text-ink">
                    <p class="text-sm font-bold tracking-[2px] text-sun-text uppercase">Oasis</p>
                    <x-oasis.check-list class="text-lg leading-[1.5]" :items="[
                        'lebt von Mitgliedsbeiträgen ab '.\App\Support\OasisFormat::euro($tiers['solidar']['monthly']).' im Monat',
                        'zeigt dir alles der Reihe nach, mit einem Ende',
                        'braucht kein Werbeprofil und verkauft keine Daten',
                    ]" />
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-x-8 gap-y-[18px] rounded-[20px] border border-night-line px-7 py-6">
                <p class="flex flex-[1_1_520px] items-start gap-3.5 text-[17px] leading-[1.55] text-on-night">
                    <x-oasis.icon name="lock" :size="22" :stroke="1.9" class="mt-0.5 text-sun-on-night" />
                    <span><b>Unser Versprechen:</b> keine Werbung, kein Werbeprofil, kein Datenverkauf. Ändern könnten das nur die Mitglieder selbst.</span>
                </p>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <a href="{{ route('oasis.manifest') }}" class="o-link text-base text-on-night">Zum Manifest</a>
                    <a href="{{ route('oasis.transparenz') }}" class="o-link text-base text-on-night">Jeder Euro offen gelegt</a>
                </div>
            </div>
        </div>
    </section>

    <section aria-labelledby="weg-titel" class="o-section">
        <div class="o-container flex flex-col gap-12">
            <div class="flex max-w-[760px] flex-col gap-4">
                <p class="o-eyebrow">In drei Schritten</p>
                <h2 id="weg-titel" class="o-h2 text-night">Erst erleben, dann entscheiden.</h2>
            </div>
            <ol class="o-auto-grid gap-6 [--min:300px]">
                @foreach ([
                    ['Reinlesen', 'Öffentliche Tropfen und Profile kannst du ohne Konto lesen. So siehst du, wer schon da ist.'],
                    [$trialDays.' Tage mitmachen', 'Voller Zugang, ohne Zahlungsdaten. Der Test endet von selbst, und wir erinnern dich vorher.'],
                    ['Mitglied werden', 'Ab '.\App\Support\OasisFormat::euro($tiers['solidar']['monthly']).' im Monat. Alle bekommen dasselbe, du wählst nur, wie viel du beiträgst.'],
                ] as $i => [$heading, $text])
                    <li class="flex flex-col gap-3 rounded-[24px] border border-dune-line px-7 py-8">
                        <span aria-hidden="true" class="o-display text-[56px] leading-none text-sun-text">{{ $i + 1 }}</span>
                        <h3 class="text-[22px] leading-[1.3] font-extrabold text-night">{{ $heading }}</h3>
                        <p class="text-base leading-[1.6] text-ink-2">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
            <div class="flex flex-wrap items-center justify-between gap-x-8 gap-y-4 rounded-[20px] bg-dune px-6 py-5">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2.5">
                    @foreach ($tiers as $tier)
                        <span @class([
                            'rounded-full px-4 py-2 text-base font-bold',
                            'bg-night text-on-night' => $tier['default'] ?? false,
                            'bg-paper' => ! ($tier['default'] ?? false),
                        ])>{{ $tier['name'] }} {{ $tier['min'] ? 'ab ' : '' }}{{ \App\Support\OasisFormat::euro($tier['monthly']) }}</span>
                    @endforeach
                    <span class="text-base text-ink-2">pro Monat · alle Funktionen in jeder Stufe · monatlich kündbar</span>
                </div>
                <a href="{{ route('oasis.preise') }}" class="o-link text-[17px] text-night">Preise im Detail <x-oasis.icon name="arrow-right" :size="18" :stroke="2" /></a>
            </div>
        </div>
    </section>

    <section aria-labelledby="gemeinsam-titel" class="o-section bg-dune">
        <div class="o-container o-auto-grid items-center gap-[clamp(40px,5vw,72px)] [--min:420px]">
            <div class="flex flex-col gap-5">
                <p class="o-eyebrow">Gemeinsam starten</p>
                <h2 id="gemeinsam-titel" class="o-h2 text-night">Zieh nicht allein um.</h2>
                <p class="max-w-[32em] text-lg leading-[1.6] text-ink-2">Ein Netzwerk ist nur so gut wie die Menschen darin. Lade deine WG, deine Fachschaft oder dein Team ein. Euer Test beginnt erst, wenn alle da sind.</p>
                <a href="{{ route('oasis.gemeinsam') }}" class="o-btn o-btn-md o-btn-outline self-start">So funktioniert der Gruppenstart</a>
            </div>
            <figure aria-label="Beispiel: eine Gruppe startet gemeinsam" class="flex w-full max-w-[440px] flex-col gap-[18px] justify-self-center rounded-[28px] bg-paper p-7 shadow-card">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex flex-col leading-[1.3]">
                        <span class="text-[19px] font-extrabold text-night">WG Kastanienallee</span>
                        <span class="text-[15px] text-muted">4 von 5 sind dabei</span>
                    </div>
                    <div aria-hidden="true" class="flex">
                        @foreach ([['LB', 'bg-sun text-sun-ink'], ['JW', 'bg-spring text-sun-ink'], ['MS', 'bg-night text-on-night'], ['AK', 'bg-dune-line text-night']] as $i => [$initials, $colors])
                            <span @class(['flex size-10 items-center justify-center rounded-full border-[3px] border-paper text-[13px] font-extrabold', $colors, '-ml-3' => $i > 0])>{{ $initials }}</span>
                        @endforeach
                        <span class="-ml-3 flex size-10 items-center justify-center rounded-full border-2 border-dashed border-muted bg-paper text-lg font-bold text-muted">+</span>
                    </div>
                </div>
                <div aria-hidden="true" class="flex gap-1.5">
                    @foreach ([true, true, true, true, false] as $joined)
                        <span @class(['h-2 flex-1 rounded', 'bg-spring' => $joined, 'bg-dune-line' => ! $joined])></span>
                    @endforeach
                </div>
                <p class="text-[15px] leading-[1.5] text-ink-2">Euer gemeinsamer Test startet, sobald alle angenommen haben.</p>
                <span class="inline-flex min-h-12 items-center justify-center gap-2.5 rounded-full bg-night text-base font-bold text-on-night">
                    <x-oasis.icon name="link" :size="18" :stroke="2" />
                    Einladungslink kopieren
                </span>
            </figure>
        </div>
    </section>

    <section aria-labelledby="faq-titel" class="o-section">
        <div class="o-container o-auto-grid items-start gap-x-[72px] gap-y-10 [--min:360px]">
            <div class="flex flex-col gap-4">
                <p class="o-eyebrow">Fragen</p>
                <h2 id="faq-titel" class="o-h2 text-night">Was du wissen willst, bevor du startest.</h2>
                <a href="{{ route('oasis.hilfe') }}" class="o-link self-start text-[17px] text-night">Alle Fragen und Antworten <x-oasis.icon name="arrow-right" :size="18" :stroke="2" /></a>
            </div>
            <div class="flex flex-col border-t border-dune-line">
                <x-oasis.faq-item question="Brauche ich für den Test Zahlungsdaten?" :open="true">
                    <p>Nein. Du testest {{ $trialDays }} Tage mit vollem Zugang. Danach endet der Test von selbst. Mitglied wirst du nur, wenn du dich aktiv dafür entscheidest.</p>
                </x-oasis.faq-item>
                <x-oasis.faq-item question="Warum ist Oasis nicht kostenlos?">
                    <p>Weil wir sonst Werbung verkaufen müssten, und dann wäre deine Aufmerksamkeit das Produkt. Mit Mitgliedsbeiträgen bleibt Oasis werbefrei und unabhängig.</p>
                </x-oasis.faq-item>
                <x-oasis.faq-item question="Was, wenn ich mir {{ \App\Support\OasisFormat::euro($tiers['oase']['monthly']) }} nicht leisten kann?">
                    <p>Dann wähl {{ $tiers['solidar']['name'] }} für {{ \App\Support\OasisFormat::euro($tiers['solidar']['monthly']) }}, ohne Nachweis und für andere unsichtbar. Und wenn auch das gerade nicht geht, schreib uns: Mitglieder verschenken Patenplätze.</p>
                </x-oasis.faq-item>
            </div>
        </div>
    </section>

    <section aria-labelledby="cta-titel" class="bg-sun py-[clamp(64px,7vw,104px)] text-sun-ink">
        <div class="o-container flex flex-col items-center gap-[22px] text-center">
            <h2 id="cta-titel" class="o-h1 leading-[1.05]">Komm in die Oase.</h2>
            <p class="max-w-[30em] text-[19px] leading-[1.55]">{{ $trialDays }} Tage voller Zugang. Keine Zahlungsdaten, und aus dem Test wird nie von selbst ein Abo.</p>
            <a href="{{ route('oasis.testen') }}" class="o-btn o-btn-dark px-[30px]">{{ $trialDays }} Tage kostenlos testen</a>
        </div>
    </section>
@endsection
