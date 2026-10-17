/**
 * Live local E2E for the Privatsphäre-Center (stage 13): every setting and every offered audience is saved through a
 * real Livewire request of privacy.center and then checked where it is enforced, as guest, as a stranger (whom the
 * owner does not follow, although the stranger follows the owner) and as a person the owner follows.
 * Intentionally outside the offline test globs; see docs/oasis-privacy.md.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { ChatApi } from '../../resources/js/chat/client/api.js';
import { ChatSession } from '../../resources/js/chat/client/session.js';
import { setUpIdentity } from '../../resources/js/chat/client/keyring.js';
import { Browser, base } from './support/browser.mjs';

const root = fileURLToPath(new URL('../../', import.meta.url));
const VIEWERS = ['guest', 'stranger', 'followed', 'owner'];

function fixture(operation, run, data = {}) {
    try {
        return JSON.parse(execFileSync('php', ['tests/e2e/support/privacy-fixtures.php'], {
            cwd: root, input: JSON.stringify({ operation, run, ...data }), encoding: 'utf8',
            timeout: 30000, stdio: ['pipe', 'pipe', 'pipe'],
        }));
    } catch (error) {
        throw new Error(`Local PHP fixture ${operation} failed (${String(error.stderr ?? '').trim()}); check Sail, migrations and APP_ENV=local`);
    }
}

/** The privacy rule as documented: owner always, everyone incl. guests, members, people the owner follows, nobody. */
function allowed(audience, viewer) {
    if (viewer === 'owner') return true;
    return audience === 'everyone' || (audience === 'members' && viewer !== 'guest') || (audience === 'following' && viewer === 'followed');
}

const escape = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

