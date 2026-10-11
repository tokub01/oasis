/**
 * Oasis chat crypto – identity keys.
 *
 * What: creates a user's chat identity (an X25519 key pair for receiving wrapped conversation keys and an Ed25519 key
 * pair for signing), locks it with the chat passphrase so only ciphertext is stored on the server, unlocks it again,
 * publishes signed public keys, and computes the safety number two people compare to detect a swapped key.
 *
 * Identity object (in memory only, never persisted by this library):
 *   {
 *     encryptionKeyPair: { publicKey: Uint8Array(32), privateKey: Uint8Array(32) },  // X25519
 *     signingKeyPair:    { publicKey: Uint8Array(32), privateKey: Uint8Array(64) },  // Ed25519
 *   }
 *
 * Markup / DOM: none. Behaviour without JS: not applicable.
 *
 * Security notes:
 * - Passphrase → key: Argon2id (`crypto_pwhash`, ALG_ARGON2ID13) with OPSLIMIT_MODERATE (3 passes) and
 *   MEMLIMIT_MODERATE (256 MiB) in production. Rationale: the locked identity is stored on the server, so the
 *   relevant attacker is someone with a copy of that blob doing an offline guessing attack (server compromise,
 *   database leak). INTERACTIVE (64 MiB) is meant for frequent logins and gives ~4x less resistance; SENSITIVE (1 GiB)
 *   risks out-of-memory failures in mobile browser tabs and takes several seconds. Unlocking happens once per tab
 *   session, so ~1 s at 256 MiB is acceptable. The `kdfOptions` parameter exists only so tests can run fast.
 * - The private keys are encrypted with XChaCha20-Poly1305 (AEAD, random 192-bit nonce). The format version, KDF
 *   parameters, salt and both public keys are bound as associated data, so the server cannot pair the ciphertext
 *   with other public keys or parameters without unlocking failing.
 * - After unlocking, the public keys are re-derived from the private keys and compared in constant time with the
 *   stored ones, as an additional consistency check.
 * - The passphrase is Unicode-normalised (NFC) so the same passphrase typed on different systems derives the same key.
 * - Temporary secrets (derived key, serialised private keys, passphrase bytes) are wiped after use. The passphrase
 *   string itself cannot be wiped (JS strings are immutable).
 */

import { getSodium, wipe } from './sodium.js';
import { canonicalBytes, fromBase64, requireBytes, toBase64, utf8Encode } from './encoding.js';
import { InvalidInputError, InvalidPublicKeysError, WrongPassphraseError } from './errors.js';

/** Version of the locked-identity format produced by `lockIdentity`. */
export const LOCKED_IDENTITY_VERSION = 1;

/** KDF algorithm label stored in the locked identity. Only Argon2id v1.3 is accepted. */
export const KDF_ALGORITHM = 'argon2id13';

const X25519_PUBLIC_BYTES = 32;
const X25519_SECRET_BYTES = 32;
const ED25519_PUBLIC_BYTES = 32;
const ED25519_SECRET_BYTES = 64;
const SERIALISED_SECRET_BYTES = X25519_SECRET_BYTES + ED25519_SECRET_BYTES;

const IDENTITY_AD_LABEL = 'oasis-chat/locked-identity/v1';
const KEY_BINDING_LABEL = 'oasis-chat/key-binding/v1';
const SAFETY_NUMBER_LABEL = 'oasis-chat/safety-number/v1';

/**
 * Create a fresh identity with random keys.
 *
 * @returns {Promise<{encryptionKeyPair: {publicKey: Uint8Array, privateKey: Uint8Array},
 *                    signingKeyPair: {publicKey: Uint8Array, privateKey: Uint8Array}}>}
 *          the new identity; the caller owns it and should `wipeIdentity` it when done
 */
export async function createIdentity() {
    const sodium = await getSodium();
    const box = sodium.crypto_box_keypair();
    const sign = sodium.crypto_sign_keypair();
    return {
        encryptionKeyPair: { publicKey: box.publicKey, privateKey: box.privateKey },
        signingKeyPair: { publicKey: sign.publicKey, privateKey: sign.privateKey },
    };
}

