/*
 * Oasis website: small page modules. A module only runs on elements with a
 * matching data-oasis="<name>" attribute. Interactive app screens are Livewire
 * components; these modules cover the few purely client-side helpers.
 */

const modules = {};

// Module definitions go here: modules.name = (root) => { ... };

// --- Preise: monthly/yearly toggle ------------------------------------------
// Markup: root [data-oasis="pricing"] containing a hidden [data-period-toggle] with two buttons
// [data-period="month"|"year"] and any number of elements with data-month/data-year texts.
// Clicking a button swaps every element's text and sets aria-pressed.
// Without JS: the toggle stays hidden and the server-rendered monthly values are shown.
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

// --- Profil / Einladungen: share or copy a link ------------------------------
// The button is rendered `hidden` and only shown when sharing or copying works. The buttons live inside Livewire
// components, whose re-renders reset `hidden`; revealing is therefore safe to repeat, and the click is handled once
// for the whole document (below) instead of being bound to each button.
modules.share = (button) => {
    if (navigator.share || navigator.clipboard) {
        button.hidden = false;
    }
};

// Shares the button's data-url natively, or copies it and briefly confirms "Kopiert!".
const shareLink = async (button) => {
    const url = button.dataset.url;
    try {
        if (navigator.share) {
            await navigator.share({ url });
        } else {
            await navigator.clipboard.writeText(url);
            button.setAttribute("aria-label", "Link kopiert");
            const label = button.querySelector ? button.querySelector("[data-share-label]") : null;
            if (label) {
                const original = label.textContent;
                label.textContent = "Kopiert!";
                setTimeout(() => {
                    label.textContent = original;
                    button.setAttribute("aria-label", original);
                }, 2000);
            } else if (button.innerHTML !== undefined && button.innerHTML) {
                const originalHtml = button.innerHTML;
                button.innerHTML = '<span class="text-xs font-bold text-spring-text">Kopiert!</span>';
                setTimeout(() => {
                    button.innerHTML = originalHtml;
                    button.setAttribute("aria-label", "Profil teilen");
                }, 2000);
            }
        }
    } catch {
        // The person cancelled the share sheet; nothing to do.
    }
};

document.addEventListener("click", (event) => {
    const button = event.target.closest?.('[data-oasis="share"]');
    if (button) {
        shareLink(button);
    }
});

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

// Livewire's SPA navigation (wire:navigate) swaps the page without re-running this script, so modules start on
// every `livewire:navigated` event, which Livewire also fires after the first page load. The marker keeps an
// element that survives a navigation from being set up twice.
const startModules = () => {
    document.querySelectorAll("[data-oasis]").forEach((root) => {
        if (root.dataset.oasisStarted) {
            return;
        }
        root.dataset.oasisStarted = "true";
        modules[root.dataset.oasis]?.(root);
    });
};

document.addEventListener("livewire:navigated", startModules);

// A Livewire re-render morphs a component in place and resets its markup (including the start marker), so modules
// inside components start again after every update; modules must therefore be safe to run twice on an element.
document.addEventListener("livewire:init", () => {
    window.Livewire.hook("morphed", startModules);
});
