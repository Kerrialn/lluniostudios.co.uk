import { Controller } from '@hotwired/stimulus';

/*
 * Product page purchase route (Site v4 prototype). As the quantity and options
 * change, the button switches between "Cast Your Lamp" (direct order and
 * checkout) and "Reserve Your Design" (request), with the prototype's
 * "Purchase route" note and line underneath. Reserve applies when the basket
 * would go above the lamp limit, the size is Request-only, or a "Custom..."
 * value is chosen. The server makes the same decision when the form is sent.
 */
export default class extends Controller {
    static targets = ['cta', 'sub', 'note', 'route'];
    static values = {
        basketLamps: Number,
        max: { type: Number, default: 2 },
        direct: Boolean,
        notes: Object,
    };

    connect() {
        this.update = this.update.bind(this);
        this.element.addEventListener('input', this.update);
        this.element.addEventListener('change', this.update);
        this.update();
    }

    disconnect() {
        this.element.removeEventListener('input', this.update);
        this.element.removeEventListener('change', this.update);
    }

    update() {
        const quantityInput = this.element.querySelector('input[name$="[quantity]"]');
        const quantity = Math.max(1, parseInt(quantityInput?.value, 10) || 1);
        const custom = [...this.element.querySelectorAll('select')]
            .some((select) => select.selectedOptions[0]?.dataset.goesToRequest === '1');

        const notes = [];
        if (this.basketLampsValue + quantity > this.maxValue) notes.push(this.notesValue.quantity);
        if (!this.directValue) notes.push(this.notesValue.size);
        if (custom) notes.push(this.notesValue.custom);

        const reserve = notes.length > 0;
        this.ctaTarget.textContent = reserve ? 'Reserve Your Design' : 'Cast Your Lamp';
        this.subTarget.textContent = reserve ? this.notesValue.reserveSub : this.notesValue.directSub;
        this.noteTarget.textContent = reserve ? notes.join(' ') : this.notesValue.direct;
        this.routeTarget.classList.toggle('border-ll-ink', reserve);
        this.routeTarget.classList.toggle('border-ll-line', !reserve);
    }
}
