=== Kenakata Kit – WooCommerce Toolkit for Bangladesh ===
Contributors: DigiBhai.com
Plugin URI: https://digibhai.com/
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 3.11.0
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
