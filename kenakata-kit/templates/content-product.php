<?php
/**
 * Kenakata Kit product card inside WooCommerce loops (replaces woocommerce/content-product.php
 * while "প্রোডাক্ট কার্ড → সাইটের সব প্রোডাক্ট লিস্টে এই কার্ড" is on).
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}
?>
<li <?php wc_product_class( 'kk-loop-item', $product ); ?>>
	<?php echo kk_render_product_card( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_render_product_card() ?>
</li>
