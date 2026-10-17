/** Live local E2E: real sessions, production crypto, Echo/Pusher/Reverb; intentionally outside the offline globs. */
import test from 'node:test';
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';
import Pusher from 'pusher-js';
import { ChatApi } from '../../resources/js/chat/client/api.js';
import { ChatSession } from '../../resources/js/chat/client/session.js';
import { ServerClock } from '../../resources/js/chat/client/clock.js';
import { setUpIdentity, unlockStoredIdentity, loadDirectory, distributeKey, openGrants } from '../../resources/js/chat/client/keyring.js';
import { MessageState, sealMessage, openMessage } from '../../resources/js/chat/client/messages.js';
import { Realtime } from '../../resources/js/chat/client/realtime.js';

const root = fileURLToPath(new URL('../../', import.meta.url));
const base = new URL(process.env.CHAT_E2E_URL ?? 'http://localhost');
assert.ok(['localhost', '127.0.0.1', '[::1]'].includes(base.hostname), 'E2E must target the local app');
const browserSessions = new Map();

function fixture(operation, run, data = {}) {
    try {
        return JSON.parse(execFileSync('php', ['tests/e2e/support/chat-fixtures.php'], {
            cwd: root, input: JSON.stringify({ operation, run, ...data }), encoding: 'utf8',
            timeout: 15000, stdio: ['pipe', 'pipe', 'pipe'],
        }));
    } catch {
        throw new Error(`Local PHP fixture ${operation} failed; check Sail, migrations and APP_ENV=local`);
    }
}

/** Same-origin browser transport with an in-memory cookie jar; follows login redirects without losing Set-Cookie. */
class BrowserSession {
    cookies = new Map();
    authStatuses = [];
    chatBodies = [];
    historyReads = 0;

    async fetch(path, init = {}) {
        let url = new URL(path, base);
        for (let redirects = 0; redirects < 6; redirects++) {
            assert.equal(url.origin, base.origin, 'Never forward session cookies to another origin');
            const headers = new Headers(init.headers);
            headers.set('Cookie', [...this.cookies].map(([name, value]) => `${name}=${value}`).join('; '));
            if (url.pathname.endsWith('/messages') && (init.method ?? 'GET') === 'GET') this.historyReads++;
            if (url.pathname.startsWith('/app/chat/api/') && init.body) this.chatBodies.push(String(init.body));
            const response = await fetch(url, { ...init, headers, redirect: 'manual', signal: AbortSignal.timeout(10000) });
            for (const cookie of response.headers.getSetCookie()) {
                const pair = cookie.split(';', 1)[0];
                const equals = pair.indexOf('=');
                this.cookies.set(pair.slice(0, equals), pair.slice(equals + 1));
            }
            if (url.pathname === '/broadcasting/auth') this.authStatuses.push(response.status);
            if (![301, 302, 303, 307, 308].includes(response.status)) return response;
            url = new URL(response.headers.get('location'), url);
            await response.body?.cancel();
            if ([301, 302, 303].includes(response.status)) init = { method: 'GET' };
        }
        throw new Error('Too many login redirects');
    }

    async login(account, password) {
        const page = await this.fetch('/anmelden');
        assert.equal(page.status, 200);
        const token = (await page.text()).match(/name="_token" value="([^"]+)"/)?.[1];
        assert.ok(token, 'Login CSRF token present');
        const signedIn = await this.fetch('/anmelden', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _token: token, login: account.email, password }),
        });
        assert.equal(signedIn.status, 200, 'Login reaches the member app');
        await signedIn.body?.cancel();
        const chat = await this.fetch('/app/nachrichten');
        assert.equal(chat.status, 200);
        this.csrf = (await chat.text()).match(/name="csrf-token" content="([^"]+)"/)?.[1];
        assert.ok(this.csrf, 'Authenticated chat CSRF token present');
        browserSessions.set(this.csrf, this);
        this.api = new ChatApi({ baseUrl: '/app/chat/api', csrfToken: this.csrf, fetchImpl: this.fetch.bind(this) });
    }
}

