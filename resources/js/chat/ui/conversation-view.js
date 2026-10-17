/**
 * Oasis chat UI – screen "conversation": read and write one chat.
 *
 * Markup: resources/views/app/chat/partials/conversation.blade.php.
 *
 * Flow on open (docs/oasis-chat.md, "Rotation procedure"):
 *   1. load the conversation (syncs the server clock), verify the members' public keys, open my key grants;
 *   2. if the key is pending and I am the owner, create and distribute a new key; otherwise wait for KeyRotated;
 *   3. load the newest history, decrypt it, and listen on the private channel for new envelopes.
 * Every KeyRotated event and every 409 from the API repeats step 1–2 (membership or identity changed).
 *
 * Plaintext only lives in the in-memory Timeline; expired messages are removed from screen and memory every 15 s.
 */

import { ApiError } from '../client/api.js';
import { distributeKey, loadDirectory, openGrants } from '../client/keyring.js';
import { MessageState, Timeline, measureDraft, openMessage, sealMessage } from '../client/messages.js';
import { scanIncoming, scanOutgoing } from '../client/safety.js';
import { MAX_MESSAGE_LENGTH } from '../index.js';
import { conversationTitle } from './conversation-list.js';
import { $, formatDateTime, fromTemplate, lifetimeLabel, setText, showError } from './dom.js';
import { describeError } from './errors.js';

const EXPIRY_SWEEP_MS = 15_000;

