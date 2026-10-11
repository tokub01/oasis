{{--
    Screen "details": settings of one chat – lifetime (owner), members, add/remove (group owner) and the pairwise
    safety numbers people compare in person to rule out a swapped key.
    JS: resources/js/chat/ui/conversation-details.js
--}}
<section data-screen="details" hidden aria-labelledby="chat-details-title" class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <a data-back class="o-link self-start">Zurück zum Chat</a>
        <h1 id="chat-details-title" class="o-h2 m-0 text-night">Details</h1>
    </div>

    <form data-lifetime-form class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
        <label for="chat-details-lifetime" class="text-[15px] font-bold">Neue Nachrichten löschen sich nach</label>
        <select id="chat-details-lifetime" name="lifetime_days" class="o-input">
            <option value="1">1 Tag</option>
            <option value="7">7 Tagen</option>
            <option value="30">30 Tagen</option>
        </select>
        <p data-owner-hint class="m-0 text-sm text-muted">Gilt für neue Nachrichten. Ändern kann das nur, wer den Chat gestartet hat.</p>
        <button type="submit" data-owner-only class="o-btn o-btn-md o-btn-dark self-start">Speichern</button>
    </form>

    <div class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
        <h2 class="m-0 text-[21px] font-extrabold text-night">Sicherheitsnummern</h2>
        <p class="m-0 text-ink-2">
            Vergleicht die Nummer persönlich oder am Telefon. Stimmt sie bei beiden überein, liest niemand
            dazwischen mit. Ändert sie sich, hat jemand neue Schlüssel – frag nach, bevor du Vertrauliches schreibst.
        </p>
        <ul data-members class="m-0 flex list-none flex-col gap-4 p-0"></ul>
    </div>

    <form data-add-form hidden novalidate class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
        <label for="chat-details-username" class="text-[15px] font-bold">Mitglied hinzufügen</label>
        <div class="flex flex-wrap gap-3">
            <input id="chat-details-username" name="username" type="text" class="o-input min-w-0 flex-1" autocomplete="off"
                   autocapitalize="none" spellcheck="false">
            <button type="submit" class="o-btn o-btn-md o-btn-dark">Hinzufügen</button>
        </div>
        <p class="m-0 text-sm text-muted">Neue Mitglieder sehen nur Nachrichten ab jetzt. Danach bekommt der Chat einen neuen Schlüssel.</p>
    </form>

    <p data-form-error role="alert" hidden class="m-0 text-sun-text"></p>

    <template data-member>
        <li class="flex flex-col gap-1 border-b border-paper-line pb-4 last:border-b-0 last:pb-0">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span data-name class="font-bold text-night"></span>
                <button type="button" data-remove hidden class="o-link cursor-pointer border-none bg-transparent text-sm">Entfernen</button>
            </div>
            <span data-warning hidden class="text-sm font-bold text-sun-text"></span>
            <code data-number class="font-mono text-[15px] tracking-wide break-words text-ink"></code>
        </li>
    </template>
</section>
