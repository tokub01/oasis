/**
 * Oasis chat – page entry (Vite input), mounted on [data-oasis-chat] in resources/views/app/chat/index.blade.php.
 *
 * What: builds the shared context (API, in-memory session, server clock, realtime, screens) and routes by URL hash:
 *   ""            conversation list         "#neu"           new chat
 *   "#c/{id}"     one conversation          "#c/{id}/info"   details & safety numbers
 * Until the identity is unlocked, every route shows "setup" (no keys yet) or "unlock".
 *
 * Livewire SPA navigation (`wire:navigate`):
 * - Starts on initial load and on `livewire:navigated`, guarded by `data-oasis-chat-started` against double init.
 * - Cleans up on `livewire:navigating` (wipes keys, closes active conversation/dialogs, disconnects Reverb WebSocket,
 *   clears timers, and detaches window listeners).
 *
 * Security notes:
 * - Keys and plaintext live only in memory (ChatSession, Timeline). They are wiped on "Sperren", on `livewire:navigating`,
 *   on `pagehide` (tab closed or navigated away) and after 30 minutes without interaction.
 * - The crypto core is in resources/js/chat/*.js; this layer never handles raw key material itself.
 */

import { ChatApi } from './client/api.js';
import { ServerClock } from './client/clock.js';
import { Realtime } from './client/realtime.js';
import { ChatSession } from './client/session.js';
import { ConversationDetails } from './ui/conversation-details.js';
import { ConversationList } from './ui/conversation-list.js';
import { ConversationView } from './ui/conversation-view.js';
import { bindIdentityScreens } from './ui/identity-screens.js';
import { NewConversation } from './ui/new-conversation.js';
import { ReportDialog } from './ui/report-dialog.js';
import { describeError } from './ui/errors.js';
import { parseRoute } from './ui/route.js';

const IDLE_LOCK_MS = 30 * 60 * 1000;
const ANNOUNCE_MS = 6000;

/**
 * Bind the chat lifecycle to a document/window pair for `wire:navigate` SPA navigation.
 *
 * @param {Document} [doc]
 * @param {(root: HTMLElement) => (() => void)} [mount]
 */
export function bindChatNavigation(doc = document, mount = start) {
    let stopCurrent = null;

    const mountIfPresent = () => {
        const root = doc.querySelector('[data-oasis-chat]');
        if (!root || root.dataset.oasisChatStarted === 'true') {
            return;
        }
        root.dataset.oasisChatStarted = 'true';
        stopCurrent = mount(root);
    };

    const unmountCurrent = () => {
        if (stopCurrent) {
            stopCurrent();
            stopCurrent = null;
        }
    };

    doc.addEventListener('livewire:navigated', mountIfPresent);
    doc.addEventListener('livewire:navigating', unmountCurrent);
    mountIfPresent();

    return { mountIfPresent, unmountCurrent };
}

/**
 * Mount the chat application on the `[data-oasis-chat]` root element and return a teardown callback.
 *
 * @param {HTMLElement} root
 * @returns {() => void} stop function that wipes keys and releases timers, sockets, and window listeners
 */
