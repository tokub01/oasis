/**
 * Oasis chat crypto – error classes.
 *
 * What: typed errors thrown by the chat crypto core so the UI can react precisely (for example "wrong passphrase" vs.
 * "this message was manipulated") without parsing messages. All of them extend `ChatCryptoError`.
 *
 * Markup / DOM: none. Behaviour without JS: not applicable.
 *
 * Security notes:
 * - Error messages never contain key material, plaintext or passphrases. They may be shown to users and logged.
 * - The tamper errors deliberately do not say *which* check failed (signature, AEAD tag, encoding), so they do not act
 *   as an oracle for an attacker probing the client.
 */

/** Base class of every error raised by the chat crypto core. */
export class ChatCryptoError extends Error {
    /**
     * @param {string} message human-readable, secret-free description
     */
    constructor(message) {
        super(message);
        this.name = new.target.name;
    }
}

/**
 * The identity could not be unlocked: the passphrase is wrong, or the stored ciphertext / its public metadata were
 * changed (both cases are indistinguishable by design of authenticated encryption).
 */
export class WrongPassphraseError extends ChatCryptoError {
    constructor() {
        super('The chat passphrase is wrong or the stored key data is damaged.');
    }
}

/**
 * A message envelope failed verification: bad signature, wrong sender key, modified ciphertext, nonce or bound
 * metadata (conversation, key version, sender, expiry), unknown version, or malformed encoding.
 */
export class TamperedMessageError extends ChatCryptoError {
    constructor() {
        super('The message could not be verified and was rejected.');
    }
}

/** A correctly signed message whose `expires_at` lies in the past; it must be dropped, not shown. */
export class ExpiredMessageError extends ChatCryptoError {
    constructor() {
        super('The message has expired.');
    }
}

/** The plaintext exceeds the maximum message length (see `MAX_MESSAGE_LENGTH` in conversation.js). */
export class MessageTooLongError extends ChatCryptoError {
    /**
     * @param {number} maxLength the limit in Unicode code points
     */
    constructor(maxLength) {
        super(`The message is longer than ${maxLength} characters.`);
        this.maxLength = maxLength;
    }
}

/**
 * A key grant (wrapped conversation key) failed verification: bad signature, wrong granter key, wrong recipient,
 * or the sealed box could not be opened with the caller's identity.
 */
export class InvalidKeyGrantError extends ChatCryptoError {
    constructor() {
        super('The conversation key could not be verified and was rejected.');
    }
}

/**
 * A published public-key bundle failed verification: the encryption public key is not signed by the bundle's
 * signing key (the server may have swapped it), or the bundle is malformed.
 */
export class InvalidPublicKeysError extends ChatCryptoError {
    constructor() {
        super('The public keys could not be verified and were rejected.');
    }
}

/**
 * A caller passed an argument of the wrong type, length or range (programming error, or an obviously malformed
 * object received from the server such as a locked identity with out-of-range KDF parameters).
 */
export class InvalidInputError extends ChatCryptoError {}
