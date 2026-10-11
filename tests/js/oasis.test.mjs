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
        document: { querySelectorAll: () => [] },
        navigator: {},
        ...globals,
    };
    const context = vm.createContext(sandbox);
    vm.runInContext(oasisCode, context);
    const membershipCalc = vm.runInContext("typeof membershipCalc !== 'undefined' ? membershipCalc : null", context);
    const modules = vm.runInContext("typeof modules !== 'undefined' ? modules : null", context);
    return { context, membershipCalc, modules };
}

// ---------------------------------------------------------------------------
// 1. Pure calculation tests (membershipCalc)
// ---------------------------------------------------------------------------

test("membershipCalc: German money formatting", () => {
    const { membershipCalc } = loadOasisScript();
    assert.ok(membershipCalc, "membershipCalc should be defined in oasis.js");

    assert.equal(membershipCalc.formatEuro(0), "0,00 €");
    assert.equal(membershipCalc.formatEuro(5), "5,00 €");
    assert.equal(membershipCalc.formatEuro(3), "3,00 €");
    assert.equal(membershipCalc.formatEuro(12), "12,00 €");
    assert.equal(membershipCalc.formatEuro(1234.5), "1.234,50 €");
    assert.equal(membershipCalc.formatEuro(0.7983), "0,80 €");
    assert.equal(membershipCalc.formatEuro(7.9831), "7,98 €");
    assert.equal(membershipCalc.formatEuro(1000000), "1.000.000,00 €");
    assert.equal(membershipCalc.formatEuro(4.166666667), "4,17 €");
    assert.equal(membershipCalc.formatEuro(2.5), "2,50 €");
});

test("membershipCalc: VAT included (x 19 / 119)", () => {
    const { membershipCalc } = loadOasisScript();
    const rate = 19;

    // Gross 119 € contains exactly 19 € VAT
    assert.equal(membershipCalc.vatIncluded(119, rate), 19);

    // Gross 5 € (Oase monatlich): 5 * 19 / 119 = 0.798319... €
    const vat5 = membershipCalc.vatIncluded(5, rate);
    assert.ok(Math.abs(vat5 - (5 * 19 / 119)) < 1e-9);
    assert.equal(membershipCalc.formatEuro(vat5), "0,80 €");

    // Gross 50 € (Oase jährlich): 50 * 19 / 119 = 7.98319... €
    const vat50 = membershipCalc.vatIncluded(50, rate);
    assert.ok(Math.abs(vat50 - (50 * 19 / 119)) < 1e-9);
    assert.equal(membershipCalc.formatEuro(vat50), "7,98 €");

    // Gross 3 € (Solidar monatlich): 3 * 19 / 119 = 0.47899... € -> "0,48 €"
    const vat3 = membershipCalc.vatIncluded(3, rate);
    assert.equal(membershipCalc.formatEuro(vat3), "0,48 €");

    // Gross 30 € (Solidar jährlich): 30 * 19 / 119 = 4.7899... € -> "4,79 €"
    const vat30 = membershipCalc.vatIncluded(30, rate);
    assert.equal(membershipCalc.formatEuro(vat30), "4,79 €");

    // Gross 12 € (Mäzen min monatlich): 12 * 19 / 119 = 1.91596... € -> "1,92 €"
    const vat12 = membershipCalc.vatIncluded(12, rate);
    assert.equal(membershipCalc.formatEuro(vat12), "1,92 €");

    // Gross 120 € (Mäzen min jährlich): 120 * 19 / 119 = 19.1596... € -> "19,16 €"
    const vat120 = membershipCalc.vatIncluded(120, rate);
    assert.equal(membershipCalc.formatEuro(vat120), "19,16 €");
});

test("membershipCalc: yearly = monthly * 10 (yearMonths)", () => {
    const { membershipCalc } = loadOasisScript();
    const yearMonths = 10;

    // Solidar (3 €)
    assert.equal(membershipCalc.total(3, false, yearMonths), 3);
    assert.equal(membershipCalc.total(3, true, yearMonths), 30);

    // Oase (5 €)
    assert.equal(membershipCalc.total(5, false, yearMonths), 5);
    assert.equal(membershipCalc.total(5, true, yearMonths), 50);

    // Mäzen base (12 €)
    assert.equal(membershipCalc.total(12, false, yearMonths), 12);
    assert.equal(membershipCalc.total(12, true, yearMonths), 120);

    // Custom Mäzen (e.g. 25 €)
    assert.equal(membershipCalc.total(25, false, yearMonths), 25);
    assert.equal(membershipCalc.total(25, true, yearMonths), 250);
});

