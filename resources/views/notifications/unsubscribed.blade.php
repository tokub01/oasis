{{-- Result of DigestUnsubscribeController::confirm. No variables, no JS; repeating it changes nothing. --}}
@extends('layouts.oasis', ['title' => 'Zusammenfassung abbestellt', 'description' => 'Die tägliche Zusammenfassung ist ausgeschaltet.', 'noindex' => true])
@section('content')
    <section class="o-section-s">
        <div class="o-container max-w-3xl">
            <h1 class="o-h1 mb-6">Zusammenfassung abbestellt</h1>
            <p>Du bekommst keine tägliche E-Mail mehr. Einschalten kannst du sie jederzeit unter Hinweise → Einstellungen.</p>
        </div>
    </section>
@endsection
