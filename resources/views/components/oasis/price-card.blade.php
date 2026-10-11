{{--
    Price card for one membership tier. Shows the monthly values; the "pricing"
    JS module swaps in the yearly values from the data-month/data-year attributes.
--}}
@props(['tier', 'subtitle', 'featured' => false])

@php
    $t = config('oasis.tiers.'.$tier);
    $yearMonths = config('oasis.year_months');
    $monthly = $t['monthly'];
    $yearly = $monthly * $yearMonths;
    $from = $t['min'] ? 'ab ' : '';
    $euro = fn (float $v) => \App\Support\OasisFormat::euro($v);
    $cents = fn (float $v) => $from.'rund '.round($v * 100).' Cent am Tag';

    $values = [
        'price' => [$from.$euro($monthly), $from.$euro($yearly)],
        'unit' => ['/ Monat', '/ Jahr'],
        'day' => [$cents($monthly * 12 / 365), $cents($yearly / 365)],
        'note' => ['oder '.$from.$euro($yearly).' im Jahr', 'entspricht '.$euro($yearly / 12).' im Monat'],
    ];
@endphp

<div @class([
    'flex flex-col gap-[18px] rounded-[28px] border px-8 py-9',
    'border-night bg-night text-on-night shadow-[0_30px_60px_-30px_rgba(14,43,47,0.6)]' => $featured,
    'border-dune-line bg-paper' => ! $featured,
])>
    <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
        <div class="flex flex-col gap-1">
            <h2 @class(['o-display text-[36px] leading-[1.1] font-semibold', 'text-night' => ! $featured])>{{ $t['name'] }}</h2>
            <p @class(['text-base', 'text-on-night-muted' => $featured, 'text-muted' => ! $featured])>{{ $subtitle }}</p>
        </div>
        @if ($featured)
            <span class="rounded-full bg-sun px-3.5 py-1.5 text-sm font-extrabold text-sun-ink">Standard</span>
        @endif
    </div>
    <p @class(['flex items-baseline gap-2', 'text-night' => ! $featured])>
        <span class="o-display text-[60px] leading-none font-semibold" data-month="{{ $values['price'][0] }}" data-year="{{ $values['price'][1] }}">{{ $values['price'][0] }}</span>
        <span @class(['text-lg font-semibold', 'text-on-night-2' => $featured, 'text-ink-2' => ! $featured]) data-month="{{ $values['unit'][0] }}" data-year="{{ $values['unit'][1] }}">{{ $values['unit'][0] }}</span>
    </p>
    <p @class(['text-[15px]', 'text-on-night-2' => $featured, 'text-ink-2' => ! $featured])>
        <span data-month="{{ $values['day'][0] }}" data-year="{{ $values['day'][1] }}">{{ $values['day'][0] }}</span> ·
        <span data-month="{{ $values['note'][0] }}" data-year="{{ $values['note'][1] }}">{{ $values['note'][0] }}</span>
    </p>
    <p @class(['text-[17px] leading-[1.6]', 'text-on-night-2' => $featured, 'text-ink-2' => ! $featured])>{{ $slot }}</p>
    <a href="{{ route('oasis.testen') }}" @class([
        'o-btn mt-auto min-h-[52px] px-[22px] text-[17px]',
        'o-btn-primary' => $featured,
        'o-btn-outline' => ! $featured,
    ])>{{ config('oasis.trial_days') }} Tage kostenlos testen</a>
</div>
