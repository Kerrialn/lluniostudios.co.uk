import { Controller } from '@hotwired/stimulus';

/*
 * Homepage bulb dimmer (Site v4 prototype, "Test 2" hero). Scrolling over the
 * bulb, or dragging up/down on it, fades the dark photo to the lit one. As in
 * the prototype the level glides towards its target (16% of the remaining gap
 * per frame) instead of jumping: wheel deltaY x 0.0011 (lines x16, pages x400),
 * touch 200px for the full range. A thin meter shows the level while the
 * pointer is over the bulb or during a drag.
 *
 * The nav, logo, footer icons and intro text sit on the photo and switch from
 * the bulb's amber to near-black at a measured balance point (where both give
 * equal contrast against the photo behind them), with a 0.3s crossfade, rather
 * than fading through colours as dark as the photo. The switch points live in
 * app.css (--sw-*); this controller only publishes the level as --ll-hero-b on
 * <html>, because the nav and footer sit outside this element.
 *
 * Also usable from the keyboard (arrow keys, Home/End) via the slider role,
 * which the prototype lacks.
 */
const clamp = (v) => Math.max(0, Math.min(1, v));

export default class extends Controller {
    static targets = ['overlay', 'hit', 'meter', 'meterFill'];

    connect() {
        this.bright = 0;
        this.target = 0;
        this.listeners = new AbortController();
        const { signal } = this.listeners;
        const hit = this.hitTarget;

        hit.addEventListener('mouseenter', () => this.setMeter(true), { signal });
        hit.addEventListener('mouseleave', () => this.setMeter(false), { signal });
        hit.addEventListener('wheel', (e) => {
            e.preventDefault();
            const k = e.deltaMode === 1 ? 16 : e.deltaMode === 2 ? 400 : 1;
            this.setMeter(true);
            this.glideTo(this.target + e.deltaY * k * 0.0011);
        }, { passive: false, signal });
        hit.addEventListener('touchstart', (e) => {
            this.touchY = e.touches[0].clientY;
            this.touchStartBright = this.bright;
            clearTimeout(this.meterTimer);
            this.setMeter(true);
            this.element.classList.add('is-dragging');
        }, { passive: true, signal });
        hit.addEventListener('touchmove', (e) => {
            e.preventDefault();
            this.glideTo(this.touchStartBright + (this.touchY - e.touches[0].clientY) / 200);
        }, { passive: false, signal });
        hit.addEventListener('touchend', () => {
            this.element.classList.remove('is-dragging');
            clearTimeout(this.meterTimer);
            this.meterTimer = setTimeout(() => this.setMeter(false), 900);
        }, { signal });
        hit.addEventListener('keydown', (e) => {
            const step = { ArrowUp: 0.1, ArrowRight: 0.1, ArrowDown: -0.1, ArrowLeft: -0.1 }[e.key];
            if (step !== undefined) this.glideTo(this.target + step);
            else if (e.key === 'Home') this.glideTo(0);
            else if (e.key === 'End') this.glideTo(1);
            else return;
            e.preventDefault();
        }, { signal });
        hit.addEventListener('focus', () => this.setMeter(true), { signal });
        hit.addEventListener('blur', () => this.setMeter(false), { signal });

        this.render();
    }

    disconnect() {
        clearTimeout(this.meterTimer);
        cancelAnimationFrame(this.frame);
        this.frame = null;
        this.listeners.abort();
        document.documentElement.style.removeProperty('--ll-hero-b');
    }

    glideTo(value) {
        this.target = clamp(value);
        if (this.frame) return;
        const tick = () => {
            const gap = this.target - this.bright;
            if (Math.abs(gap) < 0.0015) {
                this.bright = this.target;
                this.frame = null;
                this.render();
                return;
            }
            this.bright += gap * 0.16;
            this.render();
            this.frame = requestAnimationFrame(tick);
        };
        this.frame = requestAnimationFrame(tick);
    }

    setMeter(on) {
        this.meterTarget.classList.toggle('opacity-100', on);
        this.meterTarget.classList.toggle('opacity-0', !on);
    }

    render() {
        this.overlayTargets.forEach((el) => { el.style.opacity = String(this.bright); });
        this.meterFillTarget.style.height = `${(this.bright * 100).toFixed(1)}%`;
        this.hitTarget.setAttribute('aria-valuenow', String(Math.round(this.bright * 100)));

        document.documentElement.style.setProperty('--ll-hero-b', this.bright.toFixed(4));
    }
}
