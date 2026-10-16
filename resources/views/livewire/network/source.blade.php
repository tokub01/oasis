{{--
    "Quelle": the member's home (route app.home, /app, Source::render). Chronological Tropfen of the member,
    the people and the oases they follow – no ranking, no recommendations, and an honest end. From 1280px a side
    column shows the member's oases, their quiet numbers of the week, a running vote and an invitation hint; below
    that width the same blocks follow the feed.

    Variables:
      $posts          CursorPaginator<Post>  PostFeed::source
      $myOases        Collection<Oasis>      oases the member belongs to (with members_count)
      $followsAnyone  bool                   false shows the first-steps hints
      $week           array{followers, reactions, karawane}  MemberStats::lastDays(7), only for this member
      $openVote       Vote|null              the running vote ending first
--}}
<div class="flex flex-col gap-6">
<p role="status" aria-live="polite">{{ $postStatus }}</p>
<p wire:loading class="text-muted">Wird geladen …</p>
    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start">
        <div class="flex min-w-0 flex-col gap-8">
            <div class="flex flex-col gap-2">
                <p class="o-eyebrow m-0">Quelle</p>
                <h1 class="o-h2 m-0 text-night">Hallo {{ auth()->user()->display_name }}.</h1>
                <p class="o-lead m-0 text-ink-2">Hier siehst du der Reihe nach, was Menschen und Oasen teilen, denen du folgst.</p>
            </div>

            @can('create', \App\Models\Post::class)
                <x-network.compose />
            @else
                <p class="m-0 rounded-2xl bg-dune px-[18px] py-3.5">
                    Dein Konto ist gerade nur zum Lesen. Du kannst alles lesen, herunterladen und löschen.
                    <a wire:navigate href="{{ route('app.membership') }}" class="text-night underline underline-offset-4">Mitglied werden</a>
                </p>
            @endcan

            @unless ($followsAnyone)
                <div class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
                    <h2 class="m-0 text-[21px] font-extrabold text-night">Nächste Schritte</h2>
                    <ul class="m-0 flex list-disc flex-col gap-2 pl-5 text-ink-2">
                        <li><a wire:navigate href="{{ route('network.discover') }}" class="text-night underline underline-offset-4">Entdecken, wer schon da ist</a></li>
                        <li><a wire:navigate href="{{ route('network.oases.index') }}" class="text-night underline underline-offset-4">Einer Oase folgen</a></li>
                        <li><a wire:navigate href="{{ route('network.profile.edit') }}" class="text-night underline underline-offset-4">Profil vervollständigen</a></li>
                        <li><a wire:navigate href="{{ route('network.invitations.index') }}" class="text-night underline underline-offset-4">Menschen einladen</a></li>
                    </ul>
                </div>
            @endunless

            <section aria-labelledby="quelle-tropfen" class="flex flex-col gap-4">
                <h2 id="quelle-tropfen" class="sr-only">Tropfen</h2>
                <x-network.feed :posts="$posts" empty="Noch keine Tropfen. Folge Menschen oder Oasen, dann erscheinen ihre Tropfen hier." />
            </section>
        </div>

        <aside aria-label="Überblick" class="flex flex-col gap-5 xl:sticky xl:top-8">
            <section aria-labelledby="seite-woche" class="flex flex-col gap-4 rounded-[24px] bg-night p-6 text-on-night">
                <div class="flex flex-col gap-1">
                    <p class="o-eyebrow o-eyebrow-night m-0">Nur für dich</p>
                    <h2 id="seite-woche" class="m-0 text-[21px] font-extrabold">Deine Woche</h2>
                </div>
                <dl class="m-0 grid grid-cols-3 gap-3">
                    @foreach ([['Reaktionen', $week['reactions']], ['neu dabei', $week['followers']], ['Karawane', $week['karawane']]] as [$label, $value])
                        <div wire:key="source-1-{{ $loop->index }}" class="flex flex-col-reverse gap-1">
                            <dt class="text-sm text-on-night-muted">{{ $label }}</dt>
                            <dd class="m-0 text-[28px] leading-none font-extrabold">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <a wire:navigate href="{{ route('network.stats') }}" class="text-on-night-2 underline underline-offset-4">Alle deine Zahlen</a>
            </section>

            <section aria-labelledby="seite-oasen" class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
                <h2 id="seite-oasen" class="m-0 text-[19px] font-extrabold text-night">Deine Oasen</h2>
                @forelse ($myOases as $oasis)
                    <a wire:key="source-2-{{ $oasis->id }}" wire:navigate href="{{ route('network.oases.show', $oasis) }}" class="flex items-center gap-3 rounded-2xl px-1 py-1.5 text-ink no-underline hover:bg-sand">
                        <span aria-hidden="true" class="size-9 flex-none rounded-full bg-{{ $oasis->color }}"></span>
                        <span class="flex min-w-0 flex-col leading-tight">
                            <span class="truncate font-bold text-night">{{ $oasis->name }}</span>
                            <span class="text-sm text-muted">{{ $oasis->members_count }} {{ $oasis->members_count === 1 ? 'Mitglied' : 'Mitglieder' }}</span>
                        </span>
                    </a>
                @empty
                    <p class="m-0 text-ink-2">Du folgst noch keiner Oase.</p>
                @endforelse
                <a wire:navigate href="{{ route('network.oases.index') }}" class="o-link self-start text-[15px]">Alle Oasen</a>
            </section>

            @if ($openVote)
                <section aria-labelledby="seite-abstimmung" class="flex flex-col gap-3 rounded-[24px] border border-dune-line bg-paper p-6">
                    <span class="self-start rounded-full bg-spring px-3 py-1 text-sm font-extrabold text-sun-ink">Abstimmung läuft</span>
                    <h2 id="seite-abstimmung" class="m-0 text-[19px] leading-snug font-extrabold text-night">{{ $openVote->title }}</h2>
                    <p class="m-0 text-sm text-muted">endet am {{ $openVote->ends_at->locale('de')->isoFormat('D. MMMM') }}</p>
                    <a wire:navigate href="{{ route('app.votes') }}" class="o-btn o-btn-md o-btn-dark self-start">Mitbestimmen</a>
                </section>
            @endif

            <section aria-labelledby="seite-einladen" class="flex flex-col gap-3 rounded-[24px] bg-dune p-6">
                <h2 id="seite-einladen" class="m-0 text-[19px] font-extrabold text-night">Gemeinsam ist schöner</h2>
                <p class="m-0 text-ink-2">Lade Menschen ein, mit denen du gern redest. Ihr folgt euch dann automatisch.</p>
                <a wire:navigate href="{{ route('network.invitations.index') }}" class="o-btn o-btn-md o-btn-outline self-start">Einladen</a>
            </section>
        </aside>
    </div>
</div>
