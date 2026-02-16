import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input'];

    connect() {
        this.inputTarget.readOnly = true;
        if (this.inputTarget.value === '') {
            this.inputTarget.value = 0;
        }
    }

    decrement() {
        const value = parseFloat(this.inputTarget.value) || 0;
        const min = this.inputTarget.hasAttribute('min') ? parseFloat(this.inputTarget.min) : null;
        this.inputTarget.value = (min !== null) ? Math.max(value - 1, min) : (value - 1);
    }

    increment() {
        const value = parseFloat(this.inputTarget.value) || 0;
        const max = this.inputTarget.hasAttribute('max') ? parseFloat(this.inputTarget.max) : null;
        this.inputTarget.value = (max !== null) ? Math.min(value + 1, max) : (value + 1);
    }

    keydown(event) {
        if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;

        event.preventDefault();
        const delta = event.key === 'ArrowUp' ? 1 : -1;
        const value = parseFloat(this.inputTarget.value) || 0;
        const min = this.inputTarget.hasAttribute('min') ? parseFloat(this.inputTarget.min) : null;
        const max = this.inputTarget.hasAttribute('max') ? parseFloat(this.inputTarget.max) : null;

        let newValue = value + delta;
        if (min !== null && newValue < min) newValue = min;
        if (max !== null && newValue > max) newValue = max;

        this.inputTarget.value = newValue;
    }
}