export function start(root) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const screens = Object.fromEntries(
        [...root.querySelectorAll('[data-screen]')].map((section) => [section.dataset.screen, section]),
    );
    const announcer = root.querySelector('[data-chat-announcer]');
    const errorBox = root.querySelector('[data-chat-error]');
    let announceTimer = null;
    let idleTimer = null;
    let stopped = false;

    const ctx = {
        root,
        screens,
        api: new ChatApi({ baseUrl: root.dataset.api, csrfToken }),
        session: new ChatSession(root.dataset.userId),
        clock: new ServerClock(),
        realtime: new Realtime({
            key: import.meta.env?.VITE_REVERB_APP_KEY,
            host: import.meta.env?.VITE_REVERB_HOST,
            port: import.meta.env?.VITE_REVERB_PORT,
            scheme: import.meta.env?.VITE_REVERB_SCHEME,
            csrfToken,
        }),
        /** False for read-only accounts (trial ended, no membership): reading only, no new chats or sending. */
        canParticipate: root.dataset.canParticipate === '1',
        /** Word lists for the in-browser safety checks (client/safety.js): {lists, tone}. */
        safety: JSON.parse(root.dataset.safety || '{"lists":{},"tone":{}}'),
        /** Identity from GET /identity: null until the chat is set up. */
        storedIdentity: null,

        /** Show exactly one screen and move focus to its heading for keyboard and screen-reader users. */
        show(name) {
            if (stopped) {
                return;
            }
            for (const [key, section] of Object.entries(screens)) {
                section.hidden = key !== name;
            }
            const heading = screens[name].querySelector('h1');
            if (heading) {
                heading.tabIndex = -1;
                heading.focus({ preventScroll: false });
            }
        },
        announce(text) {
            if (stopped) {
                return;
            }
            announcer.textContent = text;
            announcer.hidden = false;
            clearTimeout(announceTimer);
            announceTimer = setTimeout(() => { announcer.hidden = true; }, ANNOUNCE_MS);
        },
        fail(text) {
            if (stopped) {
                return;
            }
            errorBox.textContent = text;
            errorBox.hidden = false;
        },
        navigate(path) {
            if (stopped) {
                return;
            }
            const hash = path === '' ? '' : `#${path}`;
            if (location.hash === hash || (hash === '' && location.hash === '#')) {
                route();
            } else {
                location.hash = hash;
            }
        },
        lock() {
            if (stopped) {
                return;
            }
            view.close();
            ctx.report.close();
            ctx.session.lock();
            ctx.announce('Nachrichten gesperrt. Deine Schlüssel sind aus dem Speicher gelöscht.');
            route();
        },
    };

    const list = new ConversationList(ctx);
    const create = new NewConversation(ctx);
    const view = new ConversationView(ctx);
    const details = new ConversationDetails(ctx);
    ctx.report = new ReportDialog(ctx);

    function route() {
        if (stopped) {
            return;
        }
        errorBox.hidden = true;
        if (!ctx.session.unlocked) {
            view.close();
            ctx.show(ctx.storedIdentity ? 'unlock' : 'setup');
            return;
        }
        const target = parseRoute(location.hash);
        if (target.name !== 'conversation') {
            view.close();
        }
        ctx.show(target.name);
        ({
            list: () => list.open(),
            new: () => create.open(target.username),
            conversation: () => view.open(target.id),
            details: () => details.open(target.id),
        })[target.name]();
    }

    bindIdentityScreens(ctx, route);
    window.addEventListener('hashchange', route);

    // Wipe keys when the tab is closed or left; bfcache restores then show the unlock screen again.
    const onPageHide = () => {
        view.close();
        ctx.report.close();
        ctx.session.lock();
    };
    const onPageShow = (event) => {
        if (event.persisted) {
            route();
        }
    };
    window.addEventListener('pagehide', onPageHide);
    window.addEventListener('pageshow', onPageShow);

    const resetIdle = () => {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(() => {
            if (ctx.session.unlocked) {
                ctx.lock();
            }
        }, IDLE_LOCK_MS);
    };
    for (const type of ['pointerdown', 'keydown']) {
        window.addEventListener(type, resetIdle, { passive: true });
    }
    resetIdle();

    ctx.api.identity()
        .then(({ data }) => {
            if (stopped) {
                return;
            }
            ctx.storedIdentity = data;
            route();
        })
        .catch((error) => {
            if (!stopped) {
                ctx.fail(describeError(error));
            }
        });

    return () => {
        if (stopped) {
            return;
        }
        stopped = true;
        clearTimeout(announceTimer);
        announceTimer = null;
        clearTimeout(idleTimer);
        idleTimer = null;
        view.close();
        ctx.report.close();
        ctx.session.lock();
        for (const input of root.querySelectorAll('input[type="password"]')) {
            input.value = '';
        }
        ctx.realtime.disconnect();
        window.removeEventListener('hashchange', route);
        window.removeEventListener('pagehide', onPageHide);
        window.removeEventListener('pageshow', onPageShow);
        for (const type of ['pointerdown', 'keydown']) {
            window.removeEventListener(type, resetIdle);
        }
        delete root.dataset.oasisChatStarted;
    };
}

if (typeof document !== 'undefined') {
    bindChatNavigation(document, start);
}
