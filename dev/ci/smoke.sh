#!/usr/bin/env bash
# Real WordPress + WooCommerce + Kenakata Kit, then the important paths. Used by .github/workflows/wp-test.yml.
# Needs: php, wp-cli, a MySQL server on 127.0.0.1 (root/root, database "wp"), curl.
set -u
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
WP=/tmp/wp
URL=http://localhost:8080
JAR=/tmp/cookies.txt
FAILS=0
PASSES=0
ok()   { PASSES=$((PASSES+1)); echo "  ✓ $1"; }
bad()  { FAILS=$((FAILS+1));  echo "  ✗ $1"; }
check(){ if eval "$2"; then ok "$1"; else bad "$1"; fi; }
wpc()  { wp --path="$WP" --allow-root "$@"; }

echo "== Install WordPress ${WP_VERSION:-latest} + WooCommerce ${WC_VERSION:-latest}"
# Local mode (no MySQL, no wordpress.org): LOCAL_WP=<WordPress folder> LOCAL_WC_ZIP=<woocommerce.zip>
# LOCAL_SQLITE=<sqlite-database-integration plugin folder> runs the same test on SQLite.
rm -rf "$WP"; mkdir -p "$WP"
if [ -n "${LOCAL_WP:-}" ]; then
	cp -r "$LOCAL_WP/." "$WP/" && rm -rf "$WP/.git"
	cp -rL "$LOCAL_SQLITE" "$WP/wp-content/plugins/sqlite-database-integration"
	sed "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WP/wp-content/plugins/sqlite-database-integration#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" "$LOCAL_SQLITE/db.copy" > "$WP/wp-content/db.php"
else
	wpc core download --version="${WP_VERSION:-latest}" --quiet || exit 1
fi
wpc config create --dbname=wp --dbuser=root --dbpass=root --dbhost=127.0.0.1 --skip-check --quiet --extra-php <<'PHP' || exit 1
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', false );
if ( file_exists( '/tmp/kk-test-pub.php' ) ) { require '/tmp/kk-test-pub.php'; }
PHP

# A test signing key, so license keys can be made here (the real private key never leaves the owner's panel).
php -r '
$kp  = sodium_crypto_sign_keypair();
file_put_contents("/tmp/kk-test-secret", base64_encode(sodium_crypto_sign_secretkey($kp)));
file_put_contents("/tmp/kk-test-pub.php", "<?php define( \"KK_LICENSE_PUBKEY\", \"" . base64_encode(sodium_crypto_sign_publickey($kp)) . "\" );\n");
' || exit 1
mkkey() { # mkkey domain → a signed key for that domain
	php -r '
	$sk = base64_decode(file_get_contents("/tmp/kk-test-secret"));
	$b  = function ($s) { return rtrim(strtr(base64_encode($s), "+/", "-_"), "="); };
	$p  = $b(json_encode(array("v" => 1, "id" => "T-1", "d" => array($argv[1]), "p" => "pro", "e" => 0, "i" => time())));
	echo "KK1." . $p . "." . $b(sodium_crypto_sign_detached($p, $sk));
	' "$1"
}

wpc core install --url="$URL" --title="KK test" --admin_user=admin --admin_password=admin-pass-123 --admin_email=ci@example.com --skip-email --quiet || exit 1
if [ -n "${LOCAL_WC_ZIP:-}" ]; then
	wpc plugin install "$LOCAL_WC_ZIP" --activate --quiet || exit 1
else
	wpc plugin install woocommerce $( [ "${WC_VERSION:-latest}" != latest ] && echo "--version=$WC_VERSION" ) --activate --quiet || exit 1
fi
cp -r "$REPO/kenakata-kit" "$WP/wp-content/plugins/kenakata-kit"
wpc plugin activate kenakata-kit || exit 1
wpc wc tool run install_pages --user=1 >/dev/null 2>&1 || true
wpc option update woocommerce_coming_soon no >/dev/null 2>&1 || true
# Classic checkout and cart (what Kenakata Kit's checkout builds on; new WooCommerce makes block pages)
wpc post update "$(wpc option get woocommerce_checkout_page_id)" --post_content='[woocommerce_checkout]' >/dev/null
wpc post update "$(wpc option get woocommerce_cart_page_id)" --post_content='[woocommerce_cart]' >/dev/null
PID=$(wpc wc product create --user=1 --name="Test Saree" --regular_price=1500 --porcelain)
wpc post create --post_type=page --post_status=publish --post_title=Track --post_content='[kk_order_tracking]' --porcelain >/dev/null
CARDS=$(wpc post create --post_type=page --post_status=publish --post_title=Cards --post_content='[kk_products limit="4"]' --porcelain)
echo "product $PID"

php -S localhost:8080 -t "$WP" > /tmp/server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null' EXIT
sleep 2

clean() { ! grep -qE "Fatal error|Parse error|Warning:|Notice:|Deprecated:|There has been a critical error" "$1"; }
get() { curl -s -o /tmp/body -w "%{http_code}" -b "$JAR" -c "$JAR" "$@"; }

echo "== Shop pages (visitor)"
for path in "/" "/?post_type=product" "/?p=$PID" "/?page_id=$(wpc option get woocommerce_cart_page_id)" "/?page_id=$(wpc option get woocommerce_checkout_page_id)" "/?page_id=$(wpc option get woocommerce_myaccount_page_id)" "/?pagename=track&kk_phone=01712345678"; do
	code=$(curl -sL -o /tmp/body -w "%{http_code}" "$URL$path")
	check "GET $path → $code, no PHP errors" '[ "$code" = 200 ] && clean /tmp/body'
done
check "product cards have instant Add to cart + Buy Now" 'curl -sL "$URL/?page_id=$CARDS" | grep -q "data-kk-add" && curl -sL "$URL/?page_id=$CARDS" | grep -q "data-kk-buy"'
check "robots.txt blocks add-to-cart links for bots" 'curl -s "$URL/?robots=1" | grep -qi "add-to-cart"'

