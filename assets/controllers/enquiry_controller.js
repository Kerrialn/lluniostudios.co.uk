import { Controller } from '@hotwired/stimulus';

/*
 * "Begin the Conversation" (Step 7c): the phone's "(optional)" becomes "*"
 * when the visitor would like a phone or video call (the server checks too).
 */
const CALLS = ['phone', 'video'];

export default class extends Controller {
    static targets = ['phoneOptional', 'phoneRequired'];

    connect() {
        this.contact();
    }

    contact() {
        const chosen = this.element.querySelector('input[name$="[talk]"]:checked');
        const required = CALLS.includes(chosen?.value);
        this.phoneOptionalTargets.forEach((el) => { el.hidden = required; });
        this.phoneRequiredTargets.forEach((el) => { el.hidden = !required; });
    }
}
