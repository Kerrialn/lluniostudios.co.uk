// The Step 6 product page and the basket: the purchase route ("Cast Your
// Lamp" / "Reserve Your Design" and its notes), the 2-lamp basket limit, and
// "Edit configuration" opening the configurator.
// The Lumen Forma sizes are unpublished; the old product pages only show
// published products, so this publishes them for the run and restores them.
import { writeFileSync } from 'node:fs';
import { open, sleep, checker, sql, out, BASE_URL } from './lib.mjs';

const { check, summary } = checker();
sql("update product set is_published = true where slug like 'lumen-forma-%'");
const b = await open(1280, 1000, 'product-page', { mobile: false });
const { send, ev } = b;
try {
    const go = async (url, wait = 2200) => { await send('Page.navigate', { url }); await sleep(wait); };
    const shot = async (name, sel) => { await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`); const b = await ev(`(() => { const r = document.querySelector('${sel}').getBoundingClientRect(); return [r.left, r.top + scrollY, r.width, r.height]; })()`); writeFileSync(out(`${name}.png`), Buffer.from((await send('Page.captureScreenshot', { clip: { x: b[0], y: b[1], width: b[2], height: b[3], scale: 1 }, captureBeyondViewport: true })).result.data, 'base64')); };
    const route = () => ev(`(() => { const c = document.querySelector('[data-purchase-route-target=cta]'); return { cta: c.textContent.trim(), note: document.querySelector('[data-purchase-route-target=note]').textContent.trim(), sub: document.querySelector('[data-purchase-route-target=sub]').textContent.trim(), line: getComputedStyle(document.querySelector('[data-purchase-route-target=route]')).borderLeftColor, alertBox: !!document.querySelector('[role=alert]') }; })()`);
    const setQty = (n) => ev(`(() => { const i = document.querySelector('input[name$="[quantity]"]'); i.value = '${n}'; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);
    const setPigment = (label) => ev(`(() => { const s = document.querySelector('select'); s.value = [...s.options].find(o => o.text === '${label}').value; s.dispatchEvent(new Event('change', { bubbles: true })); })()`);
    const pickAll = () => ev(`document.querySelectorAll('select').forEach(s => { s.value = [...s.options].find(o => o.value && !/^Custom/.test(o.text)).value; s.dispatchEvent(new Event('change', { bubbles: true })); })`);
    await send('Network.enable'); await send('Network.clearBrowserCookies');
    const SIZE = 'Hand cast to order. Reserve now to secure your casting slot. Delivery will be quoted for individually.';
    const CUSTOM = 'Hand cast to order. Reserve now to secure your casting slot. Custom options are developed with you and quoted individually.';
    const BOTH = 'Hand cast to order. Reserve now to secure your casting slot. Custom options are developed with you, and delivery quoted individually.';
    const LIMIT = 'Quantities of three or more proceed through the Reserve request process, not direct checkout.';

    // Empty basket.
    await go(BASE_URL + '/products/lumen-forma-studio');
    let r = await route();
    check('empty basket: Cast Your Lamp, direct note', r.cta === 'Cast Your Lamp' && r.note === 'Direct order and checkout.' && /^Limited weekly batch capacity/.test(r.sub), JSON.stringify(r));
    check('no message box or extra buttons', !r.alertBox && !(await ev(`/Send my whole order|Keep my basket/.test(document.body.innerText)`)));
    await shot('route-cast', 'form');
    await setQty(3); r = await route();
    check('quantity 3 switches to Reserve with the quantity note', r.cta === 'Reserve Your Design' && r.note === 'Quantities of three or more proceed through the Reserve request process, not direct checkout.' && /^Small-batch studio capacity/.test(r.sub), r.note);
    await setQty(1); await pickAll(); await setPigment('Custom'); r = await route();
    check('Custom pigment switches to Reserve with the custom note', r.cta === 'Reserve Your Design' && r.note === CUSTOM, r.note);
    await setQty(3); r = await route();
    check('Custom and 3 lamps: the custom note, then the 3-or-more note', r.note === CUSTOM + ' ' + LIMIT, r.note);
    await shot('route-reserve', 'form');
    await setQty(1); await setPigment('White'); r = await route();
    check('back to Cast Your Lamp', r.cta === 'Cast Your Lamp' && r.line !== 'rgb(236, 227, 211)', r.line);

    // A Request size, alone, with a Custom choice, and with 3 lamps.
    await go(BASE_URL + '/products/lumen-forma-atelier'); r = await route();
    check('Atelier: Reserve with the Request size note', r.cta === 'Reserve Your Design' && r.note === SIZE, r.note);
    await pickAll(); await setPigment('Custom'); r = await route();
    check('Atelier with a Custom choice: the single combined note', r.note === BOTH, r.note);
    await setQty(3); r = await route();
    check('Atelier, Custom and 3 lamps: the combined note, then the 3-or-more note', r.note === BOTH + ' ' + LIMIT, r.note);
    await setPigment('White'); r = await route();
    check('Atelier and 3 lamps: the size note, then the 3-or-more note', r.note === SIZE + ' ' + LIMIT, r.note);

    // Fill the basket to 2 lamps.
    await go(BASE_URL + '/products/lumen-forma-studio'); await pickAll();
    await ev(`document.querySelector('form').requestSubmit()`); await sleep(2500);
    await go(BASE_URL + '/products/lumen-forma-studio'); await pickAll();
    await ev(`document.querySelector('form').requestSubmit()`); await sleep(2500);
    check('basket holds 2 lamps with payment buttons', /2 lamps/i.test(await ev(`document.querySelector('main').innerText`)) && (await ev(`!!document.querySelector('a[href="/checkout"]')`)));

    // Product page with a full basket starts on Reserve; pressing it goes to the request route.
    await go(BASE_URL + '/products/lumen-forma-gallery'); r = await route();
    check('full basket: page starts on Reserve Your Design', r.cta === 'Reserve Your Design' && /^Quantities of three/.test(r.note), r.cta);
    await pickAll(); await ev(`document.querySelector('form').requestSubmit()`); await sleep(2500);
    check('Reserve goes to the request route', (await ev('location.pathname')) === '/reserve');
    await go(BASE_URL + '/basket');
    check('nothing was added (still 2 lamps)', /2 lamps · 2 items/i.test(await ev(`document.querySelector('main').innerText`)));

    // Editing a line in the full basket is not "a third lamp".
    // (Lumen Forma lines are edited in the configurator since Step 7.6.)
    const edit = await ev(`document.querySelector('a[href*="edit="]').getAttribute('href')`);
    check('"Edit configuration" opens the configurator', /^\/lighting-designs\?edit=/.test(edit), edit);
    await go(BASE_URL + '' + edit, 3000);
    const editCta = await ev(`document.querySelector('[data-configurator-target=cta]').textContent`);
    check('editing a line keeps Cast Your Lamp', editCta === 'Cast Your Lamp', editCta);

    // Over the limit in the basket (database-only: one line to quantity 2 => 3 lamps).
    sql("update cart_item set quantity = 2 where id = (select id from cart_item where cart_id = (select cart_id from cart_item order by created_at desc limit 1) order by created_at limit 1)");
    await go(BASE_URL + '/basket');
    const b = await ev(`({ cta: document.querySelector('main a[href="/reserve?basket=1"]')?.textContent.trim(), note: [...document.querySelectorAll('main span')].find(s => /^Quantities of three/.test(s.textContent.trim()))?.textContent.trim(), pay: !!document.querySelector('a[href="/checkout"]'), box: !!document.querySelector('[role=alert]'), label: /Purchase route/i.test(document.querySelector('aside').innerText) })`);
    check('over-limit basket: Reserve Your Design + purchase route, no payment buttons, no message box', b.cta === 'Reserve Your Design' && !!b.note && !b.pay && !b.box && b.label, JSON.stringify(b));
    await shot('basket-reserve', 'aside');
    await go(BASE_URL + '/checkout');
    check('checkout still bounces back', (await ev('location.pathname')) === '/basket');
    summary();
} finally { b.close(); sql("update product set is_published = false where slug like 'lumen-forma-%'"); }
