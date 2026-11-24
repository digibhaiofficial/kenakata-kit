# Settings reference (generated)

Generated from the code by `dev/docs/build-reference.php` — do not edit by hand.
Every option is stored in the single WordPress option `kk_settings` (defaults: `kk_defaults()` in kenakata-kit.php).
Admin: left menu **Kenakata Kit**. Labels are the English source; the shop owner sees the Bengali translation.

## Main on/off switches (Dashboard tab)

| Key | Feature | Tab | What it does |
|---|---|---|---|
| `card_enable` | New product card | cards | A clean, mobile-friendly card for every product list. |
| `buynow_enable` | Buy Now Button | buynow | Straight to checkout in one click. |
| `co_enable` | Simple checkout | checkout | Name, phone, address, delivery area. |
| `co_throttle_enable` | Repeat-order block | guard | One order per number within a set time. |
| `ty_enable` | Thank-you page | thankyou | Your own page after ordering. |
| `gd_blacklist_enable` | Blocked numbers | guard | Refuse orders from numbers you blocked. |
| `gd_risk_enable` | Pay first after many returns | guard | No cash on delivery for customers who often refuse parcels. |
| `mp_enable` | bKash / Nagad / Rocket | mobilepay | Payment by hand with Transaction ID. |
| `msg_enable` | Order messages | messaging | One-tap WhatsApp / email with ready text. |
| `print_enable` | Invoice & label printing | print | Print one order or many at once. |
| `sd_enable` | Store dashboard | storedash | Orders, products and stock at /dashboard – no wp-admin needed. |
| `search_enable` | Live Search | search | Results with image and price while typing. |
| `popup_enable` | Login popup | account | Login/registration with phone number. |
| `wa_float_enable` | Floating WhatsApp | whatsapp | WhatsApp button in the corner of every page. |
| `pp_wa_enable` | Order on WhatsApp | product | Message with the product name on product pages. |
| `pp_delivery_enable` | Delivery Info | product | Charges and time on product pages. |
| `sticky_enable` | Sticky Buy Now bar | product | At the bottom of product pages on mobile. |
| `swatches_enable` | Variation buttons | product | Size/colour options as buttons. |
| `wl_enable` | Wishlist | wishlist | ♡ button, wishlist page and count. |
| `qa_enable` | Questions & answers | qa | "Questions" tab on product pages. |
| `rv_enable` | Photo reviews | reviews | Rating summary and customer photos. |
| `cart_drawer_enable` | Side cart | cart | A cart that slides in from the side. |
| `mnav_enable` | Bottom Bar | mobile | An app-like menu at the bottom on phones. |
| `seo_schema_enable` | Delivery & returns for Google | seo | Delivery charge, time and return policy in the product data. |
| `seo_pricefaq_enable` | Price answer | seo | "How much does it cost?" with the live price on product pages. |
| `sec_hide_users` | Hide user names | security | Lowers hacking risk. |
| `sec_force_https` | Always https | security | Redirects http to https. |

## Tabs and fields

### Group: Start

#### Dashboard (`dashboard`)

See at a glance which features are on. Click a switch to turn a feature on or off – no separate saving needed.

_No stored fields (information or tools screen)._

#### License (`license`)

Free or Pro: add your license key here to turn on the Pro features on this website.

_No stored fields (information or tools screen)._

### Group: Design

#### Colours & design (`design`)

Colours, font, button and form field sizes for the whole plugin. Changes here apply everywhere at once: product cards, checkout, search, popup.

| Key | Type | Label | Help |
|---|---|---|---|
| `h_colors` | heading | Colour | Buttons, links, badges, checkout, search and popup all use these colours. |
| `use_elementor_colors` | toggle | Use Elementor Global Colors | When on, the Primary, Accent and Text colours from Elementor Site Settings are used instead of the colours below, so the whole site stays consistent. |
| `color_primary` | color | Primary colour | Add to cart button, links, selected delivery area. |
| `color_primary_hover` | color | Primary colour: hover |  |
| `color_accent` | color | Accent colour | Buy Now button. Use a dark enough colour for white text. |
| `color_accent_hover` | color | Accent colour: hover |  |
| `color_badge` | color | Discount badge colour | The "-10%" badge on product images. |
| `color_badge_text` | color | Discount badge text colour |  |
| `color_price` | color | Price colour |  |
| `color_star` | color | Rating star colour | Stars on product cards and in reviews. |
| `color_text` | color | Text colour |  |
| `color_muted` | color | Muted text colour | Old prices, product counts, etc. |
| `color_border` | color | Border colour |  |
| `color_surface_soft` | color | Soft background | Behind product images, search button, etc. |
| `color_surface_brand` | color | Light primary tint |  |
| `color_surface_accent` | color | Light accent tint |  |
| `h_type` | heading | Text & corners |  |
| `font_family` | text | Font | "inherit" uses the theme font (recommended). For another font type its name, e.g. Hind Siliguri, sans-serif |
| `font_size_base` | number | Text size |  |
| `radius` | number | Box corner radius | Cards, boxes, popup. 0 = square. |
| `h_buttons` | heading | Button | Buy Now, Add to cart, checkout, tracking, popup and thank-you page buttons. |
| `btn_height` | number | Button height | Keep 44 or more so it is easy to tap on phones. |
| `btn_radius` | number | Button corner radius | 0 = square, 40 = fully round (pill). |
| `btn_font_size` | number | Button text size |  |
| `btn_font_weight` | select | Button text weight |  |
| `btn_uppercase` | toggle | Uppercase button text |  |
| `btn_text_color` | color | Button text colour | Text on Add to cart, Buy Now and checkout buttons. White suits dark button colours. |
| `h_inputs` | heading | Form fields (checkout, tracking, popup) |  |
| `input_height` | number | Field height |  |
| `input_radius` | number | Field corner radius |  |
| `h_compat` | heading | Theme compatibility |  |
| `compat_strong` | toggle | Strong styles (recommended) | Some themes (Hello, Astra, Kadence, Flatsome…) restyle buttons and forms. Keep this on so Kenakata Kit keeps its design. |

#### Product cards (`cards`)

Every product list on the site (shop, categories, brands, search results, related products) uses this card. Each section can also be styled separately with the Elementor "Products Grid" widget.

| Key | Type | Label | Help |
|---|---|---|---|
| `card_enable` | toggle | Use this card for every product list on the site | When off, shop/category pages use the theme's or WooCommerce's own card (the Elementor "Products Grid" widget always uses this card). |
| `h_card_look` | heading | Card look |  |
| `card_style` | choice | Card style |  |
| `card_align` | choice | Text alignment |  |
| `card_image_ratio` | select | Image shape | All product images are shown at the same size. |
| `card_image_fit` | choice | Image fit | "Whole image" never crops (recommended). "Fill the box" fills the space but may crop the edges. |
| `card_title_lines` | number | Product name lines | Longer names are cut with "…". All cards keep the same height. |
| `card_title_size` | number | Name text size |  |
| `card_price_size` | number | Price text size |  |
| `card_title_tag` | select | Product name tag (SEO) | Search engines read headings (H2, H3) as the outline of a page. A home page with several product carousels can get 50+ headings that are only product names. "Plain text" keeps the look and removes those headings. "Automatic" keeps each list's own choice. Any other choice is used for every card on the site, also in Elementor widgets. |
| `h_card_show` | heading | What the card shows |  |
| `card_show_brand` | toggle | Brand name |  |
| `card_show_rating` | toggle | Rating stars (when there are reviews) |  |
| `card_show_badge` | toggle | Discount badge |  |
| `card_badge_text` | text | Badge text | {percent} = discount percent, {amount} = amount saved. Examples: -{percent}% or Save {amount}৳ or Sale |
| `card_show_stock` | toggle | "Out of stock" badge |  |
| `card_stock_text` | text | Out-of-stock badge text |  |
| `h_card_buttons` | heading | Card buttons |  |
| `card_actions_layout` | select | Buttons to show |  |
| `card_actions_mobile` | choice | Buttons on mobile | "Cart icon + Buy Now" fits both buttons in one row even on small screens. |
| `card_cart_text` | text | Add to cart button text |  |
| `h_card_archive` | heading | Shop, category and brand pages |  |
| `archive_per_page` | number | Products per page | With more products, page numbers (1, 2, 3…) appear below. |
| `archive_columns` | number | Columns: desktop |  |
| `archive_columns_tablet` | number | Columns: tablet |  |
| `archive_columns_mobile` | number | Columns: mobile |  |

