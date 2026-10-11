@extends('oasis.legal._frame', ['legalTitle' => 'Widerrufsbelehrung', 'legalKey' => 'widerrufsbelehrung'])
@section('legal-content')

<div class="flex flex-col gap-[18px] [max-width:44em]">
<p class="m-0 [padding:14px_18px] rounded-[14px] bg-dune text-[15px]">Platzhalter: Diese Seite braucht einen anwaltlich geprüften Text.</p>
<p class="m-0">{{ config('oasis.legal.withdrawal') }}</p>
<a href="{{ route('oasis.widerrufen') }}" class="[align-self:flex-start] inline-flex items-center [min-height:48px] [padding:0_22px] [border:2px_solid_#0E2B2F] rounded-[999px] text-[16px] font-extrabold text-night no-underline">Vertrag widerrufen</a>
</div>

@endsection
