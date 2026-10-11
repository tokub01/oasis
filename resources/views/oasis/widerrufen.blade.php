{{--
    Public withdrawal page from Kuendigen.dc.html at oasis.widerrufen, for §§ 355 ff. BGB declarations.
    Includes _contract with $mode=widerrufen. No JavaScript required.
--}}
@extends('layouts.oasis', ['title' => 'Vertrag widerrufen', 'description' => 'Vertrag widerrufen bei Oasis.', 'noindex' => false])
@section('content')
    <section class="o-section-s">@include('oasis.forms._contract', ['mode' => 'widerrufen'])</section>
@endsection