#### Brands & categories (`terms`)

Default settings for brand and category cards and carousels. Each section can be changed separately in the Elementor "Brands" or "Categories" widget.

| Key | Type | Label | Help |
|---|---|---|---|
| `cards_style` | choice | Card style | "Logo only" is best for brand logos (when logos are uploaded). |
| `cards_noimg` | choice | Card without a picture shows | For brands or categories that have no logo or image yet. "Name as a logo" looks tidier than a single letter. |
| `cards_show_count` | toggle | Show product count | For example "124 products". |
| `cards_image_size` | number | Image/logo size | Without an image the first letter of the name is shown. |
| `h_terms_carousel` | heading | Carousel (slider) |  |
| `cards_per_view` | number | Visible at once: desktop |  |
| `cards_per_view_tablet` | number | Visible at once: tablet |  |
| `cards_per_view_mobile` | number | Visible at once: mobile |  |
| `cards_autoplay` | toggle | Autoplay | Pauses on hover or touch. |
| `cards_autoplay_speed` | number | Seconds between slides |  |
| `cards_loop` | toggle | Loop |  |
| `cards_arrows` | toggle | Previous/next arrows (desktop) |  |
| `cards_dots` | toggle | Dots |  |

### Group: Sales

#### Buy Now Button (`buynow`)

Takes the customer straight to checkout in one click. Shown on product cards and product pages.

| Key | Type | Label | Help |
|---|---|---|---|
| `buynow_enable` | toggle | Enable Buy Now | Adds the product to the cart and opens the checkout page. |
| `buynow_text` | text | Button text | For example: Buy Now / Order now |
| `buynow_loop` | toggle | Show on product cards | Choose which buttons show, and how, in the "Product cards" tab. |
| `buynow_single` | toggle | Show on product pages | Next to Add to cart. Works with quantity and variations. |
| `buynow_clear_cart` | toggle | Buy only this product | When on, Buy Now empties the cart first. |

#### Checkout (`checkout`)

A simple checkout for Bangladeshi customers: name, phone, address, delivery area and optional email.

| Key | Type | Label | Help |
|---|---|---|---|
| `co_enable` | toggle | Enable simple checkout | Shows the fields below instead of the long WooCommerce form. Turn off to get the original WooCommerce form back. |
| `co_country` | select | Country | Every order uses this country (the country field is hidden). |
| `co_heading` | text | Form heading |  |
| `co_button_text` | text | Order button text |  |
| `h_co_fields` | heading | Name & phone |  |
| `co_name_label` | text | Name label |  |
| `co_name_placeholder` | text | Name placeholder |  |
| `co_phone_label` | text | Phone label |  |
| `co_phone_placeholder` | text | Phone placeholder |  |
| `h_co_address` | heading | Address & email |  |
| `co_address_label` | text | Address label |  |
| `co_address_placeholder` | text | Address placeholder |  |
| `co_address_textarea` | toggle | Multi-line address box |  |
| `co_email_show` | toggle | Show email field |  |
| `co_email_required` | toggle | Email is required |  |
| `co_email_label` | text | Email label |  |
| `co_email_placeholder` | text | Email placeholder |  |
| `h_co_area` | heading | Delivery area | The shipping methods enabled in WooCommerce → Settings → Shipping (e.g. "Inside Dhaka ৳100") are shown as big buttons. The customer must choose one. |
| `h_co_district` | heading | District and thana | Customers pick their district (64) and then their thana / upazila from a list, so couriers get a clean address. City thanas of Dhaka, Chattogram, Rajshahi, Khulna, Gazipur and Narayanganj are included. "Other" lets them write it in the address. |
| `co_district_enable` | toggle | Ask for district and thana | Adds a District list (always required) to the checkout. Turn off to go back to the address field only. |
| `co_thana_mode` | select | Thana / upazila |  |
| `co_district_label` | text | District field label |  |
| `co_thana_label` | text | Thana field label |  |
| `co_area_enable` | toggle | Delivery area buttons |  |
| `co_area_label` | text | Area label |  |
| `co_area_prompt` | text | Message before an area is chosen |  |
| `co_area_list` | areas | Areas shown now |  |
| `h_co_more` | heading | Other |  |
| `co_hide_notes` | toggle | Hide order notes |  |
| `co_coupon` | select | Promo code (coupon) field | Hide the field when you are not running any offer. Coupons stay switched on in WooCommerce, so you can show the field again at any time. |
| `co_cart_hide_shipping` | toggle | Cart page: show delivery charges as information only | The customer chooses the area at checkout. |
| `co_cart_note` | text | Cart page note |  |
| `h_fd` | heading | Free delivery | Delivery becomes free when the products in the cart reach this amount. A progress bar shows how much more to buy. |
| `fd_enable` | toggle | Free delivery above an amount |  |
| `fd_amount` | number | Amount for free delivery | Cart total of products (after coupons). |
| `fd_text` | text | Message before the amount is reached | {remaining} = amount still needed, {amount} = free delivery amount. |
| `fd_done` | text | Message when free delivery is unlocked |  |
| `fd_product` | toggle | Show on product pages | Elementor product templates: use the "Free Delivery Bar" widget instead. |
| `fd_cart` | toggle | Show on the cart page |  |
| `fd_checkout` | toggle | Show on the checkout page |  |
| `fd_drawer` | toggle | Show in the side cart |  |

#### Fake-order protection (`guard`)

Blocks wrong phone numbers and repeat orders from the same number.

