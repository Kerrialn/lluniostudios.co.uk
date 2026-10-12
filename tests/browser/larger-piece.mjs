// Step 7b.4: "Discuss a Larger Piece". The configurator panel as a single step
// (The Scale with Atelier, Estate and Monument, none chosen at first, no stage
// counter, and the
// quantity), "Please choose a size to continue." without one, the size's
// specifications and the purchase route note, "Image Coming Soon" with its name,
// the way back ("Considering Studio or Gallery?") and in again ("Considering a
// larger piece?"), remembered (a reload opens on it), a design remembered from
// before with a larger size moving here, then its button straight to Reserve
// Your Design with the size and quantity and "Finishes: To be chosen together",
// the request saved without options, emails 02 and the studio's showing the
// finishes, the step's choices forgotten (the configurator's kept), and (on
// desktop) admin's request and quote pages.
//   node tests/browser/larger-piece.mjs 1440 900
//   node tests/browser/larger-piece.mjs 390 844
import { open, sleep, checker, sql, BASE_URL, MAILPIT_URL } from './lib.mjs';

const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const phone = W < 900;
const { check, summary } = checker();
const b = await open(W, H, `larger${W}`, { mobile: phone });
const { send, ev } = b;
const EMAIL = `ada.larger${W}@example.test`;
const ADMIN = 'lluniostudios@gmail.com';
const SIZE = 'Hand cast to order. Reserve now to secure your casting slot. Delivery will be quoted for individually.';
const LIMIT = 'Quantities of three or more proceed through the Reserve request process, not direct checkout.';
const mailpit = async (q) => (await (await fetch(MAILPIT_URL + '/api/v1/search?query=' + encodeURIComponent(q))).json()).messages || [];
const mailBody = async (id) => (await (await fetch(MAILPIT_URL + '/api/v1/message/' + id)).json());
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
            panelTop: Math.round(p.getBoundingClientRect().top), saved: localStorage.getItem('llunio:larger') }; })()`);
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
    check('3 lamps: the size note, then the 3-or-more note', s.quantity === '3' && s.route === `${SIZE} ${LIMIT}`, s.route);

    // Out and back in, in place (no page load).
    check('the way back reads "Prefer a smaller piece? Configure Studio or Gallery"', (await ev(`document.querySelector('[data-configurator-target=smaller]').parentElement.textContent.trim()`)) === 'Prefer a smaller piece? Configure Studio or Gallery');
    await ev('window.stayed = true');
    await ev(`document.querySelector('[data-configurator-target=smaller]').click()`); await sleep(600); s = await state();
    check('"Prefer a smaller piece? Configure Studio or Gallery": the configurator, The Scale with Studio and Gallery, the Gallery photos', !s.larger && s.main && s.sizes === 'Studio,Gallery*' && s.photo === 'gallery', JSON.stringify(s));
    await ev(`document.querySelector('.ll-cfg-opt[data-group=size][data-value=lumen-forma-studio]').click()`);
    await ev(`document.querySelector('[data-configurator-target=larger]').click()`); await sleep(600); s = await state();
    check('"Considering a larger piece? Discuss a Larger Piece": back on the step, Atelier and 3 lamps kept, without leaving the page', s.larger && s.sizes === 'Atelier*,Estate,Monument' && s.quantity === '3' && (await ev('window.stayed === true')), JSON.stringify(s));
    if (phone) check('on phones, back at the top of the panel', Math.abs(s.panelTop) <= 2, String(s.panelTop));

    // Remembered: a reload opens on the step.
    await go(BASE_URL + '/lighting-designs', 3500); s = await state();
    check('coming back: straight onto the step (no introduction), Atelier and 3 lamps', s.revealed && s.larger && !s.story && s.sizes === 'Atelier*,Estate,Monument' && s.quantity === '3' && s.photo === 'soon:Atelier', JSON.stringify(s));

    // Reserve Your Design.
    await button(); await sleep(2500);
    const reserve = await ev(`({ path: location.pathname, rows: [...document.querySelectorAll('aside .flex.justify-between')].map(r => [...r.children].map(c => c.textContent.trim()).join('=')),
        labels: [...document.querySelectorAll('form label > span:first-child')].map(l => l.textContent.trim()) })`);
    check('the button: Reserve Your Design with the Atelier, 3 lamps, "Finishes: To be chosen together" (no options, no Configuration, no Custom Details)',
        reserve.path === '/reserve' && JSON.stringify(reserve.rows.filter(r => !/^(Subtotal|Delivery|Total)/.test(r))) === JSON.stringify(['Size=Atelier', 'Finishes=To be chosen together', 'Quantity=3'])
        && !reserve.labels.some(l => /^Custom Details|^Reference photos/.test(l)), JSON.stringify(reserve));
    const form = { fullName: 'Ada Larger', email: EMAIL, phone: '07700 900123', addressLine1: '1 High Street', town: 'Wimborne', county: 'Dorset', postcode: 'BH21 1AA' };
    await ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(form)})) document.querySelector('[name="reserve_form[' + k + ']"]').value = v; document.querySelector('form[name=reserve_form]').requestSubmit(); })()`); await sleep(3500);
    const after = await ev(`({ path: location.pathname, larger: localStorage.getItem('llunio:larger'), configurator: localStorage.getItem('llunio:configurator') })`);
    check('sent: "Reservation received"; the step\'s choices forgotten, the configurator\'s (Studio) kept', after.path === '/reserve/received' && after.larger === null && /lumen-forma-studio/.test(after.configurator ?? ''), JSON.stringify(after));
    const number = sql(`select request_number from design_request where email = '${EMAIL}' order by created_at desc limit 1`).match(/LR-\d{6}-\d{4}/)?.[0];
    const item = sql(`select size_name, quantity, options_snapshot from design_request_item i join design_request r on r.id = i.design_request_id where r.request_number = '${number}'`);
    check('saved: one Atelier, 3 lamps, no options', /Atelier/.test(item) && /\b3\b/.test(item) && /\[\]/.test(item), item.replace(/\s+/g, ' '));

    // The emails.
    let customer, studio;
    for (let i = 0; i < 20 && !(customer && studio); i++) {
        customer ??= (await mailpit(`to:${EMAIL}`)).find(m => m.Subject === 'We have your request, Ada Larger');
        for (const m of await mailpit('to:lluniostudios@gmail.com subject:"New request"')) if (!studio && (await mailBody(m.ID)).HTML.includes(number)) studio = m;
        if (!(customer && studio)) await sleep(500);
    }
    const text = (html) => html.replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ');
    const c = customer ? text((await mailBody(customer.ID)).HTML) : '';
    check('email 02: the larger size and more than two lamps opening, Size Atelier, Finishes To be chosen together, Quantity 3, no options or "Same configuration"',
        /Your order is for more than two lamps and includes one of our larger sizes/.test(c) && /Size Atelier Finishes To be chosen together Quantity 3/.test(c) && !/The Pigment|Same configuration/.test(c), c.slice(0, 600));
    const t = studio ? text((await mailBody(studio.ID)).HTML) : '';
    check('the studio\'s notification: "New request · Atelier × 3", the finishes to be chosen together', /Atelier × 3/.test(studio?.Subject ?? '') && /Finishes To be chosen together/.test(t) && !/The Pigment/.test(t), `${studio?.Subject} | ${t.slice(0, 300)}`);

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
    check('server: an Estate with its size only goes to Reserve Your Design', (await force({ size: 'lumen-forma-estate', quantity: 1, mode: 'all', lamps: [{}], finishes: 'together' })) === '/reserve');

    if (!phone) {
        // Admin: the request's page and its quote show the finishes to be chosen together.
        await go(BASE_URL + '/login');
        await ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
        let code;
        for (let i = 0; i < 20 && !code; i++) {
            const m = (await mailpit(`to:${ADMIN} subject:"Sign in to Llunio Studios"`))[0];
            if (m) code = ((await mailBody(m.ID)).Text || '').match(/\b\d{6}\b/)?.[0];
            if (!code) await sleep(500);
        }
        await ev(`(() => { const h = document.querySelector('[data-pin-code-target=hidden]'); h.value = '${code}'; h.form.requestSubmit(); })()`); await sleep(3000);
        const id = sql(`select id from design_request where request_number = '${number}'`).match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)[0];
        await go(`${BASE_URL}/admin/design-request/${id}`, 3000);
        const detail = await ev(`document.body.innerText`);
        check('admin, the request: "Lumen Forma Atelier · 3 lamps", "Finishes: to be chosen together", no "Customise All"', /Atelier/.test(detail) && /3 lamps/.test(detail) && /Finishes: to be chosen together/.test(detail) && !/Customise All/.test(detail), detail.slice(0, 400));
        await go(`${BASE_URL}/admin/design-request/${id}/quote`, 3000);
        check('admin, its quote: the design with "Finishes: to be chosen together"', /Finishes: to be chosen together/.test(await ev(`document.body.innerText`)));
    }
    summary();
} finally { b.close(); }
