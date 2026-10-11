/**
 * Adversarial tests for resources/js/chat/ – attacks a malicious server or network adversary could try.
 *
 * Threat model (see docs/oasis-product-plan.md and docs/oasis-chat.md):
 * - The server and Pusher are untrusted for confidentiality.
 * - They may store, replay, reorder, drop or alter ciphertexts, swap public keys, hand out their own conversation keys,
 *   and change expiry times.
 * - All tests use real libsodium (no mocks or stubs).
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import {
    createConversationKey,
    createIdentity,
    createKeyGrant,
    decryptMessage,
    encryptMessage,
    exportPublicKeys,
    getSodium,
    InvalidKeyGrantError,
    InvalidPublicKeysError,
    lockIdentity,
    openKeyGrant,
    TamperedMessageError,
    unlockIdentity,
    verifyPublicKeys,
    wrapKeyFor,
    WrongPassphraseError,
    InvalidInputError,
} from '../../../resources/js/chat/index.js';

const sodium = await getSodium();
const HOUR = 60 * 60 * 1000;
const FAST_KDF = { opslimit: sodium.crypto_pwhash_OPSLIMIT_MIN, memlimit: sodium.crypto_pwhash_MEMLIMIT_MIN };

/** Helper to generate a baseline message fixture */
async function messageFixture({
    conversationId = 'conv-alpha',
    keyVersion = 1,
    senderId = 'alice',
    plaintext = 'Streng vertrauliche Nachricht 🔒',
    expiresAt = new Date(Date.now() + 2 * HOUR),
} = {}) {
    const sender = await createIdentity();
    const conversationKey = await createConversationKey();
    const envelope = await encryptMessage({
        conversationKey,
        conversationId,
        keyVersion,
        senderId,
        identity: sender,
        plaintext,
        expiresAt,
    });
    return { sender, conversationKey, envelope: JSON.parse(JSON.stringify(envelope)) };
}

