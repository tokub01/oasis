/**
 * Tests for the chat client layer (resources/js/chat/client/* and ui/route.js) against real libsodium and an
 * in-memory fake of the JSON API from docs/oasis-chat.md. Covers the flows the page relies on: identity setup and
 * unlock, owner key distribution, grant opening with signature checks, sealing/opening messages and the timeline.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { ServerClock } from '../../../resources/js/chat/client/clock.js';
import { ChatSession } from '../../../resources/js/chat/client/session.js';
import {
    MissingMemberKeysError,
    PublishedKeysMismatchError,
    distributeKey,
    loadDirectory,
    openGrants,
    safetyNumber,
    setUpIdentity,
    unlockStoredIdentity,
} from '../../../resources/js/chat/client/keyring.js';
import { MessageState, Timeline, measureDraft, openMessage, sealMessage } from '../../../resources/js/chat/client/messages.js';
import { parseRoute } from '../../../resources/js/chat/ui/route.js';
import { createIdentity, exportPublicKeys, lockIdentity, WrongPassphraseError } from '../../../resources/js/chat/index.js';

const FAST_KDF = { opslimit: 1, memlimit: 8192 };
const DAY = 24 * 60 * 60 * 1000;

/** A user with an unlocked identity in a session, plus the PublicUser the server would return. */
async function member(id) {
    const session = new ChatSession(id);
    const identity = await createIdentity();
    const publicKeys = await exportPublicKeys(identity);
    session.unlock(identity);
    return { session, publicUser: { id, username: `user${id}`, display_name: `User ${id}`, public_keys: publicKeys, key_changed_at: null } };
}

/** In-memory stand-in for the parts of ChatApi the keyring uses. */
function fakeApi(users) {
    const grants = [];
    return {
        grants,
        publicKeys: async () => ({ data: users }),
        keyGrants: async () => ({ data: JSON.parse(JSON.stringify(grants)) }),
        storeKeyGrants: async (id, version, batch) => { grants.push(...batch); return { data: {} }; },
        activateKey: async (id, version) => ({ data: { ...conversationFor(users, version), rotation_required: false } }),
    };
}

function conversationFor(users, keyVersion = 1) {
    return {
        id: '42', type: 'group', title: null, created_by: users[0].id, lifetime_days: 7, key_version: keyVersion,
        rotation_required: true, server_time: new Date().toISOString().replace(/\.\d{3}Z$/, 'Z'),
        members: users.map((user, index) => ({ user_id: user.id, username: user.username, display_name: user.display_name, role: index === 0 ? 'owner' : 'member' })),
    };
}

test('parseRoute maps hashes to screens and rejects odd ids', () => {
    assert.deepEqual(parseRoute(''), { name: 'list' });
    assert.deepEqual(parseRoute('#'), { name: 'list' });
    assert.deepEqual(parseRoute('#neu'), { name: 'new' });
    assert.deepEqual(parseRoute('#c/12'), { name: 'conversation', id: '12' });
    assert.deepEqual(parseRoute('#c/12/info'), { name: 'details', id: '12' });
    assert.deepEqual(parseRoute('#c/0'), { name: 'list' });
    assert.deepEqual(parseRoute('#c/12<script>'), { name: 'list' });
});

test('ServerClock follows the server and computes whole-second expiries', () => {
    let local = Date.parse('2026-10-11T10:00:00.500Z');
    const clock = new ServerClock(() => local);
    clock.sync('2026-10-11T10:05:00Z'); // browser is ~5 minutes behind
    assert.equal(clock.now(), Date.parse('2026-10-11T10:05:00Z'));
    local += 1000;
    assert.equal(clock.expiryFor(7).toISOString(), '2026-10-18T10:05:01.000Z');
    clock.sync('kaputt');
    assert.equal(clock.now(), Date.parse('2026-10-11T10:05:01Z'));
});

test('setUpIdentity publishes a locked identity and unlockStoredIdentity reopens it', async () => {
    let stored = null;
    const api = { saveIdentity: async (locked, publicKeys) => { stored = { locked_identity: locked, public_keys: publicKeys }; return { data: stored }; } };
    const session = new ChatSession('1');
    // Production KDF costs are used here on purpose: the page must work with them (takes ~1 s).
    await setUpIdentity(api, session, 'Palme Regen Kompass Tee');
    assert.ok(session.unlocked);
    assert.equal(stored.locked_identity.kdf.memlimit, 268435456);

    const fresh = new ChatSession('1');
    await assert.rejects(unlockStoredIdentity(fresh, stored, 'falsch falsch falsch'), WrongPassphraseError);
    assert.equal(fresh.unlocked, false);
    await unlockStoredIdentity(fresh, stored, 'Palme Regen Kompass Tee');
    assert.ok(fresh.unlocked);
});

test('unlockStoredIdentity refuses a server that publishes foreign public keys', async () => {
    const own = await createIdentity();
    const foreign = await createIdentity();
    const stored = { locked_identity: await lockIdentity(own, 'richtige passphrase', FAST_KDF), public_keys: await exportPublicKeys(foreign) };
    const session = new ChatSession('1');
    await assert.rejects(unlockStoredIdentity(session, stored, 'richtige passphrase'), PublishedKeysMismatchError);
    assert.equal(session.unlocked, false);
});

