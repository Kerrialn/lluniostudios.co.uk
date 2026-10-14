// Step 11c: the Terms & Conditions page (14 October 2026). Theo's final text,
// word for word (tests/browser/fixtures/terms-2026-10-14.txt, as he gave it):
// every line in order, the 13 numbered sections with their headings, "•" lines
// as bulleted lists and section 9's four points as a numbered list; "Last
// updated: 14 October 2026" just under the heading; the page heading as every
// page's; the Basket, Card Payment and Contact pages link to it.
//   node tests/browser/terms.mjs
import { readFileSync } from 'node:fs';
import { open, sleep, checker, BASE_URL } from './lib.mjs';

const { check, summary } = checker();
const SOURCE = readFileSync(new URL('./fixtures/terms-2026-10-14.txt', import.meta.url), 'utf8').trimEnd().split('\n');
const b = await open(1440, 900, 'terms', { mobile: false });
const { send, ev } = b;
try {
    const go = async (path, wait = 2500) => { await send('Page.navigate', { url: BASE_URL + path }); await sleep(wait); };
    await b.clearStorage(); await send('Network.enable'); await send('Network.clearBrowserCookies');
    await go('/terms-and-conditions');
    const page = await ev(`(() => {
        const main = document.querySelector('main');
        const blocks = [...main.querySelectorAll('h1, h2, p, li')].map(e => ({ tag: e.tagName, list: e.parentElement.tagName, text: e.textContent.replace(/\\s+/g, ' ').trim() }));
        const h1 = main.querySelector('h1'); const updated = main.querySelector('[data-terms-updated]');
        return { blocks, h1: h1.textContent.trim(), updatedBelow: !!updated && updated.getBoundingClientRect().top > h1.getBoundingClientRect().bottom && updated.textContent.trim() === 'Last updated: 14 October 2026',
            sections: [...main.querySelectorAll('section')].length, firstAfterHeading: main.querySelector('.ll-page-heading').nextElementSibling?.querySelector('p')?.textContent.trim() };
    })()`);
    check('the heading "Terms & Conditions", then "Last updated: 14 October 2026" under it', page.h1 === 'Terms & Conditions' && page.updatedBelow && page.firstAfterHeading === 'Last updated: 14 October 2026', JSON.stringify([page.h1, page.updatedBelow, page.firstAfterHeading]));
    // The page's text in order, with list markers as the source writes them.
    const shown = page.blocks.filter((x) => x.tag !== 'H1' && x.text !== 'Last updated: 14 October 2026');
    let olIndex = 0;
    const asSource = shown.map((x) => {
        if (x.tag === 'LI' && x.list === 'UL') return '• ' + x.text;
        if (x.tag === 'LI' && x.list === 'OL') return `${++olIndex}. ${x.text}`;
        return x.text;
    });
    const mismatch = SOURCE.findIndex((line, i) => line !== asSource[i]);
    check(`every line of the text, word for word and in order (${SOURCE.length} lines)`, mismatch === -1 && asSource.length === SOURCE.length, mismatch === -1 ? `${asSource.length} shown` : `line ${mismatch + 1}: expected ${JSON.stringify(SOURCE[mismatch])}, shown ${JSON.stringify(asSource[mismatch])}`);
    check('13 numbered sections, each with its heading', page.sections === 13 && shown.filter((x) => x.tag === 'H2').map((x) => x.text.split('.')[0]).join(',') === '1,2,3,4,5,6,7,8,9,10,11,12,13', String(page.sections));
    check('section 9\'s four points as a numbered list', shown.filter((x) => x.list === 'OL').length === 4);
    const heading = await ev(`(() => { const h = document.querySelector('main h1'); return getComputedStyle(h).fontSize + ' ' + Math.round(h.getBoundingClientRect().left); })()`);
    await go('/contact');
    const contactHeading = await ev(`(() => { const h = document.querySelector('main h1'); return getComputedStyle(h).fontSize + ' ' + Math.round(h.getBoundingClientRect().left); })()`);
    check('the page heading as every page\'s (Contact\'s)', heading === contactHeading, `${heading} vs ${contactHeading}`);
    const link = () => ev(`[...document.querySelectorAll('main a')].some(a => a.getAttribute('href') === '/terms-and-conditions')`);
    check('Contact links to the Terms & Conditions', await link());
    // A Gallery lamp in the basket, so the Basket and Card Payment show their links.
    await go('/lighting-designs');
    await ev(`document.querySelector('form[action$="/lighting-designs/add"]').removeAttribute('data-action')`); await sleep(100);
    await ev(`(() => { const f = document.querySelector('form[action$="/lighting-designs/add"]'); f.querySelector('[name=configuration]').value = ${JSON.stringify(JSON.stringify({ size: 'lumen-forma-gallery', quantity: 1, mode: 'all', lamps: [{ 'The Pigment': 'Grey', 'Cable Colour': 'Raw Jute', 'Footing Material': 'Polished Copper', 'Bulb Style': 'Smoked Glass LED' }] }))}; f.requestSubmit(); })()`); await sleep(2500);
    await go('/basket');
    check('the Basket links to the Terms & Conditions', await link());
    await go('/checkout');
    check('Card Payment links to the Terms & Conditions', await link());
    summary();
} finally { b.close(); }
