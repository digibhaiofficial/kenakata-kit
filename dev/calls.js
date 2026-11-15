// Static check: lists every function a PHP file calls that is not defined inside the plugin, and every
// method called, so they can be checked against WordPress/WooCommerce. Also flags functions defined twice.
// Usage: node dev/calls.js includes/store-dashboard.php includes/store-dashboard-api.php
const fs = require('fs');
const path = require('path');
const Parser = require(path.join(__dirname, 'node_modules', 'php-parser'));
const root = path.join(__dirname, '..', 'kenakata-kit');
const parser = new Parser({ parser: { php7: true, php8: true }, ast: { withPositions: true } });

function walk(node, fn) {
  if (!node || typeof node !== 'object') return;
  if (Array.isArray(node)) { node.forEach((n) => walk(n, fn)); return; }
  if (node.kind) fn(node);
  for (const k of Object.keys(node)) if (k !== 'loc' && k !== 'leadingComments') walk(node[k], fn);
}
const all = [];
(function scan(d) { for (const f of fs.readdirSync(d)) { const p = path.join(d, f); fs.statSync(p).isDirectory() ? scan(p) : p.endsWith('.php') && all.push(p); } })(root);

const defined = new Map();
for (const f of all) {
  walk(parser.parseCode(fs.readFileSync(f, 'utf8'), f), (n) => {
    if (n.kind === 'function' && n.name) {
      const name = (n.name.name || n.name).toLowerCase();
      if (defined.has(name)) console.log('DEFINED TWICE', name, path.relative(root, defined.get(name)), path.relative(root, f));
      defined.set(name, f);
    }
  });
}
const targets = process.argv.slice(2).map((f) => path.join(root, f));
const ext = new Map();
const methods = new Map();
for (const f of targets) {
  walk(parser.parseCode(fs.readFileSync(f, 'utf8'), f), (n) => {
    if (n.kind === 'call' && n.what) {
      if (n.what.kind === 'name') {
        const name = n.what.name.replace(/^\\/, '');
        if (!defined.has(name.toLowerCase())) ext.set(name, (ext.get(name) || 0) + 1);
      } else if (n.what.kind === 'propertylookup' || n.what.kind === 'staticlookup' || n.what.kind === 'nullsafepropertylookup') {
        const m = n.what.offset && (n.what.offset.name || n.what.offset.value);
        const obj = n.what.what && (n.what.what.name || n.what.what.kind);
        if (m) methods.set(`${typeof obj === 'string' ? obj : '?'}->${m}`, 1);
      }
    }
  });
}
console.log('EXTERNAL FUNCTIONS (' + ext.size + '):\n' + [...ext.keys()].sort().join(', '));
console.log('\nMETHODS (' + methods.size + '):\n' + [...methods.keys()].sort().join(', '));
