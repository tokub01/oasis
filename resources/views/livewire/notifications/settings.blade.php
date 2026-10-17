{{-- Hint settings, component App\Livewire\Notifications\Settings. Variables: $kindCases (NoticeKind[]). --}}
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
                Eine tägliche Zusammenfassung um 18 Uhr
            </label>
            <p class="m-0 text-sm text-muted">Nur wenn es neue ungelesene Hinweise gibt. Die E-Mail enthält eine Summe und einen Link, keine Tropfen-Texte.</p>
        </fieldset>
        <button class="o-btn o-btn-md o-btn-dark self-start" type="submit" wire:loading.attr="disabled">Einstellungen speichern</button>
    </form>
    <x-slot:aside><x-network.side-card title="In deinem Tempo" tone="dune">
        <p class="m-0 text-ink-2">Die E-Mail-Zusammenfassung ist standardmäßig aus. Gelesene Hinweise bleiben bis zu 90 Tage in deiner Liste.</p>
        <a href="{{ route('notifications.index') }}" wire:navigate class="o-link min-h-11 py-2 text-night">Zurück zu deinen Hinweisen</a>
    </x-network.side-card></x-slot:aside>
</x-network.columns>
</div>
