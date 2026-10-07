// Shared helpers for the browser checks (see README.md): settings, a
// headless Chrome driven over the DevTools protocol, and a database helper.
import { spawn, spawnSync, execSync } from 'node:child_process';
import { mkdirSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

// ---- Settings (environment variables, with local defaults) ----------------

/** The local site. */
export const BASE_URL = (process.env.BASE_URL || 'https://localhost').replace(/\/$/, '');
/** Mailpit, which catches every email the local site sends. */
export const MAILPIT_URL = (process.env.MAILPIT_URL || 'http://localhost:52374').replace(/\/$/, '');
/** The project (for database commands through docker compose). */
export const PROJECT_DIR = process.env.PROJECT_DIR || resolve(dirname(fileURLToPath(import.meta.url)), '../..');
/** Where screenshots and saved emails go: var/browser-checks in the project (ignored by git). */
export const OUT_DIR = process.env.OUT_DIR || join(PROJECT_DIR, 'var', 'browser-checks');
/** Google Chrome. */
export const CHROME = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
/**
 * The unzipped Claude Design prototype folder (the one holding
 * "Llunio Site v4 Unified.dc.html"), for the side-by-side comparisons only.
 */
export const PROTOTYPE_DIR = process.env.PROTOTYPE_DIR || '';

mkdirSync(OUT_DIR, { recursive: true });

export const out = (name) => join(OUT_DIR, name);

/** file:// URL of the prototype page; exits with a clear message if it isn't set up. */
export function prototypeUrl() {
    const page = join(PROTOTYPE_DIR, 'Llunio Site v4 Unified.dc.html');
    if (!PROTOTYPE_DIR || !existsSync(page)) {
        console.error('Set PROTOTYPE_DIR to the unzipped prototype folder (the one with "Llunio Site v4 Unified.dc.html"). See tests/browser/README.md.');
        process.exit(2);
    }
    return pathToFileURL(page).href;
}

// ---- Helpers ---------------------------------------------------------------

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Run SQL on the local database (inside the PHP container); returns the printed table. */
export const sql = (query) => execSync(`docker compose exec -T php php bin/console dbal:run-sql "${query}" 2>/dev/null`, { cwd: PROJECT_DIR, encoding: 'utf8' });

/** PASS/FAIL lines; call summary() at the end. */
export function checker() {
    let ok = true;
    const check = (label, pass, detail = '') => {
        if (!pass) ok = false;
        console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`);
    };
    const summary = () => { console.log(ok ? 'ALL PASSED' : 'SOME CHECKS FAILED'); process.exitCode = ok ? 0 : 1; };
    return { check, summary };
}

/**
 * A headless Chrome at W x H (emulating a mobile device, as the checks were
 * written and verified; pass { mobile: false } for a desktop browser). Each named profile
 * lives in the system temp folder, so cookies persist between runs of the
 * same check unless it clears them.
 * Returns { send, ev, size, shot, listeners, close }.
 */
export async function open(W, H, name = 'default', { mobile = true } = {}) {
    const port = 9800 + Math.floor(Math.random() * 150);
    const profile = join(tmpdir(), `llunio-chrome-profile-${name}`);
    // A crashed earlier run can leave Chrome holding this profile.
    spawnSync('pkill', ['-f', `${profile}( |$)`]);
    await sleep(300);
    const chrome = spawn(CHROME, ['--headless=new', '--ignore-certificate-errors', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
    process.on('exit', () => chrome.kill());
    process.on('uncaughtException', (e) => { console.error(e); chrome.kill(); process.exit(1); });
    process.on('unhandledRejection', (e) => { console.error(e); chrome.kill(); process.exit(1); });

    let target;
    for (let i = 0; i < 60 && !target; i++) {
        await sleep(250);
        try { target = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find((x) => x.type === 'page'); } catch {}
    }
    if (!target) throw new Error(`Chrome didn't start (${CHROME}); set CHROME to its path.`);
    const ws = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((r) => ws.addEventListener('open', r));
    let id = 0;
    const pending = new Map();
    const listeners = [];
    ws.addEventListener('message', (e) => {
        const m = JSON.parse(e.data);
        if (pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); } else listeners.forEach((l) => l(m));
    });
    const send = (method, params = {}) => new Promise((r) => { pending.set(++id, r); ws.send(JSON.stringify({ id, method, params })); });
    const ev = async (expression) => {
        const r = (await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result;
        if (r.exceptionDetails) console.log('JS error', r.exceptionDetails.exception?.description?.split('\n')[0]);
        return r.result?.value;
    };
    const size = (w, h) => send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile });
    await size(W, H);
    const shot = async (file, clip) => {
        const { writeFileSync } = await import('node:fs');
        const s = await send('Page.captureScreenshot', clip ? { clip: { ...clip, scale: 1 }, captureBeyondViewport: true } : {});
        writeFileSync(file, Buffer.from(s.result.data, 'base64'));
    };
    return { send, ev, size, shot, listeners, close: () => { ws.close(); chrome.kill(); } };
}
