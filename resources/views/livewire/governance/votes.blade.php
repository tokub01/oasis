{{--
    "Abstimmungen" for members (route app.votes, App\Livewire\Governance\Votes):
    take part in running votes, see results of ended ones.
    Variables: $open, $ended (Collection<Vote> with options), $votedIds (list<int>), $canVote (bool)
--}}
<div class="flex flex-col gap-6">
    @if ($status !== '')
        <p role="status" aria-live="polite" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5 text-night">{{ $status }}</p>
    @else
        <p role="status" aria-live="polite" class="sr-only"></p>
    @endif

    <x-network.columns label="Hinweise">
        <x-network.page-header eyebrow="Mitbestimmen" title="Abstimmungen">
            Jedes Mitglied hat eine Stimme, egal welche Stufe. Geheim: Wir speichern nur, dass du abgestimmt hast, nicht wie. Ergebnisse zeigen wir nach dem Ende.
        </x-network.page-header>

        @unless ($canVote)
            <p class="m-0 rounded-2xl bg-dune px-[18px] py-3.5">Abstimmen können Mitglieder mit aktiver Mitgliedschaft. Während des Tests kannst du mitlesen.</p>
        @endunless

        <section aria-labelledby="votes-open" class="flex flex-col gap-4">
            <h2 id="votes-open" class="m-0 text-[21px] font-extrabold text-night">Laufend</h2>
            @forelse ($open as $vote)
                <article wire:key="open-vote-{{ $vote->id }}" class="flex flex-col gap-4 rounded-[24px] bg-paper p-6">
                    <div class="flex flex-col gap-1">
                        <h3 class="m-0 text-xl font-extrabold text-night">{{ $vote->title }}</h3>
                        <p class="m-0 text-sm text-muted">endet am {{ $vote->ends_at->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr</p>
                    </div>
                    <p class="m-0 whitespace-pre-line">{{ $vote->description }}</p>
                    @if (in_array($vote->id, $votedIds, true))
                        <p class="m-0 font-bold text-spring-text">Du hast abgestimmt. Danke!</p>
                    @elseif ($canVote)
                        <form wire:submit="cast({{ $vote->id }})" class="flex flex-col gap-3">
                            <fieldset class="m-0 flex flex-col gap-2.5 border-none p-0">
                                <legend class="sr-only">Antworten zu: {{ $vote->title }}</legend>
                                @foreach ($vote->options as $option)
                                    <label wire:key="vote-option-{{ $option->id }}" class="flex min-h-12 cursor-pointer items-center gap-3 rounded-[14px] border border-dune-line px-4">
                                        <input type="radio" name="option-{{ $vote->id }}" wire:model="options.{{ $vote->id }}" value="{{ $option->id }}" required>
                                        <span>{{ $option->label }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                            @error('option') <p role="alert" class="m-0 font-bold text-sun-text">{{ $message }}</p> @enderror
                            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Stimme abgeben</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="m-0 text-ink-2">Gerade läuft keine Abstimmung.</p>
            @endforelse
        </section>

        <section aria-labelledby="votes-ended" class="flex flex-col gap-4">
            <h2 id="votes-ended" class="m-0 text-[21px] font-extrabold text-night">Ergebnisse</h2>
            @forelse ($ended as $vote)
                @php($total = $vote->options->sum('votes_count'))
                <article wire:key="ended-vote-{{ $vote->id }}" class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
                    <h3 class="m-0 text-lg font-extrabold text-night">{{ $vote->title }}</h3>
                    <p class="m-0 text-sm text-muted">{{ $total }} Stimmen · beendet am {{ $vote->ends_at->locale('de')->isoFormat('D. MMMM YYYY') }}</p>
                    <ul class="m-0 flex list-none flex-col gap-2 p-0">
                        @foreach ($vote->options as $option)
                            @php($share = $total > 0 ? (int) round($option->votes_count / $total * 100) : 0)
                            <li wire:key="ended-option-{{ $option->id }}" class="flex flex-col gap-1">
                                <span class="flex justify-between gap-3"><span @class(['font-bold' => in_array($option->label, $vote->winners(), true)])>{{ $option->label }}</span><span>{{ $share }} %</span></span>
                                <span aria-hidden="true" class="block h-2 overflow-hidden rounded-full bg-dune"><span class="block h-2 bg-spring" style="width: {{ $share }}%"></span></span>
                            </li>
                        @endforeach
                    </ul>
                </article>
            @empty
                <p class="m-0 text-ink-2">Noch keine abgeschlossenen Abstimmungen.</p>
            @endforelse
        </section>

        <x-slot:aside>
            <x-network.side-card title="So stimmen wir ab" tone="night">
                <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[15px] text-on-night-2">
                    <li>Eine Stimme pro Mitglied – egal ob Solidar, Oase oder Mäzen.</li>
                    <li>Geheim: Gespeichert wird nur, dass du abgestimmt hast.</li>
                    <li>Ergebnisse erst nach dem Ende, damit niemand dem Trend folgt.</li>
                </ul>
            </x-network.side-card>
        </x-slot:aside>
    </x-network.columns>
</div>
