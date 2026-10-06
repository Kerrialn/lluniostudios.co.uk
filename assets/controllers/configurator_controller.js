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
 *  - Stages (7.2): size, options and quantity. Gallery starts selected;
 *    every option starts empty and must be chosen. A group only some sizes
 *    have (Bulb Count) shows for those sizes only, and its choice is cleared
 *    when switching to a size without it. Moving past a stage (by Next or the
 *    stage numbers) with a choice still missing goes back to that stage with
 *    "Please choose a ... to continue."
 *  - Photo (7.3): after the reveal, The Scale and the review show the
 *    chosen size's own gallery, which loops seamlessly both ways (arrows,
 *    the side zones, swipe, arrow keys) with a clone at each end; option
 *    stages show the chosen value's photo; anything without a real photo
 *    shows "Image Coming Soon" with its name. Changing an option never moves
 *    the gallery. Photos load only when needed.
 *  - Customise All / Individually (7.4), for 2 or more lamps: individually,
 *    the option stages show Lamp 1, Lamp 2, ... tabs. Each lamp starts from
 *    the shared choices and keeps its own changes; every choice is needed for
 *    every lamp (moving on opens the first lamp missing one on that stage).
 *  - Review and purchase route (7.5): the review lists every choice (or each
 *    lamp's), each row opening its stage. The button is "Cast Your Lamp" (adds
 *    the design to the basket) unless the design must be reserved: a Request
 *    size, a Custom value, or more than the checkout limit of lamps in the
 *    whole basket. Then it is "Reserve Your Design" (the request route) and
 *    the "Purchase route" note, on The Scale and under the button, says why.
 *    Choosing a Custom value shows a short line under that list (for the lamp
 *    being edited). The server checks again.
 */
const SLIDE = 'transform .7s cubic-bezier(.2,.7,.2,1)';
const SWIPE_MIN = 50;
const MIN_LAMPS = 1;
const WIDE = '(min-width: 900px)';
const SIDEWAYS = '(max-width: 899px) and (orientation: landscape)';

export default class extends Controller {
    static targets = ['idle', 'hero', 'stage', 'step', 'stageNumber', 'stageTitle', 'intro', 'back', 'backTitle', 'next', 'nextTitle',
        'option', 'group', 'warning', 'sizeNote', 'spec', 'dimension', 'quantity',
        'idleLayer', 'gallery', 'optionPhoto', 'soon', 'soonTitle', 'modes', 'mode', 'units',
        'route', 'routeNote', 'review', 'configuration', 'cta', 'ctaSub', 'customNote'];
    static values = {
        stages: Array, stageKeys: Array, sizes: Array, groups: Array, size: String,
        maxLamps: { type: Number, default: 10 }, basketLamps: Number, maxCheckout: { type: Number, default: 2 },
        requestUrl: String, notes: Object,
        interval: { type: Number, default: 5000 },
    };

    connect() {
        this.idleIndex = 0;
        this.step = 0;
        this.revealedAlready = false;
        this.quantity = MIN_LAMPS;
        this.choices = {};
        this.warned = new Set();
        this.mode = 'all';
        this.lampChoices = [];
        this.unit = 0;
        this.photoIndex = 0;
        this.slot = null;
        this.dragX = 0;
        this.onKey = (event) => this.keyPhoto(event);
        window.addEventListener('keydown', this.onKey);
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
        // The first stage before the target with a choice missing for any lamp.
        const lamps = this.lampConfigurations();
        const isMissing = (group) => this.available(group) && lamps.some((lamp) => !lamp[group.name]);
        const first = this.groupsValue.find((group) => this.stageOf(group) < target && isMissing(group));
        if (first) {
            const stage = this.stageOf(first);
            const missing = this.groupsValue.filter((group) => this.stageOf(group) === stage && isMissing(group));
            missing.forEach((group) => this.warned.add(group.name));
            this.step = stage;
            if (this.individually()) this.unit = lamps.findIndex((lamp) => missing.some((group) => !lamp[group.name]));
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
            this.groupsValue.forEach((g) => {
                if (this.available(g)) return;
                delete this.choices[g.name];
                this.lampChoices.forEach((lamp) => { if (lamp) delete lamp[g.name]; });
            });
        } else if (this.individually()) {
            this.lampChoices[this.unit] = { ...(this.lampChoices[this.unit] || {}), [group]: value };
        } else {
            this.choices[group] = value;
        }
        this.renderChoices();
        this.renderPhoto();
    }

