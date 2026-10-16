{{--
    Host statistics of one oasis (route network.oases.stats). Sums only, no lists of people.
    Variables: $oasis, $totals (OasisStats::totals), $weekly (OasisStats::weekly)
--}}
<div class="flex flex-col gap-6">
<p role="status" aria-live="polite">{{ $postStatus }}</p>
<p wire:loading class="text-muted">Wird geladen …</p>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <a wire:navigate href="{{ route('network.oases.show', $oasis) }}" class="o-link self-start">Zurück zur Oase</a>
            <p class="o-eyebrow m-0">Nur für Gastgeber:innen</p>
            <h1 class="o-h2 m-0 text-night">Statistik: {{ $oasis->name }}</h1>
            <p class="m-0 text-ink-2">Nur Summen. Wer was liest oder schreibt, zeigen wir hier bewusst nicht.</p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            <x-network.stat :value="$totals['members']" label="Mitglieder" />
            <x-network.stat :value="$totals['hosts']" label="Gastgeber:innen" />
            <x-network.stat :value="$totals['posts']" label="Tropfen insgesamt" />
            <x-network.stat :value="$totals['posts_30_days']" label="Tropfen in 30 Tagen" />
            <x-network.stat :value="$totals['active_authors_30_days']" label="Menschen, die in 30 Tagen geschrieben haben" />
            <x-network.stat :value="$totals['removed']" label="Von euch entfernte Tropfen" />
        </div>

        <div class="rounded-[24px] bg-paper p-6">
            <x-network.week-chart caption="Die letzten 12 Wochen" :weeks="$weekly" :series="['joined' => 'Neue Mitglieder', 'posts' => 'Tropfen']" />
        </div>
    </div>
</div>
