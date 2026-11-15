// Mock WordPress for testing the store dashboard UI without a site: serves /dashboard (like kk_sd_render_page),
// the plugin's dashboard CSS/JS, and an in-memory /wp-json/kenakata/v1/sd/* API with sample data.
// Also checks that every t('key') used in store-dashboard.js exists in kk_sd_strings() (PHP).
// Usage: node dev/mock-server.js [port]   → http://localhost:8765/dashboard/
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = Number(process.argv[2] || 5187);
const PLUGIN = path.join(__dirname, '..', 'kenakata-kit');
const php = fs.readFileSync(path.join(PLUGIN, 'includes', 'store-dashboard.php'), 'utf8');
const jsSrc = fs.readFileSync(path.join(PLUGIN, 'assets', 'store-dashboard.js'), 'utf8');

// Strings from PHP: 'key' => __( 'Text', 'kenakata-kit' ),
const i18n = {};
for (const m of php.matchAll(/'(\w+)'\s*=>\s*__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'kenakata-kit'\s*\)/g)) i18n[m[1]] = m[2].replace(/\\'/g, "'");
const used = new Set([...jsSrc.matchAll(/\bt\('(\w+)'\)/g)].map((m) => m[1]));
const statusKeys = ['published', 'draft', 'pending', 'private', 'scheduled', 'today', 'last7', 'last30']; // used via t(variable)
const missing = [...used, ...statusKeys].filter((k) => !(k in i18n));
console.log(missing.length ? 'MISSING STRINGS: ' + missing.join(', ') : `strings ok (${used.size} used, ${Object.keys(i18n).length} defined)`);

// ?lang=bn → the Bengali translations from dev/i18n (to check the layout with longer Bengali text).
const bn = {};
// Same priority as dev/i18n/i18n-build.js: the newer files first, i18n-dict.js last.
for (const f of fs.readdirSync(path.join(__dirname, 'i18n')).filter((x) => /^i18n-(?!build|dict).*\.js$/.test(x)).sort().concat(['i18n-dict.js'])) {
  for (const [b, e] of require(path.join(__dirname, 'i18n', f))) if (!(e in bn)) bn[e] = b;
}
const i18nBn = Object.fromEntries(Object.entries(i18n).map(([k, v]) => [k, bn[v] || v]));
const untranslated = Object.entries(i18n).filter(([, v]) => !bn[v]).map(([k]) => k);
if (untranslated.length) console.log('NO BENGALI FOR: ' + untranslated.join(', '));

const money = (n) => '৳\u00a0' + Number(n).toLocaleString('en-US');
const now = Math.floor(Date.now() / 1000);
const STATUSES = { pending: 'Pending payment', processing: 'Processing', shipped: 'Shipped', 'on-hold': 'On hold', completed: 'Completed', cancelled: 'Cancelled', refunded: 'Refunded', failed: 'Failed' };
const COURIERS = { steadfast: 'Steadfast Courier', pathao: 'Pathao Courier', redx: 'RedX' };
const IMG = (c) => `data:image/svg+xml;utf8,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300"><rect width="300" height="300" fill="${c}"/></svg>`)}`;

const names = ['Nusrat Jahan', 'Rafiq Ahmed', 'Sumaiya Akter', 'Tanvir Hasan', 'Farhana Rahman', 'Mahmud Karim', 'Ayesha Siddika', 'Imran Hossain'];
const orders = [];
for (let i = 0; i < 27; i++) {
  const st = ['processing', 'processing', 'shipped', 'completed', 'on-hold', 'cancelled', 'completed', 'processing'][i % 8];
  const qty = 1 + (i % 2);
  orders.push({
    id: 900 + i, number: String(900 + i), date: { ts: now - i * 5400, text: new Date((now - i * 5400) * 1000).toLocaleString('en-GB') },
    status: st, status_label: STATUSES[st], total: money(2350 * qty + 80), name: names[i % names.length], phone: '0171' + String(2345670 + i),
    wa: 'https://wa.me/880171' + String(2345670 + i), area: i % 3 ? 'Inside Dhaka' : 'Outside Dhaka', items: [`Rose Garden Embroidered Bedsheet Set - Cream × ${qty}`],
    items_count: qty, payment: 'Cash on delivery', courier: '', tracking_id: '', courier_key: '', tracking_url: '',
    lines: [{ name: 'Rose Garden Embroidered Bedsheet Set - Cream, Standard Set', qty, total: money(2350 * qty), meta: 'Color: Cream, Set: Standard Set', sku: 'PF-RG-01', image: IMG('#e8d9c4'), product_id: 623 }],
    totals: { subtotal: money(2350 * qty), shipping: money(80), shipping_method: 'Inside Dhaka', discount: '', refunded: '', total: money(2350 * qty + 80), to_collect: st === 'completed' ? '' : money(2350 * qty + 80), cod: true },
    address: 'House 12, Road 5, Dhanmondi, Dhaka', email: i % 2 ? '' : 'customer@example.com', customer_note: i === 0 ? 'Please call before delivery <script>alert(1)</script>' : '',
    notes: [{ content: 'Order placed.', date: { ts: now - i * 5400, text: 'today' }, customer: false, by: '' }],
    history: { total: 1 + (i % 4), completed: i % 3, cancelled: i % 4 === 3 ? 1 : 0 }, edit_url: 'http://localhost:' + PORT + '/wp-admin/post.php?post=' + (900 + i),
  });
}
orders[4].payment = 'bKash / Nagad / Rocket'; orders[4].mp_wait = true;
orders[4].mpay = { wallet: 'bKash', to: '01728366779', sender: '01812345678', trx: 'BK7X9QW2LM', amount: 80, amount_text: money(80), advance: true, shot: '', state: 'wait' };
const cats = [{ id: 47, name: 'Bedroom & Bedding', depth: 0, count: 12 }, { id: 48, name: 'Bedsheets', depth: 1, count: 9 }, { id: 50, name: 'Cushion Covers', depth: 1, count: 1 }, { id: 52, name: 'Table Linen', depth: 0, count: 3 }];
let nextId = 2000;
const brands = [{ id: 90, name: 'Purple Flora', depth: 0, count: 5 }];
const attrSugg = [{ name: 'Size', values: ['S', 'M', 'L', 'XL'], global: true }, { name: 'Color', values: ['Cream', 'Lavender', 'Red'], global: true }];
function saveOptions(p, body) {
  if (!Array.isArray(body.attributes)) return '';
  p.type = 'variable'; p.attributes = body.attributes.map((a) => ({ name: a.name, values: a.values, variation: true, global: false }));
  const keep = [];
  (body.variations || []).forEach((r) => {
    if (r.remove) return;
    let v = r.id ? p.variations.find((x) => x.id === r.id) : null;
    if (!v) v = { id: nextId++, image: '', sku: '', status: 'publish', image_id: 0 };
    v.options = r.options; v.label = Object.keys(r.options).map((k) => k + ': ' + r.options[k]).join(', ');
    const e = applyPriceStock(v, r); if (e) throw new Error(e);
    keep.push(v);
  });
  p.variations = keep; p.regular_price = ''; p.manage_stock = false; return '';
}
const products = [];
const pnames = ['Tulip', 'Royal Elegance', 'Rose Garden', 'Orchid', 'Lily', 'Joypuri', 'Floral Romance', 'Blossom Bunch', 'Aurora Nest'];
pnames.forEach((n, i) => {
  const variable = i % 3 === 0;
  products.push({
    id: 600 + i, type: variable ? 'variable' : 'simple', editable_type: true, name: `${n} Embroidered Bedsheet Set`, status: i === 8 ? 'draft' : 'publish', sku: 'PF-' + (600 + i),
    regular_price: variable ? '' : '2350', sale_price: i === 1 ? '2150' : '', manage_stock: !variable, stock_quantity: variable ? '' : [12, 2, 0, 7, 3, 15, 1, 9, 4][i], stock_status: i === 2 ? 'outofstock' : 'instock',
    short_description: '<ul><li>King size: 8.5 ft x 7.5 ft</li><li>1 bedsheet + 2 pillow covers</li></ul>', description: `<p>The <strong>${n}</strong> set.</p><h2>Styling ideas</h2><p>Pairs well with wood.</p><p><img src="x" onerror="alert(2)"></p>`,
    category_ids: [48], images: [{ id: 700 + i, url: IMG(['#e8d9c4', '#d9c6e8', '#c4d9e8'][i % 3]) }], permalink: 'http://localhost:' + PORT + '/product/' + i,
    variations: variable ? [{ id: 800 + i * 3, label: 'Color: Cream, Set: Standard Set', options: { Color: 'Cream', Set: 'Standard Set' }, image_id: 0, image: '', sku: '', regular_price: '2350', sale_price: '', manage_stock: true, stock_quantity: 5, stock_status: 'instock', status: 'publish' },
      { id: 801 + i * 3, label: 'Color: Lavender, Set: With Side Pillow Covers', options: { Color: 'Lavender', Set: 'With Side Pillow Covers' }, image_id: 0, image: '', sku: '', regular_price: '2650', sale_price: '', manage_stock: false, stock_quantity: '', stock_status: 'outofstock', status: 'publish' }] : [],
    attributes: variable ? [{ name: 'Color', values: ['Cream', 'Lavender'], variation: true, global: true }, { name: 'Set', values: ['Standard Set', 'With Side Pillow Covers'], variation: true, global: false }] : [],
    brand_ids: [],
    edit_url: '',
  });
});
const posts = [{ id: 1, title: 'Hello world!', title_raw: 'Hello world!', status: 'publish', date: { ts: now - 86400 * 40, text: '10 Sep 2026' }, image: '', link: 'http://localhost/', content: '<p>Welcome to WordPress.</p>', excerpt: '', featured_id: 0, featured: '', edit_url: '' }];
const media = {};

function priceOf(p) {
  if (p.type === 'variable') { const ps = p.variations.map((v) => Number(v.sale_price || v.regular_price)); return { price: ps.length ? money(Math.min(...ps)) + ' – ' + money(Math.max(...ps)) : '', regular: '' }; }
  if (p.regular_price === '') return { price: '', regular: '' };
  return { price: money(p.sale_price || p.regular_price), regular: p.sale_price ? money(p.regular_price) : '' };
}
function stockLabel(p) { return p.manage_stock && p.type !== 'variable' ? (Number(p.stock_quantity) > 0 ? `${p.stock_quantity} in stock` : 'Out of stock') : ({ instock: 'In stock', outofstock: 'Out of stock', onbackorder: 'On backorder' })[p.stock_status]; }
function brief(p) { return { id: p.id, edit_id: p.id, name: p.name, type: p.type, status: p.status, image: p.images[0] ? p.images[0].url : '', sku: p.sku, stock_status: p.stock_status, stock_label: stockLabel(p), variations: p.variations.length, ...priceOf(p) }; }
function page(list, q) { const per = 20; const pg = Math.max(1, Number(q.get('page') || 1)); return { items: list.slice((pg - 1) * per, pg * per), total: list.length, pages: Math.ceil(list.length / per), page: pg }; }
function err(res, status, code, message) { send(res, status, { code, message, data: { status } }); }
function send(res, status, data) { res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8' }); res.end(JSON.stringify(data)); }
function applyPriceStock(p, b) {
  if ('regular_price' in b) {
    if (b.sale_price !== '' && (b.regular_price === '' || Number(b.sale_price) >= Number(b.regular_price))) return 'The sale price must be lower than the regular price.';
    p.regular_price = b.regular_price; p.sale_price = b.sale_price;
  }
  if ('manage_stock' in b) { p.manage_stock = !!b.manage_stock; if (p.manage_stock) { p.stock_quantity = Number(b.stock_quantity) || 0; p.stock_status = p.stock_quantity > 0 ? 'instock' : 'outofstock'; } else { p.stock_status = b.stock_status; p.stock_quantity = ''; } }
  return '';
}

const log = [];
function api(req, res, route, q, body) {
  log.push(req.method + ' ' + route + (body ? ' ' + JSON.stringify(body).slice(0, 160) : ''));
  if (req.headers['x-wp-nonce'] !== 'test-nonce') return err(res, 403, 'rest_cookie_invalid_nonce', 'Cookie check failed');
  let m;
  if (route === 'summary') {
    const counts = {}; Object.keys(STATUSES).forEach((k) => { counts[k] = orders.filter((o) => o.status === k).length; });
    return send(res, 200, { periods: { today: { orders: 4, sales: money(9480) }, last7: { orders: 19, sales: money(45210) }, last30: { orders: 27, sales: money(66300) } }, counts,
      low_stock: products.filter((p) => p.manage_stock && p.stock_quantity > 0 && p.stock_quantity <= 3).length, out_stock: products.filter((p) => p.stock_status === 'outofstock').length });
  }
  if (route === 'orders' && req.method === 'GET') {
    const st = q.get('status'); const s = (q.get('search') || '').toLowerCase();
    return send(res, 200, page(orders.filter((o) => (!st || o.status === st) && (!s || o.number.includes(s) || o.name.toLowerCase().includes(s) || o.phone.includes(s))), q));
  }
  if ((m = route.match(/^orders\/(\d+)\/mpay$/)) && req.method === 'POST') {
    const o = orders.find((x) => x.id === Number(m[1])); if (!o || !o.mpay) return err(res, 400, 'kk_mp_not', 'Not a mobile payment.');
    o.mpay.state = body.decision === 'yes' ? 'yes' : 'no'; o.mp_wait = false;
    if (body.decision === 'yes') { o.status = 'processing'; o.status_label = STATUSES.processing; }
    return send(res, 200, o);
  }
  if ((m = route.match(/^orders\/(\d+)$/))) {
    const o = orders.find((x) => x.id === Number(m[1])); if (!o) return err(res, 404, 'kk_sd_not_found', 'Not found.');
    if (req.method === 'POST') {
      if ('tracking_url' in body) { if (body.tracking_url && !/^https?:\/\//.test(body.tracking_url)) return err(res, 400, 'kk_sd_bad_url', 'Please enter a full link starting with https://'); o.courier_key = body.courier; o.courier = COURIERS[body.courier] || ''; o.tracking_id = body.tracking_id; if (body.tracking_url && body.tracking_url !== o.tracking_url && o.status === 'processing') { o.status = 'shipped'; o.status_label = STATUSES.shipped; o.notes.unshift({ content: 'Your parcel has been handed to ' + (o.courier || 'our courier partner') + '. Track it here: ' + body.tracking_url, date: { ts: now, text: 'now' }, customer: true, by: 'Shop Owner' }); } o.tracking_url = body.tracking_url; }
      if (body.status) { if (!STATUSES[body.status]) return err(res, 400, 'kk_sd_bad_status', 'Unknown order status.'); if (o.status !== body.status) { o.notes.unshift({ content: `Order status changed from ${o.status_label} to ${STATUSES[body.status]}.`, date: { ts: now, text: 'now' }, customer: false, by: 'Shop Owner' }); o.status = body.status; o.status_label = STATUSES[body.status]; } }
      if (body.note) o.notes.unshift({ content: body.note, date: { ts: now, text: 'now' }, customer: !!body.note_customer, by: 'Shop Owner' });
    }
    return send(res, 200, o);
  }
  if (route === 'products' && req.method === 'GET') {
    const f = q.get('filter'); const s = (q.get('search') || '').toLowerCase();
    let list = products;
    if (f === 'low') list = products.filter((p) => p.manage_stock && p.stock_quantity > 0 && p.stock_quantity <= 3);
    else if (f === 'out') list = products.filter((p) => p.stock_status === 'outofstock');
    else if (f === 'draft') list = products.filter((p) => p.status !== 'publish');
    if (s) list = list.filter((p) => p.name.toLowerCase().includes(s) || p.sku.toLowerCase() === s);
    return send(res, 200, page(list.map(brief), q));
  }
  if (route === 'products' && req.method === 'POST') {
    if (!body.name) return err(res, 400, 'kk_sd_no_name', 'Please enter a name.');
    if (body.type !== 'variable' && (body.regular_price === '' || body.regular_price === undefined)) return err(res, 400, 'kk_sd_no_price', 'Please enter a price.');
    const p = { id: nextId++, type: 'simple', editable_type: true, name: body.name, status: body.status, sku: body.sku || '', regular_price: '', sale_price: '', manage_stock: false, stock_quantity: '', stock_status: 'instock', short_description: body.short_description, description: body.description, category_ids: body.category_ids, brand_ids: body.brand_ids || [], attributes: [], images: body.image_ids.map((id) => media[id] || { id, url: IMG('#ccc') }), permalink: '', variations: [], edit_url: '' };
    if (body.type === 'variable') { try { saveOptions(p, body); } catch (x) { return err(res, 400, 'kk_sd_bad_sale', x.message); } }
    else { const e = applyPriceStock(p, body); if (e) return err(res, 400, 'kk_sd_bad_sale', e); }
    products.unshift(p); return send(res, 200, p);
  }
  if ((m = route.match(/^products\/(\d+)$/))) {
    const p = products.find((x) => x.id === Number(m[1])); if (!p) return err(res, 404, 'kk_sd_not_found', 'Not found.');
    if (req.method === 'POST') {
      if ('name' in body && !body.name) return err(res, 400, 'kk_sd_no_name', 'Please enter a name.');
      if (body.type === 'variable') { try { saveOptions(p, body); } catch (x) { return err(res, 400, 'kk_sd_bad_sale', x.message); } }
      else if (p.type !== 'variable') { const e = applyPriceStock(p, body); if (e) return err(res, 400, 'kk_sd_bad_sale', e); }
      if (body.brand_ids) p.brand_ids = body.brand_ids;
      Object.assign(p, { name: body.name, status: body.status, sku: body.sku, short_description: body.short_description, description: body.description, category_ids: body.category_ids, images: body.image_ids.map((id) => media[id] || p.images.find((i) => i.id === id) || { id, url: IMG('#ccc') }) });
    }
    return send(res, 200, p);
  }
  if ((m = route.match(/^variations\/(\d+)$/))) {
    const p = products.find((x) => x.variations.some((v) => v.id === Number(m[1]))); if (!p) return err(res, 404, 'kk_sd_not_found', 'Not found.');
    const v = p.variations.find((x) => x.id === Number(m[1])); const e = applyPriceStock(v, body); if (e) return err(res, 400, 'kk_sd_bad_sale', e);
    return send(res, 200, p);
  }
  if (route === 'categories') return send(res, 200, cats);
  if (route === 'brands') return send(res, 200, brands);
  if (route === 'courier-sync' && req.method === 'POST') return send(res, 200, { done: 2, skipped: 1, errors: [] });
  if (route === 'attributes') return send(res, 200, attrSugg);
  if (route === 'terms' && req.method === 'POST') {
    if (!body.name) return err(res, 400, 'kk_sd_no_name', 'Please enter a name.');
    const list = body.tax === 'brand' ? brands : cats; const have = list.find((x) => x.name.toLowerCase() === body.name.toLowerCase());
    if (have) return send(res, 200, { id: have.id, name: have.name, existed: true, list });
    const t = { id: nextId++, name: body.name, depth: body.parent && body.parent !== '0' ? 1 : 0, count: 0 }; list.push(t);
    return send(res, 200, { id: t.id, name: t.name, existed: false, list });
  }
  if (route === 'report/range') {
    const from = q.get('from'), to = q.get('to'); const days = Math.round((new Date(to) - new Date(from)) / 864e5) + 1;
    const series = []; for (let i = 0; i < days; i++) { const d = new Date(new Date(from).getTime() + i * 864e5).toISOString().slice(0, 10); series.push({ d, orders: i % 3, sales: (i % 3) * 1200, text: money((i % 3) * 1200) }); }
    const orders = series.reduce((a, r) => a + r.orders, 0), sales = series.reduce((a, r) => a + r.sales, 0);
    return send(res, 200, { from, to, monthly: false, series, orders, items: orders * 2, sales: money(sales), avg: money(orders ? sales / orders : 0), truncated: false });
  }
  if (route === 'media' && req.method === 'POST') { const id = nextId++; media[id] = { id, url: IMG('#9b7bb5') }; return send(res, 200, media[id]); }
  if (route === 'posts' && req.method === 'GET') return send(res, 200, page(posts, q));
  if (route === 'posts' && req.method === 'POST') { if (!body.title) return err(res, 400, 'kk_sd_no_title', 'Please enter a title.'); const p = { id: nextId++, title: body.title, title_raw: body.title, status: body.status, date: { ts: now, text: 'today' }, image: body.featured_id ? (media[body.featured_id] || {}).url : '', link: body.status === 'publish' ? 'http://localhost/p' : '', content: body.content, excerpt: body.excerpt, featured_id: body.featured_id, featured: body.featured_id ? (media[body.featured_id] || {}).url : '', edit_url: '' }; posts.unshift(p); return send(res, 200, p); }
  if ((m = route.match(/^posts\/(\d+)$/))) {
    const p = posts.find((x) => x.id === Number(m[1])); if (!p) return err(res, 404, 'kk_sd_not_found', 'Not found.');
    if (req.method === 'POST') Object.assign(p, { title: body.title, title_raw: body.title, status: body.status, content: body.content, excerpt: body.excerpt, featured_id: body.featured_id });
    return send(res, 200, p);
  }
  return err(res, 404, 'rest_no_route', 'No route was found matching the URL and request method.');
}

function page404(res) { res.writeHead(404); res.end('not found'); }
http.createServer((req, res) => {
  const u = new URL(req.url, 'http://localhost');
  if (u.pathname === '/__log') { send(res, 200, log); return; }
  if (u.pathname.startsWith('/wp-json/kenakata/v1/sd/')) {
    const route = u.pathname.replace('/wp-json/kenakata/v1/sd/', '');
    if (req.method === 'POST' && (req.headers['content-type'] || '').startsWith('multipart/')) { req.resume(); req.on('end', () => api(req, res, route, u.searchParams, {})); return; }
    let raw = ''; req.on('data', (c) => { raw += c; }); req.on('end', () => { let body = {}; try { body = raw ? JSON.parse(raw) : {}; } catch (e) { body = {}; } api(req, res, route, u.searchParams, body); });
    return;
  }
  if (u.pathname.startsWith('/assets/')) {
    const f = path.join(PLUGIN, 'assets', path.basename(u.pathname));
    if (!fs.existsSync(f)) return page404(res);
    res.writeHead(200, { 'Content-Type': f.endsWith('.css') ? 'text/css' : 'application/javascript', 'Cache-Control': 'no-store' });
    res.end(fs.readFileSync(f)); return;
  }
  if (u.pathname === '/dashboard/' || u.pathname === '/dashboard') {
    const cfg = {
      rest: `http://localhost:${PORT}/wp-json/kenakata/v1/sd/`, nonce: u.searchParams.get('badnonce') ? 'x' : 'test-nonce', site: 'Purple Flora', home: `http://localhost:${PORT}/`, logo: '',
      user: { name: 'Shop Owner' }, caps: { orders: true, products: true, publishProduct: true, posts: true, publishPost: true, upload: true, terms: true, money: true, admin: !u.searchParams.get('manager') }, brands: true, maxVariations: 100, print: '/wp-admin/admin-post.php?action=kk_print&_wpnonce=abc', courierSync: true,
      adminUrl: u.searchParams.get('manager') ? '' : `http://localhost:${PORT}/wp-admin/`, logoutUrl: `http://localhost:${PORT}/wp-login.php?action=logout`, profileUrl: `http://localhost:${PORT}/wp-admin/profile.php`, statuses: STATUSES, couriers: COURIERS, currency: '৳', lowStock: 3, i18n: u.searchParams.get('lang') === 'bn' ? i18nBn : i18n,
    };
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' });
    res.end(`<!doctype html><html lang="en-US"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title>Store dashboard</title>
<link rel="stylesheet" href="/assets/store-dashboard.css"><style>:root{--sd-brand:#471E60}</style></head><body>
<div id="sd-app" class="sd" aria-live="polite"><p class="sd-boot">Loading…</p></div>
<script>window.kkSD = ${JSON.stringify(cfg).replace(/</g, '\\u003c')};</script><script src="/assets/store-dashboard.js" defer></script></body></html>`);
    return;
  }
  page404(res);
}).listen(PORT, '127.0.0.1', () => console.log('mock dashboard on http://localhost:' + PORT + '/dashboard/'));
