// Paying by card on the Card Payment page (Step 10.3, Stripe test mode):
// Stripe's card boxes; a missing card is outlined with the page's message and
// saves nothing; a declined card shows Stripe's message and a retry reuses
// the same order; a good card pays, the order is saved with every detail,
// linked to the account for its email (created quietly, no sign-in), the
// basket is emptied and the confirmation email sent; a second order by the
// same email joins the same account; the order's pages are open only to the
// visitor who placed it; "Order & Slot Confirmed" (with "Gift · Arrive by"
// for a gift, which is saved on the order); admin's gift column and order
// details. Typing goes into Stripe's frames as a customer's would. Takes a size: 1440 900 (default) or 390 844.
import { open, sleep, checker, sql, BASE_URL, MAILPIT_URL } from './lib.mjs';

const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const { check, summary } = checker();
const b = await open(W, H, 'checkout-pay' + W, { mobile: W < 900 });
const { send, ev } = b;
const EMAIL = `ada.pay.${W}@example.test`;
const count = (query) => Number(sql(query).match(/\d+/g)?.pop() ?? 0);
try {
    const go = async (url, wait = 2200) => { await send('Page.navigate', { url }); await sleep(wait); };
    const lamp = { 'The Pigment': 'White', 'Cable Colour': 'Slate Grey', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED' };
    const add = async (size = 'lumen-forma-gallery') => {
        await go(BASE_URL + '/lighting-designs', 1800);
        await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
        await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size, quantity: 1, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`);
        await sleep(2500);
    };
    const openCheckout = async () => {
        await go(BASE_URL + '/checkout', 2000);
        for (let i = 0; i < 30 && (await ev(`document.querySelectorAll('.ll-card-box iframe').length`)) < 3; i++) await sleep(500);
        await sleep(1500);
    };
    const fill = (values) => ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(values)})) { const e = document.querySelector('[name="card_payment_form[' + k + ']"]'); if (e.type === 'checkbox') e.checked = v; else e.value = v; } })()`);
    // Type into one of Stripe's boxes: click into its frame, clear it, type.
    // A click can land before the frame takes focus, so check the box ended
    // up complete (Stripe marks it) and type again if not.
    const card = async (box, text) => {
        for (let attempt = 0; attempt < 3; attempt++) {
            const [x, y] = await ev(`(() => { const b = document.querySelector('[data-checkout-target=${box}]'); b.scrollIntoView({ block: 'center' }); const q = b.getBoundingClientRect(); return [q.left + 60, q.top + q.height / 2]; })()`);
            await sleep(400);
            await send('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', clickCount: 1 });
            await send('Input.dispatchMouseEvent', { type: 'mouseReleased', x, y, button: 'left', clickCount: 1 });
            await sleep(500);
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'a', code: 'KeyA', commands: ['selectAll'] });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'a', code: 'KeyA' });
            await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Backspace', code: 'Backspace', windowsVirtualKeyCode: 8 });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Backspace', code: 'Backspace', windowsVirtualKeyCode: 8 });
            await send('Input.insertText', { text });
            await sleep(800);
            if (await ev(`document.querySelector('[data-checkout-target=${box}]').classList.contains('StripeElement--complete')`)) return;
        }
    };
    // Stripe marks each box complete once what was typed is a whole card number, date or code.
    const cardsComplete = async () => { for (let i = 0; i < 20 && (await ev(`document.querySelectorAll('.ll-card-box.StripeElement--complete').length`)) < 3; i++) await sleep(250); };
    const pay = async (wait = 12000) => {
        await ev(`document.querySelector('main aside button[type=submit]').click()`);
        for (let i = 0; i < wait / 500; i++) {
            await sleep(500);
            const s = await ev(`({ path: location.pathname, message: document.querySelector('[data-checkout-target=message]')?.hidden === false ? document.querySelector('[data-checkout-target=message]').textContent.trim() : '', busy: !!document.querySelector('main aside button[type=submit]')?.disabled })`).catch(() => null);
            if (s && (s.path !== '/checkout' || (s.message && !s.busy))) return s;
        }
        return ev(`({ path: location.pathname, message: document.querySelector('[data-checkout-target=message]')?.textContent.trim() ?? '' })`);
    };
    const details = { email: EMAIL, phone: '07700 900123', name: 'Ada Test', company: 'Test Ltd', line1: '1 High Street', line2: 'Flat 2', town: 'Wimborne', county: 'Dorset', postcode: 'BH21 1AA', notes: 'Side gate', cardName: 'ADA TEST', billingSame: false, billingName: 'Ada Billing', billingLine1: '9 Bank Road', billingTown: 'Poole', billingPostcode: 'bh15 1aa' };
    await send('Network.enable'); await send('Network.clearBrowserCookies');

    // Orders left by an earlier run of this check (if not cleaned up) don't count.
    const paidBefore = count(`select count(*) from orders where email = '${EMAIL}' and status = 'paid'`);
    const waitingBefore = count(`select count(*) from orders where email = '${EMAIL}' and status = 'awaiting_payment'`);
    const cancelledBefore = count(`select count(*) from orders where email = '${EMAIL}' and status = 'cancelled'`);
    await add();
    await openCheckout();
    check("Stripe's three card boxes are on the page", (await ev(`document.querySelectorAll('.ll-card-box iframe').length`)) === 3);

    // No card: nothing saved.
    const before = count(`select count(*) from orders where email = '${EMAIL}'`);
    await fill(details);
    let s = await pay();
    const outlined = await ev(`[...document.querySelectorAll('.ll-card-box')].map(b => b.classList.contains('is-wrong'))`);
    check('no card: "Please complete the required fields to continue.", the card boxes outlined, no order', s.path === '/checkout' && s.message === 'Please complete the required fields to continue.' && outlined.every(Boolean) && count(`select count(*) from orders where email = '${EMAIL}'`) === before, JSON.stringify({ s, outlined }));

    // A declined card.
    await card('cardNumber', '4000000000000002'); await card('cardExpiry', '1234'); await card('cardCvc', '123');
    await cardsComplete();
    s = await pay();
    check("a declined card: Stripe's message, still on the page", s.path === '/checkout' && /declined/i.test(s.message), JSON.stringify(s));
    const first = sql(`select order_number from orders where email = '${EMAIL}' order by created_at desc limit 1`).match(/LL-[\w-]+|[A-Z]{2,}-\d[\w-]*/)?.[0];
    check('the order waits for payment', count(`select count(*) from orders where email = '${EMAIL}' and status = 'awaiting_payment'`) === waitingBefore + 1, first);

    // A good card: paid.
    await card('cardNumber', '4242424242424242');
    await cardsComplete();
    s = await pay(20000);
    check('a good card pays and goes to the completion page', /^\/checkout\/complete\//.test(s.path), JSON.stringify(s));
    const orderNumber = s.path.split('/').pop();
    check('the retry used the same order (one order, now paid)', count(`select count(*) from orders where email = '${EMAIL}'`) === before + 1 && count(`select count(*) from orders where order_number = '${orderNumber}' and status = 'paid' and paid_at is not null`) === 1, orderNumber);
    const done = (await ev(`document.querySelector('main').innerText`)).replace(/\s+/g, ' ').trim();
    check('"Order & Slot Confirmed", "Added to Studio Casting Schedule" and the confirmation line (no gift line)', done === 'ORDER & SLOT CONFIRMED Added to Studio Casting Schedule Confirmation has been sent to the email address provided.', done);
    check('laid out like the other thank-you pages', await ev(`(() => { const h = document.querySelector('main h1'); return getComputedStyle(h).fontSize === '28.8px' && Math.round(h.parentElement.getBoundingClientRect().width) === Math.min(1200, innerWidth) && getComputedStyle(document.querySelector('main p')).fontSize === '15px'; })()`) || W < 900);
    const row = sql(`select o.contact_phone, o.delivery_notes, o.shipping_cost, o.total, s.recipient_name, s.company, s.line1, s.line2, s.city, s.county, s.postcode, s.phone, bl.recipient_name as billing, bl.postcode as billing_postcode, u.email from orders o join address s on s.id = o.shipping_address_id left join address bl on bl.id = o.billing_address_id join users u on u.id = o.user_id where o.order_number = '${orderNumber}'`).replace(/\s+/g, ' ');
    check('saved with every detail (contact phone, notes, DPD price, company, delivery and billing addresses) and linked to the account for its email', row.includes(`07700 900123 Side gate 1295 81195 Ada Test Test Ltd 1 High Street Flat 2 Wimborne Dorset BH21 1AA 07700 900123 Ada Billing BH15 1AA ${EMAIL}`), row);
    check('the account was created quietly (nobody signed in)', count(`select count(*) from users where email = '${EMAIL}'`) === 1 && !/Sign out|Your orders/i.test(await ev(`document.querySelector('#navbar').innerText`)));
    await go(BASE_URL + '/basket');
    check('the basket is empty', /empty/i.test(await ev(`document.querySelector('main').innerText`)));
    await sleep(1000);
    const mail = (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent('to:' + EMAIL)}`)).json()).messages || [];
    check('the confirmation email was sent', mail.some((m) => m.Subject.includes(orderNumber)), JSON.stringify(mail.map((m) => m.Subject)));

    // Another visitor can't see it.
    const other = await open(W, H, 'checkout-pay-other' + W, { mobile: W < 900 });
    await other.send('Network.enable'); await other.send('Network.clearBrowserCookies');
    await other.send('Page.navigate', { url: `${BASE_URL}/checkout/complete/${orderNumber}` }); await sleep(2000);
    check("another visitor can't open this order's page", /not found|404/i.test(await other.ev('document.title + document.body.innerText.slice(0, 300)')));
    other.close();

    // A second order by the same email joins the same account; a changed basket gets a new order.
    await add('lumen-forma-studio');
    await openCheckout();
    await fill({ ...details, name: 'Ada Again' });
    await card('cardNumber', '4000000000000002'); await card('cardExpiry', '1234'); await card('cardCvc', '123');
    await cardsComplete();
    await pay();
    const pending = sql(`select order_number from orders where email = '${EMAIL}' and status = 'awaiting_payment'`);
    await add('lumen-forma-studio');
    await openCheckout();
    await fill(details);
    await card('cardNumber', '4242424242424242'); await card('cardExpiry', '1234'); await card('cardCvc', '123');
    await cardsComplete();
    s = await pay(20000);
    check('after the basket changed, the earlier unpaid order is cancelled and a new one is paid', /^\/checkout\/complete\//.test(s.path) && count(`select count(*) from orders where email = '${EMAIL}' and status = 'cancelled'`) === cancelledBefore + 1 && /awaiting_payment|cancelled|LL|\w/.test(pending), JSON.stringify(s));
    // A gift: the same details as any order; DPD's delivery phone is the customer's mobile.
    await add();
    await go(BASE_URL + '/basket');
    const arrive = await ev(`document.querySelector('main aside input[type=date]').min`);
    await ev(`[...document.querySelectorAll('main aside label')].find(l => /This is a gift/.test(l.textContent)).click()`);
    await ev(`document.querySelector('main aside input[type=date]').value = '${arrive}'`);
    await ev(`[...document.querySelectorAll('main aside button')].find(e => e.textContent.trim() === 'Proceed to Payment').click()`); await sleep(2000);
    await openCheckout();
    await fill({ ...details, billingSame: true, name: 'Grace Friend' });
    await card('cardNumber', '4242424242424242'); await card('cardExpiry', '1234'); await card('cardCvc', '123');
    await cardsComplete();
    s = await pay(20000);
    const giftOrder = s.path.split('/').pop();
    const nice = new Date(arrive + 'T12:00:00Z').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
    check(`a gift's confirmation adds "Gift · Arrive by ${nice}"`, (await ev(`document.querySelector('main').innerText`)).includes(`Gift · Arrive by ${nice}`));
    check('the gift and its date are saved on the order', sql(`select case when gift then 'gift-yes' else 'gift-no' end || ' ' || coalesce(to_char(gift_arrive_by, 'YYYY-MM-DD'), 'none') as g from orders where order_number = '${giftOrder}'`).includes(`gift-yes ${arrive}`));
    check("a gift pays; the delivery phone is the customer's own mobile", /^\/checkout\/complete\//.test(s.path) && sql(`select o.contact_phone, s.phone, s.recipient_name from orders o join address s on s.id = o.shipping_address_id where o.order_number = '${giftOrder}'`).replace(/\s+/g, ' ').includes('07700 900123 07700 900123 Grace Friend'), JSON.stringify(s));
    check('all three paid orders belong to the one account for that email', count(`select count(distinct user_id) from orders where email = '${EMAIL}' and status = 'paid'`) === 1 && count(`select count(*) from orders where email = '${EMAIL}' and status = 'paid'`) === paidBefore + 3 && count(`select count(*) from users where email = '${EMAIL}'`) === 1);
    // Admin: the gift column and an order's checkout details.
    if (W >= 900) {
        const ADMIN = 'lluniostudios@gmail.com';
        await go(BASE_URL + '/login');
        await ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
        const codeMail = (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent('to:' + ADMIN + ' subject:"sign-in code"')}`)).json()).messages?.[0];
        const code = (codeMail ? (await (await fetch(`${MAILPIT_URL}/api/v1/message/${codeMail.ID}`)).json()).Text : '').match(/\b\d{6}\b/)?.[0];
        await ev(`(() => { const h = document.querySelector('[data-pin-code-target=hidden]'); h.value = '${code}'; h.form.requestSubmit(); })()`); await sleep(3000);
        await go(BASE_URL + '/admin/order', 3000);
        const list = await ev(`({ heads: [...document.querySelectorAll('table thead th')].map(t => t.innerText.trim()), rows: [...document.querySelectorAll('table tbody tr')].map(r => r.innerText.replace(/\s+/g, ' ')) })`);
        check(`orders list: a "Gift" column, "Arrive by ${nice}" for the gift order, "No" for the others`, list.heads.includes('Gift') && list.rows.some((r) => r.includes(giftOrder) && r.includes(`Arrive by ${nice}`)) && list.rows.some((r) => r.includes(orderNumber) && /\bNo\b/.test(r)), JSON.stringify(list).slice(0, 400));
        const id = sql(`select id from orders where order_number = '${orderNumber}'`).match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)[0];
        await go(`${BASE_URL}/admin/order/${id}`, 3000);
        const page = (await ev(`document.querySelector('main, .content-wrapper, body').innerText`)).replace(/\s+/g, ' ');
        check("an order's page shows the mobile, delivery instructions and billing address", /Mobile 07700 900123/.test(page) && /Delivery instructions Side gate/.test(page) && /Billing address Ada Billing/.test(page), page.slice(0, 300));
    }
    summary();
} finally { b.close(); }
