/**
 * Oasis chat crypto – conversation keys and messages (1:1 and groups).
 *
 * What: every conversation has a random 32-byte symmetric key (XChaCha20-Poly1305). It is wrapped with a sealed box
 * for each member's X25519 public key (the server stores only the wrapped copies), and each message is encrypted with
 * it and signed with the sender's Ed25519 key. When membership changes, the caller creates a new key with a higher
 * `keyVersion` and wraps it for the remaining members only.
 *
 * Message envelope (JSON-serialisable, what the server stores and relays; Base64 = URL-safe, no padding):
 *   {
 *     v: 1,
 *     conversation_id: string,          // ids are normalised to strings
 *     key_version: number,               // non-negative integer
 *     sender_id: string,
 *     nonce: base64 (24 bytes),
 *     ciphertext: base64,                // XChaCha20-Poly1305 output incl. 16-byte tag
 *     signature: base64 (64 bytes),      // Ed25519, detached
 *     expires_at: string | null,         // ISO 8601 UTC, whole seconds, e.g. "2026-10-18T12:00:00Z"
 *   }
 *
 * Markup / DOM: none. Behaviour without JS: not applicable.
 *
 * Security notes:
 * - Associated data binds v, conversation_id, key_version, sender_id and expires_at (as Unix seconds), so the server
 *   cannot move a message to another conversation, relabel its sender or key version, or change its expiry without
 *   decryption failing. Binding the expiry as an instant (not as the literal string) tolerates the server re-
 *   formatting the timestamp (e.g. Laravel's "….000000Z"), but it must keep the same instant.
 * - The signature covers nonce, ciphertext and the associated data, and is verified before anything is decrypted.
 *   It proves which member wrote the message (the AEAD key alone is shared by all members, so it cannot).
 * - `decryptMessage` reports every verification failure as the same `TamperedMessageError` (no oracle).
 * - A random 192-bit nonce per message makes nonce reuse under one key practically impossible.
 * - `wrapKeyFor` / `unwrapKey` are the raw sealed-box primitives. A sealed box is anonymous: anyone, including the
 *   server, can seal a key of its own choosing for a member. Use `createKeyGrant` / `openKeyGrant`, which add a
 *   signature by the granting member, whenever keys are exchanged through the server.
 * - No forward secrecy per message: whoever obtains a conversation key (and the stored ciphertexts) can read every
 *   message of that key version. Rotation happens on membership changes, not per message.
 */

import { getSodium, wipe } from './sodium.js';
import {
    canonicalBytes,
    fromBase64,
    normaliseId,
    normaliseKeyVersion,
    requireBytes,
    toBase64,
    utf8Decode,
    utf8Encode,
} from './encoding.js';
import {
    ExpiredMessageError,
    InvalidInputError,
    InvalidKeyGrantError,
    MessageTooLongError,
    TamperedMessageError,
} from './errors.js';
import { assertIdentity } from './keys.js';

/** Envelope format version produced by `encryptMessage`. */
export const MESSAGE_VERSION = 1;

/** Key-grant format version produced by `createKeyGrant`. */
export const KEY_GRANT_VERSION = 1;

/** Maximum plaintext length in Unicode code points (e.g. a single emoji like 🌴 counts as 1; composed emoji count once per code point). */
export const MAX_MESSAGE_LENGTH = 4000;

/**
 * Maximum plaintext size in UTF-8 bytes. The envelope travels as one Pusher event (10 KiB limit); with the AEAD tag,
 * Base64 and the JSON fields, 5984 plaintext bytes keep it below that limit (docs/oasis-chat.md). Emoji-heavy text
 * hits this byte limit before the character limit.
 */
export const MAX_MESSAGE_BYTES = 5984;

const KEY_BYTES = 32;
const X25519_PUBLIC_BYTES = 32;
const ED25519_PUBLIC_BYTES = 32;

const MESSAGE_AD_LABEL = 'oasis-chat/message-ad/v1';
const MESSAGE_SIGNATURE_LABEL = 'oasis-chat/message-signature/v1';
const KEY_GRANT_LABEL = 'oasis-chat/key-grant/v1';

const ISO_TIMESTAMP = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/;

/**
 * Create a new random conversation key.
 *
 * @returns {Promise<Uint8Array>} 32 random bytes; the caller owns it and should `wipe` it when done
 */
export async function createConversationKey() {
    const sodium = await getSodium();
    return sodium.crypto_aead_xchacha20poly1305_ietf_keygen();
}

