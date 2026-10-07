// Apple Pay / Google Pay on the Card Payment page (Step 10.4). Headless
// Chrome has no wallet, so this checks: the wallet buttons stay hidden when
// the device offers none; what the wallet sheet is told for a partial
// postcode (DPD price; outside the UK mainland, refused); and the server's
// side of a wallet payment, sending exactly what a wallet sends from the
// page and paying it with Stripe's test wallet card (pm_card_visa): the
// order saved and linked to the account, then paid; outside the mainland,
// Reserve Your Design with the address carried over; gifts allowed (decided
// 7 October 2026); addresses too long for DPD refused with "pay by card". (Pressing a real Apple Pay or
// Google Pay button is checked by hand: see docs/merge-plan.md, 10.4.)
import { open, sleep, checker, sql, BASE_URL } from './lib.mjs';

const { check, summary } = checker();
const b = await open(1440, 900, 'checkout-wallet', { mobile: false });
const { send, ev } = b;
const EMAIL = 'ada.wallet@example.test';
const count = (query) => Number(sql(query).match(/\d+/g)?.pop() ?? 0);
try {
    const go = async (url, wait = 2200) => { await send('Page.navigate', { url }); await sleep(wait); };
    const lamp = { 'The Pigment': 'White', 'Cable Colour': 'Slate Grey', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED' };
    const add = async () => {
        await go(BASE_URL + '/lighting-designs', 1800);
        await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
        await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`);
        await sleep(2500);
    };
    // What Stripe's Express Checkout hands the page after the customer approves.
    const wallet = (shipping = {}) => ({
        email: EMAIL, phone: '+447700900123',
        shipping: { name: 'Ada Wallet', line1: '1 High Street', line2: null, city: 'Wimborne', state: 'Dorset', postal_code: 'BH21 1AA', country: 'GB', ...shipping },
        billing: { name: 'Ada Wallet', line1: '1 High Street', city: 'Wimborne', postal_code: 'BH21 1AA', country: 'GB' },
    });
    const send_ = (body) => ev(`fetch(${JSON.stringify(BASE_URL + '/checkout/express')}, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: ${JSON.stringify(JSON.stringify(body))} }).then(async r => ({ code: r.status, ...(await r.json()) }))`);
    await send('Network.enable'); await send('Network.clearBrowserCookies');

    await add();
    await go(BASE_URL + '/checkout', 4000);
    for (let i = 0; i < 20 && (await ev(`document.querySelectorAll('.ll-card-box iframe').length`)) < 3; i++) await sleep(500);
    await sleep(3000);
    check('no wallet on this device: the Apple Pay / Google Pay area stays hidden', (await ev(`document.querySelector('[data-checkout-target=expressArea]').hidden`)) === true);
    check("Stripe's wallet buttons were set up on the page", (await ev(`document.querySelectorAll('[data-checkout-target=express] iframe').length`)) >= 1);

    // What the wallet sheet is told, from the half postcode a wallet shares.
    const area = (postcode) => ev(`fetch('/checkout/delivery?partial=1&postcode=${encodeURIComponent(postcode)}', { headers: { Accept: 'application/json' } }).then(r => r.json())`);
    let d = await area('BH21 1');
    check('wallet sheet, Dorset: DPD £12.95 added to the £799', d.state === 'mainland' && d.deliveryPence === 1295 && d.subtotalPence === 79900, JSON.stringify(d));
    d = await area('BT1 1');
    check('wallet sheet, Belfast: refused as outside the UK mainland', d.state === 'outside', JSON.stringify(d));
    d = await area('HS1');
    check('wallet sheet, Outer Hebrides: refused', d.state === 'outside');

    // The server's side of an approved wallet payment.
    let r = await send_(wallet({ line1: 'A very long address line that DPD cannot print' }));
    check('an address line longer than DPD allows: "pay by card" instead', r.code === 422 && r.status === 'error' && /pay by card/i.test(r.message), JSON.stringify(r));
    r = await send_(wallet());
    check('approved wallet details: the order is saved and a payment started', r.status === 'confirm' && /^pi_.*_secret_/.test(r.clientSecret) && /\/checkout\/complete\//.test(r.completeUrl), JSON.stringify(r).slice(0, 200));
    const orderNumber = r.completeUrl.split('/').pop();
    check("saved with the wallet's details, linked to the account for its email", sql(`select o.contact_phone, s.recipient_name, s.city, s.postcode, u.email from orders o join address s on s.id = o.shipping_address_id join users u on u.id = o.user_id where o.order_number = '${orderNumber}'`).replace(/\s+/g, ' ').includes(`+447700900123 Ada Wallet Wimborne BH21 1AA ${EMAIL}`));
    const paid = await ev(`Stripe(document.querySelector('main form').dataset.checkoutStripeKeyValue).confirmCardPayment(${JSON.stringify(r.clientSecret)}, { payment_method: 'pm_card_visa' }).then(x => x.error ? x.error.message : x.paymentIntent.status)`);
    check("paid with Stripe's test wallet card", paid === 'succeeded', paid);
    await go(r.completeUrl);
    check('the completion page marks it paid', count(`select count(*) from orders where order_number = '${orderNumber}' and status = 'paid'`) === 1);

    // Outside the mainland: Reserve Your Design with the address.
    await add();
    await go(BASE_URL + '/checkout', 3000);
    r = await send_(wallet({ line1: '2 Harbour Road', city: 'Belfast', state: '', postal_code: 'BT1 1AA' }));
    check('a wallet address in Northern Ireland goes to Reserve Your Design', r.status === 'redirect' && /\/reserve/.test(r.url), JSON.stringify(r));
    await go(BASE_URL + r.url.replace(/^https?:\/\/[^/]+/, ''));
    const reserve = await ev(`({ name: document.querySelector('[name="reserve_form[fullName]"]')?.value, address: document.querySelector('[name="reserve_form[deliveryAddress]"]')?.value, email: document.querySelector('[name="reserve_form[email]"]')?.value })`);
    check('with the address carried over', reserve.name === 'Ada Wallet' && reserve.address === '2 Harbour Road\nBelfast\nBT1 1AA' && reserve.email === EMAIL, JSON.stringify(reserve));

    // Gifts: wallets too.
    await go(BASE_URL + '/basket');
    const arrive = await ev(`document.querySelector('main aside input[type=date]').min`);
    await ev(`[...document.querySelectorAll('main aside label')].find(l => /This is a gift/.test(l.textContent)).click()`);
    await ev(`document.querySelector('main aside input[type=date]').value = '${arrive}'`);
    await ev(`[...document.querySelectorAll('main aside button')].find(e => e.textContent.trim() === 'Proceed to Payment').click()`); await sleep(2500);
    for (let i = 0; i < 20 && (await ev(`document.querySelectorAll('[data-checkout-target=express] iframe').length`)) < 1; i++) await sleep(500);
    check('a gift: the Apple Pay / Google Pay buttons are set up as for any order', (await ev(`document.querySelectorAll('[data-checkout-target=express] iframe').length`)) >= 1);
    r = await send_(wallet());
    check('and a wallet payment for a gift is accepted (gift date kept on the basket)', r.status === 'confirm' && /^pi_/.test(r.clientSecret), JSON.stringify(r).slice(0, 160));
    summary();
} finally { b.close(); }
