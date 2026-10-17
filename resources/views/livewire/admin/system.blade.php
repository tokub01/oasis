{{--
    Systemzustand, component App\Livewire\Admin\System (route admin.system). Variables: $results (HealthCheck::run()),
    $latestBackup (string|null), $heartbeats (Collection<SystemHeartbeat>), $tokenSet, $backupKeySet (bool),
    $botRefusals (reason => count today, BotProtection::today()).
--}}
@php($berlin = fn ($date) => $date?->timezone('Europe/Berlin')->format('d.m.Y H:i') ?? '–')
<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-2">
        <p class="o-eyebrow m-0">Admin</p>
        <h1 class="o-h2 m-0 text-night">Systemzustand</h1>
        <p class="m-0 max-w-[52em] text-ink-2">Alles, wovon die Versprechen von Oasis abhängen: Löschfristen, Mollie-Abgleich, Mails und Sicherungen. Probleme meldet der Zeitplaner alle 15 Minuten per E-Mail. Was zu tun ist, steht in docs/oasis-operations.md.</p>
    </div>

    <section aria-labelledby="pruefungen-titel" class="flex flex-col gap-4 rounded-[24px] bg-paper p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="pruefungen-titel" class="m-0 text-[21px] font-extrabold text-night">
                {{ \App\Support\Operations\HealthCheck::healthy($results) ? 'Alles in Ordnung' : 'Es gibt Probleme' }}
            </h2>
            <button type="button" wire:click="check" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline">Jetzt prüfen</button>
        </div>
        <ul class="m-0 flex list-none flex-col gap-2 p-0">
            @foreach ($results as $key => $result)
                <li wire:key="pruefung-{{ $key }}" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-t border-paper-line pt-2">
                    <span class="font-bold text-night">{{ $result['label'] }}</span>
                    <span @class(['text-[15px]', 'text-ink-2' => $result['ok'], 'font-bold text-sun-text' => ! $result['ok']])>{{ $result['ok'] ? '✓' : '✕' }} {{ $result['detail'] }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section aria-labelledby="sicherung-titel" class="flex flex-col gap-3 rounded-[24px] bg-paper p-5 sm:p-6">
        <h2 id="sicherung-titel" class="m-0 text-[21px] font-extrabold text-night">Sicherungen und Überwachung</h2>
        <ul class="m-0 flex list-disc flex-col gap-1 pl-5 text-[15px]">
            <li>Neueste Sicherung: {{ $latestBackup ?? 'noch keine' }} ({{ config('oasis.operations.backup_disk') }}, {{ config('oasis.operations.backup_keep_days') }} Tage aufbewahrt)</li>
            <li>Sicherungsschlüssel: {{ $backupKeySet ? 'gesetzt' : 'fehlt – mit „php artisan oasis:backup-key“ erzeugen' }}</li>
            <li>Überwachungs-URL /gesundheit: {{ $tokenSet ? 'aktiv (mit OASIS_HEALTH_TOKEN)' : 'aus – OASIS_HEALTH_TOKEN setzen' }}</li>
        </ul>
    </section>

    <section aria-labelledby="bots-titel" class="flex flex-col gap-3 rounded-[24px] bg-paper p-5 sm:p-6">
        <h2 id="bots-titel" class="m-0 text-[21px] font-extrabold text-night">Abgewehrte Bot-Versuche heute</h2>
        @forelse ($botRefusals as $reason => $count)
            <p wire:key="bot-{{ $reason }}" class="m-0 flex justify-between gap-4 border-t border-paper-line pt-2 text-[15px]">
                <span>{{ \App\Support\Security\BotProtection::REASONS[$reason] }}</span><span class="font-bold text-night">{{ $count }}</span>
            </p>
        @empty
            <p class="m-0 text-ink-2">Heute keine.</p>
        @endforelse
        <p class="m-0 text-sm text-muted">Nur Zähler, ohne Adressen oder Konten (docs/oasis-bot-protection.md).</p>
    </section>

    <section aria-labelledby="laeufe-titel" class="flex flex-col gap-3 rounded-[24px] bg-paper p-5 sm:p-6">
        <h2 id="laeufe-titel" class="m-0 text-[21px] font-extrabold text-night">Letzte Läufe</h2>
        @forelse ($heartbeats as $beat)
            <p wire:key="lauf-{{ $beat->key }}" class="m-0 flex flex-wrap justify-between gap-x-4 border-t border-paper-line pt-2 text-[15px]">
                <span class="font-bold text-night">{{ $beat->key }}</span>
                <span class="text-ink-2">erfolgreich {{ $berlin($beat->last_success_at) }} · fehlgeschlagen {{ $berlin($beat->last_failure_at) }}</span>
            </p>
        @empty
            <p class="m-0 text-ink-2">Noch keine Läufe aufgezeichnet.</p>
        @endforelse
    </section>
</div>
