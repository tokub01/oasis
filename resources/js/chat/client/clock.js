/**
 * Oasis chat client – server clock.
 *
 * What: keeps the offset between the browser clock and the server clock (`Conversation.server_time`) and computes the
 * signed expiry of a new message. The API only accepts an expiry within ±120 s of server time + lifetime, so a
 * browser whose clock is minutes off would otherwise be unable to send.
 *
 * Pure module: no DOM, no network; testable in node.
 */

const DAY_MS = 24 * 60 * 60 * 1000;

/**
 * Clock that follows the server. Call `sync` with every fresh `server_time`.
 */
export class ServerClock {
    /** @param {() => number} [localNow] browser time in epoch ms; injectable for tests */
    constructor(localNow = () => Date.now()) {
        this.localNow = localNow;
        this.offsetMs = 0;
    }

    /**
     * Adopt the server time of a response.
     *
     * @param {string} serverTime UTC string `YYYY-MM-DDTHH:mm:ssZ`
     * @returns {void}
     */
    sync(serverTime) {
        const parsed = Date.parse(serverTime);
        if (Number.isFinite(parsed)) {
            this.offsetMs = parsed - this.localNow();
        }
    }

    /** @returns {number} the current server time in epoch ms */
    now() {
        return this.localNow() + this.offsetMs;
    }

    /**
     * Expiry for a message sent now in a conversation with the given lifetime, rounded down to whole seconds.
     *
     * @param {number} lifetimeDays 1, 7 or 30
     * @returns {Date}
     */
    expiryFor(lifetimeDays) {
        const ms = this.now() + lifetimeDays * DAY_MS;
        return new Date(Math.floor(ms / 1000) * 1000);
    }
}
