<?php
/**
 * Related products in the shared exhibition-works slider.
 * @version 10.3.0
 */
defined( 'ABSPATH' ) || exit;
if ( ! $related_products ) { return; }
global $product;
$original_product = $product;
$heading = apply_filters( 'woocommerce_product_related_products_heading', __( 'Related products', 'woocommerce' ) );
?>
<div class="kogo-product__related">
	<section class="kogo-posts-slider kogo-exhibition-works kogo-related-products swiper" data-slider-label="<?php echo esc_attr( $heading ); ?>" data-slider-item-label="<?php esc_attr_e( 'products', 'kogo' ); ?>">
		<div class="wp-block-group is-content-justification-space-between is-nowrap is-layout-flex kogo-posts-slider__header">
			<h2 class="kogo-posts-slider__heading"><?php echo esc_html( $heading ); ?></h2>
			<div class="kogo-posts-slider__actions">
				<div class="wp-block-button kogo-posts-slider__more"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'More', 'kogo' ); ?></a></div>
			</div>
		</div>
		<ul class="wp-block-post-template kogo-posts-slider__items kogo-posts-slider__items--related swiper-wrapper">
			<?php foreach ( $related_products as $related_product ) : if ( ! $related_product->is_visible() ) { continue; } $product = $related_product; ?>
			<li>
				<article class="kogo-posts-slider__card kogo-related-products__card">
					<div class="kogo-edition-card__media kogo-posts-slider__image">
						<a class="kogo-related-products__image" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo $product->get_image( 'large', array( 'loading' => 'lazy' ) ); ?></a>
						<div class="kogo-related-products__cart"><?php woocommerce_template_loop_add_to_cart(); ?></div>
					</div>
					<h3 class="kogo-posts-slider__title"><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
					<div class="kogo-related-products__price"><?php echo $product->get_price_html(); ?></div>
				</article>
			</li>
			<?php endforeach; $product = $original_product; ?>
		</ul>
	</section>
</div>
