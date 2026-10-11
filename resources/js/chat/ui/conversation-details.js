/**
 * Oasis chat UI – screen "details": lifetime, members and pairwise safety numbers of one chat.
 *
 * Markup: resources/views/app/chat/partials/details.blade.php.
 * Only the owner may change the lifetime and (in groups) add or remove members; the server enforces this too.
 * After a membership change the conversation needs a new key, which the conversation view distributes on return.
 */

import { loadDirectory, safetyNumber } from '../client/keyring.js';
import { $, fromTemplate, setText, showError } from './dom.js';
import { describeError } from './errors.js';

export class ConversationDetails {
    /** @param {object} ctx shared chat context (see app.js) */
    constructor(ctx) {
        this.ctx = ctx;
        this.screen = ctx.screens.details;
        this.lifetimeForm = $(this.screen, '[data-lifetime-form]');
        this.addForm = $(this.screen, '[data-add-form]');
        this.members = $(this.screen, '[data-members]');
        this.error = $(this.screen, '[data-form-error]');
        this.conversation = null;

        this.lifetimeForm.addEventListener('submit', (event) => {
            event.preventDefault();
            this.saveLifetime();
        });
        this.addForm.addEventListener('submit', (event) => {
            event.preventDefault();
            this.addMember();
        });
    }

    /** @param {string} conversationId */
    async open(conversationId) {
        showError(this.error, '');
        $(this.screen, '[data-back]').href = `#c/${conversationId}`;
        try {
            const { data } = await this.ctx.api.conversation(conversationId);
            await this.render(data);
        } catch (error) {
            this.ctx.fail(describeError(error));
        }
    }

    get isOwner() {
        return this.conversation.created_by === this.ctx.session.userId;
    }

    async render(conversation) {
        this.conversation = conversation;
        const directory = await loadDirectory(this.ctx.api, this.ctx.session, conversation.id);
        const select = this.lifetimeForm.elements.lifetime_days;
        select.value = String(conversation.lifetime_days);
        select.disabled = !this.isOwner;
        $(this.lifetimeForm, '[data-owner-only]').hidden = !this.isOwner;
        this.addForm.hidden = !(this.isOwner && conversation.type === 'group');

        const items = [];
        for (const member of conversation.members) {
            items.push(await this.memberItem(member, directory));
        }
        this.members.replaceChildren(...items);
    }

    async memberItem(member, directory) {
        const myId = this.ctx.session.userId;
        const element = fromTemplate(this.screen, '[data-member]');
        const user = directory.users.find((candidate) => candidate.id === member.user_id);
        const isMe = member.user_id === myId;
        setText($(element, '[data-name]'), `${member.display_name || member.username} (@${member.username})${isMe ? ' – du' : ''}${member.role === 'owner' ? ' · hat den Chat gestartet' : ''}`);

        const number = $(element, '[data-number]');
        if (isMe) {
            number.remove();
        } else {
            const digits = await safetyNumber(this.ctx.session, member.user_id);
            setText(number, digits ?? 'Keine gültigen Schlüssel');
            number.setAttribute('aria-label', `Sicherheitsnummer mit ${member.username}: ${digits ?? 'keine'}`);
        }
        const warning = $(element, '[data-warning]');
        if (!isMe && (user?.key_changed_at || directory.changedInTab.includes(member.user_id))) {
            warning.hidden = false;
            setText(warning, 'Neue Schlüssel – Nummer erneut vergleichen.');
        }

        const remove = $(element, '[data-remove]');
        if (this.isOwner && this.conversation.type === 'group' && !isMe) {
            remove.hidden = false;
            remove.setAttribute('aria-label', `@${member.username} aus dem Chat entfernen`);
            remove.addEventListener('click', () => this.removeMember(member));
        }
        return element;
    }

    async saveLifetime() {
        try {
            const { data } = await this.ctx.api.updateConversation(this.conversation.id, {
                lifetime_days: Number(this.lifetimeForm.elements.lifetime_days.value),
            });
            await this.render(data);
            this.ctx.announce('Gespeichert. Gilt für neue Nachrichten.');
        } catch (error) {
            showError(this.error, describeError(error));
        }
    }

    async addMember() {
        const input = this.addForm.elements.username;
        const username = input.value.trim().replace(/^@/, '').toLowerCase();
        if (username === '') {
            return;
        }
        try {
            const { data: users } = await this.ctx.api.findUser(username);
            if (!users[0]) {
                showError(this.error, `@${username} gibt es nicht oder hat den Chat noch nicht eingerichtet.`);
                return;
            }
            const { data } = await this.ctx.api.addMember(this.conversation.id, users[0].id);
            input.value = '';
            showError(this.error, '');
            await this.render(data);
            this.ctx.announce(`@${username} hinzugefügt. Der Chat bekommt einen neuen Schlüssel.`);
        } catch (error) {
            showError(this.error, describeError(error));
        }
    }

    async removeMember(member) {
        try {
            const { data } = await this.ctx.api.removeMember(this.conversation.id, member.user_id);
            showError(this.error, '');
            await this.render(data);
            this.ctx.announce(`@${member.username} entfernt. Der Chat bekommt einen neuen Schlüssel.`);
        } catch (error) {
            showError(this.error, describeError(error));
        }
    }
}
