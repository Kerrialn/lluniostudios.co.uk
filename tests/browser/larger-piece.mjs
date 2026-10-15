// Step 7b.4: "Discuss a Larger Piece". The configurator panel as a single step
// (The Scale with Atelier, Estate and Monument, none chosen at first, no stage
// counter, and the
// quantity), "Please choose a size to continue." without one, the size's
// specifications and the purchase route note, "Image Coming Soon" with its name,
// the way back ("Considering Studio or Gallery?") and in again ("Considering a
// larger piece?"), remembered (a reload opens on it), a design remembered from
// before with a larger size moving here, then its button, "Begin the
// Conversation", straight to the enquiry page (Step 7c) with the size, quantity
// and "Finishes: To be chosen together", and Reserve Your Design not carrying
// it. (Saving the enquiry, its emails and admin: 7c.2 and 7c.3.)
//   node tests/browser/larger-piece.mjs 1440 900
//   node tests/browser/larger-piece.mjs 390 844
import { open, sleep, checker, BASE_URL } from './lib.mjs';

const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const phone = W < 900;
const { check, summary } = checker();
const b = await open(W, H, `larger${W}`, { mobile: phone });
const { send, ev } = b;
const SIZE = 'Hand cast to order. Reserve now to secure your casting slot. Delivery will be quoted for individually.';
const LIMIT = 'Quantities of three or more proceed through the Reserve request process, not direct checkout.';
try {
    const go = async (url, wait = 2500) => { await send('Page.navigate', { url }); await sleep(wait); await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`); };
    const state = () => ev(`(() => { const root = document.querySelector('[data-controller~=configurator]'), p = document.querySelector('.ll-cfg-panel'), vis = (e) => !!e && e.getClientRects().length > 0;
        const step = p.querySelector('.ll-cfg-larger');
        return { revealed: root.classList.contains('is-revealed'), larger: root.classList.contains('is-larger'), story: vis(document.querySelector('.ll-story')),
            counter: vis(step) && /\\d\\d \\/ \\d\\d/.test(step.innerText), title: vis(step) ? step.querySelector('h2').textContent.trim() : null,
            sizes: [...p.querySelectorAll('.ll-cfg-opt')].filter(vis).map(e => e.textContent.trim() + (e.classList.contains('is-selected') ? '*' : '')).join(),
            warn: vis(p.querySelector('[data-configurator-target=largerWarning]')), route: p.querySelector('[data-configurator-target=largerRouteNote]').textContent.trim(),
            spec: vis(p.querySelector('[data-configurator-target=largerSpecs]')) ? p.querySelector('[data-configurator-target=largerSpec]').textContent + ' ' + [...p.querySelectorAll('[data-configurator-target=largerDimension]')].map(e => e.textContent).join('x') : null,
            quantity: p.querySelector('[data-configurator-target=largerQuantity]').textContent, main: vis(p.querySelector('[data-configurator-target=next]')) || vis(p.querySelector('ol[aria-label=Stages]')),
            photo: !document.querySelector('[data-configurator-target=soon]').hidden ? 'soon:' + document.querySelector('[data-configurator-target=soonTitle]').textContent : 'gallery',
            panelTop: Math.round(p.getBoundingClientRect().top), atEnd: Math.ceil(scrollY + innerHeight) >= document.documentElement.scrollHeight - 1, scroll: [Math.round(scrollY), innerHeight, document.documentElement.scrollHeight], saved: localStorage.getItem('llunio:larger') }; })()`);
    const button = () => ev(`document.querySelector('[data-configurator-target=largerConfiguration]').closest('form').querySelector('button').click()`);

    // From the introduction's ending.
    await b.clearStorage(); await send('Network.enable'); await send('Network.clearBrowserCookies');
    await go(BASE_URL + '/lighting-designs');
    await ev(`[...document.querySelectorAll('.ll-story-stage')[6].querySelectorAll('button')].find(x => x.textContent.trim() === 'Discuss a Larger Piece').click()`); await sleep(2500);
    let s = await state();
    check('"Discuss a Larger Piece": the panel as one step, The Scale (no stage counter), Atelier, Estate and Monument, none chosen, no other stages',
        s.revealed && s.larger && !s.story && !s.counter && s.title === 'The Scale' && s.sizes === 'Atelier,Estate,Monument' && !s.main && s.quantity === '1' && !s.spec, JSON.stringify(s));
    check('its purchase route note (the Request size note) and "Image Coming Soon" (no size yet)', s.route === SIZE && s.photo === 'soon:', JSON.stringify([s.route, s.photo]));
    await button(); await sleep(600); s = await state();
    check('the button without a size: "Please choose a size to continue.", nothing sent', s.warn && (await ev('location.pathname')) === '/lighting-designs', JSON.stringify(s));
    await ev(`document.querySelector('[data-configurator-target=largerOption][data-value=lumen-forma-atelier]').click()`); s = await state();
    check('Atelier chosen: the message goes, its specifications, "Image Coming Soon: Atelier"', !s.warn && s.sizes === 'Atelier*,Estate,Monument' && s.spec === 'Atelier · Starting at £1,699 30x30x90' && s.photo === 'soon:Atelier', JSON.stringify(s));
    for (let i = 0; i < 2; i++) await ev(`document.querySelector('[data-action="configurator#largerMore"]').click()`);
    s = await state();
    check('3 lamps: the size note only, no 3-or-more note (every larger piece is an enquiry; 13 October 2026)', s.quantity === '3' && s.route === SIZE && !s.route.includes(LIMIT), s.route);
    for (let i = 0; i < 8; i++) await ev(`document.querySelector('[data-action="configurator#largerMore"]').click()`);
    s = await state();
    check('10 lamps: still the size note only', s.quantity === '10' && s.route === SIZE, s.route);
    for (let i = 0; i < 7; i++) await ev(`document.querySelector('[data-action="configurator#largerFewer"]').click()`);

    // Out and back in, in place (no page load).
    check('the way back reads "Prefer a smaller piece? Configure a Studio or Gallery lamp"', (await ev(`document.querySelector('[data-configurator-target=smaller]').parentElement.textContent.trim()`)) === 'Prefer a smaller piece? Configure a Studio or Gallery lamp');
    await ev('window.stayed = true');
    await ev(`document.querySelector('[data-configurator-target=smaller]').click()`); await sleep(600); s = await state();
    check('"Prefer a smaller piece? Configure a Studio or Gallery lamp": the configurator, The Scale with Studio and Gallery, the Gallery photos', !s.larger && s.main && s.sizes === 'Studio,Gallery*' && s.photo === 'gallery', JSON.stringify(s));
    await ev(`document.querySelector('.ll-cfg-opt[data-group=size][data-value=lumen-forma-studio]').click()`);
    await ev(`document.querySelector('[data-configurator-target=larger]').click()`); await sleep(600); s = await state();
    check('"Considering a larger piece? Discuss a Larger Piece": back on the step, Atelier and 3 lamps kept, without leaving the page', s.larger && s.sizes === 'Atelier*,Estate,Monument' && s.quantity === '3' && (await ev('window.stayed === true')), JSON.stringify(s));
    // The step is given the screen's full height on phones, so its panel reaches the top as the other stages do (13 October 2026).
    if (phone) check('on phones, back at the top of the panel', Math.abs(s.panelTop) <= 2, JSON.stringify([s.panelTop, s.atEnd, s.scroll]));

    // Remembered: a reload opens on the step.
    await go(BASE_URL + '/lighting-designs', 3500); s = await state();
    check('coming back: straight onto the step (no introduction), Atelier and 3 lamps', s.revealed && s.larger && !s.story && s.sizes === 'Atelier*,Estate,Monument' && s.quantity === '3' && s.photo === 'soon:Atelier', JSON.stringify(s));

    // "Begin the Conversation" (Step 7c; the page itself: enquiry-page.mjs). Saving and the
    // emails come in 7c.2 and 7c.3, with their checks.
    check('the step\'s button reads "Begin the Conversation"', (await ev(`document.querySelector('[data-configurator-target=largerConfiguration]').closest('form').querySelector('button').textContent.trim()`)) === 'Begin the Conversation');
    await button(); await sleep(2500);
    const enquiry = await ev(`({ path: location.pathname, h1: document.querySelector('h1')?.textContent.trim(), rows: [...document.querySelectorAll('[aria-label="Your piece"] .flex.justify-between')].slice(1).map(r => [...r.children].map(c => c.textContent.trim()).join('=')) })`);
    check('the button: "Begin the Conversation" with the Atelier, 3 lamps, "Finishes: To be chosen together" (not Reserve Your Design)',
        enquiry.path === '/begin-the-conversation' && enquiry.h1 === 'Begin the Conversation' && JSON.stringify(enquiry.rows) === JSON.stringify(['Size=Atelier', 'Finishes=To be chosen together', 'Quantity=3']), JSON.stringify(enquiry));
    await go(BASE_URL + '/reserve', 2000);
    check('Reserve Your Design doesn\'t carry the larger piece (nothing to reserve: back to Lighting Designs)', (await ev('location.pathname')) === '/lighting-designs');

    // A design remembered from before 7b.4 with a larger size moves to the step.
    await go(BASE_URL + '/contact', 1500);
    await ev(`localStorage.setItem('llunio:configurator', ${JSON.stringify(JSON.stringify({ version: 1, size: 'lumen-forma-monument', quantity: 2, mode: 'all', choices: { 'The Pigment': 'Grey', 'Cable Colour': 'Orange', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED', 'Bulb Count': 'Dual Array' }, lamps: [], details: {}, lampDetails: [] }))}); localStorage.removeItem('llunio:larger')`);
    await go(BASE_URL + '/lighting-designs', 3500); s = await state();
    check('a design remembered from before with Monument: opens on the step with Monument and 2 lamps', s.revealed && s.larger && s.sizes === 'Atelier,Estate,Monument*' && s.quantity === '2', JSON.stringify(s));
    await ev(`document.querySelector('[data-configurator-target=smaller]').click()`); await sleep(600); s = await state();
    check('and the configurator starts from Gallery, its choices kept (no Bulb Count)', s.sizes === 'Studio,Gallery*' && /"size":"lumen-forma-gallery"/.test(await ev(`localStorage.getItem('llunio:configurator')`)) && !/Bulb Count/.test(await ev(`localStorage.getItem('llunio:configurator')`)), await ev(`localStorage.getItem('llunio:configurator')`));

    // The server: a size only is a larger piece only, and always goes to Reserve.
    const force = async (config) => { await go(BASE_URL + '/lighting-designs'); await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100); await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify(config))}; f.requestSubmit(); })()`); await sleep(2500); return ev('location.pathname'); };
    check('server: a Gallery with its size only is refused (back to the configurator, nothing in the basket)', (await force({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [{}], finishes: 'together' })) === '/lighting-designs' && !/Basket \(\d/.test(await ev(`document.querySelector('#navbar').innerText`)));
    check('server: an Estate with its size only goes to "Begin the Conversation"', (await force({ size: 'lumen-forma-estate', quantity: 1, mode: 'all', lamps: [{}], finishes: 'together' })) === '/begin-the-conversation');

    summary();
} finally { b.close(); }