/**
 * Wrap (seal) a conversation key for one recipient's X25519 public key. Anonymous – see module security notes.
 *
 * @param {Uint8Array} conversationKey 32-byte conversation key
 * @param {Uint8Array} recipientEncryptionPublicKey recipient's X25519 public key (from a verified bundle,
 *        see `verifyPublicKeys` in keys.js)
 * @returns {Promise<string>} the sealed box, Base64
 * @throws {InvalidInputError} on wrong key types/lengths
 */
export async function wrapKeyFor(conversationKey, recipientEncryptionPublicKey) {
    const sodium = await getSodium();
    requireBytes(conversationKey, KEY_BYTES, 'conversationKey');
    requireBytes(recipientEncryptionPublicKey, X25519_PUBLIC_BYTES, 'recipientEncryptionPublicKey');
    return toBase64(sodium, sodium.crypto_box_seal(conversationKey, recipientEncryptionPublicKey));
}

/**
 * Open a sealed conversation key with the caller's identity.
 *
 * @param {string} wrapped sealed box from `wrapKeyFor`, Base64
 * @param {object} identity the caller's unlocked identity
 * @returns {Promise<Uint8Array>} the 32-byte conversation key; the caller should `wipe` it when done
 * @throws {InvalidKeyGrantError} if the box cannot be opened with this identity or is malformed
 */
export async function unwrapKey(wrapped, identity) {
    const sodium = await getSodium();
    assertIdentity(identity);
    let key;
    try {
        key = sodium.crypto_box_seal_open(
            fromBase64(sodium, wrapped, sodium.crypto_box_SEALBYTES + KEY_BYTES),
            identity.encryptionKeyPair.publicKey,
            identity.encryptionKeyPair.privateKey,
        );
    } catch {
        throw new InvalidKeyGrantError();
    }
    if (key.length !== KEY_BYTES) {
        wipe(key);
        throw new InvalidKeyGrantError();
    }
    return key;
}

/**
 * Wrap a conversation key for one member and sign the result (authenticated key distribution).
 *
 * The signature covers conversation id, key version, recipient id, granter id, the recipient's encryption public key
 * and the sealed box, so the server can neither inject its own key nor reassign a grant to another conversation,
 * version or member.
 *
 * Returned grant: { v: 1, conversation_id, key_version, recipient_id, granter_id, wrapped_key: base64,
 * signature: base64 }.
 *
 * @param {object} params
 * @param {Uint8Array} params.conversationKey 32-byte key to distribute
 * @param {string|number} params.conversationId
 * @param {number} params.keyVersion
 * @param {string|number} params.recipientId member receiving the key
 * @param {Uint8Array} params.recipientEncryptionPublicKey recipient's verified X25519 public key
 * @param {string|number} params.granterId the member creating the grant (the caller)
 * @param {object} params.identity the granter's unlocked identity (signs the grant)
 * @returns {Promise<object>} the key grant
 * @throws {InvalidInputError} on invalid arguments
 */
export async function createKeyGrant({
    conversationKey,
    conversationId,
    keyVersion,
    recipientId,
    recipientEncryptionPublicKey,
    granterId,
    identity,
}) {
    const sodium = await getSodium();
    assertIdentity(identity);
    const grant = {
        v: KEY_GRANT_VERSION,
        conversation_id: normaliseId(conversationId, 'conversationId'),
        key_version: normaliseKeyVersion(keyVersion),
        recipient_id: normaliseId(recipientId, 'recipientId'),
        granter_id: normaliseId(granterId, 'granterId'),
        wrapped_key: await wrapKeyFor(conversationKey, recipientEncryptionPublicKey),
    };
    const signed = keyGrantSignedBytes(grant, recipientEncryptionPublicKey, fromBase64(sodium, grant.wrapped_key));
    return { ...grant, signature: toBase64(sodium, sodium.crypto_sign_detached(signed, identity.signingKeyPair.privateKey)) };
}

/**
 * Verify a key grant addressed to the caller and unwrap the conversation key.
 *
 * Security note: this proves the grant was made by the holder of `granterSigningPublicKey`. Whether that person is
 * allowed to hand out keys for this conversation (i.e. is a member) is the caller's policy decision; the verified
 * `conversationId` / `keyVersion` / `granterId` are returned for exactly that check.
 *
 * @param {object} params
 * @param {object} params.grant grant from `createKeyGrant` (as delivered by the server)
 * @param {object} params.identity the recipient's unlocked identity
 * @param {Uint8Array} params.granterSigningPublicKey Ed25519 public key of the member named in `granter_id`
 * @returns {Promise<{conversationKey: Uint8Array, conversationId: string, keyVersion: number, granterId: string,
 *                    recipientId: string}>}
 * @throws {InvalidKeyGrantError} on any verification or decoding failure
 */
