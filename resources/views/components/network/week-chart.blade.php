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
        <caption class="mb-3 text-left text-[21px] font-extrabold text-night">{{ $caption }}</caption>
        <thead>
            <tr class="text-left text-sm text-muted">
                <th scope="col" class="py-1 pr-3 text-left align-bottom font-bold">Woche ab</th>
                @foreach ($series as $seriesKey => $label)
                    <th wire:key="week-heading-{{ $caption }}-header-{{ $seriesKey }}" scope="col" class="w-1/4 py-1 pr-3 align-bottom font-bold">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($weeks as $row)
                <tr wire:key="week-row-{{ $caption }}-{{ $row['week']->format('Y-m-d') }}" class="border-t border-paper-line">
                    <th scope="row" class="py-1.5 pr-3 text-left font-medium whitespace-nowrap">{{ $row['week']->locale('de')->isoFormat('D. MMM') }}</th>
                    @foreach (array_keys($series) as $index => $key)
                        <td wire:key="week-cell-{{ $caption }}-{{ $row['week']->format('Y-m-d') }}-{{ $key }}" class="w-1/4 py-1.5 pr-3">
                            <span class="flex items-center gap-2">
                                {{-- Bars scale with the cell (at most 120px), so the table fits on phones; zero draws no bar. --}}
                                <span aria-hidden="true" class="block max-w-[120px] flex-1">
                                    @if ($row[$key] > 0)
                                        <span class="block h-2.5 rounded-full {{ $colors[$index % 3] }}" style="width: {{ round($row[$key] / $max * 100) }}%"></span>
                                    @endif
                                </span>
                                <span>{{ $row[$key] }}</span>
                            </span>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