/** Pusher's Node XHR has no browser cookie jar. Only its HTTP adapter is supplied; authorization stays real. */
class BrowserXHR {
    headers = new Headers();
    open(method, url) { this.method = method; this.url = url; }
    setRequestHeader(name, value) { this.headers.set(name, value); }
    send(body) {
        assert.equal(this.url, '/broadcasting/auth');
        const session = browserSessions.get(this.headers.get('X-CSRF-TOKEN'));
        assert.ok(session, 'Every subscription uses its own signed-in cookie jar');
        session.fetch(this.url, { method: this.method, headers: this.headers, body }).then(async (response) => {
            this.status = response.status;
            this.responseText = await response.text();
            this.readyState = 4;
            this.onreadystatechange?.();
        }).catch(() => {
            this.status = 0;
            this.responseText = '';
            this.readyState = 4;
            this.onreadystatechange?.();
        });
    }
}

async function within(promise, label, milliseconds = 15000) {
    let timer;
    try {
        return await Promise.race([promise, new Promise((_, reject) => {
            timer = setTimeout(() => reject(new Error(`Timed out: ${label}`)), milliseconds);
        })]);
    } finally { clearTimeout(timer); }
}

function watch(realtime, id, onMessage, denied = false) {
    realtime.watch(id, { onMessage, onKeyRotated() {} });
    const channel = realtime.echo.private(`conversation.${id}`);
    return within(new Promise((resolve, reject) => {
        channel.subscribed(() => denied ? reject(new Error('Nonmember subscribed to private channel')) : resolve());
        channel.error((error) => denied ? resolve(error) : reject(new Error(`Subscription failed: ${error.status}`)));
    }), denied ? 'outsider subscription denial' : 'private subscription');
}

