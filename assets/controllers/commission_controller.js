import { Controller } from '@hotwired/stimulus';

/*
 * Bespoke Commissions: the "For Your Home" / "For Your Project" tabs (no form
 * shows until one is chosen; switching fades the new form in, as in the Site
 * v4 prototype), the phone field's "(optional)" turning to "*" when the
 * customer would like a call. The file choosers have their own controller
 * (file_chooser_controller.js).
 */
export default class extends Controller {
    static targets = ['tab', 'panel', 'phoneOptional', 'phoneRequired'];
    static values = { client: String };

    connect() {
        this.render(false);
        this.contact();
    }

    choose(event) {
        const client = event.currentTarget.dataset.client;
        if (client === this.clientValue) return;
        this.clientValue = client;
        this.render(true);
    }

    render(fade) {
        this.tabTargets.forEach((tab) => tab.setAttribute('aria-pressed', String(tab.dataset.client === this.clientValue)));
        this.panelTargets.forEach((panel) => {
            const shown = panel.dataset.client === this.clientValue;
            panel.hidden = !shown;
            if (shown && fade) {
                panel.classList.add('opacity-0');
                requestAnimationFrame(() => requestAnimationFrame(() => panel.classList.remove('opacity-0')));
            }
        });
    }

    // The phone number is required when the customer chooses Phone.
    contact() {
        const chosen = this.element.querySelector('input[name$="[contactMethod]"]:checked');
        const phone = chosen?.value === 'phone';
        this.phoneOptionalTargets.forEach((el) => { el.hidden = phone; });
        this.phoneRequiredTargets.forEach((el) => { el.hidden = !phone; });
    }
}
