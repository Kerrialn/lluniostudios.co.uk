// Runs every browser check in turn and prints a summary.
//   node tests/browser/run-all.mjs           run them all
//   node tests/browser/run-all.mjs --clean   ...then remove the test data this run left
//   node tests/browser/run-all.mjs reserve configurator-edit   just these (by file name)
import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { cleanUp } from './clean-up.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const CHECKS = [
    ['configurator-loads.mjs'],
    ['configurator-page.mjs'],
    ['configurator-stages.mjs'],
    ['configurator-customise.mjs'],
    ['configurator-photos.mjs', '1440', '900'],
    ['configurator-photos.mjs', '390', '844'],
    ['configurator-add-to-basket.mjs'],
    ['configurator-custom-notes.mjs', '1440', '900'],
    ['configurator-custom-notes.mjs', '390', '844'],
    ['configurator-edit.mjs', '1440', '900'],
    ['configurator-edit.mjs', '390', '844'],
    ['configurator-hover-and-headings.mjs'],
    ['configurator-remember.mjs'],
    ['reserve.mjs'],
    ['old-shop-redirects.mjs'],
    ['commissions.mjs', '1440', '900'],
    ['commissions.mjs', '390', '844'],
    ['commissions-saving.mjs'],
    ['basket-gift.mjs', '1440', '900'],
    ['basket-gift.mjs', '390', '844'],
    ['checkout-page.mjs', '1440', '900'],
    ['checkout-page.mjs', '390', '844'],
    ['checkout-pay.mjs', '1440', '900'],
    ['checkout-pay.mjs', '390', '844'],
    ['checkout-wallet.mjs'],
    ['account.mjs'],
    ['page-footers.mjs', '1440', '900'],
    ['page-footers.mjs', '390', '844'],
    ['quote-form.mjs'],
    ['quote-send.mjs'],
    ['quote-pay.mjs'],
    ['quote-balance.mjs'],
    ['order-emails.mjs'],
    ['gift-options.mjs'],
    ['order-stages.mjs'],
];

const args = process.argv.slice(2);
const clean = args.includes('--clean');
const only = args.filter((a) => !a.startsWith('--')).map((a) => a.replace(/\.mjs$/, '') + '.mjs');
const started = new Date();
const results = [];

for (const [file, ...size] of CHECKS.filter(([f]) => only.length === 0 || only.includes(f))) {
    const label = [file, ...size].join(' ');
    process.stdout.write(`== ${label} ... `);
    const run = spawnSync(process.execPath, [join(here, file), ...size], { encoding: 'utf8', timeout: 10 * 60 * 1000 });
    const output = (run.stdout || '') + (run.stderr || '');
    const passed = /ALL PASSED/.test(output) && !/^FAIL/m.test(output);
    results.push([label, passed]);
    console.log(passed ? 'passed' : 'FAILED');
    if (!passed) console.log(output.split('\n').filter((l) => /^FAIL|Error|error/.test(l)).slice(0, 12).map((l) => '   ' + l).join('\n'));
}

const failed = results.filter(([, passed]) => !passed);
console.log(`\n${results.length - failed.length} of ${results.length} passed${failed.length ? '; failed: ' + failed.map(([l]) => l).join(', ') : ''}.`);
if (clean) await cleanUp(started);
process.exitCode = failed.length ? 1 : 0;
