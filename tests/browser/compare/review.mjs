// Prototype vs ours: the review stage, for one lamp or two customised
// individually ("two"), with screenshots in OUT_DIR.
//   PROTOTYPE_DIR=... node tests/browser/compare/review.mjs 1440 900 [one|two]
import { open, sleep, out, prototypeUrl, BASE_URL } from '../lib.mjs';
const [W, H, variant] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900), process.argv[4] || 'one'];
const pages = { prototype: prototypeUrl(), ours: BASE_URL + '/lighting-designs' };
const b = await open(W, H, 'r75' + W);
const clickText = (t) => b.ev(`(() => { const el = [...document.querySelectorAll('div,span,button')].filter(e => e.textContent.trim() === ${JSON.stringify(t)} && e.getBoundingClientRect().width > 0).pop(); el && el.click(); return !!el; })()`);
const probe = `(() => { const out = {}; const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n; while ((n = w.nextNode())) { const t = n.textContent.trim().replace('Bournemouth studio', 'Dorset Studio'); if (!t) continue; const r = document.createRange(); r.selectNodeContents(n); const q = r.getBoundingClientRect(); if (q.width === 0 || (${W} >= 900 && q.left < ${W} * 0.7)) continue; const cs = getComputedStyle(n.parentElement); const k = out[t] ? t + ' #2' : t; out[k] = [Math.round(q.left), Math.round(q.top + scrollY), Math.round(q.width), cs.fontSize, cs.color, cs.fontWeight]; } return out; })()`;
const panel = `(() => { let el = [...document.querySelectorAll('h1,h2')].find(h => /^(The Scale|The Pigment|The Cable|The Footing|The Light|Your Lumen Forma)$/.test(h.textContent.trim()) && h.getBoundingClientRect().width > 0); while (el.parentElement && el.parentElement.getBoundingClientRect().width < ${W} - 5) el = el.parentElement; const r = el.getBoundingClientRect(); return [r.left, r.top + scrollY, r.width, r.height]; })()`;
const res = {};
for (const [name, url] of Object.entries(pages)) {
    await b.send('Page.navigate', { url }); await sleep(2500);
    if (name === 'prototype') { await b.ev(`localStorage.setItem('llunio-site-v4', JSON.stringify({ v: 7, screen: 'product', basket: [] }))`); await b.send('Page.navigate', { url }); }
    await sleep(2500);
    await b.ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);
    await b.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: 5, y: 120, button: 'left', clickCount: 1 });
    await b.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: 5, y: 120, button: 'left', clickCount: 1 });
    await sleep(2500);
    if (variant === 'two') { await clickText('+'); await sleep(200); await clickText('Customise Individually'); await sleep(200); }
    res[name] = { s1: await b.ev(probe) };
    let r = await b.ev(panel); let s = await b.send('Page.captureScreenshot', { captureBeyondViewport: true, clip: { x: r[0], y: r[1], width: r[2], height: r[3], scale: 1 } }); (await import('node:fs')).writeFileSync(out(`r75-${variant}-${name}-${W}-1.png`), Buffer.from(s.result.data, 'base64'));
    const lamps = variant === 'two' ? [0, 1] : [0];
    const pickAll = [['The Pigment', 'White'], ['The Cable', 'Black Braided Cotton'], ['The Footing', 'Brushed Aluminium'], ['The Light', 'XXL Spiral Filament']];
    for (const [stage, value] of pickAll) {
        await b.ev(`document.querySelector('[title="${stage}"]').click()`); await sleep(250);
        for (const lamp of lamps) {
            if (variant === 'two') { await clickText('Lamp ' + (lamp + 1)); await sleep(150); }
            if (name === 'ours' || stage === 'The Pigment') { await clickText(lamp === 1 && stage === 'The Pigment' ? 'Grey' : value); await sleep(150); }
        }
    }
    await b.ev(`document.querySelector('[title="Your Lumen Forma"]').click()`); await sleep(500);
    console.log(name, 'reached', await b.ev(`[...document.querySelectorAll('h1,h2')].filter(h => h.getBoundingClientRect().width > 0).map(h => h.textContent.trim()).join('/')`));
    res[name].s6 = await b.ev(probe);
    r = await b.ev(panel); s = await b.send('Page.captureScreenshot', { captureBeyondViewport: true, clip: { x: r[0], y: r[1], width: r[2], height: r[3], scale: 1 } }); (await import('node:fs')).writeFileSync(out(`r75-${variant}-${name}-${W}-6.png`), Buffer.from(s.result.data, 'base64'));
}
b.close();
let n = 0;
for (const k of ['s1', 's6']) {
    const p = res.prototype[k], o = res.ours[k];
    for (const t of Object.keys(p)) {
        const tt = t === 'Finish Colour' ? 'The Pigment' : t;
        if (!o[tt]) { console.log(`${k} missing in ours: ${t.slice(0, 70)}`); n++; continue; }
        const d = p[t].map((v, i) => v === o[tt][i] ? null : `${['x','y','w','size','colour','weight'][i]} ${v}→${o[tt][i]}`).filter(Boolean);
        if (d.length && t !== 'Finish Colour') { console.log(`${k} "${t.slice(0, 50)}": ${d.join(', ')}`); n++; }
    }
    for (const t of Object.keys(o)) if (!p[t] && !(t === 'The Pigment' && p['Finish Colour']) && !(t.startsWith('The Pigment') && p['Finish Colour'])) console.log(`${k} only in ours: ${t.slice(0, 70)}`);
}
console.log('differences:', n);
