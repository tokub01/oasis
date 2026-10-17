/**
 * Same-origin HTTP "browser" for live local E2E tests: an in-memory cookie jar, the real login form with its CSRF
 * token, and Livewire requests built exactly like livewire.js sends them (snapshot from the rendered HTML, POST to the
 * page's data-update-uri with updates and calls). No DOM, no JavaScript execution.
 */
import assert from 'node:assert/strict';

export const base = new URL(process.env.E2E_URL ?? process.env.CHAT_E2E_URL ?? 'http://localhost');
assert.ok(['localhost', '127.0.0.1', '[::1]'].includes(base.hostname), 'E2E must target the local app');

/** Decode the HTML attribute escaping Blade/Livewire use for wire:snapshot. */
export function decodeAttribute(value) {
    return value
        .replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>')
        .replace(/&#x([0-9a-f]+);/gi, (_, hex) => String.fromCodePoint(parseInt(hex, 16)))
        .replace(/&#(\d+);/g, (_, dec) => String.fromCodePoint(Number(dec)))
        .replace(/&amp;/g, '&');
}

/** Livewire dehydrates arrays as [value, {s: 'arr'}] tuples; unwrap them recursively for assertions. */
export function unwrap(value) {
    if (Array.isArray(value) && value.length === 2 && value[1] && typeof value[1] === 'object' && 's' in value[1]) {
        return unwrap(value[0]);
    }
    if (Array.isArray(value)) return value.map(unwrap);
    if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, unwrap(v)]));
    return value;
}

/**
 * All Livewire components rendered in a page, in document order.
 *
 * @returns {{name: string, snapshot: string, data: object, memo: object}[]}
 */
export function components(html) {
    const found = [];
    for (const [tag] of html.matchAll(/<[a-z][a-z0-9-]*\s[^>]*wire:snapshot="[^"]*"[^>]*>/gi)) {
        const snapshot = decodeAttribute(tag.match(/wire:snapshot="([^"]*)"/)[1]);
        const parsed = JSON.parse(snapshot);
        found.push({ name: parsed.memo.name, snapshot, data: unwrap(parsed.data), memo: parsed.memo });
    }
    return found;
}

export class Browser {
    cookies = new Map();
    csrf = null;

    constructor(label) {
        this.label = label;
    }

    async fetch(path, init = {}) {
        let url = new URL(path, base);
        for (let redirects = 0; redirects < 6; redirects++) {
            assert.equal(url.origin, base.origin, 'Never forward session cookies to another origin');
            const headers = new Headers(init.headers);
            headers.set('Cookie', [...this.cookies].map(([name, value]) => `${name}=${value}`).join('; '));
            const response = await fetch(url, { ...init, headers, redirect: 'manual', signal: AbortSignal.timeout(30000) });
            for (const cookie of response.headers.getSetCookie()) {
                const pair = cookie.split(';', 1)[0];
                const equals = pair.indexOf('=');
                this.cookies.set(pair.slice(0, equals), pair.slice(equals + 1));
            }
            if (![301, 302, 303, 307, 308].includes(response.status) || init.followRedirects === false) return response;
            url = new URL(response.headers.get('location'), url);
            await response.body?.cancel();
            if ([301, 302, 303].includes(response.status)) init = { method: 'GET' };
        }
        throw new Error('Too many redirects');
    }

    /** GET a page; returns status, final HTML and the Livewire components on it. */
    async page(path) {
        const response = await this.fetch(path);
        const html = await response.text();
        const token = html.match(/name="csrf-token" content="([^"]+)"/)?.[1];
        if (token) this.csrf = token;
        this.updateUri ??= html.match(/data-update-uri="([^"]+)"/)?.[1];
        return { status: response.status, html, components: response.status === 200 ? components(html) : [] };
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
        assert.equal(signedIn.status, 200, `Login of ${this.label} reaches the member app`);
        await signedIn.body?.cancel();
        const app = await this.page('/app');
        assert.equal(app.status, 200);
        assert.ok(this.csrf, 'Authenticated CSRF token present');
        assert.ok(this.updateUri, 'Livewire update URI present');
    }

    /**
     * One Livewire round trip for one component, exactly as livewire.js posts it.
     *
     * @param {string} snapshot the JSON snapshot string from the page or a previous response
     * @param {object} updates property path => new value (e.g. {'settings.searchable': false})
     * @param {{method: string, params?: any[]}[]} calls
     */
    async livewire(snapshot, updates = {}, calls = []) {
        assert.ok(this.updateUri, 'Open a Livewire page first');
        const response = await this.fetch(this.updateUri, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Livewire': '1', 'X-CSRF-TOKEN': this.csrf ?? '', Accept: 'application/json' },
            body: JSON.stringify({ _token: this.csrf, components: [{ snapshot, updates,
                calls: calls.map((call) => ({ method: call.method, params: call.params ?? [], metadata: {} })) }] }),
            followRedirects: false,
        });
        const text = await response.text();
        if (response.status !== 200) return { status: response.status, body: text.slice(0, 300) };
        const component = JSON.parse(text).components[0];
        const parsed = JSON.parse(component.snapshot);
        return { status: 200, snapshot: component.snapshot, data: unwrap(parsed.data), errors: parsed.memo.errors ?? {},
            effects: component.effects ?? {} };
    }
}