export async function openKeyGrant({ grant, identity, granterSigningPublicKey }) {
    const sodium = await getSodium();
    assertIdentity(identity);
    let verified;
    try {
        requireBytes(granterSigningPublicKey, ED25519_PUBLIC_BYTES, 'granterSigningPublicKey');
        if (grant === null || typeof grant !== 'object' || grant.v !== KEY_GRANT_VERSION) {
            throw new InvalidKeyGrantError();
        }
        verified = {
            v: grant.v,
            conversation_id: normaliseId(grant.conversation_id, 'conversation_id'),
            key_version: normaliseKeyVersion(grant.key_version),
            recipient_id: normaliseId(grant.recipient_id, 'recipient_id'),
            granter_id: normaliseId(grant.granter_id, 'granter_id'),
        };
        const sealed = fromBase64(sodium, grant.wrapped_key, sodium.crypto_box_SEALBYTES + KEY_BYTES);
        const signature = fromBase64(sodium, grant.signature, sodium.crypto_sign_BYTES);
        const signed = keyGrantSignedBytes(verified, identity.encryptionKeyPair.publicKey, sealed);
        if (!sodium.crypto_sign_verify_detached(signature, signed, granterSigningPublicKey)) {
            throw new InvalidKeyGrantError();
        }
    } catch {
        throw new InvalidKeyGrantError();
    }
    const conversationKey = await unwrapKey(grant.wrapped_key, identity);
    return {
        conversationKey,
        conversationId: verified.conversation_id,
        keyVersion: verified.key_version,
        granterId: verified.granter_id,
        recipientId: verified.recipient_id,
    };
}

/**
 * Encrypt and sign a text message.
 *
 * @param {object} params
 * @param {Uint8Array} params.conversationKey 32-byte key of `keyVersion`
 * @param {string|number} params.conversationId
 * @param {number} params.keyVersion version of `conversationKey`
 * @param {string|number} params.senderId the caller's user id
 * @param {object} params.identity the sender's unlocked identity (signs the message)
 * @param {string} params.plaintext UTF-8 text, at most MAX_MESSAGE_LENGTH code points and MAX_MESSAGE_BYTES bytes
 * @param {Date|string|number} params.expiresAt when the message self-deletes (Date, ISO string or epoch ms); rounded
 *        down to whole seconds. Required: every Oasis message self-deletes, and the API rejects envelopes without it.
 * @param {Date|number} [params.now] current time (Date or epoch ms), e.g. the server time to compensate client clock
 *        skew; defaults to `Date.now()`
 * @returns {Promise<object>} the envelope (see module header)
 * @throws {MessageTooLongError} if the plaintext is too long
 * @throws {InvalidInputError} on invalid arguments, a missing expiry, or an expiry that is not in the future
 */
export async function encryptMessage({
    conversationKey,
    conversationId,
    keyVersion,
    senderId,
    identity,
    plaintext,
    expiresAt,
    now = Date.now(),
}) {
    const sodium = await getSodium();
    requireBytes(conversationKey, KEY_BYTES, 'conversationKey');
    assertIdentity(identity);
    if (typeof plaintext !== 'string') {
        throw new InvalidInputError('plaintext must be a string.');
    }
    if (countCharacters(plaintext) > MAX_MESSAGE_LENGTH || utf8Encode(plaintext).length > MAX_MESSAGE_BYTES) {
        throw new MessageTooLongError(MAX_MESSAGE_LENGTH);
    }
    if (expiresAt === null || expiresAt === undefined) {
        throw new InvalidInputError('expiresAt is required: every message self-deletes.');
    }
    const expirySeconds = normaliseExpiry(expiresAt);
    const nowMs = now instanceof Date ? now.getTime() : now;
    if (expirySeconds * 1000 <= nowMs) {
        throw new InvalidInputError('expiresAt must lie in the future.');
    }

    const header = {
        v: MESSAGE_VERSION,
        conversation_id: normaliseId(conversationId, 'conversationId'),
        key_version: normaliseKeyVersion(keyVersion),
        sender_id: normaliseId(senderId, 'senderId'),
        expires_at: expirySeconds === null ? null : new Date(expirySeconds * 1000).toISOString().replace('.000Z', 'Z'),
    };
    const associatedData = messageAssociatedData(header, expirySeconds);
    const nonce = sodium.randombytes_buf(sodium.crypto_aead_xchacha20poly1305_ietf_NPUBBYTES);
    const plaintextBytes = utf8Encode(plaintext);
    try {
        const ciphertext = sodium.crypto_aead_xchacha20poly1305_ietf_encrypt(
            plaintextBytes,
            associatedData,
            null,
            nonce,
            conversationKey,
        );
        const signature = sodium.crypto_sign_detached(
            canonicalBytes(MESSAGE_SIGNATURE_LABEL, [nonce, ciphertext, associatedData]),
            identity.signingKeyPair.privateKey,
        );
        return {
            v: header.v,
            conversation_id: header.conversation_id,
            key_version: header.key_version,
            sender_id: header.sender_id,
            nonce: toBase64(sodium, nonce),
            ciphertext: toBase64(sodium, ciphertext),
            signature: toBase64(sodium, signature),
            expires_at: header.expires_at,
        };
    } finally {
        wipe(plaintextBytes);
    }
}