test('Mira sends ciphertext through Reverb; Jonas verifies/decrypts it; a nonmember is excluded', { timeout: 90000 }, async (t) => {
    const run = randomBytes(10).toString('hex');
    const password = randomBytes(24).toString('base64url');
    const passphrase = randomBytes(32).toString('base64url');
    const plaintext = `Chat-E2E ${run}: Mira grüßt Jonas über Reverb.`;
    const clients = [];
    const realtime = [];
    const originalXHR = Pusher.Runtime.getXHRAPI;
    let created = false;
    try {
        // Fixture creation is local-only and transactional; cleanup also runs after partial HTTP/crypto failures.
        const setup = fixture('setup', run, { password });
        created = true;
        assert.equal(setup.broadcast_driver, 'reverb', 'App broadcasts to the actual Reverb server');
        assert.equal(setup.broadcast_now, true, 'MessageSent uses ShouldBroadcastNow after DB commit');
        assert.ok(setup.reverb_key, 'Public Reverb key configured');
        t.diagnostic(`Fixtures: three isolated chat-e2e accounts; broadcast=reverb, synchronous after commit; queue=${setup.queue_connection} unused`);
        for (const name of ['mira', 'jonas', 'outsider']) {
            const client = new BrowserSession();
            clients.push(client);
            await client.login(setup.accounts[name], password);
            client.session = new ChatSession(setup.accounts[name].id);
        }
        const [mira, jonas, outsider] = clients;
        for (const client of clients) await setUpIdentity(client.api, client.session, passphrase);
        for (const client of [mira, jonas]) {
            client.session.lock();
            assert.equal(await unlockStoredIdentity(client.session, (await client.api.identity()).data, passphrase), true);
        }
        t.diagnostic('Real logins + CSRF; three chat-ready identities; Mira/Jonas unlocked again with production Argon2id costs');
        const found = await mira.api.findUser(setup.accounts.jonas.username);
        assert.equal(found.data[0].id, jonas.session.userId);
        let { data: conversation } = await mira.api.createConversation({
            type: 'direct', title: null, member_ids: [jonas.session.userId], lifetime_days: 1,
        });
        await loadDirectory(mira.api, mira.session, conversation.id);
        conversation = await distributeKey(mira.api, mira.session, conversation);
        await loadDirectory(jonas.api, jonas.session, conversation.id);
        assert.deepEqual(await openGrants(jonas.api, jonas.session, conversation), [conversation.key_version]);
        t.diagnostic('Conversation created, signed owner grants distributed, Jonas opened his own grant');

        Pusher.Runtime.getXHRAPI = () => BrowserXHR;
        for (const client of clients) {
            const transport = new Realtime({
                key: setup.reverb_key, host: process.env.CHAT_E2E_REVERB_HOST ?? 'reverb',
                port: process.env.CHAT_E2E_REVERB_PORT ?? 8080, scheme: 'http', csrfToken: client.csrf,
            });
            assert.equal(transport.enabled, true);
            realtime.push(transport);
        }
        let receiveJonas;
        let receiveMira;
        const jonasEvent = new Promise((resolve) => { receiveJonas = resolve; });
        const miraEvent = new Promise((resolve) => { receiveMira = resolve; });
        const outsiderEvents = [];
        const subscriptions = await Promise.all([
            watch(realtime[0], conversation.id, receiveMira),
            watch(realtime[1], conversation.id, receiveJonas),
            watch(realtime[2], conversation.id, (event) => outsiderEvents.push(event), true),
        ]);
        assert.equal(subscriptions[2].status, 403);
        assert.deepEqual(mira.authStatuses, [200]);
        assert.deepEqual(jonas.authStatuses, [200]);
        assert.deepEqual(outsider.authStatuses, [403]);
        await assert.rejects(outsider.api.keyGrants(conversation.id), (error) => error.status === 403);
        t.diagnostic('Actual /broadcasting/auth: Mira 200, Jonas 200, outsider 403; outsider grants also 403');

        const clock = new ServerClock();
        clock.sync(conversation.server_time);
        const envelope = await sealMessage(mira.session, conversation, clock, plaintext);
        const sentAt = performance.now();
        const stored = await mira.api.sendMessage(conversation.id, envelope);
        const received = await within(jonasEvent, 'Jonas MessageSent over Reverb');
        assert.deepEqual(received, envelope, 'The real Realtime.watch callback receives the exact signed envelope');
        assert.deepEqual(await within(miraEvent, 'Mira MessageSent over Reverb'), envelope);
        assert.equal(jonas.historyReads, 0, 'Realtime proof must never fall back to history/polling');
        const opened = await openMessage(jonas.session, conversation.id, received, clock.now());
        assert.equal(opened.state, MessageState.READABLE);
        assert.equal(opened.plaintext, plaintext);
        const tampered = { ...received, signature: (received.signature[0] === 'A' ? 'B' : 'A') + received.signature.slice(1) };
        const rejected = await openMessage(jonas.session, conversation.id, tampered, clock.now());
        assert.equal(rejected.state, MessageState.UNVERIFIABLE);
        assert.equal(rejected.plaintext, null);
        t.diagnostic(`Reverb MessageSent received and decrypted in ${Math.round(performance.now() - sentAt)}ms; signature verified; tampered signature rejected; history reads=0`);

        const row = fixture('inspect', run, { conversation: conversation.id });
        assert.deepEqual(JSON.parse(row.envelope_json), envelope);
        assert.equal(row.envelope_json.includes(plaintext), false);
        assert.equal(Date.parse(row.expires_at), Date.parse(envelope.expires_at));
        assert.equal(Date.parse(row.expires_at), Date.parse(stored.data.expires_at));
        assert.ok(Date.parse(row.expires_at) > clock.now());
        assert.equal(row.identity_count, 3);
        assert.equal(row.grant_count, 2);
        for (const client of clients) {
            for (const body of client.chatBodies) {
                assert.equal(body.includes(plaintext), false, 'No plaintext in any chat API write');
                assert.equal(body.includes(passphrase), false, 'No chat passphrase sent to the API');
            }
        }
        await delay(500);
        assert.deepEqual(outsiderEvents, []);
        t.diagnostic('DB contains the ciphertext envelope only, exact signed expiry retained; outsider received no events');
    } finally {
        for (const transport of realtime) transport.disconnect();
        for (const client of clients) { client.session?.lock(); client.cookies.clear(); }
        browserSessions.clear();
        Pusher.Runtime.getXHRAPI = originalXHR;
        if (created) {
            const cleanup = fixture('cleanup', run);
            assert.equal(cleanup.deleted_users, 3);
            assert.ok(Object.values(cleanup.remaining).every((count) => count === 0));
            t.diagnostic('Cleanup verified: users, identities, conversations, memberships, messages, grants, sessions all removed');
        }
    }
});