    fewer() {
        this.quantity = Math.max(MIN_LAMPS, this.quantity - 1);
        this.unit = Math.min(this.unit, this.quantity - 1);
        this.renderChoices();
    }

    chooseMode(event) {
        this.mode = event.currentTarget.dataset.mode;
        this.renderChoices();
    }

    chooseUnit(event) {
        this.unit = Number(event.currentTarget.dataset.unit);
        this.renderChoices();
        this.renderPhoto();
    }

    individually() {
        return this.quantity >= 2 && this.mode === 'individual';
    }

    // One lamp's choices: the shared ones, overridden by its own.
    choicesFor(i) {
        return { ...this.choices, ...(this.lampChoices[i] || {}) };
    }

    // The choices being edited: the current lamp's, or the shared ones.
    current() {
        return this.individually() ? this.choicesFor(this.unit) : this.choices;
    }

    // Every lamp's choices (one entry when customising all).
    lampConfigurations() {
        return this.individually()
            ? Array.from({ length: this.quantity }, (_, i) => this.choicesFor(i))
            : [this.choices];
    }

    more() {
        this.quantity = Math.min(this.maxLampsValue, this.quantity + 1);
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

        const current = this.current();
        this.optionTargets.forEach((el) => {
            const { group, value } = el.dataset;
            const selected = group === 'size' ? value === size.slug : current[group] === value;
            el.classList.toggle('is-selected', selected);
            el.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        this.groupTargets.forEach((el) => {
            el.hidden = !el.dataset.sizes.split(',').includes(size.slug);
        });
        // "Custom options are developed with you..." under a list whose chosen
        // value (for the lamp being edited) is a Custom one.
        this.customNoteTargets.forEach((el) => {
            const group = this.groupsValue.find((g) => g.name === el.dataset.group);
            el.hidden = !group?.values.some((v) => v.request && v.value === current[group.name]);
        });
        this.warningTargets.forEach((el) => {
            const name = el.dataset.group;
            el.hidden = !(this.warned.has(name) && !current[name]);
        });

        if (this.hasSizeNoteTarget) this.sizeNoteTarget.textContent = size.note;
        if (this.hasSpecTarget) this.specTarget.textContent = `${size.name} · ${size.price}`;
        this.dimensionTargets.forEach((el) => { el.textContent = size[el.dataset.dimension] ?? '—'; });
        if (this.hasQuantityTarget) this.quantityTarget.textContent = String(this.quantity);
        if (this.hasModesTarget) this.modesTarget.hidden = this.quantity < 2;
        this.modeTargets.forEach((el) => {
            const selected = el.dataset.mode === this.mode;
            el.classList.toggle('is-selected', selected);
            el.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        this.renderUnits();
        this.renderRoute();
        this.renderReview();
    }

    // Lamp tabs: only when customising individually, on the option stages.
    renderUnits() {
        if (!this.hasUnitsTarget) return;
        const key = this.stageKeysValue[this.step];
        const show = this.individually() && key !== 'scale' && key !== 'review';
        this.unitsTarget.hidden = !show;
        if (!show) return;
        if (this.unitsTarget.children.length !== this.quantity) {
            this.unitsTarget.replaceChildren(...Array.from({ length: this.quantity }, (_, i) => {
                const tab = document.createElement('button');
                tab.type = 'button';
                tab.className = 'll-cfg-unit';
                tab.dataset.unit = String(i);
                tab.dataset.action = 'configurator#chooseUnit';
                tab.textContent = `Lamp ${i + 1}`;
                return tab;
            }));
        }
        [...this.unitsTarget.children].forEach((tab, i) => {
            tab.classList.toggle('is-current', i === this.unit);
            tab.setAttribute('aria-pressed', i === this.unit ? 'true' : 'false');
        });
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

    // Review and purchase route -----------------------------------------

    currentSize() {
        return this.sizesValue.find((s) => s.slug === this.sizeValue) || this.sizesValue[0];
    }

    // Why this design must be reserved, as the note's sentences ([] = Cast Your
    // Lamp). As BasketRules::routeNote(): the size and Custom notes (or one
    // combined note when both apply), then the 3-or-more-lamps note.
    reserveReasons() {
        const size = !this.currentSize().direct;
        const custom = this.lampConfigurations().some((lamp) => this.groupsValue.some((group) => this.available(group)
            && group.values.some((v) => v.request && v.value === lamp[group.name])));
        const reasons = [];
        if (size && custom) reasons.push(this.notesValue.sizeAndCustom);
        else if (size) reasons.push(this.notesValue.size);
        else if (custom) reasons.push(this.notesValue.custom);
        if (this.basketLampsValue + this.quantity > this.maxCheckoutValue) reasons.push(this.notesValue.quantity);
        return reasons;
    }

    renderRoute() {
        const reasons = this.reserveReasons();
        const reserve = reasons.length > 0;
        // On The Scale and under the button on the review.
        this.routeTargets.forEach((el) => {
            el.classList.toggle('border-ll-ink', reserve);
            el.classList.toggle('border-ll-line', !reserve);
        });
        this.routeNoteTargets.forEach((el) => { el.textContent = reserve ? reasons.join(' ') : this.notesValue.direct; });
        if (this.hasCtaTarget) {
            this.ctaTarget.textContent = reserve ? 'Reserve Your Design' : 'Cast Your Lamp';
            this.ctaSubTarget.textContent = reserve ? this.notesValue.reserveSub : this.notesValue.directSub;
        }
    }

    renderReview() {
        if (!this.hasReviewTarget) return;
        const size = this.currentSize();
        const groups = this.groupsValue.filter((group) => this.available(group));
        const stageOfKey = (key) => this.stageKeysValue.indexOf(key);
        const rows = [
            ['Size', size.name, 0], ['Price', size.price, 0], ['Quantity', String(this.quantity), 0],
        ];
        if (this.quantity >= 2) rows.push(['Configuration', this.individually() ? 'Customise Individually' : 'Customise All', 0]);
        if (this.individually()) {
            this.lampConfigurations().forEach((lamp, i) => {
                rows.push([`Lamp ${i + 1}`, groups.map((g) => lamp[g.name]).filter(Boolean).join(' · ') || '—', stageOfKey(groups[0]?.stage), i]);
            });
        } else {
            groups.forEach((g) => rows.push([g.name, this.choices[g.name] || '—', this.stageOf(g)]));
        }

        this.reviewTarget.replaceChildren(...rows.map(([key, value, stage, lamp]) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'flex justify-between gap-4 w-full py-[11px] border-b border-ll-line-soft text-[13px] text-left';
            row.dataset.action = 'configurator#reviewRow';
            row.dataset.stage = String(stage);
            if (lamp !== undefined) row.dataset.lamp = String(lamp);
            const k = document.createElement('span');
            k.className = 'self-center text-[10.5px] uppercase tracking-[0.12em] text-ll-mute';
            k.textContent = key;
            const v = document.createElement('span');
            v.className = 'text-right';
            v.textContent = value;
            row.append(k, v);
            return row;
        }));
    }

    reviewRow(event) {
        const { stage, lamp } = event.currentTarget.dataset;
        if (lamp !== undefined) this.unit = Number(lamp);
        this.goTo(Number(stage));
    }

    // The button: add to the basket, or go to the request route.
    cast(event) {
        if (this.reserveReasons().length > 0) {
            event.preventDefault();
            window.location.assign(this.requestUrlValue);
            return;
        }
        this.configurationTarget.value = JSON.stringify({
            size: this.sizeValue,
            quantity: this.quantity,
            mode: this.individually() ? 'individual' : 'all',
            lamps: this.lampConfigurations().map((lamp) => Object.fromEntries(this.groupsValue
                .filter((group) => this.available(group))
                .map((group) => [group.name, lamp[group.name]]))),
        });
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
            const value = group ? this.current()[group.name] : undefined;
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
