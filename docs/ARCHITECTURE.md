# Architecture

## Files
```
kenakata-kit/                    the plugin (this folder is what goes into the zip)
├─ kenakata-kit.php              header + KK_VERSION, constants, kk_defaults(), helpers, the module loader
├─ includes/                     one feature per file (see FEATURES.md for which file does what)
│  ├─ settings-schema.php        every admin tab and field (labels, help, type) — the admin UI is generated from it
│  ├─ admin.php                  the settings screens, export/import, shortcode help
│  ├─ license.php · updater.php  Free/Pro and signed updates from GitHub
│  ├─ rest.php                   shared REST routes (namespace kenakata/v1)
│  ├─ assets.php                 which CSS/JS loads on which page, design variables
│  ├─ store-dashboard*.php       Pro dashboard (page, role, REST /sd/*)
│  ├─ elementor/                 Elementor widgets (base.php = shared style controls)
│  └─ data/bd-thanas.php         districts and thanas (English + Bengali)
├─ assets/                       name.css / name.js (readable) + name.min.* (made by dev/minify.sh; served by default).
│                                kenakata-kit.css = core; -checkout/-account/-order/-product = per page type;
│                                -wishlist/-search/-popup/-bbar/-sticky/-readmore = only when that feature is on
├─ templates/                    WooCommerce template overrides used by the kit
├─ languages/                    kenakata-kit-bn_BD.po / .mo (built from dev/i18n)
├─ docs/index.html               the shop owner's Bengali user guide (opened from the Help tab)
└─ readme.txt                    WordPress readme + changelog of every version
```

## How it starts
1. `kenakata-kit.php` defines constants (`KK_VERSION`, `KK_DIR`, `KK_URL`, `KK_FILE`; `KK_GH_REPO` and the public keys are in `license.php`) and helpers
   (`kk_opt()`, `kk_asset()`…).
2. On `plugins_loaded` it stops quietly with an admin notice if WooCommerce is missing or older than 7.0.
3. It loads `license.php` and asks `kk_is_pro()`. Pro files (`kk_pro_modules()`: courier, incomplete,
   store-dashboard) are not loaded without a valid key — other modules must therefore use `function_exists()`
   before calling anything from them.
4. The settings screens and the update checker load only in the admin, cron and WP-CLI (`kk_load_admin()`), so
   shop pages stay light.
5. Every other module file is required in a fixed list. Each module checks its own switch in `kk_settings` and adds
   its hooks only when switched on. A module never depends on another being on.
6. HPOS (WooCommerce custom order tables) compatibility is declared; the classic checkout is required (the kit
   warns and offers a switch when a shop uses the block checkout).

## Where data is stored
| Data | Where |
|---|---|
| All settings | one option `kk_settings` (array; defaults `kk_defaults()`); never autoloaded twice |
| License | options `kk_license_key`, `kk_license_cache`, `kk_license_revoked` |
| API keys (courier, BD Courier, Turnstile…) | options `kk_secrets`, `kk_courier_secrets` — optionally encrypted with `KK_ENCRYPTION_KEY` from wp-config.php |
| Logs and lists | `kk_courier_log`, `kk_blacklist`, `kk_login_locks`, `kk_shield_count`, `kk_cron_last` … |
| Incomplete orders | own table `{prefix}kk_incomplete` (created by `incomplete.php`, version in `kk_incomplete_db`) |
| Per order | order meta via `WC_Order` (HPOS safe): `_kk_delivery_area`, `_kk_courier*`, `_kk_tracking_*`, `_kk_mp_*` (mobile payment) |
| Per product / term | `_kk_wl_count` (wishlist), `_kk_intro` (term intro) |
| Mobile-payment screenshots | uploads folder, protected by an `.htaccess` block the kit adds and removes |
Uninstall keeps everything by default; with "Delete all data" on it removes settings, license, logs, lists, table.

## Browser ↔ server
- REST routes under `/wp-json/kenakata/v1/…` (search, wishlist, Q&A, products, courier, incomplete, store
  dashboard `/sd/*`, cron health). Every route has a `permission_callback` with a real capability or a nonce.
- Which styles a page gets: `includes/assets.php` (`kk_need_css()` for page types, `kk_feature_css_parts()` for switched-on
  features). REST addresses in JS are built with a helper that works with plain permalinks (`?rest_route=`).
- Shop JS `assets/kenakata-kit.js` (side cart, Buy Now, search, wishlist…) works without WooCommerce cart fragments.
- The store dashboard is a single-page app (`assets/store-dashboard.js`) that never puts server data into
  `innerHTML` (uses `h()`; editor HTML goes through `cleanHTML()`).

## Scheduled jobs
`kk_cron_heartbeat` (hourly, cron health), `kk_license_daily` (daily: re-check key, read `revoked.json`),
`kk_incomplete_cleanup` (daily). All are removed on deactivation. Courier status is never synced in the background.

## Translation
Source strings are English with `__( '…', 'kenakata-kit' )`. Bengali lives in `dev/i18n/*.js` dictionaries;
`node dev/i18n/i18n-build.js --no-convert` writes the .po/.mo. Store-dashboard strings are listed in
`kk_sd_strings()`.

## Updates and license (summary)
`updater.php` reads the GitHub releases of `KK_GH_REPO`, takes the highest version, downloads its zip and checks the
`.sig` file with the Ed25519 public key `KK_RELEASE_PUBKEY` before WordPress installs it. License keys are signed
with a different key (`KK_LICENSE_PUBKEY`). Details: RELEASE-AND-UPDATES.md and LICENSE-SYSTEM.md.
