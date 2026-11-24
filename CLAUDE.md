# Kenakata Kit — instructions for Claude

You are continuing development of **Kenakata Kit**, a WordPress/WooCommerce plugin for Bangladeshi online shops,
written by DigiBhai.com. **Language: always talk to and report to the owner in simple Bengali (বাংলা) — every reply, every
report, every question.** Keep code, comments and UI source strings in English.
Read `docs/STATUS.md` first (current status, where everything lives, open items, roadmap, owner decisions).
Business notes (customers, prices) are in `HANDOFF.md` of the private repo `kenakata-license-panel`.

## Folder layout
```
kenakata-kit/                 ← this project folder (open it in Claude Code)
├─ kenakata-kit/              ← THE PLUGIN (what goes into the zip). Main file: kenakata-kit.php
│  ├─ includes/               ← PHP modules, one feature per file (loaded in kenakata-kit.php → plugins_loaded)
│  │  ├─ store-dashboard.php       role, /dashboard page, settings hooks, strings for the script
│  │  ├─ store-dashboard-api.php   REST API /wp-json/kenakata/v1/sd/*
│  │  ├─ settings-schema.php       every settings tab + field (labels, help texts, types)
│  │  └─ elementor/                Elementor widgets (base.php = shared style helpers)
│  ├─ assets/                 ← kenakata-kit.css/js (shop), store-dashboard.css/js (dashboard), admin.*
│  ├─ languages/              ← kenakata-kit-bn_BD.po/.mo (generated, see i18n below)
│  └─ docs/index.html         ← Bengali user guide shown in the admin
├─ .github/workflows/         ← check.yml (lint on every push), release.yml (tag vX.Y.Z → zip → GitHub release)
├─ revoked.json               ← IDs of cancelled license keys (sites read it daily)
├─ license-panel/index.html   ← owner's key panel, served by GitHub Pages (panel.yml); holds no secret
├─ docs/                      ← STATUS.md (project status), OWNER-GUIDE-bn.md (owner's PC guide), PUSH-TO-GITHUB.bat
├─ AGENTS.md                  ← pointer for other AI agents
├─ dev/                       ← development tools (never shipped)
│  ├─ lint.js                 syntax check of all PHP (php-parser) + JS (node --check)
│  ├─ calls.js                lists external functions/methods used by given PHP files (to verify they exist)
│  ├─ mock-server.js          fake WordPress for the dashboard (sample data, XSS probes, ?lang=bn, ?manager=1, ?badnonce=1)
│  ├─ e2e-dashboard.js        clicks through the whole dashboard in headless Edge/Chrome, 48 checks, screenshots
│  ├─ shots-dashboard.js      screenshots of every dashboard screen, desktop + phone, optional Bengali
│  ├─ build-zip.py            builds dist/kenakata-kit-<version>.zip (forward-slash paths, kenakata-kit/ root)
│  ├─ i18n/                   i18n-build.js + Bengali dictionaries ([bengali, english] pairs)
│  └─ package.json            php-parser for lint.js / i18n-build.js (`cd dev && npm install`)
└─ dist/                      ← built zips (git-ignored; releases are built by GitHub Actions)
```
Source of truth: GitHub `digibhaiofficial/kenakata-kit` (public). The license panel, signing key (encrypted),
customer ledger and handoff notes live in the private repo `digibhaiofficial/kenakata-license-panel`.

## Free / Pro and license
- Pro modules = `kk_pro_modules()` in kenakata-kit.php (courier, incomplete, store-dashboard). They are not loaded
  without a valid key, so every other module must keep using `function_exists()` for anything from them.
- `includes/license.php`: keys `KK1.<base64url JSON>.<base64url Ed25519 signature>`, checked with
  `sodium_crypto_sign_verify_detached` against `KK_LICENSE_PUBKEY`. Payload: id, d (domains, sub-domains included),
  p (plan label), e (updates until, '' = lifetime), i (issued). Dev hosts (localhost, *.local, *.test) are Pro.
- `includes/updater.php`: latest GitHub release → WordPress update. Expired Pro keys keep Pro but get no package.
- Never change the public key unless all keys are re-issued.

## Rules
- The plugin must never break a shop. Feature flags default to off for new features; check `function_exists` /
  `class_exists` for optional integrations; every module stays independent.
- Use only public WordPress / WooCommerce / Elementor APIs (hooks, CRUD classes, REST, documented widget API).
  Orders: only `wc_get_orders`, `wc_get_order`, `WC_Order` methods (HPOS compatible, declared in kenakata-kit.php).
- Security: every REST route has a `permission_callback` with a real capability; sanitize input
  (`sanitize_text_field`, `wp_kses_post`, `absint`, `wc_format_decimal`…); escape output (`esc_html`, `esc_attr`, `esc_url`).
  In the dashboard JS never put server data into `innerHTML` — use the `h()` helper (textContent); editor HTML goes
  through `cleanHTML()` (allowlist).
- Prefix everything `kk_` (functions, options, meta `_kk_*`, CSS `.kk-*`; dashboard CSS `.sd-*`).
- UI strings: English source + `__( '…', 'kenakata-kit' )`; add the Bengali in `dev/i18n/i18n-v36.js` (or a new
  `i18n-vNN.js`; `i18n-a-current.js` holds everything shipped up to 3.11.0 and wins over the older files),
  then `node dev/i18n/i18n-build.js --no-convert`.
  Check meaning in context — a reused English word may already have a Bengali translation for another meaning
  (e.g. "Order" was "ক্রম" = sort order).
