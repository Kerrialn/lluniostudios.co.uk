// Step 8: Reserve Your Design, end to end.
import { open, sleep, sql, out, BASE_URL, MAILPIT_URL } from './lib.mjs';
import { writeFileSync } from 'node:fs';
const b = await open(1440, 900, 'reserve');
let ok = true;
const check = (label, pass, detail = '') => { if (!pass) ok = false; console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`); };
const ev = b.ev;
const mailpit = async (q) => (await (await fetch(MAILPIT_URL + '/api/v1/search?query=' + encodeURIComponent(q))).json()).messages || [];
const mailBody = async (id) => (await (await fetch(MAILPIT_URL + '/api/v1/message/' + id)).json());
const go = async (url, wait = 1800) => { await b.send('Page.navigate', { url }); await sleep(wait); };
const reveal = async () => { await b.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 }); await sleep(1300); };
const pick = (g, v) => ev(`document.querySelector('.ll-cfg-opt[data-group="${g}"][data-value="${v}"]').click()`);
const jump = (i) => ev(`document.querySelectorAll('.ll-cfg-step')[${i}].click()`);
const press = async () => { await ev(`document.querySelector('[data-configurator-target=cta]').click()`); await sleep(2200); };
const fill = async (size, v) => { await go(BASE_URL + '/lighting-designs'); await reveal(); await pick('size', size); for (const [s, g] of [[1, 'The Pigment'], [2, 'Cable Colour'], [3, 'Footing Material'], [4, 'Bulb Style'], [4, 'Bulb Count']]) { if (v[g]) { await jump(s); await pick(g, v[g]); } } await jump(5); };
const page = () => ev(`({ path: location.pathname, h1: document.querySelector('h1')?.textContent.trim(), text: document.querySelector('main').innerText, labels: [...document.querySelectorAll('form label')].map(l => l.textContent.replace(/\\s+/g, ' ').trim()), errors: [...document.querySelectorAll('form [role=alert]')].map(e => e.textContent.trim()), rows: [...document.querySelectorAll('aside .flex.justify-between')].map(r => r.children[0].textContent + '=' + r.children[1].textContent) })`);
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
await fill('lumen-forma-atelier', { 'The Pigment': 'Custom', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED', 'Bulb Count': 'Dual Array' });
check('the button reads Reserve Your Design', (await ev(`document.querySelector('[data-configurator-target=cta]').textContent`)) === 'Reserve Your Design');
await press();
let p = await page();
check('Reserve Your Design opens the request form', p.path === '/reserve' && p.h1 === 'Reserve Your Design' && !/Casting Slot/.test(p.text), p.path + ' ' + p.h1);
check('it says no payment is taken', /No payment is taken today/.test(p.text));
check('it carries the basket lamp and the lamp being configured, each in full', JSON.stringify(p.rows) === JSON.stringify(['Edition=Gallery', 'The Pigment=White', 'Cable Colour=Slate Grey', 'Footing Material=Brushed Copper', 'Bulb Style=Crown Gold LED', 'Quantity=1', 'Edition=Atelier', 'The Pigment=Custom', 'Cable Colour=Raw Jute', 'Footing Material=Polished Copper', 'Bulb Style / Count=Smoked Glass LED / Dual Array', 'Quantity=1']), JSON.stringify(p.rows));
check('the prototype\'s fields, with Custom Details for a Custom choice', JSON.stringify(p.labels) === JSON.stringify(['Full Name *', 'Email Address * (For sending the invoice and build schedule)', 'Phone Number * (Required by carriers for heavy-goods delivery scheduling)', 'Delivery Address & Postcode * (Required to calculate exact delivery costs)', "Site Access & Delivery Notes (e.g. 'Are there stairs, narrow access, or delivery constraints for heavy items?')", "Custom Details (Describe what you'd like for each custom option, e.g. target tone or Pantone/RAL code, cable textile or length, footing finish, bulb specification)"]), JSON.stringify(p.labels));
check('mixed sizes: Image Coming Soon, never another size\'s photo', /Image Coming Soon/i.test(await ev(`document.querySelector('aside').innerText`)) && !(await ev(`!!document.querySelector('aside img')`)));
check('the basket still holds its lamp until the request is sent', /Basket \(1\)/i.test(await ev(`document.querySelector('#navbar').innerText`)));
await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);
let s = await b.send('Page.captureScreenshot', { captureBeyondViewport: true, clip: { x: 0, y: 0, width: 1440, height: await ev('document.documentElement.scrollHeight'), scale: 1 } }); writeFileSync(out('reserve-form-1440.png'), Buffer.from(s.result.data, 'base64'));

// Validation
await submit(); p = await page();
check('sending it empty shows each required field\'s message and saves nothing', p.path === '/reserve' && ['Please enter your full name.', 'Please enter your email address.', 'Please enter your phone number.', 'Please enter your delivery address and postcode.'].every(m => p.errors.includes(m)) && requests() === before, JSON.stringify(p.errors));
await setField('fullName', 'Ada Test'); await setField('email', 'not-an-email'); await setField('phone', '07700 900123'); await setField('deliveryAddress', '1 High Street, Wimborne'); await submit(); p = await page();
check('a bad email and an address without a postcode are refused', p.errors.includes('Please enter a valid email address.') && p.errors.includes('Please include your postcode.'), JSON.stringify(p.errors));
check('what was typed is kept', (await ev(`document.querySelector('[name="reserve_form[fullName]"]').value`)) === 'Ada Test');

// Send it
await setField('email', 'ada.test@example.test'); await setField('deliveryAddress', '1 High Street\nWimborne\nBH21 1AA'); await setField('accessNotes', 'Two flights of stairs.'); await setField('customDetails', 'Warm grey, close to RAL 7044.');
await submit(); p = await page();
check('sending it shows "Reservation received": heading, the message, the confirmation line, no request number', p.path === '/reserve/received' && /^Reservation Received/i.test(p.text) && /Your design request has been received/.test(p.text) && /Confirmation has been sent to the email address provided\./.test(p.text) && !/LR-\d/.test(p.text), p.text.slice(0, 160));
const number = (sql(`select request_number from design_request where email = 'ada.test@example.test' order by created_at desc limit 1`).match(/LR-\d{8}-[0-9A-F]{6}/) || [])[0];
check('one request saved', requests() === before + 1);
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
const toStudio = (await mailpit(`to:lluniostudios@gmail.com subject:"${number}"`))[0];
check('email 02 sent to the customer', !!toCustomer);
check('notification sent to lluniostudios@gmail.com', !!toStudio && toStudio.Subject === `New request ${number} from Ada Test`, toStudio?.Subject);
if (toCustomer) {
    const m = await mailBody(toCustomer.ID);
    check('email 02: request number, both lamps in full, "No payment has been taken", Dorset Studio', m.HTML.includes(number) && /Atelier/.test(m.HTML) && /Smoked Glass LED \/ Dual Array/.test(m.HTML) && /Gallery/.test(m.HTML) && /Dorset Studio/.test(m.HTML) && !/Bournemouth/.test(m.HTML));
    check('email 02: the opening for a larger size with a custom option, then "Your casting slot is now secured."', m.HTML.includes("Dear Ada Test, thank you for your request. Your design is one of our larger sizes and includes a custom option, so we&#039;ll be in touch to develop it with you and to confirm lead time according to our casting schedule, including a bespoke delivery quote.") && m.HTML.includes('Your casting slot is now secured. No payment has been taken.'));
    check('email 02: prices as in the design (from price for Atelier)', /Starting at £1,699/.test(m.HTML) && /£799/.test(m.HTML));
    check('email 02: images hosted on the site', m.HTML.includes(BASE_URL + '/images/email/header-montage.jpg'));
    writeFileSync(out('email-02.html'), m.HTML);
}
if (toStudio) {
    const m = await mailBody(toStudio.ID);
    check('notification: replies go to the customer', (m.ReplyTo || []).some(r => r.Address === 'ada.test@example.test'));
    check('notification: every detail and a link to the request in admin', /07700 900123/.test(m.HTML) && /Two flights of stairs/.test(m.HTML) && /RAL 7044/.test(m.HTML) && /\/admin\/design-request\//.test(m.HTML));
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
await fill('lumen-forma-estate', { 'The Pigment': 'Black', 'Cable Colour': 'Orange', 'Footing Material': 'Brushed Aluminium', 'Bulb Style': 'Tubular Amber LED', 'Bulb Count': 'Single Array' });
await press();
check('Estate reserved: form shows the basket Studio and the Estate', JSON.stringify((await page()).rows.filter(r => r.startsWith('Edition'))) === JSON.stringify(['Edition=Studio', 'Edition=Estate']));
sql("update cart_item set quantity = 3 where id = (select id from cart_item order by created_at desc limit 1)");
await go(BASE_URL + '/basket');
const link = await ev(`[...document.querySelectorAll('main a')].find(a => a.textContent.trim() === 'Reserve Your Design')?.getAttribute('href')`);
check('the basket\'s Reserve Your Design link sends just the basket', link === '/reserve?basket=1', link);
await go(BASE_URL + '' + link); p = await page();
check('from the basket: only the basket\'s lamps', JSON.stringify(p.rows.filter(r => r.startsWith('Edition') || r.startsWith('Quantity'))) === JSON.stringify(['Edition=Studio', 'Quantity=3']), JSON.stringify(p.rows));
check('no Custom choice: no Custom Details box', !p.labels.some(l => l.startsWith('Custom Details')));

// Editing a basket line into a Request size: the edited version replaces the line
const edit = await ev(`[...document.querySelectorAll('a')].find(a => a.textContent.trim() === 'Edit configuration')?.getAttribute('href')`) || (await (async () => { await go(BASE_URL + '/basket'); return ev(`[...document.querySelectorAll('a')].find(a => a.textContent.trim() === 'Edit configuration')?.getAttribute('href')`); })());
await go(BASE_URL + '' + edit, 2500); await jump(0); await pick('size', 'lumen-forma-monument'); await jump(4); await pick('Bulb Count', 'Triple Array'); await jump(5); await press(); p = await page();
check('editing a line into Monument: the request shows the edited lamp in place of the line', JSON.stringify(p.rows.filter(r => r.startsWith('Edition') || r.startsWith('Quantity'))) === JSON.stringify(['Edition=Monument', 'Quantity=3']), JSON.stringify(p.rows));
await go(BASE_URL + '/basket');
check('the basket line is unchanged until the request is sent', /Lumen Forma Studio/i.test(await ev(`document.querySelector('main').innerText`)));

await go(BASE_URL + '/reserve/received');
check('/reserve/received without a request just sent goes to Lighting Designs', (await ev('location.pathname')) === '/lighting-designs');

console.log('REQUEST', number);
console.log(ok ? 'ALL PASSED' : 'SOME CHECKS FAILED');
b.close();
