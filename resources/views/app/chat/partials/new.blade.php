{{--
    Screen "new": start a 1:1 chat or a group. Members are added by exact username (there is no member directory).
    JS: resources/js/chat/ui/new-conversation.js
--}}
<section data-screen="new" hidden aria-labelledby="chat-new-title" class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <a href="#" class="o-link self-start">Zurück zu den Nachrichten</a>
        <h1 id="chat-new-title" class="o-h2 m-0 text-night">Neuer Chat</h1>
    </div>

    <form data-new-form novalidate class="flex flex-col gap-5 rounded-[24px] bg-paper p-6">
        <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
            <legend class="mb-2 text-[15px] font-bold">Art</legend>
            <label class="flex items-center gap-2"><input type="radio" name="type" value="direct" checked> Zu zweit</label>
            <label class="flex items-center gap-2"><input type="radio" name="type" value="group"> Gruppe (bis 50 Menschen)</label>
        </fieldset>

        <div class="flex flex-col gap-1.5">
            <label for="chat-new-username" class="text-[15px] font-bold">Benutzername</label>
            <div class="flex flex-wrap gap-3">
                <input id="chat-new-username" name="username" type="text" class="o-input min-w-0 flex-1" autocomplete="off"
                       autocapitalize="none" spellcheck="false" aria-describedby="chat-new-username-hint">
                <button type="button" data-add-member class="o-btn o-btn-md o-btn-outline">Hinzufügen</button>
            </div>
            <p id="chat-new-username-hint" class="m-0 text-sm text-muted">Genau wie im Profil, ohne @. Nur wer den Chat schon eingerichtet hat, kann hinzugefügt werden.</p>
        </div>
        <ul data-selected class="m-0 flex list-none flex-wrap gap-2 p-0" aria-label="Ausgewählt"></ul>

        <div data-group-only hidden class="flex flex-col gap-1.5">
            <label for="chat-new-title-input" class="text-[15px] font-bold">Name der Gruppe <span class="font-medium text-muted">(optional)</span></label>
            <input id="chat-new-title-input" name="title" type="text" maxlength="100" class="o-input" aria-describedby="chat-new-title-hint">
            <p id="chat-new-title-hint" class="m-0 text-sm text-muted">Der Name ist nicht verschlüsselt. Schreib nichts Vertrauliches hinein.</p>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="chat-new-lifetime" class="text-[15px] font-bold">Nachrichten löschen sich nach</label>
            <select id="chat-new-lifetime" name="lifetime_days" class="o-input">
                <option value="1">1 Tag</option>
                <option value="7" selected>7 Tagen</option>
                <option value="30">30 Tagen</option>
            </select>
        </div>

        <p data-form-error role="alert" hidden class="m-0 text-sun-text"></p>
        <button type="submit" class="o-btn o-btn-md o-btn-dark self-start">Chat starten</button>
    </form>

    <template data-selected-item>
        <li class="flex items-center gap-2 rounded-full bg-dune py-1 pr-1 pl-4">
            <span data-name class="font-bold text-night"></span>
            <button type="button" data-remove class="min-h-9 min-w-9 cursor-pointer rounded-full border-none bg-transparent text-night">✕</button>
        </li>
    </template>
</section>
