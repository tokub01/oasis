/**
 * Oasis chat crypto – libsodium loader and memory hygiene.
 *
 * What: the single place that imports `libsodium-wrappers-sumo` and waits for its WebAssembly module to be ready.
 * Every other module of the chat crypto core obtains libsodium through `getSodium()` so that there is exactly one
 * import site to audit (and to pin with Subresource Integrity later).
 *
 * Why the "sumo" build: the standard `libsodium-wrappers` build does not contain `crypto_pwhash` (Argon2id), which we
 * need to derive the key that protects the identity private keys from the chat passphrase.
 *
 * Markup / DOM: none. This is a framework-free ES module library without UI, network access or persistence.
 * Behaviour without JS: not applicable – the encrypted chat cannot work without JavaScript.
 *
 * Security notes:
 * - Nothing in this library persists data anywhere (no browser storage APIs, no cookies). Keys only ever live in memory
 *   and are handed back to the caller, who is responsible for wiping them (`wipe`) when they are no longer needed.
 * - `wipe` can only clear `Uint8Array` buffers. JavaScript strings (for example the passphrase as typed) are immutable
 *   and cannot be wiped; callers should keep them short-lived.
 */

import sodiumModule from 'libsodium-wrappers-sumo';

/** @type {Promise<typeof sodiumModule> | null} */
let readyPromise = null;

/** True once `sodium.ready` has resolved, so the synchronous `wipe` can use `sodium.memzero`. */
let isReady = false;

/**
 * Load libsodium (once) and resolve with the initialised library object.
 *
 * @returns {Promise<typeof sodiumModule>} the ready libsodium wrapper object
 */
export function getSodium() {
    if (readyPromise === null) {
        readyPromise = sodiumModule.ready.then(() => {
            isReady = true;
            return sodiumModule;
        });
    }
    return readyPromise;
}

/**
 * Overwrite a buffer holding secret material with zeros.
 *
 * Use it for temporary secrets (derived keys, serialised private keys, plaintext bytes) as soon as they are no longer
 * needed, and for keys handed out by this library once the caller is done with them.
 *
 * Security note: this reduces the time secrets sit in memory; it cannot guarantee the JS engine never made a copy
 * (garbage-collected intermediate buffers, strings). It is defence in depth, not a guarantee.
 *
 * @param {Uint8Array | null | undefined} buffer buffer to clear; `null`/`undefined` are ignored for convenience
 * @returns {void}
 */
export function wipe(buffer) {
    if (!(buffer instanceof Uint8Array)) {
        return;
    }
    if (isReady) {
        sodiumModule.memzero(buffer);
    } else {
        buffer.fill(0);
    }
}
