/**
 * Oasis chat UI – small DOM helpers shared by the screens.
 *
 * Security note: every text that may come from other people is set with `textContent` (via `setText`); nothing in
 * the chat UI injects HTML.
 */

/** @returns {Element} the first match inside `root`; throws so a missing hook fails loudly during development */
export function $(root, selector) {
    const element = root.querySelector(selector);
    if (!element) {
        throw new Error(`Missing element ${selector}`);
    }
    return element;
}

/** Clone the first element of a `<template>`. */
export function fromTemplate(root, selector) {
    return $(root, selector).content.firstElementChild.cloneNode(true);
}

export function setText(element, text) {
    element.textContent = text ?? '';
}

/** Show a message in a `[data-form-error]` element, or hide it with an empty message. */
export function showError(element, message) {
    setText(element, message);
    element.hidden = !message;
}

/**
 * Disable a form's controls while `task` runs and show its `[data-busy]` hint, if any.
 *
 * @template T
 * @param {HTMLFormElement} form
 * @param {() => Promise<T>} task
 * @returns {Promise<T>}
 */
export async function whileBusy(form, task) {
    const controls = [...form.elements];
    const busy = form.querySelector('[data-busy]');
    controls.forEach((control) => { control.disabled = true; });
    if (busy) {
        busy.hidden = false;
    }
    form.setAttribute('aria-busy', 'true');
    try {
        return await task();
    } finally {
        controls.forEach((control) => { control.disabled = false; });
        if (busy) {
            busy.hidden = true;
        }
        form.removeAttribute('aria-busy');
    }
}

const dateTime = new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' });

/** German date and time, e.g. "12.10.2026, 14:05". */
export function formatDateTime(value) {
    return dateTime.format(new Date(value));
}

/** "1 Tag" / "7 Tagen" as used in "löschen sich nach …". */
export function lifetimeLabel(days) {
    return days === 1 ? '1 Tag' : `${days} Tagen`;
}
