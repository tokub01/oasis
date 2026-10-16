{{--
    Moderation start page, component App\Livewire\Moderation\Overview (route moderation.index): overview figures and
    latest activity on top, then the Queue component, which loads and filters the cases itself.

    Variables:
      $figures   QueueOverview::figures (active, unassigned, urgent, mine, complaints, decided_week, oldest_waiting,
                 median_seconds)
      $activity  Collection<ModerationEvent> with actor, newest first
--}}
@php
    $median = $figures['median_seconds'] === null ? '–'
        : \Carbon\CarbonInterval::seconds((int) $figures['median_seconds'])->cascade()->locale('de')->forHumans(['short' => true, 'parts' => 2]);
    // [label, value, hint, filtered queue link, highlight when > 0]
    $cards = [
        ['Aktive Fälle', $figures['active'], 'offen, in Prüfung oder mit Beschwerde', route('moderation.index'), false],
        ['Ohne Zuständigkeit', $figures['unassigned'], 'noch niemandem zugewiesen', route('moderation.index', ['status' => 'open']), true],
        ['Dringend', $figures['urgent'], 'zuerst bearbeiten', route('moderation.index', ['prioritaet' => 'urgent']), true],
        ['Meine Fälle', $figures['mine'], 'dir zugewiesen', route('moderation.index', ['meine' => 1]), false],
        ['Offene Beschwerden', $figures['complaints'], 'Art. 20 DSA, zweite Person entscheidet', route('moderation.index', ['status' => 'complaint_open']), true],
        ['Entschieden', $figures['decided_week'], 'in den letzten 7 Tagen', route('moderation.index', ['status' => 'decided']), false],
    ];
@endphp

<div class="flex flex-col gap-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="o-h2 m-0 text-night">Moderation</h1>
            <p class="m-0 max-w-[48em] text-ink-2">Meldungen nach Art. 16 DSA. Jede Entscheidung braucht eine Begründung, die die Betroffenen verstehen.</p>
        </div>
        <dl class="m-0 flex flex-wrap gap-x-8 gap-y-2 text-[15px]">
            <div><dt class="inline text-muted">Älteste wartende Meldung:</dt> <dd class="m-0 inline font-bold text-night">{{ $figures['oldest_waiting']?->locale('de')->diffForHumans() ?? 'keine' }}</dd></div>
            <div><dt class="inline text-muted">Median bis zur Entscheidung (30 Tage):</dt> <dd class="m-0 inline font-bold text-night">{{ $median }}</dd></div>
        </dl>
    </div>

    <section aria-label="Überblick" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($cards as [$label, $value, $hint, $href, $highlight])
            @php($alert = $highlight && $value > 0)
            <a href="{{ $href }}" wire:navigate wire:key="figure-{{ $loop->index }}" @class([
                'flex flex-col gap-1 rounded-[20px] p-5 no-underline',
                'bg-sun text-sun-ink' => $alert,
                'bg-paper text-ink' => ! $alert,
            ])>
                <span class="text-[30px] leading-none font-extrabold">{{ $value }}</span>
                <span class="font-bold">{{ $label }}</span>
                <span @class(['text-sm', 'text-sun-ink' => $alert, 'text-muted' => ! $alert])>{{ $hint }}</span>
            </a>
        @endforeach
    </section>

    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_340px] xl:items-start">
        <section aria-labelledby="queue-titel" class="flex min-w-0 flex-col gap-4">
            <h2 id="queue-titel" class="m-0 text-[21px] font-extrabold text-night">Warteschlange</h2>
            <livewire:moderation.queue />
        </section>

        <section aria-labelledby="aktivitaet-titel" class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
            <h2 id="aktivitaet-titel" class="m-0 text-[19px] font-extrabold text-night">Letzte Aktivität</h2>
            <ol class="m-0 flex list-none flex-col gap-3 p-0">
                @forelse ($activity as $event)
                    <li wire:key="activity-{{ $event->id }}" class="flex flex-col gap-0.5 border-b border-paper-line pb-3 last:border-b-0 last:pb-0">
                        <a href="{{ route('moderation.cases.show', $event->case_id) }}" wire:navigate class="font-bold text-night no-underline">Fall #{{ $event->case_id }} · {{ $event->type->label() }}</a>
                        <span class="text-sm text-muted">{{ $event->actor?->display_name ?? 'System' }} · {{ $event->created_at->locale('de')->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="text-ink-2">Noch keine Aktivität.</li>
                @endforelse
            </ol>
        </section>
    </div>
</div>
