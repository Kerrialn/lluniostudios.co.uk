// The "Page Not Found" page (wording pass 11.5, 15 October 2026; wording
// approved 16 October 2026): the 404 status, the heading styled as every page's, the line, links
// to Lighting Designs and the homepage, "noindex", and no sideways scroll on a
// phone. Shown through Symfony's error preview (/_error/404), as the local
// site runs in debug mode.
//   node tests/browser/not-found.mjs
import { open, sleep, checker, BASE_URL } from './lib.mjs';

const { check, summary } = checker();
for (const [W, H] of [[1440, 900], [390, 844]]) {
    const b = await open(W, H, 'not-found' + W, { mobile: W < 900 });
    try {
        await b.send('Page.navigate', { url: BASE_URL + '/_error/404' });
        await sleep(2500);
        if (W === 1440) {
            const status = await b.ev(`fetch('/_error/404').then(r => r.status)`);
            check('the page answers with status 404', status === 404, String(status));
        }
        const page = await b.ev(`(() => {
            const main = document.querySelector('[data-not-found]');
            const links = [...main.querySelectorAll('a')].map(a => [a.textContent.trim(), a.getAttribute('href')]);
            return { title: document.title, h1: main.querySelector('h1')?.textContent.trim(), line: main.querySelector('p')?.textContent.trim(), links,
                robots: document.querySelector('meta[name=robots]')?.content, sideways: document.documentElement.scrollWidth > innerWidth };
        })()`);
        check(`${W}: "Page Not Found", its line and tab title`, page.h1 === 'Page Not Found' && page.line === "This page doesn't exist, or it has moved. The lamps are still here." && page.title === 'Page Not Found | Llunio Studios', JSON.stringify([page.h1, page.line, page.title]));
        check(`${W}: links to Lighting Designs and the homepage`, JSON.stringify(page.links) === JSON.stringify([['Explore our lighting designs', '/lighting-designs'], ['Back to the homepage', '/']]), JSON.stringify(page.links));
        check(`${W}: not indexed, no sideways scroll`, page.robots === 'noindex, nofollow' && !page.sideways, JSON.stringify([page.robots, page.sideways]));
    } finally {
        b.close();
    }
}
summary();
