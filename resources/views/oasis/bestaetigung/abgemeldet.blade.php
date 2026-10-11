{{--
    Waitlist unsubscribe result rendered by oasis.waitlist.destroy after the signed POST.
    No variables or JavaScript required; repeated deletion has the same result.
--}}
@extends('layouts.oasis', ['title' => 'Du bist abgemeldet', 'description' => 'Du bist abgemeldet bei Oasis.', 'noindex' => true])
@section('content')
    <section class="o-section-s">
        <div class="o-container max-w-3xl">
            <h1 class="o-h1 mb-6">Du bist abgemeldet</h1>
            <p>Deine E-Mail-Adresse wurde von der Startliste entfernt.</p>
        </div>
    </section>
@endsection
