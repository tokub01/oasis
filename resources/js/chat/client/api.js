/**
 * Oasis chat client – HTTP API.
 *
 * What: thin wrapper around the JSON API documented in docs/oasis-chat.md (`/app/chat/api/*`). One method per route;
 * no crypto, no DOM. Requests use the web session cookie and send the CSRF token on writes.
 *
 * Security notes: responses are never cached by the browser (`cache: 'no-store'`); nothing is written to any storage.
 */

/** A non-2xx response. `status` lets callers react to 403 (removed), 409 (rotation pending) and 422 (invalid). */
export class ApiError extends Error {
    /**
     * @param {number} status HTTP status
     * @param {object|null} body parsed JSON error body, if any
     */
    constructor(status, body) {
        super(body?.message ?? `Request failed with status ${status}.`);
        this.name = 'ApiError';
        this.status = status;
        this.body = body;
    }
}

export class ChatApi {
    /**
     * @param {object} options
     * @param {string} options.baseUrl absolute or root-relative prefix, e.g. "/app/chat/api"
     * @param {string} options.csrfToken token from `<meta name="csrf-token">`
     * @param {typeof fetch} [options.fetchImpl] injectable for tests
     */
    constructor({ baseUrl, csrfToken, fetchImpl = (...args) => fetch(...args) }) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.csrfToken = csrfToken;
        this.fetchImpl = fetchImpl;
    }

    /**
     * @param {string} method
     * @param {string} path path after the prefix, starting with "/"
     * @param {object} [body] JSON body for writes
     * @returns {Promise<object>} parsed JSON
     * @throws {ApiError} on a non-2xx status
     */
    async request(method, path, body) {
        const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (method !== 'GET') {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-TOKEN'] = this.csrfToken;
        }
        const response = await this.fetchImpl(this.baseUrl + path, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const json = await response.json().catch(() => null);
        if (!response.ok) {
            throw new ApiError(response.status, json);
        }
        return json;
    }

    identity() {
        return this.request('GET', '/identity');
    }

    /** @param {object} lockedIdentity `lockIdentity` output @param {object} publicKeys `exportPublicKeys` output */
    saveIdentity(lockedIdentity, publicKeys) {
        return this.request('PUT', '/identity', { locked_identity: lockedIdentity, public_keys: publicKeys });
    }

    /** Exact username lookup; returns an empty list for unknown or not chat-ready accounts. */
    findUser(username) {
        return this.request('GET', `/users?username=${encodeURIComponent(username)}`);
    }

    conversations(page = 1) {
        return this.request('GET', `/conversations?page=${page}`);
    }

    /** @param {{type: string, title: string|null, member_ids: string[], lifetime_days: number}} input */
    createConversation(input) {
        return this.request('POST', '/conversations', input);
    }

    conversation(id) {
        return this.request('GET', `/conversations/${id}`);
    }

    /** Owner only. @param {{lifetime_days?: number, title?: string|null}} input */
    updateConversation(id, input) {
        return this.request('PATCH', `/conversations/${id}`, input);
    }

    publicKeys(id) {
        return this.request('GET', `/conversations/${id}/public-keys`);
    }

    addMember(id, userId) {
        return this.request('POST', `/conversations/${id}/members`, { user_id: userId });
    }

    removeMember(id, userId) {
        return this.request('DELETE', `/conversations/${id}/members/${userId}`);
    }

    keyGrants(id) {
        return this.request('GET', `/conversations/${id}/key-grants`);
    }

    storeKeyGrants(id, keyVersion, grants) {
        return this.request('POST', `/conversations/${id}/key-grants`, { key_version: keyVersion, grants });
    }

    activateKey(id, keyVersion) {
        return this.request('POST', `/conversations/${id}/rotate`, { key_version: keyVersion });
    }

    messages(id, beforeId = null) {
        return this.request('GET', `/conversations/${id}/messages${beforeId ? `?before_id=${beforeId}` : ''}`);
    }

    sendMessage(id, envelope) {
        return this.request('POST', `/conversations/${id}/messages`, { envelope });
    }

    /** Consent-based report: the reporter forwards one decrypted message to moderation. */
    reportMessage(id, envelope, plaintext, reason) {
        return this.request('POST', `/conversations/${id}/reports`, { envelope, plaintext, reason });
    }
}
