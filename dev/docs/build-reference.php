<?php
/**
 * Builds docs/SETTINGS-REFERENCE.md from the code (every settings tab, field, shortcode, Elementor widget).
 * Run from the repo root:  php dev/docs/build-reference.php > docs/SETTINGS-REFERENCE.md
 * Re-run after changing settings-schema.php, admin.php (shortcodes) or includes/elementor/.
 */
define( 'ABSPATH', __DIR__ );
foreach ( array( '__', 'esc_html__', 'esc_attr__', '_x' ) as $f ) { if ( ! function_exists( $f ) ) { eval( "function $f( \$s ) { return \$s; }" ); } }
function _n( $a, $b, $n ) { return 1 === $n ? $a : $b; }
function add_action() {} function add_filter() {} function add_shortcode() {} function register_activation_hook() {}
function get_option( $k, $d = false ) { return $d; } function apply_filters( $t, $v ) { return $v; }
function admin_url( $p = '' ) { return '/wp-admin/' . $p; } function home_url( $p = '' ) { return $p; }
function wp_get_theme() { return new class { function get( $k ) { return ''; } }; }
function get_pages() { return array(); } function wc_get_page_id() { return 0; }
function add_query_arg() { return '/dashboard/'; } function get_permalink() { return '/'; } function get_post() { return null; } function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; } function user_trailingslashit( $s ) { return $s; } function get_page_by_path() { return null; } function esc_url( $s ) { return $s; } function get_post_field() { return ''; } function sanitize_title( $s ) { return $s; }
$root = dirname( __DIR__, 2 ) . '/kenakata-kit/';
$kk_take = function ( $file, $name ) use ( $root ) {
	$c = file_get_contents( $root . $file );
	if ( ! function_exists( $name ) && preg_match( '/\nfunction ' . $name . '\(.*?\n\}/s', $c, $mm ) ) { eval( $mm[0] ); }
};
$kk_take( 'includes/manual-pay.php', 'kk_mp_types' );
$kk_take( 'includes/bottom-bar.php', 'kk_bbar_default_items' );
$kk_take( 'includes/store-dashboard.php', 'kk_sd_presets' );
$kk_take( 'includes/store-dashboard.php', 'kk_sd_url' );
$kk_take( 'kenakata-kit.php', 'kk_defaults' );
require $root . 'includes/settings-schema.php';
$src_admin = file_get_contents( $root . 'includes/admin.php' );
preg_match( '/function kk_shortcode_list\(\) \{.*?\n\}/s', $src_admin, $m );
eval( $m[0] );

$tabs = kk_tabs(); $fields = kk_fields(); $mods = kk_modules();
$md = function ( $s ) { return str_replace( array( '|', "\n" ), array( '\\|', ' ' ), strip_tags( (string) $s ) ); };
echo "# Settings reference (generated)\n\n";
echo "Generated from the code by `dev/docs/build-reference.php` — do not edit by hand.\n";
echo "Every option is stored in the single WordPress option `kk_settings` (defaults: `kk_defaults()` in kenakata-kit.php).\n";
echo "Admin: left menu **Kenakata Kit**. Labels are the English source; the shop owner sees the Bengali translation.\n\n";
echo "## Main on/off switches (Dashboard tab)\n\n| Key | Feature | Tab | What it does |\n|---|---|---|---|\n";
foreach ( $mods as $k => $v ) { echo "| `$k` | {$md($v[0])} | {$v[1]} | {$md($v[2])} |\n"; }
echo "\n## Tabs and fields\n";
$group = '';
foreach ( $tabs as $tk => $t ) {
	if ( $t['group'] !== $group ) { $group = $t['group']; echo "\n### Group: {$group}\n"; }
	echo "\n#### {$md($t['label'])} (`$tk`)\n\n{$md($t['intro'] ?? '')}\n\n";
	$rows = array_filter( $fields, function ( $f ) use ( $tk ) { return $f['tab'] === $tk; } );
	if ( ! $rows ) { echo "_No stored fields (information or tools screen)._\n"; continue; }
	echo "| Key | Type | Label | Help |\n|---|---|---|---|\n";
	foreach ( $rows as $k => $f ) { echo "| `$k` | {$f['type']} | {$md($f['label'])} | {$md($f['desc'])} |\n"; }
}
echo "\n## Shortcodes\n\n| Shortcode | What | Options |\n|---|---|---|\n";
foreach ( kk_shortcode_list() as $s ) { echo "| `{$md($s[0])}` | {$md($s[1])} | {$md($s[2])} |\n"; }
echo "\n## Elementor widgets (category \"Kenakata Kit\")\n\n| Widget class | Title | File |\n|---|---|---|\n";
foreach ( glob( $root . 'includes/elementor/*.php' ) as $file ) {
	$c = file_get_contents( $file );
	if ( preg_match_all( '/class (\w+) extends [\\\\\w]+.*?function get_title\(\)\s*\{\s*return\s*(?:esc_html__|__)\(\s*\'([^\']+)\'/s', $c, $w, PREG_SET_ORDER ) ) {
		foreach ( $w as $x ) { echo "| `{$x[1]}` | {$x[2]} | includes/elementor/" . basename( $file ) . " |\n"; }
	}
}
