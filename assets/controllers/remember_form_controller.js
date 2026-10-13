import { Controller } from '@hotwired/stimulus';
import { readChoices, saveChoices, wasForgotten } from '../saved_choices.js';

/*
 * Remembers what is typed into a form (Reserve Your Design), and what is
 * ticked or chosen (its gift options, 9 October 2026), while the
 * visitor leaves the page and comes back (see saved_choices.js). Restored
 * only when the server hasn't filled the form itself (details carried over
 * from checkout, or a sent form with a problem), as those are newer.
 * A box the server fills from elsewhere (data-remember-form-carried: Custom
 * Details, from the configurator's descriptions, 10 October 2026) doesn't
 * count as filled. What was typed in it is restored while the carried text
 * is unchanged; once it has changed (new descriptions), the new text is
 * shown with anything the customer added below the old text kept under it.
 *
 * Once the form has been sent its details are forgotten, but the browser's
 * Back button can show the page again from the browser's own copy, still
 * filled in (found 13 October 2026, after sending an enquiry). A page shown
 * from the browser's history after its details were forgotten is emptied.
 */
export default class extends Controller {
    static values = { name: String };

    connect() {
        this.shownAgain = (event) => { if (event.persisted) this.emptyIfForgotten(); };
        window.addEventListener('pageshow', this.shownAgain);
        if (performance.getEntriesByType?.('navigation')[0]?.type === 'back_forward') this.emptyIfForgotten();

        const saved = readChoices(this.nameValue);
        const filled = this.fields().some((field) => !this.carried(field)
            && (this.ticks(field) ? field.checked : field.tagName !== 'SELECT' && field.value.trim() !== ''));
        if (saved && !filled) {
            this.fields().forEach((field) => {
                const value = saved[field.name];
                if (typeof value !== 'string') return;
                if (this.carried(field)) {
                    field.value = this.mergeCarried(field.dataset.rememberFormCarried, saved[this.carriedKey(field)] ?? '', value);
                    return;
                }
                if (field.type === 'checkbox') {
                    field.checked = value === '1';
                } else if (field.type === 'radio') {
                    field.checked = value === field.value;
                } else if (field.tagName !== 'SELECT' || [...field.options].some((o) => o.value === value)) {
                    field.value = value;
                }
            });
            // Keeps what is remembered in step with the carried text shown.
            if (this.fields().some((field) => this.carried(field))) this.save();
        }
    }

    disconnect() {
        window.removeEventListener('pageshow', this.shownAgain);
    }

    // Shown again from the browser's history after the form was sent: empty
    // every field (and chosen files), without remembering the empty form.
    emptyIfForgotten() {
        if (!wasForgotten(this.nameValue) || readChoices(this.nameValue)) return;
        this.emptying = true;
        this.fields().forEach((field) => {
            if (this.ticks(field)) field.checked = false;
            else if (field.tagName === 'SELECT') field.selectedIndex = 0;
            else field.value = this.carried(field) ? field.dataset.rememberFormCarried : '';
            this.changed(field);
        });
        this.element.querySelectorAll('input[type=file]').forEach((field) => {
            field.value = '';
            this.changed(field);
        });
        this.emptying = false;
    }

    // Tells the page's other scripts (the file list, the phone's "*", the
    // gift's questions), which listen for either event.
    changed(field) {
        ['input', 'change'].forEach((type) => field.dispatchEvent(new Event(type, { bubbles: true })));
    }

    save() {
        if (this.emptying) return;
        const choices = {};
        this.fields().forEach((field) => {
            // Tick boxes: ticked or not; radio buttons: the one chosen.
            if (field.type === 'checkbox') choices[field.name] = field.checked ? '1' : '';
            else if (field.type === 'radio') { if (field.checked || !(field.name in choices)) choices[field.name] = field.checked ? field.value : ''; }
            else choices[field.name] = field.value;
            if (this.carried(field)) choices[this.carriedKey(field)] = field.dataset.rememberFormCarried;
        });
        saveChoices(this.nameValue, choices);
    }

    carried(field) {
        return field.dataset.rememberFormCarried !== undefined;
    }

    // The box's text: what was typed, if the carried text is unchanged;
    // otherwise the new carried text with what was added to the old kept.
    mergeCarried(carried, oldCarried, typed) {
        if (carried === oldCarried) return typed;
        const added = (oldCarried !== '' && typed.startsWith(oldCarried) ? typed.slice(oldCarried.length) : typed).trim();
        if (added === '' || added === oldCarried.trim()) return carried;
        return carried === '' ? added : `${carried}\n${added}`;
    }

    carriedKey(field) {
        return `${field.name}#carried`;
    }

    ticks(field) {
        return field.type === 'checkbox' || field.type === 'radio';
    }

    fields() {
        return [...this.element.querySelectorAll('input[name], textarea[name], select[name]')]
            // Chosen files can't be remembered (browsers don't allow it); the
            // spam trap (data-remember-form-skip) never is.
            .filter((field) => field.type !== 'hidden' && field.type !== 'file' && !field.name.endsWith('[_token]') && field.dataset.rememberFormSkip === undefined);
    }
}
