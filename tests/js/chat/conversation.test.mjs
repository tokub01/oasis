/**
 * Tests for resources/js/chat/conversation.js – conversation keys, key wrapping/grants and message envelopes.
 * Uses real libsodium (no mocks).
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import {
    createConversationKey,
    createIdentity,
    createKeyGrant,
    decryptMessage,
    encryptMessage,
    ExpiredMessageError,
    getSodium,
    InvalidInputError,
    InvalidKeyGrantError,
    MAX_MESSAGE_LENGTH,
    MAX_MESSAGE_BYTES,
    MessageTooLongError,
    openKeyGrant,
    TamperedMessageError,
    unwrapKey,
    wipe,
    wrapKeyFor,
} from '../../../resources/js/chat/index.js';

const sodium = await getSodium();
const HOUR = 60 * 60 * 1000;

/** Swap one Base64 character for another valid one so the decoded bytes change. */
function flipBase64(text, index = 0) {
    const replacement = text[index] === 'A' ? 'B' : 'A';
    return text.slice(0, index) + replacement + text.slice(index + 1);
}

/** A sender, a conversation key and one valid envelope (JSON round-tripped as if it came from the server). */
async function fixture({ plaintext = 'Hallo aus der Oase 🌴', expiresAt = new Date(Date.now() + HOUR) } = {}) {
    const sender = await createIdentity();
    const conversationKey = await createConversationKey();
    const envelope = await encryptMessage({
        conversationKey,
        conversationId: 42,
        keyVersion: 3,
        senderId: 'user-7',
        identity: sender,
        plaintext,
        expiresAt,
    });
    return { sender, conversationKey, envelope: JSON.parse(JSON.stringify(envelope)) };
}

test('createConversationKey returns 32 random bytes', async () => {
    const a = await createConversationKey();
    const b = await createConversationKey();
    assert.equal(a.length, 32);
    assert.notDeepEqual(a, b);
});

test('wrap/unwrap works for 3 recipients and a non-recipient cannot unwrap', async () => {
    const key = await createConversationKey();
    const members = [await createIdentity(), await createIdentity(), await createIdentity()];
    for (const member of members) {
        const wrapped = await wrapKeyFor(key, member.encryptionKeyPair.publicKey);
        assert.equal(typeof wrapped, 'string');
        assert.deepEqual(await unwrapKey(wrapped, member), key);
    }
    const outsider = await createIdentity();
    const wrappedForFirst = await wrapKeyFor(key, members[0].encryptionKeyPair.publicKey);
    await assert.rejects(unwrapKey(wrappedForFirst, outsider), InvalidKeyGrantError);
});

test('signed key grants open for each of 3 recipients and reject forgery or reassignment', async () => {
    const key = await createConversationKey();
    const granter = await createIdentity();
    const mallory = await createIdentity();
    const members = [await createIdentity(), await createIdentity(), await createIdentity()];

    for (const [index, member] of members.entries()) {
        const grant = JSON.parse(
            JSON.stringify(
                await createKeyGrant({
                    conversationKey: key,
                    conversationId: 'conv-1',
                    keyVersion: 2,
                    recipientId: `member-${index}`,
                    recipientEncryptionPublicKey: member.encryptionKeyPair.publicKey,
                    granterId: 'granter',
                    identity: granter,
                }),
            ),
        );
        const opened = await openKeyGrant({
            grant,
            identity: member,
            granterSigningPublicKey: granter.signingKeyPair.publicKey,
        });
        assert.deepEqual(opened.conversationKey, key);
        assert.equal(opened.conversationId, 'conv-1');
        assert.equal(opened.keyVersion, 2);
        assert.equal(opened.granterId, 'granter');

        const verifyWith = (changed, signingKey = granter.signingKeyPair.publicKey) =>
            openKeyGrant({ grant: changed, identity: member, granterSigningPublicKey: signingKey });
        await assert.rejects(verifyWith(grant, mallory.signingKeyPair.publicKey), InvalidKeyGrantError);
        await assert.rejects(verifyWith({ ...grant, conversation_id: 'conv-2' }), InvalidKeyGrantError);
        await assert.rejects(verifyWith({ ...grant, key_version: 3 }), InvalidKeyGrantError);
        await assert.rejects(verifyWith({ ...grant, recipient_id: 'someone-else' }), InvalidKeyGrantError);
        // Server substitutes its own sealed key for the member.
        const injected = await wrapKeyFor(await createConversationKey(), member.encryptionKeyPair.publicKey);
        await assert.rejects(verifyWith({ ...grant, wrapped_key: injected }), InvalidKeyGrantError);
    }
});

test('encrypt/decrypt round-trip preserves UTF-8 text and binds metadata in the envelope', async () => {
    const { sender, conversationKey, envelope } = await fixture();
    assert.equal(envelope.v, 1);
    assert.equal(envelope.conversation_id, '42');
    assert.equal(envelope.key_version, 3);
    assert.equal(envelope.sender_id, 'user-7');
    assert.match(envelope.expires_at, /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/);
    assert.equal(sodium.from_base64(envelope.nonce, 7).length, 24);

    const plaintext = await decryptMessage({
        envelope,
        conversationKey,
        senderSigningPublicKey: sender.signingKeyPair.publicKey,
    });
    assert.equal(plaintext, 'Hallo aus der Oase 🌴');
});

