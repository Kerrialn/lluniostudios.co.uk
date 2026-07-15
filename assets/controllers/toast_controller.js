import { Controller } from '@hotwired/stimulus';

/*
 * Auto-dismissing Flowbite-style toast. Starts visible (no dependency on JS to
 * be seen), then fades out after `delay` ms; the close button dismisses early.
 */
export default class extends Controller {
    static values = { delay: { type: Number, default: 4500 } };

    connect() {
        this.timeout = setTimeout(() => this.dismiss(), this.delayValue);
    }

    disconnect() {
        clearTimeout(this.timeout);
    }

    dismiss() {
        clearTimeout(this.timeout);
        this.element.classList.add('opacity-0', 'translate-y-[-8px]');
        this.element.addEventListener('transitionend', () => this.element.remove(), { once: true });
        // Fallback in case the transition never fires.
        setTimeout(() => this.element.remove(), 400);
    }
}
