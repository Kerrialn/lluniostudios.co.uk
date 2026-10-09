import { Controller } from '@hotwired/stimulus';
import { readChoices, saveChoices } from '../saved_choices.js';

/*
 * Remembers what is typed into a form (Reserve Your Design), and what is
 * ticked or chosen (its gift options, 9 October 2026), while the
 * visitor leaves the page and comes back (see saved_choices.js). Restored
 * only when the server hasn't filled the form itself (details carried over
 * from checkout, or a sent form with a problem), as those are newer.
 */
export default class extends Controller {
    static values = { name: String };

    connect() {
        const saved = readChoices(this.nameValue);
        const filled = this.fields().some((field) => (this.ticks(field) ? field.checked : field.tagName !== 'SELECT' && field.value.trim() !== ''));
        if (saved && !filled) {
            this.fields().forEach((field) => {
                const value = saved[field.name];
                if (typeof value !== 'string') return;
                if (field.type === 'checkbox') {
                    field.checked = value === '1';
                } else if (field.type === 'radio') {
                    field.checked = value === field.value;
                } else if (field.tagName !== 'SELECT' || [...field.options].some((o) => o.value === value)) {
                    field.value = value;
                }
            });
        }
    }

    save() {
        const choices = {};
        this.fields().forEach((field) => {
            // Tick boxes: ticked or not; radio buttons: the one chosen.
            if (field.type === 'checkbox') choices[field.name] = field.checked ? '1' : '';
            else if (field.type === 'radio') { if (field.checked || !(field.name in choices)) choices[field.name] = field.checked ? field.value : ''; }
            else choices[field.name] = field.value;
        });
        saveChoices(this.nameValue, choices);
    }

    ticks(field) {
        return field.type === 'checkbox' || field.type === 'radio';
    }

    fields() {
        return [...this.element.querySelectorAll('input[name], textarea[name], select[name]')]
            .filter((field) => field.type !== 'hidden' && !field.name.endsWith('[_token]'));
    }
}