echo "== Add to cart"
code=$(curl -s -o /dev/null -w "%{http_code}" "$URL/?add-to-cart=$PID")
check "bot GET ?add-to-cart without referrer is redirected (302), nothing added → $code" '[ "$code" = 302 ]'
rm -f /tmp/c1; curl -s -o /tmp/body -c /tmp/c1 -X POST -d "product_id=$PID&quantity=1" "$URL/?wc-ajax=kk_add"
check "kk_add (instant add) returns fragments" 'grep -q "fragments" /tmp/body'
check "kk_add sets the cart cookie" 'grep -q woocommerce_items_in_cart /tmp/c1'
curl -s -o /tmp/body -X POST -d "product_id=$PID&quantity=1&buy=1" "$URL/?wc-ajax=kk_add"
check "Buy Now (kk_add buy=1) answers with the checkout address" 'grep -q "redirect" /tmp/body'
code=$(curl -s -o /dev/null -w "%{http_code}" "$URL/?wc-ajax=kk_add")
check "kk_add refuses GET (405) → $code" '[ "$code" = 405 ]'
check "live search REST answers" 'curl -s "$URL/?rest_route=/kenakata/v1/search&q=Saree" | grep -q "Saree"'

echo "== Admin"
rm -f "$JAR"
curl -s -o /dev/null -c "$JAR" "$URL/wp-login.php"
curl -s -o /dev/null -b "$JAR" -c "$JAR" -d "log=admin&pwd=admin-pass-123&wp-submit=Log+In&testcookie=1&redirect_to=$URL/wp-admin/" "$URL/wp-login.php"
get -L "$URL/wp-admin/" >/dev/null  # WooCommerce may send the first admin visit to its setup screen
code=$(get "$URL/wp-admin/profile.php")
check "admin login works → $code" '[ "$code" = 200 ]'
for tab in $(wpc eval 'echo implode( " ", array_keys( kk_tabs() ) );'); do
	code=$(get "$URL/wp-admin/admin.php?page=kenakata-kit&tab=$tab")
	check "settings tab $tab → $code, no PHP errors" '[ "$code" = 200 ] && clean /tmp/body && grep -q "kk-" /tmp/body'
done
code=$(get "$URL/wp-admin/edit.php?post_type=shop_order"); code2=$(get "$URL/wp-admin/admin.php?page=wc-orders")
check "WooCommerce orders list opens (classic $code / HPOS $code2)" '[ "$code" = 200 ] || [ "$code2" = 200 ]'

echo "== License"
NONCE=$(curl -s -b "$JAR" "$URL/wp-admin/admin.php?page=kenakata-kit&tab=license" | grep -oP 'name="_wpnonce" value="\K[0-9a-f]+' | head -1)
post_key() { curl -s -o /dev/null -w "%{redirect_url}" -b "$JAR" -c "$JAR" --data-urlencode "kk_license_key=$1" -d "action=kk_license_save&_wpnonce=$NONCE&_wp_http_referer=/" "$URL/wp-admin/admin-post.php"; }
r=$(post_key "$(mkkey screenshaper.com)")
check "key for another domain is refused (license-bad, code domain)" 'echo "$r" | grep -q "kk_msg=license-bad" && echo "$r" | grep -q "kk_code=domain"'
check "…and it was NOT saved" '[ -z "$(wpc option get kk_license_key 2>/dev/null)" ]'
r=$(post_key "KK1.garbage.key")
check "broken key is refused (format)" 'echo "$r" | grep -q "kk_code=format"'
r=$(post_key "$(mkkey localhost)")
check "key for this site is saved (license-saved)" 'echo "$r" | grep -q "kk_msg=license-saved"'
get "$URL/wp-admin/admin.php?page=kenakata-kit&tab=license" >/dev/null
check "header shows Pro activated" 'grep -q "Pro activated" /tmp/body'

echo "== Update box and courier log"
wpc eval 'set_site_transient( "kk_update_latest", array( "v" => "99.0.0", "zip" => "https://github.com/x/y/releases/download/v99.0.0/kenakata-kit-99.0.0.zip", "url" => "https://github.com/x/y/releases/tag/v99.0.0", "notes" => "", "published" => "" ), 600 );'
get "$URL/wp-admin/admin.php?page=kenakata-kit&tab=license" >/dev/null
check "Kenakata Kit screens show 'new version is ready' with Update now + Download" 'grep -q "kk-update-notice" /tmp/body && grep -q "upgrade-plugin" /tmp/body && grep -q "kenakata-kit-99.0.0.zip" /tmp/body'
get "$URL/wp-admin/index.php" >/dev/null
check "…and the WordPress Dashboard too" 'grep -q "kk-update-notice" /tmp/body'
up=$(wpc eval '$f = plugin_basename( KK_FILE ); $t = new stdClass(); $t->checked = array( $f => "3.0.0" ); $t->response = array(); $a = apply_filters( "pre_set_site_transient_update_plugins", $t ); $t2 = new stdClass(); $t2->checked = array( $f => "99.0.0" ); $t2->response = array( $f => (object) array( "new_version" => "99.0.0" ) ); $t2->no_update = array(); $b = apply_filters( "pre_set_site_transient_update_plugins", $t2 ); echo ( isset( $a->response[ $f ] ) && ! empty( $a->response[ $f ]->icons ) && $a->response[ $f ]->tested ? "offer" : "none" ), "|", isset( $b->response[ $f ] ) ? "stale" : "clean";')
check "update offered with icon + tested version; not offered again once the new files are on disk ($up)" '[ "$up" = "offer|clean" ]'
wpc eval 'delete_site_transient( "kk_update_latest" );'
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["courier_pathao_on"] = 1; $s["courier_pathao_store_id"] = "1"; kk_update_settings( $s ); foreach ( array( "client_id", "client_secret", "username", "password" ) as $k ) { kk_courier_save_secret( "courier_pathao_" . $k, "ci-" . $k ); }'
RN=$(curl -s -b "$JAR" "$URL/wp-admin/admin-ajax.php?action=rest-nonce")
curl -s -o /tmp/body -b "$JAR" -H "X-WP-Nonce: $RN" -X POST "$URL/?rest_route=/kenakata/v1/courier/test&target=pathao"
check "Pathao test with wrong keys gives a clear message: $(head -c 160 /tmp/body)" 'grep -q "message" /tmp/body && ! grep -q "critical error" /tmp/body'
get "$URL/wp-admin/admin.php?page=kenakata-kit&tab=courier" >/dev/null
check "courier log lists the call (no secrets in it)" 'grep -q "kk-clog" /tmp/body && grep -q "issue-token" /tmp/body && ! grep -q "ci-password" /tmp/body'

