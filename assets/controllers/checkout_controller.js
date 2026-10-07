import { Controller } from '@hotwired/stimulus';

/*
 * Card Payment page: as the postcode is typed, asks the server
 * (/checkout/delivery) for the DPD price and updates the delivery line, the
 * total and the button ("Pay £X"). A postcode outside the UK mainland turns
 * the button into "Reserve Your Design" with a note (the server decides
 * again when the form is sent).
 */
export default class extends Controller {
    static targets = ['postcode', 'delivery', 'total', 'button', 'outsideNote', 'secure'];
    static values = { deliveryUrl: String };

    postcodeChanged() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.refresh(), 300);
    }

    disconnect() {
        clearTimeout(this.timer);
        this.request?.abort();
    }

    async refresh() {
        this.request?.abort();
        this.request = new AbortController();
        const url = `${this.deliveryUrlValue}?postcode=${encodeURIComponent(this.postcodeTarget.value)}`;
        try {
            const response = await fetch(url, { signal: this.request.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            this.deliveryTarget.textContent = data.delivery;
            this.totalTarget.textContent = data.total;
            this.buttonTarget.textContent = data.button;
            this.outsideNoteTarget.hidden = data.state !== 'outside';
            this.secureTarget.hidden = data.state === 'outside';
        } catch (error) {
            if (error.name !== 'AbortError') throw error;
        }
    }
}
