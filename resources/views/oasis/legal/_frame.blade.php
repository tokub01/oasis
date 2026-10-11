{{--
    Shared legal-page frame from Rechtliches.dc.html.
    $legalTitle: string document heading; $legalKey: string Oasis route suffix for active navigation.
    Child views provide legal-content; each document has its own URL. No JavaScript is required.
--}}
@extends('layouts.oasis', ['title' => $legalTitle, 'description' => $legalTitle.' bei Oasis.'])
@section('content')
    <section class="o-section-s">
        <div class="o-container flex flex-col gap-9">
            <p class="o-eyebrow">Rechtliches</p>
            <h1 class="o-h1">{{ $legalTitle }}</h1>
            <div class="flex flex-wrap items-start gap-10">
                <nav aria-label="Rechtliche Dokumente" class="flex flex-[1_1_240px] flex-col gap-1 rounded-[24px] border border-paper-line bg-paper p-3">
                    @foreach (['impressum' => 'Impressum', 'datenschutz' => 'Datenschutz', 'agb' => 'AGB', 'widerrufsbelehrung' => 'Widerrufsbelehrung', 'regeln' => 'Community-Regeln', 'melden' => 'Inhalte melden'] as $key => $label)
                        <a href="{{ route('oasis.'.$key) }}" @if($legalKey === $key) aria-current="page" @endif
                        class="flex min-h-12 items-center rounded-[14px] px-4 font-bold {{ $legalKey === $key ? 'bg-night text-on-night' : 'text-night' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <div class="min-w-0 flex-[999_1_560px] rounded-[28px] border border-paper-line bg-paper p-[clamp(24px,3.5vw,44px)]">
                @yield('legal-content')
            </div>
        </div>
    </div>
</section>
@endsection
