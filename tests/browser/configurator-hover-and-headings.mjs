// Option headings (none except on The Light), the hand pointer and hover
// feedback in the configurator, and the pointer on buttons elsewhere.
import { open, sleep, BASE_URL } from './lib.mjs';
const b = await open(1440, 900, 'cfghover');
let ok = true;
const check = (label, pass, detail = '') => { if (!pass) ok = false; console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`); };
const ev = b.ev;
await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');
await b.send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(1800);
await b.ev("document.querySelector('.ll-story-skip').click()"); await sleep(1500);
const jump = (i) => ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
// Headings
const headings = await ev(`[...document.querySelectorAll('[data-configurator-target=group]')].map(g => g.dataset.group + '=' + (g.querySelector('[id^=cfg-group-]')?.textContent || '(none, aria-label ' + g.getAttribute('aria-label') + ')'))`);
check('no heading under The Pigment, The Cable or The Footing; each keeps its name for screen readers', ['The Pigment', 'Cable Colour', 'Footing Material'].every(n => headings.includes(`${n}=(none, aria-label ${n})`)), JSON.stringify(headings));
check('The Light keeps "Bulb Style" and "Bulb Count"', headings.includes('Bulb Style=Bulb Style') && headings.includes('Bulb Count=Bulb Count'));
const size = await ev(`(() => { const g = document.querySelector('[role=group][aria-label=Size]'); return g ? { label: g.getAttribute('aria-label'), heading: [...g.querySelectorAll('span')].some(s => s.textContent.trim() === 'Size') } : null; })()`);
check('no "Size" heading under The Scale; the list keeps its name for screen readers', size && size.label === 'Size' && !size.heading, JSON.stringify(size));

// Hover: move the mouse onto an element and read its cursor and colour/opacity/border before and after.
const hover = async (sel, prop) => {
    const r = await ev(`(() => { const e = [...document.querySelectorAll(${JSON.stringify(sel)})].find(x => x.getBoundingClientRect().width > 0 && x.getBoundingClientRect().top >= 0); if (!e) return null; e.scrollIntoView({ block: 'center' }); const q = e.getBoundingClientRect(); window.__h = e; return [q.left + q.width / 2, q.top + q.height / 2]; })()`);
    if (!r) return null;
    await b.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 2, y: 2 }); await sleep(400);
    const before = await ev(`getComputedStyle(window.__h${prop[1] ? '.querySelector(' + JSON.stringify(prop[1]) + ')' : ''}).${prop[0]}`);
    await b.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: r[0], y: r[1] }); await sleep(450);
    const after = await ev(`getComputedStyle(window.__h${prop[1] ? '.querySelector(' + JSON.stringify(prop[1]) + ')' : ''}).${prop[0]}`);
    const cursor = await ev(`getComputedStyle(window.__h).cursor`);
    return { before, after, cursor };
};
const expect = async (label, sel, prop) => { const h = await hover(sel, prop); check(`${label}: hand pointer and a hover change`, h && h.cursor === 'pointer' && h.before !== h.after, JSON.stringify(h)); };
await expect('stage numbers', '.ll-cfg-step:not(.is-current)', ['color']);
await expect('size options', '.ll-cfg-opt:not(.is-selected)', ['color']);
await expect('quantity +', '[aria-label="More lamps"]', ['opacity']);
await expect('quantity −', '[aria-label="Fewer lamps"]', ['opacity']);
await expect('Next', '[data-configurator-target=next]', ['opacity']);
await ev(`document.querySelector('[aria-label="More lamps"]').click()`);
await expect('Customise cards', '.ll-cfg-mode:not(.is-selected)', ['borderTopColor']);
await ev(`document.querySelector('.ll-cfg-mode[data-mode=individual]').click()`); await jump(1);
await expect('lamp tabs', '.ll-cfg-unit:not(.is-current)', ['color']);
await expect('Back', '[data-configurator-target=back]', ['color']);
await ev(`document.querySelector('.ll-cfg-mode[data-mode=all]')?.click()`);
await jump(0); await ev(`document.querySelector('[aria-label="Fewer lamps"]').click()`);
await expect('gallery arrow →', '[data-configurator-target=gallery]:not([hidden]) [aria-label="Next photo"]', ['opacity']);
await expect('gallery arrow ←', '[data-configurator-target=gallery]:not([hidden]) [aria-label="Previous photo"]', ['opacity']);
for (const [s, g, v] of [[1, 'The Pigment', 'White'], [2, 'Cable Colour', 'Raw Jute'], [3, 'Footing Material', 'Brushed Copper'], [4, 'Bulb Style', 'Crown Gold LED']]) { await jump(s); await ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`); }
await jump(5);
await expect('review rows', '.ll-cfg-review-row', ['color', '.ll-cfg-review-key']);
await expect('Cast Your Lamp', '[data-configurator-target=cta]', ['opacity']);

// Elsewhere on the site
const pointer = async (url, sel, label) => { await b.send('Page.navigate', { url }); await sleep(1500); const c = await ev(`(() => { const e = document.querySelector(${JSON.stringify(sel)}); return e ? getComputedStyle(e).cursor : null; })()`); check(`${label}: hand pointer`, c === 'pointer', c); };
await pointer(BASE_URL + '/workshop', '[data-action="slideshow#next"]', 'Workshop slideshow Next');
await b.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
await pointer(BASE_URL + '/', '[data-controller~="mobile-menu"] button, button[aria-controls]', 'Mobile menu button');
console.log(ok ? 'ALL PASSED' : 'SOME CHECKS FAILED');
b.close();
