// Reference photos on Reserve Your Design (10 October 2026): with a Custom
// choice, an optional "Reference photos" box under "Custom Details", with
// the Bespoke Commissions upload rules (PDF, DWG, JPG, PNG or HEIC, 50MB
// each, 100MB in total, checked on the page and again on the server). The
// photos are kept privately with the commission files, listed (not attached)
// in the studio's notification, downloaded from the request in admin, and
// deleted with the request. Signs in to admin with the local admin account
// (code from Mailpit).
import { execSync } from 'node:child_process';
import { existsSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL, PROJECT_DIR } from './lib.mjs';

const ADMIN = 'lluniostudios@gmail.com';
const EMAIL = 'ada.photos@example.test';
const STORE = join(PROJECT_DIR, 'var', 'commission-uploads');
const HINT = "A photo of anything that shows what you have in mind, whether a colour, a material, a finish or a bulb, helps us get it right. Colours and finishes vary between photos and screens, so we'll confirm the details with you before casting.";
const { check, summary } = checker();
const b = await open(1440, 900, 'reserve-photos', { mobile: false });
const { send, ev } = b;
const dir = out('reserve-photo-files'); mkdirSync(dir, { recursive: true });
const make = (name, mb) => { execSync(`rm -f "${dir}/${name}" && truncate -s ${mb}M "${dir}/${name}"`); return `${dir}/${name}`; };
const mailpit = async (query) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json()).messages || [];
const mailBody = async (id) => (await fetch(`${MAILPIT_URL}/api/v1/message/${id}`)).json();
const lamp = { 'The Pigment': 'Custom', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED' };
try {
    const go = async (url, wait = 2200) => { await send('Page.navigate', { url }); await sleep(wait); };
    // Straight to Reserve Your Design with one Gallery lamp (as the configurator sends it).
    const reserveWith = async (choices, details) => {
        await go(BASE_URL + '/lighting-designs', 2000);
        await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(300);
        await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [choices], details: [details] }))}; f.requestSubmit(); })()`);
        await sleep(2500);
    };
    const field = () => ev(`(() => { const c = document.querySelector('[data-controller=file-chooser]'); if (!c) return null; const custom = document.querySelector('[name="reserve_form[customDetails]"]').closest('label').getBoundingClientRect(); const r = c.getBoundingClientRect(); return { label: c.querySelector('span').childNodes[0].textContent.trim(), hint: c.querySelector('span span').textContent.trim(), drop: c.querySelector('label').textContent.trim(), under: r.top >= custom.bottom && r.top - custom.bottom < 40, accept: c.querySelector('input').accept, multiple: c.querySelector('input').multiple }; })()`);
    const attach = async (files, bypass = false) => {
        if (bypass) await ev(`document.querySelector('[data-controller=file-chooser] input[type=file]').removeAttribute('data-action')`);
        const input = (await send('Runtime.evaluate', { expression: `document.querySelector('[data-controller=file-chooser] input[type=file]')` })).result.result;
        await send('DOM.setFileInputFiles', { files, objectId: input.objectId });
        await sleep(300);
    };
    const chooser = () => ev(`(() => { const c = document.querySelector('[data-controller=file-chooser]'); const e = c.querySelector('[data-file-chooser-target=error]'); return { listed: [...c.querySelectorAll('li')].map(l => l.textContent), error: e.hidden ? '' : e.textContent, kept: c.querySelector('input').files.length }; })()`);
    const typed = { fullName: 'Ada Photos', email: EMAIL, phone: '07700 900123', addressLine1: '1 High Street', town: 'Wimborne', county: 'Dorset', postcode: 'BH21 1AA' };
    const fill = () => ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(typed)})) { const f = document.querySelector('[name="reserve_form[' + k + ']"]'); f.value = v; } })()`);
    const submit = async (wait = 3500) => { await ev(`document.querySelector('form[name=reserve_form]').requestSubmit()`); await sleep(wait); };
    const requestCount = () => Number(sql(`select count(*) from design_request where email = '${EMAIL}'`).match(/\d+/g).pop());
    await send('Network.enable'); await send('Network.clearBrowserCookies');
    sql(`delete from design_request where email = '${EMAIL}'`);

    // No Custom choice: no box.
    await go(BASE_URL + '/lighting-designs', 2000);
    await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(300);
    await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-atelier', quantity: 1, mode: 'all', lamps: [{ ...lamp, 'The Pigment': 'Grey', 'Bulb Count': 'Dual Array' }] }))}; f.requestSubmit(); })()`); await sleep(2500);
    check('no Custom choice (an Atelier): no Reference photos box', (await ev('location.pathname')) === '/reserve' && (await field()) === null);

    // A Custom choice: the box under Custom Details.
    await reserveWith(lamp, { 'The Pigment': 'Terracotta, like the swatch attached.' });
    const f = await field();
    check('a Custom choice: "Reference photos" under Custom Details, with its hint', (await ev('location.pathname')) === '/reserve' && f?.label === 'Reference photos' && f.hint === HINT && f.under, JSON.stringify(f));
    check('the file box as Bespoke Commissions: several files, PDF, DWG, JPG, PNG or HEIC, 50MB each, 100MB in total', f?.multiple && f.accept === '.pdf,.dwg,.jpg,.jpeg,.png,.heic' && f.drop === 'Choose photos or files — PDF, DWG, JPG, PNG or HEIC, up to 50MB each, 100MB in total', JSON.stringify(f));

    // The page's checks.
    await attach([make('notes.docx', 1)]);
    let c = await chooser();
    check('another type is refused on the page, with the Commissions message', c.error === '"notes.docx" can\'t be sent: please choose PDF, DWG, JPG, PNG or HEIC files.' && c.kept === 0, JSON.stringify(c));
    await attach([make('huge.jpg', 51)]);
    c = await chooser();
    check('a file over 50MB is refused on the page', c.error === '"huge.jpg" is over 50MB: each file can be up to 50MB.' && c.kept === 0, JSON.stringify(c));
    await attach([make('a.jpg', 40), make('b.jpg', 40), make('c.png', 30)]);
    c = await chooser();
    check('more than 100MB together is refused on the page', c.error === 'These files come to more than 100MB in total. Please choose fewer or smaller files.' && c.kept === 0, JSON.stringify(c));

    // The server's checks (the page's bypassed).
    await fill(); await attach([make('notes.docx', 1)], true);
    const before = requestCount();
    await submit();
    let errors = await ev(`[...document.querySelectorAll('form [role=alert]')].filter(e => !e.hidden && e.textContent.trim()).map(e => e.textContent.trim())`);
    check('sent anyway, the server refuses it in the same words and nothing is saved', requestCount() === before && errors.includes('"notes.docx" can\'t be sent: please choose PDF, DWG, JPG, PNG or HEIC files.'), JSON.stringify(errors));

    // Two photos sent.
    await go(BASE_URL + '/reserve');
    await fill(); await attach([make('Paint chip.jpg', 2), make('Fabric.png', 1)]);
    c = await chooser();
    check('chosen photos are listed with their sizes', JSON.stringify(c.listed) === JSON.stringify(['Paint chip.jpg (2.0MB)', 'Fabric.png (1.0MB)']), JSON.stringify(c));
    await submit(4500);
    check('the reservation is sent', (await ev('location.pathname')) === '/reserve/received');
    const rows = sql(`select f.original_name, f.stored_name, f.size from design_request_file f join design_request r on r.id = f.design_request_id where r.email = '${EMAIL}' order by f.position`);
    const stored = [...rows.matchAll(/\b[0-9a-f]{32}\.(?:jpg|png)\b/g)].map((m) => m[0]);
    check('both photos are saved with the request, in order, with their names and sizes', /Paint chip\.jpg\s+[0-9a-f]{32}\.jpg\s+2097152/.test(rows) && /Fabric\.png\s+[0-9a-f]{32}\.png\s+1048576/.test(rows) && rows.indexOf('Paint chip') < rows.indexOf('Fabric'), rows);
    check('kept privately with the commission files (not in the public folder), under random names', stored.length === 2 && stored.every((n) => existsSync(join(STORE, n)) && !existsSync(join(PROJECT_DIR, 'public', n))), JSON.stringify(stored));
    const number = sql(`select request_number from design_request where email = '${EMAIL}' order by created_at desc limit 1`).match(/LR-\d{6}-\d{4}/)?.[0];

    // The studio's notification: listed, not attached.
    let notification = null;
    for (let i = 0; i < 20 && !notification; i++) {
        for (const m of await mailpit('to:lluniostudios@gmail.com subject:"New request"')) { const body = await mailBody(m.ID); if (body.HTML.includes(number)) { notification = body; break; } }
        if (!notification) await sleep(500);
    }
    const text = (notification?.Text || '').replace(/\s+/g, ' ');
    check('your notification lists the photos (names and sizes) and links to the request in admin, with nothing attached', !!notification && /Reference photos\s*2 files/.test(text) && /Paint chip\.jpg \(2\.0MB\)/.test(notification.HTML) && /Fabric\.png \(1\.0MB\)/.test(notification.HTML) && /Download from the request in admin/.test(notification.HTML) && /\/admin\/design-request\//.test(notification.HTML) && (notification.Attachments || []).length === 0, text.slice(0, 400));

    // Admin: download links on the request's page.
    await go(BASE_URL + '/login');
    await ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
    const mail = (await mailpit(`to:${ADMIN} subject:"Sign in to Llunio Studios"`))[0];
    const code = (mail ? (await mailBody(mail.ID)).Text : '').match(/\b\d{6}\b/)?.[0];
    await ev(`(() => { document.querySelector('[data-pin-code-target=hidden]').value = '${code}'; document.querySelector('[data-pin-code-target=hidden]').form.requestSubmit(); })()`); await sleep(3000);
    const requestId = sql(`select id from design_request where request_number = '${number}'`).match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)[0];
    await go(`${BASE_URL}/admin/design-request/${requestId}`, 2500);
    const links = await ev(`[...document.querySelectorAll('a[href*="/admin/request-file/"]')].map(a => [a.textContent.trim(), a.getAttribute('href')])`);
    check('the request\'s page in admin lists "Reference photos" as download links', JSON.stringify(links.map((l) => l[0])) === JSON.stringify(['Paint chip.jpg', 'Fabric.png']) && /Reference photos/.test(await ev('document.body.innerText')), JSON.stringify(links));
    const dl = await ev(`fetch(${JSON.stringify(links[0]?.[1] || '')}).then(async r => ({ status: r.status, type: r.headers.get('content-type'), disposition: r.headers.get('content-disposition'), size: (await r.blob()).size }))`);
    check('a photo downloads under the customer\'s file name, never opened in the browser', dl.status === 200 && dl.size === 2097152 && /^attachment; filename="Paint chip\.jpg"/.test(dl.disposition) && dl.type === 'application/octet-stream', JSON.stringify(dl));
    const anon = execSync(`curl -sk -o /dev/null -w "%{http_code} %{redirect_url}" "${BASE_URL}${links[0]?.[1]}"`, { encoding: 'utf8' });
    check('without signing in, the download goes to the sign-in page', /^302 .*\/login/.test(anon), anon);
    const commissions = await ev(`fetch('/admin/commission-enquiry').then(r => r.status)`);
    check('the Commissions section in admin still opens (its file list shares the template)', commissions === 200, String(commissions));

    // Deleting the request deletes its photos.
    await ev(`(() => { const a = [...document.querySelectorAll('a, button')].find(e => /delete/i.test(e.className + e.textContent) && e.offsetParent); a.click(); })()`); await sleep(800);
    await ev(`(() => { const b = document.querySelector('#modal-delete-button, .modal.show button[type=submit], .modal.show .btn-danger'); b.click(); })()`); await sleep(2500);
    check('deleting the request in admin deletes its photos', requestCount() === 0 && stored.every((n) => !existsSync(join(STORE, n))));
    summary();
} finally {
    execSync(`rm -rf "${dir}"`);
    b.close();
}
