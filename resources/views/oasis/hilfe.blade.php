@extends('layouts.oasis', [
    'title' => 'Hilfe & FAQ',
    'description' => 'Antworten zu Test und Mitgliedschaft, Konto und Sicherheit, Moderation und Datenschutz bei Oasis.',
])

@php
    $tiers = config('oasis.tiers');
    $euro = fn ($value) => \App\Support\OasisFormat::euro($value);
    $trialDays = config('oasis.trial_days');
    $contactMail = config('oasis.mail.contact');

    $topics = [
        ['id' => 'mitgliedschaft', 'title' => 'Test & Mitgliedschaft', 'text' => 'Preise, Stufen, Kündigung'],
        ['id' => 'konto', 'title' => 'Konto & Sicherheit', 'text' => 'Alter, Profil, Passwort'],
        ['id' => 'moderation', 'title' => 'Moderation & Melden', 'text' => 'Melden, Entscheidungen'],
        ['id' => 'datenschutz', 'title' => 'Datenschutz', 'text' => 'Daten, Speicherort'],
    ];

    $faqs = [
        'mitgliedschaft' => [
            ['q' => 'Brauche ich für den Test Zahlungsdaten?', 'a' => "Nein. Du testest {$trialDays} Tage mit vollem Zugang, ganz ohne Zahlungsdaten.", 'open' => true],
            ['q' => "Was passiert nach den {$trialDays} Tagen?", 'a' => 'Dein Test endet von selbst, zwei Tage vorher erinnern wir dich. Wirst du Mitglied, bleibt alles, wie es ist. Wenn nicht, bleiben deine Tropfen noch '.config('oasis.retention_days').' Tage gespeichert, damit du sie herunterladen kannst.'],
            ['q' => 'Welche Stufe soll ich wählen?', 'a' => 'Alle bekommen dasselbe. '.$tiers['oase']['name'].' für '.$euro($tiers['oase']['monthly']).' deckt deinen eigenen Platz. Ist das gerade zu viel, wähl '.$tiers['solidar']['name'].' für '.$euro($tiers['solidar']['monthly']).'. Kannst du mehr geben, wähl '.$tiers['maezen']['name'].'.'],
            ['q' => 'Wie kündige ich?', 'a' => 'Über „Verträge hier kündigen“ unten auf jeder Seite, auch ohne Anmeldung. Monatsbeiträge enden zum Monatsende, Jahresbeiträge zum Ende des Jahres.'],
            ['q' => 'Wie bekomme ich einen Patenplatz?', 'a' => "Schreib uns an {$contactMail}. Wir fragen nicht nach Nachweisen."],
        ],
        'konto' => [
            ['q' => 'Ab welchem Alter kann ich mitmachen?', 'a' => 'Testen ab 16. Mitglied werden ab 18, mit 16 und 17 mit Zustimmung der Eltern oder über eine Geschenk-Mitgliedschaft. Lesen als Gast geht ohne Konto.'],
            ['q' => 'Wer kann meine Tropfen sehen?', 'a' => 'Das entscheidest du: öffentlich, also auch für Gäste lesbar, oder nur für Mitglieder.'],
            ['q' => 'Ich habe mein Passwort vergessen.', 'a' => 'Ab dem Start klickst du bei der Anmeldung auf „Passwort vergessen?“ und bekommst einen Link per E-Mail. Bis dahin gibt es noch keine Konten. Fragen beantworten wir unter '.$contactMail.'.'],
            ['q' => 'Wie lösche ich mein Konto?', 'a' => 'In den Einstellungen unter „Konto löschen“. Eine laufende Mitgliedschaft kündigst du vorher über „Verträge hier kündigen“.'],
        ],
        'moderation' => [
            ['q' => 'Wie melde ich einen Tropfen oder ein Profil?', 'a' => 'Über das Menü am Tropfen oder Profil, Punkt „Melden“. Auch ohne Konto kannst du Inhalte über unser Meldeformular melden.'],
            ['q' => 'Was passiert nach einer Meldung?', 'a' => 'Unser Moderationsteam prüft sie. Wenn wir etwas entfernen oder einschränken, bekommt die betroffene Person eine Begründung.'],
            ['q' => 'Ich halte eine Entscheidung für falsch.', 'a' => 'Du kannst jeder Entscheidung widersprechen. Dann schaut sie sich jemand anderes aus dem Team noch einmal an.'],
        ],
        'datenschutz' => [
            ['q' => 'Verkauft Oasis meine Daten?', 'a' => 'Nein. Oasis lebt von Mitgliedsbeiträgen, nicht von Daten oder Werbung.'],
            ['q' => 'Wo werden meine Daten gespeichert?', 'a' => config('oasis.server_location').'. Die Details stehen in der Datenschutzerklärung.'],
            ['q' => 'Wie bekomme ich eine Kopie meiner Daten?', 'a' => 'In den Einstellungen unter „Daten herunterladen“ oder per E-Mail an '.config('oasis.mail.privacy').'.'],
        ],
    ];
