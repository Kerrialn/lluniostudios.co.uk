// Custom line under option lists, and the purchase route note on the review.
import { open, sleep, out, BASE_URL } from './lib.mjs';
const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const b = await open(W, H, 'cfgcustom' + W);
let ok = true;
const check = (label, pass, detail = '') => { if (!pass) ok = false; console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`); };
const ev = b.ev;
const LINE = 'Custom options are developed with you and quoted individually.';
const SIZE = 'Hand cast to order. Reserve now to secure your casting slot. Delivery will be quoted for individually.';
const CUSTOM = 'Hand cast to order. Reserve now to secure your casting slot. Custom options are developed with you and quoted individually.';
const BOTH = 'Hand cast to order. Reserve now to secure your casting slot. Custom options are developed with you, and delivery quoted individually.';
const LIMIT = 'Quantities of three or more proceed through the Reserve request process, not direct checkout.';
await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');
await b.send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(1800);
await b.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 }); await sleep(1300);
const pick = (g, v) => ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
const jump = (i) => ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
const tab = (i) => ev(`document.querySelectorAll('.ll-cfg-unit')[${i}].click()`);
const more = () => ev(`document.querySelector('[data-action="configurator#more"]').click()`);
const fewer = () => ev(`document.querySelector('[data-action="configurator#fewer"]').click()`);
const lines = () => ev(`[...document.querySelectorAll('[data-configurator-target=customNote]')].filter(e => !e.hidden && e.getBoundingClientRect().height > 0).map(e => e.dataset.group + ': ' + e.textContent.trim())`);
const notes = () => ev(`[...document.querySelectorAll('[data-configurator-target=routeNote]')].map(e => e.textContent.trim())`);
const reviewNote = () => ev(`(() => { const s = document.querySelectorAll('[data-configurator-target=stage]')[5]; const n = s.querySelector('[data-configurator-target=routeNote]'); const cta = s.querySelector('[data-configurator-target=cta]'); const route = n.closest('[data-configurator-target=route]'); return { note: n.textContent.trim(), visible: n.getBoundingClientRect().height > 0, underButton: route.getBoundingClientRect().top >= cta.getBoundingClientRect().bottom && route.getBoundingClientRect().top - cta.getBoundingClientRect().bottom < 30, rule: getComputedStyle(route).borderLeftColor, cta: cta.textContent }; })()`);

check('Custom line elements exist for the four stages with a Custom value (not Bulb Count)', JSON.stringify(await ev(`[...document.querySelectorAll('[data-configurator-target=customNote]')].map(e => e.dataset.group)`)) === JSON.stringify(['The Pigment', 'Cable Colour', 'Footing Material', 'Bulb Style']));
for (const [stage, group, custom, other] of [[1, 'The Pigment', 'Custom', 'Grey'], [2, 'Cable Colour', 'Custom Textile / Length', 'Raw Jute'], [3, 'Footing Material', 'Custom Metal Finish', 'Polished Copper'], [4, 'Bulb Style', 'Custom Specification', 'Clear Vintage Edison']]) {
    await jump(stage);
    check(`${group}: no line before choosing`, (await lines()).length === 0);
    await pick(group, custom);
    check(`${group}: choosing "${custom}" shows the line at once`, JSON.stringify(await lines()) === JSON.stringify([`${group}: ${LINE}`]), JSON.stringify(await lines()));
    await pick(group, other);
    check(`${group}: choosing "${other}" hides it`, (await lines()).length === 0);
}
const lineStyle = await ev(`(() => { const l = document.querySelector('[data-configurator-target=customNote]'); const w = document.querySelector('[data-configurator-target=warning]'); const a = getComputedStyle(l), c = getComputedStyle(w); return a.fontSize === c.fontSize && a.color === c.color && a.lineHeight === c.lineHeight; })()`);
check('the line is styled like the other small notes', lineStyle);

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
await jump(1); await pick('The Pigment', 'Custom'); await jump(5); r = await reviewNote();
check('review with a Custom choice: Reserve and the custom note, rule in cream', r.cta === 'Reserve Your Design' && r.note === CUSTOM && r.rule === 'rgb(236, 227, 211)', JSON.stringify(r));
await jump(0); await pick('size', 'lumen-forma-atelier'); await jump(4); await pick('Bulb Count', 'Dual Array'); await jump(5); r = await reviewNote();
check('review, Atelier with a Custom choice: the combined note', r.note === BOTH, r.note);
await jump(0); await more(); await more(); await jump(5); r = await reviewNote();
check('review, Atelier, Custom, 3 lamps: combined note then the 3-or-more note', r.note === BOTH + ' ' + LIMIT, r.note);
await jump(1); await pick('The Pigment', 'White'); await jump(5); r = await reviewNote();
check('review, Atelier, 3 lamps: size note then the 3-or-more note', r.note === SIZE + ' ' + LIMIT, r.note);
const both = await notes();
check('The Scale shows the same note', both.length === 2 && both[0] === both[1], JSON.stringify(both));
// screenshots
await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);
const panel = async (f) => { const q = await ev(`(() => { const r = document.querySelector('.ll-cfg-panel').getBoundingClientRect(); return [r.left, r.top + scrollY, r.width, r.height]; })()`); const s = await b.send('Page.captureScreenshot', { captureBeyondViewport: true, clip: { x: q[0], y: q[1], width: q[2], height: q[3], scale: 1 } }); (await import('node:fs')).writeFileSync(f, Buffer.from(s.result.data, 'base64')); };
await panel(out(`custom-review-${W}.png`));
await jump(1); await pick('The Pigment', 'Custom'); await panel(out(`custom-pigment-${W}.png`));
console.log(ok ? 'ALL PASSED' : 'SOME CHECKS FAILED');
b.close();
