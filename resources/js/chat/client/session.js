/**
 * Oasis chat client – in-memory session ("vault").
 *
 * What: holds everything secret or verified for the lifetime of one open tab: the unlocked identity, the conversation
 * keys per (conversation, key version) and the verified public keys of other members. Nothing here is ever written
 * to web storage, IndexedDB, cookies or caches – "nothing persisted on the device".
 *
 * `lock()` wipes every private key and conversation key; the page calls it on "Sperren" and on `pagehide`.
 *
 * Pure module apart from the crypto core; testable in node.
 */

import { wipe, wipeIdentity } from '../index.js';

export class ChatSession {
    /** @param {string} userId the signed-in account (string, as in all crypto objects) */
    constructor(userId) {
        this.userId = String(userId);
        this.identity = null;
        /** @type {Map<string, Uint8Array>} key `${conversationId}:${keyVersion}` → conversation key */
        this.conversationKeys = new Map();
        /** @type {Map<string, {encryption: Uint8Array, signing: Uint8Array, keyChangedAt: string|null}>} */
        this.publicKeys = new Map();
    }

    /** @returns {boolean} whether an identity is unlocked in this tab */
    get unlocked() {
        return this.identity !== null;
    }

    /** @param {object} identity unlocked identity from the crypto core; the session owns it from now on */
    unlock(identity) {
        this.lock();
        this.identity = identity;
    }

    /** Wipe the identity and every conversation key. Verified public keys are public and may stay. */
    lock() {
        wipeIdentity(this.identity);
        this.identity = null;
        for (const key of this.conversationKeys.values()) {
            wipe(key);
        }
        this.conversationKeys.clear();
    }

    /** @returns {Uint8Array|null} */
    conversationKey(conversationId, keyVersion) {
        return this.conversationKeys.get(`${conversationId}:${keyVersion}`) ?? null;
    }

    rememberConversationKey(conversationId, keyVersion, key) {
        const slot = `${conversationId}:${keyVersion}`;
        const previous = this.conversationKeys.get(slot);
        if (previous && previous !== key) {
            wipe(previous);
        }
        this.conversationKeys.set(slot, key);
    }

    /**
     * Remember a member's verified public keys. Returns true if a different signing key was already known in this tab,
     * i.e. the key changed while the chat was open – the UI must warn.
     *
     * @param {string} userId
     * @param {{encryption: Uint8Array, signing: Uint8Array}} keys output of `verifyPublicKeys`
     * @param {string|null} keyChangedAt server timestamp of the last key replacement
     * @returns {boolean}
     */
    rememberPublicKeys(userId, keys, keyChangedAt) {
        const previous = this.publicKeys.get(String(userId));
        const changed = previous !== undefined && !sameBytes(previous.signing, keys.signing);
        this.publicKeys.set(String(userId), { ...keys, keyChangedAt });
        return changed;
    }

    publicKeysOf(userId) {
        return this.publicKeys.get(String(userId)) ?? null;
    }
}

/** Byte comparison of public data (not constant-time; public keys are not secret). */
function sameBytes(a, b) {
    return a.length === b.length && a.every((byte, index) => byte === b[index]);
}
