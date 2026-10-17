{{-- Verborgene Wörter, component App\Livewire\Privacy\HiddenWords. Variables: $words (list<string>), $max (int). --}}
<div>
<x-network.columns label="So wirken verborgene Wörter">
    <div class="flex flex-col gap-3">
        <a href="{{ route('app.privacy') }}" wire:navigate class="o-link inline-flex min-h-11 items-center self-start text-[15px] text-night">← Privatsphäre</a>
        <x-network.page-header eyebrow="Privatsphäre" title="Verborgene Wörter">
            Tropfen, Antworten und Hinweise mit diesen Wörtern bekommst du nicht mehr zu sehen. Groß- und Kleinschreibung spielt keine Rolle.
        </x-network.page-header>
        <p role="status" aria-live="polite" class="m-0 font-bold text-spring-text empty:sr-only">{{ $status }}</p>
    </div>

    <section aria-labelledby="woerter-titel" class="flex flex-col gap-5 rounded-[24px] bg-paper p-5 sm:p-6">
        <h2 id="woerter-titel" class="m-0 text-xl font-extrabold text-night">Deine Wörter <span class="font-bold text-muted">({{ count($words) }} von {{ $max }})</span></h2>

        <form wire:submit="add" class="flex flex-col gap-2">
            <label for="verborgenes-wort" class="font-bold text-night">Wort oder Ausdruck hinzufügen</label>
            <div class="flex flex-col gap-2 min-[400px]:flex-row">
                <input id="verborgenes-wort" type="text" wire:model="word" maxlength="40" autocomplete="off"
                       aria-describedby="verborgenes-wort-hilfe @error('word') verborgenes-wort-fehler @enderror"
                       @error('word') aria-invalid="true" @enderror
                       class="o-input min-w-0 flex-1">
                <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark">Hinzufügen</button>
            </div>
            <p id="verborgenes-wort-hilfe" class="m-0 text-sm text-muted">2 bis 40 Zeichen. Auch Teile von Wörtern werden erkannt: „spoiler“ verbirgt auch „Spoileralarm“.</p>
            @error('word') <p id="verborgenes-wort-fehler" role="alert" class="o-error m-0">{{ $message }}</p> @enderror
        </form>

        @if ($words === [])
            <p class="m-0 border-t border-paper-line pt-4 text-[15px] text-muted">Du verbirgst noch keine Wörter.</p>
        @else
            <ul class="m-0 flex list-none flex-wrap gap-2 border-t border-paper-line p-0 pt-4">
                @foreach ($words as $word)
                    <li wire:key="wort-{{ md5($word) }}" class="flex max-w-full items-center gap-1 rounded-full border border-dune-line bg-field py-1 pr-1 pl-4">
                        <span class="min-w-0 break-words">{{ $word }}</span>
                        <button type="button" wire:click="remove('{{ md5($word) }}')" wire:loading.attr="disabled"
                                class="inline-flex size-11 flex-none items-center justify-center rounded-full text-muted hover:bg-sand hover:text-night"
                                aria-label="„{{ $word }}“ nicht mehr verbergen">
                            <x-oasis.icon name="close" :size="18" />
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <x-slot:aside>
        <x-network.side-card title="Was passiert genau?" tone="dune">
            <ul class="m-0 flex list-disc flex-col gap-2 pl-5 text-[15px] text-ink-2">
                <li>In deiner Quelle, in Oasen, Suche und Antworten fehlen Tropfen mit diesen Wörtern – deine eigenen bleiben.</li>
                <li>Antworten mit diesen Wörtern unter deinen Tropfen sieht nur noch, wer sie geschrieben hat.</li>
                <li>Du bekommst keine Hinweise zu Tropfen mit diesen Wörtern.</li>
                <li>Niemand erfährt, welche Wörter du verbirgst.</li>
            </ul>
        </x-network.side-card>
    </x-slot:aside>
</x-network.columns>
</div>