export class ConversationView {
    /** @param {object} ctx shared chat context (see app.js) */
    constructor(ctx) {
        this.ctx = ctx;
        this.screen = ctx.screens.conversation;
        this.title = $(this.screen, '[data-title]');
        this.lifetime = $(this.screen, '[data-lifetime]');
        this.notices = $(this.screen, '[data-notices]');
        this.messages = $(this.screen, '[data-messages]');
        this.empty = $(this.screen, '[data-empty]');
        this.older = $(this.screen, '[data-older]');
        this.composer = $(this.screen, '[data-composer]');
        this.counter = $(this.composer, '[data-counter]');
        this.error = $(this.composer, '[data-form-error]');
        this.hint = $(this.composer, '[data-composer-hint]');
        this.safety = $(this.screen, '[data-safety]');
        /** Signatures of flagged messages the member dismissed with "Ist in Ordnung" (in memory only). */
        this.dismissed = new Set();
        /** Draft the member confirmed after the sensitive-data warning; sending it again goes through. */
        this.confirmedDraft = null;
        this.flagged = null;
        $(this.safety, '[data-safety-dismiss]').addEventListener('click', () => {
            if (this.flagged) {
                this.dismissed.add(this.flagged.signature);
            }
            this.safety.hidden = true;
        });
        $(this.safety, '[data-safety-report]').addEventListener('click', () => {
            if (this.flagged) {
                this.ctx.report.open(this.conversation.id, this.flagged, this.senderName(this.flagged.senderId));
            }
        });
        $(this.safety, '[data-safety-block]').addEventListener('click', () => this.blockAndLeave());
        this.requestBanner = $(this.screen, '[data-request]');
        $(this.requestBanner, '[data-request-accept]').addEventListener('click', () => this.acceptRequest());
        $(this.requestBanner, '[data-request-decline]').addEventListener('click', () => this.declineRequest(false));
        $(this.requestBanner, '[data-request-block]').addEventListener('click', () => this.declineRequest(true));

        this.conversation = null;
        this.users = new Map();
        this.warnings = [];
        this.timeline = new Timeline();
        this.nextBeforeId = null;
        this.sweep = null;
        /** Increases on every open/close; async results of an older value are dropped. */
        this.openCount = 0;

        this.composer.addEventListener('submit', (event) => {
            event.preventDefault();
            this.send();
        });
        this.composer.elements.message.addEventListener('input', () => {
            this.updateCounter();
            this.updateHint();
        });
        // Enter sends, Shift+Enter makes a new line (common chat behaviour).
        this.composer.elements.message.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                this.send();
            }
        });
        this.older.addEventListener('click', () => this.loadHistory());
    }

    /** @param {string} conversationId */
    async open(conversationId) {
        this.close();
        const token = this.openCount;
        $(this.screen, '[data-details-link]').href = `#c/${conversationId}/info`;
        try {
            await this.refresh(conversationId);
            if (!this.isCurrent(token)) {
                return;
            }
            await this.loadHistory();
            if (!this.isCurrent(token)) {
                return;
            }
            this.markRead(conversationId);
            this.ctx.realtime.watch(conversationId, {
                onMessage: (envelope) => this.receive(envelope),
                onKeyRotated: () => this.refresh(conversationId).catch((error) => this.handleFailure(error)),
            });
            this.sweep = setInterval(() => {
                if (this.timeline.dropExpired(this.ctx.clock.now())) {
                    this.renderMessages();
                }
            }, EXPIRY_SWEEP_MS);
        } catch (error) {
            this.handleFailure(error);
        }
    }

    /**
     * Leave the conversation: stop listening and forget its plaintext – in memory AND on the page (rendered messages,
     * the unsent draft, notices). Results of requests still running are ignored (see `isCurrent`).
     */
    close() {
        this.ctx.realtime.leave();
        clearInterval(this.sweep);
        this.sweep = null;
        this.openCount++;
        this.timeline.clear();
        this.conversation = null;
        this.nextBeforeId = null;
        this.messages.replaceChildren();
        this.notices.replaceChildren();
        this.composer.elements.message.value = '';
        // The safety banner and hints refer to this chat's plaintext: forget them with it.
        this.safety.hidden = true;
        this.requestBanner.hidden = true;
        this.flagged = null;
        this.dismissed.clear();
        this.confirmedDraft = null;
        this.showHint('');
        setText(this.title, '');
        setText(this.lifetime, '');
    }

    /** @returns {boolean} whether async work started under `token` still belongs to the open conversation */
    isCurrent(token) {
        return token === this.openCount && this.ctx.session.unlocked;
    }

    get isOwner() {
        return this.conversation?.created_by === this.ctx.session.userId;
    }

    /** Steps 1–2: conversation, directory, grants and – as owner – a pending key. */
    async refresh(conversationId) {
        const token = this.openCount;
        let { data: conversation } = await this.ctx.api.conversation(conversationId);
        this.ctx.clock.sync(conversation.server_time);
        const directory = await loadDirectory(this.ctx.api, this.ctx.session, conversationId);
        this.users = new Map(directory.users.map((user) => [user.id, user]));
        await openGrants(this.ctx.api, this.ctx.session, conversation);

        this.warnings = keyWarnings(conversation, directory, this.ctx.session.userId);
        if (conversation.rotation_required && conversation.created_by === this.ctx.session.userId) {
            try {
                conversation = await distributeKey(this.ctx.api, this.ctx.session, conversation);
                this.ctx.clock.sync(conversation.server_time);
            } catch (error) {
                this.warnings.push(describeError(error));
            }
        }
        if (!this.isCurrent(token)) {
            return;
        }
        this.conversation = conversation;
        this.renderHeader();
        this.renderNotices();
        this.renderRequest();
        // New keys may make earlier "no key" messages readable.
        await this.redecrypt();
    }

    renderHeader() {
        const conversation = this.conversation;
        setText(this.title, conversationTitle(conversation, this.ctx.session.userId));
        setText(this.lifetime, `Ende-zu-Ende verschlüsselt · Nachrichten löschen sich nach ${lifetimeLabel(conversation.lifetime_days)}`);
        const canSend = this.ctx.canParticipate && !conversation.rotation_required
            && this.ctx.session.conversationKey(conversation.id, conversation.key_version) !== null;
        for (const control of this.composer.elements) {
            control.disabled = !canSend;
        }
        this.updateCounter();
    }

    renderNotices() {
        const texts = [...this.warnings];
        if (!this.ctx.canParticipate) {
            texts.unshift('Dein Konto ist gerade nur zum Lesen. Mit einer Mitgliedschaft kannst du wieder schreiben.');
        }
        const conversation = this.conversation;
        if (conversation.rotation_required && !this.isOwner) {
            texts.unshift('Der Chat bekommt einen neuen Schlüssel. Sobald die Person, die ihn gestartet hat, online ist, kannst du schreiben.');
        } else if (!this.ctx.session.conversationKey(conversation.id, conversation.key_version)) {
            texts.unshift('Für diesen Chat liegt dir noch kein Schlüssel vor. Du kannst noch nicht schreiben.');
        }
        this.notices.replaceChildren(...texts.map((text) => {
            const item = fromTemplate(this.screen, '[data-notice]');
            setText(item, text);
            return item;
        }));
    }

    /** Step 3: the next 50 older messages. */
    async loadHistory() {
        const token = this.openCount;
        const conversationId = this.conversation.id;
        const result = await this.ctx.api.messages(conversationId, this.nextBeforeId);
        const now = this.ctx.clock.now();
        const items = [];
        for (const message of [...result.data].reverse()) {
            items.push(await openMessage(this.ctx.session, conversationId, message.envelope, now));
        }
        if (!this.isCurrent(token)) {
            return;
        }
        this.timeline.prepend(items);
        this.nextBeforeId = result.next_before_id;
        this.older.hidden = this.nextBeforeId === null;
        this.renderMessages();
    }

    async receive(envelope) {
        if (!this.conversation || String(envelope?.conversation_id) !== this.conversation.id) {
            return;
        }
        const token = this.openCount;
        const item = await openMessage(this.ctx.session, this.conversation.id, envelope, this.ctx.clock.now());
        if (!this.isCurrent(token)) {
            return;
        }
        if (this.timeline.append(item)) {
            this.renderMessages();
            if (item.senderId !== this.ctx.session.userId) {
                this.ctx.announce(`Neue Nachricht von ${this.senderName(item.senderId)}.`);
                if (!document.hidden) {
                    this.markRead(this.conversation.id);
                }
            }
        }
    }

    /** Message request banner: shown while this member has not accepted the chat (see list "Anfragen"). */
    renderRequest() {
        this.requestBanner.hidden = !this.conversation.request;
        if (this.conversation.request) {
            const others = this.conversation.members.filter((member) => member.user_id !== this.ctx.session.userId);
            const name = others.length === 1 ? (others[0].display_name || `@${others[0].username}`) : 'Jemand';
            setText($(this.requestBanner, '[data-request-title]'), `Nachrichtenanfrage von ${name}`);
        }
    }

    async acceptRequest() {
        try {
            await this.ctx.api.accept(this.conversation.id);
            this.conversation = { ...this.conversation, request: false };
            this.renderRequest();
            this.ctx.announce('Angenommen. Das ist jetzt ein normaler Chat.');
        } catch (error) {
            showError(this.error, describeError(error));
        }
    }

    /** Decline: leave the chat; with block: also block the person who started it. */
    async declineRequest(block) {
        const starter = this.conversation.created_by;
        if (block && !window.confirm('Diese Person blockieren und die Anfrage löschen?')) {
            return;
        }
        try {
            if (block) {
                await this.ctx.api.block(starter);
            }
            await this.ctx.api.leave(this.conversation.id);
            this.ctx.announce(block ? 'Blockiert und gelöscht.' : 'Anfrage gelöscht. Die Person erfährt davon nichts.');
            window.location.hash = '';
        } catch (error) {
            showError(this.error, describeError(error));
        }
    }

    /** Tell the server the chat was seen and update the count at "Chats"; a failure only leaves the badge stale. */
    markRead(conversationId) {
        this.ctx.api.markRead(conversationId).then((result) => {
            document.querySelectorAll('[data-chat-badge]').forEach((badge) => {
                badge.hidden = result.count === 0;
                badge.querySelector('[data-chat-count]').textContent = String(result.count);
            });
        }).catch(() => {});
    }

    /** Try again to decrypt messages that had no key (after a refresh brought new grants). */
    async redecrypt() {
        const token = this.openCount;
        const conversationId = this.conversation.id;
        let changed = false;
        for (const [index, item] of this.timeline.items.entries()) {
            if (item.state === MessageState.NO_KEY) {
                const reopened = await openMessage(this.ctx.session, conversationId, item.envelope, this.ctx.clock.now());
                if (!this.isCurrent(token)) {
                    return;
                }
                if (reopened && reopened.state !== MessageState.NO_KEY) {
                    this.timeline.items[index] = reopened;
                    changed = true;
                }
            }
        }
        if (changed) {
            this.renderMessages();
        }
    }

    async send() {
        const textarea = this.composer.elements.message;
        const text = textarea.value.trim();
        if (text === '' || measureDraft(text).tooLong || textarea.disabled) {
            return;
        }
        showError(this.error, '');
        // Passwords, card numbers, IBANs or an address: warn once; pressing "Senden" again sends this exact text.
        const { sensitive } = scanOutgoing(text, this.ctx.safety.tone);
        if (sensitive.length > 0 && this.confirmedDraft !== text) {
            this.confirmedDraft = text;
            this.showHint(`Du bist dabei, ${sensitive.join(' und ')} zu verschicken. Oasis fragt dich nie im Chat danach – sei vorsichtig, besonders bei Menschen, die du noch nicht kennst. Zum Senden tippe noch einmal auf „Senden“.`);
            return;
        }
        try {
            const envelope = await sealMessage(this.ctx.session, this.conversation, this.ctx.clock, text);
            await this.ctx.api.sendMessage(this.conversation.id, envelope);
            textarea.value = '';
            this.confirmedDraft = null;
            this.updateCounter();
            this.updateHint();
            await this.receive(envelope);
            this.ctx.announce('Gesendet.');
        } catch (error) {
            showError(this.error, describeError(error));
            if (error instanceof ApiError && error.status === 409) {
                await this.refresh(this.conversation.id).catch((failure) => this.handleFailure(failure));
            }
        }
    }

    /** Tone hint while typing (same rule as Tropfen); a pending sensitive-data warning ends when the text changes. */
    updateHint() {
        const text = this.composer.elements.message.value;
        if (this.confirmedDraft !== null && this.confirmedDraft !== text.trim()) {
            this.confirmedDraft = null;
        }
        const { tone } = scanOutgoing(text, this.ctx.safety.tone);
        this.showHint(tone ? `Kurzer Hinweis: „${tone}“ wirkt schnell herablassend. Bitte achte auf einen freundlichen Ton.` : '');
    }

    showHint(message) {
        setText(this.hint, message);
        this.hint.hidden = message === '';
    }

    /**
     * Show the safety banner for the newest flagged message from someone else (not dismissed). In the first messages
     * of a person, such requests are a typical warning sign, so the text says so.
     */
    checkSafety() {
        const myId = this.ctx.session.userId;
        const incoming = this.timeline.items.filter((item) => item.senderId !== myId && item.state === MessageState.READABLE);
        let found = null;
        for (const item of [...incoming].reverse()) {
            const result = this.dismissed.has(item.signature) ? null : scanIncoming(item.plaintext, this.ctx.safety.lists);
            if (result) {
                found = { item, result };
                break;
            }
        }
        this.flagged = found?.item ?? null;
        this.safety.hidden = found === null;
        if (found === null) {
            return;
        }
        const name = this.senderName(found.item.senderId);
        const early = incoming.filter((item) => item.senderId === found.item.senderId).length <= 3;
        const advice = {
            phishing: `${name} fragt nach Passwort, Bank- oder Kartendaten oder deiner Adresse. Gib so etwas nie im Chat heraus – Oasis fragt dich nie danach.`,
            solicitation: `Die Nachricht von ${name} wirkt wie ein sexuelles Angebot oder eine sexuelle Anfrage. Das verstößt gegen unsere Regeln.`,
            harassment: `Die Nachricht von ${name} wirkt beleidigend oder bedrohlich. Du musst dir das nicht gefallen lassen.`,
        }[found.result.kind];
        setText($(this.safety, '[data-safety-title]'), `${found.result.title} ${name} blockieren und Chat löschen?`);
        setText($(this.safety, '[data-safety-text]'), early ? `${advice} Gerade in den ersten Nachrichten ist das ein typisches Warnzeichen.` : advice);
    }

    /** Block the sender of the flagged message, leave the chat and go back to the list. */
    async blockAndLeave() {
        if (!this.flagged || !window.confirm(`${this.senderName(this.flagged.senderId)} blockieren und diesen Chat löschen?`)) {
            return;
        }
        const conversationId = this.conversation.id;
        try {
            await this.ctx.api.block(this.flagged.senderId);
            await this.ctx.api.leave(conversationId).catch((error) => {
                // The owner of a group cannot leave it; the block alone already stops the messages.
                if (!(error instanceof ApiError) || error.status !== 422) {
                    throw error;
                }
            });
            this.ctx.announce('Blockiert. Der Chat ist gelöscht.');
            window.location.hash = '';
        } catch (error) {
            showError(this.error, describeError(error));
        }
    }

    updateCounter() {
        const { characters, tooLong } = measureDraft(this.composer.elements.message.value);
        setText(this.counter, tooLong ? 'Zu lang' : `${characters} / ${MAX_MESSAGE_LENGTH}`);
        this.counter.classList.toggle('text-sun-text', tooLong);
    }

    renderMessages() {
        const myId = this.ctx.session.userId;
        this.messages.replaceChildren(...this.timeline.items.map((item) => {
            const element = fromTemplate(this.screen, '[data-message]');
            const mine = item.senderId === myId;
            const bubble = $(element, '[data-bubble]');
            bubble.classList.add(...(mine ? ['self-end', 'bg-night', 'text-on-night'] : ['self-start', 'bg-paper', 'text-ink']));
            $(element, '[data-footer]').classList.add(mine ? 'self-end' : 'self-start');
            setText($(element, '[data-sender]'), mine ? 'Du' : this.senderName(item.senderId));
            const text = $(element, '[data-text]');
            if (item.state === MessageState.READABLE) {
                setText(text, item.plaintext);
            } else {
                text.classList.add('italic');
                setText(text, item.state === MessageState.NO_KEY
                    ? 'Für diese Nachricht hast du keinen Schlüssel.'
                    : 'Diese Nachricht ließ sich nicht prüfen und wird nicht angezeigt.');
            }
            setText($(element, '[data-expiry]'), `löscht sich ${formatDateTime(item.expiresAt)}`);
            if (!mine && item.state === MessageState.READABLE) {
                const report = $(element, '[data-report]');
                report.hidden = false;
                report.setAttribute('aria-label', `Nachricht von ${this.senderName(item.senderId)} melden`);
                report.addEventListener('click', () => this.ctx.report.open(this.conversation.id, item, this.senderName(item.senderId)));
            }
            return element;
        }));
        this.empty.hidden = this.timeline.items.length > 0;
        this.checkSafety();
    }

    senderName(userId) {
        const user = this.users.get(userId);
        return user ? (user.display_name || `@${user.username}`) : 'Unbekannt';
    }

    handleFailure(error) {
        this.ctx.fail(describeError(error));
        if (error instanceof ApiError && [403, 404].includes(error.status)) {
            this.close();
            this.ctx.navigate('');
        }
    }
}

/**
 * Warnings about keys: replaced identities (server-recorded), keys that changed while this tab was open, and bundles
 * that failed verification.
 *
 * @returns {string[]}
 */
export function keyWarnings(conversation, directory, myId) {
    const name = (id) => {
        const user = directory.users.find((candidate) => candidate.id === id);
        return user ? (user.display_name || `@${user.username}`) : 'Jemand';
    };
    const warnings = [];
    for (const member of conversation.members) {
        const user = directory.users.find((candidate) => candidate.id === member.user_id);
        if (member.user_id !== myId && user?.key_changed_at) {
            warnings.push(`${name(member.user_id)} hat am ${new Date(user.key_changed_at).toLocaleDateString('de-DE')} neue Chat-Schlüssel erstellt. Vergleicht eure Sicherheitsnummer erneut.`);
        }
    }
    for (const id of directory.changedInTab) {
        warnings.push(`Die Schlüssel von ${name(id)} haben sich gerade geändert. Vergleicht eure Sicherheitsnummer, bevor du Vertrauliches schreibst.`);
    }
    for (const id of directory.invalid) {
        warnings.push(`Die Schlüssel von ${name(id)} sind ungültig. Diese Person bekommt keinen Schlüssel für den Chat.`);
    }
    return warnings;
}
