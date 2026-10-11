{{--
    Two-column page body for wide app pages (layout variable `wide` => true): main content left, a side column right
    from 1280px; below that the side column follows the main content. The side column sticks while scrolling.

    Slots: default (main content), aside (side column, optional – without it the main content uses the full width)
    Props: label (string) accessible name of the side column, default "Überblick"
--}}
@props(['label' => 'Überblick'])

<div {{ $attributes->class(['grid gap-8', 'xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start' => isset($aside)]) }}>
    <div class="flex min-w-0 flex-col gap-8">
        {{ $slot }}
    </div>
    @isset($aside)
        <aside aria-label="{{ $label }}" class="flex flex-col gap-5 xl:sticky xl:top-8">
            {{ $aside }}
        </aside>
    @endisset
</div>
