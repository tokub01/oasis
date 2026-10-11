/**
 * Oasis chat client – realtime transport (Pusher Channels via Laravel Echo).
 *
 * What: subscribes to the private channel `private-conversation.{id}` and forwards the two events of the contract:
 * `.MessageSent` (the envelope itself) and `.KeyRotated` (`{conversation_id, key_version, rotation_required}`).
 * Pusher only ever sees ciphertext envelopes and these ids.
 *
 * Channel authorization goes through `POST /broadcasting/auth` with the web session and CSRF token.
 * Without a configured Pusher key (local development without credentials) realtime is disabled and the UI falls
 * back to reloading history.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export class Realtime {
    /**
     * @param {object} options
     * @param {string|undefined} options.key Pusher app key (public)
     * @param {string|undefined} options.cluster Pusher cluster, e.g. "eu"
     * @param {string} options.csrfToken
     */
    constructor({ key, cluster, csrfToken }) {
        this.echo = key
            ? new Echo({
                broadcaster: 'pusher',
                key,
                cluster,
                forceTLS: true,
                Pusher,
                authEndpoint: '/broadcasting/auth',
                auth: { headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' } },
            })
            : null;
        this.current = null;
    }

    /** @returns {boolean} whether live updates are available */
    get enabled() {
        return this.echo !== null;
    }

    /**
     * Listen to one conversation; leaves the previously watched one.
     *
     * @param {string} conversationId
     * @param {{onMessage: (envelope: object) => void, onKeyRotated: (event: object) => void}} handlers
     */
    watch(conversationId, { onMessage, onKeyRotated }) {
        this.leave();
        if (!this.echo) {
            return;
        }
        this.current = `conversation.${conversationId}`;
        this.echo.private(this.current)
            .listen('.MessageSent', onMessage)
            .listen('.KeyRotated', onKeyRotated);
    }

    leave() {
        if (this.echo && this.current) {
            this.echo.leave(this.current);
        }
        this.current = null;
    }
}
