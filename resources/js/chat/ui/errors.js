/**
 * Oasis chat UI – turns errors from the API and the crypto core into short German texts for the user.
 * Technical details stay out of the UI (and out of the console in production).
 */

import { ApiError } from '../client/api.js';
import { MissingMemberKeysError, PublishedKeysMismatchError } from '../client/keyring.js';
import { MessageTooLongError, WrongPassphraseError } from '../index.js';

/**
 * @param {unknown} error
 * @returns {string}
 */
export function describeError(error) {
    if (error instanceof WrongPassphraseError) {
        return 'Die Passphrase stimmt nicht.';
    }
    if (error instanceof MessageTooLongError) {
        return 'Die Nachricht ist zu lang.';
    }
    if (error instanceof MissingMemberKeysError) {
        return `Für ${error.usernames.join(', ')} gibt es keine gültigen Chat-Schlüssel. Der Chat kann noch keinen Schlüssel bekommen.`;
    }
    if (error instanceof PublishedKeysMismatchError) {
        return 'Die veröffentlichten Schlüssel passen nicht zu deinen. Bitte melde dich bei uns – schreib vorerst nichts Vertrauliches.';
    }
    if (error instanceof ApiError) {
        switch (error.status) {
            case 401:
            case 419:
                return 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu und melde dich an.';
            case 403:
                return 'Du hast keinen Zugriff (mehr) auf diesen Chat.';
            case 404:
                return 'Diesen Chat gibt es nicht mehr.';
            case 409:
                return 'Der Chat bekommt gerade einen neuen Schlüssel. Versuch es gleich noch einmal.';
            case 422:
                return firstValidationMessage(error.body) ?? 'Die Eingabe passt nicht.';
            case 429:
                return 'Zu viele Anfragen in kurzer Zeit. Warte eine Minute.';
            default:
                return 'Da ist etwas schiefgegangen. Versuch es gleich noch einmal.';
        }
    }
    return 'Da ist etwas schiefgegangen. Versuch es gleich noch einmal.';
}

function firstValidationMessage(body) {
    const errors = body?.errors ? Object.values(body.errors).flat() : [];
    return errors[0] ?? body?.message ?? null;
}
