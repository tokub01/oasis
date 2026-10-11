{{--
    Withdrawal receipt page based on Kuendigen.dc.html at oasis.bestaetigung.widerrufen.
    $declaration: array<string, string|null> flashed by WithdrawalController after successful receipt mails.
    $fields: labelled copy for _declaration, recording the §§ 355 ff. BGB declaration. No JS required.
--}}
@extends('layouts.oasis', ['title' => 'Dein Widerruf ist eingegangen', 'description' => 'Dein Widerruf ist eingegangen bei Oasis.', 'noindex' => true])

@php
    $declaration = session('oasis.submitted.widerrufen');
    $fields = [
        'Eingegangen am' => $declaration['received_at'],
        'Vertrag' => $declaration['contract'],
        'Name' => $declaration['name'],
        'E-Mail' => $declaration['email'],
        'Vertragsdatum' => $declaration['contracted_on'] ?? null,
    ];
@endphp

@section('content')
    <section class="o-section-s">
        <div class="o-container max-w-3xl">
            <h1 class="o-h1 mb-6">Dein Widerruf ist eingegangen</h1>
            <p class="mb-6">Wir haben deinen Widerruf gespeichert und dir eine Eingangsbestätigung per E-Mail geschickt.</p>
            @include('oasis.forms._declaration', ['fields' => $fields])
            <p>Du kannst diese Seite speichern oder drucken.</p>
        </div>
    </section>
@endsection
