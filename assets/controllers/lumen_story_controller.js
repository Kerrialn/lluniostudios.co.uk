import { Controller } from '@hotwired/stimulus';

/*
 * Lighting Designs introduction (Step 7b), as the design's Lighting Designs
 * page (formerly Range Test D). Reads the scroll position only; it never
 * takes over scrolling.
 *  - The page snaps to each stage (CSS snap points in the template, on while
 *    the introduction shows), never held.
 *  - Scrolling through the tall section (k, 0 to 7) fades the seven stages
 *    in turn: each is fully shown for most of its stretch and eases out
 *    over the hand-over (the design's timings); the last fades in near the
 *    end. Stages that aren't shown can't be reached by keyboard (inert).
 *  - The lit photo fades in over the dim one a step at each stage: fully
 *    dim on 01, fully lit on 07.
 *  - "01 / 07" shows the stage.
 *  - Phones (7b.2): the section is longer (11 steps instead of 7): The Sizes
 *    holds for four more while its drawing, in the photo's place, pans from
 *    Studio to Monument (one snap per size); the photo fades out for it and
 *    back for The Signature. On short upright phones the photo starts just
 *    below the longest stage text (--story-photo-top), so no text runs over
 *    it.
 *  - At the top of the page the hero shows; once scrolled, it fades onto the
 *    pinned lamp (.is-story-scrolled, app.css) and the slideshow pauses
 *    (configurator_controller.js).
 *  - Once the panel opens (hero-reveal:revealed), the snapping stops and the
 *    page returns to the top.
 *  - Reduced motion: a plain page (app.css): nothing here runs.
 */
const STAGES = 7;
const REDUCED = '(prefers-reduced-motion: reduce)';
// Phones, and tablets held upright (up to 1199px wide, 14 October 2026), use the phone layout.
const PHONE = '(max-width: 899px), (orientation: portrait) and (max-width: 1199px)';
const UPRIGHT = '(orientation: portrait) and (max-width: 1199px)';
// Phones: The Sizes (stage 4, k 3.5) holds while the drawing pans.
const SIZES_AT = 3.5;
const PAN_STEPS = 4;
// The drawing's column centres (px of its 1536px width): Studio … Monument.
const COLUMNS = [186, 424, 702, 1010, 1331];
const DRAWING_WIDTH = 1536;
// The photo starts this far below the longest text when it would otherwise run over it.
const PHOTO_GAP = 16;
// Upright phones: the drawing's area starts this far below The Sizes' text.
const PLAN_GAP = 12;

const clamp = (v) => Math.max(0, Math.min(1, v));
const smooth = (v) => { v = clamp(v); return v * v * (3 - 2 * v); };

export default class extends Controller {
    static targets = ['sequence', 'stage', 'lit', 'marker', 'markerNumber', 'photo', 'plan', 'pan'];

    connect() {
        this.reduced = window.matchMedia(REDUCED);
        this.phone = window.matchMedia(PHONE);
        this.upright = window.matchMedia(UPRIGHT);
        this.onScroll = () => {
            if (this.frame) return;
            this.frame = requestAnimationFrame(() => { this.frame = 0; this.update(); });
        };
        this.onResize = () => { this.fitPhoto(); this.update(); };
        this.onReduced = () => this.start();
        this.reduced.addEventListener('change', this.onReduced);
        this.phone.addEventListener('change', this.onResize);
        this.upright.addEventListener('change', this.onResize);
        // The text's height changes once Special Elite has loaded.
        document.fonts?.ready.then(() => this.onResize());
        window.addEventListener('scroll', this.onScroll, { passive: true });
        window.addEventListener('resize', this.onResize);
        this.start();
    }

    disconnect() {
        this.reduced.removeEventListener('change', this.onReduced);
        this.phone.removeEventListener('change', this.onResize);
        this.upright.removeEventListener('change', this.onResize);
        this.stopListening();
        this.snap(false);
    }

    revealed() {
        this.stopListening();
        this.snap(false);
        window.scrollTo(0, 0);
    }

    get open() {
        return this.element.classList.contains('is-revealed');
    }

    start() {
        if (this.open) return;
        const plain = this.reduced.matches;
        this.snap(!plain);
        if (plain) {
            this.stageTargets.forEach((stage) => { stage.style.opacity = ''; stage.inert = false; });
            if (this.hasLitTarget) this.litTarget.style.opacity = '';
            if (this.hasPhotoTarget) this.photoTarget.style.opacity = '';
            if (this.hasPlanTarget) this.planTarget.style.opacity = '';
            this.element.style.removeProperty('--story-photo-top');
            this.element.classList.remove('is-story-scrolled');
            return;
        }
        this.fitPhoto();
        this.update();
    }

