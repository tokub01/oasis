{{--
    Explicit step to switch the daily summary off from the mail (DigestUnsubscribeController::show). $user: the account
    from the signed link. GET changes nothing; the button sends a signed, CSRF-protected POST. No JS required.
--}}
@extends('layouts.oasis', ['title' => 'Zusammenfassung abbestellen', 'description' => 'Die tägliche E-Mail-Zusammenfassung von Oasis abbestellen.', 'noindex' => true])
@section('content')
    <section class="o-section-s">
        <div class="o-container max-w-3xl">
            <h1 class="o-h1 mb-6">Tägliche Zusammenfassung abbestellen</h1>
            <p class="mb-6">Du bekommst dann keine E-Mail mehr mit der Zahl neuer Hinweise. Deine Hinweise in der App bleiben, und du kannst die Zusammenfassung in den Einstellungen jederzeit wieder einschalten.</p>
            <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('notifications.digest.confirm', ['user' => $user]) }}">
                @csrf
                <button type="submit" class="o-btn o-btn-primary">Zusammenfassung abbestellen</button>
            </form>
        </div>
    </section>
@endsection
