{{--
    One Tropfen with its replies (route network.posts.show, /tropfen/{id}). Readable by guests if public.
    Side column: who wrote it and – if any – the oasis it lives in.

    Variables:
      $post     Post  loaded with card data
      $replies  Collection<Post>  oldest first, already filtered by visibility
--}}
@extends('app.network.frame', [
    'title' => 'Tropfen von '.$post->author->display_name,
    'active' => 'quelle',
    'description' => \Illuminate\Support\Str::limit($post->body, 150),
    'wide' => true,
])

@php($author = $post->author)

@section('network-content')
    <x-network.columns>
        <h1 class="sr-only">Tropfen von {{ $author->display_name }}</h1>
        <x-network.post-card :post="$post" :link="false" />

        <section id="antworten" aria-labelledby="antworten-titel" class="flex flex-col gap-4">
            <h2 id="antworten-titel" class="m-0 text-[21px] font-extrabold text-night">Antworten</h2>

            @can('interact', $post)
                <x-network.compose label="Deine Antwort" submit="Antworten" :reply-to="$post" :images="false" />
            @endcan

            @forelse ($replies as $reply)
                <x-network.post-card :post="$reply" />
            @empty
                <p class="m-0 rounded-[20px] border-2 border-dashed border-dune-line p-6 text-center text-ink-2">Noch keine Antworten.</p>
            @endforelse
        </section>

        <x-slot:aside>
            <x-network.side-card title="Geschrieben von">
                <a href="{{ route('oasis.profil', $author->username) }}" class="flex items-center gap-3 text-ink no-underline">
                    <x-network.avatar :user="$author" :size="52" />
                    <span class="flex min-w-0 flex-col leading-tight">
                        <span class="font-extrabold text-night">{{ $author->display_name }}</span>
                        <span class="text-sm text-muted">{{ '@'.$author->username }}</span>
                    </span>
                </a>
                @if ($author->profileOrDefault()->bio)
                    <p class="m-0 text-[15px] text-ink-2">{{ $author->profileOrDefault()->bio }}</p>
                @endif
                <a href="{{ route('oasis.profil', $author->username) }}" class="o-link self-start text-[15px]">Zum Profil</a>
            </x-network.side-card>

            @if ($post->oasis)
                <x-network.side-card title="In der Oase">
                    <a href="{{ route('network.oases.show', $post->oasis) }}" class="flex items-center gap-3 text-ink no-underline">
                        <span aria-hidden="true" class="size-10 flex-none rounded-full bg-{{ $post->oasis->color }}"></span>
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
@endsection
