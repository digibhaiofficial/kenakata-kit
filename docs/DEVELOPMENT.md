# Development

The binding rules and exact commands are in [../CLAUDE.md](../CLAUDE.md). This page explains how to work.

## Ways to work
| Who / where | Setup | How changes reach GitHub |
|---|---|---|
| AI agent in the cloud with the Claude GitHub App on the repo (recommended for the owner) | claude.ai/code, pick `digibhaiofficial/kenakata-kit` | the agent commits, tags and pushes itself |
| AI agent in the cloud without GitHub access | any chat with file tools | it leaves `kenakata-kit.bundle` in `D:\kenakata-kit\inbox\`; the owner double-clicks `PUSH-TO-GITHUB.bat` |
| AI agent or developer on the owner's PC | open `D:\kenakata-kit\kenakata-kit\` (a git clone) | commit + tag there, then `PUSH-TO-GITHUB.bat` (or `git push origin main --tags`) |
| Any developer | `git clone https://github.com/digibhaiofficial/kenakata-kit` | normal git push (needs write access) |

## First-time setup
1. Node 18+ and PHP 7.4+ (PHP 8.x fine). `cd dev && npm install` once (php-parser; `dev/node_modules` is not in git).
2. Optional for the full WordPress test: a WordPress checkout, a WooCommerce zip and the SQLite integration plugin
   (see CLAUDE.md → "Real WordPress test"), or let GitHub Actions run it on every push.

## Rules in one screen
- Never break a shop: new features start off; check `function_exists`/`class_exists` for optional parts; modules stay independent.
- Only public WordPress / WooCommerce / Elementor APIs; orders only through `wc_get_orders` / `WC_Order` (HPOS).
- Security: capability in every REST `permission_callback`; sanitize every input; escape every output.
- Prefix `kk_` (PHP), `_kk_` (meta), `.kk-` (CSS), `.sd-` (dashboard CSS).
- English UI strings + Bengali in `dev/i18n`; check the Bengali meaning in context.
- No code or wording for one particular shop: a shop's need becomes a generic feature with a setting.

## Adding or fixing something — checklist
1. Read STATUS.md and the module file involved; search for the setting key in `settings-schema.php`.
2. New setting: add the default in `kk_defaults()` (kenakata-kit.php), the field in `settings-schema.php`, the
   Bengali in `dev/i18n/i18n-vNN.js`; regenerate `docs/SETTINGS-REFERENCE.md`.
3. New file in `includes/`: add it to the loader list in kenakata-kit.php (and to `kk_pro_modules()` if Pro).
4. CSS/JS changed: `sh dev/minify.sh` (CI fails if the .min files do not match). Styles of a feature that has its own
   switch go in `assets/kenakata-kit-<part>.css`: add the part to `kk_feature_css_parts()` (assets.php) and to
   `dev/minify.sh`. Styles of a component drawn by a shortcode/widget (slider, brand cards) go in their own part and
   the renderer prepends `kk_need_css( '<part>' )`. Inline CSS for such a part must be attached to its handle, not to `kenakata-kit` (order matters).
5. Run the tests (below). Add a check to `dev/ci/smoke.sh` for new server behaviour.
6. Update `kenakata-kit/docs/index.html` (user guide) and `readme.txt` (changelog) when users see a change.
7. Release: RELEASE-AND-UPDATES.md.

## Tests
| Command | What it checks | Expected |
|---|---|---|
| `node dev/lint.js` | syntax of every PHP and JS file | 0 errors |
| `node dev/calls.js includes/<file>.php` | lists external functions used, to confirm they exist | review the list |
| `node dev/e2e-dashboard.js - <outDir>` | clicks through the store dashboard in headless Chrome | 70 passed, 0 failed |
| `node dev/shots-dashboard.js <outDir> bn` | screenshots of every dashboard screen (look at them) | — |
| `bash dev/ci/smoke.sh` | installs WordPress + WooCommerce + the plugin and tests shop pages, checkout, guards, settings, license, courier list, feature styles, size budget, uninstall, PHP log; runs `dev/e2e-shop.js` in headless Chrome (cards, wishlist, side cart, search, login popup, Q&A, reviews, phone layout) | 220 passed, 0 failed (3.30.0) |
GitHub Actions: `check.yml` (lint, minified files), `wp-test.yml` (smoke test on PHP 7.4 and 8.3) on every push.

## Useful facts
- `dev/build-zip.py` builds `dist/kenakata-kit-<version>.zip` locally (for testing only; real releases come from GitHub).
- `dev/mock-server.js` is a fake WordPress for the dashboard (`?lang=bn`, `?manager=1`, `?badnonce=1`).
- Dev hosts (`localhost`, `*.local`, `*.test`) count as Pro, so Pro features can be tested without a key.
