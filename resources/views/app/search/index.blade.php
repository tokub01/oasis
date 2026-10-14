{{--
    Search for members (route search.index, /app/suche?q=…). People and oases alphabetically (exact handle first),
    Tropfen newest first – nothing ranked by reach. A term like "#garten" redirects to the topic list instead.

    Variables:
      $term       string  trimmed search term
      $searching  bool    term long enough (SearchController::MIN_LENGTH)
      $people     Collection<User>   visible, not blocked
      $oases      Collection<Oasis>  visible, with members_count
      $posts      CursorPaginator<Post>|null  PostFeed::matching
--}}
@extends('layouts.app', ['title' => $searching ? 'Suche: '.$term : 'Suche', 'active' => 'entdecken', 'wide' => true])

@section('content')
    <x-network.columns label="Menschen und Oasen">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <p class="o-eyebrow m-0">Suche</p>
                <h1 class="o-h2 m-0 text-night">{{ $searching ? 'Ergebnisse für „'.$term.'“' : 'Wonach suchst du?' }}</h1>
            </div>
            <form method="GET" action="{{ route('search.index') }}" role="search" class="flex flex-wrap gap-3">
                <label for="search-q" class="sr-only">Suchbegriff</label>
                <input id="search-q" type="search" name="q" value="{{ $term }}" maxlength="100" class="o-input min-w-0 flex-1"
                       placeholder="Menschen, Oasen, Tropfen oder #Thema" aria-describedby="search-hint">
                <button type="submit" class="o-btn o-btn-md o-btn-dark">Suchen</button>
            </form>
            <p id="search-hint" class="m-0 text-sm text-muted">Mindestens {{ \App\Http\Controllers\Search\SearchController::MIN_LENGTH }} Zeichen. Mit # davor findest du alle Tropfen zu einem Thema.</p>
        </div>

        @if ($searching)
            <section aria-labelledby="search-posts" class="flex flex-col gap-4">
                <h2 id="search-posts" class="m-0 text-[21px] font-extrabold text-night">Tropfen</h2>
                <x-network.feed :posts="$posts" empty="Keine Tropfen mit „{{ $term }}“." />
            </section>
        @else
            <div class="flex flex-col gap-2 rounded-[20px] border-2 border-dashed border-dune-line p-6 text-ink-2">
                <p class="m-0 font-bold text-night">So sucht Oasis</p>
                <p class="m-0">Neueste Tropfen zuerst. Keine Ergebnisse, die nach Klicks sortiert sind, und keine, die jemand bezahlt hat.</p>
            </div>
        @endif

        <x-slot:aside>
            <x-network.side-card title="Menschen">
                @forelse ($people as $person)
                    <a href="{{ route('oasis.profil', $person->username) }}" class="flex items-center gap-3 rounded-2xl px-1 py-1 text-ink no-underline hover:bg-sand">
                        <x-network.avatar :user="$person" :size="40" />
                        <span class="flex min-w-0 flex-col leading-tight">
                            <span class="truncate font-bold text-night">{{ $person->display_name }}</span>
                            <span class="text-sm text-muted">{{ '@'.$person->username }}</span>
                        </span>
                    </a>
                @empty
                    <p class="m-0 text-[15px] text-ink-2">{{ $searching ? 'Niemand gefunden.' : 'Such nach Namen oder @Benutzernamen.' }}</p>
                @endforelse
            </x-network.side-card>

            <x-network.side-card title="Oasen">
                @forelse ($oases as $oasis)
                    <a href="{{ route('network.oases.show', $oasis) }}" class="flex items-center gap-3 rounded-2xl px-1 py-1 text-ink no-underline hover:bg-sand">
                        <span aria-hidden="true" class="size-9 flex-none rounded-full bg-{{ $oasis->color }}"></span>
                        <span class="flex min-w-0 flex-col leading-tight">
                            <span class="truncate font-bold text-night">{{ $oasis->name }}</span>
                            <span class="text-sm text-muted">{{ $oasis->members_count }} {{ $oasis->members_count === 1 ? 'Mitglied' : 'Mitglieder' }}</span>
                        </span>
                    </a>
                @empty
                    <p class="m-0 text-[15px] text-ink-2">{{ $searching ? 'Keine Oase gefunden.' : 'Such nach Name oder Thema einer Oase.' }}</p>
                @endforelse
            </x-network.side-card>
        </x-slot:aside>
    </x-network.columns>
@endsection
