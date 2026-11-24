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
the PHP log (expect "0 failed"). GitHub runs it on every push (.github/workflows/wp-test.yml, PHP 7.4 + 8.3).
Without MySQL / wordpress.org it also runs on SQLite:
`LOCAL_WP=<WordPress git checkout> LOCAL_WC_ZIP=<woocommerce.zip> LOCAL_SQLITE=<sqlite-database-integration/packages/plugin-sqlite-database-integration> bash dev/ci/smoke.sh`
(WordPress: `git clone --depth 1 --branch <ver> https://github.com/WordPress/WordPress`; WooCommerce zip from its GitHub releases).
The owner's Windows PC has no PHP: there PHP is checked by syntax lint + careful review + the live test site.
After the release, update the plugin on the live site from Dashboard → Updates and test there with real data.

## Release (how code reaches GitHub — this cloud session cannot push itself)
The owner's PC has `D:\kenakata-kit\github-push\push-to-github.bat`, which pushes with the owner's own GitHub login.
1. Commit here. Make a bundle: `git bundle create kenakata-kit.bundle main --tags`.
2. Copy it to the PC: `D:\kenakata-kit\github-push\kenakata-kit.bundle` (device_commit_files, overwrite).
3. Ask the owner to double-click `push-to-github.bat` and reply "hoyeche". It pushes `main` and all tags
   (it merges GitHub-only commits such as revoked.json from the license panel first).
4. Two steps for a new version: first a bundle WITHOUT the new tag (push main → GitHub runs check.yml and
   wp-test.yml on PHP 7.4 + 8.3; the repo is public, check github.com/digibhaiofficial/kenakata-kit/actions).
   When green: `git tag vX.Y.Z`, new bundle, owner clicks again → release.yml builds, signs and publishes.
5. After a merge on GitHub, the next bundle must contain those commits too: before bundling, ask the owner to run the
   .bat once without changes or fetch the public repo (`git fetch https://github.com/digibhaiofficial/kenakata-kit main`).

## Live site used for testing
purpleflorabd.com (Hello theme + Elementor Pro + WooCommerce + LiteSpeed Cache). It is a demo shop, not a live
business, but treat its data carefully: never delete content, never place real orders, back up before changing
Elementor documents. Access details are in the private repo, never in this one.
