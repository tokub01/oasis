{{--
    Public cancellation page from Kuendigen.dc.html at oasis.kuendigen, for § 312k BGB declarations.
    Includes _contract with $mode=kuendigen; optional cancellation JS improves field visibility. Usable without JS.
--}}
@extends('layouts.oasis', ['title' => 'Verträge hier kündigen', 'description' => 'Verträge hier kündigen bei Oasis.', 'noindex' => false])
@section('content')
    <section class="o-section-s">@include('oasis.forms._contract', ['mode' => 'kuendigen'])</section>
@endsection
