=== Kenakata Kit – WooCommerce Toolkit for Bangladesh ===
Contributors: DigiBhai.com
Plugin URI: https://digibhai.com/
Requires at least: 6.3
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 3.25.2
License: GPLv2 or later

Everything a Bangladeshi WooCommerce store usually needs. Settings: left admin menu → Kenakata Kit.
Admin screens and widgets are in English; a Bengali translation is included (Dashboard → Plugin language, or the WordPress profile language).
Full guide (Bengali): Kenakata Kit → Full guide (docs/index.html).

== Features ==
* English or Bengali admin: dashboard with on/off switches, site health checks, live preview, export/import.
* Product card for every product list (mobile-safe titles, discount badge "-12%", brand, rating, Add to cart + Buy Now).
* Buy Now button, simple checkout with delivery areas from shipping methods, 11-digit phone check, repeat-order block.
* Thank-you page, order tracking by phone with courier links and a Shipped status.
* Live search (icon or search bar), login/registration popup (phone or email).
* Brand / category cards and carousels (autoplay, loop, dots).
* WhatsApp / call buttons (floating, product page), delivery information, sticky Buy Now bar, side cart, mobile bottom bar.
* Security switches: force https, hide user names, disable XML-RPC.
* Store dashboard at /dashboard: orders (call / WhatsApp the customer, order history of the number, status, courier & tracking, notes), products (price, stock, pictures, categories, options), blog posts — for a "Store Manager" role that never needs wp-admin. Works on phones.

== Elementor widgets (category "Kenakata Kit") ==
Products Grid, Products Carousel, Brands, Categories, Buy Now Button, Live Search, Cart Icon, Login / My Account, WhatsApp / Call Button, Delivery Info, Checkout Form, Cart Page, My Account, Order Tracking, Order Confirmation, Add to Cart Form, Product Reviews.

== Shortcodes ==
[kk_products] [kk_brands] [kk_categories] [kk_terms] [kk_live_search] [kk_cart_icon] [kk_account_link] [kk_buy_now] [kk_whatsapp] [kk_delivery_info] [kk_order_tracking] [kk_thank_you] [kk_my_account] [kk_product_brand] [kk_wishlist] [kk_wishlist_link]
Older [ss_…] shortcodes keep working. Options: see the guide or Kenakata Kit → Shortcodes.

== Changelog ==
= 3.25.2 =
* Privacy: the thank-you page (?order=…&key=…) is never stored by page caches (LiteSpeed, WP Rocket…), because it shows the customer's name, phone and address.
* User guide: the new 3.25 settings (product name tag, block checkout switch, the three bot switches, API key encryption) are explained.

= 3.25.1 =
* Fix: "Block AI answer engines" blocked AI tools acting for a person (Claude-User, ChatGPT-User, Perplexity-User), which some shops use to manage the site through its REST API. Only the answer engines' crawlers are blocked now, and the bot block never applies to the REST API or admin-ajax.

= 3.25.0 =
* Checkout: Kenakata Kit now tells you when the cart or checkout page uses WooCommerce's new blocks (its checkout features work only with the classic checkout), with a one-click switch to the classic page. The block version stays as a page revision. WooCommerce is told that the plugin needs the classic checkout.
* Speed: "WooCommerce files only on shop pages" no longer removes Order Attribution (where a customer came from: Google, Facebook…) on blog posts, keeps the cart script when the header has a cart icon, and treats pages built with Kenakata Kit's Elementor widgets as shop pages.
* SEO: new setting Product cards → "Product name tag (SEO)": make product names in cards plain text (or H2/H3/H4) everywhere, so pages with many carousels do not get dozens of headings. Default "Automatic" keeps everything as before.
* SEO / accessibility: icon-only Login / My account link and the sticky cart button always have a name; the live search "see all" link has text and a real address, and searches with & or # in them link correctly.
* Security: optional encryption of saved API keys (define KK_ENCRYPTION_KEY in wp-config.php; the site checkup shows the line); saved keys can be removed; the Font setting accepts only font names; safer escaping in live search results; new accounts from the login popup need 8-character passwords; the wishlist counter cannot fill the database.
* Bots: "Block AI crawlers" is split in two: AI training crawlers, and AI answer engines (ChatGPT search, Perplexity, Claude), which bring customers. Sites that blocked AI crawlers keep both blocked until changed. robots.txt uses the shop's real cart / checkout / account addresses.
* Uninstall with "Delete all data" also removes the plugin's logs, lists and wishlists.

