/**
 * Oasis chat crypto – public entry point.
 *
 * What: re-exports the client-side end-to-end encryption core (identity keys, conversation keys, messages, errors).
 * It is a framework-free ES module library without UI, network code or persistence; the chat UI and the transport
 * (HTTP + Pusher) build on top of it. See docs/oasis-product-plan.md, "Architecture of the encrypted chat".
 *
 * Markup / DOM: none. Behaviour without JS: not applicable – the encrypted chat requires JavaScript.
 *
 * Security notes: keys only live in memory and are returned to the caller; wipe them with `wipe` / `wipeIdentity`
 * when the chat is locked. Details per module.
 */

export { getSodium, wipe } from './sodium.js';
export {
    LOCKED_IDENTITY_VERSION,
    KDF_ALGORITHM,
    createIdentity,
    wipeIdentity,
    lockIdentity,
    unlockIdentity,
    exportPublicKeys,
    verifyPublicKeys,
    fingerprint,
} from './keys.js';
export {
    MESSAGE_VERSION,
    KEY_GRANT_VERSION,
    MAX_MESSAGE_LENGTH,
    createConversationKey,
    wrapKeyFor,
    unwrapKey,
    createKeyGrant,
    openKeyGrant,
    encryptMessage,
    decryptMessage,
} from './conversation.js';
export {
    ChatCryptoError,
    WrongPassphraseError,
    TamperedMessageError,
    ExpiredMessageError,
    MessageTooLongError,
    InvalidKeyGrantError,
    InvalidPublicKeysError,
    InvalidInputError,
} from './errors.js';
