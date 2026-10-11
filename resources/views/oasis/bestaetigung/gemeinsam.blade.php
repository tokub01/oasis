{{--
    Organisation-enquiry acknowledgement at oasis.bestaetigung.gemeinsam, based on Gemeinsam.dc.html.
    Shown after storage and operator notification; does not promise a completed group registration. No JS required.
--}}
@extends('layouts.oasis', ['title' => 'Deine Anfrage ist eingegangen', 'description' => 'Deine Anfrage ist eingegangen bei Oasis.', 'noindex' => true])
@section('content')
    <section class="o-section-s">
        <div class="o-container max-w-3xl">
            <h1 class="o-h1 mb-6">Deine Anfrage ist eingegangen</h1>
            <p>Wir haben deine Anfrage gespeichert und an den Betreiber geschickt.</p>
        </div>
    </section>
@endsection
