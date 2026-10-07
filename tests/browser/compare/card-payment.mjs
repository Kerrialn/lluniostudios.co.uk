// Prototype vs ours: the Card Payment page (one Gallery lamp, not a gift):
// heading, section labels, every field's label and box, Country, the billing
// tick, and the summary (rows, totals, button, secure line, Terms).
// Expected differences: no "Card details" section yet (Stripe's card box
// comes in 10.3); "DPD Next Day Delivery" for "UK Express Delivery (DPD)",
// "Calculated from postcode" until one is typed (as the prototype's logic).
//   PROTOTYPE_DIR=... node tests/browser/compare/card-payment.mjs 1440 900
import { writeFileSync } from 'node:fs';
import { open, sleep, out, prototypeUrl, BASE_URL } from '../lib.mjs';
const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const b = await open(W, H, 'cmpcard' + W, { mobile: W < 900 });
const probe = `(() => { const r = (e) => { if (!e) return null; const q = e.getBoundingClientRect(); const cs = getComputedStyle(e); return [Math.round(q.left), Math.round(q.top + scrollY), Math.round(q.width), Math.round(q.height), cs.fontSize, cs.lineHeight, cs.color, cs.borderTopColor]; };
  const own = (t, root = document) => [...root.querySelectorAll('h1, div, span, label, a, button')].find(e => [...e.childNodes].some(n => n.nodeType === 3 && n.textContent.trim() === t));
  const o = { h1: r(document.querySelector('h1')) };
  for (const t of ['Contact', 'Delivery address', 'Billing address', 'Order summary', 'Subtotal', 'Total', 'Payment is processed securely by our payment provider. Your card details are never stored by Llunio.', 'Terms & Conditions']) o[t.slice(0, 18)] = r(own(t));
  const country = own('Country'); o.country = r(country); o.countryBox = r(own('United Kingdom'));
  o.billTick = r(own('Same as delivery address'));
  [...document.querySelectorAll('input:not([type=hidden]):not([type=checkbox])')].filter(i => i.getBoundingClientRect().width > 0 && !/card|cvc|expir|security/i.test((i.closest('label')?.textContent || ''))).forEach((i) => { const l = i.closest('label'); const name = (l?.querySelector('span')?.textContent || '').trim(); o['label: ' + name] = r(l?.querySelector('span')); o['field: ' + name] = r(i); });
  const pay = [...document.querySelectorAll('div, button')].filter(e => /^Pay £/.test(e.textContent.trim()) && e.children.length === 0)[0]; o.pay = r(pay);
  return o; })()`;
const shot = async (name) => { const h = await b.ev(`(document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove()), document.documentElement.scrollHeight)`); writeFileSync(out(`card-payment-${name}-${W}.png`), Buffer.from((await b.send('Page.captureScreenshot', { clip: { x: 0, y: 0, width: W, height: h, scale: 1 }, captureBeyondViewport: true })).result.data, 'base64')); };

const proto = prototypeUrl();
const item = { id: 'cmp', sizeIdx: 1, size: 'Gallery (S)', unit: 799, price: '£799', qty: 1, mode: 'all', indiv: false, countActive: false, lamps: [{ finish: 'White', cable: 'Slate Grey', footing: 'Brushed Copper', bulb: 'Crown Gold LED', count: '' }] };
await b.send('Page.navigate', { url: proto }); await sleep(1500);
await b.ev(`localStorage.setItem('llunio-site-v4', JSON.stringify({ v: 7, screen: 'card', basket: [${JSON.stringify(item)}] }))`); await b.send('Page.navigate', { url: proto }); await sleep(3000);
const p = await b.ev(probe); await shot('prototype');

await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');
await b.send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(2000);
const lamp = { 'The Pigment': 'White', 'Cable Colour': 'Slate Grey', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED' };
await b.ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
await b.ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`); await sleep(2500);
await b.send('Page.navigate', { url: BASE_URL + '/checkout' }); await sleep(2500);
const o = await b.ev(probe); await shot('ours');

for (const k of new Set([...Object.keys(p), ...Object.keys(o)])) {
    const a = p[k], c = o[k];
    if (!a || !c) { console.log(k.padEnd(34), 'missing in', !a ? 'prototype' : 'ours'); continue; }
    const d = a.map((v, i) => v === c[i] ? null : `${['x', 'y', 'w', 'h', 'size', 'lh', 'colour', 'border'][i]} ${v}→${c[i]}`).filter(Boolean);
    if (d.length) console.log(k.padEnd(34), d.join(', '));
}
b.close();
