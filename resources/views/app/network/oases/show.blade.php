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
@extends('app.network.frame', ['title' => $oasis->name, 'active' => 'oasen', 'description' => $oasis->description, 'wide' => true])

@section('network-content')
    <x-network.columns label="Über die Oase">
        <header class="overflow-hidden rounded-[24px] bg-paper">
            <div aria-hidden="true" class="relative h-32 overflow-hidden bg-{{ $oasis->color }}">
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
                            <form method="POST" action="{{ route('network.unfollow.oasis', $oasis) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="o-btn o-btn-md o-btn-dark">Gefolgt · nicht mehr folgen</button>
                            </form>
                        @elseif (auth()->user()->can('participate'))
                            <form method="POST" action="{{ route('network.follow.oasis', $oasis) }}">
                                @csrf
                                <button type="submit" class="o-btn o-btn-md o-btn-primary">Folgen</button>
                            </form>
                        @endif
                    </div>
                    @error('follow') <p role="alert" class="m-0 font-bold text-sun-text">{{ $message }}</p> @enderror
                @endauth
            </div>
        </header>

        @auth
            @can('post', $oasis)
                <x-network.compose label="Dein Tropfen in {{ $oasis->name }}" :oasis="$oasis" />
            @endcan
        @endauth

        <section aria-labelledby="oase-tropfen" class="flex flex-col gap-4">
            <h2 id="oase-tropfen" class="m-0 text-[21px] font-extrabold text-night">Tropfen</h2>
            <x-network.feed :posts="$posts" empty="In dieser Oase gibt es noch keine Tropfen." />
        </section>

        <x-slot:aside>
            <x-network.side-card title="Hausregeln" tone="dune">
                <p class="m-0 text-[15px] whitespace-pre-line">{{ $oasis->house_rules }}</p>
                <p class="m-0 text-sm text-muted">Zusätzlich gelten die <a href="{{ route('oasis.regeln') }}" class="text-night underline underline-offset-4">Community-Regeln</a> von Oasis.</p>
            </x-network.side-card>

            <x-network.side-card title="Gastgeber:innen">
                @foreach ($hosts as $host)
                    <a href="{{ route('oasis.profil', $host->username) }}" class="flex items-center gap-3 text-ink no-underline">
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
                        <a href="{{ route('network.oases.edit', $oasis) }}" class="o-btn o-btn-md self-start bg-on-night text-night">Bearbeiten</a>
                        <a href="{{ route('network.oases.stats', $oasis) }}" class="o-btn o-btn-md self-start border-2 border-on-night text-on-night">Statistik</a>
                    </div>
                </x-network.side-card>
            @endif
        </x-slot:aside>
    </x-network.columns>
@endsection
