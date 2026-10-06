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
 *  - Photo (7.3): after the reveal, The Scale and the review show the
 *    chosen size's own gallery, which loops seamlessly both ways (arrows,
 *    the side zones, swipe, arrow keys) with a clone at each end; option
 *    stages show the chosen value's photo; anything without a real photo
 *    shows "Image Coming Soon" with its name. Changing an option never moves
 *    the gallery. Photos load only when needed.
 */
const SLIDE = 'transform .7s cubic-bezier(.2,.7,.2,1)';
const SWIPE_MIN = 50;
const MIN_LAMPS = 1;
// Most lamps one configuration can hold. Checkout is separately limited to
// 2 lamps in the whole basket (BasketRules); 3 or more go to Reserve.
const MAX_LAMPS = 10;
const WIDE = '(min-width: 900px)';
const SIDEWAYS = '(max-width: 899px) and (orientation: landscape)';

export default class extends Controller {
    static targets = ['idle', 'hero', 'stage', 'step', 'stageNumber', 'stageTitle', 'intro', 'back', 'backTitle', 'next', 'nextTitle',
        'option', 'group', 'warning', 'sizeNote', 'spec', 'dimension', 'quantity', 'modePlaceholder',
        'idleLayer', 'gallery', 'optionPhoto', 'soon', 'soonTitle'];
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
        this.photoIndex = 0;
        this.slot = null;
        this.dragX = 0;
        this.onKey = (event) => this.keyPhoto(event);
        window.addEventListener('keydown', this.onKey);
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
        clearTimeout(this.snapTimer);
        window.removeEventListener('keydown', this.onKey);
        this.wide.removeEventListener('change', this.onMedia);
        this.sideways.removeEventListener('change', this.onMedia);
    }

    revealed() {
        this.revealedAlready = true;
        this.revealedAt = performance.now();
        clearInterval(this.timer);
        this.idleIndex = 0;
        this.renderIdle();
        if (this.hasIdleLayerTarget) this.idleLayerTarget.hidden = true;
        this.renderPhoto();
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

    // Starts loading idle photo i (if it hasn't been yet) and returns it.
    load(i) {
        return this.loadImage(this.idleTargets[i % this.idleTargets.length]);
    }

    loadImage(photo) {
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
        this.renderPhoto();
    }

    // Choices -------------------------------------------------------------

    choose(event) {
        const { group, value } = event.currentTarget.dataset;
        if (group === 'size') {
            this.sizeValue = value;
            this.slot = null;
            this.groupsValue.forEach((g) => { if (!this.available(g)) delete this.choices[g.name]; });
        } else {
            this.choices[group] = value;
        }
        this.renderChoices();
        this.renderPhoto();
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

    // Photo -----------------------------------------------------------------

    renderPhoto() {
        if (!this.revealedAlready) return;
        const stageKey = this.stageKeysValue[this.step];
        const showGallery = stageKey === 'scale' || stageKey === 'review';
        const gallery = showGallery ? this.currentGallery() : null;

        let photo = null;
        let soonTitle = '';
        if (showGallery) {
            if (!gallery) soonTitle = (this.sizesValue.find((s) => s.slug === this.sizeValue) || {}).name || '';
        } else {
            const group = this.groupsValue.find((g) => g.stage === stageKey && this.available(g));
            const value = group ? this.choices[group.name] : undefined;
            photo = this.optionPhotoTargets.find((el) => el.dataset.group === group?.name && el.dataset.value === value) || null;
            if (!photo) soonTitle = value || '';
        }

        this.galleryTargets.forEach((el) => { el.hidden = el !== gallery; });
        this.optionPhotoTargets.forEach((el) => { el.hidden = el !== photo; });
        if (photo) this.loadImage(photo);
        this.soonTarget.hidden = Boolean(gallery || photo);
        this.soonTitleTarget.textContent = soonTitle;
        this.soonTitleTarget.hidden = !soonTitle;
        this.soonTarget.setAttribute('aria-label', soonTitle ? `Image coming soon: ${soonTitle}` : 'Image coming soon');
        if (gallery) this.renderGallery(true);
    }

    currentGallery() {
        return this.galleryTargets.find((el) => el.dataset.size === this.sizeValue) || null;
    }

    galleryCount() {
        const gallery = this.currentGallery();
        return gallery ? Number(gallery.dataset.count) : 0;
    }

    // Track positions: with clones, position 0 is the last photo's clone and
    // n + 1 the first photo's; photo i sits at i + 1.
    renderGallery(instant = false) {
        const gallery = this.currentGallery();
        if (!gallery || gallery.hidden) return;
        const n = this.galleryCount();
        this.photoIndex = ((this.photoIndex % n) + n) % n;
        if (this.slot === null) this.slot = n > 1 ? this.photoIndex + 1 : 0;

        const track = gallery.querySelector('[data-gallery-track]');
        track.style.transition = instant || this.dragging ? 'none' : SLIDE;
        track.style.transform = `translateX(calc(${-this.slot * 100}% + ${this.dragX}px))`;
        const pad = (v) => String(v).padStart(2, '0');
        gallery.querySelector('[data-gallery-count]').textContent = `${pad(this.photoIndex + 1)} / ${pad(n)}`;

        const imgs = track.children;
        [this.slot - 1, this.slot, this.slot + 1].forEach((k) => this.loadImage(imgs[k]));
    }

    previousPhoto() {
        this.movePhoto(-1);
    }

    nextPhoto() {
        this.movePhoto(1);
    }

    movePhoto(direction) {
        const n = this.galleryCount();
        if (n < 2 || !this.currentGallery() || this.currentGallery().hidden) return;
        clearTimeout(this.snapTimer);

        // Sitting on a clone (a quick second press): jump to the real photo first.
        if (this.slot === 0 || this.slot === n + 1) {
            this.slot = this.photoIndex + 1;
            this.renderGallery(true);
            void this.currentGallery().offsetWidth;
        }

        this.photoIndex = (this.photoIndex + direction + n) % n;
        this.slot = Math.max(0, Math.min(n + 1, this.slot + direction));
        this.renderGallery();

        // Slid onto a clone: once the slide ends, swap to the real photo unseen.
        if (this.slot === 0 || this.slot === n + 1) {
            this.snapTimer = setTimeout(() => {
                this.slot = this.photoIndex + 1;
                this.renderGallery(true);
            }, 720);
        }
    }

    swipeStart(event) {
        const t = event.touches[0];
        this.touchStart = [t.clientX, t.clientY];
        this.dragging = true;
        this.dragX = 0;
    }

    swipeMove(event) {
        if (!this.touchStart) return;
        const t = event.touches[0];
        const dx = t.clientX - this.touchStart[0];
        const dy = t.clientY - this.touchStart[1];
        if (Math.abs(dx) > Math.abs(dy)) {
            this.dragX = dx;
            this.renderGallery();
        }
    }

    swipeEnd() {
        if (!this.touchStart) return;
        const dx = this.dragX;
        this.touchStart = null;
        this.dragging = false;
        this.dragX = 0;
        if (dx < -SWIPE_MIN) this.movePhoto(1);
        else if (dx > SWIPE_MIN) this.movePhoto(-1);
        else this.renderGallery();
    }

    keyPhoto(event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        // The key press that revealed the panel doesn't also move the gallery.
        if (!this.revealedAlready || event.timeStamp <= this.revealedAt) return;
        if (/^(INPUT|TEXTAREA|SELECT)$/.test(event.target?.tagName || '')) return;
        this.movePhoto(event.key === 'ArrowRight' ? 1 : -1);
    }
}
