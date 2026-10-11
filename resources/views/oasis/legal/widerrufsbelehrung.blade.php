{{--
    Withdrawal-policy draft from Rechtliches.dc.html at oasis.widerrufsbelehrung.
    The reviewed §§ 355 ff. BGB policy replaces the config placeholder before launch. No JavaScript required.
--}}
@extends('oasis.legal._frame', ['legalTitle' => 'Widerrufsbelehrung', 'legalKey' => 'widerrufsbelehrung'])
@section('legal-content')

    <div class="flex flex-col gap-[18px] [max-width:44em]">
        <p class="m-0 [padding:14px_18px] rounded-[14px] bg-dune text-[15px]">Platzhalter: Diese Seite braucht einen anwaltlich geprüften Text.</p>
        <p class="m-0">{{ config('oasis.legal.withdrawal') }}</p>
        <a href="{{ route('oasis.widerrufen') }}" class="o-btn o-btn-md o-btn-outline self-start text-base font-extrabold">Vertrag widerrufen</a>
    </div>

@endsection