@endphp

@section('content')
    {{-- Intro with search --}}
    <section aria-labelledby="h-titel" class="bg-dune pt-[clamp(56px,6vw,88px)] pb-[clamp(40px,4vw,56px)]">
        <div class="o-container flex flex-col gap-5">
            <p class="o-eyebrow">Hilfe</p>
            <h1 id="h-titel" class="o-h1 text-night">Wie können wir helfen?</h1>
            {{-- The search only filters the questions on this page, so it stays hidden until JS (help-search module) shows it. --}}
            <form role="search" aria-label="Hilfe durchsuchen" class="max-w-[680px]" data-oasis="help-search" hidden>
                <label for="h-suche" class="sr-only">Suche in der Hilfe</label>
                <div class="flex min-h-[60px] items-center gap-3 rounded-full border border-field-line bg-field px-5">
                    <x-oasis.icon name="search" :size="22" :stroke="2" class="text-ink-2" />
                    <input id="h-suche" type="search" placeholder="z. B. kündigen, Patenplatz, Passwort" autocomplete="off"
                           class="min-h-14 min-w-0 flex-1 border-none bg-transparent text-lg text-ink">
                </div>
                <p data-help-status role="status" class="mt-3 text-base font-semibold text-ink-2 empty:hidden"></p>
            </form>
        </div>
    </section>

    {{-- Topics --}}
    <section aria-label="Themen" class="pt-[clamp(32px,4vw,48px)]">
        <div class="o-container">
            <nav aria-label="Hilfethemen" class="o-auto-grid gap-4 [--min:240px]">
                @foreach ($topics as $topic)
                    <a href="#{{ $topic['id'] }}" class="flex flex-col gap-1 rounded-[20px] border border-paper-line bg-paper px-6 py-[22px] text-ink no-underline">
                        <span class="text-lg font-extrabold text-night">{{ $topic['title'] }}</span>
                        <span class="text-[15px] text-ink-2">{{ $topic['text'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

    {{-- FAQ --}}
    <section aria-label="Häufige Fragen" class="o-section-s">
        <div class="o-container flex flex-col gap-14">
            @foreach ($topics as $topic)
                <div id="{{ $topic['id'] }}" data-help-group class="o-auto-grid scroll-mt-6 items-start gap-x-[72px] gap-y-4 [--min:320px]">
                    <h2 class="o-display text-[clamp(28px,3vw,40px)] leading-[1.15] text-night">{{ $topic['title'] }}</h2>
                    <div class="flex flex-col border-t border-dune-line">
                        @foreach ($faqs[$topic['id']] as $faq)
                            <x-oasis.faq-item :question="$faq['q']" :open="$faq['open'] ?? false" class="mb-5! max-w-[40em]!">
                                <p>{{ $faq['a'] }}</p>
                            </x-oasis.faq-item>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Contact --}}
    <section aria-labelledby="kontakt-titel" class="pb-[clamp(72px,8vw,120px)]">
        <div class="o-container">
            <div class="flex flex-wrap items-center justify-between gap-x-10 gap-y-5 rounded-[28px] bg-night p-[clamp(28px,3.5vw,44px)] text-on-night">
                <div class="flex max-w-[620px] flex-col gap-2">
                    <h2 id="kontakt-titel" class="o-display text-[clamp(26px,2.6vw,36px)] leading-[1.2]">Nicht gefunden, was du suchst?</h2>
                    <p class="text-[17px] leading-[1.6] text-on-night-2">Schreib uns an {{ $contactMail }}. Wir antworten in der Regel innerhalb von {{ config('oasis.support_reply_days') }} Werktagen.</p>
                </div>
                <a href="{{ route('oasis.melden') }}" class="inline-flex min-h-12 items-center rounded-full border border-on-night-muted px-[22px] text-base font-bold text-on-night no-underline">Rechtswidrige Inhalte melden</a>
            </div>
        </div>
    </section>
@endsection
