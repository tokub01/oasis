{{--
    Privacy draft from Rechtliches.dc.html at oasis.datenschutz.
    Operator, processing and rights placeholders come from config; GDPR review is pending. No JavaScript required.
--}}
@extends('oasis.legal._frame', ['legalTitle' => 'Datenschutzerklärung', 'legalKey' => 'datenschutz'])
@section('legal-content')

    <div class="flex flex-col gap-[22px] [max-width:44em]">
        <p class="m-0 [padding:14px_18px] rounded-[14px] bg-dune text-[15px]">Platzhalter: Diese Seite braucht einen anwaltlich geprüften Text.</p>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">1. Verantwortliche Stelle</h2>
            <p class="m-0">{{ config('oasis.operator.legal_name') }}, {{ config('oasis.operator.street') }}, {{ config('oasis.operator.city') }}, {{ config('oasis.mail.privacy') }}</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">2. Welche Daten wir verarbeiten</h2>
            <p class="m-0">{{ config('oasis.legal.privacy_data') }}</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">3. Zwecke und Rechtsgrundlagen</h2>
            <p class="m-0">{{ config('oasis.legal.text') }}</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">4. Dienstleister und Speicherort</h2>
            <p class="m-0">{{ config('oasis.server_location') }}</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">5. Speicherdauer</h2>
            <p class="m-0">{{ config('oasis.legal.text') }}</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">6. Deine Rechte</h2>
            <p class="m-0">{{ config('oasis.legal.privacy_rights') }}</p>
        </section>
    </div>

@endsection
