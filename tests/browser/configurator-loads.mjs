// The configurator loads with no script errors: a normal visit, an old
// session whose basket no longer exists, and an edit link.
import { open, sleep, BASE_URL } from './lib.mjs';
const b = await open(1440, 900, 'noerr');
let ok = true;
const check = (label, pass, detail = '') => { if (!pass) ok = false; console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}${detail ? '  (' + detail + ')' : ''}`); };
let errs = []; b.listeners.push((m) => { if (m.method === 'Runtime.exceptionThrown') errs.push(m.params.exceptionDetails.exception?.description?.split('\n')[0]); if (m.method === 'Runtime.consoleAPICalled' && m.params.type === 'error') errs.push(m.params.args.map(a => a.value || a.description).join(' ').slice(0, 200)); });
await b.send('Runtime.enable'); await b.send('Network.enable');
const visit = async (url) => { errs = []; await b.send('Page.navigate', { url }); await sleep(2500); return b.ev(`({ selected: [...document.querySelectorAll('.ll-cfg-opt.is-selected')].map(e => e.textContent.trim()).join(','), spec: document.querySelector('[data-configurator-target=spec]').textContent })`); };
let s = await visit(BASE_URL + '/lighting-designs');
check('normal visit: no script errors, Gallery selected and its spec shown', errs.length === 0 && s.selected === 'Gallery' && s.spec === 'Gallery · £799', errs.join(' | ') || JSON.stringify(s));
s = await visit(BASE_URL + '/lighting-designs?edit=not-a-line');
check('unknown edit link: no script errors', errs.length === 0 && s.selected === 'Gallery', errs.join(' | '));
await b.send('Network.clearBrowserCookies');
s = await visit(BASE_URL + '/lighting-designs');
check('fresh session: no script errors', errs.length === 0 && s.selected === 'Gallery', errs.join(' | '));
console.log(ok ? 'ALL PASSED' : 'SOME CHECKS FAILED');
b.close();