/**
 * Overwrite both private keys of an identity with zeros (e.g. when the tab is locked or closed).
 *
 * @param {{encryptionKeyPair?: {privateKey?: Uint8Array}, signingKeyPair?: {privateKey?: Uint8Array}} | null} identity
 * @returns {void}
 */
export function wipeIdentity(identity) {
    wipe(identity?.encryptionKeyPair?.privateKey);
    wipe(identity?.signingKeyPair?.privateKey);
}

/**
 * Encrypt the identity's private keys with a key derived from the chat passphrase.
 *
 * The returned object is JSON-serialisable and is what gets stored on the server:
 *   {
 *     version: 1,
 *     kdf: { alg: 'argon2id13', opslimit: number, memlimit: number (bytes), salt: base64 },
 *     nonce: base64 (24 bytes), ciphertext: base64,
 *     publicKeys: { encryption: base64, signing: base64 },
 *   }
 * All Base64 is URL-safe without padding.
 *
 * @param {object} identity identity as returned by `createIdentity` / `unlockIdentity`
 * @param {string} passphrase the chat passphrase (non-empty; strength policy is enforced by the UI)
 * @param {{opslimit?: number, memlimit?: number}} [kdfOptions] Argon2id cost override – FOR TESTS ONLY. Production
 *        must omit it and get OPSLIMIT_MODERATE / MEMLIMIT_MODERATE (see module header for the reasoning).
 * @returns {Promise<object>} the locked identity
 * @throws {InvalidInputError} on malformed identity, empty passphrase or out-of-range KDF options
 */
export async function lockIdentity(identity, passphrase, kdfOptions = {}) {
    const sodium = await getSodium();
    assertIdentity(identity);
    const opslimit = kdfOptions.opslimit ?? sodium.crypto_pwhash_OPSLIMIT_MODERATE;
    const memlimit = kdfOptions.memlimit ?? sodium.crypto_pwhash_MEMLIMIT_MODERATE;
    assertKdfLimits(sodium, opslimit, memlimit);

    const salt = sodium.randombytes_buf(sodium.crypto_pwhash_SALTBYTES);
    const nonce = sodium.randombytes_buf(sodium.crypto_aead_xchacha20poly1305_ietf_NPUBBYTES);
    const header = {
        version: LOCKED_IDENTITY_VERSION,
        kdf: { alg: KDF_ALGORITHM, opslimit, memlimit, salt: toBase64(sodium, salt) },
        publicKeys: {
            encryption: toBase64(sodium, identity.encryptionKeyPair.publicKey),
            signing: toBase64(sodium, identity.signingKeyPair.publicKey),
        },
    };

    const key = deriveKey(sodium, passphrase, salt, opslimit, memlimit);
    const secret = new Uint8Array(SERIALISED_SECRET_BYTES);
    try {
        secret.set(identity.encryptionKeyPair.privateKey, 0);
        secret.set(identity.signingKeyPair.privateKey, X25519_SECRET_BYTES);
        const ciphertext = sodium.crypto_aead_xchacha20poly1305_ietf_encrypt(
            secret,
            identityAssociatedData(header, salt, identity.encryptionKeyPair.publicKey, identity.signingKeyPair.publicKey),
            null,
            nonce,
            key,
        );
        return {
            version: header.version,
            kdf: header.kdf,
            nonce: toBase64(sodium, nonce),
            ciphertext: toBase64(sodium, ciphertext),
            publicKeys: header.publicKeys,
        };
    } finally {
        wipe(key);
        wipe(secret);
    }
}

/**
 * Decrypt a locked identity with the chat passphrase.
 *
 * @param {object} locked object produced by `lockIdentity` (as loaded from the server)
 * @param {string} passphrase the chat passphrase
 * @returns {Promise<object>} the identity (see module header); the caller owns it and should wipe it when done
 * @throws {WrongPassphraseError} wrong passphrase, or ciphertext / bound metadata modified
 * @throws {InvalidInputError} structurally malformed locked object, unsupported version/algorithm, or KDF parameters
 *         outside the accepted range (protects against a server forcing a huge memory allocation)
 */
