// Step 8: Reserve Your Design, end to end.
import { open, sleep, sql, out, BASE_URL, MAILPIT_URL, customerEmailProblems, studioEmailProblems } from './lib.mjs';
import { writeFileSync } from 'node:fs';
const b = await open(1440, 900, 'reserve');
let ok = true;
const check = (label, pass, detail = '') => { if (!pass) ok = false; console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`); };
const ev = b.ev;
const mailpit = async (q) => (await (await fetch(MAILPIT_URL + '/api/v1/search?query=' + encodeURIComponent(q))).json()).messages || [];
const mailBody = async (id) => (await (await fetch(MAILPIT_URL + '/api/v1/message/' + id)).json());
const go = async (url, wait = 1800) => { await b.send('Page.navigate', { url }); await sleep(wait); };
const reveal = async () => { await b.ev("document.querySelector('.ll-story-skip').click()"); await sleep(1300); };
const pick = (g, v) => ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
const jump = (i) => ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
const press = async () => { await ev(`document.querySelector('[data-configurator-target=cta]').click()`); await sleep(2200); };
// A Custom choice needs its description (10 October 2026).
const describe = (g, text = 'A description.') => ev(`(() => { const t = document.querySelector('[data-configurator-target=describe][data-group="${g}"] textarea'); t.value = ${JSON.stringify(text)}; t.dispatchEvent(new Event('input', { bubbles: true })); })()`);
const fill = async (size, v) => { await go(BASE_URL + '/lighting-designs'); await reveal(); await pick('size', size); for (const [s, g] of [[1, 'The Pigment'], [2, 'Cable Colour'], [3, 'Footing Material'], [4, 'Bulb Style'], [4, 'Bulb Count']]) { if (v[g]) { await jump(s); await pick(g, v[g]); if (v[g].startsWith('Custom')) await describe(g); } } await jump(5); };
// Since 7b.4 the larger sizes aren't configured on the page (their own step sends the
// size only, larger-piece.mjs); a full larger design is sent to the server directly, as
// the configurator's form would (the server's rules are unchanged). On an edit page the
// form carries the line being edited.
const send = async (design, url = BASE_URL + '/lighting-designs') => { await go(url, 2200); await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100); await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify(design))}; f.requestSubmit(); })()`); await sleep(2500); };
const page = () => ev(`({ path: location.pathname, h1: document.querySelector('h1')?.textContent.trim(), text: document.querySelector('main').innerText, labels: [...document.querySelectorAll('form label > span:first-child')].map(l => l.textContent.replace(/\\s+/g, ' ').trim()), errors: [...document.querySelectorAll('form [role=alert]')].map(e => e.textContent.trim()), rows: [...document.querySelectorAll('aside .flex.justify-between')].map(r => r.children[0].textContent + '=' + r.children[1].textContent) })`);
const setField = (name, value) => ev(`(() => { const e = document.querySelector('[name="reserve_form[${name}]"]'); e.value = ${JSON.stringify(value)}; })()`);
const submit = async () => { await ev(`document.querySelector('form[name=reserve_form]').requestSubmit()`); await sleep(2500); };
const requests = () => Number(sql('select count(*) from design_request').match(/\d+/g).pop());
const since = new Date().toISOString();

await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');
const before = requests();

// Nothing to reserve
await go(BASE_URL + '/reserve');
check('/reserve with nothing to reserve goes to Lighting Designs', (await ev('location.pathname')) === '/lighting-designs');

