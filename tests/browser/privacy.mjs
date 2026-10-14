// Step 11d: the Privacy Policy page (14 October 2026). Theo's final text, word
// for word (tests/browser/fixtures/privacy-2026-10-14.txt, as he gave it, its
// tables written one cell per line): every line in order, the three tables kept
// as tables (columns and rows), "•" lines as bulleted lists, "Last updated: 14
// October 2026" under the heading (styled as every page's, as the Terms); the
// Contact page links to it beside the Terms & Conditions; it is in the
// sitemap; and the line "We'll only use your details for your order or
// enquiry. See our Privacy Policy." (linking to it) sits by the submit button on
// Card Payment, Reserve Your Design, Begin the Conversation, both Bespoke
// Commissions forms and the sign-in page (the quote page: quote-pay.mjs).
//   node tests/browser/privacy.mjs
import { readFileSync } from 'node:fs';
import { open, sleep, checker, BASE_URL } from './lib.mjs';

const { check, summary } = checker();
const SOURCE = readFileSync(new URL('./fixtures/privacy-2026-10-14.txt', import.meta.url), 'utf8').trimEnd().split('\n');
const LINE = "We'll only use your details for your order or enquiry. See our Privacy Policy.";
const b = await open(1440, 900, 'privacy', { mobile: false });
const { send, ev } = b;
try {
    const go = async (path, wait = 2500) => { await send('Page.navigate', { url: BASE_URL + path }); await sleep(wait); };
    await b.clearStorage(); await send('Network.enable'); await send('Network.clearBrowserCookies');
    await go('/privacy-policy');
    const page = await ev(`(() => {
        const main = document.querySelector('main');
        // Text in reading order: headings, paragraphs, list items, and each table cell.
        const parts = [...main.querySelectorAll('h1, h2, p, li, th, td')].map(e => ({ tag: e.tagName, list: e.parentElement.tagName, text: e.textContent.replace(/\\s+/g, ' ').trim() }));
        const tables = [...main.querySelectorAll('table')].map(t => ({ cols: t.querySelectorAll('thead th').length, rows: t.querySelectorAll('tbody tr').length }));
        const h1 = main.querySelector('h1'); const updated = main.querySelector('[data-privacy-updated]');
        return { parts, tables, h1: h1.textContent.trim(), updatedBelow: !!updated && updated.getBoundingClientRect().top > h1.getBoundingClientRect().bottom && updated.textContent.trim() === 'Last updated: 14 October 2026',
            firstAfterHeading: main.querySelector('.ll-page-heading').nextElementSibling?.querySelector('p')?.textContent.trim(), title: document.title };
    })()`);
    check('the heading "Privacy Policy", then "Last updated: 14 October 2026" under it', page.h1 === 'Privacy Policy' && page.updatedBelow && page.firstAfterHeading === 'Last updated: 14 October 2026' && page.title === 'Privacy Policy | Llunio Studios', JSON.stringify([page.h1, page.updatedBelow, page.firstAfterHeading]));
    const shown = page.parts.filter((x) => x.tag !== 'H1' && x.text !== 'Last updated: 14 October 2026').map((x) => (x.tag === 'LI' ? '• ' + x.text : x.text));
    const mismatch = SOURCE.findIndex((line, i) => line !== shown[i]);
    check(`every line of the text, word for word and in order, table cells included (${SOURCE.length} lines)`, mismatch === -1 && shown.length === SOURCE.length, mismatch === -1 ? `${shown.length} shown` : `line ${mismatch + 1}: expected ${JSON.stringify(SOURCE[mismatch])}, shown ${JSON.stringify(shown[mismatch])}`);
    check('the three tables kept as tables: 4 columns × 9 rows, 3 × 6, 2 × 4', JSON.stringify(page.tables) === JSON.stringify([{ cols: 4, rows: 9 }, { cols: 3, rows: 6 }, { cols: 2, rows: 4 }]), JSON.stringify(page.tables));
    const heading = await ev(`(() => { const h = document.querySelector('main h1'); return getComputedStyle(h).fontSize + ' ' + Math.round(h.getBoundingClientRect().left); })()`);
    await go('/terms-and-conditions');
    const termsHeading = await ev(`(() => { const h = document.querySelector('main h1'); return getComputedStyle(h).fontSize + ' ' + Math.round(h.getBoundingClientRect().left); })()`);
    check('styled as the Terms & Conditions (the same heading)', heading === termsHeading, `${heading} vs ${termsHeading}`);

    await go('/contact');
    const contact = await ev(`(() => { const links = [...document.querySelectorAll('main a')]; const t = links.findIndex(a => a.getAttribute('href') === '/terms-and-conditions'); const p = links.findIndex(a => a.getAttribute('href') === '/privacy-policy'); return { t, p, text: links[p]?.textContent.trim() }; })()`);
    check('Contact links to the Privacy Policy, beside the Terms & Conditions', contact.p === contact.t + 1 && contact.text === 'Privacy Policy', JSON.stringify(contact));
    const sitemap = await ev(`fetch('/sitemap.xml').then(r => r.text())`);
    check('the Privacy Policy is in the sitemap', sitemap.includes(`<loc>${BASE_URL}/privacy-policy</loc>`));

    // The privacy line by each form's submit button.
    const line = (form) => ev(`(() => { const f = document.querySelector(${JSON.stringify(form)}); const l = f && f.querySelector('[data-privacy-line]'); const submit = f && [...f.querySelectorAll('button[type=submit]')].filter(x => x.getClientRects().length).pop(); if (!l || !submit) return null; return { text: l.textContent.replace(/\\s+/g, ' ').trim(), href: l.querySelector('a')?.getAttribute('href'), near: Math.abs(l.getBoundingClientRect().top - submit.getBoundingClientRect().bottom) < 120 }; })()`);
    const ok = (r) => r?.text === LINE && r.href === '/privacy-policy' && r.near;
    await go('/login');
    let r = await line('form'); check('sign-in: the line by "Send sign-in email"', ok(r), JSON.stringify(r));
    await go('/bespoke-commissions?for=home', 3000);
    r = await line('form[name=commission_home]'); check('Bespoke Commissions, For Your Home: the line by the button', ok(r), JSON.stringify(r));
    await go('/bespoke-commissions?for=project', 3000);
    r = await line('form[name=commission_project]'); check('Bespoke Commissions, For Your Project: the line by the button', ok(r), JSON.stringify(r));
    // A Custom Gallery (Reserve), then a larger piece (Begin the Conversation), then a basket lamp (Card Payment).
    const configure = async (config) => {
        await go('/lighting-designs');
        await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
        const json = JSON.stringify(JSON.stringify(config));
        await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${json}; f.requestSubmit(); })()`); await sleep(2500);
    };
    const lamp = { 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED' };
    await configure({ size: 'lumen-forma-gallery', quantity: 3, mode: 'all', lamps: [lamp] });
    r = await line('form[name=reserve_form]'); check('Reserve Your Design: the line by "Reserve Your Design"', ok(r), JSON.stringify(r));
    await configure({ size: 'lumen-forma-estate', quantity: 1, mode: 'all', lamps: [{}], finishes: 'together' });
    r = await line('form[name=enquiry_form]'); check('Begin the Conversation: the line by "Send Enquiry"', ok(r), JSON.stringify(r));
    await configure({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [lamp] });
    await go('/checkout', 3000);
    r = await ev(`(() => { const l = document.querySelector('main [data-privacy-line]'); return l && { text: l.textContent.replace(/\\s+/g, ' ').trim(), href: l.querySelector('a')?.getAttribute('href'), afterTerms: l.previousElementSibling?.getAttribute('href') === '/terms-and-conditions' }; })()`);
    check('Card Payment: the line under "Pay" and the Terms & Conditions link', r?.text === LINE && r.href === '/privacy-policy' && r.afterTerms, JSON.stringify(r));
    summary();
} finally { b.close(); }
