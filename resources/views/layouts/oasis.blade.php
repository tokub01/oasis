{{--
    Oasis page layout. Pages extend it with:
    @extends('layouts.oasis', ['title' => ..., 'description' => ..., 'active' => ..., 'minimal' => ..., 'announce' => ..., 'noindex' => ...])
    Livewire pages: ->layout('layouts.oasis', [...]) and the component renders into $slot.
    Livewire assets load on every page so internal links can use wire:navigate (docs/oasis-livewire.md).
--}}
@php
    $pageTitle = isset($title) ? $title.' · Oasis' : 'Oasis – Social Media, das dir Zeit zurückgibt';
    $pageDescription = $description ?? 'Oasis ist das werbefreie soziale Netzwerk: ein Feed mit Ende, kein Algorithmus, der dich festhält. Getragen von Mitgliedern statt von Werbekunden.';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    @if ($noindex ?? false)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="de_DE">
    <meta property="og:site_name" content="Oasis">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-oasis.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ asset('images/og-oasis.png') }}">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @include('layouts.partials.pwa')
    <link rel="preload" href="{{ asset('fonts/manrope-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/fraunces-latin-full-normal.woff2') }}" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/oasis.js'])
    @livewireStyles
</head>
<body class="bg-sand text-ink antialiased">
    <a href="#inhalt" class="o-skip-link">Zum Inhalt springen</a>

    <x-oasis.header :active="$active ?? null" :minimal="$minimal ?? false" :announce="$announce ?? true" />

    <main id="inhalt" tabindex="-1" class="outline-none">
        {{-- Flash messages that land on a public page, e.g. after deleting the account. The auth pages show
             their own status inside <x-oasis.auth-card>, so they are skipped here. --}}
        @if (session('status') && ! ($minimal ?? false))
            <div class="o-container pt-6">
                <p role="status" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5">{{ session('status') }}</p>
            </div>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <x-oasis.footer />

    @livewireScripts
</body>
</html>
