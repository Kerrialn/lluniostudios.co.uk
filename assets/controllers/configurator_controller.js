import { Controller } from '@hotwired/stimulus';

/*
 * Lumen Forma configurator (Site v4 prototype), sub-step 7.1.
 *  - Before the reveal, the Gallery photos cross-fade behind the title every
 *    5s (1.6s fade). The title is drawn dark over light photos, cream with a
 *    soft shadow over dark ones, using each photo's precomputed brightness
 *    for desktop, upright phones and sideways phones.
 *    Only the first photo comes with the page; each later one is loaded
 *    while the photo before it shows, and the fade waits until it's ready.
 *  - On reveal (hero-reveal:revealed) the cross-fade stops and the first
 *    photo shows.
 *  - The options panel moves between the six stages with Back / Next and the
 *    01-06 indicator.
 *  - Stages (7.2): size, options and quantity, starting from the prototype's
 *    choices (Gallery and three pre-selected options; The Pigment and Bulb
 *    Count empty). A group only some sizes have (Bulb Count) shows for those
 *    sizes only, and its choice is cleared when switching to a size without
 *    it. Moving past a stage with a required group still unchosen goes back
 *    to that stage with "Please choose ... to continue."
 */
const MIN_LAMPS = 1;
// Most lamps one configuration can hold. Checkout is separately limited to
// 2 lamps in the whole basket (BasketRules); 3 or more go to Reserve.
const MAX_LAMPS = 10;
const WIDE = '(min-width: 900px)';
const SIDEWAYS = '(max-width: 899px) and (orientation: landscape)';

export default class extends Controller {
    static targets = ['idle', 'hero', 'stage', 'step', 'stageNumber', 'stageTitle', 'intro', 'back', 'backTitle', 'next', 'nextTitle',
        'option', 'group', 'warning', 'sizeNote', 'spec', 'dimension', 'quantity', 'modePlaceholder'];
    static values = {
        stages: Array, stageKeys: Array, sizes: Array, groups: Array, size: String,
        interval: { type: Number, default: 5000 },
    };

    connect() {
        this.idleIndex = 0;
        this.step = 0;
        this.revealedAlready = false;
        this.quantity = MIN_LAMPS;
        this.choices = {};
        this.warned = new Set();
        this.groupsValue.forEach((group) => { if (group.default) this.choices[group.name] = group.default; });
        this.wide = window.matchMedia(WIDE);
        this.onMedia = () => this.renderIdle();
        this.sideways = window.matchMedia(SIDEWAYS);
        this.wide.addEventListener('change', this.onMedia);
        this.sideways.addEventListener('change', this.onMedia);
        this.renderIdle();
        this.renderStage();
        this.renderChoices();
        this.load(1);
        this.timer = setInterval(() => this.advanceIdle(), this.intervalValue);
    }

    disconnect() {
        clearInterval(this.timer);
        this.wide.removeEventListener('change', this.onMedia);
        this.sideways.removeEventListener('change', this.onMedia);
    }

    revealed() {
        this.revealedAlready = true;
        clearInterval(this.timer);
        this.idleIndex = 0;
        this.renderIdle();
    }

    advanceIdle() {
        if (this.revealedAlready || this.idleTargets.length < 2) return;
        const nextIndex = (this.idleIndex + 1) % this.idleTargets.length;
        const nextPhoto = this.load(nextIndex);
        if (!nextPhoto.complete) return; // still downloading: try again next tick
        this.idleIndex = nextIndex;
        this.renderIdle();
        this.load(this.idleIndex + 1);
    }

    // Starts loading photo i (if it hasn't been yet) and returns it.
    load(i) {
        const photo = this.idleTargets[i % this.idleTargets.length];
        if (photo && photo.dataset.src) {
            photo.srcset = photo.dataset.srcset;
            photo.src = photo.dataset.src;
            delete photo.dataset.src;
            delete photo.dataset.srcset;
        }
        return photo;
    }

