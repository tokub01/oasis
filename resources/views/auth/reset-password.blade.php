{{--
    Reset password: Fortify resetPasswordView (route `password.reset`).

    Variables:
      $request  Illuminate\Http\Request  carries the token (route parameter) and the email (query string)
--}}
@extends('layouts.oasis', ['title' => 'Neues Passwort', 'description' => 'Neues Passwort für Oasis setzen.', 'minimal' => true, 'noindex' => true])

@section('content')
    <x-oasis.auth-card title="Neues Passwort" subtitle="Wähle ein Passwort mit mindestens 10 Zeichen.">
        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-[18px]" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <x-oasis.field name="email" type="email" label="E-Mail-Adresse" autocomplete="email" :value="$request->email" required />
            <x-oasis.field name="password" type="password" label="Neues Passwort" autocomplete="new-password" minlength="10" required autofocus />
            <x-oasis.field name="password_confirmation" type="password" label="Neues Passwort wiederholen" autocomplete="new-password" minlength="10" required />
            <button type="submit" class="o-btn o-btn-dark">Passwort speichern</button>
        </form>
    </x-oasis.auth-card>
@endsection
