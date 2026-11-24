// Browser test of the shop features on a real WordPress (run by dev/ci/smoke.sh, which prepares the site).
// Usage: node dev/e2e-shop.js <siteUrl> <uiPageId> <wishlistPageId> <productId> [outDir]
// Checks in headless Chrome/Edge: product cards, wishlist (♡ + count + page), instant add to cart + side cart,
// live search, login popup, product page Q&A + reviews, phone layout (bottom bar, no sideways scrolling),
// and that no page throws a JavaScript error. Prints "N passed, M failed" (exit code 1 on failures).
const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const [BASE0, UI, WLP, PID] = process.argv.slice(2);
const BASE = (BASE0 || '').replace(/\/$/, '');
const OUT = process.argv[6] || path.join(os.tmpdir(), 'kk-shop-e2e');
const BROWSERS = ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium', '/usr/bin/chromium-browser'];
const BROWSER = BROWSERS.find((b) => fs.existsSync(b));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let pass = 0;
let fail = 0;
function check(name, ok, info) { if (ok) { pass++; console.log('  ✓ ' + name); } else { fail++; console.log('  ✗ ' + name + (info !== undefined ? '  → ' + JSON.stringify(info).slice(0, 300) : '')); } }

(async () => {
  if (!BASE || !UI || !PID) { console.log('usage: node dev/e2e-shop.js <siteUrl> <uiPageId> <wishlistPageId> <productId> [outDir]'); process.exit(2); }
  if (!BROWSER || typeof WebSocket === 'undefined') { console.log('  (needs Chrome/Edge and Node 22+: shop browser test skipped)'); console.log('0 passed, 0 failed'); return; }
  fs.mkdirSync(OUT, { recursive: true });
  const port = 9600 + Math.floor(Math.random() * 300);
  const prof = fs.mkdtempSync(path.join(os.tmpdir(), 'kkshop-'));
  const proc = spawn(BROWSER, ['--headless=new', '--disable-gpu', '--no-sandbox', '--hide-scrollbars', `--remote-debugging-port=${port}`, `--user-data-dir=${prof}`, 'about:blank'], { stdio: 'ignore' });
  let target;
  for (let i = 0; i < 40 && !target; i++) { await sleep(250); try { target = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find((t) => t.type === 'page'); } catch (e) { /* starting */ } }
  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((r) => (ws.onopen = r));
  let n = 0;
  const pend = {};
  const errors = [];
  ws.onmessage = (m) => {
    const d = JSON.parse(m.data);
    if (d.id && pend[d.id]) { pend[d.id](d); delete pend[d.id]; }
    if (d.method === 'Runtime.exceptionThrown') {
      const x = d.params.exceptionDetails;
      const where = (x.url || '') + ' ' + (x.stackTrace ? JSON.stringify(x.stackTrace.callFrames.map((f) => f.url)) : '');
      if (where.includes('kenakata-kit')) errors.push((x.exception ? x.exception.description : x.text).slice(0, 200));
    }
    if (d.method === 'Page.javascriptDialogOpening') send('Page.handleJavaScriptDialog', { accept: true });
  };
  const send = (method, params = {}) => Promise.race([new Promise((r) => { const id = ++n; pend[id] = r; ws.send(JSON.stringify({ id, method, params })); }), sleep(15000).then(() => ({}))]);
  const ev = async (expr) => {
    const r = await send('Runtime.evaluate', { expression: `(async()=>{${expr}})()`, awaitPromise: true, returnByValue: true });
    if (r.result && r.result.exceptionDetails) return 'JS ERROR: ' + (r.result.exceptionDetails.exception || {}).description;
    return r.result && r.result.result ? r.result.result.value : null;
  };
  const waitFor = async (expr, ms = 8000) => { for (let t = 0; t < ms; t += 200) { if (await ev(`return !!(${expr});`)) return true; await sleep(200); } return false; };
  const go = async (url) => { await send('Page.navigate', { url }); await sleep(400); await waitFor("document.readyState === 'complete'", 15000); await sleep(500); };
  const shot = async (name) => { const s = await send('Page.captureScreenshot', { format: 'png' }); if (s.result) fs.writeFileSync(path.join(OUT, name + '.png'), Buffer.from(s.result.data, 'base64')); };
  await send('Page.enable');
  await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false });

  console.log('Shop page (cards, wishlist, side cart, search, login popup)');
  await go(`${BASE}/?page_id=${UI}`);
  await shot('desktop-shop');
  check('product cards are shown', await ev("return document.querySelectorAll('.kk-pcard').length") > 0);
  // Wishlist
  const wlBefore = await ev("const c = document.querySelector('[data-kk-wl-count]'); return c ? c.textContent.trim() : 'none';");
  await ev("document.querySelector('[data-kk-wl]').click(); return 1;");
  check('wishlist ♡ turns on', await waitFor("document.querySelector('[data-kk-wl]').classList.contains('is-on') || document.querySelector('[data-kk-wl]').getAttribute('aria-pressed') === 'true'"));
  check('wishlist count goes up', await waitFor("(() => { const c = document.querySelector('[data-kk-wl-count]'); return c && c.textContent.trim() !== " + JSON.stringify(wlBefore) + "; })()"), wlBefore);
  // Instant add to cart → side cart
  await ev("document.querySelector('[data-kk-add]').click(); return 1;");
  check('Add to cart opens the side cart', await waitFor("document.documentElement.classList.contains('kk-drawer-open')"));
  check('side cart lists the product', await waitFor("(() => { const d = document.querySelector('.kk-drawer'); return d && /\\S/.test(d.textContent) && d.querySelector('.kk-drawer__item, .woocommerce-mini-cart-item, li'); })()"));
  await shot('desktop-side-cart');
  await ev("const b = document.querySelector('[data-kk-drawer-close]'); if (b) b.click(); return 1;");
  check('side cart closes', await waitFor("!document.documentElement.classList.contains('kk-drawer-open')"));
  // Live search
  await ev("const i = document.querySelector('.kk-search__input'); i.focus(); i.value = 'Saree'; i.dispatchEvent(new Event('input', { bubbles: true })); return 1;");
  check('live search shows results while typing', await waitFor("(() => { const r = document.querySelector('.kk-search__results'); return r && r.querySelectorAll('a').length > 0; })()"));
  await shot('desktop-search');
  await ev("document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })); document.body.click(); return 1;");
  // Login popup
  await ev("const a = document.querySelector('[data-kk-login]'); if (a) a.click(); return 1;");
  check('login link opens the popup', await waitFor("document.documentElement.classList.contains('kk-modal-open') && document.querySelector('.kk-modal:not([hidden])')"));
  await shot('desktop-login-popup');
  await ev("const c = document.querySelector('.kk-modal:not([hidden]) [data-kk-close], .kk-modal:not([hidden]) .kk-modal__close'); if (c) c.click(); return 1;");
  check('login popup closes', await waitFor("!document.documentElement.classList.contains('kk-modal-open')"));

  if (WLP && WLP !== '0') {
    await go(`${BASE}/?page_id=${WLP}`);
    check('wishlist page shows the saved product', await waitFor("document.querySelectorAll('.kk-wl-page__list .kk-pcard, [data-kk-wl-page] .kk-pcard').length > 0"));
    await shot('desktop-wishlist-page');
  }

  console.log('Product page (Q&A, reviews)');
  await go(`${BASE}/?p=${PID}`);
  await shot('desktop-product');
  check('Questions & answers form is there', await ev("return !!document.querySelector('[data-kk-qa-form]')"));
  const qa = await ev("const f = document.querySelector('[data-kk-qa-form]'); if (!f) return 'no form'; f.question.value = 'Is this suitable for summer?'; f.name.value = 'Test Buyer'; if (f.phone) f.phone.value = '01712345678'; f.querySelector('button[type=submit]').click(); return 'sent';");
  check('a question can be sent', qa === 'sent' && await waitFor("(() => { const m = document.querySelector('.kk-qa__msg'); return m && m.textContent.trim().length > 0 && !/error|ভুল/i.test(m.className); })()", 10000), qa);
  check('reviews section is there', await ev("return !!document.querySelector('#reviews, .kk-rv, [data-kk-write-review]')"));

  console.log('Phone layout');
  await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await go(`${BASE}/?page_id=${UI}`);
  await shot('phone-shop');
  check('bottom bar is visible on a phone', await ev("const b = document.querySelector('.kk-bbar'); return !!b && getComputedStyle(b).display !== 'none' && b.getBoundingClientRect().height > 0;"));
  const over = await ev('return document.documentElement.scrollWidth - window.innerWidth;');
  check('no sideways scrolling on a phone (shop page)', over <= 1, over);
  await go(`${BASE}/?p=${PID}`);
  await shot('phone-product');
  const over2 = await ev('return document.documentElement.scrollWidth - window.innerWidth;');
  check('no sideways scrolling on a phone (product page)', over2 <= 1, over2);

  check('no JavaScript errors from the plugin', errors.length === 0, errors);
  console.log(`\n${pass} passed, ${fail} failed. Screenshots: ${OUT}`);
  ws.close();
  proc.kill();
  try { fs.rmSync(prof, { recursive: true, force: true }); } catch (e) { /* locked on Windows */ }
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.log('  ✗ test crashed: ' + e.message); console.log(`\n${pass} passed, ${fail + 1} failed`); process.exit(1); });
