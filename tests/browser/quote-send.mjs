// Sending a quote (Step 10b.2): "Send quote" in admin saves, then emails the
// customer the quote (each design with its quoted price, extra lines,
// delivery, total, deposit and balance, casting date, note, "Pay now" and the
// date it is valid until, 14 days on); the request becomes Quoted. It isn't
// sent without a casting date. "Pay now" opens the customer's private quote
// page. Changes saved afterwards reach neither the email nor the page until
// it is sent again (same link, a new 14 days). An expired quote says so.
// An unknown link is not found.
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL, STUDIO_EMAIL } from './lib.mjs';

const { check, summary } = checker();
const b = await open(1440, 1000, 'quote-send', { mobile: false });
const { send, ev } = b;
const EMAIL = 'ada.send@example.test';
const ADMIN = 'lluniostudios@gmail.com';
const mailpit = async (query) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json()).messages || [];
const message = async (id) => (await fetch(`${MAILPIT_URL}/api/v1/message/${id}`)).json();
const quoteMails = async (since) => (await mailpit(`to:${EMAIL} subject:"Your Lumen Forma quote"`)).filter((m) => new Date(m.Created) > since);
const ukDate = (d) => d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Europe/London' });
try {
    const go = async (url, wait = 2500) => { await send('Page.navigate', { url }); await sleep(wait); };
    await send('Network.enable'); await send('Network.clearBrowserCookies');

    // A request: an Atelier ×2.
    const lamp = { 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED', 'Bulb Count': 'Dual Array' };
    await go(BASE_URL + '/lighting-designs', 2000);
    await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
    await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-atelier', quantity: 2, mode: 'all', lamps: [lamp] }))}; f.requestSubmit(); })()`);
    await sleep(2500);
    const form = { fullName: 'Ada Send', email: EMAIL, phone: '07700 900123', addressLine1: '1 High Street', town: 'Wimborne', postcode: 'BH21 1AA' };
    await ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(form)})) document.querySelector('[name="reserve_form[' + k + ']"]').value = v; document.querySelector('form[name=reserve_form]').requestSubmit(); })()`); await sleep(3000);
    const number = sql(`select request_number from design_request where email = '${EMAIL}' order by created_at desc limit 1`).match(/LR-\d{6}-\d{4}/)?.[0];
    check('a request is sent through Reserve Your Design', !!number, number);

    // Admin.
    await go(BASE_URL + '/login');
    await ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
    let code;
    for (let i = 0; i < 20 && !code; i++) {
        const m = (await mailpit(`to:${ADMIN} subject:"Sign in to Llunio Studios"`))[0];
        if (m) code = ((await message(m.ID)).Text || '').match(/\b\d{6}\b/)?.[0];
        if (!code) await sleep(500);
    }
    await ev(`(() => { const h = document.querySelector('[data-pin-code-target=hidden]'); h.value = '${code}'; h.form.requestSubmit(); })()`); await sleep(3000);
    const id = sql(`select id from design_request where request_number = '${number}'`).match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)[0];
    const quoteUrl = `${BASE_URL}/admin/design-request/${id}/quote`;
    await go(quoteUrl, 3500);
    // The stand-in for "Send this quote to …?" is set just before each press (a page load removes it; Chrome's own dialog would stop the check).
    const pressSend = async () => {
        await ev(`window.confirm = (text) => { sessionStorage.setItem('confirmed', text); return true; }`);
        await ev(`document.querySelector('[data-quote-send]').click()`); await sleep(3500);
    };
    const flash = () => ev(`[...document.querySelectorAll('.alert')].map(a => a.innerText.trim()).join(' | ')`);
    const status = () => sql(`select status from design_request where request_number = '${number}'`).match(/\b(new|quoted|paid)\b/)?.[0];

    // Without a casting date: saved, not sent.
    let since = new Date();
    await ev(`(() => {
        document.querySelector('[data-quote-line] [data-quote-price]').value = '1850';
        document.getElementById('quote-add-extra').click();
        const extra = document.querySelector('[data-quote-extra]');
        extra.querySelector('input').value = 'Custom pigment development';
        extra.querySelector('[data-quote-price]').value = '150';
        document.querySelector('[data-quote-delivery]').value = '60';
        document.querySelector('[name$="[deliveryLabel]"]').value = 'DPD Next Day Delivery';
        document.querySelector('input[name$="[payment]"][value="deposit"]').click();
        document.querySelector('[data-quote-deposit]').value = '40';
        document.querySelector('[name$="[note]"]').value = 'We look forward to casting it.';
    })()`);
    check('"Send quote" asks first', (await ev(`document.querySelector('[data-quote-send]').textContent.trim()`)) === 'Send quote');
    await pressSend();
    await sleep(800);
    check('without a casting date: saved but not sent', /Quote saved but not sent\. Please add the estimated casting date before sending\./.test(await flash()) && (await quoteMails(since)).length === 0 && status() === 'new', await flash());

    // Sent.
    since = new Date();
    await ev(`document.querySelector('[name$="[castingDate]"]').value = '2026-11-16'`);
    await pressSend();
    const confirmed = await ev(`sessionStorage.getItem('confirmed')`);
    const validUntil = ukDate(new Date(Date.now() + 14 * 86400000));
    check('sent once confirmed ("Send this quote to ada.send@example.test? It will be valid for 14 days.")', confirmed === 'Send this quote to ada.send@example.test? It will be valid for 14 days.', confirmed);
    check(`"Quote sent to … It is valid until ${validUntil}."`, (await flash()).includes(`Quote sent to ${EMAIL}. It is valid until ${validUntil}.`), await flash());
    check('the request is now Quoted', status() === 'quoted');
    const state = await ev(`document.querySelector('[data-quote-state]').innerText.replace(/\\s+/g, ' ')`);
    check('admin shows when it was sent, until when it is valid, and the customer\'s page', new RegExp(`Sent \\d+ \\w+ 2026, \\d\\d:\\d\\d to ${EMAIL.replace('.', '\\.')}\\. Valid until ${validUntil}\\. Open the customer's quote page`).test(state) && (await ev(`document.querySelector('[data-quote-send]').textContent.trim()`)) === 'Send again', state);

    // The email.
    await sleep(800);
    let mails = await quoteMails(since);
    check('one email: "Your Lumen Forma quote, Ada Send"', mails.length === 1 && mails[0].Subject === 'Your Lumen Forma quote, Ada Send', JSON.stringify(mails.map((m) => m.Subject)));
    const mail = await message(mails[0].ID);
    const text = mail.HTML.replace(/<[^>]+>/g, ' ').replace(/&amp;/g, '&').replace(/&#039;/g, "'").replace(/\s+/g, ' ');
    const opening = mail.HTML.match(/<p class="lede"[^>]*>([^<]*)<\/p>\s*<p class="lede"[^>]*>([^<]*)<\/p>/);
    check('the email opens "Dear Ada," (first name only) then "Thank you for your patience. Here is our quote for your Lumen Forma request …", with no heading', opening?.[1] === 'Dear Ada,' && opening?.[2] === `Thank you for your patience. Here is our quote for your Lumen Forma request ${number}.` && !/Your quote is ready/i.test(text) && !/<h1/.test(mail.HTML), JSON.stringify(opening?.slice(1)));
    check('then the studio\'s note', text.includes('We look forward to casting it.'));
    check('each design with its configuration and quoted price (£1,850 each)', /Size Atelier/.test(text) && /The Pigment Grey/.test(text) && /Light Smoked Glass LED \/ Dual Array/.test(text) && /Price £1,850 each/.test(text) && !/Starting at/.test(text), text.slice(300, 1200));
    check('the breakdown: lamps, extra line, delivery, total, 40% deposit and the balance', /Lumen Forma Atelier × 2 £3,700/.test(text) && /Custom pigment development £150/.test(text) && /Delivery · DPD Next Day Delivery £60/.test(text) && /Total £3,910/.test(text) && /Deposit to pay now \(40%\) £1,564/.test(text) && /Balance to pay later £2,346/.test(text) && !/VAT/.test(text), text.slice(text.indexOf('Quote'), text.indexOf('Quote') + 500));
    check(`the casting date, "Pay now · £1,564" and "valid until ${validUntil}"`, /Estimated casting date 16 November 2026/.test(text) && /Pay now · £1,564/.test(text) && text.includes(`This quote is valid until ${validUntil}.`), text.slice(text.indexOf('Casting')));
    check(`replies go to the studio (${STUDIO_EMAIL}), also in the footer`, (mail.ReplyTo || []).some((r) => r.Address === STUDIO_EMAIL) && text.includes(STUDIO_EMAIL), JSON.stringify(mail.ReplyTo));
    const link = mail.HTML.match(/<a href="([^"]+\/quote\/[A-Za-z0-9_-]+)"/)?.[1];
    check('"Pay now" links to the private quote page', !!link, link);
    writeFile(out('email-quote.html'), mail.HTML);

    // The customer's page (a separate visitor, not signed in).
    const c = await open(1440, 1000, 'quote-send-customer', { mobile: false });
    try {
        await c.send('Network.enable'); await c.send('Network.clearBrowserCookies');
        const mainText = () => c.ev(`document.querySelector('main').innerText.replace(/\\s+/g, ' ')`);
        await c.send('Page.navigate', { url: link.replace(/^https?:\/\/[^/]+/, BASE_URL) }); await sleep(2500);
        const page = await c.ev(`({ h1: document.querySelector('main h1')?.innerText, text: document.querySelector('main').innerText.replace(/\\s+/g, ' '), summary: document.querySelector('[data-quote-summary]')?.innerText.replace(/\\s+/g, ' ') })`);
        check('the quote page: "Your Quote", the request, valid until, the note', page.h1 === 'YOUR QUOTE' && page.text.includes(`Request ${number}`) && page.text.includes(`It is valid until ${validUntil}.`) && page.text.includes('We look forward to casting it.'), page.text.slice(0, 300));
        check('the configuration and the breakdown as in the email', /Edition Atelier/.test(page.text) && /Price £1,850 each/.test(page.text) && /Lumen Forma Atelier × 2 £3,700/.test(page.summary) && /Custom pigment development £150/.test(page.summary) && /Total £3,910/.test(page.summary) && /Deposit to pay now \(40%\) £1,564/.test(page.summary) && /Balance to pay later £2,346/.test(page.summary) && /Estimated casting date 16 November 2026/.test(page.summary), page.summary);
        await c.shot(out('quote-page.png'));
        const headers = await c.ev(`fetch(location.href).then(r => r.headers.get('x-robots-tag'))`);
        check('never indexed', /noindex/.test(headers ?? ''), headers);

        // Changed after sending: neither the page nor the email changes until sent again.
        since = new Date();
        await go(quoteUrl, 3500);
        await ev(`(() => { document.querySelector('[data-quote-line] [data-quote-price]').value = '1900'; document.getElementById('quote-form').requestSubmit(); })()`); await sleep(3500);
        const saved = { flash: await flash(), changed: await ev(`document.querySelector('[data-quote-changed]')?.innerText`) };
        check('saved after sending: "Not sent: the customer still sees the quote sent on …", and a warning', /Quote saved\. Not sent: the customer still sees the quote sent on \d+ \w+ 2026\./.test(saved.flash) && /Changed since it was sent/.test(saved.changed ?? ''), JSON.stringify(saved));
        await c.send('Page.navigate', { url: link.replace(/^https?:\/\/[^/]+/, BASE_URL) }); await sleep(2500);
        check('the customer still sees £1,850 each', /Price £1,850 each/.test(await mainText()));
        await sleep(500);
        check('and no email was sent', (await quoteMails(since)).length === 0);

        // Sent again: same link, the new price, valid for 14 days from now.
        sql(`update quote set sent_at = sent_at - interval '3 days', expires_at = expires_at - interval '3 days' where design_request_id = '${id}'`);
        await pressSend();
        check('"Send again": sent, the warning gone', (await flash()).includes(`Quote sent to ${EMAIL}. It is valid until ${validUntil}.`) && !(await ev(`!!document.querySelector('[data-quote-changed]')`)), await flash());
        await sleep(800);
        mails = await quoteMails(since);
        const again = mails[0] ? await message(mails[0].ID) : null;
        check('a second email with the new price and the same link', mails.length === 1 && /Price £1,900 each/.test(again.HTML.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ')) && again.HTML.includes(link), mails.length);
        await c.send('Page.navigate', { url: link.replace(/^https?:\/\/[^/]+/, BASE_URL) }); await sleep(2500);
        check(`the page shows the new price, valid until ${validUntil} again`, /Price £1,900 each/.test(await mainText()) && (await mainText()).includes(`valid until ${validUntil}`));

        // Expired.
        sql(`update quote set expires_at = now() - interval '1 hour' where design_request_id = '${id}'`);
        await c.send('Page.navigate', { url: link.replace(/^https?:\/\/[^/]+/, BASE_URL) }); await sleep(2500);
        const expired = await c.ev(`document.querySelector('main').innerText.replace(/\\s+/g, ' ')`);
        check('an expired quote says so: "This quote expired on … Please contact us or reply to the quote email…"', /This quote expired on \d+ \w+ 20\d\d\. Please contact us or reply to the quote email, and we'll send you an updated quote\./.test(expired), expired.slice(0, 300));
        await go(quoteUrl, 3500);
        check('admin shows it expired, to send again', /Expired on \d+ \w+ 20\d\d\. Send it again for a new 14 days\./.test(await ev(`document.querySelector('[data-quote-state]').innerText`)));

        // Unknown links.
        await c.send('Page.navigate', { url: BASE_URL + '/quote/not-a-real-quote-link-123' }); await sleep(2000);
        const missing = await c.ev(`fetch(location.href).then(r => r.status)`);
        check('an unknown link is not found', missing === 404, missing);
    } finally { c.close(); }
    summary();
} finally { b.close(); }

function writeFile(path, content) {
    import('node:fs').then(({ writeFileSync }) => writeFileSync(path, content));
}
