import { Controller } from '@hotwired/stimulus';
import RevolutCheckout from '@revolut/checkout';

/*
 * Mounts the Revolut embedded card field against an order token created
 * server-side via the Merchant API. On success, redirects to the order
 * completion page (the webhook confirms the final PAID state).
 */
export default class extends Controller {
    static targets = ['field', 'submit', 'error', 'cardholder'];
    static values = {
        token: String,
        mode: String,
        email: String,
        completeUrl: String,
    };

    async connect() {
        try {
            // RevolutCheckout(token, mode) — `mode` is a string ('sandbox' | 'prod' | 'dev').
            // Passing an options object here makes the SDK resolve URLS[mode] to undefined
            // and fail its widget fetch ("Failed to fetch").
            this.instance = await RevolutCheckout(this.tokenValue, this.modeValue || 'sandbox');
        } catch (e) {
            this.showError('Unable to initialise payment. Please try again.');
            return;
        }

        this.card = this.instance.createCardField({
            target: this.fieldTarget,
            onSuccess: () => {
                window.location.href = this.completeUrlValue;
            },
            onError: (message) => {
                this.showError((message && message.message) || 'Payment failed. Please check your details.');
                this.enable();
            },
        });
    }

    submit(event) {
        event.preventDefault();
        this.clearError();
        this.disable();
        // Revolut requires a customer email at submit time (otherwise it rejects
        // with error.invalid-email — "Invalid email format"). Use the email
        // captured on the order during checkout.
        this.card.submit({
            name: this.hasCardholderTarget ? this.cardholderTarget.value : undefined,
            email: this.emailValue || undefined,
        });
    }

    disconnect() {
        if (this.card && typeof this.card.destroy === 'function') {
            this.card.destroy();
        }
        if (this.instance && typeof this.instance.destroy === 'function') {
            this.instance.destroy();
        }
    }

    disable() {
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = true;
            this.submitTarget.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    enable() {
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = false;
            this.submitTarget.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    showError(message) {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = message;
            this.errorTarget.classList.remove('hidden');
        }
    }

    clearError() {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = '';
            this.errorTarget.classList.add('hidden');
        }
    }
}