| Key | Type | Label | Help |
|---|---|---|---|
| `h_guard_bots` | heading | Bots and server load | Some bots open "add to cart" links over and over, which makes the server work hard for nothing. Product cards and Buy Now buttons have no such links any more; this switch also handles old links. |
| `cart_bot_guard` | toggle | Ignore cart links from outside the shop | When someone opens an "add to cart" or "Buy Now" link without coming from a page of your shop (a bot replaying links), the cart is not touched: they see the product page. Customers clicking in your shop are not affected. Turn off only if your ads link straight to "add to cart". |
| `h_guard_phone` | heading | Phone number check | Applies to checkout, order tracking and registration. |
| `co_phone_mode` | select | Accepted numbers | "Exactly 11 digits" blocks most fake numbers. Customers cannot even type more than 11 digits. |
| `co_phone_error` | text | Invalid number message |  |
| `h_guard_bl` | heading | Blocked numbers | Numbers that sent fake orders or never took their parcel. Block them from an order with one tap (order screen or store dashboard), or type them here. |
| `gd_blacklist_enable` | toggle | Use the blocked numbers list |  |
| `gd_blacklist_text` | textarea | Blocked numbers | One number per line; anything after the number is your note. Numbers blocked from an order are kept separately and are not shown here. |
| `gd_blacklist_action` | select | What happens to a blocked number | "Pay first" needs bKash / Nagad / Rocket (Mobile payment) to be on; without it the order is refused. |
| `gd_blacklist_msg` | text | Message for a blocked number |  |
| `h_guard_risk` | heading | Pay first after many returns | Looks at this customer's earlier orders in your shop (completed against cancelled / failed / refunded) and the saved Courier check result, if you checked them. Nothing is asked from outside at checkout. |
| `gd_risk_enable` | toggle | Ask customers with many returns to pay first | Cash on delivery is hidden for them; they pay with bKash / Nagad / Rocket (turn on Mobile payment, e.g. with "Only the delivery charge"). |
| `gd_risk_min` | number | Only after this many finished orders | New customers are never affected. |
| `gd_risk_rate` | number | Success rate below | Delivered orders out of all finished ones. Example: 50 = more than half were not received. |
| `gd_risk_msg` | textarea | Message for the customer | Shown above the payment choices. |
| `gd_skip_staff` | toggle | Never apply to admins / shop managers | So you can still enter a phone order for any number. |
| `h_guard_throttle` | heading | Repeat-order block |  |
| `co_throttle_enable` | toggle | Block repeat orders from the same number | A second order from the same phone number is refused within the time below. Cancelled and failed orders do not count. |
| `co_throttle_minutes` | number | Wait time |  |
| `co_throttle_ip` | toggle | Also block the same internet connection (IP) | Stops one device ordering with many numbers. Customers in the same office or on the same mobile operator can share an IP, so use with care. |
| `co_throttle_skip_admin` | toggle | Never block admins / shop managers | So you can enter phone orders yourself. |
| `co_throttle_message` | textarea | Blocked message | {minutes} = minutes left, {order} = previous order number. |

#### Thank-you page (`thankyou`)

The page customers see after ordering.

| Key | Type | Label | Help |
|---|---|---|---|
| `ty_enable` | toggle | Use a custom thank-you page | After ordering, customers go to the page below. It needs the "Order Confirmation" widget or the [kk_thank_you] shortcode. |
| `ty_page` | select | Thank-you page | Default: the page with the slug "thank-you". |
| `ty_title` | text | Title | {name} = customer name. |
| `ty_message` | textarea | Message |  |
| `ty_track_text` | text | Tracking button text |  |
| `ty_shop_text` | text | "Continue shopping" button text |  |

#### Store dashboard (`storedash`)

A simple screen for running the shop without WordPress admin: today's orders, call or WhatsApp the customer, courier and tracking, products, prices, stock and blog posts. Works well on phones.

| Key | Type | Label | Help |
|---|---|---|---|
| `sd_enable` | toggle | Turn on the store dashboard | Opens at /dashboard/ for administrators, shop managers and Store Managers. |
| `sd_slug` | text | Address | The part after your domain, e.g. "dashboard" or "manage". Lowercase English letters, numbers and dashes. Do not use the address of an existing page. |
| `sd_title` | text | Dashboard title | Shown in the browser tab. Empty = "Store dashboard". |
| `sd_low_stock` | number | Warn about low stock at | Products with stock counting on and this many or fewer items left are listed as "Low stock". 0 = off. |
| `sd_login_redirect` | choice | After login, go to | Where team members land right after they log in. Customers are never affected: they go where they went before. |
| `sd_see_sales` | toggle | Show sales amounts to managers | Turn off to show shop managers and Store Managers only the number of orders, not the money. Administrators always see everything. |
| `h_sd_colours` | heading | Dashboard colours | Pick one set below. It colours the dashboard and these settings screens. White, black and the green/yellow/red status colours stay the same so everything stays easy to read. Shop colours for your customers are set in "Colours & design". |
| `sd_theme` | theme | Colour set |  |
| `sd_color_primary` | color | Main colour | Buttons, the active menu item and links. Choose a colour dark enough for white text. |
| `sd_color_accent` | color | Second colour | Highlights: new-order alert, today's bar in the chart, the active menu marker. |
| `h_sd_team` | heading | Your team | Give a team member access: Users → Add New User → Role "Store Manager (Kenakata)". They can manage orders, products, pictures and blog posts from the dashboard, and nothing else. Administrators keep full access. |
| `sd_block_admin` | toggle | Keep Store Managers out of WordPress admin | Store Managers who open wp-admin are sent to the dashboard, and they do not see the admin bar. |

#### Incomplete orders (`incomplete`)

See the customers who reached your checkout and typed their phone number but did not place the order. Call them: many only needed a nudge, and you can turn their cart into an order in one tap.

| Key | Type | Label | Help |
|---|---|---|---|
| `incomplete_enable` | toggle | Save incomplete orders | While a customer types on the checkout page, their name, phone, address and cart are saved on your site as soon as a valid phone number is entered. Nothing is sent to anyone else. Tell your customers in your privacy policy (a text is added to WordPress → Privacy automatically). |
| `incomplete_wait` | number | Count as left after (minutes) | A checkout shows in your list only after the customer has been quiet for this long, so people still typing are not bothered. |
| `incomplete_keep_days` | number | Keep for (days) | Older entries are deleted automatically. Finished or dismissed ones go after a week. |

#### Courier booking (`courier`)

Choose your courier and add its API keys once. Then every order gets a "Send to courier" button in the store dashboard and on the order screen: one tap books the parcel, saves the tracking and marks the order Shipped.

