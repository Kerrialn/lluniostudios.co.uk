// 7.6: "Edit configuration" from the basket, and links to the configurator.
import { open, sleep, BASE_URL } from './lib.mjs';
const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const b = await open(W, H, 'cfgedit' + W);
let ok = true;
const check = (label, pass, detail = '') => { if (!pass) ok = false; console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`); };
const ev = b.ev;
await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');
const go = async (url, wait = 1800) => { await b.send('Page.navigate', { url }); await sleep(wait); };
const reveal = async () => { await b.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 }); await sleep(1300); };
const pick = (g, v) => ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
const jump = (i) => ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
const tab = (i) => ev(`document.querySelectorAll('.ll-cfg-unit')[${i}].click()`);
const more = () => ev(`document.querySelector('[data-action="configurator#more"]').click()`);
const fewer = () => ev(`document.querySelector('[data-action="configurator#fewer"]').click()`);
const cast = async () => { await ev(`document.querySelector('[data-configurator-target=cta]').click()`); await sleep(2000); };
const state = () => ev(`({ url: location.pathname + location.search, revealed: document.querySelector('[data-controller~=configurator]').classList.contains('is-revealed'),
  num: document.querySelector('[data-configurator-target=stageNumber]').textContent.slice(0, 2), editing: /Editing a lamp in your basket/.test(document.body.innerText),
  rows: [...document.querySelectorAll('[data-configurator-target=review] button')].map(r => r.children[0].textContent + '=' + r.children[1].textContent),
  cta: document.querySelector('[data-configurator-target=cta]').textContent, tabs: document.querySelector('[data-configurator-target=units]').hidden ? null : [...document.querySelectorAll('.ll-cfg-unit')].map(t => t.textContent).join(',') })`);
const basket = () => ev(`({ url: location.pathname, text: document.querySelector('main').innerText, edits: [...document.querySelectorAll('a')].filter(a => a.textContent.trim() === 'Edit configuration').map(a => a.getAttribute('href')) })`);
const lineCount = (t) => (t.match(/Size\s+(Studio|Gallery|Atelier|Estate|Monument)\b/gi) || []).length;

// Add a Gallery lamp
await go(BASE_URL + '/lighting-designs'); await reveal();
for (const [s, g, v] of [[1, 'The Pigment', 'White'], [2, 'Cable Colour', 'Slate Grey'], [3, 'Footing Material', 'Brushed Copper'], [4, 'Bulb Style', 'Crown Gold LED']]) { await jump(s); await pick(g, v); }
await jump(5); await cast();
let k = await basket();
check('basket line has "Edit configuration" pointing at the configurator', k.edits.length === 1 && /^\/lighting-designs\?edit=/.test(k.edits[0]), JSON.stringify(k.edits));
const editUrl = BASE_URL + '' + k.edits[0];

// Open it
await go(editUrl, 2500); let s = await state();
check('opens already revealed, on the review, with "Editing a lamp in your basket"', s.revealed && s.num === '06' && s.editing, JSON.stringify(s));
check('the line\'s design is loaded', JSON.stringify(s.rows) === JSON.stringify(['Size=Gallery', 'Price=£799', 'Quantity=1', 'The Pigment=White', 'Cable Colour=Slate Grey', 'Footing Material=Brushed Copper', 'Bulb Style=Crown Gold LED']), JSON.stringify(s.rows));
check('its own lamp doesn\'t count against the limit: Cast Your Lamp', s.cta === 'Cast Your Lamp');

// Change it: Studio, 2 lamps, Black
await jump(0); await pick('size', 'lumen-forma-studio'); await more(); await jump(1); await pick('The Pigment', 'Black'); await jump(5);
s = await state();
check('2 lamps replacing the 1 in the basket: still Cast Your Lamp', s.cta === 'Cast Your Lamp', s.cta);
await cast(); k = await basket();
check('saving updates the same line (still one line)', k.url === '/basket' && lineCount(k.text) === 1 && /Size\s+Studio/i.test(k.text) && /Black/.test(k.text) && !/White/.test(k.text), k.text.slice(0, 200).replace(/\n/g, ' | '));
check('no "Your basket has been updated" banner', !/Your basket has been updated/.test(await ev('document.body.innerText')) && (await ev(`document.querySelectorAll('[data-controller=toast]').length`)) === 0);
check('the nav basket count shows the 2 lamps', (await ev(`/Basket \\(2\\)/i.test(document.querySelector('#navbar').innerText) || !!document.querySelector('#navbar a[aria-label="Basket, 2 items"]')`)), await ev(`document.querySelector('#navbar').innerText.replace(/\n/g, ' ')`));
check('the edit link is unchanged (same line)', k.edits[0] === editUrl.replace(BASE_URL + '', ''));

// Customise it individually
await go(editUrl, 2500); s = await state();
check('reopened with Studio, 2 lamps, Black', s.rows.includes('Size=Studio') && s.rows.includes('Quantity=2') && s.rows.includes('The Pigment=Black'), JSON.stringify(s.rows));
await jump(0); await ev(`document.querySelector('.ll-cfg-mode[data-mode=individual]').click()`); await jump(2); await tab(1); await pick('Cable Colour', 'Raw Jute'); await jump(5);
await cast(); k = await basket();
check('saved as per-lamp configurations on the same line', lineCount(k.text) === 1 && /Lamp 1/.test(k.text) && /Lamp 2/.test(k.text) && /Raw Jute/i.test(k.text));
check('a per-lamp line also has "Edit configuration"', k.edits.length === 1);
await go(editUrl, 2500); s = await state();
check('reopened customised individually, each lamp loaded', s.rows.includes('Configuration=Customise Individually') && s.rows.includes('Lamp 1=Black · Slate Grey · Brushed Copper · Crown Gold LED') && s.rows.includes('Lamp 2=Black · Raw Jute · Brushed Copper · Crown Gold LED'), JSON.stringify(s.rows));
await jump(2); s = await state();
check('lamp tabs show on the option stages', s.tabs === 'Lamp 1,Lamp 2');

// Back to one configuration for all lamps, then 1 lamp
await jump(0); await ev(`document.querySelector('.ll-cfg-mode[data-mode=all]').click()`); await jump(5); s = await state();
check('back to Customise All: starts from Lamp 1\'s choices', s.rows.includes('Configuration=Customise All') && s.rows.includes('The Pigment=Black') && s.rows.includes('Cable Colour=Slate Grey'), JSON.stringify(s.rows));
await jump(1); await pick('The Pigment', 'Grey'); await jump(2); await pick('Cable Colour', 'Orange'); await jump(3); await pick('Footing Material', 'Polished Aluminium'); await jump(4); await pick('Bulb Style', 'Smoked Glass LED'); await jump(0); await fewer(); await jump(5);
await cast(); k = await basket();
check('saved back as one configuration for 1 lamp', lineCount(k.text) === 1 && !/Lamp 2/.test(k.text) && /Orange/i.test(k.text) && /Smoked Glass LED/i.test(k.text));

// Edit into a Request size: Reserve, line untouched
await go(editUrl, 2500); await jump(0); await pick('size', 'lumen-forma-atelier'); await jump(4); await pick('Bulb Count', 'Single Array'); await jump(5); s = await state();
check('editing into Atelier: Reserve Your Design', s.cta === 'Reserve Your Design');
await cast();
check('goes to the request route', (await ev('location.pathname')) === '/reserve');
await go(BASE_URL + '/basket'); k = await basket();
check('the basket line is unchanged', lineCount(k.text) === 1 && /Size\s+Studio/i.test(k.text) && /Orange/i.test(k.text));

// A line that isn't in this basket
await go(BASE_URL + '/lighting-designs?edit=00000000-0000-0000-0000-000000000000', 2500); s = await state();
check('an unknown line opens the normal configurator (unrevealed, nothing loaded)', !s.revealed && !s.editing && s.num === '01');

// Links
await go(BASE_URL + '/');
check('homepage "Explore our lighting designs" goes to the configurator', (await ev(`[...document.querySelectorAll('a')].find(a => /Explore our lighting designs/i.test(a.textContent)).getAttribute('href')`)) === '/lighting-designs');
await b.send('Network.clearBrowserCookies'); await go(BASE_URL + '/basket');
check('empty basket "Explore our lighting designs" goes to the configurator', (await ev(`[...document.querySelectorAll('main a')].find(a => /Explore our lighting designs/i.test(a.textContent))?.getAttribute('href')`)) === '/lighting-designs');
console.log(ok ? 'ALL PASSED' : 'SOME CHECKS FAILED');
b.close();
