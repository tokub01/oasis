{{--
    Layout for the staff area (/moderation): moderators and admins, behind the `staff` middleware (role ≥ moderator
    and confirmed two-factor authentication). Deliberately plainer and denser than the member app: it is a work tool.

    Use: @extends('layouts.staff', ['title' => …, 'active' => …]) + @section('content'), or as a Livewire layout.

    Variables:
      title   string       page title (suffix " · Moderation" is added)
      active  string|null  current navigation item: queue|roles|admin|transparency|votes
--}}
@php
    $user = auth()->user();
    $active ??= null;
    $nav = [
        'queue' => ['Warteschlange', route('moderation.index'), true],
        'roles' => ['Rollen', route('moderation.roles.index'), $user?->can('manageRoles')],
        'admin' => ['Statistik', route('admin.dashboard'), $user?->can('administrate')],
        'transparency' => ['Transparenz', route('admin.transparency.index'), $user?->can('administrate')],
        'votes' => ['Abstimmungen', route('admin.votes.index'), $user?->can('administrate')],
    ];
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' · Moderation' : 'Moderation' }} · Oasis</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/oasis.js'])
    @livewireStyles
</head>
<body class="bg-sand text-ink antialiased">
    <a href="#inhalt" class="o-skip-link">Zum Inhalt springen</a>

    <header class="bg-night text-on-night">
        <div class="mx-auto flex max-w-[1296px] flex-wrap items-center justify-between gap-x-8 gap-y-3 px-5 py-3 lg:px-10">
            <a href="{{ route('moderation.index') }}" class="flex min-h-11 items-center gap-3 text-on-night no-underline">
                <x-oasis.logo :size="30" :wordmark="false" />
                <span class="text-lg font-extrabold">Moderation</span>
            </a>
            <nav aria-label="Moderationsbereich" class="flex flex-wrap items-center gap-x-6">
                @foreach ($nav as $key => [$label, $href, $visible])
                    @if ($visible)
                        <a href="{{ $href }}" @if ($active === $key) aria-current="page" @endif
                           @class([
                               'inline-flex min-h-11 items-center text-base no-underline',
                               'font-extrabold text-on-night underline decoration-sun decoration-2 underline-offset-8' => $active === $key,
                               'font-semibold text-on-night-2' => $active !== $key,
                           ])>{{ $label }}</a>
                    @endif
                @endforeach
                <a href="{{ route('app.home') }}" class="inline-flex min-h-11 items-center text-base font-semibold text-on-night-2 no-underline">Zur App</a>
            </nav>
            <p class="m-0 text-sm text-on-night-muted">{{ $user?->display_name }} · {{ $user?->role?->label() }}</p>
        </div>
    </header>

    <main id="inhalt" tabindex="-1" class="mx-auto w-full max-w-[1296px] px-5 py-8 outline-none lg:px-10">
        @if (session('status'))
            <p role="status" class="mb-6 rounded-2xl bg-dune px-[18px] py-3.5">{{ session('status') }}</p>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>
