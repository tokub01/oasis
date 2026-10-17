/**
 * Oasis chat UI – dialog "report": forward one decrypted message to moderation (consent-based reporting).
 *
 * Markup: resources/views/app/chat/partials/report.blade.php.
 * Sends the original signed envelope (so the server can verify sender, conversation and times) together with the
 * plaintext the reporter sees and the reason. The server cannot check plaintext against ciphertext and marks it as
 * "reported by a participant".
 */

import { $, setText, showError } from './dom.js';
import { describeError } from './errors.js';

export class ReportDialog {
    /** @param {object} ctx shared chat context (see app.js) */
    constructor(ctx) {
        this.ctx = ctx;
        this.dialog = $(ctx.root, '[data-report-dialog]');
        this.form = $(this.dialog, '[data-report-form]');
        this.error = $(this.form, '[data-form-error]');
        this.thanks = $(this.dialog, '[data-report-thanks]');
        this.target = null;

        $(this.form, '[data-cancel]').addEventListener('click', () => this.close());
        $(this.thanks, '[data-report-done]').addEventListener('click', () => this.close());
        $(this.thanks, '[data-report-block]').addEventListener('click', () => this.block());
        this.dialog.addEventListener('close', () => { this.target = null; });
        this.form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.send();
        });
    }

    /**
     * @param {string} conversationId
     * @param {{envelope: object, plaintext: string}} item a readable message of another member
     * @param {string} senderName
     */
    open(conversationId, item, senderName) {
        this.target = { conversationId, item };
        this.form.reset();
        this.form.hidden = false;
        this.thanks.hidden = true;
        showError(this.error, '');
        setText($(this.form, '[data-quote]'), `${senderName}: ${item.plaintext}`);
        this.dialog.showModal();
    }

    /** Close and forget the quoted plaintext (also called when the chat is locked). */
    close() {
        if (this.dialog.open) {
            this.dialog.close();
        }
        setText($(this.form, '[data-quote]'), '');
        this.form.reset();
        this.form.hidden = false;
        this.thanks.hidden = true;
        $(this.thanks, '[data-report-blocked]').hidden = true;
        this.target = null;
    }

    async send() {
        const category = this.form.elements.category.value;
        if (category === '') {
            showError(this.error, 'Wähl bitte aus, was nicht stimmt.');
            return;
        }
        const comment = this.form.elements.reason.value.trim();
        const reason = comment === '' ? category : `${category}\n\n${comment}`;
        const { conversationId, item } = this.target;
        try {
            await this.ctx.api.reportMessage(conversationId, item.envelope, item.plaintext, reason);
            // The quoted plaintext is not needed any more; keep only who sent it, for the block button.
            setText($(this.form, '[data-quote]'), '');
            this.form.hidden = true;
            this.thanks.hidden = false;
            this.ctx.announce('Danke. Die Meldung ist bei unserem Moderationsteam.');
        } catch (error) {
            showError(this.error, describeError(error));
        }
    }

    /** Block the sender of the reported message from the thanks screen. */
    async block() {
        try {
            await this.ctx.api.block(this.target.item.senderId);
            $(this.thanks, '[data-report-blocked]').hidden = false;
            $(this.thanks, '[data-report-block]').hidden = true;
        } catch (error) {
            this.ctx.announce(describeError(error));
        }
    }
}
