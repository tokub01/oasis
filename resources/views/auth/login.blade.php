{{--
    Sign-in: Fortify loginView, URL /anmelden (route `login`), template Anmelden.dc.html.
    Field `login` accepts email or username (Fortify::authenticateUsing in FortifyServiceProvider).
--}}
@extends('layouts.oasis', [
    'title' => 'Anmelden',
    'description' => 'Bei Oasis anmelden.',
    'minimal' => true,
])

@section('content')
    <x-oasis.auth-card title="Willkommen zurück." subtitle="Schön, dass du wieder da bist.">
        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-[18px]" novalidate>
            @csrf
            <x-oasis.field name="login" label="E-Mail oder Benutzername" autocomplete="username" required autofocus />
            <x-oasis.field name="password" type="password" label="Passwort" autocomplete="current-password" required />
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <label class="flex min-h-11 items-center gap-2.5 text-[15px]">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="m-0 size-5 accent-night">
                    <span>Angemeldet bleiben</span>
                </label>
                <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center text-[15px] font-bold text-night underline underline-offset-4">Passwort vergessen?</a>
            </div>
            <button type="submit" class="o-btn o-btn-dark">Anmelden</button>
            <div aria-hidden="true" class="my-1.5 h-px bg-paper-line"></div>
            <p class="m-0 text-center text-base text-ink-2">Noch kein Konto? <a href="{{ route('register') }}" class="font-extrabold text-sun-text underline underline-offset-4">{{ config('oasis.trial_days') }} Tage kostenlos testen</a></p>
        </form>
    </x-oasis.auth-card>
@endsection
