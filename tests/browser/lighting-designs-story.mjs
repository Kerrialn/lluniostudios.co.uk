// Step 7b.1: the Lighting Designs introduction. The hero (title, "Scroll to
// begin", "Skip to configurator" only, in Special Elite), the cross-fade to the
// pinned lamp on the first scroll (the slideshow pausing), the seven stages
// snapping in turn with "01 / 07", the lamp brightening a step at each, the
// text bulb amber throughout, nothing overlapping (the phone fixes; on phones
// the marker in the bottom-left corner), the headers underlined as on a
// typewriter (a row of Special Elite underscores as wide as the text), the
// two closing buttons on phones (on the right, 11px, at least 44px tall; upright
// 75px above the lamp, or under the heading), and the
// panel opening only from "Skip to configurator" or "Configure Studio or
// Gallery", at the top of the page with the snapping off. With "reduced":
// the plain page (no pinning, snapping or fades, every section in order).
//   node tests/browser/lighting-designs-story.mjs 1440 900
//   node tests/browser/lighting-designs-story.mjs 390 844
//   node tests/browser/lighting-designs-story.mjs 844 390
//   node tests/browser/lighting-designs-story.mjs 1440 900 reduced
import { open, sleep, checker, BASE_URL } from './lib.mjs';

const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const reduced = process.argv[4] === 'reduced';
const upright = W < 900 && H > W;
const { check, summary } = checker();
const b = await open(W, H, `story${W}x${H}${reduced ? 'r' : ''}`, { mobile: W < 900 || H < 500 });
const { send, ev } = b;
const AMBER = 'rgb(250, 179, 90)';
const LINE = `(h) => { const a = getComputedStyle(h, '::after'), c = getComputedStyle(h); const fs = parseFloat(c.fontSize);
    return [/^"_+"/.test(a.content), /Special Elite/.test(a.fontFamily), a.color === c.color, Math.abs(parseFloat(a.width) - (h.getBoundingClientRect().width - 0.14 * fs)) < 1, a.overflow === 'hidden', parseFloat(a.top) > 0].every(Boolean); }`;