test('owner distributes a key that every member opens; messages round-trip between them', async () => {
    const owner = await member('1');
    const alice = await member('2');
    const users = [owner.publicUser, alice.publicUser];
    const api = fakeApi(users);
    const conversation = conversationFor(users);

    await loadDirectory(api, owner.session, '42');
    const activated = await distributeKey(api, owner.session, conversation);
    assert.equal(activated.rotation_required, false);
    assert.equal(api.grants.length, 2);

    await loadDirectory(api, alice.session, '42');
    assert.deepEqual(await openGrants(api, alice.session, conversation), [1]);

    const clock = new ServerClock();
    clock.sync(conversation.server_time);
    const envelope = await sealMessage(alice.session, activated, clock, 'Hallo 🌴');
    assert.ok(Date.parse(envelope.expires_at) - clock.now() > 7 * DAY - 2000);

    const item = await openMessage(owner.session, '42', JSON.parse(JSON.stringify(envelope)), clock.now());
    assert.equal(item.state, MessageState.READABLE);
    assert.equal(item.plaintext, 'Hallo 🌴');
    assert.equal(item.senderId, '2');
});

test('grants not signed by the owner are ignored', async () => {
    const owner = await member('1');
    const alice = await member('2');
    const mallory = await member('3');
    const users = [owner.publicUser, alice.publicUser, mallory.publicUser];
    const api = fakeApi(users);
    // Mallory (a member, not the owner) hands out a key – in this stage only the owner may.
    await loadDirectory(api, mallory.session, '42');
    await distributeKey(api, mallory.session, { ...conversationFor(users), created_by: '3' });

    await loadDirectory(api, alice.session, '42');
    assert.deepEqual(await openGrants(api, alice.session, conversationFor(users)), []);
});

test('distributeKey refuses when a member has no verifiable keys', async () => {
    const owner = await member('1');
    const alice = await member('2');
    const broken = { ...alice.publicUser, public_keys: { ...alice.publicUser.public_keys, binding: owner.publicUser.public_keys.binding } };
    const api = fakeApi([owner.publicUser, broken]);
    const directory = await loadDirectory(api, owner.session, '42');
    assert.deepEqual(directory.invalid, ['2']);
    await assert.rejects(distributeKey(api, owner.session, conversationFor([owner.publicUser, broken])), MissingMemberKeysError);
});

test('a changed signing key within the tab is reported; safety numbers are symmetric', async () => {
    const owner = await member('1');
    const alice = await member('2');
    const users = [owner.publicUser, alice.publicUser];
    await loadDirectory(fakeApi(users), owner.session, '42');
    await loadDirectory(fakeApi([owner.publicUser]), alice.session, '42');
    assert.equal(await safetyNumber(owner.session, '2'), await safetyNumber(alice.session, '1'));

    const replaced = await member('2');
    const directory = await loadDirectory(fakeApi([owner.publicUser, replaced.publicUser]), owner.session, '42');
    assert.deepEqual(directory.changedInTab, ['2']);
});

test('openMessage marks missing keys and unknown senders, drops expired messages', async () => {
    const owner = await member('1');
    const users = [owner.publicUser];
    const api = fakeApi(users);
    await loadDirectory(api, owner.session, '42');
    const conversation = await distributeKey(api, owner.session, conversationFor(users));
    const clock = new ServerClock();
    const envelope = await sealMessage(owner.session, conversation, clock, 'Test');

    const outsider = await member('9');
    assert.equal((await openMessage(outsider.session, '42', envelope, clock.now())).state, MessageState.NO_KEY);
    assert.equal((await openMessage(owner.session, '42', { ...envelope, sender_id: '5' }, clock.now())).state, MessageState.UNVERIFIABLE);
    assert.equal((await openMessage(owner.session, '43', envelope, clock.now())).state, MessageState.NO_KEY);
    assert.equal(await openMessage(owner.session, '42', envelope, clock.now() + 8 * DAY), null);
});

test('Timeline de-duplicates by signature, keeps order and forgets expired plaintext', () => {
    const timeline = new Timeline();
    const item = (signature, expiresAt) => ({ signature, expiresAt, plaintext: signature });
    assert.ok(timeline.append(item('b', 2000)));
    assert.equal(timeline.append(item('b', 2000)), false);
    assert.equal(timeline.prepend([item('a', 1000), item('b', 2000)]), 1);
    assert.deepEqual(timeline.items.map((entry) => entry.signature), ['a', 'b']);
    const expired = timeline.items[0];
    assert.ok(timeline.dropExpired(1500));
    assert.equal(expired.plaintext, null);
    assert.deepEqual(timeline.items.map((entry) => entry.signature), ['b']);
});

test('ChatSession.lock wipes identity and conversation keys', async () => {
    const { session } = await member('1');
    const key = new Uint8Array(32).fill(7);
    session.rememberConversationKey('42', 1, key);
    const privateKey = session.identity.signingKeyPair.privateKey;
    session.lock();
    assert.equal(session.unlocked, false);
    assert.ok(key.every((byte) => byte === 0));
    assert.ok(privateKey.every((byte) => byte === 0));
    assert.equal(session.conversationKey('42', 1), null);
});

test('measureDraft enforces characters and UTF-8 bytes', () => {
    assert.equal(measureDraft('a'.repeat(4000)).tooLong, false);
    assert.equal(measureDraft('a'.repeat(4001)).tooLong, true);
    assert.equal(measureDraft('🌴'.repeat(1497)).tooLong, true);
});
