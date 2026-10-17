{{--
    "Oasen" (route network.oases.index): the member's oases and all others as a grid of cards.
    Variables: $mine, $others (Collection<Oasis> with members_count)
--}}
<div class="flex flex-col gap-6">
<p role="status" aria-live="polite" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5 text-night empty:sr-only">{{ $postStatus }}</p>
<p wire:loading class="text-muted">Wird geladen …</p>
    <div class="flex flex-col gap-10">
        <div class="flex flex-wrap items-end justify-between gap-4 rounded-[28px] bg-night p-[clamp(24px,4vw,40px)] text-on-night">
            <div class="flex max-w-[36em] flex-col gap-2">
                <p class="o-eyebrow o-eyebrow-night m-0">Oasen</p>
                <h1 class="o-h2 m-0">Räume für das, was dich interessiert</h1>
                <p class="m-0 text-on-night-2">Themenräume mit Gastgeber:innen und eigenen Hausregeln. Einer Oase folgst du wie einem Menschen – dann kannst du dort auch schreiben.</p>
            </div>
            @can('create', \App\Models\Oasis::class)
                <a wire:navigate href="{{ route('network.oases.create') }}" class="o-btn o-btn-md o-btn-primary">Oase eröffnen</a>
            @endcan
        </div>

        @foreach (['Deine Oasen' => $mine, 'Weitere Oasen' => $others] as $heading => $list)
            <section wire:key="oasis-index-1-{{ $heading }}" class="flex flex-col gap-4" aria-label="{{ $heading }}">
                <h2 class="m-0 text-[21px] font-extrabold text-night">{{ $heading }}</h2>
                @if ($list->isEmpty())
                    <p class="m-0 rounded-[20px] border-2 border-dashed border-dune-line p-6 text-center text-ink-2">{{ $heading === 'Deine Oasen' ? 'Du folgst noch keiner Oase.' : 'Noch keine weiteren Oasen.' }}</p>
                @else
                    <ul class="m-0 grid list-none gap-4 p-0 sm:grid-cols-[repeat(auto-fit,minmax(300px,1fr))]">
                        @foreach ($list as $oasis)
                            <li wire:key="oasis-index-2-{{ $oasis->id }}">
                                <a wire:navigate href="{{ route('network.oases.show', $oasis) }}" class="flex h-full flex-col overflow-hidden rounded-[24px] bg-paper text-ink no-underline hover:shadow-md">
                                    <span aria-hidden="true" class="relative block h-20 overflow-hidden bg-{{ $oasis->color }} {{ $oasis->color === 'dune' ? 'ring-1 ring-inset ring-dune-line' : '' }}">
                                        <span class="absolute -right-6 -bottom-10 size-28 rounded-full bg-paper/25"></span>
                                    </span>
                                    <span class="flex flex-1 flex-col gap-2 p-5">
                                        <span class="text-lg font-extrabold text-night">{{ $oasis->name }}</span>
                                        <span class="line-clamp-3 text-[15px] text-ink-2">{{ $oasis->description }}</span>
                                        <span class="mt-auto text-sm text-muted">{{ $oasis->members_count }} {{ $oasis->members_count === 1 ? 'Mitglied' : 'Mitglieder' }}@if ($oasis->pivot?->role === 'host') · du bist Gastgeber:in @endif</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
</div>
