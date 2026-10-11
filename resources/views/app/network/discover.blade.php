{{--
    "Entdecken" for members (route network.discover): public Tropfen of public profiles, newest first, and the
    oases in a side column. No ranking by engagement.
    Variables: $posts (CursorPaginator<Post>), $oases (Collection<Oasis> with members_count, largest first)
--}}
@extends('layouts.app', ['title' => 'Entdecken', 'active' => 'entdecken', 'wide' => true])

@section('content')
    <x-network.columns label="Oasen und Hinweise">
        <div class="flex flex-col gap-2">
            <p class="o-eyebrow m-0">Entdecken</p>
            <h1 class="o-h2 m-0 text-night">Wer schon da ist</h1>
            <p class="o-lead m-0 text-ink-2">Öffentliche Tropfen, der Reihe nach. Keine Empfehlungen nach Klicks.</p>
        </div>

        <section aria-labelledby="entdecken-tropfen" class="flex flex-col gap-4">
            <h2 id="entdecken-tropfen" class="sr-only">Öffentliche Tropfen</h2>
            <x-network.feed :posts="$posts" empty="Noch keine öffentlichen Tropfen." />
        </section>

        <x-slot:aside>
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
                    <p class="m-0 text-[15px] text-ink-2">Noch keine Oasen.</p>
                @endforelse
                <a href="{{ route('network.oases.index') }}" class="o-link self-start text-[15px]">Alle Oasen</a>
            </x-network.side-card>

            <x-network.side-card title="So funktioniert Entdecken" tone="dune">
                <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[15px] text-ink-2">
                    <li>Hier stehen nur Tropfen von Menschen, die ihr Profil öffentlich gemacht haben.</li>
                    <li>Neueste zuerst – niemand bezahlt dafür, weiter oben zu stehen.</li>
                    <li>Folge Menschen, dann landen ihre Tropfen in deiner Quelle.</li>
                </ul>
            </x-network.side-card>
        </x-slot:aside>
    </x-network.columns>
@endsection
