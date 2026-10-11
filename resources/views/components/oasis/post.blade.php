{{--
    A "Tropfen" card. Props match what real posts will provide later.

    variant="compact" (default): small card inside the phone mockups.
    variant="card": full feed card (Entdecken) on paper with border and larger avatar,
        optional profile link (href), optional oasis name (context, shown as "in …"),
        optional photo via the "media" slot, and guest action buttons (members only).
--}}
@props([
    'name',
    'handle',
    'time',
    'initials',
    'avatar' => 'sun', // sun | spring | night | dune
    'compact' => false,
    'variant' => 'compact', // compact | card
    'href' => null, // card variant: profile URL
    'context' => null, // card variant: oasis name
])

@php
    $avatarClasses = [
        'sun' => 'bg-sun text-sun-ink',
        'spring' => 'bg-spring text-sun-ink',
        'night' => 'bg-night text-on-night',
        'dune' => 'bg-dune-line text-night',
    ][$avatar];
@endphp

@if ($variant === 'card')
    <article {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-[24px] border border-paper-line bg-paper p-[22px]']) }}>
        <div class="flex items-center gap-3">
            @if ($href)
                <a href="{{ $href }}" aria-label="Profil von {{ $name }}" class="flex-none no-underline">
                    <span aria-hidden="true" class="flex size-11 items-center justify-center rounded-full text-[15px] font-extrabold {{ $avatarClasses }}">{{ $initials }}</span>
                </a>
            @else
                <span aria-hidden="true" class="flex size-11 flex-none items-center justify-center rounded-full text-[15px] font-extrabold {{ $avatarClasses }}">{{ $initials }}</span>
            @endif
            <div class="flex flex-col leading-[1.35]">
                @if ($href)
                    <a href="{{ $href }}" class="text-base font-extrabold text-ink no-underline">{{ $name }}</a>
                @else
                    <span class="text-base font-extrabold">{{ $name }}</span>
                @endif
                <span class="text-sm text-muted">{{ '@'.$handle }}{{ $context ? ' · in '.$context : '' }} · {{ $time }}</span>
            </div>
        </div>
        <p class="m-0 text-[17px] leading-[1.55]">{{ $slot }}</p>
        {{ $media ?? '' }}
        <div class="flex gap-1.5 text-muted">
            @foreach (['reply' => 'Antworten', 'repost' => 'Weitertragen', 'heart' => 'Reagieren'] as $icon => $label)
                <button type="button" aria-disabled="true" aria-label="{{ $label }}, nur für Mitglieder"
                        class="inline-flex size-11 cursor-not-allowed items-center justify-center rounded-full border-none bg-transparent text-muted">
                    <x-oasis.icon :name="$icon" :size="20" />
                </button>
            @endforeach
        </div>
    </article>
@else
    <article {{ $attributes->merge(['class' => 'flex flex-col gap-2 rounded-[20px] bg-sand p-3.5']) }}>
        <div class="flex items-center gap-2.5">
            <div aria-hidden="true" class="flex size-9 flex-none items-center justify-center rounded-full text-[13px] font-extrabold {{ $avatarClasses }}">{{ $initials }}</div>
            <div class="flex flex-col leading-[1.25]">
                <span class="text-sm font-bold">{{ $name }}</span>
                <span class="text-[13px] text-muted">{{ '@'.$handle }} · {{ $time }}</span>
            </div>
        </div>
        <div class="text-[15px] leading-[1.45]">{{ $slot }}</div>
        <div aria-hidden="true" class="flex gap-[18px] text-muted">
            <x-oasis.icon name="reply" :size="18" />
            <x-oasis.icon name="repost" :size="18" />
            <x-oasis.icon name="heart" :size="18" />
        </div>
    </article>
@endif