    stopListening() {
        window.removeEventListener('scroll', this.onScroll);
        window.removeEventListener('resize', this.onResize);
        if (this.frame) cancelAnimationFrame(this.frame);
        this.frame = 0;
    }

    snap(on) {
        document.documentElement.style.scrollSnapType = on ? 'y mandatory' : '';
    }

    // Upright phones: on short screens the photo starts 16px below the
    // longest stage text if that would otherwise run into it; the drawing's
    // area starts 12px below The Sizes' own text, or where the photo starts
    // if that is higher.
    fitPhoto() {
        if (this.open || this.reduced.matches || !this.hasPhotoTarget) return;
        this.element.style.removeProperty('--story-photo-top');
        this.element.style.removeProperty('--story-plan-top');
        if (!this.upright.matches) return;
        const pin = this.photoTarget.parentElement.getBoundingClientRect();
        const textBottom = (stage) => Math.max(...[...stage.children]
            .filter((child) => !child.classList.contains('ll-story-actions') && child.getClientRects().length)
            .map((child) => child.getBoundingClientRect().bottom - pin.top));
        const photoTop = Math.max(pin.height * 0.46, Math.ceil(Math.max(...this.stageTargets.map(textBottom)) + PHOTO_GAP));
        if (photoTop > pin.height * 0.46) this.element.style.setProperty('--story-photo-top', `${photoTop}px`);
        if (this.stageTargets[3]) this.element.style.setProperty('--story-plan-top', `${Math.min(photoTop, Math.ceil(textBottom(this.stageTargets[3]) + PLAN_GAP))}px`);
    }

    update() {
        if (this.open || this.reduced.matches || !this.hasSequenceTarget) return;
        this.element.classList.toggle('is-story-scrolled', window.scrollY >= 6);
        const box = this.sequenceTarget.getBoundingClientRect();
        const span = box.height - window.innerHeight;
        const p = span > 0 ? clamp(-box.top / span) : 0;
        const phone = this.phone.matches;
        // Phones: kr runs 0 to 11; k (the stage) holds at The Sizes for the pan.
        const kr = p * (phone ? STAGES + PAN_STEPS : STAGES);
        const k = !phone || kr <= SIZES_AT ? kr : kr <= SIZES_AT + PAN_STEPS ? SIZES_AT : kr - PAN_STEPS;

        this.stageTargets.forEach((stage, i) => {
            const shown = i < STAGES - 1
                ? smooth((0.5 - Math.abs(k - (i + 0.5))) / 0.22)
                : smooth((k - 6.22) / 0.44);
            stage.style.opacity = shown.toFixed(3);
            stage.inert = shown <= 0.5;
        });

        let lit = 0;
        for (let j = 1; j < STAGES; j++) lit += smooth((k - (j - 0.22)) / 0.44) / (STAGES - 1);
        if (this.hasLitTarget) this.litTarget.style.opacity = lit.toFixed(3);

        if (phone) {
            const at = Math.max(0, Math.min(PAN_STEPS, kr - SIZES_AT));
            const i = Math.min(PAN_STEPS - 1, Math.floor(at));
            const centre = COLUMNS[i] + (COLUMNS[i + 1] - COLUMNS[i]) * smooth(at - i);
            if (this.hasPanTarget) this.panTarget.style.setProperty('--pan', (centre / DRAWING_WIDTH).toFixed(4));
            const sizes = this.stageTargets[3]?.style.opacity || '0';
            if (this.hasPlanTarget) this.planTarget.style.opacity = sizes;
            if (this.hasPhotoTarget) this.photoTarget.style.opacity = (1 - Number(sizes)).toFixed(3);
        } else {
            if (this.hasPlanTarget) this.planTarget.style.opacity = '';
            if (this.hasPhotoTarget) this.photoTarget.style.opacity = '';
        }

        const number = Math.max(1, Math.min(STAGES, Math.floor(k) + 1));
        if (this.hasMarkerNumberTarget) this.markerNumberTarget.textContent = String(number).padStart(2, '0');
        if (this.hasMarkerTarget) this.markerTarget.setAttribute('aria-label', `Stage ${number} of ${STAGES}`);
    }
}
