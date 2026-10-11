{{--
    Confirm password: Fortify confirmPasswordView (route `password.confirm`). Asked before sensitive actions such
    as deleting the account or changing two-factor settings.
--}}
@extends('layouts.oasis', ['title' => 'Passwort bestätigen', 'description' => 'Bestätige dein Passwort.', 'minimal' => true, 'noindex' => true])

@section('content')
    <x-oasis.auth-card title="Kurz bestätigen" subtitle="Bitte gib zur Sicherheit dein Passwort ein.">
        <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-[18px]" novalidate>
            @csrf
            <x-oasis.field name="password" type="password" label="Passwort" autocomplete="current-password" required autofocus />
            <button type="submit" class="o-btn o-btn-dark">Bestätigen</button>
        </form>
    </x-oasis.auth-card>
@endsection