= 3.24.1 =
* Speed: fixed carousels (brand/category/product sliders) stopping Chrome's Largest Contentful Paint measurement. The track was snapped by 2px on load, which Chrome treats as a scroll, so PageSpeed showed "NO_LCP" and Core Web Vitals could not be measured.

= 3.24.0 =
* SEO: product card pictures carry the product name as alt text (image search). The link around the picture stays hidden from screen readers, so nothing is read twice.
* SEO: robots.txt rules for all crawlers ("User-agent: *") from WordPress, WooCommerce and this plugin are merged into one group.

= 3.23.0 =
* Brand / category cards: new setting "Card without a picture shows" (Settings → Brand & category cards). "Name as a logo" shows the brand name in the logo box instead of a single letter, for brands that have no logo yet. Default stays "First letter".

= 3.22.1 =
* Speed: the first brand / category pictures on a page load at once instead of lazily (they are often in the header or the first screen; a lazy picture there makes the page look slower). Filter: kk_term_eager_images (default 6).

= 3.22.0 =
* Brands and Categories widgets / shortcodes: with a limit (for example 24), a "View all" link appears under the list when there are more items (own link and text; categories fall back to the shop page). Lighter home pages with many brands.

= 3.21.1 =
* Updates: the update check now picks the highest released version, even when GitHub marks an older release as "Latest" (this happened when two versions were published together, so sites saw 3.20.0 instead of 3.21.0). The release process also keeps "Latest" on the highest version.

= 3.21.0 =
* New (off until turned on): Messages. Ready texts for every order ("Order confirmed", "Sent with courier", "Payment reminder", "Thank you", "Could not reach you") filled in with name, order number, amount to collect and tracking link. In the store dashboard: choose a text, change it if needed, then one tap opens WhatsApp with it, or send it by email (a WooCommerce customer note). On the WooCommerce order screen: one-tap WhatsApp links. Optional SMS through your own gateway (GET or POST, off by default). Nothing is sent automatically and no background jobs are added.

= 3.20.0 =
* Security: login pause no longer locks the real owner out. A browser where an account logged in successfully is trusted for 180 days and is never paused (the password is still checked). The pause message now offers a password reset, and a password reset lifts the pause for that account. Security tab: "Paused logins now" lists every pause with a "Lift all pauses now" button.

= 3.19.0 =
* Checkout (off until turned on): District and thana. Customers choose their district (all 64) and then their thana / upazila from a list that follows the district (572 places in English and Bengali, including the city thanas of Dhaka, Chattogram, Rajshahi, Khulna, Gazipur and Narayanganj; "Other" for the rest). Thana can be off, optional or required. Saved in the order's district and city, so labels and courier bookings get them. Upazila list: nuhil/bangladesh-geocode (MIT).

= 3.18.0 =
* Guide: new "step by step" roadmap (six stages, each with a button that opens the right settings tab) and a new chapter for daily work: products with options, confirming orders and couriers, printing, bKash / Nagad / Rocket, the fake-order shield, the sales report and keeping the site fast. The version shown in the guide is now always current.
* Settings: "where do I find this?" links under fields (Steadfast, Pathao, RedX, eCourier and BD Courier panels, Cloudflare Turnstile keys, WooCommerce payment methods and store address, adding a team member, creating a page).

= 3.17.0 =
* New (off until turned on): Fake-order shield in Fake-order protection. Blocked numbers: block a phone number from the order screen or the store dashboard with one tap (or type numbers in the settings); its orders are refused, or may only be paid in advance. Pay first after many returns: customers whose earlier orders here were mostly cancelled / failed / refunded (or whose saved Courier check is low) do not get cash on delivery. Only your own orders are looked at; nothing outside is asked at checkout.
* Mobile payment: the wallet's cash-out charge is added automatically as a line in the order when the customer picks bKash (1.85%), Nagad (1.5%), Rocket (1.8%) or Upay (1.4%); each rate can be changed or set to 0. With "Only the delivery charge" the charge is on the advance only. The "fixed advance" setting was removed.
* Live search: product names come first (exact name, name starting with the words, a word in the name starting with them), then SKU, then descriptions; every typed word must match. Short searches such as "MS" now find "MS …" products instead of products that only mention the letters in their description. Names with "&" show correctly.
* Side cart: the WordPress admin bar no longer covers the top of the side cart for logged-in staff.

= 3.16.2 =
* Side cart: new switch "Also open it from the header cart icon" (off by default). With a header made with Elementor's "Menu Cart" widget (or a theme cart icon), a click opens the Kenakata side cart and their own panel stays hidden, so the shop has one cart.
* Speed: "Block editor styles only where blocks are used" also works when another plugin adds the file back late.

