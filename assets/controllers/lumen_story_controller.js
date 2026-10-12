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
 *  - At the top of the page the hero shows; once scrolled, it fades onto the
 *    pinned lamp (.is-story-scrolled, app.css) and the slideshow pauses
 *    (configurator_controller.js).
 *  - Once the panel opens (hero-reveal:revealed), the snapping stops and the
 *    page returns to the top.
 *  - Reduced motion: a plain page (app.css): nothing here runs.
 */
const STAGES = 7;
const REDUCED = '(prefers-reduced-motion: reduce)';

const clamp = (v) => Math.max(0, Math.min(1, v));
const smooth = (v) => { v = clamp(v); return v * v * (3 - 2 * v); };

export default class extends Controller {
    static targets = ['sequence', 'stage', 'lit', 'marker', 'markerNumber'];

    connect() {
        this.reduced = window.matchMedia(REDUCED);
        this.onScroll = () => {
            if (this.frame) return;
            this.frame = requestAnimationFrame(() => { this.frame = 0; this.update(); });
        };
        this.onReduced = () => this.start();
        this.reduced.addEventListener('change', this.onReduced);
        window.addEventListener('scroll', this.onScroll, { passive: true });
        window.addEventListener('resize', this.onScroll);
        this.start();
    }

    disconnect() {
        this.reduced.removeEventListener('change', this.onReduced);
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
            this.element.classList.remove('is-story-scrolled');
            return;
        }
        this.update();
    }

    stopListening() {
        window.removeEventListener('scroll', this.onScroll);
        window.removeEventListener('resize', this.onScroll);
        if (this.frame) cancelAnimationFrame(this.frame);
        this.frame = 0;
    }

    snap(on) {
        document.documentElement.style.scrollSnapType = on ? 'y mandatory' : '';
    }

    update() {
        if (this.open || this.reduced.matches || !this.hasSequenceTarget) return;
        this.element.classList.toggle('is-story-scrolled', window.scrollY >= 6);
        const box = this.sequenceTarget.getBoundingClientRect();
        const span = box.height - window.innerHeight;
        const k = (span > 0 ? clamp(-box.top / span) : 0) * STAGES;

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

        const number = Math.max(1, Math.min(STAGES, Math.floor(k) + 1));
        if (this.hasMarkerNumberTarget) this.markerNumberTarget.textContent = String(number).padStart(2, '0');
        if (this.hasMarkerTarget) this.markerTarget.setAttribute('aria-label', `Stage ${number} of ${STAGES}`);
    }
}