echo "== Internals"
out=$(wpc eval '
$r = array();
$r[] = "01712345678" === kk_normalize_phone( "০১৭১২৩৪৫৬৭৮" ) ? "ok bengali digits" : "BAD bengali digits";
$r[] = "01712345678" === kk_normalize_phone( "+880 1712-345678" ) ? "ok +880 phone" : "BAD +880 phone";
$a = kk_lock( "ci", 30 ); $b = kk_lock( "ci", 30 ); kk_unlock( "ci" ); $c = kk_lock( "ci", 30 ); kk_unlock( "ci" );
$r[] = ( $a && ! $b && $c ) ? "ok lock" : "BAD lock";
$r[] = false !== strpos( kk_courier_track_link( "steadfast", "AB12" ), "/t/AB12" ) ? "ok track link" : "BAD track link";
$p = kk_courier_pick_sanitize( array( "redx" => array( "on" => 1, "link" => "javascript:alert(1)" ), "xyz" => array( "on" => 1 ), "sa" => array( "link" => "x" ) ) );
$r[] = ( array( "redx" => "" ) === $p ) ? "ok courier pick sanitize" : "BAD courier pick sanitize";
$r[] = isset( kk_couriers()["steadfast"] ) ? "ok default couriers" : "BAD default couriers";
$r[] = "domain" === kk_license_check( "x", "a.com" )["code"] || "format" === kk_license_check( "x", "a.com" )["code"] ? "ok license check" : "BAD license check";
$r[] = function_exists( "kk_asset" ) && false !== strpos( kk_asset( "kenakata-kit.js" ), ".min.js" ) ? "ok minified asset" : "BAD minified asset";
$r[] = kk_is_pro() ? "ok pro on" : "BAD pro on";
echo implode( "\n", $r );
')
while IFS= read -r line; do case "$line" in ok*) ok "${line#ok }";; *) bad "$line";; esac; done <<< "$out"

echo "== Saving settings through the form"
FORM=$(curl -s -b "$JAR" "$URL/wp-admin/admin.php?page=kenakata-kit&tab=tracking")
ON=kk_settings_group
SN=$(echo "$FORM" | grep -oP 'name="_wpnonce" value="\K[0-9a-f]+' | head -1)
code=$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" -d "option_page=$ON&action=update&_wpnonce=$SN&_wp_http_referer=/wp-admin/admin.php%3Fpage%3Dkenakata-kit%26tab%3Dtracking&kk_settings[__tab]=tracking&kk_settings[tr_courier_pick][redx][on]=1&kk_settings[tr_courier_pick][sundarban][on]=1&kk_settings[tr_courier_pick][sundarban][link]=https://example.com/t/{id}" "$URL/wp-admin/options.php")
check "tracking tab saves (→ $code)" '[ "$code" = 302 ]'
out=$(wpc eval 'echo kk_opt("tr_courier_pick"), "|", kk_courier_track_link("sundarban","X9"), "|", implode(",", array_keys(kk_couriers()));')
check "courier tick list saved: $out" 'echo "$out" | grep -q "https://example.com/t/X9" && ! echo "$out" | grep -q "steadfast"'
code=$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" -d "option_page=$ON&action=update&_wpnonce=$SN&kk_settings[tr_courier_pick][steadfast][on]=1" "$URL/wp-admin/options.php")
check "a form post without its tab changes nothing" 'wpc eval "echo kk_opt(\"tr_courier_pick\");" | grep -vq steadfast'

echo "== Incomplete order → Make order (with the delivery charge the customer saw)"
wpc option update woocommerce_default_country "BD:BD-13" >/dev/null
wpc wc shipping_zone_method create 0 --method_id=flat_rate --settings='{"cost":"60"}' --user=1 >/dev/null 2>&1 || true
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["incomplete_enable"] = 1; kk_update_settings( $s );'
rm -f /tmp/c2
curl -s -o /dev/null -b /tmp/c2 -c /tmp/c2 -X POST -d "product_id=$PID&quantity=2" "$URL/?wc-ajax=kk_add"
curl -sL -o /dev/null -b /tmp/c2 -c /tmp/c2 "$URL/?page_id=$(wpc option get woocommerce_checkout_page_id)"
curl -s -o /tmp/body -b /tmp/c2 -c /tmp/c2 -H "Content-Type: application/json" -d '{"phone":"০১৭১২৩৪৫৬৭৮","name":"Test Buyer","address":"House 1, Road 2, Mirpur 10","city":"Dhaka"}' "$URL/?rest_route=/kenakata/v1/incomplete"
check "checkout left half-way is saved (Bengali-digit phone)" 'grep -q "\"ok\":true" /tmp/body'
curl -s -o /tmp/body2 -H "Content-Type: application/json" -d '{"phone":"01712345678","name":"Someone Else","address":"Fake road"}' "$URL/?rest_route=/kenakata/v1/incomplete"
IC=$(wpc eval 'global $wpdb; echo (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}kk_incomplete ORDER BY id DESC LIMIT 1" );')
NAME=$(wpc eval 'global $wpdb; echo $wpdb->get_var( "SELECT name FROM {$wpdb->prefix}kk_incomplete WHERE id = '"$IC"'" );')
check "another visitor with the same phone cannot overwrite it (name: $NAME)" '[ "$NAME" = "Test Buyer" ]'
RN=$(curl -s -b "$JAR" "$URL/wp-admin/admin-ajax.php?action=rest-nonce")
curl -s -o /tmp/body -b "$JAR" -H "X-WP-Nonce: $RN" -X POST "$URL/?rest_route=/kenakata/v1/sd/incomplete/$IC/order"
OID=$(grep -oP '"order_id":\K[0-9]+' /tmp/body | head -1)
out=$(wpc eval '$o = wc_get_order( '"${OID:-0}"' ); echo $o ? $o->get_shipping_total() . "|" . $o->get_total() : "none";')
check "Make order adds the delivery charge (shipping|total = $out)" '[ "${out%%|*}" = "60" ] || [ "${out%%|*}" = "60.00" ]'
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$URL/?rest_route=/kenakata/v1/sd/incomplete/$IC/order")
check "store-dashboard API refuses visitors ($code)" '[ "$code" = 401 ] || [ "$code" = 403 ]'

