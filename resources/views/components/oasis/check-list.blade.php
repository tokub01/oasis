{{-- List with turquoise check marks. Pass items as an array of strings (HTML allowed via HtmlString). --}}
@props(['items', 'size' => 20])

<ul {{ $attributes->merge(['class' => 'flex flex-col gap-3.5']) }}>
    @foreach ($items as $item)
        <li class="flex items-start gap-3">
            <x-oasis.icon name="check" :size="$size" :stroke="2.4" class="mt-[3px] text-spring-text" />
            <span>{{ $item }}</span>
        </li>
    @endforeach
</ul>
