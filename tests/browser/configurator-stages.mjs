// 7.2 stage behaviour on /lighting-designs: Gallery starts selected, every
// option starts empty and must be chosen before moving on.
import { open, sleep, BASE_URL } from './lib.mjs';
const b = await open(1440, 900, 'cfgst');
let ok = true;
const check = (label, pass, detail = '') => { if (!pass) ok = false; console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`); };
const reveal = async () => { await b.clearStorage(); await b.send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(2000); await b.ev("document.querySelector('.ll-story-skip').click()"); await sleep(1500); };
await reveal();
const ev = b.ev;
const st = () => ev(`({ num: document.querySelector('[data-configurator-target=stageNumber]').textContent.slice(0, 2),
  sel: Object.fromEntries([...document.querySelectorAll('.ll-cfg-opt.is-selected')].map(e => [e.dataset.group, e.textContent.trim()])),
  pressed: [...document.querySelectorAll('.ll-cfg-opt[aria-pressed=true]')].length,
  warn: [...document.querySelectorAll('[data-configurator-target=warning]')].filter(w => !w.hidden).map(w => w.textContent.trim()).join(' | '),
  countShown: !document.querySelector('[data-configurator-target=group][data-group="Bulb Count"]').hidden,
  note: document.querySelector('[data-configurator-target=sizeNote]').textContent, spec: document.querySelector('[data-configurator-target=spec]').textContent,
  dims: [...document.querySelectorAll('[data-configurator-target=dimension]')].map(e => e.textContent).join('x'),
  qty: document.querySelector('[data-configurator-target=quantity]').textContent, modes: !document.querySelector('[data-configurator-target=modes]').hidden })`);
const pick = (g, v) => ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
const jump = (i) => ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
const next = () => ev(`document.querySelector('[data-configurator-target=next]').click()`);
let s = await st();
check('starts on Gallery with nothing else selected', JSON.stringify(s.sel) === JSON.stringify({ size: 'Gallery' }) && s.pressed === 1, JSON.stringify(s.sel));
check('Gallery spec and note', s.spec === 'Gallery · £799' && s.dims === '22x22x60' && s.note === 'Direct Order | 7–14 Day Dispatch', `${s.spec} ${s.dims} ${s.note}`);
check('sizes listed by name only', (await ev(`[...document.querySelectorAll('[data-group=size]')].map(e => e.textContent.trim()).join(',')`)) === 'Studio,Gallery,Atelier,Estate,Monument');
check('no Bulb Count for Gallery', !s.countShown);
await next(); s = await st();
check('Next from The Scale goes to 02 with no message yet', s.num === '02' && s.warn === '');
await next(); s = await st();
check('Next without a pigment: stays on 02, pigment message', s.num === '02' && s.warn === 'Please choose a pigment to continue.', `${s.num} ${s.warn}`);
await jump(4); s = await st();
check('stage number 05 without a pigment: back to 02', s.num === '02');
await pick('The Pigment', 'Custom'); s = await st();
check('choosing clears the message; Custom is selectable', s.warn === '' && s.sel['The Pigment'] === 'Custom');
await pick('The Pigment', 'White'); await next(); s = await st();
check('then Next goes to 03 with no cable chosen', s.num === '03' && !s.sel['Cable Colour'] && s.warn === '');
await next(); s = await st();
check('Next without a cable: stays on 03, cable message', s.num === '03' && s.warn === 'Please choose a cable to continue.', `${s.num} ${s.warn}`);
await jump(5); s = await st();
check('stage number 06 without a cable: back to 03', s.num === '03');
await pick('Cable Colour', 'Slate Grey'); await next(); s = await st();
check('Next goes to 04 with no footing chosen', s.num === '04' && !s.sel['Footing Material']);
await next(); s = await st();
check('Next without a footing: stays on 04, footing message', s.num === '04' && s.warn === 'Please choose a footing to continue.', s.warn);
await pick('Footing Material', 'Brushed Copper'); await next(); s = await st();
check('Next goes to 05 with no bulb style chosen', s.num === '05' && !s.sel['Bulb Style']);
await next(); s = await st();
check('Next without a bulb style: stays on 05, bulb style message', s.num === '05' && s.warn === 'Please choose a bulb style to continue.', s.warn);
await pick('Bulb Style', 'Tubular Amber LED'); await next(); s = await st();
check('with every choice made, Next reaches 06', s.num === '06');
await jump(0); await pick('size', 'lumen-forma-atelier'); s = await st();
check('Atelier: quote note, from price, 30x30x90, Bulb Count shown empty', s.note === 'Delivery Quote Required' && s.spec === 'Atelier · Starting at £1,699' && s.dims === '30x30x90' && s.countShown && !s.sel['Bulb Count'], `${s.spec} ${s.dims}`);
check('other choices kept when changing size', s.sel['The Pigment'] === 'White' && s.sel['Cable Colour'] === 'Slate Grey' && s.sel['Bulb Style'] === 'Tubular Amber LED');
await jump(5); s = await st();
check('Atelier review without a bulb count: back to 05, bulb count message only', s.num === '05' && s.warn === 'Please choose a bulb count to continue.', `${s.num} ${s.warn}`);
await pick('Bulb Count', 'Dual Array'); await next(); s = await st();
check('with a bulb count, Next reaches 06', s.num === '06');
await jump(0); await pick('size', 'lumen-forma-studio'); await pick('size', 'lumen-forma-monument'); s = await st();
check('switching down to Studio clears the bulb count', s.countShown && !s.sel['Bulb Count'] && s.spec === 'Monument · Starting at £7,999');
await pick('size', 'lumen-forma-studio'); s = await st();
check('Studio: £399, 15x15x40, no Bulb Count', s.spec === 'Studio · £399' && s.dims === '15x15x40' && !s.countShown);
await ev(`document.querySelector('[data-action="configurator#fewer"]').click()`); s = await st();
check('quantity starts at 1 and does not go below 1', s.qty === '1' && !s.modes);
for (let i = 0; i < 15; i++) await ev(`document.querySelector('[data-action="configurator#more"]').click()`);
s = await st();
check('quantity stops at 10; the Customise choice shows from 2 lamps', s.qty === '10' && s.modes, s.qty);

await reveal();
await pick('size', 'lumen-forma-estate'); await jump(1); await pick('The Pigment', 'Grey'); await jump(2); await pick('Cable Colour', 'Raw Jute'); await jump(3); await pick('Footing Material', 'Polished Copper'); await jump(5); s = await st();
check('Estate with bulb style and count both missing: 05 shows both messages', s.num === '05' && s.warn === 'Please choose a bulb style to continue. | Please choose a bulb count to continue.', s.warn);
await pick('Bulb Style', 'Crown Gold LED'); s = await st();
check('choosing the style leaves only the count message', s.warn === 'Please choose a bulb count to continue.');
console.log(ok ? 'ALL PASSED' : 'SOME CHECKS FAILED');
b.close();
