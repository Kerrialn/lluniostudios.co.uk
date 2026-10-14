// The Card Payment page (Step 10.2): no sign-in; the fields and DPD limits;
// the delivery price following the postcode; outside the UK mainland,
// "Reserve Your Design" with its note and the details carried to the request
// form; the required-fields message and outlines; the billing address; a
// gift asking for the same details plus "Gift · Arrive by"; a gift date that has become
// too soon. (Paying, and the saved order's details: checkout-pay.mjs.)
// Takes a size: 1440 900 (default) or 390 844.
import { open, sleep, checker, sql, BASE_URL } from './lib.mjs';

const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const { check, summary } = checker();
const b = await open(W, H, 'checkout-page' + W, { mobile: W < 900 });
const { send, ev } = b;
try {
    const go = async (url, wait = 2200) => { await send('Page.navigate', { url }); await sleep(wait); };
    const lamp = { 'The Pigment': 'White', 'Cable Colour': 'Slate Grey', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED' };
    const add = async (size = 'lumen-forma-gallery', quantity = 1) => {
        await go(BASE_URL + '/lighting-designs', 1800);
        await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
        await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size, quantity, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`);
        await sleep(2500);
    };
    const field = (name) => `document.querySelector('[name="card_payment_form[${name}]"]')`;
    const fill = (values) => ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(values)})) { const e = document.querySelector('[name="card_payment_form[' + k + ']"]'); if (e.type === 'checkbox') e.checked = v; else e.value = v; } })()`);
    // Type into a field; for the postcode, wait until the summary has the server's answer
    // for this postcode (it asks after a short pause, so a slow reply can lag behind).
    const type = async (name, value) => {
        const before = name === 'postcode' ? await ev(`fetch('/checkout/delivery?postcode=' + encodeURIComponent(${JSON.stringify(value)}), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => d.delivery + '|' + d.button)`) : null;
        await ev(`(() => { const e = ${field(name)}; e.focus(); e.value = ${JSON.stringify(value)}; e.dispatchEvent(new Event('input', { bubbles: true })); })()`);
        await sleep(600);
        for (let i = 0; before && i < 20; i++) {
            if ((await ev(`document.querySelector('[data-checkout-target=delivery]').textContent.trim() + '|' + document.querySelector('[data-checkout-target=button]').textContent.trim()`)) === before) break;
            await sleep(300);
        }
    };
    const aside = () => ev(`(() => { const a = document.querySelector('main aside'); const t = (s) => a.querySelector('[data-checkout-target=' + s + ']'); return { delivery: t('delivery').textContent.trim(), total: t('total').textContent.trim(), button: t('button').textContent.trim(), note: t('outsideNote').hidden ? '' : t('outsideNote').textContent.trim(), secure: !t('secure').hidden, gift: [...a.querySelectorAll('span')].find(s => s.textContent.trim() === 'Gift')?.nextElementSibling?.textContent.trim() ?? '', error: [...a.querySelectorAll('[role=alert]')].map(e => e.textContent.trim()).join(' | ') }; })()`);
    const submit = async () => { await ev(`document.querySelector('main form').requestSubmit()`); await sleep(2800); };
    const good = { email: 'ada.test@example.test', phone: '07700 900123', name: 'Ada Test', company: 'Test Ltd', line1: '1 High Street', line2: 'Flat 2', town: 'Wimborne', county: 'Dorset', postcode: 'bh21 1aa', notes: 'Leave with neighbour at 3' };
    await send('Network.enable'); await send('Network.clearBrowserCookies');

    await go(BASE_URL + '/checkout');
    check('an empty basket goes back to the basket', (await ev('location.pathname')) === '/basket');

    await add();
    await ev(`[...document.querySelectorAll('main aside button')].find(e => e.textContent.trim() === 'Proceed to Payment').click()`); await sleep(2500);
    let page = await ev(`({ path: location.pathname, h1: document.querySelector('h1').textContent.trim(), sections: [...document.querySelectorAll('main form > div > div')].filter(d => d.children.length === 0 && /^[A-Z]/.test(d.textContent.trim())).map(d => d.textContent.trim()), signIn: /sign in|6-digit|sign-in code/i.test(document.querySelector('main').innerText) })`);
    check('"Proceed to Payment" opens Card Payment, with no sign-in', page.path === '/checkout' && page.h1 === 'Card Payment' && !page.signIn, JSON.stringify(page));
    check('sections: Contact, Delivery address, Billing address, Card details', JSON.stringify(page.sections) === JSON.stringify(['Contact', 'Delivery address', 'Billing address', 'Card details']), JSON.stringify(page.sections));
    const limits = await ev(`Object.fromEntries(['name', 'company', 'line1', 'line2', 'town', 'county', 'postcode', 'notes', 'phone'].map(n => [n, document.querySelector('[name="card_payment_form[' + n + ']"]').maxLength]))`);
    check("DPD's character limits (35, postcode 8, delivery instructions 100, phone 15)", JSON.stringify(limits) === JSON.stringify({ name: 35, company: 35, line1: 35, line2: 35, town: 35, county: 35, postcode: 8, notes: 100, phone: 15 }), JSON.stringify(limits));
    let a = await aside();
    check('before a postcode: "Calculated from postcode", total £799, "Pay £799"', a.delivery === 'Calculated from postcode' && a.total === '£799' && a.button === 'Pay £799' && a.secure && !a.note, JSON.stringify(a));

    // The postcode decides delivery.
    await type('postcode', 'BH21 1AA'); a = await aside();
    check('a mainland postcode: DPD price, total and Pay update', a.delivery === '£12.95' && a.total === '£811.95' && a.button === 'Pay £811.95', JSON.stringify(a));
    await type('postcode', 'BT1 1AA'); a = await aside();
    check('Northern Ireland: "Quoted individually", "Reserve Your Design" with the note, no secure-payment line', a.delivery === 'Quoted individually' && a.button === 'Reserve Your Design' && a.note === 'This address is outside the UK mainland, delivery will be quoted for individually. Reserve now to secure your casting slot.' && !a.secure, JSON.stringify(a));
    await type('postcode', 'IV1 1AA'); a = await aside();
    check('the Highlands (IV): Reserve Your Design', a.button === 'Reserve Your Design');
    await type('postcode', 'BH2'); a = await aside();
    check('half a postcode: back to "Calculated from postcode"', a.delivery === 'Calculated from postcode' && a.button === 'Pay £799', JSON.stringify(a));

    // Messages.
    await type('postcode', '');
    await submit();
    let errors = await ev(`({ path: location.pathname, outlined: [...document.querySelectorAll('main form input[name^=card_payment_form]')].filter(i => getComputedStyle(i).borderTopColor === 'rgb(236, 227, 211)' && i.type !== 'checkbox' && i.type !== 'hidden').map(i => i.name.replace(/^.*\\[(.*)\\]$/, '$1')) })`);
    a = await aside();
    check('sent empty: "Please complete the required fields to continue." and the required fields outlined', errors.path === '/checkout' && a.error === 'Please complete the required fields to continue.' && JSON.stringify(errors.outlined) === JSON.stringify(['email', 'phone', 'name', 'line1', 'town', 'postcode', 'cardName']), JSON.stringify({ a, errors }));
    await fill({ ...good, postcode: 'BH21', email: 'not-an-email', phone: '12' }); await submit();
    const under = await ev(`[...document.querySelectorAll('main form label [role=alert]')].map(e => e.textContent.trim())`);
    check('a half postcode, a bad email and a bad phone each say what is wrong', JSON.stringify(under) === JSON.stringify(['Please enter a valid email address.', 'Please enter a valid phone number.', 'Please enter a full UK postcode, e.g. SW1A 1AA.']), JSON.stringify(under));
    check('what was typed is kept', (await ev(`${field('line1')}.value`)) === '1 High Street');

    // Billing address.
    const billingShown = () => ev(`${field('billingName')}.getBoundingClientRect().height > 0`);
    check('billing fields hidden while "Same as delivery address" is ticked', !(await billingShown()) && (await ev(`${field('billingSame')}.checked`)));
    await ev(`${field('billingSame')}.closest('label').click()`); await sleep(300);
    check('unticking it shows the billing fields', await billingShown());
    await fill(good); await submit();
    errors = await ev(`[...document.querySelectorAll('main form input[name^=card_payment_form]')].filter(i => getComputedStyle(i).borderTopColor === 'rgb(236, 227, 211)' && i.type !== 'checkbox' && i.type !== 'hidden').map(i => i.name.replace(/^.*\\[(.*)\\]$/, '$1'))`);
    check('then the billing name, line 1, town and postcode are required', JSON.stringify(errors) === JSON.stringify(['billingName', 'billingLine1', 'billingTown', 'billingPostcode', 'cardName']) && (await billingShown()), JSON.stringify(errors));

    // Outside the mainland: to Reserve Your Design with the details.
    await go(BASE_URL + '/checkout');
    await fill({ email: 'ada.test@example.test', phone: '07700 900123', name: 'Ada Test', line1: '2 Harbour Road', town: 'Belfast', postcode: 'BT1 1AA', notes: 'Ring the bell' }); await submit();
    const reserve = await ev(`({ path: location.pathname, name: document.querySelector('[name="reserve_form[fullName]"]')?.value, email: document.querySelector('[name="reserve_form[email]"]')?.value, phone: document.querySelector('[name="reserve_form[phone]"]')?.value, address: ['addressLine1', 'addressLine2', 'town', 'county', 'postcode', 'country'].map(n => document.querySelector('[name="reserve_form[' + n + ']"]')?.value), notes: document.querySelector('[name="reserve_form[accessNotes]"]')?.value, lamps: document.querySelector('main aside')?.innerText || '' })`);
    check('a Northern Ireland postcode goes to Reserve Your Design with the details filled in, the address in its fields', reserve.path === '/reserve' && reserve.name === 'Ada Test' && reserve.email === 'ada.test@example.test' && reserve.phone === '07700 900123' && JSON.stringify(reserve.address) === JSON.stringify(['2 Harbour Road', '', 'Belfast', '', 'BT1 1AA', 'GB']) && reserve.notes === 'Ring the bell', JSON.stringify(reserve));
    check('and the basket lamp is in the request', /Gallery/.test(reserve.lamps) && /Slate Grey/.test(reserve.lamps));
    await go(BASE_URL + '/reserve');
    check('the details are filled in only once', (await ev(`document.querySelector('[name="reserve_form[fullName]"]').value`)) === '');

    // A gift.
    await go(BASE_URL + '/basket');
    const arrive = await ev(`document.querySelector('main aside input[type=date]').min`);
    await ev(`[...document.querySelectorAll('main aside label')].find(l => /This is a gift/.test(l.textContent)).click()`);
    await ev(`document.querySelector('main aside input[type=date]').value = '${arrive}'`);
    // Gift options (9 October 2026): where to send it is required.
    await ev(`[...document.querySelectorAll('main aside label')].find(l => /Send to me/.test(l.textContent))?.click()`);
    await ev(`[...document.querySelectorAll('main aside button')].find(e => e.textContent.trim() === 'Proceed to Payment').click()`); await sleep(2500);
    const labels = await ev(`[...document.querySelectorAll('main form label > span:first-child')].filter(s => s.getBoundingClientRect().height > 0).map(s => s.textContent.trim())`);
    a = await aside();
    const nice = new Date(arrive + 'T12:00:00Z').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
    check('a gift asks for the same details as any order (no recipient fields)', labels.includes('Full name *') && labels.includes('Your Mobile (for order and delivery updates) *') && !labels.some((l) => /recipient/i.test(l)), JSON.stringify(labels));
    check(`the summary shows "Gift · Arrive by ${nice}" and "Send to me"`, a.gift === `Arrive by ${nice}Send to me`, a.gift);
    sql(`update cart set gift_arrive_by = current_date where id = (select cart_id from cart_item order by created_at desc limit 1)`);
    await go(BASE_URL + '/checkout');
    check('a gift date that has become too soon sends the customer back to the basket to change it', (await ev('location.pathname')) === '/basket' && /Please choose a date on or after the earliest delivery date\./.test(await ev('document.body.innerText')));

    // Feel and fit.
    sql(`update cart set gift = false, gift_arrive_by = null where id = (select cart_id from cart_item order by created_at desc limit 1)`);
    await go(BASE_URL + '/checkout');
    if (W >= 900) {
        for (const [label, sel, prop] of [['billing tick', 'main form label:has(input[type=checkbox])', 'opacity'], ['Pay button', 'main aside button[type=submit]', 'opacity']]) {
            const r = await ev(`(() => { const e = document.querySelector(${JSON.stringify(sel)}); e.scrollIntoView({ block: 'center' }); window.__h = e; const q = e.getBoundingClientRect(); return [q.left + q.width / 2, q.top + q.height / 2]; })()`);
            await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 2, y: 2 }); await sleep(400);
            const before = await ev(`getComputedStyle(window.__h).${prop}`);
            await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: r[0], y: r[1] }); await sleep(450);
            const h = { before, after: await ev(`getComputedStyle(window.__h).${prop}`), cursor: await ev('getComputedStyle(window.__h).cursor') };
            check(`${label}: hand pointer and a hover change`, h.cursor === 'pointer' && h.before !== h.after, JSON.stringify(h));
        }
    }
    check('no horizontal scrolling', (await ev('document.documentElement.scrollWidth')) <= W);
    summary();
} finally { b.close(); }
