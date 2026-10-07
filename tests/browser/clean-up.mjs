// Removes the test data the browser checks leave on the local site:
// - baskets with no account created since the given time (the checks' baskets,
//   and any basket of your own made in that time);
// - requests from the test customer (any address @example.test);
// - Mailpit emails to @example.test, the studio notifications for those
//   requests, and sign-in codes, sent since the given time.
//   node tests/browser/clean-up.mjs "2026-10-07 08:00"   (UTC, as stored)
import { MAILPIT_URL, sql } from './lib.mjs';

export async function cleanUp(since) {
    const at = (since instanceof Date ? since : new Date(since)).toISOString().replace('T', ' ').slice(0, 19);

    const baskets = sql(`with t as (select id from cart c where c.created_at >= '${at}' and not exists (select 1 from users u where u.cart_id = c.id)), o as (delete from cart_item_option where cart_item_id in (select id from cart_item where cart_id in (select id from t)) returning 1), i as (delete from cart_item where cart_id in (select id from t) returning 1) delete from cart where id in (select id from t)`);
    const requests = sql(`delete from design_request where email like '%@example.test'`);
    console.log('baskets removed:', (baskets.match(/(\d+) rows? affected/) || [, 0])[1], '· test requests removed:', (requests.match(/(\d+) rows? affected/) || [, 0])[1]);

    const search = async (q) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(q)}`)).json()).messages || [];
    const after = (m) => new Date(m.Created) >= new Date(at + 'Z');
    const ids = [
        ...(await search('to:example.test')).filter(after),
        ...(await search('subject:"New request LR-"')).filter((m) => after(m) && /Ada Test/.test(m.Subject)),
        ...(await search('subject:"sign-in code"')).filter(after),
    ].map((m) => m.ID);
    if (ids.length) {
        await fetch(`${MAILPIT_URL}/api/v1/messages`, { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ IDs: ids }) });
    }
    console.log('test emails removed:', ids.length);
}

if (process.argv[1] && process.argv[1].endsWith('clean-up.mjs')) {
    if (!process.argv[2]) {
        console.error('Usage: node tests/browser/clean-up.mjs "<since, e.g. 2026-10-07 08:00 (UTC)>"');
        process.exit(2);
    }
    await cleanUp(process.argv[2]);
}
