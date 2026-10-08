import { Controller } from '@hotwired/stimulus';
import { loadStripe } from '@stripe/stripe-js';

/*
 * Card Payment page.
 * - As the postcode is typed, asks the server (/checkout/delivery) for the
 *   DPD price and updates the delivery line, the total and the button
 *   ("Pay £X"). Outside the UK mainland the button becomes "Reserve Your
 *   Design" with a note (the server decides again when the form is sent).
 * - Mounts Stripe's card boxes (number, expiry, security code) styled like
 *   the page's fields. Card details go straight to Stripe, never to us.
 * - "Pay": sends the form to the server first (JSON). If anything is missing
 *   the fields are outlined with the page's one message; once complete, the
 *   server saves the order and starts a payment, and the card is confirmed
 *   here (with Stripe's 3D Secure pop-up if the bank asks). On success, the
 *   completion page.
 * - Apple Pay / Google Pay (Stripe's Express Checkout buttons, shown only when
 *   the device offers one): the wallet asks for the name, email, phone and a
 *   UK delivery address; the DPD price is added once an address is chosen
 *   (outside the UK mainland the wallet refuses it and the page explains).
 *   Once approved, the wallet's details go through the same checks as the
 *   form (/checkout/express) and the payment is confirmed with the wallet.
 * - The quote page (Step 10b.3) uses the same script with walletAddress
 *   false: the address is the page's (filled in from the request), so the
 *   wallet only pays; once approved, the page's fields go to the quote's
 *   "pay" address (?wallet=1) and are checked as for a card.
 */
const INK = '#ece3d3';
const MUTE = '#a89b86';
const REQUIRED = 'Please complete the required fields to continue.';
const CARD_BOXES = ['cardNumber', 'cardExpiry', 'cardCvc'];

export default class extends Controller {
    static targets = ['postcode', 'delivery', 'total', 'button', 'outsideNote', 'secure', 'message', 'express', 'expressArea', 'expressMessage',
        'cardNumber', 'cardExpiry', 'cardCvc', 'cardNumberError', 'cardExpiryError', 'cardCvcError'];
    static values = { deliveryUrl: String, stripeKey: String, expressUrl: String, subtotal: Number, walletAddress: { type: Boolean, default: true } };

    async connect() {
        this.state = !this.hasOutsideNoteTarget || this.outsideNoteTarget.hidden ? 'mainland' : 'outside';
        this.cardComplete = {};
        if (!this.stripeKeyValue || !this.hasCardNumberTarget) return;

        try {
            this.stripe = await loadStripe(this.stripeKeyValue, { locale: 'en-GB' });
        } catch {
            this.stripe = null;
        }
        if (!this.stripe) {
            this.showMessage("We couldn't load the card payment. Please refresh the page.");
            return;
        }

        const elements = this.stripe.elements({
            locale: 'en-GB',
            fonts: [{ cssSrc: 'https://fonts.googleapis.com/css2?family=Archivo:wght@400&display=swap' }],
        });
        const style = {
            base: { color: INK, fontFamily: 'Archivo, sans-serif', fontSize: '15px', lineHeight: '22.5px', '::placeholder': { color: MUTE }, iconColor: MUTE },
            invalid: { color: INK, iconColor: INK },
        };
        this.cards = {
            cardNumber: elements.create('cardNumber', { style, placeholder: '0000 0000 0000 0000', showIcon: false, disableLink: true }),
            cardExpiry: elements.create('cardExpiry', { style, placeholder: 'MM / YY' }),
            cardCvc: elements.create('cardCvc', { style, placeholder: 'CVC' }),
        };
        for (const name of CARD_BOXES) {
            const card = this.cards[name];
            card.mount(this[`${name}Target`]);
            card.on('change', (event) => {
                this.cardComplete[name] = event.complete;
                this.setCardError(name, event.error ? event.error.message : '');
            });
        }

        if (this.hasExpressTarget) this.mountWallets();
    }

    // ---- Apple Pay / Google Pay -----------------------------------------------

