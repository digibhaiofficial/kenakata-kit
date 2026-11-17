// Kenakata Kit i18n tool.
//   node dev/i18n/i18n-build.js            -> convert Bengali PHP literals to translatable English, write languages/*.po + .mo
//   node dev/i18n/i18n-build.js --check    -> only report untranslated / unconverted strings
// Uses dev/node_modules/php-parser (or PHP_PARSER_DIR = folder containing node_modules/php-parser).
const fs = require('fs');
const path = require('path');
let Engine;
try { Engine = require(path.join(process.env.PHP_PARSER_DIR || path.join(__dirname, '..'), 'node_modules', 'php-parser')); } catch (e) { console.error('php-parser is missing. Run once:  cd dev && npm install   (it is not in the repository: dev/node_modules is ignored by git) (or set PHP_PARSER_DIR)'); process.exit(2); }
const dict = require('./i18n-dict.js');
const extra = fs.readdirSync(__dirname).filter((f) => /^i18n-(?!dict|build).*\.js$/.test(f)).sort().flatMap((f) => require('./' + f)); // i18n-frontend.js, i18n-v32.js, …

const ROOT = path.join(__dirname, '..', '..', 'kenakata-kit'); // the plugin folder
const DOMAIN = 'kenakata-kit';
const CHECK = process.argv.includes('--check');
const NO_CONVERT = process.argv.includes('--no-convert'); // only write .po/.mo, never touch PHP files
const BN = /[ঀ-৲৴-৿]/; // Bengali letters (not the ৳ sign alone)
const I18N = new Set(['__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e', '_x', 'esc_html_x', 'esc_attr_x', '_n', '_n_noop', '_nx']);

const bn2en = new Map();
const en2bn = new Map(); // en -> {bn, plural}
for (const [bn, en] of dict) if (!bn2en.has(bn)) bn2en.set(bn, en);
for (const [bn, en, plural] of [...extra, ...dict]) if (!en2bn.has(en)) en2bn.set(en, { bn, plural });