export async function unlockIdentity(locked, passphrase) {
    const sodium = await getSodium();
    if (locked === null || typeof locked !== 'object' || locked.version !== LOCKED_IDENTITY_VERSION) {
        throw new InvalidInputError('Unsupported locked identity format.');
    }
    const kdf = locked.kdf;
    if (kdf === null || typeof kdf !== 'object' || kdf.alg !== KDF_ALGORITHM) {
        throw new InvalidInputError('Unsupported key derivation algorithm.');
    }
    assertKdfLimits(sodium, kdf.opslimit, kdf.memlimit);

    const salt = fromBase64(sodium, kdf.salt, sodium.crypto_pwhash_SALTBYTES);
    const nonce = fromBase64(sodium, locked.nonce, sodium.crypto_aead_xchacha20poly1305_ietf_NPUBBYTES);
    const ciphertext = fromBase64(
        sodium,
        locked.ciphertext,
        SERIALISED_SECRET_BYTES + sodium.crypto_aead_xchacha20poly1305_ietf_ABYTES,
    );
    const encryptionPublicKey = fromBase64(sodium, locked.publicKeys?.encryption, X25519_PUBLIC_BYTES);
    const signingPublicKey = fromBase64(sodium, locked.publicKeys?.signing, ED25519_PUBLIC_BYTES);

    const key = deriveKey(sodium, passphrase, salt, kdf.opslimit, kdf.memlimit);
    let secret = null;
    let encryptionPrivateKey = null;
    let signingPrivateKey = null;
    let unlocked = false;
    try {
        try {
            secret = sodium.crypto_aead_xchacha20poly1305_ietf_decrypt(
                null,
                ciphertext,
                identityAssociatedData(locked, salt, encryptionPublicKey, signingPublicKey),
                nonce,
                key,
            );
        } catch {
            throw new WrongPassphraseError();
        }
        if (secret.length !== SERIALISED_SECRET_BYTES) {
            throw new WrongPassphraseError();
        }

        encryptionPrivateKey = secret.slice(0, X25519_SECRET_BYTES);
        signingPrivateKey = secret.slice(X25519_SECRET_BYTES);
        const derivedEncryptionPublic = sodium.crypto_scalarmult_base(encryptionPrivateKey);
        const derivedSigningPublic = sodium.crypto_sign_ed25519_sk_to_pk(signingPrivateKey);
        if (
            !sodium.memcmp(derivedEncryptionPublic, encryptionPublicKey) ||
            !sodium.memcmp(derivedSigningPublic, signingPublicKey)
        ) {
            throw new WrongPassphraseError();
        }

        unlocked = true;
        return {
            encryptionKeyPair: { publicKey: encryptionPublicKey, privateKey: encryptionPrivateKey },
            signingKeyPair: { publicKey: signingPublicKey, privateKey: signingPrivateKey },
        };
    } finally {
        wipe(key);
        wipe(secret);
        // On any failure the copied private keys never leave this function, so zero them here.
        if (!unlocked) {
            wipe(encryptionPrivateKey);
            wipe(signingPrivateKey);
        }
    }
}

/**
 * Produce the public-key bundle a user publishes so others can send them conversation keys.
 *
 * Returns `{ encryption: base64, signing: base64, binding: base64 }` where `binding` is an Ed25519 signature by the
 * signing key over the encryption key. Without it the server could replace only the encryption key – the safety
 * number (computed from signing keys) would not change, yet conversation keys would be wrapped for the attacker.
 *
 * @param {object} identity the user's own identity
 * @returns {Promise<{encryption: string, signing: string, binding: string}>}
 * @throws {InvalidInputError} on a malformed identity
 */
