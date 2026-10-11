/*
 * Oasis website: small progressive enhancements. Every page stays usable
 * without JavaScript; a module only runs on elements with a matching
 * data-oasis="<name>" attribute.
 */

const modules = {};

// Module definitions go here: modules.name = (root) => { ... };

document.querySelectorAll("[data-oasis]").forEach((root) => {
    modules[root.dataset.oasis]?.(root);
});
