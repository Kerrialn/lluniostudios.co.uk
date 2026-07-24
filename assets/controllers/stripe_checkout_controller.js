import { Controller } from '@hotwired/stimulus';
import { loadStripe } from '@stripe/stripe-js';

/*
 * Mounts the Stripe Payment Element (card + other methods) and Express Checkout
 * Element (Apple Pay / Google Pay) against a PaymentIntent created server-side.
 * Both elements confirm the same intent. On success we redirect to the order
 * completion page; the webhook confirms the final PAID state.
 */
export default class extends Controller {
    static targets = ['payment', 'express', 'submit', 'error'];
    static values = {
        publishableKey: String,
        clientSecret: String,
        amount: String,
        completeUrl: String,
    };

    async connect() {
        try {
            this.stripe = await loadStripe(this.publishableKeyValue);
        } catch {
            this.showError('Unable to initialise payment. Please try again.');
            return;
        }

        if (!this.stripe) {
            this.showError('Unable to initialise payment. Please try again.');
            return;
        }

        this.elements = this.stripe.elements({
            clientSecret: this.clientSecretValue,
            appearance: { theme: 'stripe' },
        });

        // Card + dynamically eligible payment methods.
        this.paymentElement = this.elements.create('payment', {
            layout: 'tabs',
        });
        this.paymentElement.mount(this.paymentTarget);

        // Apple Pay / Google Pay express buttons. Hidden until we know a wallet
        // is available so we don't leave an empty gap.
        if (this.hasExpressTarget) {
            this.expressTarget.classList.add('hidden');
            this.expressElement = this.elements.create('expressCheckout');
            this.expressElement.on('ready', (event) => {
                const methods = event && event.availablePaymentMethods;
                if (methods && Object.keys(methods).length > 0) {
                    this.expressTarget.classList.remove('hidden');
                }
            });
            this.expressElement.on('confirm', () => this.confirm());
            this.expressElement.mount(this.expressTarget);
        }
    }

    submit(event) {
        event.preventDefault();
        this.confirm();
    }

    async confirm() {
        this.clearError();
        this.disable();

        const { error } = await this.stripe.confirmPayment({
            elements: this.elements,
            confirmParams: {
                return_url: this.completeUrlValue,
            },
            redirect: 'if_required',
        });

        if (error) {
            // Immediate validation / card errors surface here. Redirect-based
            // methods (some wallets, 3DS) navigate to return_url instead.
            this.showError(error.message || 'Payment failed. Please check your details.');
            this.enable();
            return;
        }

        // No redirect required (e.g. card without 3DS) — payment is processing/
        // succeeded. Move to the completion page; the webhook finalises the order.
        window.location.href = this.completeUrlValue;
    }

    disconnect() {
        if (this.paymentElement) {
            this.paymentElement.destroy();
        }
        if (this.expressElement) {
            this.expressElement.destroy();
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
