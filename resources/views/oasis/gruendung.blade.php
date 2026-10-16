{{--
    Founding waitlist page from Gruendung.dc.html at oasis.gruendung.
    Uses config values and web-session errors/old input; POST goes to WaitlistController for double opt-in.
    The counter stays unpublished rather than showing invented numbers. No JavaScript required.
--}}
@extends('layouts.oasis', ['title' => 'Gründungsmitglied werden', 'description' => 'Gründungsmitglied werden bei Oasis.', 'announce' => false, 'noindex' => false])
@section('content')

    <section aria-labelledby="gr-titel" class="[padding:clamp(56px,_6vw,_88px)_0] bg-night text-on-night">
        <div class="o-container o-auto-grid [--min:420px] [gap:clamp(40px,_5vw,_72px)] items-center">
            <div class="flex flex-col gap-[22px]">
                <p class="o-eyebrow o-eyebrow-night">Gründungsphase</p>
                <h1 id="gr-titel" class="m-0 o-display font-medium [font-size:clamp(44px,_6vw,_84px)] [line-height:1.02] [letter-spacing:-0.02em] [text-wrap:balance]">Sei von Anfang an dabei.</h1>
                <p class="m-0 [max-width:32em] [font-size:clamp(18px,_1.5vw,_20px)] [line-height:1.6] text-on-night-2">@if (config('oasis.launch_date'))Oasis startet am {{ \Illuminate\Support\Carbon::parse(config('oasis.launch_date'))->format('d.m.Y') }}.@else Oasis startet bald, den Termin geben wir hier bekannt.@endif Die ersten {{ number_format(config('oasis.founding.seats'), 0, ',', '.') }} Mitglieder werden Gründungsmitglieder: mit Preisgarantie und einer Stimme in der allerersten Abstimmung.</p>
                <p class="text-on-night-muted">Veröffentlichen wir ab dem Start</p>
            </div>
            <livewire:oasis.waitlist-signup />
        </div>
    </section>

    <section aria-labelledby="vorteile-titel" class="[padding:clamp(64px,_7vw,_104px)_0]">
        <div class="o-container flex flex-col gap-[40px]">
            <h2 id="vorteile-titel" class="m-0 o-display font-medium [font-size:clamp(30px,_3.2vw,_46px)] [line-height:1.12] [letter-spacing:-0.015em] text-night">Was Gründungsmitglieder bekommen</h2>
            <div class="o-auto-grid [--min:240px] gap-[24px]">
                <div class="flex flex-col gap-[14px] [padding:28px] bg-paper rounded-[24px]">
                    <div aria-hidden="true" class="[width:48px] [height:48px] rounded-[24px] bg-dune text-night flex items-center justify-center">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2">
                            </rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4">
                            </path>
                        </svg>
                    </div>
                    <h3 class="m-0 text-[21px] [line-height:1.3] font-extrabold text-night">Preisgarantie</h3>
                    <p class="m-0 text-[16px] [line-height:1.6] text-ink-2">Dein Beitrag bleibt, solange du Mitglied bist.</p>
                </div>
                <div class="flex flex-col gap-[14px] [padding:28px] bg-paper rounded-[24px]">
                    <div aria-hidden="true" class="[width:48px] [height:48px] rounded-[24px] bg-dune text-night flex items-center justify-center">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14">
                            </path>
                            <path d="M12 5l7 7-7 7">
                            </path>
                        </svg>
                    </div>
                    <h3 class="m-0 text-[21px] [line-height:1.3] font-extrabold text-night">Früher Zugang</h3>
                    <p class="m-0 text-[16px] [line-height:1.6] text-ink-2">Die Startliste kommt in Wellen herein, noch vor dem offiziellen Start.</p>
                </div>
                <div class="flex flex-col gap-[14px] [padding:28px] bg-paper rounded-[24px]">
                    <div aria-hidden="true" class="[width:48px] [height:48px] rounded-[24px] bg-dune text-night flex items-center justify-center">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 11l3 3L22 4">
                            </path>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11">
                            </path>
                        </svg>
                    </div>
                    <h3 class="m-0 text-[21px] [line-height:1.3] font-extrabold text-night">Die erste Abstimmung</h3>
                    <p class="m-0 text-[16px] [line-height:1.6] text-ink-2">Du entscheidest mit, welche Funktion als Erstes nach dem Start kommt.</p>
                </div>
                <div class="flex flex-col gap-[14px] [padding:28px] bg-paper rounded-[24px]">
                    <div aria-hidden="true" class="[width:48px] [height:48px] rounded-[24px] bg-dune flex items-center justify-center">
                        <svg viewBox="0 0 120 120" width="30" height="30" aria-hidden="true" focusable="false">
                            <path d="M26 62 A34 34 0 0 1 94 62 Z" fill="var(--color-sun)">
                            </path>
                            <path d="M14 76 q11.5 -9 23 0 t23 0 t23 0 t23 0" fill="none" stroke="var(--color-spring)" stroke-width="9" stroke-linecap="round">
                            </path>
                            <path d="M32 92 q9.5 -8 19 0 t19 0 t19 0" fill="none" stroke="var(--color-spring)" stroke-width="9" stroke-linecap="round">
                            </path>
                        </svg>
                    </div>
                    <h3 class="m-0 text-[21px] [line-height:1.3] font-extrabold text-night">Gründungsabzeichen</h3>
                    <p class="m-0 text-[16px] [line-height:1.6] text-ink-2">Freiwillig an deinem Profil und jederzeit ausblendbar.</p>
                </div>
            </div>
        </div>
    </section>

    <section aria-labelledby="maezene-titel" class="[padding:clamp(64px,_7vw,_104px)_0] bg-dune">
        <div class="o-container o-auto-grid [--min:420px] [gap:32px_72px] items-center">
            <div class="flex flex-col gap-[16px]">
                <h2 id="maezene-titel" class="m-0 o-display font-medium [font-size:clamp(30px,_3.2vw,_46px)] [line-height:1.12] [letter-spacing:-0.015em] text-night">Gründungs-Mäzen:innen</h2>
                <p class="m-0 [max-width:32em] text-[18px] [line-height:1.6] text-ink-2">Wer als Mäzen startet, kann auf Wunsch auf unserer Dankesliste stehen. Ein sichtbares Zeichen, dass Oasis von Anfang an getragen wird und niemand wegen Geld draußen bleiben muss.</p>
            </div>
            <div class="flex flex-col gap-[14px] [padding:32px] bg-paper rounded-[28px]">
                <p class="m-0 text-[14px] font-bold [letter-spacing:2px] uppercase text-muted">Dankesliste</p>
                <p class="m-0 [padding:22px] border-2 border-dashed border-field-line rounded-[16px] text-[17px] [line-height:1.6] text-ink-2">Die Dankesliste veröffentlichen wir ab dem Start.</p>
                <p class="m-0 text-[15px] text-ink-2">Die Nennung ist freiwillig und lässt sich jederzeit zurücknehmen.</p>
            </div>
        </div>
    </section>

    <section aria-labelledby="gfaq-titel" class="[padding:clamp(64px,_7vw,_104px)_0]">
        <div class="o-container o-auto-grid [--min:360px] [gap:32px_72px] items-start">
            <h2 id="gfaq-titel" class="m-0 o-display font-medium [font-size:clamp(30px,_3.2vw,_46px)] [line-height:1.12] [letter-spacing:-0.015em] text-night">Fragen zur Gründungsphase</h2>
            <div class="flex flex-col border-t border-dune-line">
                <x-oasis.faq-item question="Was kostet die Gründungsmitgliedschaft?" :open="true">
                    <p>Dasselbe wie jede Mitgliedschaft: Solidar {{ \App\Support\OasisFormat::euro(config('oasis.tiers.solidar.monthly')) }}, Oase {{ \App\Support\OasisFormat::euro(config('oasis.tiers.oase.monthly')) }} oder Mäzen ab {{ \App\Support\OasisFormat::euro(config('oasis.tiers.maezen.monthly')) }} im Monat. Der Unterschied: Dein Preis bleibt, solange du dabei bist.</p>
                </x-oasis.faq-item>
                <x-oasis.faq-item question="Muss ich mich jetzt festlegen?">
                    <p>Nein. Die Startliste ist kostenlos und unverbindlich. Du startest wie alle mit {{ config('oasis.trial_days') }} Tagen Test und entscheidest danach.</p>
                </x-oasis.faq-item>
                <x-oasis.faq-item question="Was passiert, wenn alle Plätze vergeben sind?">
                    <p>Dann kannst du trotzdem mitmachen, zu den normalen Preisen, nur ohne Preisgarantie.</p>
                </x-oasis.faq-item>
            </div>
        </div>
    </section>

@endsection
