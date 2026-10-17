{{--
    2FA recovery, step 1 (TwoFactorRecoveryController::create, route two-factor.recovery.create): e-mail and password.
    The answer is the same for every input, so the page reveals no accounts. Bot traps from <x-oasis.bot-trap />.
--}}
@extends('layouts.oasis', ['title' => 'Zwei-Faktor verloren', 'description' => 'Zugang wiederherstellen, wenn Handy und Wiederherstellungscodes fehlen.', 'minimal' => true, 'noindex' => true])

@section('content')
    <x-oasis.auth-card title="Kein Zugriff auf deine Codes?" subtitle="Handy weg und Wiederherstellungscodes nicht zur Hand? So kommst du sicher wieder in dein Konto.">
        <ol class="m-0 flex list-decimal flex-col gap-1 pl-5 text-[15px] text-ink-2">
            <li>Gib E-Mail-Adresse und Passwort deines Kontos ein.</li>
            <li>Bestätige den Link, den wir dir per E-Mail schicken.</li>
            <li>Zu deinem Schutz warten wir dann {{ \App\Models\TwoFactorReset::WAIT_HOURS }} Stunden. So lange kannst du jederzeit abbrechen.</li>
            <li>Danach schaltest du die Zwei-Faktor-Anmeldung über denselben Link aus und richtest sie neu ein.</li>
        </ol>
        @if ($errors->has('form'))
            <p role="alert" class="o-error m-0">{{ $errors->first('form') }}</p>
        @endif
        <form method="POST" action="{{ route('two-factor.recovery.store') }}" class="flex flex-col gap-[18px]" novalidate>
            @csrf
            <x-oasis.bot-trap />
            <x-oasis.field name="email" type="email" label="E-Mail-Adresse deines Kontos" autocomplete="email" required autofocus />
            <x-oasis.field name="password" type="password" label="Passwort" autocomplete="current-password" required />
            <button type="submit" class="o-btn o-btn-dark">Weiter</button>
            <p class="m-0 text-center text-base text-ink-2">Hast du doch einen Wiederherstellungscode? <a href="{{ route('two-factor.login') }}" class="inline-flex min-h-11 items-center font-bold text-night underline underline-offset-4">Zurück zur Code-Eingabe</a></p>
        </form>
    </x-oasis.auth-card>
@endsection
