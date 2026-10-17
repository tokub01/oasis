{{--
    One Tropfen with its replies (route network.posts.show, /tropfen/{id}). Readable by guests if public.
    Side column: who wrote it and – if any – the oasis it lives in.

    Variables:
      $post     Post  loaded with card data
      $replies  Collection<Post>  oldest first, already filtered by visibility
--}}
@php($author = $post->author)

<div @class(['flex flex-col gap-6', 'o-container py-8' => ! auth()->check()])>
@guest <p class="rounded-2xl bg-dune p-4"><b>Du liest als Gast.</b> Zum Folgen, Antworten und Schreiben brauchst du ein Konto.</p> @endguest
<p role="status" aria-live="polite" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5 text-night empty:sr-only">{{ $postStatus }}</p>
<p wire:loading class="text-muted">Wird geladen …</p>
    <x-network.columns>
        <h1 class="sr-only">Tropfen von {{ $author->display_name }}</h1>
        <x-network.post-card :post="$post" :link="false" />

        @if ($post->edited_at && $post->revisions->isNotEmpty())
            <details id="versionen" class="rounded-[20px] border border-paper-line bg-paper px-5 py-3">
                <summary class="flex min-h-11 cursor-pointer items-center font-bold text-night">Frühere Fassungen ({{ $post->revisions->count() }})</summary>
                <ol class="m-0 flex list-none flex-col gap-3 p-0 pb-2">
                    @foreach ($post->revisions as $revision)
                        <li wire:key="fassung-{{ $revision->id }}" class="flex flex-col gap-1 border-t border-paper-line pt-3">
                            <span class="text-sm text-muted">Gültig bis {{ $revision->replaced_at->timezone('Europe/Berlin')->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr</span>
                            <p class="m-0 whitespace-pre-line break-words text-ink-2">{{ $revision->body }}</p>
                        </li>
                    @endforeach
                </ol>
            </details>
        @endif

        <section id="antworten" aria-labelledby="antworten-titel" class="flex flex-col gap-4">
            <h2 id="antworten-titel" class="m-0 text-[21px] font-extrabold text-night">Antworten</h2>

            @can('reply', $post)
                <x-network.compose :reply-to="$post" :images="false" />
            @endcan

            @forelse ($replies as $reply)
                <x-network.post-card wire:key="post-show-1-{{ $reply->id }}" :post="$reply" />
            @empty
                <p class="m-0 rounded-[20px] border-2 border-dashed border-dune-line p-6 text-center text-ink-2">Noch keine Antworten.</p>
            @endforelse
        </section>

        <x-slot:aside>
            <x-network.side-card title="Geschrieben von">
                <a wire:navigate href="{{ route('oasis.profil', $author->username) }}" class="flex items-center gap-3 text-ink no-underline">
                    <x-network.avatar :user="$author" :size="52" />
                    <span class="flex min-w-0 flex-col leading-tight">
                        <span class="font-extrabold text-night">{{ $author->display_name }}</span>
                        <span class="text-sm text-muted">{{ '@'.$author->username }}</span>
                    </span>
                </a>
                @if ($author->profileOrDefault()->isVisibleTo(auth()->user()) && $author->profileOrDefault()->bio)
                    <p class="m-0 text-[15px] text-ink-2">{{ $author->profileOrDefault()->bio }}</p>
                @endif
                <a wire:navigate href="{{ route('oasis.profil', $author->username) }}" class="o-link self-start text-[15px]">Zum Profil</a>
            </x-network.side-card>

            @if ($post->oasis)
                <x-network.side-card title="In der Oase">
                    <a wire:navigate href="{{ route('network.oases.show', $post->oasis) }}" class="flex items-center gap-3 text-ink no-underline">
                        <span aria-hidden="true" class="size-10 flex-none rounded-full bg-{{ $post->oasis->color }} {{ $post->oasis->color === 'dune' ? 'ring-1 ring-inset ring-dune-line' : '' }}"></span>
                        <span class="font-extrabold text-night">{{ $post->oasis->name }}</span>
                    </a>
                    <p class="m-0 text-[15px] text-ink-2">{{ $post->oasis->description }}</p>
                </x-network.side-card>
            @endif

            <x-network.side-card title="Leise Zahlen" tone="dune">
                <p class="m-0 text-[15px] text-ink-2">Wie viele reagiert haben, sieht nur, wer den Tropfen geschrieben hat. So zählt, was gesagt wird – nicht, wie laut.</p>
            </x-network.side-card>
        </x-slot:aside>
    </x-network.columns>
</div>