test("membershipCalc: Mäzen minimum 12 € validation and parsing", () => {
    const { membershipCalc } = loadOasisScript();
    const min = 12;

    // Helper to check result without cross-vm prototype mismatch
    const check = (actual, expectedMonthly, expectedTooLow) => {
        assert.equal(actual.monthly, expectedMonthly);
        assert.equal(actual.tooLow, expectedTooLow);
    };

    // Valid amounts at or above minimum
    check(membershipCalc.parseAmount(12, min), 12, false);
    check(membershipCalc.parseAmount("12", min), 12, false);
    check(membershipCalc.parseAmount("15", min), 15, false);
    check(membershipCalc.parseAmount("15.5", min), 15.5, false);
    check(membershipCalc.parseAmount("15,50", min), 15.5, false);
    check(membershipCalc.parseAmount(" 20,00 ", min), 20, false);

    // Amounts below minimum (flagged tooLow: true, defaults to min)
    check(membershipCalc.parseAmount(11.99, min), 12, true);
    check(membershipCalc.parseAmount("11", min), 12, true);
    check(membershipCalc.parseAmount("5", min), 12, true);
    check(membershipCalc.parseAmount("0", min), 12, true);
    check(membershipCalc.parseAmount("-5", min), 12, true);

    // Invalid / empty inputs (defaults to min, not flagged as tooLow)
    check(membershipCalc.parseAmount("", min), 12, false);
    check(membershipCalc.parseAmount(null, min), 12, false);
    check(membershipCalc.parseAmount(undefined, min), 12, false);
    check(membershipCalc.parseAmount("abc", min), 12, false);
});

// ---------------------------------------------------------------------------
// 2. Pricing toggle values & DOM module tests (minimal DOM)
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

