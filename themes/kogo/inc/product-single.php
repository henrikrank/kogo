<?php
/** Single-product presentation using native WooCommerce gallery and cart forms. */

add_action( 'after_setup_theme', static function () {
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-slider' );
	add_theme_support( 'wc-product-gallery-lightbox' );
} );

add_action( 'wp', static function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
	add_action( 'woocommerce_single_product_summary', 'kogo_product_sku', 6 );
	add_action( 'woocommerce_single_product_summary', 'kogo_product_description', 20 );
	add_action( 'woocommerce_single_product_summary', 'kogo_product_terms', 40 );
} );

function kogo_product_sku() {
	global $product;
	if ( wc_product_sku_enabled() && ( $product->get_sku() || $product->is_type( 'variable' ) ) ) {
		echo '<p class="kogo-product__sku product_meta"><span class="screen-reader-text">' . esc_html__( 'SKU:', 'woocommerce' ) . ' </span><span class="sku">' . esc_html( $product->get_sku() ?: __( 'N/A', 'woocommerce' ) ) . '</span></p>';
	}
}

function kogo_product_description() {
	global $product;
	$content = $product->get_description();
	if ( $content ) {
		echo '<div class="kogo-product__description">' . apply_filters( 'the_content', $content ) . '</div>'; // Native product description supports blocks and shortcodes.
	} else {
		woocommerce_template_single_excerpt();
	}
}

function kogo_product_terms() {
	global $product;
	$terms = array_filter( array( wc_get_product_category_list( $product->get_id() ), wc_get_product_tag_list( $product->get_id() ) ) );
	if ( $terms ) {
		echo '<div class="kogo-product__terms" aria-label="' . esc_attr__( 'Product categories and tags', 'kogo' ) . '">' . wp_kses_post( implode( ', ', $terms ) ) . '</div>';
	}
}

add_filter( 'woocommerce_product_tabs', static function ( $tabs ) {
	if ( is_product() ) { unset( $tabs['description'] ); }
	return $tabs;
}, 99 );

// Keep WooCommerce's localized prices, including variation ranges and sale labels.
add_filter( 'woocommerce_get_price_html', static function ( $html, $product ) {
	if ( is_product() && in_array( get_queried_object_id(), array( $product->get_id(), $product->get_parent_id() ), true ) && $html && wc_tax_enabled() && $product->is_taxable() && 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
		$html .= ' <span class="kogo-product__vat">' . esc_html__( 'VAT included', 'kogo' ) . '</span>';
	}
	return $html;
}, 20, 2 );

add_filter( 'woocommerce_get_breadcrumb', static function ( $crumbs ) {
	if ( is_product() && count( $crumbs ) > 1 ) {
		array_pop( $crumbs ); // The product title is already shown directly below the category trail.
		$shop = wc_get_page_permalink( 'shop' );
		if ( ! in_array( $shop, array_column( $crumbs, 1 ), true ) ) {
			array_splice( $crumbs, 1, 0, array( array( get_the_title( wc_get_page_id( 'shop' ) ), $shop ) ) );
		}
	}
	return $crumbs;
} );

add_filter( 'woocommerce_single_product_carousel_options', static function ( $options ) {
	$options['directionNav'] = true;
	$options['prevText'] = __( 'Previous image', 'kogo' );
	$options['nextText'] = __( 'Next image', 'kogo' );
	return $options;
} );

function kogo_product_quantity_button( $direction ) {
	if ( function_exists( 'is_product' ) && is_product() ) {
		echo '<button class="kogo-product__quantity-button kogo-product__quantity-button--' . esc_attr( $direction ) . '" type="button" aria-label="' . esc_attr( 'minus' === $direction ? __( 'Decrease quantity', 'kogo' ) : __( 'Increase quantity', 'kogo' ) ) . '"></button>';
	}
}
add_action( 'woocommerce_before_quantity_input_field', static function () { kogo_product_quantity_button( 'minus' ); } );
add_action( 'woocommerce_after_quantity_input_field', static function () { kogo_product_quantity_button( 'plus' ); } );

add_filter( 'woocommerce_output_related_products_args', static function ( $args ) {
	$args['posts_per_page'] = 12;
	return $args;
} );
