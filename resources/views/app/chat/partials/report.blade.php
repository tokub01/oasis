{{--
    Dialog "report": consent-based reporting. Moderation cannot read encrypted chats; a participant may forward one
    message they can read. Only this message, its sender and times reach the moderation team.
    JS: resources/js/chat/ui/report-dialog.js
--}}
<dialog data-report-dialog aria-labelledby="chat-report-title"
        class="m-auto w-[min(560px,calc(100%-2.5rem))] rounded-[24px] border-none bg-paper p-6 text-ink backdrop:bg-night/60">
    <form data-report-form method="dialog" class="flex flex-col gap-4">
        <h2 id="chat-report-title" class="m-0 text-[21px] font-extrabold text-night">Nachricht melden</h2>
        <p class="m-0 text-ink-2">
            Diese eine Nachricht geht unverschlüsselt an unser Moderationsteam, zusammen mit Absender und Uhrzeit.
            Andere Nachrichten aus dem Chat sieht dort niemand.
        </p>
        <blockquote data-quote class="m-0 max-h-48 overflow-auto rounded-2xl bg-dune px-4 py-3 whitespace-pre-wrap break-words"></blockquote>
        <div class="flex flex-col gap-1.5">
            <label for="chat-report-reason" class="text-[15px] font-bold">Was ist das Problem?</label>
            <textarea id="chat-report-reason" name="reason" rows="3" maxlength="2000" required class="o-input"></textarea>
        </div>
        <p data-form-error role="alert" hidden class="m-0 text-sun-text"></p>
        <div class="flex flex-wrap gap-3">
            <button type="submit" value="send" class="o-btn o-btn-md o-btn-dark">Melden</button>
            <button type="button" data-cancel class="o-btn o-btn-md o-btn-outline">Abbrechen</button>
        </div>
    </form>
</dialog>
