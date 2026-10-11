const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
const path = require("node:path");

function setup() {
    const context = vm.createContext({ document: { querySelectorAll: () => [] } });
    vm.runInContext(fs.readFileSync(path.join(__dirname, "../../resources/js/oasis.js"), "utf8"), context);
    const module = vm.runInContext("modules.cancellation", context);
    const state = {
        type: { value: "ordentlich" },
        timing: { value: "naechst" },
        field: { hidden: false },
        reason: { required: false, disabled: false, value: "Keep this draft" },
        date: { required: false },
    };
    let change;
    const form = {
        querySelector(selector) {
            return ({
                "[data-reason-field]": state.field,
                '[name="reason"]': state.reason,
                '[name="effective_date"]': state.date,
                '[name="type"]:checked': state.type,
                '[name="timing"]:checked': state.timing,
            })[selector];
        },
        addEventListener(event, callback) {
            assert.equal(event, "change");
            change = callback;
        },
    };
    module(form);
    return { state, update: () => change() };
}

test("extraordinary cancellation reveals and requires the reason", () => {
    const { state, update } = setup();
    assert.equal(state.field.hidden, true);
    assert.equal(state.reason.disabled, true);
    state.type.value = "ausserordentlich";
    update();
    assert.equal(state.field.hidden, false);
    assert.equal(state.reason.required, true);
    assert.equal(state.reason.disabled, false);
    state.type.value = "ordentlich";
    update();
    assert.equal(state.reason.required, false);
    assert.equal(state.reason.disabled, true);
    assert.equal(state.reason.value, "Keep this draft");
});

test("a selected date is required and becomes optional again", () => {
    const { state, update } = setup();
    assert.equal(state.date.required, false);
    state.timing.value = "datum";
    update();
    assert.equal(state.date.required, true);
    state.timing.value = "naechst";
    update();
    assert.equal(state.date.required, false);
});
