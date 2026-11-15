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
check "Buy Now button on the product page" 'curl -sL "$URL/?p=$PID" | grep -q "kk-buy-now"'
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
