/**
 * Oasis chat crypto – shared encoding helpers (internal).
 *
 * What: Base64 and UTF-8 conversion plus the canonical, unambiguous byte encoding used for authenticated data and
 * signatures. Kept in one module so every other module encodes identically (one Base64 variant everywhere).
 *
 * Base64 variant: URL-safe without padding (`sodium.base64_variants.URLSAFE_NO_PADDING`) – safe in URLs, JSON and
 * HTML attributes without escaping.
 *
 * Markup / DOM: none. Behaviour without JS: not applicable.
 *
 * Security notes:
 * - `canonicalBytes` prefixes every field with its 4-byte big-endian length, so two different field lists can never
 *   produce the same bytes (no "ab"+"c" == "a"+"bc" ambiguity), and starts with a domain-separation label so a
 *   signature made for one purpose (message, key grant, key binding) can never be replayed for another.
 */

import { InvalidInputError } from './errors.js';

const textEncoder = new TextEncoder();
const textDecoder = new TextDecoder('utf-8', { fatal: true });

/**
 * Encode bytes as URL-safe Base64 without padding.
 *
 * @param {typeof import('libsodium-wrappers-sumo').default} sodium ready libsodium instance
 * @param {Uint8Array} bytes
 * @returns {string}
 */
export function toBase64(sodium, bytes) {
    return sodium.to_base64(bytes, sodium.base64_variants.URLSAFE_NO_PADDING);
}

/**
 * Decode URL-safe, unpadded Base64.
 *
 * @param {typeof import('libsodium-wrappers-sumo').default} sodium ready libsodium instance
 * @param {unknown} text value expected to be a Base64 string
 * @param {number} [expectedLength] if given, the decoded length must match exactly
 * @returns {Uint8Array}
 * @throws {InvalidInputError} if `text` is not a string, not valid Base64, or has the wrong length
 */
export function fromBase64(sodium, text, expectedLength) {
    if (typeof text !== 'string' || text.length === 0) {
        throw new InvalidInputError('Expected a non-empty Base64 string.');
    }
    let bytes;
    try {
        bytes = sodium.from_base64(text, sodium.base64_variants.URLSAFE_NO_PADDING);
    } catch {
        throw new InvalidInputError('Invalid Base64 encoding.');
    }
    if (expectedLength !== undefined && bytes.length !== expectedLength) {
        throw new InvalidInputError('Decoded value has the wrong length.');
    }
    return bytes;
}

/**
 * Encode a string as UTF-8 bytes.
 *
 * @param {string} text
 * @returns {Uint8Array}
 */
export function utf8Encode(text) {
    return textEncoder.encode(text);
}

/**
 * Decode UTF-8 bytes; invalid sequences throw instead of being replaced.
 *
 * @param {Uint8Array} bytes
 * @returns {string}
 * @throws {TypeError} on invalid UTF-8
 */
export function utf8Decode(bytes) {
    return textDecoder.decode(bytes);
}

/**
 * Build the canonical byte string `label || len(f1) || f1 || len(f2) || f2 ...`.
 *
 * @param {string} label domain-separation label, e.g. 'oasis-chat/message-ad/v1'
 * @param {Array<Uint8Array | string>} fields byte fields, or strings (encoded as UTF-8)
 * @returns {Uint8Array}
 */
export function canonicalBytes(label, fields) {
    const parts = [label, ...fields].map((field) => (typeof field === 'string' ? utf8Encode(field) : field));
    const total = parts.reduce((sum, part) => sum + 4 + part.length, 0);
    const out = new Uint8Array(total);
    const view = new DataView(out.buffer);
    let offset = 0;
    for (const part of parts) {
        view.setUint32(offset, part.length, false);
        offset += 4;
        out.set(part, offset);
        offset += part.length;
    }
    return out;
}

/**
 * Assert that a value is a `Uint8Array` of exactly the given length.
 *
 * @param {unknown} value
 * @param {number} length
 * @param {string} name argument name for the error message (never the value itself)
 * @returns {Uint8Array}
 * @throws {InvalidInputError}
 */
export function requireBytes(value, length, name) {
    if (!(value instanceof Uint8Array) || value.length !== length) {
        throw new InvalidInputError(`${name} must be a Uint8Array of ${length} bytes.`);
    }
    return value;
}

/**
 * Normalise an identifier (conversation, user) to a non-empty string so numeric and string ids sign identically.
 *
 * @param {unknown} value string or safe integer id
 * @param {string} name argument name for the error message
 * @returns {string}
 * @throws {InvalidInputError}
 */
export function normaliseId(value, name) {
    if (typeof value === 'number' && Number.isSafeInteger(value)) {
        return String(value);
    }
    if (typeof value === 'string' && value.length > 0 && value.length <= 255) {
        return value;
    }
    throw new InvalidInputError(`${name} must be a non-empty string or a safe integer.`);
}

/**
 * Validate a key version (non-negative safe integer).
 *
 * @param {unknown} value
 * @returns {number}
 * @throws {InvalidInputError}
 */
export function normaliseKeyVersion(value) {
    if (typeof value !== 'number' || !Number.isSafeInteger(value) || value < 0) {
        throw new InvalidInputError('keyVersion must be a non-negative safe integer.');
    }
    return value;
}
