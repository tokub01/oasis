import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const oasisJsPath = path.resolve(__dirname, "../../resources/js/oasis.js");
const oasisCode = fs.readFileSync(oasisJsPath, "utf8");

function loadOasisScript(globals = {}) {
    const sandbox = {
        navigator: {},
        window: { addEventListener: () => {} },
        ...globals,
        document: { querySelectorAll: () => [], addEventListener: () => {}, ...globals.document },
    };
    const context = vm.createContext(sandbox);
    vm.runInContext(oasisCode, context);
    const modules = vm.runInContext("typeof modules !== 'undefined' ? modules : null", context);
    return { context, modules };
}

// ---------------------------------------------------------------------------
// Pricing toggle values & DOM module tests (minimal DOM)
// ---------------------------------------------------------------------------

test("pricing toggle values match PROMPT.md calculation rules", () => {
    // Formulas from PROMPT.md:
    // - Tagespreis monatlich = Preis × 12 / 365
    // - Tagespreis jährlich = Jahrespreis / 365
    // - „entspricht X € im Monat“ = Jahrespreis / 12

    const calcDayCents = (price) => Math.round(price * 100);
    const formatEuroShort = (val) => {
        const rounded2 = Math.round(val * 100) / 100;
        if (Number.isInteger(rounded2)) return `${rounded2} €`;
        return `${rounded2.toFixed(2).replace(".", ",")} €`;
    };

    // Solidar (3 € / 30 €):
    const solidarMonthDay = 3 * 12 / 365; // 0.09863 -> 10 Cent
    const solidarYearDay = 30 / 365;      // 0.08219 -> 8 Cent
    const solidarEquivalent = 30 / 12;    // 2.50 €
    assert.equal(calcDayCents(solidarMonthDay), 10);
    assert.equal(calcDayCents(solidarYearDay), 8);
    assert.equal(formatEuroShort(solidarEquivalent), "2,50 €");

    // Oase (5 € / 50 €):
    const oaseMonthDay = 5 * 12 / 365; // 0.16438 -> 16 Cent
    const oaseYearDay = 50 / 365;      // 0.13698 -> 14 Cent
    const oaseEquivalent = 50 / 12;    // 4.1666... -> 4,17 €
    assert.equal(calcDayCents(oaseMonthDay), 16);
    assert.equal(calcDayCents(oaseYearDay), 14);
    assert.equal(formatEuroShort(oaseEquivalent), "4,17 €");

    // Mäzen (12 € / 120 €):
    const maezenMonthDay = 12 * 12 / 365; // 0.39452 -> 39 Cent
    const maezenYearDay = 120 / 365;      // 0.32876 -> 33 Cent
    const maezenEquivalent = 120 / 12;    // 10 €
    assert.equal(calcDayCents(maezenMonthDay), 39);
    assert.equal(calcDayCents(maezenYearDay), 33);
    assert.equal(formatEuroShort(maezenEquivalent), "10 €");
});

