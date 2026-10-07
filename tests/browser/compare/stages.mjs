// Prototype vs ours, numerically: the position, size, weight, spacing and colour
// of every piece of text in the options panel on each stage.
//   PROTOTYPE_DIR=... node tests/browser/compare/stages.mjs 1440 900
import { open, sleep, out, prototypeUrl, BASE_URL } from '../lib.mjs';
const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const stages = ['The Scale', 'The Pigment', 'The Cable', 'The Light'];
const pages = { prototype: prototypeUrl(), ours: BASE_URL + '/lighting-designs' };
const b = await open(W, H, 'pos' + W);
const res = {};
const probe = `(() => { const out = {}; const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n; while ((n = w.nextNode())) { const t = n.textContent.trim().replace(/Bournemouth studio/, 'Dorset Studio'); if (!t) continue; const r = document.createRange(); r.selectNodeContents(n); const q = r.getBoundingClientRect(); if (q.width === 0 || q.left < ${W} * 0.6 && ${W} >= 900) continue; const cs = getComputedStyle(n.parentElement); if (!(t in out)) out[t] = [Math.round(q.left), Math.round(q.top + scrollY), Math.round(q.width), cs.fontSize, cs.fontWeight, cs.letterSpacing, cs.color]; } return out; })()`;
for (const [name, url] of Object.entries(pages)) {
    await b.send('Page.navigate', { url }); await sleep(2500);
    if (name === 'prototype') { await b.ev(`localStorage.setItem('llunio-site-v4', JSON.stringify({ v: 7, screen: 'product', basket: [] }))`); await b.send('Page.navigate', { url }); }
    await sleep(2500);
    await b.ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);
    await b.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: 5, y: H - 5, button: 'left', clickCount: 1 });
    await b.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: 5, y: H - 5, button: 'left', clickCount: 1 });
    await sleep(2500);
    res[name] = {};
    for (const st of stages) {
        await b.ev(`(() => { const el = [...document.querySelectorAll('[title="${st}"]')].find(e => e.getBoundingClientRect().width > 0); el && el.click(); })()`); await sleep(300);
        if (st === 'The Light') { /* noop */ }
        res[name][st] = await b.ev(probe);
        const pickText = (t) => b.ev(`(() => { const el = [...document.querySelectorAll('div,button')].filter(e => e.textContent.trim() === '${t}' && e.getBoundingClientRect().width > 0).pop(); el && el.click(); })()`);
        if (st === 'The Pigment') { await pickText('White'); await sleep(300); }
        // Ours starts with everything empty; choose the prototype's starting values so both can move on.
        if (name === 'ours' && st === 'The Cable') { await pickText('Black Braided Cotton'); await b.ev(`document.querySelector('[title="The Footing"]').click()`); await sleep(200); await pickText('Brushed Aluminium'); await b.ev(`document.querySelector('[title="The Cable"]').click()`); await sleep(200); }
        if (name === 'ours' && st === 'The Light') { await pickText('XXL Spiral Filament'); await sleep(200); }
    }
}
b.close();
let diffs = 0;
for (const st of stages) {
    const p = res.prototype[st], o = res.ours[st];
    for (const t of Object.keys(p)) {
        const tt = t === 'Finish Colour' ? 'The Pigment' : t;
        if (!o[tt]) { console.log(`${st}: missing in ours: ${t}`); diffs++; continue; }
        const d = p[t].map((v, i) => v === o[tt][i] ? null : `${['x','y','w','size','weight','ls','colour'][i]} ${v}→${o[tt][i]}`).filter(Boolean);
        if (t === 'Finish Colour') continue;
        if (d.length) { console.log(`${st}: "${t.slice(0, 40)}" ${d.join(', ')}`); diffs++; }
    }
    for (const t of Object.keys(o)) if (!p[t] && !(t === 'The Pigment' && p['Finish Colour'])) console.log(`${st}: only in ours: ${t.slice(0, 60)}`);
}
console.log('differences:', diffs);
