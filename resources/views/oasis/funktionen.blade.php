@extends('layouts.oasis', [
    'title' => 'Funktionen',
    'description' => 'Alles, was ein Netzwerk braucht. Nichts, was dich festhält: ein Feed, der aufhört, Tropfen und Weitertragen, Oasen, leise Zahlen und verlässliche Moderation.',
    'active' => 'funktionen',
])

@section('content')
    {{-- Intro --}}
    <section aria-labelledby="f-titel" class="pt-[clamp(56px,6vw,88px)] pb-[clamp(48px,5vw,72px)]">
        <div class="o-container flex flex-col gap-5">
            <p class="o-eyebrow">Funktionen</p>
            <h1 id="f-titel" class="o-h1 max-w-[15em] text-night">Alles, was ein Netzwerk braucht. Nichts, was dich festhält.</h1>
            <p class="max-w-[38em] text-[clamp(18px,1.5vw,20px)] leading-[1.6] text-ink-2">Oasis ist ein Ort für kurze Gedanken, Bilder und Gespräche. Gebaut für Menschen, die gern online sind, aber nicht ständig.</p>
        </div>
    </section>

    {{-- Die Quelle --}}
    <section aria-labelledby="quelle-titel" class="o-section-s border-t border-paper-line bg-paper">
        <div class="o-container o-auto-grid items-center gap-[clamp(40px,5vw,80px)] [--min:420px]">
            <div class="flex flex-col gap-[18px]">
                <p class="o-eyebrow text-spring-text">Die Quelle</p>
                <h2 id="quelle-titel" class="o-h2-s text-night">Ein Feed, der aufhört.</h2>
                <p class="max-w-[32em] text-lg leading-[1.6] text-ink-2">In der Quelle siehst du Tropfen von Menschen und Oasen, denen du folgst. Der Reihe nach, ohne eingeschobene Empfehlungen. Wenn du alles gesehen hast, zeigt Oasis dir das ehrlich an.</p>
                <x-oasis.check-list class="gap-2.5! text-[17px]" :items="[
                    'Chronologisch als Standard',
                    'Empfehlungen nur, wenn du sie einschaltest',
                    'Ein klares Ende statt endlosem Scrollen',
                ]" />
            </div>
            <figure aria-label="Beispiel: das Ende der Quelle" class="flex flex-col gap-3.5 rounded-[28px] bg-dune p-[clamp(24px,3vw,40px)]">
                <div class="flex flex-col gap-2 rounded-[20px] bg-paper p-[18px]">
                    <div class="flex items-center gap-3">
                        <div aria-hidden="true" class="flex size-10 flex-none items-center justify-center rounded-[20px] bg-night text-sm font-extrabold text-on-night">AK</div>
                        <div class="flex flex-col leading-[1.3]"><span class="text-base font-bold">Aylin Kaya</span><span class="text-sm text-muted">@aylin · vor 3 Std.</span></div>
                    </div>
                    <p class="text-[17px] leading-normal">Kennt jemand ein ruhiges Café zum Lernen in der Nähe der Unibibliothek?</p>
                </div>
                <div class="flex flex-col items-center gap-1.5 rounded-[20px] border-2 border-dashed border-spring bg-sand p-[22px] text-center">
                    <x-oasis.icon name="check-circle" :size="28" :stroke="1.9" class="text-spring-text" />
                    <span class="text-lg font-extrabold text-spring-text">Du bist auf dem neuesten Stand.</span>
                    <span class="text-base text-ink-2">Alles seit gestern Abend gesehen. Zeit zum Durchatmen.</span>
                </div>
            </figure>
        </div>
    </section>

    {{-- Tropfen und Weitertragen --}}
    <section aria-labelledby="tropfen-titel" class="o-section-s">
        <div class="o-container o-auto-grid items-center gap-[clamp(40px,5vw,80px)] [--min:420px]">
            <figure aria-label="Beispiel: ein weitergetragener Tropfen" class="flex flex-col gap-3 rounded-[28px] bg-dune p-[clamp(24px,3vw,40px)]">
                <p class="flex items-center gap-2 text-[15px] font-bold text-ink-2">
                    <x-oasis.icon name="repost" :size="18" :stroke="1.9" />
                    Jonas hat das weitergetragen</p>
                <div class="flex flex-col gap-3 rounded-[20px] bg-paper p-[18px]">
                    <p class="text-[17px] leading-normal">„Genau das hab ich gesucht. Wer kommt mit?“</p>
                    <div class="flex flex-col gap-2 rounded-2xl border border-dune-line p-4">
                        <div class="flex items-center gap-2.5">
                            <div aria-hidden="true" class="flex size-8 flex-none items-center justify-center rounded-2xl bg-spring text-xs font-extrabold text-sun-ink">SG</div>
                            <span class="text-[15px] font-bold">Oase Stadtgarten</span>
                        </div>
                        <p class="text-base leading-normal">Samstag Setzling-Tausch im Hof. Wer bringt Tomaten mit?</p>
                    </div>
                </div>
            </figure>
            <div class="flex flex-col gap-[18px]">
                <p class="o-eyebrow text-spring-text">Tropfen und Weitertragen</p>
                <h2 id="tropfen-titel" class="o-h2-s text-night">Kurz gesagt, gut weitergegeben.</h2>
                <p class="max-w-[32em] text-lg leading-[1.6] text-ink-2">Ein Tropfen ist ein kurzer Beitrag mit Text oder Bildern. Wer etwas weiterträgt, schreibt ein paar eigene Worte dazu. So wird aus Teilen ein Gespräch statt ein Reflex.</p>
            </div>
        </div>
    </section>

    {{-- Oasen --}}
    <section aria-labelledby="oasen-titel" class="o-section-s border-y border-paper-line bg-paper">
        <div class="o-container o-auto-grid items-center gap-[clamp(40px,5vw,80px)] [--min:420px]">
            <div class="flex flex-col gap-[18px]">
                <p class="o-eyebrow text-spring-text">Oasen</p>
                <h2 id="oasen-titel" class="o-h2-s text-night">Räume für das, was dich interessiert.</h2>
                <p class="max-w-[32em] text-lg leading-[1.6] text-ink-2">Oasen sind Themenräume mit Gastgeber:innen und eigenen Hausregeln. Vom Stadtgarten über analoge Fotografie bis zum Lesekreis. Einer Oase folgst du wie einem Menschen.</p>
            </div>
            <figure aria-label="Beispiele für Oasen" class="flex flex-col gap-3 rounded-[28px] bg-dune p-[clamp(24px,3vw,40px)]">
                @foreach ([
                    ['name' => 'Stadtgarten', 'text' => 'Gärtnern, tauschen, ernten', 'color' => 'bg-spring', 'followed' => true],
                    ['name' => 'Korn & Licht', 'text' => 'Analoge Fotografie', 'color' => 'bg-sun', 'followed' => false],
                    ['name' => 'Lesekreis', 'text' => 'Ein Buch im Monat, gemeinsam', 'color' => 'bg-night', 'followed' => false],
                ] as $oase)
                    <div class="flex items-center gap-3.5 rounded-[18px] bg-paper px-[18px] py-4">
                        <div aria-hidden="true" class="size-11 flex-none rounded-[14px] {{ $oase['color'] }}"></div>
                        <div class="flex flex-1 flex-col leading-[1.35]"><span class="text-[17px] font-extrabold">{{ $oase['name'] }}</span><span class="text-[15px] text-muted">{{ $oase['text'] }}</span></div>
                        @if ($oase['followed'])
                            <span class="rounded-full bg-night px-3.5 py-1.5 text-sm font-bold text-on-night">Gefolgt</span>
                        @else
                            <span class="rounded-full border border-night px-[13px] py-[5px] text-sm font-bold text-night">Folgen</span>
                        @endif
                    </div>
                @endforeach
            </figure>
        </div>
    </section>

    {{-- Leise Zahlen und Karawane --}}
    <section aria-labelledby="leise-titel" class="o-section-s">
        <div class="o-container o-auto-grid items-center gap-[clamp(40px,5vw,80px)] [--min:420px]">
            <figure aria-label="Beispiel: Reaktionen sind nur für dich sichtbar" class="flex flex-col gap-3 rounded-[28px] bg-dune p-[clamp(24px,3vw,40px)]">
                <div class="flex flex-col gap-3 rounded-[20px] bg-paper p-[18px]">
                    <div class="flex items-center gap-3">
                        <div aria-hidden="true" class="flex size-10 flex-none items-center justify-center rounded-[20px] bg-sun text-sm font-extrabold text-sun-ink">DU</div>
                        <div class="flex flex-col leading-[1.3]"><span class="text-base font-bold">Dein Tropfen</span><span class="text-sm text-muted">vor 2 Std.</span></div>
                    </div>
                    <p class="text-[17px] leading-normal">Erster Tag ohne Wecker seit Wochen. Ich hab vergessen, wie leise ein Sonntag sein kann.</p>
                    <div class="flex items-center gap-2.5 rounded-[14px] bg-sand px-3.5 py-3">
                        <x-oasis.icon name="lock" :size="18" :stroke="1.9" class="text-spring-text" />
                        <span class="text-[15px] text-ink"><b>Nur für dich sichtbar:</b> 14 Reaktionen, 3 Antworten</span>
                    </div>
                </div>
            </figure>
            <div class="flex flex-col gap-[18px]">
                <p class="o-eyebrow text-spring-text">Leise Zahlen und Karawane</p>
                <h2 id="leise-titel" class="o-h2-s text-night">Gesehen werden ohne Wettrennen.</h2>
                <p class="max-w-[32em] text-lg leading-[1.6] text-ink-2">Deine Karawane sind die Menschen, die dir folgen. Wie viele auf einen Tropfen reagiert haben, siehst nur du. So zählt, was gesagt wird, und nicht, wie laut.</p>
            </div>
        </div>
    </section>

    {{-- Sicherheit --}}
    <section aria-labelledby="schutz-titel" class="o-section-s border-y border-paper-line bg-paper">
        <div class="o-container o-auto-grid items-center gap-[clamp(40px,5vw,80px)] [--min:420px]">
            <div class="flex flex-col gap-[18px]">
                <p class="o-eyebrow text-spring-text">Sicherheit</p>
                <h2 id="schutz-titel" class="o-h2-s text-night">Moderation, auf die du dich verlassen kannst.</h2>
                <p class="max-w-[32em] text-lg leading-[1.6] text-ink-2">Melden dauert zwei Klicks. Unser Moderationsteam prüft Meldungen, und jede Entscheidung bekommst du begründet. Blockieren und Stummschalten wirken sofort.</p>
                <a href="{{ route('oasis.regeln') }}" class="o-link self-start text-[17px] text-night">Unsere Community-Regeln
                    <x-oasis.icon name="arrow-right" :size="18" :stroke="2" />
                </a>
            </div>
            <figure aria-label="Beispiel: einen Tropfen melden" class="rounded-[28px] bg-dune p-[clamp(24px,3vw,40px)]">
                <div class="flex flex-col gap-3.5 rounded-[20px] bg-paper p-[22px]">
                    <p class="flex items-center gap-2.5 text-lg font-extrabold text-night">
                        <x-oasis.icon name="flag" :size="20" :stroke="1.9" />
                        Tropfen melden</p>
                    <p class="text-[15px] text-ink-2">Was ist das Problem?</p>
                    <div class="flex flex-col gap-2">
                        <span class="flex items-center gap-3 rounded-[14px] border-2 border-night px-3.5 py-3 text-base font-bold"><span aria-hidden="true" class="box-border size-4 rounded-lg border-[5px] border-night"></span>Hass oder Hetze</span>
                        @foreach (['Belästigung', 'Falschinformation', 'Etwas anderes'] as $reason)
                            <span class="flex items-center gap-3 rounded-[14px] border border-dune-line px-3.5 py-3 text-base"><span aria-hidden="true" class="box-border size-4 rounded-lg border-2 border-muted"></span>{{ $reason }}</span>
                        @endforeach
                    </div>
                    <span class="inline-flex min-h-12 items-center justify-center rounded-full bg-night text-base font-bold text-on-night">Meldung absenden</span>
                </div>
            </figure>
        </div>
    </section>

    {{-- Geräte --}}
    <section aria-label="Geräte" class="py-[clamp(48px,5vw,72px)]">
        <div class="o-container o-auto-grid gap-6 [--min:300px]">
            @foreach ([
                ['icon' => 'phone', 'title' => 'Fürs Handy', 'text' => 'Als App für '.config('oasis.apps').', gebaut für kurze Momente zwischendurch.'],
                ['icon' => 'monitor', 'title' => 'Am Rechner', 'text' => 'Im Browser auf '.config('oasis.domain').', mit Platz für längere Gespräche.'],
                ['icon' => 'users', 'title' => 'Für Gäste lesbar', 'text' => 'Öffentliche Tropfen lassen sich teilen und ohne Konto lesen.'],
            ] as $device)
                <div class="flex items-start gap-[18px] rounded-3xl border border-dune-line p-7">
                    <x-oasis.icon :name="$device['icon']" :size="28" :stroke="1.8" class="text-night" />
                    <div class="flex flex-col gap-1.5">
                        <h3 class="text-xl font-extrabold text-night">{{ $device['title'] }}</h3>
                        <p class="text-base leading-[1.55] text-ink-2">{{ $device['text'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Call to action --}}
    <section aria-labelledby="f-cta" class="bg-night py-[clamp(64px,7vw,104px)] text-on-night">
        <div class="o-container flex flex-wrap items-center justify-between gap-x-12 gap-y-7">
            <div class="flex max-w-[640px] flex-col gap-3">
                <h2 id="f-cta" class="o-h2">Probier es selbst aus.</h2>
                <p class="text-lg leading-[1.6] text-on-night-2">{{ config('oasis.trial_days') }} Tage voller Zugang, ohne Zahlungsdaten. Der Test endet von selbst.</p>
            </div>
            <a href="{{ route('oasis.testen') }}" class="o-btn o-btn-primary">{{ config('oasis.trial_days') }} Tage kostenlos testen</a>
        </div>
    </section>
@endsection