- Dashboard texts used by the script are listed in `kk_sd_strings()` (store-dashboard.php); the mock server reports
  `MISSING STRINGS` (key used in JS but not defined) and `NO BENGALI FOR` (no translation).
- Bump the version in **both** places in kenakata-kit.php (header `Version:` and `KK_VERSION`), add a changelog
  entry in readme.txt, commit, then release (see "Release" below). Never reuse a version number.
- Never hand the owner a zip to upload: shops update from the signed GitHub release (Dashboard → Updates).

## Test before every delivery (all must pass)
On a fresh clone run `cd dev && npm install` once (php-parser for calls.js and i18n-build.js; dev/node_modules is not in git).
Without npm: lint falls back to `php -l`; translations can be added straight to the .po and compiled with
`python dev/i18n/po2mo.py <po> <mo>` (also add them to dev/i18n/i18n-vNN.js so the next full build keeps them).
```
node dev/lint.js
node dev/calls.js includes/store-dashboard.php includes/store-dashboard-api.php   # review the list
node dev/e2e-dashboard.js - <outDir>          # starts its own mock server; expect "70 passed, 0 failed" (rarely 1–4 timing failures: run again)
node dev/shots-dashboard.js <outDir> bn       # look at the screenshots (phone + desktop, Bengali)
sh dev/minify.sh                              # after any CSS/JS change: regenerates assets/*.min.* (CI checks they match)
python dev/build-zip.py                       # local zip for testing; releases come from the tag
```
Real WordPress test: `dev/ci/smoke.sh` installs WordPress + WooCommerce + the plugin and checks shop pages, instant
add to cart / Buy Now, the bot guard, every settings tab, license save (wrong domain refused), courier tick list,
incomplete order → order with delivery charge, tracking with order number, speed switches, Turnstile, uninstall and
the PHP log (expect "0 failed"). It also runs `dev/e2e-shop.js` (shop features in headless Chrome, needs Node 22+) and a size
budget for the plugin's files. GitHub runs it on every push (.github/workflows/wp-test.yml, PHP 7.4 + 8.3).
Without MySQL / wordpress.org it also runs on SQLite:
`LOCAL_WP=<WordPress git checkout> LOCAL_WC_ZIP=<woocommerce.zip> LOCAL_SQLITE=<sqlite-database-integration/packages/plugin-sqlite-database-integration> bash dev/ci/smoke.sh`
(WordPress: `git clone --depth 1 --branch <ver> https://github.com/WordPress/WordPress`; WooCommerce zip from its GitHub releases).
The owner's Windows PC has no PHP: there PHP is checked by syntax lint + careful review + the live test site.
After the release, update the plugin on the test shop from Dashboard → Updates and test there with real data.

## Release (how code reaches GitHub — this cloud session cannot push itself)
Shops update ONLY from a tagged, signed GitHub release (Dashboard → Updates). Pushing main alone changes nothing on
the sites. Never hand the owner a zip to upload. One click for the owner per version:
1. Before tagging, run every test (lint, e2e, and the full SQLite smoke test `dev/ci/smoke.sh` — same script GitHub
   runs on PHP 7.4 + 8.3). All must pass.
2. Commit, then tag: `git tag vX.Y.Z` (must equal KK_VERSION), then `git bundle create kenakata-kit.bundle main --tags`.
   Before bundling, merge GitHub-only commits: `git fetch https://github.com/digibhaiofficial/kenakata-kit main` and
   merge FETCH_HEAD if it is not already in main (the repo is public, fetch works here).
3. Copy it to the owner's PC: `D:\kenakata-kit\inbox\kenakata-kit.bundle` (device_commit_files, overwrite).
   (An agent working directly in `D:\kenakata-kit\kenakata-kit` on the PC skips the bundle: it commits and tags there.)
4. Owner double-clicks `D:\kenakata-kit\PUSH-TO-GITHUB.bat` (copy of `docs/PUSH-TO-GITHUB.bat`): takes the inbox
   bundle, commits any local changes, pulls GitHub, pushes main + tags (both repos) → release.yml builds,
   signs and publishes the release (~30 s). Check https://github.com/digibhaiofficial/kenakata-kit/actions (public).
5. Sites check for updates every 6 h; Dashboard → Updates → "Check again" checks at once. Then update the plugin there
   and test the live site.

## Testing on a real shop
After the release, update the plugin on a test shop the owner names and check it with real data. Never delete
content, never place real orders, back up before changing Elementor documents. Access details are kept by the
owner, never in a repo. Kenakata Kit is a stand-alone product: no code or docs specific to any one shop.

## Owner's standing instructions (the owner should never have to repeat these)
- Talk in simple Bengali. Do the work end to end; don't ask him to approve wording or technical choices.
- Releases: one click for the owner (PUSH-TO-GITHUB.bat) with a TAGGED commit; never a zip to upload.
- No site-specific code in the plugin; a site's need becomes a generic kit feature with a setting (off by default).
- Every external link put on a page or in the guide must be opened and confirmed first (never a URL from memory).
- Never print, paste or commit secrets (signing keys, Application Passwords, license vault password).
