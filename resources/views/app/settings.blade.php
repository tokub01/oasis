{{--
    Account settings (route app.settings, /app/einstellungen). Works without JavaScript: every block is a normal
    form posting to Fortify or the account controllers.

    Blocks and their endpoints:
      Profil                 PUT    user-profile-information.update   (display_name, email; username is fixed)
      Passwort               PUT    user-password.update              (errors bag: updatePassword)
      Zwei-Faktor-Anmeldung  POST/DELETE two-factor.* (Fortify; requires a recent password confirmation)
      Deine Daten            GET    account.export   (JSON download, GDPR Art. 15/20)
      Konto löschen          DELETE account.destroy  (requires a recent password confirmation)
    Fortify reports success through session('status') values, which are translated here.
--}}
@extends('layouts.app', ['title' => 'Einstellungen', 'active' => 'einstellungen', 'wide' => true])

@php
    $user = auth()->user();
    $status = session('status');
    $messages = [
        'profile-information-updated' => 'Dein Profil ist gespeichert.',
        'password-updated' => 'Dein Passwort ist geändert.',
        'two-factor-authentication-enabled' => 'Scanne jetzt den QR-Code und bestätige mit einem Code.',
        'two-factor-authentication-confirmed' => 'Die Zwei-Faktor-Anmeldung ist aktiv.',
        'two-factor-authentication-disabled' => 'Die Zwei-Faktor-Anmeldung ist ausgeschaltet.',
        'recovery-codes-generated' => 'Du hast neue Wiederherstellungscodes.',
    ];
    $card = 'flex flex-col gap-4 rounded-[24px] border border-paper-line bg-paper p-[clamp(20px,3vw,32px)]';
    $h2 = 'm-0 text-[21px] font-extrabold text-night';
    $twoFactorPending = $user->two_factor_secret && ! $user->two_factor_confirmed_at;
    $twoFactorActive = $user->two_factor_secret && $user->two_factor_confirmed_at;
@endphp

