@extends('layouts.oasis', ['title' => 'Vertrag widerrufen', 'description' => 'Vertrag widerrufen bei Oasis.', 'noindex' => false])
@section('content')
<section class="o-section-s">@include('oasis.forms._contract', ['mode' => 'widerrufen'])</section>
@endsection
