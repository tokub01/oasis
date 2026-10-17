{{--
    Two-column page body for wide app pages (layout variable `wide` => true): main content left, a side column right
    from 1280px; below that the side column follows the main content (two cards per row from 768px). The side column
    sticks while scrolling and scrolls on its own when it is taller than the screen.

    Slots: default (main content), aside (side column, optional – without it the main content uses the full width)
    Props: label (string) accessible name of the side column, default "Überblick"
--}}
@props(['label' => 'Überblick'])

<div {{ $attributes->class(['grid gap-8', 'xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start' => isset($aside)]) }}>
    <div class="flex min-w-0 flex-col gap-8">
        {{ $slot }}
    </div>
    @isset($aside)
        <aside aria-label="{{ $label }}" class="grid gap-5 md:grid-cols-2 md:[&>*:last-child:nth-child(odd)]:col-span-2 xl:sticky xl:top-8 xl:flex xl:max-h-[calc(100dvh-4rem)] xl:flex-col xl:items-stretch xl:overflow-y-auto">
            {{ $aside }}
        </aside>
    @endisset
</div>
