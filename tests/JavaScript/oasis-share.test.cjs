const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
const path = require("node:path");

function setup(navigator) {
    const context = vm.createContext({ navigator, document: { querySelectorAll: () => [] } });
    vm.runInContext(fs.readFileSync(path.join(__dirname, "../../resources/js/oasis.js"), "utf8"), context);
    const share = vm.runInContext("modules.share", context);
    const attributes = {};
    let click;
    const button = {
        hidden: true,
        dataset: { url: "https://oasis.example.tld/profil/mira" },
        setAttribute: (name, value) => { attributes[name] = value; },
        addEventListener: (event, callback) => {
            assert.equal(event, "click");
            click = callback;
        },
    };
    share(button);
    return { button, attributes, click };
}

test("sharing stays hidden when neither sharing nor clipboard is available", () => {
    const { button, click } = setup({});
    assert.equal(button.hidden, true);
    assert.equal(click, undefined);
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
