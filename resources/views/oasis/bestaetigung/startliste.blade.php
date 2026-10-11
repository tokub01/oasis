{{--
    Double-opt-in result rendered by oasis.waitlist.confirm after a valid signed token.
    Confirmation is recorded by WaitlistController before this page is shown. No variables or JS required.
--}}
@extends('layouts.oasis', ['title' => 'Deine E-Mail-Adresse ist bestätigt', 'description' => 'Deine E-Mail-Adresse ist bestätigt bei Oasis.', 'noindex' => true])
@section('content')
    <section class="o-section-s">
        <div class="o-container max-w-3xl">
            <h1 class="o-h1 mb-6">Deine E-Mail-Adresse ist bestätigt</h1>
            <p>Du stehst jetzt auf der Startliste.</p>
        </div>
    </section>
@endsection
