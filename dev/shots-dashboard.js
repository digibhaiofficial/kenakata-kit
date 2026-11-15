// Screenshots of dashboard screens (desktop 1280 and phone 390) from a fresh mock server, optionally in Bengali.
// Usage: node dev/shots-dashboard.js <outDir> [bn]
const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const OUT = process.argv[2] || path.join(os.tmpdir(), 'kk-sd-shots');
const LANG = process.argv[3] === 'bn' ? '?lang=bn' : '';
const SCREENS = ['', 'orders', 'orders/900', 'products', 'products/600', 'products/new', 'posts/new'];
const BROWSER = ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Google/Chrome/Application/chrome.exe'].find((b) => fs.existsSync(b));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const mp = 5400 + Math.floor(Math.random() * 200);
  const mock = spawn(process.execPath, [path.join(__dirname, 'mock-server.js'), String(mp)], { stdio: 'ignore' });
  const port = 9600 + Math.floor(Math.random() * 300);
  const prof = fs.mkdtempSync(path.join(os.tmpdir(), 'kksds-'));
  const proc = spawn(BROWSER, ['--headless=new', '--disable-gpu', '--hide-scrollbars', `--remote-debugging-port=${port}`, `--user-data-dir=${prof}`, 'about:blank'], { stdio: 'ignore' });
  let target;
  for (let i = 0; i < 40 && !target; i++) { await sleep(250); try { target = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find((t) => t.type === 'page'); } catch (e) { /* */ } }
  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((r) => (ws.onopen = r));
  let n = 0; const pend = {};
  ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pend[d.id]) { pend[d.id](d); delete pend[d.id]; } };
  const send = (method, params = {}) => new Promise((r) => { const id = ++n; pend[id] = r; ws.send(JSON.stringify({ id, method, params })); });
  await send('Page.enable');
  for (const mobile of [false, true]) {
    await send('Emulation.setDeviceMetricsOverride', { width: mobile ? 390 : 1280, height: mobile ? 844 : 860, deviceScaleFactor: 1, mobile });
    for (const s of SCREENS) {
      await send('Page.navigate', { url: `http://localhost:${mp}/dashboard/${LANG}#/${s}` });
      await sleep(1600);
      const r = await send('Runtime.evaluate', { expression: 'document.documentElement.scrollWidth > innerWidth', returnByValue: true });
      if (r.result && r.result.result && r.result.result.value) console.log('HORIZONTAL SCROLL:', mobile ? 'phone' : 'desktop', s || 'home');
      const shot = await send('Page.captureScreenshot', { format: 'png' });
      fs.writeFileSync(path.join(OUT, `${mobile ? 'm' : 'd'}-${(s || 'home').replace(/\//g, '_')}.png`), Buffer.from(shot.result.data, 'base64'));
    }
  }
  ws.close(); proc.kill(); mock.kill();
  console.log('saved to ' + OUT);
  process.exit(0);
})();
