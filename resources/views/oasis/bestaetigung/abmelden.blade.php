{{--
    Explicit unsubscribe step for the founding waitlist at oasis.waitlist.unsubscribe.
    $token: string, opaque token validated by signed middleware before WaitlistController renders this view.
    A signed CSRF-protected POST performs deletion; mail-scanner GETs must be harmless. No JS required.
--}}
@extends('layouts.oasis', ['title' => 'Von der Startliste abmelden', 'description' => 'Bestätige deine Abmeldung von der Oasis-Startliste.', 'noindex' => true])
@section('content')
    <section class="o-section-s">
        <div class="o-container max-w-3xl">
            <h1 class="o-h1 mb-6">Von der Startliste abmelden</h1>
            <p class="mb-6">Bitte bestätige, dass wir deine Adresse aus der Startliste löschen sollen.</p>
            <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('oasis.waitlist.destroy', ['token' => $token]) }}">
                @csrf
                <button type="submit" class="o-btn o-btn-primary">Abmeldung bestätigen</button>
            </form>
        </div>
    </section>
@endsection
