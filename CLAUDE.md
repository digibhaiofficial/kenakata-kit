# Kenakata Kit — instructions for Claude

You are continuing development of **Kenakata Kit**, a WordPress/WooCommerce plugin for Bangladeshi online shops,
written by DigiBhai.com. **Language: always talk to and report to the owner in simple Bengali (বাংলা) — every reply, every
report, every question.** Keep code, comments and UI source strings in English.
Read `HANDOFF.md` (in the private repo `kenakata-license-panel`) for the current status, the roadmap and open decisions.

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
  entry in readme.txt, commit, push, then push the tag `vX.Y.Z` (GitHub Actions builds and publishes the release).
  Never reuse a version number.

## Test before every delivery (all must pass)
```
node dev/lint.js
node dev/calls.js includes/store-dashboard.php includes/store-dashboard-api.php   # review the list
node dev/e2e-dashboard.js - <outDir>          # starts its own mock server; expect "48 passed, 0 failed"
node dev/shots-dashboard.js <outDir> bn       # look at the screenshots (phone + desktop, Bengali)
python dev/build-zip.py                       # local zip for testing; releases come from the tag
```
There is no PHP on this computer: PHP is checked by syntax lint + careful review + the live test site.
After the owner uploads a zip, test on the live site with real data (see HANDOFF.md → Live test).

## Live site used for testing
purpleflorabd.com (Hello theme + Elementor Pro + WooCommerce + LiteSpeed Cache). It is a demo shop, not a live
business, but treat its data carefully: never delete content, never place real orders, back up before changing
Elementor documents. Access details are in the private repo, never in this one.
