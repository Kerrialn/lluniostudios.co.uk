import { Controller } from '@hotwired/stimulus';

/*
 * Product show gallery:
 *  - clicking a thumbnail swaps the main image;
 *  - the navbar logo/icons flip between dark and white depending on how dark
 *    the top of the current image is (so the logo stays legible over any image).
 */
export default class extends Controller {
    static targets = ['main', 'thumb'];
    static values = { threshold: { type: Number, default: 140 } };

    connect() {
        this.onLoad = () => this.updateNav();
        this.mainTarget.addEventListener('load', this.onLoad);
        if (this.mainTarget.complete && this.mainTarget.naturalWidth > 0) {
            this.updateNav();
        }
    }

    disconnect() {
        this.mainTarget.removeEventListener('load', this.onLoad);
        // NB: don't reset the navbar here — Swup swaps in the next page's own
        // navbar (with its correct server-rendered `nav-light` state). Removing
        // the class on disconnect races that swap and can strip a legitimately
        // light navbar (e.g. navigating from the show page to the landing hero).
    }

    get navbar() {
        return document.getElementById('navbar');
    }

    select(event) {
        const button = event.currentTarget;
        const src = button.dataset.full;
        if (!src) return;

        this.mainTarget.src = src;
        this.thumbTargets.forEach((thumb) => {
            thumb.classList.toggle('ring-2', thumb === button);
            thumb.setAttribute('aria-current', thumb === button ? 'true' : 'false');
        });
    }

    updateNav() {
        const navbar = this.navbar;
        if (!navbar) return;

        try {
            const img = this.mainTarget;
            if (!img.naturalWidth) return;

            const w = 48;
            const h = Math.max(1, Math.round((w * img.naturalHeight) / img.naturalWidth));
            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(img, 0, 0, w, h);

            // Sample the top strip (where the navbar sits).
            const rows = Math.max(1, Math.round(h * 0.25));
            const { data } = ctx.getImageData(0, 0, w, rows);

            let sum = 0;
            let count = 0;
            for (let i = 0; i < data.length; i += 4) {
                sum += 0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2];
                count += 1;
            }
            const luminance = sum / count; // 0 (black) .. 255 (white)
            navbar.classList.toggle('nav-light', luminance < this.thresholdValue);
        } catch (e) {
            // Cross-origin/decode issue — leave the navbar in its default state.
        }
    }
}