/**
 * Verify and decrypt a message envelope.
 *
 * Order: decode → verify the sender's signature → check expiry → AEAD-decrypt → strict UTF-8 decode.
 * The caller chooses `conversationKey` by the conversation and `key_version` it expects, and
 * `senderSigningPublicKey` by `envelope.sender_id` (from its verified key directory).
 *
 * @param {object} params
 * @param {object} params.envelope envelope from `encryptMessage` (as delivered by the server)
 * @param {Uint8Array} params.conversationKey 32-byte key matching `envelope.key_version`
 * @param {Uint8Array} params.senderSigningPublicKey Ed25519 public key of `envelope.sender_id`
 * @param {Date|number} [params.now] current time (Date or epoch ms); injectable for tests, defaults to `Date.now()`
 * @param {string|number} [params.expectedConversationId] the conversation the caller is showing; an authentic
 *        envelope of another conversation (e.g. replayed by the server into this one) is rejected. Pass it always
 *        when rendering a conversation.
 * @param {boolean} [params.withMetadata] return `{ plaintext, conversationId, keyVersion, senderId, expiresAt }`
 *        (all verified) instead of the plain string
 * @returns {Promise<string|object>} the plaintext, or the verified message with metadata
 * @throws {TamperedMessageError} on any verification or decoding failure (incl. wrong key / wrong sender key, or a
 *         conversation other than `expectedConversationId`)
 * @throws {ExpiredMessageError} if the authentic `expires_at` is not in the future
 */
export async function decryptMessage({
    envelope,
    conversationKey,
    senderSigningPublicKey,
    now = Date.now(),
    expectedConversationId,
    withMetadata = false,
}) {
    const sodium = await getSodium();
    requireBytes(conversationKey, KEY_BYTES, 'conversationKey');
    requireBytes(senderSigningPublicKey, ED25519_PUBLIC_BYTES, 'senderSigningPublicKey');

    let parsed;
    try {
        parsed = parseEnvelope(sodium, envelope);
    } catch {
        throw new TamperedMessageError();
    }
    const signedBytes = canonicalBytes(MESSAGE_SIGNATURE_LABEL, [parsed.nonce, parsed.ciphertext, parsed.associatedData]);
    if (!sodium.crypto_sign_verify_detached(parsed.signature, signedBytes, senderSigningPublicKey)) {
        throw new TamperedMessageError();
    }

    // The ids below are covered by the signature and the AEAD associated data, so they are authentic from here on.
    const verified = {
        conversationId: normaliseId(envelope.conversation_id, 'conversation_id'),
        keyVersion: normaliseKeyVersion(envelope.key_version),
        senderId: normaliseId(envelope.sender_id, 'sender_id'),
        expiresAt: envelope.expires_at ?? null,
    };
    if (
        expectedConversationId !== undefined &&
        verified.conversationId !== normaliseId(expectedConversationId, 'expectedConversationId')
    ) {
        throw new TamperedMessageError();
    }

    const nowMs = now instanceof Date ? now.getTime() : now;
    if (parsed.expirySeconds !== null && parsed.expirySeconds * 1000 <= nowMs) {
        throw new ExpiredMessageError();
    }

    let plaintextBytes = null;
    try {
        plaintextBytes = sodium.crypto_aead_xchacha20poly1305_ietf_decrypt(
            null,
            parsed.ciphertext,
            parsed.associatedData,
            parsed.nonce,
            conversationKey,
        );
        const plaintext = utf8Decode(plaintextBytes);
        return withMetadata ? { plaintext, ...verified } : plaintext;
    } catch {
        throw new TamperedMessageError();
    } finally {
        wipe(plaintextBytes);
    }
}

