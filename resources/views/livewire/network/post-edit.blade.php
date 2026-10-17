{{--
    Edit an own Tropfen, component App\Livewire\Network\PostEdit (route network.posts.edit). Variables: $post (Post with
    images), $until (end of the edit window). Only the text changes; earlier versions stay visible on the Tropfen.
--}}
<div>
<x-network.columns label="So funktioniert Bearbeiten">
    <div class="flex flex-col gap-3">
        <a href="{{ route('network.posts.show', $post) }}" wire:navigate class="o-link inline-flex min-h-11 items-center self-start text-[15px] text-night">← Zum Tropfen</a>
        <x-network.page-header eyebrow="Tropfen" title="Tropfen bearbeiten">
            Du kannst den Text bis {{ $until->timezone('Europe/Berlin')->format('H:i') }} Uhr ändern. Bilder und Sichtbarkeit bleiben, wie sie sind.
        </x-network.page-header>
    </div>

    <form wire:submit="save" class="flex flex-col gap-4 rounded-[24px] bg-paper p-5 sm:p-6">
        <label for="tropfen-text" class="o-label">Dein Tropfen</label>
        <textarea id="tropfen-text" wire:model="body" rows="6" maxlength="{{ \App\Models\Post::MAX_LENGTH }}" class="o-input resize-y"
                  @error('body') aria-describedby="tropfen-text-fehler" aria-invalid="true" @enderror></textarea>
        @error('body') <p id="tropfen-text-fehler" role="alert" class="o-error m-0">{{ $message }}</p> @enderror
        <div class="flex flex-wrap gap-3">
            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark">Änderung speichern</button>
            <a href="{{ route('network.posts.show', $post) }}" wire:navigate class="o-btn o-btn-md o-btn-outline">Abbrechen</a>
        </div>
    </form>

    <x-slot:aside>
        <x-network.side-card title="Offen für alle" tone="dune">
            <p class="m-0 text-[15px] text-ink-2">Wer deinen Tropfen lesen darf, sieht, dass er bearbeitet wurde, und kann frühere Fassungen aufklappen. So bleibt nachvollziehbar, worauf andere geantwortet haben.</p>
        </x-network.side-card>
    </x-slot:aside>
</x-network.columns>
</div>
