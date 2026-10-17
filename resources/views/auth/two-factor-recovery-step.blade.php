{{--
    2FA recovery, the page behind the e-mail links (TwoFactorRecoveryController::show). Variables: $reset
    (TwoFactorReset|null), $token, $state (requested|waiting|available|completed|cancelled|expired|unknown). GET changes
    nothing; every button is a CSRF-protected POST.
--}}
@extends('layouts.oasis', ['title' => 'Zwei-Faktor zurücksetzen', 'description' => 'Rücksetzung der Zwei-Faktor-Anmeldung.', 'minimal' => true, 'noindex' => true])

@php
    $berlin = fn ($date) => $date->timezone('Europe/Berlin')->format('d.m.Y').' um '.$date->timezone('Europe/Berlin')->format('H:i').' Uhr';
    [$title, $text] = match ($state) {
        'requested' => ['Rücksetzung bestätigen', 'Bestätige, dass du die Zwei-Faktor-Anmeldung zurücksetzen willst. Danach warten wir zu deinem Schutz '.\App\Models\TwoFactorReset::WAIT_HOURS.' Stunden.'],
        'waiting' => ['Noch etwas Geduld', 'Ab '.$berlin($reset->available_at).' kannst du die Zwei-Faktor-Anmeldung hier ausschalten. Wir haben dir eine E-Mail mit diesem Link geschickt.'],
        'available' => ['Jetzt zurücksetzen', 'Die Wartezeit ist vorbei. Danach bist du überall abgemeldet und meldest dich mit deinem Passwort neu an.'],
        'completed' => ['Erledigt', 'Die Zwei-Faktor-Anmeldung ist zurückgesetzt. Melde dich an und richte sie gleich wieder ein.'],
        'cancelled' => ['Abgebrochen', 'Diese Rücksetzung ist abgebrochen. Es hat sich nichts geändert.'],
        default => ['Link nicht mehr gültig', 'Dieser Link ist abgelaufen oder ungültig. Du kannst die Rücksetzung neu anfordern.'],
    };
@endphp

@section('content')
    <x-oasis.auth-card :title="$title" :subtitle="$text">
        @if ($state === 'requested')
            <form method="POST" action="{{ route('two-factor.recovery.confirm', $token) }}">@csrf
                <button type="submit" class="o-btn o-btn-dark w-full">Ja, ich war das – bestätigen</button>
            </form>
        @elseif ($state === 'available')
            <form method="POST" action="{{ route('two-factor.recovery.complete', $token) }}">@csrf
                <button type="submit" class="o-btn o-btn-dark w-full">Zwei-Faktor-Anmeldung ausschalten</button>
            </form>
        @endif
        @if (in_array($state, ['requested', 'waiting', 'available'], true))
            <form method="POST" action="{{ route('two-factor.recovery.cancel', $token) }}">@csrf
                <button type="submit" class="o-btn o-btn-md o-btn-outline w-full">Rücksetzung abbrechen</button>
            </form>
            <p class="m-0 text-sm text-ink-2">Warst du das nicht? Brich ab und ändere dein Passwort. Meldest du dich mit deinem zweiten Faktor an, bricht die Rücksetzung von selbst ab.</p>
        @elseif ($state === 'completed')
            <a href="{{ route('login') }}" class="o-btn o-btn-dark">Zur Anmeldung</a>
        @else
            <a href="{{ route('two-factor.recovery.create') }}" class="o-btn o-btn-md o-btn-outline">Neu anfordern</a>
        @endif
    </x-oasis.auth-card>
@endsection