echo "== Store dashboard API"
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["sd_enable"] = 1; kk_update_settings( $s );'
code=$(curl -s -o /tmp/body -w "%{http_code}" -b "$JAR" -H "X-WP-Nonce: $RN" "$URL/?rest_route=/kenakata/v1/sd/report/cod")
check "store dashboard report answers ($code)" '[ "$code" = 200 ] && ! grep -q "rest_no_route" /tmp/body'
code=$(curl -sL -o /tmp/body -w "%{http_code}" -b "$JAR" "$URL/dashboard/")
check "store dashboard page opens ($code)" '[ "$code" = 200 ] && clean /tmp/body'
# 3.14: product with options, brand, new category, date-range report (real WooCommerce)
wpc eval 'if ( ! taxonomy_exists( "pa_size" ) ) { wc_create_attribute( array( "name" => "Size", "slug" => "size" ) ); }' >/dev/null 2>&1
curl -s -o /tmp/body -b "$JAR" -H "X-WP-Nonce: $RN" -H "Content-Type: application/json" -X POST -d '{"tax":"category","name":"Salwar Kameez"}' "$URL/?rest_route=/kenakata/v1/sd/terms"
CATID=$(grep -oP '"id":\K[0-9]+' /tmp/body | head -1)
check "store dashboard: new category made ($CATID)" '[ -n "$CATID" ] && grep -q "Salwar Kameez" /tmp/body'
curl -s -o /tmp/body -b "$JAR" -H "X-WP-Nonce: $RN" -H "Content-Type: application/json" -X POST -d '{"name":"Test Kameez","status":"publish","type":"variable","category_ids":['"${CATID:-0}"'],"image_ids":[],"attributes":[{"name":"Size","values":["S","M"]},{"name":"Colour","values":["Red","Blue"]}],"variations":[{"options":{"Size":"S","Colour":"Red"},"regular_price":"1500"},{"options":{"Size":"M","Colour":"Red"},"regular_price":"1500","manage_stock":true,"stock_quantity":4},{"options":{"Size":"S","Colour":"Blue"},"regular_price":"1600","sale_price":"1400"},{"options":{"Size":"M","Colour":"Blue"},"regular_price":"1600"}]}' "$URL/?rest_route=/kenakata/v1/sd/products"
VPID=$(grep -oP '^\{"id":\K[0-9]+' /tmp/body | head -1)
vinfo=$(wpc eval '$p = wc_get_product( '"${VPID:-0}"' ); if ( ! $p ) { echo "none"; return; } $a = $p->get_attributes(); echo $p->get_type(), "|", count( $p->get_children() ), "|", isset( $a["pa_size"] ) ? "global" : "own", "|", $p->get_variation_price( "min" ), "-", $p->get_variation_price( "max" ), "|", implode( ",", $p->get_category_ids() );')
check "product with options saved in WooCommerce (type|options|Size|price range|cat = $vinfo)" '[ "$vinfo" = "variable|4|global|1400-1600|$CATID" ] || [ "$vinfo" = "variable|4|global|1400.00-1600.00|$CATID" ]'
curl -s -o /tmp/body -b "$JAR" -H "X-WP-Nonce: $RN" "$URL/?rest_route=/kenakata/v1/sd/products/${VPID:-0}"
check "options come back with their names (Size / Colour)" 'grep -q "\"options\":{\"Size\":\"S\",\"Colour\":\"Red\"}" /tmp/body'
code=$(curl -sL -o /tmp/body -w "%{http_code}" "$URL/?p=${VPID:-0}")
check "the product page with options works ($code)" '[ "$code" = 200 ] && clean /tmp/body && grep -q "variations_form" /tmp/body'
SPID=$(wpc wc product create --user=1 --name="Plain Shirt" --regular_price=900 --porcelain 2>/dev/null)
wpc eval 'wp_update_post( array( "ID" => '"${SPID:-0}"' ) ); $p = wc_get_product( '"${SPID:-0}"' ); $a = new WC_Product_Attribute(); $a->set_name( "Fabric" ); $a->set_options( array( "Cotton" ) ); $a->set_visible( true ); $a->set_variation( false ); $p->set_attributes( array( $a ) ); $p->save();' >/dev/null 2>&1
curl -s -o /tmp/body -b "$JAR" -H "X-WP-Nonce: $RN" -H "Content-Type: application/json" -X POST -d '{"type":"variable","attributes":[{"name":"Size","values":["L","XL"]}],"variations":[{"options":{"Size":"L"},"regular_price":"950"},{"options":{"Size":"XL"},"regular_price":"990"}]}' "$URL/?rest_route=/kenakata/v1/sd/products/${SPID:-0}"
cinfo=$(wpc eval '$p = wc_get_product( '"${SPID:-0}"' ); $a = $p->get_attributes(); echo $p->get_type(), "|", count( $p->get_children() ), "|", isset( $a["fabric"] ) ? "kept" : "lost";')
check "simple product turned into one with options, its other details kept ($cinfo)" '[ "$cinfo" = "variable|2|kept" ]'
XID=$(wpc eval 'echo wc_get_product( '"${SPID:-0}"' )->get_children()[0];')
curl -s -o /tmp/body -b "$JAR" -H "X-WP-Nonce: $RN" -H "Content-Type: application/json" -X POST -d '{"variations":[{"id":'"${XID:-0}"',"remove":true}]}' "$URL/?rest_route=/kenakata/v1/sd/products/${SPID:-0}"
check "an option can be removed" '[ "$(wpc eval "echo count( wc_get_product( ${SPID:-0} )->get_children() );")" = 1 ]'
TODAY=$(date +%Y-%m-%d)
curl -s -o /tmp/body -b "$JAR" -H "X-WP-Nonce: $RN" "$URL/?rest_route=/kenakata/v1/sd/report/range&from=2026-01-01&to=$TODAY"
check "sales report for chosen dates" 'grep -q "\"orders\":" /tmp/body && grep -q "\"series\":" /tmp/body && ! grep -q "critical error" /tmp/body'

echo "== Order tracking with order number required"
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["tr_require_order"] = 1; kk_update_settings( $s );'
curl -sL -o /tmp/body "$URL/?pagename=track&kk_phone=01712345678"
check "phone alone shows no orders, asks for the order number" '! grep -q "kk-track-results" /tmp/body && grep -q "name=\"kk_order\"" /tmp/body'
curl -sL -o /tmp/body "$URL/?pagename=track&kk_phone=01712345678&kk_order=$OID"
check "phone + order number shows the order" 'grep -q "kk-track-results" /tmp/body && ! grep -q "No orders found" /tmp/body && clean /tmp/body'
curl -sL -o /tmp/body "$URL/?pagename=track&kk_phone=01799999999&kk_order=$OID"
check "a wrong phone with a real order number shows nothing" 'grep -q "No orders found" /tmp/body'

