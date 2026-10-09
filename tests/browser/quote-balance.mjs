// Requesting the balance (Step 10b.4): a quote paid by deposit shows the
// balance "to pay later" with no way to pay it yet; "Request balance" (in the
// Requests and Orders lists' menus, as a button on the request's page, on the
// order's page) asks to confirm, with an optional personal note, then emails
// the customer (order total, paid, balance, the note in the quote email's
// note style or nothing extra, "Pay balance · £x" to the quote page), and
// returns to where it was asked from; the quote page then
// has the name on the card, Stripe's boxes and "Pay balance £x"; paying by
// card (Stripe's test card) makes the order paid in full ("Payment Received",
// a receipt email, the quote page and admin say so; nothing more to request;
// the quote can't be sent again once paid). The status, in admin (the
// Requests and Orders lists, the Requests filter, the order's page) and in
// the customer's Your Orders: Deposit Paid after the deposit, Paid after the
// balance.
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL } from './lib.mjs';

const { check, summary } = checker();
const b = await open(1440, 1000, 'quote-balance-admin', { mobile: false });
let c = null;
const EMAIL = 'ada.balance@example.test';
const ADMIN = 'lluniostudios@gmail.com';
const mailpit = async (query) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json()).messages || [];
const message = async (id) => (await fetch(`${MAILPIT_URL}/api/v1/message/${id}`)).json();
const uuid = (text) => text.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)?.[0];
const flat = (html) => html.replace(/<[^>]+>/g, ' ').replace(/&amp;/g, '&').replace(/&#039;/g, "'").replace(/\s+/g, ' ');
try {
    const go = async (x, url, wait = 2500) => { await x.send('Page.navigate', { url }); await sleep(wait); };
    sql(`with o as (select id, shipping_address_id, billing_address_id from orders where email = '${EMAIL}'), i as (delete from order_item where order_id in (select id from o) returning 1), d as (delete from orders where id in (select id from o) returning shipping_address_id, billing_address_id) delete from address where id in (select shipping_address_id from d union select billing_address_id from d)`);
    sql(`delete from design_request where email = '${EMAIL}'`);
    await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');

    // A request (Atelier ×2), quoted at £3,910 with a 40% deposit, sent.
    const lamp = { 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED', 'Bulb Count': 'Dual Array' };
    await go(b, BASE_URL + '/lighting-designs', 2000);
    await b.ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
    await b.ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-atelier', quantity: 2, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`);
    await sleep(2500);
    const form = { fullName: 'Ada Balance', email: EMAIL, phone: '07700 900123', addressLine1: '1 High Street', town: 'Wimborne', postcode: 'BH21 1AA' };
    await b.ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(form)})) document.querySelector('[name="reserve_form[' + k + ']"]').value = v; document.querySelector('form[name=reserve_form]').requestSubmit(); })()`); await sleep(3000);
    const number = sql(`select request_number from design_request where email = '${EMAIL}' order by created_at desc limit 1`).match(/LR-\d{6}-\d{4}/)?.[0];
    const requestId = uuid(sql(`select id from design_request where request_number = '${number}'`));

    await go(b, BASE_URL + '/login');
    await b.ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
    let code;
    for (let i = 0; i < 20 && !code; i++) {
        const m = (await mailpit(`to:${ADMIN} subject:"Sign in to Llunio Studios"`))[0];
        if (m) code = ((await message(m.ID)).Text || '').match(/\b\d{6}\b/)?.[0];
        if (!code) await sleep(500);
    }
    await b.ev(`(() => { const h = document.querySelector('[data-pin-code-target=hidden]'); h.value = '${code}'; h.form.requestSubmit(); })()`); await sleep(3000);
    const quoteUrl = `${BASE_URL}/admin/design-request/${requestId}/quote`;
    await go(b, quoteUrl, 3500);
    await b.ev(`(() => {
        document.querySelector('[data-quote-line] [data-quote-price]').value = '1850';
        document.getElementById('quote-add-extra').click();
        const extra = document.querySelector('[data-quote-extra]');
        extra.querySelector('input').value = 'Custom pigment development';
        extra.querySelector('[data-quote-price]').value = '150';
        document.querySelector('[data-quote-delivery]').value = '60';
        document.querySelector('[name$="[deliveryLabel]"]').value = 'DPD Next Day Delivery';
        document.querySelector('input[name$="[payment]"][value="deposit"]').click();
        document.querySelector('[data-quote-deposit]').value = '40';
        document.querySelector('[name$="[castingDate]"]').value = '2026-11-16';
        document.querySelector('[name$="[note]"]').value = 'Thank you for your patience with the custom pigment.';
        window.confirm = () => true;
        document.querySelector('[data-quote-send]').click();
    })()`); await sleep(4000);
    const quoteMail = (await mailpit(`to:${EMAIL} subject:"Your Lumen Forma quote"`))[0];
    const link = (await message(quoteMail.ID)).HTML.match(/<a href="([^"]+\/quote\/[A-Za-z0-9_-]+)"/)?.[1].replace(/^https?:\/\/[^/]+/, BASE_URL);
    check('a quote with a 40% deposit is sent', !!link, link);

    // The deposit, paid as a wallet would (the page's fields with ?wallet=1).
    c = await open(1440, 1000, 'quote-balance-customer', { mobile: false });
    await c.send('Network.enable'); await c.send('Network.clearBrowserCookies');
    await go(c, link, 3000);
    const started = await c.ev(`(async () => { const form = document.querySelector('main form'); const r = await fetch(form.dataset.checkoutExpressUrlValue, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } }); return r.json(); })()`);
    const deposit = await c.ev(`Stripe(document.querySelector('main form').dataset.checkoutStripeKeyValue).confirmCardPayment(${JSON.stringify(started.clientSecret)}, { payment_method: 'pm_card_visa' }).then(x => x.error ? x.error.message : x.paymentIntent.status)`);
    await go(c, started.completeUrl.replace(/^https?:\/\/[^/]+/, BASE_URL), 3000);
    const orderId = uuid(sql(`select id from orders where design_request_id = '${requestId}' and status = 'deposit_paid'`));
    check('the deposit (£1,564) is paid', deposit === 'succeeded' && !!orderId, deposit);
    const orderNumber = sql(`select order_number from orders where id = '${orderId}'`).match(/LS-\d{6}-\d{4}/)?.[0];

    // The customer signs in (the sign-in email's button) to see Your Orders.
    await go(c, BASE_URL + '/login');
    const signInSince = new Date();
    await c.ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(EMAIL)}; i.form.requestSubmit(); })()`); await sleep(2500);
    let signIn;
    for (let i = 0; i < 20 && !signIn; i++) {
        const m = (await mailpit(`to:${EMAIL} subject:"Sign in to Llunio Studios"`)).filter((x) => new Date(x.Created) > signInSince)[0];
        if (m) signIn = (await message(m.ID)).HTML.match(/href="([^"]+\/login\/check\?[^"]+)"/)?.[1].replace(/&amp;/g, '&').replace(/^https?:\/\/[^/]+/, BASE_URL);
        if (!signIn) await sleep(500);
    }
    await go(c, signIn, 3000);

    // The status everywhere it shows.
    const statuses = async () => {
        const row = (text) => `[...document.querySelectorAll('tbody tr')].find(r => r.innerText.includes(${JSON.stringify(text)}))?.querySelector('td[data-column=status], td.field-choice')?.innerText.trim()`;
        await go(b, `${BASE_URL}/admin/design-request`, 3000);
        const request = await b.ev(row(number));
        const requestBadge = await b.ev(`[...document.querySelectorAll('tbody tr')].find(r => r.innerText.includes(${JSON.stringify(number)}))?.querySelector('.badge')?.innerText.trim()`);
        const filter = async (value) => { await go(b, `${BASE_URL}/admin/design-request?filters[status][comparison]=%3D&filters[status][value]=${value}`, 3000); return b.ev(`document.querySelector('tbody').innerText.includes(${JSON.stringify(number)})`); };
        const filtered = { deposit_paid: await filter('deposit_paid'), paid: await filter('paid') };
        await go(b, `${BASE_URL}/admin/order`, 3000);
        const order = await b.ev(row(orderNumber));
        await go(b, `${BASE_URL}/admin/order/${orderId}`, 3000);
        const orderPage = (await b.ev(`document.querySelector('main, .content').innerText.replace(/\\s+/g, ' ')`)).match(/Status (.+?) Subtotal/)?.[1];
        await go(c, `${BASE_URL}/account/orders`, 2500);
        const account = await c.ev(`[...document.querySelectorAll('main a[href*="/account/orders/"]')].find(a => a.innerText.includes(${JSON.stringify(orderNumber)}))?.innerText.replace(/\\s+/g, ' ')`);
        await go(c, `${BASE_URL}/account/orders/${orderNumber}`, 2500);
        const accountPage = await c.ev(`document.querySelector('main h1')?.parentElement.innerText.replace(/\\s+/g, ' ')`);
        return { request, requestBadge, filtered, order, orderPage, account, accountPage };
    };
    let st = await statuses();
    check('after the deposit, the request is Deposit Paid in admin (badge, and under the Deposit Paid filter, not Paid)', /^Deposit Paid$/i.test(st.requestBadge ?? '') && st.filtered.deposit_paid && !st.filtered.paid, JSON.stringify(st));
    check('and the order is Deposit Paid in the Orders list and on its page', /^Deposit Paid$/i.test(st.order ?? '') && /^Deposit Paid$/i.test(st.orderPage ?? ''), JSON.stringify(st));
    check('Your Orders: "Deposit Paid" in the list and on the order\'s page', /Deposit Paid/i.test(st.account ?? '') && /Deposit Paid ·/i.test(st.accountPage ?? ''), JSON.stringify(st));

    // Before the balance is requested: nothing to pay on the quote page.
    await go(c, link, 3000);
    let page = await c.ev(`({ form: !!document.querySelector('main form'), paid: document.querySelector('[data-quote-paid]')?.innerText })`);
    check('before the balance is requested, the quote page shows "£2,346 to pay later" and no form', !page.form && /with £2,346 to pay later/.test(page.paid ?? ''), JSON.stringify(page));

    // Admin: the request's quote shows the order; the quote can no longer be sent.
    await go(b, quoteUrl, 3500);
    const adminQuote = await b.ev(`({ order: document.querySelector('[data-quote-order]')?.innerText.replace(/\\s+/g, ' '), send: !!document.querySelector('[data-quote-send]') })`);
    check('the quote in admin: "Paid: order … · Paid £1,564.00 · Owed £2,346.00", "Request balance", no "Send again"', /Paid: order LS-\d{6}-\d{4} · Paid £1,564\.00 · Owed £2,346\.00\. Request balance/.test(adminQuote.order ?? '') && !adminQuote.send, JSON.stringify(adminQuote));

    // "Request balance" everywhere it is offered: the Requests list's "⋮" menu
    // and the request's page (a button), the Orders list and the order's page.
    const rowAction = (text, path) => `[...document.querySelectorAll('tbody tr')].find(r => r.innerText.includes(${JSON.stringify(text)}))?.querySelector('a[href*="${path}"]') ? true : false`;
    await go(b, `${BASE_URL}/admin/design-request`, 3000);
    check('the Requests list: "Request balance" in the request\'s "⋮" menu', await b.ev(rowAction(number, `/admin/design-request/${requestId}/request-balance`)));
    await go(b, `${BASE_URL}/admin/order`, 3000);
    check('the Orders list: "Request balance" in the order\'s menu', await b.ev(rowAction(orderNumber, `/admin/order/${orderId}/request-balance`)));
    await go(b, `${BASE_URL}/admin/order/${orderId}`, 3000);
    const action = await b.ev(`[...document.querySelectorAll('a')].find(a => /^\\s*Request balance\\s*$/.test(a.textContent))?.getAttribute('href')`);
    check('the order in admin has "Request balance"', /\/admin\/order\/.+\/request-balance/.test(action ?? ''), action);
    await go(b, `${BASE_URL}/admin/design-request/${requestId}`, 3000);
    const requestButton = await b.ev(`(() => { const a = [...document.querySelectorAll('a')].find(a => /^\\s*Request balance\\s*$/.test(a.textContent)); return a ? { href: a.getAttribute('href'), primary: a.classList.contains('btn-primary') } : null; })()`);
    check('the request\'s page has a "Request balance" button', /\/admin\/design-request\/.+\/request-balance/.test(requestButton?.href ?? '') && requestButton.primary, JSON.stringify(requestButton));

    // From the request: confirm, with a personal note, then the email; back to the request.
    await go(b, new URL(requestButton.href, BASE_URL).href, 3500);
    const confirmPage = await b.ev(`({ path: location.pathname, owed: document.querySelector('[data-balance-owed]')?.textContent.trim(), note: document.querySelector('[data-balance-note]')?.innerText.replace(/\\s+/g, ' '), button: document.querySelector('[data-request-balance]')?.textContent.trim(), noteBox: document.querySelector('label[for=balance-note]')?.textContent.trim(), noteValue: document.querySelector('[data-balance-note-input]')?.value, back: [...document.querySelectorAll('a.btn')].map(a => a.textContent.trim()).find(t => /^Back to/.test(t)) })`);
    check('it opens the order\'s confirm page: the balance £2,346.00, who will be emailed, an empty "Personal note (optional)", "Back to the request"', confirmPage.path === `/admin/order/${orderId}/request-balance` && confirmPage.owed === '£2,346.00' && confirmPage.note?.startsWith(`This emails ${EMAIL} a link to pay £2,346.00`) && confirmPage.button === 'Send balance request' && confirmPage.noteBox === 'Personal note (optional)' && confirmPage.noteValue === '' && confirmPage.back === 'Back to the request', JSON.stringify(confirmPage));
    await b.ev(`document.querySelector('[data-balance-note-input]').value = 'Casting is going well.\\nWe will be in touch before dispatch.'`);
    await b.shot(out('request-balance.png'));
    let since = new Date();
    await b.ev(`document.querySelector('[data-request-balance]').click()`); await sleep(3500);
    const afterSend = await b.ev(`({ path: location.pathname, text: document.body.innerText })`);
    check('"Balance requested: … has been emailed to pay £2,346.00.", back on the request\'s page', afterSend.text.includes(`Balance requested: ${EMAIL} has been emailed to pay £2,346.00.`) && afterSend.path === `/admin/design-request/${requestId}`, afterSend.path);
    await sleep(800);
    let balanceMail = (await mailpit(`to:${EMAIL} subject:"Your Lumen Forma balance"`)).filter((m) => new Date(m.Created) > since)[0];
    let balanceHtml = balanceMail ? (await message(balanceMail.ID)).HTML : '';
    const balanceText = flat(balanceHtml);
    check('the email "Your Lumen Forma balance, Ada Balance": "Dear Ada,", the total, paid, the balance, "Pay balance · £2,346"', balanceMail?.Subject === 'Your Lumen Forma balance, Ada Balance' && /Dear Ada,/.test(balanceText) && /Here is the balance for your Lumen Forma order LS-\d{6}-\d{4}\./.test(balanceText) && /Order total £3,910/.test(balanceText) && /Paid \d+ \w+ 20\d\d £1,564/.test(balanceText) && /Balance to pay £2,346/.test(balanceText) && /Pay balance · £2,346/.test(balanceText), balanceText.slice(0, 600));
    // The note: after the opening, before 01, in the quote email's note style, its line break kept.
    const quoteNote = (await message(quoteMail.ID)).HTML.match(/<p style="([^"]+)">Thank you for your patience with the custom pigment\.<\/p>/)?.[1];
    const balanceNote = balanceHtml.match(/<p style="([^"]+)" data-balance-note>Casting is going well\.<br \/>\s*We will be in touch before dispatch\.<\/p>/)?.[1];
    check('the personal note is in the email under the opening, in the same style as the quote email\'s note', !!quoteNote && balanceNote === quoteNote && /Here is the balance for your Lumen Forma order LS-\d{6}-\d{4}\. Casting is going well\. We will be in touch before dispatch\. .*01 Balance/.test(balanceText), JSON.stringify({ quoteNote, balanceNote }));
    const balanceLink = balanceHtml.match(/<a href="([^"]+\/quote\/[A-Za-z0-9_-]+)"/)?.[1].replace(/^https?:\/\/[^/]+/, BASE_URL);
    check('"Pay balance" goes to the quote page', balanceLink === link, balanceLink);

    // Again, from the order, with no note: nothing extra in the email; back to the order.
    await go(b, new URL(action, BASE_URL).href, 3000);
    const again = await b.ev(`({ button: document.querySelector('[data-request-balance]')?.textContent.trim(), note: document.querySelector('[data-balance-note]')?.innerText, back: [...document.querySelectorAll('a.btn')].map(a => a.textContent.trim()).find(t => /^Back to/.test(t)) })`);
    check('asked again from the order: "Already requested on …", "Send again", "Back to the order"', again.button === 'Send again' && /Already requested on/.test(again.note ?? '') && again.back === 'Back to the order', JSON.stringify(again));
    since = new Date();
    await b.ev(`document.querySelector('[data-request-balance]').click()`); await sleep(3500);
    check('sent again, back on the order\'s page', (await b.ev('location.pathname')) === `/admin/order/${orderId}`);
    await sleep(800);
    balanceMail = (await mailpit(`to:${EMAIL} subject:"Your Lumen Forma balance"`)).filter((m) => new Date(m.Created) > since)[0];
    balanceHtml = balanceMail ? (await message(balanceMail.ID)).HTML : '';
    check('with no note, nothing extra: "Here is the balance…" is followed by 01 Balance', !!balanceMail && !/data-balance-note/.test(balanceHtml) && /Here is the balance for your Lumen Forma order LS-\d{6}-\d{4}\. 01 Balance/.test(flat(balanceHtml).replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ')), flat(balanceHtml).slice(500, 800));
    // A note longer than the limit is refused and nothing is sent.
    await go(b, new URL(action, BASE_URL).href, 3000);
    since = new Date();
    await b.ev(`(() => { const t = document.querySelector('[data-balance-note-input]'); t.removeAttribute('maxlength'); t.value = 'x'.repeat(2001); document.querySelector('[data-request-balance]').click(); })()`); await sleep(3500);
    await sleep(800);
    check('a note over 2,000 characters: "The personal note can be at most 2000 characters. Nothing has been sent." and no email', (await b.ev('document.body.innerText')).includes('The personal note can be at most 2000 characters. Nothing has been sent.') && (await mailpit(`to:${EMAIL} subject:"Your Lumen Forma balance"`)).filter((m) => new Date(m.Created) > since).length === 0);

    // The quote page: pay the balance by card.
    await go(c, link, 2500);
    for (let i = 0; i < 30 && (await c.ev(`document.querySelectorAll('.ll-card-box iframe').length`)) < 3; i++) await sleep(500);
    await sleep(1500);
    page = await c.ev(`({ paid: document.querySelector('[data-quote-paid]')?.innerText, button: document.querySelector('main aside button[type=submit]')?.textContent.trim(), name: document.querySelector('[name="card_payment_form[cardName]"]')?.value, address: !!document.querySelector('[name="card_payment_form[line1]"]') })`);
    check('the quote page now: "The balance of £2,346 … can be paid below", the name on the card, "Pay balance £2,346", no address fields', /Thank you for your deposit of £1,564, received on \d+ \w+ 20\d\d\. The balance of £2,346 for your order LS-\d{6}-\d{4} can be paid below\./.test(page.paid ?? '') && page.button === 'Pay balance £2,346' && page.name === 'Ada Balance' && !page.address, JSON.stringify(page));
    await c.shot(out('quote-balance-form.png'));
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
    // Without the card first: the page's one message.
    await c.ev(`document.querySelector('main aside button[type=submit]').click()`); await sleep(2500);
    check('without card details: "Please complete the required fields to continue."', (await c.ev(`document.querySelector('[data-checkout-target=message]')?.textContent.trim()`)) === 'Please complete the required fields to continue.');
    await card('cardNumber', '4242424242424242'); await card('cardExpiry', '1234'); await card('cardCvc', '123');
    const paidSince = new Date();
    await c.ev(`document.querySelector('main aside button[type=submit]').click()`);
    for (let i = 0; i < 50 && !/\/balance\/complete$/.test(await c.ev('location.pathname').catch(() => '')); i++) await sleep(500);
    await sleep(1500);
    const done = await c.ev(`document.querySelector('main').innerText.replace(/\\s+/g, ' ')`);
    check('"Payment Received": "Your order … is now paid in full", "Paid in full · £3,910"', /Payment Received/i.test(done) && /Thank you\. Your order LS-\d{6}-\d{4} is now paid in full\./.test(done) && /Paid in full · £3,910/.test(done), done.slice(0, 300));
    const row = sql(`select amount_paid, total, balance_paid_at is not null as paid from orders where id = '${orderId}'`).replace(/\s+/g, ' ');
    check('the order: £3,910 paid of £3,910', /391000 391000 1/.test(row), row);

    // The receipt.
    await sleep(1000);
    const receipt = (await mailpit(`to:${EMAIL} subject:"Your Lumen Forma order is paid in full"`)).filter((m) => new Date(m.Created) > paidSince)[0];
    const receiptText = receipt ? flat((await message(receipt.ID)).HTML) : '';
    check('a receipt: "Dear Ada,", the balance received, deposit, balance, paid in full', /Dear Ada,/.test(receiptText) && /We have received your balance of £2,346, and your Lumen Forma order LS-\d{6}-\d{4} is now paid in full\./.test(receiptText) && /Deposit · \d+ \w+ 20\d\d £1,564/.test(receiptText) && /Balance · \d+ \w+ 20\d\d £2,346/.test(receiptText) && /Paid in full £3,910/.test(receiptText), receipt?.Subject);

    // Afterwards.
    await go(c, link, 3000);
    page = await c.ev(`({ form: !!document.querySelector('main form'), paid: document.querySelector('[data-quote-paid]')?.innerText, payments: document.querySelector('[data-quote-payments]')?.innerText.replace(/\\s+/g, ' ') })`);
    check('the quote page: paid in full, the deposit and balance, no form', !page.form && /Your order LS-\d{6}-\d{4} is paid in full\./.test(page.paid ?? '') && /Deposit \d+ \w+ 20\d\d £1,564 Balance \d+ \w+ 20\d\d £2,346 Paid in full £3,910/.test(page.payments ?? ''), JSON.stringify(page));
    await go(b, `${BASE_URL}/admin/order/${orderId}`, 3000);
    const adminOrder = await b.ev(`({ text: document.querySelector('main, .content').innerText.replace(/\\s+/g, ' '), action: [...document.querySelectorAll('a')].some(a => /^\\s*Request balance\\s*$/.test(a.textContent)) })`);
    await go(b, `${BASE_URL}/admin/design-request/${requestId}`, 3000);
    const requestAfter = await b.ev(`[...document.querySelectorAll('a')].some(a => /^\\s*Request balance\\s*$/.test(a.textContent))`);
    await go(b, `${BASE_URL}/admin/design-request`, 3000);
    const requestMenuAfter = await b.ev(rowAction(number, `/admin/design-request/${requestId}/request-balance`));
    check('the request (now Paid): no "Request balance" on its page or in its menu', !requestAfter && !requestMenuAfter, JSON.stringify({ requestAfter, requestMenuAfter }));
    check('admin: paid £3,910, owed £0, balance paid; no more "Request balance"', /Paid £3,910\.00/.test(adminOrder.text) && /Owed £0\.00/.test(adminOrder.text) && /Balance Paid/i.test(adminOrder.text) && !adminOrder.action, adminOrder.text.slice(0, 300));
    await go(b, `${BASE_URL}/admin/order/${orderId}/request-balance`, 3000);
    check('asking for a balance that is paid: "There is no balance to request for this order."', (await b.ev(`document.body.innerText`)).includes('There is no balance to request for this order.'));

    // The status once paid in full.
    st = await statuses();
    check('after the balance, the request is Paid in admin (badge, and under the Paid filter, not Deposit Paid)', /^Paid$/i.test(st.requestBadge ?? '') && st.filtered.paid && !st.filtered.deposit_paid, JSON.stringify(st));
    check('and the order is Paid in the Orders list and on its page', /^Paid$/i.test(st.order ?? '') && /^Paid$/i.test(st.orderPage ?? ''), JSON.stringify(st));
    check('Your Orders: "Paid" (not Deposit Paid) in the list and on the order\'s page', /\bPaid\b/i.test(st.account ?? '') && !/Deposit/i.test(st.account ?? '') && /^\S.*\bPaid ·/i.test(st.accountPage ?? '') && !/Deposit Paid/i.test(st.accountPage ?? ''), JSON.stringify(st));
    // Admin: the Orders filter, and the status still editable by hand (labels in the dropdown).
    await go(b, `${BASE_URL}/admin/order?filters[status][comparison]=%3D&filters[status][value]=paid`, 3000);
    const ordersPaid = await b.ev(`document.querySelector('tbody').innerText.includes(${JSON.stringify(orderNumber)})`);
    await go(b, `${BASE_URL}/admin/order?filters[status][comparison]=%3D&filters[status][value]=deposit_paid`, 3000);
    const ordersDeposit = await b.ev(`document.querySelector('tbody').innerText.includes(${JSON.stringify(orderNumber)})`);
    check('the Orders list can be filtered by status (Paid lists it, Deposit Paid doesn\'t)', ordersPaid && !ordersDeposit, JSON.stringify({ ordersPaid, ordersDeposit }));
    const editSelect = async (url) => { await go(b, url, 3500); return b.ev(`(() => { const s = document.querySelector('select[name$="[status]"]'); return s ? { selected: s.selectedOptions[0]?.textContent.trim(), options: [...s.options].map(o => o.textContent.trim()) } : null; })()`); };
    const requestEdit = await editSelect(`${BASE_URL}/admin/design-request/${requestId}/edit`);
    const orderEdit = await editSelect(`${BASE_URL}/admin/order/${orderId}/edit`);
    check('editing by hand: the status dropdowns show "Paid" selected, with "Deposit Paid" among the choices', requestEdit?.selected === 'Paid' && requestEdit.options.includes('Deposit Paid') && orderEdit?.selected === 'Paid' && orderEdit.options.includes('Deposit Paid'), JSON.stringify({ requestEdit, orderEdit }));
    summary();
} finally { b.close(); c?.close(); }