= 3.16.1 =
* Speed & health (off until turned on): "Block editor styles only where blocks are used". Pages built with Elementor skip the block editor's style file (about 100 KB before compression, render-blocking). Posts written with blocks, cart, checkout and My account keep it.
* Store dashboard: typing an option name and then tapping the values field no longer rebuilds the fields (the tap was lost).

= 3.16.0 =
* New (off until turned on): bKash / Nagad / Rocket / Upay by hand (Kenakata Kit → Mobile payment). The customer sends money to your number, then types the number they sent from and the Transaction ID, and may add a screenshot (kept privately, only staff can open it). No API and no fees. A TrxID can be used only once. The order waits On hold until you press "Payment received" (order screen or store dashboard) or "Not received".
* Ask for the whole amount or only the delivery charge (or a fixed amount) in advance, the rest cash on delivery: courier booking and labels then collect only what is left.
* Store dashboard: "Check payment" badge in the orders list and a payment card on the order.

= 3.15.1 =
* Fix: with the Side cart on, a page builder's own cart panel (Elementor Pro "Menu Cart" with its mini-cart template) showed unstyled after Add to cart (bullets, "View cartCheckout"). The side cart now has its own cart fragment and only it uses WooCommerce's mini-cart markup; the builder's cart keeps its own template and styles.

= 3.15.0 =
* New (off until turned on): Invoice & label printing. Tick orders in WooCommerce → Orders → Bulk actions → "Print invoices" or "Print shipping labels", use the buttons on the order screen, or press Print in the store dashboard (one order, or all orders shown in the list). A4 invoices with logo, items, totals, cash to collect and a barcode; labels 100 × 150 mm, 75 × 100 mm or 6 per A4 sheet, with the courier tracking code as a barcode.
* Courier (Pro): Bulk actions "Send to <courier>" (up to 15 orders per press, only orders not sent yet) and "Sync courier status" (up to 40, only parcels still on the way). Store dashboard: "Sync courier" button on the orders list. Nothing runs in the background.
* Fix: the courier bulk tools now load the courier code before using it.

= 3.14.0 =
* Store dashboard: products with options. Choose "Simple product" or "With options", give the options any names (Size, Colour, Weight…) and values (S, M, L…); names and values used before are suggested. "Make all combinations" creates one option per combination, each with its own price, sale price, stock and picture; "Same price for all" fills them at once. A simple product can get options later. Names that match WooCommerce → Products → Attributes use those shared lists.
* Store dashboard: brand on the product form (optional) and "+ New category" / "+ New brand" right there. The Store Manager role may now add categories and brands.
* Store dashboard: sales report for today, yesterday, 7 days, 30 days, this month, last month or any dates (orders, sales, average order, items sold, a bar per day or month). Cached 5 minutes.
* Dashboard checks: email (SMTP) plugin, page cache, cart bot guard, courier booking and Free/Pro status, each with a link to fix it.

= 3.13.1 =
* License: "Order on WhatsApp" button (no key yet? one tap opens WhatsApp with a ready message that includes the site address). Also on Pro-only screens.
* Updates screen: the plugin's logo and "Tested up to WordPress 7.1" are shown; right after updating, the same version is no longer offered again.

= 3.13.0 =
* Faster pages: the shop styles are split. Every page loads a smaller main file (103 KB → 59 KB, 17 KB → 11 KB zipped); account, cart/checkout, thank-you/tracking and product reviews/questions styles load only on the pages that show them (also inside Elementor widgets and block themes). Pages look exactly the same (checked screen by screen).
* New (Order tracking, Pro, off by default): live courier status. When a customer tracks an order that was sent with Courier booking, the courier (Steadfast, Pathao, RedX, eCourier) is asked for the latest status right then; the answer is kept for 30 minutes. Nothing runs in the background.
* New (off by default): mark the order Completed when the courier says Delivered (on the tracking page or with Sync in the order).
* Tracking results are never kept in a page cache (they are personal and change).
* Speed & health: checks the LiteSpeed Cache settings that most often slow a shop (Purge All on update, Guest Mode, Cache Mobile) and warns when many failed background tasks are stored. Read only, nothing is changed.
* License: "Order on WhatsApp" button for buying a Pro key (shown when a sales number is set).

= 3.12.4 =
* New (Speed & health → "Less load from bots", all off by default): keep crawlers off search / sort / filter / cart / checkout / account pages (robots.txt); block SEO-tool and scraper bots (Ahrefs, Semrush, MJ12, PetalBot, Bytespider…) and AI training crawlers (GPTBot, ClaudeBot, CCBot…) with a tiny 403 before a page is built. Google, Bing, Facebook and WhatsApp are never blocked; blocked bots can still read robots.txt.