test('Privatsphäre-Center: every setting and audience is saved via Livewire and enforced', { timeout: 600000 }, async (t) => {
    const run = randomBytes(10).toString('hex');
    const password = randomBytes(24).toString('base64url');
    const passphrase = randomBytes(32).toString('base64url');
    const results = [];
    let created = false;
    let setup;
    const b = {};

    /** Soft assertion: every cell of the matrix is recorded, failures are asserted per setting at the end. */
    function check(setting, audience, viewer, what, expected, actual) {
        const ok = typeof expected === 'function' ? expected(actual) : Object.is(expected, actual);
        results.push({ setting, audience: String(audience), viewer, what, expected: typeof expected === 'function' ? 'erfüllt' : String(expected), actual: String(actual), ok });
        return ok;
    }
    function settle(sub, setting) {
        const rows = results.filter((row) => row.setting === setting);
        for (const row of rows) sub.diagnostic(`${row.ok ? 'OK    ' : 'FEHLER'} ${row.setting} = ${row.audience} | ${row.viewer} | ${row.what} | erwartet ${row.expected} | ist ${row.actual}`);
        const failed = rows.filter((row) => !row.ok);
        assert.equal(failed.length, 0, `${failed.length} von ${rows.length} Prüfungen für ${setting} fehlgeschlagen: `
            + failed.map((row) => `${row.audience}/${row.viewer}/${row.what}`).join('; '));
    }

    const state = () => fixture('state', run);
    const storedRow = () => {
        const row = fixture('privacy', run).row;
        if (row === null) return null;
        for (const key of ['searchable', 'search_engines']) row[key] = [1, '1', true].includes(row[key]);
        return row;
    };
    const component = (page, name, where = () => true) => page.components.find((c) => c.name === name && where(c.data));
    const robots = (html) => /<meta name="robots" content="noindex/.test(html);

    let center;
    /** Save one setting exactly like the select/switch does (wire:model.live -> settings.<key>), then verify DB. */
    async function setPrivacy(key, value, setting = key) {
        const response = await b.owner.livewire(center, { [`settings.${key}`]: value });
        const row = storedRow();
        const dbValue = row === null ? null : row[key];
        const ok = check(setting, value, 'owner', `speichern ${key} (Livewire ${response.status}, DB ${dbValue})`, true,
            response.status === 200 && Object.keys(response.errors).length === 0 && response.data.settings[key] === value && dbValue === value);
        if (response.status === 200) center = response.snapshot;
        return ok;
    }

    async function publish(browser, snapshot, body, updates = {}) {
        return browser.livewire(snapshot, { body, ...updates }, [{ method: 'publish' }]);
    }
    async function sourceComposer(browser) {
        const page = await browser.page('/app');
        return component(page, 'network.compose', (d) => d.replyToId === null && d.carriedId === null && d.oasisId === null);
    }
    const postWith = (token) => state().posts.find((post) => post.body.includes(token));

    try {
        setup = fixture('setup', run, { password });
        created = true;
        const { accounts } = setup;
        assert.equal(setup.leftovers, 0, 'No privacy-e2e accounts of earlier runs are left over');
        t.diagnostic(`Fixtures: owner/stranger/followed (${accounts.owner.username} …); foreign accounts before: ${setup.foreign.users}`);
        for (const role of ['owner', 'stranger', 'followed']) {
            b[role] = new Browser(role);
            await b[role].login(accounts[role], password);
        }
        b.guest = new Browser('guest');
        const owner = accounts.owner;
        const profileUrl = `/profil/${owner.username}`;

        // Circles: the owner follows "followed"; the stranger follows the owner (Karawane is no audience).
        const followedProfile = await b.owner.page(`/profil/${accounts.followed.username}`);
        assert.equal((await b.owner.livewire(component(followedProfile, 'network.profile-show').snapshot, {}, [{ method: 'follow' }])).status, 200);
        const ownerProfileForStranger = await b.stranger.page(profileUrl);
        assert.equal((await b.stranger.livewire(component(ownerProfileForStranger, 'network.profile-show').snapshot, {}, [{ method: 'follow' }])).status, 200);
        const follows = state().follows;
        assert.ok(follows.some((f) => f.follower_id === owner.id && f.followable_id === accounts.followed.id), 'owner follows followed');
        assert.ok(follows.some((f) => f.follower_id === accounts.stranger.id && f.followable_id === owner.id), 'stranger follows owner');
        assert.equal(storedRow(), null, 'Opening pages wrote no privacy row (defaults apply)');

        const centerPage = await b.owner.page('/app/privatsphaere');
        assert.equal(centerPage.status, 200);
        center = component(centerPage, 'privacy.center').snapshot;
        assert.ok(center, 'privacy.center rendered');
        t.diagnostic('Real logins + follows via Livewire; privacy.center snapshot loaded');

        // The owner's public Tropfen used by profile, discover, reply, carry and search-engine checks.
        const publicToken = `oeffentlich-${run}`;
        let composer = await sourceComposer(b.owner);
        const topic = `pe2e${run.slice(0, 12)}`;
        let response = await publish(b.owner, composer.snapshot, `Ein öffentlicher Tropfen ${publicToken} #${topic}`, { visibility: 'public' });
        assert.equal(response.status, 200);
        const P = postWith(publicToken);
        assert.ok(P && P.visibility === 'public', 'Public owner Tropfen stored');

        await t.test('profile_audience', async (sub) => {
            for (const audience of ['everyone', 'members', 'following']) {
                await setPrivacy('profile_audience', audience);
                for (const viewer of VIEWERS) {
                    const page = await b[viewer].page(profileUrl);
                    check('profile_audience', audience, viewer, `GET ${profileUrl}`, allowed(audience, viewer) ? 200 : 404, page.status);
                }
                for (const viewer of ['stranger', 'followed']) {
                    const discover = await b[viewer].page('/app/entdecken');
                    check('profile_audience', audience, viewer, 'öffentlicher Tropfen in /app/entdecken', audience === 'everyone',
                        discover.html.includes(`id="tropfen-${P.id}"`));
                }
                const demo = await b.guest.page('/entdecken');
                check('profile_audience', audience, 'guest', 'öffentliche /entdecken (Demo-Seite) zeigt nie echte Tropfen', false,
                    demo.html.includes(`id="tropfen-${P.id}"`));
            }
            await setPrivacy('profile_audience', 'everyone');
            settle(sub, 'profile_audience');
        });

        await t.test('default_post_visibility', async (sub) => {
            for (const visibility of ['public', 'members']) {
                await setPrivacy('default_post_visibility', visibility);
                const compose = await sourceComposer(b.owner);
                check('default_post_visibility', visibility, 'owner', 'Vorauswahl network.compose.visibility', visibility, compose?.data.visibility);
                const token = `vorauswahl-${visibility}-${run}`;
                const sent = await publish(b.owner, compose.snapshot, `Vorauswahl ${token}`);
                check('default_post_visibility', visibility, 'owner', 'gespeicherte Sichtbarkeit ohne Änderung', visibility,
                    sent.status === 200 ? postWith(token)?.visibility : `HTTP ${sent.status}`);
            }
            settle(sub, 'default_post_visibility');
        });

        await t.test('follower_count_audience', async (sub) => {
            for (const audience of ['everyone', 'members', 'following', 'nobody']) {
                await setPrivacy('follower_count_audience', audience);
                for (const viewer of VIEWERS) {
                    const page = await b[viewer].page(profileUrl);
                    const count = page.html.match(/Karawane: (\d+)/)?.[1];
                    check('follower_count_audience', audience, viewer, 'Karawane-Zahl sichtbar', allowed(audience, viewer),
                        page.status === 200 && count !== undefined);
                    if (count !== undefined) check('follower_count_audience', audience, viewer, 'Karawane-Zahl korrekt', '1', count);
                }
            }
            await setPrivacy('follower_count_audience', 'members');
            settle(sub, 'follower_count_audience');
        });

        // Location and oasis list on the profile, each with its own audience (fixture: "Ort-<run>", "Oase <run>").
        for (const [key, marker] of [['location_audience', `Ort-${run.slice(0, 12)}`], ['oases_audience', `Oase ${run.slice(0, 12)}`]]) {
            await t.test(key, async (sub) => {
                for (const audience of ['everyone', 'members', 'following', 'nobody']) {
                    await setPrivacy(key, audience);
                    for (const viewer of VIEWERS) {
                        const page = await b[viewer].page(profileUrl);
                        check(key, audience, viewer, `${marker} auf dem Profil`, allowed(audience, viewer),
                            page.status === 200 && page.html.includes(marker));
                    }
                }
                await setPrivacy(key, 'members');
                settle(sub, key);
            });
        }

        await t.test('mentions_audience', async (sub) => {
            const link = new RegExp(`href="[^"]*/profil/${escape(owner.username)}">@${escape(owner.username)}</a>`);
            for (const audience of ['members', 'following', 'nobody']) {
                await setPrivacy('mentions_audience', audience);
                for (const viewer of ['stranger', 'followed']) {
                    const token = `erwaehnt-${audience}-${viewer}-${run}`;
                    const compose = await sourceComposer(b[viewer]);
                    const sent = await publish(b[viewer], compose.snapshot, `Hallo @${owner.username}, schau mal ${token}`);
                    const post = postWith(token);
                    check('mentions_audience', audience, viewer, 'Tropfen mit @owner veröffentlicht', true, sent.status === 200 && post !== undefined);
                    if (!post) continue;
                    const hint = state().notifications.some((n) => n.kind === 'mention' && n.post_id === post.id);
                    check('mentions_audience', audience, viewer, 'Hinweis in DB (notifications)', allowed(audience, viewer), hint);
                    const inbox = await b.owner.page('/app/hinweise');
                    check('mentions_audience', audience, viewer, 'Hinweis in /app/hinweise', allowed(audience, viewer), inbox.html.includes(token));
                    const shown = await b.owner.page(`/tropfen/${post.id}`);
                    check('mentions_audience', audience, viewer, 'Profil-Link im gerenderten Tropfen', allowed(audience, viewer),
                        shown.status === 200 && link.test(shown.html));
                }
            }
            await setPrivacy('mentions_audience', 'members');
            settle(sub, 'mentions_audience');
        });

        const stale = {};
        await t.test('replies_audience', async (sub) => {
            for (const audience of ['members', 'following', 'nobody']) {
                await setPrivacy('replies_audience', audience);
                for (const viewer of ['guest', 'stranger', 'followed']) {
                    const page = await b[viewer].page(`/tropfen/${P.id}`);
                    const expected = viewer !== 'guest' && allowed(audience, viewer);
                    check('replies_audience', audience, viewer, `GET /tropfen/${P.id}`, 200, page.status);
                    check('replies_audience', audience, viewer, 'Button „Antworten“ auf der Karte', expected, page.html.includes(`href="${base.origin}/tropfen/${P.id}#antworten"`));
                    const compose = component(page, 'network.compose', (d) => d.replyToId === P.id);
                    check('replies_audience', audience, viewer, 'Antwort-Composer (replyToId) gerendert', expected, compose !== undefined);
                    if (viewer === 'guest') continue;
                    if (compose) stale[`reply-${viewer}`] = compose.snapshot;
                    // Denied viewers replay the composer snapshot they got while it was still allowed.
                    const token = `antwort-${audience}-${viewer}-${run}`;
                    const sent = await publish(b[viewer], compose?.snapshot ?? stale[`reply-${viewer}`], `Antwort ${token}`);
                    const reply = postWith(token);
                    check('replies_audience', audience, viewer, 'Livewire publish mit replyToId', expected ? 200 : 403, sent.status);
                    check('replies_audience', audience, viewer, 'Antwort gespeichert', expected, reply !== undefined && reply.reply_to_id === P.id);
                }
            }
            await setPrivacy('replies_audience', 'members');
            settle(sub, 'replies_audience');
        });

        await t.test('carry_audience', async (sub) => {
            for (const audience of ['members', 'following', 'nobody']) {
                await setPrivacy('carry_audience', audience);
                for (const viewer of ['guest', 'stranger', 'followed']) {
                    const expected = viewer !== 'guest' && allowed(audience, viewer);
                    const card = await b[viewer].page(`/tropfen/${P.id}`);
                    check('carry_audience', audience, viewer, 'Button „Weitertragen“ auf der Karte', expected, card.html.includes(`href="${base.origin}/tropfen/${P.id}/weitertragen"`));
                    if (viewer === 'guest') {
                        const page = await b.guest.fetch(`/tropfen/${P.id}/weitertragen`, { followRedirects: false });
                        await page.body?.cancel();
                        check('carry_audience', audience, viewer, 'GET /weitertragen leitet zur Anmeldung', 302, page.status);
                        continue;
                    }
                    const page = await b[viewer].page(`/tropfen/${P.id}/weitertragen`);
                    check('carry_audience', audience, viewer, `GET /tropfen/${P.id}/weitertragen`, expected ? 200 : 403, page.status);
                    const compose = component(page, 'network.compose', (d) => d.carriedId === P.id);
                    if (compose) stale[`carry-${viewer}`] = compose.snapshot;
                    const token = `weiter-${audience}-${viewer}-${run}`;
                    const sent = await publish(b[viewer], compose?.snapshot ?? stale[`carry-${viewer}`], `Weitergetragen ${token}`);
                    const carried = postWith(token);
                    check('carry_audience', audience, viewer, 'Livewire publish mit carriedId', expected ? 200 : 403, sent.status);
                    check('carry_audience', audience, viewer, 'Weitertragen gespeichert', expected, carried !== undefined && carried.carried_post_id === P.id);
                }
            }
            await setPrivacy('carry_audience', 'members');
            settle(sub, 'carry_audience');
        });

        const api = {};
        await t.test('chat_invites_audience', async (sub) => {
            for (const role of ['owner', 'stranger', 'followed']) {
                api[role] = new ChatApi({ baseUrl: '/app/chat/api', csrfToken: b[role].csrf, fetchImpl: b[role].fetch.bind(b[role]) });
                const session = new ChatSession(String(accounts[role].id));
                await setUpIdentity(api[role], session, passphrase);
                session.lock();
            }
            // Each viewer owns a group with the other viewer, to test adding the owner to an existing chat.
            const groups = {
                stranger: (await api.stranger.createConversation({ type: 'group', title: 'E2E', member_ids: [String(accounts.followed.id)], lifetime_days: 1 })).data,
                followed: (await api.followed.createConversation({ type: 'group', title: 'E2E', member_ids: [String(accounts.stranger.id)], lifetime_days: 1 })).data,
            };
            const status = (promise) => promise.then(() => 'ok', (error) => error.status ?? String(error));
            for (const audience of ['members', 'following', 'nobody']) {
                await setPrivacy('chat_invites_audience', audience);
                for (const viewer of ['stranger', 'followed']) {
                    const expected = allowed(audience, viewer);
                    const found = await api[viewer].findUser(owner.username);
                    check('chat_invites_audience', audience, viewer, 'GET /app/chat/api/users?username=owner findet Owner', expected,
                        found.data.some((user) => user.id === String(owner.id)));
                    check('chat_invites_audience', audience, viewer, 'POST /conversations (direct mit Owner)', expected ? 'ok' : 403,
                        await status(api[viewer].createConversation({ type: 'direct', title: null, member_ids: [String(owner.id)], lifetime_days: 1 })));
                    const added = await status(api[viewer].addMember(groups[viewer].id, String(owner.id)));
                    check('chat_invites_audience', audience, viewer, 'POST /conversations/{id}/members (Owner hinzufügen)', expected ? 'ok' : 403, added);
                    if (added === 'ok') await api[viewer].removeMember(groups[viewer].id, String(owner.id));
                }
            }
            const guest = await b.guest.fetch(`/app/chat/api/users?username=${owner.username}`, { headers: { Accept: 'application/json' }, followRedirects: false });
            await guest.body?.cancel();
            check('chat_invites_audience', 'members', 'guest', 'Chat-Suche ohne Anmeldung', (s) => [401, 302].includes(s), guest.status);
            await setPrivacy('chat_invites_audience', 'members');
            settle(sub, 'chat_invites_audience');
        });

        await t.test('searchable', async (sub) => {
            for (const searchable of [true, false]) {
                await setPrivacy('searchable', searchable);
                for (const viewer of ['stranger', 'followed']) {
                    for (const q of [owner.display_name, owner.username]) {
                        const page = await b[viewer].page(`/app/suche?q=${encodeURIComponent(q)}`);
                        check('searchable', searchable, viewer, `Personensuche q=${q === owner.username ? 'Benutzername' : 'Name'}`, searchable,
                            page.status === 200 && page.html.includes(`wire:key="index-1-${owner.id}"`));
                    }
                }
            }
            await setPrivacy('searchable', true);
            settle(sub, 'searchable');
        });

        await t.test('search_engines', async (sub) => {
            for (const engines of [true, false]) {
                await setPrivacy('search_engines', engines);
                for (const audience of ['everyone', 'members', 'following']) {
                    await setPrivacy('profile_audience', audience, 'search_engines');
                    const label = `${engines} + profile_audience=${audience}`;
                    const noindex = !(engines && audience === 'everyone');
                    const profile = await b.guest.page(profileUrl);
                    check('search_engines', label, 'guest', 'Profil-Status', audience === 'everyone' ? 200 : 404, profile.status);
                    if (profile.status === 200) check('search_engines', label, 'guest', 'Profil noindex', noindex, robots(profile.html));
                    const post = await b.guest.page(`/tropfen/${P.id}`);
                    check('search_engines', label, 'guest', 'Tropfen-Status (öffentlich)', 200, post.status);
                    check('search_engines', label, 'guest', 'Tropfen noindex', noindex, robots(post.html));
                }
            }
            const member = await b.stranger.page(`/tropfen/${P.id}`);
            check('search_engines', 'false + profile_audience=following', 'stranger', 'App-Layout immer noindex', true, robots(member.html));
            await setPrivacy('search_engines', false);
            await setPrivacy('profile_audience', 'everyone', 'search_engines');
            settle(sub, 'search_engines');
        });

        // Beyond the two pages the setting names: other public guest pages must not let search engines index the
        // Tropfen either, otherwise "search_engines = Nein" is bypassed (see the stage 13 E2E report).
        await t.test('search_engines – öffentliche Themenseite /themen/{tag}', async (sub) => {
            const setting = 'search_engines (Themenseite)';
            for (const engines of [true, false]) {
                await setPrivacy('search_engines', engines, setting);
                for (const audience of ['everyone', 'members', 'following']) {
                    await setPrivacy('profile_audience', audience, setting);
                    const page = await b.guest.page(`/themen/${topic}`);
                    const shown = page.html.includes(`id="tropfen-${P.id}"`);
                    const indexable = engines && audience === 'everyone';
                    check(setting, `${engines} + profile_audience=${audience}`, 'guest',
                        `GET /themen/${topic}: Tropfen ${shown ? 'gelistet' : 'nicht gelistet'}, noindex=${robots(page.html)}`,
                        'nicht indexierbar gelistet', !shown || robots(page.html) || indexable ? 'nicht indexierbar gelistet' : 'indexierbar gelistet');
                }
            }
            await setPrivacy('search_engines', false, setting);
            await setPrivacy('profile_audience', 'everyone', setting);
            settle(sub, setting);
        });

        await t.test('manipulation', async (sub) => {
            const before = storedRow();
            const refused = [
                ['mentions_audience', 'everyone'], ['replies_audience', 'everyone'], ['carry_audience', 'everyone'],
                ['chat_invites_audience', 'everyone'], ['profile_audience', 'nobody'], ['default_post_visibility', 'everyone'],
                ['follower_count_audience', 'friends'], ['searchable', 'vielleicht'], ['search_engines', ['1']],
                ['chat_invites_audience', ['members']],
            ];
            for (const [key, value] of refused) {
                const response = await b.owner.livewire(center, { [`settings.${key}`]: value });
                check('manipulation', `${key}=${JSON.stringify(value)}`, 'owner', 'abgelehnt mit Fehlermeldung, Wert zurückgesetzt', true,
                    response.status === 200 && `settings.${key}` in response.errors && JSON.stringify(response.data.settings[key]) === JSON.stringify(before[key]));
                if (response.status === 200) center = response.snapshot;
            }
            const all = Object.fromEntries(Object.keys(before).map((key) => [key, 'everyone']));
            const replaced = await b.owner.livewire(center, { settings: all });
            check('manipulation', 'settings komplett überschreiben', 'owner', 'HTTP-Status', 422, replaced.status);
            const unknown = await b.owner.livewire(center, { 'settings.is_admin': true });
            check('manipulation', 'unbekannter Key settings.is_admin', 'owner', 'HTTP-Status', 422, unknown.status);
            const locked = await b.owner.livewire(center, { status: 'Gespeichert: gefälscht' });
            check('manipulation', 'gesperrte Property status', 'owner', 'abgelehnt (kein 200)', (s) => s !== 200, locked.status);
            const checkupPage = await b.owner.page('/app/privatsphaere/check');
            const checkup = component(checkupPage, 'privacy.checkup');
            const viaCheck = await b.owner.livewire(checkup.snapshot, { 'settings.mentions_audience': 'everyone' });
            check('manipulation', 'Privatsphäre-Check mentions_audience=everyone', 'owner', 'abgelehnt', true,
                viaCheck.status === 200 && 'settings.mentions_audience' in viaCheck.errors);
            // Replaying the owner's snapshot in another session acts on that session's account, never on the owner.
            const replay = await b.stranger.livewire(center, { 'settings.profile_audience': 'following' });
            check('manipulation', 'Owner-Snapshot in fremder Sitzung', 'stranger', 'Owner-Einstellungen unberührt', true,
                storedRow().profile_audience === before.profile_audience);
            sub.diagnostic(`Replay in fremder Sitzung: HTTP ${replay.status} (ändert höchstens die eigene Einstellung der fremden Person)`);
            const guestCenter = await b.guest.fetch('/app/privatsphaere', { followRedirects: false });
            await guestCenter.body?.cancel();
            check('manipulation', 'Gast öffnet /app/privatsphaere', 'guest', 'Weiterleitung zur Anmeldung', 302, guestCenter.status);
            check('manipulation', 'alle Versuche', 'owner', 'DB-Zeile unverändert', JSON.stringify(before), JSON.stringify(storedRow()));
            settle(sub, 'manipulation');
        });

        await t.test('block', async (sub) => {
            const strangerProfile = await b.owner.page(`/profil/${accounts.stranger.username}`);
            const blocked = await b.owner.livewire(component(strangerProfile, 'network.profile-show').snapshot, {}, [{ method: 'blockUser', params: [accounts.stranger.id] }]);
            assert.equal(blocked.status, 200);
            assert.ok(state().blocks.some((x) => x.user_id === owner.id && x.blocked_user_id === accounts.stranger.id), 'block stored');
            const row = storedRow();
            sub.diagnostic(`Owner hat stranger blockiert; Einstellungen: profile=${row.profile_audience}, replies=${row.replies_audience}, carry=${row.carry_audience}, mentions=${row.mentions_audience}, chat=${row.chat_invites_audience}, searchable=${row.searchable}`);
            const label = 'everyone/members + Block';
            check('block', label, 'stranger', `GET ${profileUrl}`, 404, (await b.stranger.page(profileUrl)).status);
            check('block', label, 'owner', 'GET /profil/stranger', 404, (await b.owner.page(`/profil/${accounts.stranger.username}`)).status);
            check('block', label, 'guest', `GET ${profileUrl} (Kontrolle)`, 200, (await b.guest.page(profileUrl)).status);
            check('block', label, 'stranger', `GET /tropfen/${P.id}`, 404, (await b.stranger.page(`/tropfen/${P.id}`)).status);
            check('block', label, 'stranger', 'öffentlicher Tropfen in /app/entdecken', false, (await b.stranger.page('/app/entdecken')).html.includes(`id="tropfen-${P.id}"`));
            check('block', label, 'stranger', 'Personensuche findet Owner', false,
                (await b.stranger.page(`/app/suche?q=${encodeURIComponent(owner.display_name)}`)).html.includes(`wire:key="index-1-${owner.id}"`));
            const reply = await publish(b.stranger, stale['reply-stranger'], `Antwort trotz Block ${run}`);
            check('block', label, 'stranger', 'Antwort (Livewire publish, alter Snapshot)', (s) => s === 403 || s === 404, reply.status);
            check('block', label, 'stranger', 'GET /weitertragen', (s) => s === 403 || s === 404, (await b.stranger.page(`/tropfen/${P.id}/weitertragen`)).status);
            const carry = await publish(b.stranger, stale['carry-stranger'], `Weitertragen trotz Block ${run}`);
            check('block', label, 'stranger', 'Weitertragen (Livewire publish, alter Snapshot)', (s) => s === 403 || s === 404, carry.status);
            check('block', label, 'stranger', 'nichts gespeichert', true, !state().posts.some((p) => p.body.includes(`trotz Block ${run}`)));
            const found = await api.stranger.findUser(owner.username);
            check('block', label, 'stranger', 'Chat-Nutzersuche leer', 0, found.data.length);
            const chat = await api.stranger.createConversation({ type: 'direct', title: null, member_ids: [String(owner.id)], lifetime_days: 1 })
                .then(() => 'ok', (error) => error.status);
            check('block', label, 'stranger', 'Chat mit Owner starten', (s) => s === 403 || s === 422, chat);
            const token = `block-erwaehnt-${run}`;
            const sent = await publish(b.stranger, (await sourceComposer(b.stranger)).snapshot, `Hallo @${owner.username} ${token}`);
            const post = postWith(token);
            check('block', label, 'stranger', 'Erwähnung veröffentlicht', true, sent.status === 200 && post !== undefined);
            if (post) {
                check('block', label, 'stranger', 'kein Hinweis an Owner', false, state().notifications.some((n) => n.post_id === post.id));
                const own = await b.stranger.page(`/tropfen/${post.id}`);
                check('block', label, 'stranger', 'kein Profil-Link im Tropfen', false,
                    new RegExp(`href="[^"]*/profil/${escape(owner.username)}">@`).test(own.html));
            }
            settle(sub, 'block');
        });
    } finally {
        for (const browser of Object.values(b)) browser.cookies.clear();
        if (created) {
            const cleanup = fixture('cleanup', run);
            t.diagnostic(`Cleanup: ${cleanup.deleted_users} Konten, ${cleanup.deleted_checks} Inhaltsprüfungen, ${cleanup.deleted_jobs} Queue-Jobs gelöscht; Rest: ${JSON.stringify(cleanup.remaining)}`);
            t.diagnostic(`Fremde Daten vorher ${JSON.stringify(setup.foreign)} nachher ${JSON.stringify(cleanup.foreign)}`);
            assert.equal(cleanup.deleted_users, 3);
            assert.ok(Object.values(cleanup.remaining).every((count) => count === 0), 'Everything of this run is removed');
            assert.deepEqual(cleanup.foreign, setup.foreign, 'Demo accounts and their data are unchanged');
        }
        const failed = results.filter((row) => !row.ok).length;
        t.diagnostic(`Matrix: ${results.length - failed} OK, ${failed} FEHLER`);
    }
});