// A Gallery lamp in the basket, then an Atelier with a Custom pigment reserved from the configurator
await fill('lumen-forma-gallery', { 'The Pigment': 'White', 'Cable Colour': 'Slate Grey', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED' });
await press();
check('Gallery lamp added to the basket', (await ev('location.pathname')) === '/basket');
await send({ size: 'lumen-forma-atelier', quantity: 1, mode: 'all', lamps: [{ 'The Pigment': 'Custom', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED', 'Bulb Count': 'Dual Array' }], details: [{ 'The Pigment': 'A description.' }] });
let p = await page();
check('Reserve Your Design opens the request form', p.path === '/reserve' && p.h1 === 'Reserve Your Design' && !/Casting Slot/.test(p.text), p.path + ' ' + p.h1);
check('it says no payment is taken', /No payment is taken today/.test(p.text));
check('it carries the basket lamp and the lamp being configured, each in full', JSON.stringify(p.rows) === JSON.stringify(['Size=Gallery', 'The Pigment=White', 'Cable Colour=Slate Grey', 'Footing Material=Brushed Copper', 'Bulb Style=Crown Gold LED', 'Quantity=1', 'Size=Atelier', 'The Pigment=Custom', 'Cable Colour=Raw Jute', 'Footing Material=Polished Copper', 'Bulb Style / Count=Smoked Glass LED / Dual Array', 'Quantity=1']), JSON.stringify(p.rows));
// The gift's fields (Step 11.1b) are checked by gift-options.mjs.
const giftLabel = (l) => /^(Needed by|Recipient's|Gift message|This is a gift|Send to me|Send directly)/.test(l);
check('the fields, the address in separate fields, with Custom Details for a Custom choice', JSON.stringify(p.labels.filter((l) => !giftLabel(l))) === JSON.stringify(['Full Name *', 'Email Address * (For sending the invoice and build schedule)', 'Phone Number * (Required by carriers for heavy-goods delivery scheduling)', 'Address line 1 *', 'Address line 2', 'Town/City *', 'County', 'Postcode *', 'Country *', "Site Access & Delivery Notes (e.g. 'Are there stairs, narrow access, or delivery constraints for heavy items?')", "Custom Details (Describe what you'd like for each custom option, e.g. target tone or Pantone/RAL code, cable textile or length, footing finish, bulb specification)"]), JSON.stringify(p.labels));
// As the Card Payment page: 14px labels above 48px boxes with a border; typed
// text, placeholders and the dropdown's choices in the page's light colour.
const look = await ev(`(() => {
    const css = (e) => getComputedStyle(e);
    // Boxes the gift's hidden fields don't count in (shown only for a gift); tick boxes and radio buttons aren't boxes.
    const boxes = [...document.querySelectorAll('form[name=reserve_form] input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=file]), form[name=reserve_form] select, form[name=reserve_form] textarea')].filter(e => e.getBoundingClientRect().height > 0);
    const name = document.querySelector('[name="reserve_form[fullName]"]');
    name.placeholder = 'x';
    const placeholder = getComputedStyle(name, '::placeholder').color;
    name.removeAttribute('placeholder');
    const country = document.querySelector('[name="reserve_form[country]"]');
    return {
        colours: [...new Set(boxes.map(e => css(e).color))],
        borders: [...new Set(boxes.map(e => css(e).borderTopStyle + ' ' + css(e).borderTopWidth + ' ' + css(e).borderLeftStyle))],
        heights: [...new Set(boxes.filter(e => e.tagName !== 'TEXTAREA').map(e => css(e).height))],
        fonts: [...new Set(boxes.map(e => css(e).fontSize))],
        labels: [...new Set([...document.querySelectorAll('form[name=reserve_form] label > span:first-child')].filter(e => e.getBoundingClientRect().height > 0).map(e => css(e).fontSize))],
        placeholder,
        option: css(country.options[0]).color + ' on ' + css(country.options[0]).backgroundColor,
        countries: [...country.options].map(o => o.value + '=' + o.textContent),
        country: country.value,
        max: ['addressLine1', 'addressLine2', 'town', 'county', 'postcode'].map(n => document.querySelector('[name="reserve_form[' + n + ']"]').maxLength),
        h1: css(document.querySelector('h1')).fontSize + ' ' + css(document.querySelector('h1')).fontWeight,
    };
})()`);
check('fields as the Card Payment page: boxed, 48px, 15px text, 14px labels; heading as Card Payment', JSON.stringify(look.borders) === JSON.stringify(['solid 1px solid']) && JSON.stringify(look.heights) === JSON.stringify(['48px']) && JSON.stringify(look.fonts) === JSON.stringify(['15px']) && JSON.stringify(look.labels) === JSON.stringify(['14px']) && look.h1 === '28.8px 400', JSON.stringify(look));
check('typed text, placeholders and the dropdown in the light colour', JSON.stringify(look.colours) === JSON.stringify(['rgb(236, 227, 211)']) && look.placeholder !== 'rgb(0, 0, 0)' && look.option === 'rgb(236, 227, 211) on rgb(21, 18, 14)', JSON.stringify([look.colours, look.placeholder, look.option]));
check('Country: United Kingdom by default, or the Isle of Man, Guernsey or Jersey', look.country === 'GB' && JSON.stringify(look.countries) === JSON.stringify(['GB=United Kingdom', 'IM=Isle of Man', 'GG=Guernsey', 'JE=Jersey']), JSON.stringify(look.countries));
check('the same character limits as Card Payment (35, postcode 8)', JSON.stringify(look.max) === JSON.stringify([35, 35, 35, 35, 8]), JSON.stringify(look.max));
check('mixed sizes: Image Coming Soon, never another size\'s photo', /Image Coming Soon/i.test(await ev(`document.querySelector('aside').innerText`)) && !(await ev(`!!document.querySelector('aside img')`)));
check('the basket still holds its lamp until the request is sent', /Basket \(1\)/i.test(await ev(`document.querySelector('#navbar').innerText`)));
await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);
let s = await b.send('Page.captureScreenshot', { captureBeyondViewport: true, clip: { x: 0, y: 0, width: 1440, height: await ev('document.documentElement.scrollHeight'), scale: 1 } }); writeFileSync(out('reserve-form-1440.png'), Buffer.from(s.result.data, 'base64'));

// Validation
await submit(); p = await page();
check('sending it empty shows each required field\'s message and saves nothing', p.path === '/reserve' && ['Please enter your full name.', 'Please enter your email address.', 'Please enter your phone number.', 'Please enter the first line of your address.', 'Please enter your town or city.', 'Please enter your postcode.'].every(m => p.errors.includes(m)) && requests() === before, JSON.stringify(p.errors));
await setField('fullName', 'Ada Test'); await setField('email', 'not-an-email'); await setField('phone', '07700 900123'); await setField('addressLine1', '1 High Street'); await setField('town', 'Wimborne'); await setField('postcode', 'BH21'); await submit(); p = await page();
check('a bad email and a half postcode are refused', p.errors.includes('Please enter a valid email address.') && p.errors.includes('Please enter a full postcode, e.g. BH21 3EG.'), JSON.stringify(p.errors));
await setField('postcode', 'IM1 1AA'); await submit(); p = await page();
check('a postcode that doesn\'t match the country is refused', p.errors.includes('That postcode is on the Isle of Man: please choose it as the country.'), JSON.stringify(p.errors));
check('what was typed is kept', (await ev(`document.querySelector('[name="reserve_form[fullName]"]').value`)) === 'Ada Test');

// Send it
await setField('email', 'ada.test@example.test'); await setField('postcode', 'bh21 1aa'); await setField('county', 'Dorset'); await setField('accessNotes', 'Two flights of stairs.'); await setField('customDetails', 'Warm grey, close to RAL 7044.');
await submit(); p = await page();
check('sending it shows "Reservation received": heading, the message, the confirmation line, no request number', p.path === '/reserve/received' && /^Reservation Received/i.test(p.text) && /Your design request has been received/.test(p.text) && /Confirmation has been sent to the email address provided\./.test(p.text) && !/LR-\d/.test(p.text), p.text.slice(0, 160));
const number = (sql(`select request_number from design_request where email = 'ada.test@example.test' order by created_at desc limit 1`).match(/LR-\d{6}-\d{4}/) || [])[0];
check('one request saved', requests() === before + 1);
const today = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit', timeZone: 'Europe/London' }).replace(/\//g, '');
check(`its reference: LR, today's date as DDMMYY, then 4 digits (LR-${today}-…)`, new RegExp(`^LR-${today}-\\d{4}$`).test(number ?? ''), number);
const address = sql(`select concat_ws('|', address_line1, coalesce(address_line2, '-'), town, county, postcode, country, coalesce(delivery_address, '-')) as a from design_request where request_number = '${number}'`);
check('the address saved in its fields (postcode tidied, United Kingdom)', /1 High Street\|-\|Wimborne\|Dorset\|BH21 1AA\|GB\|-/.test(address), address.replace(/\s+/g, ' '));
const row = sql(`select status, full_name, email, phone, access_notes, custom_details, (select count(*) from design_request_item i where i.design_request_id = r.id) as items, (select sum(quantity) from design_request_item i where i.design_request_id = r.id) as lamps from design_request r where request_number = '${number}'`);
check('saved as New with the details and both lamps', /\bnew\b/.test(row) && /Ada Test/.test(row) && /ada\.test@example\.test/.test(row) && /07700 900123/.test(row) && /Two flights of stairs\./.test(row) && /RAL 7044/.test(row) && /\b2\s+2\s*$/m.test(row.trim().split('\n').slice(-2, -1)[0] || ''), row.replace(/\s+/g, ' ').slice(0, 300));
const snap = sql(`select size_name, quantity, unit_price, customised_individually, options_snapshot from design_request_item i join design_request r on r.id = i.design_request_id where r.request_number = '${number}' order by position`);
check('each lamp\'s configuration is snapshotted', /Gallery/.test(snap) && /Atelier/.test(snap) && /Dual Array/.test(snap) && /Polished Copper/.test(snap) && /169900/.test(snap), snap.replace(/\s+/g, ' ').slice(0, 260));
await go(BASE_URL + '/basket');
check('the basket is empty once sent', /empty/i.test(await ev(`document.querySelector('main').innerText`)) && !/Basket \(/i.test(await ev(`document.querySelector('#navbar').innerText`)));
await go(BASE_URL + '/reserve');
check('nothing left to reserve afterwards', (await ev('location.pathname')) === '/lighting-designs');

// Emails
await sleep(800);
const toCustomer = (await mailpit(`to:ada.test@example.test after:${since.slice(0, 10)}`)).find(m => m.Subject === 'We have your request, Ada Test');
let toStudio = null;
for (const m of (await mailpit(`to:lluniostudios@gmail.com subject:"New request" after:${since.slice(0, 10)}`))) if (!toStudio && (await mailBody(m.ID)).HTML.includes(number)) toStudio = m;
check('email 02 sent to the customer', !!toCustomer);
check('notification sent to lluniostudios@gmail.com: "New request · Gallery × 1, Atelier × 1 · Custom pigment"', !!toStudio && toStudio.Subject === 'New request · Gallery × 1, Atelier × 1 · Custom pigment', toStudio?.Subject);
if (toCustomer) {
    const m = await mailBody(toCustomer.ID);
    check('email 02: request number, both lamps in full, "No payment has been taken", Dorset Studio', m.HTML.includes(number) && /Atelier/.test(m.HTML) && /Smoked Glass LED \/ Dual Array/.test(m.HTML) && /Gallery/.test(m.HTML) && /Dorset Studio/.test(m.HTML) && !/Bournemouth/.test(m.HTML));
    const format = customerEmailProblems(m.HTML, 'Ada', "Thank you for your request. Your design is one of our larger sizes and includes a custom option, so we'll be in touch to develop it with you and to confirm lead time according to our casting schedule, including a bespoke delivery quote.");
    check('email 02 follows the email rules: Montage C, no heading, "Dear Ada," then the opening for a larger size with a custom option, one text size', format.length === 0, format.join('; '));
    check('email 02: then "Your casting slot is now secured. No payment has been taken."', m.HTML.includes('Your casting slot is now secured. No payment has been taken.'));
    check('email 02: prices as in the design (from price for Atelier)', /Starting at £1,699/.test(m.HTML) && /£799/.test(m.HTML));
    check('email 02: images hosted on the site', m.HTML.includes(BASE_URL + '/images/email/header-montage-c.jpg'));
    writeFileSync(out('email-02.html'), m.HTML);
}
if (toStudio) {
    const m = await mailBody(toStudio.ID);
    const format = studioEmailProblems(m.HTML, 'New request');
    const summary = m.HTML.match(/data-studio-summary>([\s\S]*?)<\/table>/)?.[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    check('notification: no greeting; "New request" first, then the summary: request, lamps, Custom, customer, no payment', format.length === 0 && new RegExp(`^Request ${number} · \\d+ \\w+ 2026, \\d\\d:\\d\\d Lamps Gallery × 1, Atelier × 1 Custom Pigment Customer Ada Test Payment None taken$`).test(summary ?? ''), format.join('; ') + ' | ' + summary);
    check('notification: replies go to the customer', (m.ReplyTo || []).some(r => r.Address === 'ada.test@example.test'));
    check('notification: every detail and a link to the request in admin', /1 High Street<br \/>\s*Wimborne<br \/>\s*Dorset<br \/>\s*BH21 1AA<br \/>\s*United Kingdom/.test(m.HTML) && /07700 900123/.test(m.HTML) && /Two flights of stairs/.test(m.HTML) && /RAL 7044/.test(m.HTML) && /\/admin\/design-request\//.test(m.HTML));
    writeFileSync(out('email-notification.html'), m.HTML);
}

// A Custom cable (no Custom pigment) also asks for Custom Details
await b.send('Network.clearBrowserCookies');
await fill('lumen-forma-studio', { 'The Pigment': 'White', 'Cable Colour': 'Custom Textile / Length', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED' });
await press();
check('a Custom cable alone shows the Custom Details box', (await page()).labels.some(l => l.startsWith('Custom Details')));

// From the basket: just the basket (a lamp held earlier is forgotten)
await b.send('Network.clearBrowserCookies');
await fill('lumen-forma-studio', { 'The Pigment': 'Grey', 'Cable Colour': 'Orange', 'Footing Material': 'Brushed Aluminium', 'Bulb Style': 'Tubular Amber LED' });
await press();
await send({ size: 'lumen-forma-estate', quantity: 1, mode: 'all', lamps: [{ 'The Pigment': 'Black', 'Cable Colour': 'Orange', 'Footing Material': 'Brushed Aluminium', 'Bulb Style': 'Tubular Amber LED', 'Bulb Count': 'Single Array' }] });
check('Estate reserved: form shows the basket Studio and the Estate', JSON.stringify((await page()).rows.filter(r => r.startsWith('Size'))) === JSON.stringify(['Size=Studio', 'Size=Estate']));
sql("update cart_item set quantity = 3 where id = (select id from cart_item order by created_at desc limit 1)");
await go(BASE_URL + '/basket');
const link = await ev(`[...document.querySelectorAll('main a')].find(a => a.textContent.trim() === 'Reserve Your Design')?.getAttribute('href')`);
check('the basket\'s Reserve Your Design link sends just the basket', link === '/reserve?basket=1', link);
await go(BASE_URL + '' + link); p = await page();
check('from the basket: only the basket\'s lamps', JSON.stringify(p.rows.filter(r => r.startsWith('Size') || r.startsWith('Quantity'))) === JSON.stringify(['Size=Studio', 'Quantity=3']), JSON.stringify(p.rows));
check('no Custom choice: no Custom Details box', !p.labels.some(l => l.startsWith('Custom Details')));

// Editing a basket line into a Request size: the edited version replaces the line
const edit = await ev(`[...document.querySelectorAll('a')].find(a => a.textContent.trim() === 'Edit configuration')?.getAttribute('href')`) || (await (async () => { await go(BASE_URL + '/basket'); return ev(`[...document.querySelectorAll('a')].find(a => a.textContent.trim() === 'Edit configuration')?.getAttribute('href')`); })());
await send({ size: 'lumen-forma-monument', quantity: 3, mode: 'all', lamps: [{ 'The Pigment': 'Grey', 'Cable Colour': 'Orange', 'Footing Material': 'Brushed Aluminium', 'Bulb Style': 'Tubular Amber LED', 'Bulb Count': 'Triple Array' }] }, BASE_URL + edit); p = await page();
check('editing a line into Monument: the request shows the edited lamp in place of the line', JSON.stringify(p.rows.filter(r => r.startsWith('Size') || r.startsWith('Quantity'))) === JSON.stringify(['Size=Monument', 'Quantity=3']), JSON.stringify(p.rows));
await go(BASE_URL + '/basket');
check('the basket line is unchanged until the request is sent', /Size\s+Studio/i.test(await ev(`document.querySelector('main').innerText`)));

await go(BASE_URL + '/reserve/received');
check('/reserve/received without a request just sent goes to Lighting Designs', (await ev('location.pathname')) === '/lighting-designs');

console.log('REQUEST', number);
console.log(ok ? 'ALL PASSED' : 'SOME CHECKS FAILED');
b.close();
