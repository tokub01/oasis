/**
 * Static hygiene checks for resources/js/chat/ (crypto core, client/ and ui/): nothing may touch browser persistence
 * (keys and plaintext live only in memory), log anything (no secrets in the console) or inject HTML (no XSS through
 * message texts or names).
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const chatDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../resources/js/chat');
const forbidden = ['localStorage', 'sessionStorage', 'indexedDB', 'document.cookie', 'console.'];

test('chat crypto sources exist', () => {
    const files = fs.readdirSync(chatDir).filter((name) => name.endsWith('.js'));
    for (const expected of ['sodium.js', 'keys.js', 'conversation.js', 'errors.js', 'index.js']) {
        assert.ok(files.includes(expected), `${expected} is missing`);
    }
});

/** All chat sources including the client and UI layers (client/, ui/). */
const allChatFiles = fs.readdirSync(chatDir, { recursive: true }).filter((file) => file.endsWith('.js'));

test('chat sources (crypto, client, UI) use no browser storage, cookies or console', () => {
    for (const name of allChatFiles) {
        const source = fs.readFileSync(path.join(chatDir, name), 'utf8');
        for (const token of forbidden) {
            assert.ok(!source.includes(token), `${name} must not contain "${token}"`);
        }
    }
});

test('chat sources never inject HTML (messages and names are set with textContent)', () => {
    for (const name of allChatFiles) {
        const source = fs.readFileSync(path.join(chatDir, name), 'utf8');
        for (const token of ['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write']) {
            assert.ok(!source.includes(token), `${name} must not contain "${token}"`);
        }
    }
});

test('libsodium is imported in exactly one module', () => {
    const importers = fs
        .readdirSync(chatDir)
        .filter((file) => file.endsWith('.js'))
        .filter((file) => fs.readFileSync(path.join(chatDir, file), 'utf8').includes("from 'libsodium-wrappers-sumo'"));
    assert.deepEqual(importers, ['sodium.js']);
});
