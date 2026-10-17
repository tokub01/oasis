{{--
    Privatsphäre-Center, component App\Livewire\Privacy\Center. Variables: $sections (PrivacyOptions::SECTIONS), $steps,
    and the counts for the further controls: $publicPosts, $hiddenWords, $restricted, $blocked, $muted.
--}}
<div>
<x-network.columns label="Mehr Kontrolle">
    <div class="flex flex-col gap-3">
        <x-network.page-header eyebrow="Privatsphäre" title="Deine Privatsphäre">
            Du entscheidest, wer dich sieht und wer mit dir in Kontakt kommt. Jede Änderung gilt sofort.
        </x-network.page-header>
        <p role="status" aria-live="polite" class="m-0 font-bold text-spring-text empty:sr-only">{{ $status }}</p>
    </div>

    <a href="{{ route('app.privacy.check') }}" wire:navigate
       class="flex items-center justify-between gap-4 rounded-[24px] bg-night px-6 py-5 text-on-night no-underline">
        <span class="flex flex-col gap-1">
            <span class="text-lg font-extrabold">Privatsphäre-Check</span>
            <span class="text-[15px] text-on-night-2">In {{ $steps }} kurzen Schritten durch die wichtigsten Einstellungen.</span>
        </span>
        <x-oasis.icon name="arrow-right" :size="22" />
    </a>

    @foreach ($sections as $sectionKey => [$title, $keys])
        <section wire:key="section-{{ $sectionKey }}" aria-labelledby="titel-{{ $sectionKey }}" class="flex flex-col gap-5 rounded-[24px] bg-paper p-5 sm:p-6">
            <h2 id="titel-{{ $sectionKey }}" class="m-0 text-xl font-extrabold text-night">{{ $title }}</h2>
            @foreach ($keys as $key)
                @include('livewire.privacy.partials.setting', ['key' => $key])
            @endforeach
        </section>
    @endforeach

    <section aria-labelledby="titel-mehr" class="flex flex-col gap-4">
        <h2 id="titel-mehr" class="m-0 text-xl font-extrabold text-night">Noch mehr Kontrolle</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="flex flex-col gap-3 rounded-[24px] bg-paper p-5 sm:p-6">
                <h3 class="m-0 text-[17px] font-bold text-night">Frühere Tropfen begrenzen</h3>
                <p class="m-0 text-[15px] leading-[1.55] text-ink-2">
                    Macht alle deine öffentlichen Tropfen auf einmal nur für Mitglieder sichtbar – Gäste und Suchmaschinen sehen sie dann nicht mehr.
                    {{ $publicPosts === 0 ? 'Du hast gerade keine öffentlichen Tropfen.' : ($publicPosts === 1 ? 'Betrifft 1 Tropfen.' : 'Betrifft '.$publicPosts.' Tropfen.') }}
                </p>
                @if ($publicPosts > 0)
                    <button type="button" wire:click="limitPastPosts" wire:confirm="Alle {{ $publicPosts }} öffentlichen Tropfen nur noch für Mitglieder sichtbar machen? Das lässt sich nicht auf einmal rückgängig machen." wire:loading.attr="disabled"
                            class="o-btn o-btn-md o-btn-outline mt-auto self-start">Tropfen begrenzen</button>
                @endif
            </div>
            <a href="{{ route('app.privacy.words') }}" wire:navigate class="flex flex-col gap-3 rounded-[24px] bg-paper p-5 text-ink no-underline hover:bg-sand sm:p-6">
                <span class="flex items-center justify-between gap-3 text-[17px] font-bold text-night">Verborgene Wörter <x-oasis.icon name="arrow-right" :size="20" /></span>
                <span class="text-[15px] leading-[1.55] text-ink-2">Tropfen, Antworten und Hinweise mit bestimmten Wörtern ausblenden. {{ $hiddenWords === 0 ? 'Noch keine Wörter.' : $hiddenWords.' '.($hiddenWords === 1 ? 'Wort' : 'Wörter').' verborgen.' }}</span>
            </a>
            <a href="{{ route('safety.index') }}" wire:navigate class="flex flex-col gap-3 rounded-[24px] bg-paper p-5 text-ink no-underline hover:bg-sand sm:p-6">
                <span class="flex items-center justify-between gap-3 text-[17px] font-bold text-night">Blockiert, stumm, eingeschränkt <x-oasis.icon name="arrow-right" :size="20" /></span>
                <span class="text-[15px] leading-[1.55] text-ink-2">{{ $blocked }} blockiert · {{ $muted }} stumm · {{ $restricted }} eingeschränkt. Einschränken startest du im Menü „⋯“ eines Tropfens oder auf einem Profil.</span>
            </a>
            <a href="{{ route('app.privacy.devices') }}" wire:navigate class="flex flex-col gap-3 rounded-[24px] bg-paper p-5 text-ink no-underline hover:bg-sand sm:p-6">
                <span class="flex items-center justify-between gap-3 text-[17px] font-bold text-night">Geräte und Anmeldungen <x-oasis.icon name="arrow-right" :size="20" /></span>
                <span class="text-[15px] leading-[1.55] text-ink-2">Sehen, wo du angemeldet bist, und fremde Geräte abmelden.</span>
            </a>
        </div>
    </section>

    <x-slot:aside>
        <x-network.side-card title="Weitere Kontrolle" tone="dune">
            <ul class="m-0 flex list-none flex-col gap-1 p-0">
                <li><a href="{{ route('safety.index') }}" wire:navigate class="o-link inline-flex min-h-11 items-center text-night">Blockiert, stumm &amp; eingeschränkt</a></li>
                <li><a href="{{ route('app.privacy.devices') }}" wire:navigate class="o-link inline-flex min-h-11 items-center text-night">Geräte und Anmeldungen</a></li>
                <li><a href="{{ route('notifications.settings') }}" wire:navigate class="o-link inline-flex min-h-11 items-center text-night">Hinweise einstellen</a></li>
                <li><a href="{{ route('account.export') }}" class="o-link inline-flex min-h-11 items-center text-night">Deine Daten herunterladen</a></li>
                <li><a href="{{ route('app.settings') }}#loeschen-titel" class="o-link inline-flex min-h-11 items-center text-night">Konto löschen</a></li>
            </ul>
        </x-network.side-card>
        <x-network.side-card title="So schützen wir dich" tone="night">
            <p class="m-0 text-[15px] text-on-night-2">Blockieren wirkt immer, egal was hier eingestellt ist. Wie wir deine Daten schützen, steht unter Datenschutz &amp; Sicherheit.</p>
            <a href="{{ route('oasis.sicherheit') }}" wire:navigate class="o-link self-start text-[15px] text-on-night">Datenschutz &amp; Sicherheit</a>
        </x-network.side-card>
    </x-slot:aside>
</x-network.columns>
</div>
