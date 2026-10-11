{{--
    Anmelden: route oasis.anmelden (and /login redirects here), template Anmelden.dc.html, minimal header.
    Like Testen: shown for the design, not submittable until an auth system exists.
--}}
@extends('layouts.oasis', [
    'title' => 'Anmelden',
    'description' => 'Bei Oasis anmelden.',
    'minimal' => true,
])

@php
    $launchDate = config('oasis.launch_date')
        ? \Illuminate\Support\Carbon::parse(config('oasis.launch_date'))->locale('de')->isoFormat('D. MMMM YYYY')
        : null;
    $inputClasses = 'min-h-[52px] rounded-xl border border-field-line bg-field px-4 text-[17px] text-ink';
@endphp

@section('content')
    <div class="relative overflow-hidden px-[clamp(20px,4vw,48px)] py-[clamp(56px,7vw,104px)]">
        <svg viewBox="0 0 600 300" aria-hidden="true" focusable="false" class="absolute -right-20 -bottom-10 h-auto w-[620px] max-w-[90%]">
            <path d="M60 300 A240 240 0 0 1 540 300 Z" fill="#EADFCC"/>
        </svg>

        {{-- There is no account system yet: the form is shown as designed, but it cannot be
             submitted (disabled button, no field names) and says so. --}}
        <form method="post" aria-labelledby="login-titel"
              class="relative mx-auto flex max-w-[480px] flex-col gap-[18px] rounded-[32px] border border-paper-line bg-paper p-[clamp(28px,4vw,48px)] shadow-[0_30px_60px_-36px_rgba(14,43,47,0.45)]">
            <x-oasis.logo :size="48" :wordmark="false" />
            <div class="flex flex-col gap-1.5">
                <h1 id="login-titel" class="o-display m-0 text-[40px] leading-[1.1] tracking-[-0.015em] text-night">Willkommen zurück.</h1>
                <p class="m-0 text-[17px] text-ink-2">Schön, dass du wieder da bist.</p>
            </div>

            <p id="l-start-hinweis" class="m-0 flex items-start gap-3 rounded-2xl bg-dune px-[18px] py-3.5 text-base leading-normal text-ink">
                <x-oasis.icon name="calendar" :size="20" :stroke="2" class="mt-0.5 text-night" />
                <span>
                    @if ($launchDate)
                        Die Anmeldung öffnet am {{ $launchDate }}.
                    @else
                        Die Anmeldung öffnet am Starttag.
                    @endif
                    Trag dich bis dahin in die <a href="{{ route('oasis.gruendung') }}" class="font-bold text-night underline underline-offset-4">Startliste</a> ein.
                </span>
            </p>

            <div class="flex flex-col gap-1.5">
                <label for="l-nutzer" class="text-[15px] font-bold">E-Mail oder Benutzername</label>
                <input id="l-nutzer" type="text" autocomplete="username" class="{{ $inputClasses }}">
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="l-pass" class="text-[15px] font-bold">Passwort</label>
                <input id="l-pass" type="password" autocomplete="current-password" class="{{ $inputClasses }}">
            </div>
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <label class="flex min-h-11 items-center gap-2.5 text-[15px]">
                    <input type="checkbox" class="m-0 size-5 accent-night">
                    <span>Angemeldet bleiben</span>
                </label>
                <span aria-disabled="true" class="inline-flex min-h-11 items-center text-[15px] font-bold text-muted">Passwort vergessen?</span>
            </div>
            <button type="submit" disabled aria-describedby="l-start-hinweis"
                    class="min-h-14 cursor-not-allowed rounded-full border-none bg-night text-lg font-extrabold text-on-night opacity-60">Anmelden</button>
            <div aria-hidden="true" class="my-1.5 h-px bg-paper-line"></div>
            <p class="m-0 text-center text-base text-ink-2">Noch kein Konto? <a href="{{ route('oasis.testen') }}" class="font-extrabold text-sun-text underline underline-offset-4">{{ config('oasis.trial_days') }} Tage kostenlos testen</a></p>
        </form>
    </div>
@endsection
