{{-- Shown right after a complaint was stored (moderation.complaint.store / .account.store). No variables. --}}
@extends('layouts.oasis', ['title' => 'Widerspruch eingegangen', 'description' => 'Dein Widerspruch ist eingegangen.', 'noindex' => true])

@section('content')
    <x-oasis.auth-card title="Danke, dein Widerspruch ist da." subtitle="Wir haben dir eine Eingangsbestätigung per E-Mail geschickt.">
        <p class="m-0 text-[17px] leading-[1.6] text-ink-2">Eine andere Person aus unserem Team als die, die zuerst entschieden hat, prüft die Entscheidung. Das Ergebnis und die Begründung bekommst du per E-Mail.</p>
        <a href="{{ route('oasis.home') }}" class="o-btn o-btn-dark">Zur Startseite</a>
    </x-oasis.auth-card>
@endsection
