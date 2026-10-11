@extends('layouts.oasis', [
    'title' => config('oasis.trial_days').' Tage testen',
    'description' => config('oasis.trial_days').' Tage Oasis kostenlos testen. Keine Zahlungsdaten, der Test endet von selbst.',
    'minimal' => true,
])

@php
    $trialDays = config('oasis.trial_days');
    $launchDate = config('oasis.launch_date')
        ? \Illuminate\Support\Carbon::parse(config('oasis.launch_date'))->locale('de')->isoFormat('D. MMMM YYYY')
        : null;
    $expectations = [
        'Voller Zugang zu allen Funktionen',
        'Deine Leute finden oder einladen',
        'Oasen entdecken, die zu dir passen',
        'Am Ende entscheidest du: Mitglied werden oder nicht',
    ];
    $inputClasses ='min-h-[52px] rounded-xl border border-field-line bg-field px-4 text-[17px] text-ink';
@endphp

@section('content')
    <div class="py-[clamp(40px,5vw,72px)]">
        <div class="o-container flex flex-wrap items-start gap-8">

            {{-- There is no account system yet: the form is shown as designed, but it cannot be
                 submitted (disabled button, no field names) and says so. --}}
            <form method="post" aria-labelledby="test-titel"
                  class="flex min-w-0 flex-[999_1_520px] flex-col gap-5 rounded-[32px] border border-paper-line bg-paper p-[clamp(28px,3.5vw,48px)]">
                <div class="flex flex-col gap-2.5">
                    <p class="m-0 text-[15px] font-bold text-ink-2">Schritt 1 von 3 · Konto</p>
                    <div aria-hidden="true" class="flex max-w-[280px] gap-1.5">
                        <span class="h-1.5 flex-1 rounded-[3px] bg-night"></span>
                        <span class="h-1.5 flex-1 rounded-[3px] bg-dune-line"></span>
                        <span class="h-1.5 flex-1 rounded-[3px] bg-dune-line"></span>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <h1 id="test-titel" class="o-display m-0 text-[length:clamp(34px,3.6vw,48px)] leading-[1.1] tracking-[-0.015em] text-night">{{ $trialDays }} Tage Oasis, kostenlos.</h1>
                    <p class="m-0 text-lg text-ink-2">Keine Zahlungsdaten. Der Test endet von selbst.</p>
                </div>

                <p id="t-start-hinweis" class="m-0 flex items-start gap-3 rounded-2xl bg-dune px-[18px] py-3.5 text-base leading-normal text-ink">
                    <x-oasis.icon name="calendar" :size="20" :stroke="2" class="mt-0.5 text-night" />
                    <span>
                        @if ($launchDate)
                            Der Test startet am {{ $launchDate }}.
                        @else
                            Der Test startet am Starttag.
                        @endif
                        Trag dich bis dahin in die <a href="{{ route('oasis.gruendung') }}" class="font-bold text-night underline underline-offset-4">Startliste</a> ein.
                    </span>
                </p>

                <div class="o-auto-grid gap-x-5 gap-y-[18px] [--min:240px]">
                    <div class="flex flex-col gap-1.5">
                        <label for="t-name" class="text-[15px] font-bold">Wie sollen andere dich nennen?</label>
                        <input id="t-name" type="text" autocomplete="name" placeholder="z. B. Lena Berger" class="{{ $inputClasses }}">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="t-handle" class="text-[15px] font-bold">Benutzername</label>
                        <div class="flex min-h-[52px] items-center overflow-hidden rounded-xl border border-field-line bg-field focus-within:outline-3 focus-within:outline-offset-3 focus-within:outline-spring">
                            <span aria-hidden="true" class="pr-1 pl-4 text-[17px] text-muted">@</span>
                            <input id="t-handle" type="text" autocomplete="username" placeholder="lena"
                                   class="min-h-[50px] min-w-0 flex-1 border-none bg-transparent pr-4 text-[17px] text-ink focus-visible:outline-none">
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="t-mail" class="text-[15px] font-bold">E-Mail-Adresse</label>
                    <input id="t-mail" type="email" autocomplete="email" placeholder="name@beispiel.de" class="{{ $inputClasses }}">
                </div>
                <div class="o-auto-grid gap-x-5 gap-y-[18px] [--min:240px]">
                    <div class="flex flex-col gap-1.5">
                        <label for="t-pass" class="text-[15px] font-bold">Passwort</label>
                        <input id="t-pass" type="password" autocomplete="new-password" minlength="10" aria-describedby="t-pass-hinweis" class="{{ $inputClasses }}">
                        <span id="t-pass-hinweis" class="text-sm text-muted">Mindestens 10 Zeichen</span>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="t-geburt" class="text-[15px] font-bold">Geburtsdatum</label>
                        <input id="t-geburt" type="date" aria-describedby="t-geburt-hinweis" class="{{ $inputClasses }}">
                        <span id="t-geburt-hinweis" class="text-sm leading-[1.45] text-muted">Oasis ist ab 16. Für eine Mitgliedschaft unter 18 brauchst du die Zustimmung deiner Eltern.</span>
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="t-code" class="text-[15px] font-bold">Einladungs- oder Gruppencode <span class="font-medium text-muted">(optional)</span></label>
                    <input id="t-code" type="text" placeholder="z. B. WG-KASTANIE" class="{{ $inputClasses }}">
                </div>
                <label class="flex items-start gap-3 text-[15px] leading-normal">
                    <input type="checkbox" class="mt-0.5 size-5 flex-none accent-night">
                    <span>Ich akzeptiere die <a href="{{ route('oasis.regeln') }}" class="text-ink underline">Community-Regeln</a> und die <a href="{{ route('oasis.agb') }}" class="text-ink underline">AGB</a>. Wie wir mit deinen Daten umgehen, steht in der <a href="{{ route('oasis.datenschutz') }}" class="text-ink underline">Datenschutzerklärung</a>.</span>
                </label>
                <button type="submit" disabled aria-describedby="t-start-hinweis"
                        class="min-h-14 cursor-not-allowed rounded-full border-none bg-sun text-lg font-extrabold text-sun-ink opacity-60">Konto erstellen und Test starten</button>
                <p class="m-0 flex items-start gap-2.5 text-[15px] leading-normal text-ink-2">
                    <x-oasis.icon name="clock" :size="18" :stroke="2" class="mt-0.5 text-spring-text" />
                    <span>Dein Test endet nach {{ $trialDays }} Tagen von selbst. Zwei Tage vorher erinnern wir dich per E-Mail.</span>
                </p>
                <p class="m-0 text-base text-ink-2">Schon dabei? <a href="{{ route('oasis.anmelden') }}" class="font-bold text-night underline underline-offset-4">Anmelden</a></p>
            </form>

            <aside aria-labelledby="erwartet-titel" class="flex flex-[1_1_340px] flex-col gap-6 rounded-[32px] bg-night p-[clamp(28px,3.5vw,44px)] text-on-night">
                <x-oasis.logo :size="48" :wordmark="false" />
                <h2 id="erwartet-titel" class="o-display m-0 text-[32px] leading-[1.15]">Was dich in den {{ $trialDays }} Tagen erwartet</h2>
                <ul class="m-0 flex list-none flex-col gap-3.5 p-0 text-[17px] leading-normal">
                    @foreach ($expectations as $item)
                        <li class="flex items-start gap-3">
                            <x-oasis.icon name="check" :size="20" :stroke="2.4" class="mt-[3px] text-spring" />
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="flex flex-col gap-2 border-t border-night-line pt-5">
                    <p class="m-0 text-sm font-bold tracking-[2px] text-on-night-muted uppercase">Danach</p>
                    <p class="m-0 text-base leading-[1.55] text-on-night-2">Schritt 2: Interessen und Oasen wählen<br>Schritt 3: Leute finden oder deine Gruppe einladen</p>
                </div>
            </aside>

        </div>
    </div>
@endsection
