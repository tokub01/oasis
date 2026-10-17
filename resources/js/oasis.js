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

// --- Installierbare App und Push-Hinweise (docs/oasis-pwa.md) -------------
// The service worker (public/sw.js) makes Oasis installable, shows the offline page and receives pushes.
if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
        navigator.serviceWorker.register("/sw.js", { scope: "/" }).catch(() => {
            // Without a service worker Oasis works as a normal website.
        });
    });
}

const isStandalone = () => window.matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;
const isAppleMobile = () =>
    /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);

// Chrome, Edge and Samsung Internet offer installation through this event; it can fire before any module runs.
let installPrompt = null;
window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    installPrompt = event;
    document.querySelectorAll("[data-oasis='install']").forEach((root) => modules.install(root));
});
window.addEventListener("appinstalled", () => {
    installPrompt = null;
    document.querySelectorAll("[data-oasis='install']").forEach((root) => modules.install(root));
});

// Markup: x-oasis.install-app. Shows exactly one of: install button, iPhone steps, "already an app", general steps.
modules.install = (root) => {
    const part = (name) => root.querySelector(`[data-install-${name}]`);
    const show = (name) => {
        ["general", "button", "ios", "done"].forEach((other) => {
            part(other).hidden = other !== name;
        });
    };
    if (isStandalone()) {
        show("done");
    } else if (installPrompt) {
        show("button");
    } else if (isAppleMobile()) {
        show("ios");
    } else {
        show("general");
    }
    const button = part("button");
    if (!button.dataset.bound) {
        button.dataset.bound = "true";
        button.addEventListener("click", async () => {
            if (!installPrompt) {
                return;
            }
            installPrompt.prompt();
            await installPrompt.userChoice;
            installPrompt = null;
            modules.install(root);
        });
    }
};

// Markup: [data-oasis="push"] with data-key (VAPID public key), data-store and data-destroy (POST URLs),
// a [data-push-state] status line and a [data-push-toggle] button. Subscribes this browser and tells the server.
modules.push = (root) => {
    const state = root.querySelector("[data-push-state]");
    const toggle = root.querySelector("[data-push-toggle]");
    const csrf = document.querySelector("meta[name='csrf-token']")?.content ?? "";

    if (!("serviceWorker" in navigator) || !("PushManager" in window) || !("Notification" in window)) {
        state.textContent = isAppleMobile()
            ? "Auf iPhone und iPad gehen Push-Hinweise, sobald du Oasis zum Home-Bildschirm hinzugefügt hast und von dort öffnest."
            : "Dieser Browser unterstützt keine Push-Hinweise.";
        return;
    }

    const keyBytes = () => {
        const base64 = root.dataset.key.replace(/-/g, "+").replace(/_/g, "/");
        return Uint8Array.from(atob(base64 + "=".repeat((4 - (base64.length % 4)) % 4)), (c) => c.charCodeAt(0));
    };
    const post = (url, body) =>
        fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrf },
            body: JSON.stringify(body),
        }).then((response) => {
            if (!response.ok) {
                throw new Error(String(response.status));
            }
        });

    const render = async (message = "") => {
        const subscription = await (await navigator.serviceWorker.ready).pushManager.getSubscription();
        if (Notification.permission === "denied") {
            state.textContent = "Du hast Mitteilungen für Oasis im Browser blockiert. Erlaube sie in den Website-Einstellungen, um Push-Hinweise zu bekommen.";
            toggle.hidden = true;
            return;
        }
        state.textContent = message || (subscription ? "Auf diesem Gerät sind Push-Hinweise eingeschaltet." : "Auf diesem Gerät sind Push-Hinweise aus.");
        toggle.textContent = subscription ? "Auf diesem Gerät ausschalten" : "Auf diesem Gerät einschalten";
        toggle.dataset.on = subscription ? "true" : "false";
        toggle.hidden = false;
    };

    toggle.addEventListener("click", async () => {
        toggle.disabled = true;
        try {
            const registration = await navigator.serviceWorker.ready;
            const current = await registration.pushManager.getSubscription();
            if (current) {
                await post(root.dataset.destroy, { endpoint: current.endpoint });
                await current.unsubscribe();
                await render("Push-Hinweise sind auf diesem Gerät ausgeschaltet.");
            } else {
                if ((await Notification.requestPermission()) !== "granted") {
                    await render();
                    return;
                }
                const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes() });
                try {
                    await post(root.dataset.store, subscription.toJSON());
                } catch (error) {
                    await subscription.unsubscribe();
                    throw error;
                }
                await render("Push-Hinweise sind auf diesem Gerät eingeschaltet.");
            }
            window.Livewire?.dispatch("push-changed");
        } catch {
            state.textContent = "Das hat nicht geklappt. Bitte versuche es später noch einmal.";
        } finally {
            toggle.disabled = false;
        }
    });

    render();
};
// Markup: [data-oasis="back"] button in the app header (installed app only). Goes back within Oasis; when the app
// was opened directly on this page (e.g. from a push), there is nothing to go back to, so it opens the Quelle.
modules.back = (root) => {
    root.addEventListener("click", () => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.assign("/app");
        }
    });
};

// Markup: [data-oasis="offline"] status line in the app layout, shown while the device has no connection.
// One pair of window listeners for all pages; the module only sets the state of a freshly rendered line.
const showConnection = () => {
    document.querySelectorAll("[data-oasis='offline']").forEach((line) => {
        line.hidden = navigator.onLine;
    });
};
window.addEventListener("online", showConnection);
window.addEventListener("offline", showConnection);
modules.offline = showConnection;
// --- /Installierbare App und Push-Hinweise ----------------------------------

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

// Livewire components move the keyboard focus with $this->dispatch('oasis:focus', id: '…'), e.g. to the heading
// of a new step, so screen reader users hear where they are after the content was replaced.
window.addEventListener("oasis:focus", (event) => {
    document.getElementById(event.detail?.id)?.focus();
});

// A Livewire re-render morphs a component in place and resets its markup (including the start marker), so modules
// inside components start again after every update; modules must therefore be safe to run twice on an element.
document.addEventListener("livewire:init", () => {
    window.Livewire.hook("morphed", startModules);
});
