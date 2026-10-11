@extends('layouts.oasis', ['title' => 'Deine Kündigung ist eingegangen', 'description' => 'Deine Kündigung ist eingegangen bei Oasis.', 'noindex' => true])

@php
    $declaration = session('oasis.submitted.kuendigen');
@endphp

@section('content')
    <section class="o-section-s">
        <div class="o-container max-w-3xl">
            <h1 class="o-h1 mb-6">Deine Kündigung ist eingegangen</h1>
            <p class="mb-6">Wir haben deine Kündigung gespeichert und dir die Bestätigung mit Datum, Uhrzeit und Inhalt per E-Mail geschickt.</p>
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
                    <dt class="font-bold text-night">Benutzername</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ $declaration['username'] ?? '–' }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-night">Art der Kündigung</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ $declaration['type'] === 'ausserordentlich' ? 'Außerordentlich' : 'Ordentlich' }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-night">Grund</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ $declaration['reason'] ?? '–' }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-night">Zeitpunkt</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ $declaration['timing'] === 'datum' ? 'Zu einem bestimmten Datum' : 'Zum nächstmöglichen Zeitpunkt' }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-night">Gewünschtes Datum</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ $declaration['effective_date'] ?? '–' }}</dd>
                </div>
            </dl>
            <p>Du kannst diese Seite speichern oder drucken.</p>
        </div>
    </section>
@endsection
