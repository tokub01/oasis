{{--
    Two-factor challenge: Fortify twoFactorChallengeView (route `two-factor.login`). Accepts either the 6-digit
    code from the authenticator app or one recovery code. Works without JavaScript: both fields are shown.
--}}
@extends('layouts.oasis', ['title' => 'Zwei-Faktor-Anmeldung', 'description' => 'Bestätige deine Anmeldung.', 'minimal' => true, 'noindex' => true])

@section('content')
    <x-oasis.auth-card title="Noch ein Schritt" subtitle="Gib den Code aus deiner Authenticator-App ein.">
        <form method="POST" action="{{ route('two-factor.login') }}" class="flex flex-col gap-[18px]" novalidate>
            @csrf
            <x-oasis.field name="code" label="Sechsstelliger Code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" autofocus />
            <x-oasis.field name="recovery_code" label="Oder ein Wiederherstellungscode" optional autocomplete="off" />
            <button type="submit" class="o-btn o-btn-dark">Anmelden</button>
            <p class="m-0 text-center text-base text-ink-2"><a href="{{ route('two-factor.recovery.create') }}" class="inline-flex min-h-11 items-center font-bold text-night underline underline-offset-4">Kein Zugriff auf deine Codes?</a></p>
        </form>
    </x-oasis.auth-card>
@endsection
