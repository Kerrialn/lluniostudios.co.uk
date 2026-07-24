import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'hidden'];
    static values = { length: { type: Number, default: 6 } };

    connect() {
        // Pre-fill boxes if the hidden field already has a value (e.g. validation redisplay)
        if (this.hiddenTarget.value) {
            [...this.hiddenTarget.value].forEach((char, i) => {
                if (this.inputTargets[i]) this.inputTargets[i].value = char;
            });
        }
    }

    onInput(event) {
        const input = event.target;
        input.value = input.value.replace(/\D/g, '').slice(0, 1);

        if (input.value) {
            this.#boxAt(this.#indexOf(input) + 1)?.focus();
        }
        this.#sync();
    }

    onKeydown(event) {
        const input = event.target;

        if (event.key === 'Backspace' && !input.value) {
            const prev = this.#boxAt(this.#indexOf(input) - 1);
            if (prev) {
                prev.value = '';
                prev.focus();
                this.#sync();
                event.preventDefault();
            }
        } else if (event.key === 'ArrowLeft') {
            this.#boxAt(this.#indexOf(input) - 1)?.focus();
            event.preventDefault();
        } else if (event.key === 'ArrowRight') {
            this.#boxAt(this.#indexOf(input) + 1)?.focus();
            event.preventDefault();
        }
    }

    onPaste(event) {
        event.preventDefault();
        const digits = (event.clipboardData?.getData('text') || '')
            .replace(/\D/g, '')
            .slice(0, this.lengthValue);

        [...digits].forEach((char, i) => {
            if (this.inputTargets[i]) this.inputTargets[i].value = char;
        });
        this.#sync();
        this.#boxAt(Math.min(digits.length, this.lengthValue - 1))?.focus();
    }

    onFocus(event) {
        event.target.select();
    }

    #sync() {
        this.hiddenTarget.value = this.inputTargets.map(i => i.value).join('');
    }

    #indexOf(input) {
        return this.inputTargets.indexOf(input);
    }

    #boxAt(index) {
        return this.inputTargets[index] ?? null;
    }
}
