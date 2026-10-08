// Sign-in and order history (Step 10.6): the Contact page's "Your orders"
// link (the footer no longer has "Sign in"); the restyled Sign In page; the
// sign-in email's one-click "Sign in" button and 6-digit code; signing in
// with each; a tampered or expired link; an unknown email (no email sent,
// nothing revealed); "Your Orders" listing placed orders only; an order's
// page; signing out. An order is placed first (paid with Stripe's test card,
// as a wallet would).
import { open, sleep, checker, sql, BASE_URL, MAILPIT_URL, STUDIO_EMAIL } from './lib.mjs';

const { check, summary } = checker();
const EMAIL = 'ada.signin@example.test';
const b = await open(1440, 900, 'account-buyer', { mobile: false });
let c = null;
const mailpit = async (query) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json()).messages || [];
const latestSignIn = async (since) => {
    for (let i = 0; i < 20; i++) {
        const m = (await mailpit(`to:${EMAIL} subject:"Sign in to Llunio Studios"`)).find((x) => new Date(x.Created) > since);
        if (m) return (await fetch(`${MAILPIT_URL}/api/v1/message/${m.ID}`)).json();
        await sleep(500);
    }
    return null;
};
try {
    // Start from nothing for this test email (an earlier run may not have been cleaned up).
    sql(`with o as (select id, shipping_address_id, billing_address_id from orders where email = '${EMAIL}'), i as (delete from order_item where order_id in (select id from o) returning 1), d as (delete from orders where id in (select id from o) returning shipping_address_id, billing_address_id) delete from address where id in (select shipping_address_id from d union select billing_address_id from d)`);
    sql(`delete from users where email = '${EMAIL}'`);

    // An order to find later (placed as Apple Pay / Google Pay would, paid with Stripe's test card).
    await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');
    const go = async (x, url, wait = 2200) => { await x.send('Page.navigate', { url }); await sleep(wait); };
    const lamp = { 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED' };
    await go(b, BASE_URL + '/lighting-designs', 1800);
    await b.ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
    await b.ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`); await sleep(2500);
    await go(b, BASE_URL + '/checkout', 3500);
    const wallet = { email: EMAIL, phone: '07700 900123', shipping: { name: 'Ada Signin', line1: '1 High Street', city: 'Wimborne', postal_code: 'BH21 1AA', country: 'GB' }, billing: { name: 'Ada Signin', postal_code: 'BH21 1AA', country: 'GB' } };
    const started = await b.ev(`fetch('/checkout/express', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: ${JSON.stringify(JSON.stringify(wallet))} }).then(r => r.json())`);
    const paid = await b.ev(`Stripe(document.querySelector('main form').dataset.checkoutStripeKeyValue).confirmCardPayment(${JSON.stringify(started.clientSecret)}, { payment_method: 'pm_card_visa' }).then(x => x.error ? x.error.message : x.paymentIntent.status)`);
    await go(b, started.completeUrl);
    const orderNumber = started.completeUrl.split('/').pop();
    check('an order is placed and paid for the test email', paid === 'succeeded' && /paid/.test(sql(`select status from orders where order_number = '${orderNumber}'`)), paid);
    b.close();

    // A fresh visitor (nobody signed in).
    c = await open(1440, 900, 'account-visitor', { mobile: false });
    await c.send('Network.enable'); await c.send('Network.clearBrowserCookies');
    await go(c, BASE_URL + '/contact');
    check('the footer no longer has "Sign in"', !/sign in|your orders/i.test(await c.ev(`document.querySelector('footer').innerText`)));
    const link = await c.ev(`[...document.querySelectorAll('main a')].find(a => a.textContent.trim() === 'Your orders')?.getAttribute('href')`);
    check('the Contact page has a "Your orders" link', link === '/account/orders', link);
    const contactEmail = await c.ev(`[...document.querySelectorAll('main a[href^="mailto:"]')].map(a => a.getAttribute('href') + ' ' + a.textContent.trim())`);
    check(`the Contact page's email is the studio's (${STUDIO_EMAIL})`, JSON.stringify(contactEmail) === JSON.stringify([`mailto:${STUDIO_EMAIL} ${STUDIO_EMAIL}`]), JSON.stringify(contactEmail));
    await c.ev(`[...document.querySelectorAll('main a')].find(a => a.textContent.trim() === 'Your orders').click()`); await sleep(2200);
    const login = await c.ev(`({ path: location.pathname, h1: document.querySelector('main h1')?.innerText, text: document.querySelector('main').innerText, button: document.querySelector('main button[type=submit]')?.textContent.trim(), bg: getComputedStyle(document.querySelector('main')).backgroundColor })`);
    check('it asks to sign in first, on the restyled Sign In page', login.path === '/login' && login.h1 === 'SIGN IN' && /sign-in link, with a 6-digit code as a backup/.test(login.text) && login.button === 'Send sign-in email' && login.bg === 'rgb(21, 18, 14)', JSON.stringify(login).slice(0, 300));

    // Unknown email: same message, no email.
    let since = new Date();
    await c.ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = 'nobody.here@example.test'; i.form.requestSubmit(); })()`); await sleep(2500);
    const unknown = await c.ev(`document.body.innerText`);
    await sleep(1500);
    check("an unknown email: the same message, and no email sent", /If that email has an account, we've sent a sign-in link and code\./.test(unknown) && (await mailpit('to:nobody.here@example.test')).filter((m) => new Date(m.Created) > since).length === 0);

    // The sign-in email.
    await go(c, BASE_URL + '/login?reset=1');
    since = new Date();
    await c.ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(EMAIL)}; i.form.requestSubmit(); })()`); await sleep(2500);
    const codeStep = await c.ev(`document.querySelector('main').innerText`);
    check('then the code step, pointing to the email', /Press the button in the email we've sent to ada\.signin@example\.test, or enter its 6-digit code here\./.test(codeStep), codeStep.slice(0, 200));
    let mail = await latestSignIn(since);
    const button = mail?.HTML.match(/<a href="([^"]+)"[^>]*>Sign in<\/a>/);
    const code = mail?.HTML.match(/letter-spacing:0\.2em;color:#ece3d3;">(\d{6})</)?.[1];
    check('the email, "Sign in to Llunio Studios": a "Sign in" button and the 6-digit code', !!button && /\/login\/check\?/.test(button[1]) && /^\d{6}$/.test(code ?? ''), mail?.Subject);
    check('in the brand style, with "Dear Ada" and the 10-minute note', /Dear Ada, press the button below to sign in and see your orders\./.test(mail?.HTML ?? '') && /work for 10 minutes/.test(mail?.HTML ?? '') && /#15120e/.test(mail?.HTML ?? ''));

    // A tampered link is refused.
    await go(c, button[1].replace(/&amp;/g, '&').replace(/hash=[^&]+/, 'hash=tampered'));
    const refused = await c.ev(`({ path: location.pathname, text: document.body.innerText })`);
    check('a tampered or expired link: back to Sign In, "That sign-in link has expired…"', refused.path === '/login' && /That sign-in link has expired\. Please enter your email for a new one\./.test(refused.text), refused.path);

    // The button signs in.
    await go(c, button[1].replace(/&amp;/g, '&'), 3000);
    const orders = await c.ev(`({ path: location.pathname, h1: document.querySelector('main h1')?.innerText, rows: [...document.querySelectorAll('main a[href*="/account/orders/"]')].map(a => a.innerText.replace(/\\s+/g, ' ')) })`);
    check('the button signs in and opens "Your Orders"', orders.path === '/account/orders' && orders.h1 === 'YOUR ORDERS', JSON.stringify(orders));
    check('the paid order is listed with its date, lamps, total and status', orders.rows.length === 1 && orders.rows[0].includes(orderNumber) && /1 lamp/.test(orders.rows[0]) && /£811\.95/.test(orders.rows[0]) && /PAID/i.test(orders.rows[0]), JSON.stringify(orders.rows));
    await c.ev(`document.querySelector('main a[href*="/account/orders/"]').click()`); await sleep(2200);
    const detail = (await c.ev(`document.querySelector('main').innerText`)).replace(/\s+/g, ' ');
    check("an order's page: the lamp (size and choices), totals and delivery address", new RegExp(`ORDER ${orderNumber}`).test(detail) && /SIZE Gallery/.test(detail) && /THE PIGMENT Grey/.test(detail) && /Raw Jute/.test(detail) && /Subtotal £799/.test(detail) && /DPD Next Day Delivery £12\.95/.test(detail) && /Total £811\.95/.test(detail) && /Ada Signin, 1 High Street, Wimborne, BH21 1AA/.test(detail), detail.slice(0, 400));

    // Unfinished checkout attempts aren't listed.
    sql(`update orders set status = 'awaiting_payment' where order_number = '${orderNumber}'`);
    await go(c, BASE_URL + '/account/orders');
    check('an unfinished order (awaiting payment) is not listed', /You haven't placed any orders yet\./.test(await c.ev(`document.querySelector('main').innerText`)));
    sql(`update orders set status = 'paid' where order_number = '${orderNumber}'`);

    // Sign out.
    await c.ev(`[...document.querySelectorAll('main a')].find(a => a.textContent.trim() === 'Sign out').click()`); await sleep(2200);
    await go(c, BASE_URL + '/account/orders');
    check('after signing out, "Your orders" asks to sign in again', (await c.ev('location.pathname')) === '/login');

    // The code signs in too. (Within 30 seconds no new email is sent and the
    // code already sent still works, as the button didn't use it.)
    // After 30 seconds a new code is sent and the old one retired: use the newest.
    since = new Date();
    await c.ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(EMAIL)}; i.form.requestSubmit(); })()`); await sleep(2500);
    const fresh = (await mailpit(`to:${EMAIL} subject:"Sign in to Llunio Studios"`)).find((x) => new Date(x.Created) > since);
    const newest = fresh ? (await (await fetch(`${MAILPIT_URL}/api/v1/message/${fresh.ID}`)).json()).HTML.match(/letter-spacing:0\.2em;color:#ece3d3;">(\d{6})</)?.[1] : code;
    await c.ev(`(() => { const h = document.querySelector('[data-pin-code-target=hidden]'); h.value = '${newest}'; h.form.requestSubmit(); })()`); await sleep(3000);
    check('the 6-digit code signs in as well', (await c.ev('location.pathname')) === '/account/orders' && (await c.ev(`document.querySelectorAll('main a[href*="/account/orders/"]').length`)) === 1);
    summary();
} finally { b.close(); c?.close(); }
