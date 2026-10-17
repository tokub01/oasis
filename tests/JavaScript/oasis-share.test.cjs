/* Profile-sharing enhancement: only expose available APIs and announce successful copying after it completes. */

const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
const path = require("node:path");

/** Loads oasis.js with the given navigator; returns a revealed fake button, its share action and the document listeners. */
function setup(navigator) {
    const listeners = {};
    const document = {
        querySelectorAll: () => [],
        addEventListener: (event, callback) => { listeners[event] = callback; },
    };
    const context = vm.createContext({ navigator, document, window: { addEventListener: () => {} } });
    vm.runInContext(fs.readFileSync(path.join(__dirname, "../../resources/js/oasis.js"), "utf8"), context);
    const share = vm.runInContext("modules.share", context);
    const attributes = {};
    const button = {
        hidden: true,
        dataset: { url: "https://oasis.example.tld/profil/mira" },
        setAttribute: (name, value) => { attributes[name] = value; },
    };
    share(button);
    const shareLink = vm.runInContext("shareLink", context);
    return { button, attributes, click: () => shareLink(button), share, listeners };
}

test("sharing stays hidden when neither sharing nor clipboard is available", () => {
    const { button } = setup({});
    assert.equal(button.hidden, true);
});

test("revealing again after a Livewire re-render is harmless", () => {
    const { button, share } = setup({ clipboard: { writeText: async () => {} } });
    button.hidden = true;
    share(button);
    assert.equal(button.hidden, false);
});

test("a click inside a share button reaches it through the one delegated listener", async () => {
    let sharedUrl;
    const { button, listeners } = setup({ share: async ({ url }) => { sharedUrl = url; } });
    listeners.click({ target: { closest: (selector) => (selector === '[data-oasis="share"]' ? button : null) } });
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(sharedUrl, button.dataset.url);
});

test("clicks outside a share button do nothing", () => {
    let copied = false;
    const listeners = {};
    const context = vm.createContext({
        navigator: { clipboard: { writeText: async () => { copied = true; } } },
        window: { addEventListener: () => {} },
        document: { querySelectorAll: () => [], addEventListener: (event, callback) => { listeners[event] = callback; } },
    });
    vm.runInContext(fs.readFileSync(path.join(__dirname, "../../resources/js/oasis.js"), "utf8"), context);
    listeners.click({ target: { closest: () => null } });
    assert.equal(copied, false);
});

test("native sharing sends the actual profile URL and takes precedence over clipboard", async () => {
    let sharedUrl;
    let copied = false;
    const { button, click } = setup({
        share: async ({ url }) => { sharedUrl = url; },
        clipboard: { writeText: async () => { copied = true; } },
    });
    assert.equal(button.hidden, false);
    await click();
    assert.equal(sharedUrl, button.dataset.url);
    assert.equal(copied, false);
});

test("clipboard fallback copies the profile URL and announces success after copying", async () => {
    let copiedUrl;
    const { button, attributes, click } = setup({
        clipboard: { writeText: async (url) => { copiedUrl = url; } },
    });
    assert.equal(button.hidden, false);
    assert.equal(attributes["aria-label"], undefined);
    await click();
    assert.equal(copiedUrl, button.dataset.url);
    assert.equal(attributes["aria-label"], "Link kopiert");
});

test("failed copying does not claim that the link was copied", async () => {
    const { attributes, click } = setup({
        clipboard: { writeText: async () => { throw new Error("Permission denied"); } },
    });
    await click();
    assert.equal(attributes["aria-label"], undefined);
});

test("clipboard fallback displays visible feedback when data-share-label is present", async () => {
    const { button, click } = setup({
        clipboard: { writeText: async () => {} },
    });
    const label = { textContent: "Link kopieren" };
    button.querySelector = (selector) => (selector === "[data-share-label]" ? label : null);
    await click();
    assert.equal(label.textContent, "Kopiert!");
});
