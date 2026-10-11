{{--
    Forgot password: Fortify requestPasswordResetLinkView (route `password.request`).
    Fortify always answers with the same status message, so the page never reveals whether an address exists.
--}}
@extends('layouts.oasis', ['title' => 'Passwort vergessen', 'description' => 'Neues Passwort für Oasis anfordern.', 'minimal' => true])

@section('content')
    <x-oasis.auth-card title="Passwort vergessen?" subtitle="Wir schicken dir einen Link, mit dem du ein neues Passwort setzt.">
        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-[18px]" novalidate>
            @csrf
            <x-oasis.field name="email" type="email" label="E-Mail-Adresse deines Kontos" autocomplete="email" required autofocus />
            <button type="submit" class="o-btn o-btn-dark">Link schicken</button>
            <p class="m-0 text-center text-base text-ink-2"><a href="{{ route('login') }}" class="font-bold text-night underline underline-offset-4">Zurück zur Anmeldung</a></p>
        </form>
    </x-oasis.auth-card>
@endsection
