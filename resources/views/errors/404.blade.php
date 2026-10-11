@extends('layouts.oasis', [
    'title' => 'Seite nicht gefunden',
    'description' => 'Diese Seite gibt es leider nicht.',
    'noindex' => true,
])

@section('content')
    <section aria-labelledby="fehler-titel" class="o-section bg-night text-on-night">
        <div class="o-container flex flex-col items-center gap-[18px] text-center">
            <svg viewBox="0 0 320 200" aria-hidden="true" focusable="false" class="h-auto w-80 max-w-full">
                <path d="M70 110 A90 90 0 0 1 250 110" fill="none" stroke="#E06A43" stroke-width="5" stroke-dasharray="10 12" stroke-linecap="round"/>
                <path d="M20 140 q35 -24 70 0 t70 0 t70 0 t70 0" fill="none" stroke="#2E9E95" stroke-width="10" stroke-linecap="round" opacity="0.8"/>
                <path d="M60 175 q35 -24 70 0 t70 0 t70 0" fill="none" stroke="#2E9E95" stroke-width="10" stroke-linecap="round" opacity="0.4"/>
            </svg>
            <p class="o-eyebrow o-eyebrow-night">Fehler 404</p>
            <h1 id="fehler-titel" class="o-display text-[clamp(44px,6vw,84px)] leading-[1.02] tracking-[-0.02em]">Fata Morgana.</h1>
            <p class="max-w-[30em] text-[19px] leading-[1.6] text-on-night-2">Diese Seite gibt es leider nicht. Vielleicht ist sie weitergezogen.</p>
            <div class="mt-3.5 flex flex-wrap items-center justify-center gap-x-7 gap-y-3">
                <a href="{{ route('oasis.home') }}" class="o-btn o-btn-primary">Zurück zur Startseite</a>
                <a href="{{ route('oasis.hilfe') }}" class="o-link text-[17px] text-on-night">Hilfe &amp; FAQ</a>
            </div>
        </div>
    </section>
@endsection
