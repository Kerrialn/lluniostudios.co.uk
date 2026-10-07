// Prototype vs ours: Bespoke Commissions (hero title before and after the
// reveal, the tabs, then each form's labels, controls, help text and button).
// Prints only differences, as [x, y, width, height, font size, line height,
// colour, letter spacing]. Expected differences: "*" added to the Home form's
// required labels, and the file box text (the upload decision).
//   PROTOTYPE_DIR=... node tests/browser/compare/commissions.mjs 1440 900
import { writeFileSync } from 'node:fs';
import { open, sleep, out, prototypeUrl, BASE_URL } from '../lib.mjs';
const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const b = await open(W, H, 'cmpos' + W, { mobile: W < 900 });
const R = `const r = (e) => { if (!e) return null; const q = e.getBoundingClientRect(); const cs = getComputedStyle(e); return [Math.round(q.left), Math.round(q.top + scrollY), Math.round(q.width), Math.round(q.height), cs.fontSize, cs.lineHeight, cs.color, cs.letterSpacing]; };`;
const protoProbe = `(() => { ${R} const o = {}; const h1 = document.querySelector('h1'); [...h1.children].forEach((s, i) => o['line' + i] = r(s));
  o.cue = r([...document.querySelectorAll('div')].find(d => d.textContent.trim() === 'Scroll to begin')); o.hero = r(h1.closest('div').parentElement);
  const tabs = [...document.querySelectorAll('span')].filter(d => /^For Your (Home|Project)$/.test(d.textContent.trim())).map(x => x.parentElement); tabs.forEach((t, i) => o['tab' + i] = r(t));
  [...document.querySelectorAll('label')].filter(l => l.getBoundingClientRect().width > 0).forEach((l, i) => { o['label' + i] = r(l.children[0]); o['field' + i] = r(l.children[1]); const help = [...l.children].find((x, k) => k > 1 && x.tagName === 'SPAN'); if (help) o['help' + i] = r(help); });
  const btn = [...document.querySelectorAll('span,div')].filter(d => /^Request (Your Custom Design|Custom Collaboration)$/.test(d.textContent.trim())).map(x => x.tagName === 'SPAN' ? x.parentElement : x)[0]; if (btn) o.button = r(btn); return o; })()`;
const ourProbe = `(() => { ${R} const o = {}; const h1 = document.querySelector('h1'); [...h1.children].forEach((s, i) => o['line' + i] = r(s));
  o.cue = r(document.querySelector('.ll-reveal-cue')); o.hero = r(document.querySelector('.ll-reveal-hero'));
  document.querySelectorAll('[data-commission-target=tab]').forEach((t, i) => o['tab' + i] = r(t));
  const panel = document.querySelector('[data-commission-target=panel]:not([hidden])');
  if (panel) { [...panel.querySelector('form > div').children].filter(f => f.tagName === 'DIV').forEach((f, i) => { o['label' + i] = r(f.children[0]); const c = f.querySelector('input:not([type=hidden]):not(.sr-only), textarea, select, [role=radiogroup], label[for][class*="py-7"]'); o['field' + i] = r(c); const help = [...f.children].find(x => x.tagName === 'SPAN' && x.classList.contains('text-ll-mute') && x !== f.children[0]); if (help) o['help' + i] = r(help); });
    o.button = r(panel.querySelector('button[type=submit]')); } return o; })()`;
const key = { type: 'keyDown', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 };
const clickTab = (label) => b.ev(`[...document.querySelectorAll('span,button')].find(d => d.textContent.trim() === '${label}' && d.children.length === 0).click()`);
async function capture(probe, name) {
    const res = {};
    res.unrevealed = await b.ev(probe);
    await b.send('Input.dispatchKeyEvent', key); await sleep(2000); await b.ev('scrollTo(0, 0)'); await sleep(300);
    res.revealed = await b.ev(probe);
    await clickTab('For Your Home'); await sleep(900); res.home = await b.ev(probe);
    writeFileSync(out(`commissions-${name}-${W}-home.png`), Buffer.from((await b.send('Page.captureScreenshot', { captureBeyondViewport: true, clip: { x: 0, y: 0, width: W, height: await b.ev('document.documentElement.scrollHeight'), scale: 1 } })).result.data, 'base64'));
    await clickTab('For Your Project'); await sleep(900); res.project = await b.ev(probe);
    writeFileSync(out(`commissions-${name}-${W}-project.png`), Buffer.from((await b.send('Page.captureScreenshot', { captureBeyondViewport: true, clip: { x: 0, y: 0, width: W, height: await b.ev('document.documentElement.scrollHeight'), scale: 1 } })).result.data, 'base64'));
    return res;
}
const proto = prototypeUrl();
await b.send('Page.navigate', { url: proto }); await sleep(1500);
await b.ev(`localStorage.setItem('llunio-site-v4', JSON.stringify({ v: 7, screen: 'commissions', basket: [] }))`); await b.send('Page.navigate', { url: proto }); await sleep(4000);
const p = await capture(protoProbe, 'prototype');
await b.send('Page.navigate', { url: BASE_URL + '/bespoke-commissions' }); await sleep(2500);
const o = await capture(ourProbe, 'ours');
for (const state of Object.keys(p)) {
    console.log(`-- ${state}`);
    for (const k of new Set([...Object.keys(p[state]), ...Object.keys(o[state])])) {
        const a = p[state][k], c = o[state][k];
        if (!a || !c) { console.log(k.padEnd(9), 'missing in', !a ? 'prototype' : 'ours'); continue; }
        const d = a.map((v, i) => v === c[i] ? null : `${['x', 'y', 'w', 'h', 'size', 'lh', 'colour', 'ls'][i]} ${v}→${c[i]}`).filter(Boolean);
        if (d.length) console.log(k.padEnd(9), d.join(', '));
    }
}
b.close();
