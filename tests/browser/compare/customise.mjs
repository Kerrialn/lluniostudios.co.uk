// Prototype vs ours: the Customise All / Individually cards and the lamp tabs
// (with screenshots of both panels in OUT_DIR).
//   PROTOTYPE_DIR=... node tests/browser/compare/customise.mjs 1440 900
import { open, sleep, out, prototypeUrl, BASE_URL } from '../lib.mjs';
const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const pages = { prototype: prototypeUrl(), ours: BASE_URL + '/lighting-designs' };
const b = await open(W, H, 'm74' + W);
const clickText = (t) => b.ev(`(() => { const el = [...document.querySelectorAll('div,span,button')].filter(e => e.textContent.trim() === ${JSON.stringify(t)} && e.getBoundingClientRect().width > 0).pop(); el && el.click(); return !!el; })()`);
const probe = `(() => { const out = {}; const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n; while ((n = w.nextNode())) { const t = n.textContent.trim(); if (!t) continue; const r = document.createRange(); r.selectNodeContents(n); const q = r.getBoundingClientRect(); if (q.width === 0 || (${W} >= 900 && q.left < ${W} * 0.7)) continue; const cs = getComputedStyle(n.parentElement); if (!(t in out)) out[t] = [Math.round(q.left), Math.round(q.top + scrollY), Math.round(q.width), cs.fontSize, cs.color]; } return out; })()`;
const panel = `(() => { let el = [...document.querySelectorAll('h1,h2')].find(h => ['The Scale','The Pigment'].includes(h.textContent.trim()) && h.getBoundingClientRect().width > 0); while (el.parentElement && el.parentElement.getBoundingClientRect().width < ${W} - 5) el = el.parentElement; const r = el.getBoundingClientRect(); return [r.left, r.top + scrollY, r.width, r.height]; })()`;
const res = {};
for (const [name, url] of Object.entries(pages)) {
    await b.send('Page.navigate', { url }); await sleep(2500);
    if (name === 'prototype') { await b.ev(`localStorage.setItem('llunio-site-v4', JSON.stringify({ v: 7, screen: 'product', basket: [] }))`); await b.send('Page.navigate', { url }); }
    await sleep(2500);
    await b.ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);
    // Both open the panel from "Skip to configurator" (Step 7b: scrolling plays the introduction).
    await b.ev("[...document.querySelectorAll('span,button')].find(e => e.textContent.trim() === 'Skip to configurator →')?.click()");
    await sleep(2500);
    await clickText('+'); await sleep(300);
    await clickText('Customise Individually'); await sleep(400);
    res[name] = { s1: await b.ev(probe) };
    let r = await b.ev(panel); let s = await b.send('Page.captureScreenshot', { captureBeyondViewport: true, clip: { x: r[0], y: r[1], width: r[2], height: r[3], scale: 1 } }); (await import('node:fs')).writeFileSync(out(`m74-${name}-${W}-1.png`), Buffer.from(s.result.data, 'base64'));
    await b.ev(`document.querySelector('[title="The Pigment"]').click()`); await sleep(400);
    await clickText('Lamp 2'); await sleep(300);
    res[name].s2 = await b.ev(probe);
    r = await b.ev(panel); s = await b.send('Page.captureScreenshot', { captureBeyondViewport: true, clip: { x: r[0], y: r[1], width: r[2], height: r[3], scale: 1 } }); (await import('node:fs')).writeFileSync(out(`m74-${name}-${W}-2.png`), Buffer.from(s.result.data, 'base64'));
}
b.close();
for (const k of ['s1', 's2']) {
    const p = res.prototype[k], o = res.ours[k];
    for (const t of ['How would you like to configure your lamps?', 'Customise All', 'Apply the same configuration to every lamp.', 'Customise Individually', 'Configure each lamp separately.', 'Lamp 1', 'Lamp 2', 'White', 'The Pigment', 'Purchase route']) {
        if (!p[t] && !o[t]) continue;
        const d = !p[t] ? 'missing in prototype' : !o[t] ? 'missing in ours' : p[t].map((v, i) => v === o[t][i] ? null : `${['x','y','w','size','colour'][i]} ${v}→${o[t][i]}`).filter(Boolean).join(', ');
        console.log(`${k} "${t}": ${d || 'same'}`);
    }
}
