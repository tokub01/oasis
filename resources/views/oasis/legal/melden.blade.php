{{--
    Public DSA Art. 16 content-notice form from Rechtliches.dc.html at oasis.melden.
    Uses web-session errors/old input and _feedback; accepted notices are handled by ContentReportController.
    HTTP(S) link, explanation, contact and good-faith declaration are required. No JavaScript required.
--}}
@extends('oasis.legal._frame', ['legalTitle' => 'Inhalte melden', 'legalKey' => 'melden'])
@section('legal-content')

    <livewire:oasis.content-notice />

@endsection