= 3.12.3 =
* Faster: shop pages no longer load the settings screens and the update check (about a third less plugin code per page view; on hosting without PHP OPcache this saves real time on every uncached page, the cart and the checkout). The store dashboard's data code loads only for its own requests.
* Clearer courier messages: Pathao's own reason is shown ("login email or password is wrong" / "Client ID or secret is wrong"), and a warning appears when a courier's TEST server is switched on while real details are used.

= 3.12.2 =
* Fixed: updates were not offered on shared hosting where GitHub's API refuses the server (60 questions an hour per address, shared by many sites). Kenakata Kit now falls back to GitHub's normal release page, which has no such limit.

= 3.12.1 =
* New: "Kenakata Kit X is ready" box on the Kenakata Kit screens and the WordPress Dashboard with "Update now", "Download the zip" (for a manual upload) and "What's new"; License → Updates shows the installed and newest version with "Check for updates now".
* New: Courier log (Courier booking tab): the last 40 calls to couriers with the answer code, time and the courier's or the server's own reason, so a failing courier can be diagnosed. Keys and customer details are never logged.
* New (Pro): one-tap eCourier booking (API key / secret / user ID, package code; test lists your packages).
* Fixed: courier calls use IPv4 (on hosts with half-working IPv6, Pathao behind Cloudflare could time out); longer time-out for Pathao / RedX; the Pathao login token is kept apart for the test server and renewed when the login details change; "could not reach" messages now include the server's reason.
* Faster: live-search answers for visitors can be cached by the browser and LiteSpeed for 10 minutes (product changes clear them).
* New checks in Speed & health: PHP OPcache, how long the admin page took to build, and how long Kenakata Kit itself takes to load.
* Tests: the WordPress test now also checks product-card buttons, the update box and the courier log (88 checks).

= 3.12.0 =
* Fixed: a license key made for another website (or a broken key) is no longer saved. A red message says which website the key is for and which website this is; a key that stopped working shows "License key not working" on the License page.
* New: "Speed & health" tab: switches for lighter pages (emoji and embed scripts, WooCommerce files only on shop pages, calmer Heartbeat), Cloudflare Turnstile on login / registration / checkout, a site and security checkup with "Fix" links, and a safe database clean-up. Everything is off until turned on.
* New: "Couriers I use": a tick list of 18 couriers of Bangladesh. The tracking link is made from the tracking ID automatically (Steadfast, Pathao, RedX, or your own link with {id}). Older courier lists are moved over automatically.
* New (Pro): one-tap RedX booking (API token, optional pickup store, delivery area found from the address).
* New: order tracking can also ask for the order number, so a phone number alone shows nothing.
* New: "Make order" from an incomplete checkout adds the delivery charge the customer saw and keeps the cart prices.
* New: Tools → "Delete all data when the plugin is deleted" (off by default) and a proper uninstall that always removes scheduled tasks and temporary data.
* Faster: minified CSS/JS (smaller downloads), the first product images load at once with high priority and the rest lazily, the search cache can no longer fill the database, the health check runs hourly instead of every 5 minutes, two small settings are no longer read from the database on every page.
* Security: login attempts are also limited per account (stops attacks spread over many addresses); the login popup works on pages cached for more than a day; courier booking and "Make order" use a real lock, so a double tap never creates two parcels or orders; another visitor typing the same phone number can no longer overwrite or take over a saved incomplete checkout; the address limits and Q&A / wishlist use the real visitor address behind Cloudflare; a settings form sent without its tab changes nothing; API keys are no longer altered when saved (passwords with % or < work).
* Fixed: phone numbers typed with Bengali digits (০১৭…) work everywhere (checkout, popup, tracking, incomplete orders).
* Translations: about 330 missing Bengali texts added (Q&A, My account, wishlist, Elementor widgets, new features).

= 3.11.1 =
* New: Kenakata Kit logo in the admin menu and on the settings screens.
* Changed: with a Pro license, the settings header shows "Kenakata Kit PRO" and "Pro activated"; the small PRO tags on the store dashboard, incomplete orders and courier tabs only show while there is no license. The License page has a clear Pro / Free card.

