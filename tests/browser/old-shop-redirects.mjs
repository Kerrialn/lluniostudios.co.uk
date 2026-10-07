// The old shop's addresses (/products, /products/<slug>, /collections/<slug>)
// permanently redirect to Lighting Designs, and nothing on the site links to
// them any more. Also the basket's purchase route and 2-lamp limit (Step 6),
// with the basket filled through the configurator.
import { execSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { open, sleep, checker, sql, out, BASE_URL } from './lib.mjs';

const { check, summary } = checker();
const b = await open(1280, 1000, 'old-shop', { mobile: false });
const { send, ev } = b;
try {
    const go = async (url, wait = 2200) => { await send('Page.navigate', { url }); await sleep(wait); };
    const shot = async (name, sel) => { await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`); const b = await ev(`(() => { const r = document.querySelector('${sel}').getBoundingClientRect(); return [r.left, r.top + scrollY, r.width, r.height]; })()`); writeFileSync(out(`${name}.png`), Buffer.from((await send('Page.captureScreenshot', { clip: { x: b[0], y: b[1], width: b[2], height: b[3], scale: 1 }, captureBeyondViewport: true })).result.data, 'base64')); };
    // Status and destination of the first response, without following it.
    const head = (path, method = 'GET') => execSync(`curl -sk -o /dev/null -X ${method} -w "%{http_code} %{redirect_url}" "${BASE_URL}${path}"`, { encoding: 'utf8' }).trim();
    await send('Network.enable'); await send('Network.clearBrowserCookies');

    // Every old address is a permanent redirect to /lighting-designs.
    const OLD = ['/products', '/products/', '/products/lumen-forma-studio', '/products/lumen-forma-atelier?edit=0', '/products/no-such-lamp', '/collections', '/collections/lumen-forma', '/collections/no-such/thing'];
    for (const path of OLD) {
        const r = head(path);
        check(`${path}: permanent redirect (301) to Lighting Designs`, r === `301 ${BASE_URL}/lighting-designs`, r);
    }
    check('a form sent to an old product page is redirected too', head('/products/lumen-forma-studio', 'POST') === `301 ${BASE_URL}/lighting-designs`);
    check('addresses that only start the same way are not caught (/productsx is not found)', head('/productsx').startsWith('404'));

    // In the browser: lands on the configurator, which works, with the nav link marked.
    for (const path of ['/products/lumen-forma-gallery', '/collections/lumen-forma']) {
        await go(BASE_URL + path, 2500);
        const s = await ev(`({ path: location.pathname + location.search, configurator: !!document.querySelector('[data-controller~="configurator"]'), nav: [...document.querySelectorAll('#navbar a')].find(a => /Lighting Designs/i.test(a.textContent))?.getAttribute('aria-current') ?? null })`);
        check(`${path} opens the configurator, with Lighting Designs marked in the nav`, s.path === '/lighting-designs' && s.configurator && s.nav === 'page', JSON.stringify(s));
    }

    // Nothing links to the old addresses.
    for (const path of ['/', '/lighting-designs', '/basket', '/workshop', '/contact', '/terms-and-conditions', '/reserve']) {
        await go(BASE_URL + path, 1800);
        const links = await ev(`[...document.querySelectorAll('a[href], form[action]')].map(e => e.getAttribute('href') ?? e.getAttribute('action')).filter(h => /^(https?:\\/\\/[^/]+)?\\/(products|collections)(\\/|$|\\?)/.test(h))`);
        check(`${path}: no links to /products or /collections`, links.length === 0, links.join(', '));
    }

    // Fill the basket to 2 lamps through the configurator (as configurator-add-to-basket.mjs does).
    const lamp = { 'The Pigment': 'White', 'Cable Colour': 'Slate Grey', 'Footing Material': 'Brushed Copper', 'Bulb Style': 'Crown Gold LED' };
    const add = async (config) => {
        await go(BASE_URL + '/lighting-designs', 1800);
        await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
        await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify(config))}; f.requestSubmit(); })()`);
        await sleep(2500);
    };
    await add({ size: 'lumen-forma-studio', quantity: 1, mode: 'all', lamps: [lamp] });
    await add({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [lamp] });
    const k = await ev(`({ path: location.pathname, text: document.querySelector('main').innerText, pay: !!document.querySelector('a[href="/checkout"]'), edits: [...document.querySelectorAll('main a')].filter(a => /Edit configuration/.test(a.textContent)).map(a => a.getAttribute('href')) })`);
    check('basket holds 2 lamps with payment buttons', k.path === '/basket' && /2 lamps/i.test(k.text) && k.pay, k.path);
    check('every "Edit configuration" opens the configurator', k.edits.length === 2 && k.edits.every((h) => /^\/lighting-designs\?edit=/.test(h)), JSON.stringify(k.edits));

    // A third lamp from the configurator goes to the request route, not the basket.
    await add({ size: 'lumen-forma-studio', quantity: 1, mode: 'all', lamps: [lamp] });
    check('a third lamp goes to the request route', (await ev('location.pathname')) === '/reserve');
    await go(BASE_URL + '/basket');
    check('nothing was added (still 2 lamps)', /2 lamps · 2 items/i.test(await ev(`document.querySelector('main').innerText`)));

    // Over the limit in the basket (database-only: one line to quantity 2 => 3 lamps).
    sql("update cart_item set quantity = 2 where id = (select id from cart_item where cart_id = (select cart_id from cart_item order by created_at desc limit 1) order by created_at limit 1)");
    await go(BASE_URL + '/basket');
    const r = await ev(`({ cta: document.querySelector('main a[href="/reserve?basket=1"]')?.textContent.trim(), note: [...document.querySelectorAll('main span')].find(s => /^Quantities of three/.test(s.textContent.trim()))?.textContent.trim(), pay: !!document.querySelector('a[href="/checkout"]'), box: !!document.querySelector('[role=alert]'), label: /Purchase route/i.test(document.querySelector('aside').innerText) })`);
    check('over-limit basket: Reserve Your Design + purchase route, no payment buttons, no message box', r.cta === 'Reserve Your Design' && !!r.note && !r.pay && !r.box && r.label, JSON.stringify(r));
    await shot('basket-reserve', 'aside');
    await go(BASE_URL + '/checkout');
    check('checkout still bounces back', (await ev('location.pathname')) === '/basket');
    summary();
} finally { b.close(); }
