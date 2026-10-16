// Gift options and greeting names (Step 11.1b, decided 9 October 2026).
// Checkout: the basket's "Send to me" / "Send directly to the recipient"
// (required); sent directly, Card Payment asks for the recipient's name,
// "Recipient's mobile (optional)" and "Gift message (optional)"; the order
// keeps them, DPD's phone is the recipient's mobile, and the buyer is the
// name on card (greeted "Dear Ada," from "mrs ada LOVELACE"); email 01, the
// private page, admin and the studio's email ("DIRECT", "Direct to
// recipient: no prices in the box", the gift message) show the gift.
// "Send to me" shows no recipient fields. A wallet's payer name is the buyer
// ("DR BOB PAYER" → "Dear Bob,"). Outside the UK mainland a direct gift goes
// to Reserve Your Design with every gift detail. Reserve Your Design: the
// same options and their messages; the request, email 02 and the studio's
// email show them; quoted and paid, the order carries them.
// Paid with Stripe's test card (pm_card_visa) through the page's own requests.
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL } from './lib.mjs';

const { check, summary } = checker();
const b = await open(1440, 1000, 'gift-options', { mobile: false });
let a = null;
const EMAIL = 'ada.giftoptions@example.test';
const ADMIN = 'lluniostudios@gmail.com';
const MESSAGE = 'Happy birthday, Grace!\nLove, Ada';
const mailpit = async (query) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json()).messages || [];
const message = async (id) => (await fetch(`${MAILPIT_URL}/api/v1/message/${id}`)).json();
const flat = (html) => html.replace(/<br\s*\/?>/g, ' / ').replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&#039;/g, "'").replace(/\s+/g, ' ');
const uuid = (text) => text.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)?.[0];
const go = async (x, url, wait = 2500) => { await x.send('Page.navigate', { url }); await sleep(wait); };
const latest = async (query, since, includes = '') => { for (let i = 0; i < 10; i++) { for (const m of await mailpit(query)) if (new Date(m.Created) > since && (await message(m.ID)).HTML.includes(includes)) return message(m.ID); await sleep(500); } return null; };
const lamp = { 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED' };
const addGallery = async () => {
    await go(b, BASE_URL + '/lighting-designs', 1800);
    await b.ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
    await b.ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`); await sleep(2500);
};
// The basket's gift choice: sendTo 'me', 'recipient' or null (none chosen).
const basketGift = async (sendTo) => {
    await go(b, BASE_URL + '/basket');
    const arrive = await b.ev(`document.querySelector('[data-gift-route-earliest-value]').dataset.giftRouteEarliestValue`);
    await b.ev(`(() => { const box = document.querySelector('[name$="[gift]"]'); if (!box.checked) box.closest('label').click(); })()`);
    await b.ev(`document.querySelector('main aside input[type=date]').value = '${arrive}'`);
    if (sendTo) await b.ev(`document.querySelector('[name$="[sendTo]"][value=${sendTo}]').closest('label').click()`);
    await b.ev(`[...document.querySelectorAll('main aside button')].find(e => e.textContent.trim() === 'Proceed to Payment').click()`); await sleep(2500);
    return arrive;
};
// Admin sign-in (the code from Mailpit); again after the visitor's cookies are cleared.
const signInAdmin = async () => {
    const since = new Date();
    await go(b, BASE_URL + '/login');
    await b.ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
    let code;
    for (let i = 0; i < 20 && !code; i++) {
        const m = (await mailpit(`to:${ADMIN} subject:"Sign in to Llunio Studios"`)).find((x) => new Date(x.Created) > since);
        if (m) code = (await message(m.ID)).HTML.match(/data-login-code>(\d{6})</)?.[1];
        if (!code) await sleep(500);
    }
    await b.ev(`(() => { const h = document.querySelector('[data-pin-code-target=hidden]'); h.value = '${code}'; h.form.requestSubmit(); })()`); await sleep(3000);
};
const niceDate = (iso) => new Date(iso + 'T12:00:00Z').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
const shortDate = (iso) => new Date(iso + 'T12:00:00Z').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', timeZone: 'UTC' });
try {
    // Start from nothing for this test email.
    sql(`with o as (select id, shipping_address_id, billing_address_id from orders where email = '${EMAIL}'), i as (delete from order_item where order_id in (select id from o) returning 1), d as (delete from orders where id in (select id from o) returning shipping_address_id, billing_address_id) delete from address where id in (select shipping_address_id from d union select billing_address_id from d)`);
    sql(`delete from design_request where email = '${EMAIL}'`);
    sql(`delete from users where email = '${EMAIL}'`);
    await b.send('Network.enable'); await b.send('Network.clearBrowserCookies');

    // 1. The basket: where to send it is required.
    await addGallery();
    await basketGift(null);
    const refused = await b.ev(`({ path: location.pathname, error: document.querySelector('[data-gift-send-to] [role=alert]')?.textContent.trim(), choices: [...document.querySelectorAll('[data-gift-send-to] label')].map(l => l.textContent.trim()) })`);
    check('the basket asks "Where should we send it?": "Send to me" / "Send directly to the recipient", required ("Please choose where to send it.")', refused.path === '/basket' && refused.error === 'Please choose where to send it.' && JSON.stringify(refused.choices) === JSON.stringify(['Send to me', 'Send directly to the recipient']), JSON.stringify(refused));

    // 2. "Send to me": no recipient fields on Card Payment.
    await basketGift('me');
    const toMe = await b.ev(`({ billingSame: document.querySelector('[name$="[billingSame]"]').checked, path: location.pathname, name: document.querySelector('label[for$="_name"] span')?.textContent.trim(), message: !!document.querySelector('[name$="[giftMessage]"]'), phone: !!document.querySelector('[name$="[recipientPhone]"]'), gift: document.querySelector('[data-checkout-gift]')?.innerText.replace(/\\s+/g, ' ') })`);
    check('"Send to me": Card Payment asks for the usual "Full Name", no gift message or recipient\'s mobile; the summary says "Send to me"', toMe.billingSame && toMe.path === '/checkout' && toMe.name === 'Full Name *' && !toMe.message && !toMe.phone && /Send to me/.test(toMe.gift ?? ''), JSON.stringify(toMe));

    // 3. "Send directly to the recipient".
    const arrive = await basketGift('recipient');
    const direct = await b.ev(`({ billingSame: document.querySelector('[name$="[billingSame]"]').checked, billingShown: document.querySelector('[name$="[billingName]"]').getBoundingClientRect().height > 0, section: [...document.querySelectorAll('main div')].find(d => /^Delivery address/.test(d.textContent))?.innerText.replace(/\\s+/g, ' '), name: document.querySelector('label[for$="_name"] span')?.textContent.trim(), labels: [...document.querySelectorAll('main label span:first-child')].map(s => s.textContent.trim()), gift: document.querySelector('[data-checkout-gift]')?.innerText.replace(/\\s+/g, ' '), max: document.querySelector('[name$="[giftMessage]"]')?.maxLength })`);
    check('sent directly, "Same as delivery address" starts unticked: the buyer enters their own billing address', direct.billingSame === false && direct.billingShown, JSON.stringify(direct).slice(0, 120));
    check('"Send directly to the recipient": "Recipient\'s full name *", "Recipient\'s mobile (optional)", "Gift message (optional)" (up to 250 characters); the section says the address is the recipient\'s', direct.name === "Recipient's full name *" && direct.labels.some((l) => l.startsWith("Recipient's mobile (optional)")) && direct.labels.some((l) => l.startsWith('Gift message (optional)')) && direct.max === 250 && /the recipient's: sent directly, with no prices in the box/.test(direct.section ?? '') && /Send directly to the recipient/.test(direct.gift ?? ''), JSON.stringify(direct));
    await b.shot(out('gift-direct-checkout.png'));

    // A gift message over 250 characters is refused.
    const fill = async (values) => b.ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(values)})) { const e = document.querySelector('[name="card_payment_form[' + k + ']"]'); if (e) { if (e.type === 'checkbox') e.checked = !!v; else e.value = v; } } })()`);
    // The site's form token is made when a form is submitted (csrf_protection_controller.js): a submit event the page's own script doesn't act on.
    const prime = `(() => { const form = document.querySelector('main form'); form.addEventListener('submit', (e) => { e.preventDefault(); e.stopImmediatePropagation(); }, { capture: true, once: true }); form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); })();`;
    const pay = () => b.ev(`(async () => { ${prime} const form = document.querySelector('main form'); const body = new FormData(form); body.append('card_ready', '1'); const r = await fetch(form.action || location.href, { method: 'POST', body, headers: { Accept: 'application/json' } }); return r.json(); })()`);
    // The buyer's own billing address (its name is the buyer's, used for greetings).
    const details = { email: EMAIL, phone: '07700 900123', name: 'Grace Friend', line1: '2 Gift Lane', town: 'Wimborne', postcode: 'BH21 1AA', cardName: 'A LOVELACE', recipientPhone: '07700 900999', billingName: 'mrs ada LOVELACE', billingLine1: '1 High Street', billingTown: 'Wimborne', billingPostcode: 'SW1A 1AA' };
    await fill({ ...details, giftMessage: 'x'.repeat(251) });
    let r = await pay();
    check('a gift message over 250 characters is refused', r.status === 'invalid' && /250 characters or fewer/.test(r.errors?.giftMessage ?? ''), JSON.stringify(r).slice(0, 200));

    // Paid.
    await fill({ ...details, giftMessage: MESSAGE });
    let since = new Date();
    r = await pay();
    if (r.status !== 'confirm') console.log('pay:', JSON.stringify(r));
    const paid = r.status === 'confirm' ? await b.ev(`Stripe(document.querySelector('main form').dataset.checkoutStripeKeyValue).confirmCardPayment(${JSON.stringify(r.clientSecret)}, { payment_method: 'pm_card_visa' }).then(x => x.error ? x.error.message : x.paymentIntent.status)`) : r;
    await go(b, r.completeUrl.replace(/^https?:\/\/[^/]+/, BASE_URL), 3000);
    const orderNumber = r.completeUrl.split('/').pop();
    const row = sql(`select o.customer_name, o.gift_send_to, o.gift_recipient_phone, o.contact_phone, s.recipient_name, s.phone, u.first_name, replace(o.gift_message, chr(10), '|') from orders o join address s on s.id = o.shipping_address_id join users u on u.id = o.user_id where o.order_number = '${orderNumber}'`).replace(/\s+/g, ' ');
    check('paid; the order keeps the buyer (the billing name), "recipient", the message and the recipient\'s mobile; DPD\'s phone is the recipient\'s; the account is the buyer\'s', paid === 'succeeded' && /mrs ada LOVELACE recipient 07700 900999 07700 900123 Grace Friend 07700 900999 mrs Happy birthday, Grace!\|Love, Ada/.test(row), row);

    // Email 01.
    const mail = await latest(`to:${EMAIL} subject:"Your Llunio Studios order"`, since, orderNumber);
    const text = flat(mail?.HTML ?? '');
    check('email 01 greets the buyer, "Dear Ada," (from "mrs ada LOVELACE"), never the recipient; addressed to the buyer', /Dear Ada,/.test(text) && !/Dear Grace/.test(text) && (mail?.To?.[0]?.Name ?? '') === 'mrs ada LOVELACE', JSON.stringify(mail?.To));
    check(`email 01's delivery: the recipient's address, "Gift: Arrive by ${niceDate(arrive)} / Send directly to the recipient", the gift message`, text.includes(`Delivery address Grace Friend / 2 Gift Lane / Wimborne / BH21 1AA Gift Arrive by ${niceDate(arrive)} / Send directly to the recipient Gift message Happy birthday, Grace! / Love, Ada`), text.slice(text.indexOf('03 Delivery'), text.indexOf('03 Delivery') + 300));

    // The private page.
    const view = (mail?.HTML ?? '').match(/<a href="([^"]+\/order\/[A-Za-z0-9_-]+)"[^>]*>View your order</)?.[1].replace(/^https?:\/\/[^/]+/, BASE_URL);
    a = await open(1440, 1000, 'gift-options-view', { mobile: false });
    await a.send('Network.enable'); await a.send('Network.clearBrowserCookies');
    await go(a, view);
    const viewText = await a.ev(`({ gift: document.querySelector('[data-order-gift]')?.innerText.replace(/\\s+/g, ' '), message: document.querySelector('[data-order-gift-message]')?.innerText })`);
    check('the private order page shows the date, "Send directly to the recipient" and the gift message', viewText.gift === `Gift Arrive by ${niceDate(arrive)} Send directly to the recipient` && /Happy birthday, Grace!\nLove, Ada/.test(viewText.message ?? ''), JSON.stringify(viewText));
    a.close(); a = null;

    // The studio's email.
    const studio = await latest('to:lluniostudios@gmail.com subject:"New order"', since, orderNumber);
    const studioHtml = studio?.HTML ?? '';
    const studioSummary = studioHtml.match(/data-studio-summary>([\s\S]*?)<\/table>/)?.[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    check(`the studio: "… · GIFT by ${shortDate(arrive)} · DIRECT", "Gift · Needed by", "Direct to recipient: no prices in the box", the gift message for the card, the buyer and the recipient`, studio?.Subject === `New order · Gallery × 1 · £811.95 · GIFT by ${shortDate(arrive)} · DIRECT` && /data-studio-needed-by>Gift · Needed by/.test(studioHtml) && /data-studio-direct>Direct to recipient: no prices in the box</.test(studioHtml) && /data-studio-gift-message>Happy birthday, Grace!<br \/>\s*Love, Ada</.test(studioHtml) && /Customer mrs ada LOVELACE Recipient Grace Friend/.test(studioSummary ?? '') && /Delivery phone \(DPD\)[\s\S]*?07700 900999/.test(studioHtml), studio?.Subject + ' | ' + studioSummary);

    // Admin.
    await signInAdmin();
    const orderId = uuid(sql(`select id from orders where order_number = '${orderNumber}'`));
    await go(b, `${BASE_URL}/admin/order/${orderId}`, 3000);
    const adminText = await b.ev(`document.querySelector('main, .content').innerText.replace(/\\s+/g, ' ')`);
    check('admin: the gift (date, direct), "Direct to recipient: no prices in the box", the message, the recipient\'s mobile, the buyer', adminText.includes(`Gift Arrive by ${niceDate(arrive)} · Direct to recipient`) && /Packing Direct to recipient: no prices in the box/i.test(adminText) && /Gift Message Happy birthday, Grace!/i.test(adminText) && /Recipient's Mobile 07700 900999/i.test(adminText) && /Customer \(buyer\) mrs ada LOVELACE/i.test(adminText), adminText.slice(adminText.indexOf('Gift'), adminText.indexOf('Gift') + 400));

    // 4. A wallet: the payer's name is the buyer ("DR BOB PAYER" → "Dear Bob,").
    await b.send('Network.clearBrowserCookies');
    await addGallery();
    await basketGift('recipient');
    since = new Date();
    const wallet = { email: EMAIL, phone: '07700 900123', shipping: { name: 'Grace Friend', line1: '2 Gift Lane', city: 'Wimborne', postal_code: 'BH21 1AA', country: 'GB' }, billing: { name: 'DR BOB PAYER', postal_code: 'BH21 1AA', country: 'GB' }, giftMessage: 'From Bob', recipientPhone: '' };
    r = await b.ev(`fetch('/checkout/express', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: ${JSON.stringify(JSON.stringify(wallet))} }).then(r => r.json())`);
    const walletPaid = r.status === 'confirm' ? await b.ev(`Stripe(document.querySelector('main form').dataset.checkoutStripeKeyValue).confirmCardPayment(${JSON.stringify(r.clientSecret)}, { payment_method: 'pm_card_visa' }).then(x => x.error ? x.error.message : x.paymentIntent.status)`) : r;
    await go(b, r.completeUrl.replace(/^https?:\/\/[^/]+/, BASE_URL), 3000);
    const walletOrder = r.completeUrl.split('/').pop();
    const walletRow = sql(`select o.customer_name, o.gift_message, s.phone from orders o join address s on s.id = o.shipping_address_id where o.order_number = '${walletOrder}'`).replace(/\s+/g, ' ');
    const walletMail = await latest(`to:${EMAIL} subject:"Your Llunio Studios order"`, since, walletOrder);
    check('a wallet payment: the buyer is the payer (DR BOB PAYER), greeted "Dear Bob,"; the message kept; with no recipient\'s mobile DPD has the customer\'s', walletPaid === 'succeeded' && /DR BOB PAYER From Bob 07700 900123/.test(walletRow) && /Dear Bob,/.test(flat(walletMail?.HTML ?? '')), walletRow);

    // 5. Outside the UK mainland: a direct gift goes to Reserve Your Design with every detail.
    await b.send('Network.clearBrowserCookies');
    await addGallery();
    const arriveOutside = await basketGift('recipient');
    await fill({ email: EMAIL, phone: '07700 900123', name: 'Grace Friend', line1: '2 Gift Lane', town: 'Belfast', postcode: 'BT1 1AA', cardName: 'Ada Lovelace', recipientPhone: '07700 900999', giftMessage: 'From Ada', billingName: 'Ada Lovelace', billingLine1: '1 High Street', billingTown: 'Wimborne', billingPostcode: 'SW1A 1AA' });
    r = await b.ev(`(async () => { ${prime} const form = document.querySelector('main form'); const r = await fetch(form.action || location.href, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } }); return r.json(); })()`);
    await go(b, r.url?.startsWith('http') ? r.url : BASE_URL + r.url, 3000);
    const carried = await b.ev(`(() => { const v = (n) => document.querySelector('[name="reserve_form[' + n + ']"]'); return { path: location.pathname, fullName: v('fullName')?.value, gift: v('gift')?.checked, neededBy: v('giftNeededBy')?.value, direct: document.querySelector('[name="reserve_form[giftSendTo]"][value=recipient]')?.checked, recipient: v('giftRecipientName')?.value, message: v('giftMessage')?.value, phone: v('giftRecipientPhone')?.value, postcode: v('postcode')?.value }; })()`);
    check('outside the mainland, Reserve Your Design opens with the gift: ticked, the date, "Send directly to the recipient", the recipient, the message and mobile; the customer is the buyer', carried.path === '/reserve' && carried.fullName === 'Ada Lovelace' && carried.gift === true && carried.neededBy === arriveOutside && carried.direct === true && carried.recipient === 'Grace Friend' && carried.message === 'From Ada' && carried.phone === '07700 900999' && carried.postcode === 'BT1 1AA', JSON.stringify(carried));

    // 6. Reserve Your Design: the gift's messages.
    await b.send('Network.clearBrowserCookies');
    await go(b, BASE_URL + '/lighting-designs', 1800);
    await b.ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
    await b.ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-atelier', quantity: 1, mode: 'all', lamps: [{ ...lamp, 'Bulb Count': 'Dual Array' }] }))}; f.requestSubmit(); })()`); await sleep(2500);
    const reserveForm = { fullName: 'MRS ADA LOVELACE', email: EMAIL, phone: '07700 900123', addressLine1: '2 Gift Lane', town: 'Wimborne', postcode: 'BH21 1AA' };
    const fillReserve = (values) => b.ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(values)})) { const e = document.querySelector('[name="reserve_form[' + k + ']"]'); if (e) e.value = v; } })()`);
    if ((await b.ev('location.pathname')) !== '/reserve') await go(b, BASE_URL + '/reserve');
    const hidden = await b.ev(`({ date: getComputedStyle(document.querySelector('[data-gift-only]')).display, direct: getComputedStyle(document.querySelector('[data-direct-only]')).display })`);
    await b.ev(`document.querySelector('[name="reserve_form[gift]"]').closest('label').click()`); await sleep(200);
    const shown = await b.ev(`getComputedStyle(document.querySelector('[data-gift-only]')).display`);
    await b.ev(`document.querySelector('[name="reserve_form[giftSendTo]"][value=recipient]').closest('label').click()`); await sleep(200);
    const shownDirect = await b.ev(`getComputedStyle(document.querySelector('[data-direct-only]')).display`);
    check('Reserve Your Design: the gift\'s date and choice appear once "This is a gift" is ticked, the recipient\'s fields once sent directly', hidden.date === 'none' && hidden.direct === 'none' && shown === 'flex' && shownDirect !== 'none', JSON.stringify({ hidden, shown, shownDirect }));
    await b.ev(`document.querySelector('[name="reserve_form[giftSendTo]"][value=recipient]').checked = false; document.querySelector('[name="reserve_form[giftSendTo]"][value=me]').checked = false;`);
    await fillReserve(reserveForm);
    await b.ev(`document.querySelector('form[name=reserve_form]').requestSubmit()`); await sleep(3000);
    const errors = await b.ev(`[...document.querySelectorAll('[role=alert]')].map(e => e.textContent.trim()).filter(Boolean)`);
    check('a gift without its date or where to send it: "Please choose a date to continue.", "Please choose where to send it."', errors.includes('Please choose a date to continue.') && errors.includes('Please choose where to send it.'), JSON.stringify(errors));
    const earliest = await b.ev(`document.querySelector('[name="reserve_form[giftNeededBy]"]').min`);
    await fillReserve({ ...reserveForm, giftNeededBy: earliest });
    await b.ev(`document.querySelector('[name="reserve_form[giftSendTo]"][value=recipient]').closest('label').click()`);
    await b.ev(`document.querySelector('form[name=reserve_form]').requestSubmit()`); await sleep(3000);
    const errors2 = await b.ev(`[...document.querySelectorAll('[role=alert]')].map(e => e.textContent.trim()).filter(Boolean)`);
    check('sent directly without the recipient\'s name: "Please enter the recipient\'s full name."', errors2.includes("Please enter the recipient's full name."), JSON.stringify(errors2));
    await fillReserve({ ...reserveForm, giftNeededBy: earliest, giftRecipientName: 'Grace Friend', giftMessage: MESSAGE, giftRecipientPhone: '07700 900999' });
    await b.shot(out('gift-reserve.png'));
    since = new Date();
    await b.ev(`document.querySelector('form[name=reserve_form]').requestSubmit()`); await sleep(3500);
    const requestNumber = sql(`select request_number from design_request where email = '${EMAIL}' order by created_at desc limit 1`).match(/LR-\d{6}-\d{4}/)?.[0];
    const requestRow = sql(`select gift, gift_needed_by, gift_send_to, gift_recipient_name, gift_recipient_phone, replace(gift_message, chr(10), '|') from design_request where request_number = '${requestNumber}'`).replace(/\s+/g, ' ');
    check('the request keeps the gift: needed by, "recipient", the recipient\'s name and mobile, the message', (await b.ev('location.pathname')) === '/reserve/received' && requestRow.includes(`1 ${earliest} recipient Grace Friend 07700 900999 Happy birthday, Grace!|Love, Ada`), requestRow);
    const received = await latest(`to:${EMAIL} subject:"We have your request"`, since, requestNumber);
    const receivedText = flat(received?.HTML ?? '');
    check(`email 02: "Dear Ada," (from "MRS ADA LOVELACE"), "Gift: Needed by ${niceDate(earliest)} / Send directly to the recipient: Grace Friend", the message, the address with the recipient's name`, /Dear Ada,/.test(receivedText) && receivedText.includes(`Gift Needed by ${niceDate(earliest)} / Send directly to the recipient: Grace Friend Gift message Happy birthday, Grace! / Love, Ada`), receivedText.slice(receivedText.indexOf('01 Request'), receivedText.indexOf('01 Request') + 300));
    const requestStudio = await latest('to:lluniostudios@gmail.com subject:"New request"', since, requestNumber);
    check(`the studio: "New request · Atelier × 1 · GIFT by ${shortDate(earliest)} · DIRECT", "Direct to recipient: no prices in the box", the gift message, the recipient`, requestStudio?.Subject === `New request · Atelier × 1 · GIFT by ${shortDate(earliest)} · DIRECT` && /data-studio-direct>/.test(requestStudio?.HTML ?? '') && /data-studio-gift-message>Happy birthday, Grace!/.test(requestStudio?.HTML ?? '') && /Recipient Grace Friend/.test(flat(requestStudio?.HTML ?? '')), requestStudio?.Subject);
    const requestId = uuid(sql(`select id from design_request where request_number = '${requestNumber}'`));
    await signInAdmin();
    await go(b, `${BASE_URL}/admin/design-request/${requestId}`, 3000);
    const requestAdmin = await b.ev(`document.querySelector('main, .content').innerText.replace(/\\s+/g, ' ')`);
    check('admin: the request\'s gift, "Direct to recipient: no prices in the box", the recipient, the message and mobile', requestAdmin.includes(`Gift Needed by ${niceDate(earliest)} · Direct to recipient`) && /Packing Direct to recipient: no prices in the box/i.test(requestAdmin) && /Recipient's Name Grace Friend/i.test(requestAdmin) && /Gift Message Happy birthday, Grace!/i.test(requestAdmin) && /Recipient's Mobile 07700 900999/i.test(requestAdmin), requestAdmin.slice(requestAdmin.indexOf('Gift'), requestAdmin.indexOf('Gift') + 300));

    // 7. Quoted and paid: the order carries the gift.
    await go(b, `${BASE_URL}/admin/design-request/${requestId}/quote`, 3500);
    const quoteGift = await b.ev(`document.body.innerText.includes('Direct to recipient: no prices in the box')`);
    await b.ev(`(() => { document.querySelector('[data-quote-delivery]').value = '60'; document.querySelector('[name$="[deliveryLabel]"]').value = 'DPD Next Day Delivery'; document.querySelector('[name$="[castingDate]"]').value = '2026-11-16'; window.confirm = () => true; document.querySelector('[data-quote-send]').click(); })()`); await sleep(4000);
    const quoteMail = await latest(`to:${EMAIL} subject:"Your Lumen Forma quote"`, since, requestNumber);
    const quoteLink = (quoteMail?.HTML ?? '').match(/<a href="([^"]+\/quote\/[A-Za-z0-9_-]+)"/)?.[1].replace(/^https?:\/\/[^/]+/, BASE_URL);
    const quoteText = flat(quoteMail?.HTML ?? '');
    check('the quote (admin and email) shows the gift', quoteGift && quoteText.includes(`Gift Needed by ${niceDate(earliest)} / Send directly to the recipient`), JSON.stringify({ quoteGift, text: quoteText.slice(quoteText.indexOf('03 Casting'), quoteText.indexOf('03 Casting') + 200) }));
    a = await open(1440, 1000, 'gift-options-quote', { mobile: false });
    await a.send('Network.enable'); await a.send('Network.clearBrowserCookies');
    await go(a, quoteLink, 3000);
    const quotePage = await a.ev(`(() => { const v = (n) => document.querySelector('[name="card_payment_form[' + n + ']"]')?.value; return { billingSame: document.querySelector('[name$="[billingSame]"]').checked, name: v('name'), cardName: v('cardName'), message: v('giftMessage'), phone: v('recipientPhone'), gift: document.querySelector('[data-quote-gift]')?.innerText.replace(/\\s+/g, ' ') }; })()`);
    check('the quote page: "Same as delivery address" unticked, the recipient as the delivery name, the gift message and mobile filled in, the gift in the summary', quotePage.billingSame === false && quotePage.name === 'Grace Friend' && quotePage.message === MESSAGE && quotePage.phone === '07700 900999' && /Send directly to the recipient/.test(quotePage.gift ?? ''), JSON.stringify(quotePage));
    since = new Date();
    await a.ev(`(() => { for (const [k, v] of Object.entries({ billingName: 'Ada Lovelace', billingLine1: '1 High Street', billingTown: 'Wimborne', billingPostcode: 'SW1A 1AA' })) document.querySelector('[name="card_payment_form[' + k + ']"]').value = v; })()`);
    const started = await a.ev(`(async () => { const form = document.querySelector('main form'); const r = await fetch(form.dataset.checkoutExpressUrlValue, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } }); return r.json(); })()`);
    const quotePaid = await a.ev(`Stripe(document.querySelector('main form').dataset.checkoutStripeKeyValue).confirmCardPayment(${JSON.stringify(started.clientSecret)}, { payment_method: 'pm_card_visa' }).then(x => x.error ? x.error.message : x.paymentIntent.status)`);
    await go(a, started.completeUrl.replace(/^https?:\/\/[^/]+/, BASE_URL), 3000);
    const quoteOrder = sql(`select o.customer_name, o.gift, o.gift_arrive_by, o.gift_send_to, s.recipient_name, s.phone, replace(o.gift_message, chr(10), '|') from orders o join address s on s.id = o.shipping_address_id where o.design_request_id = '${requestId}' and o.status in ('paid', 'deposit_paid')`).replace(/\s+/g, ' ');
    const quoteOrderMail = await latest(`to:${EMAIL} subject:"Your Llunio Studios order"`, since);
    check('paid: the order carries the gift (date, "recipient", message), the recipient\'s name and mobile for DPD, the buyer; email 01 greets "Dear Ada,"', quotePaid === 'succeeded' && quoteOrder.includes(`MRS ADA LOVELACE 1 ${earliest} recipient Grace Friend 07700 900999 Happy birthday, Grace!|Love, Ada`) && /Dear Ada,/.test(flat(quoteOrderMail?.HTML ?? '')), quoteOrder);
    summary();
} finally { b.close(); a?.close(); }
