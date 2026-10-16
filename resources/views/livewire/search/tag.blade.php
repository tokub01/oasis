{{--
    All Tropfen with one #Thema (route search.tags.show, /themen/{tag}), newest first. Guests see public Tropfen.
    Variables: $tag (lowercase, validated), $posts (CursorPaginator<Post>, PostFeed::tagged)
--}}
<div @class(['flex flex-col gap-6', 'o-container py-8' => ! auth()->check()])>
@guest <p class="rounded-2xl bg-dune p-4"><b>Du liest als Gast.</b> Zum Folgen, Antworten und Schreiben brauchst du ein Konto.</p> @endguest
<p role="status" aria-live="polite">{{ $postStatus }}</p>
@error('tag')<p role="alert" class="o-error">{{ $message }}</p>@enderror
<p wire:loading class="text-muted">Wird geladen …</p>
    <x-network.columns label="Über Themen">
        <div class="flex flex-col gap-2">
            <p class="o-eyebrow m-0">Thema</p>
            <h1 class="o-h2 m-0 break-words text-night">#{{ $tag }}</h1>
            <p class="m-0 text-ink-2">Alle Tropfen mit #{{ $tag }}, neueste zuerst.</p>
        </div>

        <x-network.feed :posts="$posts" empty="Noch keine Tropfen zu #{{ $tag }}." />

        <x-slot:aside>
            <x-network.side-card title="Themen auf Oasis" tone="dune">
                <p class="m-0 text-[15px] text-ink-2">Schreib #{{ $tag }} in deinen Tropfen, dann erscheint er hier. Es gibt keine „Trends“ – nur die Reihenfolge, in der geschrieben wurde.</p>
                @auth
                    <a wire:navigate href="{{ route('search.index') }}" class="o-link self-start text-[15px]">Zur Suche</a>
                @endauth
            </x-network.side-card>
        </x-slot:aside>
    </x-network.columns>
</div>
