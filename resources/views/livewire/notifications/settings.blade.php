{{-- Hint settings, component App\Livewire\Notifications\Settings. Variables: $kindCases (NoticeKind[]), $pausedUntil (Carbon|null), $pauseDays (list<int>). --}}
<div>
<x-network.columns label="Deine Wahl">
    <x-network.page-header eyebrow="Hinweise" title="Hinweise einstellen">Du entscheidest, was in deiner Hinweis-Liste landet und ob du eine E-Mail bekommst.</x-network.page-header>
    <p role="status" class="m-0 font-bold text-spring-text empty:sr-only">{{ $status }}</p>
    @if ($errors->any()) <p role="alert" class="o-error">{{ $errors->first() }}</p> @endif
    <form wire:submit="save" class="flex flex-col gap-6 rounded-[24px] bg-paper p-6">
        <fieldset class="flex flex-col gap-2 border-0 p-0">
            <legend class="mb-3 text-xl font-extrabold text-night">In deiner Hinweis-Liste</legend>
            @foreach ($kindCases as $kind)
                <label wire:key="kind-{{ $kind->value }}" class="flex min-h-11 items-center gap-3" for="kind-{{ $kind->value }}">
                    <input id="kind-{{ $kind->value }}" type="checkbox" wire:model="kinds.{{ $kind->value }}">
                    {{ $kind->label() }}
                </label>
            @endforeach
        </fieldset>
        <fieldset class="border-0 p-0">
            <legend class="text-xl font-extrabold text-night">E-Mail</legend>
            <label for="digest" class="flex min-h-11 items-center gap-3">
                <input id="digest" type="checkbox" wire:model="digest">
                Eine tägliche Zusammenfassung am Abend
            </label>
            <p class="m-0 text-sm text-muted">Ab 18 Uhr, außerhalb deiner Ruhezeit. Nur wenn es neue ungelesene Hinweise gibt. Die E-Mail enthält eine Summe und einen Link, keine Tropfen-Texte.</p>
        </fieldset>
        <fieldset class="m-0 flex flex-col gap-3 border-0 p-0">
            <legend class="mb-1 text-xl font-extrabold text-night">Ruhezeiten</legend>
            <label for="quiet" class="flex min-h-11 items-center gap-3">
                <input id="quiet" type="checkbox" wire:model.live="quietEnabled">
                Jeden Tag eine Ruhezeit einhalten
            </label>
            @if ($quietEnabled)
                <div class="flex flex-wrap gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="quiet-from" class="text-[15px] font-bold">Von</label>
                        <input id="quiet-from" type="time" wire:model="quietFrom" class="o-input w-36"
                               @error('quietFrom') aria-describedby="quiet-from-fehler" aria-invalid="true" @enderror>
                        @error('quietFrom') <p id="quiet-from-fehler" class="o-error m-0">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="quiet-until" class="text-[15px] font-bold">Bis</label>
                        <input id="quiet-until" type="time" wire:model="quietUntil" class="o-input w-36"
                               @error('quietUntil') aria-describedby="quiet-until-fehler" aria-invalid="true" @enderror>
                        @error('quietUntil') <p id="quiet-until-fehler" class="o-error m-0">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif
            <p class="m-0 text-sm text-muted">In dieser Zeit (deutsche Zeit) kommen keine E-Mails und keine Push-Hinweise. Deine Hinweise sammeln sich in der App und warten auf dich. Wichtige Mitteilungen zu Konto, Sicherheit und Moderation kommen trotzdem.</p>
        </fieldset>
        <button class="o-btn o-btn-md o-btn-dark self-start" type="submit" wire:loading.attr="disabled">Einstellungen speichern</button>
    </form>
    <section aria-labelledby="pause-titel" class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
        <h2 id="pause-titel" class="m-0 text-xl font-extrabold text-night">Pause</h2>
        @if ($pausedUntil)
            <p class="m-0">Pause bis <strong>{{ $pausedUntil->timezone('Europe/Berlin')->locale('de')->isoFormat('dddd, D. MMMM, HH:mm') }} Uhr</strong>. Bis dahin keine E-Mails, keine Push-Hinweise und kein Punkt für neue Hinweise.</p>
            <button type="button" wire:click="resume" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-outline self-start">Pause beenden</button>
        @else
            <p class="m-0 text-ink-2">Mal ganz abschalten: keine E-Mails, keine Push-Hinweise und kein Punkt für neue Hinweise. Danach geht alles von selbst weiter.</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($pauseDays as $days)
                    <button type="button" wire:key="pause-{{ $days }}" wire:click="pause({{ $days }})" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline">{{ $days === 1 ? '1 Tag' : ($days === 7 ? '1 Woche' : $days.' Tage') }}</button>
                @endforeach
            </div>
        @endif
    </section>
    <x-slot:aside><x-network.side-card title="In deinem Tempo" tone="dune">
        <p class="m-0 text-ink-2">Die E-Mail-Zusammenfassung ist standardmäßig aus. Gelesene Hinweise bleiben bis zu 90 Tage in deiner Liste.</p>
        <a href="{{ route('notifications.index') }}" wire:navigate class="o-link min-h-11 py-2 text-night">Zurück zu deinen Hinweisen</a>
    </x-network.side-card></x-slot:aside>
</x-network.columns>
</div>
