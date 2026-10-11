{{--
    Shared frame for pages that guests may read (Tropfen, oasis, profile): members get the app shell, guests the
    public website layout with a sign-up hint. Usage:

        @extends('app.network.frame', ['title' => …, 'active' => …, 'description' => …])
        @section('network-content') … @endsection

    Variables: title (string), active (nav key for members), description (meta description for guests),
    noindex (bool, default false; members-only content is never rendered for guests anyway), wide (bool, default
    false: wider column for pages with a side column, see x-network.columns).
--}}
@extends(auth()->check() ? 'layouts.app' : 'layouts.oasis', [
    'title' => $title,
    'active' => $active ?? null,
    'description' => $description ?? 'Oasis – das werbefreie soziale Netzwerk.',
    'noindex' => $noindex ?? false,
    'wide' => $wide ?? false,
])

@section('content')
    @guest
        <div role="region" aria-label="Hinweis für Gäste" class="border-b border-dune-line bg-dune">
            <div class="o-container flex flex-wrap items-center justify-between gap-x-6 gap-y-3 py-[18px]">
                <p class="m-0 flex items-center gap-3 text-base leading-normal">
                    <x-oasis.icon name="eye" :size="22" class="text-night" />
                    <span><b>Du liest als Gast.</b> Zum Folgen, Antworten und Schreiben brauchst du ein Konto.</span>
                </p>
                <a href="{{ route('register') }}" class="o-btn o-btn-md o-btn-primary text-base font-extrabold">{{ config('oasis.trial_days') }} Tage kostenlos testen</a>
            </div>
        </div>
        <div class="o-container py-[clamp(32px,5vw,64px)]">
            <div @class(["mx-auto flex flex-col gap-6", "max-w-[1180px]" => $wide ?? false, "max-w-[760px]" => ! ($wide ?? false)])>
                @yield('network-content')
            </div>
        </div>
    @else
        <div class="flex flex-col gap-6">
            @yield('network-content')
        </div>
    @endguest
@endsection
