{{--
    Public profile in the guest preview: /profil/{handle}, route oasis.profil, template Profil.dc.html.
    Data comes from ProfileShow::render() -> App\Support\DemoProfiles (sample profiles only).

    Variables:
      $handle   string  lowercase handle from the URL
      $profile  array   sample profile (name, first_name, initials, bio, location, since, founding_member, oasen)
--}}

<div class="flex flex-col gap-6">
<p role="status" aria-live="polite">{{ $postStatus }}</p>
<p wire:loading class="text-muted">Wird geladen …</p>
    <div role="region" aria-label="Hinweis für Gäste" class="border-b border-dune-line bg-dune">
        <div class="o-container flex flex-wrap items-center justify-between gap-x-6 gap-y-3 py-[18px]">
            <p class="m-0 flex items-center gap-3 text-base leading-normal">
                <x-oasis.icon name="eye" :size="22" class="text-night" />
                <span><b>Du liest als Gast.</b> Um {{ $profile['first_name'] }} zu folgen oder zu antworten, starte deinen kostenlosen Test.</span>
            </p>
            <a wire:navigate href="{{ route('register') }}" class="o-btn o-btn-md o-btn-primary text-base font-extrabold">{{ config('oasis.trial_days') }} Tage kostenlos testen</a>
        </div>
    </div>

    <div class="pb-[clamp(56px,6vw,88px)]">
        <div aria-hidden="true" class="h-[180px] overflow-hidden bg-spring">
            <svg viewBox="0 0 1440 180" preserveAspectRatio="xMidYMid slice" focusable="false" class="h-[180px] w-full">
                <circle cx="1080" cy="120" r="80" fill="#E06A43"/>
                <path d="M0 120 q90 -36 180 0 t180 0 t180 0 t180 0 t180 0 t180 0 t180 0 t180 0 V180 H0 Z" fill="#0E2B2F" opacity="0.5"/>
                <path d="M0 150 q90 -36 180 0 t180 0 t180 0 t180 0 t180 0 t180 0 t180 0 t180 0 V180 H0 Z" fill="#0E2B2F"/>
            </svg>
        </div>

        <div class="o-container flex flex-wrap items-start gap-8">

            <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-6">

                <section aria-labelledby="profil-name" class="-mt-14 flex flex-col gap-3.5">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <span aria-hidden="true" class="o-display flex size-28 items-center justify-center rounded-full border-[6px] border-sand bg-night text-[40px] font-semibold text-on-night">{{ $profile['initials'] }}</span>
                        <div class="flex items-center gap-2.5">
                            <button type="button" data-oasis="share" data-url="{{ route('oasis.profil', $handle) }}" aria-label="Profil teilen" hidden
                                    class="inline-flex size-12 cursor-pointer items-center justify-center rounded-full border border-night bg-transparent text-night">
                                <x-oasis.icon name="link" :size="20" :stroke="1.9" />
                            </button>
                            <a wire:navigate href="{{ route('register') }}" class="o-btn o-btn-md o-btn-dark px-[26px] text-base font-extrabold">Folgen</a>
                        </div>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <h1 id="profil-name" class="o-display m-0 text-[40px] leading-[1.15] font-semibold text-night">{{ $profile['name'] }}</h1>
                        <p class="m-0 text-base text-muted">{{ '@'.strtolower($handle) }}</p>
                    </div>
                    <p class="m-0 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[15px] leading-normal text-ink-2">
                        <span class="rounded-full bg-night px-3 py-[3px] text-[13px] font-extrabold tracking-[0.5px] text-on-night uppercase">Beispielansicht</span>
                        <span>Dieses Profil und seine Tropfen sind erfundene Beispiele. Echte Profile gibt es ab dem Start.</span>
                    </p>
                    <p class="m-0 max-w-[36em] text-lg leading-[1.55]">{{ $profile['bio'] }}</p>
                    <ul class="m-0 flex list-none flex-wrap gap-x-[18px] gap-y-2 p-0 text-[15px] text-ink-2">
                        <li class="flex items-center gap-1.5"><x-oasis.icon name="map-pin" :size="16" :stroke="2" />{{ $profile['location'] }}</li>
                        <li class="flex items-center gap-1.5"><x-oasis.icon name="calendar" :size="16" :stroke="2" />dabei seit {{ $profile['since'] }}</li>
                        @if ($profile['founding_member'])
                            <li class="flex items-center gap-1.5 font-bold text-ink">
                                <svg viewBox="0 0 120 120" width="18" height="18" aria-hidden="true" focusable="false" class="flex-none"><path d="M26 62 A34 34 0 0 1 94 62 Z" fill="#E06A43"/><path d="M14 76 q11.5 -9 23 0 t23 0 t23 0 t23 0" fill="none" stroke="#2E9E95" stroke-width="10" stroke-linecap="round"/></svg>Gründungsmitglied
                            </li>
                        @endif
                    </ul>
                </section>

                <nav aria-label="Profilbereiche" class="flex gap-1.5 border-b border-dune-line">
                    <a wire:navigate href="{{ route('oasis.profil', $handle) }}" aria-current="page"
                       class="inline-flex min-h-12 items-center border-b-[3px] border-sun px-4 text-base font-extrabold text-night no-underline">Tropfen</a>
                    <span aria-disabled="true" title="In der Beispielansicht nicht verfügbar"
                       class="inline-flex min-h-12 items-center px-4 text-base font-semibold text-muted">Antworten</span>
                    <span aria-disabled="true" title="In der Beispielansicht nicht verfügbar"
                       class="inline-flex min-h-12 items-center px-4 text-base font-semibold text-muted">Oasen</span>
                </nav>

                {{-- Profile posts carry no author header (the profile is the author), so they are
                     plain cards here rather than <x-oasis.post>. --}}
                <article class="flex flex-col gap-3 rounded-[24px] border border-paper-line bg-paper p-[22px]">
                    <p class="m-0 text-sm text-muted">vor 40 Min. · in Korn &amp; Licht</p>
                    <p class="m-0 text-[17px] leading-[1.55]">Morgens um sechs am Kanal. Nebel, zwei Schwäne, kein Mensch. Ich liebe diese Stadt um diese Uhrzeit.</p>
                    <div role="img" aria-label="Foto (Platzhalter)" class="relative h-[300px] overflow-hidden rounded-[18px] bg-on-night-muted">
                        <svg viewBox="0 0 600 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false" class="absolute inset-0 size-full">
                            <rect width="600" height="300" fill="#C9D6D2"/>
                            <circle cx="170" cy="110" r="46" fill="#F4EDE1"/>
                            <path d="M0 200 q75 -24 150 0 t150 0 t150 0 t150 0 V300 H0 Z" fill="#2E9E95" opacity="0.7"/>
                            <path d="M0 240 q75 -24 150 0 t150 0 t150 0 t150 0 V300 H0 Z" fill="#1F6F69"/>
                        </svg>
                        <span class="absolute bottom-3 left-3.5 rounded-full bg-sand px-2.5 py-[3px] text-[13px] font-bold text-ink">Beispielfoto</span>
                    </div>
                </article>

                <article class="flex flex-col gap-3 rounded-[24px] border border-paper-line bg-paper p-[22px]">
                    <p class="m-0 flex items-center gap-2 text-sm font-bold text-ink-2">
                        <x-oasis.icon name="repost" :size="16" :stroke="2" />
                        {{ $profile['first_name'] }} hat das weitergetragen · vor 1 Tag
                    </p>
                    <p class="m-0 text-[17px] leading-[1.55]">„Kommt alle mit, ich bring Ersatzfilme mit.“</p>
                    <div class="flex flex-col gap-2 rounded-2xl border border-dune-line p-4">
                        <div class="flex items-center gap-2.5">
                            <span aria-hidden="true" class="size-8 flex-none rounded-[10px] bg-sun"></span>
                            <span class="text-[15px] font-extrabold">Oase Korn &amp; Licht</span>
                        </div>
                        <p class="m-0 text-base leading-[1.55]">Fotowalk am Sonntag um 10 Uhr. Treffpunkt: Hauptbahnhof, Westseite. Jede Kamera ist willkommen.</p>
                    </div>
                </article>

                <article class="flex flex-col gap-3 rounded-[24px] border border-paper-line bg-paper p-[22px]">
                    <p class="m-0 text-sm text-muted">vor 3 Tagen</p>
                    <p class="m-0 text-[17px] leading-[1.55]">Frage an die Runde: Welcher Film für graue Novembertage? Ich schwanke zwischen Farbe und Schwarzweiß.</p>
                </article>
            </div>

            <aside aria-label="Mehr über {{ $profile['first_name'] }}" class="mt-8 flex flex-[1_1_300px] flex-col gap-5">
                <section aria-labelledby="profil-oasen" class="flex flex-col gap-3 rounded-[24px] border border-paper-line bg-paper p-6">
                    <h2 id="profil-oasen" class="m-0 text-[19px] font-extrabold text-night">Oasen von {{ $profile['first_name'] }}</h2>
                    <ul class="m-0 flex list-none flex-col gap-1 p-0">
                        @foreach ($profile['oasen'] as $oase)
                            <li wire:key="profile-demo-1-{{ $oase['name'] }}" class="flex min-h-[52px] items-center gap-3">
                                <span aria-hidden="true" class="size-9 flex-none rounded-[11px] {{ $oase['color'] }}"></span>
                                <span class="flex flex-col leading-[1.3]">
                                    <span class="text-base font-extrabold">{{ $oase['name'] }}</span>
                                    <span class="text-sm text-muted">{{ $oase['role'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section aria-labelledby="folgen-cta" class="flex flex-col gap-3 rounded-[24px] bg-night p-6 text-on-night">
                    <h2 id="folgen-cta" class="m-0 text-[19px] font-extrabold">{{ $profile['first_name'] }} folgen?</h2>
                    <p class="m-0 text-base leading-[1.6] text-on-night-2">Starte deinen kostenlosen Test. Dann landen ihre Tropfen in deiner Quelle, der Reihe nach.</p>
                    <a wire:navigate href="{{ route('register') }}" class="o-link self-start text-base text-on-night">{{ config('oasis.trial_days') }} Tage kostenlos testen</a>
                </section>
            </aside>

        </div>
    </div>
</div>