= 3.11.0 =
* New: Free and Pro. Pro (store dashboard, one-tap courier booking and courier check, incomplete orders) turns on with a license key in Kenakata Kit → License. Everything else stays Free. Keys are checked on the site itself (signed keys, no license server); test copies on localhost / .local / .test run Pro without a key.
* New: updates from GitHub. WordPress now shows "Update available" for Kenakata Kit and installs new versions from the GitHub releases. Free sites and Pro sites within their update period get updates; when a Pro update period ends, Pro keeps working and only new versions stop.
* New: Courier check on WooCommerce → Orders (a column next to the status) and on the order screen (under the billing / shipping addresses). One click opens a side panel with the customer's delivery record; the result stays on the order as a coloured badge.
* Speed: much less work for the server. Add to cart and Buy Now buttons no longer contain "add to cart" links (bots follow links, people click): a click adds the product in the background (POST) and opens the side cart, or goes straight to the checkout for Buy Now. The cart count and side cart are no longer asked from the server on every page: only when the visitor really has a cart, and then once per change.
* New: "Ignore cart links from outside the shop" (Fake-order protection, on by default): add-to-cart and Buy Now links opened without coming from your shop (bots replaying links) do not touch the cart and show the product page. robots.txt asks crawlers to skip cart links.
* Fix: removing an item in the side cart shows a light veil with a small spinner instead of a black shadow.
* Dev: new files includes/license.php, includes/updater.php, includes/cart-guard.php, includes/courier-admin.php, assets/courier-check.js/.css; new ?wc-ajax=kk_add endpoint; the plugin no longer enqueues wc-cart-fragments; options kk_license_key, kk_license_cache, kk_license_revoked; daily cron kk_license_daily; deactivation now also clears kk_incomplete_cleanup.

= 3.10.1 =
* Fix: with the side cart set to open by itself, WooCommerce no longer adds a "View cart" link next to the Add to cart button (product cards, product page, Elementor product widgets). The side cart opens and the button shows a ✓, nothing else.
* New: Open Graph locale bn_IN for Bengali sites (Yoast), as Facebook has no bn_BD.

= 3.10.0 =
* New: Incomplete orders (Kenakata Kit → Incomplete orders, off by default). When a visitor types a valid phone number on the checkout page, a ~2 KB script (checkout page only) saves their name, phone, address and cart on your site. The store dashboard gets an "Incomplete orders" screen (To call / Called / Ordered / Dismissed, search, Call and WhatsApp buttons, "Make an order" to turn the cart into a Cash on Delivery order, "Dismiss") with a count badge and a home alert; WooCommerce gets a plain list under WooCommerce → Incomplete orders. Entries only appear after the customer has been quiet for a set time (default 15 minutes), become "Ordered" automatically when an order with that phone is placed, and are deleted after a set number of days (default 30). Works with the classic and block checkout (field ids). Public route is guarded: switch, team members skipped, rate limit, size limits, cart required. WordPress privacy policy text, export and erase are included.
* Changed: couriers are now turned on one by one (Steadfast, Pathao). Only turned-on and fully set-up couriers appear on orders: one courier = one button, several = several buttons. With a courier on, the courier / tracking ID / tracking link fields are not shown any more: the booking fills them in (read-only, with an "Open tracking page" button), in the dashboard and on the WooCommerce order screen. With no courier on, the manual fields stay as before. Saving the courier tab retires the single-courier drop-down of 3.8/3.9.
* Changed: Courier check now has its own switch (off by default) and a new layout: a full-width button, then a panel with a traffic-light banner, four figure tiles, bars, the split by courier and reports from other shops.
* Fix: Courier check result floated to the side on wide screens (a CSS class name clashed with the category check boxes).
* Fix: saving the WooCommerce order screen with the tracking fields hidden can no longer wipe the tracking.
* Speed: the dashboard home summary loads orders in one batch instead of one lookup per order.
* Dev: new REST routes kenakata/v1/incomplete (public, guarded) and kenakata/v1/sd/incomplete[/<id>[/order]]; table {prefix}kk_incomplete; daily cron kk_incomplete_cleanup; option kk_incomplete_db.

= 3.9.0 =
* New: Pathao booking. Choose Pathao in Kenakata Kit → Courier booking, enter Client ID, Client secret, login email, password and your Store ID. "Test connection" lists your stores with their numbers. Pathao finds the city, zone and area from the address. Login tokens are kept until shortly before they expire and renewed automatically.
* New: BD Courier look-up (optional). With a BD Courier API token, Courier check also shows the customer's record across all couriers, the split by courier and reports from other shops, next to your own shop's record. Results are remembered (default 24 hours) so the same number does not use your plan twice; a Refresh button asks again. If BD Courier does not answer, the check still works with your own shop's record and a notice.
* Privacy: with the look-up on, the customer's phone number is sent to BD Courier when "Courier check" is tapped. Mention it in your privacy policy.
* Dev: filters kk_courier_check_providers, kk_courier_checkup_sources; kk_courier_tracking_url now gets the customer phone as 4th argument. New private settings (API keys, tokens) still live only in the kk_courier_secrets option.

