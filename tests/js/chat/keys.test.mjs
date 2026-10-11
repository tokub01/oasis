/**
 * Tests for resources/js/chat/keys.js – identity creation, passphrase locking, public-key bundles, safety numbers.
 * Uses real libsodium (no mocks). Argon2id runs with libsodium's MIN costs via `kdfOptions` to keep the suite fast;
 * one test exercises the production defaults (MODERATE) once.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import {
    createIdentity,
    exportPublicKeys,
    fingerprint,
    getSodium,
    InvalidInputError,
    InvalidPublicKeysError,
    lockIdentity,
    unlockIdentity,
    verifyPublicKeys,
    WrongPassphraseError,
} from '../../../resources/js/chat/index.js';

const sodium = await getSodium();
const FAST_KDF = { opslimit: sodium.crypto_pwhash_OPSLIMIT_MIN, memlimit: sodium.crypto_pwhash_MEMLIMIT_MIN };
const PASSPHRASE = 'korrekt Pferd Batterie Heftklammer';

/** Swap one Base64 character for another valid one so the decoded bytes change. */
function flipBase64(text, index = 0) {
    const replacement = text[index] === 'A' ? 'B' : 'A';
    return text.slice(0, index) + replacement + text.slice(index + 1);
}

test('createIdentity returns X25519 and Ed25519 key pairs of the right sizes', async () => {
    const identity = await createIdentity();
    assert.equal(identity.encryptionKeyPair.publicKey.length, 32);
    assert.equal(identity.encryptionKeyPair.privateKey.length, 32);
    assert.equal(identity.signingKeyPair.publicKey.length, 32);
    assert.equal(identity.signingKeyPair.privateKey.length, 64);
});

test('lock/unlock round-trip restores identical keys and the locked object is JSON-serialisable', async () => {
    const identity = await createIdentity();
    const locked = await lockIdentity(identity, PASSPHRASE, FAST_KDF);
    const fromServer = JSON.parse(JSON.stringify(locked));

    assert.equal(fromServer.version, 1);
    assert.equal(fromServer.kdf.alg, 'argon2id13');
    assert.equal(fromServer.kdf.opslimit, FAST_KDF.opslimit);
    assert.equal(fromServer.kdf.memlimit, FAST_KDF.memlimit);
    assert.match(fromServer.ciphertext, /^[A-Za-z0-9_-]+$/, 'URL-safe Base64 without padding');

    const unlocked = await unlockIdentity(fromServer, PASSPHRASE);
    assert.deepEqual(unlocked.encryptionKeyPair.privateKey, identity.encryptionKeyPair.privateKey);
    assert.deepEqual(unlocked.encryptionKeyPair.publicKey, identity.encryptionKeyPair.publicKey);
    assert.deepEqual(unlocked.signingKeyPair.privateKey, identity.signingKeyPair.privateKey);
    assert.deepEqual(unlocked.signingKeyPair.publicKey, identity.signingKeyPair.publicKey);
});

test('the ciphertext does not contain the private keys in the clear', async () => {
    const identity = await createIdentity();
    const locked = await lockIdentity(identity, PASSPHRASE, FAST_KDF);
    const raw = sodium.from_base64(locked.ciphertext, sodium.base64_variants.URLSAFE_NO_PADDING);
    const hex = sodium.to_hex(raw);
    assert.ok(!hex.includes(sodium.to_hex(identity.encryptionKeyPair.privateKey)));
    assert.ok(!hex.includes(sodium.to_hex(identity.signingKeyPair.privateKey.slice(0, 32))));
});

test('production defaults use Argon2id MODERATE and still round-trip', async () => {
    const identity = await createIdentity();
    const locked = await lockIdentity(identity, PASSPHRASE);
    assert.equal(locked.kdf.opslimit, sodium.crypto_pwhash_OPSLIMIT_MODERATE);
    assert.equal(locked.kdf.memlimit, sodium.crypto_pwhash_MEMLIMIT_MODERATE);
    const unlocked = await unlockIdentity(locked, PASSPHRASE);
    assert.deepEqual(unlocked.signingKeyPair.publicKey, identity.signingKeyPair.publicKey);
});

