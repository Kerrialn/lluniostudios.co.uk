import { Controller } from '@hotwired/stimulus';

/*
 * Cross-fading slideshow (Workshop hero and origin story).
 * Images advance every `interval` ms. Videos play muted from the start and
 * advance when they end; as in the Site v4 prototype, a video that fails to
 * load or hasn't started playing within 6s is skipped. Optional Back/Next,
 * "01 / 08" counter, swipe (50px, mostly sideways) and a sound toggle that
 * shows only while a video is actually playing.
 * Autoplay is off for visitors who prefer reduced motion.
 * Photos given as data-src / data-srcset (with their WebP <source>) are
 * loaded only for the slide shown and the next one (the speed pass).
 */
export default class extends Controller {
    static targets = ['slide', 'counter', 'sound', 'soundOn', 'soundOff'];
    static values = { interval: { type: Number, default: 5000 } };

    connect() {
        this.index = 0;
        this.muted = true;
        this.playing = null;
        this.autoplay = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.listeners = new AbortController();

        this.videos().forEach((video) => {
            const { signal } = this.listeners;
            video.addEventListener('playing', () => {
                if (video !== this.currentVideo()) return;
                this.playing = video;
                this.renderSound();
            }, { signal });
            const stopped = () => {
                if (this.playing !== video) return;
                this.playing = null;
                this.renderSound();
            };
            video.addEventListener('pause', stopped, { signal });
            video.addEventListener('ended', stopped, { signal });
            video.addEventListener('error', () => {
                if (this.autoplay && video === this.currentVideo()) this.next();
            }, { signal });
        });

        this.show(0);
    }

    disconnect() {
        clearTimeout(this.timer);
        clearTimeout(this.guard);
        this.listeners.abort();
        this.videos().forEach((video) => video.pause());
    }

    next() {
        this.show(this.index + 1);
    }

    prev() {
        this.show(this.index - 1);
    }

    toggleSound() {
        this.muted = !this.muted;
        this.videos().forEach((video) => { video.muted = this.muted; });
        const video = this.currentVideo();
        if (video && video.paused) video.play().catch(() => {});
        this.renderSound();
    }

    touchStart(event) {
        this.touchX = event.touches[0].clientX;
        this.touchY = event.touches[0].clientY;
        this.touchDx = 0;
        this.touchDy = 0;
    }

    touchMove(event) {
        if (this.touchX === undefined) return;
        this.touchDx = event.touches[0].clientX - this.touchX;
        this.touchDy = event.touches[0].clientY - this.touchY;
    }

    touchEnd() {
        const dx = this.touchDx || 0;
        const dy = this.touchDy || 0;
        this.touchX = undefined;
        this.touchDx = 0;
        this.touchDy = 0;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) dx < 0 ? this.next() : this.prev();
    }

    show(index) {
        const count = this.slideTargets.length;
        if (count === 0) return;
        clearTimeout(this.timer);
        clearTimeout(this.guard);
        this.index = ((index % count) + count) % count;

        [this.index, (this.index + 1) % count, (this.index - 1 + count) % count].forEach((i) => this.load(this.slideTargets[i]));
        this.slideTargets.forEach((slide, i) => {
            const active = i === this.index;
            slide.classList.toggle('opacity-100', active);
            slide.classList.toggle('opacity-0', !active);
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');
            const video = slide.querySelector('video');
            if (video && !active) video.pause();
        });

        if (this.hasCounterTarget) {
            const pad = (n) => String(n).padStart(2, '0');
            this.counterTarget.textContent = `${pad(this.index + 1)} / ${pad(count)}`;
        }
        this.renderSound();

        const video = this.currentVideo();
        if (video && !this.autoplay) {
            // Reduced motion: no autoplay, let the visitor start the video.
            video.controls = true;
        } else if (video) {
            video.muted = this.muted;
            video.currentTime = 0;
            video.onended = () => this.next();
            video.play().catch(() => {
                // Autoplay with sound can be blocked: fall back to muted.
                this.muted = true;
                video.muted = true;
                this.renderSound();
                video.play().catch(() => {});
            });
            this.guardVideo(video);
        } else if (this.autoplay && count > 1) {
            this.timer = setTimeout(() => this.next(), this.intervalValue);
        }
    }

    // Skip a video that hasn't started within 6s; if it is playing, make sure
    // the slideshow still moves on shortly after it should have finished.
    guardVideo(video) {
        const index = this.index;
        this.guard = setTimeout(() => {
            if (this.index !== index) return;
            if (this.playing !== video) {
                this.next();
                return;
            }
            const remaining = Number.isFinite(video.duration) ? video.duration - video.currentTime + 1 : 0;
            this.guard = setTimeout(() => {
                if (this.index === index) this.next();
            }, Math.max(1000, remaining * 1000));
        }, 6000);
    }

    // Loads a slide's photo if it was waiting (data-src), WebP source first.
    load(slide) {
        slide?.querySelectorAll('source[data-srcset]').forEach((source) => {
            source.srcset = source.dataset.srcset;
            delete source.dataset.srcset;
        });
        slide?.querySelectorAll('img[data-src]').forEach((img) => {
            if (img.dataset.srcset) img.srcset = img.dataset.srcset;
            img.src = img.dataset.src;
            delete img.dataset.src;
            delete img.dataset.srcset;
        });
    }

    renderSound() {
        if (!this.hasSoundTarget) return;
        const video = this.currentVideo();
        this.soundTarget.hidden = !(video && this.playing === video);
        this.soundTarget.setAttribute('aria-label', this.muted ? 'Turn sound on' : 'Turn sound off');
        this.soundTarget.title = this.soundTarget.getAttribute('aria-label');
        if (this.hasSoundOnTarget) this.soundOnTarget.hidden = this.muted;
        if (this.hasSoundOffTarget) this.soundOffTarget.hidden = !this.muted;
    }

    currentVideo() {
        return this.slideTargets[this.index]?.querySelector('video') ?? null;
    }

    videos() {
        return this.slideTargets.map((slide) => slide.querySelector('video')).filter(Boolean);
    }
}
