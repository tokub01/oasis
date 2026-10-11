{{--
    Screen "setup": first use of the chat. Creates the chat keys in the browser and locks them with a separate chat
    passphrase; only the locked keys are stored on the server. Also used after "Passphrase vergessen" (new keys).
    JS: resources/js/chat/ui/identity-screens.js
--}}
<section data-screen="setup" hidden aria-labelledby="chat-setup-title" class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <p class="o-eyebrow m-0">Nachrichten</p>
        <h1 id="chat-setup-title" class="o-h2 m-0 text-night">Deine Chat-Passphrase</h1>
        <p class="o-lead m-0 text-ink-2">
            Deine Nachrichten werden in deinem Browser verschlüsselt. Lesen können sie nur die Menschen im Chat –
            nicht Oasis, nicht unser Hosting, nicht Pusher.
        </p>
    </div>

    <ul class="m-0 flex list-disc flex-col gap-2 pl-5 text-ink-2">
        <li>Die Passphrase ist getrennt von deinem Passwort und verlässt nie dein Gerät.</li>
        <li>Du gibst sie ein, wenn du die Nachrichten öffnest. Gespeichert wird auf deinem Gerät nichts.</li>
        <li><strong class="text-night">Vergisst du sie, kann niemand deine Nachrichten wiederherstellen</strong> – auch wir nicht.</li>
    </ul>

    <form data-setup-form novalidate class="flex flex-col gap-5 rounded-[24px] bg-paper p-6">
        {{-- Hidden username field helps password managers file the passphrase under the right account. --}}
        <input type="text" name="username" autocomplete="username" value="{{ auth()->user()->username }}" hidden>
        <div class="flex flex-col gap-1.5">
            <label for="chat-setup-passphrase" class="text-[15px] font-bold">Passphrase</label>
            <input id="chat-setup-passphrase" name="passphrase" type="password" class="o-input" required
                   minlength="12" autocomplete="new-password" aria-describedby="chat-setup-passphrase-hint">
            <p id="chat-setup-passphrase-hint" class="m-0 text-sm text-muted">Mindestens 12 Zeichen. Gut merkbar sind mehrere Wörter, z. B. „Palme Regen Kompass Tee“.</p>
        </div>
        <div class="flex flex-col gap-1.5">
            <label for="chat-setup-confirmation" class="text-[15px] font-bold">Passphrase wiederholen</label>
            <input id="chat-setup-confirmation" name="confirmation" type="password" class="o-input" required
                   autocomplete="new-password">
        </div>
        <p data-form-error role="alert" hidden class="m-0 text-sun-text"></p>
        <button type="submit" class="o-btn o-btn-md o-btn-dark self-start">Schlüssel erstellen</button>
        <p data-busy hidden role="status" class="m-0 text-ink-2">Schlüssel werden erstellt. Das dauert ein paar Sekunden …</p>
    </form>
</section>