echo "== Speed switches and Turnstile on"
wpc eval '$s = (array) get_option( "kk_settings", array() ); foreach ( array( "speed_no_emoji", "speed_no_embeds", "speed_wc_assets", "speed_heartbeat", "turnstile_enable", "turnstile_login", "turnstile_checkout" ) as $k ) { $s[ $k ] = 1; } $s["turnstile_site"] = "1x00000000000000000000AA"; kk_update_settings( $s ); kk_save_secret( "turnstile_secret", "1x0000000000000000000000000000000AA" );'
for path in "/" "/?p=$PID" "/?page_id=$(wpc option get woocommerce_checkout_page_id)" "/?p=1"; do
	code=$(curl -sL -o /tmp/body -w "%{http_code}" "$URL$path")
	check "GET $path with speed switches → $code, no PHP errors" '[ "$code" = 200 ] && clean /tmp/body'
done
check "emoji script removed" '! curl -s "$URL/" | grep -q "wp-emoji"'
check "login popup has the Turnstile box (script loads only when opened)" 'curl -s "$URL/" | grep -q "data-kk-ts" && ! curl -s "$URL/" | grep -q "challenges.cloudflare.com/turnstile/v0/api.js"'
rm -f /tmp/c3; curl -s -o /dev/null -c /tmp/c3 -X POST -d "product_id=$PID&quantity=1" "$URL/?wc-ajax=kk_add"
check "checkout shows Turnstile (outside the part WooCommerce redraws)" 'curl -sL -b /tmp/c3 "$URL/?page_id=$(wpc option get woocommerce_checkout_page_id)" | grep -q "cf-turnstile"'
check "wp-login.php shows Turnstile" 'curl -s "$URL/wp-login.php" | grep -q "cf-turnstile"'
code=$(curl -s -o /tmp/body -w "%{http_code}" -d "log=admin&pwd=admin-pass-123&wp-submit=Log+In" "$URL/wp-login.php")
check "login without the Turnstile answer is refused" 'grep -q "security check" /tmp/body'
get "$URL/wp-admin/admin.php?page=kenakata-kit&tab=speed" >/dev/null
check "Speed & health tab shows the checkups" 'grep -q "kk-health" /tmp/body && clean /tmp/body'
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["turnstile_enable"] = 0; kk_update_settings( $s );'