test("modules.membership: summary calculation and state updates with minimal DOM", () => {
    const { modules } = loadOasisScript();
    assert.ok(modules.membership, "modules.membership should be defined");

    const amountInput = {
        value: "12",
        attributes: {},
        listeners: {},
        setAttribute(k, v) { this.attributes[k] = String(v); },
        getAttribute(k) { return this.attributes[k]; },
        addEventListener(ev, fn) { this.listeners[ev] = fn; },
    };

    const amountError = { hidden: true };

    const createRadioGroup = (name) => {
        const group = [];
        return {
            create(value, isChecked, dataset) {
                const radio = {
                    name,
                    value,
                    dataset,
                    _checked: isChecked,
                    get checked() { return this._checked; },
                    set checked(v) {
                        if (v) {
                            group.forEach(other => { other._checked = false; });
                        }
                        this._checked = v;
                    },
                };
                group.push(radio);
                return radio;
            },
        };
    };

    const stufeGroup = createRadioGroup("stufe");
    const stufeInputs = {
        solidar: stufeGroup.create("solidar", false, { label: "Solidar", monthly: "3" }),
        oase: stufeGroup.create("oase", true, { label: "Oase", monthly: "5" }),
        maezen: stufeGroup.create("maezen", false, { label: "Mäzen" }),
    };

    const rhythmusGroup = createRadioGroup("rhythmus");
    const rhythmusInputs = {
        monat: rhythmusGroup.create("monat", true, { label: "Monatlich", unit: "/ Monat", term: "Monatlich kündbar …" }),
        jahr: rhythmusGroup.create("jahr", false, { label: "Jährlich", unit: "/ Jahr", term: "Läuft 12 Monate. Danach kannst du monatlich kündigen oder ein neues Jahr abschließen." }),
    };

    const zahlartGroup = createRadioGroup("zahlart");
    const zahlartInputs = {
        sepa: zahlartGroup.create("sepa", true, { label: "SEPA-Lastschrift" }),
        paypal: zahlartGroup.create("paypal", false, { label: "PayPal" }),
        karte: zahlartGroup.create("karte", false, { label: "Kreditkarte" }),
    };

    const outputs = {
        stufe: [],
        rhythmus: [],
        zahlart: [],
        betrag: [],
        einheit: [],
        ust: [],
        laufzeit: [],
    };

    const createOutputEl = (key) => {
        const el = { textContent: "" };
        outputs[key].push(el);
        return el;
    };

    createOutputEl("stufe");
    createOutputEl("rhythmus");
    createOutputEl("zahlart");
    createOutputEl("betrag");
    createOutputEl("einheit");
    createOutputEl("ust");
    createOutputEl("laufzeit");

    const priceLabels = [
        { dataset: { month: "5 €", year: "50 €" }, textContent: "" },
    ];

    const payContainers = [
        { dataset: { pay: "sepa" }, hidden: false },
        { dataset: { pay: "paypal" }, hidden: true },
        { dataset: { pay: "karte" }, hidden: true },
    ];

    let rootChangeHandler;
    const root = {
        dataset: { yearMonths: "10", vatRate: "19", min: "12" },
        querySelector(selector) {
            if (selector === "#m-betrag") return amountInput;
            if (selector === "[data-amount-error]") return amountError;
            if (selector === 'input[name="stufe"]:checked') {
                return Object.values(stufeInputs).find(i => i.checked);
            }
            if (selector === 'input[name="rhythmus"]:checked') {
                return Object.values(rhythmusInputs).find(i => i.checked);
            }
            if (selector === 'input[name="zahlart"]:checked') {
                return Object.values(zahlartInputs).find(i => i.checked);
            }
            if (selector === 'input[name="stufe"][value="maezen"]') return stufeInputs.maezen;
            return null;
        },
        querySelectorAll(selector) {
            const outMatch = selector.match(/\[data-out="(.+?)"\]/);
            if (outMatch) return outputs[outMatch[1]] || [];
            if (selector === "[data-price]") return priceLabels;
            if (selector === "[data-pay]") return payContainers;
            return [];
        },
        addEventListener(ev, fn) {
            if (ev === "change") rootChangeHandler = fn;
        },
    };

    // Initialize module
    modules.membership(root);

    // Initial state: Oase, monthly, SEPA (5 € / month)
    assert.equal(outputs.stufe[0].textContent, "Oase");
    assert.equal(outputs.rhythmus[0].textContent, "Monatlich");
    assert.equal(outputs.betrag[0].textContent, "5,00 €");
    assert.equal(outputs.einheit[0].textContent, "/ Monat");
    assert.equal(outputs.ust[0].textContent, "darin 0,80 €");
    assert.equal(outputs.zahlart[0].textContent, "SEPA-Lastschrift");
    assert.equal(payContainers[0].hidden, false);
    assert.equal(payContainers[1].hidden, true);

    // Switch to yearly
    rhythmusInputs.monat.checked = false;
    rhythmusInputs.jahr.checked = true;
    rootChangeHandler();

    assert.equal(outputs.rhythmus[0].textContent, "Jährlich");
    assert.equal(outputs.betrag[0].textContent, "50,00 €"); // 5 * 10
    assert.equal(outputs.einheit[0].textContent, "/ Jahr");
    assert.equal(outputs.ust[0].textContent, "darin 7,98 €");
    assert.equal(priceLabels[0].textContent, "50 €");

    // Focus on amount input switches stufe to Mäzen
    amountInput.listeners.focus();
    assert.equal(stufeInputs.maezen.checked, true);
    assert.equal(stufeInputs.oase.checked, false);
    assert.equal(outputs.stufe[0].textContent, "Mäzen");
    assert.equal(outputs.betrag[0].textContent, "120,00 €"); // 12 * 10
    assert.equal(outputs.ust[0].textContent, "darin 19,16 €");

    // Enter custom valid amount (25 €)
    amountInput.value = "25";
    amountInput.listeners.input();
    assert.equal(outputs.betrag[0].textContent, "250,00 €"); // 25 * 10
    assert.equal(outputs.ust[0].textContent, "darin 39,92 €");
    assert.equal(amountError.hidden, true);
    assert.equal(amountInput.getAttribute("aria-invalid"), "false");

    // Enter invalid/too-low amount (8 €)
    amountInput.value = "8";
    amountInput.listeners.input();
    // Below minimum falls back to min (12 € * 10 = 120 €) and flags error
    assert.equal(outputs.betrag[0].textContent, "120,00 €");
    assert.equal(amountError.hidden, false);
    assert.equal(amountInput.getAttribute("aria-invalid"), "true");
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


