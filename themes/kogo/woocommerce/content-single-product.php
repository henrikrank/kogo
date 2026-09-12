<?php
/**
 * Native single-product hooks in the Kogo page layout.
 * @version 3.6.0
 */
defined( 'ABSPATH' ) || exit;
global $product;
do_action( 'woocommerce_before_single_product' );
if ( post_password_required() ) {
	echo get_the_password_form();
	return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'kogo-product', $product ); ?>>
	<div class="kogo-product__layout">
		<?php do_action( 'woocommerce_before_single_product_summary' ); ?>
		<div class="summary entry-summary">
			<?php do_action( 'woocommerce_single_product_summary' ); ?>
		</div>
	</div>
	<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
</div>
<?php do_action( 'woocommerce_after_single_product' ); ?>
