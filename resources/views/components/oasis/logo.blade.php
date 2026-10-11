@props(['size' => 40, 'wordmark' => true, 'waveWidth' => 7])

{{-- Half sun over two waves; the wordmark is "oasis" in Fraunces 600. --}}
<span {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }}>
    <svg viewBox="0 0 120 120" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" focusable="false" class="flex-none">
        <path d="M26 62 A34 34 0 0 1 94 62 Z" fill="#E06A43"/>
        <path d="M14 76 q11.5 -9 23 0 t23 0 t23 0 t23 0" fill="none" stroke="#2E9E95" stroke-width="{{ $waveWidth }}" stroke-linecap="round"/>
        <path d="M32 92 q9.5 -8 19 0 t19 0 t19 0" fill="none" stroke="#2E9E95" stroke-width="{{ $waveWidth }}" stroke-linecap="round" opacity="0.6"/>
    </svg>
    @if ($wordmark)
        <span class="o-display text-[30px] font-semibold leading-none tracking-[-0.5px]">oasis</span>
    @endif
</span>
