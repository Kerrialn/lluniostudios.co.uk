// 7.4 Customise All / Individually on /lighting-designs.
import { open, sleep, BASE_URL } from './lib.mjs';
const b = await open(1440, 900, 'cfgmodes');
let ok = true;
const check = (label, pass, detail = '') => { if (!pass) ok = false; console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`); };
await b.send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(2000);
await b.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 }); await sleep(1500);
const ev = b.ev;
const st = () => ev(`({ num: document.querySelector('[data-configurator-target=stageNumber]').textContent.slice(0, 2),
  modes: !document.querySelector('[data-configurator-target=modes]').hidden,
  mode: document.querySelector('.ll-cfg-mode.is-selected')?.dataset.mode,
  tabs: document.querySelector('[data-configurator-target=units]').hidden ? null : [...document.querySelectorAll('.ll-cfg-unit')].map(t => t.textContent + (t.classList.contains('is-current') ? '*' : '')).join(','),
  sel: Object.fromEntries([...document.querySelectorAll('.ll-cfg-opt.is-selected')].map(e => [e.dataset.group, e.textContent.trim()])),
  warn: [...document.querySelectorAll('[data-configurator-target=warning]')].filter(w => !w.hidden).map(w => w.textContent.trim()).join(),
  soon: document.querySelector('[data-configurator-target=soonTitle]').textContent })`);
const pick = (g, v) => ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
const jump = (i) => ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
const next = () => ev(`document.querySelector('[data-configurator-target=next]').click()`);
const more = () => ev(`document.querySelector('[data-action="configurator#more"]').click()`);
const fewer = () => ev(`document.querySelector('[data-action="configurator#fewer"]').click()`);
const mode = (m) => ev(`document.querySelector('.ll-cfg-mode[data-mode=${m}]').click()`);
const tab = (i) => ev(`document.querySelectorAll('.ll-cfg-unit')[${i}].click()`);

let s = await st();
check('one lamp: no configuration choice', !s.modes);
await more(); s = await st();
check('two lamps: the choice appears with Customise All selected', s.modes && s.mode === 'all');
await jump(1); s = await st();
check('Customise All: no lamp tabs', s.tabs === null);
await jump(0); await mode('individual'); s = await st();
check('Customise Individually selected; no tabs on The Scale', s.mode === 'individual' && s.tabs === null);
await jump(1); s = await st();
check('The Pigment shows Lamp 1 / Lamp 2 tabs, Lamp 1 current', s.tabs === 'Lamp 1*,Lamp 2');
await pick('The Pigment', 'White'); await tab(1); s = await st();
check('Lamp 2 has its own (empty) pigment', s.tabs === 'Lamp 1,Lamp 2*' && !s.sel['The Pigment']);
await tab(0); await next(); s = await st();
check('moving on with Lamp 2 missing a pigment opens Lamp 2 on 02 with the warning', s.num === '02' && s.tabs === 'Lamp 1,Lamp 2*' && s.warn === 'Please choose a pigment to continue.', JSON.stringify(s));
await pick('The Pigment', 'Black'); await next(); s = await st();
check('with both pigments, Next goes to 03 and opens Lamp 1 (not Lamp 2, last edited)', s.num === '03' && s.tabs === 'Lamp 1*,Lamp 2', s.tabs);
check('the cable starts empty for each lamp', !s.sel['Cable Colour']);
await tab(1); await pick('Cable Colour', 'Orange'); s = await st();
check('the photo follows the lamp being edited (Lamp 2: Orange)', s.soon === 'Orange');
await next(); s = await st();
check('moving on with Lamp 1 missing a cable opens Lamp 1 on 03 with the cable message', s.num === '03' && s.tabs === 'Lamp 1*,Lamp 2' && s.warn === 'Please choose a cable to continue.', JSON.stringify(s));
await pick('Cable Colour', 'Slate Grey'); s = await st();
check('Lamp 1 has its own cable and photo name', s.sel['Cable Colour'] === 'Slate Grey' && s.soon === 'Slate Grey');
await tab(1); s = await st();
check('Lamp 2 keeps Orange', s.sel['Cable Colour'] === 'Orange');
await next(); s = await st();
check('Next from 03 while on Lamp 2: 04 opens on Lamp 1', s.num === '04' && s.tabs === 'Lamp 1*,Lamp 2', s.tabs);
await tab(1); await ev(`document.querySelector('[data-configurator-target=back]').click()`); s = await st();
check('Back to 03 while on Lamp 2: opens on Lamp 1', s.num === '03' && s.tabs === 'Lamp 1*,Lamp 2', s.tabs);
await tab(1); await jump(1); s = await st();
check('a stage number while on Lamp 2: opens on Lamp 1', s.num === '02' && s.tabs === 'Lamp 1*,Lamp 2', s.tabs);
await jump(0); await more(); await jump(5); s = await st();
check('a third lamp starts without a pigment: moving on opens Lamp 3 on 02', s.num === '02' && s.tabs === 'Lamp 1,Lamp 2,Lamp 3*', JSON.stringify(s));
await jump(0); await fewer(); await jump(1); s = await st();
check('back to two lamps, then a new stage: opens on Lamp 1', s.tabs === 'Lamp 1*,Lamp 2', s.tabs);
await jump(0); await pick('size', 'lumen-forma-atelier');
for (const i of [0, 1]) { await jump(3); await tab(i); await pick('Footing Material', 'Polished Aluminium'); await jump(4); await tab(i); await pick('Bulb Style', 'Smoked Glass LED'); }
await tab(0); await pick('Bulb Count', 'Dual Array'); await jump(5); s = await st();
check('Atelier individually: a missing bulb count on Lamp 2 opens Lamp 2 on 05', s.num === '05' && s.tabs === 'Lamp 1,Lamp 2*' && s.warn === 'Please choose a bulb count to continue.', JSON.stringify(s));
await jump(0); await pick('size', 'lumen-forma-studio'); await pick('size', 'lumen-forma-atelier'); await jump(4); await tab(0); s = await st();
check('switching to Studio cleared every lamp\'s bulb count', !s.sel['Bulb Count']);
await jump(0); await mode('all'); await jump(1); s = await st();
check('back to Customise All: no tabs, the shared choices show again (empty)', s.tabs === null && !s.sel['The Pigment']);
await fewer(); s = await st();
check('down to one lamp: the choice disappears', !s.modes);

// Choices made under Customise All carry into each lamp.
await b.send('Page.navigate', { url: BASE_URL + '/lighting-designs' }); await sleep(2000);
await b.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 }); await sleep(1500);
await more(); await jump(1); await pick('The Pigment', 'Grey'); await jump(0); await mode('individual'); await jump(1); await tab(1); s = await st();
check('a pigment chosen under Customise All is each lamp\'s starting choice', s.sel['The Pigment'] === 'Grey');
console.log(ok ? 'ALL PASSED' : 'SOME CHECKS FAILED');
b.close();