test("modules.pricing: DOM toggle interaction with minimal DOM", () => {
    const { modules } = loadOasisScript();
    assert.ok(modules.pricing, "modules.pricing should be defined");

    // Construct minimal DOM representing the pricing page structure
    const monthButton = {
        dataset: { period: "month" },
        attributes: { "aria-pressed": "true" },
        listeners: {},
        setAttribute(k, v) { this.attributes[k] = String(v); },
        getAttribute(k) { return this.attributes[k]; },
        addEventListener(ev, fn) { this.listeners[ev] = fn; },
    };

    const yearButton = {
        dataset: { period: "year" },
        attributes: { "aria-pressed": "false" },
        listeners: {},
        setAttribute(k, v) { this.attributes[k] = String(v); },
        getAttribute(k) { return this.attributes[k]; },
        addEventListener(ev, fn) { this.listeners[ev] = fn; },
    };

    const toggle = {
        hidden: true,
        querySelectorAll(selector) {
            if (selector === "[data-period]") return [monthButton, yearButton];
            return [];
        },
    };

    // Card price elements with exact values from Preise.dc.html / PROMPT.md
    const items = [
        // Solidar
        { dataset: { month: "3 €", year: "30 €" }, textContent: "3 €" },
        { dataset: { month: "/ Monat", year: "/ Jahr" }, textContent: "/ Monat" },
        { dataset: { month: "rund 10 Cent am Tag", year: "rund 8 Cent am Tag" }, textContent: "rund 10 Cent am Tag" },
        { dataset: { month: "oder 30 € im Jahr", year: "entspricht 2,50 € im Monat" }, textContent: "oder 30 € im Jahr" },
        // Oase
        { dataset: { month: "5 €", year: "50 €" }, textContent: "5 €" },
        { dataset: { month: "/ Monat", year: "/ Jahr" }, textContent: "/ Monat" },
        { dataset: { month: "rund 16 Cent am Tag", year: "rund 14 Cent am Tag" }, textContent: "rund 16 Cent am Tag" },
        { dataset: { month: "oder 50 € im Jahr", year: "entspricht 4,17 € im Monat" }, textContent: "oder 50 € im Jahr" },
        // Mäzen
        { dataset: { month: "ab 12 €", year: "ab 120 €" }, textContent: "ab 12 €" },
        { dataset: { month: "/ Monat", year: "/ Jahr" }, textContent: "/ Monat" },
        { dataset: { month: "ab rund 39 Cent am Tag", year: "ab rund 33 Cent am Tag" }, textContent: "ab rund 39 Cent am Tag" },
        { dataset: { month: "oder ab 120 € im Jahr", year: "entspricht 10 € im Monat" }, textContent: "oder ab 120 € im Jahr" },
    ];

    const root = {
        querySelector(selector) {
            if (selector === "[data-period-toggle]") return toggle;
            return null;
        },
        querySelectorAll(selector) {
            if (selector === "[data-month][data-year]") return items;
            return [];
        },
    };

    // Initialize pricing module
    modules.pricing(root);

    // 1. Toggle should be revealed
    assert.equal(toggle.hidden, false, "Toggle container should be unhidden when JS runs");

    // 2. Click yearly button
    yearButton.listeners.click();

    // Verify aria-pressed states
    assert.equal(yearButton.getAttribute("aria-pressed"), "true");
    assert.equal(monthButton.getAttribute("aria-pressed"), "false");

    // Verify all text contents switched to yearly values
    assert.equal(items[0].textContent, "30 €");
    assert.equal(items[1].textContent, "/ Jahr");
    assert.equal(items[2].textContent, "rund 8 Cent am Tag");
    assert.equal(items[3].textContent, "entspricht 2,50 € im Monat");

    assert.equal(items[4].textContent, "50 €");
    assert.equal(items[5].textContent, "/ Jahr");
    assert.equal(items[6].textContent, "rund 14 Cent am Tag");
    assert.equal(items[7].textContent, "entspricht 4,17 € im Monat");

    assert.equal(items[8].textContent, "ab 120 €");
    assert.equal(items[9].textContent, "/ Jahr");
    assert.equal(items[10].textContent, "ab rund 33 Cent am Tag");
    assert.equal(items[11].textContent, "entspricht 10 € im Monat");

    // 3. Click monthly button to switch back
    monthButton.listeners.click();

    assert.equal(monthButton.getAttribute("aria-pressed"), "true");
    assert.equal(yearButton.getAttribute("aria-pressed"), "false");

    assert.equal(items[0].textContent, "3 €");
    assert.equal(items[1].textContent, "/ Monat");
    assert.equal(items[2].textContent, "rund 10 Cent am Tag");
    assert.equal(items[3].textContent, "oder 30 € im Jahr");

    assert.equal(items[4].textContent, "5 €");
    assert.equal(items[5].textContent, "/ Monat");
    assert.equal(items[6].textContent, "rund 16 Cent am Tag");
    assert.equal(items[7].textContent, "oder 50 € im Jahr");

    assert.equal(items[8].textContent, "ab 12 €");
    assert.equal(items[9].textContent, "/ Monat");
    assert.equal(items[10].textContent, "ab rund 39 Cent am Tag");
    assert.equal(items[11].textContent, "oder ab 120 € im Jahr");
});

