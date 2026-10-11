{{--
    A chronological list of Tropfen with the honest end ("Du bist auf dem neuesten Stand").

    Props:
      posts   CursorPaginator<Post> from App\Queries\Network\PostFeed
      empty   string  text when there is nothing at all
--}}
@props(['posts', 'empty' => 'Hier ist noch nichts.'])

<div {{ $attributes->class('flex flex-col gap-4') }}>
    @forelse ($posts as $post)
        <x-network.post-card :post="$post" />
    @empty
        <p class="m-0 rounded-[20px] border-2 border-dashed border-dune-line p-6 text-center text-ink-2">{{ $empty }}</p>
    @endforelse

    @if ($posts->hasMorePages())
        <a href="{{ $posts->nextPageUrl() }}" class="o-btn o-btn-md o-btn-outline self-center">Ältere Tropfen</a>
    @elseif ($posts->isNotEmpty())
        <div class="flex flex-col gap-1 rounded-[20px] border-2 border-dashed border-spring p-6 text-center">
            <span class="font-bold text-spring-text">Du bist auf dem neuesten Stand.</span>
            <span class="text-ink-2">Zeit zum Durchatmen.</span>
        </div>
    @endif
</div>
