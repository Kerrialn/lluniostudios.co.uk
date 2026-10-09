// Order stages (Step 11.2): In Production. A paid order has "Mark In
// Production" (in the Orders list's menu and as a button on its page); the
// confirm page suggests the expected dispatch date (the end of the 7–14 day
// lead time from payment, as no quote) and refuses a past date; confirming
// marks the order, emails 04 "Your Lumen Forma is being cast" (the email
// rules' format, the order number, the expected dispatch, "View your
// order") and the action goes; admin shows the stage (column, filter, its
// dates); the private order page says "In Production" with the expected
// dispatch. The payment status is kept.
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL, customerEmailProblems } from './lib.mjs';
import { writeFileSync } from 'node:fs';

const { check, summary } = checker();
const b = await open(1440, 1000, 'order-stages', { mobile: false });
let c = null;
const EMAIL = 'ada.stages@example.test';
const ADMIN = 'lluniostudios@gmail.com';
const mailpit = async (query) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json()).messages || [];
const message = async (id) => (await fetch(`${MAILPIT_URL}/api/v1/message/${id}`)).json();
const flat = (html) => html.replace(/<br\s*\/?>/g, ' / ').replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&#039;/g, "'").replace(/\s+/g, ' ');
const uuid = (text) => text.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)?.[0];
const go = async (x, url, wait = 2500) => { await x.send('Page.navigate', { url }); await sleep(wait); };
const nice = (iso) => new Date(iso + 'T12:00:00Z').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
try {
    await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');

    // A paid order (as Apple Pay / Google Pay would, Stripe's test card).
    const lamp = { 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED' };
    await go(b, BASE_URL + '/lighting-designs', 1800);
    await b.ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
    await b.ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`); await sleep(2500);
    await go(b, BASE_URL + '/checkout', 3500);
    const wallet = { email: EMAIL, phone: '07700 900123', shipping: { name: 'Ada Stages', line1: '1 High Street', city: 'Wimborne', postal_code: 'BH21 1AA', country: 'GB' }, billing: { name: 'Ada Stages', postal_code: 'BH21 1AA', country: 'GB' } };
    const started = await b.ev(`fetch('/checkout/express', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: ${JSON.stringify(JSON.stringify(wallet))} }).then(r => r.json())`);
    const paid = await b.ev(`Stripe(document.querySelector('main form').dataset.checkoutStripeKeyValue).confirmCardPayment(${JSON.stringify(started.clientSecret)}, { payment_method: 'pm_card_visa' }).then(x => x.error ? x.error.message : x.paymentIntent.status)`);
    await go(b, started.completeUrl.replace(/^https?:\/\/[^/]+/, BASE_URL), 3000);
    const orderNumber = started.completeUrl.split('/').pop();
    const orderId = uuid(sql(`select id from orders where order_number = '${orderNumber}'`));
    // Paid "yesterday" (UK), so the suggestion is 14 days from then.
    sql(`update orders set paid_at = paid_at - interval '1 day' where id = '${orderId}'`);
    const paidDay = sql(`select to_char((paid_at at time zone 'UTC') at time zone 'Europe/London', 'YYYY-MM-DD') from orders where id = '${orderId}'`).match(/\d{4}-\d\d-\d\d/)?.[0];
    const suggested = new Date(Date.parse(paidDay + 'T12:00:00Z') + 14 * 86400000).toISOString().slice(0, 10);
    check('a paid order', paid === 'succeeded' && !!orderId, paid);

    // Admin.
    const since0 = new Date();
    await go(b, BASE_URL + '/login');
    await b.ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
    let code;
    for (let i = 0; i < 20 && !code; i++) {
        const m = (await mailpit(`to:${ADMIN} subject:"Sign in to Llunio Studios"`)).find((x) => new Date(x.Created) > since0);
        if (m) code = (await message(m.ID)).HTML.match(/data-login-code>(\d{6})</)?.[1];
        if (!code) await sleep(500);
    }
    await b.ev(`(() => { const h = document.querySelector('[data-pin-code-target=hidden]'); h.value = '${code}'; h.form.requestSubmit(); })()`); await sleep(3000);
    await go(b, `${BASE_URL}/admin/order`, 3000);
    const inMenu = await b.ev(`!![...document.querySelectorAll('tbody tr')].find(r => r.innerText.includes(${JSON.stringify(orderNumber)}))?.querySelector('a[href*="/in-production"]')`);
    await go(b, `${BASE_URL}/admin/order/${orderId}`, 3000);
    const button = await b.ev(`(() => { const a = [...document.querySelectorAll('a')].find(a => /^\\s*Mark In Production\\s*$/.test(a.textContent)); return a ? { href: a.getAttribute('href'), primary: a.classList.contains('btn-primary') } : null; })()`);
    check('"Mark In Production": in the Orders list\'s menu and a button on the order\'s page', inMenu && /\/in-production/.test(button?.href ?? '') && button.primary, JSON.stringify({ inMenu, button }));

    // The confirm page.
    await go(b, new URL(button.href, BASE_URL).href, 3000);
    const confirm = await b.ev(`({ date: document.querySelector('[data-expected-dispatch]')?.value, min: document.querySelector('[data-expected-dispatch]')?.min, note: document.querySelector('[data-in-production-note]')?.innerText.replace(/\\s+/g, ' '), button: document.querySelector('[data-mark-in-production]')?.textContent.trim() })`);
    check(`it asks to confirm, with the expected dispatch suggested as 14 days from payment (${suggested})`, confirm.date === suggested && confirm.note?.startsWith(`This marks the order In Production and emails ${EMAIL}`) && confirm.button === 'Mark In Production and email the customer', JSON.stringify(confirm));
    await b.shot(out('in-production.png'));
    // A past date is refused (the page's own date check bypassed).
    await b.ev(`(() => { const d = document.querySelector('[data-expected-dispatch]'); d.removeAttribute('min'); d.value = '2026-01-01'; document.querySelector('[data-mark-in-production]').click(); })()`); await sleep(2500);
    const refused = await b.ev(`document.querySelector('[data-in-production-problem]')?.textContent.trim()`);
    check('a past date: "The expected dispatch date can\'t be in the past." and nothing marked', refused === "The expected dispatch date can't be in the past." && /^\s*$/.test(sql(`select coalesce(stage, '') from orders where id = '${orderId}'`).replace(/[-\s]|coalesce/g, '')), refused);

    // Marked.
    const since = new Date();
    await b.ev(`(() => { const d = document.querySelector('[data-expected-dispatch]'); d.value = '${suggested}'; document.querySelector('[data-mark-in-production]').click(); })()`); await sleep(3500);
    const after = await b.ev(`({ path: location.pathname, text: document.body.innerText.replace(/\\s+/g, ' '), action: [...document.querySelectorAll('a')].some(a => /^\\s*Mark In Production\\s*$/.test(a.textContent)) })`);
    check(`"Marked In Production: … has been emailed (expected dispatch ${nice(suggested)})."; back on the order; no more "Mark In Production"`, after.path === `/admin/order/${orderId}` && after.text.includes(`Marked In Production: ${EMAIL} has been emailed (expected dispatch ${nice(suggested)}).`) && !after.action, after.text.slice(0, 200));
    const row = sql(`select stage, status, expected_dispatch, in_production_at is not null from orders where id = '${orderId}'`).replace(/\s+/g, ' ');
    check('the order: in_production, still paid, the expected dispatch, when it was marked', row.includes(`in_production paid ${suggested} 1`), row);
    check('admin shows the stage, "In Production since" and "Expected dispatch"', /Stage In Production/i.test(after.text) && /In Production Since/i.test(after.text) && /Expected dispatch/i.test(after.text), after.text.slice(after.text.indexOf('Stage'), after.text.indexOf('Stage') + 120));
    await go(b, `${BASE_URL}/admin/order?filters[stage][comparison]=%3D&filters[stage][value]=in_production`, 3000);
    const listed = await b.ev(`({ row: [...document.querySelectorAll('tbody tr')].find(r => r.innerText.includes(${JSON.stringify(orderNumber)}))?.innerText.replace(/\\s+/g, ' ') })`);
    check('the Orders list: "In Production" in its Stage column, and the Stage filter finds it', /In Production/i.test(listed.row ?? ''), JSON.stringify(listed));

    // Email 04.
    let mail = null;
    for (let i = 0; i < 10 && !mail; i++) { mail = (await mailpit(`to:${EMAIL} subject:"Your Lumen Forma is being cast"`)).find((m) => new Date(m.Created) > since); if (!mail) await sleep(500); }
    const html = mail ? (await message(mail.ID)).HTML : '';
    writeFileSync(out('email-04.html'), html);
    const long = new Date(suggested + 'T12:00:00Z').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).replace(',', '');
    const format = customerEmailProblems(html, 'Ada', `Your Lumen Forma is now in production. Expected dispatch date is: ${long}.`);
    const thanks = html.includes("Thank you for supporting our small studio. Every piece is made with care, and we can't wait for you to see yours.");
    check(`email 04 "Your Lumen Forma is being cast (${orderNumber})" follows the email rules: Montage C, no heading, "Dear Ada," then "Your Lumen Forma is now in production. Expected dispatch date is: ${long}.", then the thanks; one text size`, thanks && mail?.Subject === `Your Lumen Forma is being cast (${orderNumber})` && format.length === 0, format.join('; ') + ' | ' + mail?.Subject);
    const text = flat(html);
    check(`01 Progress: the order number and "Expected dispatch ${nice(suggested)}", then "View your order"; Dorset Studio`, text.includes(`01 Progress Order number ${orderNumber} Expected dispatch ${nice(suggested)} View your order Reply to this email with any questions.`) && /Dorset Studio/.test(html) && !/Bournemouth/.test(html), text.slice(text.indexOf('01 Progress'), text.indexOf('01 Progress') + 200));

    // The private order page.
    const view = html.match(/<a href="([^"]+\/order\/[A-Za-z0-9_-]+)"[^>]*>View your order</)?.[1].replace(/^https?:\/\/[^/]+/, BASE_URL);
    c = await open(1440, 1000, 'order-stages-visitor', { mobile: false });
    await c.send('Network.enable'); await c.send('Network.clearBrowserCookies');
    await go(c, view);
    const page = await c.ev(`({ status: document.querySelector('main h1')?.nextElementSibling?.innerText, dispatch: document.querySelector('[data-order-dispatch]')?.innerText.replace(/\\s+/g, ' ') })`);
    check(`the private order page: "In Production" and "Expected dispatch ${nice(suggested)}"`, /^IN PRODUCTION ·/i.test(page.status ?? '') && page.dispatch === `EXPECTED DISPATCH ${nice(suggested)}`.replace(/^EXPECTED DISPATCH/, 'EXPECTED DISPATCH'), JSON.stringify(page));
    summary();
} finally { b.close(); c?.close(); }
