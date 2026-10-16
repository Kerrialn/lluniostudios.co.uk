// Bespoke Commissions (Step 9.1): the links to the page, the hero (eight
// photos, the title visible on load, the reveal), the For Your Home / For
// Your Project tabs, both forms' required fields and messages, the phone
// becoming required when Phone is chosen, the file chooser's checks, and the
// hand pointer and hover feedback. (Saving: commissions-saving.mjs.) Takes a size: 1440 900 (default) or 390 844.
import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { open, sleep, checker, out, BASE_URL, PROJECT_DIR } from './lib.mjs';

const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const { check, summary } = checker();
const b = await open(W, H, 'commissions' + W, { mobile: W < 900 });
const { send, ev } = b;
try {
    const go = async (url, wait = 2200) => { await send('Page.navigate', { url }); await sleep(wait); };
    const reveal = async () => { await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 }); await sleep(1600); };
    const tab = (client) => ev(`document.querySelector('[data-commission-target=tab][data-client=${client}]').click()`);
    const state = () => ev(`({ revealed: document.querySelector('[data-controller~="hero-reveal"]').classList.contains('is-revealed'),
        pressed: [...document.querySelectorAll('[data-commission-target=tab]')].filter(t => t.getAttribute('aria-pressed') === 'true').map(t => t.dataset.client),
        shown: [...document.querySelectorAll('[data-commission-target=panel]')].filter(p => !p.hidden).map(p => p.dataset.client),
        errors: [...document.querySelectorAll('[data-commission-target=panel]:not([hidden]) [role=alert]')].filter(e => !e.hidden).map(e => e.textContent.trim()) })`);
    const submit = async (client) => { await ev(`document.querySelector('[data-commission-target=panel][data-client=${client}] form').requestSubmit()`); await sleep(2200); };
    await send('Network.enable'); await send('Network.clearBrowserCookies');

    // Links to the page.
    await go(BASE_URL + '/');
    check('homepage "Commission Your vision" goes to Bespoke Commissions', (await ev(`[...document.querySelectorAll('a')].find(a => /Commission Your vision/i.test(a.textContent))?.getAttribute('href')`)) === '/bespoke-commissions');
    await go(BASE_URL + '/bespoke-commissions', 3000);
    const nav = await ev(`[...document.querySelectorAll('#navbar a, #mobile-menu a')].filter(a => /Bespoke Commissions/i.test(a.textContent)).map(a => [a.getAttribute('href'), a.getAttribute('aria-current')])`);
    check('nav link goes to the page and is marked as the current page', nav.length > 0 && nav.every(([h]) => h === '/bespoke-commissions') && nav.some(([, c]) => c === 'page'), JSON.stringify(nav));

    // Hero.
    const hero = await ev(`({ slides: document.querySelectorAll('.ll-reveal-hero [data-slideshow-target=slide] img').length,
        first: (() => { const i = document.querySelector('.ll-reveal-hero img'); return i.complete && i.naturalWidth > 0; })(),
        lines: [...document.querySelectorAll('h1 span')].map(s => [s.textContent, getComputedStyle(s).opacity, Math.round(s.getBoundingClientRect().right)]),
        cue: getComputedStyle(document.querySelector('.ll-reveal-cue')).opacity, body: getComputedStyle(document.querySelector('.ll-reveal-body')).opacity,
        title: getComputedStyle(document.querySelector('h1').parentElement).color })`);
    check('eight hero photos; the first has loaded', hero.slides === 8 && hero.first, JSON.stringify(hero.slides));
    check('the title\'s three lines are visible on load (no fade-in)', JSON.stringify(hero.lines.map(([t, o]) => [t, o])) === JSON.stringify([['Your design', '1'], ['in our signature brutalist style,', '1'], ['custom‑built for you.', '1']]), JSON.stringify(hero.lines));
    check('every title line fits on the screen', hero.lines.every(([, , right]) => right <= W), JSON.stringify(hero.lines.map((l) => l[2])));
    check('title in cream (all eight photos are dark)', hero.title === 'rgb(236, 227, 211)', hero.title);
    check('"Scroll to begin" shows and the forms are hidden until the first interaction', hero.cue === '1' && hero.body === '0', JSON.stringify([hero.cue, hero.body]));
    await sleep(5600);
    check('the photos cross-fade', (await ev(`[...document.querySelectorAll('.ll-reveal-hero [data-slideshow-target=slide]')].findIndex(s => s.classList.contains('opacity-100'))`)) === 1);
    await reveal();
    let s = await state();
    check('revealed: the tabs show, neither chosen, no form yet', s.revealed && s.pressed.length === 0 && s.shown.length === 0, JSON.stringify(s));
    check('no horizontal scrolling', (await ev('document.documentElement.scrollWidth')) <= W);

    // Tabs.
    await tab('home'); await sleep(800); s = await state();
    check('For Your Home shows the Home form', JSON.stringify(s.pressed) === '["home"]' && JSON.stringify(s.shown) === '["home"]', JSON.stringify(s));
    const homeLabels = await ev(`[...document.querySelectorAll('[data-client=home] form > div > div')].map(f => f.children[0].textContent.replace(/\\s+/g, ' ').trim())`);
    check('Home fields in the prototype\'s order, required ones marked *', JSON.stringify(homeLabels) === JSON.stringify(['Name *', 'Email *', 'Phone (optional)*', 'How would you like to talk? *', 'Tell me about your idea *', 'Share a photo of the space or anything that inspired you (optional)']), JSON.stringify(homeLabels));
    check('button "Send Enquiry"', (await ev(`document.querySelector('[data-client=home] button[type=submit]').textContent.trim()`)) === 'Send Enquiry');
    await tab('project'); await sleep(800); s = await state();
    check('For Your Project shows the Project form instead', JSON.stringify(s.pressed) === '["project"]' && JSON.stringify(s.shown) === '["project"]', JSON.stringify(s));
    const projectLabels = await ev(`[...document.querySelectorAll('[data-client=project] form > div > div')].map(f => f.children[0].textContent.replace(/\\s+/g, ' ').trim())`);
    check('Project fields in the prototype\'s order', JSON.stringify(projectLabels) === JSON.stringify(['Company *', 'Contact Name *', 'Email *', 'Phone (optional)', 'Project Type *', 'Project Location & Installation Schedule *', 'The Project (optional)', 'Integration Requirements (optional)', 'Drawings and Files (optional)']), JSON.stringify(projectLabels));
    const types = await ev(`(() => { const s = document.querySelector('[data-client=project] select'); return [...s.options].map(o => o.text).concat('=' + s.selectedOptions[0].text); })()`);
    check('Project Type: the four types, starting on "Please choose"', JSON.stringify(types) === JSON.stringify(['Please choose', 'High-End Residential', 'Commercial', 'Hospitality', 'Retail', '=Please choose']), JSON.stringify(types));
    check('button "Send Enquiry"', (await ev(`document.querySelector('[data-client=project] button[type=submit]').textContent.trim()`)) === 'Send Enquiry');

    // Phone becomes required when Phone is chosen.
    await tab('home'); await sleep(700);
    const phoneLabel = () => ev(`document.querySelector('label[for=commission_home_phone]').innerText.replace(/\\s+/g, ' ').trim()`);
    check('phone starts optional', (await phoneLabel()) === 'Phone (optional)', await phoneLabel());
    await ev(`document.querySelector('input[name="commission_home[contactMethod]"][value=phone]').click()`); await sleep(200);
    check('choosing Phone marks it required', (await phoneLabel()) === 'Phone *', await phoneLabel());
    const chosen = await ev(`[...document.querySelectorAll('[role=radiogroup] label')].map(l => getComputedStyle(l).backgroundColor)`);
    check('the chosen method is filled cream, the other outlined', chosen[1] === 'rgb(236, 227, 211)' && chosen[0] === 'rgba(0, 0, 0, 0)', JSON.stringify(chosen));
    await ev(`document.querySelector('input[name="commission_home[contactMethod]"][value=email]').click()`); await sleep(200);
    check('choosing Email makes it optional again', (await phoneLabel()) === 'Phone (optional)');

    // Required fields: Home (nothing chosen).
    await ev(`document.querySelectorAll('input[name="commission_home[contactMethod]"]').forEach(i => { i.checked = false; })`);
    await submit('home'); s = await state();
    check('Home sent empty: reopens on the Home form with a message for each required field', s.revealed && JSON.stringify(s.shown) === '["home"]' && JSON.stringify(s.errors) === JSON.stringify(['Please enter your name.', 'Please enter your email address.', "Please choose how you'd like to talk.", 'Please tell me about your idea.']), JSON.stringify(s));
    await ev(`(() => { const f = document.querySelector('[data-client=home] form'); f.querySelector('[name="commission_home[name]"]').value = 'Ada Test'; f.querySelector('[name="commission_home[email]"]').value = 'not-an-email'; f.querySelector('[value=phone]').click(); f.querySelector('textarea').value = 'A lamp for the hall.'; })()`);
    await submit('home'); s = await state();
    check('Phone chosen with no number: asks for it; a bad email is refused', JSON.stringify(s.errors) === JSON.stringify(['Please enter a valid email address.', 'Please enter your phone number so I can call you.']), JSON.stringify(s.errors));
    check('what was typed is kept, and Phone stays chosen and required', (await ev(`document.querySelector('[name="commission_home[name]"]').value`)) === 'Ada Test' && (await phoneLabel()) === 'Phone *');

    // Required fields: Project.
    await tab('project'); await sleep(700);
    await submit('project'); s = await state();
    check('Project sent empty: reopens on the Project form with a message for each required field', JSON.stringify(s.shown) === '["project"]' && JSON.stringify(s.errors) === JSON.stringify(['Please enter your company name.', 'Please enter a contact name.', 'Please enter your email address.', 'Please choose a project type.', 'Please enter the project location and installation schedule.']), JSON.stringify(s));

    // Opening on a form directly.
    await go(BASE_URL + '/bespoke-commissions?for=project'); s = await state();
    check('?for=project opens revealed on the Project form', s.revealed && JSON.stringify(s.shown) === '["project"]');

    // The file chooser (checked in the page; the server's checks come in 9.2).
    const dir = out('commission-files'); mkdirSync(dir, { recursive: true });
    const make = (name, mb) => { execSync(`rm -f "${dir}/${name}" && truncate -s ${mb}M "${dir}/${name}"`); return `${dir}/${name}`; };
    const choose = async (client, files) => {
        const input = (await send('Runtime.evaluate', { expression: `document.querySelector('[data-client=${client}] input[type=file]')` })).result.result;
        await send('DOM.setFileInputFiles', { files, objectId: input.objectId });
        await sleep(400);
        return ev(`(() => { const f = document.querySelector('[data-client=${client}] [data-controller=file-chooser]'); const e = f.querySelector('[data-file-chooser-target=error]'); return { listed: [...f.querySelectorAll('li')].map(l => l.textContent), error: e.hidden ? '' : e.textContent, kept: f.querySelector('input').files.length }; })()`);
    };
    let f = await choose('project', [make('plan.pdf', 2), make('model.dwg', 3), make('mood.HEIC', 1)]);
    check('several files can be chosen and are listed', JSON.stringify(f.listed) === JSON.stringify(['plan.pdf (2.0MB)', 'model.dwg (3.0MB)', 'mood.HEIC (1.0MB)']) && f.error === '' && f.kept === 3, JSON.stringify(f));
    f = await choose('project', [make('notes.txt', 1)]);
    check('a file of another type is refused', f.kept === 0 && f.error === '"notes.txt" can\'t be sent: please choose PDF, DWG, JPG, PNG or HEIC files.', JSON.stringify(f));
    f = await choose('project', [make('huge.pdf', 51)]);
    check('a file over 50MB is refused', f.kept === 0 && f.error === '"huge.pdf" is over 50MB: each file can be up to 50MB.', JSON.stringify(f));
    f = await choose('project', [make('a.jpg', 40), make('b.png', 40), make('c.pdf', 40)]);
    check('more than 100MB in total is refused', f.kept === 0 && f.error === 'These files come to more than 100MB in total. Please choose fewer or smaller files.', JSON.stringify(f));
    await tab('home'); await sleep(700);
    f = await choose('home', [make('space.jpg', 2), make('sketch.pdf', 1)]);
    check('the Home form takes several files too', f.kept === 2 && f.listed.length === 2, JSON.stringify(f));
    const accept = await ev(`[...document.querySelectorAll('input[type=file]')].map(i => i.accept + (i.multiple ? ' multiple' : ''))`);
    const hidden = await ev(`[...document.querySelectorAll('input[type=file]')].map(i => { const q = i.getBoundingClientRect(); return q.width <= 1 && q.height <= 1; })`);
    check('the file inputs themselves are hidden behind the boxes', hidden.every(Boolean), JSON.stringify(hidden));
    check('both choosers offer PDF, DWG, JPG, PNG and HEIC, several at once', accept.every((a) => a === '.pdf,.dwg,.jpg,.jpeg,.png,.heic multiple'), JSON.stringify(accept));
    execSync(`rm -rf "${dir}"`);

    // Hand pointer and hover feedback.
    if (W >= 900) {
        const hover = async (sel, prop) => {
            const r = await ev(`(() => { const e = document.querySelector(${JSON.stringify(sel)}); e.scrollIntoView({ block: 'center' }); const q = e.getBoundingClientRect(); window.__h = e; return [q.left + q.width / 2, q.top + q.height / 2]; })()`);
            await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 2, y: 2 }); await sleep(450);
            const before = await ev(`getComputedStyle(window.__h).${prop}`);
            await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: r[0], y: r[1] }); await sleep(450);
            return { before, after: await ev(`getComputedStyle(window.__h).${prop}`), cursor: await ev(`getComputedStyle(window.__h).cursor`) };
        };
        for (const [label, sel, prop] of [['tab', '[data-commission-target=tab][data-client=project]', 'opacity'], ['contact choice', '[data-client=home] [role=radiogroup] label', 'opacity'], ['file box', '[data-client=home] label[for$=_files]', 'color'], ['send button', '[data-client=home] button[type=submit]', 'opacity']]) {
            const h = await hover(sel, prop);
            check(`${label}: hand pointer and a hover change`, h.cursor === 'pointer' && h.before !== h.after, JSON.stringify(h));
        }
    }

    // A complete enquiry goes through (saving is checked in commissions-saving.mjs).
    execSync('docker compose exec -T php php bin/console cache:pool:clear cache.rate_limiter 2>/dev/null', { cwd: PROJECT_DIR });
    await go(BASE_URL + '/bespoke-commissions?for=home');
    await ev(`(() => { const f = document.querySelector('[data-client=home] form'); f.querySelector('[name="commission_home[name]"]').value = 'Ada Test'; f.querySelector('[name="commission_home[email]"]').value = 'ada.test@example.test'; f.querySelector('[value=email]').click(); f.querySelector('textarea').value = 'A lamp for the hall.'; })()`);
    await submit('home');
    check('a complete Home enquiry is accepted', (await ev('location.pathname')) === '/bespoke-commissions/received');
    summary();
} finally { b.close(); }
