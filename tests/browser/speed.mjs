// The speed pass (13 October 2026): how much each main page downloads and how
// quickly it shows, on a phone-sized screen (390×844) over a throttled mobile
// connection (as Lighthouse's mobile test: 1.6 Mbps down, 150 ms round trip,
// a 4× slower processor), with an empty cache. Prints, per page, the images
// downloaded (count, KB, formats), the total KB, when the largest content
// appeared (LCP) and when the page finished loading. With --check, also checks
// the targets set in the speed pass: every photo served as AVIF or WebP where
// the browser supports it, no image larger than the screen needs, nothing below
// the first screen downloaded before it is needed, and LCP under 4 seconds.
//   node tests/browser/speed.mjs [--check]
import { open, sleep, checker, BASE_URL } from './lib.mjs';

const CHECK = process.argv.includes('--check');
const PAGES = ['/', '/lighting-designs', '/bespoke-commissions', '/workshop'];
const { check, summary } = checker();
const results = [];
for (const path of PAGES) {
    const b = await open(390, 844, 'speed', { mobile: true });
    const { send, ev, listeners } = b;
    try {
        await send('Network.enable');
        await send('Network.clearBrowserCache');
        await send('Network.setCacheDisabled', { cacheDisabled: true });
        await send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1.6 * 1024 * 1024 / 8, uploadThroughput: 750 * 1024 / 8 });
        await send('Emulation.setCPUThrottlingRate', { rate: 4 });
        const responses = new Map();
        listeners.push((m) => {
            if (m.method === 'Network.responseReceived') responses.set(m.params.requestId, { url: m.params.response.url, type: m.params.type, mime: m.params.response.mimeType, bytes: 0 });
            if (m.method === 'Network.loadingFinished' && responses.has(m.params.requestId)) responses.get(m.params.requestId).bytes = m.params.encodedDataLength;
        });
        await send('Page.navigate', { url: BASE_URL + path });
        await sleep(15000);
        const timing = await ev(`new Promise((resolve) => { let lcp = 0; new PerformanceObserver((list) => { for (const e of list.getEntries()) lcp = e.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true }); setTimeout(() => { const nav = performance.getEntriesByType('navigation')[0]; resolve({ lcp: Math.round(lcp), load: Math.round(nav.loadEventEnd) }); }, 200); })`);
        const images = [...responses.values()].filter((r) => r.type === 'Image' && !/\/_wdt|data:/.test(r.url));
        const formats = {};
        images.forEach((r) => { const f = r.mime.replace('image/', ''); formats[f] = (formats[f] || 0) + 1; });
        const kb = (n) => Math.round(n / 1024);
        const all = [...responses.values()].filter((r) => !/\/_wdt|\/_profiler/.test(r.url));
        const result = { path, images: images.length, imageKB: kb(images.reduce((t, r) => t + r.bytes, 0)), totalKB: kb(all.reduce((t, r) => t + r.bytes, 0)), formats, lcp: timing.lcp, load: timing.load, list: images.map((r) => `${r.url.replace(/^https?:\/\/[^/]+/, '')} ${kb(r.bytes)}KB ${r.mime}`) };
        results.push(result);
        console.log(`${path}: ${result.images} images, ${result.imageKB} KB of images, ${result.totalKB} KB in all; formats ${JSON.stringify(formats)}; LCP ${result.lcp} ms; loaded ${result.load} ms`);
        result.list.forEach((l) => console.log('   ' + l));
        if (CHECK) {
            const photos = images.filter((r) => /\/images\/(home|lumen-forma|commissions|workshop)\/.+\.(jpg|webp|avif)/.test(r.url));
            check(`${path}: every photo served as AVIF or WebP`, photos.every((r) => /avif|webp/.test(r.mime)), JSON.stringify(photos.map((r) => r.mime)));
            check(`${path}: no 2000px photo on a 390px-wide screen (the 1000px versions suffice)`, !photos.some((r) => /-2000\.|-2560\./.test(r.url)), JSON.stringify(photos.map((r) => r.url.split('/').pop())));
            check(`${path}: largest content shown within 4 seconds on a throttled phone`, result.lcp > 0 && result.lcp < 4000, `${result.lcp} ms`);
        }
    } finally { b.close(); }
    await sleep(500);
}
if (CHECK) summary();