| Key | Type | Label | Help |
|---|---|---|---|
| `h_courier_send` | heading | Send parcels from here | Turn on each courier you use and add its details. Only turned-on couriers appear on orders: one courier = one button, two couriers = two buttons. The courier, tracking ID and tracking link are then filled in automatically when a parcel is sent. |
| `courier_steadfast_on` | toggle | Steadfast | Send orders to Steadfast with one tap. |
| `courier_steadfast_api_key` | secret | API key | From your Steadfast merchant account (its API settings). It is kept private: only the last 4 characters are ever shown. Leave empty to keep the saved key. |
| `courier_steadfast_secret_key` | secret | Secret key | The second key from the same Steadfast page. Leave empty to keep the saved key. |
| `courier_steadfast_test` | courier_test | Test Steadfast | Save first, then test. It only asks Steadfast for your account balance: nothing is booked. |
| `courier_pathao_on` | toggle | Pathao | Send orders to Pathao with one tap. |
| `courier_pathao_client_id` | secret | Client ID | From your Pathao Merchant account: Developers' API → API Credentials. Leave empty to keep the saved value. |
| `courier_pathao_client_secret` | secret | Client secret | From the same Pathao page. Leave empty to keep the saved value. |
| `courier_pathao_username` | secret | Pathao login email | The email you use to sign in to your Pathao Merchant account. Leave empty to keep the saved value. |
| `courier_pathao_password` | secret | Pathao password | Your Pathao account password. It is kept private and never shown again. Leave empty to keep the saved value. |
| `courier_pathao_store_id` | text | Store ID | The number of your pickup store in Pathao. Not sure? Save the details above, then press "Test connection": it lists your stores with their numbers. |
| `courier_pathao_sandbox` | toggle | Use Pathao's test server | Only for trying things out with test credentials: parcels are not real. |
| `courier_default_weight` | select | Parcel weight | Weight sent for every parcel to couriers that ask for it (kg). |
| `courier_pathao_test` | courier_test | Test Pathao | Save first, then test. It lists your Pathao stores with their numbers: nothing is booked. |
| `courier_redx_on` | toggle | RedX | Send orders to RedX with one tap. New: please send your first parcel while watching your RedX panel. |
| `courier_redx_token` | secret | API access token | From RedX (redx.com.bd → Developer API, or ask your RedX contact). Kept private. Leave empty to keep the saved token. |
| `courier_redx_store_id` | text | Pickup store ID (optional) | Empty = your default RedX pickup store. "Test connection" lists your stores with their numbers. |
| `courier_redx_area_id` | text | Default delivery area ID (optional) | RedX needs an area for every parcel. It is found from the address; when no area name is in the address, this one is used. |
| `courier_redx_sandbox` | toggle | Use RedX's test server | Only for trying things out: parcels are not real. |
| `courier_redx_test` | courier_test | Test RedX | Save first, then test. It checks the token and lists your pickup stores: nothing is booked. |
| `courier_ecourier_on` | toggle | eCourier | Send orders to eCourier with one tap. New: please send your first parcel while watching your eCourier panel. |
| `courier_ecourier_api_key` | secret | API key | Ask eCourier for your API details (API key, API secret, user ID). Kept private. Leave empty to keep the saved value. |
| `courier_ecourier_api_secret` | secret | API secret | Leave empty to keep the saved value. |
| `courier_ecourier_user_id` | secret | User ID | Leave empty to keep the saved value. |
| `courier_ecourier_package` | text | Package code | For example #2416. "Test connection" lists your packages with their codes. |
| `courier_ecourier_zip` | text | Postcode when the order has none | eCourier asks for a postcode. Orders without one use this (for example 1212). |
| `courier_ecourier_sandbox` | toggle | Use eCourier's test server | Only for trying things out: parcels are not real. |
| `courier_ecourier_test` | courier_test | Test eCourier | Save first, then test. It checks the API details and lists your packages: nothing is booked. |
| `courier_log` | courier_log | Courier log | The last 40 calls to couriers with their answer. If sending or testing fails, the reason is here (for example a wrong key, a time-out, or the hosting blocking the connection). Keys and customer details are never written here. |
| `h_courier_check` | heading | Courier check | A "Courier check" button on every order shows how many parcels this phone number received, how many succeeded and how many were cancelled, with a simple traffic light. It appears only while this is turned on. |
| `courier_check_enable` | toggle | Turn on Courier check | Shows the button on orders in the store dashboard. It first looks at your own shop's orders; add a BD Courier token below to include other shops and couriers. |
| `courier_bdcourier_token` | secret | BD Courier API token | Optional. With it, Courier check also shows the customer's record across all couriers (Pathao, Steadfast, RedX, Paperfly and more), not only your own shop. Get a free token from your BD Courier account (API Settings). Note: the customer's phone number is sent to BD Courier when you tap Courier check. Leave empty to keep the saved token. |
| `courier_check_test` | courier_test | Test BD Courier | Save first, then test. It checks the token and shows your plan and free look-ups left. It does not use a look-up. |
| `courier_check_cache` | number | Remember a look-up for (hours) | The same phone number is not looked up again for this long, which saves your BD Courier plan. You can still refresh any result by hand. |
| `courier_good_rate` | number | Green from (% successful) | At or above this success rate: cash on delivery is fine. |
| `courier_bad_rate` | number | Red below (% successful) | Below this success rate: ask for the delivery charge first. In between is yellow. |

#### Mobile payment (`mobilepay`)

bKash, Nagad, Rocket and Upay by hand: the customer sends money to your number and types the Transaction ID (and may add a screenshot). No API, no extra fees. The order waits in "On hold" until you check your app and press "Payment received". Ask for the whole amount, or only the delivery charge in advance to stop fake orders.

| Key | Type | Label | Help |
|---|---|---|---|
| `mp_enable` | toggle | Accept bKash / Nagad / Rocket by hand | Adds a payment choice at checkout. It appears only when at least one number is filled in below. Works with the Kenakata Kit checkout and other classic checkouts (not the block checkout). |
| `mp_title` | text | Name at checkout |  |
| `mp_amount` | select | How much to pay now | "Only the delivery charge" stops most fake orders: the customer pays the delivery charge now (plus the wallet charge) and the rest in cash on delivery. The courier collect amount and labels use what is left, after you press "Payment received". |
| `mp_bkash_number` | text | bKash number | Empty = bKash is not offered. |
| `mp_bkash_type` | select | bKash account type | Personal = "Send Money", Agent = "Cash Out", Merchant = "Payment". The customer is told which one to use. |
| `mp_bkash_fee` | text | bKash charge (%) | Added to what the customer pays with bKash (the cash-out charge). 0 = no charge. |
| `mp_nagad_number` | text | Nagad number | Empty = Nagad is not offered. |
| `mp_nagad_type` | select | Nagad account type |  |
| `mp_nagad_fee` | text | Nagad charge (%) | Added to what the customer pays with Nagad (the cash-out charge). 0 = no charge. |
| `mp_rocket_number` | text | Rocket number | 12 digits (your mobile number + 1 digit). Empty = Rocket is not offered. |
| `mp_rocket_type` | select | Rocket account type |  |
| `mp_rocket_fee` | text | Rocket charge (%) | Added to what the customer pays with Rocket (the cash-out charge). 0 = no charge. |
| `mp_upay_number` | text | Upay number | Empty = Upay is not offered. |
| `mp_upay_type` | select | Upay account type |  |
| `mp_upay_fee` | text | Upay charge (%) | Added to what the customer pays with Upay (the cash-out charge). 0 = no charge. |
| `mp_screenshot` | select | Payment screenshot | Pictures are kept privately on your site (not in the Media Library) and only your team can open them from the order. |
| `mp_note` | textarea | Extra note for the customer | For example: "Add the 1.85% cash-out charge" or "Questions? Call 01XXXXXXXXX". |

#### Invoice & label (`print`)

Print invoices and shipping labels for one order or many at once: tick orders in WooCommerce → Orders and choose "Print invoices" or "Print shipping labels" (Bulk actions), or press Print in the store dashboard. With a courier connected (Pro), the same menu can send many orders to the courier and sync their status, only when you press it.

| Key | Type | Label | Help |
|---|---|---|---|
| `print_enable` | toggle | Print invoices and shipping labels | Adds Print to the orders list (Bulk actions), the order screen and the store dashboard. |
| `print_shop_name` | text | Shop name | Empty = the site title. |
| `print_shop_phone` | text | Shop phone | Shown on the invoice and as the sender on the label. |
| `print_shop_address` | textarea | Shop address | Empty = the store address in WooCommerce → Settings → General. |
| `print_logo` | toggle | Show the site logo on the invoice | The logo from Appearance → Customize → Site Identity. |
| `print_barcode` | toggle | Barcode | The order number on invoices and the courier tracking code (or the order number) on labels, as a barcode that scanners can read. |
| `print_show_sku` | toggle | Show SKU on the invoice |  |
| `print_label_size` | select | Label size | Choose the paper of your label printer. A4 prints 6 labels per sheet for an ordinary printer. |
| `print_invoice_note` | textarea | Note at the bottom of the invoice | For example a thank-you, your return policy or your Facebook page. |

#### Messages (`messaging`)

