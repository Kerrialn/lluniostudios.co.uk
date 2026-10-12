// Remembered choices (decided 8 October 2026): leaving the configurator or
// Reserve Your Design and coming back keeps the size, quantity, every
// option, Customise All / Individually and each lamp's choices, and what was
// typed on the Reserve form. An unfinished design starts with the hero and
// stage 01 as before; a finished one (every choice made, for every lamp)
// opens straight on the review, the panel revealed. Forgotten once the lamp is added to the basket or the
// reservation is sent; "Start again" clears everything. Editing a basket line
// isn't remembered. Also: on the review, no line under "Reserve Your Design".
import { open, sleep, checker, sql, BASE_URL } from './lib.mjs';

const { check, summary } = checker();
const b = await open(1440, 900, 'cfg-remember');
const { send, ev } = b;
const EMAIL = 'ada.remember@example.test';
let phone = null;
try {
    const go = async (path, wait = 2000) => { await send('Page.navigate', { url: BASE_URL + path }); await sleep(wait); };
    const reveal = async () => { await ev("document.querySelector('.ll-story-skip').click()"); await sleep(1500); };
    const pick = (g, v) => ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
    const jump = (i) => ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
    const more = () => ev(`document.querySelector('[data-action="configurator#more"]').click()`);
    const mode = (m) => ev(`document.querySelector('.ll-cfg-mode[data-mode=${m}]').click()`);
    const tab = (i) => ev(`document.querySelectorAll('.ll-cfg-unit')[${i}].click()`);
    const press = async () => { await ev(`document.querySelector('[data-configurator-target=cta]').click()`); await sleep(2500); };
    const state = () => ev(`({
        revealed: document.querySelector('[data-controller~="hero-reveal"]').classList.contains('is-revealed'),
        stage: document.querySelector('[data-configurator-target=stageNumber]').textContent.slice(0, 2),
        size: document.querySelector('.ll-cfg-opt.is-selected[data-group=size]')?.dataset.value,
        quantity: document.querySelector('[data-configurator-target=quantity]').textContent,
        mode: document.querySelector('[data-configurator-target=modes]').hidden ? null : document.querySelector('.ll-cfg-mode.is-selected')?.dataset.mode,
        review: [...document.querySelectorAll('[data-configurator-target=review] button')].map(r => r.children[0].textContent + '=' + r.children[1].textContent),
        startAgain: (e => !!e && !e.hidden)(document.querySelector('[data-configurator-target=startAgain]')),
        saved: localStorage.getItem('llunio:configurator'),
    })`);
    await send('Network.enable'); await send('Network.clearBrowserCookies');

    // A fresh visit: nothing to clear.
    await go('/lighting-designs');
    let s = await state();
    check('a first visit: unrevealed, Gallery, 1 lamp, no "Start again", nothing saved', !s.revealed && s.size === 'lumen-forma-gallery' && s.quantity === '1' && !s.startAgain && s.saved === null, JSON.stringify(s));

    // Gallery ×3, customised individually, each lamp its own choices (three lamps go to
    // Reserve Your Design; since 7b.4 the larger sizes have their own step).
    await reveal();
    await more(); await more(); await mode('individual');
    await jump(1); await tab(0); await pick('The Pigment', 'Grey'); await tab(1); await pick('The Pigment', 'Black'); await tab(2); await pick('The Pigment', 'White');
    await jump(2); await tab(0); await pick('Cable Colour', 'Raw Jute'); await tab(1); await pick('Cable Colour', 'Orange'); await tab(2); await pick('Cable Colour', 'Slate Grey');
    await jump(3); await tab(0); await pick('Footing Material', 'Polished Copper'); await tab(1); await pick('Footing Material', 'Brushed Aluminium'); await tab(2); await pick('Footing Material', 'Brushed Copper');
    await jump(4); await tab(0); await pick('Bulb Style', 'Smoked Glass LED'); await tab(1); await pick('Bulb Style', 'Crown Gold LED'); await tab(2); await pick('Bulb Style', 'Tubular Amber LED');
    await jump(5);
    const before = (await state()).review;
    check('"Start again" appears once something is chosen', (await state()).startAgain);

    // Away and back (a full page load, then a Swup link click).
    const revealedSoon = async () => { for (let i = 0; i < 20 && !(await ev(`document.querySelector('[data-controller~="hero-reveal"]').classList.contains('is-revealed')`)); i++) await sleep(250); };
    await go('/contact');
    await go('/lighting-designs'); await revealedSoon();
    s = await state();
    check('coming back with a finished design: no hero, the panel revealed on the review (06)', s.revealed && s.stage === '06' && (await ev(`document.querySelector('[data-configurator-target=cta]').offsetParent !== null`)), JSON.stringify([s.revealed, s.stage]));
    check('and the choices are still there: Gallery, 3 lamps, Customise Individually', s.size === 'lumen-forma-gallery' && s.quantity === '3' && s.mode === 'individual', JSON.stringify(s));
    check("each lamp's own choices kept (the review is the same)", JSON.stringify(s.review) === JSON.stringify(before) && before.some(r => r === 'Lamp 1=Grey · Raw Jute · Polished Copper · Smoked Glass LED') && before.some(r => r === 'Lamp 2=Black · Orange · Brushed Aluminium · Crown Gold LED') && before.some(r => r === 'Lamp 3=White · Slate Grey · Brushed Copper · Tubular Amber LED'), JSON.stringify(before));
    await ev(`(() => { const a = document.createElement('a'); a.href = '/contact'; document.querySelector('#main').append(a); a.click(); })()`); await sleep(2300);
    await ev(`(() => { const a = document.createElement('a'); a.href = '/lighting-designs'; document.querySelector('#main').append(a); a.click(); })()`); await sleep(2300); await revealedSoon();
    s = await state();
    check('the same after moving between pages without reloading (Swup)', JSON.stringify(s.review) === JSON.stringify(before) && s.revealed && s.stage === '06', JSON.stringify([s.stage, s.review]));
    await jump(1); await ev(`document.querySelectorAll('.ll-cfg-unit')[1].click()`); await pick('The Pigment', 'White');
    s = await state();
    check('any stage can still be opened and changed from there', s.stage === '02' && s.review.includes('Lamp 2=White · Orange · Brushed Aluminium · Crown Gold LED'), JSON.stringify(s.review));
    await jump(1); await ev(`document.querySelectorAll('.ll-cfg-unit')[1].click()`); await pick('The Pigment', 'Black');

    // The review: no line under "Reserve Your Design".
    await reveal(); await jump(5);
    const review = await ev(`({ cta: document.querySelector('[data-configurator-target=cta]').textContent, sub: document.querySelector('[data-configurator-target=ctaSub]'), text: document.querySelector('[data-configurator-target=cta]').closest('.flex-col').innerText })`);
    check('the review: "Reserve Your Design" with no "Small-batch studio capacity…" line under it', review.cta === 'Reserve Your Design' && !/Small-batch studio capacity/.test(review.text), review.text.slice(-200));

    // Reserve Your Design: what is typed is remembered too.
    await press();
    check('Reserve Your Design opens with the three lamps', (await ev('location.pathname')) === '/reserve' && /Small-batch studio capacity/.test(await ev(`document.querySelector('main').innerText`)), 'and the line stays on the Reserve page');
    const typed = { fullName: 'Ada Remember', email: EMAIL, phone: '07700 900123', addressLine1: '1 High Street', town: 'Wimborne', county: 'Dorset', postcode: 'BH21 1AA', accessNotes: 'Narrow gate.' };
    await ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(typed)})) { const f = document.querySelector('[name="reserve_form[' + k + ']"]'); f.value = v; f.dispatchEvent(new Event('input', { bubbles: true })); } })()`);
    await go('/lighting-designs');
    s = await state();
    check('back in the configurator from Reserve: the choices are still there', s.size === 'lumen-forma-gallery' && JSON.stringify(s.review) === JSON.stringify(before));
    await go('/reserve');
    const form = await ev(`Object.fromEntries(${JSON.stringify(Object.keys(typed))}.map(k => [k, document.querySelector('[name="reserve_form[' + k + ']"]').value]))`);
    check('back on Reserve Your Design: what was typed is still there', JSON.stringify(form) === JSON.stringify(typed), JSON.stringify(form));
    await ev(`document.querySelector('form[name=reserve_form]').requestSubmit()`); await sleep(3000);
    const sent = await ev(`({ path: location.pathname, configurator: localStorage.getItem('llunio:configurator'), reserve: sessionStorage.getItem('llunio:reserve') })`);
    check('once the reservation is sent, both are forgotten', sent.path === '/reserve/received' && sent.configurator === null && sent.reserve === null, JSON.stringify(sent));
    check('the request has the three lamps', /\b3\b/.test(sql(`select sum(quantity) from design_request_item i where i.design_request_id = (select id from design_request where email = '${EMAIL}' order by created_at desc limit 1)`).match(/\d+/g).pop()));
    await go('/lighting-designs');
    s = await state();
    check('the configurator starts empty again', s.size === 'lumen-forma-gallery' && s.quantity === '1' && !s.startAgain && s.saved === null, JSON.stringify(s));

    // "Start again" clears everything.
    await reveal();
    await pick('size', 'lumen-forma-studio'); await jump(1); await pick('The Pigment', 'White');
    await go('/lighting-designs');
    s = await state();
    check('coming back with an unfinished design: the hero as before, then stage 01, the choices kept', !s.revealed && s.stage === '01' && s.size === 'lumen-forma-studio' && s.review.includes('The Pigment=White'), JSON.stringify(s));
    await reveal();
    await ev(`sessionStorage.setItem('llunio:reserve', '{"reserve_form[fullName]":"Someone"}')`);
    await ev(`document.querySelector('[data-configurator-target=startAgain]').click()`); await sleep(600);
    s = await state();
    check('"Start again": back to the first stage, Gallery, nothing chosen, nothing saved (Reserve details too)', s.stage === '01' && s.size === 'lumen-forma-gallery' && s.quantity === '1' && !s.startAgain && s.saved === null && (await ev(`sessionStorage.getItem('llunio:reserve')`)) === null && (await ev(`document.querySelectorAll('.ll-cfg-opt.is-selected:not([data-group=size])').length`)) === 0, JSON.stringify(s));
    await go('/lighting-designs');
    check('and it stays cleared after a reload', (await state()).saved === null);

    // Cast Your Lamp: forgotten once in the basket.
    await reveal();
    await jump(1); await pick('The Pigment', 'Grey'); await jump(2); await pick('Cable Colour', 'Raw Jute'); await jump(3); await pick('Footing Material', 'Polished Copper'); await jump(4); await pick('Bulb Style', 'Smoked Glass LED'); await jump(5);
    check('a Gallery design is remembered before it is added', (await state()).saved !== null);
    await press();
    check('Cast Your Lamp: in the basket, and the choices are forgotten', (await ev('location.pathname')) === '/basket' && (await ev(`localStorage.getItem('llunio:configurator')`)) === null);
    await go('/lighting-designs');
    s = await state();
    check('the configurator starts empty after adding to the basket', s.size === 'lumen-forma-gallery' && s.saved === null && !s.startAgain);

    // Editing a basket line isn't remembered, and doesn't replace what is.
    await reveal(); await pick('size', 'lumen-forma-studio');
    const saved = (await state()).saved;
    await go('/basket');
    const edit = await ev(`[...document.querySelectorAll('main a')].find(a => /edit=/.test(a.href))?.getAttribute('href')`);
    await go(edit, 2500);
    await jump(1); await pick('The Pigment', 'Black');
    check('editing a basket line: no "Start again", and the remembered design is untouched', !(await state()).startAgain && (await ev(`localStorage.getItem('llunio:configurator')`)) === saved, edit);

    // A finished design on a phone: scrolled down to the review, the button on screen.
    const finished = JSON.stringify({ version: 1, size: 'lumen-forma-gallery', quantity: 1, mode: 'all', choices: { 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED' }, lamps: [] });
    await ev(`localStorage.setItem('llunio:configurator', ${JSON.stringify(finished)})`);
    await go('/lighting-designs', 2500);
    check('on a desktop the page stays at the top (the panel is beside the photo)', (await ev('scrollY')) === 0);
    phone = await open(390, 844, 'cfg-remember-phone');
    await phone.send('Page.navigate', { url: BASE_URL + '/contact' }); await sleep(1500);
    await phone.ev(`localStorage.setItem('llunio:configurator', ${JSON.stringify(finished)})`);
    await phone.send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(3000);
    const onPhone = await phone.ev(`(() => { const p = document.querySelector('.ll-cfg-panel').getBoundingClientRect(); const c = document.querySelector('[data-configurator-target=cta]').getBoundingClientRect(); return { scrollY, panelTop: Math.round(p.top), ctaBottom: Math.round(c.bottom), height: innerHeight, stage: document.querySelector('[data-configurator-target=stageNumber]').textContent }; })()`);
    check('on a phone: scrolled straight to the review, "Cast Your Lamp" on screen', onPhone.scrollY > 0 && Math.abs(onPhone.panelTop) <= 2 && onPhone.ctaBottom <= onPhone.height && onPhone.stage === '06 / 06', JSON.stringify(onPhone));
    summary();
} finally { b.close(); phone?.close(); }
