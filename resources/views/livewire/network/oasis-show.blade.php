{{--
    One oasis (route network.oases.show, /oasen/{slug}). Public oases are readable by guests.
    Side column: house rules (always open), hosts, and for hosts the links to edit and statistics.

    Variables:
      $oasis     Oasis with members_count
      $hosts     Collection<User>
      $posts     CursorPaginator<Post>  PostFeed::inOasis
      $isMember  bool
      $isHost    bool
--}}
<div @class(['flex flex-col gap-6', 'o-container py-8' => ! auth()->check()])>
@guest <p class="rounded-2xl bg-dune p-4"><b>Du liest als Gast.</b> Zum Folgen, Antworten und Schreiben brauchst du ein Konto.</p> @endguest
<p role="status" aria-live="polite" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5 text-night empty:sr-only">{{ $postStatus }}</p>
<p wire:loading class="text-muted">Wird geladen …</p>
    <x-network.columns label="Über die Oase">
        <header class="overflow-hidden rounded-[24px] bg-paper">
            <div aria-hidden="true" class="relative h-32 overflow-hidden bg-{{ $oasis->color }} {{ $oasis->color === 'dune' ? 'ring-1 ring-inset ring-dune-line' : '' }}">
                <span class="absolute -right-10 -bottom-16 size-48 rounded-full bg-paper/25"></span>
                <span class="absolute right-28 -top-10 size-24 rounded-full bg-paper/20"></span>
            </div>
            <div class="flex flex-col gap-4 p-6">
                <div class="flex flex-col gap-1">
                    <p class="o-eyebrow m-0">Oase</p>
                    <h1 class="o-h2 m-0 break-words text-night">{{ $oasis->name }}</h1>
                </div>
                <p class="m-0 text-[17px]">{{ $oasis->description }}</p>
                <p class="m-0 inline-flex items-center gap-1.5 text-ink-2"><x-oasis.icon name="users" :size="18" /> {{ $oasis->members_count }} {{ $oasis->members_count === 1 ? 'Mitglied' : 'Mitglieder' }}</p>

                @auth
                    <div class="flex flex-wrap gap-3">
                        @if ($isMember)
                            <form wire:submit="unfollow">

                                <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark">Gefolgt · nicht mehr folgen</button>
                            </form>
                        @elseif (auth()->user()->can('participate'))
                            <form wire:submit="follow">
                                <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-primary">Folgen</button>
                            </form>
                        @endif
                        @unless ($isHost)
                            <a wire:navigate href="{{ route('oasis.melden', ['url' => route('network.oases.show', $oasis)]) }}" class="o-link self-center text-[15px]">Melden</a>
                        @endunless
                    </div>
                    @error('follow') <p role="alert" class="m-0 font-bold text-sun-text">{{ $message }}</p> @enderror
                @endauth
            </div>
        </header>

        {{-- Below xl the side column follows the whole feed; the house rules belong before it. --}}
        <details class="rounded-[20px] border border-dune-line bg-paper xl:hidden" wire:ignore.self>
            <summary class="flex min-h-11 cursor-pointer items-center px-5 py-2 font-bold text-night">Hausregeln dieser Oase</summary>
            <div class="flex flex-col gap-3 px-5 pb-5">
                @include('livewire.network.partials.house-rules')
            </div>
        </details>

        @auth
            @can('post', $oasis)
                <x-network.compose :oasis="$oasis" />
            @endcan
        @endauth

        <section aria-labelledby="oase-tropfen" class="flex flex-col gap-4">
            <h2 id="oase-tropfen" class="m-0 text-[21px] font-extrabold text-night">Tropfen</h2>
            <x-network.feed :posts="$posts" empty="In dieser Oase gibt es noch keine Tropfen." />
        </section>

        <x-slot:aside>
            <x-network.side-card title="Hausregeln" tone="dune">
                @include('livewire.network.partials.house-rules')
            </x-network.side-card>

            <x-network.side-card title="Gastgeber:innen">
                @foreach ($hosts as $host)
                    <a wire:key="oasis-show-1-{{ $host->id }}" wire:navigate href="{{ route('oasis.profil', $host->username) }}" class="flex items-center gap-3 text-ink no-underline">
                        <x-network.avatar :user="$host" :size="40" />
                        <span class="flex flex-col leading-tight">
                            <span class="font-bold text-night">{{ $host->display_name }}</span>
                            <span class="text-sm text-muted">{{ '@'.$host->username }}</span>
                        </span>
                    </a>
                @endforeach
            </x-network.side-card>

            @if ($isHost)
                <x-network.side-card title="Du bist Gastgeber:in" tone="night">
                    <p class="m-0 text-[15px] text-on-night-2">Bearbeite Texte und Rollen, oder sieh nach, wie es der Oase geht.</p>
                    <div class="flex flex-wrap gap-2">
                        <a wire:navigate href="{{ route('network.oases.edit', $oasis) }}" class="o-btn o-btn-md self-start bg-on-night text-night">Bearbeiten</a>
                        <a wire:navigate href="{{ route('network.oases.stats', $oasis) }}" class="o-btn o-btn-md self-start border-2 border-on-night text-on-night">Statistik</a>
                    </div>
                </x-network.side-card>
            @endif
        </x-slot:aside>
    </x-network.columns>
</div>
