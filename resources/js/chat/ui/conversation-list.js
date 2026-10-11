/**
 * Oasis chat UI – screen "list": the member's conversations, 30 per page, newest first.
 *
 * Markup: resources/views/app/chat/partials/list.blade.php.
 */

import { $, fromTemplate, lifetimeLabel, setText } from './dom.js';
import { describeError } from './errors.js';

export class ConversationList {
    /** @param {object} ctx shared chat context (see app.js) */
    constructor(ctx) {
        this.ctx = ctx;
        this.screen = ctx.screens.list;
        this.list = $(this.screen, '[data-conversation-list]');
        this.empty = $(this.screen, '[data-empty]');
        this.more = $(this.screen, '[data-more]');
        this.nextPage = null;

        this.more.addEventListener('click', () => this.load(this.nextPage));
        $(this.screen, '[data-lock]').addEventListener('click', () => ctx.lock());
    }

    /** Render the first page. */
    async open() {
        this.list.replaceChildren();
        await this.load(1);
    }

    async load(page) {
        try {
            const result = await this.ctx.api.conversations(page);
            for (const conversation of result.data) {
                this.list.append(this.item(conversation));
            }
            this.nextPage = result.current_page < result.last_page ? result.current_page + 1 : null;
            this.more.hidden = this.nextPage === null;
            this.empty.hidden = this.list.children.length > 0;
        } catch (error) {
            this.ctx.fail(describeError(error));
        }
    }

    item(conversation) {
        const element = fromTemplate(this.screen, '[data-conversation-item]');
        const link = $(element, '[data-link]');
        link.href = `#c/${conversation.id}`;
        setText($(element, '[data-title]'), conversationTitle(conversation, this.ctx.session.userId));
        const people = conversation.members.length;
        const meta = [
            conversation.type === 'group' ? `Gruppe · ${people} Menschen` : 'Zu zweit',
            `löscht nach ${lifetimeLabel(conversation.lifetime_days)}`,
        ];
        if (conversation.rotation_required) {
            meta.push('wartet auf neuen Schlüssel');
        }
        setText($(element, '[data-meta]'), meta.join(' · '));
        return element;
    }
}

/**
 * Display title: the group title, or the other person's name in a 1:1 chat.
 *
 * @param {object} conversation
 * @param {string} myId
 * @returns {string}
 */
export function conversationTitle(conversation, myId) {
    if (conversation.title) {
        return conversation.title;
    }
    const others = conversation.members.filter((member) => member.user_id !== myId);
    if (others.length === 0) {
        return 'Nur du';
    }
    return others.map((member) => member.display_name || `@${member.username}`).join(', ');
}
