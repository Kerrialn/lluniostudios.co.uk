// Paying a quote (Step 10b.3): the quote page has the Card Payment page's
// fields filled in from the request, card details and "Pay £x" (the deposit);
// a different postcode is refused (delivery was quoted for the request's);
// paying by card (Stripe's test card, typed into Stripe's boxes) makes the
// order: the quote's lines, delivery and total, the deposit paid and the
// balance owed, the order and request now Deposit Paid, linked to the account for the
// email; "Order Confirmed" shows what was paid and is owed; the quote
// page then shows the payment instead of the form; the order email and the
// order in admin show paid and owed. A full-payment quote, paid as Apple Pay /
// Google Pay would (the page's fields sent with ?wallet=1), is paid in full
// (the order and request Paid).
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL, customerEmailProblems, studioEmailProblems } from './lib.mjs';

const { check, summary } = checker();
const b = await open(1440, 1000, 'quote-pay-admin', { mobile: false });
let c = null;
const EMAIL = 'ada.pay@example.test';
const ADMIN = 'lluniostudios@gmail.com';
const mailpit = async (query) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json()).messages || [];
const message = async (id) => (await fetch(`${MAILPIT_URL}/api/v1/message/${id}`)).json();
const uuid = (text) => text.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)?.[0];
try {
    const go = async (x, url, wait = 2500) => { await x.send('Page.navigate', { url }); await sleep(wait); };
    // Start from nothing for this test email.
    sql(`with o as (select id, shipping_address_id, billing_address_id from orders where email = '${EMAIL}'), i as (delete from order_item where order_id in (select id from o) returning 1), d as (delete from orders where id in (select id from o) returning shipping_address_id, billing_address_id) delete from address where id in (select shipping_address_id from d union select billing_address_id from d)`);
    sql(`delete from design_request where email = '${EMAIL}'`);
    await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');

    // Two requests: an Atelier ×2 (to be paid by deposit) and a Gallery (paid in full).
    const lamp = { 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED', 'Bulb Count': 'Dual Array' };
    const reserve = async (size, quantity, fullName) => {
        await go(b, BASE_URL + '/lighting-designs', 2000);
        await b.ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
        const lamps = size === 'lumen-forma-gallery' ? [{ ...lamp, 'Bulb Count': undefined, 'The Pigment': 'Custom' }] : [lamp];
        await b.ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size, quantity, mode: 'all', lamps, details: [{ 'The Pigment': 'Warm grey.' }] }))}; f.requestSubmit(); })()`);
        await sleep(2500);
        const form = { fullName, email: EMAIL, phone: '07700 900123', addressLine1: '1 High Street', town: 'Wimborne', county: 'Dorset', postcode: 'BH21 1AA', customDetails: 'Warm grey.' };
        await b.ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(form)})) { const e = document.querySelector('[name="reserve_form[' + k + ']"]'); if (e) e.value = v; } document.querySelector('form[name=reserve_form]').requestSubmit(); })()`); await sleep(3000);
        return sql(`select request_number from design_request where email = '${EMAIL}' and full_name = '${fullName}' order by created_at desc limit 1`).match(/LR-\d{6}-\d{4}/)?.[0];
    };
    const number = await reserve('lumen-forma-atelier', 2, 'Ada Pay');
    const fullNumber = await reserve('lumen-forma-gallery', 1, 'Ada Full');
    check('two requests sent through Reserve Your Design', !!number && !!fullNumber, `${number} ${fullNumber}`);

    // Admin: quote and send both.
    await go(b, BASE_URL + '/login');
    await b.ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
    let code;
    for (let i = 0; i < 20 && !code; i++) {
        const m = (await mailpit(`to:${ADMIN} subject:"Sign in to Llunio Studios"`))[0];
        if (m) code = ((await message(m.ID)).Text || '').match(/\b\d{6}\b/)?.[0];
        if (!code) await sleep(500);
    }
    await b.ev(`(() => { const h = document.querySelector('[data-pin-code-target=hidden]'); h.value = '${code}'; h.form.requestSubmit(); })()`); await sleep(3000);
    const sendQuote = async (requestNumber, deposit) => {
        const id = uuid(sql(`select id from design_request where request_number = '${requestNumber}'`));
        await go(b, `${BASE_URL}/admin/design-request/${id}/quote`, 3500);
        await b.ev(`(() => {
            document.querySelector('[data-quote-line] [data-quote-price]').value = ${deposit ? "'1850'" : "'850'"};
            ${deposit ? `document.getElementById('quote-add-extra').click();
            const extra = document.querySelector('[data-quote-extra]');
            extra.querySelector('input').value = 'Custom pigment development';
            extra.querySelector('[data-quote-price]').value = '150';
            document.querySelector('input[name$="[payment]"][value="deposit"]').click();
            document.querySelector('[data-quote-deposit]').value = '40';` : ''}
            document.querySelector('[data-quote-delivery]').value = '60';
            document.querySelector('[name$="[deliveryLabel]"]').value = 'DPD Next Day Delivery';
            document.querySelector('[name$="[castingDate]"]').value = '2026-11-16';
            window.confirm = () => true;
            document.querySelector('[data-quote-send]').click();
        })()`); await sleep(4000);
        const html = (await message((await mailpit(`to:${EMAIL} subject:"Your Lumen Forma quote" "${requestNumber}"`))[0].ID)).HTML;
        return { id, link: html.match(/<a href="([^"]+\/quote\/[A-Za-z0-9_-]+)"/)?.[1].replace(/^https?:\/\/[^/]+/, BASE_URL) };
    };
    const deposit = await sendQuote(number, true);
    const full = await sendQuote(fullNumber, false);
    check('both quotes sent, each with its "Pay now" link', !!deposit.link && !!full.link && deposit.link !== full.link, `${deposit.link} ${full.link}`);

    // The customer pays the deposit by card.
    c = await open(1440, 1000, 'quote-pay-customer', { mobile: false });
    await c.send('Network.enable'); await c.send('Network.clearBrowserCookies');
    await go(c, deposit.link, 2500);
    for (let i = 0; i < 30 && (await c.ev(`document.querySelectorAll('.ll-card-box iframe').length`)) < 3; i++) await sleep(500);
    await sleep(1500);
    const field = (name) => c.ev(`document.querySelector('[name="card_payment_form[${name}]"]')?.value`);
    const filled = { email: await field('email'), phone: await field('phone'), name: await field('name'), line1: await field('line1'), town: await field('town'), county: await field('county'), postcode: await field('postcode') };
    check('the quote page has the Card Payment fields, filled in from the request', JSON.stringify(filled) === JSON.stringify({ email: EMAIL, phone: '07700 900123', name: 'Ada Pay', line1: '1 High Street', town: 'Wimborne', county: 'Dorset', postcode: 'BH21 1AA' }), JSON.stringify(filled));
    const page = await c.ev(`({ button: document.querySelector('main aside button[type=submit]')?.textContent.trim(), country: [...document.querySelectorAll('main span')].find(s => s.textContent === 'Country')?.nextElementSibling?.textContent.trim(), boxes: document.querySelectorAll('.ll-card-box iframe').length, wallets: document.querySelector('[data-checkout-target=expressArea]')?.hidden })`);
    check('"Pay £1,564" (the 40% deposit), the country, Stripe\'s card boxes; no wallet buttons in headless Chrome', page.button === 'Pay £1,564' && page.country === 'United Kingdom' && page.boxes === 3 && page.wallets === true, JSON.stringify(page));
    const privacy = await c.ev(`(() => { const l = document.querySelector('main aside [data-privacy-line]'); return l && { text: l.textContent.replace(/\\s+/g, ' ').trim(), href: l.querySelector('a')?.getAttribute('href'), afterTerms: l.previousElementSibling?.getAttribute('href') === '/terms-and-conditions' }; })()`);
    check('the privacy line under "Pay" and the Terms & Conditions link (Step 11d)', privacy?.text === "We'll only use your details for your order or enquiry. See our Privacy Policy." && privacy.href === '/privacy-policy' && privacy.afterTerms, JSON.stringify(privacy));
    await c.shot(out('quote-pay-form.png'));

    // Typing into Stripe's boxes (as checkout-pay.mjs).
    const card = async (box, text) => {
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
            await c.send('Input.insertText', { text });
            await sleep(800);
            if (await c.ev(`document.querySelector('[data-checkout-target=${box}]').classList.contains('StripeElement--complete')`)) return;
        }
    };
    await card('cardNumber', '4242424242424242'); await card('cardExpiry', '1234'); await card('cardCvc', '123');
    await c.ev(`document.querySelector('[name="card_payment_form[cardName]"]').value = 'Ada Pay'`);
    const pay = async (wait = 15000) => {
        const start = await c.ev('location.pathname');
        await c.ev(`document.querySelector('main aside button[type=submit]').click()`);
        for (let i = 0; i < wait / 500; i++) {
            await sleep(500);
            const s = await c.ev(`({ path: location.pathname, message: document.querySelector('[data-checkout-target=message]')?.hidden === false ? document.querySelector('[data-checkout-target=message]').textContent.trim() : '', under: [...document.querySelectorAll('[data-field-error]')].map(e => e.textContent.trim()), busy: !!document.querySelector('main aside button[type=submit]')?.disabled })`).catch(() => null);
            if (s && (s.path !== start || ((s.message || s.under.length) && !s.busy))) return s;
        }
        return c.ev(`({ path: location.pathname })`);
    };

    // A different postcode is refused.
    await c.ev(`document.querySelector('[name="card_payment_form[postcode]"]').value = 'SW1A 1AA'`);
    let s = await pay();
    check('a different postcode: "Delivery was quoted for BH21 1AA. To deliver elsewhere, please reply to your quote email…"', s.under?.includes("Delivery was quoted for BH21 1AA. To deliver elsewhere, please reply to your quote email and I'll update your quote.") && Number(sql(`select count(*) from orders where design_request_id = '${deposit.id}'`).match(/\d+/g).pop()) === 0, JSON.stringify(s));

    // Paid.
    const since = new Date();
    await c.ev(`document.querySelector('[name="card_payment_form[postcode]"]').value = 'bh21 1aa'`);
    s = await pay(25000);
    const done = await c.ev(`({ path: location.pathname, text: document.querySelector('main').innerText.replace(/\\s+/g, ' ') })`);
    check('paid: "Order Confirmed", "Paid · £1,564", "Balance to pay later · £2,346"', /\/quote\/.+\/complete$/.test(done.path) && /Order Confirmed/i.test(done.text) && /Paid · £1,564/.test(done.text) && /Balance to pay later · £2,346/.test(done.text), done.text.slice(0, 300));
    const order = sql(`select o.order_number, o.status, o.total, o.subtotal, o.shipping_cost, o.amount_paid, o.charge_now, o.shipping_service_name, u.email, r.status as request_status from orders o join users u on u.id = o.user_id join design_request r on r.id = o.design_request_id where o.design_request_id = '${deposit.id}' and o.status = 'deposit_paid'`).replace(/\s+/g, ' ');
    check('the order: Deposit Paid, total £3,910 (lamps and extra £3,850, delivery £60), £1,564 paid, the account for the email, the request now Deposit Paid', /LS-\d{6}-\d{4} deposit_paid 391000 385000 6000 156400 156400 DPD Next Day Delivery ada\.pay@example\.test deposit_paid/.test(order), order);
    const items = sql(`select product_title, quantity, unit_price, product_id is null as extra from order_item i join orders o on o.id = i.order_id where o.design_request_id = '${deposit.id}' and o.status = 'deposit_paid' order by i.product_id is null, product_title`).replace(/\s+/g, ' ');
    check("its lines: the design at the quoted price, the extra line", /Lumen Forma Atelier 2 185000 Custom pigment development 1 15000 1 /.test(items), items);
    const address = sql(`select a.line1, a.city, a.county, a.postcode from orders o join address a on a.id = o.shipping_address_id where o.design_request_id = '${deposit.id}' and o.status = 'deposit_paid'`).replace(/\s+/g, ' ');
    check('delivered to the confirmed address', /1 High Street Wimborne Dorset BH21 1AA/.test(address), address);

    // The quote page now.
    await go(c, deposit.link, 2500);
    const after = await c.ev(`({ form: !!document.querySelector('main form'), paid: document.querySelector('[data-quote-paid]')?.innerText, payments: document.querySelector('[data-quote-payments]')?.innerText.replace(/\\s+/g, ' ') })`);
    check('the quote page then shows the payment, with no form', !after.form && /Thank you for your deposit of £1,564, received on \d+ \w+ 2026\. I'll email you when the balance of £2,346 is due\. Your order number is LS-\d{6}-\d{4}\./.test(after.paid ?? '') && /Paid \d+ \w+ 2026 £1,564 Still to pay £2,346/.test(after.payments ?? ''), JSON.stringify(after));

    // The order email.
    await sleep(1000);
    const confirmation = (await mailpit(`to:${EMAIL}`)).filter((m) => new Date(m.Created) > since && !/quote/i.test(m.Subject))[0];
    const confirmationHtml = confirmation ? (await message(confirmation.ID)).HTML : '';
    const confirmationText = confirmationHtml.replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/\s+/g, ' ');
    const orderNumber = sql(`select order_number from orders where design_request_id = '${deposit.id}' and status = 'deposit_paid'`).match(/LS-\d{6}-\d{4}/)?.[0];
    const format = customerEmailProblems(confirmationHtml, 'Ada', "Thank you for your order, and for trusting me with your Lumen Forma Atelier lamps. They'll be cast by hand in our Dorset Studio, and I'll keep you updated as they take shape: when production begins, when the balance is due, and when they're on their way to you.");
    check('email 01 "Your Llunio Studios order LS-…" follows the email rules: Montage C, no heading, "Dear Ada," then "Thank you for your order…", one text size', confirmation?.Subject === `Your Llunio Studios order ${orderNumber}` && format.length === 0, format.join('; ') + ' | ' + confirmation?.Subject);
    check('email 01 for a quote: the extra line, the quote\'s delivery, the total, the deposit paid and the balance', /Custom pigment development £150/.test(confirmationText) && /Delivery · DPD Next Day Delivery £60/.test(confirmationText) && /Total £3,910/.test(confirmationText) && /Paid \(deposit\) £1,564/.test(confirmationText) && /Balance to pay later £2,346/.test(confirmationText), confirmationText.slice(confirmationText.indexOf('Your configuration'), confirmationText.indexOf('Your configuration') + 900));
    check('email 01 for a quote: 03 Delivery with the address and the estimated casting date (no lead time)', /03 Delivery Delivery address Ada Pay 1 High Street Wimborne Dorset BH21 1AA Estimated casting date 16 November 2026 View your order/.test(confirmationText), confirmationText.slice(confirmationText.indexOf('03 Delivery'), confirmationText.indexOf('03 Delivery') + 300));
    // "View your order": the order's private page, without signing in, never indexed.
    const viewLink = confirmationHtml.match(/<a href="([^"]+\/order\/[A-Za-z0-9_-]+)"[^>]*>View your order</)?.[1].replace(/^https?:\/\/[^/]+/, BASE_URL);
    const viewPage = await open(1440, 1000, 'quote-pay-view', { mobile: false });
    try {
        await viewPage.send('Network.enable'); await viewPage.send('Network.clearBrowserCookies');
        await viewPage.send('Page.navigate', { url: viewLink }); await sleep(2500);
        const view = await viewPage.ev(`(async () => ({ path: location.pathname, h1: document.querySelector('main h1')?.innerText, text: document.querySelector('main').innerText.replace(/\\s+/g, ' '), back: [...document.querySelectorAll('main a')].some(a => /Your Orders/.test(a.textContent)), robots: await fetch(location.href).then(r => r.headers.get('x-robots-tag')) }))()`);
        check('"View your order" opens the order without signing in: the order, Deposit Paid, the extra line, paid and still to pay; no link to Your Orders; never indexed', /^\/order\/[A-Za-z0-9_-]{32}$/.test(view.path) && view.h1 === `ORDER ${orderNumber}` && /DEPOSIT PAID/i.test(view.text) && /Custom pigment development £150/.test(view.text) && /Paid £1,564/.test(view.text) && /Still to pay £2,346/.test(view.text) && !view.back && view.robots === 'noindex, nofollow', JSON.stringify({ ...view, text: view.text.slice(0, 200) }));
        const missing = await viewPage.ev(`fetch(${JSON.stringify(BASE_URL + '/order/not-a-real-order-link-1234567890')}).then(r => r.status)`);
        check('an unknown order link is not found', missing === 404, missing);
    } finally { viewPage.close(); }
    // The studio: "Deposit paid".
    const studio = async (kind, number) => { for (const m of await mailpit(`to:lluniostudios@gmail.com subject:"${kind}"`)) if (new Date(m.Created) > since && (await message(m.ID)).HTML.includes(number)) return m; return null; };
    const depositMail = await studio('Deposit paid', orderNumber);
    const depositHtml = depositMail ? (await message(depositMail.ID)).HTML : '';
    const depositSummary = depositHtml.match(/data-studio-summary>([\s\S]*?)<\/table>/)?.[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    const depositFormat = studioEmailProblems(depositHtml, 'Deposit paid');
    check('the studio is emailed "Deposit paid · Atelier × 2 · £1,564 of £3,910": no greeting, the summary first (order, lamps, paid, still to pay, customer, delivery, request, casting)', depositMail?.Subject === 'Deposit paid · Atelier × 2 · £1,564 of £3,910' && depositFormat.length === 0 && new RegExp(`^Order ${orderNumber} · \\d+ \\w+ 2026, \\d\\d:\\d\\d Lamps Atelier × 2 Paid £1,564 deposit of £3,910 Still to pay £2,346 Customer Ada Pay Delivery DPD Next Day Delivery · BH21 1AA Request ${number} Casting 16 November 2026 \\(estimated\\)$`).test(depositSummary ?? ''), depositFormat.join('; ') + ' | ' + depositMail?.Subject + ' | ' + depositSummary);
    check('and replies to it go to the customer', ((await message(depositMail?.ID ?? '')).ReplyTo || []).some((r) => r.Address === EMAIL));

    // Admin.
    const orderId = uuid(sql(`select id from orders where design_request_id = '${deposit.id}' and status = 'deposit_paid'`));
    await go(b, `${BASE_URL}/admin/order/${orderId}`, 3000);
    const adminText = await b.ev(`document.querySelector('main, .content').innerText.replace(/\\s+/g, ' ')`);
    check('the order in admin: Deposit Paid, Paid £1,564, Owed £2,346, the request', /Status Deposit Paid/.test(adminText) && /Paid £1,564\.00/.test(adminText) && /Owed £2,346\.00/.test(adminText) && adminText.includes(number), adminText.slice(0, 400));

    // A full-payment quote, paid as a wallet would (the page's fields with ?wallet=1).
    await go(c, full.link, 3000);
    const started = await c.ev(`(async () => {
        const form = document.querySelector('main form');
        const body = new FormData(form);
        const r = await fetch(form.closest('[data-controller~=checkout]').dataset.checkoutExpressUrlValue, { method: 'POST', body, headers: { Accept: 'application/json' } });
        return r.json();
    })()`);
    const paid = started.status === 'confirm' ? await c.ev(`Stripe(document.querySelector('main form').dataset.checkoutStripeKeyValue).confirmCardPayment(${JSON.stringify(started.clientSecret)}, { payment_method: 'pm_card_visa' }).then(x => x.error ? x.error.message : x.paymentIntent.status)`) : started;
    check('a wallet payment: the page\'s fields are enough (no name on card needed)', paid === 'succeeded', JSON.stringify(paid));
    await go(c, started.completeUrl.replace(/^https?:\/\/[^/]+/, BASE_URL), 3000);
    const fullDone = await c.ev(`document.querySelector('main').innerText.replace(/\\s+/g, ' ')`);
    const fullOrder = sql(`select o.total, o.amount_paid, r.status from orders o join design_request r on r.id = o.design_request_id where o.design_request_id = '${full.id}' and o.status = 'paid'`).replace(/\s+/g, ' ');
    check('a full-payment quote is paid in full: "Paid · £910", nothing owed, the order and request Paid', /Paid · £910/.test(fullDone) && !/Balance to pay later/.test(fullDone) && /91000 91000 paid\b/.test(fullOrder), fullOrder);
    await sleep(1000);
    const fullNumberPaid = sql(`select order_number from orders where design_request_id = '${full.id}' and status = 'paid'`).match(/LS-\d{6}-\d{4}/)?.[0];
    const fullStudio = await studio('New order', fullNumberPaid);
    check('the studio is emailed "New order · Gallery × 1 · £910"', fullStudio?.Subject === 'New order · Gallery × 1 · £910', fullStudio?.Subject);

    // Unknown or unsent links.
    await go(c, BASE_URL + '/quote/not-a-real-quote-link-123/complete', 2000);
    check('an unknown link is not found', (await c.ev(`fetch(location.href).then(r => r.status)`)) === 404);
    summary();
} finally { b.close(); c?.close(); }
