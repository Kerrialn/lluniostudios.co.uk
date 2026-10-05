import { Controller } from '@hotwired/stimulus';

/*
 * Full-screen hero that "reveals" the page on the first deliberate interaction
 * (scroll, wheel, click/tap, touch-move or key), as in the Site v4 prototype:
 * the hero shrinks, "Scroll to begin" fades and the content below fades in.
 * Interactions in the first 500ms after arriving are ignored (e.g. scroll
 * restoration). Nothing is remembered: every visit starts unrevealed.
 * The visual states live in app.css (.ll-reveal-*).
 */
const EVENTS = ['wheel', 'scroll', 'click', 'keydown', 'touchmove'];

export default class extends Controller {
    connect() {
        this.arrivedAt = Date.now();
        this.element.classList.remove('is-revealed');
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
    }

    stopListening() {
        EVENTS.forEach((type) => window.removeEventListener(type, this.onInteract));
    }
}
