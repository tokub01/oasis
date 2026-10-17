{{--
    "Deine Zahlen" (route network.stats): the member's own statistics – "leise Zahlen", visible only to them.
    Variables: $totals (MemberStats::totals), $weekly (MemberStats::weekly), $topPosts (Collection<Post> with counts)
--}}
<div class="flex flex-col gap-6">
<p role="status" aria-live="polite" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5 text-night empty:sr-only">{{ $postStatus }}</p>
<p wire:loading class="text-muted">Wird geladen …</p>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <p class="o-eyebrow m-0">Nur für dich sichtbar</p>
            <h1 class="o-h2 m-0 text-night">Deine Zahlen</h1>
            <p class="m-0 text-ink-2">Niemand sonst sieht diese Zahlen. Wer reagiert hat, zeigen wir auch dir nicht – es zählt, was gesagt wird.</p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            <x-network.stat :value="$totals['followers']" label="In deiner Karawane" />
            <x-network.stat :value="$totals['following']" label="Du folgst" />
            <x-network.stat :value="$totals['posts']" label="Tropfen" />
            <x-network.stat :value="$totals['reactions']" label="Reaktionen erhalten" />
            <x-network.stat :value="$totals['replies']" label="Antworten erhalten" />
            <x-network.stat :value="$totals['carries']" label="Mal weitergetragen" />
        </div>

        <div class="rounded-[24px] bg-paper p-6">
            <x-network.week-chart caption="Die letzten 12 Wochen" :weeks="$weekly"
                                  :series="['followers' => 'Neu in der Karawane', 'posts' => 'Tropfen', 'reactions' => 'Reaktionen']" />
        </div>

        <section aria-labelledby="top-tropfen" class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
            <h2 id="top-tropfen" class="m-0 text-[21px] font-extrabold text-night">Deine Tropfen der letzten 30 Tage mit den meisten Reaktionen</h2>
            @forelse ($topPosts as $post)
                <a wire:key="statistics-1-{{ $post->id }}" wire:navigate href="{{ route('network.posts.show', $post) }}" class="flex flex-col gap-1 rounded-2xl border border-dune-line p-4 text-ink no-underline">
                    <span class="line-clamp-2">{{ $post->body }}</span>
                    <span class="text-sm text-muted">{{ $post->reactions_count }} {{ $post->reactions_count === 1 ? 'Reaktion' : 'Reaktionen' }} · {{ $post->replies_count }} {{ $post->replies_count === 1 ? 'Antwort' : 'Antworten' }} · {{ $post->created_at->locale('de')->isoFormat('D. MMMM') }}</span>
                </a>
            @empty
                <p class="m-0 text-ink-2">In den letzten 30 Tagen hast du nichts geschrieben.</p>
            @endforelse
        </section>
    </div>
</div>
