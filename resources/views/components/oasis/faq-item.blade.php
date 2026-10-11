@props(['question', 'open' => false])

<details @if ($open) open @endif class="border-b border-dune-line">
    <summary class="flex min-h-11 items-center justify-between gap-4 py-5 text-[19px] font-bold text-night">
        {{ $question }}
        <x-oasis.icon name="plus" :size="22" :stroke="2" class="o-faq-icon" />
    </summary>
    <div {{ $attributes->merge(['class' => 'mb-[22px] flex max-w-[38em] flex-col gap-3 text-[17px] leading-[1.6] text-ink-2']) }}>
        {{ $slot }}
    </div>
</details>
