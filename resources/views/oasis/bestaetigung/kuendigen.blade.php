{{--
    Cancellation receipt page based on Kuendigen.dc.html at oasis.bestaetigung.kuendigen.
    $declaration: array<string, string|null> flashed by CancellationController after successful receipt mails.
    $fields: labelled copy for _declaration; supports retaining the declaration under § 312k BGB. No JS required.
--}}
@extends('layouts.oasis', ['title' => 'Deine Kündigung ist eingegangen', 'description' => 'Deine Kündigung ist eingegangen bei Oasis.', 'noindex' => true])

@php
    $declaration = session('oasis.submitted.kuendigen');
    $fields = [
        'Eingegangen am' => $declaration['received_at'],
        'Vertrag' => $declaration['contract'],
        'Name' => $declaration['name'],
        'E-Mail' => $declaration['email'],
        'Benutzername' => $declaration['username'] ?? null,
        'Art der Kündigung' => $declaration['type'] === 'ausserordentlich' ? 'Außerordentlich' : 'Ordentlich',
        'Grund' => $declaration['reason'] ?? null,
        'Zeitpunkt' => $declaration['timing'] === 'datum' ? 'Zu einem bestimmten Datum' : 'Zum nächstmöglichen Zeitpunkt',
        'Gewünschtes Datum' => $declaration['effective_date'] ?? null,
    ];
@endphp

@section('content')
    <section class="o-section-s">
        <div class="o-container max-w-3xl">
            <h1 class="o-h1 mb-6">Deine Kündigung ist eingegangen</h1>
            <p class="mb-6">Wir haben deine Kündigung gespeichert und dir die Bestätigung mit Datum, Uhrzeit und Inhalt per E-Mail geschickt.</p>
            @include('oasis.forms._declaration', ['fields' => $fields])
            <p>Du kannst diese Seite speichern oder drucken.</p>
        </div>
    </section>
@endsection