= 3.8.0 =
* New: Courier booking (Kenakata Kit → Courier booking). An administrator picks a courier and saves its API keys once; every order then gets a "Send to courier" button in the store dashboard and on the WooCommerce order screen. A small box confirms the name, phone, address and amount to collect (editable), then one tap books the parcel, saves the tracking, marks the order Shipped and notifies the customer. Steadfast is the first courier; each courier is a separate adapter. API keys are stored in their own private, non-autoloaded option (never in the exportable settings) and only administrators can change them. Double taps, timeouts and unreadable answers cannot create duplicate parcels.
* New: "Refresh status" asks the courier for the parcel's current status, only when tapped.
* New: Courier check on an order. Shows how many earlier orders of this phone number were delivered or cancelled in your shop, with a green / yellow / red advice (limits are settings). It loads only when tapped.
* Fix: the "amount to collect" of Cash on Delivery orders in Processing was empty because WooCommerce counts Processing as paid. It now uses the recorded payment date.
* Dev: REST routes kenakata/v1/courier/{book,status,checkup}/<order> and courier/test; filters kk_courier_providers, kk_courier_base_url, kk_courier_tracking_url, kk_courier_checkup_sources. Courier code loads only when used.

= 3.7.0 =
* New: "After login, go to" (Kenakata Kit → Store dashboard). Choose whether only Store Managers or the whole team (administrators and shop managers too) land on the dashboard after logging in. Default is unchanged (Store Managers only). Customers are never redirected; a page asked for explicitly (for example a wp-admin screen) is respected.
* New: "More insights" on the dashboard home: sales trend (7 / 30 days chart), best-selling products (30 days) and cash on delivery money still with couriers (Shipped orders). Each block loads only when tapped and is cached for 5 minutes.
* New: "Show sales amounts to managers" switch. Off = shop managers and Store Managers see order counts only; administrators always see amounts.
* New: dashboard colours. Pick one of 7 ready colour sets or your own two colours (main and second). The colours also apply to the plugin's settings screens. Status colours (green / amber / red) stay fixed. "Same as my shop" keeps the previous look.
* New: user guide section "Store dashboard" (Bengali) and Bengali translations for all new texts.
* Dev: new REST routes kenakata/v1/sd/report/{trend,top,cod} (administrators, or managers when sales amounts are allowed); functions kk_sd_presets, kk_sd_theme_colors, kk_sd_can_money.

= 3.6.0 =
* New: Store dashboard (Kenakata Kit → Store dashboard; off until turned on). A light, theme-free page at /dashboard (address can be changed) with Home (today / 7 / 30 days sales, orders that need attention, low and out-of-stock products), Orders (filter by status, search by number / name / phone, call and WhatsApp buttons, order history of the phone number, status change, courier and tracking link, private or customer notes, "Copy details for courier"), Products (edit name, prices, stock, pictures, categories, descriptions, per-option price and stock; add new products) and Blog (write and publish posts with pictures).
* New: "Store Manager (Kenakata)" user role: orders, products, pictures and blog posts only; optionally kept out of wp-admin (own profile and password stay available).
* New: the dashboard follows the "Plugin language" setting like the admin screens; Bengali texts included.
* Fix: Bengali "Order" read "ক্রম" (sort order) on the order tracking page; the Elementor sort control is now labelled "Sort order".
* Code: courier/tracking saving and "orders of a phone number" are shared functions (kk_save_order_tracking, kk_orders_by_phone).

= 3.5.8 =
* Fix: My Account greeting - "Greeting text" colour now also reaches the customer name, the round initial and the Log out button (they stayed white on a light greeting background). New: initial circle background and Log out hover colours.

= 3.5.7 =
* Fix: the side cart preview in the Elementor editor is now off by default and floats below the cart icon, so it never pushes the header layout.

= 3.5.6 =
* New: Products Grid / Carousel → Style → "Section spacing" (padding and background) that hides together with the products, so an empty "Recently viewed" or "Related" leaves no blank gap.

= 3.5.5 =
* New: Cart Icon widget shows an open side cart with sample products in the Elementor editor ("Preview side cart in the editor"), so every side cart style can be seen while editing.

