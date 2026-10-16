// Basket (Step 10.1): "This is a gift" with "When does it need to arrive
// by?" (any date from tomorrow since 16 October 2026; one sooner than the
// usual earliest, today + 3 weeks then 2 working days, turns the button into
// "Reserve Your Design" with its note, and goes to Reserve Your Design with
// the gift carried), its messages, the choice saved on the basket and kept,
// a single "Proceed to Payment" (no Express Payment button), and no gift box
// when the basket goes by request. Takes a size: 1440 900 (default) or 390 844.
import { execSync } from 'node:child_process';
import { open, sleep, checker, sql, BASE_URL, PROJECT_DIR } from './lib.mjs';

const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const { check, summary } = checker();
const b = await open(W, H, 'basket-gift' + W, { mobile: W < 900 });
const { send, ev } = b;
const php = (method) => execSync(`docker compose exec -T php php -r 'require "vendor/autoload.php"; echo (new App\\Catalogue\\GiftDate())->${method}()->format("Y-m-d");' 2>/dev/null`, { cwd: PROJECT_DIR, encoding: 'utf8' }).trim();
const earliest = php('earliest');
const tomorrow = php('tomorrow');
const today = new Date(new Date(tomorrow + 'T12:00:00Z').getTime() - 86400000).toISOString().slice(0, 10);
const NOTE = "This date is sooner than our usual 1–3 week lead time. Reserve your design and we'll let you know if we can make it.";
const dayBefore = new Date(new Date(earliest + 'T12:00:00Z').getTime() - 86400000).toISOString().slice(0, 10);
// (the day before the usual earliest)
const later = new Date(new Date(earliest + 'T12:00:00Z').getTime() + 7 * 86400000).toISOString().slice(0, 10);
const cartRow = () => sql(`select case when gift then 'gift-yes' else 'gift-no' end || ' ' || coalesce(to_char(gift_arrive_by, 'YYYY-MM-DD'), 'no-date') as saved from cart where id = (select cart_id from cart_item order by created_at desc limit 1)`).match(/gift-(yes|no) (\S+)/)?.[0] ?? '';
try {
    const go = async (url, wait = 2000) => { await send('Page.navigate', { url }); await sleep(wait); };
    const lamp = { 'The Pigment': 'White', 'Cable Colour': 'Slate Grey', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED' };
    const add = async (quantity = 1) => {
        await go(BASE_URL + '/lighting-designs', 1800);
        await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
        await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-gallery', quantity, mode: 'all', lamps: Array(1).fill(lamp) }))}; f.requestSubmit(); })()`);
        await sleep(2500);
    };
    const state = () => ev(`(() => { const a = document.querySelector('main aside'); const box = [...(a?.querySelectorAll('label') || [])].find(l => /This is a gift/.test(l.textContent)); const date = a?.querySelector('input[type=date]');
        return { path: location.pathname, giftBox: !!box, ticked: !!box?.querySelector('input')?.checked, dateShown: !!date && date.getBoundingClientRect().height > 0, min: date?.min, value: date?.value,
          buttons: [...(a?.querySelectorAll('a, button') || [])].map(e => e.textContent.trim()).filter(t => /Payment|Reserve/.test(t)), errors: [...(a?.querySelectorAll('[role=alert]') || [])].map(e => e.textContent.trim()),
          note: [...(a?.querySelectorAll('div') || [])].find(d => /^Each lamp is made by hand/.test(d.textContent.trim()))?.textContent.trim(),
          soonNote: (() => { const n = a?.querySelector('[data-gift-too-soon-note]'); return n && n.getClientRects().length ? n.textContent.trim() : null; })(),
          submit: a?.querySelector('button[type=submit]')?.textContent.trim() }; })()`);
    const tick = () => ev(`[...document.querySelectorAll('main aside label')].find(l => /This is a gift/.test(l.textContent)).click()`);
    const setDate = async (v) => { await ev(`(() => { const d = document.querySelector('main aside input[type=date]'); d.value = '${v}'; d.dispatchEvent(new Event('input', { bubbles: true })); d.dispatchEvent(new Event('change', { bubbles: true })); })()`); await sleep(200); };
    const proceed = async () => { await ev(`document.querySelector('main aside button[type=submit]').click()`); await sleep(2200); };
    await send('Network.enable'); await send('Network.clearBrowserCookies');

    await add();
    let s = await state();
    check('basket: "This is a gift" unticked, no date showing', s.giftBox && !s.ticked && !s.dateShown, JSON.stringify(s));
    check('a single "Proceed to Payment" button (no Express Payment)', JSON.stringify(s.buttons) === JSON.stringify(['Proceed to Payment']), JSON.stringify(s.buttons));
    await tick(); await sleep(300); s = await state();
    check('ticking it shows the date, its note, and any date from tomorrow allowed', s.ticked && s.dateShown && s.note === 'Each lamp is made by hand. Please allow 1–3 weeks.' && s.min === tomorrow, JSON.stringify(s));
    const earliestDay = new Date(earliest + 'T12:00:00Z');
    check(`the usual earliest date (${earliest}) is at least 23 days away and a weekday`, (earliestDay - Date.now()) / 86400000 >= 22 && ![0, 6].includes(earliestDay.getUTCDay()), earliest);

    // Messages.
    await proceed(); s = await state();
    check('gift with no date or where to send it: "Please choose a date to continue.", "Please choose where to send it.", stays on the basket', s.path === '/basket' && s.ticked && s.dateShown && JSON.stringify(s.errors) === JSON.stringify(['Please choose a date to continue.', 'Please choose where to send it.']), JSON.stringify(s));
    // Gift options (9 October 2026): "Send to me" for the rest of this check (gift-options.mjs covers sending directly).
    await ev(`[...document.querySelectorAll('main aside label')].find(l => /Send to me/.test(l.textContent)).click()`);
    check('the date box is outlined in cream when there is a message', (await ev(`getComputedStyle(document.querySelector('main aside input[type=date]')).borderTopColor`)) === 'rgb(236, 227, 211)');
    await setDate(today); await proceed(); s = await state();
    check('today (in the past for delivery): "Please choose a date from tomorrow onwards."', s.path === '/basket' && JSON.stringify(s.errors) === JSON.stringify(['Please choose a date from tomorrow onwards.']) && s.value === today, JSON.stringify(s));
    check('nothing saved yet', cartRow() === 'gift-no no-date', cartRow());

    // Sooner than usual: Reserve Your Design, with the gift carried.
    await setDate(dayBefore); s = await state();
    check('a date sooner than the usual earliest turns the button into "Reserve Your Design", with the note under it', s.submit === 'Reserve Your Design' && s.soonNote === NOTE, JSON.stringify(s));
    await setDate(later); s = await state();
    check('a later date turns it back to "Proceed to Payment", no note', s.submit === 'Proceed to Payment' && s.soonNote === null, JSON.stringify(s));
    await setDate(tomorrow); await proceed();
    const carried = await ev(`(() => { const f = document.querySelector('form[name=reserve_form]'); return f && { path: location.pathname, gift: f.querySelector('[name="reserve_form[gift]"]').checked, date: f.querySelector('[name="reserve_form[giftNeededBy]"]').value, min: f.querySelector('[name="reserve_form[giftNeededBy]"]').min, sendTo: f.querySelector('[name="reserve_form[giftSendTo]"]:checked')?.value }; })()`);
    check('sending it goes to Reserve Your Design, with the gift carried (ticked, the date, "me"), any date from tomorrow', carried?.path === '/reserve' && carried.gift && carried.date === tomorrow && carried.min === tomorrow && carried.sendTo === 'me', JSON.stringify(carried));
    check('the gift is saved on the basket', cartRow() === `gift-yes ${tomorrow}`, cartRow());
    await go(BASE_URL + '/checkout'); s = await state();
    check('Card Payment sends it back to the basket, which offers Reserve Your Design with the note', s.path === '/basket' && s.submit === 'Reserve Your Design' && s.soonNote === NOTE, JSON.stringify(s));

    // Saved and kept.
    await setDate(later); await proceed();
    check('a good date goes on to checkout', (await ev('location.pathname')) === '/checkout');
    check('the gift and its date are saved on the basket', cartRow() === `gift-yes ${later}`, cartRow());
    check('and where to send it ("me")', /\bme\b/.test(sql("select gift_send_to from cart where id = (select cart_id from cart_item order by created_at desc limit 1)")));
    await go(BASE_URL + '/basket'); s = await state();
    check('back at the basket, still ticked with the date', s.ticked && s.dateShown && s.value === later, JSON.stringify(s));
    await tick(); await sleep(200); await proceed();
    check('unticking it and proceeding clears the gift', (await ev('location.pathname')) === '/checkout' && cartRow() === 'gift-no no-date', cartRow());

    // Feel and fit.
    await go(BASE_URL + '/basket');
    if (W >= 900) {
        for (const [label, sel, prop] of [['gift tick box', 'main aside label', 'opacity'], ['Proceed to Payment', 'main aside button[type=submit]', 'opacity']]) {
            const r = await ev(`(() => { const e = document.querySelector(${JSON.stringify(sel)}); window.__h = e; const q = e.getBoundingClientRect(); return [q.left + q.width / 2, q.top + q.height / 2]; })()`);
            await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 2, y: 2 }); await sleep(400);
            const before = await ev(`getComputedStyle(window.__h).${prop}`);
            await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: r[0], y: r[1] }); await sleep(450);
            const h = { before, after: await ev(`getComputedStyle(window.__h).${prop}`), cursor: await ev('getComputedStyle(window.__h).cursor') };
            check(`${label}: hand pointer and a hover change`, h.cursor === 'pointer' && h.before !== h.after, JSON.stringify(h));
        }
    }
    check('no horizontal scrolling', (await ev('document.documentElement.scrollWidth')) <= W);

    // A basket that goes by request has no gift box, and can't be sent on.
    sql("update cart_item set quantity = 3 where id = (select id from cart_item order by created_at desc limit 1)");
    await go(BASE_URL + '/basket'); s = await state();
    check('3 lamps: Reserve Your Design, no gift box, no Proceed to Payment', !s.giftBox && JSON.stringify(s.buttons) === JSON.stringify(['Reserve Your Design']), JSON.stringify(s));
    summary();
} finally { b.close(); }
