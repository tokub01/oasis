{{--
    A card in a side column. Props: title (string), tone (paper|night|dune, default paper), id (heading id).
    Slot: card content.
--}}
@props(['title', 'tone' => 'paper', 'id' => null])

@php($id ??= 'side-'.\Illuminate\Support\Str::slug($title))

<section aria-labelledby="{{ $id }}" {{ $attributes->class([
    'flex flex-col gap-3 rounded-[24px] p-6',
    'bg-paper text-ink' => $tone === 'paper',
    'bg-night text-on-night' => $tone === 'night',
    'bg-dune text-ink' => $tone === 'dune',
]) }}>
    <h2 id="{{ $id }}" @class(['m-0 text-[19px] font-extrabold', 'text-night' => $tone !== 'night'])>{{ $title }}</h2>
    {{ $slot }}
</section>
