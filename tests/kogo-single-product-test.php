<?php
// Run with WordPress loaded: wp --user=admin eval-file tests/kogo-single-product-test.php
// Read-only integration check; no products, orders or cart sessions are changed.
$sample = wc_get_product( wc_get_product_id_by_sku( 'KOGO-SAMPLE-002' ) );
if ( ! $sample ) { throw new RuntimeException( 'The sample sale product is required.' ); }
$response = wp_remote_get( $sample->get_permalink() );
if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
	throw new RuntimeException( 'The single product page must load successfully.' );
}
$document = new DOMDocument();
@$document->loadHTML( '<?xml encoding="utf-8" ?>' . wp_remote_retrieve_body( $response ) );
$xpath = new DOMXPath( $document );
$has_class = static function ( $class ) { return 'contains(concat(" ",normalize-space(@class)," ")," ' . $class . ' ")'; };
$product_path = '//div[@id="product-' . $sample->get_id() . '"]';
$summary = $product_path . '//div[' . $has_class( 'summary' ) . ']';
$assert_count = static function ( $path, $count, $message ) use ( $xpath ) {
	if ( $count !== $xpath->query( $path )->length ) { throw new RuntimeException( $message ); }
};
$assert_count( $summary . '//h1', 1, 'The native product title must appear once.' );
$assert_count( $summary . '//*[' . $has_class( 'product_meta' ) . ']//span[' . $has_class( 'sku' ) . ']', 1, 'SKU must retain the selector used by WooCommerce variations.' );
$assert_count( $summary . '//form[' . $has_class( 'cart' ) . ']//input[@name="quantity"]', 1, 'The native quantity input must be retained.' );
$assert_count( $summary . '//form//button[@type="submit" and @name="add-to-cart" and @value="' . $sample->get_id() . '"]', 1, 'The native add-to-cart submit must retain its product ID.' );
$assert_count( $summary . '//button[' . $has_class( 'kogo-product__quantity-button' ) . ' and @type="button"]', 2, 'Quantity controls must not submit the purchase form.' );
$assert_count( $summary . '//*[' . $has_class( 'price' ) . ']//del', 1, 'The native original sale price must be retained.' );
$assert_count( $summary . '//*[' . $has_class( 'price' ) . ']//ins', 1, 'The native discounted price must be retained.' );
if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_shop' ) && $sample->is_taxable() ) {
	$assert_count( $summary . '//*[' . $has_class( 'kogo-product__vat' ) . ']', 1, 'Tax-inclusive prices must show VAT included.' );
}
$assert_count( '//nav[' . $has_class( 'woocommerce-breadcrumb' ) . ']//a[@href="' . wc_get_page_permalink( 'shop' ) . '"]', 1, 'Breadcrumbs must include the Editions shop once.' );
foreach ( array( 'slider', 'zoom', 'lightbox' ) as $feature ) {
	if ( ! current_theme_supports( 'wc-product-gallery-' . $feature ) ) { throw new RuntimeException( 'The native gallery feature must be enabled: ' . $feature ); }
}
$assert_count( $product_path . '//*[' . $has_class( 'woocommerce-product-gallery__image' ) . ']', 1 + count( $sample->get_gallery_image_ids() ), 'Every product gallery image must be rendered for the native gallery.' );
$related = $product_path . '//section[' . $has_class( 'kogo-related-products' ) . ']';
$assert_count( $related, 1, 'Related products must use the shared slider section.' );
if ( ! $xpath->query( $related . '//ul[' . $has_class( 'swiper-wrapper' ) . ']/li' )->length ) { throw new RuntimeException( 'The sample product must have related slides.' ); }
$assert_count( $related . '//a[@data-product_id="' . $sample->get_id() . '"]', 0, 'Related products must exclude the current product.' );
foreach ( $xpath->query( $related . '//a[@data-product_id]' ) as $link ) {
	if ( ! wc_get_product( $link->getAttribute( 'data-product_id' ) ) ) { throw new RuntimeException( 'Related cart controls must target real products.' ); }
}

$before_product = $GLOBALS['product'] ?? null;
$before_post = $GLOBALS['post'] ?? null;
$before_query = $GLOBALS['wp_query'];
try {
	$GLOBALS['wp_query'] = new WP_Query( array( 'post_type' => 'product', 'p' => $sample->get_id() ) );
	$GLOBALS['product'] = clone $sample;
	$GLOBALS['product']->set_description( '<p>Full product description check.</p>' );
	$GLOBALS['product']->set_short_description( 'Short description fallback check.' );
	$GLOBALS['post'] = clone get_post( $sample->get_id() );
	$GLOBALS['post']->post_excerpt = 'Short description fallback check.';
	ob_start(); kogo_product_description(); $description = ob_get_clean();
	if ( false === strpos( $description, 'Full product description check.' ) || false !== strpos( $description, 'Short description fallback check.' ) ) {
		throw new RuntimeException( 'The full description must be displayed in the summary without repeating the excerpt.' );
	}
	$GLOBALS['product']->set_description( '' );
	ob_start(); kogo_product_description(); $description = ob_get_clean();
	if ( false === strpos( $description, 'Short description fallback check.' ) ) { throw new RuntimeException( 'The native excerpt must be used when the full description is empty.' ); }
	$variation = new WC_Product_Variation();
	$variation->set_parent_id( $sample->get_id() );
	$variation->set_regular_price( '15.50' );
	$variation->set_price( '15.50' );
	$variation->set_tax_status( 'taxable' );
	if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_shop' ) && false === strpos( $variation->get_price_html(), 'kogo-product__vat' ) ) {
		throw new RuntimeException( 'Selected variation prices must also show VAT included.' );
	}
} finally {
	$GLOBALS['product'] = $before_product;
	$GLOBALS['post'] = $before_post;
	$GLOBALS['wp_query'] = $before_query;
}
echo "Single product integration check passed.\n";
