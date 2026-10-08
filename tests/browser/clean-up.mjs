// Removes the test data the browser checks leave on the local site:
// - baskets with no account created since the given time (the checks' baskets,
//   and any basket of your own made in that time);
// - requests, Bespoke Commissions enquiries (with their stored files),
//   orders (with their addresses) and accounts of the test customer (any
//   address @example.test);
// - Mailpit emails to @example.test, the studio notifications for those
//   requests and enquiries, and sign-in codes, sent since the given time.
//   node tests/browser/clean-up.mjs "2026-10-07 08:00"   (UTC, as stored)
import { rmSync } from 'node:fs';
import { join } from 'node:path';
import { MAILPIT_URL, PROJECT_DIR, sql } from './lib.mjs';

export async function cleanUp(since) {
    const at = (since instanceof Date ? since : new Date(since)).toISOString().replace('T', ' ').slice(0, 19);

    const baskets = sql(`with t as (select id from cart c where c.created_at >= '${at}' and not exists (select 1 from users u where u.cart_id = c.id)), o as (delete from cart_item_option where cart_item_id in (select id from cart_item where cart_id in (select id from t)) returning 1), i as (delete from cart_item where cart_id in (select id from t) returning 1) delete from cart where id in (select id from t)`);
    const requests = sql(`delete from design_request where email like '%@example.test'`);
    const stored = [...sql(`select f.stored_name from commission_enquiry_file f join commission_enquiry e on e.id = f.enquiry_id where e.email like '%@example.test'`).matchAll(/\b[0-9a-f]{32}(?:\.[a-z]+)?\b/g)].map((m) => m[0]);
    stored.forEach((name) => rmSync(join(PROJECT_DIR, 'var', 'commission-uploads', name), { force: true }));
    const enquiries = sql(`delete from commission_enquiry where email like '%@example.test'`);
    const orderCount = Number(sql(`select count(*) from orders where email like '%@example.test'`).match(/\d+/g)?.pop() ?? 0);
    const accounts = sql(`select count(*) from users where email like '%@example.test'`).match(/\d+/g)?.pop() ?? 0;
    sql(`with o as (select id, shipping_address_id, billing_address_id from orders where email like '%@example.test'), i as (delete from order_item where order_id in (select id from o) returning 1), d as (delete from orders where id in (select id from o) returning shipping_address_id, billing_address_id) delete from address where id in (select shipping_address_id from d union select billing_address_id from d)`);
    const rows = (out) => (out.match(/(\d+) rows? affected/) || [, 0])[1];
    console.log('baskets removed:', rows(baskets), '· test requests removed:', rows(requests), '· test commission enquiries removed:', rows(enquiries), `(${stored.length} files)`, '· test orders removed:', orderCount, '· test accounts removed:', accounts);
    sql(`delete from users where email like '%@example.test'`);

    const search = async (q) => (await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(q)}`)).json()).messages || [];
    const after = (m) => new Date(m.Created) >= new Date(at + 'Z');
    const ids = [
        ...(await search('to:example.test')).filter(after),
        ...(await search('subject:"New request LR-"')).filter((m) => after(m) && /Ada Test/.test(m.Subject)),
        ...(await search('subject:"New commission enquiry LC-"')).filter((m) => after(m) && /Ada Test|Test Architects Ltd/.test(m.Subject)),
        ...(await search('subject:"Sign in to Llunio Studios"')).filter(after),
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
