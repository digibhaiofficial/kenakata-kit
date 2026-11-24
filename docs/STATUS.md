# Kenakata Kit — project status (read this first)

Last updated: 25 Nov 2026 · current version **3.30.0** (smaller styles, see `kenakata-kit/readme.txt`).
Owner: DigiBhai.com (talks Bengali; is not a programmer). Kenakata Kit is a stand-alone product sold to any
Bangladeshi WooCommerce shop; it belongs to no particular website. Docs index: `README.md` in this folder.

## 1. What this is
A WordPress + WooCommerce plugin for Bangladeshi online shops: "everything for selling and orders" in one
plugin, in English and Bengali. Free features for every shop; Pro features need a license key sold by DigiBhai.

| Area | What exists (module file in `kenakata-kit/includes/`) |
|---|---|
| Selling | Buy Now (`buy-now.php`), simple checkout with inside/outside Dhaka charges and optional district → thana (`checkout.php`, `data/bd-thanas.php`), free-delivery bar (`free-delivery.php`), side cart + mobile bottom bar (`bottom-bar.php`), thank-you page and order tracking by phone (`orders.php`), incomplete orders — Pro (`incomplete.php`) |
| Fake orders | 11-digit phone check, repeat number/IP limits, cart-bot guard (`cart-guard.php`, `order-guard.php`), courier success ratio via BD Courier (`courier-core.php`), Cloudflare Turnstile, blocked-numbers shield (`shield.php`) |
| Orders & courier | Courier booking Steadfast / Pathao / RedX / eCourier, bulk send, manual "Sync status" button (no background sync — owner's decision), courier log (`courier*.php`, Pro); invoice and label printing (`print.php`); bKash / Nagad / Rocket / Upay by hand with TrxID + screenshot, advance payment (`manual-pay.php`); ready WhatsApp/email messages per order (`messaging.php`) |
| Store dashboard (Pro) | `/dashboard`: orders, products incl. products with options, stock, customers, sales report, Bengali, mobile (`store-dashboard.php`, `store-dashboard-api.php`, `assets/store-dashboard.js`) |
| Product & design | Product cards, brand/category carousels and cards, variation buttons, photo reviews, Q&A, wishlist, live search, 15+ Elementor widgets (`product-card.php`, `products.php`, `variations.php`, `reviews.php`, `qa.php`, `wishlist.php`, `search.php`, `elementor/`) |
| Customer | Login popup, My Account, WhatsApp / call buttons (`account.php`, `my-account.php`, `extras.php`) |
| Site | Security (login limits, XML-RPC off…), Speed & health switches, bot/AI-crawler control, cron health (`security.php`, `speed.php`, `cron-health.php`) |
| SEO | Term intro `[kk_term_intro]` (`terms.php`), "SEO & Google" tab: delivery/returns in product schema, live price answer `[kk_price_faq]` (`seo-extras.php`) |
| Platform | License (`license.php`), signed updates from GitHub releases (`updater.php`), settings schema (`settings-schema.php`), admin (`admin.php`), Bengali `.po/.mo` (`languages/`), user guide (`docs/index.html`) |

Free vs Pro: Pro = `kk_pro_modules()` in `kenakata-kit.php` (courier, incomplete, store-dashboard). Changelog of
every version: `kenakata-kit/readme.txt`.

## 2. Where everything lives
| Thing | Where |
|---|---|
| Plugin source (single source of truth) | GitHub `digibhaiofficial/kenakata-kit` (public). Owner's PC: `D:\kenakata-kit\kenakata-kit\` (a clone) |
| Releases (zip + signature) | GitHub Releases of that repo, built by `.github/workflows/release.yml` when a tag `vX.Y.Z` is pushed |
| License key panel | `license-panel/index.html`, published by GitHub Pages: https://digibhaiofficial.github.io/kenakata-kit/ (holds no secret) |
| Cancelled keys | `revoked.json` in this repo (sites read it daily) |
| Signing key for licenses (encrypted), customer ledger, business handoff | private repo `digibhaiofficial/kenakata-license-panel` (`kk-vault.json`, `customers.json`, `HANDOFF.md`). PC: `D:\kenakata-kit\kenakata-license-panel\` |
| Release-signing secret for GitHub Actions | GitHub repo secret (set by the owner). Copies on the PC in `D:\kenakata-kit\secrets\` — never commit, never print |
| Owner guide for the PC folder | `D:\kenakata-kit\00-START-HERE.md` (copy: `docs/OWNER-GUIDE-bn.md`) |
| Marketing material (logos, icons, banners) | `D:\kenakata-kit\media\` on the owner's PC (not in git) |

## 3. How work is done
- Code rules, tests and the release steps: `../CLAUDE.md` (also valid for other AI agents; `../AGENTS.md` points there).
- Tests (all pass on 3.30.0, 25 Nov 2026): `node dev/lint.js` → 0 errors ·
  `dev/ci/smoke.sh` on real WordPress + WooCommerce (SQLite), including the shop browser test `dev/e2e-shop.js` and a
  size budget → 220 passed, 0 failed · `node dev/e2e-dashboard.js` → 70 passed, 0 failed. GitHub runs lint + smoke on PHP 7.4 and 8.3 on every push.
- Release = commit + tag → push (by the AI directly when the Claude GitHub App is installed, otherwise the owner
  double-clicks `D:\kenakata-kit\PUSH-TO-GITHUB.bat`) → GitHub builds and signs the zip → shops see "Update
  available" (checked every 6 h, or License → "Check for updates now" (or Dashboard → Updates → Check again)). Details: `RELEASE-AND-UPDATES.md`.
- Never give the owner a zip to upload; never put site-specific code in the plugin; new features start switched off.

## 4. Testing on a real shop
After every release, update the plugin on a test shop the owner chooses (Dashboard → Updates) and check it there
with real data. Treat any shop's data carefully: never delete content, never place real orders. Site access
(Application Passwords, MCP connectors) is kept by the owner — never in a repo, a doc or a chat.

## 5. Owner decisions (keep)
- Every feature has its own on/off switch; if a feature risks breaking a site or conflicting, leave it out.
- Never force the shop owner to pay for an outside service; SMS/OTP only optional.
- Courier: no background auto-sync — "Send to courier" sends, "Sync" fetches status.
- Variations: owner types any option name and values; earlier names are suggested.
- Not in the kit: page cache/Redis, SEO plugin replacement, backups, page builder, full funnel builder, official
  bKash/Nagad API (manual TrxID instead), SMTP.
- District → thana: separate switch, off by default.

## 6. Known open items (not bugs — all tests pass)
Done in 3.29–3.30: main stylesheet 61 → 37 KB (feature styles only when switched on, slider and brand/category
card styles only where shown; screenshots identical to before); browser tests for cards,
wishlist, side cart, search, login popup, Q&A, reviews and phone layout; size budget in the tests; Bengali checked
(2,311 texts, none missing); "could not be reactivated" explained in the Updates box; plain-permalink address fix.
Still open (small improvements, no new features):
1. The main stylesheet (37 KB) is still above the old 15 KB goal: what is left is product cards, product lists,
   Buy Now, WhatsApp/delivery, side cart and variation buttons — on almost every shop page.
2. Browser tests do not yet cover Elementor widgets inside the Elementor editor.
3. Store dashboard cannot yet create orders, refunds or coupons (counts as a new feature: discuss first).

## 7. Roadmap (not started — discuss with the owner before building)
- Marketing: Meta Pixel + Conversions API, GA4, GTM, TikTok; one light product feed for Google and Facebook
  (would let shops remove Site Kit, Google for WooCommerce, Facebook for WooCommerce).
- More sales: checkout order bump, "bought together", next-order coupon on the thank-you page.
- Optional SMS gateway module (any Bangladeshi HTTP API) and OTP only for risky orders.
- Dashboard 2: new-order notifications, PWA, change log, Owner/Manager/Packer roles, create orders from the dashboard.
The older detailed research (market comparison, plugin removal plan): Claude artifact "Kenakata Kit: মাস্টার প্লান".
