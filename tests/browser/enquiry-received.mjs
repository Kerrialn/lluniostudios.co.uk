// Step 7c.3: after a "Begin the Conversation" enquiry. "Enquiry Received"
// (laid out like the other thank-you pages: how they'll be contacted, by
// email / by phone / to arrange a video call, about their piece; no reference
// number); the customer's email (the email rules, in the "me" voice: "Dear
// Ada," then the opening, the sections, signed exactly "All the best, / Theo /
// @LlunioStuidos", replies to the studio); the studio's notification ("New
// enquiry · Atelier × 1 · Video call", no greeting, the summary first, every
// detail, the files listed with the admin link, replies to the customer); a
// spam-trapped enquiry thanked with no emails. All wording DRAFT (11.5).
// Clears the enquiry limit before and after.
//   node tests/browser/enquiry-received.mjs
import { execSync } from 'node:child_process';
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { open, sleep, checker, sql, out, BASE_URL, MAILPIT_URL, PROJECT_DIR, STUDIO_EMAIL, customerEmailProblems, studioEmailProblems } from './lib.mjs';

const EMAIL = 'ada.received@example.test';
const { check, summary } = checker();
const resetLimit = () => execSync('docker compose exec -T php php bin/console cache:pool:clear cache.rate_limiter 2>/dev/null', { cwd: PROJECT_DIR });
const b = await open(1440, 900, 'enquiry-received', { mobile: false });
const { send, ev } = b;
const dir = out('enquiry-received-files'); mkdirSync(dir, { recursive: true });
const make = (name, mb) => { execSync(`rm -f "${dir}/${name}" && truncate -s ${mb}M "${dir}/${name}"`); return `${dir}/${name}`; };
const mailpit = async (query) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json()).messages || [];
const mailBody = async (id) => (await fetch(`${MAILPIT_URL}/api/v1/message/${id}`)).json();
const text = (html) => html.replace(/<style[\s\S]*?<\/style>/g, ' ').replace(/<br\s*\/?>/g, '\n').replace(/<[^>]+>/g, ' ').replace(/&#039;/g, "'").replace(/&amp;/g, '&').replace(/&nbsp;/g, ' ').replace(/[ \t]+/g, ' ').replace(/\s*\n\s*/g, '\n').trim();
try {
    const go = async (url, wait = 2500) => { await send('Page.navigate', { url }); await sleep(wait); await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`); };
    // The piece, as the larger-piece step sends it (larger-piece.mjs checks the step itself).
    const piece = async (size, quantity) => {
        await go(BASE_URL + '/lighting-designs');
        await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
        await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: `lumen-forma-${size}`, quantity, mode: 'all', lamps: [{}], finishes: 'together' }))}; f.requestSubmit(); })()`); await sleep(2500);
        await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);
    };
    const enquire = async (values, talk, files = []) => {
        await ev(`(() => { for (const [k, v] of Object.entries(${JSON.stringify(values)})) { const e = document.querySelector('[name="enquiry_form[' + k + ']"]'); e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); } document.querySelector('[name="enquiry_form[talk]"][value=${talk}]').click(); })()`);
        if (files.length) {
            const input = (await send('Runtime.evaluate', { expression: `document.querySelector('[name="enquiry_form[files][]"]')` })).result.result;
            await send('DOM.setFileInputFiles', { files, objectId: input.objectId }); await sleep(300);
        }
        await ev(`document.querySelector('form[name=enquiry_form]').requestSubmit()`); await sleep(4000);
        await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);
        return ev(`({ path: location.pathname, text: document.querySelector('main').innerText.trim().replace(/\\s+/g, ' ') })`);
    };
    const latest = (email) => sql(`select request_number from design_request where email = '${email}' order by created_at desc limit 1`).match(/LR-\d{6}-\d{4}/)?.[0];
    const customerMail = async (email, number) => { for (let i = 0; i < 20; i++) { for (const m of await mailpit(`to:${email}`)) { const body = await mailBody(m.ID); if (body.HTML.includes(number)) return body; } await sleep(500); } return null; };
    const studioMail = async (number) => { for (let i = 0; i < 20; i++) { for (const m of await mailpit('to:lluniostudios@gmail.com subject:"New enquiry"')) { const body = await mailBody(m.ID); if (body.HTML.includes(number)) return body; } await sleep(500); } return null; };

    await b.clearStorage(); await send('Network.enable'); await send('Network.clearBrowserCookies');
    // An earlier run's enquiries, with their stored files.
    const earlier = [...sql(`select f.stored_name from design_request_file f join design_request r on r.id = f.design_request_id where r.email like 'ada.received%@example.test'`).matchAll(/\b[0-9a-f]{32}\.[a-z]+\b/g)].map((m) => m[0]);
    sql(`delete from design_request where email like 'ada.received%@example.test'`);
    earlier.forEach((name) => rmSync(join(PROJECT_DIR, 'var', 'commission-uploads', name), { force: true }));
    resetLimit();

    // A video call about an Atelier, with everything filled in.
    await piece('atelier', 1);
    let page = await enquire({ fullName: 'Ada Received', email: EMAIL, phone: '07700 900123', bestTime: 'Weekday evenings', description: 'A double-height hallway.\nOak floor, white walls.', boardLink: 'pinterest.com/ada/hallway', postcode: 'SW1A 1AA' }, 'video', [make('Hallway.jpg', 2), make('Mood board.pdf', 1)]);
    check('"Enquiry Received": the heading, "I\'ll be in touch to arrange a video call, to talk about your Atelier lamp.", the confirmation line, no reference (DRAFT)', page.path === '/begin-the-conversation/received'
        && page.text === "ENQUIRY RECEIVED Thank you. I'll be in touch to arrange a video call, to talk about your Atelier lamp. Confirmation has been sent to the email address provided." && !/LR-/.test(page.text), JSON.stringify(page));
    const layout = await ev(`(() => { const h = document.querySelector('main h1'); const p = document.querySelector('main p'); const r = (e) => e.getBoundingClientRect(); return { col: Math.round(r(h.parentElement).width), left: Math.round(r(h).left), hSize: getComputedStyle(h).fontSize, pSize: getComputedStyle(p).fontSize, pColour: getComputedStyle(p).color, rule: getComputedStyle(p.parentElement).borderTopStyle }; })()`);
    await go(BASE_URL + '/contact');
    const contact = await ev(`(() => { const h = document.querySelector('main h1'); const a = document.querySelector('main address'); const r = (e) => e.getBoundingClientRect(); return { col: Math.round(r(h.parentElement).width), left: Math.round(r(h).left), hSize: getComputedStyle(h).fontSize, pSize: getComputedStyle(a).fontSize, pColour: getComputedStyle(a).color, rule: getComputedStyle(a).borderTopStyle }; })()`);
    check('laid out like the other thank-you pages and Contact (column, heading, text size and colour, rule)', JSON.stringify(layout) === JSON.stringify(contact), JSON.stringify({ layout, contact }));
    await go(BASE_URL + '/begin-the-conversation/received');
    check('the thank-you page can be reopened in the same visit', (await ev('location.pathname')) === '/begin-the-conversation/received');

    const number = latest(EMAIL);
    const mail = await customerMail(EMAIL, number);
    check('the customer\'s email is sent: "Your Lumen Forma enquiry, Ada" (the first name, as the greeting; DRAFT)', mail?.Subject === 'Your Lumen Forma enquiry, Ada', mail?.Subject);
    if (mail) {
        writeFileSync(out('enquiry-customer.html'), mail.HTML);
        const opening = "Thank you for your enquiry about a Lumen Forma Atelier lamp. I'll be in touch to arrange a video call to talk through your space and the finishes, then send you a quote.";
        const format = customerEmailProblems(mail.HTML, 'Ada', opening);
        check('it follows the email rules: Montage C, no heading, "Dear Ada," then the opening ("me", a video call), one text size, "Llunio Studios" in full (the signature\'s handle excepted)', format.length === 0, format.join('; '));
        const t = text(mail.HTML);
        check('then the signature, then 01 (no "No payment has been taken.", removed 13 October 2026)', t.includes(`${opening}\nAll the best,\nTheo\n@LlunioStuidos\n01 Your enquiry`) && !/No payment has been taken/.test(t), t.slice(0, 400));
        check('01 Your enquiry: the enquiry number', t.includes(`01 Your enquiry\nEnquiry number\n${number}\n`), t.slice(0, 600));
        check('02 Your piece: Size Atelier, Finishes To be chosen together, Quantity 1, Starting at £1,699', /02 Your piece\nLumen Forma\nSize Atelier\nFinishes To be chosen together\nQuantity 1\nPrice Starting at £1,699\n/.test(t), t);
        check('03 What you told me: the description (line break kept), the board as typed, "2 files received"', /03 What you told me\nYour space and what you have in mind\nA double-height hallway\.\nOak floor, white walls\.\nYour board\npinterest\.com\/ada\/hallway\nPhotos and files\n2 files received\n/.test(t), t);
        check('04 Getting in touch: "Video call · 07700 900123", the best time', /04 Getting in touch\nHow you'd like to talk\nVideo call · 07700 900123\nBest time to reach you\nWeekday evenings\n/.test(t), t);
        const signature = await (async () => { const m = mail.HTML.match(/<div data-email-signature>([\s\S]*?)<\/div>/); return m ? [...m[1].matchAll(/<p [^>]*>([\s\S]*?)<\/p>/g)].map((p) => p[1].replace(/<br\s*\/?>\s*/g, '\n').trim()) : null; })();
        check('signed exactly "All the best," / blank line / "Theo" / "@LlunioStuidos", straight after the opening, before the enquiry\'s details (13 October 2026)', JSON.stringify(signature) === JSON.stringify(['All the best,', 'Theo\n@LlunioStuidos']) && t.indexOf('@LlunioStuidos') < t.indexOf('01 Your enquiry') && /Weekday evenings\nLlunio Studios\n/.test(t), JSON.stringify(signature));
        const above = t.split('\nLlunio Studios\ninfo@')[0];
        check('in the "me" voice (no "we" or "us" in the text above the footer)', !/\b(we|we'll|we've|us|our)\b/i.test(above), (above.match(/\b(we|we'll|we've|us|our)\b/gi) || []).join());
        check('replies go to the studio', (mail.ReplyTo || []).some((r) => r.Address === STUDIO_EMAIL), JSON.stringify(mail.ReplyTo));
        check('Dorset Studio, never Bournemouth; images hosted on the site', /Dorset Studio/.test(mail.HTML) && !/Bournemouth/.test(mail.HTML) && mail.HTML.includes(BASE_URL + '/images/email/header-montage-c.jpg'));
    }

    const studio = await studioMail(number);
    check('your notification: "New enquiry · Atelier × 1 · Video call"', studio?.Subject === 'New enquiry · Atelier × 1 · Video call', studio?.Subject);
    if (studio) {
        writeFileSync(out('enquiry-studio.html'), studio.HTML);
        const format = studioEmailProblems(studio.HTML, 'New enquiry');
        const t = text(studio.HTML);
        check('no greeting or signature; "New enquiry" first', format.length === 0 && !/Theo|All the best/.test(t), format.join('; '));
        check('the summary: enquiry and time, lamps, talk by, best time, phone, postcode, files, customer, payment none taken', new RegExp(`New enquiry\\nEnquiry ${number} · \\d+ October 2026, \\d\\d:\\d\\d\\nLamps Atelier × 1\\nTalk by Video call\\nBest time Weekday evenings\\nPhone 07700 900123\\nPostcode SW1A 1AA\\nFiles 2 files\\nCustomer Ada Received\\nPayment None taken\\nOpen the enquiry in admin`).test(t), t.slice(0, 500));
        check('then their space (line break kept) and every detail, the board link as typed', /Their space and what they have in mind\nA double-height hallway\.\nOak floor, white walls\.\nDetails\nName Ada Received\nEmail ada\.received@example\.test\nPhone 07700 900123\nHow to talk Video call\nBest time to reach them Weekday evenings\nBoard link pinterest\.com\/ada\/hallway\nPostcode SW1A 1AA\nCountry United Kingdom\nFiles\n/.test(t), t);
        check('the files listed (names and sizes), downloaded from the enquiry in admin, nothing attached', /Hallway\.jpg \(2\.0MB\)/.test(studio.HTML) && /Mood board\.pdf \(1\.0MB\)/.test(studio.HTML) && /Download from the enquiry in admin/.test(studio.HTML) && /\/admin\/design-request\//.test(studio.HTML) && (studio.Attachments || []).length === 0);
        check('the piece: Atelier, finishes to be chosen together', /The piece\nLumen Forma\nSize Atelier\nFinishes To be chosen together\nQuantity 1\n/.test(t), t.slice(-400));
        check('replies go to the customer', (studio.ReplyTo || []).some((r) => r.Address === EMAIL), JSON.stringify(studio.ReplyTo));
    }

    // By phone, three Estates; by email, a Monument, nothing optional.
    await piece('estate', 3);
    page = await enquire({ fullName: 'Ada Received', email: 'ada.received2@example.test', phone: '07700 900123', description: 'A long gallery.', postcode: 'JE2 3AB' }, 'phone');
    check('by phone, 3 Estates: "I\'ll be in touch by phone, to talk about your 3 Estate lamps."', page.text.includes("Thank you. I'll be in touch by phone, to talk about your 3 Estate lamps."), page.text);
    const phoneMail = await customerMail('ada.received2@example.test', latest('ada.received2@example.test'));
    const pt = text(phoneMail?.HTML || '');
    check('…its email: "about 3 Lumen Forma Estate lamps. I\'ll be in touch by phone…", no board, "None" for files, no best time', pt.includes("Thank you for your enquiry about 3 Lumen Forma Estate lamps. I'll be in touch by phone to talk through") && !/Your board/.test(pt) && /Photos and files\nNone\n/.test(pt) && !/Best time/.test(pt) && /How you'd like to talk\nPhone · 07700 900123\n/.test(pt), pt.slice(0, 800));
    const phoneStudio = await studioMail(latest('ada.received2@example.test'));
    check('…your notification: "New enquiry · Estate × 3 · Phone", the Jersey postcode with its country', phoneStudio?.Subject === 'New enquiry · Estate × 3 · Phone' && /Postcode JE2 3AB · Jersey\n/.test(text(phoneStudio?.HTML || '')), phoneStudio?.Subject);

    await piece('monument', 1);
    page = await enquire({ fullName: 'Ada Received', email: 'ada.received3@example.test', description: 'A stairwell.', postcode: 'SW1A 1AA' }, 'email');
    check('by email, a Monument: "I\'ll be in touch by email, to talk about your Monument lamp."', page.text.includes("Thank you. I'll be in touch by email, to talk about your Monument lamp."), page.text);
    const emailMail = await customerMail('ada.received3@example.test', latest('ada.received3@example.test'));
    check('…its email: "I\'ll be in touch by email", "How we\'ll talk: Email" (no phone)', text(emailMail?.HTML || '').includes("I'll be in touch by email to talk through") && /How you'd like to talk\nEmail\n/.test(text(emailMail?.HTML || '')));

    // The spam trap: thanked, nothing saved or sent.
    await piece('atelier', 1);
    const before = (await mailpit('to:ada.received4@example.test')).length;
    page = await enquire({ fullName: 'Ada Received', email: 'ada.received4@example.test', description: 'Spam.', postcode: 'SW1A 1AA', website: 'http://spam.example' }, 'email');
    await sleep(1500);
    check('the hidden field filled: the same thank-you page, nothing saved, no emails', page.path === '/begin-the-conversation/received' && page.text.startsWith('ENQUIRY RECEIVED') && !latest('ada.received4@example.test') && (await mailpit('to:ada.received4@example.test')).length === before);
    summary();
} finally {
    resetLimit();
    execSync(`rm -rf "${dir}"`);
    b.close();
}
