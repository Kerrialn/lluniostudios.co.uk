import { Controller } from '@hotwired/stimulus';

/*
 * Full-screen mobile menu from the Site v4 design. Opens over the page, locks
 * scrolling, and closes on the Close button, Escape, or following a link
 * (Swup swaps the page without a reload, so the menu must close itself).
 */
export default class extends Controller {
    static targets = ['panel', 'openButton'];

    connect() {
        this.onKeydown = (event) => {
            if (event.key === 'Escape') this.close();
        };
    }

    disconnect() {
        this.close();
    }

    open() {
        this.panelTarget.hidden = false;
        this.openButtonTarget.setAttribute('aria-expanded', 'true');
        document.documentElement.classList.add('ll-menu-open');
        document.addEventListener('keydown', this.onKeydown);
    }

    close() {
        if (!this.hasPanelTarget || this.panelTarget.hidden) return;
        this.panelTarget.hidden = true;
        this.openButtonTarget.setAttribute('aria-expanded', 'false');
        document.documentElement.classList.remove('ll-menu-open');
        document.removeEventListener('keydown', this.onKeydown);
    }
}
