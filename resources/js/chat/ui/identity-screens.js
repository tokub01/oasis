/**
 * Oasis chat UI – screens "setup" (create keys + passphrase) and "unlock" (open the stored keys in this tab).
 *
 * Markup: resources/views/app/chat/partials/setup.blade.php and unlock.blade.php.
 * The passphrase is only passed to the crypto core and the input is cleared right after; it is never sent anywhere.
 */

import { MIN_PASSPHRASE_LENGTH, setUpIdentity, unlockStoredIdentity } from '../client/keyring.js';
import { $, showError, whileBusy } from './dom.js';
import { describeError } from './errors.js';

/**
 * @param {object} ctx shared chat context (see app.js)
 * @param {() => void} onReady called once the identity is unlocked in this tab
 */
export function bindIdentityScreens(ctx, onReady) {
    bindSetup(ctx, onReady);
    bindUnlock(ctx, onReady);
}

function bindSetup(ctx, onReady) {
    const form = $(ctx.screens.setup, '[data-setup-form]');
    const error = $(form, '[data-form-error]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const passphrase = form.elements.passphrase.value;
        const confirmation = form.elements.confirmation.value;
        if ([...passphrase].length < MIN_PASSPHRASE_LENGTH) {
            showError(error, `Die Passphrase braucht mindestens ${MIN_PASSPHRASE_LENGTH} Zeichen.`);
            form.elements.passphrase.focus();
            return;
        }
        if (passphrase !== confirmation) {
            showError(error, 'Die beiden Eingaben stimmen nicht überein.');
            form.elements.confirmation.focus();
            return;
        }
        showError(error, '');
        try {
            const stored = await whileBusy(form, () => setUpIdentity(ctx.api, ctx.session, passphrase));
            form.reset();
            if (!stored) {
                return;
            }
            ctx.storedIdentity = stored;
            ctx.announce('Deine Chat-Schlüssel sind eingerichtet.');
            onReady();
        } catch (failure) {
            showError(error, describeError(failure));
        }
    });
}

function bindUnlock(ctx, onReady) {
    const screen = ctx.screens.unlock;
    const form = $(screen, '[data-unlock-form]');
    const error = $(form, '[data-form-error]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const passphrase = form.elements.passphrase.value;
        if (passphrase === '') {
            showError(error, 'Bitte gib deine Chat-Passphrase ein.');
            return;
        }
        showError(error, '');
        try {
            const unlocked = await whileBusy(form, () => unlockStoredIdentity(ctx.session, ctx.storedIdentity, passphrase));
            form.reset();
            if (!unlocked) {
                return;
            }
            ctx.announce('Nachrichten entsperrt.');
            onReady();
        } catch (failure) {
            form.elements.passphrase.select();
            showError(error, describeError(failure));
        }
    });

    // Forgotten passphrase: new keys via the setup screen. The setup form explains the consequences again.
    $(screen, '[data-reset-identity]').addEventListener('click', () => ctx.show('setup'));
}
