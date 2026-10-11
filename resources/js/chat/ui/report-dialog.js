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
        this.target = null;

        $(this.form, '[data-cancel]').addEventListener('click', () => this.close());
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
        showError(this.error, '');
        setText($(this.form, '[data-quote]'), `${senderName}: ${item.plaintext}`);
        this.dialog.showModal();
        this.form.elements.reason.focus();
    }

    close() {
        this.dialog.close();
    }

    async send() {
        const reason = this.form.elements.reason.value.trim();
        if (reason === '') {
            showError(this.error, 'Bitte beschreib kurz das Problem.');
            return;
        }
        const { conversationId, item } = this.target;
        try {
            await this.ctx.api.reportMessage(conversationId, item.envelope, item.plaintext, reason);
            this.close();
            this.ctx.announce('Danke. Die Meldung ist bei unserem Moderationsteam.');
        } catch (error) {
            showError(this.error, describeError(error));
        }
    }
}
