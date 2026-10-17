{{--
    A member's profile (route oasis.profil, /profil/{handle}, App\Livewire\Network\ProfileShow). Guests see public profiles
    only when the profile audience allows it. The Karawane size has its own audience; per-Tropfen counts stay with the author.
    Side column: about, oases and – for the owner – links to edit and to the own numbers.

    Variables:
      $member     User
      $profile    Profile (stored or default)
      $posts      CursorPaginator<Post>  PostFeed::byAuthor
      $followers  int
      $oases      Collection<Oasis>  oases the member belongs to that the viewer may see
      $following  bool  whether the viewer follows this member
--}}
@php($isMe = auth()->id() === $member->id)

<div @class(['flex flex-col gap-6', 'o-container py-8' => ! auth()->check()])>
@guest <p class="rounded-2xl bg-dune p-4"><b>Du liest als Gast.</b> Zum Folgen, Antworten und Schreiben brauchst du ein Konto.</p> @endguest
<p role="status" aria-live="polite">{{ $postStatus }}</p>
<p wire:loading class="text-muted">Wird geladen …</p>
    <x-network.columns label="Über {{ $member->display_name }}">
        <header class="overflow-hidden rounded-[24px] bg-paper">
            <div aria-hidden="true" @class(['h-28', $profile->avatar_color->classes()])></div>
            <div class="flex flex-col gap-4 p-6 pt-0">
                <div class="-mt-10 flex flex-wrap items-end justify-between gap-4">
                    <x-network.avatar :user="$member" :size="88" class="ring-4 ring-paper" />
                    @auth
                        @if ($isMe)
                            <a wire:navigate href="{{ route('network.profile.edit') }}" class="o-btn o-btn-md o-btn-outline">Profil bearbeiten</a>
                        @else
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($following)
                                    <form wire:submit="unfollow">

                                        <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark">Gefolgt · nicht mehr folgen</button>
                                    </form>
                                @elseif (auth()->user()->can('participate'))
                                    <form wire:submit="follow">
                                        <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-primary">Folgen</button>
                                    </form>
                                @endif

                                @if (auth()->user()->hasMuted($member))
                                    <form wire:submit="unmute">

                                        <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-outline">Nicht mehr stumm</button>
                                    </form>
                                @else
                                    <form wire:submit="muteUser({{ $member->id }})">
                                        <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-outline">Stummschalten</button>
                                    </form>
                                @endif

                                <form wire:submit="blockUser({{ $member->id }})">
                                    <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-outline text-muted">Blockieren</button>
                                </form>
                                <a wire:navigate href="{{ route('oasis.melden', ['url' => route('oasis.profil', $member->username)]) }}" class="o-link self-center text-[15px]">Melden</a>
                            </div>
                        @endif
                    @endauth
                </div>
                <div class="flex flex-col gap-1">
                    <h1 class="o-h2 m-0 break-words text-night">{{ $member->display_name }}</h1>
                    <p class="m-0 text-muted">{{ '@'.$member->username }} · dabei seit {{ $member->created_at->locale('de')->isoFormat('MMMM YYYY') }}</p>
                </div>
                @if ($profile->bio)
                    <p class="m-0 text-[17px] whitespace-pre-line">{{ $profile->bio }}</p>
                @endif
                <p class="m-0 flex flex-wrap gap-x-5 gap-y-1 text-ink-2">
                    @if ($profile->location)
                        <span class="inline-flex items-center gap-1.5"><x-oasis.icon name="map-pin" :size="18" /> {{ $profile->location }}</span>
                    @endif
                    @if ($followers !== null)
                    <span class="inline-flex items-center gap-1.5"><x-oasis.icon name="users" :size="18" /> Karawane: {{ $followers }}</span>
                    @endif
                </p>
            </div>
        </header>

        <section aria-labelledby="profil-tropfen" class="flex flex-col gap-4">
            <h2 id="profil-tropfen" class="m-0 text-[21px] font-extrabold text-night">Tropfen</h2>
            <x-network.feed :posts="$posts" empty="Noch keine Tropfen." />
        </section>

        <x-slot:aside>
            <x-network.side-card title="Oasen">
                @forelse ($oases as $oasis)
                    <a wire:key="profile-show-1-{{ $oasis->id }}" wire:navigate href="{{ route('network.oases.show', $oasis) }}" class="flex items-center gap-3 rounded-2xl px-1 py-1 text-ink no-underline hover:bg-sand">
                        <span aria-hidden="true" class="size-9 flex-none rounded-full bg-{{ $oasis->color }}"></span>
                        <span class="flex min-w-0 flex-col leading-tight">
                            <span class="truncate font-bold text-night">{{ $oasis->name }}</span>
                            <span class="text-sm text-muted">{{ $oasis->pivot->role === 'host' ? 'Gastgeber:in' : 'Mitglied' }}</span>
                        </span>
                    </a>
                @empty
                    <p class="m-0 text-[15px] text-ink-2">Noch in keiner Oase.</p>
                @endforelse
            </x-network.side-card>

            @if ($isMe)
                <x-network.side-card title="Nur für dich" tone="night">
                    <p class="m-0 text-[15px] text-on-night-2">Wie deine Tropfen ankommen, siehst nur du.</p>
                    <a wire:navigate href="{{ route('network.stats') }}" class="o-btn o-btn-md self-start bg-on-night text-night">Deine Zahlen</a>
                </x-network.side-card>
                <x-network.side-card title="Sichtbarkeit" tone="dune">
                    <p class="m-0 text-[15px] text-ink-2">{{ $member->privacy()->profile_audience->label() }}. <a wire:navigate href="{{ route('app.privacy') }}" class="o-link">Privatsphäre-Einstellungen</a></p>
                </x-network.side-card>
            @else
                <x-network.side-card title="Ein Netzwerk ohne Werbung" tone="dune">
                    <p class="m-0 text-[15px] text-ink-2">Auf Oasis siehst du Tropfen der Reihe nach, ohne Empfehlungen, die dich festhalten sollen.</p>
                    @guest
                        <a wire:navigate href="{{ route('register') }}" class="o-btn o-btn-md o-btn-primary self-start">{{ config('oasis.trial_days') }} Tage testen</a>
                    @endguest
                </x-network.side-card>
            @endif
        </x-slot:aside>
    </x-network.columns>
</div>
