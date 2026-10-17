{{-- Privatsphäre-Center, component App\Livewire\Privacy\Center. Variables: $sections (PrivacyOptions::SECTIONS), $steps. --}}
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
        <section wire:key="section-{{ $sectionKey }}" aria-labelledby="titel-{{ $sectionKey }}" class="flex flex-col gap-5 rounded-[24px] bg-paper p-6">
            <h2 id="titel-{{ $sectionKey }}" class="m-0 text-xl font-extrabold text-night">{{ $title }}</h2>
            @foreach ($keys as $key)
                @include('livewire.privacy.partials.setting', ['key' => $key])
            @endforeach
        </section>
    @endforeach

    <x-slot:aside>
        <x-network.side-card title="Weitere Kontrolle" tone="dune">
            <ul class="m-0 flex list-none flex-col gap-1 p-0">
                <li><a href="{{ route('safety.index') }}" wire:navigate class="o-link inline-flex min-h-11 items-center text-night">Blockiert &amp; stumm</a></li>
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