export async function exportPublicKeys(identity) {
    const sodium = await getSodium();
    assertIdentity(identity);
    const message = canonicalBytes(KEY_BINDING_LABEL, [
        identity.encryptionKeyPair.publicKey,
        identity.signingKeyPair.publicKey,
    ]);
    return {
        encryption: toBase64(sodium, identity.encryptionKeyPair.publicKey),
        signing: toBase64(sodium, identity.signingKeyPair.publicKey),
        binding: toBase64(sodium, sodium.crypto_sign_detached(message, identity.signingKeyPair.privateKey)),
    };
}

/**
 * Verify a public-key bundle from `exportPublicKeys` (as delivered by the server) and decode it.
 *
 * Security note: this only proves that the encryption key belongs to the signing key in the same bundle. Whether the
 * signing key really belongs to the person is established by comparing the safety number (`fingerprint`) and by
 * warning when a previously seen signing key changes – that is the caller's job.
 *
 * @param {{encryption: string, signing: string, binding: string}} bundle
 * @returns {Promise<{encryption: Uint8Array, signing: Uint8Array}>} the verified raw public keys
 * @throws {InvalidPublicKeysError} if the bundle is malformed or the binding signature does not verify
 */
export async function verifyPublicKeys(bundle) {
    const sodium = await getSodium();
    try {
        const encryption = fromBase64(sodium, bundle?.encryption, X25519_PUBLIC_BYTES);
        const signing = fromBase64(sodium, bundle?.signing, ED25519_PUBLIC_BYTES);
        const binding = fromBase64(sodium, bundle?.binding, sodium.crypto_sign_BYTES);
        const message = canonicalBytes(KEY_BINDING_LABEL, [encryption, signing]);
        if (!sodium.crypto_sign_verify_detached(binding, message, signing)) {
            throw new InvalidPublicKeysError();
        }
        return { encryption, signing };
    } catch (error) {
        if (error instanceof InvalidPublicKeysError) {
            throw error;
        }
        throw new InvalidPublicKeysError();
    }
}

/**
 * Compute the safety number of two identities: 12 groups of 5 digits, e.g. "01234 56789 …".
 *
 * Order-independent: `fingerprint(a, b) === fingerprint(b, a)`. The two Ed25519 public signing keys are sorted
 * bytewise, length-prefixed, and hashed with BLAKE2b-512 (`crypto_generichash`) under a domain-separation label.
 * Each group takes 5 bytes (40 bits) of the digest modulo 100000 (bias < 2^-23, negligible), giving 60 digits
 * (~199 bits). Public data only, so non-constant-time sorting is fine.
 *
 * @param {Uint8Array} publicSigningKeyA Ed25519 public key (32 bytes)
 * @param {Uint8Array} publicSigningKeyB Ed25519 public key (32 bytes)
 * @returns {Promise<string>} 12 space-separated groups of 5 digits
 * @throws {InvalidInputError} if a key is not 32 bytes
 */
export async function fingerprint(publicSigningKeyA, publicSigningKeyB) {
    const sodium = await getSodium();
    requireBytes(publicSigningKeyA, ED25519_PUBLIC_BYTES, 'publicSigningKeyA');
    requireBytes(publicSigningKeyB, ED25519_PUBLIC_BYTES, 'publicSigningKeyB');
    const [first, second] =
        compareBytes(publicSigningKeyA, publicSigningKeyB) <= 0
            ? [publicSigningKeyA, publicSigningKeyB]
            : [publicSigningKeyB, publicSigningKeyA];
    const digest = sodium.crypto_generichash(64, canonicalBytes(SAFETY_NUMBER_LABEL, [first, second]), null);

    const groups = [];
    for (let group = 0; group < 12; group += 1) {
        let value = 0;
        for (let i = 0; i < 5; i += 1) {
            value = value * 256 + digest[group * 5 + i];
        }
        groups.push(String(value % 100000).padStart(5, '0'));
    }
    return groups.join(' ');
}

/**
 * Derive the 32-byte identity-wrapping key from the passphrase with Argon2id.
 *
 * @param {object} sodium ready libsodium
 * @param {unknown} passphrase
 * @param {Uint8Array} salt
 * @param {number} opslimit
 * @param {number} memlimit
 * @returns {Uint8Array} derived key – caller must wipe it
 * @throws {InvalidInputError} if the passphrase is not a non-empty string
 */