const HEADERS = ['', 'THE MATERIAL', 'THE PROCESS', 'THE SIZES', 'THE SIGNATURE', 'THE MAKER', 'BEGIN YOUR LUMEN FORMA.'];
try {
    if (reduced) await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    const go = async () => { await b.clearStorage(); await send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(2500); };
    const opened = () => ev(`document.querySelector('[data-controller~=lumen-story]').classList.contains('is-revealed')`);
    const panelShown = () => ev(`document.querySelector('.ll-cfg-panel').getBoundingClientRect().width > 0`);
    const snapType = () => ev(`document.documentElement.style.scrollSnapType`);
    await go();
    await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);

    const hero = await ev(`(() => { const h1 = document.querySelector('.ll-story h1'); const vis = (e) => e && e.getBoundingClientRect().width > 0 && getComputedStyle(e).visibility !== 'hidden';
        return { title: h1.textContent.trim(), font: getComputedStyle(h1).fontFamily, upper: getComputedStyle(h1).textTransform,
            cue: vis(document.querySelector('.ll-story-cue')), skip: vis(document.querySelector('.ll-story-skip')) && document.querySelector('.ll-story-skip').textContent.trim(),
            subtitle: [...document.querySelectorAll('p')].some(p => vis(p) && p.textContent.includes('Hand-cast liquid stone lighting series')) }; })()`);
    check('hero: only the title "Lumen Forma" (Special Elite, uppercase), "Scroll to begin" and "Skip to configurator →"', hero.title === 'Lumen Forma' && /Special Elite/.test(hero.font) && hero.upper === 'uppercase' && hero.cue && hero.skip === 'Skip to configurator →' && !hero.subtitle, JSON.stringify(hero));
    check('the configurator is not shown before it is opened', !(await opened()) && !(await panelShown()));

    if (reduced) {
        const s = await ev(`(() => { const st = [...document.querySelectorAll('.ll-story-stage')]; return { pin: getComputedStyle(document.querySelector('.ll-story-pin')).position, shown: st.map(e => getComputedStyle(e).opacity).join(), inert: st.some(e => e.inert),
            ordered: st.every((e, i) => i === 0 || e.getBoundingClientRect().top >= st[i - 1].getBoundingClientRect().bottom - 1), marker: getComputedStyle(document.querySelector('.ll-story-marker')).display, lit: getComputedStyle(document.querySelector('.ll-story-lit')).opacity }; })()`);
        check('reduced motion: no pinning or snapping, every section shown in order, no marker, the lamp lit', s.pin === 'static' && (await snapType()) === '' && s.shown === '1,1,1,1,1,1,1' && !s.inert && s.ordered && s.marker === 'none' && s.lit === '1', JSON.stringify(s));
        await ev(`scrollTo(0, 4000)`); await sleep(400);
        const lines = await ev(`[...document.querySelectorAll('.ll-story-header')].map(${LINE}).join()`);
        check('reduced motion: the five headers underlined as on a typewriter, each as wide as its text', lines === 'true,true,true,true,true', lines);
        check('reduced motion: scrolling does not open the configurator', !(await opened()));
        await ev(`document.querySelector('.ll-story-button').click()`); await sleep(800);
        check('reduced motion: "Configure Studio or Gallery" opens the configurator at the top', (await opened()) && (await panelShown()) && (await ev('scrollY')) === 0);
        summary();
        process.exit();
    }

    check('the page snaps (scroll-snap-type y mandatory) with 8 snap points', (await snapType()) === 'y mandatory' && (await ev(`document.querySelectorAll('.ll-story-snap').length`)) === 8);
    const visibleIdle = `[...document.querySelectorAll('[data-configurator-target=idle]')].findIndex(i => i.classList.contains('opacity-100'))`;
    const tops = await ev(`[...document.querySelectorAll('.ll-story-snap')].map(e => Math.round(e.getBoundingClientRect().top + scrollY))`);
    await ev(`scrollTo(0, ${tops[1]})`); await sleep(2000);
    const i0 = await ev(visibleIdle);
    const heroGone = await ev(`getComputedStyle(document.querySelector('.ll-story-hero')).opacity`);
    await sleep(5500);
    check('the first scroll fades the hero onto the pinned lamp and pauses the slideshow, without opening the configurator', heroGone === '0' && (await ev(visibleIdle)) === i0 && !(await opened()), `hero opacity ${heroGone}`);

    for (let n = 1; n <= 7; n++) {
        await ev(`scrollTo(0, ${tops[n]})`); await sleep(1600);
        const s = await ev(`(() => { const st = [...document.querySelectorAll('.ll-story-stage')]; const cur = st[${n - 1}]; const r = (e) => e.getBoundingClientRect();
            const head = cur.querySelector('h2'); const skip = r(document.querySelector('.ll-story-skip')); const text = [...cur.children].filter(c => !c.classList.contains('ll-story-actions')).map(r);
            const top = Math.min(...text.map(x => x.top)), bottom = Math.max(...text.map(x => x.bottom)), marker = r(document.querySelector('.ll-story-marker')), photo = r(document.querySelector('.ll-story-photo'));
            return { shown: st.map(e => Number(e.style.opacity).toFixed(3)).join(' '), inert: st.map(e => e.inert ? 1 : 0).join(''), mk: document.querySelector('[data-lumen-story-target=markerNumber]').textContent,
                lit: document.querySelector('.ll-story-lit').style.opacity, ink: getComputedStyle(cur).color, font: getComputedStyle(cur).fontFamily, head: head ? head.innerText.trim() : '', line: head && head.classList.contains('ll-story-header') ? (${LINE})(head) : null, headWidth: head ? Math.round(head.getBoundingClientRect().width) : 0,
                skipClear: skip.bottom <= top || skip.left >= Math.max(...text.map(x => x.right)), markerClear: ${upright} ? marker.top >= bottom : marker.top >= bottom || marker.right <= Math.min(...text.map(x => x.left)),
                textAbovePhoto: ${upright} ? bottom <= photo.top : true, corner: [Math.round(marker.left), Math.round(innerHeight - marker.bottom)], right: Math.round(Math.min(...text.map(x => x.left)) / innerWidth * 100) }; })()`);
        const shown = [0, 0, 0, 0, 0, 0, 0].map((_, i) => (i === n - 1 ? '1.000' : '0.000')).join(' ');
        const inert = [0, 0, 0, 0, 0, 0, 0].map((_, i) => (i === n - 1 ? 0 : 1)).join('');
        check(`stage ${n}: only it shown (the others not reachable), "0${n} / 07", the lamp ${n - 1}/6 lit, amber Special Elite${HEADERS[n - 1] ? ', "' + HEADERS[n - 1] + '"' : ''}`,
            s.shown === shown && s.inert === inert && s.mk === `0${n}` && Math.abs(Number(s.lit) - (n - 1) / 6) < 0.01 && s.ink === AMBER && /Special Elite/.test(s.font) && s.head === HEADERS[n - 1], JSON.stringify(s));
        check(`stage ${n}: "Skip to configurator" and the marker clear of the text${upright ? ', the text above the photo' : ', the text from 54% across'}`, s.skipClear && s.markerClear && s.textAbovePhoto && (upright || s.right === 54), JSON.stringify(s));
    if (W < 900) check(`stage ${n}: on phones the marker is in the bottom-left corner (20px in, 20px up)`, s.corner[0] === 20 && s.corner[1] === 20, JSON.stringify(s.corner));
        if (n >= 2 && n <= 6) check(`stage ${n}: the header underlined as on a typewriter (Special Elite underscores in its colour, as wide as its text: ${s.headWidth}px)`, s.line === true, JSON.stringify(s));
        if (n === 6) check('The Maker: "Read the full story →" goes to The Workshop', (await ev(`document.querySelectorAll('.ll-story-stage')[5].querySelector('a').getAttribute('href')`)) === '/workshop');
    }
    const ends = await ev(`[...document.querySelectorAll('.ll-story-stage')[6].querySelectorAll('.ll-story-button')].map(e => [e.textContent.trim(), e.getAttribute('href') || e.tagName])`);
    check('the ending: "Configure Studio or Gallery" and "Discuss a Larger Piece" (to Reserve Your Design)', JSON.stringify(ends) === JSON.stringify([['Configure Studio or Gallery', 'BUTTON'], ['Discuss a Larger Piece', '/reserve']]), JSON.stringify(ends));
    const sizes = await ev(`[...document.querySelectorAll('.ll-story-stage')[6].querySelectorAll('.ll-story-button')].map(e => { const r = e.getBoundingClientRect(), c = getComputedStyle(e); return [Math.round(r.width), Math.round(r.height), c.fontSize, c.padding].join(' '); })`);
    if (W >= 900) check('the two buttons as the design (320 × 58, 13px)', sizes.every((x) => x === '320 58 13px 18px 32px'), JSON.stringify(sizes));
    else {
        const [w0, h0, ...rest] = sizes[0].split(' ');
        check('on phones the two buttons the same size, 11px on one line, side padding 20.4px, at least 44px tall', sizes[0] === sizes[1] && rest.join(' ') === '11px 13.5px 20.4px' && Number(h0) >= 44 && Number(h0) < 50, JSON.stringify(sizes));
        const p = await ev(`(() => { const r = (e) => e.getBoundingClientRect(); const st = document.querySelectorAll('.ll-story-stage')[6]; const a = r(st.querySelector('.ll-story-actions')); const head = r(st.querySelector('h2'));
            const skip = r(document.querySelector('.ll-story-skip')), marker = r(document.querySelector('.ll-story-marker')), frame = r(document.querySelector('.ll-story-frame'));
            return { right: Math.round(innerWidth - a.right), textRight: Math.round(innerWidth - r(document.querySelector('.ll-story-text')).right), belowHeading: a.top >= head.bottom, clearSkip: a.top >= skip.bottom || a.right <= skip.left, clearMarker: a.bottom <= marker.top || a.left >= marker.right,
                aboveLamp: Math.round(frame.top + 0.152 * frame.height - a.bottom), underHeading: Math.round(a.top - head.bottom) }; })()`);
        check(`on phones the buttons on the right (the text's right margin), clear of the heading, "Skip to configurator" and the counter${upright ? ', 63px above the place just over the lamp (75px above the lamp), or 18px under the heading where there isn\'t room' : ''}`,
            p.right === p.textRight && p.belowHeading && p.clearSkip && p.clearMarker && (!upright || Math.abs(p.aboveLamp - 75) <= 1 || (p.underHeading === 18 && p.aboveLamp < 75 && p.aboveLamp >= 12)), JSON.stringify(p));
    }
    await ev(`document.querySelectorAll('.ll-story-stage')[6].querySelector('button').click()`); await sleep(1500);
    check('"Configure Studio or Gallery" opens the configurator on The Scale, at the top, the snapping off, the introduction gone',
        (await opened()) && (await panelShown()) && (await ev('scrollY')) === 0 && (await snapType()) === '' && (await ev(`getComputedStyle(document.querySelector('.ll-story')).display`)) === 'none' && (await ev(`document.querySelector('[data-configurator-target=stageNumber]').textContent`)) === '01 / 06');

    await go();
    await ev(`scrollTo(0, ${tops[3]})`); await sleep(1600);
    await ev(`document.querySelector('.ll-story-skip').click()`); await sleep(1500);
    check('"Skip to configurator" part-way through opens it the same way', (await opened()) && (await panelShown()) && (await ev('scrollY')) === 0 && (await snapType()) === '');
    summary();
} finally { b.close(); }
