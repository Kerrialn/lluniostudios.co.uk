// The line under a Custom choice's description box (one combined line on
// every stage with a Custom value, 10 October 2026), and the purchase route
// note on the review.
import { open, sleep, out, BASE_URL } from './lib.mjs';
const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const b = await open(W, H, 'cfgcustom' + W);
let ok = true;
const check = (label, pass, detail = '') => { if (!pass) ok = false; console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`); };
const ev = b.ev;
const LINE = 'You can upload reference photos when you reserve your design. Custom options are developed with you and quoted individually.';
const CUSTOM = 'Hand cast to order. Reserve now to secure your casting slot. Custom options are developed with you and quoted individually.';
const LIMIT = 'Online checkout takes up to two lamps per order, so we can keep to our casting schedule. Larger orders are discussed and quoted for individually.';
await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');
await b.send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(1800);
await b.ev("document.querySelector('.ll-story-skip').click()"); await sleep(1300);
const pick = (g, v) => ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
const jump = (i) => ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
const tab = (i) => ev(`document.querySelectorAll('.ll-cfg-unit')[${i}].click()`);
const more = () => ev(`document.querySelector('[data-action="configurator#more"]').click()`);
// A Custom choice needs its description (10 October 2026).
const describe = (g, text = 'A description.') => ev(`(() => { const t = document.querySelector('[data-configurator-target=describe][data-group="${g}"] textarea'); t.value = ${JSON.stringify(text)}; t.dispatchEvent(new Event('input', { bubbles: true })); })()`);
const fewer = () => ev(`document.querySelector('[data-action="configurator#fewer"]').click()`);
// The line is the last plain line in each description box; nothing else on the stage says "quoted individually".
const lines = () => ev(`[...document.querySelectorAll('[data-configurator-target=describe]')].filter(e => !e.hidden && e.getBoundingClientRect().height > 0).map(e => e.dataset.group + ': ' + e.querySelector(':scope > span:not([data-configurator-target]):last-of-type').textContent.trim())`);
const repeated = () => ev(`(() => { const s = [...document.querySelectorAll('[data-configurator-target=stage]')].find(e => !e.hidden); return (s.innerText.match(/quoted individually/g) || []).length; })()`);
const notes = () => ev(`[...document.querySelectorAll('[data-configurator-target=routeNote]')].map(e => e.textContent.trim())`);
const reviewNote = () => ev(`(() => { const s = document.querySelectorAll('[data-configurator-target=stage]')[5]; const n = s.querySelector('[data-configurator-target=routeNote]'); const cta = s.querySelector('[data-configurator-target=cta]'); const route = n.closest('[data-configurator-target=route]'); return { note: n.textContent.trim(), visible: n.getBoundingClientRect().height > 0, underButton: route.getBoundingClientRect().top >= cta.getBoundingClientRect().bottom && route.getBoundingClientRect().top - cta.getBoundingClientRect().bottom < 30, rule: getComputedStyle(route).borderLeftColor, cta: cta.textContent }; })()`);

check('the line is under the box on each of the four stages with a Custom value (not Bulb Count)', JSON.stringify(await ev(`[...document.querySelectorAll('[data-configurator-target=describe]')].map(e => [e.dataset.group, e.querySelector(':scope > span:not([data-configurator-target]):last-of-type').textContent.trim()])`)) === JSON.stringify(['The Pigment', 'Cable Colour', 'Footing Material', 'Bulb Style'].map((g) => [g, LINE])));
for (const [stage, group, custom, other] of [[1, 'The Pigment', 'Custom', 'Grey'], [2, 'Cable Colour', 'Custom Textile / Length', 'Raw Jute'], [3, 'Footing Material', 'Custom Metal Finish', 'Polished Copper'], [4, 'Bulb Style', 'Custom Specification', 'Clear Vintage Edison']]) {
    await jump(stage);
    check(`${group}: no line before choosing`, (await lines()).length === 0);
    await pick(group, custom);
    check(`${group}: choosing "${custom}" shows the one combined line at once, under the box, not repeated`, JSON.stringify(await lines()) === JSON.stringify([`${group}: ${LINE}`]) && (await repeated()) === 1, JSON.stringify([await lines(), await repeated()]));
    await pick(group, other);
    check(`${group}: choosing "${other}" hides it`, (await lines()).length === 0);
}
const lineStyle = await ev(`(() => { const l = document.querySelector('[data-configurator-target=describe] > span:not([data-configurator-target]):last-of-type'); const w = document.querySelector('[data-configurator-target=warning]'); const a = getComputedStyle(l), c = getComputedStyle(w); return a.fontSize === '12px' && a.fontSize === c.fontSize && a.color === c.color; })()`);
check('the line is small and grey, like the other small notes', lineStyle);

// Individually: follows the lamp being edited
await jump(0); await more(); await ev(`document.querySelector('.ll-cfg-mode[data-mode=individual]').click()`);
await jump(3); await tab(1); await pick('Footing Material', 'Custom Metal Finish');
check('Lamp 2 with a Custom footing shows the line', (await lines()).length === 1);
await tab(0);
check('Lamp 1 (not Custom) does not', (await lines()).length === 0, JSON.stringify(await lines()));
await tab(1);
check('back on Lamp 2 it shows again', (await lines()).length === 1);
await pick('Footing Material', 'Brushed Copper'); await jump(0); await ev(`document.querySelector('.ll-cfg-mode[data-mode=all]').click()`); await fewer();

// Review note under the button, same as The Scale
await jump(5); let r = await reviewNote();
check('review: "Direct order and checkout." directly under Cast Your Lamp', r.cta === 'Cast Your Lamp' && r.note === 'Direct order and checkout.' && r.visible && r.underButton, JSON.stringify(r));
await jump(1); await pick('The Pigment', 'Custom'); await describe('The Pigment'); await jump(5); r = await reviewNote();
check('review with a Custom choice: Reserve and the custom note, rule in cream', r.cta === 'Reserve Your Design' && r.note === CUSTOM && r.rule === 'rgb(236, 227, 211)', JSON.stringify(r));
// Since 7b.4 the larger sizes have their own step (larger-piece.mjs checks its size note).
await jump(0); await more(); await more(); await jump(5); r = await reviewNote();
check('review, Custom, 3 lamps: the custom note, then the 3-or-more note', r.note === CUSTOM + ' ' + LIMIT, r.note);
await jump(1); await pick('The Pigment', 'White'); await jump(5); r = await reviewNote();
check('review, 3 lamps: the 3-or-more note', r.note === LIMIT, r.note);
const both = await notes();
check('The Scale shows the same note', both.length === 2 && both[0] === both[1], JSON.stringify(both));
// screenshots
await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);
const panel = async (f) => { const q = await ev(`(() => { const r = document.querySelector('.ll-cfg-panel').getBoundingClientRect(); return [r.left, r.top + scrollY, r.width, r.height]; })()`); const s = await b.send('Page.captureScreenshot', { captureBeyondViewport: true, clip: { x: q[0], y: q[1], width: q[2], height: q[3], scale: 1 } }); (await import('node:fs')).writeFileSync(f, Buffer.from(s.result.data, 'base64')); };
await panel(out(`custom-review-${W}.png`));
await jump(1); await pick('The Pigment', 'Custom'); await panel(out(`custom-pigment-${W}.png`));
console.log(ok ? 'ALL PASSED' : 'SOME CHECKS FAILED');
b.close();