test('a wrong passphrase is rejected with WrongPassphraseError', async () => {
    const locked = await lockIdentity(await createIdentity(), PASSPHRASE, FAST_KDF);
    await assert.rejects(unlockIdentity(locked, PASSPHRASE + '!'), WrongPassphraseError);
});

test('the passphrase is Unicode-normalised (NFC vs NFD unlock the same identity)', async () => {
    const locked = await lockIdentity(await createIdentity(), 'Grüße aus der Oase', FAST_KDF);
    await unlockIdentity(locked, 'Grüße aus der Oase');
});

test('modified ciphertext, nonce, salt or swapped public keys cannot be unlocked', async () => {
    const locked = await lockIdentity(await createIdentity(), PASSPHRASE, FAST_KDF);
    const other = await createIdentity();
    const variants = [
        { ...locked, ciphertext: flipBase64(locked.ciphertext, 5) },
        { ...locked, nonce: flipBase64(locked.nonce, 3) },
        { ...locked, kdf: { ...locked.kdf, salt: flipBase64(locked.kdf.salt, 2) } },
        {
            ...locked,
            publicKeys: { ...locked.publicKeys, encryption: sodium.to_base64(other.encryptionKeyPair.publicKey, 7) },
        },
        { ...locked, publicKeys: { ...locked.publicKeys, signing: sodium.to_base64(other.signingKeyPair.publicKey, 7) } },
    ];
    for (const variant of variants) {
        await assert.rejects(unlockIdentity(variant, PASSPHRASE), WrongPassphraseError);
    }
});

test('out-of-range KDF parameters from the server are refused before deriving', async () => {
    const locked = await lockIdentity(await createIdentity(), PASSPHRASE, FAST_KDF);
    const huge = { ...locked, kdf: { ...locked.kdf, memlimit: sodium.crypto_pwhash_MEMLIMIT_SENSITIVE * 4 } };
    await assert.rejects(unlockIdentity(huge, PASSPHRASE), InvalidInputError);
    const otherAlg = { ...locked, kdf: { ...locked.kdf, alg: 'argon2i13' } };
    await assert.rejects(unlockIdentity(otherAlg, PASSPHRASE), InvalidInputError);
});

test('an empty passphrase is refused', async () => {
    await assert.rejects(lockIdentity(await createIdentity(), '', FAST_KDF), InvalidInputError);
});

test('public-key bundle verifies, and a swapped encryption key is detected', async () => {
    const alice = await createIdentity();
    const mallory = await createIdentity();
    const bundle = JSON.parse(JSON.stringify(await exportPublicKeys(alice)));

    const verified = await verifyPublicKeys(bundle);
    assert.deepEqual(verified.encryption, alice.encryptionKeyPair.publicKey);
    assert.deepEqual(verified.signing, alice.signingKeyPair.publicKey);

    const swapped = { ...bundle, encryption: sodium.to_base64(mallory.encryptionKeyPair.publicKey, 7) };
    await assert.rejects(verifyPublicKeys(swapped), InvalidPublicKeysError);
    await assert.rejects(verifyPublicKeys({ ...bundle, binding: 'not base64!' }), InvalidPublicKeysError);
});

test('fingerprint is symmetric, formatted as 12 groups of 5 digits, and differs for different keys', async () => {
    const a = (await createIdentity()).signingKeyPair.publicKey;
    const b = (await createIdentity()).signingKeyPair.publicKey;
    const c = (await createIdentity()).signingKeyPair.publicKey;

    const ab = await fingerprint(a, b);
    assert.equal(ab, await fingerprint(b, a));
    assert.match(ab, /^\d{5}( \d{5}){11}$/);
    assert.notEqual(ab, await fingerprint(a, c));
    assert.notEqual(ab, await fingerprint(c, b));
});

test('fingerprint rejects keys of the wrong length', async () => {
    await assert.rejects(fingerprint(new Uint8Array(31), new Uint8Array(32)), InvalidInputError);
});