Ready messages for each order: "order confirmed", "sent with courier", "payment reminder" and more, filled in with the name, order number and amount. In the store dashboard one tap opens WhatsApp with the message ready, or sends it by email. SMS works only with your own SMS gateway and is off by default. Nothing is sent automatically.

| Key | Type | Label | Help |
|---|---|---|---|
| `msg_enable` | toggle | Message templates for orders | Adds a "Message the customer" box to each order in the store dashboard and on the WooCommerce order screen. |
| `msg_tpl_confirm` | textarea | Order confirmed | Use {name}, {order}, {total}, {to_collect} (amount to collect), {items}, {status}, {tracking}, {shop}. Empty = not offered. |
| `msg_tpl_shipped` | textarea | Sent with courier |  |
| `msg_tpl_pay` | textarea | Payment reminder |  |
| `msg_tpl_delivered` | textarea | Thank you |  |
| `msg_tpl_call` | textarea | Could not reach you |  |
| `msg_sms_enable` | toggle | Send SMS through my own gateway | Only if you already have an SMS account (for example from a Bangladeshi SMS company). Each SMS costs what your gateway charges. Sent only when you press "SMS". |
| `msg_sms_method` | select | Gateway request |  |
| `msg_sms_url` | text | Gateway address | From your SMS company's API page. Use {phone} (01…), {phone88} (8801…) and {message}, e.g. https://example.com/api?key=YOURKEY&to={phone88}&msg={message} |
| `msg_sms_body` | textarea | POST body (optional) | Only for POST: form fields (to={phone88}&msg={message}) or JSON ({"to":"{phone88}","text":"{message}"}). |

#### Order tracking & courier (`tracking`)

Customers check their order status with their phone number. A courier tracking link added to the order is shown too.

| Key | Type | Label | Help |
|---|---|---|---|
| `tr_page` | select | Tracking page | This page needs the "Order Tracking" widget or the [kk_order_tracking] shortcode. Default: the page with the slug "track-order". |
| `tr_form_label` | text | Phone field label |  |
| `tr_button_text` | text | Button text |  |
| `tr_rate_limit` | number | Lookups per visitor per 10 minutes | Stops people trying many numbers. |
| `tr_require_order` | toggle | Also ask for the order number | More private: someone who only knows a phone number cannot see that person's orders. The customer finds the order number on the thank-you page and in the order SMS/email. |
| `tr_live_courier` | toggle | Live courier status (Pro) | For orders sent with Courier booking, the tracking page asks the courier for the parcel's latest status when the customer looks (Steadfast, Pathao, RedX, eCourier). The answer is kept for 30 minutes, nothing runs in the background. |
| `tr_auto_complete` | toggle | Mark the order Completed when the courier says Delivered | Happens when the status is checked: on the tracking page or with Sync in the order. Partly delivered parcels are left for you. |
| `h_tr_texts` | heading | Text for each order step |  |
| `tr_text_received` | text | Order received |  |
| `tr_text_processing` | text | Processing |  |
| `tr_text_shipped` | text | With courier (Shipped) |  |
| `tr_text_completed` | text | Delivered (Completed) |  |
| `h_tr_courier` | heading | Courier | Enter the courier, tracking ID and link in the "Courier & tracking" box on the order edit screen. The customer gets a "Track parcel" button on the tracking page. |
| `tr_shipped_status` | toggle | Add a "Shipped" order status |  |
| `tr_auto_shipped` | toggle | Mark as Shipped when a tracking link is saved | The customer also gets a note with the link. |
| `tr_courier_pick` | courier_pick | Couriers I use | Tick the couriers you send parcels with: only these appear in the order's courier list. When you type a tracking ID, the tracking link is made automatically (where the courier has a tracking page). You can paste your own link with {id} where the tracking ID goes. |
| `tr_couriers` | textarea | Other couriers | Not in the list above? One per line: key\|Name\|tracking link (the link is optional; put {id} where the tracking ID goes). Example: fastx\|FastX Courier\|https://fastx.example/track/{id} |

### Group: Customers

#### Live Search (`search`)

Shows products while typing (with image and price).

| Key | Type | Label | Help |
|---|---|---|---|
| `search_enable` | toggle | Enable live search |  |
| `search_placeholder` | text | Search placeholder |  |
| `search_min_chars` | number | Start searching after |  |
| `search_limit` | number | Results shown | With more results a "See all" link appears below. |

#### Login popup (`account`)

Login and registration with phone or email, in a popup without leaving the page.

| Key | Type | Label | Help |
|---|---|---|---|
| `popup_enable` | toggle | Enable login popup | Visitors who click a "My account" link get a popup instead of a new page. Phone-number login always works. |
| `popup_register` | toggle | Allow registration in the popup |  |
| `popup_email_required` | toggle | Email required for registration |  |
| `popup_login_tab` | text | Login tab text |  |
| `popup_register_tab` | text | Registration tab text |  |

#### WhatsApp & call (`whatsapp`)

Floating WhatsApp/call buttons in the corner of the site.

| Key | Type | Label | Help |
|---|---|---|---|
| `wa_number` | text | WhatsApp number | For example 01XXXXXXXXX. Every WhatsApp button on the site uses this number. |
| `call_number` | text | Call number | Leave empty to use the WhatsApp number. |
| `h_wa_float` | heading | Floating buttons (in the corner of every page) |  |
| `wa_float_enable` | toggle | Floating WhatsApp button |  |
| `call_float_enable` | toggle | Floating call button | Shown above the WhatsApp button. |
| `wa_float_text` | text | Text next to the button | Leave empty for icon only. Phones always show the icon only. |
| `wa_float_message` | textarea | Pre-filled WhatsApp message | The customer can edit it before sending. |
| `wa_float_position` | choice | Position |  |
| `wa_float_offset` | number | Distance from bottom | Moves up automatically when the mobile bottom bar is on. |
| `wa_float_color` | color | WhatsApp button colour |  |
| `call_float_color` | color | Call button colour | Empty = primary colour. |
| `float_icon_color` | color | Icon and text colour |  |
| `float_size` | number | Button size | The call button is a little smaller. |
| `wa_float_mobile` | toggle | Show on mobile |  |
| `wa_float_desktop` | toggle | Show on desktop |  |

#### Product page (`product`)

Variation buttons, "Order on WhatsApp" button, delivery information and a sticky Buy Now bar on mobile for product pages.

