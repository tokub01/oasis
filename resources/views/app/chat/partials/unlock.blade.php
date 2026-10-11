{{--
    Screen "unlock": the chat is set up; the passphrase decrypts the keys in this tab only. "Passphrase vergessen"
    leads to new keys (old messages become unreadable, everyone sees a key-change warning).
    JS: resources/js/chat/ui/identity-screens.js
--}}
<section data-screen="unlock" hidden aria-labelledby="chat-unlock-title" class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <p class="o-eyebrow m-0">Nachrichten</p>
        <h1 id="chat-unlock-title" class="o-h2 m-0 text-night">Nachrichten entsperren</h1>
        <p class="o-lead m-0 text-ink-2">Gib deine Chat-Passphrase ein. Sie bleibt auf diesem Gerät.</p>
    </div>

    <form data-unlock-form novalidate class="flex flex-col gap-5 rounded-[24px] bg-paper p-6">
        <input type="text" name="username" autocomplete="username" value="{{ auth()->user()->username }}" hidden>
        <div class="flex flex-col gap-1.5">
            <label for="chat-unlock-passphrase" class="text-[15px] font-bold">Chat-Passphrase</label>
            <input id="chat-unlock-passphrase" name="passphrase" type="password" class="o-input" required
                   autocomplete="current-password">
        </div>
        <p data-form-error role="alert" hidden class="m-0 text-sun-text"></p>
        <button type="submit" class="o-btn o-btn-md o-btn-dark self-start">Entsperren</button>
        <p data-busy hidden role="status" class="m-0 text-ink-2">Wird entsperrt …</p>
    </form>

    <details class="rounded-[24px] bg-paper p-6">
        <summary class="cursor-pointer font-bold text-night">Passphrase vergessen?</summary>
        <div class="mt-4 flex flex-col gap-3 text-ink-2">
            <p class="m-0">
                Ohne Passphrase lassen sich deine bisherigen Schlüssel nicht öffnen – von niemandem. Du kannst neue
                Schlüssel erstellen. Dann gilt:
            </p>
            <ul class="m-0 flex list-disc flex-col gap-1 pl-5">
                <li>Bisherige Nachrichten kannst du nicht mehr lesen.</li>
                <li>Deine Chatpartner sehen einen Hinweis, dass sich deine Schlüssel geändert haben.</li>
            </ul>
            <button type="button" data-reset-identity class="o-btn o-btn-md o-btn-outline self-start">Neue Schlüssel erstellen</button>
        </div>
    </details>
</section>
