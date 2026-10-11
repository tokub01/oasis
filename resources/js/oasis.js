/*
 * Oasis website: small progressive enhancements. Every page stays usable
 * without JavaScript; a module only runs on elements with a matching
 * data-oasis="<name>" attribute.
 */

const modules = {};

// Module definitions go here: modules.name = (root) => { ... };

// --- Preise: monthly/yearly toggle ------------------------------------------
modules.pricing = (root) => {
    const toggle = root.querySelector("[data-period-toggle]");
    const buttons = toggle.querySelectorAll("[data-period]");

    const show = (period) => {
        buttons.forEach((button) => {
            button.setAttribute("aria-pressed", String(button.dataset.period === period));
        });
        root.querySelectorAll("[data-month][data-year]").forEach((el) => {
            el.textContent = el.dataset[period];
        });
    };

    buttons.forEach((button) => {
        button.addEventListener("click", () => show(button.dataset.period));
    });
    toggle.hidden = false;
};

// --- Profil: share the profile link ------------------------------------------
// The button is rendered `hidden` and only shown when sharing or copying works.
modules.share = (button) => {
    const url = button.dataset.url;
    if (!navigator.share && !navigator.clipboard) {
        return;
    }

    button.addEventListener("click", async () => {
        try {
            if (navigator.share) {
                await navigator.share({ url });
            } else {
                await navigator.clipboard.writeText(url);
                button.setAttribute("aria-label", "Link kopiert");
            }
        } catch {
            // The person cancelled the share sheet; nothing to do.
        }
    });
    button.hidden = false;
};

// --- Hilfe: filter the FAQ on the page ---------------------------------------
// The search form is rendered with `hidden` and only shown here, so there is
// no non-functional field without JavaScript.
modules["help-search"] = (form) => {
    const input = form.querySelector("input[type='search']");
    const status = form.querySelector("[data-help-status]");
    const groups = [...document.querySelectorAll("[data-help-group]")];
    const normalize = (text) => text.toLocaleLowerCase("de").replace(/\s+/g, " ").trim();
    const items = groups.flatMap((group) =>
        [...group.querySelectorAll("details")].map((details) => ({
            group,
            details,
            text: normalize(details.textContent),
            wasOpen: details.open,
        })),
    );

    const filter = () => {
        const query = normalize(input.value);
        let hits = 0;
        items.forEach((item) => {
            const match = query === "" || item.text.includes(query);
            item.details.hidden = !match;
            item.details.open = query === "" ? item.wasOpen : match;
            if (match) hits += 1;
        });
        groups.forEach((group) => {
            group.hidden = !items.some((item) => item.group === group && !item.details.hidden);
        });
        status.textContent =
            query !== "" && hits === 0 ? `Zu „${input.value.trim()}“ haben wir keine passende Frage gefunden.` : "";
    };

    input.addEventListener("input", filter);
    form.addEventListener("submit", (event) => {
        event.preventDefault();
        filter();
    });
    form.hidden = false;
};
// --- /Hilfe ----------------------------------------------------------------

// --- Mitglied werden: membership summary ------------------------------------
// Pure calculations (kept free of DOM access so they can be tested in Node).
const membershipCalc = {
    // German money format: 1234.5 -> "1.234,50 €"
    formatEuro(amount) {
        const cents = Math.round(amount * 100);
        const euros = Math.floor(cents / 100)
            .toString()
            .replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        return `${euros},${String(cents % 100).padStart(2, "0")} €`;
    },
    // Mäzen amount from the raw input; below the minimum (or invalid) counts as the minimum.
    parseAmount(raw, min) {
        const text = String(raw ?? "").trim();
        const value = Number(text.replace(",", "."));
        const valid = text !== "" && !Number.isNaN(value);
        return {
            monthly: valid && value >= min ? Math.round(value * 100) / 100 : min,
            tooLow: valid && value < min,
        };
    },
    // A year costs `yearMonths` monthly contributions.
    total(monthly, yearly, yearMonths) {
        return yearly ? monthly * yearMonths : monthly;
    },
    // VAT contained in a gross amount, e.g. 19 % -> amount × 19 / 119.
    vatIncluded(amount, rate) {
        return (amount * rate) / (100 + rate);
    },
};

modules.membership = (root) => {
    const { formatEuro, parseAmount, total, vatIncluded } = membershipCalc;
    const yearMonths = Number(root.dataset.yearMonths);
    const vatRate = Number(root.dataset.vatRate);
    const min = Number(root.dataset.min);
    const amountInput = root.querySelector("#m-betrag");
    const amountError = root.querySelector("[data-amount-error]");
    const checked = (name) => root.querySelector(`input[name="${name}"]:checked`);
    const out = (key, text) => {
        root.querySelectorAll(`[data-out="${key}"]`).forEach((el) => {
            el.textContent = text;
        });
    };

    const update = () => {
        const tier = checked("stufe");
        const rhythm = checked("rhythmus");
        const payment = checked("zahlart");
        const yearly = rhythm.value === "jahr";
        const amount = parseAmount(amountInput.value, min);

        amountError.hidden = !amount.tooLow;
        amountInput.setAttribute("aria-invalid", String(amount.tooLow));

        const monthly = tier.value === "maezen" ? amount.monthly : Number(tier.dataset.monthly);
        const sum = total(monthly, yearly, yearMonths);

        root.querySelectorAll("[data-price]").forEach((el) => {
            el.textContent = yearly ? el.dataset.year : el.dataset.month;
        });
        root.querySelectorAll("[data-pay]").forEach((el) => {
            el.hidden = el.dataset.pay !== payment.value;
        });

        out("stufe", tier.dataset.label);
        out("rhythmus", rhythm.dataset.label);
        out("zahlart", payment.dataset.label);
        out("betrag", formatEuro(sum));
        out("einheit", rhythm.dataset.unit);
        out("ust", `darin ${formatEuro(vatIncluded(sum, vatRate))}`);
        out("laufzeit", rhythm.dataset.term);
    };

    const pickMaezen = () => {
        const maezen = root.querySelector('input[name="stufe"][value="maezen"]');
        if (maezen) maezen.checked = true;
    };

    amountInput.addEventListener("focus", () => {
        pickMaezen();
        update();
    });
    amountInput.addEventListener("input", () => {
        pickMaezen();
        update();
    });
    root.addEventListener("change", update);
    update();
};
// --- /Mitglied werden -------------------------------------------------------


// --- Oasis forms: cancellation ---------------------------------------------
modules.cancellation = (form) => {
    const reasonField = form.querySelector("[data-reason-field]");
    const reason = form.querySelector('[name="reason"]');
    const date = form.querySelector('[name="effective_date"]');
    const update = () => {
        const extraordinary = form.querySelector('[name="type"]:checked')?.value === "ausserordentlich";
        reasonField.hidden = !extraordinary;
        reason.required = extraordinary;
        reason.disabled = !extraordinary;
        date.required = form.querySelector('[name="timing"]:checked')?.value === "datum";
    };
    form.addEventListener("change", update);
    update();
};
// --- End Oasis forms -------------------------------------------------------

document.querySelectorAll("[data-oasis]").forEach((root) => {
    modules[root.dataset.oasis]?.(root);
});