    mountWallets() {
        this.wallet = this.stripe.elements({
            mode: 'payment', amount: this.subtotalValue, currency: 'gbp', paymentMethodTypes: ['card'], locale: 'en-GB',
        });
        const express = this.wallet.create('expressCheckout', {
            buttonType: { applePay: 'buy', googlePay: 'buy' },
            buttonTheme: { applePay: 'white', googlePay: 'white' },
            buttonHeight: 48,
            paymentMethods: { applePay: 'auto', googlePay: 'auto', link: 'never', paypal: 'never', amazonPay: 'never', klarna: 'never' },
        });
        this.expressElement = express;
        express.on('ready', ({ availablePaymentMethods }) => {
            this.expressAreaTarget.hidden = !availablePaymentMethods || !Object.values(availablePaymentMethods).some(Boolean);
        });
        express.on('click', (event) => {
            this.walletOutside = false;
            this.showWalletMessage('');
            this.wallet.update({ amount: this.subtotalValue });
            if (!this.walletAddressValue) {
                // The quote page: the address is the page's; the wallet only pays.
                event.resolve({ billingAddressRequired: true });
                return;
            }
            event.resolve({
                emailRequired: true,
                phoneNumberRequired: true,
                billingAddressRequired: true,
                shippingAddressRequired: true,
                allowedShippingCountries: ['GB'],
                shippingRates: [{ id: 'dpd', displayName: 'DPD Next Day Delivery', amount: 0 }],
            });
        });
        express.on('shippingaddresschange', async (event) => {
            try {
                const response = await fetch(`${this.deliveryUrlValue}?partial=1&postcode=${encodeURIComponent(event.address?.postal_code || '')}`, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (data.state !== 'mainland') {
                    this.walletOutside = data.state === 'outside';
                    event.reject();
                    return;
                }
                this.wallet.update({ amount: data.subtotalPence + data.deliveryPence });
                event.resolve({ shippingRates: [{ id: 'dpd', displayName: 'DPD Next Day Delivery', amount: data.deliveryPence }] });
            } catch {
                event.reject();
            }
        });
        express.on('cancel', () => {
            // An address outside the UK mainland: say why, as the form would.
            if (this.walletOutside && this.hasOutsideNoteTarget) this.showWalletMessage(this.outsideNoteTarget.textContent.trim());
        });
        express.on('confirm', (event) => this.payWithWallet(event));
        express.mount(this.expressTarget);
    }

    async payWithWallet(event) {
        const { error: submitError } = await this.wallet.submit();
        if (submitError) {
            event.paymentFailed({ reason: 'fail' });
            this.showWalletMessage(submitError.message);
            return;
        }
        try {
            const shipping = event.shippingAddress || {};
            const billing = event.billingDetails || {};
            const response = !this.walletAddressValue
                // The quote page: its own fields, checked as for a card.
                ? await fetch(this.expressUrlValue, { method: 'POST', body: new FormData(this.element), headers: { Accept: 'application/json' } })
                : await fetch(this.expressUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    email: billing.email,
                    phone: billing.phone,
                    shipping: { name: shipping.name, ...(shipping.address || {}) },
                    billing: { name: billing.name, ...(billing.address || {}) },
                }),
            });
            const data = await response.json().catch(() => ({ status: 'error' }));
            if (data.status === 'redirect') {
                event.paymentFailed({ reason: 'invalid_shipping_address' });
                window.location.href = data.url;
                return;
            }
            if (data.status === 'invalid') {
                event.paymentFailed({ reason: 'fail' });
                this.showFieldErrors(data.errors || {});
                this.showWalletMessage(data.message || REQUIRED);
                this.firstProblem()?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                return;
            }
            if (data.status !== 'confirm') {
                event.paymentFailed({ reason: 'fail' });
                this.showWalletMessage(data.message || 'Please pay by card using the form below.');
                return;
            }
            const result = await this.stripe.confirmPayment({
                elements: this.wallet,
                clientSecret: data.clientSecret,
                confirmParams: { return_url: data.completeUrl },
                redirect: 'if_required',
            });
            if (result.error) {
                this.showWalletMessage(result.error.message || 'The payment did not go through. Please try again, or pay by card below.');
                return;
            }
            window.location.href = data.completeUrl;
        } catch {
            event.paymentFailed({ reason: 'fail' });
            this.showWalletMessage("We couldn't reach the payment service. Please check your connection and try again.");
        }
    }

    showWalletMessage(text) {
        if (!this.hasExpressMessageTarget) return;
        this.expressMessageTarget.textContent = text;
        this.expressMessageTarget.hidden = !text;
    }

    disconnect() {
        clearTimeout(this.timer);
        this.request?.abort();
        Object.values(this.cards || {}).forEach((card) => card.destroy());
        this.expressElement?.destroy();
    }

    // ---- Delivery for the postcode ------------------------------------------

    postcodeChanged() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.refresh(), 300);
    }

    async refresh() {
        this.request?.abort();
        this.request = new AbortController();
        const url = `${this.deliveryUrlValue}?postcode=${encodeURIComponent(this.postcodeTarget.value)}`;
        try {
            const response = await fetch(url, { signal: this.request.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            this.state = data.state;
            this.deliveryTarget.textContent = data.delivery;
            this.totalTarget.textContent = data.total;
            this.buttonTarget.textContent = data.button;
            this.outsideNoteTarget.hidden = data.state !== 'outside';
            this.secureTarget.hidden = data.state === 'outside';
        } catch (error) {
            if (error.name !== 'AbortError') throw error;
        }
    }

    // ---- Pay ------------------------------------------------------------------

    async pay(event) {
        // Outside the mainland the form goes to Reserve Your Design as usual;
        // without Stripe the server explains what is needed.
        if (this.state === 'outside' || !this.stripe || this.busy) return;
        event.preventDefault();
        this.busy = true;
        this.buttonTarget.disabled = true;
        this.buttonTarget.classList.add('opacity-60');
        this.showMessage('');

        try {
            const cardReady = CARD_BOXES.every((name) => this.cardComplete[name]);
            const body = new FormData(this.element);
            body.append('card_ready', cardReady ? '1' : '0');
            const response = await fetch(this.element.action || window.location.href, { method: 'POST', body, headers: { Accept: 'application/json' } });
            const data = await response.json().catch(() => ({ status: 'error' }));

            if (data.status === 'redirect') {
                window.location.href = data.url;
                return;
            }
            this.showFieldErrors(data.errors || {});
            CARD_BOXES.forEach((name) => this[`${name}Target`].classList.toggle('is-wrong', !this.cardComplete[name] || !!this[`${name}ErrorTarget`].textContent));

            if (data.status === 'invalid') {
                this.showMessage(data.requiredMissing ? REQUIRED : '');
                this.firstProblem()?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                return;
            }
            if (data.status !== 'confirm') {
                this.showMessage(data.message || "We couldn't start the payment. Please try again in a moment.");
                return;
            }

            const result = await this.stripe.confirmCardPayment(data.clientSecret, {
                payment_method: { card: this.cards.cardNumber, billing_details: data.billingDetails },
            });
            if (result.error) {
                this.showMessage(result.error.message || 'The payment did not go through. Please check your card details.');
                return;
            }
            window.location.href = data.completeUrl;
            return;
        } catch {
            this.showMessage("We couldn't reach the payment service. Please check your connection and try again.");
        } finally {
            if (!document.hidden) {
                this.busy = false;
                this.buttonTarget.disabled = false;
                this.buttonTarget.classList.remove('opacity-60');
            }
        }
    }

    // Outline fields with a problem; say what is wrong under the field unless
    // it is just empty (the page's one message covers that).
    showFieldErrors(errors) {
        this.element.querySelectorAll('[data-field-error]').forEach((e) => e.remove());
        this.element.querySelectorAll('input[name^="card_payment_form["]').forEach((input) => {
            if (input.type === 'checkbox' || input.type === 'hidden') return;
            const name = input.name.replace(/^card_payment_form\[(.*)\]$/, '$1');
            const message = errors[name];
            input.classList.toggle('border-ll-ink', !!message);
            input.classList.toggle('border-ll-mute', !message);
            if (message && message !== REQUIRED) {
                const note = document.createElement('span');
                note.className = 'text-[12px] text-ll-ink';
                note.setAttribute('role', 'alert');
                note.dataset.fieldError = '';
                note.textContent = message;
                input.insertAdjacentElement('afterend', note);
            }
        });
    }

    firstProblem() {
        return this.element.querySelector('input.border-ll-ink, .ll-card-box.is-wrong');
    }

    setCardError(name, message) {
        const target = this[`${name}ErrorTarget`];
        target.textContent = message;
        target.hidden = !message;
        this[`${name}Target`].classList.toggle('is-wrong', !!message);
    }

    showMessage(text) {
        this.messageTarget.textContent = text;
        this.messageTarget.hidden = !text;
    }
}
