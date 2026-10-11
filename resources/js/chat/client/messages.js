/**
 * Oasis chat client – sending, reading and ordering messages.
 *
 * What:
 * - `sealMessage` encrypts and signs a text for the conversation's current key version with the server-clock expiry;
 * - `openMessage` verifies and decrypts one envelope into a display item (never throws for bad input – a tampered or
 *   unreadable message becomes a visible placeholder instead of breaking the conversation);
 * - `Timeline` keeps the decrypted items of one conversation in memory, de-duplicated by signature (realtime and
 *   history deliver the same envelope; the signed envelope carries no database id).
 *
 * No DOM, no network. Plaintext only lives in these in-memory objects and is dropped when it expires.
 */

import {
    ExpiredMessageError,
    MAX_MESSAGE_BYTES,
    MAX_MESSAGE_LENGTH,
    encryptMessage,
    decryptMessage,
} from '../index.js';

/** Display states of a message item. */
export const MessageState = Object.freeze({
    READABLE: 'readable',
    /** No key for this version in this tab (e.g. sent before the caller joined, or after a key loss). */
    NO_KEY: 'no-key',
    /** Sender unknown or signature/ciphertext invalid: the server or network altered it. */
    UNVERIFIABLE: 'unverifiable',
});

/**
 * How much of the limits a draft uses, for the character counter.
 *
 * @param {string} text
 * @returns {{characters: number, bytes: number, tooLong: boolean}}
 */
export function measureDraft(text) {
    const characters = [...text].length;
    const bytes = new TextEncoder().encode(text).length;
    return { characters, bytes, tooLong: characters > MAX_MESSAGE_LENGTH || bytes > MAX_MESSAGE_BYTES };
}

/**
 * Encrypt a message for the conversation's current key version.
 *
 * @param {import('./session.js').ChatSession} session
 * @param {object} conversation Conversation from the API (`key_version`, `lifetime_days`)
 * @param {import('./clock.js').ServerClock} clock synced with `conversation.server_time`
 * @param {string} plaintext
 * @returns {Promise<object>} the envelope to POST
 */
export function sealMessage(session, conversation, clock, plaintext) {
    return encryptMessage({
        conversationKey: session.conversationKey(conversation.id, conversation.key_version),
        conversationId: conversation.id,
        keyVersion: conversation.key_version,
        senderId: session.userId,
        identity: session.identity,
        plaintext,
        expiresAt: clock.expiryFor(conversation.lifetime_days),
        now: clock.now(),
    });
}

/**
 * Verify and decrypt one envelope.
 *
 * @param {import('./session.js').ChatSession} session
 * @param {string} conversationId the conversation being shown (replays from other conversations are rejected)
 * @param {object} envelope
 * @param {number} now current server time in epoch ms
 * @returns {Promise<{signature: string, envelope: object, senderId: string, expiresAt: number, state: string,
 *                    plaintext: string|null}|null>} the item, or null if it has already expired
 */
export async function openMessage(session, conversationId, envelope, now) {
    const item = {
        signature: String(envelope?.signature ?? ''),
        envelope,
        senderId: String(envelope?.sender_id ?? ''),
        expiresAt: Date.parse(envelope?.expires_at),
        state: MessageState.UNVERIFIABLE,
        plaintext: null,
    };
    const key = session.conversationKey(conversationId, envelope?.key_version);
    const sender = session.publicKeysOf(item.senderId);
    if (!key) {
        item.state = MessageState.NO_KEY;
        return item;
    }
    if (!sender) {
        return item;
    }
    try {
        item.plaintext = await decryptMessage({
            envelope,
            conversationKey: key,
            senderSigningPublicKey: sender.signing,
            expectedConversationId: conversationId,
            now,
        });
        item.state = MessageState.READABLE;
    } catch (error) {
        if (error instanceof ExpiredMessageError) {
            return null;
        }
    }
    return item;
}

/** In-memory list of one conversation's items, oldest first. */
export class Timeline {
    constructor() {
        /** @type {object[]} */
        this.items = [];
    }

    /** @returns {boolean} whether an item with this signature is already shown */
    has(signature) {
        return this.items.some((item) => item.signature === signature);
    }

    /** Add a newly received item at the end, unless known. @returns {boolean} whether it was added */
    append(item) {
        if (!item || this.has(item.signature)) {
            return false;
        }
        this.items.push(item);
        return true;
    }

    /** Add older history items (given oldest first) before the current ones, skipping known ones. */
    prepend(items) {
        const fresh = items.filter((item) => item && !this.has(item.signature));
        this.items.unshift(...fresh);
        return fresh.length;
    }

    /**
     * Drop expired items and wipe their plaintext reference.
     *
     * @param {number} now server time in epoch ms
     * @returns {boolean} whether anything was removed
     */
    dropExpired(now) {
        const before = this.items.length;
        this.items = this.items.filter((item) => {
            const alive = !(item.expiresAt <= now);
            if (!alive) {
                item.plaintext = null;
            }
            return alive;
        });
        return this.items.length !== before;
    }

    clear() {
        for (const item of this.items) {
            item.plaintext = null;
        }
        this.items = [];
    }
}
