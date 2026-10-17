{{--
    Private bookmarks page (route safety.bookmarks, /app/gemerkt, App\Livewire\Safety\Bookmarks).
    Shows Tropfen saved privately by the member, newest bookmarks first.

    Variables:
      $posts  CursorPaginator<Post>  BookmarkList::paginate
--}}
<div class="flex flex-col gap-6">
    @if ($postStatus !== '')
        <p role="status" aria-live="polite" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5 text-night">{{ $postStatus }}</p>
    @else
        <p role="status" aria-live="polite" class="sr-only"></p>
    @endif

    <x-network.columns label="Merkliste">
        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <p class="o-eyebrow m-0">Merkliste</p>
                <h1 class="o-h2 m-0 text-night">Gemerkt</h1>
                <p class="m-0 text-muted">Deine private Liste gespeicherter Tropfen. Niemand sieht, wer was gemerkt hat – auch Autor:innen nicht.</p>
            </div>

            <x-network.feed :posts="$posts" empty="Du hast dir noch keine Tropfen gemerkt. In jeder Tropfen-Karte findest du den Knopf „Merken“." />
        </div>

        <x-slot:aside>
            <x-network.side-card title="Privat und leise" tone="dune">
                <div class="flex flex-col gap-3 text-[15px] text-ink-2 leading-relaxed">
                    <p class="m-0">Lesezeichen sind ausschließlich für dich sichtbar. Es gibt keine öffentlichen Listen oder Zähler für gemerkte Tropfen.</p>
                    <p class="m-0">Tropfen, die gelöscht oder aus einer Oase entfernt wurden, oder deren Autor:innen blockiert sind, verschwinden automatisch aus deiner Liste.</p>
                </div>
            </x-network.side-card>

            <x-network.side-card title="Sicherheit">
                <p class="m-0 text-[15px] text-ink-2">Verwalte blockierte und stummgeschaltete Konten.</p>
                <a wire:navigate href="{{ route('safety.index') }}" class="o-btn o-btn-md o-btn-outline self-start">Sicherheitseinstellungen</a>
            </x-network.side-card>
        </x-slot:aside>
    </x-network.columns>
</div>