test('messages without expiry are refused: every Oasis message self-deletes', async () => {
    await assert.rejects(fixture({ expiresAt: null }), InvalidInputError);
    // Omitted entirely (the fixture would fill in its default, so call encryptMessage directly).
    await assert.rejects(
        encryptMessage({
            conversationKey: await createConversationKey(),
            conversationId: 42,
            keyVersion: 1,
            senderId: 'user-7',
            identity: await createIdentity(),
            plaintext: 'ohne Ablauf',
        }),
        InvalidInputError,
    );
});

test('decryptMessage rejects an authentic envelope shown in another conversation', async () => {
    const { sender, conversationKey, envelope } = await fixture();
    await assert.rejects(
        decryptMessage({ envelope, conversationKey, senderSigningPublicKey: sender.signingKeyPair.publicKey, expectedConversationId: 43 }),
        TamperedMessageError,
    );
    const verified = await decryptMessage({
        envelope,
        conversationKey,
        senderSigningPublicKey: sender.signingKeyPair.publicKey,
        expectedConversationId: 42,
        withMetadata: true,
    });
    assert.equal(verified.plaintext, 'Hallo aus der Oase 🌴');
    assert.equal(verified.conversationId, '42');
    assert.equal(verified.keyVersion, 3);
    assert.equal(verified.senderId, 'user-7');
});

test('server re-formatting expires_at to the same instant (Laravel microseconds) is accepted', async () => {
    const { sender, conversationKey, envelope } = await fixture();
    const reformatted = { ...envelope, expires_at: envelope.expires_at.replace('Z', '.000000Z') };
    const plaintext = await decryptMessage({
        envelope: reformatted,
        conversationKey,
        senderSigningPublicKey: sender.signingKeyPair.publicKey,
    });
    assert.equal(plaintext, 'Hallo aus der Oase 🌴');
});

test('tampering with any envelope field is rejected with TamperedMessageError', async () => {
    const { sender, conversationKey, envelope } = await fixture();
    const later = new Date(Date.parse(envelope.expires_at) + HOUR).toISOString().replace('.000Z', 'Z');
    const tampered = {
        ciphertext: { ...envelope, ciphertext: flipBase64(envelope.ciphertext, 4) },
        nonce: { ...envelope, nonce: flipBase64(envelope.nonce, 4) },
        conversation_id: { ...envelope, conversation_id: '43' },
        key_version: { ...envelope, key_version: 4 },
        sender_id: { ...envelope, sender_id: 'user-8' },
        expires_at: { ...envelope, expires_at: later },
        expires_at_removed: { ...envelope, expires_at: null },
        signature: { ...envelope, signature: flipBase64(envelope.signature, 4) },
        version: { ...envelope, v: 2 },
        garbage_base64: { ...envelope, ciphertext: '***' },
    };
    for (const [field, changed] of Object.entries(tampered)) {
        await assert.rejects(
            decryptMessage({ envelope: changed, conversationKey, senderSigningPublicKey: sender.signingKeyPair.publicKey }),
            TamperedMessageError,
            `tampered ${field} must be rejected`,
        );
    }
});

test('a validly signed message under the wrong conversation key is rejected', async () => {
    const { sender, envelope } = await fixture();
    await assert.rejects(
        decryptMessage({
            envelope,
            conversationKey: await createConversationKey(),
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
        }),
        TamperedMessageError,
    );
});

test('the wrong sender signing key is rejected', async () => {
    const { conversationKey, envelope } = await fixture();
    const impostor = await createIdentity();
    await assert.rejects(
        decryptMessage({ envelope, conversationKey, senderSigningPublicKey: impostor.signingKeyPair.publicKey }),
        TamperedMessageError,
    );
});

test('an expired message is rejected with ExpiredMessageError', async () => {
    const { sender, conversationKey, envelope } = await fixture({ expiresAt: new Date(Date.now() + 5000) });
    await assert.rejects(
        decryptMessage({
            envelope,
            conversationKey,
            senderSigningPublicKey: sender.signingKeyPair.publicKey,
            now: Date.now() + 10_000,
        }),
        ExpiredMessageError,
    );
});

test('encrypting with an expiry in the past is refused', async () => {
    await assert.rejects(fixture({ expiresAt: new Date(Date.now() - 1000) }), InvalidInputError);
});

test('messages are limited by characters and by UTF-8 bytes (one Pusher event)', async () => {
    assert.equal(MAX_MESSAGE_LENGTH, 4000);
    assert.equal(MAX_MESSAGE_BYTES, 5984);
    await fixture({ plaintext: 'a'.repeat(4000) });
    await assert.rejects(fixture({ plaintext: 'a'.repeat(4001) }), MessageTooLongError);
    await fixture({ plaintext: '🌴'.repeat(1496) }); // 1496 x 4 bytes = 5984 bytes
    await assert.rejects(fixture({ plaintext: '🌴'.repeat(1497) }), MessageTooLongError); // 5988 bytes
});

test('wipe zeroes a buffer', async () => {
    const key = await createConversationKey();
    wipe(key);
    assert.ok(key.every((byte) => byte === 0));
});
