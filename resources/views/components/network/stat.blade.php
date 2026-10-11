{{--
    One number with a label, for the statistics pages.
    Props: value (int|string), label (string)
--}}
@props(['value', 'label'])

<div {{ $attributes->class('flex flex-col gap-1 rounded-[20px] bg-paper p-5') }}>
    <span class="text-[28px] leading-none font-extrabold text-night">{{ is_int($value) ? number_format($value, 0, ',', '.') : $value }}</span>
    <span class="text-sm text-ink-2">{{ $label }}</span>
</div>
