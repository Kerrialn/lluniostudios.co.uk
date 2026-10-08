import { Controller } from '@hotwired/stimulus';
import { readChoices, saveChoices } from '../saved_choices.js';

/*
 * Remembers what is typed into a form (Reserve Your Design) while the
 * visitor leaves the page and comes back (see saved_choices.js). Restored
 * only when the server hasn't filled the form itself (details carried over
 * from checkout, or a sent form with a problem), as those are newer.
 */
export default class extends Controller {
    static values = { name: String };

    connect() {
        const saved = readChoices(this.nameValue);
        const filled = this.fields().some((field) => field.tagName !== 'SELECT' && field.value.trim() !== '');
        if (saved && !filled) {
            this.fields().forEach((field) => {
                if (typeof saved[field.name] === 'string' && (field.tagName !== 'SELECT' || [...field.options].some((o) => o.value === saved[field.name]))) {
                    field.value = saved[field.name];
                }
            });
        }
    }

    save() {
        saveChoices(this.nameValue, Object.fromEntries(this.fields().map((field) => [field.name, field.value])));
    }

    fields() {
        return [...this.element.querySelectorAll('input[name], textarea[name], select[name]')]
            .filter((field) => field.type !== 'hidden' && !field.name.endsWith('[_token]'));
    }
}
