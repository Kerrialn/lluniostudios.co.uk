import { Controller } from '@hotwired/stimulus';

/*
 * The basket's gift date (decided 16 October 2026): any date from tomorrow;
 * one sooner than the usual lead time plus delivery (earliestValue, as
 * GiftDate::earliest()) turns "Proceed to Payment" into "Reserve Your
 * Design", with the note under it. The server makes the same choice when the
 * form is sent (CartController), so this only keeps the button honest.
 */
export default class extends Controller {
    static targets = ['gift', 'date', 'button', 'note'];

    static values = { earliest: String, payLabel: String, reserveLabel: String };

    connect() {
        this.update();
    }

    update() {
        const date = this.hasDateTarget ? this.dateTarget.value : '';
        const soon = this.hasGiftTarget && this.giftTarget.checked && date !== '' && date < this.earliestValue;
        this.buttonTarget.textContent = soon ? this.reserveLabelValue : this.payLabelValue;
        this.noteTarget.hidden = !soon;
    }
}