@section('content')
    <div class="flex flex-col gap-6">
        <h1 class="o-h2 m-0 text-night">Einstellungen</h1>

        @if (isset($messages[$status]))
            <p role="status" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5">{{ $messages[$status] }}</p>
        @endif

        {{-- Two columns of cards from 1280px: account data left, security and data rights right. --}}
        <div class="grid gap-6 xl:grid-cols-2 xl:items-start">
        <div class="flex flex-col gap-6">
        <section aria-labelledby="profil-titel" class="{{ $card }}">
            <h2 id="profil-titel" class="{{ $h2 }}">Profil</h2>
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="flex flex-col gap-4" novalidate>
                @csrf
                @method('PUT')
                <x-oasis.field bag="updateProfileInformation" name="display_name" label="Anzeigename" :value="$user->display_name" autocomplete="name" maxlength="50" required />
                <div class="flex flex-col gap-1.5">
                    <span class="text-[15px] font-bold">Benutzername</span>
                    <span class="flex min-h-[52px] items-center rounded-[14px] bg-dune px-4 text-[17px] font-bold">{{ '@'.$user->username }}</span>
                    <span class="text-sm text-muted">Dein Benutzername bleibt fest, damit Links zu deinem Profil nicht ins Leere führen.</span>
                </div>
                <x-oasis.field bag="updateProfileInformation" name="email" type="email" label="E-Mail-Adresse" :value="$user->email" autocomplete="email" required
                               hint="Wenn du sie änderst, bestätigst du die neue Adresse per Link." />
                <button type="submit" class="o-btn o-btn-md o-btn-dark self-start">Profil speichern</button>
            </form>
        </section>

        <section aria-labelledby="passwort-titel" class="{{ $card }}">
            <h2 id="passwort-titel" class="{{ $h2 }}">Passwort</h2>
            <form method="POST" action="{{ route('user-password.update') }}" class="flex flex-col gap-4" novalidate>
                @csrf
                @method('PUT')
                <x-oasis.field bag="updatePassword" name="current_password" type="password" label="Aktuelles Passwort" autocomplete="current-password" required />
                <x-oasis.field bag="updatePassword" name="password" type="password" label="Neues Passwort" autocomplete="new-password" minlength="10" required hint="Mindestens 10 Zeichen" />
                <x-oasis.field bag="updatePassword" name="password_confirmation" type="password" label="Neues Passwort wiederholen" autocomplete="new-password" minlength="10" required />
                <button type="submit" class="o-btn o-btn-md o-btn-dark self-start">Passwort ändern</button>
            </form>
        </section>

        </div>
        <div class="flex flex-col gap-6">
        <section aria-labelledby="zwei-faktor-titel" class="{{ $card }}">
            <h2 id="zwei-faktor-titel" class="{{ $h2 }}">Zwei-Faktor-Anmeldung</h2>
            @if ($twoFactorActive)
                <p class="m-0 text-ink-2">Aktiv. Bei der Anmeldung fragen wir zusätzlich nach einem Code aus deiner Authenticator-App.</p>
                @if (in_array($status, ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true))
                    <div class="flex flex-col gap-2">
                        <p class="m-0 font-bold">Bewahre diese Wiederherstellungscodes sicher auf. Jeder funktioniert einmal.</p>
                        <ul class="m-0 grid list-none grid-cols-2 gap-2 rounded-2xl bg-dune p-4 font-mono text-sm">
                            @foreach ($user->recoveryCodes() as $code)
                                <li>{{ $code }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                        @csrf
                        <button type="submit" class="o-btn o-btn-md o-btn-outline">Neue Wiederherstellungscodes</button>
                    </form>
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="o-btn o-btn-md o-btn-outline">Ausschalten</button>
                    </form>
                </div>
            @elseif ($twoFactorPending)
                <p class="m-0 text-ink-2">Scanne den QR-Code mit deiner Authenticator-App und gib den angezeigten Code ein.</p>
                <div class="self-start rounded-2xl bg-white p-4" role="img" aria-label="QR-Code für die Authenticator-App">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="flex flex-col gap-4" novalidate>
                    @csrf
                    <x-oasis.field bag="confirmTwoFactorAuthentication" name="code" label="Sechsstelliger Code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" required />
                    <button type="submit" class="o-btn o-btn-md o-btn-dark self-start">Bestätigen</button>
                </form>
            @else
                <p class="m-0 text-ink-2">Schütze dein Konto zusätzlich mit einem Code aus einer Authenticator-App.</p>
                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <button type="submit" class="o-btn o-btn-md o-btn-outline">Einschalten</button>
                </form>
            @endif
        </section>

        <section aria-labelledby="daten-titel" class="{{ $card }}">
            <h2 id="daten-titel" class="{{ $h2 }}">Deine Daten</h2>
            <p class="m-0 text-ink-2">Lade alle Daten herunter, die wir zu deinem Konto speichern, als JSON-Datei.</p>
            <a href="{{ route('account.export') }}" class="o-btn o-btn-md o-btn-outline self-start">Daten herunterladen</a>
        </section>

        <section aria-labelledby="loeschen-titel" class="{{ $card }} border-sun-text">
            <h2 id="loeschen-titel" class="{{ $h2 }}">Konto löschen</h2>
            <p class="m-0 text-ink-2">Dein Konto und alle Daten dazu werden endgültig gelöscht. Eine laufende Mitgliedschaft kündigst du vorher über „<a href="{{ route('oasis.kuendigen') }}" class="text-night underline underline-offset-4">Verträge hier kündigen</a>“.</p>
            <form method="POST" action="{{ route('account.destroy') }}">
                @csrf
                @method('DELETE')
                <label class="flex items-start gap-3 text-[15px] leading-normal">
                    <input type="checkbox" name="confirm" value="1" required class="mt-0.5 size-5 flex-none accent-night">
                    <span>Ja, ich möchte mein Konto endgültig löschen.</span>
                </label>
                <button type="submit" class="o-btn o-btn-md mt-4 bg-sun-text text-white">Konto endgültig löschen</button>
            </form>
        </section>
        </div>
        </div>
    </div>
@endsection
