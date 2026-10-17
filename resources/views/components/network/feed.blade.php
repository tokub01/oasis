{{--
    A chronological list of Tropfen with an honest end (default "Du bist auf dem neuesten Stand").

    Props:
      posts   CursorPaginator<Post> from App\Queries\Network\PostFeed
      empty   string  text when there is nothing at all
      end     string|null  closing line after the last Tropfen; null keeps the feed wording with "Zeit zum Durchatmen"
              (a search result is not a feed, so it passes its own line)
--}}
@props(['posts', 'empty' => 'Hier ist noch nichts.', 'end' => null])

<div {{ $attributes->class('flex flex-col gap-4') }}>
    @forelse ($posts as $post)
        <x-network.post-card :post="$post" wire:key="post-{{ $post->id }}" />
    @empty
        <p class="m-0 rounded-[20px] border-2 border-dashed border-dune-line p-6 text-center text-balance text-ink-2">{{ $empty }}</p>
    @endforelse

    @if ($posts->hasMorePages())
        <button type="button" wire:click="setPage('{{ $posts->nextCursor()->encode() }}', 'cursor')" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-outline self-center">Ältere Tropfen</button>
    @elseif ($posts->isNotEmpty())
        <div class="flex flex-col gap-1 rounded-[20px] border-2 border-dashed border-spring p-6 text-center">
            @if ($end === null)
                <span class="font-bold text-spring-text">Du bist auf dem neuesten Stand.</span>
                <span class="text-ink-2">Zeit zum Durchatmen.</span>
            @else
                <span class="font-bold text-spring-text">{{ $end }}</span>
            @endif
        </div>
    @endif
</div>
