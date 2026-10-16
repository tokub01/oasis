{{--
    Prepare member votes, component App\Livewire\Admin\Votes (route admin.votes.index). Running votes show
    participation only; results after the end. Variables: $votes (Collection<Vote> with options and participations_count)
--}}
<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-2">
        <p class="o-eyebrow m-0">Admin</p>
        <h1 class="o-h2 m-0 text-night">Abstimmungen</h1>
        <p class="m-0 max-w-[48em] text-ink-2">Jedes Mitglied mit aktiver Mitgliedschaft hat eine Stimme. Die Abstimmung ist geheim: Wir speichern nur, dass jemand abgestimmt hat, nicht wie. Ergebnisse sieht auch hier niemand vor dem Ende.</p>
    </div>

    <p role="status" @class(['m-0 rounded-2xl bg-dune px-[18px] py-3.5', 'sr-only' => ! $status])>{{ $status }}</p>

    <form wire:submit="create" class="flex flex-col gap-5 rounded-[24px] bg-paper p-6">
        <h2 class="m-0 text-[21px] font-extrabold text-night">Neue Abstimmung</h2>
        <x-oasis.field name="title" label="Frage" wire:model="title" required maxlength="160" />
        <div class="flex flex-col gap-1.5">
            <label for="vote-description" class="text-[15px] font-bold">Erklärung</label>
            <textarea id="vote-description" wire:model="description" rows="5" maxlength="5000" required class="o-input"
                      @error('description') aria-describedby="vote-description-error" aria-invalid="true" @enderror></textarea>
            @error('description') <p id="vote-description-error" class="m-0 text-sm font-bold text-sun-text">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <x-oasis.field name="starts_at" type="datetime-local" label="Beginn" wire:model="starts_at" required />
            <x-oasis.field name="ends_at" type="datetime-local" label="Ende" wire:model="ends_at" required />
        </div>
        <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
            <legend class="mb-2 text-[15px] font-bold">Antworten (mindestens zwei)</legend>
            @foreach ($options as $i => $option)
                <label wire:key="option-label-{{ $i }}" class="sr-only" for="vote-option-{{ $i }}">Antwort {{ $i + 1 }}</label>
                <input wire:key="option-{{ $i }}" id="vote-option-{{ $i }}" wire:model="options.{{ $i }}" class="o-input" maxlength="120" placeholder="Antwort {{ $i + 1 }}">
            @endforeach
            @error('options') <p role="alert" class="m-0 font-bold text-sun-text">{{ $message }}</p> @enderror
        </fieldset>
        <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Abstimmung anlegen</button>
    </form>

    <section aria-labelledby="admin-votes" class="flex flex-col gap-3">
        <h2 id="admin-votes" class="m-0 text-[21px] font-extrabold text-night">Alle Abstimmungen</h2>
        @forelse ($votes as $vote)
            <article wire:key="vote-{{ $vote->id }}" class="flex flex-col gap-2 rounded-[20px] bg-paper p-5">
                <h3 class="m-0 text-lg font-extrabold text-night">{{ $vote->title }}</h3>
                <p class="m-0 text-sm text-muted">
                    {{ $vote->starts_at->locale('de')->isoFormat('D. MMM YYYY, HH:mm') }} – {{ $vote->ends_at->locale('de')->isoFormat('D. MMM YYYY, HH:mm') }}
                    · {{ $vote->isOpen() ? 'läuft' : ($vote->hasEnded() ? 'beendet' : 'geplant') }}
                    · {{ $vote->participations_count }} Stimmen
                </p>
                @if ($vote->hasEnded())
                    <ul class="m-0 flex list-none flex-col gap-1 p-0">
                        @foreach ($vote->options as $option)
                            <li wire:key="vote-{{ $vote->id }}-option-{{ $option->id }}">{{ $option->label }}: <b>{{ $option->votes_count }}</b></li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @empty
            <p class="m-0 text-ink-2">Noch keine Abstimmungen.</p>
        @endforelse
    </section>
</div>
