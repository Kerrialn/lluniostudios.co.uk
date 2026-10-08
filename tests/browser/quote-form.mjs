// The quote form in admin (Step 10b.1): a request sent through Reserve Your
// Design (an Atelier ×2; a Monument on another) gets a "Quote" action; the draft
// starts with each design at its size's price and the carriers' delivery
// estimate (marked as an estimate, with its notes); prices, extra named
// lines, delivery, full payment or deposit, casting date and note can be
// set; the totals follow as prices are typed; saving keeps it as a draft and
// sends nothing; wrong values are refused with a message.
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL } from './lib.mjs';

const { check, summary } = checker();
const b = await open(1440, 1000, 'quote-form', { mobile: false });
const { send, ev } = b;
const EMAIL = 'ada.quote@example.test';
const ADMIN = 'lluniostudios@gmail.com';
try {
    const go = async (url, wait = 2500) => { await send('Page.navigate', { url }); await sleep(wait); };
    await send('Network.enable'); await send('Network.clearBrowserCookies');

    // A request: an Atelier ×2 and an Estate (both go by request), to a Dorset address.
    const lamp = { 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED', 'Bulb Count': 'Dual Array' };
    const force = async (size, quantity) => {
        await go(BASE_URL + '/lighting-designs', 2000);
        await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
        await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size, quantity, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`);
        await sleep(2500);
    };
    await force('lumen-forma-atelier', 2);
    const form = { fullName: 'Ada Quote', email: EMAIL, phone: '07700 900123', addressLine1: '1 High Street', town: 'Wimborne', county: 'Dorset', postcode: 'BH21 1AA', accessNotes: 'Narrow gate.' };
    await ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(form)})) document.querySelector('[name="reserve_form[' + k + ']"]').value = v; document.querySelector('form[name=reserve_form]').requestSubmit(); })()`); await sleep(3000);
    const number = sql(`select request_number from design_request where email = '${EMAIL}' order by created_at desc limit 1`).match(/LR-\d{6}-\d{4}/)?.[0];
    check('a request is sent through Reserve Your Design', !!number, number);
    // A Monument (200cm, longer than DPD takes) on its own request, to see a pallet price.
    await force('lumen-forma-monument', 1);
    await ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify({ ...form, fullName: 'Ada Pallet' })})) document.querySelector('[name="reserve_form[' + k + ']"]').value = v; document.querySelector('form[name=reserve_form]').requestSubmit(); })()`); await sleep(3000);
    const palletNumber = sql(`select request_number from design_request where email = '${EMAIL}' and full_name = 'Ada Pallet' order by created_at desc limit 1`).match(/LR-\d{6}-\d{4}/)?.[0];

    // Admin.
    const since = new Date();
    await go(BASE_URL + '/login');
    await ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
    let code;
    for (let i = 0; i < 20 && !code; i++) {
        const m = ((await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent('to:' + ADMIN + ' subject:"Sign in to Llunio Studios"')}`)).json()).messages || [])[0];
        if (m) code = ((await (await fetch(`${MAILPIT_URL}/api/v1/message/${m.ID}`)).json()).Text || '').match(/\b\d{6}\b/)?.[0];
        if (!code) await sleep(500);
    }
    await ev(`(() => { const h = document.querySelector('[data-pin-code-target=hidden]'); h.value = '${code}'; h.form.requestSubmit(); })()`); await sleep(3000);

    const id = sql(`select id from design_request where request_number = '${number}'`).match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)[0];
    await go(`${BASE_URL}/admin/design-request/${id}`, 3000);
    const fields = await ev(`document.querySelector('main, .content').innerText.replace(/\\s+/g, ' ')`);
    check("the request's admin page shows the address in its fields", /Address line 1 1 High Street/i.test(fields) && /Town\/City Wimborne/i.test(fields) && /County Dorset/i.test(fields) && /Postcode BH21 1AA/i.test(fields) && /Country United Kingdom/i.test(fields), fields.slice(0, 400));
    const action = await ev(`[...document.querySelectorAll('a')].find(a => /^\\s*Quote\\s*$/.test(a.textContent))?.getAttribute('href')`);
    check('the request\'s page has a "Quote" action', /\/admin\/design-request\/.+\/quote/.test(action ?? ''), action);
    await go(new URL(action, BASE_URL).href, 3500);
    await b.shot(out("quote-form.png"));

    const draft = await ev(`({ title: document.querySelector('h1')?.innerText.trim(), intro: document.querySelector('main p.text-muted, .content p.text-muted')?.innerText, lamps: [...document.querySelectorAll('[data-quote-line]')].map(r => r.querySelector('strong').textContent.trim() + ' ×' + r.dataset.quantity + ' @ ' + r.querySelector('[data-quote-price]').value), estimate: document.querySelector('.alert')?.innerText.replace(/\\s+/g, ' '), delivery: document.querySelector('[data-quote-delivery]').value, label: document.querySelector('[name$="[deliveryLabel]"]').value, payment: document.querySelector('input[name$="[payment]"]:checked')?.value, deposit: document.querySelector('[data-quote-deposit]').value, total: document.querySelector('[data-quote-total=total]').textContent })`);
    const address = await ev(`[...document.querySelectorAll('[data-quote-address] dt')].map(dt => dt.textContent + '=' + dt.nextElementSibling.textContent)`);
    check("the request's address in its fields", JSON.stringify(address) === JSON.stringify(['Address line 1=1 High Street', 'Address line 2=—', 'Town/City=Wimborne', 'County=Dorset', 'Postcode=BH21 1AA', 'Country=United Kingdom']), JSON.stringify(address));
    check('a new draft for the request, not saved yet', draft.title === `Quote for request ${number}` && /New draft, not saved yet/.test(draft.intro), JSON.stringify(draft).slice(0, 200));
    check("each design at its size's price (Atelier from £1,699)", JSON.stringify(draft.lamps) === JSON.stringify(['Lumen Forma Atelier ×2 @ 1699.00']), JSON.stringify(draft.lamps));
    check('the delivery estimate: a mock DPD price, marked as an estimate, noting Atelier has no packed weight yet', /Estimate: £\d/.test(draft.estimate) && /mock carrier price/.test(draft.estimate) && /No packed weight yet for Atelier/.test(draft.estimate) && draft.label === 'DPD Next Day Delivery' && Number(draft.delivery) > 0, draft.estimate);
    check('full payment to start with, deposit set at 50%', draft.payment === 'full' && draft.deposit === '50');

    // Wrong values.
    await ev(`(() => { document.querySelector('[data-quote-deposit]').value = '100'; document.querySelector('[data-quote-line] [data-quote-price]').value = '-5'; document.getElementById('quote-add-extra').click(); document.querySelector('[data-quote-extra] [data-quote-price]').value = '150'; document.getElementById('quote-form').requestSubmit(); })()`); await sleep(3000);
    const refused = await ev(`[...document.querySelectorAll('.invalid-feedback, .form-error-message')].map(e => e.textContent.trim()).filter(Boolean)`);
    check('wrong values are refused: deposit outside 1–99%, a negative price, an unnamed extra line', refused.includes('The deposit must be between 1% and 99%.') && refused.includes('Please enter an amount of £0 or more.') && refused.includes('Please name the line.'), JSON.stringify(refused));
    check('nothing saved', Number(sql(`select count(*) from quote q join design_request r on r.id = q.design_request_id where r.request_number = '${number}'`).match(/\d+/g).pop()) === 0);

    // A proper quote: £1,850 a lamp, an extra line, own delivery price, 40% deposit, casting date, note.
    await ev(`(() => {
        document.querySelector('[data-quote-line] [data-quote-price]').value = '1850';
        const extra = document.querySelector('[data-quote-extra]');
        extra.querySelector('input').value = 'Custom pigment development';
        extra.querySelector('[data-quote-price]').value = '150';
        document.querySelector('[data-quote-delivery]').value = '60';
        document.querySelector('input[name$="[payment]"][value="deposit"]').click();
        document.querySelector('[data-quote-deposit]').value = '40';
        document.querySelector('[name$="[castingDate]"]').value = '2026-11-16';
        document.querySelector('[name$="[note]"]').value = 'Thank you for your patience.';
        document.getElementById('quote-form').dispatchEvent(new Event('input', { bubbles: true }));
    })()`); await sleep(400);
    const live = await ev(`Object.fromEntries([...document.querySelectorAll('[data-quote-total]')].map(e => [e.dataset.quoteTotal, e.textContent]))`);
    check('the totals follow as prices are typed (2 × £1,850 + £150 + £60 = £3,910; 40% deposit £1,564)', live.lamps === '£3,700.00' && live.extras === '£150.00' && live.delivery === '£60.00' && live.total === '£3,910.00' && live.due === '£1,564.00' && live.balance === '£2,346.00', JSON.stringify(live));
    const mailsBefore = ((await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent('to:' + EMAIL)}`)).json()).messages || []).filter((m) => new Date(m.Created) > since).length;
    await ev(`document.getElementById('quote-form').requestSubmit()`); await sleep(3000);
    const saved = await ev(`({ flash: document.body.innerText.includes('Quote saved as a draft. Nothing has been sent to the customer.'), intro: [...document.querySelectorAll('p.text-muted')].map(p => p.innerText).join(' '), price: document.querySelector('[data-quote-line] [data-quote-price]').value, extra: [...document.querySelectorAll('[data-quote-extra] input')].map(i => i.value), delivery: document.querySelector('[data-quote-delivery]').value, payment: document.querySelector('input[name$="[payment]"]:checked')?.value, deposit: document.querySelector('[data-quote-deposit]').value, date: document.querySelector('[name$="[castingDate]"]').value, note: document.querySelector('[name$="[note]"]').value, due: document.querySelector('[data-quote-total=due]').textContent })`);
    check('saved as a draft ("Nothing has been sent to the customer.")', saved.flash && /Draft, last saved/.test(saved.intro), JSON.stringify(saved));
    check('and kept: price, extra line, delivery, deposit, casting date, note', saved.price === '1850.00' && JSON.stringify(saved.extra) === JSON.stringify(['Custom pigment development', '150.00']) && saved.delivery === '60.00' && saved.payment === 'deposit' && saved.deposit === '40' && saved.date === '2026-11-16' && saved.note === 'Thank you for your patience.' && saved.due === '£1,564.00', JSON.stringify(saved));
    const row = sql(`select q.delivery_price, q.delivery_estimated, q.payment, q.deposit_percent, q.casting_date, (select string_agg(label || ':' || quantity || ':' || unit_price, ',' order by position) from quote_line where quote_id = q.id) as lines from quote q join design_request r on r.id = q.design_request_id where r.request_number = '${number}'`).replace(/\s+/g, ' ');
    check('in the database: £60 own delivery (not an estimate), deposit 40%, the lines in pence', /6000 (f|false|) ?deposit 40 2026-11-16 Lumen Forma Atelier:2:185000,Custom pigment development:1:15000/.test(row), row);
    await sleep(1000);
    const mailsAfter = ((await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent('to:' + EMAIL)}`)).json()).messages || []).filter((m) => new Date(m.Created) > since).length;
    check('nothing sent to the customer, and the request is still New', mailsAfter === mailsBefore && /\bnew\b/.test(sql(`select status from design_request where request_number = '${number}'`)));

    // Removing the extra line.
    await ev(`document.querySelector('[data-quote-remove]').click()`); await sleep(200);
    await ev(`document.getElementById('quote-form').requestSubmit()`); await sleep(3000);
    check('an extra line can be removed', /Lumen Forma Atelier:2:185000\s*$/.test(sql(`select string_agg(label || ':' || quantity || ':' || unit_price, ',' order by position) as lines from quote_line l join quote q on q.id = l.quote_id join design_request r on r.id = q.design_request_id where r.request_number = '${number}'`).replace(/[\s-]+$/g, '').replace(/\s+/g, ' ').replace(/ lines /, ' ').trim()));

    // An Estate goes by pallet.
    const palletId = sql(`select id from design_request where request_number = '${palletNumber}'`).match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)[0];
    await go(`${BASE_URL}/admin/design-request/${palletId}/quote`, 3500);
    const pallet = await ev(`({ label: document.querySelector('[name$="[deliveryLabel]"]').value, estimate: document.querySelector('.alert')?.innerText.replace(/\\s+/g, ' ') })`);
    check('a Monument is too long for DPD: the estimate is a Palletways pallet', /^Palletways · /.test(pallet.label) && /Palletways/.test(pallet.estimate), JSON.stringify(pallet));
    summary();
} finally { b.close(); }
