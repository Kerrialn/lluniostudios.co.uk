// Step 11b, search and link-preview groundwork (13 October 2026). For every
// public page: lang="en-GB", a unique title, and on the indexable pages a
// unique description (DRAFT wording; the hidden pages have none, removed
// 16 October 2026,
// "Llunio Studios" never shortened), Open Graph and Twitter previews with a
// real photo that exists, one h1 and no skipped heading levels, descriptive
// alt text on every image. The indexable pages (homepage, Lighting Designs,
// Bespoke Commissions, The Workshop, Contact, Terms & Conditions) have a
// canonical link, no "noindex", the LocalBusiness data and are in
// sitemap.xml; Lighting Designs has Product data for the five sizes with their
// prices ("from" for Atelier, Estate, Monument); every other page (basket,
// checkout, the forms, thank-you pages, sign-in, quotes, orders, admin) is
// "noindex" (meta and header). robots.txt blocks admin only (the other
// private pages rely on "noindex", decided 13 October 2026) and points to the
// sitemap.
//   node tests/browser/seo.mjs
import { execSync } from 'node:child_process';
import { open, sleep, checker, BASE_URL, PROJECT_DIR } from './lib.mjs';

const { check, summary } = checker();
const INDEXABLE = { '/': 'landing', '/lighting-designs': 'Lighting Designs', '/bespoke-commissions': 'Bespoke Commissions', '/workshop': 'The Workshop', '/contact': 'Contact', '/terms-and-conditions': 'Terms & Conditions', '/privacy-policy': 'Privacy Policy' };
const b = await open(1440, 900, 'seo', { mobile: false });
const { send, ev } = b;
const curl = (path) => execSync(`curl -sk -D - -o /dev/null "${BASE_URL}${path}"`, { encoding: 'utf8' });
try {
    const go = async (path, wait = 2500) => { await send('Page.navigate', { url: BASE_URL + path }); await sleep(wait); await ev(`document.querySelectorAll('[id^=sfwdt]').forEach(e => e.remove())`); };
    const read = () => ev(`(() => {
        const meta = (sel) => document.querySelector(sel)?.getAttribute('content') ?? null;
        const headings = [...document.querySelectorAll('h1, h2, h3, h4, h5, h6')].filter(h => !h.closest('[aria-hidden=true]')).map(h => Number(h.tagName[1]));
        const images = [...document.querySelectorAll('img')].filter(i => !i.closest('[aria-hidden=true]'));
        return {
            path: location.pathname, lang: document.documentElement.lang, title: document.title,
            description: meta('meta[name=description]'), robots: meta('meta[name=robots]'),
            canonical: document.querySelector('link[rel=canonical]')?.href ?? null,
            og: { title: meta('meta[property="og:title"]'), description: meta('meta[property="og:description"]'), image: meta('meta[property="og:image"]'), alt: meta('meta[property="og:image:alt"]'), url: meta('meta[property="og:url"]'), locale: meta('meta[property="og:locale"]') },
            twitterDescription: meta('meta[name="twitter:description"]'),
            twitter: { card: meta('meta[name="twitter:card"]'), image: meta('meta[name="twitter:image"]'), title: meta('meta[name="twitter:title"]') },
            ld: [...document.querySelectorAll('script[type="application/ld+json"]')].map(s => { try { return JSON.parse(s.textContent); } catch { return 'invalid'; } }),
            headings, missingAlt: images.filter(i => !(i.getAttribute('alt') || '').trim()).map(i => i.getAttribute('src') || i.dataset.src || '?'),
        };
    })()`);
    const pages = {};
    const titles = new Map(); const descriptions = new Map();
    const shortened = (t) => /Llunio(?! Studios)/.test(t || '');
    const common = async (label, p, indexable = true) => {
        check(`${label}: lang="en-GB"`, p.lang === 'en-GB', p.lang);
        if (!indexable) {
            check(`${label}: a title ("Llunio Studios" in full) and no description (meta, Open Graph or Twitter): hidden from search engines`, !!p.title && !shortened(p.title) && p.description === null && p.og.description === null && !p.twitterDescription, JSON.stringify([p.title, p.description, p.og.description]));
            if (titles.has(p.title)) check(`${label}: its title is unique`, false, `same as ${titles.get(p.title)}`); else titles.set(p.title, label);
            return;
        }
        check(`${label}: a title and a description ("Llunio Studios" in full)`, !!p.title && !!p.description && p.description.length >= 40 && p.description.length <= 200 && !shortened(p.title) && !shortened(p.description), JSON.stringify([p.title, p.description]));
        check(`${label}: Open Graph and Twitter previews (title, description, photo with its description, en_GB, large card)`, p.og.title === p.title && p.og.description === p.description && !!p.og.image && !!p.og.alt && p.og.locale === 'en_GB' && p.twitter.card === 'summary_large_image' && p.twitter.image === p.og.image && p.twitter.title === p.title, JSON.stringify([p.og, p.twitter]));
        const image = p.og.image ? execSync(`curl -sk -o /dev/null -w "%{http_code} %{content_type}" "${p.og.image.replace(/^https?:\/\/[^/]+/, BASE_URL)}"`, { encoding: 'utf8' }) : '';
        check(`${label}: the preview photo is a real photo on the site (a JPEG that loads)`, /^200 image\/jpeg/.test(image) && /\/images\/(home|lumen-forma|commissions|workshop)\//.test(p.og.image), `${p.og.image} ${image}`);
        const order = p.headings.every((level, i) => i === 0 || level <= p.headings[i - 1] + 1);
        check(`${label}: one h1, first, and no skipped heading levels`, p.headings.filter((h) => h === 1).length === 1 && p.headings[0] === 1 && order, JSON.stringify(p.headings));
        check(`${label}: every image has descriptive alt text`, p.missingAlt.length === 0, JSON.stringify(p.missingAlt));
        if (titles.has(p.title)) check(`${label}: its title is unique`, false, `same as ${titles.get(p.title)}`); else titles.set(p.title, label);
        if (descriptions.has(p.description)) check(`${label}: its description is unique`, false, `same as ${descriptions.get(p.description)}`); else descriptions.set(p.description, label);
    };

    // The indexable pages.
    for (const [path, name] of Object.entries(INDEXABLE)) {
        await go(path, path === '/' ? 3500 : 2500);
        const p = await read(); pages[path] = p;
        await common(name, p);
        const header = curl(path);
        check(`${name}: indexable (no "noindex" meta or header), canonical to itself`, !p.robots && !/x-robots-tag/i.test(header) && p.canonical === BASE_URL + path && p.og.url === p.canonical, JSON.stringify([p.robots, p.canonical]));
        const business = p.ld.find((d) => d['@type'] === 'LocalBusiness');
        check(`${name}: LocalBusiness data for Llunio Studios (Bournemouth, Dorset, no street or postcode; info@, Instagram and YouTube, no Facebook)`, business?.name === 'Llunio Studios' && business.address?.addressLocality === 'Bournemouth' && business.address?.addressRegion === 'Dorset' && business.address?.addressCountry === 'GB' && !('streetAddress' in (business.address ?? {})) && !('postalCode' in (business.address ?? {})) && business.email === 'info@lluniostudios.co.uk' && JSON.stringify(business.sameAs) === JSON.stringify(['https://www.instagram.com/llunio_studios/', 'https://www.youtube.com/@LlunioStudios']) && !JSON.stringify(business).includes('facebook'), JSON.stringify(business));
    }
    check('the homepage title leads with "Llunio Studios"', pages['/'].title.startsWith('Llunio Studios'), pages['/'].title);

    // Product data on Lighting Designs.
    const products = pages['/lighting-designs'].ld.find((d) => Array.isArray(d['@graph']))?.['@graph'] ?? [];
    const summaryOf = (p) => `${p.name}|${p.offers['@type']}|${p.offers.price ?? p.offers.lowPrice}|${p.offers.priceCurrency}|${p.brand?.name}|${p.image ? 'photo' : 'none'}`;
    check('Lighting Designs: Product data for the five sizes: Studio £399 and Gallery £799, Atelier from £1,699, Estate from £3,499, Monument from £7,999; brand Llunio Studios; a photo only for Gallery (real photos only)', JSON.stringify(products.map(summaryOf)) === JSON.stringify([
        'Lumen Forma Studio|Offer|399.00|GBP|Llunio Studios|none',
        'Lumen Forma Gallery|Offer|799.00|GBP|Llunio Studios|photo',
        'Lumen Forma Atelier|AggregateOffer|1699.00|GBP|Llunio Studios|none',
        'Lumen Forma Estate|AggregateOffer|3499.00|GBP|Llunio Studios|none',
        'Lumen Forma Monument|AggregateOffer|7999.00|GBP|Llunio Studios|none',
    ]) && /\/images\/lumen-forma\/gallery-01-2000\.jpg$/.test(products[1]?.image ?? ''), JSON.stringify(products.map(summaryOf)));

    // Pages kept out of search results.
    const hidden = { '/basket': 'Basket', '/login': 'Sign In' };
    for (const [path, name] of Object.entries(hidden)) {
        await go(path);
        const p = await read();
        await common(name, p, false);
        check(`${name}: "noindex, nofollow" (meta and header), no canonical, no structured data`, p.robots === 'noindex, nofollow' && /x-robots-tag: noindex, nofollow/i.test(curl(path)) && !p.canonical && p.ld.length === 0, JSON.stringify([p.robots, p.canonical]));
    }
    // Pages that need something first (a piece, a basket, a sent form): the header on any visit.
    for (const path of ['/checkout', '/reserve', '/begin-the-conversation', '/bespoke-commissions/received', '/reserve/received', '/begin-the-conversation/received', '/account/orders', '/quote/not-a-real-quote-link-123', '/order/not-a-real-order-link-1234567890', '/admin']) {
        check(`${path}: "noindex, nofollow" header`, /x-robots-tag: noindex, nofollow/i.test(curl(path)), curl(path).split('\n')[0]);
    }

    // robots.txt and sitemap.xml.
    const robots = execSync(`curl -sk "${BASE_URL}/robots.txt"`, { encoding: 'utf8' });
    check('robots.txt: blocks admin only (the other private pages rely on "noindex"); points to the sitemap', JSON.stringify([...robots.matchAll(/^Disallow: (.*)$/gm)].map((m) => m[1])) === JSON.stringify(['/admin']) && robots.includes(`Sitemap: ${BASE_URL}/sitemap.xml`), robots);
    const sitemap = execSync(`curl -sk "${BASE_URL}/sitemap.xml"`, { encoding: 'utf8' });
    const locs = [...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
    check('sitemap.xml: exactly the seven indexable pages (the Privacy Policy since 14 October 2026)', JSON.stringify(locs) === JSON.stringify(Object.keys(INDEXABLE).map((p) => BASE_URL + p)) && /application\/xml/.test(curl('/sitemap.xml')), JSON.stringify(locs));
    summary();
} finally { b.close(); }