= 3.5.4 =
* Fix: side cart mini-cart override also through wc_get_template (page builders).
* New: /wp-json/kenakata/v1/diagnose for administrators (template and free-delivery checks).

= 3.5.3 =
* Fix: the side cart always uses WooCommerce's own mini-cart (with remove buttons) even when a page builder replaces the mini-cart template site-wide (e.g. Elementor Pro Menu Cart), so the side cart styles and options work.
* New: product pages add to cart without reloading and open the side cart (Side cart → "Product pages: add to cart without reloading"). Buy Now is unchanged.
* Fix: free delivery bar amount is now exact right after a page load (calculated from the cart lines).

= 3.5.2 =
* New: "Wishlist Button" Elementor widget – place "Add to wishlist" anywhere, with its own texts, icon on/off and full style controls (normal, hover, saved).
* New: "Add to Cart Form" widget – show/hide the wishlist button (or follow the setting) and style it.
* New: "Cart Icon" widget – full styling of the side cart: panel, header, close button, product pictures, names, options, quantity, remove (×) button, subtotal, View cart and Checkout buttons, empty message.
* New: Side cart settings – show/hide pictures, remove button and View cart button; button and empty-cart texts.

= 3.5.1 =
* New: Free delivery above an amount (Checkout → Free delivery). Delivery charges become ৳0 once the cart reaches the amount; a progress bar ("Spend ৳X more to unlock Free Delivery") shows on product pages, cart, checkout and the side cart and updates after Add to cart.
* New: Elementor widget "Free Delivery Bar" and shortcode [kk_free_delivery].

= 3.5.0 =
* New: Wishlist. ♡ on product cards and product pages, works without an account (kept in the browser) and is synced to the account after login. "Wishlist Icon" (header, with count) and "Wishlist Page" Elementor widgets, [kk_wishlist] / [kk_wishlist_link], "Wishlist" in My account, "Share on WhatsApp", and a ♡ column (how often each product was saved) in Products → All products. Settings: Kenakata Kit → Wishlist.
* New: Product questions & answers. A "Questions (n)" tab on product pages with a question form (name + phone, spam protection), search, and Kenakata Kit → Questions to answer, edit, hide or bin questions. A question appears on the site only after it is answered. Email alert for new questions. Settings: Kenakata Kit → Questions & answers.

= 3.4.9 =
* New: "Product Brand" Elementor widget and [kk_product_brand] [kk_wishlist] [kk_wishlist_link] shortcode: "Brand: [small logo] Name" with a link to the brand page. Brands without a logo show their first letter (or only the name). Style tab: text, name colours, logo size / shape / background / border, spacing.

= 3.4.8 =
* New: "Always show the stock status" (Product page settings, on by default). Products that do not track stock now show "In stock" (text can be changed), so the Elementor "Product Stock" widget is no longer empty. The automatic line is not repeated inside the Add to cart form. The stock widget gets a small green / red / orange dot.

= 3.4.7 =
* New: long product descriptions are folded: the first part is shown with a fade and a "Read more" button; "Show less" folds it again. Short descriptions are not folded. The full text stays in the page for search engines. Settings: Product page → Product description (on/off, visible height for computer and phone, button texts, button colour).

= 3.4.6 =
* Fixed: "Track order" (outline) button on the account dashboard was filled green with green text when the site forces a background on all WooCommerce buttons.
* New: Forgot-password pages inside the My Account widget get a title ("Forgot your password?", "Check your email", "Set a new password"), clearer text, a full-width field and button, a "Back to login" link, and a spam-folder / WhatsApp hint after the email is sent.

= 3.4.5 =
* New: "My Account" widget redesign: login card for visitors; for customers a dashboard with greeting, latest order (status, View / Track) and quick-link cards, an icon menu (side or top), and "Hide these menu items" (e.g. Downloads). Every part has Style tab settings, and "Preview in the editor" shows the login card or the dashboard while you design. Also as [kk_my_account cards="yes" icons="yes" menu="side" hide="downloads"].
* New: "Who can see this" (Advanced tab) on every container, section, column and widget: Everyone / Visitors who are not logged in / Logged-in customers. Works with Elementor element caching.
* Changed: Kenakata Kit widgets are never stored by Elementor's element cache (they show live cart, account and price data).
* Fixed: cart page "View cart" notice button stayed visible on phones.