echo "== 3.13: page-only styles, tracking not cached, live courier status"
check "home page: core styles only (no account/checkout/order/product styles)" 'curl -s "$URL/" | grep -q "kenakata-kit-css" && ! curl -s "$URL/" | grep -qE "kenakata-kit-(account|checkout|order|product)-css"'
check "product page loads the product styles" 'curl -sL "$URL/?p=$PID" | grep -q "kenakata-kit-product-css"'
check "cart page loads the checkout styles" 'curl -sL "$URL/?page_id=$(wpc option get woocommerce_cart_page_id)" | grep -q "kenakata-kit-checkout-css"'
check "account page loads the account styles" 'curl -sL "$URL/?page_id=$(wpc option get woocommerce_myaccount_page_id)" | grep -q "kenakata-kit-account-css"'
check "tracking page loads the order styles" 'curl -sL "$URL/?pagename=track" | grep -q "kenakata-kit-order-css"'
check "WooCommerce form fields keep their style everywhere (core)" 'grep -q "\.woocommerce form \.form-row label" "$WP/wp-content/plugins/kenakata-kit/assets/kenakata-kit.min.css"'
check "tracking results are never cached" 'curl -s -D - -o /dev/null "$URL/?pagename=track&kk_phone=01712345678" | grep -qi "cache-control: no-cache"'
mkdir -p "$WP/wp-content/mu-plugins"
cat > "$WP/wp-content/mu-plugins/kk-fake-courier.php" <<'PHP'
<?php
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'status_by_cid' ) ) { return $pre; }
	update_option( 'kk_fake_calls', (int) get_option( 'kk_fake_calls' ) + 1 );
	return array( 'headers' => array(), 'body' => '{"status":200,"delivery_status":"delivered"}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
}, 10, 3 );
PHP
LID=$(wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["tr_require_order"] = 0; $s["tr_live_courier"] = 1; $s["tr_auto_complete"] = 1; kk_update_settings( $s ); $o = wc_create_order(); $o->set_billing_phone( "01712345678" ); $o->update_meta_data( "_kk_courier_booking", array( "provider" => "steadfast", "consignment_id" => "999", "tracking_code" => "ABC", "status" => "pending", "booked_at" => time() - 86400, "checked_at" => time() - 7200 ) ); $o->update_meta_data( "_kk_courier", "steadfast" ); $o->update_meta_data( "_kk_tracking_url", "https://steadfast.com.bd/t/ABC" ); $o->set_status( "processing" ); $o->save(); update_option( "kk_fake_calls", 0 ); echo $o->get_id();' 2>/dev/null | tail -1)
curl -sL -o /tmp/body "$URL/?pagename=track&kk_phone=01712345678"
check "live courier status shown on the tracking page" 'grep -q "kk-order__live" /tmp/body && clean /tmp/body'
check "courier said Delivered → order Completed (setting on)" '[ "$(wpc eval "echo wc_get_order( $LID )->get_status();")" = completed ]'
curl -sL -o /dev/null "$URL/?pagename=track&kk_phone=01712345678"
check "the courier is asked only once (answer kept 30 minutes)" '[ "$(wpc option get kk_fake_calls)" = 1 ]'
rm -f "$WP/wp-content/mu-plugins/kk-fake-courier.php"
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["tr_live_courier"] = 0; $s["tr_auto_complete"] = 0; kk_update_settings( $s );'
KEYSAVE=$(wpc option get kk_license_key 2>/dev/null); wpc option delete kk_license_key >/dev/null 2>&1
get "$URL/wp-admin/admin.php?page=kenakata-kit&tab=license" >/dev/null
[ -n "$KEYSAVE" ] && wpc option update kk_license_key "$KEYSAVE" >/dev/null
check "License tab (no key): 'Order on WhatsApp' button with this site's address" 'grep -q "kk-buy-pro" /tmp/body && grep -q "wa.me/8801728366779" /tmp/body && grep -q "localhost" /tmp/body && clean /tmp/body'

echo "== 3.15: invoices, labels, courier sync only when pressed"
PO=$(wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["print_enable"] = 1; $s["print_shop_phone"] = "01700000000"; kk_update_settings( $s ); $p = new WC_Product_Simple(); $p->set_name( "Print Test Tee" ); $p->set_regular_price( 500 ); $p->set_sku( "PTT-1" ); $p->save(); $o = wc_create_order(); $o->add_product( $p, 2 ); $o->set_billing_first_name( "Rahim" ); $o->set_billing_last_name( "Uddin" ); $o->set_billing_phone( "01712345679" ); $o->set_billing_address_1( "House 5, Road 2, Mirpur" ); $o->set_billing_city( "Dhaka" ); $o->set_payment_method( "cod" ); $o->set_payment_method_title( "Cash on delivery" ); $sh = new WC_Order_Item_Shipping(); $sh->set_method_title( "Inside Dhaka" ); $sh->set_total( 60 ); $o->add_item( $sh ); $o->calculate_totals(); $o->set_status( "processing" ); $o->save(); echo $o->get_id();' 2>/dev/null | tail -1)
curl -sL -o /tmp/body -b "$JAR" "$(wpc eval "echo kk_sd_url();")"
PURL=$(grep -oP '"print":"\K[^"]+' /tmp/body | head -1 | sed 's#\\/#/#g; s#\\u0026#\&#g')
check "store dashboard gets a print link" '[ -n "$PURL" ] && echo "$PURL" | grep -q "action=kk_print"'
code=$(curl -s -o /tmp/body -w "%{http_code}" -b "$JAR" "$PURL&kind=invoice&ids=$PO")
check "invoice prints ($code): items, total, cash to collect, barcode" '[ "$code" = 200 ] && clean /tmp/body && grep -q "kkp-inv" /tmp/body && grep -q "Print Test Tee" /tmp/body && grep -q "Rahim Uddin" /tmp/body && grep -q "1,060\|1060" /tmp/body && grep -q "kkp-sum__cod" /tmp/body && grep -q "kkp-bc" /tmp/body && grep -q "window.print" /tmp/body'
code=$(curl -s -o /tmp/body -w "%{http_code}" -b "$JAR" "$PURL&kind=label&ids=$PO,$OID")
check "labels print ($code): two labels, phone, area, sender" '[ "$code" = 200 ] && clean /tmp/body && [ "$(grep -o "class=\"kkp-lab\"" /tmp/body | wc -l)" = 2 ] && grep -q "01712345679" /tmp/body && grep -q "Inside Dhaka" /tmp/body && grep -q "01700000000" /tmp/body'
code=$(curl -s -o /tmp/body -w "%{http_code}" -b "$JAR" "$URL/wp-admin/admin-post.php?action=kk_print&kind=invoice&ids=$PO&_wpnonce=bad")
check "print link with a wrong key is refused ($code)" '[ "$code" = 403 ] && ! grep -q "Print Test Tee" /tmp/body'
code=$(curl -s -o /tmp/body -w "%{http_code}" "$URL/wp-admin/admin-post.php?action=kk_print&kind=invoice&ids=$PO")
check "visitors cannot print orders ($code)" '! grep -q "Print Test Tee" /tmp/body'
get "$URL/wp-admin/admin.php?page=wc-orders" >/dev/null; [ -s /tmp/body ] && grep -q "kk_print_invoice" /tmp/body || get "$URL/wp-admin/edit.php?post_type=shop_order" >/dev/null
check "orders list has Print invoices / Print shipping labels" 'grep -q "kk_print_invoice" /tmp/body && grep -q "kk_print_label" /tmp/body'
red=$(wpc eval 'wp_set_current_user( 1 ); echo kk_bulk_handle( "x", "kk_print_label", array( '"$PO"' ) );' 2>/dev/null)
check "bulk Print shipping labels goes to the print page" 'echo "$red" | grep -q "kind=label" && echo "$red" | grep -q "ids='"$PO"'"'
cat > "$WP/wp-content/mu-plugins/kk-fake-courier.php" <<'PHP'
<?php
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'status_by_cid' ) ) { return $pre; }
	update_option( 'kk_fake_calls', (int) get_option( 'kk_fake_calls' ) + 1 );
	return array( 'headers' => array(), 'body' => '{"status":200,"delivery_status":"in_review"}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
}, 10, 3 );
PHP
SYN=$(wpc eval 'update_option( "kk_fake_calls", 0 ); $o = wc_create_order(); $o->update_meta_data( "_kk_courier_booking", array( "provider" => "steadfast", "consignment_id" => "777", "tracking_code" => "XYZ", "status" => "pending", "booked_at" => time() ) ); $o->set_status( "processing" ); $o->save(); wp_set_current_user( 1 ); $r = kk_bulk_courier_run( "kk_courier_sync", array( $o->get_id(), '"$PO"', '"$LID"' ) ); echo $r["done"], "|", $r["skipped"], "|", get_option( "kk_fake_calls" ), "|", wc_get_order( $o->get_id() )->get_meta( "_kk_courier_booking" )["status"];' 2>/dev/null | tail -1)
check "Sync: asks the courier only for parcels still on the way (done|skipped|calls|status = $SYN)" '[ "$SYN" = "1|2|1|in_review" ]'
rm -f "$WP/wp-content/mu-plugins/kk-fake-courier.php"
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["print_enable"] = 0; kk_update_settings( $s );'
code=$(curl -s -o /tmp/body -w "%{http_code}" -b "$JAR" "$PURL&kind=invoice&ids=$PO")
check "print switched off → no print page ($code)" '[ "$code" = 403 ] && ! grep -q "Print Test Tee" /tmp/body'

