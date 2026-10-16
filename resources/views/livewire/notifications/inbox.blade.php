{{-- Hint inbox, component App\Livewire\Notifications\Inbox. Variables: paginated $hints, visible $groups. --}}
<div>
<x-network.columns label="Über Hinweise">
    <header class="flex flex-col gap-3">
        <h1 class="o-h2 m-0 text-night">Hinweise</h1>
        <p class="m-0 text-ink-2">Was dich betrifft, in Ruhe und der Reihe nach.</p>
        <p role="status" class="m-0 text-spring-text">{{ $status }}</p>
        <button wire:click="markAllRead" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline self-start" type="button">Alle als gelesen markieren</button>
    </header>
    @forelse ($groups as $group => $entries)
        <section wire:key="group-{{ $group }}" aria-labelledby="group-{{ $loop->index }}" class="flex flex-col gap-3">
            <h2 id="group-{{ $loop->index }}" class="m-0 text-xl font-extrabold text-night">{{ $group }}</h2>
            <ul class="m-0 flex list-none flex-col gap-3 p-0">
                @foreach ($entries as $entry)
                    @php($hint = $entry['hint'])
                    <li wire:key="hint-{{ $hint->id }}" @class(['rounded-[20px] border p-5', 'bg-paper border-paper-line' => $hint->read_at, 'bg-sand border-spring-text' => ! $hint->read_at])>
                        @if (! $hint->read_at) <span class="text-sm font-bold text-spring-text">Ungelesen</span> @endif
                        <a href="{{ $entry['item']['url'] }}" wire:navigate class="block min-h-11 py-2 font-bold text-night underline underline-offset-4">{{ $entry['item']['label'] }}</a>
                        @if ($entry['item']['excerpt'] !== '') <p class="m-0 whitespace-pre-line break-words text-ink-2">{{ $entry['item']['excerpt'] }}</p> @endif
                        <time class="text-sm text-muted" datetime="{{ $hint->created_at->toIso8601String() }}">{{ $hint->created_at->copy()->timezone('Europe/Berlin')->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }}</time>
                        @if (! $hint->read_at)
                            <button wire:click="markRead('{{ $hint->id }}')" wire:loading.attr="disabled" type="button" class="o-link block min-h-11 cursor-pointer border-0 bg-transparent text-night">Als gelesen markieren</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="text-ink-2">Hier gibt es gerade keine Hinweise für dich.</p>
    @endforelse
    {{ $hints->links() }}
    <x-slot:aside>
        <x-network.side-card title="Du entscheidest" tone="dune">
            <p class="m-0 text-ink-2">Wähle, welche Hinweise du erhalten möchtest. Eine tägliche E-Mail gibt es nur, wenn du sie einschaltest.</p>
            <a href="{{ route('notifications.settings') }}" wire:navigate class="o-link min-h-11 py-2 text-night">Hinweise einstellen</a>
        </x-network.side-card>
    </x-slot:aside>
</x-network.columns>
</div>
