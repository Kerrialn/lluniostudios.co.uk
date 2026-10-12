// Custom descriptions (10 October 2026): under a Custom choice on The
// Pigment, The Cable, The Footing and The Light, a box (styled as the site's
// other fields, up to 500 characters) to describe it, required while Custom
// is chosen (for every lamp): moving on without it goes back with "Please
// describe ... to continue.", and the server refuses a design without it. It
// follows the lamp being edited when customising individually, is
// remembered with the choices, shows on the review, and is carried into
// Reserve Your Design's "Custom Details" (filled in, still editable, saved
// with the request). A pasted link is kept exactly as it is.
import { open, sleep, checker, sql, BASE_URL } from './lib.mjs';

const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const { check, summary } = checker();
const b = await open(W, H, 'cfg-describe' + W);
const { send, ev } = b;
const EMAIL = `ada.describe${W}@example.test`;
const PROMPTS = {
    'The Pigment': 'Describe the colour you have in mind',
    'Cable Colour': 'Describe the cable colour you have in mind or send a link',
    'Footing Material': 'Describe the footing material and finish you have in mind',
    'Bulb Style': 'Describe the bulb you have in mind or send a link',
};
const WARNINGS = {
    'The Pigment': 'Please describe the colour you have in mind to continue.',
    'Cable Colour': 'Please describe the cable colour you have in mind to continue.',
    'Footing Material': 'Please describe the footing material and finish you have in mind to continue.',
    'Bulb Style': 'Please describe the bulb you have in mind to continue.',
};
const LINK = 'https://www.example.com/cables/ochre-twill?colour=ochre&weave=twill%20fine#swatch-3';
try {
    const go = async (path, wait = 2000) => { await send('Page.navigate', { url: BASE_URL + path }); await sleep(wait); };
    const reveal = async () => { await ev("document.querySelector('.ll-story-skip').click()"); await sleep(1500); };
    const pick = (g, v) => ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
    const jump = (i) => ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
    const more = () => ev(`document.querySelector('[data-action="configurator#more"]').click()`);
    const tab = (i) => ev(`document.querySelectorAll('.ll-cfg-unit')[${i}].click()`);
    const box = (g) => `document.querySelector('[data-configurator-target=describe][data-group="${g}"]')`;
    const shown = () => ev(`[...document.querySelectorAll('[data-configurator-target=describe]')].filter(e => !e.hidden && e.getBoundingClientRect().height > 0).map(e => e.dataset.group)`);
    const value = (g) => ev(`${box(g)}.querySelector('textarea').value`);
    // Typed as a customer would: focus, clear, then the text.
    const type = async (g, text) => {
        await ev(`(() => { const t = ${box(g)}.querySelector('textarea'); t.focus(); t.select(); })()`);
        await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Backspace', code: 'Backspace', windowsVirtualKeyCode: 8 });
        await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Backspace', code: 'Backspace', windowsVirtualKeyCode: 8 });
        if (text) await send('Input.insertText', { text });
        await sleep(200);
    };
    const stageNow = () => ev(`document.querySelector('[data-configurator-target=stageNumber]').textContent.slice(0, 2)`);
    const warnings = () => ev(`[...document.querySelectorAll('[data-configurator-target=describeWarning]')].filter(e => !e.hidden && e.getBoundingClientRect().height > 0).map(e => e.textContent.trim())`);
    const review = () => ev(`[...document.querySelectorAll('[data-configurator-target=review] button')].map(r => r.children[0].textContent + '=' + r.children[1].innerText.replace(/\\n+/g, ' | '))`);
    await send('Network.enable'); await send('Network.clearBrowserCookies');
    await go('/contact'); await ev(`localStorage.clear(); sessionStorage.clear()`);
    await go('/lighting-designs'); await reveal();

    check('a box for each of the four stages with a Custom value (not Bulb Count), with its wording',
        JSON.stringify(await ev(`[...document.querySelectorAll('[data-configurator-target=describe]')].map(e => [e.dataset.group, e.querySelector('span').textContent.replace(/\\s+/g, ' ').trim()])`)) === JSON.stringify(Object.entries(PROMPTS)));

    for (const [stage, group, custom, other] of [[1, 'The Pigment', 'Custom', 'Grey'], [2, 'Cable Colour', 'Custom Textile / Length', 'Raw Jute'], [3, 'Footing Material', 'Custom Metal Finish', 'Polished Copper'], [4, 'Bulb Style', 'Custom Specification', 'Clear Vintage Edison']]) {
        await jump(stage);
        check(`${group}: no box before choosing`, (await shown()).length === 0);
        await pick(group, other);
        check(`${group}: none for "${other}"`, (await shown()).length === 0);
        await pick(group, custom);
        await jump(stage + 1);
        check(`${group}: moving on with the box empty stays on the stage, with "${WARNINGS[group]}"`, (await stageNow()) === String(stage + 1).padStart(2, '0') && JSON.stringify(await warnings()) === JSON.stringify([WARNINGS[group]]), JSON.stringify([await stageNow(), await warnings()]));
        await type(group, '   ');
        await jump(stage + 1);
        check(`${group}: spaces alone don't count`, (await stageNow()) === String(stage + 1).padStart(2, '0'));
        const place = await ev(`(() => { const list = ${box(group)}.previousElementSibling.getBoundingClientRect(); const custom = document.querySelector('.ll-cfg-opt[data-group="${group}"][data-value="${custom}"]').getBoundingClientRect(); const b = ${box(group)}.getBoundingClientRect(); return { lastIsCustom: Math.round(custom.bottom) === Math.round(list.bottom), gap: Math.round(b.top - custom.bottom) }; })()`);
        check(`${group}: choosing "${custom}" shows the box directly under it`, JSON.stringify(await shown()) === JSON.stringify([group]) && place.lastIsCustom && place.gap >= 0 && place.gap <= 20, JSON.stringify(place));
        await type(group, `${group} wish`);
        check(`${group}: the message goes once something is typed`, (await warnings()).length === 0);
        await pick(group, other);
        check(`${group}: choosing "${other}" hides it`, (await shown()).length === 0);
        await pick(group, custom);
        check(`${group}: back on "${custom}", the text is still there`, (await value(group)) === `${group} wish`, await value(group));
    }

    const lines = await ev(`[...document.querySelectorAll('[data-configurator-target=describe]')].map(e => [e.dataset.group, [...e.querySelectorAll(':scope > span:not([data-configurator-target])')].map(x => x.textContent.trim())])`);
    const LINE = 'You can upload reference photos when you reserve your design, Custom options are developed with you and quoted individually.';
    check('under every box only its label and the one combined line (no "Up to 500 characters")', JSON.stringify(lines) === JSON.stringify(Object.keys(PROMPTS).map((g) => [g, [PROMPTS[g], LINE]])), JSON.stringify(lines));
    const noteStyle = await ev(`(() => { const n = ${box('The Pigment')}.querySelector(':scope > span:not([data-configurator-target]):last-of-type'), w = document.querySelector('[data-configurator-target=warning]'); const a = getComputedStyle(n), c = getComputedStyle(w); return a.fontSize === c.fontSize && a.color === c.color; })()`);
    check('the line is small and grey (12px), as the other notes', noteStyle);
    const warnStyle = await ev(`(() => { const a = getComputedStyle(document.querySelector('[data-configurator-target=describeWarning]')), c = getComputedStyle(document.querySelector('[data-configurator-target=warning]')); return a.fontSize === c.fontSize && a.color === c.color; })()`);
    check('the message is styled like "Please choose a … to continue."', warnStyle);

    // Styled as the site's other fields (the Reserve page's "Custom Details" box).
    await jump(1);
    const style = await ev(`(() => { const t = ${box('The Pigment')}.querySelector('textarea'); const s = getComputedStyle(t); const l = getComputedStyle(${box('The Pigment')}.querySelector('span')); return { font: s.fontSize, line: s.lineHeight, border: s.borderTopWidth + ' ' + s.borderTopStyle + ' ' + s.borderTopColor, radius: s.borderTopLeftRadius, bg: s.backgroundColor, colour: s.color, pad: s.paddingLeft, family: s.fontFamily, label: l.fontSize, max: t.maxLength, required: t.required }; })()`);
    check('the box is styled as the site\'s fields (its label 12px, smaller than the fields\' 14px) and limited to 500 characters', style.max === 500 && style.font === '15px' && style.label === '12px' && style.radius === '0px' && /^1px solid/.test(style.border), JSON.stringify(style));
    await type('The Pigment', 'x'.repeat(600));
    check('typing more than 500 characters stops at 500', (await value('The Pigment')).length === 500, String((await value('The Pigment')).length));
    await type('The Pigment', 'A warm terracotta, close to RAL 8004');

    // The review: each description under its choice.
    await jump(5);
    let rows = await review();
    check('the review shows each description under its Custom choice',
        rows.includes('The Pigment=Custom | A warm terracotta, close to RAL 8004') && rows.includes('Cable Colour=Custom Textile / Length | Cable Colour wish')
        && rows.includes('Footing Material=Custom Metal Finish | Footing Material wish') && rows.includes('Bulb Style=Custom Specification | Bulb Style wish'), JSON.stringify(rows));
    await jump(2); await pick('Cable Colour', 'Raw Jute'); await jump(5);
    rows = await review();
    check('a description under a choice no longer Custom is left out', rows.includes('Cable Colour=Raw Jute') && !rows.some((r) => /Cable Colour wish/.test(r)), JSON.stringify(rows));
    await jump(2); await pick('Cable Colour', 'Custom Textile / Length');
    await type('Cable Colour', LINK);
    check('a pasted link is kept exactly as it is', (await value('Cable Colour')) === LINK, await value('Cable Colour'));
    await jump(5);
    check('and shows on the review exactly as it is', (await review()).includes(`Cable Colour=Custom Textile / Length | ${LINK}`), JSON.stringify(await review()));

    // The server refuses a design whose Custom choice has no description.
    // The page's own handler is removed first (Stimulus lets go of it a moment later).
    await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(300);
    await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = JSON.stringify({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [{ 'The Pigment': 'Custom', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED' }] }); f.requestSubmit(); })()`);
    // Read at once: the message fades away by itself (toast_controller.js).
    let refused = null;
    for (let i = 0; i < 40 && !refused; i++) {
        await sleep(100);
        refused = await ev(`location.pathname === '/lighting-designs' && document.querySelector('#flashes').textContent.includes(${JSON.stringify(WARNINGS['The Pigment'])}) ? 'yes' : null`).catch(() => null);
    }
    check('sent without a description, the server refuses it with the message (nothing reserved)', refused === 'yes');

    // Remembered: a reload keeps every description.
    await go('/lighting-designs', 2500);
    rows = await review();
    check('remembered: after a reload the review still shows the descriptions', rows.includes('The Pigment=Custom | A warm terracotta, close to RAL 8004') && rows.includes('Bulb Style=Custom Specification | Bulb Style wish'), JSON.stringify(rows));
    await jump(1);
    check('and the box still holds the text', (await value('The Pigment')) === 'A warm terracotta, close to RAL 8004');

    // Customise Individually: the box follows the lamp being edited.
    await jump(0); await more(); await ev(`document.querySelector('.ll-cfg-mode[data-mode=individual]').click()`);
    await jump(1); await tab(1);
    check('Lamp 2 starts from the shared text', (await value('The Pigment')) === 'A warm terracotta, close to RAL 8004');
    await type('The Pigment', 'Lamp 2: a deep slate');
    await tab(0);
    check("Lamp 1 keeps its own text", (await value('The Pigment')) === 'A warm terracotta, close to RAL 8004', await value('The Pigment'));
    await tab(1);
    check("back on Lamp 2, its own text", (await value('The Pigment')) === 'Lamp 2: a deep slate');
    await pick('The Pigment', 'Grey');
    check('Lamp 2 not Custom: no box', (await shown()).length === 0);
    await tab(0);
    check('Lamp 1 still Custom: its box shows', JSON.stringify(await shown()) === JSON.stringify(['The Pigment']));
    await tab(1); await pick('The Pigment', 'Custom');
    await jump(5);
    rows = await review();
    // Required for each lamp: Lamp 2's description emptied, moving on opens Lamp 2.
    await jump(1); await tab(1); await type('The Pigment', ''); await tab(0);
    await jump(3);
    const current = await ev(`document.querySelector('.ll-cfg-unit.is-current')?.textContent`);
    check('individually: an empty description on Lamp 2 stops moving on and opens Lamp 2 with the message', (await stageNow()) === '02' && current === 'Lamp 2' && JSON.stringify(await warnings()) === JSON.stringify([WARNINGS['The Pigment']]), JSON.stringify([await stageNow(), current, await warnings()]));
    await tab(0);
    check('Lamp 1, described, has no message', (await warnings()).length === 0);
    await tab(1); await type('The Pigment', 'Lamp 2: a deep slate');
    check('the review lists each lamp\'s descriptions under its row',
        rows.some((r) => r.startsWith('Lamp 1=') && r.includes('The Pigment: A warm terracotta, close to RAL 8004') && r.includes('Bulb Style: Bulb Style wish') && r.includes(`Cable Colour: ${LINK}`))
        && rows.some((r) => r.startsWith('Lamp 2=') && r.includes('The Pigment: Lamp 2: a deep slate')), JSON.stringify(rows));

    // Reserve Your Design: "Custom Details" filled in with every description.
    await ev(`document.querySelector('[data-configurator-target=cta]').click()`); await sleep(2500);
    const details = () => ev(`document.querySelector('[name="reserve_form[customDetails]"]')?.value ?? null`);
    const expected = [
        'Lamp 1 · The Pigment: A warm terracotta, close to RAL 8004', `Lamp 1 · Cable Colour: ${LINK}`, 'Lamp 1 · Footing Material: Footing Material wish', 'Lamp 1 · Bulb Style: Bulb Style wish',
        'Lamp 2 · The Pigment: Lamp 2: a deep slate', `Lamp 2 · Cable Colour: ${LINK}`, 'Lamp 2 · Footing Material: Footing Material wish', 'Lamp 2 · Bulb Style: Bulb Style wish',
    ].join('\n');
    check('Reserve Your Design: "Custom Details" is filled in with each description', (await ev('location.pathname')) === '/reserve' && (await details()) === expected, JSON.stringify(await details()));
    const typed = { fullName: 'Ada Describe', email: EMAIL, phone: '07700 900123', addressLine1: '1 High Street', town: 'Wimborne', county: 'Dorset', postcode: 'BH21 1AA' };
    await ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(typed)})) { const f = document.querySelector('[name="reserve_form[' + k + ']"]'); f.value = v; f.dispatchEvent(new Event('input', { bubbles: true })); } })()`);
    await ev(`(() => { const f = document.querySelector('[name="reserve_form[customDetails]"]'); f.focus(); f.setSelectionRange(f.value.length, f.value.length); })()`);
    await send('Input.insertText', { text: '\nBoth for a hallway.' }); await sleep(300);
    check('and it can be edited', (await details()) === expected + '\nBoth for a hallway.');

    // Away and back: what was typed stays, details too.
    await go('/lighting-designs', 2500);
    await go('/reserve');
    check('back on Reserve Your Design unchanged: the edited text and the other details are kept', (await details()) === expected + '\nBoth for a hallway.' && (await ev(`document.querySelector('[name="reserve_form[fullName]"]').value`)) === 'Ada Describe', JSON.stringify(await details()));

    // A description changed in the configurator: the new text, with the addition kept.
    await go('/lighting-designs', 2500);
    await jump(1); await tab(0); await type('The Pigment', 'A warm terracotta, RAL 8004 exactly'); await jump(5);
    await ev(`document.querySelector('[data-configurator-target=cta]').click()`); await sleep(2500);
    const updated = expected.replace('close to RAL 8004', 'RAL 8004 exactly');
    check('a description changed in the configurator: the new text, with what was added kept under it', (await details()) === updated + '\nBoth for a hallway.' && (await ev(`document.querySelector('[name="reserve_form[fullName]"]').value`)) === 'Ada Describe', JSON.stringify(await details()));

    if (W < 900) {
        check('on a phone, the page has no sideways scroll', await ev(`document.documentElement.scrollWidth <= innerWidth`));
    }

    // Sent: saved with the request.
    await ev(`document.querySelector('form[name=reserve_form]').requestSubmit()`); await sleep(3000);
    check('the reservation is sent', (await ev('location.pathname')) === '/reserve/received');
    const saved = sql(`select custom_details from design_request where email = '${EMAIL}' order by created_at desc limit 1`);
    check('the request is saved with the Custom Details as sent', saved.includes('Lamp 1 · The Pigment: A warm terracotta, RAL 8004 exactly') && saved.includes('Both for a hallway.'), saved.slice(0, 400));
    check('with the link exactly as pasted', sql(`select position('${LINK}' in custom_details) > 0 as kept from design_request where email = '${EMAIL}' order by created_at desc limit 1`).replace(/\s+/g, ' ').includes('kept ------ 1'));
    check('and the configurator forgot the descriptions with the choices', (await ev(`localStorage.getItem('llunio:configurator')`)) === null);
    summary();
} finally { b.close(); }
