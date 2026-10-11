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

    <ul data-conversation-list class="m-0 flex list-none flex-col gap-3 p-0"></ul>
    <p data-empty hidden class="m-0 rounded-[20px] border-2 border-dashed border-spring p-6 text-center text-ink-2">
        Noch keine Unterhaltungen. Starte einen Chat mit jemandem, dessen Benutzernamen du kennst.
    </p>
    <button type="button" data-more hidden class="o-btn o-btn-md o-btn-outline self-start">Ältere laden</button>

    <template data-conversation-item>
        <li>
            <a data-link class="flex flex-col gap-1 rounded-[20px] bg-paper px-5 py-4 text-ink no-underline hover:bg-field">
                <span data-title class="font-bold text-night"></span>
                <span data-meta class="text-sm text-muted"></span>
            </a>
        </li>
    </template>
</section>
