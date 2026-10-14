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
