// Every page shows its own footer however it is reached (Swup page
// transitions swap the footer with the page): the homepage its icons pinned
// over the photo, Lighting Designs none, every other page the normal footer.
// Checked on a direct visit to each page, then along a route that goes from
// every page to every other page exactly once (Swup link clicks), with the
// back and forward buttons along the way. Takes a size: 1440 900 (default)
// or 390 844.
import { open, sleep, checker, BASE_URL } from './lib.mjs';

const [W, H] = [Number(process.argv[2] || 1440), Number(process.argv[3] || 900)];
const { check, summary } = checker();
const b = await open(W, H, 'page-footers' + W, { mobile: W < 900 });
const { send, ev } = b;

const PAGES = ['/', '/lighting-designs', '/bespoke-commissions', '/workshop', '/contact', '/terms-and-conditions', '/basket'];
const expected = (path) => (path === '/' ? 'pinned' : path === '/lighting-designs' ? 'none' : 'normal');

// A route through every ordered pair of pages (each page to each other page
// once): an Eulerian circuit of the complete directed graph.
function route() {
    const left = new Map(PAGES.map((p) => [p, PAGES.filter((q) => q !== p)]));
    const stack = [PAGES[0]];
    const circuit = [];
    while (stack.length) {
        const here = stack[stack.length - 1];
        const next = left.get(here);
        if (next.length) stack.push(next.shift());
        else circuit.push(stack.pop());
    }
    return circuit.reverse();
}

try {
    const footer = () => ev(`(() => {
        const wrappers = document.querySelectorAll('#footer');
        const footers = [...document.querySelectorAll('footer')];
        const f = footers[0];
        const kind = !f ? 'none' : f.classList.contains('ll-footer-pinned') ? 'pinned' : 'normal';
        const shown = !!f && f.getBoundingClientRect().height > 0 && getComputedStyle(f).visibility !== 'hidden';
        return { path: location.pathname, kind, wrappers: wrappers.length, footers: footers.length, shown, icons: f ? f.querySelectorAll('a[aria-label]').length : 0 };
    })()`);
    const verify = async (label) => {
        const f = await footer();
        const want = expected(f.path);
        const ok = f.wrappers === 1 && f.footers === (want === 'none' ? 0 : 1) && f.kind === want && (want === 'none' || (f.shown && f.icons === 4));
        check(`${label}: ${f.path} shows ${want === 'none' ? 'no footer' : `the ${want} footer`}`, ok, JSON.stringify(f));
        return f.path;
    };
    // A click on an ordinary link, as Swup handles it (no page reload).
    const follow = async (path) => {
        await ev(`(() => { const a = document.createElement('a'); a.href = ${JSON.stringify(path)}; a.textContent = 'go'; document.querySelector('#main').appendChild(a); window.__loaded = false; a.click(); })()`);
        await sleep(2300);
        return ev('location.pathname');
    };
    await send('Network.enable'); await send('Network.clearBrowserCookies');

    for (const path of PAGES) {
        await send('Page.navigate', { url: BASE_URL + path }); await sleep(2200);
        await verify('direct visit');
    }

    const steps = route();
    await send('Page.navigate', { url: BASE_URL + steps[0] }); await sleep(2200);
    const reloads = await ev('performance.getEntriesByType("navigation").length');
    await ev('window.__sameDocument = true');
    for (let i = 1; i < steps.length; i++) {
        const from = steps[i - 1];
        const to = steps[i];
        const at = await follow(to);
        await verify(`${from} → ${to}`);
        if (at !== to) continue;
        if (i % 3 === 0) {
            await ev('history.back()'); await sleep(2300);
            await verify(`back from ${to}`);
            await ev('history.forward()'); await sleep(2300);
            await verify(`forward to ${to}`);
        }
    }
    check('the whole route ran as Swup page transitions (no full reloads)', (await ev('window.__sameDocument === true')) && (await ev('performance.getEntriesByType("navigation").length')) === reloads);
    // The icons in order, Facebook included (9 October 2026), and the Contact page's links.
    await ev(`location.href = '/contact'`); await sleep(2500);
    const icons = await ev(`[...document.querySelectorAll('#footer footer a[aria-label]')].map(a => a.getAttribute('aria-label'))`);
    const links = await ev(`[...document.querySelectorAll('main address a')].map(a => a.textContent.trim()).filter(t => /^(Facebook|Instagram|YouTube)$/.test(t))`);
    const facebook = await ev(`document.querySelector('[data-footer-facebook]')?.href === document.querySelector('[data-contact-facebook]')?.href && /facebook\\.com/.test(document.querySelector('[data-footer-facebook]')?.href)`);
    check('footer icons: Contact, Facebook, Instagram, YouTube; the Contact page lists Facebook, Instagram, YouTube (Facebook\'s link is the one setting)', JSON.stringify(icons) === JSON.stringify(['Contact', 'Facebook', 'Instagram', 'YouTube']) && JSON.stringify(links) === JSON.stringify(['Facebook', 'Instagram', 'YouTube']) && facebook, JSON.stringify({ icons, links, facebook }));
    summary();
} finally { b.close(); }
