{{--
    Sign-up ("30 Tage testen"): Fortify registerView, URL /testen (route `register`), template Testen.dc.html.
    Posts to Fortify's registration route; App\Actions\Fortify\CreateNewUser validates and creates the account
    (username, age ≥ 16, password ≥ 10, terms) and starts the trial. Field names are the contract with that action.
--}}
@extends('layouts.oasis', [
    'title' => config('oasis.trial_days').' Tage testen',
    'description' => config('oasis.trial_days').' Tage Oasis kostenlos testen. Keine Zahlungsdaten, der Test endet von selbst.',
    'minimal' => true,
])

@php
    $trialDays = config('oasis.trial_days');
    $expectations = [
        'Voller Zugang zu allen Funktionen',
        'Deine Leute finden oder einladen',
        'Oasen entdecken, die zu dir passen',
        'Am Ende entscheidest du: Mitglied werden oder nicht',
    ];
@endphp

@section('content')
    <div class="py-[clamp(40px,5vw,72px)]">
        <div class="o-container flex flex-wrap items-start gap-8">

            <form method="POST" action="{{ route('register') }}" aria-labelledby="test-titel" novalidate
                  class="flex min-w-0 flex-[999_1_520px] flex-col gap-5 rounded-[32px] border border-paper-line bg-paper p-5 sm:p-[clamp(28px,3.5vw,48px)]">
                @csrf
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

                @if (session('status'))
                    <p role="status" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5 text-base leading-normal text-ink">{{ session('status') }}</p>
                @endif

                @if ($errors->any())
                    <p role="alert" class="o-error m-0 rounded-2xl border border-sun-text px-[18px] py-3.5">Bitte prüfe die markierten Felder.</p>
                @endif

                <div class="o-auto-grid gap-x-5 gap-y-[18px] [--min:240px]">
                    <x-oasis.field name="display_name" label="Wie sollen andere dich nennen?" autocomplete="name"
                                   placeholder="z. B. Lena Berger" maxlength="50" required />
                    <x-oasis.field name="username" label="Benutzername" prefix="@" autocomplete="username"
                                   placeholder="lena" maxlength="30" pattern="[a-z0-9_]{3,30}" required
                                   hint="3 bis 30 Zeichen: Kleinbuchstaben, Ziffern und Unterstrich (_)" />
                </div>
                <x-oasis.field name="email" type="email" label="E-Mail-Adresse" autocomplete="email"
                               placeholder="name@beispiel.tld" required />
                <div class="o-auto-grid gap-x-5 gap-y-[18px] [--min:240px]">
                    <x-oasis.field name="password" type="password" label="Passwort" autocomplete="new-password"
                                   minlength="10" required hint="Mindestens 10 Zeichen" />
                    <x-oasis.field name="password_confirmation" type="password" label="Passwort wiederholen"
                                   autocomplete="new-password" minlength="10" required />
                </div>
                <x-oasis.field name="birth_date" type="date" label="Geburtsdatum" required
                               hint="Oasis ist ab 16. Für eine Mitgliedschaft unter 18 brauchst du die Zustimmung deiner Eltern." />

                <div class="flex flex-col gap-1.5">
                    <label class="flex items-start gap-3 text-[15px] leading-normal">
                        <input type="checkbox" name="terms" value="1" @checked(old('terms')) required
                               aria-invalid="{{ $errors->has('terms') ? 'true' : 'false' }}"
                               @if ($errors->has('terms')) aria-describedby="f-terms-error" @endif
                               class="mt-0.5 size-5 flex-none accent-night">
                        <span>Ich akzeptiere die <a href="{{ route('oasis.regeln') }}" class="text-ink underline">Community-Regeln</a> und die <a href="{{ route('oasis.agb') }}" class="text-ink underline">AGB</a>. Wie wir mit deinen Daten umgehen, steht in der <a href="{{ route('oasis.datenschutz') }}" class="text-ink underline">Datenschutzerklärung</a>.</span>
                    </label>
                    @error('terms')
                        <p id="f-terms-error" class="o-error m-0">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="o-btn o-btn-primary px-4 text-base sm:px-7 sm:text-lg">Konto erstellen und Test starten</button>
                <p class="m-0 flex items-start gap-2.5 text-[15px] leading-normal text-ink-2">
                    <x-oasis.icon name="clock" :size="18" :stroke="2" class="mt-0.5 text-spring-text" />
                    <span>Dein Test endet nach {{ $trialDays }} Tagen von selbst. Zwei Tage vorher erinnern wir dich per E-Mail.</span>
                </p>
                <p class="m-0 text-base text-ink-2">Schon dabei? <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-bold whitespace-nowrap text-night underline underline-offset-4">Anmelden</a></p>
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
