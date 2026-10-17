{{--
    Bot traps for a classic (non-Livewire) form, App\Support\Security\BotProtection: a honeypot field moved
    off-screen and hidden from assistive technology, plus the encrypted time stamp. Livewire forms use the honeypot of
    oasis/forms/_feedback and the time stamp of SubmitsPublicForm instead.
--}}
<div class="o-hp" aria-hidden="true">
    <label for="f-{{ \App\Support\Security\BotProtection::FIELD }}">Dieses Feld bitte leer lassen</label>
    <input id="f-{{ \App\Support\Security\BotProtection::FIELD }}" type="text" name="{{ \App\Support\Security\BotProtection::FIELD }}" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ \App\Support\Security\BotProtection::TOKEN }}" value="{{ \App\Support\Security\BotProtection::token() }}">
