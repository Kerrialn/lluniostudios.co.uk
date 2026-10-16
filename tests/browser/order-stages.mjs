// Order stages: In Production (Step 11.2), Dispatched (11.3), Delivered (11.4). A paid order has "Mark In
// Production" (in the Orders list's menu and as a button on its page); the
// confirm page suggests the expected dispatch date (the end of the 1–3 week
// lead time from payment, as no quote) and refuses a past date; confirming
// marks the order, emails 04 "Your Lumen Forma is being cast" (the email
// rules' format, the order number, the expected dispatch, "View your
// order") and the action goes; admin shows the stage (column, filter, its
// dates); the private order page says "In Production" with the expected
// dispatch. The payment status is kept. Then "Mark Dispatched": blocked
// while a balance is owed (with the reason); the courier (DPD suggested) and
// tracking number (required); marking emails 05 "Your Lumen Forma is on its
// way" (the email rules' format, courier, tracking number, "Track your
// delivery" to DPD, "View your order") and both actions go; admin and the
// private order page show it. Then "Mark Delivered" (only once dispatched):
// confirming marks it and emails 06 "Your Lumen Forma has arrived" (the
// email rules' format, "… lamp has been delivered …", the photo request, no
// care section, "View your order", Facebook in the footer); admin and the
// private order page say "Delivered".
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL, customerEmailProblems } from './lib.mjs';
// The signature on emails 04–06, exactly as the enquiry email (14 October 2026), before the details.
const signatureOf = (html) => { const m = html.match(/<div data-email-signature>([\s\S]*?)<\/div>/); return m ? [...m[1].matchAll(/<p [^>]*>([\s\S]*?)<\/p>/g)].map((p) => p[1].replace(/<br\s*\/?>\s*/g, '\n').trim()) : null; };
const signed = (html, before) => JSON.stringify(signatureOf(html)) === JSON.stringify(['All the best,', 'Theo\n@LlunioStuidos']) && html.indexOf('data-email-signature') < html.indexOf(before);
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
    // Paid "yesterday" (UK), so the suggestion is 3 weeks (21 days) from then (the lead time since 16 October 2026).
    sql(`update orders set paid_at = paid_at - interval '1 day' where id = '${orderId}'`);
    const paidDay = sql(`select to_char((paid_at at time zone 'UTC') at time zone 'Europe/London', 'YYYY-MM-DD') from orders where id = '${orderId}'`).match(/\d{4}-\d\d-\d\d/)?.[0];
    const suggested = new Date(Date.parse(paidDay + 'T12:00:00Z') + 21 * 86400000).toISOString().slice(0, 10);
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
    check(`it asks to confirm, with the expected dispatch suggested as 3 weeks from payment (${suggested})`, confirm.date === suggested && confirm.note?.startsWith(`This marks the order In Production and emails ${EMAIL}`) && confirm.button === 'Mark In Production and email the customer', JSON.stringify(confirm));
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
    const format = customerEmailProblems(html, 'Ada', `Your Lumen Forma Gallery lamp is now in production. I expect to dispatch it on ${long}.`);
    check('email 04 signed "All the best, / Theo / @LlunioStuidos" after the opening, before the details', signed(html, 'Progress'), JSON.stringify(signatureOf(html)));
    const thanks = html.includes("Thank you for supporting my small studio. Every lamp is made with care, and I can't wait for you to see yours in its new home.");
    check(`email 04 "Your Lumen Forma is being cast (${orderNumber})" follows the email rules: Montage C, no heading, "Dear Ada," then "Your Lumen Forma Gallery lamp is now in production. I expect to dispatch it on ${long}.", then the thanks; one text size`, thanks && mail?.Subject === `Your Lumen Forma is being cast (${orderNumber})` && format.length === 0, format.join('; ') + ' | ' + mail?.Subject);
    const text = flat(html);
    check(`01 Progress: the order number and "Expected dispatch ${nice(suggested)}", then "View your order"; Dorset Studio`, text.includes(`01 Progress Order number ${orderNumber} Expected dispatch ${nice(suggested)} View your order Reply to this email with any questions.`) && /Dorset Studio/.test(html) && !/Bournemouth/.test(html), text.slice(text.indexOf('01 Progress'), text.indexOf('01 Progress') + 200));

    // The private order page.
    const view = html.match(/<a href="([^"]+\/order\/[A-Za-z0-9_-]+)"[^>]*>View your order</)?.[1].replace(/^https?:\/\/[^/]+/, BASE_URL);
    c = await open(1440, 1000, 'order-stages-visitor', { mobile: false });
    await c.send('Network.enable'); await c.send('Network.clearBrowserCookies');
    await go(c, view);
    const page = await c.ev(`({ status: document.querySelector('main h1')?.nextElementSibling?.innerText, dispatch: document.querySelector('[data-order-dispatch]')?.innerText.replace(/\\s+/g, ' ') })`);
    check(`the private order page: "In Production" and "Expected dispatch ${nice(suggested)}"`, /^IN PRODUCTION ·/i.test(page.status ?? '') && page.dispatch === `EXPECTED DISPATCH ${nice(suggested)}`.replace(/^EXPECTED DISPATCH/, 'EXPECTED DISPATCH'), JSON.stringify(page));

    // Dispatched (Step 11.3). Blocked while a balance is owed.
    await go(b, `${BASE_URL}/admin/order/${orderId}`, 3000);
    const dispatchButton = await b.ev(`[...document.querySelectorAll('a')].find(a => /^\\s*Mark Dispatched\\s*$/.test(a.textContent))?.getAttribute('href')`);
    check('"Mark Dispatched" on the order in production', /\/dispatched/.test(dispatchButton ?? ''), dispatchButton);
    sql(`update orders set amount_paid = total - 10000 where id = '${orderId}'`);
    await go(b, new URL(dispatchButton, BASE_URL).href, 3000);
    const blocked = await b.ev(`({ text: document.querySelector('[data-dispatch-blocked]')?.innerText.replace(/\\s+/g, ' '), form: !!document.querySelector('[data-mark-dispatched]') })`);
    check('with a balance owed: "This order can\'t be dispatched yet: the balance of £100.00 hasn\'t been paid." and no form', /^This order can't be dispatched yet: the balance of £100\.00 hasn't been paid\./.test(blocked.text ?? '') && !blocked.form, JSON.stringify(blocked));
    sql(`update orders set amount_paid = total where id = '${orderId}'`);

    // Paid in full: courier and tracking number.
    await go(b, new URL(dispatchButton, BASE_URL).href, 3000);
    const dispatchPage = await b.ev(`({ courier: document.querySelector('[data-courier]')?.value, couriers: [...document.querySelectorAll('[data-courier] option')].map(o => o.textContent.trim()), tracking: document.querySelector('[data-tracking-number]')?.value, button: document.querySelector('[data-mark-dispatched]')?.textContent.trim() })`);
    check('paid in full: the courier (DPD or Palletways, DPD suggested) and the tracking number', dispatchPage.courier === 'dpd' && JSON.stringify(dispatchPage.couriers) === JSON.stringify(['DPD', 'Palletways']) && dispatchPage.tracking === '' && dispatchPage.button === 'Mark Dispatched and email the customer', JSON.stringify(dispatchPage));
    await b.shot(out('dispatched.png'));
    await b.ev(`(() => { document.querySelector('[data-tracking-number]').removeAttribute('required'); document.querySelector('[data-mark-dispatched]').click(); })()`); await sleep(2500);
    check('no tracking number: "Please enter the tracking number." and nothing marked', (await b.ev(`document.querySelector('[data-dispatched-problem]')?.textContent.trim()`)) === 'Please enter the tracking number.' && /in_production/.test(sql(`select stage from orders where id = '${orderId}'`)));
    const since5 = new Date();
    await b.ev(`(() => { document.querySelector('[data-tracking-number]').value = '1550 1234 5678'; document.querySelector('[data-mark-dispatched]').click(); })()`); await sleep(3500);
    const afterDispatch = await b.ev(`({ path: location.pathname, text: document.body.innerText.replace(/\\s+/g, ' '), actions: [...document.querySelectorAll('a')].map(a => a.textContent.trim()).filter(t => /^Mark /.test(t)) })`);
    check('"Marked Dispatched: … has been emailed (DPD, tracking number 1550 1234 5678)."; only "Mark Delivered" left', afterDispatch.path === `/admin/order/${orderId}` && afterDispatch.text.includes(`Marked Dispatched: ${EMAIL} has been emailed (DPD, tracking number 1550 1234 5678).`) && JSON.stringify(afterDispatch.actions) === JSON.stringify(['Mark Delivered']), JSON.stringify(afterDispatch.actions));
    const dispatchRow = sql(`select stage, courier, tracking_number, dispatched_at is not null, status from orders where id = '${orderId}'`).replace(/\s+/g, ' ');
    check('the order: dispatched, DPD, the tracking number, when; still paid', dispatchRow.includes('dispatched dpd 1550 1234 5678 1 paid'), dispatchRow);
    check('admin shows Stage Dispatched, the courier, tracking number and link', /Stage Dispatched/i.test(afterDispatch.text) && /Courier DPD/i.test(afterDispatch.text) && /Tracking Number 1550 1234 5678/i.test(afterDispatch.text) && afterDispatch.text.includes('track.dpd.co.uk/search?reference=155012345678'), afterDispatch.text.slice(afterDispatch.text.indexOf('Dispatched'), afterDispatch.text.indexOf('Dispatched') + 300));

    // Email 05.
    let mail5 = null;
    for (let i = 0; i < 10 && !mail5; i++) { mail5 = (await mailpit(`to:${EMAIL} subject:"Your Lumen Forma is on its way"`)).find((m) => new Date(m.Created) > since5); if (!mail5) await sleep(500); }
    const html5 = mail5 ? (await message(mail5.ID)).HTML : '';
    writeFileSync(out('email-05.html'), html5);
    const format5 = customerEmailProblems(html5, 'Ada', 'Your Lumen Forma Gallery lamp has left the studio and is on its way to you.');
    check('email 05 signed "All the best, / Theo / @LlunioStuidos" after the opening, before the details', signed(html5, 'data-email-courier'), JSON.stringify(signatureOf(html5)));
    check(`email 05 "Your Lumen Forma is on its way (${orderNumber})" follows the email rules: Montage C, no heading, "Dear Ada," then "Your Lumen Forma Gallery lamp has left the studio and is on its way to you.", one text size`, mail5?.Subject === `Your Lumen Forma is on its way (${orderNumber})` && format5.length === 0, format5.join('; ') + ' | ' + mail5?.Subject);
    const plain5 = flat(html5);
    check('then "I hope it\'s even better in person, and I\'d love to hear what you think once it arrives." (no photo request)', plain5.includes("on its way to you. I hope it's even better in person, and I'd love to hear what you think once it arrives. All the best, Theo / @LlunioStuidos 01 Delivery") && !/photo/i.test(plain5), plain5.slice(plain5.indexOf('Dear Ada'), plain5.indexOf('Dear Ada') + 300));
    const text5 = flat(html5);
    check('01 Delivery: order number, "Courier DPD", the tracking number; "Track your delivery" (DPD\'s page) and "View your order"', text5.includes(`01 Delivery Order number ${orderNumber} Courier DPD Tracking number 1550 1234 5678 Track your delivery View your order Reply to this email with any questions.`) && /<a href="https:\/\/track\.dpd\.co\.uk\/search\?reference=155012345678"[^>]*>Track your delivery</.test(html5), text5.slice(text5.indexOf('01 Delivery'), text5.indexOf('01 Delivery') + 250));

    // The private order page.
    await go(c, view);
    const page5 = await c.ev(`({ status: document.querySelector('main h1')?.nextElementSibling?.innerText, tracking: document.querySelector('[data-order-tracking]')?.innerText.replace(/\\s+/g, ' '), link: document.querySelector('[data-order-tracking] a')?.href })`);
    check('the private order page: "Dispatched", the courier, tracking number and "Track your delivery"', /^DISPATCHED ·/i.test(page5.status ?? '') && /DPD Tracking number 1550 1234 5678 Track your delivery/i.test(page5.tracking ?? '') && page5.link === 'https://track.dpd.co.uk/search?reference=155012345678', JSON.stringify(page5));

    // Delivered (Step 11.4).
    await go(b, `${BASE_URL}/admin/order/${orderId}`, 3000);
    const deliveredButton = await b.ev(`[...document.querySelectorAll('a')].find(a => /^\\s*Mark Delivered\\s*$/.test(a.textContent))?.getAttribute('href')`);
    await go(b, `${BASE_URL}/admin/order`, 3000);
    const deliveredMenu = await b.ev(`!![...document.querySelectorAll('tbody tr')].find(r => r.innerText.includes(${JSON.stringify(orderNumber)}))?.querySelector('a[href*="/delivered"]')`);
    check('"Mark Delivered" on the dispatched order (its page and the list\'s menu)', /\/delivered/.test(deliveredButton ?? '') && deliveredMenu, deliveredButton);
    await go(b, new URL(deliveredButton, BASE_URL).href, 3000);
    const deliveredPage = await b.ev(`({ note: document.querySelector('[data-delivered-note]')?.innerText.replace(/\\s+/g, ' '), button: document.querySelector('[data-mark-delivered]')?.textContent.trim() })`);
    check('it asks to confirm: who will be emailed, the dispatch and tracking', deliveredPage.note?.startsWith(`This marks the order Delivered and emails ${EMAIL} "Your Lumen Forma has arrived".`) && /with DPD, tracking number 1550 1234 5678/.test(deliveredPage.note ?? '') && deliveredPage.button === 'Mark Delivered and email the customer', JSON.stringify(deliveredPage));
    const since6 = new Date();
    await b.ev(`document.querySelector('[data-mark-delivered]').click()`); await sleep(3500);
    const afterDelivered = await b.ev(`({ path: location.pathname, text: document.body.innerText.replace(/\\s+/g, ' '), actions: [...document.querySelectorAll('a')].map(a => a.textContent.trim()).filter(t => /^Mark /.test(t)) })`);
    check('"Marked Delivered: … has been emailed."; Stage Delivered with its date; no more "Mark" actions', afterDelivered.path === `/admin/order/${orderId}` && afterDelivered.text.includes(`Marked Delivered: ${EMAIL} has been emailed.`) && /Stage Delivered/i.test(afterDelivered.text) && afterDelivered.actions.length === 0 && /delivered 1/.test(sql(`select stage, delivered_at is not null from orders where id = '${orderId}'`).replace(/\s+/g, ' ')), afterDelivered.text.slice(0, 160));

    // Email 06.
    let mail6 = null;
    for (let i = 0; i < 10 && !mail6; i++) { mail6 = (await mailpit(`to:${EMAIL} subject:"Your Lumen Forma has arrived"`)).find((m) => new Date(m.Created) > since6); if (!mail6) await sleep(500); }
    const html6 = mail6 ? (await message(mail6.ID)).HTML : '';
    writeFileSync(out('email-06.html'), html6);
    const format6 = customerEmailProblems(html6, 'Ada', "Your Lumen Forma Gallery lamp has been delivered. I hope it's found its place in your home.");
    check('email 06 signed "All the best, / Theo / @LlunioStuidos" after the opening, before the details', signed(html6, 'View your order'), JSON.stringify(signatureOf(html6)));
    check(`email 06 "Your Lumen Forma has arrived (${orderNumber})" follows the email rules: Montage C, no heading, "Dear Ada," then "Your Lumen Forma Gallery lamp has been delivered. I hope it's found its place in your home.", one text size, "Llunio Studios" in full`, mail6?.Subject === `Your Lumen Forma has arrived (${orderNumber})` && format6.length === 0, format6.join('; ') + ' | ' + mail6?.Subject);
    const text6 = flat(html6);
    check('then the photo request (Facebook or Instagram, @llunio_studios, or a reply) and "By sending me a photo, you\'re happy for me to share it.", "View your order"; no care or Share section', text6.includes("in your home. Would you do me a small favour? I'd love to see where it ended up. Share a photo on Facebook or Instagram and tag Llunio Studios (@llunio_studios), or simply reply to this email with a picture. You might see it featured on our page. By sending me a photo, you're happy for me to share it. All the best, Theo / @LlunioStuidos View your order Reply to this email with any questions.") && !/\b0[12] (Care|Share)\b/.test(text6), text6.slice(text6.indexOf('Dear Ada'), text6.indexOf('Dear Ada') + 600));
    check('the email footer: Facebook (the placeholder link), Instagram, YouTube', /<a href="https:\/\/www\.facebook\.com\/"[^>]*><img [^>]*icon-facebook\.png[^>]*alt="Facebook"[\s\S]*icon-instagram\.png[\s\S]*icon-youtube\.png/.test(html6));

    // The private order page.
    await go(c, view);
    const page6 = await c.ev(`({ status: document.querySelector('main h1')?.nextElementSibling?.innerText, delivered: document.querySelector('[data-order-delivered]')?.innerText })`);
    check('the private order page: "Delivered" and when', /^DELIVERED ·/i.test(page6.status ?? '') && /^DELIVERED \d+ \w+ 20\d\d$/i.test(page6.delivered ?? ''), JSON.stringify(page6));
    summary();
} finally { b.close(); c?.close(); }