| Key | Type | Label | Help |
|---|---|---|---|
| `h_pp_wa` | heading | Order by WhatsApp / call | Shown below the Add to cart and Buy Now buttons. Set the number in the "WhatsApp & call" tab. |
| `pp_wa_enable` | toggle | "Order on WhatsApp" button |  |
| `pp_wa_text` | text | Button text |  |
| `pp_wa_message` | textarea | Pre-filled message | {product} = product name, {price} = price, {url} = product link. |
| `pp_call_enable` | toggle | "Call to order" button |  |
| `pp_call_text` | text | Call button text |  |
| `pp_wa_color` | color | WhatsApp button colour |  |
| `pp_call_color` | color | Call button colour | Empty = primary colour. |
| `pp_btn_text_color` | color | Text colour of these buttons |  |
| `h_pp_desc` | heading | Product description |  |
| `desc_fallback` | toggle | Long short descriptions go to the Description tab | For products whose full text was saved as the short description (common after an import) and whose Description is empty: the Description tab shows the full text, and next to the price only the first lines appear with a "Read more" link. Products are not changed. |
| `stock_always` | toggle | Always show the stock status | WooCommerce shows nothing for an in-stock product when "Manage stock?" is off, so the Elementor "Product Stock" widget stays empty. With this on, those products show the text below. Products that track stock keep WooCommerce's own text (e.g. "12 in stock", "Out of stock"). |
| `stock_in_text` | text | "In stock" text | For example: স্টকে আছে |
| `rm_enable` | toggle | Fold long descriptions ("Read more") | Shows the first part of a long description with a "Read more" button that opens the full text. The whole text stays on the page, so search engines still read it. Short descriptions are not folded. |
| `rm_height` | number | Visible height (computer and tablet) |  |
| `rm_height_mobile` | number | Visible height (phone) |  |
| `rm_more_text` | text | "Read more" button text | For example: আরও পড়ুন |
| `rm_less_text` | text | "Show less" button text | For example: কম দেখুন |
| `rm_color` | color | Button colour | Empty = primary colour. |
| `h_pp_swatches` | heading | Variation options (size, colour…) | Shows each option as a button instead of a dropdown. Out-of-stock combinations are greyed out. The look follows the primary colour; the "Add to Cart Form" Elementor widget can style it further. |
| `swatches_enable` | toggle | Show options as buttons |  |
| `swatches_height` | number | Button height |  |
| `swatches_radius` | number | Button corner radius |  |
| `swatches_clear` | toggle | Show the "Clear" link |  |
| `h_pp_delivery` | heading | Delivery information box | Delivery charges (taken from the shipping settings), delivery time and cash-on-delivery information. |
| `pp_delivery_enable` | toggle | Show delivery information on product pages |  |
| `di_title` | text | Box title |  |
| `di_show_charges` | toggle | Show delivery charges |  |
| `di_lines` | textarea | Extra lines | One line each. |
| `di_bg` | color | Box background | Empty = soft background colour. |
| `di_icon_color` | color | Icon colour | Empty = primary colour. |
| `h_pp_sticky` | heading | Sticky bar on mobile | When the customer scrolls down, the image, price and Buy Now button stay at the bottom of the screen. |
| `sticky_enable` | toggle | Sticky Buy Now bar |  |
| `sticky_show_cart` | toggle | Also show Add to cart in the sticky bar |  |
| `sticky_bg` | color | Sticky bar background |  |

#### Wishlist (`wishlist`)

A ♡ button on product cards and product pages. Works without an account; after login the list is kept on the account. Put the "Wishlist Page" widget (or [kk_wishlist]) on a page with the slug "wishlist".

| Key | Type | Label | Help |
|---|---|---|---|
| `wl_enable` | toggle | Wishlist |  |
| `wl_card` | toggle | ♡ on product cards | Top-right corner of the product picture. |
| `wl_single` | toggle | "Add to wishlist" on product pages | Next to the Add to cart button. |
| `wl_page` | select | Wishlist page | This page needs the "Wishlist Page" widget or the [kk_wishlist] shortcode. Default: the page with the slug "wishlist". It also appears in My account. |
| `wl_text_add` | text | Button text |  |
| `wl_text_added` | text | Button text when saved |  |
| `wl_empty_text` | text | Text when the list is empty |  |
| `wl_share` | toggle | "Share on WhatsApp" button on the wishlist page | Sends a link that opens the same list, e.g. to family who will pay. |
| `wl_color` | color | Heart colour when saved |  |

#### Questions & answers (`qa`)

A "Questions" tab on product pages. Customers ask with their name and phone number; a question appears on the site only after your team answers it (Kenakata Kit → Questions).

| Key | Type | Label | Help |
|---|---|---|---|
| `qa_enable` | toggle | Questions & answers on product pages |  |
| `qa_tab_title` | text | Tab title | The number of answered questions is added, e.g. "Questions (3)". |
| `qa_require_phone` | toggle | Phone number is required | Fewer spam questions, and your team can call back. The number is never shown on the site. |
| `qa_notify` | toggle | Email me about new questions | Sent to the site admin email (Settings → General). |
| `qa_team_name` | text | Name shown on answers |  |
| `qa_intro` | textarea | Text above the question form |  |
| `qa_thanks` | text | Message after sending a question |  |

#### Reviews (`reviews`)

A tidy reviews section for product pages: rating summary, review cards and photos from customers.

| Key | Type | Label | Help |
|---|---|---|---|
| `rv_enable` | toggle | Use the Kenakata Kit reviews section | A rating summary with bars, review cards with photos and a tidy review form. Works inside the product tabs and with the "Product Reviews" Elementor widget. Turn off to get the theme/WooCommerce reviews back. |
| `rv_title` | text | Section title |  |
| `rv_summary` | toggle | Rating summary (average + 5-to-1 star bars) |  |
| `rv_gallery` | toggle | Customer photos strip above the reviews |  |
| `rv_button_text` | text | "Write a review" button text |  |
| `rv_tab` | toggle | Show reviews in the product tabs | Turn off when you place the "Product Reviews" widget on the product template, so reviews are not shown twice. |
| `rv_open_all` | toggle | Allow reviews on every product | Imported products often have "Enable reviews" switched off, so they show no Reviews tab. When on, every product accepts reviews, without changing the products. |
| `h_rv_photos` | heading | Photos in reviews | Customers can attach photos to their review. Photos are checked (JPG, PNG or WebP only), resized and saved in the Media Library. |
| `rv_photos` | toggle | Allow photos in reviews |  |
| `rv_photos_max` | number | Photos per review |  |
| `rv_photos_size` | number | Largest photo size |  |
| `rv_photos_who` | select | Who can add photos | "Customers who bought it" checks the email address against past orders. |
| `rv_photos_hold` | toggle | Approve reviews with photos before they appear | Recommended. Approve them in Products → Reviews. |
| `rv_photo_label` | text | Photo field label |  |

#### Side cart (`cart`)

The cart slides in from the side when the cart icon is clicked or a product is added – no page change.

| Key | Type | Label | Help |
|---|---|---|---|
| `cart_drawer_enable` | toggle | Enable side cart | Clicking the "Cart Icon" widget or the [kk_cart_icon] shortcode opens the cart from the side. |
| `cart_drawer_takeover` | toggle | Also open it from the header cart icon | For a header made with Elementor's "Menu Cart" widget or a theme cart icon: a click opens this side cart instead of their own cart panel, so there is only one cart. Turn off to get their panel back. |
| `cart_drawer_auto_open` | toggle | Open the side cart after Add to cart |  |
| `cart_drawer_title` | text | Side cart title |  |
| `cart_drawer_width` | number | Side cart width | Phones always use almost the full width. |
| `cart_drawer_ajax` | toggle | Product pages: add to cart without reloading | Add to cart on the product page adds the product in the background and opens the side cart. Buy Now still goes straight to checkout. |
| `cart_drawer_thumbs` | toggle | Show product pictures |  |
| `cart_drawer_remove` | toggle | Show the remove (×) button | Lets customers take a product out of the cart without leaving the page. |
| `cart_drawer_view` | toggle | Show the "View cart" button | Off = only the Checkout button. |
| `cart_drawer_view_text` | text | "View cart" button text |  |
| `cart_drawer_checkout_text` | text | Checkout button text |  |
| `cart_drawer_empty_text` | text | Text when the cart is empty |  |

#### Bottom Bar (`mobile`)

An app-like menu fixed to the bottom of the screen on phones: Home, Shop, Cart, Account, Orders and more. Hidden on computers.