= 3.4.4 =
* New: "Cart Page" and "My Account" Elementor widgets (replace the [woocommerce_cart] / [woocommerce_my_account] shortcode widgets; style tab for boxes, titles and buttons).
* Fixed: brand/category image boxes could be wider than a small card on phones and overlap the next card.
* Fixed: cart on phones: product picture shown, white rows, the coupon field no longer pushes the page sideways, no "View cart" button in the notice on the cart page itself, the delivery-charge row has its label.
* Fixed: checkout: the address box had a dark border; newer WooCommerce showed a * on every delivery-area button; cleaner order summary table.
* Fixed: order-tracking steps no longer run into each other on small phones.
* Fixed: brand/category widget "Show only these" keeps the chosen order.

= 3.4.3 =
* New: scheduled tasks (cron) health check in Dashboard → Site status and GET /wp-json/kenakata/v1/status (administrators).

= 3.4.2 =
* Fixed: the review star picker showed empty boxes while hovering or after choosing a rating (WooCommerce's icon font code).

= 3.4.1 =
* Fixed: "Write a review", "Load more", the Buy Now widget, the cart icon and the account link took Elementor's global link colour (e.g. green text on a green button).

= 3.4.0 =
* Reviews back in the product tabs (last tab, new design). New "Allow reviews on every product" for imported products whose reviews are switched off.
* Imported products with the full text in the short description: shown in the Description tab, with the first lines and "Read more" next to the price.
* Security: login pause after too many wrong passwords (all login forms), browser security headers, hidden WordPress version, no code editor in the admin, optional block of PHP files in uploads, XML-RPC fully off, Cloudflare-safe visitor IP for all limits, limit on reviews with photos, security settings for administrators only.
* Update safety: pauses itself on WooCommerce older than 7.0; if a future Elementor lacks something the widgets need, only the widgets pause (with an admin notice).
* Fixed: shop managers could not save the settings.

= 3.3.0 =
* Product page: variation options (size, colour…) as buttons; out-of-stock combinations are greyed out.
* New Elementor widgets: Add to Cart Form (variation buttons, quantity, Add to cart, Buy Now, WhatsApp/call, delivery box) and Product Reviews.
* Reviews: rating summary with star bars, customer photo strip and lightbox, review cards, photo uploads with checks and approval.
* Product cards: variable products show Add to cart + Buy Now; with default options they add to the cart / go to checkout in one click.
* Products Grid: pagination (page numbers, AJAX page numbers, Load more), "Recently viewed" source, optional section title, separate rating-count style, tighter price ranges.
* New settings: promo code field on/off, colours for call/WhatsApp buttons, icons, stars, badge text, button text, sticky bar and delivery box; floating button size; side cart width.
* Fixed: false "unsaved changes" warning in the settings, side cart opening after Add to cart while that option was off, Buy Now border colour in widgets.

= 3.2.0 =
* New Bottom Bar (Kenakata Kit → Bottom Bar): drag to reorder items, change each label, icon and link, 3 styles, highlighted item, full colours and sizes, cart count, iPhone safe area, hide on scroll, phone preview.
* Elementor widgets: style settings no longer leak into other widgets; button typography, border and shadow now work with Strong styles on; button size/typography in one place ("Buttons (both)"), colours per button.
* Fixed: "Left" alignment, carousel arrow size, search bar font, WhatsApp button text colour, checkout total typography.
* Fixed: WhatsApp message line breaks, duplicate phone error and "Billing" prefix on checkout, side cart layout after AJAX add to cart, dashboard switches lost when clicked quickly.
* Branding: DigiBhai.com.

= 3.1.0 =
* Admin screens and Elementor widgets are now English and fully translatable; Bengali translation included (languages/).
* New 'Plugin language' switch on the dashboard: Automatic / English / বাংলা.
* Customer-facing texts are translated automatically on Bengali sites.
* Narrow product cards switch the Add to cart button to an icon so both buttons fit.

= 3.0.0 =
* New admin: own menu, Bengali labels and help, dashboard switches, live preview, shortcode copy buttons, widget guide, export/import, reset all.
* New product card used site-wide; titles no longer overlap prices on phones.
* New Elementor widgets: Products grid, Products carousel, Brands, Categories, Cart icon, WhatsApp/Call button, Delivery info, Checkout form. All widgets have Bengali options and full style controls.
* Carousels: autoplay, loop, dots, per-device slides.
* Live search bar mode, floating WhatsApp/call, WhatsApp order button, delivery info, sticky Buy Now bar, side cart, mobile bottom bar.
* Security: force https, hide user names, disable XML-RPC. REST: /wp-json/kenakata/v1/settings.

= 2.1.0 =
* Phone number check (exactly 11 digits by default), repeat-order block, design settings, 6 Elementor widgets.

= 2.0.0 =
* First release as Kenakata Kit: settings page; delivery areas from shipping methods.
