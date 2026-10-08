import { Controller } from '@hotwired/stimulus';
import { forgetChoices } from '../saved_choices.js';

/*
 * Forgets remembered choices when the server says they've been used: the
 * lamp added to the basket, or the reservation sent (base.html.twig renders
 * this from the session's forget_choices list).
 */
export default class extends Controller {
    static values = { names: Array };

    connect() {
        forgetChoices(...this.namesValue);
    }
}
