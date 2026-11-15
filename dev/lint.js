// Syntax check for the plugin: every PHP file (php-parser, PHP 7.4–8.x grammar) and every JS file (node --check).
// Usage: node dev/lint.js          (run from the kenakata-kit project folder or anywhere)
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
// php-parser (vendored in dev/node_modules, or `npm install` in dev/); without it, `php -l` is used if PHP is installed.
let Parser = null;
try { Parser = require(path.join(__dirname, 'node_modules', 'php-parser')); } catch (e) { Parser = null; }

const root = path.join(__dirname, '..', 'kenakata-kit');
const parser = Parser ? new Parser({ parser: { php7: true, php8: true, extractDoc: false }, ast: { withPositions: true } }) : null;
const phpCheck = (f) => {
  if (parser) { parser.parseCode(fs.readFileSync(f, 'utf8'), f); return; }
  try { execFileSync('php', ['-l', f], { stdio: 'pipe' }); } catch (e) {
    if (e.code === 'ENOENT') { console.log('No php-parser (run npm install in dev/) and no php: PHP files not checked'); process.exit(2); }
    throw new Error(String(e.stdout || e.stderr).trim().split('\n')[0]);
  }
};
const php = [];
const js = [];
(function walk(d) {
  for (const f of fs.readdirSync(d)) {
    const p = path.join(d, f);
    if (fs.statSync(p).isDirectory()) walk(p);
    else if (p.endsWith('.php')) php.push(p);
    else if (p.endsWith('.js')) js.push(p);
  }
})(root);

let bad = 0;
for (const f of php) {
  try { phpCheck(f); } catch (e) { bad++; console.log('PHP FAIL', path.relative(root, f), e.message); }
}
for (const f of js) {
  try { execFileSync(process.execPath, ['--check', f], { stdio: 'pipe' }); } catch (e) { bad++; console.log('JS FAIL', path.relative(root, f), String(e.stderr).split('\n').slice(0, 4).join(' | ')); }
}
console.log(`${php.length} PHP + ${js.length} JS files, ${bad} errors`);
process.exit(bad ? 1 : 0);