echo "== 3.15.1: side cart and a page builder's mini cart keep their own markup"
mkdir -p "$WP/wp-content/kk-fake"; echo '<div class="fake-builder-mc">BUILDER CART</div>' > "$WP/wp-content/kk-fake/mini-cart.php"
cat > "$WP/wp-content/mu-plugins/kk-fake-builder.php" <<'PHP'
<?php
add_filter( 'woocommerce_locate_template', function ( $t, $name ) { return 'cart/mini-cart.php' === $name ? WP_CONTENT_DIR . '/kk-fake/mini-cart.php' : $t; }, 10, 2 );
PHP
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["cart_drawer_enable"] = 1; kk_update_settings( $s );'
curl -s -o /tmp/body -X POST "$URL/?wc-ajax=get_refreshed_fragments"
check "builder mini cart keeps its template; side cart gets WooCommerce markup in its own fragment" 'php -r '"'"'$j = json_decode( file_get_contents( "/tmp/body" ), true ); $f = $j["fragments"]; exit( isset( $f["div.kk-drawer__content"] ) && false === strpos( $f["div.kk-drawer__content"], "BUILDER" ) && false !== strpos( $f["div.kk-drawer__content"], "woocommerce-mini-cart" ) && false !== strpos( $f["div.widget_shopping_cart_content"], "BUILDER CART" ) ? 0 : 1 );'"'"''
curl -sL -o /tmp/body "$URL/?post_type=product"
check "side cart in the page uses WooCommerce markup, not the builder's" 'grep -q "kk-drawer__content" /tmp/body && ! grep -q "kk-drawer__content\"><div class=\"fake-builder-mc" /tmp/body && clean /tmp/body'
rm -rf "$WP/wp-content/mu-plugins/kk-fake-builder.php" "$WP/wp-content/kk-fake"

echo "== 3.16: bKash / Nagad / Rocket by hand"
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["mp_enable"] = 1; $s["mp_bkash_number"] = "01728366779"; $s["mp_nagad_number"] = "01812345678"; $s["mp_nagad_type"] = "merchant"; $s["mp_amount"] = "delivery"; $s["mp_screenshot"] = "optional"; kk_update_settings( $s );'
rm -f /tmp/c3
curl -s -o /dev/null -b /tmp/c3 -c /tmp/c3 -X POST -d "product_id=$PID&quantity=1" "$URL/?wc-ajax=kk_add"
curl -sL -o /tmp/body -b /tmp/c3 -c /tmp/c3 "$URL/?page_id=$(wpc option get woocommerce_checkout_page_id)"
CN=$(grep -oP 'name="woocommerce-process-checkout-nonce" value="\K[^"]+' /tmp/body | head -1)
check "checkout shows bKash / Nagad with numbers, Send Money / Payment, advance amount" 'grep -q "payment_method_kk_mobile_pay" /tmp/body && grep -q "01728366779" /tmp/body && grep -q "Send Money" /tmp/body && grep -q "&quot;Payment&quot;\|\"Payment\"" /tmp/body && grep -q "kk-mp__amount" /tmp/body && grep -q "manual-pay" /tmp/body && clean /tmp/body'
curl -s -o /tmp/body -b /tmp/c3 -F "shot=@$REPO/dev/ci/shot.png;type=image/png" "$URL/?wc-ajax=kk_mp_upload"
TOK=$(grep -oP '"token":"\K[A-Za-z0-9]+' /tmp/body)
check "screenshot upload gives a token" '[ ${#TOK} = 32 ]'
printf '<?php echo 1;' > /tmp/evil.png
curl -s -o /tmp/body -b /tmp/c3 -F "shot=@/tmp/evil.png;type=image/png" "$URL/?wc-ajax=kk_mp_upload"
check "a file that is not a picture is refused" 'grep -q "error" /tmp/body && ! grep -q token /tmp/body'
check "uploaded pictures cannot be opened from the web" '[ "$(ls $WP/wp-content/uploads/kk-private/ | grep -c tmp-)" -ge 1 ] && [ -f $WP/wp-content/uploads/kk-private/.htaccess ]'
SM=$(wpc eval 'foreach ( WC_Shipping_Zones::get_zone( 0 )->get_shipping_methods( true ) as $m ) { echo $m->get_rate_id(); break; }')
CO="billing_first_name=Mim&billing_last_name=Akter&billing_phone=01712000111&billing_email=&billing_address_1=House+9+Road+3+Uttara&billing_city=Dhaka&billing_state=BD-13&billing_country=BD&shipping_method%5B0%5D=$SM&billing_delivery_area=$SM&payment_method=kk_mobile_pay&kk_mp_wallet=bkash&kk_mp_sender=01812000222&kk_mp_shot=$TOK&terms=1&woocommerce-process-checkout-nonce=$CN"
curl -s -o /tmp/body -b /tmp/c3 -c /tmp/c3 -X POST -d "$CO&kk_mp_trx=bad" "$URL/?wc-ajax=checkout"
check "wrong TrxID is refused at checkout" 'grep -q "\"result\":\"failure\"" /tmp/body && grep -q "Transaction ID" /tmp/body'
curl -s -o /tmp/body -b /tmp/c3 -c /tmp/c3 -X POST -d "$CO&kk_mp_trx=bk7x-9qw2lm" "$URL/?wc-ajax=checkout"
check "order placed with bKash TrxID" 'grep -q "\"result\":\"success\"" /tmp/body'
MPO=$(wpc eval '$o = wc_get_orders( array( "limit" => 1, "payment_method" => "kk_mobile_pay", "orderby" => "date", "order" => "DESC", "return" => "ids" ) ); echo $o ? $o[0] : 0;')
minfo=$(wpc eval '$o = wc_get_order( '"$MPO"' ); echo $o->get_status(), "|", $o->get_meta( "_kk_mp_trx" ), "|", $o->get_meta( "_kk_mp_sender" ), "|", (float) $o->get_meta( "_kk_mp_amount" ), "|", $o->get_meta( "_kk_mp_shot" ) ? "shot" : "noshot", "|", kk_mp_to_collect( $o ) == $o->get_total() ? "full" : "less";')
check "order waits On hold with TrxID, sender, delivery charge, screenshot; nothing deducted yet ($minfo)" '[ "$minfo" = "on-hold|BK79QW2LM|01812000222|60|shot|full" ] || [ "$minfo" = "on-hold|BK7X9QW2LM|01812000222|60|shot|full" ]'
rm -f /tmp/c4; curl -s -o /dev/null -b /tmp/c4 -c /tmp/c4 -X POST -d "product_id=$PID&quantity=1" "$URL/?wc-ajax=kk_add"; curl -sL -o /tmp/body -b /tmp/c4 -c /tmp/c4 "$URL/?page_id=$(wpc option get woocommerce_checkout_page_id)"
CN2=$(grep -oP 'name="woocommerce-process-checkout-nonce" value="\K[^"]+' /tmp/body | head -1)
curl -s -o /tmp/body -b /tmp/c4 -c /tmp/c4 -X POST -d "${CO%%woocommerce-process*}woocommerce-process-checkout-nonce=$CN2&kk_mp_trx=BK7X9QW2LM" "$URL/?wc-ajax=checkout"
check "the same TrxID cannot be used for a second order" 'grep -q "\"result\":\"failure\"" /tmp/body && grep -q "already used" /tmp/body'
SHOT=$(wpc eval 'echo kk_mp_details( wc_get_order( '"$MPO"' ) )["shot"] ? "y" : "n";')
code=$(curl -s -o /tmp/body -w "%{http_code}" "$URL/wp-admin/admin-post.php?action=kk_mp_shot&order=$MPO")
check "visitors cannot see the screenshot ($code)" '! grep -q PNG /tmp/body'
curl -s -o /tmp/body -b "$JAR" -H "X-WP-Nonce: $RN" "$URL/?rest_route=/kenakata/v1/sd/orders/$MPO"
check "store dashboard shows the payment to check" 'grep -q "\"mpay\":{" /tmp/body && grep -q "BK7X9QW2LM" /tmp/body'
SURL=$(grep -oP '"shot":"\K[^"]+' /tmp/body | sed 's#\\/#/#g; s#\\u0026#\&#g; s#&amp;#\&#g')
code=$(curl -s -o /tmp/shot.out -w "%{http_code}" -b "$JAR" "$SURL")
check "staff can open the screenshot ($code)" '[ "$code" = 200 ] && file /tmp/shot.out | grep -q PNG'
curl -s -o /tmp/body -b "$JAR" -H "X-WP-Nonce: $RN" -H "Content-Type: application/json" -X POST -d '{"decision":"yes"}' "$URL/?rest_route=/kenakata/v1/sd/orders/$MPO/mpay"
dinfo=$(wpc eval '$o = wc_get_order( '"$MPO"' ); echo $o->get_status(), "|", (float) kk_mp_to_collect( $o ), "|", (float) $o->get_total(), "|", function_exists( "kk_courier_defaults" ) || ( function_exists( "kk_courier_load" ) && ( kk_courier_load() || true ) ) ? kk_courier_defaults( $o )["cod_amount"] : "x";')
check "Payment received → Processing, courier collects the rest ($dinfo)" 'IFS="|" read st col tot cod <<< "$dinfo"; [ "$st" = processing ] && [ "$(php -r "echo $tot - 60 == $col && $col == $cod ? 1 : 0;")" = 1 ]'
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["mp_enable"] = 0; kk_update_settings( $s );'
curl -s -o /dev/null -b /tmp/c4 -c /tmp/c4 -X POST -d "product_id=$PID&quantity=1" "$URL/?wc-ajax=kk_add"; curl -sL -o /tmp/body -b /tmp/c4 "$URL/?page_id=$(wpc option get woocommerce_checkout_page_id)"
check "switched off → not offered at checkout" '! grep -q "payment_method_kk_mobile_pay" /tmp/body && clean /tmp/body'

