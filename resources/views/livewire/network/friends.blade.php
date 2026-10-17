{{--
    Friends, component App\Livewire\Network\Friends (route network.friends).
    Variables: $incoming, $outgoing, $friends (Collection<User>), $acceptsRequests (bool).
--}}
<div>
<x-network.columns label="Freundschaften">
    <x-network.page-header eyebrow="Freund:innen" title="Freundschaften">Freundschaft und Folgen sind getrennt: Freund:innen dürfen dir schreiben und ihre Bilder siehst du sofort – folgen müsst ihr euch dafür nicht.</x-network.page-header>
    <p role="status" class="m-0 font-bold text-spring-text empty:sr-only">{{ $postStatus }}</p>

    <section aria-labelledby="anfragen-titel" class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
        <h2 id="anfragen-titel" class="m-0 text-xl font-extrabold text-night">Anfragen an dich</h2>
        @forelse ($incoming as $person)
            <div wire:key="in-{{ $person->id }}" class="flex flex-wrap items-center justify-between gap-3">
                <a wire:navigate href="{{ route('oasis.profil', $person->username) }}" class="flex min-w-0 items-center gap-3 text-ink no-underline">
                    <x-network.avatar :user="$person" :size="40" />
                    <span class="flex min-w-0 flex-col leading-tight"><span class="truncate font-bold text-night">{{ $person->display_name }}</span><span class="truncate text-sm text-muted">{{ '@'.$person->username }}</span></span>
                </a>
                <div class="flex gap-2">
                    <button type="button" wire:click="accept({{ $person->id }})" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-dark">Annehmen</button>
                    <button type="button" wire:click="remove({{ $person->id }})" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline">Ablehnen</button>
                </div>
            </div>
        @empty
            <p class="m-0 text-ink-2">Gerade keine offenen Anfragen.</p>
        @endforelse
        <p class="m-0 text-sm text-muted">
            @if ($acceptsRequests)
                Ablehnen sagt niemandem etwas. Möchtest du gar keine Anfragen bekommen? <a href="{{ route('app.privacy') }}#titel-interaktionen" wire:navigate class="o-link">Freundschaftsanfragen ausschalten</a>
            @else
                Du nimmst gerade keine Freundschaftsanfragen an. <a href="{{ route('app.privacy') }}#titel-interaktionen" wire:navigate class="o-link">Wieder einschalten</a>
            @endif
        </p>
    </section>

    <section aria-labelledby="freunde-titel" class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
        <h2 id="freunde-titel" class="m-0 text-xl font-extrabold text-night">Deine Freund:innen</h2>
        @forelse ($friends as $person)
            <div wire:key="friend-{{ $person->id }}" class="flex flex-wrap items-center justify-between gap-3">
                <a wire:navigate href="{{ route('oasis.profil', $person->username) }}" class="flex min-w-0 items-center gap-3 text-ink no-underline">
                    <x-network.avatar :user="$person" :size="40" />
                    <span class="flex min-w-0 flex-col leading-tight"><span class="truncate font-bold text-night">{{ $person->display_name }}</span><span class="truncate text-sm text-muted">{{ '@'.$person->username }}</span></span>
                </a>
                <button type="button" wire:click="remove({{ $person->id }})" wire:confirm="Freundschaft mit {{ $person->display_name }} beenden?" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline">Freundschaft beenden</button>
            </div>
        @empty
            <p class="m-0 text-ink-2">Noch keine. Auf einem Profil findest du „Freundschaft anfragen“.</p>
        @endforelse
    </section>

    @if ($outgoing->isNotEmpty())
        <section aria-labelledby="gesendet-titel" class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
            <h2 id="gesendet-titel" class="m-0 text-xl font-extrabold text-night">Von dir gesendet</h2>
            @foreach ($outgoing as $person)
                <div wire:key="out-{{ $person->id }}" class="flex flex-wrap items-center justify-between gap-3">
                    <span class="font-bold text-night">{{ $person->display_name }} <span class="font-normal text-muted">{{ '@'.$person->username }}</span></span>
                    <button type="button" wire:click="remove({{ $person->id }})" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline">Zurückziehen</button>
                </div>
            @endforeach
        </section>
    @endif
</x-network.columns>
</div>