test('attack: replay of an old envelope into another conversation', async () => {
    // Alice sends a message in conv-1 under convKey1.
    const { sender, conversationKey: convKey1, envelope } = await messageFixture({
        conversationId: 'conv-1',
        keyVersion: 1,
        senderId: 'alice',
    });

    // Attack 1a: Server changes conversation_id in the envelope to replay it into conv-2.
    // The recipient verifies with the original sender's public key and convKey1.
    const modifiedEnvelope = { ...envelope, conversation_id: 'conv-2' };
    await assert.rejects(
        decryptMessage({
            envelope: modifiedEnvelope,
            conversationKey: convKey1,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Server rewriting conversation_id must be rejected (bound in signature and AEAD AD)',
    );

    // Attack 1b: Server replays the untouched envelope into conv-2, where members use convKey2.
    const convKey2 = await createConversationKey();
    await assert.rejects(
        decryptMessage({
            envelope,
            conversationKey: convKey2,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Envelope replayed into another conversation with different key must fail AEAD decryption',
    );
});

test('attack: key-version downgrade', async () => {
    // A conversation was rotated to key_version 2. Alice sends a message under version 2.
    const convKeyV1 = await createConversationKey();
    const convKeyV2 = await createConversationKey();
    const sender = await createIdentity();

    const envelopeV2 = await encryptMessage({
        conversationKey: convKeyV2,
        conversationId: 'conv-secure',
        keyVersion: 2,
        senderId: 'alice',
        identity: sender,
        plaintext: 'Nach Rotation gesendet',
        expiresAt: new Date(Date.now() + HOUR),
    });

    // Attack 2a: Server downgrades key_version from 2 to 1 in the envelope header.
    const downgradedEnvelope = { ...envelopeV2, key_version: 1 };

    // Recipient decrypting with current convKeyV2 must reject the downgraded header
    await assert.rejects(
        decryptMessage({
            envelope: downgradedEnvelope,
            conversationKey: convKeyV2,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Server downgrading key_version must be rejected even with the correct key',
    );

    // Recipient attempting to decrypt with obsolete convKeyV1 must also reject
    await assert.rejects(
        decryptMessage({
            envelope: downgradedEnvelope,
            conversationKey: convKeyV1,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Downgraded envelope under old key must fail signature verification and AEAD',
    );

    // Attack 2b: Server injects an out-of-range or negative key version
    const invalidVersionEnvelope = { ...envelopeV2, key_version: -1 };
    await assert.rejects(
        decryptMessage({
            envelope: invalidVersionEnvelope,
            conversationKey: convKeyV2,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Negative key version must be rejected',
    );
});

test('attack: grant re-targeted to another recipient', async () => {
    const owner = await createIdentity();
    const bob = await createIdentity();
    const charlie = await createIdentity();
    const conversationKey = await createConversationKey();

    // Owner creates a signed grant specifically for Bob
    const grantForBob = JSON.parse(
        JSON.stringify(
            await createKeyGrant({
                conversationKey,
                conversationId: 'conv-group',
                keyVersion: 1,
                recipientId: 'bob',
                recipientEncryptionPublicKey: bob.encryptionKeyPair.publicKey,
                granterId: 'owner',
                identity: owner,
            }),
        ),
    );

    // Legitimate recipient (Bob) can open the grant
    const openedByBob = await openKeyGrant({
        grant: grantForBob,
        identity: bob,
        granterSigningPublicKey: owner.signingKeyPair.publicKey,
    });
    assert.deepEqual(openedByBob.conversationKey, conversationKey);

    // Attack 3a: Server modifies recipient_id in Bob's grant to 'charlie' and gives it to Charlie
    const retargetedGrant = { ...grantForBob, recipient_id: 'charlie' };
    await assert.rejects(
        openKeyGrant({
            grant: retargetedGrant,
            identity: charlie,
            granterSigningPublicKey: owner.signingKeyPair.publicKey,
        }),
        InvalidKeyGrantError,
        'Grant with rewritten recipient_id must fail signature verification',
    );

    // Attack 3b: Server delivers Bob\'s grant unmodified to Charlie
    await assert.rejects(
        openKeyGrant({
            grant: grantForBob,
            identity: charlie,
            granterSigningPublicKey: owner.signingKeyPair.publicKey,
        }),
        InvalidKeyGrantError,
        'Grant delivered to wrong recipient must fail signature check and sealed box unwrap',
    );

    // Attack 3c: Server replaces wrapped_key with a key wrapped for Charlie, keeping owner signature
    const injectedKeyForCharlie = await wrapKeyFor(await createConversationKey(), charlie.encryptionKeyPair.publicKey);
    const splicedGrant = { ...grantForBob, wrapped_key: injectedKeyForCharlie };
    await assert.rejects(
        openKeyGrant({
            grant: splicedGrant,
            identity: charlie,
            granterSigningPublicKey: owner.signingKeyPair.publicKey,
        }),
        InvalidKeyGrantError,
        'Grant with substituted sealed box must fail owner signature verification',
    );
});

test('attack: swapped encryption key with unchanged signing key', async () => {
    const alice = await createIdentity();
    const mallory = await createIdentity();

    // Alice publishes her authentic public-key bundle
    const bundle = JSON.parse(JSON.stringify(await exportPublicKeys(alice)));

    // Attack 4a: Server replaces Alice's encryption key with Mallory's encryption key
    // but keeps Alice's signing key untouched so the pairwise safety number does not change.
    const swappedBundle = {
        ...bundle,
        encryption: sodium.to_base64(mallory.encryptionKeyPair.publicKey, sodium.base64_variants.URLSAFE_NO_PADDING),
    };

    await assert.rejects(
        verifyPublicKeys(swappedBundle),
        InvalidPublicKeysError,
        'Swapped encryption key must fail binding signature verification under original signing key',
    );

    // Attack 4b: Server replaces Alice's binding signature with one signed by Mallory
    const forgedMessage = sodium.to_base64(
        sodium.crypto_sign_detached(
            new Uint8Array([1, 2, 3]), // arbitrary forged signature
            mallory.signingKeyPair.privateKey,
        ),
        sodium.base64_variants.URLSAFE_NO_PADDING,
    );
    const forgedBundle = { ...swappedBundle, binding: forgedMessage };
    await assert.rejects(
        verifyPublicKeys(forgedBundle),
        InvalidPublicKeysError,
        'Forged binding signature must fail verification against Alice signing key',
    );
});

test('attack: expiry extension', async () => {
    const originalExpiry = new Date(Date.now() + HOUR);
    const { sender, conversationKey, envelope } = await messageFixture({
        expiresAt: originalExpiry,
    });

    // Attack 5a: Server extends expiry by 30 days
    const extendedExpiry = new Date(Date.parse(envelope.expires_at) + 30 * 24 * HOUR).toISOString().replace('.000Z', 'Z');
    const extendedEnvelope = { ...envelope, expires_at: extendedExpiry };
    await assert.rejects(
        decryptMessage({
            envelope: extendedEnvelope,
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Server extending expires_at must be rejected (expiry is cryptographically bound)',
    );

    // Attack 5b: Server strips expires_at completely (attempts to make ephemeral message permanent)
    const strippedEnvelope = { ...envelope, expires_at: null };
    await assert.rejects(
        decryptMessage({
            envelope: strippedEnvelope,
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Server stripping expires_at must be rejected',
    );

    // Attack 5c: Server nudges expiry by even 1 second
    const nudgedExpiry = new Date(Date.parse(envelope.expires_at) + 1000).toISOString().replace('.000Z', 'Z');
    const nudgedEnvelope = { ...envelope, expires_at: nudgedExpiry };
    await assert.rejects(
        decryptMessage({
            envelope: nudgedEnvelope,
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'A 1-second modification of expires_at must fail signature and AEAD verification',
    );
});

test('attack: truncated or padded ciphertext', async () => {
    const { sender, conversationKey, envelope } = await messageFixture();
    const rawCiphertext = sodium.from_base64(envelope.ciphertext, sodium.base64_variants.URLSAFE_NO_PADDING);

    // Attack 6a: Truncated ciphertext (remove 1 byte)
    const truncated1 = rawCiphertext.slice(0, rawCiphertext.length - 1);
    const truncatedEnvelope1 = {
        ...envelope,
        ciphertext: sodium.to_base64(truncated1, sodium.base64_variants.URLSAFE_NO_PADDING),
    };
    await assert.rejects(
        decryptMessage({
            envelope: truncatedEnvelope1,
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Ciphertext truncated by 1 byte must be rejected',
    );

    // Attack 6b: Truncated ciphertext below AEAD tag length (15 bytes instead of >= 16)
    const truncatedShort = rawCiphertext.slice(0, 15);
    const truncatedEnvelopeShort = {
        ...envelope,
        ciphertext: sodium.to_base64(truncatedShort, sodium.base64_variants.URLSAFE_NO_PADDING),
    };
    await assert.rejects(
        decryptMessage({
            envelope: truncatedEnvelopeShort,
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Ciphertext shorter than AEAD tag length must be rejected',
    );

    // Attack 6c: Padded ciphertext (appended 1 byte or 16 null bytes)
    const padded = new Uint8Array(rawCiphertext.length + 16);
    padded.set(rawCiphertext);
    const paddedEnvelope = {
        ...envelope,
        ciphertext: sodium.to_base64(padded, sodium.base64_variants.URLSAFE_NO_PADDING),
    };
    await assert.rejects(
        decryptMessage({
            envelope: paddedEnvelope,
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Ciphertext with trailing padding bytes must be rejected',
    );

    // Attack 6d: Truncated or padded locked identity ciphertext
    const identity = await createIdentity();
    const locked = await lockIdentity(identity, 'geheimes-passwort', FAST_KDF);
    const rawLockedCiphertext = sodium.from_base64(locked.ciphertext, sodium.base64_variants.URLSAFE_NO_PADDING);

    const truncatedLocked = {
        ...locked,
        ciphertext: sodium.to_base64(
            rawLockedCiphertext.slice(0, rawLockedCiphertext.length - 8),
            sodium.base64_variants.URLSAFE_NO_PADDING,
        ),
    };
    await assert.rejects(
        unlockIdentity(truncatedLocked, 'geheimes-passwort'),
        // Refused either by the length check (InvalidInputError) or by the AEAD tag (WrongPassphraseError).
        (error) => error instanceof WrongPassphraseError || error instanceof InvalidInputError,
        'Truncated locked identity ciphertext must fail unlocking',
    );

    const paddedLocked = new Uint8Array(rawLockedCiphertext.length + 8);
    paddedLocked.set(rawLockedCiphertext);
    const paddedLockedIdentity = {
        ...locked,
        ciphertext: sodium.to_base64(paddedLocked, sodium.base64_variants.URLSAFE_NO_PADDING),
    };
    await assert.rejects(
        unlockIdentity(paddedLockedIdentity, 'geheimes-passwort'),
        // Refused either by the length check (InvalidInputError) or by the AEAD tag (WrongPassphraseError).
        (error) => error instanceof WrongPassphraseError || error instanceof InvalidInputError,
        'Padded locked identity ciphertext must fail unlocking',
    );
});

test('attack: mixed base64 alphabets', async () => {
    const { sender, conversationKey, envelope } = await messageFixture();

    const rawNonce = sodium.from_base64(envelope.nonce, sodium.base64_variants.URLSAFE_NO_PADDING);
    const rawCiphertext = sodium.from_base64(envelope.ciphertext, sodium.base64_variants.URLSAFE_NO_PADDING);
    const rawSignature = sodium.from_base64(envelope.signature, sodium.base64_variants.URLSAFE_NO_PADDING);

    // Attack 7a: Standard RFC 4648 Base64 with padding '=' on signature (64 bytes -> 88 chars with '==')
    const stdPaddedSignature = sodium.to_base64(rawSignature, sodium.base64_variants.ORIGINAL);
    assert.ok(stdPaddedSignature.endsWith('=='), 'Standard Ed25519 signature base64 has padding');
    await assert.rejects(
        decryptMessage({
            envelope: { ...envelope, signature: stdPaddedSignature },
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Standard Base64 with padding in signature must be rejected',
    );

    // Attack 7b: Explicit padding '=' appended to nonce
    await assert.rejects(
        decryptMessage({
            envelope: { ...envelope, nonce: envelope.nonce + '=' },
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Base64 with padding in nonce must be rejected',
    );

    // Attack 7c: Standard Base64 characters '+' and '/' injected into ciphertext
    const withPlusCiphertext = '+' + envelope.ciphertext.slice(1);
    const withSlashCiphertext = '/' + envelope.ciphertext.slice(1);
    await assert.rejects(
        decryptMessage({
            envelope: { ...envelope, ciphertext: withPlusCiphertext },
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Standard Base64 with "+" in ciphertext must be rejected',
    );
    await assert.rejects(
        decryptMessage({
            envelope: { ...envelope, ciphertext: withSlashCiphertext },
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Standard Base64 with "/" in ciphertext must be rejected',
    );

    // Attack 7d: Converting legitimate '-' and '_' to standard '+' and '/'
    // If envelope contains '-' or '_', replacing them with standard base64 equivalent must fail
    let testEnvelope = envelope;
    // If testEnvelope has neither '-' nor '_', find an envelope that has them
    while (!testEnvelope.ciphertext.includes('-') && !testEnvelope.ciphertext.includes('_')) {
        const next = await messageFixture();
        testEnvelope = next.envelope;
    }
    const mixedCiphertext = testEnvelope.ciphertext.replace(/-/g, '+').replace(/_/g, '/');
    await assert.rejects(
        decryptMessage({
            envelope: { ...testEnvelope, ciphertext: mixedCiphertext },
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
        'Replacing URL-safe "-" and "_" with standard "+" and "/" must be rejected',
    );

    // Attack 7e: Standard Base64 with padding in key grant wrapped_key (80 bytes -> 108 chars ending in '=')
    const bob = await createIdentity();
    const grant = await createKeyGrant({
        conversationKey,
        conversationId: 'conv-b64',
        keyVersion: 1,
        recipientId: 'bob',
        recipientEncryptionPublicKey: bob.encryptionKeyPair.publicKey,
        granterId: 'alice',
        identity: sender,
    });

    const rawWrapped = sodium.from_base64(grant.wrapped_key, sodium.base64_variants.URLSAFE_NO_PADDING);
    const stdPaddedWrapped = sodium.to_base64(rawWrapped, sodium.base64_variants.ORIGINAL);
    assert.ok(stdPaddedWrapped.endsWith('='), 'Standard wrapped key has padding');
    await assert.rejects(
        openKeyGrant({
            grant: { ...grant, wrapped_key: stdPaddedWrapped },
            identity: bob,
            granterSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        InvalidKeyGrantError,
        'Standard padded Base64 in key grant wrapped_key must be rejected',
    );

    // Attack 7f: Standard Base64 in public key bundle (32 bytes public key -> 44 chars ending in '=')
    const bundle = await exportPublicKeys(sender);
    const rawSigning = sodium.from_base64(bundle.signing, sodium.base64_variants.URLSAFE_NO_PADDING);
    const stdPaddedSigning = sodium.to_base64(rawSigning, sodium.base64_variants.ORIGINAL);
    assert.ok(stdPaddedSigning.endsWith('='), 'Standard public key has padding');
    await assert.rejects(
        verifyPublicKeys({ ...bundle, signing: stdPaddedSigning }),
        InvalidPublicKeysError,
        'Standard padded Base64 in public key bundle must be rejected',
    );
});
