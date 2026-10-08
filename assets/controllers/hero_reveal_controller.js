import { Controller } from '@hotwired/stimulus';

/*
 * Full-screen hero that "reveals" the page on the first deliberate interaction
 * (scroll, wheel, click/tap, touch-move or key), as in the Site v4 prototype:
 * the hero shrinks, "Scroll to begin" fades and the content below fades in.
 * Interactions in the first 500ms after arriving are ignored (e.g. scroll
 * restoration). Every visit starts unrevealed, unless the page asks to open
 * revealed (open value, e.g. editing a basket line) or another controller
 * calls reveal() (the configurator, for a returning visitor's finished design).
 * The visual states live in app.css (.ll-reveal-*).
 */
const EVENTS = ['wheel', 'scroll', 'click', 'keydown', 'touchmove'];

export default class extends Controller {
    static values = { open: Boolean };

    connect() {
        this.arrivedAt = Date.now();
        this.element.classList.remove('is-revealed');
        if (this.openValue) {
            // After the other controllers on the page have connected, so they hear it.
            requestAnimationFrame(() => this.reveal());
            return;
        }
        this.onInteract = () => {
            if (Date.now() - this.arrivedAt < 500) return;
            this.reveal();
        };
        EVENTS.forEach((type) => window.addEventListener(type, this.onInteract, { passive: true }));
    }

    disconnect() {
        this.stopListening();
    }

    reveal() {
        this.element.classList.add('is-revealed');
        this.stopListening();
        // Lets other controllers react, e.g. data-action="hero-reveal:revealed->configurator#revealed".
        this.dispatch('revealed');
    }

    stopListening() {
        EVENTS.forEach((type) => window.removeEventListener(type, this.onInteract));
    }
}
