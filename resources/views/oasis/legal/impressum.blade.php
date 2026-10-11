{{--
    Imprint draft from Rechtliches.dc.html at oasis.impressum.
    Config (oasis.operator, oasis.mail) supplies the § 5 DDG operator details, the § 18 MStV responsible person,
    the DSA Art. 11/12 contact point and the § 36 VSBG notice. No JavaScript required.
--}}
@extends('oasis.legal._frame', ['legalTitle' => 'Impressum', 'legalKey' => 'impressum'])
@section('legal-content')
    <p class="mb-6 rounded-[14px] bg-dune p-4">Platzhalter: Diese Seite braucht einen anwaltlich geprüften Text.</p>
    <div class="flex flex-col gap-[24px] [max-width:44em]">
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">Angaben gemäß § 5 DDG</h2>
            <p class="m-0">{{ config('oasis.operator.legal_name') }}<br>{{ config('oasis.operator.legal_form') }}<br>{{ config('oasis.operator.street') }}<br>{{ config('oasis.operator.city') }}<br>{{ config('oasis.operator.country') }}</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">Kontakt</h2>
            <p class="m-0">E-Mail: {{ config('oasis.mail.contact') }}<br>Telefon: {{ config('oasis.operator.phone') }}</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">Umsatzsteuer</h2>
            <p class="m-0">Umsatzsteuer-Identifikationsnummer nach § 27a UStG: {{ config('oasis.operator.vat_id') }}</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">Verantwortlich für den Inhalt</h2>
            <p class="m-0">Verantwortlich nach § 18 Abs. 2 MStV: {{ config('oasis.operator.responsible') }}, {{ config('oasis.operator.street') }}, {{ config('oasis.operator.city') }}</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">Kontaktstelle nach dem Digital Services Act</h2>
            <p class="m-0">Zentrale Kontaktstelle für Behörden und Nutzer:innen nach Art. 11 und 12 DSA: {{ config('oasis.mail.dsa') }}. Sprachen: Deutsch, Englisch.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">Verbraucherstreitbeilegung</h2>
            <p class="m-0">{{ config('oasis.operator.vsbg_notice') }}</p>
        </section>
    </div>

@endsection
