// The configurator page: nav link, no footer, the photo cross-fade and title
// colour, the reveal, the panel width and moving between stages.
import { open, sleep, checker, BASE_URL } from './lib.mjs';

const { check, summary } = checker();
const b = await open(1440, 900, 'page', { mobile: false });
const { send, ev } = b;
try {
    const visibleIdle = `[...document.querySelectorAll('[data-configurator-target=idle]')].findIndex(i => i.classList.contains('opacity-100'))`;
    const heroDark = `document.querySelector('.ll-cfg-hero').classList.contains('is-over-light')`;

    await send('Page.navigate', { url: BASE_URL + '/' }); await sleep(1500);
    check('nav "Lighting Designs" goes to /lighting-designs', (await ev(`[...document.querySelectorAll('#navbar a')].find(a => a.textContent.trim() === 'Lighting Designs').getAttribute('href')`)) === '/lighting-designs');
    await send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(1500);
    check('nav link marked current', (await ev(`[...document.querySelectorAll('#navbar a')].find(a => a.textContent.trim() === 'Lighting Designs').getAttribute('aria-current')`)) === 'page');
    check('no footer', (await ev(`document.querySelectorAll('footer').length`)) === 0);
    const i0 = await ev(visibleIdle), d0 = await ev(heroDark);
    await sleep(5400);
    const i1 = await ev(visibleIdle), d1 = await ev(heroDark);
    await sleep(5200);
    const i2 = await ev(visibleIdle), d2 = await ev(heroDark);
    check('cross-fade advances every 5s in order', i0 === 0 && i1 === 1 && i2 === 2, `${i0} -> ${i1} -> ${i2}`);
    check('title dark over the light first photos', d0 && d1 && d2);
    await sleep(5200);
    const i3 = await ev(visibleIdle), d3 = await ev(heroDark);
    check('title turns cream over the darker 4th photo', i3 === 3 && !d3, `photo ${i3 + 1}, dark=${d3}`);
    check('panel hidden before reveal', (await ev(`document.querySelector('.ll-cfg-panel').getBoundingClientRect().width`)) === 0);

    await ev("document.querySelector('.ll-story-skip').click()");
    await sleep(2200);
    const panelW = await ev(`Math.round(document.querySelector('.ll-cfg-panel').getBoundingClientRect().width)`);
    check('"Skip to configurator" reveals the panel at 25% (360px at 1440)', panelW === 360, `${panelW}px`);
    const afterReveal = await ev(visibleIdle);
    await sleep(5400);
    check('cross-fade stops after reveal on the first photo', afterReveal === 0 && (await ev(visibleIdle)) === 0);

    const state = () => ev(`({ num: document.querySelector('[data-configurator-target=stageNumber]').textContent, title: document.querySelector('[data-configurator-target=stageTitle]').textContent, intro: !document.querySelector('[data-configurator-target=intro]').hidden, backHidden: document.querySelector('[data-configurator-target=back]').classList.contains('opacity-0'), back: document.querySelector('[data-configurator-target=backTitle]').textContent, next: document.querySelector('[data-configurator-target=next]').hidden ? null : document.querySelector('[data-configurator-target=nextTitle]').textContent, current: [...document.querySelectorAll('.ll-cfg-step')].findIndex(s => s.classList.contains('is-current')), past: [...document.querySelectorAll('.ll-cfg-step.is-past')].length })`);
    let s = await state();
    check('stage 1: The Scale, intro shown, no Back, Next "The Pigment"', s.num === '01 / 06' && s.title === 'The Scale' && s.intro && s.backHidden && s.next === 'The Pigment', JSON.stringify(s));
    await ev(`document.querySelector('[data-configurator-target=next]').click()`); s = await state();
    check('Next: 02 / 06 The Pigment, intro hidden, Back "The Scale"', s.num === '02 / 06' && s.title === 'The Pigment' && !s.intro && !s.backHidden && s.back === 'The Scale' && s.past === 1);
    for (const [g, v] of [['The Pigment', 'White'], ['Cable Colour', 'Slate Grey'], ['Footing Material', 'Brushed Copper'], ['Bulb Style', 'Crown Gold LED']]) await ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
    await ev(`document.querySelectorAll('.ll-cfg-step')[5].click()`); s = await state();
    check('indicator jump to 06: Your Lumen Forma, no Next', s.num === '06 / 06' && s.title === 'Your Lumen Forma' && s.next === null && s.past === 5);
    await ev(`document.querySelector('[data-configurator-target=back]').click()`); s = await state();
    check('Back: 05 / 06 The Light', s.num === '05 / 06' && s.title === 'The Light' && s.next === 'Your Lumen Forma');

    // Every choice made: a reload opens on the review, the panel revealed (remembered choices).
    await send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(1500);
    for (let i = 0; i < 20 && !(await ev(`document.querySelector('[data-controller~=configurator]').classList.contains('is-revealed')`)); i++) await sleep(250);
    check('reload with a finished design: the panel revealed on 06', (await ev(`document.querySelector('[data-controller~=configurator]').classList.contains('is-revealed')`)) && (await ev(`document.querySelector('[data-configurator-target=stageNumber]').textContent`)) === '06 / 06');
    await b.clearStorage();
    await send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(1500);
    check('reload with nothing remembered starts unrevealed again', (await ev(`document.querySelector('.ll-cfg-panel').getBoundingClientRect().width`)) === 0 && !(await ev(`document.querySelector('[data-controller~=configurator]').classList.contains('is-revealed')`)));
    summary();
} finally { b.close(); }
