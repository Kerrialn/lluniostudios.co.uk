// Prototype vs ours: the basket summary with "This is a gift" ticked (the
// gift box, the date and its note, Proceed to Payment, Terms). Positions are
// relative to the summary box. Expected differences: ours has no "Express
// Payment" button (decided 7 October 2026), so Proceed to Payment and Terms
// sit higher; the prototype's delivery row says "UK Express Delivery (DPD)",
// ours "DPD Next Day Delivery" (decided).
//   PROTOTYPE_DIR=... node tests/browser/compare/basket-gift.mjs 1440 900
import { writeFileSync } from 'node:fs';
import { open, sleep, out, prototypeUrl, BASE_URL } from '../lib.mjs';
const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const b = await open(W, H, 'cmpgift' + W, { mobile: W < 900 });
const R = `const box = (e) => e.getBoundingClientRect(); const r = (e, o) => { if (!e) return null; const q = box(e); const cs = getComputedStyle(e); return [Math.round(q.left - o.left), Math.round(q.top - o.top), Math.round(q.width), Math.round(q.height), cs.fontSize, cs.lineHeight, cs.color, cs.borderTopColor]; };`;
const protoProbe = `(() => { ${R} const f = (t) => [...document.querySelectorAll('div, span, label')].find(e => [...e.childNodes].some(n => n.nodeType === 3 && n.textContent.trim() === t)); const gift = f('This is a gift'); let aside = gift; while (aside && getComputedStyle(aside).flexBasis !== '320px') aside = aside.parentElement; const o = box(aside);
  return { aside: r(aside, { left: 0, top: 0 }), lead: r(f([...aside.querySelectorAll('*')].map(e => [...e.childNodes].find(n => n.nodeType === 3 && /Hand-cast to order/.test(n.textContent))).find(Boolean)?.textContent.trim()), o), giftRow: r(gift, o), giftBox: r(gift.querySelector('span'), o), tick: r(gift.querySelector('span span'), o), dateLabel: r(f('When does it need to arrive by? *'), o), date: r(aside.querySelector('input[type=date]'), o), note: r([...aside.querySelectorAll('*')].find(e => [...e.childNodes].some(n => n.nodeType === 3 && /Each lamp is made by hand/.test(n.textContent))), o), proceed: r(f('Proceed to Payment'), o), terms: r(f('Terms & Conditions'), o) }; })()`;
const ourProbe = `(() => { ${R} const aside = document.querySelector('main aside'); const o = box(aside); const f = (t) => [...aside.querySelectorAll('*')].find(e => [...e.childNodes].some(n => n.nodeType === 3 && n.textContent.trim() === t)); const gift = f('This is a gift');
  return { aside: r(aside, { left: 0, top: 0 }), lead: r(f([...aside.querySelectorAll('*')].map(e => [...e.childNodes].find(n => n.nodeType === 3 && /Hand-cast to order/.test(n.textContent))).find(Boolean)?.textContent.trim()), o), giftRow: r(gift, o), giftBox: r(gift.querySelector('span[aria-hidden]'), o), tick: r(gift.querySelector('span[aria-hidden] span'), o), dateLabel: r(f('When does it need to arrive by? *'), o), date: r(aside.querySelector('input[type=date]'), o), note: r([...aside.querySelectorAll('*')].find(e => [...e.childNodes].some(n => n.nodeType === 3 && /Each lamp is made by hand/.test(n.textContent))), o), proceed: r(f('Proceed to Payment'), o), terms: r(f('Terms & Conditions'), o) }; })()`;
const shot = async (name) => { const a = await b.ev(`(() => { document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove()); const q = [...document.querySelectorAll('aside, div')].find(e => /This is a gift/.test(e.textContent) && /Proceed to Payment/.test(e.textContent) && e.getBoundingClientRect().width < ${W}); const r = (q.closest('aside') || q).getBoundingClientRect(); return [r.left, r.top + scrollY, r.width, r.height]; })()`); writeFileSync(out(`basket-gift-${name}-${W}.png`), Buffer.from((await b.send('Page.captureScreenshot', { clip: { x: a[0], y: a[1], width: a[2], height: a[3], scale: 1 }, captureBeyondViewport: true })).result.data, 'base64')); };

const proto = prototypeUrl();
const item = { id: 'cmp', sizeIdx: 1, size: 'Gallery (S)', unit: 799, price: '£799', qty: 1, mode: 'all', indiv: false, countActive: false, lamps: [{ finish: 'White', cable: 'Slate Grey', footing: 'Brushed Copper', bulb: 'Crown Gold LED', count: '' }] };
await b.send('Page.navigate', { url: proto }); await sleep(1500);
await b.ev(`localStorage.setItem('llunio-site-v4', JSON.stringify({ v: 7, screen: 'cart', basket: [${JSON.stringify(item)}] }))`); await b.send('Page.navigate', { url: proto }); await sleep(3000);
await b.ev(`[...document.querySelectorAll('[role=checkbox]')].find(e => /This is a gift/.test(e.textContent)).click()`); await sleep(600);
const p = await b.ev(protoProbe); await shot('prototype');

await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');
await b.send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(2000);
const lamp = { 'The Pigment': 'White', 'Cable Colour': 'Slate Grey', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED' };
await b.ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
await b.ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`); await sleep(2500);
await b.ev(`[...document.querySelectorAll('main aside label')].find(e => /This is a gift/.test(e.textContent)).click()`); await sleep(600);
const o = await b.ev(ourProbe); await shot('ours');

for (const k of Object.keys(p)) {
    const a = p[k], c = o[k];
    if (!a || !c) { console.log(k.padEnd(10), 'missing in', !a ? 'prototype' : 'ours'); continue; }
    const d = a.map((v, i) => v === c[i] ? null : `${['x', 'y', 'w', 'h', 'size', 'lh', 'colour', 'border'][i]} ${v}→${c[i]}`).filter(Boolean);
    if (d.length) console.log(k.padEnd(10), d.join(', '));
}
b.close();
