// End-to-end test of the store dashboard in headless Edge/Chrome against dev/mock-server.js (or a real site).
// Clicks through every screen like a user, checks the result of each step, takes screenshots, and fails on
// JavaScript errors or unexpected dialogs (alert() would mean HTML from data was executed).
// Usage: node dev/mock-server.js 5187   (in another terminal)  then  node dev/e2e-dashboard.js [baseUrl] [outDir]
const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

// Without a base URL a fresh mock server is started (clean sample data every run) and stopped afterwards.
let mock = null;
let BASE = process.argv[2] && process.argv[2] !== '-' ? process.argv[2].replace(/\/$/, '') : '';
if (!BASE) {
  const mp = 5200 + Math.floor(Math.random() * 200);
  mock = spawn(process.execPath, [path.join(__dirname, 'mock-server.js'), String(mp)], { stdio: ['ignore', 'pipe', 'inherit'] });
  mock.stdout.on('data', (d) => { if (/MISSING/.test(String(d))) console.log(String(d).trim()); });
  BASE = 'http://localhost:' + mp;
}
const OUT = process.argv[3] || path.join(os.tmpdir(), 'kk-sd-e2e');
const BROWSERS = ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/google-chrome', '/usr/bin/chromium'];
const BROWSER = BROWSERS.find((b) => fs.existsSync(b));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let pass = 0;
let fail = 0;
function check(name, ok, info) { if (ok) { pass++; console.log('  ✓ ' + name); } else { fail++; console.log('  ✗ ' + name + (info !== undefined ? '  → ' + JSON.stringify(info).slice(0, 300) : '')); } }

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  if (mock) { for (let i = 0; i < 40; i++) { await sleep(150); try { await fetch(BASE + '/dashboard/'); break; } catch (e) { /* starting */ } } }
  const port = 9300 + Math.floor(Math.random() * 300);
  const prof = fs.mkdtempSync(path.join(os.tmpdir(), 'kksd-'));
  const proc = spawn(BROWSER, ['--headless=new', '--disable-gpu', '--hide-scrollbars', `--remote-debugging-port=${port}`, `--user-data-dir=${prof}`, 'about:blank'], { stdio: 'ignore' });
  let target;
  for (let i = 0; i < 40 && !target; i++) { await sleep(250); try { target = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find((t) => t.type === 'page'); } catch (e) { /* starting */ } }
  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((r) => (ws.onopen = r));
  let n = 0;
  const pend = {};
  const errors = [];
  const dialogs = [];
  let dialogAnswer = true;
  ws.onmessage = (m) => {
    const d = JSON.parse(m.data);
    if (d.id && pend[d.id]) { pend[d.id](d); delete pend[d.id]; }
    if (d.method === 'Runtime.exceptionThrown') {
      // Only errors of the dashboard page itself (the browser's own built-in scripts can throw too).
      const x = d.params.exceptionDetails;
      const where = (x.url || '') + ' ' + (x.stackTrace ? JSON.stringify(x.stackTrace.callFrames.map((f) => f.url)) : '');
      if (where.includes(BASE) || where.includes('store-dashboard')) errors.push(x.exception ? x.exception.description : x.text);
    }
    if (d.method === 'Runtime.consoleAPICalled' && d.params.type === 'error') errors.push(d.params.args.map((a) => a.value || a.description).join(' '));
    if (d.method === 'Page.javascriptDialogOpening') { dialogs.push(d.params.type + ': ' + d.params.message); send('Page.handleJavaScriptDialog', { accept: dialogAnswer }); }
  };
  const send = (method, params = {}) => Promise.race([new Promise((r) => { const id = ++n; pend[id] = r; ws.send(JSON.stringify({ id, method, params })); }), sleep(15000).then(() => ({}))]);
  const ev = async (expr) => {
    const r = await send('Runtime.evaluate', { expression: `(async()=>{${expr}})()`, awaitPromise: true, returnByValue: true });
    if (r.result && r.result.exceptionDetails) return 'JS ERROR: ' + (r.result.exceptionDetails.exception || {}).description;
    return r.result && r.result.result ? r.result.result.value : null;
  };
  const shot = async (name) => { const s = await send('Page.captureScreenshot', { format: 'png' }); if (s.result) fs.writeFileSync(path.join(OUT, name + '.png'), Buffer.from(s.result.data, 'base64')); };
  const viewport = (mobile) => send('Emulation.setDeviceMetricsOverride', { width: mobile ? 390 : 1280, height: mobile ? 844 : 800, deviceScaleFactor: 1, mobile });
  const open = async (url) => { await send('Page.navigate', { url }); for (let i = 0; i < 40; i++) { await sleep(250); if ((await ev('return document.readyState')) === 'complete') break; } await sleep(900); };
  const hash = async (h) => { await ev(`location.hash=${JSON.stringify(h)};return 1`); await sleep(900); };
  const waitFor = async (js, ms = 5000) => { for (let i = 0; i < ms / 150; i++) { const v = await ev(js); if (v) return v; await sleep(150); } return null; };
  const H = (sel) => `document.querySelector(${JSON.stringify(sel)})`;
  const textOf = (sel) => ev(`const e=${H(sel)};return e?e.textContent.trim():null`);
  const click = (sel) => ev(`const e=${H(sel)};if(!e)return 'missing';e.click();return 'ok'`);
  const lastToast = () => ev(`const t=[...document.querySelectorAll('.sd-toast')];return t.length?t[t.length-1].textContent:''`);
  const clickText = (sel, text) => ev(`const e=[...document.querySelectorAll(${JSON.stringify(sel)})].find(x=>x.textContent.trim()===${JSON.stringify(text)});if(!e)return 'missing';e.click();return 'ok'`);
  const setVal = (sel, v) => ev(`const e=${H(sel)};if(!e)return 'missing';e.value=${JSON.stringify(v)};e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}));return 'ok'`);

  await send('Page.enable');
  await send('Runtime.enable');
  await viewport(false);

  console.log('Home');
  await open(BASE + '/dashboard/');
  check('home renders stats', (await ev(`return document.querySelectorAll('.sd-stat').length`)) === 3);
  check('attention alerts', (await ev(`return document.querySelectorAll('.sd-alert').length`)) >= 1);
  check('status chips link to orders', (await ev(`return [...document.querySelectorAll('.sd-chip')].every(a=>a.getAttribute('href').startsWith('#/orders?status='))`)) === true);
  check('desktop sidebar visible', (await ev(`return getComputedStyle(${H('.sd-side')}).display`)) === 'flex');
  await shot('01-home-desktop');

  console.log('Orders');
  await hash('#/orders');
  await waitFor(`return document.querySelectorAll('.sd-order').length`);
  check('20 orders on page 1', (await ev(`return document.querySelectorAll('.sd-order').length`)) === 20);
  check('no nested links in order cards', (await ev(`return document.querySelectorAll('a a').length`)) === 0);
  await click('.sd-more');
  await waitFor(`return document.querySelectorAll('.sd-order').length===27`);
  check('load more → 27', (await ev(`return document.querySelectorAll('.sd-order').length`)) === 27);
  await hash('#/orders?status=processing');
  await waitFor(`return document.querySelectorAll('.sd-order').length`);
  check('filter processing', (await ev(`const b=[...document.querySelectorAll('.sd-order .sd-badge')];return b.length>0&&b.every(x=>x.classList.contains('sd-badge--processing'))`)) === true);
  check('active chip marked', (await textOf('.sd-chip.is-active span')) === 'Processing');
  await ev(`const i=${H('.sd-search__input')};i.value='Nusrat';i.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter'}));return 1`);
  await sleep(1000);
  check('search keeps status + finds name', (await ev(`return location.hash.includes('search=Nusrat')&&[...document.querySelectorAll('.sd-order__name')].every(e=>e.textContent==='Nusrat Jahan')`)) === true);
  await shot('02-orders');

  console.log('Order detail');
  await hash('#/orders/900');
  await waitFor(`return document.querySelector('.sd-lines')`);
  check('customer note shown as text (no HTML run)', (await ev(`return document.querySelector('.sd-note-box').textContent.includes('<script>')`)) === true);
  check('call + WhatsApp buttons', (await ev(`return !!document.querySelector('.sd-card a[href^="tel:"]')&&!!document.querySelector('.sd-card a[href^="https://wa.me/"]')`)) === true);
  check('cash to collect shown', !!(await textOf('.sd-totals__cod dd')));
  await shot('03-order-detail');
  await ev(`const s=document.querySelectorAll('.sd-card')[2].querySelector('select');s.value='completed';s.dispatchEvent(new Event('change',{bubbles:true}));return 1`);
  await ev(`document.querySelectorAll('.sd-card')[2].querySelector('.sd-btn--primary').click();return 1`);
  await waitFor(`return document.querySelector('.sd-head .sd-badge--completed')`);
  check('status saved → completed', !!(await ev(`return document.querySelector('.sd-head .sd-badge--completed')`)));
  check('status change wrote a note', (await ev(`return document.querySelector('.sd-notes__item p').textContent.includes('Completed')`)) === true);

  await hash('#/orders/901');
  await waitFor(`return document.querySelector('.sd-lines')`);
  const courierCard = `[...document.querySelectorAll('.sd-card')].find(c=>c.querySelector('input[type=url]'))`;
  await ev(`const c=${courierCard};c.querySelector('input[type=url]').value='not a link';c.querySelector('.sd-btn--primary').click();return 1`);
  await sleep(700);
  check('bad tracking link rejected', (await lastToast()).includes('https://'));
  await ev(`const c=${courierCard};c.querySelector('select').value='steadfast';c.querySelectorAll('input')[0].value='SF123';c.querySelector('input[type=url]').value='https://steadfast.com.bd/t/SF123';c.querySelector('.sd-btn--primary').click();return 1`);
  await waitFor(`return document.querySelector('.sd-head .sd-badge--shipped')`);
  check('tracking saved → Shipped + customer note', (await ev(`return !!document.querySelector('.sd-head .sd-badge--shipped')&&document.querySelector('.sd-notes__item.is-customer')!==null`)) === true);
  await ev(`const ta=[...document.querySelectorAll('.sd-card textarea')].pop();ta.value='Customer asked for evening delivery';ta.dispatchEvent(new Event('input'));return 1`);
  await clickText('.sd-btn span', 'Add note');
  await waitFor(`return [...document.querySelectorAll('.sd-notes__item p')].some(p=>p.textContent.includes('evening'))`);
  check('private note added', (await ev(`return [...document.querySelectorAll('.sd-notes__item p')].some(p=>p.textContent.includes('evening'))`)) === true);

  console.log('Products');
  await hash('#/products');
  await waitFor(`return document.querySelectorAll('.sd-pcard').length`);
  check('9 products', (await ev(`return document.querySelectorAll('.sd-pcard').length`)) === 9);
  check('sale price shows old price', (await ev(`return document.querySelectorAll('.sd-pcard del').length`)) >= 1);
  await shot('04-products');
  await hash('#/products?filter=low');
  await waitFor(`return document.querySelectorAll('.sd-pcard').length`);
  check('low stock filter', (await ev(`return document.querySelectorAll('.sd-pcard').length`)) >= 1);
  await hash('#/products/601');
  await waitFor(`return document.querySelector('.sd-editor__area')`);
  check('description HTML cleaned (no onerror)', (await ev(`return document.querySelectorAll('[onerror]').length`)) === 0);
  check('description keeps headings', (await ev(`return !!document.querySelector('.sd-editor__area h2')`)) === true);
  const priceCard = `[...document.querySelectorAll('.sd-card')].find(c=>c.querySelector('.sd-cols'))`;
  await ev(`const c=${priceCard};const i=c.querySelectorAll('input');i[0].value='2350';i[1].value='2600';i[1].dispatchEvent(new Event('input'));return 1`);
  await click('.sd-savebar .sd-btn--primary');
  await sleep(500);
  check('sale ≥ regular blocked', (await lastToast()).toLowerCase().includes('sale price'));
  await ev(`const c=${priceCard};const i=c.querySelectorAll('input');i[1].value='1999';i[1].dispatchEvent(new Event('input'));return 1`);
  await ev(`const a=document.querySelector('.sd-editor__area');a.focus();document.execCommand('insertText',false,' Extra line');return 1`);
  await click('.sd-savebar .sd-btn--primary');
  await waitFor(`return [...document.querySelectorAll('.sd-toast')].some(t=>t.textContent==='Saved')`);
  check('product saved', (await ev(`return [...document.querySelectorAll('.sd-toast')].some(t=>t.textContent==='Saved')`)) === true);
  const saved = await (await fetch(BASE + '/wp-json/kenakata/v1/sd/products/601', { headers: { 'X-WP-Nonce': 'test-nonce' } })).json().catch(() => ({}));
  check('server got the new sale price', saved.sale_price === '1999', saved.sale_price);
  check('saved description has no event handlers', saved.description && !/onerror|<script/i.test(saved.description), saved.description);
  await shot('05-product-edit');

  console.log('Unsaved changes guard');
  dialogAnswer = false;
  await ev(`const i=document.querySelector('.sd-card input');i.value='Changed name';i.dispatchEvent(new Event('input'));return 1`);
  const before = dialogs.length;
  await hash('#/orders');
  check('leaving asks to confirm', dialogs.length === before + 1 && dialogs[dialogs.length - 1].startsWith('confirm'), dialogs.slice(-1));
  check('cancel keeps the form', (await ev(`return location.hash`)) === '#/products/601' && (await ev(`return document.querySelector('.sd-card input').value`)) === 'Changed name');
  dialogAnswer = true;
  await hash('#/orders');
  check('confirm leaves', (await ev(`return location.hash`)) === '#/orders');

  console.log('New product');
  await hash('#/products/new');
  await waitFor(`return document.querySelector('.sd-savebar')`);
  await click('.sd-savebar .sd-btn--primary');
  await sleep(400);
  check('name required', (await lastToast()) === 'Please enter a name.');
  await setVal('.sd-card input', 'Test Cushion Cover');
  await click('.sd-savebar .sd-btn--primary');
  await sleep(400);
  check('price required', (await lastToast()) === 'Please enter a price.');
  await ev(`const c=${priceCard};const i=c.querySelectorAll('input');i[0].value='450';i[0].dispatchEvent(new Event('input'));return 1`);
  await ev(`const b=[...document.querySelectorAll('.sd-check input')].find(x=>x.value==='50');b.click();return 1`);
  await click('.sd-savebar .sd-btn--primary');
  await waitFor(`return /#\\/products\\/\\d+$/.test(location.hash)`);
  check('new product saved and opened', /#\/products\/\d+$/.test(await ev('return location.hash')), await ev('return location.hash'));

  console.log('Variations');
  await hash('#/products/600');
  await waitFor(`return document.querySelector('.sd-var')`);
  check('variable product shows its options', (await ev(`return document.querySelectorAll('.sd-var').length`)) === 2);
  check('option names shown as chips', (await ev(`return document.querySelectorAll('.sd-chip--val').length`)) === 4);
  await ev(`const v=document.querySelector('.sd-var');const q=v.querySelector('input[type=number]');q.value='9';q.dispatchEvent(new Event('input'));return 1`);
  await click('.sd-savebar .sd-btn--primary');
  await waitFor(`return [...document.querySelectorAll('.sd-toast')].some(t=>t.textContent==='Saved')`);
  check('option stock saved', (await ev(`return document.querySelector('.sd-var input[type=number]').value`)) === '9');

  console.log('New product with options + new category + brand');
  await hash('#/products/new');
  await waitFor(`return document.querySelector('.sd-seg--type')`);
  await setVal('.sd-card input', 'Test Salwar Kameez');
  await ev(`document.querySelectorAll('.sd-seg--type button')[1].click();return 1`);
  check('options editor appears', (await ev(`return !document.querySelector('.sd-opts-card').hidden && document.querySelector('.sd-opts-card .sd-attr input').placeholder.length>0`)) === true);
  await waitFor(`return document.querySelector('.sd-opts-card:not([hidden]) .sd-attr input[list^="sd-attrnames"]') && document.querySelector('.sd-attr__val input')`);
  await sleep(300);
  await setVal('.sd-attr input[list^="sd-attrnames"]', 'Size');
  await sleep(200);
  await ev(`const i=document.querySelector('.sd-attr__val input');i.value='S, M';i.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));return 1`);
  if (!(await ev(`return document.querySelectorAll('.sd-chip--val').length`))) { await sleep(500); await ev(`const i=document.querySelector('.sd-attr__val input');i.value='S, M';i.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));return 1`); }
  await waitFor(`return document.querySelectorAll('.sd-chip--val').length===2`);
  check('values added as chips', (await ev(`return [...document.querySelectorAll('.sd-chip--val span:first-child')].map(x=>x.textContent).join(',')`)) === 'S,M');
  await click('.sd-opts-bar .sd-btn--primary');
  check('combinations made', (await ev(`return document.querySelectorAll('.sd-var').length`)) === 2);
  await click('.sd-savebar .sd-btn--primary');
  await sleep(300);
  check('price required for each option', (await lastToast()).includes('S'));
  await ev(`const d=document.querySelector('.sd-bulk');d.open=true;const i=d.querySelectorAll('input');i[0].value='1800';d.querySelector('.sd-btn').click();return 1`);
  check('same price applied to all options', (await ev(`return [...document.querySelectorAll('.sd-var')].every(v=>v.querySelector('input[inputmode=decimal]').value==='1800')`)) === true);
  await ev(`[...document.querySelectorAll('.sd-newterm__bar .sd-btn')][0].click();return 1`);
  await ev(`const f=document.querySelector('.sd-newterm:not([hidden])');const i=f.querySelector('input');i.value='Salwar';f.querySelector('.sd-btn').click();return 1`);
  await waitFor(`return [...document.querySelectorAll('.sd-check span')].some(s=>s.textContent==='Salwar')`);
  check('new category added and ticked', (await ev(`return [...document.querySelectorAll('.sd-check')].some(l=>l.textContent==='Salwar'&&l.querySelector('input').checked)`)) === true);
  await ev(`const b=[...document.querySelectorAll('.sd-check')].find(l=>l.textContent==='Purple Flora');b.querySelector('input').click();return 1`);
  await click('.sd-savebar .sd-btn--primary');
  await waitFor(`return /#\\/products\\/\\d+$/.test(location.hash)`);
  const vid = (await ev('return location.hash')).split('/').pop();
  const vp = await (await fetch(BASE + '/wp-json/kenakata/v1/sd/products/' + vid, { headers: { 'X-WP-Nonce': 'test-nonce' } })).json().catch(() => ({}));
  check('saved as a product with 2 options', vp.type === 'variable' && vp.variations.length === 2 && vp.variations.every((v) => v.regular_price === '1800'), vp.variations);
  check('brand and new category saved', (vp.brand_ids || []).includes(90) && (vp.category_ids || []).length === 1, [vp.brand_ids, vp.category_ids]);
  await shot('06b-product-options');

  console.log('Print + courier sync');
  await hash('#/orders?status=processing');
  await waitFor(`return document.querySelector('.sd-tools') && document.querySelector('.sd-order')`);
  await ev(`window.__opened=[];window.open=function(u){window.__opened.push(u);return null;};return 1`);
  await ev(`[...document.querySelectorAll('.sd-tools .sd-btn')].find(b=>b.textContent==='Print labels').click();return 1`);
  const opened = await ev(`return window.__opened[0]||''`);
  check('print labels opens the print page with the shown orders', /action=kk_print/.test(opened) && /kind=label/.test(opened) && /ids=\d+(,\d+)*/.test(opened), opened);
  await ev(`[...document.querySelectorAll('.sd-tools .sd-btn')].find(b=>b.textContent==='Sync courier').click();return 1`);
  await waitFor(`return [...document.querySelectorAll('.sd-toast')].some(t=>/Updated 2 orders/.test(t.textContent))`);
  check('sync courier reports what it did', true);
  await hash('#/orders/902');
  await waitFor(`return document.querySelector('.sd-tools .sd-btn')`);
  await ev(`window.__opened=[];window.open=function(u){window.__opened.push(u);return null;};document.querySelector('.sd-tools .sd-btn').click();return 1`);
  check('order screen prints its invoice', /kind=invoice&ids=902$/.test(await ev(`return window.__opened[0]||''`)));

  console.log('Mobile payment check');
  await hash('#/orders?status=on-hold');
  await waitFor(`return document.querySelector('.sd-badge--mp')`);
  check('orders waiting for a payment check are marked', (await ev(`return document.querySelector('.sd-badge--mp').textContent`)) === 'Check payment');
  await hash('#/orders/904');
  await waitFor(`return [...document.querySelectorAll('.sd-card h2, .sd-card h3')].some(x=>/bKash/.test(x.textContent))`);
  check('payment card shows the TrxID', (await ev(`return document.body.textContent.includes('BK7X9QW2LM')`)) === true);
  await ev(`[...document.querySelectorAll('.sd-btn')].find(b=>b.textContent==='Payment received').click();return 1`);
  await waitFor(`return [...document.querySelectorAll('.sd-card h2, .sd-card h3')].some(x=>/bKash · received/.test(x.textContent))`);
  check('payment received → order moves on', (await ev(`return document.querySelector('.sd-badge').textContent`)) === 'Processing');

  console.log('Block a number');
  await hash('#/orders/903');
  await waitFor(`return [...document.querySelectorAll('.sd-btn')].some(b=>b.textContent==='Block this number')`);
  dialogAnswer = true;
  await ev(`[...document.querySelectorAll('.sd-btn')].find(b=>b.textContent==='Block this number').click();return 1`);
  await waitFor(`return document.querySelector('.sd-danger-text')`);
  check('number blocked from the order', (await ev(`return [...document.querySelectorAll('.sd-btn')].some(b=>b.textContent==='Unblock this number')`)) === true);

  console.log('Sales report periods');
  await hash('#/');
  await waitFor(`return document.querySelector('.sd-insight, .sd-insights, [class*=insight]')`);
  await ev(`const b=[...document.querySelectorAll('button')].find(x=>/Sales report/.test(x.textContent));if(b)b.click();return !!b`);
  await waitFor(`return document.querySelector('.sd-seg--wrap')`);
  check('report shows period choices', (await ev(`return document.querySelectorAll('.sd-seg--wrap button').length`)) === 7);
  await ev(`[...document.querySelectorAll('.sd-seg--wrap button')].find(b=>b.textContent==='Last month').click();return 1`);
  await waitFor(`return [...document.querySelectorAll('.sd-seg--wrap button.is-active')].some(b=>b.textContent==='Last month')`);
  check('last month loaded with totals', (await ev(`return document.querySelectorAll('.sd-kpis dd').length`)) >= 3);

  console.log('Blog');
  await hash('#/posts/new');
  await waitFor(`return document.querySelector('.sd-editor__area')`);
  await setVal('.sd-card input', 'How to choose a bedsheet');
  await ev(`const a=document.querySelector('.sd-editor__area');a.focus();document.execCommand('insertText',false,'First paragraph');document.execCommand('insertParagraph');document.execCommand('insertText',false,'Second paragraph');return 1`);
  await click('.sd-savebar .sd-btn--primary');
  await waitFor(`return /#\\/posts\\/\\d+$/.test(location.hash)`);
  const pid = (await ev('return location.hash')).split('/').pop();
  const post = await (await fetch(BASE + '/wp-json/kenakata/v1/sd/posts/' + pid, { headers: { 'X-WP-Nonce': 'test-nonce' } })).json().catch(() => ({}));
  check('post published', post.status === 'publish', post.status);
  check('paragraphs kept (2 × <p>)', (post.content || '').split('<p>').length - 1 === 2, post.content);
  await shot('06-post');

  console.log('Mobile');
  await viewport(true);
  await open(BASE + '/dashboard/#/orders');
  await waitFor(`return document.querySelectorAll('.sd-order').length`);
  check('bottom nav visible', (await ev(`return getComputedStyle(${H('.sd-bottom')}).display`)) === 'flex');
  check('no horizontal scroll', (await ev(`return document.documentElement.scrollWidth<=innerWidth`)) === true, await ev('return [document.documentElement.scrollWidth, innerWidth]'));
  check('orders nav item active', (await ev(`return document.querySelector('.sd-bottom__item.is-active').textContent`)) === 'Orders');
  await shot('07-mobile-orders');
  await ev(`[...document.querySelectorAll('.sd-bottom__item')].pop().click();return 1`);
  await sleep(300);
  check('More opens the menu sheet', (await ev(`return !document.querySelector('.sd-sheet').hidden`)) === true);
  await shot('08-mobile-more');
  await open(BASE + '/dashboard/#/products/601');
  await waitFor(`return document.querySelector('.sd-savebar')`);
  check('mobile: no horizontal scroll on product form', (await ev(`return document.documentElement.scrollWidth<=innerWidth`)) === true);
  await shot('09-mobile-product');
  await open(BASE + '/dashboard/#/orders/902');
  await waitFor(`return document.querySelector('.sd-lines')`);
  check('mobile: order detail fits', (await ev(`return document.documentElement.scrollWidth<=innerWidth`)) === true);
  await shot('10-mobile-order');

  console.log('Store Manager (no admin rights)');
  await open(BASE + '/dashboard/?manager=1');
  await waitFor(`return document.querySelector('.sd-stat')`);
  check('no WordPress admin link', (await ev(`return [...document.querySelectorAll('.sd-side a')].some(a=>a.href.endsWith('/wp-admin/'))`)) === false);
  check('profile & password link', (await ev(`return [...document.querySelectorAll('.sd-side a')].some(a=>a.href.endsWith('/wp-admin/profile.php'))`)) === true);

  console.log('Session ended');
  await viewport(false);
  await open(BASE + '/dashboard/?badnonce=1');
  await waitFor(`return document.querySelector('.sd-error')`);
  check('expired login message', (await textOf('.sd-error p')) === 'Your login has ended. Please log in again.', await textOf('.sd-error p'));

  console.log('Errors');
  const unexpected = dialogs.filter((d) => !d.startsWith('confirm'));
  check('no alert() dialogs (HTML from data never ran)', unexpected.length === 0, unexpected);
  check('no JavaScript errors', errors.length === 0, errors);

  console.log(`\n${pass} passed, ${fail} failed. Screenshots: ${OUT}`);
  ws.close();
  proc.kill();
  if (mock) mock.kill();
  await sleep(300);
  try { fs.rmSync(prof, { recursive: true, force: true }); } catch (e) { /* locked */ }
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error('ERROR', e); if (mock) mock.kill(); process.exit(1); });
