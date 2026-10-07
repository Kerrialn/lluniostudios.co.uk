// 7.5: review, purchase route and adding to the basket, end to end.
import { open, sleep, BASE_URL } from './lib.mjs';
const b = await open(1440, 900, 'cfgcast');
let ok = true;
const check = (label, pass, detail = '') => { if (!pass) ok = false; console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`); };
const ev = b.ev;
const SIZE = 'Hand cast to order. Reserve now to secure your casting slot. Delivery will be quoted for individually.';
const CUSTOM = 'Hand cast to order. Reserve now to secure your casting slot. Custom options are developed with you and quoted individually.';
const BOTH = 'Hand cast to order. Reserve now to secure your casting slot. Custom options are developed with you, and delivery quoted individually.';
const LIMIT = 'Quantities of three or more proceed through the Reserve request process, not direct checkout.';
const note = () => ev(`document.querySelector('[data-configurator-target=routeNote]').textContent`);
const fresh = async () => { await b.send('Network.enable'); await b.send('Network.clearBrowserCookies'); };
const open_ = async () => { await b.send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(1800); await b.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 }); await sleep(1300); };
const pick = (g, v) => ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
const jump = (i) => ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
const tab = (i) => ev(`document.querySelectorAll('.ll-cfg-unit')[${i}].click()`);
const more = () => ev(`document.querySelector('[data-action="configurator#more"]').click()`);
const fill = async (lamps = 1, values = {}) => {
    const v = { 'The Pigment': 'White', 'Cable Colour': 'Slate Grey', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED', 'Bulb Count': 'Single Array', ...values };
    for (const [stage, groups] of [[1, ['The Pigment']], [2, ['Cable Colour']], [3, ['Footing Material']], [4, ['Bulb Style', 'Bulb Count']]]) {
        await jump(stage);
        for (let l = 0; l < lamps; l++) {
            if (lamps > 1) await tab(l);
            for (const g of groups) { const has = await ev(`!!document.querySelector('.ll-cfg-opt[data-group="${g}"]') && !document.querySelector('[data-configurator-target=group][data-group="${g}"]').hidden`); if (has) await pick(g, l === 1 && v[g + ' 2'] ? v[g + ' 2'] : v[g]); }
        }
    }
    await jump(5);
};
const route = () => ev(`({ note: document.querySelector('[data-configurator-target=routeNote]').textContent, cta: document.querySelector('[data-configurator-target=cta]').textContent, sub: document.querySelector('[data-configurator-target=ctaSub]').textContent, num: document.querySelector('[data-configurator-target=stageNumber]').textContent.slice(0, 2),
  rows: [...document.querySelectorAll('[data-configurator-target=review] button')].map(r => r.children[0].textContent + '=' + r.children[1].textContent) })`);
const submit = async () => { await ev(`document.querySelector('[data-configurator-target=cta]').click()`); await sleep(2000); };
const basket = () => ev(`({ url: location.pathname, text: document.querySelector('main')?.innerText || '', flash: [...document.querySelectorAll('[role=alert], .flash, [data-flash]')].map(e => e.innerText).join(' ') })`);

// 1. One Gallery lamp
await fresh(); await open_();
let r = await route();
check('The Scale: purchase route "Direct order and checkout."', r.note === 'Direct order and checkout.');
await fill(1);
r = await route();
check('review rows: size, price, quantity and each choice', JSON.stringify(r.rows) === JSON.stringify(['Size=Gallery', 'Price=£799', 'Quantity=1', 'The Pigment=White', 'Cable Colour=Slate Grey', 'Footing Material=Brushed Copper', 'Bulb Style=Crown Gold LED']), JSON.stringify(r.rows));
check('button "Cast Your Lamp" with the direct line', r.cta === 'Cast Your Lamp' && r.sub.startsWith('Limited weekly batch capacity'));
await ev(`[...document.querySelectorAll('[data-configurator-target=review] button')].find(b => b.textContent.startsWith('Cable Colour')).click()`); r = await route();
check('a review row opens its stage (Cable Colour -> 03)', r.num === '03');
await jump(5); await submit();
let k = await basket();
check('Cast Your Lamp adds it and opens the basket', k.url === '/basket' && /Size\s+Gallery/i.test(k.text) && /Slate Grey/i.test(k.text) && /Crown Gold LED/i.test(k.text), k.url);
check('no "Added to your basket" banner', !/Added to your basket/.test(await ev('document.body.innerText')) && (await ev(`document.querySelectorAll('[data-controller=toast]').length`)) === 0);
check('the nav basket count shows it', /Basket \(1\)/i.test(await ev(`document.querySelector('#navbar').innerText`)), await ev(`document.querySelector('#navbar').innerText.replace(/\\n/g, ' ')`));

// 2. Basket now holds 1 lamp: 2 more goes to Reserve
await open_(); await more(); r = await route();
check('with 1 lamp already in the basket, 2 more: Reserve, limit note', r.note === 'Quantities of three or more proceed through the Reserve request process, not direct checkout.', r.note);
await fill(1); r = await route();
check('review button "Reserve Your Design" with the reserve line', r.cta === 'Reserve Your Design' && r.sub.startsWith('Small-batch studio capacity'));
await submit(); k = await basket();
check('Reserve goes to the request route; nothing added', k.url === '/reserve', k.url);
await b.send('Page.navigate', { url: BASE_URL + '/basket' }); await sleep(1200); k = await basket();
check('basket still holds just the one lamp', (k.text.match(/Size\s+Gallery/gi) || []).length === 1);

// 3. Customise Individually, fresh basket
await fresh(); await open_(); await more(); await ev(`document.querySelector('.ll-cfg-mode[data-mode=individual]').click()`);
await fill(2, { 'The Pigment 2': 'Black', 'Cable Colour 2': 'Raw Jute' }); r = await route();
check('individual review rows per lamp', r.rows.includes('Configuration=Customise Individually') && r.rows.includes('Lamp 1=White · Slate Grey · Brushed Copper · Crown Gold LED') && r.rows.includes('Lamp 2=Black · Raw Jute · Brushed Copper · Crown Gold LED'), JSON.stringify(r.rows));
check('2 lamps in an empty basket: Cast Your Lamp', r.cta === 'Cast Your Lamp');
await submit(); k = await basket();
check('added as one line with Lamp 1 and Lamp 2 configurations', k.url === '/basket' && /Lamp 1/.test(k.text) && /Lamp 2/.test(k.text) && /Raw Jute/.test(k.text) && /Black/.test(k.text), k.text.slice(0, 300).replace(/\n/g, ' | '));

// 4. Request size and Custom values
await fresh(); await open_(); await pick('size', 'lumen-forma-atelier'); r = await route();
check('Atelier: the Request size note', r.note === SIZE, r.note);
await jump(0); await pick('size', 'lumen-forma-studio'); await fill(1, { 'Footing Material': 'Custom Metal Finish' }); r = await route();
check('a Custom value: Reserve with the custom note', r.cta === 'Reserve Your Design' && (await note()) === CUSTOM, await note());
await jump(0); await pick('size', 'lumen-forma-atelier');
check('a Request size and a Custom value: the single combined note', (await note()) === BOTH, await note());
await pick('size', 'lumen-forma-studio'); await more(); await more();
check('a Custom value and 3 lamps: the custom note, then the 3-or-more note', (await note()) === CUSTOM + ' ' + LIMIT, await note());
await ev(`document.querySelector('[data-action="configurator#fewer"]').click()`); await ev(`document.querySelector('[data-action="configurator#fewer"]').click()`);
await jump(0); for (let i = 0; i < 2; i++) await more(); await pick('size', 'lumen-forma-estate');
check('Request size, Custom and 3 lamps: the combined note, then the 3-or-more note (nothing repeated)', (await note()) === BOTH + ' ' + LIMIT, await note());
await jump(3); await pick('Footing Material', 'Brushed Copper'); await jump(0);
check('Request size and 3 lamps: the size note, then the 3-or-more note', (await note()) === SIZE + ' ' + LIMIT, await note());

// 5. The server checks again (page rules bypassed)
const force = async (config, token = true) => { await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100); await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); ${token ? '' : "f.querySelector('[name=_csrf_token]').remove();"} f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify(config))}; f.requestSubmit(); })()`); await sleep(2000); return basket(); };
const lamp = { 'The Pigment': 'White', 'Cable Colour': 'Slate Grey', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED' };
await fresh(); await open_();
k = await force({ size: 'lumen-forma-atelier', quantity: 1, mode: 'all', lamps: [{ ...lamp, 'Bulb Count': 'Dual Array' }] });
check('server: an Atelier sent directly goes to the request route', k.url === '/reserve', k.url);
await open_(); k = await force({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [{ ...lamp, 'Cable Colour': 'Custom Textile / Length' }] });
check('server: a Custom value sent directly goes to the request route', k.url === '/reserve', k.url);
await open_(); k = await force({ size: 'lumen-forma-gallery', quantity: 3, mode: 'all', lamps: [lamp] });
check('server: 3 lamps sent directly go to the request route', k.url === '/reserve', k.url);
await open_(); k = await force({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [{ ...lamp, 'Bulb Style': undefined }] });
check('server: a missing choice is refused', k.url === '/lighting-designs' && /Please complete every choice/.test(k.text + k.flash), k.url);
await open_(); k = await force({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [{ ...lamp, 'Bulb Style': 'Neon Tube' }] });
check('server: an unknown value is refused', k.url === '/lighting-designs');
await open_(); k = await force({ size: 'lumen-forma-gallery', quantity: 11, mode: 'all', lamps: [lamp] });
check('server: more than 10 lamps is refused', k.url === '/lighting-designs');
await open_(); k = await force({ size: 'lumen-forma-gallery', quantity: 2, mode: 'individual', lamps: [lamp] });
check('server: individual with too few lamps is refused', k.url === '/lighting-designs');
await open_(); k = await force({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [lamp] }, false);
check('server: no CSRF token is refused', k.url === '/lighting-designs' && /try again/.test(k.text + k.flash), k.url);
await b.send('Page.navigate', { url: BASE_URL + '/basket' }); await sleep(1200); k = await basket();
check('none of those reached the basket', !/Lumen Forma/i.test(k.text));
await open_(); k = await force({ size: 'lumen-forma-studio', quantity: 2, mode: 'all', lamps: [lamp] });
check('server: a valid design sent directly is added', k.url === '/basket' && /Size\s+Studio/i.test(k.text));
console.log(ok ? 'ALL PASSED' : 'SOME CHECKS FAILED');
b.close();
