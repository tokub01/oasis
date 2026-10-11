/** Rebuild the backend interoperability fixture using the real browser core; never exports private keys. */
import { writeFile } from 'node:fs/promises';
import {
    createIdentity, lockIdentity, exportPublicKeys, createConversationKey, createKeyGrant,
    encryptMessage, verifyPublicKeys, wipeIdentity, wipe,
} from '../../resources/js/chat/index.js';

const owner = await createIdentity();
const member = await createIdentity();
const key = await createConversationKey();
const originalNow = Date.now;
Date.now = () => Date.parse('2026-10-11T12:00:00Z');
try {
    const ownerKeys = await exportPublicKeys(owner);
    const memberKeys = await exportPublicKeys(member);
    const grants = [];
    for (const [recipientId, bundle] of [['11', ownerKeys], ['12', memberKeys]]) {
        const verified = await verifyPublicKeys(bundle);
        grants.push(await createKeyGrant({ conversationKey: key, conversationId: '4242', keyVersion: 1,
            recipientId, recipientEncryptionPublicKey: verified.encryption,
            granterId: '11', identity: owner }));
    }
    const fixture = {
        server_time: '2026-10-11T12:00:00Z',
        owner: { public_keys: ownerKeys, locked_identity: await lockIdentity(owner, 'test-only-fixture-passphrase') },
        member: { public_keys: memberKeys, locked_identity: await lockIdentity(member, 'test-only-fixture-passphrase') },
        grants,
        envelope: await encryptMessage({ conversationKey: key, conversationId: '4242', keyVersion: 1,
            senderId: '11', identity: owner, plaintext: 'Browser interoperability fixture 🌴',
            expiresAt: '2026-10-18T12:00:00Z' }),
    };
    await writeFile(new URL('../Fixtures/chat-core.json', import.meta.url), JSON.stringify(fixture, null, 2) + '\n');
} finally {
    Date.now = originalNow;
    wipe(key);
    wipeIdentity(owner);
    wipeIdentity(member);
}
