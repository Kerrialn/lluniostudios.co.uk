/*
 * Remembered choices (decided 8 October 2026): the configurator's design and
 * what was typed on Reserve Your Design survive leaving the page and coming
 * back. Kept in the visitor's own browser only:
 *  - the configurator's choices in localStorage (no personal details), so
 *    they last until used or cleared;
 *  - the Reserve form's details (name, email, phone, address) in
 *    sessionStorage, so they are forgotten when the tab is closed (the
 *    device may be shared).
 * Forgotten when the lamp is added to the basket or the reservation is sent
 * (the server asks for it: forget_choices_controller.js) and by "Start
 * again". Storage can be unavailable (private windows, blocked site data):
 * every use is guarded and the pages work without it.
 */
const STORES = {
    configurator: () => window.localStorage,
    reserve: () => window.sessionStorage,
};
const KEY = (name) => `llunio:${name}`;

export function readChoices(name) {
    try {
        const raw = STORES[name]().getItem(KEY(name));
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

export function saveChoices(name, value) {
    try {
        STORES[name]().setItem(KEY(name), JSON.stringify(value));
    } catch {
        // Storage unavailable: nothing is remembered.
    }
}

export function forgetChoices(...names) {
    names.forEach((name) => {
        try {
            STORES[name]?.().removeItem(KEY(name));
        } catch {
            // Storage unavailable: nothing to forget.
        }
    });
}
