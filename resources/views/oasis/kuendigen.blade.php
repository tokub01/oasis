@extends('layouts.oasis', ['title' => 'Verträge hier kündigen', 'description' => 'Verträge hier kündigen bei Oasis.', 'noindex' => false])
@section('content')
<section class="o-section-s">@include('oasis.forms._contract', ['mode' => 'kuendigen'])</section>
@endsection
