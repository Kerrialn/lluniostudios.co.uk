// Prototype vs ours: the Reserve Your Design page layout (fields, button,
// summary card). The labelN rows always differ: for ours the probe measures
// the hint text inside the label, for the prototype the whole label; compare
// the fieldN rows, which are the inputs themselves.
//   PROTOTYPE_DIR=... node tests/browser/compare/reserve.mjs 1440 900
import { open, sleep, out, prototypeUrl, BASE_URL } from '../lib.mjs';
const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const b = await open(W, H, 'rpos' + W);
const probe = `(() => { const r = (e) => { if (!e) return null; const q = e.getBoundingClientRect(); const cs = getComputedStyle(e); return [Math.round(q.left), Math.round(q.top + scrollY), Math.round(q.width), Math.round(q.height), cs.fontSize, cs.lineHeight, cs.fontFamily.split(',')[0]]; };
  const labels = [...document.querySelectorAll('label')].filter(l => l.getBoundingClientRect().width > 0);
  const out = { h1: r(document.querySelector('h1')), intro: r(document.querySelector('h1').nextElementSibling) };
  labels.forEach((l, i) => { const ctl = l.querySelector('input,textarea') || document.getElementById(l.htmlFor); out['label' + i] = r(l.querySelector('span') || l); out['field' + i] = r(ctl); });
  const btn = [...document.querySelectorAll('div,button')].filter(e => e.textContent.trim() === 'Reserve Your Design' && e.getBoundingClientRect().height > 30).pop(); out.button = r(btn); out.sub = r(btn.parentElement.tagName === 'FORM' ? btn.nextElementSibling : btn.nextElementSibling);
  const yc = [...document.querySelectorAll('div')].find(e => e.textContent.trim() === 'Your configuration'); out.card = r(yc.parentElement); out.img = r(yc.parentElement.querySelector('img')); out.yourConfig = r(yc);
  const rows = [...yc.parentElement.querySelectorAll('span')].filter(s => s.textContent.trim() === 'Edition'); out.rowEdition = r(rows[0]?.parentElement);
  return out; })()`;
const proto = prototypeUrl();
await b.send('Page.navigate', { url: proto }); await sleep(1500);
await b.ev(`localStorage.setItem('llunio-site-v4', JSON.stringify({ v: 7, screen: 'reserve', basket: [] }))`); await b.send('Page.navigate', { url: proto }); await sleep(2500);
const p = await b.ev(probe);
await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');
await b.send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(1800);
await b.ev("document.querySelector('.ll-story-skip').click()"); await sleep(1300);
const pick = (g, v) => b.ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
const jump = (i) => b.ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
for (let i = 0; i < 2; i++) await b.ev(`document.querySelector('[aria-label="More lamps"]').click()`);
for (const [s, g, v] of [[1, 'The Pigment', 'White'], [2, 'Cable Colour', 'Black Braided Cotton'], [3, 'Footing Material', 'Brushed Aluminium'], [4, 'Bulb Style', 'XXL Spiral Filament']]) { await jump(s); await pick(g, v); }
await jump(5); await b.ev(`document.querySelector('[data-configurator-target=cta]').click()`); await sleep(2500);
const o = await b.ev(probe);
for (const k of Object.keys(p)) { const a = p[k], c = o[k]; if (!a || !c) { console.log(k, 'missing', !a ? 'proto' : 'ours'); continue; } const d = a.map((v, i) => v === c[i] ? null : `${['x', 'y', 'w', 'h', 'size', 'lh', 'font'][i]} ${v}→${c[i]}`).filter(Boolean); if (d.length) console.log(k.padEnd(10), d.join(', ')); }
b.close();
