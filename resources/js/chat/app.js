/**
 * Oasis chat – page entry (Vite input), mounted on [data-oasis-chat] in resources/views/app/chat/index.blade.php.
 *
 * What: builds the shared context (API, in-memory session, server clock, realtime, screens) and routes by URL hash:
 *   ""            conversation list         "#neu"           new chat
 *   "#c/{id}"     one conversation          "#c/{id}/info"   details & safety numbers
 * Until the identity is unlocked, every route shows "setup" (no keys yet) or "unlock".
 *
 * Security notes:
 * - Keys and plaintext live only in memory (ChatSession, Timeline). They are wiped on "Sperren", on `pagehide`
 *   (tab closed or navigated away) and after 30 minutes without interaction.
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

const root = document.querySelector('[data-oasis-chat]');
if (root) {
    start(root);
}

function start(root) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const screens = Object.fromEntries(
        [...root.querySelectorAll('[data-screen]')].map((section) => [section.dataset.screen, section]),
    );
    const announcer = root.querySelector('[data-chat-announcer]');
    const errorBox = root.querySelector('[data-chat-error]');
    let announceTimer = null;

    const ctx = {
        root,
        screens,
        api: new ChatApi({ baseUrl: root.dataset.api, csrfToken }),
        session: new ChatSession(root.dataset.userId),
        clock: new ServerClock(),
        realtime: new Realtime({
            key: import.meta.env.VITE_PUSHER_APP_KEY,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
            csrfToken,
        }),
        /** Identity from GET /identity: null until the chat is set up. */
        storedIdentity: null,

        /** Show exactly one screen and move focus to its heading for keyboard and screen-reader users. */
        show(name) {
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
            announcer.textContent = text;
            announcer.hidden = false;
            clearTimeout(announceTimer);
            announceTimer = setTimeout(() => { announcer.hidden = true; }, ANNOUNCE_MS);
        },
        fail(text) {
            errorBox.textContent = text;
            errorBox.hidden = false;
        },
        navigate(path) {
            const hash = path === '' ? '' : `#${path}`;
            if (location.hash === hash || (hash === '' && location.hash === '#')) {
                route();
            } else {
                location.hash = hash;
            }
        },
        lock() {
            view.close();
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
            new: () => create.open(),
            conversation: () => view.open(target.id),
            details: () => details.open(target.id),
        })[target.name]();
    }

    bindIdentityScreens(ctx, route);
    window.addEventListener('hashchange', route);

    // Wipe keys when the tab is closed or left; bfcache restores then show the unlock screen again.
    window.addEventListener('pagehide', () => {
        view.close();
        ctx.session.lock();
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            route();
        }
    });

    let idleTimer = null;
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
            ctx.storedIdentity = data;
            route();
        })
        .catch((error) => ctx.fail(describeError(error)));
}
