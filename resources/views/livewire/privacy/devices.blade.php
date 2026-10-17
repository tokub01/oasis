{{--
    Geräte und Anmeldungen, component App\Livewire\Privacy\Devices. Variables: $database (bool, sessions stored in the
    database), $current and $others (device, ip, active[, key]), $known (Collection<KnownDevice>), $alerts (bool).
--}}
@php($time = fn ($at) => $at->timezone('Europe/Berlin')->locale('de')->isoFormat('D. MMMM YYYY, HH:mm').' Uhr')
<div>
<x-network.columns label="Wenn dir etwas fremd vorkommt">
    <div class="flex flex-col gap-3">
        <a href="{{ route('app.privacy') }}" wire:navigate class="o-link inline-flex min-h-11 items-center self-start text-[15px] text-night">← Privatsphäre</a>
        <x-network.page-header eyebrow="Privatsphäre" title="Geräte und Anmeldungen">
            Hier siehst du, wo du gerade angemeldet bist, und meldest fremde Geräte sofort ab.
        </x-network.page-header>
        <p role="status" aria-live="polite" class="m-0 font-bold text-spring-text empty:sr-only">{{ $status }}</p>
    </div>

    <section aria-labelledby="sitzungen-titel" class="flex flex-col gap-4 rounded-[24px] bg-paper p-5 sm:p-6">
        <h2 id="sitzungen-titel" class="m-0 text-xl font-extrabold text-night">Wo du angemeldet bist</h2>

        <div class="flex flex-col gap-1 rounded-2xl bg-spring/30 p-4">
            <p class="m-0 flex flex-wrap items-center gap-x-2 font-bold text-night"><x-oasis.icon name="monitor" :size="18" /> {{ $current['device'] }} <span class="rounded-full bg-night px-2.5 py-0.5 text-xs text-on-night">Dieses Gerät</span></p>
            <p class="m-0 text-sm text-ink-2">Netz {{ $current['ip'] }} · aktiv jetzt</p>
        </div>

        @if (! $database)
            <p class="m-0 text-[15px] text-muted">Andere Anmeldungen können auf diesem Server nicht angezeigt werden.</p>
        @else
            @forelse ($others as $session)
                <div wire:key="sitzung-{{ $session['key'] }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-paper-line pt-4">
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span class="font-bold text-night">{{ $session['device'] }}</span>
                        <span class="text-sm text-ink-2">Netz {{ $session['ip'] }} · zuletzt aktiv {{ $time($session['active']) }}</span>
                    </div>
                    <button type="button" wire:click="endSession('{{ $session['key'] }}')" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline">Abmelden</button>
                </div>
            @empty
                <p class="m-0 border-t border-paper-line pt-4 text-[15px] text-muted">Du bist auf keinem anderen Gerät angemeldet.</p>
            @endforelse

            @if ($others !== [])
                <div class="flex flex-col gap-2 border-t border-paper-line pt-4">
                    <button type="button" wire:click="endOtherSessions" wire:confirm="Auf allen anderen Geräten abmelden?" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Überall sonst abmelden</button>
                    <p class="m-0 text-sm text-muted">„Angemeldet bleiben“ wird dabei überall zurückgesetzt. Kommt dir ein Gerät fremd vor, ändere danach dein Passwort.</p>
                </div>
            @endif
        @endif
    </section>

    <section aria-labelledby="bekannt-titel" class="flex flex-col gap-4 rounded-[24px] bg-paper p-5 sm:p-6">
        <div class="flex flex-col gap-1">
            <h2 id="bekannt-titel" class="m-0 text-xl font-extrabold text-night">Bekannte Geräte</h2>
            <p class="m-0 text-[15px] text-ink-2">
                {{ $alerts ? 'Meldet sich jemand mit einem anderen Browser oder System an, bekommst du sofort eine E-Mail.' : 'Anmelde-Warnungen per E-Mail sind ausgeschaltet.' }}
                Wir speichern nur Browser und System, keine IP-Adresse. Nach einem Jahr ohne Anmeldung vergessen wir ein Gerät.
            </p>
        </div>
        @forelse ($known as $device)
            <div wire:key="geraet-{{ $device->id }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-paper-line pt-4">
                <div class="flex min-w-0 flex-col gap-0.5">
                    <span class="font-bold text-night">{{ $device->label }}</span>
                    <span class="text-sm text-ink-2">zuletzt angemeldet {{ $time($device->last_seen_at) }}</span>
                </div>
                <button type="button" wire:click="forgetDevice({{ $device->id }})" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline">Vergessen</button>
            </div>
        @empty
            <p class="m-0 border-t border-paper-line pt-4 text-[15px] text-muted">Noch keine Geräte gespeichert.</p>
        @endforelse
    </section>

    <x-slot:aside>
        <x-network.side-card title="Konto zusätzlich schützen" tone="dune">
            <p class="m-0 text-[15px] text-ink-2">Mit der Zwei-Faktor-Anmeldung braucht jede neue Anmeldung zusätzlich einen Code von deinem Handy.</p>
            <a href="{{ route('app.settings') }}#zwei-faktor-titel" class="o-link inline-flex min-h-11 items-center self-start text-night">Zwei-Faktor-Anmeldung</a>
            <a href="{{ route('app.privacy') }}#titel-sicherheit" wire:navigate class="o-link inline-flex min-h-11 items-center self-start text-night">Anmelde-Warnungen einstellen</a>
        </x-network.side-card>
    </x-slot:aside>
</x-network.columns>
</div>
