/**
 * Oasis chat UI – screen "new": pick people by exact username and start a 1:1 chat or a group.
 *
 * Markup: resources/views/app/chat/partials/new.blade.php.
 * After creation the conversation still needs its first key; the conversation view distributes it (owner).
 */

import { $, fromTemplate, setText, showError } from './dom.js';
import { describeError } from './errors.js';

const MAX_GROUP_OTHERS = 49;

export class NewConversation {
    /** @param {object} ctx shared chat context (see app.js) */
    constructor(ctx) {
        this.ctx = ctx;
        this.screen = ctx.screens.new;
        this.form = $(this.screen, '[data-new-form]');
        this.error = $(this.form, '[data-form-error]');
        this.selectedList = $(this.form, '[data-selected]');
        this.groupOnly = $(this.form, '[data-group-only]');
        /** @type {{id: string, username: string, display_name: string}[]} */
        this.selected = [];

        $(this.form, '[data-add-member]').addEventListener('click', () => this.addFromInput());
        this.form.elements.username.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.addFromInput();
            }
        });
        for (const radio of this.form.elements.type) {
            radio.addEventListener('change', () => this.renderSelection());
        }
        this.form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.create();
        });
    }

    open() {
        this.form.reset();
        this.selected = [];
        showError(this.error, '');
        this.renderSelection();
    }

    get type() {
        return this.form.elements.type.value;
    }

    async addFromInput() {
        const input = this.form.elements.username;
        const username = input.value.trim().replace(/^@/, '').toLowerCase();
        if (username === '') {
            return;
        }
        const limit = this.type === 'direct' ? 1 : MAX_GROUP_OTHERS;
        if (this.selected.length >= limit) {
            showError(this.error, this.type === 'direct'
                ? 'Ein Chat zu zweit hat genau eine andere Person. Wähle „Gruppe“ für mehr.'
                : 'Eine Gruppe hat höchstens 50 Menschen.');
            return;
        }
        try {
            const { data } = await this.ctx.api.findUser(username);
            const user = data[0];
            if (!user) {
                showError(this.error, `@${username} gibt es nicht oder hat den Chat noch nicht eingerichtet.`);
                return;
            }
            if (user.id === this.ctx.session.userId) {
                showError(this.error, 'Du bist automatisch dabei.');
                return;
            }
            if (!this.selected.some((person) => person.id === user.id)) {
                this.selected.push(user);
            }
            input.value = '';
            showError(this.error, '');
            this.renderSelection();
            this.ctx.announce(`@${user.username} hinzugefügt.`);
        } catch (error) {
            showError(this.error, describeError(error));
        }
    }

    renderSelection() {
        this.groupOnly.hidden = this.type !== 'group';
        this.selectedList.replaceChildren(...this.selected.map((person) => {
            const item = fromTemplate(this.screen, '[data-selected-item]');
            setText($(item, '[data-name]'), `@${person.username}`);
            const remove = $(item, '[data-remove]');
            remove.setAttribute('aria-label', `@${person.username} entfernen`);
            remove.addEventListener('click', () => {
                this.selected = this.selected.filter((other) => other.id !== person.id);
                this.renderSelection();
            });
            return item;
        }));
    }

    async create() {
        if (this.selected.length === 0) {
            showError(this.error, 'Füge mindestens eine Person hinzu.');
            return;
        }
        if (this.type === 'direct' && this.selected.length !== 1) {
            showError(this.error, 'Ein Chat zu zweit hat genau eine andere Person.');
            return;
        }
        const title = this.type === 'group' ? this.form.elements.title.value.trim() : '';
        try {
            const { data } = await this.ctx.api.createConversation({
                type: this.type,
                title: title === '' ? null : title,
                member_ids: this.selected.map((person) => person.id),
                lifetime_days: Number(this.form.elements.lifetime_days.value),
            });
            this.ctx.navigate(`c/${data.id}`);
        } catch (error) {
            showError(this.error, describeError(error));
        }
    }
}
