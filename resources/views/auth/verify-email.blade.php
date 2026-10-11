{{--
    Verify email notice: Fortify verifyEmailView (route `verification.notice`), shown to signed-in users whose
    address is not confirmed yet. "verification-link-sent" is Fortify's status after re-sending the link.
--}}
@extends('layouts.oasis', ['title' => 'E-Mail bestätigen', 'description' => 'Bestätige deine E-Mail-Adresse für Oasis.', 'minimal' => true, 'noindex' => true])

@section('content')
    <x-oasis.auth-card title="Fast geschafft." subtitle="Bitte bestätige deine E-Mail-Adresse.">
        <p class="m-0 text-[17px] leading-[1.6] text-ink-2">Wir haben dir einen Link an <b class="text-ink">{{ auth()->user()->email }}</b> geschickt. Sobald du ihn öffnest, startet dein Test.</p>

        @if (session('status') === 'verification-link-sent')
            <p role="status" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5 text-base">Wir haben dir einen neuen Link geschickt.</p>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="o-btn o-btn-dark w-full">Link noch einmal schicken</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="o-link mx-auto text-base text-night">Abmelden</button>
        </form>
    </x-oasis.auth-card>
@endsection