function deriveKey(sodium, passphrase, salt, opslimit, memlimit) {
    if (typeof passphrase !== 'string' || passphrase.length === 0) {
        throw new InvalidInputError('The passphrase must be a non-empty string.');
    }
    const passphraseBytes = utf8Encode(passphrase.normalize('NFC'));
    try {
        return sodium.crypto_pwhash(
            sodium.crypto_aead_xchacha20poly1305_ietf_KEYBYTES,
            passphraseBytes,
            salt,
            opslimit,
            memlimit,
            sodium.crypto_pwhash_ALG_ARGON2ID13,
        );
    } finally {
        wipe(passphraseBytes);
    }
}

/**
 * Associated data for the locked identity: version, KDF parameters, salt and both public keys.
 *
 * @param {{version: number, kdf: {alg: string, opslimit: number, memlimit: number}}} header
 * @param {Uint8Array} salt
 * @param {Uint8Array} encryptionPublicKey
 * @param {Uint8Array} signingPublicKey
 * @returns {Uint8Array}
 */
function identityAssociatedData(header, salt, encryptionPublicKey, signingPublicKey) {
    return canonicalBytes(IDENTITY_AD_LABEL, [
        String(header.version),
        header.kdf.alg,
        String(header.kdf.opslimit),
        String(header.kdf.memlimit),
        salt,
        encryptionPublicKey,
        signingPublicKey,
    ]);
}

/**
 * Accept only Argon2id costs between libsodium's MIN and SENSITIVE limits.
 *
 * @param {object} sodium ready libsodium
 * @param {unknown} opslimit
 * @param {unknown} memlimit bytes
 * @returns {void}
 * @throws {InvalidInputError}
 */
function assertKdfLimits(sodium, opslimit, memlimit) {
    const inRange = (value, min, max) => Number.isSafeInteger(value) && value >= min && value <= max;
    if (
        // Upper bound MODERATE (what lockIdentity uses in production): a manipulated server cannot make a phone tab
        // allocate 1 GiB (SENSITIVE) and crash before the AEAD check fails (Antigravity crypto review, finding 2).
        !inRange(opslimit, sodium.crypto_pwhash_OPSLIMIT_MIN, sodium.crypto_pwhash_OPSLIMIT_MODERATE) ||
        !inRange(memlimit, sodium.crypto_pwhash_MEMLIMIT_MIN, sodium.crypto_pwhash_MEMLIMIT_MODERATE)
    ) {
        throw new InvalidInputError('Key derivation parameters are out of the accepted range.');
    }
}

/**
 * Check the shape of an identity object (types and key lengths).
 *
 * @param {unknown} identity
 * @returns {void}
 * @throws {InvalidInputError}
 */
export function assertIdentity(identity) {
    if (identity === null || typeof identity !== 'object') {
        throw new InvalidInputError('identity must be an identity object.');
    }
    requireBytes(identity.encryptionKeyPair?.publicKey, X25519_PUBLIC_BYTES, 'encryptionKeyPair.publicKey');
    requireBytes(identity.encryptionKeyPair?.privateKey, X25519_SECRET_BYTES, 'encryptionKeyPair.privateKey');
    requireBytes(identity.signingKeyPair?.publicKey, ED25519_PUBLIC_BYTES, 'signingKeyPair.publicKey');
    requireBytes(identity.signingKeyPair?.privateKey, ED25519_SECRET_BYTES, 'signingKeyPair.privateKey');
}

/**
 * Lexicographic byte comparison of two equal-length public values (not constant time – public data only).
 *
 * @param {Uint8Array} a
 * @param {Uint8Array} b
 * @returns {number} negative, zero or positive
 */
function compareBytes(a, b) {
    for (let i = 0; i < a.length; i += 1) {
        if (a[i] !== b[i]) {
            return a[i] - b[i];
        }
    }
    return 0;
}
