// Order emails (Step 11.1), for a basket order sent as a gift: email 01
// ("Your Llunio Studios order LS-…") in the email rules' format, with the lamp, DPD
// delivery and total, 03 Delivery (address, "Gift · Arrive by", the 7–14 day
// lead time) and "View your order"; the order's private page (no sign-in,
// never indexed; an unpaid order's link isn't found); and the studio's
// "New order · Gallery × 1 · £x · GIFT by …" with "Gift · Needed by" and the
// summary first. Placed as Apple Pay / Google Pay would, paid with Stripe's
// test card.
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL, customerEmailProblems, studioEmailProblems } from './lib.mjs';
import { writeFileSync } from 'node:fs';

const { check, summary } = checker();
const b = await open(1440, 1000, 'order-emails', { mobile: false });
let c = null;
const EMAIL = 'ada.orderemail@example.test';
const mailpit = async (query) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json()).messages || [];
const message = async (id) => (await fetch(`${MAILPIT_URL}/api/v1/message/${id}`)).json();
const flat = (html) => html.replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&#039;/g, "'").replace(/\s+/g, ' ');
const go = async (x, url, wait = 2500) => { await x.send('Page.navigate', { url }); await sleep(wait); };
try {
    await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');

    // A Gallery in the basket, marked as a gift for the earliest date.
    const lamp = { 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED' };
    await go(b, BASE_URL + '/lighting-designs', 1800);
    await b.ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
    await b.ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`); await sleep(2500);
    await go(b, BASE_URL + '/basket');
    const arrive = await b.ev(`document.querySelector('main aside input[type=date]').min`);
    await b.ev(`[...document.querySelectorAll('main aside label')].find(l => /This is a gift/.test(l.textContent)).click()`);
    await b.ev(`document.querySelector('main aside input[type=date]').value = '${arrive}'`);
    // Gift options (9 October 2026): where to send it is required.
    await b.ev(`[...document.querySelectorAll('main aside label')].find(l => /Send to me/.test(l.textContent))?.click()`);
    await b.ev(`[...document.querySelectorAll('main aside button')].find(e => e.textContent.trim() === 'Proceed to Payment').click()`); await sleep(2000);
    await go(b, BASE_URL + '/checkout', 3500);
    const nice = new Date(arrive + 'T12:00:00Z').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
    const short = new Date(arrive + 'T12:00:00Z').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', timeZone: 'UTC' });

    // Paid as a wallet would.
    const since = new Date();
    const wallet = { email: EMAIL, phone: '07700 900123', shipping: { name: 'Ada Gift', line1: '1 High Street', city: 'Wimborne', postal_code: 'BH21 1AA', country: 'GB' }, billing: { name: 'Ada Gift', postal_code: 'BH21 1AA', country: 'GB' } };
    const started = await b.ev(`fetch('/checkout/express', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: ${JSON.stringify(JSON.stringify(wallet))} }).then(r => r.json())`);
    const paid = await b.ev(`Stripe(document.querySelector('main form').dataset.checkoutStripeKeyValue).confirmCardPayment(${JSON.stringify(started.clientSecret)}, { payment_method: 'pm_card_visa' }).then(x => x.error ? x.error.message : x.paymentIntent.status)`);
    await go(b, started.completeUrl.replace(/^https?:\/\/[^/]+/, BASE_URL), 3000);
    const orderNumber = started.completeUrl.split('/').pop();
    const row = sql(`select total, shipping_cost from orders where order_number = '${orderNumber}'`).match(/(\d+)\s+(\d+)/);
    const money = (pence) => '£' + (pence / 100).toLocaleString('en-GB', { minimumFractionDigits: pence % 100 ? 2 : 0, maximumFractionDigits: 2 });
    const total = money(Number(row?.[1])); const delivery = money(Number(row?.[2]));
    check('a gift order is placed and paid', paid === 'succeeded' && !!row, paid);

    // Email 01.
    await sleep(1200);
    const mail = (await mailpit(`to:${EMAIL} subject:"Your Llunio Studios order"`)).find((m) => new Date(m.Created) > since);
    const html = mail ? (await message(mail.ID)).HTML : '';
    const text = flat(html);
    writeFileSync(out('email-01-gift.html'), html);
    const format = customerEmailProblems(html, 'Ada', 'Thank you for your order. Your Lumen Forma will be cast by hand in our Dorset Studio.');
    check(`email 01 "Your Llunio Studios order ${orderNumber}" follows the email rules: Montage C, no heading, "Dear Ada," then "Thank you for your order…", one text size`, mail?.Subject === `Your Llunio Studios order ${orderNumber}` && format.length === 0, format.join('; ') + ' | ' + mail?.Subject);
    check('01 Order (the order number), 02 Your configuration (the lamp in full, its price), the delivery and total; nothing about deposits', new RegExp(`01 Order Order number ${orderNumber} 02 Your configuration Lumen Forma Size Gallery The Pigment Grey Cable Raw Jute Footing Polished Copper Light Smoked Glass LED Quantity 1 Price £799 Delivery · DPD Next Day Delivery ${delivery.replace('.', '\\.')} Total ${total.replace('.', '\\.')} Photography is illustrative`).test(text) && !/deposit/i.test(text), text.slice(text.indexOf('01 Order'), text.indexOf('01 Order') + 500));
    check(`03 Delivery: the address, "Gift · Arrive by ${nice}" and "Send to me", the lead time, "View your order"`, new RegExp(`03 Delivery Delivery address Ada Gift 1 High Street Wimborne BH21 1AA Gift Arrive by ${nice} Send to me Expected lead time Dispatched within 7–14 days, then DPD Next Day Delivery View your order Reply to this email with any questions\\.`).test(text), text.slice(text.indexOf('03 Delivery'), text.indexOf('03 Delivery') + 300));
    check('replies go to the studio', ((await message(mail?.ID ?? '')).ReplyTo || []).length === 1);

    // "View your order".
    const link = html.match(/<a href="([^"]+\/order\/[A-Za-z0-9_-]+)"[^>]*>View your order</)?.[1].replace(/^https?:\/\/[^/]+/, BASE_URL);
    c = await open(1440, 1000, 'order-emails-visitor', { mobile: false });
    await c.send('Network.enable'); await c.send('Network.clearBrowserCookies');
    await go(c, link);
    const view = await c.ev(`(async () => ({ path: location.pathname, h1: document.querySelector('main h1')?.innerText, text: document.querySelector('main').innerText.replace(/\\s+/g, ' '), back: [...document.querySelectorAll('main a')].some(a => /Your Orders/.test(a.textContent)), robots: await fetch(location.href).then(r => r.headers.get('x-robots-tag')) }))()`);
    check(`"View your order" opens the order without signing in: Paid, the lamp, the gift (Arrive by ${nice}), the total and address; no link to Your Orders; never indexed`, /^\/order\/[A-Za-z0-9_-]{32}$/.test(view.path) && view.h1 === `ORDER ${orderNumber}` && /PAID ·/i.test(view.text) && /Gallery/.test(view.text) && view.text.includes(`Arrive by ${nice}`) && view.text.includes(`Total ${total}`) && /1 High Street/.test(view.text) && !view.back && view.robots === 'noindex, nofollow', JSON.stringify({ ...view, text: view.text.slice(0, 300) }));
    await c.shot(out('order-view.png'));
    // Only placed orders: an unpaid one's link isn't found.
    sql(`update orders set status = 'awaiting_payment' where order_number = '${orderNumber}'`);
    const unpaid = await c.ev(`fetch(location.href).then(r => r.status)`);
    sql(`update orders set status = 'paid' where order_number = '${orderNumber}'`);
    check('an unpaid order\'s link is not found', unpaid === 404, unpaid);

    // The studio.
    let studio = null;
    for (const m of await mailpit('to:lluniostudios@gmail.com subject:"New order"')) if (!studio && new Date(m.Created) > since && (await message(m.ID)).HTML.includes(orderNumber)) studio = m;
    const studioHtml = studio ? (await message(studio.ID)).HTML : '';
    writeFileSync(out('email-studio-order.html'), studioHtml);
    const studioFormat = studioEmailProblems(studioHtml, 'New order');
    const needed = studioHtml.match(/data-studio-needed-by>([^<]*)</)?.[1];
    const studioSummary = studioHtml.match(/data-studio-summary>([\s\S]*?)<\/table>/)?.[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    check(`the studio is emailed "New order · Gallery × 1 · ${total} · GIFT by ${short}"`, studio?.Subject === `New order · Gallery × 1 · ${total} · GIFT by ${short}`, studio?.Subject);
    check(`no greeting; "New order" first, then "Gift · Needed by ${nice}", then the summary (order, lamps, paid, customer, delivery)`, studioFormat.length === 0 && needed === `Gift · Needed by ${nice}` && new RegExp(`^Order ${orderNumber} · \\d+ \\w+ 20\\d\\d, \\d\\d:\\d\\d Lamps Gallery × 1 Paid ${total.replace('.', '\\.')} Customer Ada Gift Delivery DPD Next Day Delivery · BH21 1AA$`).test(studioSummary ?? ''), studioFormat.join('; ') + ' | ' + needed + ' | ' + studioSummary);
    check('then the details: mobile, address, gift, the configuration, the totals and a link to the order in admin', /07700 900123/.test(studioHtml) && studioHtml.includes(`Arrive by ${nice}`) && /Smoked Glass LED/.test(studioHtml) && /\/admin\/order\//.test(studioHtml));
    check('replies to it go to the customer', ((await message(studio?.ID ?? '')).ReplyTo || []).some((r) => r.Address === EMAIL));
    summary();
} finally { b.close(); c?.close(); }
