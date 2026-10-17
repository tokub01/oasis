{{--
    Admin statistics, component App\Livewire\Admin\Dashboard (route admin.dashboard). Sums only, no people and no content.
    Variables: $members, $network, $chat, $moderation (AdminStats), $weekly (12 weeks); the period is $days.
    Revenue card: $revenueMonth (first day, German time), $revenue and $revenuePrevious (cents, payments), $isCurrentMonth.
--}}
@php
    $euro = fn ($value) => \App\Support\OasisFormat::euro((float) $value);
    $tiers = config('oasis.tiers');
    $outcomes = collect($moderation['period']['decisions_by_outcome'])->sum();
    $median = $moderation['period']['median_time_to_decision_seconds'];
@endphp

<div class="flex flex-col gap-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-2">
            <p class="o-eyebrow m-0">Admin</p>
            <h1 class="o-h2 m-0 text-night">Statistik</h1>
            <p class="m-0 text-ink-2">Nur Summen. Zeitraumwerte beziehen sich auf die letzten {{ $days }} Tage.</p>
        </div>
        <div class="flex flex-col gap-1.5">
            <label for="admin-period" class="text-sm font-bold">Zeitraum</label>
            <select id="admin-period" wire:model.live="days" class="o-input">
                @foreach (\App\Livewire\Admin\Dashboard::PERIODS as $option)
                    <option value="{{ $option }}">{{ $option }} Tage</option>
                @endforeach
            </select>
        </div>
    </div>

    <section aria-labelledby="admin-einnahmen" class="flex flex-col gap-4 rounded-[24px] bg-night p-6 text-on-night">
        @php
            $monthName = $revenueMonth->settings(['locale' => 'de'])->isoFormat('MMMM YYYY');
            $delta = $revenue['cents'] - $revenuePrevious['cents'];
            $money = fn (int $cents) => \App\Support\OasisFormat::money($cents / 100);
        @endphp
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h2 id="admin-einnahmen" class="m-0 text-[21px] font-extrabold">{{ $isCurrentMonth ? 'Diesen Monat eingenommen' : 'Eingenommen im '.$monthName }}</h2>
                <p class="m-0 text-sm text-on-night-muted">{{ $revenueMonth->format('d.m.') }}–{{ $revenueMonth->endOfMonth()->format('d.m.Y') }}{{ $isCurrentMonth ? ', Stand heute' : '' }} · brutto, ohne Erstattungen, vor Mollie-Gebühren</p>
            </div>
            <div class="flex flex-wrap gap-2" role="group" aria-label="Monat wählen">
                <button type="button" wire:click="shiftMonth(-1)" wire:loading.attr="disabled" class="o-btn o-btn-sm border border-on-night-muted bg-transparent text-on-night">‹ Vormonat</button>
                @unless ($isCurrentMonth)
                    <button type="button" wire:click="shiftMonth(1)" wire:loading.attr="disabled" class="o-btn o-btn-sm border border-on-night-muted bg-transparent text-on-night">Nächster Monat ›</button>
                    <button type="button" wire:click="thisMonth" wire:loading.attr="disabled" class="o-btn o-btn-sm bg-sun text-sun-ink">Dieser Monat</button>
                @endunless
            </div>
        </div>
        <p class="m-0 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <span class="o-display text-[clamp(40px,8vw,56px)] leading-none">{{ $money($revenue['cents']) }}</span>
            <span class="text-on-night-muted">aus {{ $revenue['payments'] }} {{ $revenue['payments'] === 1 ? 'Zahlung' : 'Zahlungen' }}</span>
        </p>
        <p class="m-0 text-sm text-on-night-muted">
            Vormonat: {{ $money($revenuePrevious['cents']) }}
            @if ($revenuePrevious['cents'] > 0 || $revenue['cents'] > 0)
                ({{ $delta >= 0 ? '+' : '−' }}{{ $money(abs($delta)) }}{{ $isCurrentMonth ? ' bisher' : '' }})
            @endif
        </p>
    </section>

    <section aria-labelledby="admin-mitglieder" class="flex flex-col gap-3">
        <h2 id="admin-mitglieder" class="m-0 text-[21px] font-extrabold text-night">Mitglieder und Beiträge</h2>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-network.stat :value="$members['paying']" label="zahlende Mitglieder" />
            <x-network.stat :value="$euro($members['mrr'])" label="monatlich wiederkehrende Beiträge" />
            <x-network.stat :value="$members['trials_active']" label="Konten im Test" />
            <x-network.stat :value="$members['registered']" label="Konten insgesamt ({{ $members['verified'] }} bestätigt)" />
            <x-network.stat :value="$members['trials_started']" label="Tests gestartet" />
            <x-network.stat :value="$members['trials_converted']" label="Tests umgewandelt" />
            <x-network.stat :value="$members['trials_expired']" label="Tests ausgelaufen" />
            <x-network.stat :value="$members['cancellations']" label="Kündigungen" />
            <x-network.stat :value="$members['founding_used']" label="Gründungsplätze vergeben ({{ $members['founding_remaining'] }} frei)" />
            <x-network.stat :value="$members['gifts_sold']" label="Geschenke verkauft" />
            <x-network.stat :value="$members['gifts_redeemed']" label="Geschenke eingelöst" />
        </div>
        @if ($members['by_tier'] !== [])
            <ul class="m-0 flex list-none flex-wrap gap-2 p-0">
                @foreach ($members['by_tier'] as $tier => $count)
                    <li wire:key="tier-{{ $tier }}" class="rounded-full bg-paper px-4 py-2"><b>{{ $tiers[$tier]['name'] ?? $tier }}</b>: {{ $count }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="admin-netzwerk" class="flex flex-col gap-3">
        <h2 id="admin-netzwerk" class="m-0 text-[21px] font-extrabold text-night">Netzwerk</h2>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-network.stat :value="$network['posts_period']" label="Tropfen im Zeitraum" />
            <x-network.stat :value="$network['active_authors']" label="Menschen, die geschrieben haben" />
            <x-network.stat :value="$network['posts']" label="Tropfen insgesamt" />
            <x-network.stat :value="$network['oases']" label="Oasen" />
        </div>
        <div class="rounded-[24px] bg-paper p-6">
            <x-network.week-chart caption="Die letzten 12 Wochen" :weeks="$weekly" :series="['registered' => 'Neue Konten', 'posts' => 'Tropfen']" />
        </div>
    </section>

    <section aria-labelledby="admin-chat" class="flex flex-col gap-3">
        <h2 id="admin-chat" class="m-0 text-[21px] font-extrabold text-night">Chat <span class="text-base font-medium text-muted">(nur Metadaten – Inhalte sind verschlüsselt)</span></h2>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-network.stat :value="$chat['identities']" label="eingerichtete Chats" />
            <x-network.stat :value="$chat['conversations']" label="Unterhaltungen" />
            <x-network.stat :value="$chat['messages_period']" label="Nachrichten im Zeitraum" />
            <x-network.stat :value="$chat['stored_messages']" label="gerade gespeichert (verschlüsselt)" />
        </div>
    </section>

    <section aria-labelledby="admin-moderation" class="flex flex-col gap-3">
        <h2 id="admin-moderation" class="m-0 text-[21px] font-extrabold text-night">Moderation</h2>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-network.stat :value="$moderation['open']" label="offene Fälle" />
            <x-network.stat :value="$moderation['in_review']" label="in Prüfung" />
            <x-network.stat :value="$moderation['period']['notices_received']" label="Meldungen im Zeitraum" />
            <x-network.stat :value="$outcomes" label="Entscheidungen im Zeitraum" />
            <x-network.stat :value="$moderation['period']['complaints_received']" label="Beschwerden" />
            <x-network.stat :value="$moderation['period']['complaints_overturned']" label="Beschwerden mit Erfolg" />
            <x-network.stat :value="$median === null ? '–' : \Carbon\CarbonInterval::seconds((int) $median)->cascade()->locale('de')->forHumans(['short' => true, 'parts' => 2])" label="Median bis zur Entscheidung" />
        </div>
    </section>
</div>
