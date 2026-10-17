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
            Melden ist ausdrücklich erwünscht – auch wenn du dir nicht sicher bist. Diese eine Nachricht geht an unser
            Moderationsteam, zusammen mit Absender und Uhrzeit; andere Nachrichten aus dem Chat sieht dort niemand.
            Wir sagen der Person nicht, wer gemeldet hat.
        </p>
        <blockquote data-quote class="m-0 max-h-48 overflow-auto rounded-2xl bg-dune px-4 py-3 whitespace-pre-wrap break-words"></blockquote>
        <fieldset class="m-0 flex flex-col gap-1.5 border-0 p-0">
            <legend class="mb-1 text-[15px] font-bold">Was ist das Problem?</legend>
            @foreach (\App\Enums\Moderation\ReportCategory::cases() as $category)
                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-dune-line px-3 py-1.5 text-[15px] has-[:checked]:border-night has-[:checked]:bg-dune">
                    <input type="radio" name="category" value="{{ $category->label() }}" class="accent-night">
                    <span>{{ $category->label() }}</span>
                </label>
            @endforeach
        </fieldset>
        <div class="flex flex-col gap-1.5">
            <label for="chat-report-reason" class="text-[15px] font-bold">Möchtest du noch etwas dazu sagen? <span class="font-normal text-muted">(freiwillig)</span></label>
            <textarea id="chat-report-reason" name="reason" rows="3" maxlength="1800" class="o-input"></textarea>
        </div>
        <p data-form-error role="alert" hidden class="m-0 text-sun-text"></p>
        <div class="flex flex-wrap gap-3">
            <button type="submit" value="send" class="o-btn o-btn-md o-btn-dark">Melden</button>
            <button type="button" data-cancel class="o-btn o-btn-md o-btn-outline">Abbrechen</button>
        </div>
    </form>
    {{-- After sending: thanks, block offer and help contacts. --}}
    <div data-report-thanks hidden class="flex flex-col gap-4">
        <h2 class="m-0 text-[21px] font-extrabold text-night">Danke, dass du das meldest.</h2>
        <p class="m-0 text-ink-2">Du hast richtig gehandelt. Unser Moderationsteam schaut sich das an. Die Person erfährt nicht, wer gemeldet hat.</p>
        <p data-report-blocked role="status" hidden class="m-0 font-bold text-spring-text">Blockiert. Ihr könnt euch keine Nachrichten mehr schicken.</p>
        <div class="flex flex-wrap gap-3">
            <button type="button" data-report-block class="o-btn o-btn-md o-btn-dark">Person blockieren</button>
            <button type="button" data-report-done class="o-btn o-btn-md o-btn-outline">Schließen</button>
        </div>
        <x-oasis.support-contacts />
    </div>
</dialog>