    renderIdle() {
        this.idleTargets.forEach((photo, i) => {
            photo.classList.toggle('opacity-100', i === this.idleIndex);
            photo.classList.toggle('opacity-0', i !== this.idleIndex);
        });
        const current = this.idleTargets[this.idleIndex];
        if (!current || !this.hasHeroTarget) return;
        const key = this.wide.matches ? 'lightWide' : (this.sideways.matches ? 'lightSideways' : 'lightNarrow');
        const light = current.dataset[key] === '1';
        this.heroTargets.forEach((el) => el.classList.toggle('is-over-light', light));
    }

    // Stage navigation -----------------------------------------------------

    back() {
        this.goTo(this.step - 1);
    }

    next() {
        this.goTo(this.step + 1);
    }

    jump(event) {
        this.goTo(Number(event.currentTarget.dataset.step));
    }

    goTo(step) {
        const target = Math.max(0, Math.min(this.stagesValue.length - 1, step));
        const missing = this.groupsValue.find((group) => group.required && this.available(group)
            && !this.choices[group.name] && this.stageOf(group) < target);
        if (missing) {
            this.warned.add(missing.name);
            this.step = this.stageOf(missing);
        } else {
            this.step = target;
        }
        this.renderStage();
        this.renderChoices();
    }

    // Choices -------------------------------------------------------------

    choose(event) {
        const { group, value } = event.currentTarget.dataset;
        if (group === 'size') {
            this.sizeValue = value;
            this.groupsValue.forEach((g) => { if (!this.available(g)) delete this.choices[g.name]; });
        } else {
            this.choices[group] = value;
        }
        this.renderChoices();
    }

    fewer() {
        this.quantity = Math.max(MIN_LAMPS, this.quantity - 1);
        this.renderChoices();
    }

    more() {
        this.quantity = Math.min(MAX_LAMPS, this.quantity + 1);
        this.renderChoices();
    }

    available(group) {
        return group.sizes.includes(this.sizeValue);
    }

    stageOf(group) {
        return this.stageKeysValue.indexOf(group.stage);
    }

    renderChoices() {
        const size = this.sizesValue.find((s) => s.slug === this.sizeValue) || this.sizesValue[0];
        if (!size) return;

        this.optionTargets.forEach((el) => {
            const { group, value } = el.dataset;
            const selected = group === 'size' ? value === size.slug : this.choices[group] === value;
            el.classList.toggle('is-selected', selected);
            el.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        this.groupTargets.forEach((el) => {
            el.hidden = !el.dataset.sizes.split(',').includes(size.slug);
        });
        this.warningTargets.forEach((el) => {
            const name = el.dataset.group;
            el.hidden = !(this.warned.has(name) && !this.choices[name]);
        });

        if (this.hasSizeNoteTarget) this.sizeNoteTarget.textContent = size.note;
        if (this.hasSpecTarget) this.specTarget.textContent = `${size.name} · ${size.price}`;
        this.dimensionTargets.forEach((el) => { el.textContent = size[el.dataset.dimension] ?? '—'; });
        if (this.hasQuantityTarget) this.quantityTarget.textContent = String(this.quantity);
        if (this.hasModePlaceholderTarget) this.modePlaceholderTarget.hidden = this.quantity < 2;
    }

    renderStage() {
        const stages = this.stagesValue;
        const pad = (n) => String(n).padStart(2, '0');

        this.stepTargets.forEach((el, i) => {
            el.classList.toggle('is-current', i === this.step);
            el.classList.toggle('is-past', i < this.step);
            el.setAttribute('aria-current', i === this.step ? 'step' : 'false');
        });
        this.stageTargets.forEach((el, i) => { el.hidden = i !== this.step; });
        this.stageNumberTarget.textContent = `${pad(this.step + 1)} / ${pad(stages.length)}`;
        this.stageTitleTarget.textContent = stages[this.step];
        if (this.hasIntroTarget) this.introTarget.hidden = this.step !== 0;

        this.backTarget.classList.toggle('opacity-0', this.step === 0);
        this.backTarget.disabled = this.step === 0;
        this.backTitleTarget.textContent = this.step > 0 ? stages[this.step - 1] : '';
        const hasNext = this.step < stages.length - 1;
        this.nextTarget.hidden = !hasNext;
        this.nextTitleTarget.textContent = hasNext ? stages[this.step + 1] : '';
    }
}
