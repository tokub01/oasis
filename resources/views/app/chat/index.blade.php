{{--
    "Nachrichten": the end-to-end encrypted chat (route app.chat, /app/nachrichten).

    One page with client-side screens, because the unlocked keys may only live in the memory of this tab: a full page
    load would mean typing the chat passphrase again. resources/js/chat/app.js shows exactly one [data-screen] at a
    time and routes by URL hash (#neu, #c/{id}, #c/{id}/info). All texts that come from other people are inserted
    with textContent, never as HTML.

    Behaviour without JS: only the notice below; encryption happens in the browser, so the chat needs JavaScript.
    Partials: resources/views/app/chat/partials/*.blade.php (one per screen).
    data-can-participate="0" (read-only account): the UI reads and reports but offers no new chats or sending.
    Layout: wide page; the screens fill the main column, a static side column explains the encryption.
--}}
@extends('layouts.app', ['title' => 'Nachrichten', 'active' => 'nachrichten', 'wide' => true])

@push('scripts')
    @vite('resources/js/chat/app.js')
@endpush

@section('content')
    <div data-oasis-chat
         data-user-id="{{ auth()->id() }}"
         data-api="{{ url('/app/chat/api') }}"
         data-can-participate="{{ auth()->user()->can('participate') ? '1' : '0' }}"
         {{-- Word lists for the safety checks in the browser (resources/js/chat/client/safety.js); chats never reach the server as text. --}}
         data-safety="{{ json_encode(['lists' => require resource_path('moderation/wortliste.php'), 'tone' => require resource_path('moderation/umgangston.php')]) }}">
        <x-network.columns label="Über Nachrichten">
            <div class="flex flex-col gap-6">
                <noscript>
                    <p class="m-0 rounded-2xl bg-dune px-[18px] py-3.5">
                        Nachrichten werden in deinem Browser verschlüsselt. Dafür braucht diese Seite JavaScript.
                    </p>
                </noscript>

                {{-- Short results ("Gespeichert", "Gesperrt"), visible and announced to screen readers; cleared after a while. --}}
                <p data-chat-announcer role="status" hidden class="m-0 rounded-2xl bg-night px-[18px] py-3.5 text-on-night"></p>

                <section data-screen="loading" class="flex flex-col gap-2">
                    <h1 class="o-h2 m-0 text-night">Nachrichten</h1>
                    <p class="m-0 text-ink-2">Einen Moment …</p>
                </section>

                @include('app.chat.partials.setup')
                @include('app.chat.partials.unlock')
                @include('app.chat.partials.list')
                @include('app.chat.partials.new')
                @include('app.chat.partials.conversation')
                @include('app.chat.partials.details')
                @include('app.chat.partials.report')

                <p data-chat-error role="alert" hidden class="m-0 rounded-2xl bg-dune px-[18px] py-3.5 text-sun-text"></p>
            </div>

            <x-slot:aside>
                <x-network.side-card title="Ende-zu-Ende verschlüsselt" tone="night">
                    <p class="m-0 text-[15px] text-on-night-2">Deine Chat-Schlüssel entstehen in deinem Browser und bleiben mit deiner Passphrase verschlossen. Auf unserem Server liegen nur verschlossene Schlüssel und verschlüsselte Nachrichten.</p>
                </x-network.side-card>
                <x-network.side-card title="Gut zu wissen" tone="dune">
                    <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[15px] text-ink-2">
                        <li>Nachrichten löschen sich nach 1, 7 oder 30 Tagen von selbst.</li>
                        <li>Vergleicht die Sicherheitsnummern in den Details, um vertauschte Schlüssel auszuschließen.</li>
                        <li>Meldest du eine Nachricht, sieht die Moderation nur diese eine.</li>
                    </ul>
                    <a href="{{ route('oasis.sicherheit') }}" wire:navigate class="o-link self-start text-[15px] text-night">Datenschutz &amp; Sicherheit</a>
                </x-network.side-card>
            </x-slot:aside>
        </x-network.columns>
    </div>
@endsection
