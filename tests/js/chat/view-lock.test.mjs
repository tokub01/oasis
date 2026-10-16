/**
 * Tests for resources/js/chat/ui/conversation-view.js – locking/closing must remove plaintext from the page, not
 * only from memory (stage 9, B4). Runs without a browser: a small fake DOM provides the elements the view uses.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import { ChatSession } from '../../../resources/js/chat/client/session.js';
import { ConversationView } from '../../../resources/js/chat/ui/conversation-view.js';

/** A fake element with the few DOM features the view touches. */
function element() {
    const children = [];
    return {
        children,
        textContent: '',
        value: '',
        disabled: false,
        href: '',
        hidden: false,
        classList: { add() {}, toggle() {} },
        addEventListener() {},
        append(...nodes) { children.push(...nodes); },
        replaceChildren(...nodes) { children.splice(0, children.length, ...nodes); },
        setAttribute() {},
    };
}

function fakeScreen() {
    const elements = {};
    const message = element();
    const composer = element();
    composer.elements = { message, [Symbol.iterator]: function* () { yield message; } };
    composer.querySelector = (selector) => (elements[`composer ${selector}`] ??= element());
    return {
        querySelector(selector) {
            if (selector === '[data-composer]') {
                return composer;
            }
            return (elements[selector] ??= element());
        },
        elements,
        message,
    };
}

test('close() removes rendered messages, notices and the unsent draft from the page', () => {
    const screen = fakeScreen();
    const ctx = { screens: { conversation: screen }, realtime: { leave() {} }, session: new ChatSession('1') };
    const view = new ConversationView(ctx);

    view.messages.append({ textContent: 'geheimer Klartext' });
    view.notices.append({ textContent: 'Hinweis' });
    screen.message.value = 'ungesendeter Entwurf';
    view.title.textContent = 'Chat mit Mira';

    view.close();

    assert.equal(view.messages.children.length, 0);
    assert.equal(view.notices.children.length, 0);
    assert.equal(screen.message.value, '');
    assert.equal(view.title.textContent, '');
});

test('results of requests started before close() are ignored', () => {
    const ctx = { screens: { conversation: fakeScreen() }, realtime: { leave() {} }, session: new ChatSession('1') };
    const view = new ConversationView(ctx);
    const token = view.openCount;
    view.close();
    assert.equal(view.isCurrent(token), false);
});

test('bindChatNavigation starts once on livewire:navigated, cleans up on livewire:navigating and re-mounts on return', async () => {
    const { bindChatNavigation } = await import('../../../resources/js/chat/app.js');

    let currentRoot = { dataset: {} };
    const docListeners = {};
    const fakeDoc = {
        querySelector(selector) {
            return selector === '[data-oasis-chat]' ? currentRoot : null;
        },
        addEventListener(event, fn) {
            docListeners[event] = fn;
        },
    };

    let mounts = 0;
    let stops = 0;
    bindChatNavigation(fakeDoc, (mountedRoot) => {
        mounts += 1;
        return () => {
            stops += 1;
            delete mountedRoot.dataset.oasisChatStarted;
        };
    });

    // Mounted on initial load.
    assert.equal(mounts, 1);
    assert.equal(currentRoot.dataset.oasisChatStarted, 'true');

    // livewire:navigated firing on the same DOM element must not double-initialize.
    docListeners['livewire:navigated']();
    assert.equal(mounts, 1);

    // Navigating away via wire:navigate tears down the active chat instance.
    docListeners['livewire:navigating']();
    assert.equal(stops, 1);
    assert.equal(currentRoot.dataset.oasisChatStarted, undefined);

    // Navigating to a non-chat page does nothing.
    currentRoot = null;
    docListeners['livewire:navigated']();
    assert.equal(mounts, 1);

    // Navigating back to /app/nachrichten mounts the new [data-oasis-chat] root cleanly.
    currentRoot = { dataset: {} };
    docListeners['livewire:navigated']();
    assert.equal(mounts, 2);
    assert.equal(currentRoot.dataset.oasisChatStarted, 'true');
});


test('the real chat teardown clears timers listeners password inputs and the realtime transport', async () => {
    const { start } = await import('../../../resources/js/chat/app.js');
    const { ChatApi } = await import('../../../resources/js/chat/client/api.js');
    const { Realtime } = await import('../../../resources/js/chat/client/realtime.js');
    const old = { document: globalThis.document, window: globalThis.window, setTimeout: globalThis.setTimeout, clearTimeout: globalThis.clearTimeout, identity: ChatApi.prototype.identity, disconnect: Realtime.prototype.disconnect };
    const listeners = new Map();
    const timers = new Set();
    let socketsClosed = 0;
    const password = { value: 'private passphrase' };
    const screens = ['loading', 'setup', 'unlock', 'list', 'new', 'conversation', 'details'].map((name) => {
        const screen = stubNode();
        screen.dataset.screen = name;
        return screen;
    });
    const root = stubNode();
    root.dataset = { userId: '1', api: '/app/chat/api', oasisChatStarted: 'true' };
    root.querySelectorAll = (selector) => selector === '[data-screen]' ? screens : [password];

    try {
        globalThis.document = { querySelector: () => null };
        globalThis.window = {
            addEventListener: (event, handler) => listeners.set(event, handler),
            removeEventListener: (event, handler) => { if (listeners.get(event) === handler) listeners.delete(event); },
        };
        globalThis.setTimeout = () => { const timer = {}; timers.add(timer); return timer; };
        globalThis.clearTimeout = (timer) => timers.delete(timer);
        ChatApi.prototype.identity = () => new Promise(() => {});
        Realtime.prototype.disconnect = () => { socketsClosed++; };

        const stop = start(root);
        assert.equal(listeners.size, 5);
        assert.equal(timers.size, 1);
        stop();
        stop();

        assert.equal(listeners.size, 0);
        assert.equal(timers.size, 0);
        assert.equal(socketsClosed, 1);
        assert.equal(password.value, '');
        assert.equal(root.dataset.oasisChatStarted, undefined);
    } finally {
        globalThis.document = old.document;
        globalThis.window = old.window;
        globalThis.setTimeout = old.setTimeout;
        globalThis.clearTimeout = old.clearTimeout;
        ChatApi.prototype.identity = old.identity;
        Realtime.prototype.disconnect = old.disconnect;
    }
});

/** Minimal DOM surface used by the actual chat constructors; no transport or cryptography is replaced. */
function stubNode() {
    const selectors = new Map();
    const node = element();
    node.dataset = {};
    node.querySelector = (selector) => {
        if (!selectors.has(selector)) selectors.set(selector, stubNode());
        return selectors.get(selector);
    };
    node.querySelectorAll = () => [];
    const controls = new Map();
    node.elements = new Proxy({}, {
        get(target, name) {
            if (name === Symbol.iterator) return function* () { yield* controls.values(); };
            if (name === 'type') return [];
            if (!controls.has(name)) controls.set(name, element());
            return controls.get(name);
        },
    });
    node.reset = () => {};
    return node;
}
