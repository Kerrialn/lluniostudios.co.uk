// Bespoke Commissions (Step 9.2): enquiries are saved with their files kept
// privately; the server's own checks (file type, 50MB a file, 100MB in
// total, the phone rule) with the page's checks bypassed; the spam trap; the
// limit of 5 enquiries an hour; and admin: the Commissions list, an
// enquiry's details, file downloads, and deleting an enquiry deleting its
// files. Signs in to admin with the local admin account (code from Mailpit).
import { execSync } from 'node:child_process';
import { existsSync, mkdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL, PROJECT_DIR } from './lib.mjs';

const ADMIN = 'lluniostudios@gmail.com';
const STORE = join(PROJECT_DIR, 'var', 'commission-uploads');
const { check, summary } = checker();
const console_ = (cmd) => execSync(`docker compose exec -T php php bin/console ${cmd} 2>/dev/null`, { cwd: PROJECT_DIR, encoding: 'utf8' });
const resetLimit = () => console_('cache:pool:clear cache.rate_limiter');
const b = await open(1440, 900, 'commissions-saving', { mobile: false });
const { send, ev } = b;
const dir = out('commission-saving-files'); mkdirSync(dir, { recursive: true });
const make = (name, mb) => { execSync(`rm -f "${dir}/${name}" && truncate -s ${mb}M "${dir}/${name}"`); return `${dir}/${name}`; };
const count = (where) => Number(sql(`select count(*) from commission_enquiry where ${where}`).match(/\d+/g).pop());
try {
    resetLimit();
    const go = async (url, wait = 2200) => { await send('Page.navigate', { url }); await sleep(wait); };
    const fill = (client, values) => ev(`(() => { const f = document.querySelector('[data-client=${client}] form'); const v = ${JSON.stringify(values)}; for (const [k, x] of Object.entries(v)) { const e = f.querySelector('[name="commission_${client}[' + k + ']"]' + (k === 'contactMethod' ? '[value="' + x + '"]' : '')); if (k === 'contactMethod') e.click(); else e.value = x; } })()`);
    const attach = async (client, files, bypass = false) => {
        if (bypass) await ev(`document.querySelector('[data-client=${client}] input[type=file]').removeAttribute('data-action')`);
        const input = (await send('Runtime.evaluate', { expression: `document.querySelector('[data-client=${client}] input[type=file]')` })).result.result;
        await send('DOM.setFileInputFiles', { files, objectId: input.objectId });
        await sleep(300);
    };
    const submit = async (client, wait = 2500) => { await ev(`document.querySelector('[data-client=${client}] form').requestSubmit()`); await sleep(wait); };
    const errors = () => ev(`[...document.querySelectorAll('[data-commission-target=panel]:not([hidden]) [role=alert]')].filter(e => !e.hidden && e.textContent.trim()).map(e => e.textContent.trim())`);
    const home = { name: 'Ada Test', email: 'ada.test@example.test', phone: '07700 900123', contactMethod: 'phone', idea: 'A tall lamp for the stairwell, about 1.5m.' };
    const project = { company: 'Test Architects Ltd', contactName: 'Ada Test', email: 'ada.test@example.test', phone: '', projectType: 'Hospitality', location: 'Bath, installing spring 2027', project: 'A hotel lobby.', integration: 'DALI dimming.' };
    await send('Network.enable'); await send('Network.clearBrowserCookies');

    // A Home enquiry with two files.
    const before = count("email like '%@example.test'");
    await go(BASE_URL + '/bespoke-commissions?for=home');
    await fill('home', home);
    await attach('home', [make('Hall photo.jpg', 2), make('sketch.pdf', 1)]);
    await submit('home');
    check('a Home enquiry is saved', count("email like '%@example.test'") === before + 1);
    let row = sql(`select enquiry_number, client, status, name, phone, contact_method, idea, company from commission_enquiry where email = 'ada.test@example.test' order by created_at desc limit 1`);
    check('with its details: LC- number, Home, New, name, phone, Phone chosen, idea', /LC-\d{8}-[0-9A-F]{6}/.test(row) && /\bhome\b/.test(row) && /\bnew\b/.test(row) && /07700 900123/.test(row) && /\bphone\b/.test(row) && /stairwell/.test(row), row);
    const homeNumber = row.match(/LC-\d{8}-[0-9A-F]{6}/)?.[0];
    let files = sql(`select f.original_name, f.size, f.stored_name from commission_enquiry_file f join commission_enquiry e on e.id = f.enquiry_id where e.enquiry_number = '${homeNumber}' order by position`);
    const stored = [...files.matchAll(/([0-9a-f]{32}\.(?:jpg|pdf))/g)].map((m) => m[1]);
    check('both files recorded, with the customer\'s names and sizes', /Hall photo\.jpg\s+2097152/.test(files) && /sketch\.pdf\s+1048576/.test(files) && stored.length === 2, files);
    check('the files are kept in the private folder under random names', stored.every((n) => existsSync(join(STORE, n))) && statSync(join(STORE, stored[0])).size === 2097152, stored.join(', '));
    const publicTry = execSync(`curl -sk -o /dev/null -w "%{http_code}" "${BASE_URL}/../var/commission-uploads/${stored[0]}"`, { encoding: 'utf8' });
    check('and cannot be opened from the website', !/^2/.test(publicTry) && !existsSync(join(PROJECT_DIR, 'public', stored[0])), publicTry);

    // A Project enquiry, with a 30MB drawing.
    await go(BASE_URL + '/bespoke-commissions?for=project');
    await fill('project', project);
    await attach('project', [make('Lobby elevations.dwg', 30)]);
    await submit('project', 5000);
    row = sql(`select client, name, company, project_type, location, project, integration, phone from commission_enquiry where email = 'ada.test@example.test' order by created_at desc limit 1`);
    check('a Project enquiry is saved with its details (a 30MB file accepted)', /\bproject\b/.test(row) && /Test Architects Ltd/.test(row) && /Hospitality/.test(row) && /Bath, installing spring 2027/.test(row) && /DALI dimming/.test(row), row);
    check('its file is recorded', /Lobby elevations\.dwg\s+31457280/.test(sql(`select f.original_name, f.size from commission_enquiry_file f join commission_enquiry e on e.id = f.enquiry_id order by e.created_at desc limit 1`)));

    // The server's own checks (the page's checks bypassed).
    const saved = count('true');
    const refused = async (label, files, expected) => {
        await go(BASE_URL + '/bespoke-commissions?for=project');
        await fill('project', project);
        await attach('project', files, true);
        await submit('project', 8000);
        const e = await errors();
        check(`server: ${label}`, e.length === 1 && e[0] === expected && count('true') === saved, JSON.stringify(e));
    };
    await refused('another file type is refused', [make('notes.txt', 1)], '"notes.txt" can\'t be sent: please choose PDF, DWG, JPG, PNG or HEIC files.');
    await refused('a file over 50MB is refused', [make('huge.pdf', 51)], '"huge.pdf" is over 50MB: each file can be up to 50MB.');
    await refused('more than 100MB in total is refused', [make('a.pdf', 45), make('b.pdf', 45), make('c.pdf', 15)], 'These files come to more than 100MB in total. Please choose fewer or smaller files.');
    await refused('more than the server takes at once (over 110MB) is refused', [make('d.pdf', 40), make('e.pdf', 40), make('f.pdf', 40)], 'These files come to more than 100MB in total. Please choose fewer or smaller files.');
    await go(BASE_URL + '/bespoke-commissions?for=home');
    await fill('home', { ...home, phone: '' });
    await ev(`document.querySelector('[data-client=home] input[name="commission_home[phone]"]').value = ''`);
    await submit('home');
    check('server: Phone chosen with no number is refused', JSON.stringify(await errors()) === JSON.stringify(['Please enter your phone number so we can call you.']) && count('true') === saved);
    check('no refused enquiry left files behind', Number(execSync(`ls "${STORE}" | wc -l`, { encoding: 'utf8' }).trim()) === Number(sql('select count(*) from commission_enquiry_file').match(/\d+/g).pop()));

    // The spam trap.
    await go(BASE_URL + '/bespoke-commissions?for=home');
    await fill('home', { ...home, website: 'http://spam.example' });
    await submit('home');
    check('an enquiry with the hidden field filled is not saved', count('true') === saved && (await errors()).length === 0);
    check('the hidden field is off screen and hidden from screen readers', await ev(`(() => { const i = document.querySelector('[name="commission_home[website]"]'); const w = i.closest('[aria-hidden=true]'); return !!w && i.getBoundingClientRect().right < 0 && i.tabIndex === -1; })()`));

    // At most 5 enquiries an hour (2 sent above).
    for (let i = 0; i < 3; i++) { await go(BASE_URL + '/bespoke-commissions?for=home'); await fill('home', home); await submit('home'); }
    check('5 enquiries in an hour are saved', count("email like '%@example.test'") === before + 5);
    await go(BASE_URL + '/bespoke-commissions?for=home'); await fill('home', home); await submit('home');
    check('a 6th is refused with a message, and what was typed is kept', count("email like '%@example.test'") === before + 5 && JSON.stringify(await errors()) === JSON.stringify(["You've sent several enquiries in a short time. Please try again in an hour, or email us at info@lluniostudios.co.uk."]) && (await ev(`document.querySelector('[name="commission_home[name]"]').value`)) === 'Ada Test');
    resetLimit();

    // Admin.
    await go(BASE_URL + '/login');
    await ev(`(() => { const i = document.querySelector('input[name=email]'); i.value = ${JSON.stringify(ADMIN)}; i.form.requestSubmit(); })()`); await sleep(2500);
    const mail = (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent('to:' + ADMIN + ' subject:"sign-in code"')}`)).json()).messages?.[0];
    const text = mail ? (await (await fetch(`${MAILPIT_URL}/api/v1/message/${mail.ID}`)).json()).Text : '';
    const code = text.match(/\b\d{6}\b/)?.[0];
    await ev(`(() => { document.querySelector('[data-pin-code-target=hidden]').value = '${code}'; document.querySelector('[data-pin-code-target=hidden]').form.requestSubmit(); })()`); await sleep(3000);
    await go(BASE_URL + '/admin');
    check('admin menu has "Commissions"', await ev(`[...document.querySelectorAll('a')].some(a => a.textContent.trim() === 'Commissions')`));
    await ev(`[...document.querySelectorAll('a')].find(a => a.textContent.trim() === 'Commissions').click()`); await sleep(2500);
    const list = await ev(`({ heads: [...document.querySelectorAll('table thead th')].map(t => t.innerText.trim()).filter(Boolean), first: document.querySelector('table tbody tr')?.innerText || '' })`);
    check('the list: newest first, with number, received, status, type, name, company, email and files', JSON.stringify(list.heads.slice(0, 8)) === JSON.stringify(['Enquiry', 'Received', 'Status', 'Type', 'Name', 'Company', 'Email', 'Files']) && /\bnew\b/i.test(list.first) && /\bhome\b/i.test(list.first), JSON.stringify(list));
    const homeId = sql(`select id from commission_enquiry where enquiry_number = '${homeNumber}'`).match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)[0];
    await go(`${BASE_URL}/admin/commission-enquiry/${homeId}`, 2500);
    const detail = await ev(`document.querySelector('main, .content-wrapper, body').innerText`);
    check('an enquiry\'s page shows its details', detail.includes(homeNumber) && /07700 900123/.test(detail) && /Phone/.test(detail) && /stairwell/.test(detail), detail.slice(0, 200));
    const links = await ev(`[...document.querySelectorAll('a[href*="/admin/commission-file/"]')].map(a => [a.textContent.trim(), a.getAttribute('href')])`);
    check('and its files, as download links', JSON.stringify(links.map((l) => l[0])) === JSON.stringify(['Hall photo.jpg', 'sketch.pdf']), JSON.stringify(links));
    const dl = await ev(`fetch(${JSON.stringify(links[0]?.[1] || '')}).then(async r => ({ status: r.status, type: r.headers.get('content-type'), disposition: r.headers.get('content-disposition'), size: (await r.blob()).size }))`);
    check('a file downloads under the customer\'s name, never opened in the browser', dl.status === 200 && dl.size === 2097152 && /^attachment; filename="Hall photo\.jpg"/.test(dl.disposition) && dl.type === 'application/octet-stream', JSON.stringify(dl));
    const anon = execSync(`curl -sk -o /dev/null -w "%{http_code} %{redirect_url}" "${BASE_URL}${links[0]?.[1]}"`, { encoding: 'utf8' });
    check('without signing in, the download goes to the sign-in page', /^302 .*\/login/.test(anon), anon);
    // Deleting an enquiry deletes its files.
    await ev(`(() => { const a = [...document.querySelectorAll('a, button')].find(e => /delete/i.test(e.className + e.textContent) && e.offsetParent); a.click(); })()`); await sleep(800);
    await ev(`(() => { const b = document.querySelector('#modal-delete-button, .modal.show button[type=submit], .modal.show .btn-danger'); b.click(); })()`); await sleep(2500);
    check('deleting an enquiry in admin deletes its files', count(`enquiry_number = '${homeNumber}'`) === 0 && stored.every((n) => !existsSync(join(STORE, n))));
    summary();
} finally {
    resetLimit();
    execSync(`rm -rf "${dir}"`);
    b.close();
}