echo "== Bots"
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["speed_robots_shop"] = 1; $s["speed_bot_block"] = 1; $s["speed_ai_block"] = 1; kk_update_settings( $s );'
check "robots.txt keeps crawlers off search/sort/filter pages and names the blocked bots" 'curl -s "$URL/?robots=1" | grep -q "orderby" && curl -s "$URL/?robots=1" | grep -q "User-agent: AhrefsBot"'
code=$(curl -s -o /dev/null -w "%{http_code}" -A "Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)" "$URL/?p=$PID")
check "SEO-tool bot gets 403 ($code)" '[ "$code" = 403 ]'
code=$(curl -s -o /dev/null -w "%{http_code}" -A "Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)" "$URL/")
check "AI crawler gets 403 ($code)" '[ "$code" = 403 ]'
code=$(curl -sL -o /dev/null -w "%{http_code}" -A "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)" "$URL/?p=$PID")
check "Googlebot still gets the page ($code)" '[ "$code" = 200 ]'
code=$(curl -s -o /dev/null -w "%{http_code}" -A "Mozilla/5.0 (compatible; AhrefsBot/7.0)" "$URL/?robots=1")
check "blocked bots can still read robots.txt ($code)" '[ "$code" = 200 ]'
wpc eval '$s = (array) get_option( "kk_settings", array() ); $s["speed_bot_block"] = 0; $s["speed_ai_block"] = 0; kk_update_settings( $s );'

echo "== Deactivate / activate / uninstall"
wpc plugin deactivate kenakata-kit >/dev/null
check "crons cleared on deactivation" '! wpc cron event list --fields=hook --format=csv | grep -q "^kk_"'
wpc plugin activate kenakata-kit >/dev/null
check "activates again" 'wpc plugin is-active kenakata-kit'
wpc plugin deactivate kenakata-kit >/dev/null && wpc plugin uninstall kenakata-kit --skip-delete >/dev/null
check "uninstall keeps settings and license by default" 'wpc option get kk_settings >/dev/null 2>&1 && [ -n "$(wpc option get kk_license_key 2>/dev/null)" ]'
wpc plugin activate kenakata-kit >/dev/null && wpc option update kk_uninstall_wipe 1 >/dev/null
wpc plugin deactivate kenakata-kit >/dev/null && wpc plugin uninstall kenakata-kit --skip-delete >/dev/null
check "uninstall with 'delete all data' removes settings and license" '! wpc option get kk_settings >/dev/null 2>&1 && ! wpc option get kk_license_key >/dev/null 2>&1'
wpc plugin activate kenakata-kit >/dev/null

echo "== PHP log"
if [ -f "$WP/wp-content/debug.log" ] && grep -nE "PHP (Fatal error|Parse error|Warning|Notice|Deprecated).*kenakata-kit" "$WP/wp-content/debug.log"; then
	bad "PHP warnings/notices from the plugin (above)"
else
	ok "no PHP warnings or notices from the plugin"
fi

echo
echo "RESULT: $PASSES passed, $FAILS failed"
[ "$FAILS" = 0 ]
