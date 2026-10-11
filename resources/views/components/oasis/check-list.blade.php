{{--
    List with turquoise check marks (e.g. "In jeder Mitgliedschaft").

    Props:
      items  array  list items; plain strings are escaped, pass an HtmlString for inline markup
      size   int    check icon size in px, default 20
    Extra attributes (e.g. text size classes) go to the <ul>. No JavaScript required.
--}}
@props(['items', 'size' => 20])

<ul {{ $attributes->merge(['class' => 'flex flex-col gap-3.5']) }}>
    @foreach ($items as $item)
        <li class="flex items-start gap-3">
            <x-oasis.icon name="check" :size="$size" :stroke="2.4" class="mt-[3px] text-spring-text" />
            <span>{{ $item }}</span>
        </li>
    @endforeach
</ul>
