@extends('layouts.oasis', ['title' => 'Dein Widerruf ist eingegangen', 'description' => 'Dein Widerruf ist eingegangen bei Oasis.', 'noindex' => true])

@php
    $declaration = session('oasis.submitted.widerrufen');
@endphp

@section('content')
    <section class="o-section-s">
        <div class="o-container max-w-3xl">
            <h1 class="o-h1 mb-6">Dein Widerruf ist eingegangen</h1>
            <p class="mb-6">Wir haben deinen Widerruf gespeichert und dir eine Eingangsbestätigung per E-Mail geschickt.</p>
            <dl class="mb-6 flex flex-col gap-4 rounded-3xl border border-paper-line bg-paper p-6">
                <div>
                    <dt class="font-bold text-night">Eingegangen am</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ $declaration['received_at'] }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-night">Vertrag</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ $declaration['contract'] }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-night">Name</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ $declaration['name'] }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-night">E-Mail</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ $declaration['email'] }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-night">Vertragsdatum</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ $declaration['contracted_on'] ?? '–' }}</dd>
                </div>
            </dl>
            <p>Du kannst diese Seite speichern oder drucken.</p>
        </div>
    </section>
@endsection