/**
 * Validate and decode an envelope; recompute its associated data from the (untrusted) header fields.
 *
 * @param {object} sodium ready libsodium
 * @param {unknown} envelope
 * @returns {{nonce: Uint8Array, ciphertext: Uint8Array, signature: Uint8Array, associatedData: Uint8Array,
 *            expirySeconds: number|null}}
 * @throws {Error} on any structural problem (mapped to TamperedMessageError by the caller)
 */
function parseEnvelope(sodium, envelope) {
    if (envelope === null || typeof envelope !== 'object' || envelope.v !== MESSAGE_VERSION) {
        throw new InvalidInputError('Unsupported envelope.');
    }
    const expirySeconds = parseEnvelopeExpiry(envelope.expires_at);
    const header = {
        v: envelope.v,
        conversation_id: normaliseId(envelope.conversation_id, 'conversation_id'),
        key_version: normaliseKeyVersion(envelope.key_version),
        sender_id: normaliseId(envelope.sender_id, 'sender_id'),
    };
    return {
        nonce: fromBase64(sodium, envelope.nonce, sodium.crypto_aead_xchacha20poly1305_ietf_NPUBBYTES),
        ciphertext: fromBase64(sodium, envelope.ciphertext),
        signature: fromBase64(sodium, envelope.signature, sodium.crypto_sign_BYTES),
        associatedData: messageAssociatedData(header, expirySeconds),
        expirySeconds,
    };
}

/**
 * Associated data of a message: version, conversation, key version, sender and expiry (Unix seconds or empty).
 *
 * @param {{v: number, conversation_id: string, key_version: number, sender_id: string}} header
 * @param {number|null} expirySeconds
 * @returns {Uint8Array}
 */
function messageAssociatedData(header, expirySeconds) {
    return canonicalBytes(MESSAGE_AD_LABEL, [
        String(header.v),
        header.conversation_id,
        String(header.key_version),
        header.sender_id,
        expirySeconds === null ? '' : String(expirySeconds),
    ]);
}

/**
 * Bytes signed by a key grant.
 *
 * @param {{v: number, conversation_id: string, key_version: number, recipient_id: string, granter_id: string}} grant
 * @param {Uint8Array} recipientEncryptionPublicKey
 * @param {Uint8Array} sealedKey raw sealed box
 * @returns {Uint8Array}
 */
function keyGrantSignedBytes(grant, recipientEncryptionPublicKey, sealedKey) {
    return canonicalBytes(KEY_GRANT_LABEL, [
        String(grant.v),
        grant.conversation_id,
        String(grant.key_version),
        grant.recipient_id,
        grant.granter_id,
        recipientEncryptionPublicKey,
        sealedKey,
    ]);
}

/**
 * Convert the caller's `expiresAt` to whole Unix seconds (rounded down).
 *
 * @param {Date|string|number|null|undefined} value
 * @returns {number|null}
 * @throws {InvalidInputError} if the value is not a valid point in time
 */
function normaliseExpiry(value) {
    if (value === null || value === undefined) {
        return null;
    }
    let ms;
    if (value instanceof Date) {
        ms = value.getTime();
    } else if (typeof value === 'number') {
        ms = value;
    } else if (typeof value === 'string' && ISO_TIMESTAMP.test(value)) {
        ms = Date.parse(value);
    } else {
        ms = Number.NaN;
    }
    if (!Number.isFinite(ms)) {
        throw new InvalidInputError('expiresAt must be a Date, an ISO 8601 string or epoch milliseconds.');
    }
    return Math.floor(ms / 1000);
}

/**
 * Parse `expires_at` from a received envelope. Must be null/absent or an ISO 8601 timestamp of a whole second.
 *
 * @param {unknown} value
 * @returns {number|null} Unix seconds
 * @throws {InvalidInputError} on any other value
 */
function parseEnvelopeExpiry(value) {
    if (value === null || value === undefined) {
        return null;
    }
    if (typeof value !== 'string' || !ISO_TIMESTAMP.test(value)) {
        throw new InvalidInputError('Invalid expires_at.');
    }
    const ms = Date.parse(value);
    if (!Number.isFinite(ms) || ms % 1000 !== 0) {
        throw new InvalidInputError('Invalid expires_at.');
    }
    return ms / 1000;
}

/**
 * Count Unicode code points (an emoji built from one code point counts once; combined emoji count per part).
 *
 * @param {string} text
 * @returns {number}
 */
function countCharacters(text) {
    let count = 0;
    // eslint-disable-next-line no-unused-vars
    for (const _ of text) {
        count += 1;
        if (count > MAX_MESSAGE_LENGTH) {
            break;
        }
    }
    return count;
}
