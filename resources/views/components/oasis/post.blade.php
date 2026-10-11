{{-- A "Tropfen" card. Props match what real posts will provide later. --}}
@props([
    'name',
    'handle',
    'time',
    'initials',
    'avatar' => 'sun', // sun | spring | night | dune
    'compact' => false,
])

@php
    $avatarClasses = [
        'sun' => 'bg-sun text-sun-ink',
        'spring' => 'bg-spring text-sun-ink',
        'night' => 'bg-night text-on-night',
        'dune' => 'bg-dune-line text-night',
    ][$avatar];
@endphp

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
