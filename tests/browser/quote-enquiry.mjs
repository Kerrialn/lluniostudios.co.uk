// Step 7c.4: an enquiry ("Begin the Conversation") quoted and paid, end to
// end. Admin's quote page for an enquiry ("Quote for enquiry LR-…", what they
// told you, the delivery estimate from the postcode) with the gift section
// (its messages; a gift sent directly saved on the enquiry); the quote email
// and the quote page in the "me" voice, calling it an "enquiry", signed exactly
// "All the best, / Theo / @LlunioStuidos" (decided 13 October 2026), with the
// gift; the quote page asking the full address (only the postcode was given),
// the postcode kept as quoted; the deposit paid by card; the order (the gift,
// direct to the recipient, carried as a reservation's), email 01 and the
// studio's email; the gift section gone once paid; the balance requested
// (in the "me" voice, signed, naming the enquiry) and paid. Signs in to admin;
// uses Stripe test mode; clears the enquiry limit before and after.
//   node tests/browser/quote-enquiry.mjs
import { execSync } from 'node:child_process';
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL, PROJECT_DIR, STUDIO_EMAIL, customerEmailProblems, studioEmailProblems } from './lib.mjs';

const { check, summary } = checker();
const EMAIL = 'ada.quote.enquiry@example.test';
const ADMIN = 'lluniostudios@gmail.com';
const SIGNATURE = ['All the best,', 'Theo\n@LlunioStuidos'];
const resetLimit = () => execSync('docker compose exec -T php php bin/console cache:pool:clear cache.rate_limiter 2>/dev/null', { cwd: PROJECT_DIR });
const mailpit = async (query) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json()).messages || [];
const message = async (id) => (await fetch(`${MAILPIT_URL}/api/v1/message/${id}`)).json();
const uuid = (text) => text.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)?.[0];
const text = (html) => html.replace(/<style[\s\S]*?<\/style>/g, ' ').replace(/<br\s*\/?>/g, '\n').replace(/<[^>]+>/g, ' ').replace(/&#039;/g, "'").replace(/&amp;/g, '&').replace(/&nbsp;/g, ' ').replace(/[ \t]+/g, ' ').replace(/\s*\n\s*/g, '\n').trim();
const signatureOf = (html) => { const m = html.match(/<div data-email-signature>([\s\S]*?)<\/div>/); return m ? [...m[1].matchAll(/<p [^>]*>([\s\S]*?)<\/p>/g)].map((p) => p[1].replace(/<br\s*\/?>\s*/g, '\n').trim()) : null; };
const ukDate = (days) => { const d = new Date(Date.now() + days * 86400000); return d.toLocaleDateString('en-CA', { timeZone: 'Europe/London' }); };
const longDate = (iso) => new Date(iso + 'T12:00:00Z').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Europe/London' });
const b = await open(1440, 1000, 'quote-enquiry-admin', { mobile: false });
let c = null;
try {
    const go = async (x, url, wait = 2500) => { await x.send('Page.navigate', { url }); await sleep(wait); await x.ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`); };
    sql(`with o as (select id, shipping_address_id, billing_address_id from orders where email = '${EMAIL}'), i as (delete from order_item where order_id in (select id from o) returning 1), d as (delete from orders where id in (select id from o) returning shipping_address_id, billing_address_id) delete from address where id in (select shipping_address_id from d union select billing_address_id from d)`);
    sql(`delete from design_request where email = '${EMAIL}'`);
    resetLimit();
    await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');

    // The enquiry: Estate × 2, a video call, a postcode only.
    await go(b, BASE_URL + '/lighting-designs');
    await b.ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
    await b.ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-estate', quantity: 2, mode: 'all', lamps: [{}], finishes: 'together' }))}; f.requestSubmit(); })()`); await sleep(2500);
    await b.ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify({ fullName: 'Ada Lovelace', email: EMAIL, phone: '07700 900123', bestTime: 'Mornings', description: 'A tall entrance hall.', postcode: 'bh21 1aa' })})) { const e = document.querySelector('[name="enquiry_form[' + k + ']"]'); e.value = v; } document.querySelector('[name="enquiry_form[talk]"][value=video]').click(); document.querySelector('form[name=enquiry_form]').requestSubmit(); })()`); await sleep(4000);
    const number = sql(`select request_number from design_request where email = '${EMAIL}'`).match(/LR-\d{6}-\d{4}/)?.[0];
    const id = uuid(sql(`select id from design_request where request_number = '${number}'`));
    check('an enquiry sent: Estate × 2, a video call, postcode BH21 1AA', !!number && (await b.ev('location.pathname')) === '/begin-the-conversation/received', number);

    // Admin.
    await go(b, BASE_URL + '/login');
    await b.ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
    let code;
    for (let i = 0; i < 20 && !code; i++) {
        const m = (await mailpit(`to:${ADMIN} subject:"Sign in to Llunio Studios"`))[0];
        if (m) code = ((await message(m.ID)).Text || '').match(/\b\d{6}\b/)?.[0];
        if (!code) await sleep(500);
    }
    await b.ev(`(() => { const h = document.querySelector('[data-pin-code-target=hidden]'); h.value = '${code}'; h.form.requestSubmit(); })()`); await sleep(3000);
    const quoteUrl = `${BASE_URL}/admin/design-request/${id}/quote`;
    await go(b, quoteUrl, 3500);
    const admin = await b.ev(`({ title: document.querySelector('h1')?.innerText.trim(), back: [...document.querySelectorAll('a.btn')].map(a => a.textContent.trim()).find(t => /^Back/.test(t)), card: document.querySelector('.card-body')?.innerText.replace(/\\s+/g, ' '), estimate: document.querySelector('.alert.small')?.innerText.replace(/\\s+/g, ' '), lamp: document.querySelector('[data-quote-line]')?.innerText.replace(/\\s+/g, ' '), price: document.querySelector('[data-quote-line] [data-quote-price]')?.value, gift: !!document.querySelector('[data-quote-gift-section]') })`);
    check('admin: "Quote for enquiry LR-…", "Back to the enquiry", the Enquiry card with how to talk and their space', admin.title === `Quote for enquiry ${number}` && admin.back === 'Back to the enquiry' && /^Enquiry Ada Lovelace/i.test(admin.card ?? '') && /How to talk: Video call · Mornings/.test(admin.card) && /Their space: A tall entrance hall\./.test(admin.card) && /BH21 1AA/.test(admin.card), JSON.stringify(admin));
    check('the piece: Estate × 2, finishes to be chosen together, priced from its size (£3,499 each); a delivery estimate from the postcode', /Lumen Forma Estate/.test(admin.lamp) && /Finishes: to be chosen together/.test(admin.lamp) && admin.price.replace(',', '') === '3499.00' && /Estimate: £/.test(admin.estimate ?? ''), JSON.stringify([admin.lamp, admin.price, admin.estimate]));
    check('a "Gift" section (the page no longer asks)', admin.gift);

    // The gift section's messages.
    const errors = () => b.ev(`[...document.querySelectorAll('[data-quote-gift-section] .invalid-feedback, [data-quote-gift-section] .form-error-message')].map(e => e.textContent.trim()).filter(Boolean)`);
    await b.ev(`document.querySelector('[name$="[gift]"]').click(); document.querySelector('form#quote-form button[type=submit]:not([name])').click()`); await sleep(3500);
    check('"This is a gift" alone: "Please choose a date to continue." and "Please choose where to send it."', JSON.stringify(await errors()) === JSON.stringify(['Please choose a date to continue.', 'Please choose where to send it.']), JSON.stringify(await errors()));
    await b.ev(`(() => { document.querySelector('[name$="[giftNeededBy]"]').value = ${JSON.stringify(ukDate(-1))}; document.querySelector('[name$="[giftSendTo]"][value=recipient]').click(); document.querySelector('form#quote-form button[type=submit]:not([name])').click(); })()`); await sleep(3500);
    check('a past date and "Send directly" without a name: refused', JSON.stringify(await errors()) === JSON.stringify(["The needed-by date can't be in the past.", "Please enter the recipient's full name."]), JSON.stringify(await errors()));
    check('nothing saved on the enquiry while refused', /\bf\b|false/.test(sql(`select gift::text from design_request where id = '${id}'`)));

    // A gift sent directly, and the quote: £3,600 each, 50% deposit, a note.
    const neededBy = ukDate(40);
    const casting = ukDate(30);
    await b.ev(`(() => {
        document.querySelector('[name$="[giftNeededBy]"]').value = ${JSON.stringify(neededBy)};
        document.querySelector('[name$="[giftRecipientName]"]').value = 'Grace Hopper';
        document.querySelector('[name$="[giftRecipientPhone]"]').value = '07700 900456';
        document.querySelector('[name$="[giftMessage]"]').value = 'Happy birthday, Grace.';
        document.querySelector('[data-quote-line] [data-quote-price]').value = '3600';
        document.querySelector('[data-quote-delivery]').value = '120';
        document.querySelector('[name$="[deliveryLabel]"]').value = 'Palletways delivery';
        document.querySelector('input[name$="[payment]"][value="deposit"]').click();
        document.querySelector('[data-quote-deposit]').value = '50';
        document.querySelector('[name$="[castingDate]"]').value = ${JSON.stringify(casting)};
        document.querySelector('[name$="[note]"]').value = 'Lovely to talk this morning.';
        window.confirm = () => true;
        document.querySelector('[data-quote-send]').click();
    })()`); await sleep(4500);
    const saved = sql(`select gift::text, gift_needed_by, gift_send_to, gift_recipient_name, gift_recipient_phone, gift_message, status from design_request where id = '${id}'`).replace(/\s+/g, ' ');
    check('the gift saved on the enquiry (needed by, direct, Grace Hopper, her mobile, the message) and the enquiry Quoted', saved.includes(`true ${neededBy} recipient Grace Hopper 07700 900456 Happy birthday, Grace. quoted`), saved);
    await go(b, quoteUrl, 3000);
    const kept = await b.ev(`({ ticked: document.querySelector('[name$="[gift]"]').checked, date: document.querySelector('[name$="[giftNeededBy]"]').value, direct: document.querySelector('[name$="[giftSendTo]"][value=recipient]').checked, name: document.querySelector('[name$="[giftRecipientName]"]').value, summary: document.querySelector('[data-quote-gift]')?.innerText.replace(/\\s+/g, ' ') })`);
    check('the quote page in admin keeps the gift, and shows it in the Enquiry card (direct to recipient)', kept.ticked && kept.date === neededBy && kept.direct && kept.name === 'Grace Hopper' && /Direct to recipient: no prices in the box/.test(kept.summary ?? ''), JSON.stringify(kept));

    // The quote email.
    let quoteMail = null;
    for (let i = 0; i < 20 && !quoteMail; i++) { quoteMail = (await mailpit(`to:${EMAIL} subject:"Your Lumen Forma quote"`))[0]; if (!quoteMail) await sleep(500); }
    const quoteHtml = quoteMail ? (await message(quoteMail.ID)).HTML : '';
    const qt = text(quoteHtml);
    const opening = `Thank you for your patience. Here is my quote for your Lumen Forma enquiry ${number}.`;
    const quoteFormat = customerEmailProblems(quoteHtml, 'Ada', opening);
    check('the quote email: the email rules, "Dear Ada," then "Here is my quote for your Lumen Forma enquiry LR-…" (the "me" voice)', !!quoteMail && quoteFormat.length === 0, quoteFormat.join('; '));
    check('…then the note, then the signature exactly as the enquiry email, before 01', qt.includes(`${opening}\nLovely to talk this morning.\nAll the best,\nTheo\n@LlunioStuidos\n01 Your configuration`) && JSON.stringify(signatureOf(quoteHtml)) === JSON.stringify(SIGNATURE), qt.slice(0, 500));
    check('…no "our quote" or "request"; the gift (needed by, sent directly to Grace Hopper)', !/\bour quote|\brequest\b/i.test(qt.split('\nLlunio Studios\ninfo@')[0]) && qt.includes(`Needed by ${longDate(neededBy)}`) && /Send directly to the recipient/.test(qt), qt.slice(qt.indexOf('Casting') - 200, qt.indexOf('Casting') + 300));
    check('…replies go to the studio', ((await message(quoteMail?.ID ?? '')).ReplyTo || []).some((r) => r.Address === STUDIO_EMAIL));
    const link = quoteHtml.match(/<a href="([^"]+\/quote\/[A-Za-z0-9_-]+)"/)?.[1].replace(/^https?:\/\/[^/]+/, BASE_URL);

    // The customer's quote page.
    c = await open(1440, 1000, 'quote-enquiry-customer', { mobile: false });
    await c.send('Network.enable'); await c.send('Network.clearBrowserCookies');
    await go(c, link, 2500);
    for (let i = 0; i < 30 && (await c.ev(`document.querySelectorAll('.ll-card-box iframe').length`)) < 3; i++) await sleep(500);
    await sleep(1500);
    const page = await c.ev(`({ ref: document.querySelector('[data-quote-reference]')?.textContent.trim(), greeting: document.querySelector('[data-quote-greeting]')?.textContent.trim(), signature: [...document.querySelectorAll('[data-quote-signature] p')].map(p => p.innerText.trim()), button: document.querySelector('main aside button[type=submit]')?.textContent.trim(), country: [...document.querySelectorAll('main span')].find(s => s.textContent === 'Country')?.nextElementSibling?.textContent.trim() })`);
    check('the quote page: "Enquiry LR-…", "Dear Ada, here is my quote for your Lumen Forma enquiry. It is valid until …", signed exactly', page.ref === `Enquiry ${number}` && /^Dear Ada, here is my quote for your Lumen Forma enquiry\. It is valid until \d+ \w+ 2026\.$/.test(page.greeting ?? '') && JSON.stringify(page.signature) === JSON.stringify(SIGNATURE), JSON.stringify(page));
    const field = (name) => c.ev(`document.querySelector('[name="card_payment_form[${name}]"]')?.value`);
    const filled = { email: await field('email'), phone: await field('phone'), name: await field('name'), line1: await field('line1'), town: await field('town'), postcode: await field('postcode'), giftMessage: await field('giftMessage'), recipientPhone: await field('recipientPhone'), billingSame: await c.ev(`document.querySelector('[name="card_payment_form[billingSame]"]')?.checked`) };
    check('the full address asked (only the postcode given): the recipient as the delivery name, the postcode, the gift message and her mobile; billing not the same (a direct gift)', JSON.stringify(filled) === JSON.stringify({ email: EMAIL, phone: '07700 900123', name: 'Grace Hopper', line1: '', town: '', postcode: 'BH21 1AA', giftMessage: 'Happy birthday, Grace.', recipientPhone: '07700 900456', billingSame: false }), JSON.stringify(filled));
    const total = 3600 * 2 + 120;
    check(`"Pay £${(total / 2).toLocaleString('en-GB')}" (the 50% deposit), the country United Kingdom`, page.button === `Pay £${(total / 2).toLocaleString('en-GB')}` && page.country === 'United Kingdom', JSON.stringify(page));

    // Paying the deposit by card.
    const card = async (box, value) => {
        for (let attempt = 0; attempt < 3; attempt++) {
            const [x, y] = await c.ev(`(() => { const b = document.querySelector('[data-checkout-target=${box}]'); b.scrollIntoView({ block: 'center' }); const q = b.getBoundingClientRect(); return [q.left + 60, q.top + q.height / 2]; })()`);
            await sleep(400);
            await c.send('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', clickCount: 1 });
            await c.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x, y, button: 'left', clickCount: 1 });
            await sleep(500);
            await c.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'a', code: 'KeyA', commands: ['selectAll'] });
            await c.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'a', code: 'KeyA' });
            await c.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Backspace', code: 'Backspace', windowsVirtualKeyCode: 8 });
            await c.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Backspace', code: 'Backspace', windowsVirtualKeyCode: 8 });
            await c.send('Input.insertText', { text: value });
            await sleep(800);
            if (await c.ev(`document.querySelector('[data-checkout-target=${box}]').classList.contains('StripeElement--complete')`)) return;
        }
    };
    const pay = async (wait = 25000) => {
        const start = await c.ev('location.pathname');
        await c.ev(`document.querySelector('main aside button[type=submit]').click()`);
        for (let i = 0; i < wait / 500; i++) {
            await sleep(500);
            const s = await c.ev(`({ path: location.pathname, message: document.querySelector('[data-checkout-target=message]')?.hidden === false ? document.querySelector('[data-checkout-target=message]').textContent.trim() : '', under: [...document.querySelectorAll('[data-field-error]')].map(e => e.textContent.trim()), busy: !!document.querySelector('main aside button[type=submit]')?.disabled })`).catch(() => null);
            if (s && (s.path !== start || ((s.message || s.under.length) && !s.busy))) return s;
        }
        return c.ev(`({ path: location.pathname })`);
    };
    await card('cardNumber', '4242424242424242'); await card('cardExpiry', '1234'); await card('cardCvc', '123');
    const fill = (values) => c.ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(values)})) { const e = document.querySelector('[name="card_payment_form[' + k + ']"]'); e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); } })()`);
    await fill({ line1: '2 Church Lane', town: 'Wimborne', county: 'Dorset', cardName: 'Ada Lovelace', billingName: 'Ada Lovelace', billingLine1: '1 High Street', billingTown: 'Wimborne', billingPostcode: 'BH21 1AB' });
    await fill({ postcode: 'SW1A 1AA' });
    let s = await pay(15000);
    check('another postcode refused: "Delivery was quoted for BH21 1AA…"', (s.under || []).some((e) => e.startsWith('Delivery was quoted for BH21 1AA.')), JSON.stringify(s));
    await fill({ postcode: 'BH21 1AA' });
    const since = new Date();
    s = await pay();
    const done = await c.ev(`document.querySelector('main').innerText.replace(/\\s+/g, ' ')`);
    check(`paid: "Order & Slot Confirmed", "Paid · £${(total / 2).toLocaleString('en-GB')}"`, /\/complete$/.test(s.path ?? '') && /Order & Slot Confirmed/i.test(done) && done.includes(`Paid · £${(total / 2).toLocaleString('en-GB')}`), done.slice(0, 300));
    const order = sql(`select o.order_number, o.status, o.total, o.amount_paid, o.gift::text, o.gift_send_to, o.gift_message, o.gift_recipient_phone, o.customer_name, r.status as request_status from orders o join design_request r on r.id = o.design_request_id where o.design_request_id = '${id}' and o.status = 'deposit_paid'`).replace(/\s+/g, ' ');
    const orderNumber = order.match(/LS-\d{6}-\d{4}/)?.[0];
    check('the order: Deposit Paid, the gift carried (direct, the message, her mobile), the buyer Ada Lovelace, the enquiry Deposit Paid', !!orderNumber && order.includes(`deposit_paid ${total * 100} ${total * 50} true recipient Happy birthday, Grace. 07700 900456 Ada Lovelace deposit_paid`), order);
    const address = sql(`select a.recipient_name, a.line1, a.city, a.postcode, a.country from orders o join address a on a.id = o.shipping_address_id where o.design_request_id = '${id}' and o.status = 'deposit_paid'`).replace(/\s+/g, ' ');
    check('delivered to Grace Hopper at the address typed on the quote page, the quoted postcode', /Grace Hopper 2 Church Lane Wimborne BH21 1AA GB/.test(address), address);

    // Email 01 and the studio's email.
    await sleep(1500);
    const confirmation = (await mailpit(`to:${EMAIL}`)).filter((m) => new Date(m.Created) > since && /Your Llunio Studios order/.test(m.Subject))[0];
    const ct = text(confirmation ? (await message(confirmation.ID)).HTML : '');
    check('email 01 for the enquiry\'s order: the gift sent directly with its message (unchanged for every order)', !!confirmation && /Send directly to the recipient/.test(ct) && /Happy birthday, Grace\./.test(ct) && /Finishes To be chosen together/.test(ct), ct.slice(ct.indexOf('03 Delivery'), ct.indexOf('03 Delivery') + 300));
    let studio = null;
    for (const m of await mailpit('to:lluniostudios@gmail.com subject:"Deposit paid"')) if (new Date(m.Created) > since && (await message(m.ID)).HTML.includes(orderNumber)) { studio = await message(m.ID); break; }
    check('the studio\'s "Deposit paid · Estate × 2 · … · GIFT by … · DIRECT", "Direct to recipient: no prices in the box", the message', !!studio && /^Deposit paid · Estate × 2 · .+ · GIFT by .+ · DIRECT$/.test(studio.Subject) && studioEmailProblems(studio.HTML, 'Deposit paid').length === 0 && /data-studio-direct/.test(studio.HTML) && /Happy birthday, Grace\./.test(studio.HTML), studio?.Subject);

    // Paid: no gift section any more.
    await go(b, quoteUrl, 3000);
    check('once paid, admin\'s quote page has no gift section (the order keeps its gift); the gift still shown', !(await b.ev(`!!document.querySelector('[data-quote-gift-section]')`)) && /Direct to recipient/.test(await b.ev(`document.querySelector('[data-quote-gift]')?.innerText ?? ''`)));

    // The balance: requested, signed, naming the enquiry.
    const orderId = uuid(sql(`select id from orders where order_number = '${orderNumber}'`));
    await go(b, `${BASE_URL}/admin/order/${orderId}/request-balance`, 3000);
    await b.ev(`(() => { document.querySelector('[data-balance-note-input]').value = 'Your Estates are cured and ready.'; document.querySelector('[data-request-balance]').click(); })()`); await sleep(3500);
    let balanceMail = null;
    for (let i = 0; i < 20 && !balanceMail; i++) { balanceMail = (await mailpit(`to:${EMAIL} subject:"Your Lumen Forma balance"`))[0]; if (!balanceMail) await sleep(500); }
    const balanceHtml = balanceMail ? (await message(balanceMail.ID)).HTML : '';
    const bt = text(balanceHtml);
    // Only the order number (14 October 2026).
    const balanceOpening = `Here is the balance for your Lumen Forma order ${orderNumber}.`;
    const balanceFormat = customerEmailProblems(balanceHtml, 'Ada', balanceOpening);
    check('the balance request: the email rules, "Dear Ada," then "…order LS-…." (one number, no enquiry reference)', !!balanceMail && balanceFormat.length === 0 && !bt.includes(number), balanceFormat.join('; '));
    check('…the note, then the signature exactly, before 01; no "we" or "us"', bt.includes(`${balanceOpening}\nYour Estates are cured and ready.\nAll the best,\nTheo\n@LlunioStuidos\n01 Balance`) && JSON.stringify(signatureOf(balanceHtml)) === JSON.stringify(SIGNATURE) && !/\b(we|we'll|us|our)\b/i.test(bt.split('\nLlunio Studios\ninfo@')[0]), bt.slice(0, 500));

    // Paying the balance.
    await go(c, link, 2500);
    for (let i = 0; i < 30 && (await c.ev(`document.querySelectorAll('.ll-card-box iframe').length`)) < 3; i++) await sleep(500);
    await sleep(1500);
    check('the quote page then offers the balance, with no signature (only with the quote\'s greeting)', (await c.ev(`document.querySelector('main aside button[type=submit]')?.textContent.trim()`)) === `Pay balance £${(total / 2).toLocaleString('en-GB')}` && !(await c.ev(`!!document.querySelector('[data-quote-signature]')`)));
    await card('cardNumber', '4242424242424242'); await card('cardExpiry', '1234'); await card('cardCvc', '123');
    await c.ev(`document.querySelector('main aside button[type=submit]').click()`);
    for (let i = 0; i < 50 && !/\/balance\/complete$/.test(await c.ev('location.pathname').catch(() => '')); i++) await sleep(500);
    await sleep(1500);
    const finished = sql(`select o.status, o.amount_paid, r.status as request_status from orders o join design_request r on r.id = o.design_request_id where o.order_number = '${orderNumber}'`).replace(/\s+/g, ' ');
    check('the balance paid: the order and the enquiry Paid, paid in full', finished.includes(`paid ${total * 100} paid`), finished);
    summary();
} finally {
    resetLimit();
    b.close(); c?.close();
}
