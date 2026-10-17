{{--
    Screen "list": the member's conversations (newest first). Items are rendered from the template below.
    JS: resources/js/chat/ui/conversation-list.js
--}}
<section data-screen="list" hidden aria-labelledby="chat-list-title" class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-2">
            <p class="o-eyebrow m-0">Ende-zu-Ende verschlüsselt</p>
            <h1 id="chat-list-title" class="o-h2 m-0 text-night">Nachrichten</h1>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="#neu" class="o-btn o-btn-md o-btn-dark">Neuer Chat</a>
            <button type="button" data-lock class="o-btn o-btn-md o-btn-outline">Sperren</button>
        </div>
    </div>

    {{-- Message requests: chats from people the member neither follows nor is friends with (no count, no mail, no push). --}}
    <section data-requests hidden aria-labelledby="chat-requests-title" class="flex flex-col gap-3 rounded-[20px] border border-dune-line bg-dune p-4">
        <h2 id="chat-requests-title" class="m-0 text-lg font-extrabold text-night">Anfragen</h2>
        <p class="m-0 text-sm text-ink-2">Von Menschen, denen du nicht folgst und mit denen du nicht befreundet bist. Sie lösen keine Benachrichtigung aus, und niemand sieht, ob du sie gelesen hast. Antworten oder Annehmen macht daraus einen normalen Chat.</p>
        <ul data-request-list class="m-0 flex list-none flex-col gap-2 p-0"></ul>
    </section>

    <ul data-conversation-list class="m-0 flex list-none flex-col gap-3 p-0"></ul>
    <p data-empty hidden class="m-0 rounded-[20px] border-2 border-dashed border-spring p-6 text-center text-ink-2">
        Noch keine Unterhaltungen. Starte einen Chat mit jemandem, dessen Benutzernamen du kennst.
    </p>
    <button type="button" data-more hidden class="o-btn o-btn-md o-btn-outline self-start">Ältere laden</button>

    {{-- The same switches as in the Privatsphäre-Center, right here (client: conversation-list.js). --}}
    <details data-chat-settings class="rounded-[20px] bg-paper p-4">
        <summary class="cursor-pointer font-bold text-night">Wer darf dir schreiben?</summary>
        <div class="mt-3 flex flex-col gap-3">
            <label for="chat-invites" class="text-[15px] font-bold">Chats beginnen dürfen</label>
            <select id="chat-invites" data-invites class="o-input"></select>
            <p class="m-0 text-sm text-muted">Bei „Alle Mitglieder“ landen Menschen, denen du nicht folgst und mit denen du nicht befreundet bist, als Anfrage.</p>
            <label class="flex min-h-11 items-center gap-3">
                <input type="checkbox" data-friend-requests class="accent-night">
                Freundschaftsanfragen erhalten
            </label>
            <p data-settings-status role="status" class="m-0 text-sm font-bold text-spring-text empty:sr-only"></p>
        </div>
    </details>

    <template data-conversation-item>
        <li>
            <a data-link class="flex flex-col gap-1 rounded-[20px] bg-paper px-5 py-4 text-ink no-underline hover:bg-field">
                <span class="flex items-center gap-2">
                    <span data-title class="font-bold text-night"></span>
                    <span data-unread hidden class="rounded-full bg-sun px-2 py-0.5 text-xs font-extrabold text-sun-ink">Neu</span>
                </span>
                <span data-meta class="text-sm text-muted"></span>
            </a>
        </li>
    </template>
</section>
