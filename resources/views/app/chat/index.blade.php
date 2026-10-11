{{--
    "Nachrichten": the end-to-end encrypted chat (route app.chat, /app/nachrichten).

    One page with client-side screens, because the unlocked keys may only live in the memory of this tab: a full page
    load would mean typing the chat passphrase again. resources/js/chat/app.js shows exactly one [data-screen] at a
    time and routes by URL hash (#neu, #c/{id}, #c/{id}/info). All texts that come from other people are inserted
    with textContent, never as HTML.

    Behaviour without JS: only the notice below; encryption happens in the browser, so the chat needs JavaScript.
    Partials: resources/views/app/chat/partials/*.blade.php (one per screen).
    data-can-participate="0" (read-only account): the UI reads and reports but offers no new chats or sending.
--}}
@extends('layouts.app', ['title' => 'Nachrichten', 'active' => 'nachrichten'])

@push('scripts')
    @vite('resources/js/chat/app.js')
@endpush

@section('content')
    <div data-oasis-chat
         data-user-id="{{ auth()->id() }}"
         data-api="{{ url('/app/chat/api') }}"
         data-can-participate="{{ auth()->user()->can('participate') ? '1' : '0' }}"
         class="flex flex-col gap-6">
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
@endsection
