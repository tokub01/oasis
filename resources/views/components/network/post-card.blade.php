{{--
    A real Tropfen card (stage 2), the counterpart of the sample card x-oasis.post.

    Props:
      post     App\Models\Post loaded via PostFeed::withCardData (author.profile, oasis, images, carried, counts,
               `reacted` for the viewer)
      link     bool  link the time to the Tropfen page (default true; false on the Tropfen page itself)

    A carried Tropfen of a person the viewer muted is replaced by a neutral line: muting hides that person
    everywhere, also when someone else carries their words further.
    Shows "leise Zahlen" (reactions/replies) only to the author. Actions call the surrounding Livewire component through InteractsWithPosts; buttons a viewer may not use are not rendered at all (guests get a sign-up hint instead).
--}}
@props(['post', 'link' => true])

@php
    $viewer = auth()->user();
    $canInteract = $viewer?->can('interact', $post) ?? false;
    $canReply = $viewer?->can('reply', $post) ?? false;
    $canCarry = $viewer?->can('carry', $post) ?? false;
    $carried = $post->carried;
    $carriedMuted = $carried && $viewer && in_array($carried->user_id, $viewer->mutedUserIds(), true);
    $postUrl = route('network.posts.show', $post);
    $bookmarked = $viewer && ($post->bookmarked ?? $viewer->hasBookmarked($post));
    // Phones: the actions share one row as icon-over-label tiles (icons only below 380px, labels stay for screen
    // readers); from 640px they sit side by side as pills.
    $actionCount = ($canInteract ? 1 + (int) $canReply + (int) $canCarry : 0) + ($viewer ? 1 : 0);
    $actionGrid = $actionCount === 3 ? 'grid-cols-3' : 'grid-cols-4';
    $action = 'inline-flex min-h-11 w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-none bg-transparent px-1 py-1 text-xs no-underline sm:w-auto sm:flex-row sm:gap-2 sm:rounded-full sm:px-3 sm:text-base';
    $menuItem = 'flex min-h-11 w-full cursor-pointer items-center rounded-xl border-none bg-transparent px-3 text-left text-[15px] font-bold text-night no-underline hover:bg-sand';
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
        <div class="flex min-w-0 flex-1 flex-col leading-[1.35]">
            <a wire:navigate href="{{ route('oasis.profil', $post->author->username) }}" class="self-start text-base font-extrabold text-ink no-underline">{{ $post->author->display_name }}</a>
            <span class="text-sm text-muted">
                {{ '@'.$post->author->username }}
                @if ($post->oasis)
                    · in <a wire:navigate href="{{ route('network.oases.show', $post->oasis) }}" class="py-2 text-muted underline underline-offset-4">{{ $post->oasis->name }}</a>
                @endif
                ·
                @if ($link)
                    <a wire:navigate href="{{ $postUrl }}" class="py-2 text-muted underline underline-offset-4"><time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->locale('de')->diffForHumans() }}</time></a>
                @else
                    <time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }}</time>
                @endif
                @if ($post->visibility->value === 'members')
                    · <span title="Nur für Mitglieder">nur Mitglieder</span>
                @endif
                @if ($post->edited_at)
                    · <a wire:navigate href="{{ $postUrl }}#versionen" class="py-2 text-muted underline underline-offset-4" title="Bearbeitet {{ $post->edited_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}">bearbeitet</a>
                @endif
            </span>
        </div>

        @if ($viewer)
            {{-- Rarely needed actions live in a "⋯" menu (no JavaScript: <details>); confirmations stay nested inside. --}}
            <details class="relative flex-none self-start">
                <summary aria-label="Weitere Aktionen zum Tropfen von {{ $post->author->display_name }}" title="Weitere Aktionen"
                         class="flex size-11 cursor-pointer list-none items-center justify-center rounded-full text-2xl leading-none text-night hover:bg-sand [&::-webkit-details-marker]:hidden">
                    <span aria-hidden="true">⋯</span>
                </summary>
                <div class="absolute top-full right-0 z-10 mt-1 flex w-64 flex-col rounded-2xl border border-paper-line bg-paper p-2 shadow-lg">
                    @can('removeFromOasis', $post)
                        <form wire:submit="removePostFromOasis({{ $post->id }})">
                            <button type="submit" wire:loading.attr="disabled" class="{{ $menuItem }}">Aus Oase entfernen</button>
                        </form>
                    @endcan
                    @can('update', $post)
                        <a wire:navigate href="{{ route('network.posts.edit', $post) }}" class="{{ $menuItem }}">Bearbeiten</a>
                    @endcan
                    @can('delete', $post)
                        <details>
                            <summary class="{{ $menuItem }} list-none [&::-webkit-details-marker]:hidden">Löschen …</summary>
                            <div class="flex flex-col gap-2 px-3 pt-1 pb-2 text-sm">
                                <span class="font-bold text-night">Wirklich löschen?</span>
                                <form wire:submit="deletePost({{ $post->id }})">
                                    <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-sm w-full bg-sun text-sm font-bold text-sun-ink">Löschen bestätigen</button>
                                </form>
                            </div>
                        </details>
                    @elseif ($viewer->id !== $post->author->id)
                        @if ($viewer->hasMuted($post->author))
                            <form wire:submit="unmuteUser({{ $post->author->id }})">
                                <button type="submit" wire:loading.attr="disabled" class="{{ $menuItem }}">Nicht mehr stumm</button>
                            </form>
                        @else
                            <form wire:submit="muteUser({{ $post->author->id }})">
                                <button type="submit" wire:loading.attr="disabled" class="{{ $menuItem }}">Stummschalten</button>
                            </form>
                        @endif
                        @if ($viewer->hasRestricted($post->author))
                            <form wire:submit="unrestrictUser({{ $post->author->id }})">
                                <button type="submit" wire:loading.attr="disabled" class="{{ $menuItem }}">Nicht mehr einschränken</button>
                            </form>
                        @else
                            <form wire:submit="restrictUser({{ $post->author->id }})">
                                <button type="submit" wire:loading.attr="disabled" class="{{ $menuItem }}">Einschränken</button>
                            </form>
                        @endif
                        <details>
                            <summary class="{{ $menuItem }} list-none [&::-webkit-details-marker]:hidden">Blockieren …</summary>
                            <div class="flex flex-col gap-2 px-3 pt-1 pb-2 text-sm">
                                <span class="font-bold text-night">Wirklich blockieren?</span>
                                <span class="text-ink-2">Ihr verliert gegenseitige Follows.</span>
                                <form wire:submit="blockUser({{ $post->author->id }})">
                                    <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-sm w-full bg-night text-sm font-bold text-on-night">Blockieren bestätigen</button>
                                </form>
                            </div>
                        </details>
                        <a wire:navigate href="{{ route('oasis.melden', ['url' => $postUrl]) }}" class="{{ $menuItem }}">Melden</a>
                    @else
                        <a wire:navigate href="{{ route('oasis.melden', ['url' => $postUrl]) }}" class="{{ $menuItem }}">Melden</a>
                    @endcan
                </div>
            </details>
        @endif
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
        {{-- Held by the automated check (docs/oasis-auto-moderation.md); only the author and staff see the Tropfen. --}}
        <div role="note" class="flex flex-col gap-1.5 rounded-2xl border border-sun/40 bg-dune px-4 py-3 text-sm">
            <p class="m-0 font-bold text-night">Zurückgehalten und dem Moderationsteam gemeldet</p>
            <p class="m-0">Unsere automatische Prüfung hat in diesem Tropfen möglicherweise {{ $post->holdReason() }} erkannt. Bis ein Mensch aus dem Moderationsteam ihn geprüft hat, siehst nur du ihn – in der Regel innerhalb von 48 Stunden.</p>
            @if (filled($post->contentCheck?->reason))
                <p class="m-0 text-ink-2">Begründung der Prüfung: „{{ $post->contentCheck->reason }}“</p>
            @endif
            <p class="m-0 text-ink-2">Ist alles in Ordnung, wird er für alle sichtbar. Du kannst ihn jederzeit selbst löschen. <a href="{{ route('oasis.regeln') }}" class="o-link">Community-Regeln</a></p>
        </div>
    @endif

    <p class="m-0 text-[17px] leading-[1.55] whitespace-pre-line break-words"><x-network.rich-text :text="$post->body" :author="$post->author" /></p>

    @if ($post->quarantined_at && $post->images->isNotEmpty())
        <p class="m-0 rounded-2xl border-2 border-dashed border-dune-line px-4 py-3 text-sm text-ink-2">Bilder gesperrt.</p>
    @elseif ($post->images->isNotEmpty())
        <div @class(['grid gap-2', 'grid-cols-2' => $post->images->count() > 1])>
            @foreach ($post->images as $image)
                <img wire:key="post-card-1-{{ $post->id }}-{{ $image->id }}" src="{{ route('network.images.show', [$post, $image->id]) }}" alt="{{ $image->alt }}"
                     width="{{ $image->width }}" height="{{ $image->height }}" loading="lazy"
                     class="h-auto w-full rounded-2xl bg-dune object-cover">
            @endforeach
        </div>
    @endif

    @if ($post->carried_post_id !== null)
        @if ($carriedMuted)
            <p class="m-0 rounded-2xl border border-dashed border-dune-line p-4 text-sm text-muted">Tropfen einer stummgeschalteten Person.</p>
        @elseif ($carried && $carried->isVisibleTo($viewer))
            {{-- No outer link: the text may contain @/# links, and links must not be nested. --}}
            <div class="flex flex-col gap-1 rounded-2xl border border-dune-line p-4 text-ink">
                <a wire:navigate href="{{ route('network.posts.show', $carried) }}" class="text-sm font-bold text-night no-underline">{{ $carried->author->display_name }} <span class="font-medium text-muted">{{ '@'.$carried->author->username }}</span></a>
                <span class="line-clamp-4 whitespace-pre-line"><x-network.rich-text :text="$carried->body" :author="$carried->author" /></span>
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
                            <p class="m-0 text-ink-2 whitespace-pre-line break-words text-sm"><x-network.rich-text :text="$reply->body" :author="$reply->author" /></p>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($post->has_more_replies || ($post->replies_count ?? 0) > $post->conversationReplies->count())
                <a wire:navigate href="{{ $postUrl }}#antworten" class="self-start text-xs font-bold text-night underline underline-offset-4">weitere Antworten</a>
            @endif
        </div>
    @endif

    @if ($viewer)
        <footer class="grid w-full {{ $actionGrid }} gap-1 text-muted sm:flex sm:flex-wrap sm:items-center sm:gap-1.5">
            @if ($canInteract)
                @if ($canReply)
                    <a wire:navigate href="{{ $postUrl }}#antworten" class="{{ $action }} text-night">
                        <x-oasis.icon name="reply" :size="20" /><span class="max-[379px]:sr-only">Antworten</span>
                    </a>
                @endif
                @if ($canCarry)
                    <a wire:navigate href="{{ route('network.posts.carry', $post) }}" class="{{ $action }} text-night">
                        <x-oasis.icon name="repost" :size="20" /><span class="max-[379px]:sr-only">Weitertragen</span>
                    </a>
                @endif
                @if ($post->reacted)
                    <form wire:submit="unreact({{ $post->id }})">
                        <button type="submit" wire:loading.attr="disabled" aria-pressed="true" class="{{ $action }} text-sun-text">
                            <x-oasis.icon name="heart" :size="20" /><span class="max-[379px]:sr-only">Reagiert</span>
                        </button>
                    </form>
                @else
                    <form wire:submit="react({{ $post->id }})">
                        <button type="submit" wire:loading.attr="disabled" aria-pressed="false" class="{{ $action }} text-night">
                            <x-oasis.icon name="heart" :size="20" /><span class="max-[379px]:sr-only">Reagieren</span>
                        </button>
                    </form>
                @endif
            @endif

            @if ($bookmarked)
                <form wire:submit="unbookmark({{ $post->id }})">
                    <button type="submit" wire:loading.attr="disabled" aria-pressed="true" aria-label="Aus Merkliste entfernen" title="Aus Merkliste entfernen" class="{{ $action }} text-spring-text">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="flex-none"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        <span class="max-[379px]:sr-only">Gemerkt</span>
                    </button>
                </form>
            @else
                <form wire:submit="bookmark({{ $post->id }})">
                    <button type="submit" wire:loading.attr="disabled" aria-pressed="false" aria-label="Tropfen merken" title="Tropfen merken" class="{{ $action }} text-night">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="flex-none"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        <span class="max-[379px]:sr-only">Merken</span>
                    </button>
                </form>
            @endif
        </footer>
    @else
        <footer class="text-sm text-muted">
            Antworten und Reagieren nur für Mitglieder <span class="whitespace-nowrap"><span aria-hidden="true" class="max-sm:hidden">· </span><a wire:navigate href="{{ route('register') }}" class="-my-2 inline-block py-2 max-sm:block text-night underline underline-offset-4">{{ config('oasis.trial_days') }} Tage testen</a></span>
        </footer>
    @endif

    @if ($link && $canReply && $viewer && $viewer->id !== $post->author->id && $viewer->followsMutually($post->author))
        <details id="quick-reply-{{ $post->id }}" class="border-t border-paper-line pt-2" wire:ignore.self>
            <summary class="flex min-h-11 cursor-pointer items-center gap-2 text-[15px] font-bold text-night">
                <x-oasis.icon name="reply" :size="18" />Schnell antworten
            </summary>
            <x-network.compose :reply-to="$post" :images="false" :inline="true" />
        </details>
    @endif
</article>