const parser = new Engine({ parser: { php8: true }, lexer: { all_tokens: true } });
const phpQuote = (s) => "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
const unquote = (lit) => {
  if (lit[0] === "'") return lit.slice(1, -1).replace(/\\'/g, "'").replace(/\\\\/g, '\\');
  return lit.slice(1, -1).replace(/\\"/g, '"').replace(/\\n/g, '\n').replace(/\\\\/g, '\\');
};

const files = [];
(function walk(d) {
  for (const f of fs.readdirSync(d)) {
    const p = path.join(d, f);
    if (fs.statSync(p).isDirectory()) { if (!['tools', 'docs', 'languages', 'assets'].includes(f)) walk(p); }
    else if (p.endsWith('.php')) files.push(p);
  }
})(ROOT);

const unknown = new Set();
for (const file of files) {
  const src = fs.readFileSync(file, 'utf8');
  const toks = parser.tokenGetAll(src);
  let out = '';
  let changed = 0;
  for (let i = 0; i < toks.length; i++) {
    const t = toks[i];
    if (!Array.isArray(t)) { out += t; continue; }
    const [type, val] = t;
    if (type === 'T_CONSTANT_ENCAPSED_STRING' && BN.test(val)) {
      const s = unquote(val);
      if (!bn2en.has(s)) { unknown.add(path.relative(ROOT, file) + ': ' + s); out += val; continue; }
      const en = bn2en.get(s);
      // Already inside an i18n call?  __( '…'
      let j = i - 1; while (j >= 0 && Array.isArray(toks[j]) && toks[j][0] === 'T_WHITESPACE') j--;
      let k = j - 1; while (k >= 0 && Array.isArray(toks[k]) && toks[k][0] === 'T_WHITESPACE') k--;
      const inCall = toks[j] === '(' && Array.isArray(toks[k]) && toks[k][0] === 'T_STRING' && I18N.has(toks[k][1]);
      out += inCall ? phpQuote(en) : `__( ${phpQuote(en)}, '${DOMAIN}' )`;
      changed++;
      continue;
    }
    out += val;
  }
  if (changed && !CHECK && !NO_CONVERT) fs.writeFileSync(file, out);
  if (changed) console.log((CHECK ? 'would convert ' : 'converted ') + changed + ' in ' + path.relative(ROOT, file));
}
if (unknown.size) { console.log('\nBengali literals without a translation (left as they are):'); for (const u of unknown) console.log('  ' + u); }

// ---- collect msgids from the (converted) code ----
const ids = new Map(); // msgid -> {plural, refs}
for (const file of files) {
  const toks = parser.tokenGetAll(fs.readFileSync(file, 'utf8')).filter((t) => !(Array.isArray(t) && ['T_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT'].includes(t[0])));
  for (let i = 0; i < toks.length - 2; i++) {
    const t = toks[i];
    if (!Array.isArray(t) || t[0] !== 'T_STRING' || !I18N.has(t[1]) || toks[i + 1] !== '(') continue;
    const a = toks[i + 2];
    if (!Array.isArray(a) || a[0] !== 'T_CONSTANT_ENCAPSED_STRING') continue;
    const id = unquote(a[1]);
    let plural = null;
    if (['_n', '_n_noop', '_nx'].includes(t[1]) && toks[i + 3] === ',' && Array.isArray(toks[i + 4])) plural = unquote(toks[i + 4][1]);
    const ref = path.relative(ROOT, file).replace(/\\/g, '/') + ':' + t[2];
    if (!ids.has(id)) ids.set(id, { plural, refs: [] });
    ids.get(id).refs.push(ref);
  }
}
const missing = [...ids.keys()].filter((id) => !en2bn.has(id));
console.log(`\nmsgids: ${ids.size}, translated: ${ids.size - missing.length}, missing: ${missing.length}`);
if (missing.length) { console.log('Missing Bengali translations:'); for (const m of missing) console.log('  ' + JSON.stringify(m)); }
if (CHECK) process.exit(0);

// ---- write .po / .mo ----
const poEsc = (s) => s.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n');
const header = 'Project-Id-Version: Kenakata Kit\nLanguage: bn_BD\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\nPlural-Forms: nplurals=2; plural=(n != 1);\nX-Domain: kenakata-kit\n';
let po = `# Bengali translation for Kenakata Kit.\nmsgid ""\nmsgstr ""\n` + header.split('\n').filter(Boolean).map((l) => `"${poEsc(l)}\\n"`).join('\n') + '\n';
const entries = [];
for (const [id, info] of [...ids.entries()].sort((a, b) => a[0].localeCompare(b[0]))) {
  const tr = en2bn.get(id);
  if (!tr) continue;
  po += `\n#: ${info.refs.slice(0, 3).join(' ')}\nmsgid "${poEsc(id)}"\n`;
  if (info.plural) {
    po += `msgid_plural "${poEsc(info.plural)}"\nmsgstr[0] "${poEsc(tr.bn)}"\nmsgstr[1] "${poEsc(tr.bn)}"\n`;
    entries.push([id + '\u0000' + info.plural, tr.bn + '\u0000' + tr.bn]);
  } else {
    po += `msgstr "${poEsc(tr.bn)}"\n`;
    entries.push([id, tr.bn]);
  }
}
const langDir = path.join(ROOT, 'languages');
fs.mkdirSync(langDir, { recursive: true });
fs.writeFileSync(path.join(langDir, 'kenakata-kit-bn_BD.po'), po);

// GNU .mo writer
entries.unshift(['', header]);
entries.sort((a, b) => (Buffer.from(a[0]) < Buffer.from(b[0]) ? -1 : Buffer.from(a[0]) > Buffer.from(b[0]) ? 1 : 0));
const n = entries.length;
const keys = entries.map((e) => Buffer.from(e[0], 'utf8'));
const vals = entries.map((e) => Buffer.from(e[1], 'utf8'));
const headerSize = 28;
const origTable = headerSize, transTable = origTable + n * 8;
let offset = transTable + n * 8;
const origOffsets = [], transOffsets = [];
for (const k of keys) { origOffsets.push([k.length, offset]); offset += k.length + 1; }
for (const v of vals) { transOffsets.push([v.length, offset]); offset += v.length + 1; }
const buf = Buffer.alloc(offset);
buf.writeUInt32LE(0x950412de, 0); buf.writeUInt32LE(0, 4); buf.writeUInt32LE(n, 8);
buf.writeUInt32LE(origTable, 12); buf.writeUInt32LE(transTable, 16); buf.writeUInt32LE(0, 20); buf.writeUInt32LE(transTable + n * 8, 24); // empty hash table placed right after the tables (old WP MO reader needs this offset)
origOffsets.forEach(([len, off], i) => { buf.writeUInt32LE(len, origTable + i * 8); buf.writeUInt32LE(off, origTable + i * 8 + 4); keys[i].copy(buf, off); });
transOffsets.forEach(([len, off], i) => { buf.writeUInt32LE(len, transTable + i * 8); buf.writeUInt32LE(off, transTable + i * 8 + 4); vals[i].copy(buf, off); });
fs.writeFileSync(path.join(langDir, 'kenakata-kit-bn_BD.mo'), buf);
console.log(`wrote languages/kenakata-kit-bn_BD.po and .mo (${n - 1} strings)`);
