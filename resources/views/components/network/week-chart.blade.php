{{--
    Accessible weekly bar chart: a real table (screen readers read the numbers) with CSS bars, no JavaScript.

    Props:
      caption  string  table caption
      weeks    Collection<array{week: CarbonImmutable, ...}>  rows from MemberStats/OasisStats::weekly
      series   array<string, string>  key in the row => column label, e.g. ['followers' => 'Neue in der Karawane']
--}}
@props(['caption', 'weeks', 'series'])

@php
    $max = max(1, $weeks->flatMap(fn ($row) => collect($series)->keys()->map(fn ($key) => $row[$key]))->max());
    $colors = ['bg-spring', 'bg-sun', 'bg-night'];
@endphp

<div class="overflow-x-auto">
    <table class="w-full border-collapse text-[15px]">
        <caption class="mb-3 text-left font-bold text-night">{{ $caption }}</caption>
        <thead>
            <tr class="text-left text-sm text-muted">
                <th scope="col" class="py-1 pr-3 font-bold">Woche ab</th>
                @foreach ($series as $seriesKey => $label)
                    <th wire:key="week-heading-{{ $caption }}-header-{{ $seriesKey }}" scope="col" class="py-1 pr-3 font-bold">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($weeks as $row)
                <tr wire:key="week-row-{{ $caption }}-{{ $row['week']->format('Y-m-d') }}" class="border-t border-paper-line">
                    <th scope="row" class="py-1.5 pr-3 font-medium whitespace-nowrap">{{ $row['week']->locale('de')->isoFormat('D. MMM') }}</th>
                    @foreach (array_keys($series) as $index => $key)
                        <td wire:key="week-cell-{{ $caption }}-{{ $row['week']->format('Y-m-d') }}-{{ $key }}" class="py-1.5 pr-3">
                            <span class="flex items-center gap-2">
                                <span aria-hidden="true" class="inline-block h-2.5 rounded-full {{ $colors[$index % 3] }}" style="width: {{ round($row[$key] / $max * 120) }}px"></span>
                                <span>{{ $row[$key] }}</span>
                            </span>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
