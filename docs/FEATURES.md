# Features

Every feature has its own switch and starts **off** (except a few safe defaults), so installing the plugin never
changes a shop by surprise. Settings: WordPress left menu **Kenakata Kit** → tab named below. The exact fields of each
tab are listed in [SETTINGS-REFERENCE.md](SETTINGS-REFERENCE.md). **Pro** = needs a license key
([LICENSE-SYSTEM.md](LICENSE-SYSTEM.md)); everything else is Free.

## Selling
| Feature | What it does for the shop | Tab | Code |
|---|---|---|---|
| Buy Now | A button next to Add to cart that goes straight to checkout with that product | Buy Now Button | `includes/buy-now.php` |
| Simple checkout | Short Bangladeshi checkout: name, 11-digit phone, address, delivery area (inside / outside Dhaka) with its own charge; optional district → thana lists (64 districts, 572 thanas) | Checkout | `includes/checkout.php`, `includes/data/bd-thanas.php` |
| Free-delivery bar | "Buy ৳X more for free delivery" in cart and checkout | Checkout | `includes/free-delivery.php` |
| Thank-you page | Own order-confirmation page with order details | Thank-you page | `includes/orders.php` |
| Side cart | Cart slides in from the side; can replace the theme's/Elementor's mini cart | Side cart | `includes/extras.php`, `assets/kenakata-kit.js` |
| Bottom bar | App-like menu at the bottom on phones | Bottom Bar | `includes/bottom-bar.php` |
| Incomplete orders (Pro) | Saves customers who typed their phone at checkout but did not order; turn them into orders with one click | Incomplete orders | `includes/incomplete.php` |

## Fake-order protection
| Feature | What it does | Tab | Code |
|---|---|---|---|
| Phone check | Only real Bangladeshi numbers (01XXXXXXXXX) | Fake-order protection | `includes/checkout.php` |
| Repeat-order block | One order per number / IP within a set time | Fake-order protection | `includes/order-guard.php` |
| Cart-bot guard | Stops bots that fill carts and slow the server | Fake-order protection | `includes/cart-guard.php` |
| Turnstile | Cloudflare's free "are you human" check at checkout (never blocks checkout if Cloudflare is down) | Fake-order protection | `includes/order-guard.php` |
| Courier ratio | Shows how often a phone number received / refused parcels (BD Courier service) | Order tracking & courier | `includes/courier-core.php` |
| Blocked numbers & "pay first" | Block a number with one tap; customers with many returns must pay in advance | Fake-order protection | `includes/shield.php` |

## Orders, courier, payment
| Feature | What it does | Tab | Code |
|---|---|---|---|
| Order tracking by phone | Customers see their order status with phone or order number; live courier status (Pro) | Order tracking & courier | `includes/orders.php` |
| Courier booking (Pro) | Send orders to Steadfast, Pathao, RedX, eCourier in one click or in bulk; "Sync status" button; courier log. No background sync (owner decision) | Courier booking | `includes/courier.php`, `courier-admin.php`, `courier-core.php` |
| Courier links | Tracking links for 18 couriers | Order tracking & courier | `includes/orders.php` |
| Invoice & label | Print invoices and shipping labels, one order or many | Invoice & label | `includes/print.php` |
| bKash / Nagad / Rocket / Upay by hand | Customer sends money, types sender number + Transaction ID, optional screenshot (kept private); full amount or only delivery charge in advance; cash-out fee line | Mobile payment | `includes/manual-pay.php`, `assets/manual-pay.*` |
| Order messages | Ready WhatsApp / email texts per order ("confirmed", "sent with courier", "payment reminder"…) | Messages | `includes/messaging.php` |

## Store dashboard (Pro)
A simple shop-management screen at `/dashboard` for owner and staff (role "Store Manager"), in Bengali, made for
phones: orders (confirm, courier, print, payment check), products incl. products with options (any option names),
stock, categories and brands, customers, sales report. Code: `includes/store-dashboard.php` (role, page, strings),
`includes/store-dashboard-api.php` (REST `/wp-json/kenakata/v1/sd/*`), `assets/store-dashboard.js|css`.

## Products and design
| Feature | What it does | Tab | Code |
|---|---|---|---|
| Product cards | One clean mobile-friendly card for every product list | Product cards | `includes/product-card.php` |
| Product grids / carousels | `[kk_products]` and Elementor widgets (latest, featured, sale, best sellers, by category/brand…) | — | `includes/products.php` |
| Brand / category cards and carousels | `[kk_brands]`, `[kk_categories]`, logo or name cards | Brands & categories | `includes/terms.php` |
| Category/brand intro | Short text above products, `[kk_term_intro]` | Brands & categories | `includes/terms.php` |
| Variation buttons | Size/colour as buttons instead of dropdowns | Product page | `includes/variations.php` |
| Product page extras | Order on WhatsApp, delivery info, sticky Buy Now bar | Product page | `includes/extras.php` |
| Photo reviews | Rating summary, customer photos | Reviews | `includes/reviews.php` |
| Questions & answers | "Questions" tab on product pages | Questions & answers | `includes/qa.php` |
| Wishlist | ♡ button, wishlist page, count | Wishlist | `includes/wishlist.php` |
| Live search | Results with image and price while typing; names first, then SKU | Live Search | `includes/search.php` |
| Colours & design | Brand colours, font, sizes for all Kenakata parts | Colours & design | `includes/assets.php` |
| Elementor widgets | 20+ widgets in the "Kenakata Kit" category | Elementor widgets | `includes/elementor/` |

## Customers
Login popup with phone or email, My Account page, floating WhatsApp / call buttons — tabs Login popup and
WhatsApp & call; code `includes/account.php`, `includes/my-account.php`, `includes/extras.php`.

## Site
| Feature | What it does | Tab | Code |
|---|---|---|---|
| Security | Hide user names, XML-RPC off, login limits with trusted browsers, always https, uploads protection | Security | `includes/security.php` |
| Speed & health | Load WooCommerce files only on shop pages, block-editor styles only where used, bot and AI-crawler control (robots.txt), checks with links to fix | Speed & health | `includes/speed.php` |
| Cron health | Warns when WordPress scheduled jobs stop running | Speed & health | `includes/cron-health.php` |
| SEO & Google | Delivery charge/time and return policy in the product data (Rank Math or WooCommerce schema); "How much does it cost?" answer with live price `[kk_price_faq]` | SEO & Google | `includes/seo-extras.php` |
| Export / import | All settings as one .json file | Tools | `includes/admin.php` |
| Updates | Signed updates from GitHub releases, shown in Dashboard → Updates | — | `includes/updater.php` |
| Bengali / English | Full Bengali translation; the user guide is Bengali | — | `languages/`, `docs/index.html` |

## What the kit deliberately does not do
Page cache or Redis, replacing an SEO plugin, backups, a page builder, a full funnel builder, the official
bKash/Nagad payment APIs, SMTP. Use a specialised plugin for these (see STATUS.md → owner decisions).
