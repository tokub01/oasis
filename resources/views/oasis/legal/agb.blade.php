@extends('oasis.legal._frame', ['legalTitle' => 'AGB', 'legalKey' => 'agb'])
@section('legal-content')

<div class="flex flex-col gap-[22px] [max-width:44em]">
<p class="m-0 [padding:14px_18px] rounded-[14px] bg-dune text-[15px]">Platzhalter: Diese Seite braucht einen anwaltlich geprüften Text.</p>
<section class="flex flex-col gap-[6px]"><h2 class="m-0 text-[20px] font-extrabold text-night">§ 1 Geltungsbereich</h2><p class="m-0">{{ config('oasis.legal.text') }}</p></section>
<section class="flex flex-col gap-[6px]"><h2 class="m-0 text-[20px] font-extrabold text-night">§ 2 Test und Vertragsschluss</h2><p class="m-0">{{ config('oasis.trial_days') }} Tage Test ohne Zahlungsdaten, Mitgliedschaft erst nach aktiver Bestellung</p></section>
<section class="flex flex-col gap-[6px]"><h2 class="m-0 text-[20px] font-extrabold text-night">§ 3 Preise und Zahlung</h2><p class="m-0">{{ config('oasis.legal.prices') }}</p></section>
<section class="flex flex-col gap-[6px]"><h2 class="m-0 text-[20px] font-extrabold text-night">§ 4 Laufzeit und Kündigung</h2><p class="m-0">{{ config('oasis.legal.contract_term') }}</p></section>
<section class="flex flex-col gap-[6px]"><h2 class="m-0 text-[20px] font-extrabold text-night">§ 5 Unsere Versprechen</h2><p class="m-0">{{ config('oasis.legal.promises') }}</p></section>
<section class="flex flex-col gap-[6px]"><h2 class="m-0 text-[20px] font-extrabold text-night">§ 6 Inhalte und Community-Regeln</h2><p class="m-0">{{ config('oasis.legal.text') }}</p></section>
</div>

@endsection