| Key | Type | Label | Help |
|---|---|---|---|
| `h_bbar_main` | heading | Bottom Bar | Shown only on phones, fixed to the bottom of the screen. Page content is pushed up automatically so nothing is hidden behind it, and the iPhone home-indicator area is respected. |
| `mnav_enable` | toggle | Enable the bottom bar |  |
| `bbar_items` | bbar_items | Menu items | Drag to change the order. Switch items on or off and change the label, icon and link. 4–5 items work best on phones. |
| `h_bbar_look` | heading | Look |  |
| `bbar_style` | choice | Style | "Pill" puts a soft coloured pill behind the active icon. "Floating" is a rounded bar with space around it. |
| `bbar_featured` | select | Highlighted item | Shows one item as a raised round button in the primary colour (for example Cart or Home). Put it in the middle of the list for the best look. |
| `bbar_labels` | toggle | Show labels under the icons | Recommended: labels make the menu easier to understand. |
| `bbar_height` | number | Bar height | The iPhone home-indicator space is added on top of this. |
| `bbar_icon_size` | number | Icon size |  |
| `bbar_label_size` | number | Label size |  |
| `h_bbar_colors` | heading | Colours |  |
| `bbar_bg` | color | Background |  |
| `bbar_border` | color | Top border |  |
| `bbar_shadow` | choice | Shadow |  |
| `bbar_icon_color` | color | Icon colour |  |
| `bbar_text_color` | color | Label colour |  |
| `bbar_active_color` | color | Active item colour | Icon and label of the page the customer is on, and the highlighted button. |
| `bbar_active_bg` | color | Active pill colour | Used by the "Pill" style. |
| `bbar_badge_bg` | color | Cart count badge |  |
| `h_bbar_where` | heading | Where and when |  |
| `bbar_breakpoint` | number | Show on screens narrower than | 767 = phones only (recommended). 1024 = phones and tablets. |
| `bbar_hide_checkout` | toggle | Hide on the checkout page | Keeps the customer focused on finishing the order. |
| `bbar_hide_cart` | toggle | Hide on the cart page |  |
| `bbar_hide_on_scroll` | toggle | Hide while scrolling down | The bar slides away while reading and comes back when scrolling up. |
| `bbar_cats_url` | text | Categories page link | Used by items that link to "Categories page". Empty = the page with the slug "categories", or the Shop page. |

### Group: Site

#### Security (`security`)

Basic security protections, one click each.

| Key | Type | Label | Help |
|---|---|---|---|
| `sec_force_https` | toggle | Always use https (secure connection) | Visitors arriving on http:// are sent to https://. Can only be turned on while you are in the admin over https. |
| `sec_hide_users` | toggle | Hide admin user names | Outsiders cannot find your login name through /wp-json/wp/v2/users or ?author=1. Author archive pages are turned off. |
| `sec_disable_xmlrpc` | toggle | Disable XML-RPC | Hackers use this old feature to guess passwords. Keep it off unless you use Jetpack or the mobile app. |
| `h_sec_login` | heading | Login protection |  |
| `sec_limit_login` | toggle | Pause login after too many wrong passwords | Applies to wp-login.php, My account, the login popup and XML-RPC. The connection that made the wrong attempts waits 15 minutes; everybody else can log in normally. A browser where you logged in before is never paused, and the message offers a password reset. |
| `sec_login_attempts` | number | Wrong passwords allowed |  |
| `sec_login_locks` | login_locks | Paused logins now | Locked yourself out on a new browser? Log in from a browser you used before (or reset your password) and lift the pause here. |
| `h_sec_more` | heading | More protection |  |
| `sec_headers` | toggle | Browser security headers | Stops other sites from showing your shop inside a frame, blocks some file tricks and hides the PHP version. With "Always use https" on, browsers are also told to use https only. |
| `sec_hide_version` | toggle | Hide the WordPress version | Removes the version number from the page source, so attackers cannot quickly look up known weaknesses. |
| `sec_no_file_edit` | toggle | Turn off the theme/plugin code editor in the admin | If a password ever leaks, nobody can change your site's code from the admin. Updates still work. |
| `sec_uploads_php` | toggle | Block PHP files in the uploads folder | Pictures and documents never need to run as code. Even if a harmful file is uploaded somehow, it cannot run. (Apache/LiteSpeed servers; written to wp-content/uploads/.htaccess.) |

#### SEO & Google (`seo`)

Extra product information for Google and AI search: delivery charges and the return policy in the product data, and a short "How much does it cost?" answer on every product page. Works with Rank Math or WooCommerce's own product data. Every switch is off until you turn it on.

| Key | Type | Label | Help |
|---|---|---|---|
| `h_seo_schema` | heading | Delivery and returns in the product data | Google Search Console (Merchant listings) asks for the delivery charge, the delivery time and the return policy of every product. Without them it shows "Missing field shippingDetails / hasMerchantReturnPolicy" and cannot show delivery or returns in the search results. Check one product afterwards with Google's Rich Results Test. |
| `seo_schema_enable` | toggle | Add delivery and return details to products | Nothing changes on the page itself: only the hidden product data that search engines read. |
| `seo_ship_local_region` | select | Area with its own charge | Most shops charge less inside Dhaka. Choose "None" if the charge is the same everywhere. |
| `seo_ship_local_cost` | text | Delivery charge in that area | Only the number, e.g. 80. Empty = the lowest delivery charge in WooCommerce shipping. |
| `seo_ship_local_days` | text | Delivery time in that area (days) | For example 1-2. |
| `seo_ship_other_cost` | text | Delivery charge for the rest of the country | Only the number, e.g. 150. Empty = the highest delivery charge in WooCommerce shipping. |
| `seo_ship_other_days` | text | Delivery time for the rest of the country (days) | For example 2-5. |
| `seo_handling_days` | text | Time to pack and hand over to the courier (days) | For example 0-1. Empty = not added. |
| `seo_return_mode` | select | Return policy | Write only what your return policy page really says: Google compares them. |
| `seo_return_days` | number | Days to return |  |
| `seo_return_fees` | select | Who pays for the return delivery |  |
| `seo_return_no_cats` | text | Categories that cannot be returned | Category slugs, separated by commas (e.g. medicine, innerwear). Products in them are marked "no returns". Empty = none. |
| `seo_return_page` | select | Return policy page | Linked in the product data. Empty = WooCommerce's "Refund and Returns Policy" page. |
| `h_seo_pricefaq` | heading | "How much does it cost?" on product pages | Many people search "price of …" on Google or ask ChatGPT. A short answer under the description with the current price, stock and delivery charges helps them find your shop. The price is always the current one. |
| `seo_pricefaq_enable` | toggle | Show the price answer under the product description | Or place it yourself with [kk_price_faq] (then it is not added again). |
| `seo_pricefaq_tag` | select | Question heading |  |
| `seo_pricefaq_title` | text | Question | {name} = product name. |
| `seo_pricefaq_text` | textarea | Answer | One paragraph per line. {name} = product, {price} = current price, {stock} = in stock or not, {site} = shop name, {delivery} = delivery charges from WooCommerce shipping. Write only what is true for your shop. |

#### Speed & health (`speed`)

Make the site lighter, stop login bots, and see a checkup of speed and security with what to fix. Every switch is off until you turn it on.

