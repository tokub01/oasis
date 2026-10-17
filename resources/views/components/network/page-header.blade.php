{{--
    Uniform heading of an app page: eyebrow (small label above), the page's h1 and an optional lead sentence.

    Props: title (string) h1 text, eyebrow (string|null) label above the heading
    Slot:  lead text below the heading (optional, inline content only – it is wrapped in a <p>)
--}}
@props(['title', 'eyebrow' => null])

<header {{ $attributes->class('flex flex-col gap-2') }}>
    @if ($eyebrow)
        <p class="o-eyebrow m-0">{{ $eyebrow }}</p>
    @endif
    <h1 class="o-h2 m-0 text-night">{{ $title }}</h1>
    @if ($slot->isNotEmpty())
        <p class="o-lead m-0 max-w-[62ch] text-ink-2">{{ $slot }}</p>
    @endif
</header>
