{{--
    Guided Privatsphäre-Check, component App\Livewire\Privacy\Checkup. Variables: $section ([title, keys] of the current
    step, null on the summary), $total (number of steps before the summary), $summary (key => [title, value]).
--}}
<div>
<x-network.columns label="Privatsphäre-Check">
    <header class="flex flex-col gap-3">
        <p class="o-eyebrow m-0">Privatsphäre-Check</p>
        <h1 id="check-titel" tabindex="-1" class="o-h2 m-0 text-night outline-none">{{ $section ? $section[0] : 'Alles im Blick' }}</h1>
        <p class="m-0 text-ink-2">
            {{ $section ? 'Schritt '.$step.' von '.$total.' – jede Auswahl gilt sofort.' : 'So sind deine Einstellungen jetzt. Ändern kannst du sie jederzeit.' }}
        </p>
        <div aria-hidden="true" class="flex gap-2">
            @for ($i = 1; $i <= $total; $i++)
                <span @class(['h-1.5 flex-1 rounded-full', 'bg-night' => $i <= $step, 'bg-dune' => $i > $step])></span>
            @endfor
        </div>
        <p role="status" aria-live="polite" class="m-0 font-bold text-spring-text empty:sr-only">{{ $status }}</p>
    </header>

    @if ($section)
        <section wire:key="step-{{ $step }}" class="flex flex-col gap-5 rounded-[24px] bg-paper p-6">
            @foreach ($section[1] as $key)
                @include('livewire.privacy.partials.setting', ['key' => $key])
            @endforeach
        </section>
    @else
        <section wire:key="step-summary" aria-label="Zusammenfassung" class="flex flex-col gap-5 rounded-[24px] bg-paper p-6">
            <dl class="m-0 flex flex-col divide-y divide-paper-line">
                @foreach ($summary as $key => $row)
                    <div wire:key="summary-{{ $key }}" class="flex flex-wrap justify-between gap-x-6 gap-y-1 py-3">
                        <dt class="text-ink-2">{{ $row['title'] }}</dt>
                        <dd class="m-0 font-bold text-night">{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
            <ul class="m-0 flex list-none flex-wrap gap-x-6 gap-y-1 p-0">
                <li><a href="{{ route('safety.index') }}" wire:navigate class="o-link inline-flex min-h-11 items-center text-night">Blockiert &amp; stumm ansehen</a></li>
                <li><a href="{{ route('account.export') }}" class="o-link inline-flex min-h-11 items-center text-night">Deine Daten herunterladen</a></li>
                <li><a href="{{ route('app.settings') }}#loeschen-titel" class="o-link inline-flex min-h-11 items-center text-night">Konto löschen</a></li>
            </ul>
        </section>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        @if ($step > 1)
            <button type="button" wire:click="back" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-outline">Zurück</button>
        @endif
        @if ($section)
            <button type="button" wire:click="next" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark">{{ $step === $total ? 'Zusammenfassung' : 'Weiter' }}</button>
        @else
            <a href="{{ route('app.privacy') }}" wire:navigate class="o-btn o-btn-md o-btn-dark">Fertig</a>
        @endif
    </div>

    <x-slot:aside>
        <x-network.side-card title="Warum dieser Check?" tone="dune">
            <p class="m-0 text-[15px] text-ink-2">Oasis startet mit datenschutzfreundlichen Voreinstellungen. Hier siehst du in Ruhe, was sie bedeuten, und passt sie an dich an.</p>
        </x-network.side-card>
    </x-slot:aside>
</x-network.columns>
</div>