test("membership module is gone from oasis.js (moved to Livewire Checkout) and livewire:navigated initializes modules once", () => {
    let navigatedHandler = null;
    const button = { dataset: { oasis: "share", url: "https://get-oasis.com/@mira" }, hidden: true };

    const { modules, context } = loadOasisScript({
        navigator: { share: async () => {} },
        document: {
            querySelectorAll(selector) {
                return selector === "[data-oasis]" ? [button] : [];
            },
            addEventListener(event, fn) {
                if (event === "livewire:navigated") {
                    navigatedHandler = fn;
                }
            },
        },
    });

    assert.equal(modules.membership, undefined);
    assert.equal(vm.runInContext("typeof membershipCalc", context), "undefined");
    assert.equal(typeof navigatedHandler, "function");

    navigatedHandler();
    assert.equal(button.hidden, false);
    assert.equal(button.dataset.oasisStarted, "true");

    // A repeated livewire:navigated skips an element that already started (it keeps its marker).
    button.hidden = true;
    navigatedHandler();
    assert.equal(button.hidden, true);
});

test("modules['help-search']: FAQ filtering with minimal DOM", () => {
    const searchInput = {
        value: "",
        listeners: {},
        addEventListener(ev, fn) { this.listeners[ev] = fn; },
    };
    const status = { textContent: "" };

    const form = {
        hidden: true,
        listeners: {},
        querySelector(selector) {
            if (selector === "input[type='search']") return searchInput;
            if (selector === "[data-help-status]") return status;
            return null;
        },
        addEventListener(ev, fn) { this.listeners[ev] = fn; },
    };

    const details1 = { textContent: "Wie kündige ich meinen Vertrag?", open: false, hidden: false };
    const details2 = { textContent: "Gibt es Ermäßigungen?", open: true, hidden: false };
    const group1 = {
        hidden: false,
        querySelectorAll(selector) {
            if (selector === "details") return [details1, details2];
            return [];
        },
    };

    const details3 = { textContent: "Kann ich Beiträge exportieren?", open: false, hidden: false };
    const group2 = {
        hidden: false,
        querySelectorAll(selector) {
            if (selector === "details") return [details3];
            return [];
        },
    };

    const { modules } = loadOasisScript({
        document: {
            querySelectorAll(selector) {
                if (selector === "[data-help-group]") return [group1, group2];
                return [];
            },
        },
    });

    assert.ok(modules["help-search"], "modules['help-search'] should be defined");

    // Initialize
    modules["help-search"](form);
    assert.equal(form.hidden, false, "Search form should become unhidden");

    // 1. Search for "vertrag"
    searchInput.value = "vertrag";
    searchInput.listeners.input();

    assert.equal(details1.hidden, false);
    assert.equal(details1.open, true);
    assert.equal(details2.hidden, true);
    assert.equal(group1.hidden, false);
    assert.equal(details3.hidden, true);
    assert.equal(group2.hidden, true);
    assert.equal(status.textContent, "");

    // 2. Search for non-existent query
    searchInput.value = "astronaut";
    searchInput.listeners.input();

    assert.equal(details1.hidden, true);
    assert.equal(details2.hidden, true);
    assert.equal(details3.hidden, true);
    assert.equal(group1.hidden, true);
    assert.equal(group2.hidden, true);
    assert.equal(status.textContent, "Zu „astronaut“ haben wir keine passende Frage gefunden.");

    // 3. Clear query
    searchInput.value = "";
    searchInput.listeners.input();

    assert.equal(details1.hidden, false);
    assert.equal(details1.open, false); // restored to initial wasOpen
    assert.equal(details2.hidden, false);
    assert.equal(details2.open, true);  // restored to initial wasOpen
    assert.equal(details3.hidden, false);
    assert.equal(details3.open, false);
    assert.equal(group1.hidden, false);
    assert.equal(group2.hidden, false);
    assert.equal(status.textContent, "");
});



test("modules['reveal-image']: the covered image is only requested after the tap", () => {
    const { modules } = loadOasisScript();
    const image = { dataset: { src: "/tropfen/1/bilder/1" }, hidden: true, src: "" };
    const cover = { removed: false, remove() { this.removed = true; } };
    let onClick = null;
    const reveal = { addEventListener: (type, handler) => { onClick = handler; } };
    const root = {
        style: { removed: [], removeProperty(name) { this.removed.push(name); } },
        querySelector: (selector) => ({ "[data-reveal]": reveal, "img[data-src]": image, "[data-cover]": cover })[selector],
    };

    modules["reveal-image"](root);
    assert.equal(image.src, "", "nothing is loaded before the tap");

    let prevented = false;
    onClick({ preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(image.src, "/tropfen/1/bilder/1");
    assert.equal(image.hidden, false);
    assert.equal(cover.removed, true);
});
