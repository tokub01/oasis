{{--
    A real Tropfen card (stage 2), the counterpart of the sample card x-oasis.post.

    Props:
      post     App\Models\Post loaded via PostFeed::withCardData (author.profile, oasis, images, carried, counts,
               `reacted` for the viewer)
      link     bool  link the time to the Tropfen page (default true; false on the Tropfen page itself)

    Shows "leise Zahlen" (reactions/replies) only to the author. Actions call the surrounding Livewire component through InteractsWithPosts; buttons a viewer may not use are not rendered at all (guests get a sign-up hint instead).
--}}
@props(['post', 'link' => true])

@php
    $viewer = auth()->user();
    $canInteract = $viewer?->can('interact', $post) ?? false;
    $carried = $post->carried;
    $postUrl = route('network.posts.show', $post);
    $bookmarked = $viewer && ($post->bookmarked ?? $viewer->hasBookmarked($post));
@endphp

<article id="tropfen-{{ $post->id }}" {{ $attributes->class('flex flex-col gap-3 rounded-[24px] border border-paper-line bg-paper p-[22px]') }}>
    @if ($post->replyTo)
        <p class="m-0 text-sm text-muted">Antwort an <a wire:navigate href="{{ route('network.posts.show', $post->replyTo) }}" class="text-night underline underline-offset-4">{{ '@'.$post->replyTo->author->username }}</a></p>
    @elseif ($post->reply_to_id !== null)
        <p class="m-0 text-sm text-muted">Antwort auf einen Tropfen, der nicht mehr verfügbar ist</p>
    @endif

    <header class="flex items-center gap-3">
        <a wire:navigate href="{{ route('oasis.profil', $post->author->username) }}" class="flex-none no-underline" tabindex="-1">
            <x-network.avatar :user="$post->author" />
        </a>
        <div class="flex min-w-0 flex-col leading-[1.35]">
            <a wire:navigate href="{{ route('oasis.profil', $post->author->username) }}" class="text-base font-extrabold text-ink no-underline">{{ $post->author->display_name }}</a>
            <span class="text-sm text-muted">
                {{ '@'.$post->author->username }}
                @if ($post->oasis)
                    · in <a wire:navigate href="{{ route('network.oases.show', $post->oasis) }}" class="text-muted underline underline-offset-4">{{ $post->oasis->name }}</a>
                @endif
                ·
                @if ($link)
                    <a wire:navigate href="{{ $postUrl }}" class="text-muted underline underline-offset-4"><time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->locale('de')->diffForHumans() }}</time></a>
                @else
                    <time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }}</time>
                @endif
                @if ($post->visibility->value === 'members')
                    · <span title="Nur für Mitglieder">nur Mitglieder</span>
                @endif
            </span>
        </div>
    </header>

    @if ($post->isQuestion())
        <div class="inline-flex items-center gap-1.5 self-start rounded-full bg-sand px-3 py-1 text-xs font-bold text-night">
            <x-oasis.icon name="users" :size="14" class="text-spring-text" />
            <span>{{ $viewer && $viewer->id === $post->author->id ? 'Frage an meine Karawane' : 'Frage an die Karawane' }}</span>
        </div>
    @endif

    @if ($post->removed_from_oasis_at)
        <p class="m-0 rounded-2xl bg-dune px-4 py-3 text-sm">Die Gastgeber:innen haben diesen Tropfen aus der Oase entfernt. Nur du siehst ihn noch.</p>
    @endif
    @if ($post->held_at)
        <p class="m-0 rounded-2xl bg-dune px-4 py-3 text-sm">Unsere automatische Prüfung hat in diesem Tropfen möglicherweise sexuelle Inhalte erkannt. Bis ein Mensch aus dem Moderationsteam ihn geprüft hat, siehst nur du ihn.</p>
    @endif

    <p class="m-0 text-[17px] leading-[1.55] whitespace-pre-line break-words"><x-network.rich-text :text="$post->body" /></p>

    @if ($post->images->isNotEmpty())
        <div @class(['grid gap-2', 'grid-cols-2' => $post->images->count() > 1])>
            @foreach ($post->images as $image)
                <img wire:key="post-card-1-{{ $post->id }}-{{ $image->id }}" src="{{ route('network.images.show', [$post, $image->id]) }}" alt="{{ $image->alt }}"
                     width="{{ $image->width }}" height="{{ $image->height }}" loading="lazy"
                     class="h-auto w-full rounded-2xl bg-dune object-cover">
            @endforeach
        </div>
    @endif

    @if ($post->carried_post_id !== null)
        @if ($carried && $carried->isVisibleTo($viewer))
            {{-- No outer link: the text may contain @/# links, and links must not be nested. --}}
            <div class="flex flex-col gap-1 rounded-2xl border border-dune-line p-4 text-ink">
                <a wire:navigate href="{{ route('network.posts.show', $carried) }}" class="text-sm font-bold text-night no-underline">{{ $carried->author->display_name }} <span class="font-medium text-muted">{{ '@'.$carried->author->username }}</span></a>
                <span class="line-clamp-4 whitespace-pre-line"><x-network.rich-text :text="$carried->body" /></span>
            </div>
        @else
            <p class="m-0 rounded-2xl border border-dashed border-dune-line p-4 text-sm text-muted">Dieser Tropfen ist nicht mehr verfügbar.</p>
        @endif
    @endif

    @if ($post->isQuestion() && $viewer && $viewer->id === $post->author->id)
        <p class="m-0 flex items-center gap-2 rounded-2xl bg-sand px-4 py-2.5 text-[15px] font-bold text-night">
            <x-oasis.icon name="reply" :size="18" class="text-spring-text" />
            <span>
                @if ($post->replies_count === 1)
                    1 Mensch hat geantwortet
                @elseif ($post->replies_count > 1)
                    {{ $post->replies_count }} Menschen haben geantwortet
                @else
                    Noch hat niemand geantwortet
                @endif
                <span class="text-xs font-normal text-muted">(nur für dich sichtbar · {{ $post->reactions_count }} {{ $post->reactions_count === 1 ? 'Reaktion' : 'Reaktionen' }})</span>
            </span>
        </p>
    @elseif ($viewer && $viewer->can('viewStats', $post))
        <p class="m-0 flex items-center gap-2 rounded-2xl bg-sand px-4 py-2.5 text-[15px]">
            <x-oasis.icon name="eye-off" :size="18" class="text-night" />
            <span><b>Nur für dich sichtbar:</b> {{ $post->reactions_count }} {{ $post->reactions_count === 1 ? 'Reaktion' : 'Reaktionen' }}, {{ $post->replies_count }} {{ $post->replies_count === 1 ? 'Antwort' : 'Antworten' }}</span>
        </p>
    @endif

    @if ($link && $post->relationLoaded('conversationReplies') && $post->conversationReplies->isNotEmpty())
        <div class="flex flex-col gap-2 rounded-2xl border border-dune-line bg-sand/40 p-3.5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-muted uppercase tracking-wider">
                    {{ $post->isQuestion() ? 'Antworten aus der Karawane' : 'Gesprächsverlauf' }}
                </span>
                @if ($post->has_more_replies || ($post->replies_count ?? 0) > $post->conversationReplies->count())
                    <a wire:navigate href="{{ $postUrl }}#antworten" class="text-xs font-bold text-night underline underline-offset-4">weitere Antworten</a>
                @endif
            </div>
            <div class="flex flex-col gap-2.5">
                @foreach ($post->conversationReplies as $reply)
                    <div wire:key="post-card-2-{{ $post->id }}-{{ $reply->id }}" class="flex items-start gap-2.5 text-sm">
                        <a wire:navigate href="{{ route('oasis.profil', $reply->author->username) }}" class="flex-none no-underline" tabindex="-1">
                            <x-network.avatar :user="$reply->author" :size="32" />
                        </a>
                        <div class="flex min-w-0 flex-col leading-snug">
                            <div class="flex items-baseline gap-1.5">
                                <a wire:navigate href="{{ route('oasis.profil', $reply->author->username) }}" class="font-bold text-ink no-underline hover:underline">
                                    {{ $reply->author->display_name }}
                                </a>
                                <span class="text-xs text-muted">{{ '@'.$reply->author->username }}</span>
                            </div>
                            <p class="m-0 text-ink-2 whitespace-pre-line break-words text-sm">
                                <x-network.rich-text :text="$reply->body" />
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($post->has_more_replies || ($post->replies_count ?? 0) > $post->conversationReplies->count())
                <a wire:navigate href="{{ $postUrl }}#antworten" class="self-start text-xs font-bold text-night underline underline-offset-4">weitere Antworten</a>
            @endif
        </div>
    @endif

    <footer class="flex flex-wrap items-center gap-1.5 text-muted">
        @if ($canInteract)
            <a wire:navigate href="{{ $postUrl }}#antworten" class="inline-flex min-h-11 items-center gap-2 rounded-full px-3 text-night no-underline">
                <x-oasis.icon name="reply" :size="20" /><span>Antworten</span>
            </a>
            <a wire:navigate href="{{ route('network.posts.carry', $post) }}" class="inline-flex min-h-11 items-center gap-2 rounded-full px-3 text-night no-underline">
                <x-oasis.icon name="repost" :size="20" /><span>Weitertragen</span>
            </a>
            @if ($post->reacted)
                <form wire:submit="unreact({{ $post->id }})">

                    <button type="submit" wire:loading.attr="disabled" aria-pressed="true" class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border-none bg-transparent px-3 text-sun-text">
                        <x-oasis.icon name="heart" :size="20" /><span>Reagiert</span>
                    </button>
                </form>
            @else
                <form wire:submit="react({{ $post->id }})">
                    <button type="submit" wire:loading.attr="disabled" aria-pressed="false" class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border-none bg-transparent px-3 text-night">
                        <x-oasis.icon name="heart" :size="20" /><span>Reagieren</span>
                    </button>
                </form>
            @endif
        @elseif (! $viewer)
            <span class="text-sm">Antworten und Reagieren nur für Mitglieder · <a wire:navigate href="{{ route('register') }}" class="text-night underline underline-offset-4">{{ config('oasis.trial_days') }} Tage testen</a></span>
        @endif

        @if ($viewer)
            @if ($bookmarked)
                <form wire:submit="unbookmark({{ $post->id }})">

                    <button type="submit" wire:loading.attr="disabled" aria-pressed="true" aria-label="Aus Merkliste entfernen" title="Aus Merkliste entfernen" class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border-none bg-transparent px-3 text-spring-text">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="flex-none"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        <span>Gemerkt</span>
                    </button>
                </form>
            @else
                <form wire:submit="bookmark({{ $post->id }})">
                    <button type="submit" wire:loading.attr="disabled" aria-pressed="false" aria-label="Tropfen merken" title="Tropfen merken" class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border-none bg-transparent px-3 text-night">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="flex-none"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        <span>Merken</span>
                    </button>
                </form>
            @endif
        @endif

        <span class="ml-auto flex flex-wrap items-center gap-1.5">
            @can('removeFromOasis', $post)
                <form wire:submit="removePostFromOasis({{ $post->id }})">
                    <button type="submit" wire:loading.attr="disabled" class="o-link cursor-pointer border-none bg-transparent px-2 text-sm">Aus Oase entfernen</button>
                </form>
            @endcan
            @can('delete', $post)
                <details class="relative inline-block">
                    <summary class="o-link cursor-pointer border-none bg-transparent px-2 text-sm">Löschen</summary>
                    <div class="absolute right-0 bottom-full z-10 mb-2 flex flex-col gap-2 rounded-2xl border border-paper-line bg-paper p-3 shadow-lg text-xs whitespace-nowrap">
                        <span class="font-bold text-night">Wirklich löschen?</span>
                        <form wire:submit="deletePost({{ $post->id }})">

                            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-sm bg-sun text-sun-ink text-xs font-bold w-full">Löschen bestätigen</button>
                        </form>
                    </div>
                </details>
            @else
                @if ($viewer && $viewer->id !== $post->author->id)
                    @if ($viewer->hasMuted($post->author))
                        <form wire:submit="unmuteUser({{ $post->author->id }})">

                            <button type="submit" wire:loading.attr="disabled" class="o-link cursor-pointer border-none bg-transparent px-2 text-sm">Nicht mehr stumm</button>
                        </form>
                    @else
                        <form wire:submit="muteUser({{ $post->author->id }})">
                            <button type="submit" wire:loading.attr="disabled" class="o-link cursor-pointer border-none bg-transparent px-2 text-sm">Stumm</button>
                        </form>
                    @endif
                    <details class="relative inline-block">
                        <summary class="o-link cursor-pointer border-none bg-transparent px-2 text-sm text-muted">Blockieren</summary>
                        <div class="absolute right-0 bottom-full z-10 mb-2 flex flex-col gap-2 rounded-2xl border border-paper-line bg-paper p-3 shadow-lg text-xs whitespace-nowrap">
                            <span class="font-bold text-night">Wirklich blockieren?</span>
                            <span class="text-ink-2 max-w-[200px] whitespace-normal">Ihr verliert gegenseitige Follows.</span>
                            <form wire:submit="blockUser({{ $post->author->id }})">
                                <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-sm bg-night text-on-night text-xs font-bold w-full">Blockieren bestätigen</button>
                            </form>
                        </div>
                    </details>
                    <a wire:navigate href="{{ route('oasis.melden', ['url' => $postUrl]) }}" class="o-link px-2 text-sm">Melden</a>
                @elseif ($viewer)
                    <a wire:navigate href="{{ route('oasis.melden', ['url' => $postUrl]) }}" class="o-link px-2 text-sm">Melden</a>
                @endif
            @endcan
        </span>
    </footer>

    @if ($link && $canInteract && $viewer && $viewer->id !== $post->author->id && $viewer->followsMutually($post->author))
        <div id="quick-reply-{{ $post->id }}"><x-network.compose :reply-to="$post" :images="false" /></div>
    @endif
</article>
