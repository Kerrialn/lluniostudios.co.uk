// Step 7c.2: "Begin the Conversation" enquiries saved. A complete enquiry is
// saved as a request marked as an enquiry (an LR- number, status New) with its
// piece (size, quantity, finishes to be chosen together), how and when to talk,
// the description, the board link (as typed), the postcode and its country (no
// address), an empty phone when not needed; its files kept privately; the
// server's own file checks; the piece and what was typed forgotten (a Studio
// or Gallery design in progress and the basket kept); the spam trap; the limit
// of 5 an hour; admin: the Requests list's Type column and filter, the
// enquiry's page with everything and its files to download, its quote page
// opening, a reservation's page unchanged, deleting an enquiry deleting its
// files. Until 7c.3 a placeholder page follows (no thank-you page or emails).
// Signs in to admin as lluniostudios@gmail.com (code from Mailpit) and clears
// the enquiry limit before and after.
//   node tests/browser/enquiry-saving.mjs
import { execSync } from 'node:child_process';
import { existsSync, mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL, PROJECT_DIR } from './lib.mjs';

const ADMIN = 'lluniostudios@gmail.com';
const EMAIL = 'ada.saving@example.test';
const STORE = join(PROJECT_DIR, 'var', 'commission-uploads');
const { check, summary } = checker();
const resetLimit = () => execSync('docker compose exec -T php php bin/console cache:pool:clear cache.rate_limiter 2>/dev/null', { cwd: PROJECT_DIR });
const b = await open(1440, 900, 'enquiry-saving', { mobile: false });
const { send, ev } = b;
const dir = out('enquiry-files'); mkdirSync(dir, { recursive: true });
const make = (name, mb) => { execSync(`rm -f "${dir}/${name}" && truncate -s ${mb}M "${dir}/${name}"`); return `${dir}/${name}`; };
const mailpit = async (query) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json()).messages || [];
const mailBody = async (id) => (await fetch(`${MAILPIT_URL}/api/v1/message/${id}`)).json();
try {
    const go = async (url, wait = 2500) => { await send('Page.navigate', { url }); await sleep(wait); await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`); };
    // The configurator's form, as the larger-piece step sends it (larger-piece.mjs checks the step itself).
    const configure = async (config) => {
        await go(BASE_URL + '/lighting-designs');
        await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
        await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify(config))}; f.requestSubmit(); })()`); await sleep(2500);
        await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);
        return ev('location.pathname');
    };
    const piece = (size, quantity) => configure({ size: `lumen-forma-${size}`, quantity, mode: 'all', lamps: [{}], finishes: 'together' });
    const set = (values) => ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(values)})) { const e = document.querySelector('[name="enquiry_form[' + k + ']"]'); e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); } })()`);
    const talk = (value) => ev(`document.querySelector('[name="enquiry_form[talk]"][value=${value}]').click()`);
    const attach = async (files, bypass = false) => {
        if (bypass) await ev(`document.querySelector('[data-controller~=file-chooser] input[type=file]').removeAttribute('data-action')`);
        const input = (await send('Runtime.evaluate', { expression: `document.querySelector('[name="enquiry_form[files][]"]')` })).result.result;
        await send('DOM.setFileInputFiles', { files, objectId: input.objectId });
        await sleep(300);
    };
    const submit = async (wait = 3500) => { await ev(`document.querySelector('form[name=enquiry_form]').requestSubmit()`); await sleep(wait); await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`); };
    const errors = () => ev(`[...document.querySelectorAll('form[name=enquiry_form] [role=alert]')].filter(e => !e.hidden && e.textContent.trim()).map(e => e.textContent.trim())`);
    const count = (email = EMAIL) => Number(sql(`select count(*) from design_request where email = '${email}'`).match(/\d+/g).pop());
    const basket = async () => (await ev(`document.querySelector('#navbar').innerText`)).match(/Basket \((\d+)\)/i)?.[1] ?? '0';
    const minimal = { fullName: 'Ada Saving', email: EMAIL, description: 'A small reading corner.', postcode: 'BH21 3EG' };

    await b.clearStorage(); await send('Network.enable'); await send('Network.clearBrowserCookies');
    // An earlier run's enquiries, with their stored files.
    const earlier = [...sql(`select f.stored_name from design_request_file f join design_request r on r.id = f.design_request_id where r.email like 'ada.saving%@example.test'`).matchAll(/\b[0-9a-f]{32}\.[a-z]+\b/g)].map((m) => m[0]);
    sql(`delete from design_request where email like 'ada.saving%@example.test'`);
    earlier.forEach((name) => rmSync(join(STORE, name), { force: true }));
    resetLimit();

    // A Gallery lamp in the basket and a Studio design in progress: both should be kept.
    await configure({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [{ 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED' }] });
    const basketBefore = await basket();
    const progress = JSON.stringify({ version: 1, size: 'lumen-forma-studio', quantity: 1, mode: 'all', choices: { 'The Pigment': 'White' }, lamps: [], details: {}, lampDetails: [] });
    await ev(`localStorage.setItem('llunio:configurator', ${JSON.stringify(progress)})`);

    // Everything filled in: Monument × 3, a video call, two files, an Isle of Man postcode.
    check('the step sends Monument × 3 to "Begin the Conversation"', (await piece('monument', 3)) === '/begin-the-conversation');
    await ev(`localStorage.setItem('llunio:larger', JSON.stringify({ size: 'lumen-forma-monument', quantity: 3, open: true }))`);
    await set({ fullName: 'Ada Saving', email: EMAIL, phone: '07700 900123', bestTime: 'Weekday evenings', description: 'A double-height hallway.\nOak floor, white walls.', boardLink: 'Pinterest.com/ada/Hallway-Ideas', postcode: 'im1 1aa' });
    await talk('video');
    await attach([make('Hallway.jpg', 2), make('Mood board.pdf', 1)]);
    await submit(4500);
    const after = await ev(`({ path: location.pathname, h1: document.querySelector('h1')?.textContent.trim(), saved: !!document.querySelector('[data-enquiry-saved]'), enquiry: sessionStorage.getItem('llunio:enquiry'), larger: localStorage.getItem('llunio:larger'), configurator: localStorage.getItem('llunio:configurator') })`);
    check('sent: the placeholder page until 7c.3 ("Enquiry Saved")', after.path === '/begin-the-conversation/received' && after.h1 === 'Enquiry Saved' && after.saved, JSON.stringify(after));
    check('one enquiry saved', count() === 1, String(count()));
    const row = sql(`select request_number, type, status, full_name, phone, talk, best_time, board_link, postcode, country, coalesce(address_line1, town, delivery_address, access_notes, custom_details, 'none') as address, gift::text from design_request where email = '${EMAIL}'`);
    const number = row.match(/LR-\d{6}-\d{4}/)?.[0];
    check('saved as an enquiry: an LR- number, status New, the name, phone, a video call, the best time', !!number && /\benquiry\b/.test(row) && /\bnew\b/.test(row) && /Ada Saving/.test(row) && /07700 900123/.test(row) && /\bvideo\b/.test(row) && /Weekday evenings/.test(row), row);
    check('the board link kept exactly as typed', /Pinterest\.com\/ada\/Hallway-Ideas/.test(row), row);
    check('the postcode tidied, the country worked out from it (Isle of Man), no address, not a gift, no reservation notes', /IM1 1AA\s+IM\s+none\s+false\b/.test(row), row);
    const description = sql(`select description from design_request where email = '${EMAIL}'`);
    check('the description saved with its line break', /A double-height hallway\.\s*\n?\s*Oak floor, white walls\./.test(description), description);
    const items = sql(`select i.quantity, i.options_snapshot::text, i.size_name from design_request_item i join design_request r on r.id = i.design_request_id where r.email = '${EMAIL}'`);
    check('its piece: Monument × 3, no options (finishes to be chosen together)', /\b3\b/.test(items) && /Monument/.test(items) && /\[\]/.test(items) && (items.match(/Monument/g) || []).length === 1, items);
    const files = sql(`select f.original_name, f.stored_name, f.size from design_request_file f join design_request r on r.id = f.design_request_id where r.email = '${EMAIL}' order by f.position`);
    const stored = [...files.matchAll(/\b[0-9a-f]{32}\.(?:jpg|pdf)\b/g)].map((m) => m[0]);
    check('both files saved, in order, with their names and sizes', /Hallway\.jpg\s+[0-9a-f]{32}\.jpg\s+2097152/.test(files) && /Mood board\.pdf\s+[0-9a-f]{32}\.pdf\s+1048576/.test(files) && files.indexOf('Hallway') < files.indexOf('Mood board'), files);
    check('kept privately with the other customer files (not in the public folder), under random names', stored.length === 2 && stored.every((n) => existsSync(join(STORE, n)) && !existsSync(join(PROJECT_DIR, 'public', n))), JSON.stringify(stored));
    check('what was typed and the step\'s choices are forgotten; the Studio design in progress is kept', after.enquiry === null && after.larger === null && after.configurator === progress, JSON.stringify(after));
    // Back to the form (the browser's own copy of the page): emptied, nothing remembered again.
    await ev('history.back()'); await sleep(3000);
    const backed = await ev(`({ path: location.pathname, values: [...document.querySelectorAll('form[name=enquiry_form] input[type=text], form[name=enquiry_form] input[type=email], form[name=enquiry_form] input[type=tel], form[name=enquiry_form] textarea')].filter(e => e.tabIndex !== -1).map(e => e.value).join(''), talk: !!document.querySelector('[name="enquiry_form[talk]"]:checked'), files: document.querySelector('[name="enquiry_form[files][]"]').files.length, listed: document.querySelectorAll('[data-controller~=file-chooser] li').length, phone: document.querySelector('label[for=enquiry_form_phone]').innerText.replace(/\\s+/g, ' ').trim(), saved: sessionStorage.getItem('llunio:enquiry') })`);
    check('Back to the sent form: every field empty, no way to talk chosen, no files, the phone "(optional)" again, nothing remembered', backed.path === '/begin-the-conversation' && backed.values === '' && !backed.talk && backed.files === 0 && backed.listed === 0 && backed.phone === 'Phone (optional)' && backed.saved === null, JSON.stringify(backed));
    await go(BASE_URL + '/contact', 1500);
    check('the basket is left as it was', (await basket()) === basketBefore && basketBefore === '1', `${basketBefore} → ${await basket()}`);
    await go(BASE_URL + '/begin-the-conversation');
    check('the piece is forgotten: the page goes back to Lighting Designs', (await ev('location.pathname')) === '/lighting-designs');
    check('no emails yet (7c.3)', (await mailpit(`to:${EMAIL}`)).length === 0);

    // The fewest details: email, no phone; nothing optional saved.
    await piece('atelier', 1);
    await set(minimal); await talk('email');
    await submit();
    const plain = sql(`select coalesce(phone, best_time, board_link, 'empty') as optional, talk from design_request where email = '${EMAIL}' order by created_at desc limit 1`);
    check('an email enquiry without a phone: saved with the phone, best time and board link empty', count() === 2 && /\bempty\s+email\b/.test(plain), plain);

    // The server's own file checks (the page's bypassed).
    await piece('estate', 1);
    await set(minimal); await talk('email');
    await attach([make('notes.docx', 1)], true);
    await submit();
    check('a file of another type sent anyway: refused by the server in the same words, nothing saved', count() === 2 && (await errors()).includes('"notes.docx" can\'t be sent: please choose PDF, DWG, JPG, PNG or HEIC files.'), JSON.stringify(await errors()));

    // The spam trap: thanked, nothing saved.
    await go(BASE_URL + '/begin-the-conversation');
    check('the hidden field is off screen and hidden from screen readers', await ev(`(() => { const i = document.querySelector('[name="enquiry_form[website]"]'); const w = i.closest('[aria-hidden=true]'); return !!w && i.getBoundingClientRect().right < 0 && i.tabIndex === -1; })()`));
    await set({ ...minimal, website: 'http://spam.example' }); await talk('email');
    await submit();
    check('filled in (a spam bot): the same placeholder page, nothing saved', (await ev('location.pathname')) === '/begin-the-conversation/received' && count() === 2);
    check('the hidden field is never remembered with what was typed', !/website/.test(await ev(`sessionStorage.getItem('llunio:enquiry') || ''`)), await ev(`sessionStorage.getItem('llunio:enquiry')`));

    // At most 5 an hour from one connection (two sent above).
    for (let i = 0; i < 3; i++) {
        await piece('atelier', 1);
        await set({ ...minimal, email: `ada.saving${i}@example.test` }); await talk('email');
        await submit();
    }
    await piece('atelier', 1);
    await set({ ...minimal, email: 'ada.saving9@example.test' }); await talk('email');
    await submit();
    check('three more within the hour: saved', [0, 1, 2].every((i) => count(`ada.saving${i}@example.test`) === 1));
    const limited = (await ev(`document.querySelector('form[name=enquiry_form]')?.closest('div').innerText`)) || '';
    check('a sixth within the hour: refused with the message (DRAFT), nothing saved, what was typed kept', count('ada.saving9@example.test') === 0 && /You've sent several enquiries in a short time\. Please try again in an hour, or email me at \S+@\S+\./.test(limited) && (await ev(`document.querySelector('[name="enquiry_form[description]"]').value`)) === minimal.description, limited.slice(0, 300));
    resetLimit();

    // A reservation, to compare in admin.
    await configure({ size: 'lumen-forma-gallery', quantity: 3, mode: 'all', lamps: [{ 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED' }] });
    await ev(`(() => { for (const [k, v] of Object.entries({ fullName: 'Ada Reserving', email: 'ada.saving.reserve@example.test', phone: '07700 900123', addressLine1: '1 High Street', town: 'Wimborne', postcode: 'BH21 1AA' })) document.querySelector('[name="reserve_form[' + k + ']"]').value = v; document.querySelector('form[name=reserve_form]').requestSubmit(); })()`); await sleep(4000);
    const reservation = sql(`select request_number, type from design_request where email = 'ada.saving.reserve@example.test'`);
    const reservationNumber = reservation.match(/LR-\d{6}-\d{4}/)?.[0];
    check('a reservation from Reserve Your Design is saved as a Reservation', !!reservationNumber && /\breservation\b/.test(reservation), reservation);

    // Admin.
    await go(BASE_URL + '/login');
    await ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
    const mail = (await mailpit(`to:${ADMIN} subject:"Sign in to Llunio Studios"`))[0];
    const code = (mail ? (await mailBody(mail.ID)).Text : '').match(/\b\d{6}\b/)?.[0];
    await ev(`(() => { document.querySelector('[data-pin-code-target=hidden]').value = '${code}'; document.querySelector('[data-pin-code-target=hidden]').form.requestSubmit(); })()`); await sleep(3000);

    const typeOf = (num) => ev(`(() => { const head = [...document.querySelectorAll('thead th')].map(t => t.innerText.trim()); const i = head.indexOf('Type'); const r = [...document.querySelectorAll('tbody tr')].find(r => r.innerText.includes(${JSON.stringify(num)})); return r && i >= 0 ? r.children[i].innerText.trim() : null; })()`);
    await go(`${BASE_URL}/admin/design-request`, 3000);
    check('Requests list: a Type column, "Enquiry" for the enquiry and "Reservation" for the reservation', (await typeOf(number)) === 'Enquiry' && (await typeOf(reservationNumber)) === 'Reservation', `${await typeOf(number)} / ${await typeOf(reservationNumber)}`);
    const has = (num) => ev(`document.querySelector('tbody').innerText.includes(${JSON.stringify(num)})`);
    await go(`${BASE_URL}/admin/design-request?filters[type][comparison]=%3D&filters[type][value]=enquiry`, 3000);
    check('the Type filter: Enquiry lists the enquiry, not the reservation', (await has(number)) && !(await has(reservationNumber)));
    await go(`${BASE_URL}/admin/design-request?filters[type][comparison]=%3D&filters[type][value]=reservation`, 3000);
    check('the Type filter: Reservation lists the reservation, not the enquiry', (await has(reservationNumber)) && !(await has(number)));

    const idOf = (num) => sql(`select id from design_request where request_number = '${num}'`).match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)[0];
    const enquiryId = idOf(number);
    await go(`${BASE_URL}/admin/design-request/${enquiryId}`, 3000);
    const page = await ev(`({ title: document.querySelector('h1')?.innerText.trim(), fields: Object.fromEntries([...document.querySelectorAll('.field-group, .form-group, dl > div, .data-row')].map(g => [g.querySelector('.field-label, dt, label')?.innerText.trim(), g.querySelector('.field-value, dd, .form-widget')?.innerText.trim()]).filter(([k]) => k)), text: document.querySelector('main, #main, body').innerText })`);
    const f = page.fields;
    check('the enquiry\'s page: titled "Enquiry LR-…", Type Enquiry, status New', page.title === `Enquiry ${number}` && f.Type === 'Enquiry' && f.Status === 'New', JSON.stringify([page.title, f.Type, f.Status]));
    check('…the contact details, how and when to talk', f.Name === 'Ada Saving' && f.Email === EMAIL && f.Phone === '07700 900123' && f['How to talk'] === 'Video call' && f['Best time to reach them'] === 'Weekday evenings', JSON.stringify(f));
    check('…their space (with its line break), the board link as typed, the postcode and country', /A double-height hallway\.\nOak floor, white walls\./.test(f['Their space and what they have in mind'] || '') && f['Board link'] === 'Pinterest.com/ada/Hallway-Ideas' && f.Postcode === 'IM1 1AA' && f.Country === 'Isle of Man', JSON.stringify(f));
    const board = await ev(`(() => { const a = [...document.querySelectorAll('a')].find(a => a.textContent.trim() === 'Pinterest.com/ada/Hallway-Ideas'); return a && { href: a.getAttribute('href'), target: a.target, rel: a.rel }; })()`);
    check('…the board link clickable, as typed, opening in a new tab ("https://" added)', board?.href === 'https://Pinterest.com/ada/Hallway-Ideas' && board.target === '_blank' && /noopener/.test(board.rel), JSON.stringify(board));
    check('…the piece: Monument × 3, finishes to be chosen together', /Monument/.test(f.Lamps || '') && /× ?3|3 ×|Quantity:? ?3|\b3\b/.test(f.Lamps || '') && /Finishes: to be chosen together/i.test(f.Lamps || ''), f.Lamps);
    check('…and none of a reservation\'s fields (address lines, access notes, Custom details, gift)', !/Address line 1|Site access|Custom details|Gift|Packing|Recipient/i.test(Object.keys(f).join('|')), Object.keys(f).join('|'));
    const links = await ev(`[...document.querySelectorAll('a[href*="/admin/request-file/"]')].map(a => [a.textContent.trim(), a.getAttribute('href')])`);
    check('…its "Files" as download links', JSON.stringify(links.map((l) => l[0])) === JSON.stringify(['Hallway.jpg', 'Mood board.pdf']) && 'Files' in f, JSON.stringify(links));
    const dl = await ev(`fetch(${JSON.stringify(links[0]?.[1] || '')}).then(async r => ({ status: r.status, disposition: r.headers.get('content-disposition'), size: (await r.blob()).size }))`);
    check('a file downloads under the customer\'s file name', dl.status === 200 && dl.size === 2097152 && /^attachment; filename="?Hallway\.jpg"?/.test(dl.disposition), JSON.stringify(dl));
    const quote = await ev(`fetch('/admin/design-request/${enquiryId}/quote').then(async r => ({ status: r.status, monument: (await r.text()).includes('Monument') }))`);
    check('its quote page opens (the quote itself is checked in 7c.4)', quote.status === 200 && quote.monument, JSON.stringify(quote));

    await go(`${BASE_URL}/admin/design-request/${idOf(reservationNumber)}`, 3000);
    const resPage = await ev(`({ title: document.querySelector('h1')?.innerText.trim(), text: document.body.innerText })`);
    check('a reservation\'s page as before: "Request LR-…", Type Reservation, its address, no enquiry fields', resPage.title === `Request ${reservationNumber}` && /Address line 1/.test(resPage.text) && /Reference photos/.test(resPage.text) && !/How to talk|Board link|Their space/.test(resPage.text), resPage.title);

    // Deleting the enquiry deletes its files.
    await go(`${BASE_URL}/admin/design-request/${enquiryId}`, 3000);
    await ev(`(() => { const a = [...document.querySelectorAll('a, button')].find(e => /delete/i.test(e.className + e.textContent) && e.offsetParent); a.click(); })()`); await sleep(800);
    await ev(`(() => { const b = document.querySelector('#modal-delete-button, .modal.show button[type=submit], .modal.show .btn-danger'); b.click(); })()`); await sleep(2500);
    check('deleting the enquiry in admin deletes its files', count() === 1 && stored.every((n) => !existsSync(join(STORE, n))));
    summary();
} finally {
    resetLimit();
    execSync(`rm -rf "${dir}"`);
    b.close();
}
