/**
 * Oasis chat client – identity and key management on top of the API.
 *
 * What:
 * - set up a new chat identity (create, lock with the passphrase, publish) and unlock the stored one;
 * - load and verify the public keys of a conversation's members (key directory);
 * - open the key grants addressed to the caller and remember the conversation keys;
 * - as owner: create a fresh conversation key, grant it to every active member and activate the version.
 *
 * Follows the rotation procedure in docs/oasis-chat.md. No DOM.
 *
 * Security notes:
 * - Only grants signed by the conversation owner (`created_by`) are accepted: in this stage only the owner hands out
 *   keys, so a grant from any other member is treated as an attack by the server.
 * - Public-key bundles are verified with `verifyPublicKeys`; a member whose bundle fails verification gets no key.
 * - Whether a signing key really belongs to the person is decided by comparing safety numbers (see `safetyNumber`).
 */

import {
    InvalidKeyGrantError,
    InvalidPublicKeysError,
    createConversationKey,
    createIdentity,
    createKeyGrant,
    exportPublicKeys,
    fingerprint,
    lockIdentity,
    openKeyGrant,
    unlockIdentity,
    verifyPublicKeys,
    wipeIdentity,
} from '../index.js';

/** Minimum length of a chat passphrase; Argon2id slows guessing, a long passphrase is still the main defence. */
export const MIN_PASSPHRASE_LENGTH = 12;

/** The owner could not distribute a key because a member has no valid public keys. */
export class MissingMemberKeysError extends Error {
    /** @param {string[]} usernames members without verifiable keys */
    constructor(usernames) {
        super(`No verifiable chat keys for: ${usernames.join(', ')}`);
        this.name = 'MissingMemberKeysError';
        this.usernames = usernames;
    }
}

/** The server's published keys do not match the identity that was just unlocked. */
export class PublishedKeysMismatchError extends Error {
    constructor() {
        super('The published public keys do not belong to this identity.');
        this.name = 'PublishedKeysMismatchError';
    }
}

/**
 * Create a new identity, lock it with the passphrase and publish it. Replaces an existing identity (forgotten
 * passphrase): old messages become unreadable and every conversation needs a new key.
 *
 * @param {import('./api.js').ChatApi} api
 * @param {import('./session.js').ChatSession} session receives the unlocked identity
 * @param {string} passphrase
 * @returns {Promise<object>} the stored Identity from the API
 */
export async function setUpIdentity(api, session, passphrase) {
    const identity = await createIdentity();
    try {
        const locked = await lockIdentity(identity, passphrase);
        const response = await api.saveIdentity(locked, await exportPublicKeys(identity));
        session.unlock(identity);
        return response.data;
    } catch (error) {
        wipeIdentity(identity);
        throw error;
    }
}

/**
 * Unlock the stored identity with the passphrase and check that the published bundle belongs to it.
 *
 * @param {import('./session.js').ChatSession} session
 * @param {object} storedIdentity Identity from `GET /identity`
 * @param {string} passphrase
 * @returns {Promise<void>}
 * @throws {import('../errors.js').WrongPassphraseError} on a wrong passphrase
 * @throws {PublishedKeysMismatchError} if the server publishes other public keys than the locked blob contains
 */
export async function unlockStoredIdentity(session, storedIdentity, passphrase) {
    const identity = await unlockIdentity(storedIdentity.locked_identity, passphrase);
    try {
        const published = await verifyPublicKeys(storedIdentity.public_keys);
        if (!equalBytes(published.signing, identity.signingKeyPair.publicKey)
            || !equalBytes(published.encryption, identity.encryptionKeyPair.publicKey)) {
            throw new PublishedKeysMismatchError();
        }
    } catch (error) {
        wipeIdentity(identity);
        throw error instanceof InvalidPublicKeysError ? new PublishedKeysMismatchError() : error;
    }
    session.unlock(identity);
}

/**
 * Load the public keys of all (also past) members of a conversation, verify them and remember them in the session.
 *
 * @returns {Promise<{users: object[], changedInTab: string[], invalid: string[]}>} the PublicUser list, the ids whose
 *          signing key changed while this tab was open, and the ids whose bundle failed verification
 */
export async function loadDirectory(api, session, conversationId) {
    const { data: users } = await api.publicKeys(conversationId);
    const changedInTab = [];
    const invalid = [];
    for (const user of users) {
        if (!user.public_keys) {
            continue;
        }
        try {
            const keys = await verifyPublicKeys(user.public_keys);
            if (session.rememberPublicKeys(user.id, keys, user.key_changed_at)) {
                changedInTab.push(user.id);
            }
        } catch {
            invalid.push(user.id);
        }
    }
    return { users, changedInTab, invalid };
}

/**
 * Open every grant addressed to the caller and remember the keys. Grants that fail verification are skipped.
 *
 * @param {object} conversation Conversation from the API
 * @returns {Promise<number[]>} the key versions now available
 */
export async function openGrants(api, session, conversation) {
    const owner = session.publicKeysOf(conversation.created_by);
    if (!owner) {
        return [];
    }
    const { data: grants } = await api.keyGrants(conversation.id);
    const versions = [];
    for (const grant of grants) {
        if (session.conversationKey(conversation.id, grant.key_version)) {
            versions.push(grant.key_version);
            continue;
        }
        try {
            const opened = await openKeyGrant({ grant, identity: session.identity, granterSigningPublicKey: owner.signing });
            if (opened.conversationId !== conversation.id || opened.recipientId !== session.userId
                || opened.granterId !== conversation.created_by) {
                throw new InvalidKeyGrantError();
            }
            session.rememberConversationKey(conversation.id, opened.keyVersion, opened.conversationKey);
            versions.push(opened.keyVersion);
        } catch (error) {
            if (!(error instanceof InvalidKeyGrantError)) {
                throw error;
            }
        }
    }
    return versions;
}

/**
 * Owner: create a new random key for the current version, grant it to every active member (including the owner)
 * and activate it. Call after `loadDirectory` so the members' keys are verified.
 *
 * @param {object} conversation Conversation from the API with `rotation_required: true`
 * @returns {Promise<object>} the activated Conversation
 * @throws {MissingMemberKeysError} if a member has no verified public keys
 */
export async function distributeKey(api, session, conversation) {
    const missing = conversation.members.filter((member) => !session.publicKeysOf(member.user_id));
    if (missing.length > 0) {
        throw new MissingMemberKeysError(missing.map((member) => member.username));
    }
    const key = await createConversationKey();
    const grants = [];
    for (const member of conversation.members) {
        grants.push(await createKeyGrant({
            conversationKey: key,
            conversationId: conversation.id,
            keyVersion: conversation.key_version,
            recipientId: member.user_id,
            recipientEncryptionPublicKey: session.publicKeysOf(member.user_id).encryption,
            granterId: session.userId,
            identity: session.identity,
        }));
    }
    await api.storeKeyGrants(conversation.id, conversation.key_version, grants);
    const { data } = await api.activateKey(conversation.id, conversation.key_version);
    session.rememberConversationKey(conversation.id, conversation.key_version, key);
    return data;
}

/**
 * Safety number between the caller and another member, or null if a key is unknown.
 *
 * @returns {Promise<string|null>} 12 groups of 5 digits
 */
export async function safetyNumber(session, userId) {
    const other = session.publicKeysOf(userId);
    if (!other || !session.identity) {
        return null;
    }
    return fingerprint(session.identity.signingKeyPair.publicKey, other.signing);
}

function equalBytes(a, b) {
    return a.length === b.length && a.every((byte, index) => byte === b[index]);
}
