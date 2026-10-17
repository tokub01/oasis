{{--
    Screen "conversation": one chat. Messages are decrypted in the browser and rendered from the templates below;
    their text is only ever set with textContent. Notices explain a pending key, key changes and unreadable messages.
    JS: resources/js/chat/ui/conversation-view.js
--}}
<section data-screen="conversation" hidden aria-labelledby="chat-conversation-title" class="flex flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex min-w-0 flex-col gap-2">
            <a href="#" class="o-link self-start">Alle Nachrichten</a>
            <h1 id="chat-conversation-title" data-title class="o-h2 m-0 break-words text-night"></h1>
            <p data-lifetime class="m-0 text-sm text-muted"></p>
        </div>
        <a data-details-link class="o-btn o-btn-md o-btn-outline">Details &amp; Sicherheitsnummern</a>
    </div>

    <ul data-notices class="m-0 flex list-none flex-col gap-2 p-0"></ul>

    {{-- Safety banner (client/safety.js, checked only on this device): block and leave, report, or dismiss. --}}
    <div data-safety role="alert" hidden class="flex flex-col gap-3 rounded-[20px] border-2 border-sun bg-dune p-4">
        <p data-safety-title class="m-0 font-extrabold text-night"></p>
        <p data-safety-text class="m-0 text-ink-2"></p>
        <div class="flex flex-wrap gap-2">
            <button type="button" data-safety-block class="o-btn o-btn-sm o-btn-dark">Blockieren und Chat löschen</button>
            <button type="button" data-safety-report class="o-btn o-btn-sm o-btn-outline">Nachricht melden</button>
            <button type="button" data-safety-dismiss class="o-btn o-btn-sm border-none bg-transparent text-night underline">Ist in Ordnung</button>
        </div>
        <p class="m-0 text-xs text-muted">Diese Prüfung läuft nur auf deinem Gerät. Oasis liest deine Chats nicht; gemeldet wird nur, was du meldest. <a href="/community-regeln#chat" class="o-link">Regeln im Chat</a></p>
    </div>

    <button type="button" data-older hidden class="o-btn o-btn-sm o-btn-outline self-center">Ältere Nachrichten laden</button>
    <ol data-messages aria-label="Nachrichten" class="m-0 flex list-none flex-col gap-3 p-0"></ol>
    <p data-empty hidden class="m-0 text-center text-ink-2">Noch keine Nachrichten. Schreib die erste.</p>

    <form data-composer novalidate class="flex flex-col gap-2 rounded-[24px] bg-paper p-4">
        <label for="chat-message" class="sr-only">Nachricht</label>
        <textarea id="chat-message" name="message" rows="3" class="o-input min-h-[96px] resize-y"
                  aria-describedby="chat-message-counter"></textarea>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <span id="chat-message-counter" data-counter class="text-sm text-muted"></span>
            <button type="submit" class="o-btn o-btn-md o-btn-dark">Senden</button>
        </div>
        <p data-composer-hint role="status" hidden class="m-0 rounded-xl bg-dune px-3 py-2 text-sm"></p>
        <p data-form-error role="alert" hidden class="m-0 text-sun-text"></p>
    </form>

    <template data-notice>
        <li class="rounded-2xl bg-dune px-[18px] py-3.5 text-ink"></li>
    </template>

    <template data-message>
        <li class="flex flex-col gap-1">
            <div data-bubble class="flex max-w-[85%] flex-col gap-1 rounded-[20px] px-4 py-3">
                <span data-sender class="text-sm font-bold"></span>
                <p data-text class="m-0 whitespace-pre-wrap break-words"></p>
            </div>
            <div data-footer class="flex flex-wrap items-center gap-3 px-2 text-xs text-muted">
                <span data-expiry></span>
                <button type="button" data-report hidden class="o-link cursor-pointer border-none bg-transparent text-xs">Melden</button>
            </div>
        </li>
    </template>
</section>