| Key | Type | Label | Help |
|---|---|---|---|
| `h_speed` | heading | Lighter pages | After turning a switch on, open your home page, a product, the cart and the checkout once to check, and clear the cache if you use a cache plugin. |
| `speed_no_emoji` | toggle | Remove the emoji script | WordPress adds a script to every page for emoji on very old browsers. Phones and new browsers show emoji without it. |
| `speed_no_embeds` | toggle | Remove the embed script | Only needed to show your posts as cards inside other WordPress sites. |
| `speed_wc_assets` | toggle | WooCommerce files only on shop pages | Blog posts and plain pages load without WooCommerce's styles and scripts. Shop, product, cart, checkout, account, the home page and pages with product widgets keep them. |
| `speed_block_css` | toggle | Block editor styles only where blocks are used | Pages built with Elementor do not need the block editor's style file (about 100 KB, 15 KB compressed, loaded before the page shows). Posts and pages written with blocks, cart, checkout and My account keep it. |
| `speed_heartbeat` | toggle | Calmer "Heartbeat" | An open admin tab asks the server every 15–60 seconds. This makes it every 2 minutes (the post editor keeps its normal speed for auto-save) and turns it off for shop visitors. |
| `h_bots` | heading | Less load from bots | On a busy shop most page views that cannot be served from the cache come from bots. These switches keep useful crawlers (Google, Bing, Facebook, WhatsApp) and stop the rest. |
| `speed_robots_shop` | toggle | Keep crawlers off search, sort and filter pages | Adds rules to robots.txt so search engines skip ?s=, ?orderby=, filters, cart, checkout and account pages: endless page combinations that can never be cached. Products, categories and posts stay fully indexed. |
| `speed_bot_block` | toggle | Block SEO-tool and scraper bots | Ahrefs, Semrush, MJ12, DotBot, PetalBot, Bytespider, Baidu, Yandex and similar get a tiny "Forbidden" answer instead of a full page. They bring no customers in Bangladesh. (If you use Ahrefs or Semrush for your own SEO, leave this off.) |
| `speed_ai_block` | toggle | Block AI training crawlers | GPTBot, ClaudeBot, CCBot, Google-Extended and Meta download whole shops to train AI models; they send no customers. Normal Google search is not affected. |
| `speed_ai_search_block` | toggle | Block AI answer engines | ChatGPT search, Perplexity and Claude open a page when someone asks them a question, and show a link to your shop in the answer. Usually best left off (allowed): they bring customers. |
| `h_turnstile` | heading | Stop bots: Cloudflare Turnstile | A free check from Cloudflare that real people usually do not even notice. Get the two keys at dash.cloudflare.com → Turnstile → Add site (your site does not need to use Cloudflare). |
| `turnstile_enable` | toggle | Use Cloudflare Turnstile |  |
| `turnstile_site` | text | Site key |  |
| `turnstile_secret` | secret | Secret key | Kept private: only the last 4 characters are ever shown. Leave empty to keep the saved key. |
| `turnstile_login` | toggle | On login and registration | wp-login.php, My Account and the login popup. If your site uses another login form (for example the Elementor "Login" widget), log out and test it once. |
| `turnstile_checkout` | toggle | On checkout | Stops bots placing fake orders. If Cloudflare cannot be reached, the order still goes through. |

#### Tools (Export / Import) (`tools`)

Move all settings to another site with one file, or reset everything.

_No stored fields (information or tools screen)._

### Group: Help

#### Elementor widgets (`widgets`)

Find these widgets in the "Kenakata Kit" category of the Elementor panel. Drag one onto the page, then choose what it shows in the Content tab and how it looks in the Style tab.

_No stored fields (information or tools screen)._

#### Shortcodes (`shortcodes`)

To use the features without Elementor (block editor, other page builders), copy these shortcodes.

_No stored fields (information or tools screen)._

#### Full guide (`help`)

The complete user guide (in Bengali).

_No stored fields (information or tools screen)._

## Shortcodes

| Shortcode | What | Options |
|---|---|---|
| `[kk_products source="featured" limit="8" columns="4"]` | Products grid / carousel | source="latest\|featured\|sale\|best_selling\|top_rated\|category\|brand\|ids\|related\|current" · category="slug,slug" · brand="slug" · ids="12,34" · limit="8" · columns="4" columns_tablet="3" columns_mobile="2" · layout="grid\|carousel" · orderby="date\|price\|popularity\|rating\|title\|rand" order="DESC\|ASC" · paginate="yes" · buttons="side\|stack\|buynow_only\|cart_only\|none" |
| `[kk_brands layout="carousel" limit="18"]` | Brand cards | layout="carousel\|grid" · limit="18" (0 = all) · orderby="count\|name" · style="card\|minimal\|circle\|logo" · include="5,6" exclude="12" · show_count="yes\|no" |
| `[kk_categories layout="grid"]` | Category cards | Same options as brands · parent="0" |
| `[kk_live_search mode="bar"]` | Live Search | mode="icon\|bar" · placeholder="…" |
| `[kk_cart_icon show_total="yes"]` | Cart Icon | show_total="yes\|no" |
| `[kk_account_link]` | Login / account link | login_text="Log in" account_text="My account" |
| `[kk_buy_now id="123"]` | Buy Now Button | id="123" · text="Order now" |
| `[kk_whatsapp text="Order on WhatsApp"]` | WhatsApp / Call Button | type="whatsapp\|call" · number="01XXXXXXXXX" · message="…" ({product}, {price}, {url}) · text="…" |
| `[kk_delivery_info]` | Delivery Info | title="…" · charges="yes\|no" |
| `[kk_order_tracking]` | Order tracking form | — |
| `[kk_thank_you]` | Order confirmation (on the thank-you page) | — |

## Elementor widgets (category "Kenakata Kit")

| Widget class | Title | File |
|---|---|---|
| `KK_EW_Buy_Now` | Buy Now Button | includes/elementor/misc.php |
| `KK_EW_Live_Search` | Live Search | includes/elementor/misc.php |
| `KK_EW_Cart_Icon` | Cart Icon | includes/elementor/misc.php |
| `KK_EW_Account_Link` | Login / My Account | includes/elementor/misc.php |
| `KK_EW_Contact_Button` | WhatsApp / Call Button | includes/elementor/misc.php |
| `KK_EW_Delivery_Info` | Delivery Info | includes/elementor/misc.php |
| `KK_EW_Free_Delivery` | Free Delivery Bar | includes/elementor/misc.php |
| `KK_EW_Checkout` | Checkout Form | includes/elementor/misc.php |
| `KK_EW_Cart` | Cart Page | includes/elementor/misc.php |
| `KK_EW_My_Account` | My Account | includes/elementor/misc.php |
| `KK_EW_Wishlist_Icon` | Wishlist Icon | includes/elementor/misc.php |
| `KK_EW_Wishlist_Page` | Wishlist Page | includes/elementor/misc.php |
| `KK_EW_Box_Widget` | Order Tracking | includes/elementor/misc.php |
| `KK_EW_Thank_You` | Order Confirmation | includes/elementor/misc.php |
| `KK_EW_Product_Base` | Product Brand | includes/elementor/product.php |
| `KK_EW_Add_To_Cart` | Add to Cart Form | includes/elementor/product.php |
| `KK_EW_Product_Reviews` | Product Reviews | includes/elementor/product.php |
| `KK_EW_Wishlist_Button` | Wishlist Button | includes/elementor/product.php |
| `KK_EW_Products` | Products Grid | includes/elementor/products.php |
| `KK_EW_Products_Carousel` | Products Carousel | includes/elementor/products.php |
| `KK_EW_Terms_Base` | Brands | includes/elementor/terms.php |
| `KK_EW_Categories` | Categories | includes/elementor/terms.php |
| `KK_EW_Terms` | Brand/Category (old) | includes/elementor/terms.php |
