// Step 7c.1: "Begin the Conversation", the enquiry page for a larger piece. Only
// reached with a piece from the larger-piece step; its size, quantity and
// "Finishes: To be chosen together" with "Change" back to the step; the fields,
// styled as the Bespoke Commissions forms (no "Needed by" or gift: removed
// 13 October 2026); every message (required fields, the phone for a phone or
// video call, the postcode, the board link, the file rules); what is typed
// remembered; and
// a complete enquiry accepted and saved (what is saved, and admin:
// enquiry-saving.mjs). The wording is DRAFT until the wording pass (11.5).
//   node tests/browser/enquiry-page.mjs 1440 900
//   node tests/browser/enquiry-page.mjs 390 844
import { execSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { open, sleep, checker, sql, out, BASE_URL, PROJECT_DIR } from './lib.mjs';

const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const { check, summary } = checker();
const b = await open(W, H, `enquiry${W}`, { mobile: W < 900 });
const { send, ev } = b;
try {
    const go = async (url, wait = 2500) => { await send('Page.navigate', { url }); await sleep(wait); await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`); };
    const errors = () => ev(`[...document.querySelectorAll('form[name=enquiry_form] [role=alert]')].map(e => e.textContent.trim()).filter(Boolean)`);
    const set = (name, value) => ev(`(() => { const e = document.querySelector('[name="enquiry_form[${name}]"]'); e.value = ${JSON.stringify(value)}; e.dispatchEvent(new Event('input', { bubbles: true })); })()`);
    const tick = (selector) => ev(`(() => { const e = document.querySelector(${JSON.stringify(selector)}); if (!e.checked) e.click(); })()`);
    const submit = async () => { await ev(`document.querySelector('form[name=enquiry_form]').requestSubmit()`); await sleep(2500); await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`); };
    const requests = () => Number(sql('select count(*) from design_request').match(/\d+/g).pop());

    await b.clearStorage(); await send('Network.enable'); await send('Network.clearBrowserCookies');
    // The limit of 5 enquiries an hour (7c.2).
    execSync('docker compose exec -T php php bin/console cache:pool:clear cache.rate_limiter 2>/dev/null', { cwd: PROJECT_DIR });
    await go(BASE_URL + '/begin-the-conversation');
    check('without a piece from the larger-piece step: back to Lighting Designs', (await ev('location.pathname')) === '/lighting-designs');

    // Through the step: Estate ×2.
    const toPage = async () => {
        await go(BASE_URL + '/lighting-designs');
        await ev(`[...document.querySelectorAll('.ll-story-stage')[6].querySelectorAll('button')].find(x => x.textContent.trim() === 'Discuss a Larger Piece').click()`); await sleep(2000);
        await ev(`document.querySelector('[data-configurator-target=largerOption][data-value=lumen-forma-estate]').click()`);
        for (let i = 0; i < 10; i++) await ev(`document.querySelector('[data-action="configurator#largerFewer"]').click()`);
        await ev(`document.querySelector('[data-action="configurator#largerMore"]').click()`);
        await ev(`document.querySelector('[data-configurator-target=largerConfiguration]').closest('form').querySelector('button').click()`); await sleep(2500);
        await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`);
    };
    await toPage();
    let p = await ev(`({ path: location.pathname, title: document.title, h1: document.querySelector('h1').textContent.trim(), intro: document.querySelector('.ll-page-heading + div p').textContent.trim(),
        rows: [...document.querySelectorAll('[aria-label="Your piece"] .flex.justify-between')].slice(1).map(r => [...r.children].map(c => c.textContent.trim()).join('=')),
        labels: [...document.querySelectorAll('form[name=enquiry_form] label, form[name=enquiry_form] [id$=_label], form[name=enquiry_form] legend, form[name=enquiry_form] span[class^="text-[14px]"]')].filter(l => l.getClientRects().length && !l.closest('[aria-hidden=true]')).map(l => l.innerText.replace(/\\s+/g, ' ').trim()),
        sections: [...document.querySelectorAll('form[name=enquiry_form] > div.ll-section-heading')].map(e => e.textContent.trim()),
        talk: [...document.querySelectorAll('[name="enquiry_form[talk]"]')].map(i => i.closest('label').textContent.trim()).join(),
        button: document.querySelector('form[name=enquiry_form] button[type=submit]').textContent.trim(),
        wide: document.documentElement.scrollWidth <= innerWidth })`);
    check('the page: "Begin the Conversation", its intro (DRAFT), no horizontal scrolling', p.path === '/begin-the-conversation' && p.title === 'Begin the Conversation | Llunio Studios' && p.h1 === 'Begin the Conversation'
        && p.intro === "Atelier, Estate and Monument lamps are made in conversation with me. Tell me a little about your space and I'll be in touch to talk it through. No payment is taken." && p.wide, JSON.stringify([p.h1, p.intro, p.wide]));
    check('at the top: Size Estate, Finishes To be chosen together, Quantity 2', JSON.stringify(p.rows) === JSON.stringify(['Size=Estate', 'Finishes=To be chosen together', 'Quantity=2']), JSON.stringify(p.rows));
    check('the sections: Contact, Getting in touch, Your space, Delivery', JSON.stringify(p.sections) === JSON.stringify(['Contact', 'Getting in touch', 'Your space', 'Delivery']), JSON.stringify(p.sections));
    check('the fields, in order (no "Needed by" or gift)', JSON.stringify(p.labels) === JSON.stringify([
        'Name *', 'Email *', 'Phone (optional)', 'How would you like to talk? *', 'EMAIL', 'PHONE', 'VIDEO CALL', 'Best time to reach you (optional)',
        'Tell me about your space and what you have in mind *', 'Photos of your space, inspiration or a mood board (optional)', 'Choose photos or files — PDF, DWG, JPG, PNG or HEIC, up to 50MB each, 100MB in total',
        'Pinterest, Instagram or Houzz board (optional)', 'Postcode *']) && !(await ev(`!!document.querySelector('[name*="neededBy"], [name*="gift"]')`)), JSON.stringify(p.labels));
    check('"How would you like to talk?": Email, Phone, Video call; the button "Send Enquiry"', p.talk === 'Email,Phone,Video call' && p.button === 'Send Enquiry', `${p.talk} | ${p.button}`);
    const help = await ev(`[...document.querySelectorAll('form[name=enquiry_form] span.text-ll-mute')].map(e => e.textContent.trim()).filter(t => t.length > 20)`);
    check('the hints: the space ("Where the lamp will stand…") and the postcode (DRAFT)', help.includes("Where the lamp will stand, roughly how high the ceiling is, and the colours or finishes you're drawn to.") && help.includes('Where the lamp is going, so we can plan delivery.'), JSON.stringify(help));

    // Styled as the Bespoke Commissions forms.
    const style = (path) => ev(`(() => { const css = (e) => getComputedStyle(e); const form = document.querySelector(${JSON.stringify(path)});
        const inputs = [...form.querySelectorAll('input[type=text], input[type=email], input[type=tel], textarea')].filter(e => e.getClientRects().length && !e.closest('[aria-hidden=true]') && e.tabIndex !== -1);
        const labels = [...form.querySelectorAll('label[for]')].filter(e => e.getClientRects().length && !e.closest('[aria-hidden=true]') && e.textContent.trim().length > 2 && !/Choose photos/.test(e.textContent));
        return { boxes: [...new Set(inputs.map(e => css(e).borderTopWidth + ' ' + css(e).borderTopStyle + ' ' + css(e).fontSize + ' ' + css(e).color))].join(' | '),
            heights: [...new Set(inputs.filter(e => e.tagName === 'INPUT').map(e => Math.round(e.getBoundingClientRect().height)))].join(),
            labels: [...new Set(labels.map(e => css(e).fontSize))].join(), button: (() => { const x = form.querySelector('button[type=submit]'); return css(x).fontSize + ' ' + css(x).letterSpacing + ' ' + css(x).backgroundColor; })() }; })()`);
    const ours = await style('form[name=enquiry_form]');
    await go(BASE_URL + '/bespoke-commissions?for=home', 3000);
    const theirs = await style('form[name=commission_home]');
    check('styled as the Bespoke Commissions forms (boxes, heights, labels, button)', JSON.stringify(ours) === JSON.stringify(theirs), `${JSON.stringify(ours)} vs ${JSON.stringify(theirs)}`);

    // "Change": back to the step, Estate ×2 kept.
    await toPage();
    await ev(`document.querySelector('[data-enquiry-change]').click()`); await sleep(3500);
    const back = await ev(`({ path: location.pathname, larger: document.querySelector('[data-controller~=configurator]').classList.contains('is-larger'), size: document.querySelector('[data-configurator-target=largerOption].is-selected')?.textContent.trim(), quantity: document.querySelector('[data-configurator-target=largerQuantity]').textContent })`);
    check('"Change": back on the larger-piece step, Estate and 2 kept', back.path === '/lighting-designs' && back.larger && back.size === 'Estate' && back.quantity === '2', JSON.stringify(back));

    // The messages.
    await toPage();
    const before = requests();
    await submit();
    check('sent empty: the required fields\' messages (DRAFT where new)', JSON.stringify(await errors()) === JSON.stringify(['Please enter your full name.', 'Please enter your email address.', "Please choose how you'd like to talk.", 'Please tell me a little about your space.', 'Please enter your postcode.']), JSON.stringify(await errors()));
    await set('fullName', 'Ada Enquiry'); await set('email', 'ada.enquiry@example.test'); await set('description', 'A tall hallway with a north-facing window.');
    await tick('[name="enquiry_form[talk]"][value=video]');
    check('choosing a video call: the phone becomes required ("*")', (await ev(`document.querySelector('label[for=enquiry_form_phone]').innerText.replace(/\\s+/g, ' ').trim()`)) === 'Phone *');
    await set('postcode', 'BH21'); await set('boardLink', 'not a link');
    await submit();
    check('a video call without a phone, a half postcode, a board link that isn\'t a web address: each refused', JSON.stringify(await errors()) === JSON.stringify(['Please enter your phone number for a phone or video call.', 'Please enter a web address, e.g. pinterest.com/your-board.', 'Please enter a full postcode, e.g. SW1A 1AA.']), JSON.stringify(await errors()));
    check('what was typed is kept, and the phone still shows "*"', (await ev(`document.querySelector('[name="enquiry_form[fullName]"]').value`)) === 'Ada Enquiry' && (await ev(`document.querySelector('label[for=enquiry_form_phone]').innerText.replace(/\\s+/g, ' ').trim()`)) === 'Phone *');
    await set('postcode', '75001');
    await submit();
    check('a postcode outside the UK is refused', (await errors()).includes('Please enter a full postcode, e.g. SW1A 1AA.'));

    // Files: the Bespoke Commissions rules (checked as they are chosen; the server's check is commissions-saving.mjs's and enquiry-saving.mjs's).
    const txt = out('enquiry-notes.txt'); writeFileSync(txt, 'Not a photo.');
    const { root } = (await send('DOM.getDocument')).result;
    const { nodeId } = (await send('DOM.querySelector', { nodeId: root.nodeId, selector: '[name="enquiry_form[files][]"]' })).result;
    await send('DOM.setFileInputFiles', { nodeId, files: [txt] }); await sleep(300);
    const fileError = await ev(`(() => { const e = document.querySelector('[data-file-chooser-target=error]'); return e.hidden ? null : e.textContent.trim(); })()`);
    check('a file of another type: refused as it is chosen, with the Bespoke Commissions message (the server checks too)', fileError === '"enquiry-notes.txt" can\'t be sent: please choose PDF, DWG, JPG, PNG or HEIC files.', fileError);

    // Remembered for the tab.
    await toPage();
    check('what was typed is remembered on coming back', (await ev(`document.querySelector('[name="enquiry_form[fullName]"]').value`)) === 'Ada Enquiry' && (await ev(`document.querySelector('[name="enquiry_form[description]"]').value`)) === 'A tall hallway with a north-facing window.');

    // Complete: accepted and saved (7c.2), then "Enquiry Received" (7c.3; enquiry-received.mjs checks it and the emails).
    await set('phone', '07700 900123'); await set('postcode', 'je2 3ab'); await set('boardLink', 'pinterest.com/ada/hallway');
    await tick('[name="enquiry_form[talk]"][value=video]');
    await submit();
    let done = await ev(`({ path: location.pathname, saved: document.querySelector('main h1')?.textContent.trim() === 'Enquiry Received' })`);
    check('a Jersey postcode, a board link and a video call: accepted and saved, then "Enquiry Received"', done.path === '/begin-the-conversation/received' && done.saved && requests() === before + 1, JSON.stringify(done));
    await toPage();
    await set('fullName', 'Ada Enquiry'); await set('email', 'ada.enquiry@example.test'); await set('description', 'A tall hallway with a north-facing window.');
    await tick('[name="enquiry_form[talk]"][value=email]'); await set('postcode', 'SW1A 1AA');
    await submit();
    done = await ev(`({ path: location.pathname, saved: document.querySelector('main h1')?.textContent.trim() === 'Enquiry Received' })`);
    check('an email enquiry with no phone: accepted and saved', done.path === '/begin-the-conversation/received' && done.saved && requests() === before + 2, JSON.stringify(done));
    summary();
} finally { b.close(); }
